<?php
// Appointments API - Handles appointment scheduling and management
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'db_connect.php';

$database = new Database();
$pdo = $database->connect();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to the database.']);
    exit();
}

// Ensure appointments table has required columns
ensureAppointmentsSchema($pdo);

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($pdo);
            break;
        case 'POST':
            handlePostRequest($pdo);
            break;
        case 'PUT':
            handlePutRequest($pdo);
            break;
        case 'DELETE':
            handleDeleteRequest($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function ensureAppointmentsSchema($pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'status'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE appointments ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'scheduled' AFTER notes");
        }
    } catch (PDOException $e) {
        // If can't alter, ignore to avoid breaking API, but queries below assume presence
    }
}

function handleGetRequest($pdo) {
    $date = $_GET['date'] ?? null; // expected format: YYYY-MM-DD
    $patient_id = $_GET['patient_id'] ?? null;
    $id = $_GET['id'] ?? null;

    try {
        if ($id) {
            $stmt = $pdo->prepare("
                SELECT a.id,
                       a.patient_id,
                       DATE(a.appointment_date) AS appointment_date,
                       TIME_FORMAT(a.appointment_date, '%H:%i:%s') AS appointment_time,
                       a.notes,
                       COALESCE(a.status, 'scheduled') AS status,
                       CONCAT(p.first_name, ' ', p.father_name, ' ', p.last_name) as patient_name,
                       p.phone_number
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($appointment ?: null);
            return;
        } elseif ($patient_id) {
            // Get appointments for specific patient
            $stmt = $pdo->prepare("
                SELECT a.id,
                       a.patient_id,
                       DATE(a.appointment_date) AS appointment_date,
                       TIME_FORMAT(a.appointment_date, '%H:%i:%s') AS appointment_time,
                       a.notes,
                       COALESCE(a.status, 'scheduled') AS status,
                       CONCAT(p.first_name, ' ', p.father_name, ' ', p.last_name) as patient_name,
                       p.phone_number
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.patient_id = ?
                ORDER BY a.appointment_date DESC
            ");
            $stmt->execute([$patient_id]);
        } elseif ($date) {
            // Get appointments for specific date
            $stmt = $pdo->prepare("
                SELECT a.id,
                       a.patient_id,
                       DATE(a.appointment_date) AS appointment_date,
                       TIME_FORMAT(a.appointment_date, '%H:%i:%s') AS appointment_time,
                       a.notes,
                       COALESCE(a.status, 'scheduled') AS status,
                       CONCAT(p.first_name, ' ', p.father_name, ' ', p.last_name) as patient_name,
                       p.phone_number
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE DATE(a.appointment_date) = ?
                ORDER BY a.appointment_date
            ");
            $stmt->execute([$date]);
        } else {
            // Get all upcoming appointments
            $stmt = $pdo->prepare("
                SELECT a.id,
                       a.patient_id,
                       DATE(a.appointment_date) AS appointment_date,
                       TIME_FORMAT(a.appointment_date, '%H:%i:%s') AS appointment_time,
                       a.notes,
                       COALESCE(a.status, 'scheduled') AS status,
                       CONCAT(p.first_name, ' ', p.father_name, ' ', p.last_name) as patient_name,
                       p.phone_number
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.appointment_date >= CURDATE()
                ORDER BY a.appointment_date
            ");
            $stmt->execute();
        }

        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($appointments);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePostRequest($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    $required_fields = ['patient_id', 'appointment_date', 'appointment_time'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }

    $patient_id = (int)$input['patient_id'];
    $date = $input['appointment_date']; // YYYY-MM-DD
    $time = $input['appointment_time']; // HH:MM:SS
    $notes = $input['notes'] ?? null;

    $datetime = "$date $time";

    try {
        // Check if appointment slot is available
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ?");
        $stmt->execute([$datetime]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($existing > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'هذا الموعد محجوز مسبقاً']);
            return;
        }

        // Insert new appointment
        $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, appointment_date, notes, status, created_at) VALUES (?, ?, ?, 'scheduled', NOW())");
        $stmt->execute([$patient_id, $datetime, $notes]);

        $appointment_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'تم حجز الموعد بنجاح',
            'appointment_id' => $appointment_id
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePutRequest($pdo) {
    $appointment_id = $_GET['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$appointment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Appointment ID is required']);
        return;
    }

    try {
        $fields = [];
        $values = [];

        $newDatetime = null;
        $hasDate = isset($input['appointment_date']) && !empty($input['appointment_date']);
        $hasTime = isset($input['appointment_time']) && !empty($input['appointment_time']);

        if ($hasDate || $hasTime) {
            // Fetch current datetime to merge with provided parts
            $stmt = $pdo->prepare("SELECT appointment_date FROM appointments WHERE id = ?");
            $stmt->execute([$appointment_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                http_response_code(404);
                echo json_encode(['error' => 'Appointment not found']);
                return;
            }
            $currentDate = date('Y-m-d', strtotime($current['appointment_date']));
            $currentTime = date('H:i:s', strtotime($current['appointment_date']));
            $datePart = $hasDate ? $input['appointment_date'] : $currentDate;
            $timePart = $hasTime ? $input['appointment_time'] : $currentTime;
            $newDatetime = "$datePart $timePart";

            // Check availability if datetime changed
            if ($newDatetime !== $current['appointment_date']) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND id != ?");
                $stmt->execute([$newDatetime, $appointment_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                if ($existing > 0) {
                    http_response_code(409);
                    echo json_encode(['error' => 'هذا الموعد محجوز مسبقاً']);
                    return;
                }
            }

            $fields[] = 'appointment_date = ?';
            $values[] = $newDatetime;
        }

        if (isset($input['notes'])) {
            $fields[] = 'notes = ?';
            $values[] = $input['notes'];
        }

        if (isset($input['status'])) {
            $fields[] = 'status = ?';
            $values[] = $input['status'];
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }

        $values[] = $appointment_id;

        $stmt = $pdo->prepare("UPDATE appointments SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($values);

        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث الموعد بنجاح'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDeleteRequest($pdo) {
    $appointment_id = $_GET['id'] ?? null;

    if (!$appointment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Appointment ID is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt->execute([$appointment_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'تم حذف الموعد بنجاح'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Appointment not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
