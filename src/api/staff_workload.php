<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
requireLogin();

// Allow Admin and Staff
$currentUser = currentUser();
if (!$currentUser || ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'staff')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get staff and admin users with their active ticket count
    $sql = "SELECT 
                u.id, 
                u.username, 
                u.full_name, 
                u.role, 
                COUNT(t.id) as active_tickets
            FROM users u
            LEFT JOIN tickets t ON u.id = t.assigned_to AND t.status IN ('pending', 'ongoing')
            WHERE u.role IN ('admin', 'staff') AND u.is_active = 1
            GROUP BY u.id
            ORDER BY active_tickets ASC, u.full_name ASC";
            
    $stmt = $db->query($sql);
    $staffList = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $staffList]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
