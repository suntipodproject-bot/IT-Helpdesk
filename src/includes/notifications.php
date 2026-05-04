<?php
require_once __DIR__ . '/config.php';

function sendSystemNotification($message, $ticketNo = '', $priority = 'normal') {
    // 1. Telegram
    if (defined('TELEGRAM_ENABLED') && TELEGRAM_ENABLED && TELEGRAM_BOT_TOKEN !== 'YOUR_TELEGRAM_BOT_TOKEN') {
        $priorityIcon = ($priority === 'critical') ? '🔴' : (($priority === 'urgent') ? '🟠' : '🟢');
        $text = "🔔 *แจ้งซ่อมใหม่: $ticketNo*\n";
        $text .= "ระดับ: $priorityIcon\n\n";
        $text .= $message;

        $payload = [
            'chat_id' => TELEGRAM_CHAT_ID,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];

        $ch = curl_init('https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    // 2. Line Notify
    if (defined('LINE_NOTIFY_ENABLED') && LINE_NOTIFY_ENABLED && LINE_NOTIFY_TOKEN !== 'YOUR_LINE_NOTIFY_TOKEN') {
        $lineMsg = "\n🔔 แจ้งซ่อมใหม่: $ticketNo\n" . $message;
        
        $ch = curl_init('https://notify-api.line.me/api/notify');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['message' => $lineMsg]),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . LINE_NOTIFY_TOKEN],
            CURLOPT_TIMEOUT => 5
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    // 3. Discord
    if (defined('DISCORD_ENABLED') && DISCORD_ENABLED && DISCORD_WEBHOOK_URL !== 'YOUR_DISCORD_WEBHOOK_URL') {
        $colorMap = ['critical' => 16711680, 'urgent' => 16750848, 'normal' => 3066993];
        $payload = [
            'embeds' => [[
                'title' => "🔔 แจ้งซ่อมใหม่: $ticketNo",
                'description' => $message,
                'color' => $colorMap[$priority] ?? 3066993,
                'footer' => ['text' => date('d/m/Y H:i')]
            ]]
        ];

        $ch = curl_init(DISCORD_WEBHOOK_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

function sendAssignmentNotification($ticketNo, $staffName, $priority, $description) {
    if (defined('TELEGRAM_ENABLED') && TELEGRAM_ENABLED) {
        $priorityIcon = ($priority === 'critical') ? '🔴' : (($priority === 'urgent') ? '🟠' : '🟢');
        $text = "🎯 *มอบหมายงานใหม่: $ticketNo*\n";
        $text .= "👤 ผู้รับผิดชอบ: $staffName\n";
        $text .= "ระดับ: $priorityIcon\n\n";
        $text .= "🛠️ รายละเอียด: " . mb_substr($description, 0, 100);

        $payload = [
            'chat_id' => TELEGRAM_CHAT_ID,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];

        $ch = curl_init('https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
