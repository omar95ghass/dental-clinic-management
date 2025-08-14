<?php
// api/patients.php

// Set headers to allow cross-origin requests and handle JSON data
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include the PatientHandler class
require_once 'patient_handler.php';

// Instantiate the handler
$patientHandler = new PatientHandler();

// Get the request method
$request_method = $_SERVER["REQUEST_METHOD"];
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

switch ($request_method) {
    case 'GET':
        // Handle GET request to fetch patients
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'search':
                    if (isset($_GET['term'])) {
                        $patients = $patientHandler->searchPatients($_GET['term']);
                        http_response_code(200);
                        echo json_encode($patients ?: []);
                    } else {
                        http_response_code(400);
                        echo json_encode(["message" => "Missing search term."]);
                    }
                    break;
                case 'get_drugs':
                    // This would need to be implemented in PatientHandler
                    $drugs = [
                        ['id' => 1, 'name' => 'أموكسيسيلين 500mg', 'dosage_options' => '["bid", "tid"]'],
                        ['id' => 2, 'name' => 'إيبوبروفين 400mg', 'dosage_options' => '["bid", "tid", "qid"]'],
                        ['id' => 3, 'name' => 'باراسيتامول 500mg', 'dosage_options' => '["bid", "tid", "qid"]']
                    ];
                    http_response_code(200);
                    echo json_encode($drugs);
                    break;
                default:
                    if (isset($_GET['search_query']) && !empty($_GET['search_query'])) {
                        $patients = $patientHandler->searchPatients($_GET['search_query']);
                    } else {
                        $patients = $patientHandler->getPatients();
                    }
                    http_response_code(200);
                    echo json_encode($patients);
            }
        } else if (isset($_GET['search_query']) && !empty($_GET['search_query'])) {
            $patients = $patientHandler->searchPatients($_GET['search_query']);
            http_response_code(200);
            echo json_encode($patients);
        } else {
            $patients = $patientHandler->getPatients();
            http_response_code(200);
            echo json_encode($patients);
        }
        break;

    case 'POST':
        // Handle POST request to add a new patient
        $data = json_decode(file_get_contents("php://input"), true);
        
        if ($data && !empty($data['first_name']) && !empty($data['phone_number'])) {
            $success = $patientHandler->addPatient($data);
            if ($success) {
                http_response_code(201); // Created
                echo json_encode(["message" => "Patient added successfully."]);
            } else {
                http_response_code(500); // Internal Server Error
                echo json_encode(["message" => "Unable to add patient. Check server logs."]);
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(["message" => "Invalid patient data provided."]);
        }
        break;

    case 'PUT':
        // Handle PUT request to update an existing patient
        if ($patient_id > 0) {
            $data = json_decode(file_get_contents("php://input"), true);
            if ($data && !empty($data['first_name']) && !empty($data['phone_number'])) {
                $success = $patientHandler->updatePatient($patient_id, $data);
                if ($success) {
                    http_response_code(200); // OK
                    echo json_encode(["message" => "Patient updated successfully."]);
                } else {
                    http_response_code(404); // Not Found
                    echo json_encode(["message" => "Patient not found or no changes were made."]);
                }
            } else {
                http_response_code(400); // Bad Request
                echo json_encode(["message" => "Invalid patient data provided."]);
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(["message" => "Patient ID is missing."]);
        }
        break;
    
    case 'DELETE':
        // Handle DELETE request to delete a patient
        if ($patient_id > 0) {
            $success = $patientHandler->deletePatient($patient_id);
            if ($success) {
                http_response_code(200); // OK
                echo json_encode(["message" => "Patient deleted successfully."]);
            } else {
                http_response_code(404); // Not Found
                echo json_encode(["message" => "Patient not found."]);
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(["message" => "Patient ID is missing."]);
        }
        break;

    default:
        // Handle other request methods
        http_response_code(405); // Method Not Allowed
        echo json_encode(["message" => "Method not allowed."]);
        break;
}
?>
