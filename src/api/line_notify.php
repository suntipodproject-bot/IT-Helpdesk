<?php
// ======================================================
// api/line_notify.php — Line Notify Integration
// ======================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$token   = LINE_NOTIFY_TOKEN;
$message = $_GET['msg'] ?? ($_POST['msg'] ?? '');

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'No message']);
    exit;
}

if ($token === 'YOUR_LINE_NOTIFY_TOKEN_HERE' || empty($token)) {
    // Token not configured — skip silently (don't break ticket creation)
    echo json_encode(['success' => false, 'error' => 'Line Notify token not configured']);
    exit;
}

$ch = curl_init('https://notify-api.line.me/api/notify');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['message' => "\n" . $message]),
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT        => 5,
]);
$result = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode(['success' => $status === 200, 'line_response' => json_decode($result)]);
