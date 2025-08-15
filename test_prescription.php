<?php
// Test script for prescription functionality
require_once 'api/db_connect.php';

$database = new Database();
$pdo = $database->connect();

if (!$pdo) {
    echo "Failed to connect to database\n";
    exit(1);
}

echo "Testing prescription functionality...\n";

// Test 1: Check if prescription tables exist
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'prescriptions'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Prescriptions table exists\n";
    } else {
        echo "✗ Prescriptions table does not exist\n";
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'prescription_medicines'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Prescription_medicines table exists\n";
    } else {
        echo "✗ Prescription_medicines table does not exist\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking tables: " . $e->getMessage() . "\n";
}

// Test 2: Check if clinic_info table has logo and signature fields
try {
    $stmt = $pdo->query("DESCRIBE clinic_info");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('logo_url', $columns)) {
        echo "✓ logo_url column exists in clinic_info\n";
    } else {
        echo "✗ logo_url column does not exist in clinic_info\n";
    }
    
    if (in_array('doctor_signature_url', $columns)) {
        echo "✓ doctor_signature_url column exists in clinic_info\n";
    } else {
        echo "✗ doctor_signature_url column does not exist in clinic_info\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking clinic_info columns: " . $e->getMessage() . "\n";
}

// Test 3: Check if uploads directory exists
if (is_dir('uploads')) {
    echo "✓ Uploads directory exists\n";
} else {
    echo "✗ Uploads directory does not exist\n";
}

// Test 4: Test prescription API endpoint
echo "\nTesting prescription API...\n";
$testData = [
    'patient_id' => 1,
    'prescription_date' => date('Y-m-d'),
    'general_notes' => 'Test prescription',
    'medicines' => [
        [
            'name' => 'Test Medicine',
            'dosage' => '500mg twice daily',
            'type' => 'tablet',
            'duration' => '7 days',
            'notes' => 'Test notes'
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/prescription.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "✓ Prescription API is accessible\n";
} else {
    echo "✗ Prescription API returned HTTP code: $httpCode\n";
}

echo "\nTest completed!\n";
?>