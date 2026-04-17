# 5S Real-Time Dashboard - Rangsit

ระบบ Dashboard แสดงผลคะแนน 5S แบบ Real-time สำหรับโรงงานและสำนักงาน ออกแบบมาเพื่อแสดงผลบนหน้าจอ Monitor ขนาด 1080p อย่างสวยงามและเป็นระเบียบ

## 🌟 คุณสมบัติเด่น (Features)
- **Real-Time Display**: แสดงผลคะแนนสะสมของแต่ละพื้นที่แบบทันที
- **3-Column Layout**: จัดวางข้อมูล 30 ลำดับในหน้าเดียว ไม่ต้องสลับหน้า (Pagination)
- **Management UI**: ระบบจัดการจำนวนพนักงาน (`manage_employees.php`) ที่ใช้งานง่าย
- **Dynamic Scoring**: คำนวณคะแนนตามสัดส่วนพนักงานจริงของแต่ละพื้นที่ (Factory & Office)
- **Integrated Config**: ปรับแต่งสี ธีม และเงื่อนไขคะแนนได้ผ่าน `dashboard_config.json` และหน้า UI Settings
- **Auto-Refresh**: ระบบรีเฟรชตัวเองอัตโนมัติ และรีเฟรชใหญ่ตอน 07:30 น. ของทุกวัน

## 📂 โครงสร้างโปรเจค (Project Structure)
- `index.php`: หน้าหลักสำหรับแสดงผล Dashboard
- `manage_employees.php`: หน้าสำหรับผู้ดูแลจัดการข้อมูลพนักงาน
- `calculate_score.php`: ไฟล์หลักที่ใช้ประมวลผลคะแนน 5S
- `sqlconnect.php`: การเชื่อมต่อฐานข้อมูล SQL Server
- `dashboard_config.json`: ไฟล์เก็บการตั้งค่าธีมและเงื่อนไขการแสดงผล
- `employee_counts.json`: ไฟล์เก็บฐานข้อมูลจำนวนพนักงานในแต่ละ Area

## 🚀 การติดตั้งและใช้งาน (Setup)
1. **Database**: ตรวจสอบการเชื่อมต่อใน `sqlconnect.php` (โปรเจคนี้เชื่อมต่อกับระบบ `RSMSSQL`)
2. **Server**: รันบน Web Server ที่รองรับ PHP และ Driver สำหรับ SQL Server (sqlsrv)
3. **Configuration**: ปรับแต่งช่วงวันที่ วันหยุด และธีมได้ผ่านเมนู Settings ในหน้าจัดการ

---

### Scoring Logic
ระบบจะคำนวณจาก:
`Possible Score = Employee Count * Question Count * 5 * Working Days`
`Final Score (%) = (Actual Score / Possible Score) * 100`

---
Copyright © 2026 5S Management System - Rangsit
