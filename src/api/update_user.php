<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Only allow Admin
$currentUser = currentUser();
if (!$currentUser || $currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['userId'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Missing User ID.']);
    exit;
}

$db = getDB();

try {
    // 1. Update Role
    if (isset($data['role'])) {
        $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$data['role'], $userId]);
    }

    // 2. Update Status (Active/Inactive)
    if (isset($data['is_active'])) {
        $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$data['is_active'], $userId]);
    }

    // 3. Reset Password
    if (isset($data['reset_password']) && $data['reset_password'] === true) {
        $newPassword = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?");
        $stmt->execute([$newPassword, $userId]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
