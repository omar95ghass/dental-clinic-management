<?php
// Sessions API - Handles dental sessions and treatments
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
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($pdo, $action);
            break;
        case 'POST':
            handlePostRequest($pdo, $action);
            break;
        case 'PUT':
            handlePutRequest($pdo, $action);
            break;
        case 'DELETE':
            handleDeleteRequest($pdo, $action);
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

function handleGetRequest($pdo, $action) {
    $patient_id = $_GET['patient_id'] ?? null;
    $session_id = $_GET['session_id'] ?? null;
    
    switch ($action) {
        case 'get_patient_sessions':
            if (!$patient_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Patient ID is required']);
                return;
            }
            getPatientSessions($pdo, $patient_id);
            break;
            
        case 'get_session_details':
            if (!$session_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Session ID is required']);
                return;
            }
            getSessionDetails($pdo, $session_id);
            break;
            
        case 'get_session_treatments':
            if (!$session_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Session ID is required']);
                return;
            }
            getSessionTreatments($pdo, $session_id);
            break;
            
        case 'get_session_prescriptions':
            if (!$session_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Session ID is required']);
                return;
            }
            getSessionPrescriptions($pdo, $session_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePostRequest($pdo, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create_session':
            createSession($pdo, $input);
            break;
            
        case 'add_treatment':
            addTreatment($pdo, $input);
            break;
            
        case 'add_prescription':
            addPrescription($pdo, $input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePutRequest($pdo, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update_session':
            updateSession($pdo, $input);
            break;
            
        case 'update_treatment':
            updateTreatment($pdo, $input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleDeleteRequest($pdo, $action) {
    $session_id = $_GET['session_id'] ?? null;
    $treatment_id = $_GET['treatment_id'] ?? null;
    
    switch ($action) {
        case 'delete_session':
            if (!$session_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Session ID is required']);
                return;
            }
            deleteSession($pdo, $session_id);
            break;
            
        case 'delete_treatment':
            if (!$treatment_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Treatment ID is required']);
                return;
            }
            deleteTreatment($pdo, $treatment_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

// Get all sessions for a specific patient
function getPatientSessions($pdo, $patient_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   CONCAT(u.username, ' (', u.role, ')') as doctor_name
            FROM sessions s
            LEFT JOIN users u ON s.doctor_id = u.id
            WHERE s.patient_id = ?
            ORDER BY s.session_date DESC
        ");
        $stmt->execute([$patient_id]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($sessions);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get detailed information about a specific session
function getSessionDetails($pdo, $session_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   CONCAT(p.first_name, ' ', p.father_name, ' ', p.last_name) as patient_name,
                   p.phone_number,
                   CONCAT(u.username, ' (', u.role, ')') as doctor_name
            FROM sessions s
            JOIN patients p ON s.patient_id = p.id
            LEFT JOIN users u ON s.doctor_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
            return;
        }
        
        echo json_encode($session);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get all treatments for a specific session
function getSessionTreatments($pdo, $session_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, tt.name as treatment_type_name, tt.default_cost
            FROM treatments t
            JOIN treatment_types tt ON t.treatment_type_id = tt.id
            WHERE t.session_id = ?
            ORDER BY t.tooth_number
        ");
        $stmt->execute([$session_id]);
        $treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($treatments);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get all prescriptions for a specific session
function getSessionPrescriptions($pdo, $session_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, d.name as drug_name, d.dosage_options
            FROM prescriptions p
            JOIN drugs d ON p.drug_id = d.id
            WHERE p.session_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$session_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($prescriptions);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Create a new session
function createSession($pdo, $data) {
    if (!isset($data['patient_id']) || !isset($data['doctor_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Patient ID and Doctor ID are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sessions (patient_id, doctor_id, session_date, session_notes)
            VALUES (?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $data['patient_id'],
            $data['doctor_id'],
            $data['session_notes'] ?? null
        ]);
        
        $session_id = $pdo->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'message' => 'Session created successfully',
            'session_id' => $session_id
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Add a treatment to a session
function addTreatment($pdo, $data) {
    if (!isset($data['session_id']) || !isset($data['tooth_number']) || !isset($data['treatment_type_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID, Tooth Number, and Treatment Type ID are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO treatments (session_id, tooth_number, treatment_type_id, cost, additional_cost, discount, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['session_id'],
            $data['tooth_number'],
            $data['treatment_type_id'],
            $data['cost'] ?? 0,
            $data['additional_cost'] ?? 0,
            $data['discount'] ?? 0,
            $data['notes'] ?? null
        ]);
        
        $treatment_id = $pdo->lastInsertId();
        
        // Add treatment details if provided
        if (isset($data['treatment_details']) && is_array($data['treatment_details'])) {
            foreach ($data['treatment_details'] as $detail) {
                $detailStmt = $pdo->prepare("
                    INSERT INTO treatment_details (treatment_id, treatment_step_id, working_length_details)
                    VALUES (?, ?, ?)
                ");
                $detailStmt->execute([
                    $treatment_id,
                    $detail['treatment_step_id'],
                    isset($detail['working_length_details']) ? json_encode($detail['working_length_details']) : null
                ]);
            }
        }
        
        http_response_code(201);
        echo json_encode([
            'message' => 'Treatment added successfully',
            'treatment_id' => $treatment_id
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Add a prescription to a session
function addPrescription($pdo, $data) {
    if (!isset($data['session_id']) || !isset($data['drug_id']) || !isset($data['dosage'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID, Drug ID, and Dosage are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO prescriptions (session_id, drug_id, dosage, is_printed)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['session_id'],
            $data['drug_id'],
            $data['dosage'],
            $data['is_printed'] ?? false
        ]);
        
        $prescription_id = $pdo->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'message' => 'Prescription added successfully',
            'prescription_id' => $prescription_id
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Update session information
function updateSession($pdo, $data) {
    if (!isset($data['session_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE sessions 
            SET session_notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['session_notes'] ?? null,
            $data['session_id']
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Session updated successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Update treatment information
function updateTreatment($pdo, $data) {
    if (!isset($data['treatment_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Treatment ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE treatments 
            SET cost = ?, additional_cost = ?, discount = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['cost'] ?? 0,
            $data['additional_cost'] ?? 0,
            $data['discount'] ?? 0,
            $data['notes'] ?? null,
            $data['treatment_id']
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Treatment updated successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Treatment not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Delete a session
function deleteSession($pdo, $session_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$session_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Session deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Delete a treatment
function deleteTreatment($pdo, $treatment_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM treatments WHERE id = ?");
        $stmt->execute([$treatment_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Treatment deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Treatment not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
