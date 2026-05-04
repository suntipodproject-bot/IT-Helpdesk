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
        $where[]  = '(t.ticket_no LIKE ? OR t.reporter_name LIKE ? OR t.description LIKE ?)';
        $q        = '%' . $_GET['search'] . '%';
        $params   = array_merge($params, [$q, $q, $q]);
    }

    if (!empty($_GET['assigned_to'])) {
        $where[]  = 't.assigned_to = ?';
        $params[] = $_GET['assigned_to'];
    }

    $sql  = "SELECT t.id, t.ticket_no, t.reporter_name, t.reporter_phone, t.priority,
                    t.department_id, t.asset_id, t.problem_description AS description,
                    t.status, t.assigned_to, t.note, t.created_at, t.closed_at,
                    u.full_name AS assigned_name,
                    a.asset_name, a.model AS asset_model, a.asset_code,
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
    try {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $required = ['reporter_name', 'priority', 'description', 'department_id'];
        foreach ($required as $f) {
            if (empty($data[$f])) {
                $label = ['department_id' => 'แผนก', 'description' => 'รายละเอียดอาการเสีย',
                          'reporter_name' => 'ชื่อผู้แจ้ง', 'priority' => 'ระดับความสำคัญ'];
                echo json_encode(['success' => false, 'error' => 'กรุณากรอก: ' . ($label[$f] ?? $f)]);
                exit;
            }
        }

        // --- DUPLICATE CHECK ---
        $dup_stmt = $db->prepare("SELECT id FROM tickets WHERE reporter_name = ? AND problem_description = ? AND created_at > NOW() - INTERVAL 60 SECOND LIMIT 1");
        $dup_stmt->execute([$data['reporter_name'], $data['description']]);
        if ($dup_stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'รายการนี้ถูกส่งไปแล้ว กรุณารอสักครู่ (ป้องกันการส่งซ้ำ)']);
            exit;
        }

        // Generate ticket number
        $prefix  = 'TK-' . date('ym') . '-';
        $stmt    = $db->prepare("SELECT COUNT(*) FROM tickets WHERE ticket_no LIKE ?");
        $stmt->execute([$prefix . '%']);
        $seq     = (int)$stmt->fetchColumn() + 1;
        $ticketNo = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

        // --- ASSET LOOKUP ---
        $assetId = !empty($data['asset_id']) ? (int)$data['asset_id'] : null;
        if (!$assetId && !empty($data['asset_code_display'])) {
            $ast_stmt = $db->prepare("SELECT id FROM assets WHERE asset_code = ? LIMIT 1");
            $ast_stmt->execute([trim($data['asset_code_display'])]);
            $assetId = $ast_stmt->fetchColumn() ?: null;
        }

        $stmt = $db->prepare("INSERT INTO tickets (ticket_no, reporter_name, reporter_phone, priority, asset_id, department_id, problem_description, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([
            $ticketNo,
            $data['reporter_name'],
            $data['reporter_phone'] ?? null,
            $data['priority'],
            $assetId,
            !empty($data['department_id']) ? (int)$data['department_id'] : null,
            $data['description'],
        ]);

        $newId = $db->lastInsertId();

        // Notification
        $msg = "👤 ผู้แจ้ง: {$data['reporter_name']}\n";
        $msg .= "📱 เบอร์: " . ($data['reporter_phone'] ?? '-') . "\n";
        $dept_stmt = $db->prepare("SELECT dept_name FROM department WHERE id = ?");
        $dept_stmt->execute([$data['department_id']]);
        $deptName = $dept_stmt->fetchColumn();
        if ($deptName) $msg .= "🏥 สถานที่: $deptName\n";
        $msg .= "🛠️ อาการ: " . mb_substr($data['description'], 0, 100);

        if (function_exists('sendSystemNotification')) {
            sendSystemNotification($msg, $ticketNo, $data['priority']);
        }

        echo json_encode(['success' => true, 'ticket_no' => $ticketNo, 'id' => $newId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---- PUT: Update ticket (status / assign) ----
if ($method === 'PUT') {
    $data     = json_decode(file_get_contents('php://input'), true) ?? [];
    $ticketId = (int)($data['id'] ?? 0);

    if (!$ticketId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'ไม่พบ ticket id']);
        exit;
    }

    $sets   = [];
    $params = [];

    if (isset($data['status'])) {
        // Map frontend status values to DB ENUM values
        $statusMap = ['in_progress' => 'ongoing', 'done' => 'completed'];
        $dbStatus  = $statusMap[$data['status']] ?? $data['status'];
        $sets[]   = 'status = ?';
        $params[] = $dbStatus;
        if ($dbStatus === 'completed') {
            $sets[] = 'closed_at = NOW()';
        }
    }
    if (isset($data['assigned_to'])) {
        $sets[]   = 'assigned_to = ?';
        $params[] = $data['assigned_to'] ?: null;
        // Auto set in_progress when assigned
        $sets[]   = "status = IF(status='pending','in_progress',status)";
    }
    if (isset($data['note'])) {
        $sets[]   = 'note = ?';
        $params[] = $data['note'];
    }

    if (empty($sets)) {
        echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลให้อัปเดต']);
        exit;
    }

    $params[] = $ticketId;
    $sql      = 'UPDATE tickets SET ' . implode(', ', $sets) . ' WHERE id = ?';
    $db->prepare($sql)->execute($params);

    // --- ASSIGNMENT NOTIFICATION ---
    if (isset($data['assigned_to']) && $data['assigned_to']) {
        $staff_stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
        $staff_stmt->execute([$data['assigned_to']]);
        $staffName = $staff_stmt->fetchColumn();

        $ticket_stmt = $db->prepare("SELECT ticket_no, priority, problem_description FROM tickets WHERE id = ?");
        $ticket_stmt->execute([$ticketId]);
        $tk = $ticket_stmt->fetch();

        if ($staffName && $tk) {
            sendAssignmentNotification($tk['ticket_no'], $staffName, $tk['priority'], $tk['problem_description']);
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

// ---- DELETE: Remove ticket (Admin only) ----
if ($method === 'DELETE') {
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? 0);

    if (!$id) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'ไม่พบ ID']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM tickets WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
