<?php
// ======================================================
// config.php — Application Configuration
// ======================================================

// Database
define('DB_HOST',     'helpdesk-db');
define('DB_PORT',     '3306');
define('DB_NAME',     'helpdesk_db');
define('DB_USER',     'helpdesk');
define('DB_PASS',     'helpdesk1234');

// ======================================================
// 🔔 Notification Channels (ตั้งค่าช่องทางแจ้งเตือน)
// เปิดใช้งานได้มากกว่า 1 ช่องทางพร้อมกัน
// ======================================================

// --- [1] Telegram Bot ---
// วิธีสร้าง:
//   1. เปิด Telegram แชทกับ @BotFather แล้วพิมพ์ /newbot
//   2. ตั้งชื่อ Bot รับ Token มา (รูปแบบ: 123456:ABCDefgh...)
//   3. เพิ่ม Bot เข้า Group/Channel แล้วพิมพ์อะไรก็ได้ใน Group
//   4. เปิด https://api.telegram.org/bot<TOKEN>/getUpdates
//      หา "chat":{"id": ...} นั่นคือ CHAT_ID (ถ้าเป็น Group จะติดลบ เช่น -123456789)
define('TELEGRAM_ENABLED',  true);                        // เปลี่ยนเป็น true เมื่อพร้อม
define('TELEGRAM_BOT_TOKEN','8419719883:AAFszjwjMApYi0vKdWJY13ssB9Awkluu68g');   // ใส่ Token จาก BotFather
define('TELEGRAM_CHAT_ID',  '-5216509360');     // ใส่ Chat ID ของ Group/Channel

// --- [2] Discord Webhook ---
// วิธีสร้าง:
//   1. เปิด Discord Server → ไปที่ Channel ที่ต้องการ
//   2. คลิก ⚙️ Edit Channel → Integrations → Webhooks → New Webhook
//   3. ตั้งชื่อ คัดลอก Webhook URL มาใส่ด้านล่าง
define('DISCORD_ENABLED',       false);                    // เปลี่ยนเป็น true เมื่อพร้อม
define('DISCORD_WEBHOOK_URL',   'YOUR_DISCORD_WEBHOOK_URL');

// --- [3] Line Notify ---
// วิธีสร้าง:
//   1. เปิด https://notify-bot.line.me/ และ Login ด้วยไอดี Line
//   2. ไปที่ My Page → Generate Token
//   3. ตั้งชื่อ Token และเลือก Group ที่ต้องการรับแจ้งเตือน
//   4. กดคัดลอก Token มาใส่ด้านล่าง และอย่าลืมดึง "Line Notify" เข้า Group
define('LINE_NOTIFY_ENABLED',   false);                    // เปลี่ยนเป็น true เมื่อพร้อม
define('LINE_NOTIFY_TOKEN',     'YOUR_LINE_NOTIFY_TOKEN');

// App Settings
define('APP_NAME',    'IT Service Helpdesk');
define('APP_VERSION', '1.0.0');
define('UPLOAD_DIR',  __DIR__ . '/../uploads/');
define('BASE_URL',    'http://localhost:9090');

// Session timeout (minutes)
define('SESSION_TIMEOUT', 480); // 8 hours
