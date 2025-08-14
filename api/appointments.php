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

function handleGetRequest($pdo) {
    $date = $_GET['date'] ?? null;
    $patient_id = $_GET['patient_id'] ?? null;
    
    try {
        if ($patient_id) {
            // Get appointments for specific patient
            $stmt = $pdo->prepare("
                SELECT a.*, 
                       CONCAT(p.first_name, ' ', p.father_name, ' ', p.last_name) as patient_name,
                       p.phone_number
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.patient_id = ?
                ORDER BY a.appointment_date DESC, a.appointment_date DESC
            ");
            $stmt->execute([$patient_id]);
        } elseif ($date) {
            // Get appointments for specific date
            $stmt = $pdo->prepare("
                SELECT a.*, 
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
                SELECT a.*, 
                       CONCAT(p.first_name, ' ', p.father_name, ' ', p.last_name) as patient_name,
                       p.phone_number
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.appointment_date >= CURDATE()
                ORDER BY a.appointment_date, a.appointment_date
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
    
    $required_fields = ['patient_id', 'appointment_date', 'appointment_date'];
    
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    try {
        // Check if appointment slot is available
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM appointments 
            WHERE appointment_date = ? AND appointment_date = ?
        ");
        $stmt->execute([$input['appointment_date'], $input['appointment_date']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($existing > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'هذا الموعد محجوز مسبقاً']);
            return;
        }
        
        // Insert new appointment
        $stmt = $pdo->prepare("
            INSERT INTO appointments (patient_id, appointment_date, appointment_date, notes, status, created_at)
            VALUES (?, ?, ?, ?, 'scheduled', NOW())
        ");
        
        $stmt->execute([
            $input['patient_id'],
            $input['appointment_date'],
            $input['appointment_date'],
            $input['notes'] ?? null
        ]);
        
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
        
        if (isset($input['appointment_date'])) {
            $fields[] = 'appointment_date = ?';
            $values[] = $input['appointment_date'];
        }
        
        if (isset($input['appointment_date'])) {
            $fields[] = 'appointment_date = ?';
            $values[] = $input['appointment_date'];
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
        
        // Check if new time slot is available (if time/date is being changed)
        if (isset($input['appointment_date']) || isset($input['appointment_date'])) {
            $check_date = $input['appointment_date'] ?? null;
            $check_time = $input['appointment_date'] ?? null;
            
            if ($check_date && $check_time) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM appointments 
                    WHERE appointment_date = ? AND appointment_date = ? AND id != ?
                ");
                $stmt->execute([$check_date, $check_time, $appointment_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($existing > 0) {
                    http_response_code(409);
                    echo json_encode(['error' => 'هذا الموعد محجوز مسبقاً']);
                    return;
                }
            }
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
