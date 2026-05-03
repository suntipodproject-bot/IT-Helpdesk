<?php
// ======================================================
// api/tickets.php — Tickets CRUD API
// ======================================================
require_once __DIR__ . '/../includes/db.php';
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

    $sql  = "SELECT t.*, u.full_name AS assigned_name, a.asset_name, a.model AS asset_model
             FROM tickets t
             LEFT JOIN users  u ON t.assigned_to = u.id
             LEFT JOIN assets a ON t.asset_id    = a.id
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

    $required = ['reporter_name', 'priority', 'description'];
    foreach ($required as $f) {
        if (empty($data[$f])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => "กรุณากรอก: $f"]);
            exit;
        }
    }

    // Generate ticket number: TK-YYMM-XXX
    $prefix  = 'TK-' . date('ym') . '-';
    $stmt    = $db->prepare("SELECT COUNT(*) FROM tickets WHERE ticket_no LIKE ?");
    $stmt->execute([$prefix . '%']);
    $seq     = (int)$stmt->fetchColumn() + 1;
    $ticketNo = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

    $stmt = $db->prepare("
        INSERT INTO tickets (ticket_no, reporter_name, reporter_phone, priority,
                             location_building, location_floor, location_room,
                             asset_id, description, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $ticketNo,
        $data['reporter_name'],
        $data['reporter_phone']      ?? null,
        $data['priority'],
        $data['location_building']   ?? null,
        $data['location_floor']      ?? null,
        $data['location_room']       ?? null,
        !empty($data['asset_id']) ? (int)$data['asset_id'] : null,
        $data['description'],
    ]);

    $newId = $db->lastInsertId();

    // Trigger Notification (Telegram / Discord)
    $notifyPayload = json_encode([
        'ticket_no' => $ticketNo,
        'priority'  => $data['priority'],
        'message'   => "ผู้แจ้ง: {$data['reporter_name']}" .
                       (!empty($data['reporter_phone']) ? " (โทร {$data['reporter_phone']})" : '') . "\n" .
                       (!empty($data['location_room'])  ? "ห้อง: {$data['location_room']}\n" : '') .
                       "อาการ: " . mb_substr($data['description'], 0, 100),
    ]);
    $ch = curl_init('http://localhost/api/notify.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $notifyPayload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 5,
    ]);
    @curl_exec($ch);
    curl_close($ch);

    echo json_encode(['success' => true, 'ticket_no' => $ticketNo, 'id' => $newId]);
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
        $sets[]   = 'status = ?';
        $params[] = $data['status'];
        if ($data['status'] === 'done') {
            $sets[]   = 'closed_at = NOW()';
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

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
