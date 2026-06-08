<?php
// ======================================================
// api/dashboard.php — Dashboard Statistics API
// ======================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

// Handle month/year filters
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

// --- KPI Cards ---
$stats = [];

// Total tickets this month
$stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE MONTH(created_at)=? AND YEAR(created_at)=?");
$stmt->execute([$month, $year]);
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

// Done this month (Completed)
$stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE status='completed' AND MONTH(created_at)=? AND YEAR(created_at)=?");
$stmt->execute([$month, $year]);
$stats['done_month'] = (int)$stmt->fetchColumn();

// SLA Achievement
$stmt = $db->prepare("
    SELECT
        SUM(CASE
            WHEN priority='critical' AND TIMESTAMPDIFF(MINUTE, created_at, closed_at) <= 60 THEN 1
            WHEN priority='urgent'   AND TIMESTAMPDIFF(MINUTE, created_at, closed_at) <= 120 THEN 1
            WHEN priority='normal'   AND TIMESTAMPDIFF(HOUR,   created_at, closed_at) <= 24 THEN 1
            ELSE 0
        END) AS on_time,
        COUNT(*) AS total
    FROM tickets 
    WHERE status='completed' 
    AND closed_at IS NOT NULL 
    AND MONTH(created_at)=? AND YEAR(created_at)=?
");
$stmt->execute([$month, $year]);
$sla = $stmt->fetch();
$stats['sla_pct'] = $sla['total'] > 0 ? round(($sla['on_time'] / $sla['total']) * 100) : 100;

// --- Chart: Tickets by Department ---
$stmt = $db->prepare("
    SELECT d.dept_name AS label, COUNT(t.id) AS value
    FROM department d
    INNER JOIN tickets t ON d.id = t.department_id
    WHERE MONTH(t.created_at)=? AND YEAR(t.created_at)=?
    GROUP BY d.id
    ORDER BY value DESC
    LIMIT 6
");
$stmt->execute([$month, $year]);
$stats['chart_dept'] = $stmt->fetchAll();

// --- Chart: Tickets by Priority ---
$stmt = $db->prepare("
    SELECT priority AS label, COUNT(*) AS value
    FROM tickets
    WHERE MONTH(created_at)=? AND YEAR(created_at)=?
    GROUP BY priority
");
$stmt->execute([$month, $year]);
$stats['chart_priority'] = $stmt->fetchAll();

echo json_encode(['success' => true, 'data' => $stats]);
