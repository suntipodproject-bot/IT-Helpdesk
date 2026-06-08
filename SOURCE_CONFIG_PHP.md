# Application Configuration (PHP - REDACTED)
ไฟล์ตั้งค่าระบบ (เซ็นเซอร์ข้อมูลส่วนตัวแล้ว)

```php
<?php
// ======================================================
// config.php — Application Configuration
// ======================================================

// Database
define('DB_HOST',     'helpdesk-db');
define('DB_PORT',     '3306');
define('DB_NAME',     'helpdesk_db');
define('DB_USER',     'helpdesk');
define('DB_PASS',     'REDACTED_PASSWORD');

// 🔔 Notification Channels
define('TELEGRAM_ENABLED',   true);
define('TELEGRAM_BOT_TOKEN', 'REDACTED_TOKEN');
define('TELEGRAM_CHAT_ID',   'REDACTED_CHAT_ID');

define('DISCORD_ENABLED',    false);
define('LINE_NOTIFY_ENABLED', false);

// App Settings
define('APP_NAME',    'IT Service Helpdesk');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost:9090');
```
