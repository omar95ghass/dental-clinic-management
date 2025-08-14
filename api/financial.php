<?php
//
// Financial API - Handles payments, billing, and financial operations
//
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'db_connect.php';

$database = new Database();
$pdo = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Ensure required tables exist
ensureFinancialTables($pdo);

// Main request handler
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

// Create tables if they do not exist
function ensureFinancialTables($pdo) {
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                payment_method VARCHAR(50) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_payments_patient (patient_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                total_amount DECIMAL(12,2) NOT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_invoices_patient (patient_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (PDOException $e) {
        // If creation fails, surface error
        http_response_code(500);
        echo json_encode(['error' => 'Failed to ensure financial tables: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET requests
function handleGetRequest($pdo, $action) {
    $patient_id = $_GET['patient_id'] ?? null;
    
    // Validate patient_id for actions that require it
    if (in_array($action, ['get_patient_balance', 'get_patient_payments', 'get_patient_invoices']) && !$patient_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Patient ID is required']);
        return;
    }

    switch ($action) {
        case 'get_patient_balance':
            getPatientBalance($pdo, $patient_id);
            break;
        case 'get_patient_payments':
            getPatientPayments($pdo, $patient_id);
            break;
        case 'get_patient_invoices':
            getPatientInvoices($pdo, $patient_id);
            break;
        // Other GET actions can be added here
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

// Handle POST requests
function handlePostRequest($pdo, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'add_payment':
            addPayment($pdo, $input);
            break;
        case 'generate_invoice':
            generateInvoice($pdo, $input);
            break;
        // Other POST actions can be added here
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

// Handle PUT requests
function handlePutRequest($pdo, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update_payment':
            updatePayment($pdo, $input);
            break;
        // Other PUT actions can be added here
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

// Handle DELETE requests
function handleDeleteRequest($pdo, $action) {
    switch ($action) {
        case 'delete_payment':
            deletePayment($pdo);
            break;
        // Other DELETE actions can be added here
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

// Get patient's current balance (total owed - total paid)
function getPatientBalance($pdo, $patient_id) {
    try {
        // Calculate total payments made by the patient
        $stmt_payments = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE patient_id = ?");
        $stmt_payments->execute([$patient_id]);
        $total_payments = (float)$stmt_payments->fetchColumn();

        // Calculate total amount from all treatments for this patient (join sessions)
        $stmt_treatments = $pdo->prepare(
            "SELECT COALESCE(SUM((t.cost + COALESCE(t.additional_cost,0)) - ((t.cost + COALESCE(t.additional_cost,0)) * (COALESCE(t.discount,0)/100))), 0)
             FROM treatments t
             JOIN sessions s ON t.session_id = s.id
             WHERE s.patient_id = ?"
        );
        $stmt_treatments->execute([$patient_id]);
        $total_cost = (float)$stmt_treatments->fetchColumn();

        $balance = $total_cost - $total_payments;
        
        echo json_encode(['balance' => $balance]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get all payments for a specific patient
function getPatientPayments($pdo, $patient_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE patient_id = ? ORDER BY created_at DESC");
        $stmt->execute([$patient_id]);
        $payments = $stmt->fetchAll();
        echo json_encode($payments);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get all invoices for a specific patient
function getPatientInvoices($pdo, $patient_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE patient_id = ? ORDER BY created_at DESC");
        $stmt->execute([$patient_id]);
        $invoices = $stmt->fetchAll();
        echo json_encode($invoices);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Add a new payment
function addPayment($pdo, $input) {
    $required_fields = ['patient_id', 'amount'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Required field '$field' is missing"]);
            return;
        }
    }
    
    $patient_id = $input['patient_id'];
    $amount = $input['amount'];
    $notes = $input['notes'] ?? null;
    $payment_method = $input['payment_method'] ?? null;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO payments (patient_id, amount, payment_method, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$patient_id, $amount, $payment_method, $notes]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment added successfully',
            'payment_id' => $pdo->lastInsertId()
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Generate an invoice
function generateInvoice($pdo, $input) {
    $required_fields = ['patient_id', 'total_amount'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Required field '$field' is missing"]);
            return;
        }
    }

    $patient_id = $input['patient_id'];
    $total_amount = $input['total_amount'];
    $notes = $input['notes'] ?? null;

    try {
        $stmt = $pdo->prepare("INSERT INTO invoices (patient_id, total_amount, notes) VALUES (?, ?, ?)");
        $stmt->execute([$patient_id, $total_amount, $notes]);

        echo json_encode([
            'success' => true,
            'message' => 'Invoice generated successfully',
            'invoice_id' => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Update an existing payment
function updatePayment($pdo, $input) {
    $payment_id = $input['id'] ?? null;
    
    if (!$payment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment ID is required']);
        return;
    }
    
    $fields = [];
    $values = [];
    
    if (isset($input['amount'])) {
        $fields[] = "amount = ?";
        $values[] = $input['amount'];
    }
    if (isset($input['notes'])) {
        $fields[] = "notes = ?";
        $values[] = $input['notes'];
    }
    if (isset($input['payment_method'])) {
        $fields[] = "payment_method = ?";
        $values[] = $input['payment_method'];
    }
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        return;
    }
    
    $values[] = $payment_id;
    
    try {
        $stmt = $pdo->prepare("UPDATE payments SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($values);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Delete a payment
function deletePayment($pdo) {
    $payment_id = $_GET['id'] ?? null;
    
    if (!$payment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Payment deleted successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Payment not found']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
