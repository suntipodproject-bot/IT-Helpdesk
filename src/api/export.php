<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = getDB();

// Get all tickets with joined data
$stmt = $db->query("
    SELECT t.ticket_no, t.reporter_name, t.reporter_phone, t.priority,
           d.dept_name, a.asset_code, t.problem_description, t.status,
           u.full_name AS assigned_to, t.created_at, t.closed_at
    FROM tickets t
    LEFT JOIN department d ON t.department_id = d.id
    LEFT JOIN assets     a ON t.asset_id      = a.id
    LEFT JOIN users      u ON t.assigned_to   = u.id
    ORDER BY t.id DESC
");
$tickets = $stmt->fetchAll();

// CSV Header
$filename = "IT_Helpdesk_Export_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Add BOM for Thai characters in Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Column headers
fputcsv($output, [
    'เลขที่ Ticket',
    'ผู้แจ้ง',
    'เบอร์ติดต่อ',
    'ความสำคัญ',
    'แผนก',
    'รหัสทรัพย์สิน',
    'รายละเอียดอาการ',
    'สถานะ',
    'ผู้รับผิดชอบ',
    'วันที่แจ้ง',
    'วันที่ปิดงาน'
]);

foreach ($tickets as $t) {
    fputcsv($output, [
        $t['ticket_no'],
        $t['reporter_name'],
        $t['reporter_phone'],
        $t['priority'],
        $t['dept_name'],
        $t['asset_code'],
        $t['problem_description'],
        $t['status'],
        $t['assigned_to'],
        $t['created_at'],
        $t['closed_at']
    ]);
}

fclose($output);
exit;
