<?php
//
// API - Login Handler
//
// This file handles the user authentication process.
// It receives username and password, validates them against the database,
// and returns a JSON response indicating success or failure.
//
header('Content-Type: application/json');
session_start();

// Include the database class file
require_once 'db_connect.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// Sanitize inputs
$username = trim($username);

// Validate inputs
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit();
}

try {
    // Connect to the database using the new class
    $database = new Database();
    $pdo = $database->connect();

    // Prepare a query to fetch the user by username
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if the user exists and the password is correct
    if ($user && password_verify($password, $user['password'])) {
        // Authentication successful
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'redirect_to' => ($user['role'] === 'doctor') ? './public/doctor_dashboard.html' : './public/receptionist_dashboard.html'
        ]);
    } else {
        // Authentication failed
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    }

} catch (PDOException $e) {
    // Database connection or query error
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
