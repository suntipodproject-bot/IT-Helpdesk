# IT Service Helpdesk System

ระบบบริหารจัดการงานแจ้งซ่อมอุปกรณ์ไอทีและครุภัณฑ์ สำหรับหน่วยงานหรือโรงพยาบาล

## 🎯 วัตถุประสงค์ (Goal)
เพื่อลดความซ้ำซ้อนในการแจ้งซ่อม เพิ่มประสิทธิภาพในการติดตามงานของช่างไอที และเก็บสถิติการซ่อมเพื่อนำไปวิเคราะห์การใช้งานครุภัณฑ์

## 👥 ผู้ใช้งานระบบ (Target Users)
1. **General User (ผู้ใช้ทั่วไป):** แจ้งซ่อมผ่านฟอร์ม, ติดตามสถานะงานของตนเอง, สแกน QR Code ครุภัณฑ์เพื่อแจ้งซ่อม
2. **IT Staff (ช่าง/เจ้าหน้าที่):** รับงานซ่อม, อัปเดตสถานะ (กำลังซ่อม/เสร็จสิ้น), บันทึกหมายเหตุการซ่อม
3. **Admin (ผู้ดูแลระบบ):** จัดการสิทธิ์ผู้ใช้งาน (RBAC), ลบรายการที่ผิดพลาด, ดู Dashboard ภาพรวมสถิติ และส่งออกรายงาน (Excel)

## 🛠️ ฟีเจอร์สำคัญ (Key Features)
- **Smart Form:** ระบบแจ้งซ่อมที่รองรับการระบุระดับความสำคัญ (Priority) และการป้องกันการกดส่งซ้ำ (Duplicate Prevention)
- **Asset Integration:** เชื่อมโยงกับระบบครุภัณฑ์ สามารถดึงข้อมูลสเปกเครื่องได้อัตโนมัติ
- **Dashboard:** แสดงกราฟสถิติแยกตามแผนกและอาการเสียยอดฮิต (Chart.js)
- **Notification System:** แจ้งเตือนทันทีผ่าน Telegram, Discord และ Line Notify เมื่อมีเคสใหม่
- **Workflow Management:** ระบบจัดการสถานะงาน (Pending -> Ongoing -> Completed)
- **Role-Based Access Control (RBAC):** แยกเมนูการใช้งานตามระดับสิทธิ์ (Admin, Staff, User)

## 💻 เทคโนโลยีที่ใช้ (Tech Stack)
- **Backend:** PHP 8.x (Vanilla) + MariaDB/MySQL
- **Frontend:** HTML5, Tailwind CSS, JavaScript (ES6)
- **Libraries:** Chart.js (กราฟ), Tom Select (Dropdown ค้นหา), FontAwesome 6
- **Deployment:** Docker & Docker Compose