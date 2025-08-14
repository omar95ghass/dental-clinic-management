<?php
// api/patient_handler.php

// Include the existing database connection class
require_once 'db_connect.php';

class PatientHandler {
    private $conn;

    /**
     * PatientHandler Constructor.
     * Establishes a database connection using the Database class.
     */
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Adds a new patient to the database.
     * @param array $data An associative array containing patient data.
     * @return bool True on success, false on failure.
     */
    public function addPatient($data) {
        // SQL query to insert a new patient
        $sql = "INSERT INTO patients (first_name, father_name, last_name, date_of_birth, phone_number, address, visit_status) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->conn->prepare($sql);
            // Bind parameters to the statement
            $stmt->execute([
                $data['first_name'],
                $data['father_name'],
                $data['last_name'],
                $data['date_of_birth'],
                $data['phone_number'],
                $data['address'],
                $data['visit_status']
            ]);
            return true;
        } catch(PDOException $e) {
            // Log the error for debugging
            error_log("Error adding patient: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all patients from the database.
     * @return array An array of patient records.
     */
    public function getPatients() {
        // SQL query to select all patients
        $sql = "SELECT * FROM patients ORDER BY id DESC";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            // Fetch all results as an associative array
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching patients: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Searches for patients by name or phone number.
     * @param string $query The search query string.
     * @return array An array of matching patient records.
     */
    public function searchPatients($query) {
        $search_term = "%" . $query . "%";
        $sql = "SELECT * FROM patients WHERE first_name LIKE ? OR last_name LIKE ? OR phone_number LIKE ? ORDER BY id DESC";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$search_term, $search_term, $search_term]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error searching patients: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Updates an existing patient's information.
     * @param int $id The ID of the patient to update.
     * @param array $data An associative array containing the updated patient data.
     * @return bool True on success, false on failure.
     */
    public function updatePatient($id, $data) {
        $sql = "UPDATE patients SET first_name=?, father_name=?, last_name=?, date_of_birth=?, phone_number=?, address=?, visit_status=? WHERE id=?";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $data['first_name'],
                $data['father_name'],
                $data['last_name'],
                $data['date_of_birth'],
                $data['phone_number'],
                $data['address'],
                $data['visit_status'],
                $id
            ]);
            // Check if any rows were affected
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Error updating patient: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a patient from the database.
     * @param int $id The ID of the patient to delete.
     * @return bool True on success, false on failure.
     */
    public function deletePatient($id) {
        $sql = "DELETE FROM patients WHERE id=?";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);
            // Check if any rows were affected
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Error deleting patient: " . $e->getMessage());
            return false;
        }
    }
}
