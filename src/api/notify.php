<?php
// ======================================================
// api/notify.php — Multi-Channel Notification Service
// รองรับ: Telegram Bot, Discord Webhook
// ======================================================
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$message = $input['message'] ?? ($_GET['msg'] ?? ($_POST['msg'] ?? ''));
$title   = $input['title']   ?? 'IT Helpdesk';
$priority = $input['priority'] ?? 'normal';
$ticketNo = $input['ticket_no'] ?? '';

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'No message provided']);
    exit;
}

$results = [];

// ======================================================
// [1] Telegram Bot
// ======================================================
if (TELEGRAM_ENABLED && TELEGRAM_BOT_TOKEN !== 'YOUR_TELEGRAM_BOT_TOKEN') {

    $priorityIcon = match($priority) {
        'critical' => '🔴',
        'urgent'   => '🟠',
        default    => '🟢',
    };

    $text  = "🔔 *แจ้งเตือนระบบ IT Helpdesk*\n\n";
    $text .= $ticketNo ? "$priorityIcon *เลขที่:* `$ticketNo`\n" : '';
    $text .= str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $message);

    $payload = [
        'chat_id'    => TELEGRAM_CHAT_ID,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];

    $ch = curl_init('https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    $results['telegram'] = [
        'sent'     => $status === 200,
        'status'   => $status,
        'response' => json_decode($resp, true),
        'error'    => $error ?: null,
    ];
}

// ======================================================
// [2] Discord Webhook
// ======================================================
if (DISCORD_ENABLED && DISCORD_WEBHOOK_URL !== 'YOUR_DISCORD_WEBHOOK_URL') {

    $colorMap = [
        'critical' => 16711680,  // Red
        'urgent'   => 16750848,  // Orange
        'normal'   => 3066993,   // Green
    ];
    $priorityLabel = [
        'critical' => '🔴 วิกฤต',
        'urgent'   => '🟠 เร่งด่วน',
        'normal'   => '🟢 ปกติ',
    ];

    $payload = [
        'username'   => 'IT Helpdesk Bot',
        'avatar_url' => 'https://cdn-icons-png.flaticon.com/512/2920/2920349.png',
        'embeds'     => [[
            'title'       => '🔔 ' . ($ticketNo ?: $title),
            'description' => $message,
            'color'       => $colorMap[$priority] ?? 3066993,
            'fields'      => $ticketNo ? [[
                'name'   => 'ระดับความสำคัญ',
                'value'  => $priorityLabel[$priority] ?? $priority,
                'inline' => true,
            ]] : [],
            'footer' => ['text' => APP_NAME . ' • ' . date('d/m/Y H:i')],
        ]],
    ];

    $ch = curl_init(DISCORD_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    // Discord returns 204 No Content on success
    $results['discord'] = [
        'sent'   => in_array($status, [200, 204]),
        'status' => $status,
        'error'  => $error ?: null,
    ];
}

$anySuccess = !empty(array_filter($results, fn($r) => $r['sent'] ?? false));

if (empty($results)) {
    echo json_encode([
        'success' => false,
        'message' => 'ไม่มีช่องทางแจ้งเตือนที่เปิดใช้งาน กรุณาตั้งค่าใน config.php',
        'results' => $results,
    ]);
} else {
    echo json_encode([
        'success' => $anySuccess,
        'results' => $results,
    ]);
}
