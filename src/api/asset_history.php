<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$code = trim($_GET['code'] ?? '');
if (!$code) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุรหัสครุภัณฑ์']);
    exit;
}

$db = getDB();

// 1. Get Asset Details
$stmt = $db->prepare("
    SELECT a.*, d.dept_name 
    FROM assets a
    LEFT JOIN department d ON a.department_id = d.id
    WHERE a.asset_code = ? LIMIT 1
");
$stmt->execute([$code]);
$asset = $stmt->fetch();

if (!$asset) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลครุภัณฑ์นี้']);
    exit;
}

// 2. Get Repair History
$stmt = $db->prepare("
    SELECT t.ticket_no, t.problem_description, t.status, t.created_at, t.closed_at,
           u.full_name AS technician
    FROM tickets t
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.asset_id = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$asset['id']]);
$history = $stmt->fetchAll();

// 3. Simple Analysis
$totalRepairs = count($history);
$warrantyStatus = (strtotime($asset['warranty_until']) < time()) ? 'Expired' : 'Active';

echo json_encode([
    'success' => true,
    'data' => [
        'asset' => $asset,
        'history' => $history,
        'analysis' => [
            'total_repairs' => $totalRepairs,
            'warranty_status' => $warrantyStatus
        ]
    ]
]);
