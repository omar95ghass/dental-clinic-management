<?php
//
// Treatment Handler - API for managing detailed treatment steps and working lengths
//
// This file handles all treatment-related operations including:
// - Getting treatment types and their steps
// - Adding treatments with detailed steps
// - Managing working lengths for canal treatments
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

class TreatmentHandler {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        // تأكد من أن الاتصال يستخدم UTF-8
        $this->db->exec("set names utf8");
    }
    
    // Get all treatment types
    public function getTreatmentTypes() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM treatment_types ORDER BY name");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting treatment types: " . $e->getMessage());
            return [];
        }
    }
    
    // Get treatment steps for a specific treatment type
    public function getTreatmentSteps($treatment_type_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM treatment_steps 
                WHERE treatment_type_id = ?
                ORDER BY step_order
            ");
            $stmt->execute([$treatment_type_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting treatment steps: " . $e->getMessage());
            return [];
        }
    }

    // Get all canal types
    public function getCanalTypes() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM canal_types ORDER BY name");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting canal types: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Creates a new session and adds all associated treatments and steps.
     *
     * @param int $patient_id
     * @param int $doctor_id
     * @param array $treatments An array of treatment objects.
     * @return int|bool The ID of the new session or false on failure.
     */
    public function addSession($patient_id, $doctor_id, $treatments) {
        try {
            $this->db->beginTransaction();

            // 1. Insert into sessions table
            $stmt_session = $this->db->prepare("
                INSERT INTO sessions (patient_id, doctor_id, session_notes) 
                VALUES (?, ?, ?)
            ");
            // يمكنك إضافة ملاحظات الجلسة هنا إذا كانت متوفرة من الواجهة الأمامية
            $session_notes = ''; 
            $stmt_session->execute([$patient_id, $doctor_id, $session_notes]);
            $session_id = $this->db->lastInsertId();

            // 2. Loop through each treatment and insert it
            foreach ($treatments as $treatment) {
                // Insert into treatments table
                $stmt_treatment = $this->db->prepare("
                    INSERT INTO treatments (session_id, tooth_number, treatment_type_id, cost, additional_cost, discount, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_treatment->execute([
                    $session_id,
                    $treatment['tooth_number'],
                    $treatment['treatment_type_id'],
                    $treatment['cost'],
                    $treatment['additional_cost'],
                    $treatment['discount'],
                    $treatment['notes']
                ]);
                $treatment_id = $this->db->lastInsertId();

                // 3. Loop through each step for the current treatment and insert it
                if (!empty($treatment['steps_data'])) {
                    foreach ($treatment['steps_data'] as $step) {
                        // Insert into treatment_details table
                        $stmt_details = $this->db->prepare("
                            INSERT INTO treatment_details (treatment_id, treatment_step_id, step_notes)
                            VALUES (?, ?, ?)
                        ");
                        $step_notes = $step['notes'] ?? ''; // يمكنك إضافة ملاحظات الخطوة هنا
                        $stmt_details->execute([$treatment_id, $step['step_id'], $step_notes]);
                        $treatment_detail_id = $this->db->lastInsertId();

                        // 4. Check if working length exists and insert it
                        if (isset($step['working_length']) && !empty($step['working_length'])) {
                            $stmt_wl = $this->db->prepare("
                                INSERT INTO working_lengths (treatment_detail_id, canal_type_id, length)
                                VALUES (?, ?, ?)
                            ");
                            // يجب أن يتم تمرير canal_type_id مع البيانات من الواجهة الأمامية
                            $canal_type_id = $step['canal_type_id'] ?? null; 
                            if ($canal_type_id) {
                                $stmt_wl->execute([$treatment_detail_id, $canal_type_id, $step['working_length']]);
                            }
                        }
                    }
                }
            }

            $this->db->commit();
            return $session_id;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error adding session and treatments: " . $e->getMessage());
            return false;
        }
    }
}

// API endpoint handler
$handler = new TreatmentHandler();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    $result = null;

    switch ($action) {
        case 'get_treatment_types':
            $result = $handler->getTreatmentTypes();
            break;
        case 'get_treatment_steps':
            $treatment_type_id = $_GET['treatment_type_id'] ?? null;
            if ($treatment_type_id) {
                $result = $handler->getTreatmentSteps($treatment_type_id);
            } else {
                http_response_code(400);
                $result = ['error' => 'Missing treatment_type_id parameter'];
            }
            break;
        case 'get_canal_types':
            $result = $handler->getCanalTypes();
            break;
        default:
            http_response_code(400);
            $result = ['error' => 'Invalid action'];
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($result);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'add_session':
                // التحقق من وجود المتغيرات المطلوبة
                if (isset($input['patient_id']) && isset($input['doctor_id']) && isset($input['treatments'])) {
                    $result = $handler->addSession(
                        $input['patient_id'],
                        $input['doctor_id'],
                        $input['treatments']
                    );
                    
                    if ($result) {
                        $response = ['success' => true, 'session_id' => $result];
                    } else {
                        http_response_code(500);
                        $response = ['success' => false, 'message' => 'Failed to add session'];
                    }
                } else {
                    http_response_code(400);
                    $response = ['success' => false, 'message' => 'Missing required parameters'];
                }
                break;
            
            default:
                http_response_code(400);
                $response = ['success' => false, 'message' => 'Invalid action'];
                break;
        }
    } else {
        http_response_code(400);
        $response = ['success' => false, 'message' => 'Missing action parameter'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>
