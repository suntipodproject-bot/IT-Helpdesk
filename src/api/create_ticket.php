<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$currentUser = currentUser();
if (!$currentUser) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = getDB();

try {
    $reporter_id = $_POST['reporter_id'] ?? $currentUser['id'];
    $reporter_info = $_POST['reporter_info'] ?? $currentUser['full_name'];
    
    // Simple split: "Name (Phone)" or just "Name"
    $reporter_name = $reporter_info;
    $reporter_phone = '';
    if (preg_match('/^(.*)\((.*)\)$/', $reporter_info, $matches)) {
        $reporter_name = trim($matches[1]);
        $reporter_phone = trim($matches[2]);
    } else if (preg_match('/^(.*) (.*)$/', $reporter_info, $matches) && is_numeric($matches[2])) {
        $reporter_name = trim($matches[1]);
        $reporter_phone = trim($matches[2]);
    }

    $priority = $_POST['priority'] ?? 'normal';
    $department_id = $_POST['department_id'] ?? null;
    $problem_description = $_POST['problem_description'] ?? '';
    $asset_code = $_POST['asset_code'] ?? null;

    if (!$department_id || !$problem_description) {
        throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน (แผนก และรายละเอียดอาการเสีย)");
    }

    // 1. Lookup asset_id if asset_code provided
    $asset_id = null;
    if ($asset_code) {
        $stmt = $db->prepare("SELECT id FROM assets WHERE asset_code = ?");
        $stmt->execute([$asset_code]);
        $asset = $stmt->fetch();
        if ($asset) {
            $asset_id = $asset['id'];
        }
    }

    // 2. Generate Ticket No (TK-YYMM-XXX)
    $prefix = "TK-" . date('ym') . "-";
    $stmt = $db->prepare("SELECT ticket_no FROM tickets WHERE ticket_no LIKE ? ORDER BY ticket_no DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastTicket = $stmt->fetch();
    
    $nextNum = 1;
    if ($lastTicket) {
        $lastNum = (int)substr($lastTicket['ticket_no'], -3);
        $nextNum = $lastNum + 1;
    }
    $ticket_no = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

    // 3. Insert into DB
    $stmt = $db->prepare("INSERT INTO tickets (ticket_no, reporter_id, reporter_name, reporter_phone, priority, department_id, asset_id, problem_description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([
        $ticket_no,
        $reporter_id,
        $reporter_name,
        $reporter_phone,
        $priority,
        $department_id,
        $asset_id,
        $problem_description
    ]);

    // 4. Line Notify (Placeholder - could be implemented if token is available)
    // sendLineNotify("แจ้งซ่อมใหม่: $ticket_no\nโดย: $reporter_name\nแผนก: ...\nอาการ: $problem_description");

    echo json_encode(['success' => true, 'ticket_no' => $ticket_no]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
