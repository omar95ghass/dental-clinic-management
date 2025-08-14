<?php
// Settings API - Handles system configuration and management
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
$action = $_GET['action'] ?? '';

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

function handleGetRequest($pdo, $action) {
  switch ($action) {
    case 'get_users':
      getUsers($pdo);
      break;
    case 'get_treatment_types':
      getTreatmentTypes($pdo);
      break;
    case 'get_drugs':
      getDrugs($pdo);
      break;
    case 'get_clinic_info':
      getClinicInfo($pdo);
      break;
    case 'get_system_settings':
      getSystemSettings($pdo);
      break;
    default:
      http_response_code(400);
      echo json_encode(['error' => 'Invalid action']);
      break;
  }
}

function handlePostRequest($pdo, $action) {
  $input = json_decode(file_get_contents('php://input'), true);
  
  switch ($action) {
    case 'add_user':
      addUser($pdo, $input);
      break;
    case 'add_treatment_type':
      addTreatmentType($pdo, $input);
      break;
    case 'add_drug':
      addDrug($pdo, $input);
      break;
    case 'update_clinic_info':
      updateClinicInfo($pdo, $input);
      break;
    case 'update_system_settings':
      updateSystemSettings($pdo, $input);
      break;
    default:
      http_response_code(400);
      echo json_encode(['error' => 'Invalid action']);
      break;
  }
}

function handlePutRequest($pdo, $action) {
  $input = json_decode(file_get_contents('php://input'), true);
  
  switch ($action) {
    case 'update_user':
      updateUser($pdo, $input);
      break;
    case 'update_treatment_type':
      updateTreatmentType($pdo, $input);
      break;
    case 'update_drug':
      updateDrug($pdo, $input);
      break;
    default:
      http_response_code(400);
      echo json_encode(['error' => 'Invalid action']);
      break;
  }
}

function handleDeleteRequest($pdo, $action) {
  switch ($action) {
    case 'delete_user':
      deleteUser($pdo);
      break;
    case 'delete_treatment_type':
      deleteTreatmentType($pdo);
      break;
    case 'delete_drug':
      deleteDrug($pdo);
      break;
    default:
      http_response_code(400);
      echo json_encode(['error' => 'Invalid action']);
      break;
  }
}

