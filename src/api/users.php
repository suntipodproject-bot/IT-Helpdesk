<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Only allow Admin
$currentUser = currentUser();
if (!$currentUser || $currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("SELECT u.id, u.username, u.full_name, u.role, u.position_id, u.phone, u.is_active, p.position_name 
                        FROM users u 
                        LEFT JOIN position p ON u.position_id = p.id 
                        ORDER BY u.id DESC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['username']) || empty($data['full_name']) || empty($data['role'])) {
        echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน (Username, ชื่อ, สิทธิ์)']);
        exit;
    }

    // Check duplicate username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว']);
        exit;
    }

    $tempPass = password_hash('welcome123', PASSWORD_DEFAULT); // Default temp pass
    $mustChange = 1;

    $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role, position_id, phone, must_change_password) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['username'],
        $tempPass,
        $data['full_name'],
        $data['role'],
        !empty($data['position_id']) ? $data['position_id'] : null,
        $data['phone'] ?? null,
        $mustChange
    ]);

    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = (int)($data['id'] ?? 0);

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบ ID']);
        exit;
    }

    $sets = [];
    $params = [];

    if (isset($data['full_name'])) { $sets[] = "full_name = ?"; $params[] = $data['full_name']; }
    if (isset($data['role']))      { $sets[] = "role = ?";      $params[] = $data['role']; }
    if (isset($data['position_id'])){ $sets[] = "position_id = ?"; $params[] = $data['position_id'] ?: null; }
    if (isset($data['phone']))     { $sets[] = "phone = ?";     $params[] = $data['phone']; }
    if (isset($data['is_active'])) { $sets[] = "is_active = ?"; $params[] = $data['is_active']; }

    if (empty($sets)) {
        echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลแก้ไข']);
        exit;
    }

    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?";
    $db->prepare($sql)->execute($params);

    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบ ID']);
        exit;
    }

    // Prevent deleting self
    if ($id === $currentUser['id']) {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบบัญชีตัวเองได้']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
