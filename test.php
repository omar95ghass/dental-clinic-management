<?php
// Setup script for dental clinic database
require_once './api/db_connect.php';

$database = new Database();
$pdo = $database->connect();

if (!$pdo) {
    die('Failed to connect to database');
}

try {
    // Add sample patients
    $samplePatients = [
        [
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'last_name' => 'علي',
            'date_of_birth' => '1990-05-15',
            'phone_number' => '0912345678',
            'address' => 'دمشق، شارع العابد',
            'visit_status' => 'first_time'
        ],
        [
            'first_name' => 'فاطمة',
            'father_name' => 'علي',
            'last_name' => 'حسن',
            'date_of_birth' => '1985-12-20',
            'phone_number' => '0923456789',
            'address' => 'حلب، شارع الجمهورية',
            'visit_status' => 'review'
        ],
        [
            'first_name' => 'محمد',
            'father_name' => 'أحمد',
            'last_name' => 'خالد',
            'date_of_birth' => '1995-08-10',
            'phone_number' => '0934567890',
            'address' => 'حمص، شارع القوتلي',
            'visit_status' => 'first_time'
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO patients (first_name, father_name, last_name, date_of_birth, phone_number, address, visit_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($samplePatients as $patient) {
        $stmt->execute([
            $patient['first_name'],
            $patient['father_name'],
            $patient['last_name'],
            $patient['date_of_birth'],
            $patient['phone_number'],
            $patient['address'],
            $patient['visit_status']
        ]);
    }

    // Add sample users
    $sampleUsers = [
        ['username' => 'doctor1', 'password' => password_hash('123456', PASSWORD_DEFAULT), 'role' => 'doctor'],
        ['username' => 'receptionist1', 'password' => password_hash('123456', PASSWORD_DEFAULT), 'role' => 'receptionist']
    ];

    $userStmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    foreach ($sampleUsers as $user) {
        $userStmt->execute([$user['username'], $user['password'], $user['role']]);
    }

    // Add sample treatment types
    $treatmentTypes = [
        ['name' => 'لبية', 'default_cost' => 50000],
        ['name' => 'محافظة', 'default_cost' => 30000],
        ['name' => 'تعويض ثابت', 'default_cost' => 150000],
        ['name' => 'تعويض متحرك', 'default_cost' => 80000],
        ['name' => 'تقويم', 'default_cost' => 200000]
    ];

    $treatmentStmt = $pdo->prepare("INSERT INTO treatment_types (name, default_cost) VALUES (?, ?)");
    foreach ($treatmentTypes as $treatment) {
        $treatmentStmt->execute([$treatment['name'], $treatment['default_cost']]);
    }

    // Add sample drugs
    $drugs = [
        ['name' => 'أموكسيسيلين 500mg', 'dosage_options' => json_encode(['bid', 'tid'])],
        ['name' => 'إيبوبروفين 400mg', 'dosage_options' => json_encode(['bid', 'tid', 'qid'])],
        ['name' => 'باراسيتامول 500mg', 'dosage_options' => json_encode(['bid', 'tid', 'qid'])],
        ['name' => 'كلورهيكسيدين غسول', 'dosage_options' => json_encode(['bid', 'tid'])]
    ];

    $drugStmt = $pdo->prepare("INSERT INTO drugs (name, dosage_options) VALUES (?, ?)");
    foreach ($drugs as $drug) {
        $drugStmt->execute([$drug['name'], $drug['dosage_options']]);
    }

    echo "Database setup completed successfully!\n";
    echo "Sample data added:\n";
    echo "- 3 patients\n";
    echo "- 2 users (doctor1, receptionist1)\n";
    echo "- 5 treatment types\n";
    echo "- 4 drugs\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
