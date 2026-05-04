# Database Schema (SQL)
ไฟล์เริ่มต้นสำหรับสร้างฐานข้อมูลและตารางต่างๆ

```sql
-- ======================================================
-- IT Service Helpdesk — Database Initialization Script
-- Auto-runs on first MySQL container startup
-- ======================================================

CREATE DATABASE IF NOT EXISTS helpdesk_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE helpdesk_db;
SET NAMES utf8mb4;

-- ======================================================
-- TABLE: department (แผนก/หน่วยงาน)
-- ======================================================
CREATE TABLE IF NOT EXISTS department (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    dept_name       VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ======================================================
-- TABLE: position (หน่วยงาน/ตำแหน่ง)
-- ======================================================
CREATE TABLE IF NOT EXISTS position (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    position_name   VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ======================================================
-- TABLE: users (IT Staff accounts)
-- ======================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    full_name   VARCHAR(100) NOT NULL,
    role        ENUM('admin','staff','user') NOT NULL DEFAULT 'user',
    position_id INT,
    phone       VARCHAR(20),
    line_token  VARCHAR(255) COMMENT 'Personal Line Notify Token',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ======================================================
-- TABLE: assets (IT Equipment)
-- ======================================================
CREATE TABLE IF NOT EXISTS assets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    asset_code      VARCHAR(50)  NOT NULL UNIQUE COMMENT 'e.g. PC-OPD-001',
    asset_name      VARCHAR(100) NOT NULL,
    brand           VARCHAR(50),
    model           VARCHAR(100),
    serial_no       VARCHAR(100),
    asset_type      ENUM('computer','printer','network','server','other') DEFAULT 'computer',
    department_id   INT,
    warranty_until  DATE,
    status          ENUM('active','maintenance','retired') DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ======================================================
-- TABLE: tickets (Repair Requests)
-- ======================================================
CREATE TABLE IF NOT EXISTS tickets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no       VARCHAR(20)  NOT NULL UNIQUE COMMENT 'e.g. TK-2605-001',
    reporter_id     INT,
    reporter_name   VARCHAR(100) NOT NULL,
    reporter_phone  VARCHAR(20),
    priority        ENUM('critical','urgent','normal') NOT NULL DEFAULT 'normal',
    department_id   INT,
    asset_id        INT,
    problem_description TEXT NOT NULL,
    status          ENUM('pending','ongoing','completed','cancelled') DEFAULT 'pending',
    assigned_to     INT COMMENT 'FK → users.id',
    note            TEXT COMMENT 'ช่างบันทึกหมายเหตุ',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at       DATETIME,
    FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE SET NULL,
    FOREIGN KEY (asset_id)      REFERENCES assets(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id)  ON DELETE SET NULL,
    FOREIGN KEY (reporter_id) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