// User management functions
function getUsers($pdo) {
  try {
    $stmt = $pdo->prepare("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($users);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

function addUser($pdo, $input) {
  $required_fields = ['username', 'password', 'role'];
  
  foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
      http_response_code(400);
      echo json_encode(['error' => "Field '$field' is required"]);
      return;
    }
  }
  
  try {
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
    $stmt->execute([$input['username']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($existing > 0) {
      http_response_code(409);
      echo json_encode(['error' => 'اسم المستخدم موجود مسبقاً']);
      return;
    }
    
    // Hash password
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
      INSERT INTO users (username, password, role, created_at)
      VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->execute([
      $input['username'],
      $hashedPassword,
      $input['role']
    ]);
    
    $user_id = $pdo->lastInsertId();
    
    echo json_encode([
      'success' => true,
      'message' => 'تم إضافة المستخدم بنجاح',
      'user_id' => $user_id
    ]);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

function updateUser($pdo, $input) {
  $user_id = $_GET['id'] ?? null;
  
  if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
    return;
  }
  
  try {
    $fields = [];
    $values = [];
    
    if (isset($input['username'])) {
      // Check if new username already exists
      $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ? AND id != ?");
      $stmt->execute([$input['username'], $user_id]);
      $existing = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
      
      if ($existing > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'اسم المستخدم موجود مسبقاً']);
        return;
      }
      
      $fields[] = 'username = ?';
      $values[] = $input['username'];
    }
    
    if (isset($input['password']) && !empty($input['password'])) {
      $fields[] = 'password = ?';
      $values[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    
    if (isset($input['role'])) {
      $fields[] = 'role = ?';
      $values[] = $input['role'];
    }
    
    if (empty($fields)) {
      http_response_code(400);
      echo json_encode(['error' => 'No fields to update']);
      return;
    }
    
    $values[] = $user_id;
    
    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($values);
    
    echo json_encode([
      'success' => true,
      'message' => 'تم تحديث المستخدم بنجاح'
    ]);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

function deleteUser($pdo) {
  $user_id = $_GET['id'] ?? null;
  
  if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
    return;
  }
  
  try {
    // Don't allow deleting the last admin user
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $stmt->execute();
    $adminCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userRole = $stmt->fetch(PDO::FETCH_ASSOC)['role'];
    
    if ($userRole === 'admin' && $adminCount <= 1) {
      http_response_code(400);
      echo json_encode(['error' => 'لا يمكن حذف آخر مستخدم مدير']);
      return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    if ($stmt->rowCount() > 0) {
      echo json_encode([
        'success' => true,
        'message' => 'تم حذف المستخدم بنجاح'
      ]);
    } else {
      http_response_code(404);
      echo json_encode(['error' => 'User not found']);
    }
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

// Treatment types management
function getTreatmentTypes($pdo) {
  try {
    $stmt = $pdo->prepare("SELECT * FROM treatment_types ORDER BY name");
    $stmt->execute();
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($types);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

function addTreatmentType($pdo, $input) {
  $required_fields = ['name', 'default_cost'];
  
  foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
      http_response_code(400);
      echo json_encode(['error' => "Field '$field' is required"]);
      return;
    }
  }
  
  try {
    $stmt = $pdo->prepare("
      INSERT INTO treatment_types (name, default_cost)
      VALUES (?, ?)
    ");
    
    $stmt->execute([
      $input['name'],
      $input['default_cost']
    ]);
    
    $type_id = $pdo->lastInsertId();
    
    echo json_encode([
      'success' => true,
      'message' => 'تم إضافة نوع المعالجة بنجاح',
      'type_id' => $type_id
    ]);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

function updateTreatmentType($pdo, $input) {
  $type_id = $_GET['id'] ?? null;
  
  if (!$type_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Treatment type ID is required']);
    return;
  }
  
  try {
    $fields = [];
    $values = [];
    
    if (isset($input['name'])) {
      $fields[] = 'name = ?';
      $values[] = $input['name'];
    }
    
    if (isset($input['default_cost'])) {
      $fields[] = 'default_cost = ?';
      $values[] = $input['default_cost'];
    }
    
    if (empty($fields)) {
      http_response_code(400);
      echo json_encode(['error' => 'No fields to update']);
      return;
    }
    
    $values[] = $type_id;
    
    $stmt = $pdo->prepare("UPDATE treatment_types SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($values);
    
    echo json_encode([
      'success' => true,
      'message' => 'تم تحديث نوع المعالجة بنجاح'
    ]);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

function deleteTreatmentType($pdo) {
  $type_id = $_GET['id'] ?? null;
  
  if (!$type_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Treatment type ID is required']);
    return;
  }
  
  try {
    // Check if treatment type is being used
    // ملاحظة: تم تغيير اسم الجدول من session_treatments إلى treatments بناءً على المخطط الذي قدمته مسبقًا.
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM treatments WHERE treatment_type_id = ?");
    $stmt->execute([$type_id]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($usage > 0) {
      http_response_code(400);
      echo json_encode(['error' => 'لا يمكن حذف نوع المعالجة لأنه مستخدم في جلسات موجودة']);
      return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM treatment_types WHERE id = ?");
    $stmt->execute([$type_id]);
    
    if ($stmt->rowCount() > 0) {
      echo json_encode([
        'success' => true,
        'message' => 'تم حذف نوع المعالجة بنجاح'
      ]);
    } else {
      http_response_code(404);
      echo json_encode(['error' => 'Treatment type not found']);
    }
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

// Drugs management
function getDrugs($pdo) {
  try {
    $stmt = $pdo->prepare("SELECT * FROM drugs ORDER BY name");
    $stmt->execute();
    $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($drugs);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

function addDrug($pdo, $input) {
  $required_fields = ['name'];
  
  foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
      http_response_code(400);
      echo json_encode(['error' => "Field '$field' is required"]);
      return;
    }
  }
  
  try {
    $stmt = $pdo->prepare("
      INSERT INTO drugs (name, dosage_options)
      VALUES (?, ?)
    ");
    
    $dosage_options = isset($input['dosage_options']) ? json_encode($input['dosage_options']) : null;
    
    $stmt->execute([
      $input['name'],
      $dosage_options
    ]);
    
    $drug_id = $pdo->lastInsertId();
    
    echo json_encode([
      'success' => true,
      'message' => 'تم إضافة الدواء بنجاح',
      'drug_id' => $drug_id
    ]);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

function updateDrug($pdo, $input) {
  $drug_id = $_GET['id'] ?? null;
  
  if (!$drug_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Drug ID is required']);
    return;
  }
  
  try {
    $fields = [];
    $values = [];
    
    if (isset($input['name'])) {
      $fields[] = 'name = ?';
      $values[] = $input['name'];
    }
    
    if (isset($input['dosage_options'])) {
      $fields[] = 'dosage_options = ?';
      $values[] = json_encode($input['dosage_options']);
    }
    
    if (empty($fields)) {
      http_response_code(400);
      echo json_encode(['error' => 'No fields to update']);
      return;
    }
    
    $values[] = $drug_id;
    
    $stmt = $pdo->prepare("UPDATE drugs SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($values);
    
    echo json_encode([
      'success' => true,
      'message' => 'تم تحديث الدواء بنجاح'
    ]);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

function deleteDrug($pdo) {
  $drug_id = $_GET['id'] ?? null;
  
  if (!$drug_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Drug ID is required']);
    return;
  }
  
  try {
    // Check if drug is being used
    // ملاحظة: تم تغيير اسم الجدول من session_prescriptions إلى prescriptions بناءً على المخطط الذي قدمته مسبقًا.
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE drug_id = ?");
    $stmt->execute([$drug_id]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($usage > 0) {
      http_response_code(400);
      echo json_encode(['error' => 'لا يمكن حذف الدواء لأنه مستخدم في وصفات موجودة']);
      return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM drugs WHERE id = ?");
    $stmt->execute([$drug_id]);
    
    if ($stmt->rowCount() > 0) {
      echo json_encode([
        'success' => true,
        'message' => 'تم حذف الدواء بنجاح'
      ]);
    } else {
      http_response_code(404);
      echo json_encode(['error' => 'Drug not found']);
    }
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

// Clinic information management
function getClinicInfo($pdo) {
  try {
    $stmt = $pdo->prepare("SELECT * FROM clinic_info LIMIT 1");
    $stmt->execute();
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$info) {
      // Return default values if no clinic info exists
      $info = [
        'name' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'doctor_name' => '',
        'specialization' => ''
      ];
    }
    
    echo json_encode($info);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

function updateClinicInfo($pdo, $input) {
  try {
    // Check if clinic info exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clinic_info");
    $stmt->execute();
    $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if ($exists) {
      // Update existing record
      $fields = [];
      $values = [];
      
      $allowed_fields = ['name', 'address', 'phone', 'email', 'doctor_name', 'specialization'];
      
      foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
          $fields[] = "$field = ?";
          $values[] = $input[$field];
        }
      }
      
      if (!empty($fields)) {
        $stmt = $pdo->prepare("UPDATE clinic_info SET " . implode(', ', $fields));
        $stmt->execute($values);
      }
    } else {
      // Insert new record
      $stmt = $pdo->prepare("
        INSERT INTO clinic_info (name, address, phone, email, doctor_name, specialization)
        VALUES (?, ?, ?, ?, ?, ?)
      ");
      
      $stmt->execute([
        $input['name'] ?? '',
        $input['address'] ?? '',
        $input['phone'] ?? '',
        $input['email'] ?? '',
        $input['doctor_name'] ?? '',
        $input['specialization'] ?? ''
      ]);
    }
    
    echo json_encode([
      'success' => true,
      'message' => 'تم تحديث معلومات العيادة بنجاح'
    ]);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

// System settings management
function getSystemSettings($pdo) {
  try {
    $stmt = $pdo->prepare("SELECT * FROM system_settings");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to key-value pairs
    $result = [];
    foreach ($settings as $setting) {
      $result[$setting['setting_key']] = $setting['setting_value'];
    }
    
    echo json_encode($result);
    
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}

function updateSystemSettings($pdo, $input) {
  try {
    $pdo->beginTransaction();
    
    foreach ($input as $key => $value) {
      $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
      ");
      $stmt->execute([$key, $value]);
    }
    
    $pdo->commit();
    
    echo json_encode([
      'success' => true,
      'message' => 'تم تحديث إعدادات النظام بنجاح'
    ]);
    
  } catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
  }
}
?>
