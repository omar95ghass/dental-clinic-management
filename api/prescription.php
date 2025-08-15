<?php
// Prescription API - Handles prescription creation and management
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// استيراد ملف الاتصال
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
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_prescriptions':
            getPrescriptions($pdo);
            break;
        case 'get_prescription':
            getPrescription($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePostRequest($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }
    
    createPrescription($pdo, $input);
}

function handlePutRequest($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }
    
    updatePrescription($pdo, $input);
}

function handleDeleteRequest($pdo) {
    $prescriptionId = $_GET['id'] ?? null;
    
    if (!$prescriptionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Prescription ID is required']);
        return;
    }
    
    deletePrescription($pdo, $prescriptionId);
}

function createPrescription($pdo, $data) {
    try {
        $pdo->beginTransaction();
        
        // Insert prescription header
        $stmt = $pdo->prepare("
            INSERT INTO prescriptions (
                patient_id, 
                prescription_date, 
                general_notes, 
                created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['patient_id'],
            $data['prescription_date'],
            $data['general_notes'] ?? ''
        ]);
        
        $prescriptionId = $pdo->lastInsertId();
        
        // Insert medicines
        if (!empty($data['medicines'])) {
            $medicineStmt = $pdo->prepare("
                INSERT INTO prescription_medicines (
                    prescription_id,
                    medicine_name,
                    dosage,
                    medicine_type,
                    duration,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($data['medicines'] as $medicine) {
                $medicineStmt->execute([
                    $prescriptionId,
                    $medicine['name'],
                    $medicine['dosage'],
                    $medicine['type'],
                    $medicine['duration'],
                    $medicine['notes'] ?? ''
                ]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Prescription created successfully',
            'prescription_id' => $prescriptionId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create prescription: ' . $e->getMessage()]);
    }
}

function getPrescriptions($pdo) {
    $patientId = $_GET['patient_id'] ?? null;
    
    try {
        $sql = "
            SELECT 
                p.id,
                p.patient_id,
                p.prescription_date,
                p.general_notes,
                p.created_at,
                CONCAT(pat.first_name, ' ', pat.father_name, ' ', pat.last_name) as patient_name
            FROM prescriptions p
            JOIN patients pat ON p.patient_id = pat.id
        ";
        
        $params = [];
        
        if ($patientId) {
            $sql .= " WHERE p.patient_id = ?";
            $params[] = $patientId;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get medicines for each prescription
        foreach ($prescriptions as &$prescription) {
            $medicineStmt = $pdo->prepare("
                SELECT * FROM prescription_medicines 
                WHERE prescription_id = ? 
                ORDER BY id
            ");
            $medicineStmt->execute([$prescription['id']]);
            $prescription['medicines'] = $medicineStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'success' => true,
            'prescriptions' => $prescriptions
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get prescriptions: ' . $e->getMessage()]);
    }
}

function getPrescription($pdo) {
    $prescriptionId = $_GET['id'] ?? null;
    
    if (!$prescriptionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Prescription ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                CONCAT(pat.first_name, ' ', pat.father_name, ' ', pat.last_name) as patient_name,
                pat.phone_number,
                pat.address,
                pat.date_of_birth
            FROM prescriptions p
            JOIN patients pat ON p.patient_id = pat.id
            WHERE p.id = ?
        ");
        
        $stmt->execute([$prescriptionId]);
        $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prescription) {
            http_response_code(404);
            echo json_encode(['error' => 'Prescription not found']);
            return;
        }
        
        // Get medicines
        $medicineStmt = $pdo->prepare("
            SELECT * FROM prescription_medicines 
            WHERE prescription_id = ? 
            ORDER BY id
        ");
        $medicineStmt->execute([$prescriptionId]);
        $prescription['medicines'] = $medicineStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'prescription' => $prescription
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get prescription: ' . $e->getMessage()]);
    }
}

function updatePrescription($pdo, $data) {
    $prescriptionId = $data['id'] ?? null;
    
    if (!$prescriptionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Prescription ID is required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update prescription header
        $stmt = $pdo->prepare("
            UPDATE prescriptions 
            SET prescription_date = ?, general_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['prescription_date'],
            $data['general_notes'] ?? '',
            $prescriptionId
        ]);
        
        // Delete existing medicines
        $deleteStmt = $pdo->prepare("DELETE FROM prescription_medicines WHERE prescription_id = ?");
        $deleteStmt->execute([$prescriptionId]);
        
        // Insert updated medicines
        if (!empty($data['medicines'])) {
            $medicineStmt = $pdo->prepare("
                INSERT INTO prescription_medicines (
                    prescription_id,
                    medicine_name,
                    dosage,
                    medicine_type,
                    duration,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($data['medicines'] as $medicine) {
                $medicineStmt->execute([
                    $prescriptionId,
                    $medicine['name'],
                    $medicine['dosage'],
                    $medicine['type'],
                    $medicine['duration'],
                    $medicine['notes'] ?? ''
                ]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Prescription updated successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update prescription: ' . $e->getMessage()]);
    }
}

function deletePrescription($pdo, $prescriptionId) {
    try {
        $pdo->beginTransaction();
        
        // Delete medicines first
        $deleteMedicinesStmt = $pdo->prepare("DELETE FROM prescription_medicines WHERE prescription_id = ?");
        $deleteMedicinesStmt->execute([$prescriptionId]);
        
        // Delete prescription
        $deletePrescriptionStmt = $pdo->prepare("DELETE FROM prescriptions WHERE id = ?");
        $deletePrescriptionStmt->execute([$prescriptionId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Prescription deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete prescription: ' . $e->getMessage()]);
    }
}
?>