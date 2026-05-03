<?php
// ======================================================
// api/assets.php — Asset Lookup API (for QR scan)
// ======================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$code = trim($_GET['code'] ?? '');

if (!$code) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุรหัสอุปกรณ์']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM assets WHERE asset_code = ? LIMIT 1");
$stmt->execute([$code]);
$asset = $stmt->fetch();

if (!$asset) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสอุปกรณ์ในระบบ']);
    exit;
}

echo json_encode(['success' => true, 'data' => $asset]);
