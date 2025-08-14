<?php
// api/appointment_handler.php

// Include the existing database connection class
require_once 'db_connect.php';

class AppointmentHandler {
    private $conn;

    /**
     * AppointmentHandler Constructor.
     * Establishes a database connection using the Database class.
     */
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Adds a new appointment to the database.
     * @param array $data An associative array containing appointment data.
     * @return bool True on success, false on failure.
     */
    public function addAppointment($data) {
        $sql = "INSERT INTO appointments (patient_id, appointment_date, appointment_time, notes) VALUES (?, ?, ?, ?)";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $data['patient_id'],
                $data['appointment_date'],
                $data['appointment_time'],
                $data['notes']
            ]);
            return true;
        } catch(PDOException $e) {
            error_log("Error adding appointment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches appointments from the database, optionally filtered by date.
     * @param string|null $date The specific date to filter by (e.g., 'YYYY-MM-DD').
     * @return array An array of appointment records.
     */
    public function getAppointments($date = null) {
        $sql = "SELECT a.*, p.first_name, p.father_name, p.last_name, p.phone_number 
                FROM appointments a 
                JOIN patients p ON a.patient_id = p.id";
        
        if ($date) {
            $sql .= " WHERE a.appointment_date = ?";
        }
        
        $sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            if ($date) {
                $stmt->execute([$date]);
            } else {
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching appointments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Updates an existing appointment's information.
     * @param int $id The ID of the appointment to update.
     * @param array $data An associative array containing the updated appointment data.
     * @return bool True on success, false on failure.
     */
    public function updateAppointment($id, $data) {
        $sql = "UPDATE appointments SET patient_id=?, appointment_date=?, appointment_time=?, notes=? WHERE id=?";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $data['patient_id'],
                $data['appointment_date'],
                $data['appointment_time'],
                $data['notes'],
                $id
            ]);
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Error updating appointment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes an appointment from the database.
     * @param int $id The ID of the appointment to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteAppointment($id) {
        $sql = "DELETE FROM appointments WHERE id=?";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Error deleting appointment: " . $e->getMessage());
            return false;
        }
    }
}
