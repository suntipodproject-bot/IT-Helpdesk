<?php
// ======================================================
// api/assets.php — Asset Management API
// ======================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = currentUser();

if ($method === 'GET') {
    $code = trim($_GET['code'] ?? '');
    
    if ($code) {
        // Single lookup
        $stmt = $db->prepare("SELECT * FROM assets WHERE asset_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $asset = $stmt->fetch();
        if (!$asset) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสอุปกรณ์ในระบบ']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $asset]);
    } else {
        // List all
        $stmt = $db->query("SELECT a.*, d.dept_name FROM assets a LEFT JOIN department d ON a.department_id = d.id ORDER BY a.asset_code ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }
}

if ($method === 'POST') {
    if ($user['role'] === 'user') exit(json_encode(['success'=>false, 'error'=>'Permission denied']));
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['asset_code']) || empty($data['asset_name'])) {
        echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลที่จำเป็น (*)']);
        exit;
    }

    // Check duplicate
    $check = $db->prepare("SELECT id FROM assets WHERE asset_code = ?");
    $check->execute([$data['asset_code']]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'รหัสครุภัณฑ์นี้มีอยู่แล้วในระบบ']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO assets (asset_code, asset_name, brand, model, serial_number, department_id) VALUES (?,?,?,?,?,?)");
    $stmt->execute([
        $data['asset_code'],
        $data['asset_name'],
        $data['brand'] ?? '',
        $data['model'] ?? '',
        $data['serial_number'] ?? '',
        $data['department_id'] ?: null
    ]);
    echo json_encode(['success' => true]);
}

if ($method === 'PUT') {
    if ($user['role'] === 'user') exit(json_encode(['success'=>false, 'error'=>'Permission denied']));
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['id']) || empty($data['asset_code'])) {
        echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    $stmt = $db->prepare("UPDATE assets SET asset_code = ?, asset_name = ?, brand = ?, model = ?, serial_number = ?, department_id = ? WHERE id = ?");
    $stmt->execute([
        $data['asset_code'],
        $data['asset_name'],
        $data['brand'] ?? '',
        $data['model'] ?? '',
        $data['serial_number'] ?? '',
        $data['department_id'] ?: null,
        $data['id']
    ]);
    echo json_encode(['success' => true]);
}

if ($method === 'DELETE') {
    if ($user['role'] === 'user') exit(json_encode(['success'=>false, 'error'=>'Permission denied']));
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['id'])) exit(json_encode(['success'=>false, 'error'=>'ID required']));

    // Check usage in tickets
    $check = $db->prepare("SELECT COUNT(*) FROM tickets WHERE asset_id = ?");
    $check->execute([$data['id']]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบได้ เนื่องจากอุปกรณ์นี้มีประวัติการซ่อมอยู่ในระบบ']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM assets WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(['success' => true]);
}
