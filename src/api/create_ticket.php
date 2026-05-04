<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';

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

    // 4. Send Notifications
    $deptStmt = $db->prepare("SELECT dept_name FROM department WHERE id = ?");
    $deptStmt->execute([$department_id]);
    $dept = $deptStmt->fetch();
    $deptName = $dept ? $dept['dept_name'] : 'ไม่ระบุ';

    $notifyMsg = "👤 ผู้แจ้ง: $reporter_name\n";
    $notifyMsg .= "📞 เบอร์ติดต่อ: $reporter_phone\n";
    $notifyMsg .= "🏥 แผนก: $deptName\n";
    $notifyMsg .= "🛠️ อาการ: $problem_description";

    sendSystemNotification($notifyMsg, $ticket_no, $priority);

    echo json_encode(['success' => true, 'ticket_no' => $ticket_no]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
