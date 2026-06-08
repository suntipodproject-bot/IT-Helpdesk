# Tickets API (PHP)
ไฟล์หลักที่จัดการการเพิ่ม ลบ แก้ไข และดึงข้อมูล Ticket

```php
<?php
// ======================================================
// api/tickets.php — Tickets CRUD API
// ======================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = currentUser();

// ---- GET: List tickets ----
if ($method === 'GET') {
    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[]  = 't.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['priority'])) {
        $where[]  = 't.priority = ?';
        $params[] = $_GET['priority'];
    }
    if (!empty($_GET['search'])) {
        $where[]  = '(t.ticket_no LIKE ? OR t.reporter_name LIKE ? OR t.problem_description LIKE ?)';
        $q        = '%' . $_GET['search'] . '%';
        $params   = array_merge($params, [$q, $q, $q]);
    }

    $sql  = "SELECT t.id, t.ticket_no, t.reporter_name, t.reporter_phone, t.priority,
                    t.department_id, t.asset_id, t.problem_description AS description,
                    t.status, t.assigned_to, t.note, t.created_at, t.closed_at,
                    u.full_name AS assigned_name,
                    a.asset_name, a.model AS asset_model,
                    d.dept_name AS location_room
             FROM tickets t
             LEFT JOIN users      u ON t.assigned_to  = u.id
             LEFT JOIN assets     a ON t.asset_id     = a.id
             LEFT JOIN department d ON t.department_id = d.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY
               FIELD(t.priority,'critical','urgent','normal'),
               t.created_at DESC
             LIMIT 100";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $tickets]);
    exit;
}

// ---- POST: Create new ticket ----
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $required = ['reporter_name', 'priority', 'description', 'department_id'];
    foreach ($required as $f) {
        if (empty($data[$f])) {
            http_response_code(422);
            $label = ['department_id' => 'แผนก', 'description' => 'รายละเอียดอาการเสีย',
                      'reporter_name' => 'ชื่อผู้แจ้ง', 'priority' => 'ระดับความสำคัญ'];
            echo json_encode(['success' => false, 'error' => 'กรุณากรอก: ' . ($label[$f] ?? $f)]);
            exit;
        }
    }

    // --- DUPLICATE CHECK (Prevent double submit) ---
    $dup_stmt = $db->prepare("
        SELECT id FROM tickets 
        WHERE reporter_name = ? 
          AND problem_description = ? 
          AND created_at > NOW() - INTERVAL 60 SECOND
        LIMIT 1
    ");
    $dup_stmt->execute([$data['reporter_name'], $data['description']]);
    if ($dup_stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'error' => 'รายการนี้ถูกส่งไปแล้ว กรุณารอสักครู่ (ป้องกันการส่งซ้ำ)']);
        exit;
    }

    // Generate ticket number: TK-YYMM-XXX
    $prefix  = 'TK-' . date('ym') . '-';
    $stmt    = $db->prepare("SELECT COUNT(*) FROM tickets WHERE ticket_no LIKE ?");
    $stmt->execute([$prefix . '%']);
    $seq     = (int)$stmt->fetchColumn() + 1;
    $ticketNo = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

    $stmt = $db->prepare("
        INSERT INTO tickets (ticket_no, reporter_name, reporter_phone, priority,
                             asset_id, department_id, problem_description, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $ticketNo,
        $data['reporter_name'],
        $data['reporter_phone'] ?? null,
        $data['priority'],
        !empty($data['asset_id'])     ? (int)$data['asset_id']     : null,
        !empty($data['department_id']) ? (int)$data['department_id'] : null,
        $data['description'],
    ]);

    $newId = $db->lastInsertId();

    // ตอบกลับ browser ทันที ก่อนส่ง notification
    $responseBody = json_encode(['success' => true, 'ticket_no' => $ticketNo, 'id' => $newId]);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: ' . strlen($responseBody));
    header('Connection: close');
    echo $responseBody;

    // Flush output to browser ทันที (ไม่ต้องรอ Notification)
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();

    // ===== ทำงานในเบื้องหลังหลังจากส่ง response แล้ว =====
    ignore_user_abort(true);
    set_time_limit(30);

    // Trigger Notification (Telegram / Discord / Line)
    $msg = "👤 ผู้แจ้ง: {$data['reporter_name']}" . (!empty($data['reporter_phone']) ? " ({$data['reporter_phone']})" : '') . "\n";
    if (!empty($data['location_room'])) $msg .= "🏥 สถานที่: {$data['location_room']}\n";
    $msg .= "🛠️ อาการ: " . mb_substr($data['description'], 0, 100);

    if (function_exists('sendSystemNotification')) {
        sendSystemNotification($msg, $ticketNo, $data['priority']);
    }

    exit;
}
```
