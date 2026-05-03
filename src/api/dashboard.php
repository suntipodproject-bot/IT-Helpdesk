<?php
// ======================================================
// api/dashboard.php — Dashboard Statistics API
// ======================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

// --- KPI Cards ---
$stats = [];

// Total tickets this month
$stmt = $db->query("SELECT COUNT(*) FROM tickets WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
$stats['total_month'] = (int)$stmt->fetchColumn();

// Pending
$stmt = $db->query("SELECT COUNT(*) FROM tickets WHERE status='pending'");
$stats['pending'] = (int)$stmt->fetchColumn();

// Critical pending
$stmt = $db->query("SELECT COUNT(*) FROM tickets WHERE status='pending' AND priority='critical'");
$stats['critical_pending'] = (int)$stmt->fetchColumn();

// Urgent pending
$stmt = $db->query("SELECT COUNT(*) FROM tickets WHERE status='pending' AND priority='urgent'");
$stats['urgent_pending'] = (int)$stmt->fetchColumn();

// Done this month
$stmt = $db->query("SELECT COUNT(*) FROM tickets WHERE status='done' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
$stats['done_month'] = (int)$stmt->fetchColumn();

// SLA Achievement (done within 24h = normal, 2h = urgent, 1h = critical)
$stmt = $db->query("
    SELECT
        SUM(CASE
            WHEN priority='critical' AND TIMESTAMPDIFF(MINUTE, created_at, closed_at) <= 60 THEN 1
            WHEN priority='urgent'   AND TIMESTAMPDIFF(MINUTE, created_at, closed_at) <= 120 THEN 1
            WHEN priority='normal'   AND TIMESTAMPDIFF(HOUR,   created_at, closed_at) <= 24 THEN 1
            ELSE 0
        END) AS on_time,
        COUNT(*) AS total
    FROM tickets WHERE status='done' AND closed_at IS NOT NULL
");
$sla = $stmt->fetch();
$stats['sla_pct'] = $sla['total'] > 0 ? round(($sla['on_time'] / $sla['total']) * 100) : 100;

// --- Chart: Tickets by Location (Room/Dept) ---
$stmt = $db->query("
    SELECT COALESCE(location_room, 'ไม่ระบุ') AS label, COUNT(*) AS value
    FROM tickets
    WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
    GROUP BY location_room
    ORDER BY value DESC
    LIMIT 6
");
$stats['chart_dept'] = $stmt->fetchAll();

// --- Chart: Tickets by Priority ---
$stmt = $db->query("
    SELECT priority AS label, COUNT(*) AS value
    FROM tickets
    WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
    GROUP BY priority
");
$stats['chart_priority'] = $stmt->fetchAll();

echo json_encode(['success' => true, 'data' => $stats]);
