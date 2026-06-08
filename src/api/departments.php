<?php
// ======================================================
// api/departments.php — Departments CRUD API
// ======================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = currentUser();

// Only admin can modify
if ($method !== 'GET' && $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($method === 'GET') {
    $stmt = $db->query("SELECT * FROM department ORDER BY dept_name ASC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['dept_name'])) {
        echo json_encode(['success' => false, 'error' => 'กรุณาระบุชื่อแผนก']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO department (dept_name) VALUES (?)");
    $stmt->execute([$data['dept_name']]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['id']) || empty($data['dept_name'])) {
        echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    $stmt = $db->prepare("UPDATE department SET dept_name = ? WHERE id = ?");
    $stmt->execute([$data['dept_name'], $data['id']]);
    echo json_encode(['success' => true]);
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'error' => 'ID required']);
        exit;
    }

    // Check if being used by tickets or users
    $check = $db->prepare("SELECT COUNT(*) FROM tickets WHERE department_id = ?");
    $check->execute([$data['id']]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบได้ เนื่องจากมีข้อมูลการแจ้งซ่อมที่ผูกกับแผนกนี้']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM department WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(['success' => true]);
}
