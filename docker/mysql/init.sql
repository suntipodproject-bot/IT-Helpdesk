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

-- ======================================================
-- TABLE: ticket_images (Attached Images)
-- ======================================================
CREATE TABLE IF NOT EXISTS ticket_images (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT NOT NULL,
    file_name  VARCHAR(255) NOT NULL,
    file_path  VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEED DATA: Departments (แผนก/หน่วยงานสำหรับแจ้งซ่อม)
-- ======================================================
INSERT INTO department (dept_name) VALUES 
('Clinic ARI'), ('Clinic Asthma'), ('Clinic CAPD'), ('Clinic COPD'), ('Clinic DM'), 
('Clinic DPAC'), ('Clinic HT'), ('Clinic OCC'), ('Clinic Spiro'), ('Clinic Thalessemia'), 
('Clinic Ultrasound'), ('Clinic Warfarin'), ('Clinic โรคไต'), ('Clinic19 - ANC (ฝากครรภ์)'), 
('Clinic19 - EPI (คลินิคสุขภาพเด็กดี)'), ('Clinic19 - FP (วางแผนครอบครัว)'), ('Clinic19 - TB (คลินิกวัณโรค)'), 
('Clinic22 - ARV (คลินิกยาต้าน)'), ('Clinic22 - Counselling (คลินิคให้คำปรึกษา)'), ('Clinic22 - จิตเวช'), 
('Clinic22 - ยาเสพติด (MET)'), ('Clinic22 - ยาเสพติด (บุหรี่)'), ('Clinic22 - ยาเสพติด (เหล้า)'), 
('ICU อายุรกรรม'), ('กระตุ้นพัฒนาการ(TEDA-4I)'), ('กายภาพบำบัด'), ('คลินิกโรคหัวใจ'), 
('คลินิคโรคข้อรูมาตอยด์ ( Rheumatoid )'), ('งานประชาสัมพันธ์'), ('จ่ายกลาง'), 
('ตึกกุมารเวชกรรม'), ('ตึกทารกแรกเกิด (NICU)'), ('ตึกผู้ป่วยในอายุรกรรมหญิง'), 
('ตึกผู้ป่วยใน(PP)'), ('ตึกผู้ป่วยในศัลยกรรมและออร์โธปิดิกส์'), ('ตึกผู้ป่วยในอายุรกรรมชาย'), 
('ทันตกรรม'), ('แพทย์แผนจีน'), ('แพทย์แผนไทย'), ('เวชศาสตร์และบริการด้านปฐมภูมิ'), 
('ศูนย์ประกันสุขภาพ'), ('หน้าห้องตรวจ'), ('ห้องคลอด'), ('ห้องยาผู้ป่วยนอก'), 
('ห้องยาผู้ป่วยใน'), ('ห้องฉายรังสี'), ('ห้องฉีดยา-ทำแผล'), ('ห้องชันสูตร'), 
('ห้องชำระเงินผู้ป่วยนอก'), ('ห้องชำระเงินผู้ป่วยใน'), ('ห้องตรวจ ER คุณภาพ(นอกเวลา)'), 
('ห้องตรวจ HD 2 (รพ.)'), ('ห้องตรวจ Mammogram'), ('ห้องตรวจ STD'), ('ห้องตรวจ Telemed'), 
('ห้องตรวจกุมารเวชกรรม'), ('ห้องตรวจตา'), ('ห้องตรวจนรีเวชกรรม'), ('ห้องตรวจประเมิน IQ'), 
('ห้องตรวจผู้สูงอายุ'), ('ห้องตรวจโรค 1'), ('ห้องตรวจโรค 2'), ('ห้องตรวจโรค 3'), 
('ห้องตรวจโรค 4'), ('ห้องตรวจโรค 5'), ('ห้องตรวจโรค 6'), ('ห้องตรวจศัลยกรรมกระดูก'), 
('ห้องตรวจศัลยกรรมทั่วไป'), ('ห้องตรวจอายุรกรรม'), ('ห้องบัตร'), ('ห้องผ่าตัด'), 
('ห้องฝังเข็มยาคุม'), ('ห้องฟอกเลือด'), ('ห้องสำนักงานแพทย์'), ('ห้องอุบัติเหตุ-ฉุกเฉิน');

-- SEED DATA: Position (หน่วยงาน)
-- ======================================================
INSERT INTO position (position_name) VALUES 
('NCD'), ('OPD'), ('ER'), ('Clinic19'), ('Clinic22'), ('กายภาพบำบัด'), 
('จ่ายกลาง'), ('ตึกอายุรกรรมหญิง'), ('ตึกอายุรกรรมชาย'), 
('ตึกศัลยกรรมและออร์โธปิดิกส์'), ('ตึกกุมารเวชกรรม'), ('ตึกสูตินรีเวชกรรม'), 
('ห้องคลอด'), ('ทันตกรรม'), ('แพทย์แผนจีน'), ('แพทย์แผนไทย'), 
('ศูนย์ประกันสุขภาพ'), ('สุขภาพดิจิทัล'), ('ห้องยา'), ('FM'), 
('X-ray'), ('LAB'), ('OR'), ('การเงิน'), ('ไตเทียม'), ('แพทย์');

-- ======================================================
-- SEED DATA: Users
-- Passwords are bcrypt hashes
-- admin123 → $2y$10$... | staff123 → $2y$10$...
-- ======================================================
INSERT INTO users (username, password, full_name, role, phone) VALUES
('admin',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'สมชาย ช่างไอที (หัวหน้า)', 'admin', '086-111-2222'),
('it01',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'วิชัย รักงาน', 'staff', '086-333-4444'),
('it02',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'สุดา ขยันดี', 'staff', '086-555-6666');
-- Default password for all users: "password"

-- ======================================================
-- SEED DATA: Assets
-- ======================================================
INSERT INTO assets (asset_code, asset_name, brand, model, serial_no, asset_type, department_id, warranty_until) VALUES
('PC-OPD-001', 'คอมพิวเตอร์ตั้งโต๊ะ', 'Dell', 'Optiplex 7090', 'SN-DEL-001', 'computer', 61, '2026-12-31'),
('PC-OPD-002', 'คอมพิวเตอร์ตั้งโต๊ะ', 'Dell', 'Optiplex 7090', 'SN-DEL-002', 'computer', 62, '2026-12-31'),
('PC-ER-01',   'คอมพิวเตอร์ตั้งโต๊ะ', 'HP', 'ProDesk 600',   'SN-HP-001',  'computer', 75, '2025-06-30'),
('PC-ER-02',   'คอมพิวเตอร์ตั้งโต๊ะ', 'HP', 'ProDesk 600',   'SN-HP-002',  'computer', 75, '2025-06-30'),
('PRN-PHA-01', 'เครื่องพิมพ์สติ๊กเกอร์ยา', 'Zebra', 'ZD420', 'SN-ZEB-001', 'printer',  44, '2027-03-31'),
('NB-ACC-05',  'โน้ตบุ๊ก', 'Lenovo', 'ThinkPad E14', 'SN-LEN-005', 'computer', 49, '2028-01-31');

-- ======================================================
-- SEED DATA: Sample Tickets
-- ======================================================
INSERT INTO tickets (ticket_no, reporter_name, reporter_phone, priority, department_id, asset_id, problem_description, status, assigned_to, created_at) VALUES
('TK-2605-001', 'นพ.สมศักดิ์ ใจดี', '1234', 'critical', 75, 4, 'ระบบ HIS ค้างที่หน้า Login เข้าไม่ได้ กระทบการดูแลผู้ป่วย', 'pending', NULL, NOW() - INTERVAL 10 MINUTE),
('TK-2605-002', 'ภก.วรรณา สุขใจ', '2345', 'urgent', 44, 5, 'เครื่องพิมพ์สติ๊กเกอร์ยา พิมพ์ไม่ออก กระดาษติดใน', 'ongoing', 2, NOW() - INTERVAL 45 MINUTE),
('TK-2605-003', 'นางสาวบุญมา การเงิน', '3456', 'normal', 49, 6, 'ขอติดตั้งโปรแกรมอ่าน PDF (Adobe Reader) บน Notebook', 'completed', 1, NOW() - INTERVAL 2 HOUR);

GRANT ALL PRIVILEGES ON helpdesk_db.* TO 'helpdesk'@'%';
FLUSH PRIVILEGES;
