# IT-Helpdesk Project Skills & Guidelines

> [!IMPORTANT]
> ไฟล์นี้คือคู่มือหลักสำหรับ AI Assistant (Antigravity) ในการพัฒนาและดูแลรักษาโปรเจค IT-Helpdesk
> โปรดอ่านและปฏิบัติตามกฎในนี้อย่างเคร่งครัดทุกครั้งที่ได้รับมอบหมายงาน

## 1. Project Overview & Tech Stack
- **Project Name:** IT Service Helpdesk System
- **Backend:** PHP 8.x (running in Docker)
- **Database:** MySQL 8.0 (Container: `helpdesk-db`, Port: 3306)
- **Frontend:** HTML5, Vanilla CSS, Javascript (เน้นความเร็วและไม่ต้องโหลด Library ภายนอกมากเกินไป)
- **Integrations:** Telegram Bot (Primary notification channel)
- **Environment:** Dockerized environment (Web: 9090, DB: 3308, PMA: 9091)

## 2. Coding Standards (PHP)
- **Security First:**
    - ใช้ **PDO with Prepared Statements** 100% เพื่อป้องกัน SQL Injection
    - ทำการ Sanitize input ทุกครั้งที่รับจาก $_POST หรือ $_GET
- **Connection Style:**
    - อ้างอิงค่าจาก `config.php` เสมอ
    - ใช้ `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- **Error Handling:**
    - ใช้ `try-catch` block ในส่วนที่ติดต่อกับ Database
    - ห้ามแสดง Raw Error ให้ User เห็น (ใช้ User-friendly message แทน)

## 3. UI/UX & Design Philosophy
- **Aesthetics:** "Modern, Clean, and Professional"
- **Color Palette:**
    - Primary: Blue (#2563eb)
    - Background: Light Gray (#f8fafc) หรือ Dark Mode (#1e293b)
- **Interactivity:**
    - ใช้ CSS Transitions สำหรับ Hover effects และ Modal
    - ใช้ JavaScript (Fetch API) สำหรับการอัปเดตข้อมูลแบบไม่รีเฟรชหน้า (ถ้าทำได้)
- **Responsive:** ทุกหน้าจอต้องทำงานได้ดีทั้งบน Desktop และ Mobile

## 4. Database Workflow
- **Schema Changes:**
    - หากมีการเพิ่ม Column หรือ Table ให้สร้างไฟล์ SQL ไว้ใน `docker/mysql/`
    - หรือเขียนไฟล์ PHP สั้นๆ (เช่น `add_column.php`) สำหรับรัน One-time migration
- **Naming Convention:**
    - Table: snake_case (เช่น `it_tickets`, `user_accounts`)
    - Column: snake_case

## 5. Notification & Logs
- **Telegram Notify:**
    - เมื่อมี Ticket ใหม่ หรือสถานะเปลี่ยน ให้ส่งแจ้งเตือนผ่าน Telegram Bot เสมอ
    - ใช้ฟังก์ชันใน `config.php` หรือฟังก์ชันส่วนกลางที่เตรียมไว้
- **Logging:**
    - บันทึกการเปลี่ยนแปลงสำคัญลงใน `TROUBLESHOOTING_LOG.md` หรือ `CONFIGURATION_LOG.md` หาก AI เป็นผู้ดำเนินการ

## 6. How to work with me (AI Assistant)
- **Before starting:** ตรวจสอบไฟล์ `docker-compose.yml` เพื่อดูความเชื่อมโยงของ Service
- **When writing code:** ตรวจสอบโค้ดใน `./src` ก่อนเสมอ เพื่อให้เขียนได้สอดคล้องกับโครงสร้างเดิม
- **After finish:** ทดสอบการรันผ่าน Docker และสรุปสิ่งที่ทำลงใน `README.md` หรือ Log ไฟล์
