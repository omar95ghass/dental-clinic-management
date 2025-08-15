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
      getClinicInfoFromConfig();
      break;
    case 'get_system_settings':
      getSystemSettingsFromConfig();
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
      updateClinicInfoInConfig($input);
      break;
    case 'update_system_settings':
      updateSystemSettingsInConfig($input);
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

// ===== Helpers for config.json settings =====
function getConfigPath() {
  return __DIR__ . '/../config/config.json';
}

function readConfigJson() {
  $path = getConfigPath();
  if (!file_exists($path)) {
    return [
      'clinic_info' => [],
      'general_settings' => []
    ];
  }
  $raw = file_get_contents($path);
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function writeConfigJson($data) {
  $path = getConfigPath();
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  $tmp = $path . '.tmp';
  file_put_contents($tmp, $json, LOCK_EX);
  rename($tmp, $path);
}

function getClinicInfoFromConfig() {
  $cfg = readConfigJson();
  $info = $cfg['clinic_info'] ?? [];

  // Normalize keys to match frontend form expectations
  $result = [
    'name' => $info['clinic_name'] ?? '',
    'address' => $info['address'] ?? '',
    'phone' => $info['phone'] ?? '',
    'email' => $info['email'] ?? '',
    'doctor_name' => $info['doctor_name'] ?? '',
    'specialization' => $info['specialization'] ?? ''
  ];
  echo json_encode($result);
}

function updateClinicInfoInConfig($input) {
  $cfg = readConfigJson();
  $info = $cfg['clinic_info'] ?? [];

  $info['clinic_name'] = $input['name'] ?? ($info['clinic_name'] ?? '');
  $info['address'] = $input['address'] ?? ($info['address'] ?? '');
  $info['phone'] = $input['phone'] ?? ($info['phone'] ?? '');
  $info['email'] = $input['email'] ?? ($info['email'] ?? '');
  $info['doctor_name'] = $input['doctor_name'] ?? ($info['doctor_name'] ?? '');
  $info['specialization'] = $input['specialization'] ?? ($info['specialization'] ?? '');

  $cfg['clinic_info'] = $info;
  writeConfigJson($cfg);

  echo json_encode(['success' => true, 'message' => 'تم تحديث معلومات العيادة بنجاح']);
}

function getSystemSettingsFromConfig() {
  $cfg = readConfigJson();
  $settings = $cfg['general_settings'] ?? [];

  // Provide defaults expected by UI
  $defaults = [
    'appointment_duration' => 30,
    'working_hours_start' => '09:00',
    'working_hours_end' => '17:00',
    'currency' => 'SYP',
    'session_timeout' => 120,
    'backup_frequency' => 'weekly'
  ];

  $result = array_merge($defaults, $settings);
  echo json_encode($result);
}

function updateSystemSettingsInConfig($input) {
  $cfg = readConfigJson();
  $settings = $cfg['general_settings'] ?? [];

  // Whitelist keys from UI
  $allowed = ['appointment_duration','working_hours_start','working_hours_end','currency','session_timeout','backup_frequency'];
  foreach ($allowed as $key) {
    if (array_key_exists($key, $input)) {
      $settings[$key] = $input[$key];
    }
  }

  $cfg['general_settings'] = $settings;
  writeConfigJson($cfg);

  echo json_encode(['success' => true, 'message' => 'تم تحديث إعدادات النظام بنجاح']);
}

// ===== User management functions (DB-based) =====
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

// Treatment types management (DB-based)
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

// Drugs management (DB-based)
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
?>
