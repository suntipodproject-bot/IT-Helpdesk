<?php
// ======================================================
date_default_timezone_set('Asia/Bangkok');

// Database
define('DB_HOST',     '127.0.0.1');
define('DB_PORT',     '3306');
define('DB_NAME',     'helpdesk_db');
define('DB_USER',     'root');
define('DB_PASS',     '');

// ======================================================
// 🔔 Notification Channels (ตั้งค่าช่องทางแจ้งเตือน)
// เปิดใช้งานได้มากกว่า 1 ช่องทางพร้อมกัน
// ======================================================

// --- [1] Telegram Bot ---
define('TELEGRAM_ENABLED',  false);
define('TELEGRAM_BOT_TOKEN','YOUR_TELEGRAM_BOT_TOKEN');
define('TELEGRAM_CHAT_ID',  'YOUR_TELEGRAM_CHAT_ID');

// --- [2] Discord Webhook ---
define('DISCORD_ENABLED',       false);
define('DISCORD_WEBHOOK_URL',   'YOUR_DISCORD_WEBHOOK_URL');

// --- [3] Line Notify ---
define('LINE_NOTIFY_ENABLED',   false);
define('LINE_NOTIFY_TOKEN',     'YOUR_LINE_NOTIFY_TOKEN');

// App Settings
define('APP_NAME',    'IT Service Helpdesk');
define('APP_VERSION', '1.0.0');
define('UPLOAD_DIR',  __DIR__ . '/../uploads/');
define('BASE_URL',    'http://localhost:9092');

// Session timeout (minutes)
define('SESSION_TIMEOUT', 480); // 8 hours
