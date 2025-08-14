<?php
//
// Database Connection Configuration
//
// This class provides a reusable and secure way to connect to the MySQL database.
//
class Database {
    private $host;
    private $db_name;
    private $user;
    private $pass;
    private $pdo;

    public function __construct() {
        // Load database configuration from a config file if needed
        // For now, we'll hardcode the details
        $this->host = 'localhost';
        $this->db_name = 'dental_clinic';
        $this->user = 'root';
        $this->pass = '';
    }

    public function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            return $this->pdo;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die('❌ Sorry, we are experiencing some technical difficulties. Please try again later.');
        }
    }
}
?>
