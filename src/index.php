<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
$user = currentUser();
$db = getDB();

$users_list = [];
$positions = [];

if ($user['role'] === 'admin') {
    $users_list = $db->query("SELECT u.*, p.position_name FROM users u LEFT JOIN position p ON u.position_id = p.id ORDER BY u.id DESC")->fetchAll();
    $positions = $db->query("SELECT * FROM position ORDER BY position_name ASC")->fetchAll();
}

$staff_list = $db->query("SELECT id, full_name, role FROM users WHERE role IN ('admin', 'staff') AND is_active = 1 ORDER BY full_name ASC")->fetchAll();

// Fetch departments for ticket creation
$departments_list = $db->query("SELECT * FROM department ORDER BY dept_name ASC")->fetchAll();

// Fetch tickets
$ticket_query = "SELECT t.*, d.dept_name, a.asset_code, u.full_name as assignee_name 
                 FROM tickets t 
                 LEFT JOIN department d ON t.department_id = d.id 
                 LEFT JOIN assets a ON t.asset_id = a.id 
                 LEFT JOIN users u ON t.assigned_to = u.id";

if ($user['role'] === 'user') {
    $t_stmt = $db->prepare($ticket_query . " WHERE t.reporter_id = ? ORDER BY t.created_at DESC");
    $t_stmt->execute([$user['id']]);
} else {
    $t_stmt = $db->query($ticket_query . " ORDER BY t.created_at DESC");
}
$all_tickets = $t_stmt->fetchAll();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Service Helpdesk | Ocean Blue</title>

    <!-- Google Fonts: Sarabun -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Tom Select -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <!-- Tailwind Config & Custom CSS Variables -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Sarabun', 'sans-serif'],
                    },
                    colors: {
                        ocean: {
                            900: '#071324', // Deepest background
                            800: '#0f213a', // Sidebar/Nav
                            700: '#183050', // Cards
                            600: '#22426c', // Hover states
                            500: '#00b4d8', // Primary Accent (Cyan)
                            400: '#48cae4', // Secondary Accent (Light Blue)
                        },
                        status: {
                            critical: '#ff4d4d',
                            urgent: '#ff9f43',
                            normal: '#2ed573',
                            completed: '#10b981'
                        }
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --bg-main: #071324;
            --text-main: #f8f9fa;
            --text-muted: #94a3b8;
            --card-bg: rgba(24, 48, 80, 0.7);
            --card-border: rgba(0, 180, 216, 0.2);
            --primary-glow: 0 0 15px rgba(0, 180, 216, 0.5);
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            font-family: 'Sarabun', sans-serif;
            overflow-x: hidden;
        }

        /* Glassmorphism Cards */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--card-border);
            border-radius: 0.75rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--primary-glow);
            border-color: rgba(0, 180, 216, 0.5);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #0f213a;
        }

        ::-webkit-scrollbar-thumb {
            background: #22426c;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #00b4d8;
        }

        /* Form Inputs */
        .dark-input {
            background-color: rgba(15, 33, 58, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
        }

        .dark-input:focus {
            outline: none;
            border-color: #00b4d8;
            box-shadow: 0 0 0 2px rgba(0, 180, 216, 0.25);
        }

        /* Status Badges */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .badge-critical {
            background: rgba(255, 77, 77, 0.2);
            color: #ff4d4d;
            border: 1px solid rgba(255, 77, 77, 0.3);
        }

        .badge-urgent {
            background: rgba(255, 159, 67, 0.2);
            color: #ff9f43;
            border: 1px solid rgba(255, 159, 67, 0.3);
        }

        .badge-normal {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
            border: 1px solid rgba(46, 213, 115, 0.3);
        }

        .badge-status-pending {
            background: rgba(255, 159, 67, 0.1);
            color: #ff9f43;
        }

        .badge-status-progress {
            background: rgba(0, 180, 216, 0.1);
            color: #00b4d8;
        }

        .badge-status-done {
            background: rgba(46, 213, 115, 0.1);
            color: #2ed573;
        }

        /* Loader */
        .loader {
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            border-top: 3px solid #00b4d8;
            width: 24px;
            height: 24px;
            -webkit-animation: spin 1s linear infinite;
            /* Safari */
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #183050;
            border-left: 4px solid #00b4d8;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 50;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* Main Layout constraints */
        .app-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .main-content {
            flex: 1;
            overflow-y: auto;
            padding-bottom: 2rem;
        }
        /* Tom Select Dark Theme Overrides */
        .ts-control {
            background: rgba(13, 31, 56, 0.7) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 0.5rem !important;
            color: white !important;
            padding: 0.625rem 0.75rem 0.625rem 2.5rem !important; /* pl-10 to match icons */
        }
        .ts-dropdown {
            background: #0f2746 !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }
        .ts-dropdown .active {
            background: #00b4d8 !important;
            color: white !important;
        }
        .ts-dropdown .option {
            padding: 8px 12px !important;
        }
        .ts-control input {
            color: white !important;
        }
        .ts-wrapper.single .ts-control:after {
            border-color: #94a3b8 transparent transparent transparent !important;
        }
        .ts-wrapper.single.input-active .ts-control:after {
            border-color: transparent transparent #94a3b8 transparent !important;
        }
        .ts-wrapper .ts-control {
            font-size: 0.875rem !important;
        }
    </style>
</head>

<body class="antialiased selection:bg-ocean-500 selection:text-white">

    <div class="app-container flex flex-col md:flex-row">

        <!-- Sidebar Navigation (Desktop) / Bottom Nav (Mobile) -->
        <aside
            class="w-full md:w-64 bg-ocean-800 border-b md:border-b-0 md:border-r border-white/10 flex flex-col justify-between shrink-0 z-20">
            <div>
                <!-- Logo -->
                <div class="h-16 flex items-center px-6 border-b border-white/10">
                    <i class="fa-solid fa-microchip text-ocean-500 text-2xl mr-3"></i>
                    <h1
                        class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-ocean-400 to-white">
                        IT Helpdesk</h1>
                </div>

                <!-- Nav Links -->
                <nav class="p-4 space-y-2 flex flex-row md:flex-col overflow-x-auto md:overflow-visible">
                    <?php if ($user['role'] !== 'user'): ?>
                    <button onclick="switchView('dashboard')"
                        class="nav-btn w-full flex items-center p-3 rounded-lg <?= $user['role'] !== 'user' ? 'text-white bg-ocean-700 border-l-4 border-ocean-500' : 'text-text-muted' ?> hover:text-white hover:bg-ocean-700 transition-colors active-nav"
                        data-target="dashboard">
                        <i class="fa-solid fa-chart-pie w-6"></i>
                        <span class="ml-3 hidden md:inline">แดชบอร์ด</span>
                    </button>
                    <?php endif; ?>

                    <button onclick="switchView('create-ticket')"
                        class="nav-btn w-full flex items-center p-3 rounded-lg <?= $user['role'] === 'user' ? 'text-white bg-ocean-700 border-l-4 border-ocean-500 active-nav' : 'text-text-muted' ?> hover:text-white hover:bg-ocean-700 transition-colors"
                        data-target="create-ticket">
                        <i class="fa-solid fa-plus-circle w-6"></i>
                        <span class="ml-3 hidden md:inline">แจ้งซ่อมใหม่</span>
                    </button>

                    <button onclick="switchView('ticket-list')"
                        class="nav-btn w-full flex items-center p-3 rounded-lg text-text-muted hover:text-white hover:bg-ocean-700 transition-colors"
                        data-target="ticket-list">
                        <i class="fa-solid fa-list-check w-6"></i>
                        <span class="ml-3 hidden md:inline"><?= $user['role'] === 'user' ? 'แจ้งซ่อมของฉัน' : 'รายการแจ้งซ่อม' ?></span>
                    </button>

                    <?php if ($user['role'] !== 'user'): ?>
                    <button onclick="switchView('my-jobs')"
                        class="nav-btn w-full flex items-center p-3 rounded-lg text-text-muted hover:text-white hover:bg-ocean-700 transition-colors"
                        data-target="my-jobs">
                        <i class="fa-solid fa-briefcase w-6"></i>
                        <span class="ml-3 hidden md:inline">งานของฉัน</span>
                    </button>
                    <?php endif; ?>

                    <?php if ($user['role'] !== 'user'): ?>
                    <button onclick="switchView('assets')"
                        class="nav-btn w-full flex items-center p-3 rounded-lg text-text-muted hover:text-white hover:bg-ocean-700 transition-colors"
                        data-target="assets">
                        <i class="fa-solid fa-server w-6"></i>
                        <span class="ml-3 hidden md:inline">ระบบครุภัณฑ์</span>
                    </button>
                    <?php endif; ?>

                    <?php if ($user['role'] === 'admin'): ?>
                    <button onclick="switchView('users')"
                        class="nav-btn w-full flex items-center p-3 rounded-lg text-text-muted hover:text-white hover:bg-ocean-700 transition-colors"
                        data-target="users">
                        <i class="fa-solid fa-users-gear w-6"></i>
                        <span class="ml-3 hidden md:inline">จัดการผู้ใช้งาน</span>
                    </button>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- User Profile (Bottom of Sidebar) -->
            <div class="p-4 border-t border-white/10 hidden md:block">
                <a href="/logout.php" class="flex items-center p-2 rounded-lg hover:bg-red-900/30 cursor-pointer transition-colors group" title="ออกจากระบบ">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-ocean-500 to-blue-300 flex items-center justify-center text-white font-bold text-sm">
                        <?= mb_substr($user['full_name'], 0, 2) ?>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-white"><?= htmlspecialchars($user['full_name']) ?></p>
                        <p class="text-xs text-text-muted"><?= $user['role'] === 'admin' ? 'หัวหน้า / Admin' : ($user['role'] === 'staff' ? 'เจ้าหน้าที่ IT' : 'ผู้ใช้งานทั่วไป') ?></p>
                    </div>
                    <i class="fa-solid fa-right-from-bracket ml-auto text-xs text-text-muted group-hover:text-red-400 transition-colors"></i>
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main
            class="main-content relative bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-ocean-800 via-ocean-900 to-black">

            <!-- Mobile Header (Visible only on mobile) -->
            <header
                class="md:hidden flex items-center justify-between p-4 border-b border-white/10 bg-ocean-800 sticky top-0 z-10">
                <span class="font-bold text-lg" id="mobile-page-title">แดชบอร์ด</span>
                <div class="w-8 h-8 rounded-full bg-ocean-500 flex items-center justify-center text-white text-sm">สม
                </div>
            </header>

            <div class="p-4 md:p-8 max-w-7xl mx-auto">

                <!-- Loading Overlay -->
                <div id="page-loader"
                    class="hidden absolute inset-0 bg-ocean-900/80 backdrop-blur-sm z-50 flex flex-col items-center justify-center rounded-xl">
                    <div class="loader mb-4"></div>
                    <p class="text-ocean-400 font-medium tracking-wider">กำลังโหลดข้อมูล...</p>
                </div>

                <!-- ========================================== -->
                <!-- VIEW 1: DASHBOARD -->
                <!-- ========================================== -->
                <section id="view-dashboard" class="view-section <?= $user['role'] !== 'user' ? 'active' : 'hidden' ?> space-y-6">
                    <div class="flex justify-between items-end mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-white mb-1">ภาพรวมระบบแจ้งซ่อม</h2>
                            <p class="text-text-muted text-sm">ข้อมูลสรุปประจำเดือน พฤษภาคม 2026</p>
                        </div>
                        <a href="/api/export.php"
                            class="bg-ocean-700 hover:bg-ocean-600 text-white px-4 py-2 rounded-lg text-sm border border-white/10 transition-colors flex items-center gap-2">
                            <i class="fa-solid fa-download"></i> Export Excel
                        </a>
                    </div>

                    <!-- Stat Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                        <div class="glass-card p-5 border-l-4 border-l-ocean-500">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-text-muted text-sm font-medium mb-1">งานทั้งหมด (เดือนนี้)</p>
                                    <h3 class="text-3xl font-bold text-white">142</h3>
                                </div>
                                <div
                                    class="w-10 h-10 rounded-lg bg-ocean-500/20 flex items-center justify-center text-ocean-400">
                                    <i class="fa-solid fa-ticket"></i>
                                </div>
                            </div>
                            <p class="text-xs text-status-normal mt-3"><i class="fa-solid fa-arrow-up mr-1"></i> 12%
                                จากเดือนที่แล้ว</p>
                        </div>

                        <div class="glass-card p-5 border-l-4 border-l-status-urgent">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-text-muted text-sm font-medium mb-1">รอดำเนินการ</p>
                                    <h3 class="text-3xl font-bold text-white">12</h3>
                                </div>
                                <div
                                    class="w-10 h-10 rounded-lg bg-status-urgent/20 flex items-center justify-center text-status-urgent">
                                    <i class="fa-solid fa-clock"></i>
                                </div>
                            </div>
                            <p class="text-xs text-text-muted mt-3">วิกฤต: 2 | เร่งด่วน: 4</p>
                        </div>

                        <div class="glass-card p-5 border-l-4 border-l-status-completed">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-text-muted text-sm font-medium mb-1">ซ่อมเสร็จสิ้น</p>
                                    <h3 class="text-3xl font-bold text-white">125</h3>
                                </div>
                                <div
                                    class="w-10 h-10 rounded-lg bg-status-completed/20 flex items-center justify-center text-status-completed">
                                    <i class="fa-solid fa-check-circle"></i>
                                </div>
                            </div>
                            <p class="text-xs text-text-muted mt-3">อัตราความสำเร็จ 88%</p>
                        </div>

                        <div class="glass-card p-5 border-l-4 border-l-status-normal">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-text-muted text-sm font-medium mb-1">SLA Achievement</p>
                                    <h3 class="text-3xl font-bold text-white">94%</h3>
                                </div>
                                <div
                                    class="w-10 h-10 rounded-lg bg-status-normal/20 flex items-center justify-center text-status-normal">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </div>
                            </div>
                            <p class="text-xs text-status-normal mt-3">ซ่อมทันเวลาตามเกณฑ์</p>
                        </div>
                    </div>

                    <!-- Charts Area -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                        <div class="glass-card p-5 lg:col-span-2">
                            <h3 class="text-lg font-semibold text-white mb-4">สถิติการแจ้งซ่อมแยกตามแผนก</h3>
                            <div class="h-64 w-full relative">
                                <canvas id="deptChart"></canvas>
                            </div>
                        </div>
                        <div class="glass-card p-5">
                            <h3 class="text-lg font-semibold text-white mb-4">สัดส่วนอาการเสีย</h3>
                            <div class="h-64 w-full relative flex justify-center">
                                <canvas id="issueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================== -->
                <!-- VIEW 2: CREATE TICKET -->
                <!-- ========================================== -->
                <section id="view-create-ticket" class="view-section <?= $user['role'] === 'user' ? 'active' : 'hidden' ?> space-y-6">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-white mb-1">แจ้งซ่อมอุปกรณ์ไอที</h2>
                        <p class="text-text-muted text-sm">กรอกข้อมูลรายละเอียดเพื่อให้ช่างดำเนินการแก้ไข</p>
                    </div>

                    <div class="glass-card p-6 md:p-8 max-w-3xl mx-auto">
                        <form id="createTicketForm" onsubmit="handleFormSubmit(event)">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- ผู้แจ้ง -->
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-text-muted block">ชื่อผู้แจ้ง <span class="text-status-critical">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ocean-400">
                                            <i class="fa-solid fa-user"></i>
                                        </div>
                                        <input type="text" name="reporter_name" required
                                            class="dark-input w-full pl-10 pr-3 py-2.5 rounded-lg text-sm"
                                            value="<?= htmlspecialchars($user['full_name']) ?>"
                                            placeholder="ระบุชื่อ-นามสกุล">
                                    </div>
                                </div>

                                <!-- เบอร์ติดต่อ -->
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-text-muted block">เบอร์ติดต่อกลับ <span class="text-status-critical">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ocean-400">
                                            <i class="fa-solid fa-phone"></i>
                                        </div>
                                        <input type="text" name="reporter_phone" required
                                            class="dark-input w-full pl-10 pr-3 py-2.5 rounded-lg text-sm"
                                            value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                            placeholder="ระบุเบอร์ภายในหรือเบอร์ส่วนตัว">
                                    </div>
                                </div>

                                <!-- ระดับความสำคัญ -->
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-text-muted block">ระดับความสำคัญ (Priority)
                                        <span class="text-status-critical">*</span></label>
                                    <div class="relative">
                                        <div
                                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ocean-400">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                        </div>
                                        <select name="priority" required
                                            class="dark-input w-full pl-10 pr-3 py-2.5 rounded-lg text-sm appearance-none cursor-pointer">
                                            <option value="" disabled selected>เลือกระดับความสำคัญ...</option>
                                            <option value="critical" class="bg-ocean-800 text-status-critical">🔴 วิกฤต
                                                (ระบบล่ม, กระทบการรักษา)</option>
                                            <option value="urgent" class="bg-ocean-800 text-status-urgent">🟠 เร่งด่วน
                                                (ทำงานไม่ได้, ไม่มีเครื่องสำรอง)</option>
                                            <option value="normal" class="bg-ocean-800 text-status-normal">🟢 ปกติ
                                                (ทำงานช้า, ปรึกษาการใช้งาน)</option>
                                        </select>
                                        <div
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-text-muted">
                                            <i class="fa-solid fa-chevron-down text-xs"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- สถานที่ / แผนก -->
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium text-text-muted block">สถานที่ / แผนก (เลือกห้องตรวจ/หน่วยงาน) <span
                                            class="text-status-critical">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ocean-400">
                                            <i class="fa-solid fa-hospital"></i>
                                        </div>
                                        <select name="department_id" id="deptSelect" required
                                            class="dark-input w-full pl-10 pr-3 py-2.5 rounded-lg text-sm appearance-none cursor-pointer">
                                            <option value="" disabled selected>เลือกสถานที่/แผนกของคุณ...</option>
                                            <?php foreach ($departments_list as $dept): ?>
                                                <option value="<?= $dept['id'] ?>" class="bg-ocean-800"><?= htmlspecialchars($dept['dept_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-text-muted">
                                            <i class="fa-solid fa-chevron-down text-xs"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- เลขครุภัณฑ์ / QR -->
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium text-text-muted flex justify-between">
                                        <span>รหัสอุปกรณ์ / เลขครุภัณฑ์ (Asset ID)</span>
                                        <span class="text-xs text-ocean-400">ค้นหาประวัติอัตโนมัติ</span>
                                    </label>
                                    <div class="flex gap-2">
                                        <div class="relative flex-1">
                                            <div
                                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ocean-400">
                                                <i class="fa-solid fa-barcode"></i>
                                            </div>
                                            <input type="text" id="assetIdInput" name="asset_code_display"
                                                class="dark-input w-full pl-10 pr-3 py-2.5 rounded-lg text-sm"
                                                placeholder="เช่น PC-OPD-001 หรือสแกน QR">
                                            <input type="hidden" name="asset_id" id="hiddenAssetId">
                                        </div>
                                        <button type="button" onclick="simulateQRScan()"
                                            class="bg-ocean-700 hover:bg-ocean-600 text-white px-4 rounded-lg border border-white/10 transition-colors flex items-center justify-center"
                                            title="สแกน QR Code">
                                            <i class="fa-solid fa-qrcode text-lg"></i>
                                        </button>
                                    </div>
                                    <p id="assetInfo" class="text-xs text-status-normal hidden mt-1"><i
                                            class="fa-solid fa-circle-check mr-1"></i> พบข้อมูล: Dell Optiplex 7090
                                        (หมดประกัน 12/2026)</p>
                                </div>

                                <!-- อาการเสีย -->
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium text-text-muted block">รายละเอียดอาการเสีย <span
                                            class="text-status-critical">*</span></label>
                                    <textarea name="description" required rows="4" class="dark-input w-full px-3 py-2.5 rounded-lg text-sm"
                                        placeholder="อธิบายอาการที่พบ เช่น เปิดเครื่องไม่ติด มีเสียงร้อง, ปริ้นเตอร์กระดาษติด..."></textarea>
                                </div>

                                <!-- อัปโหลดรูปภาพ -->
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium text-text-muted block">แนบรูปภาพประกอบ
                                        (ถ้ามี)</label>
                                    <div
                                        class="border-2 border-dashed border-white/20 rounded-lg p-6 text-center hover:border-ocean-500 transition-colors cursor-pointer bg-ocean-900/50">
                                        <i class="fa-solid fa-cloud-arrow-up text-3xl text-ocean-400 mb-2"></i>
                                        <p class="text-sm text-white font-medium">คลิกเพื่ออัปโหลด หรือลากไฟล์มาวาง</p>
                                        <p class="text-xs text-text-muted mt-1">รองรับ JPG, PNG สูงสุด 5MB</p>
                                        <input type="file" class="hidden" accept="image/*" multiple>
                                    </div>
                                </div>

                            </div>

                            <div class="mt-8 pt-6 border-t border-white/10 flex justify-end gap-3">
                                <button type="button"
                                    class="px-5 py-2.5 rounded-lg text-sm font-medium text-text-muted hover:text-white transition-colors">
                                    ยกเลิก
                                </button>
                                <button type="submit" id="submitBtn"
                                    class="bg-ocean-500 hover:bg-ocean-400 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition-all shadow-[0_0_15px_rgba(0,180,216,0.3)] hover:shadow-[0_0_20px_rgba(0,180,216,0.5)] flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fa-solid fa-paper-plane mr-2" id="submitIcon"></i>
                                    <span id="submitText">ส่งเรื่องแจ้งซ่อม</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <!-- ========================================== -->
                <!-- VIEW 3: TICKET LIST (Workflow) -->
                <!-- ========================================== -->
                <section id="view-ticket-list" class="view-section hidden space-y-6">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-white mb-1">รายการแจ้งซ่อม (Workflow)</h2>
                            <p class="text-text-muted text-sm">จัดการสถานะและมอบหมายงาน</p>
                        </div>
                        <div class="flex gap-2 w-full md:w-auto">
                            <div class="relative flex-1 md:w-64">
                                <div
                                    class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                                    <i class="fa-solid fa-search"></i>
                                </div>
                                <input type="text" class="dark-input w-full pl-10 pr-3 py-2 rounded-lg text-sm"
                                    placeholder="ค้นหาเลขที่, อาการ...">
                            </div>
                            <button
                                class="bg-ocean-700 p-2 rounded-lg text-white border border-white/10 hover:bg-ocean-600 transition-colors">
                                <i class="fa-solid fa-filter"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Tickets Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php foreach ($all_tickets as $tk): 
                            $priorityClass = $tk['priority'] === 'critical' ? 'critical' : ($tk['priority'] === 'urgent' ? 'urgent' : 'normal');
                            $priorityText = $tk['priority'] === 'critical' ? 'วิกฤต' : ($tk['priority'] === 'urgent' ? 'เร่งด่วน' : 'ปกติ');
                            
                            $statusClass = 'badge-status-pending';
                            $statusText = 'รอดำเนินการ';
                            if ($tk['status'] === 'ongoing') {
                                $statusClass = 'badge-status-progress';
                                $statusText = 'กำลังซ่อม';
                            } else if ($tk['status'] === 'completed') {
                                $statusClass = 'badge-status-completed';
                                $statusText = 'ซ่อมเสร็จสิ้น';
                            } else if ($tk['status'] === 'cancelled') {
                                $statusClass = 'bg-red-500/20 text-red-400';
                                $statusText = 'ยกเลิก';
                            }
                        ?>
                        <!-- Ticket Card -->
                        <div class="glass-card p-4 relative overflow-hidden group">
                            <div class="absolute top-0 left-0 w-1 h-full bg-status-<?= $priorityClass ?>"></div>
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <span class="text-xs text-text-muted font-mono"><?= htmlspecialchars($tk['ticket_no']) ?></span>
                                    <h3 class="text-white font-semibold mt-1 truncate max-w-[200px]"><?= htmlspecialchars($tk['problem_description']) ?></h3>
                                </div>
                                <span class="badge badge-<?= $priorityClass ?>"><?= $priorityText ?></span>
                             </div>
                             <?php if ($user['role'] === 'admin'): ?>
                             <button onclick="deleteTicket(<?= $tk['id'] ?>)" class="absolute top-2 right-2 text-text-muted hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100 p-1" title="ลบรายการ">
                                 <i class="fa-solid fa-trash-can"></i>
                             </button>
                             <?php endif; ?>
                            <div class="space-y-2 text-sm text-text-muted mb-4">
                                <p><i class="fa-solid fa-location-dot w-5 text-center"></i> <?= htmlspecialchars($tk['dept_name'] ?? 'ไม่ระบุ') ?></p>
                                <p><i class="fa-solid fa-desktop w-5 text-center"></i> <?= htmlspecialchars($tk['asset_code'] ?? 'ไม่มีข้อมูลครุภัณฑ์') ?></p>
                                <p><i class="fa-solid fa-clock w-5 text-center"></i> แจ้งเมื่อ: <?= date('d/m/Y H:i', strtotime($tk['created_at'])) ?></p>
                            </div>
                            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                                <div class="flex items-center gap-2">
                                    <span class="<?= $statusClass ?> px-2 py-1 rounded text-xs font-medium">
                                        <?php if ($tk['status'] === 'ongoing'): ?><i class="fa-solid fa-spinner fa-spin mr-1"></i><?php endif; ?>
                                        <?= $statusText ?>
                                    </span>
                                </div>
                                
                                <?php if ($user['role'] !== 'user' && $tk['status'] === 'pending'): ?>
                                <select onchange="assignTicket(<?= $tk['id'] ?>, this.value)"
                                    class="bg-ocean-800 text-xs text-white border border-white/10 rounded px-2 py-1 focus:outline-none focus:border-ocean-500">
                                    <option>จ่ายงาน...</option>
                                    <?php foreach ($users_list as $staff): if ($staff['role'] !== 'user'): ?>
                                    <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['full_name']) ?></option>
                                    <?php endif; endforeach; ?>
                                </select>
                                <?php elseif ($tk['assignee_name']): ?>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center">
                                        <?= mb_substr($tk['assignee_name'], 0, 1) ?>
                                    </div>
                                    <span class="text-xs text-text-muted"><?= htmlspecialchars($tk['assignee_name']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (false): // Hidden old static content ?>

                        <!-- Ticket Card 1 (Critical) -->
                        <div class="glass-card p-4 relative overflow-hidden group">
                            <div class="absolute top-0 left-0 w-1 h-full bg-status-critical"></div>
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <span class="text-xs text-text-muted font-mono">TK-2605-001</span>
                                    <h3 class="text-white font-semibold mt-1">ระบบ HIS ค้างหน้าล็อคอิน</h3>
                                </div>
                                <span class="badge badge-critical">วิกฤต</span>
                            </div>
                            <div class="space-y-2 text-sm text-text-muted mb-4">
                                <p><i class="fa-solid fa-location-dot w-5 text-center"></i> ห้องฉุกเฉิน (ER)</p>
                                <p><i class="fa-solid fa-desktop w-5 text-center"></i> PC-ER-02</p>
                                <p><i class="fa-solid fa-clock w-5 text-center"></i> แจ้งเมื่อ: 10 นาทีที่แล้ว (SLA: 1
                                    ชม.)</p>
                            </div>
                            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="badge-status-pending px-2 py-1 rounded text-xs font-medium">รอดำเนินการ</span>
                                </div>
                                <!-- Assignment Dropdown -->
                                <select
                                    class="bg-ocean-800 text-xs text-white border border-white/10 rounded px-2 py-1 focus:outline-none focus:border-ocean-500">
                                    <option>จ่ายงานให้...</option>
                                    <option value="it01">IT-01 (สมชาย)</option>
                                    <option value="it02">IT-02 (วิชัย)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Ticket Card 2 (Urgent) -->
                        <div class="glass-card p-4 relative overflow-hidden group">
                            <div class="absolute top-0 left-0 w-1 h-full bg-status-urgent"></div>
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <span class="text-xs text-text-muted font-mono">TK-2605-002</span>
                                    <h3 class="text-white font-semibold mt-1">ปริ้นเตอร์สติ๊กเกอร์ยาพิมพ์ไม่ออก</h3>
                                </div>
                                <span class="badge badge-urgent">เร่งด่วน</span>
                            </div>
                            <div class="space-y-2 text-sm text-text-muted mb-4">
                                <p><i class="fa-solid fa-location-dot w-5 text-center"></i> ห้องจ่ายยา (OPD)</p>
                                <p><i class="fa-solid fa-print w-5 text-center"></i> PRN-PHA-01</p>
                                <p><i class="fa-solid fa-clock w-5 text-center"></i> แจ้งเมื่อ: 45 นาทีที่แล้ว (SLA: 2
                                    ชม.)</p>
                            </div>
                            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                                <div class="flex items-center gap-2">
                                    <span class="badge-status-progress px-2 py-1 rounded text-xs font-medium"><i
                                            class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังซ่อม</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center"
                                        title="IT-02 วิชัย">ว</div>
                                    <span class="text-xs text-text-muted">ผู้รับผิดชอบ</span>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket Card 3 (Normal) -->
                        <div class="glass-card p-4 relative overflow-hidden group">
                            <div class="absolute top-0 left-0 w-1 h-full bg-status-normal"></div>
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <span class="text-xs text-text-muted font-mono">TK-2605-003</span>
                                    <h3 class="text-white font-semibold mt-1">ขอติดตั้งโปรแกรมอ่าน PDF</h3>
                                </div>
                                <span class="badge badge-normal">ปกติ</span>
                            </div>
                            <div class="space-y-2 text-sm text-text-muted mb-4">
                                <p><i class="fa-solid fa-location-dot w-5 text-center"></i> แผนกบัญชี</p>
                                <p><i class="fa-solid fa-desktop w-5 text-center"></i> NB-ACC-05</p>
                                <p><i class="fa-solid fa-clock w-5 text-center"></i> แจ้งเมื่อ: 2 ชม.ที่แล้ว (SLA: 24
                                    ชม.)</p>
                            </div>
                            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                                <div class="flex items-center gap-2">
                                    <span class="badge-status-done px-2 py-1 rounded text-xs font-medium"><i
                                            class="fa-solid fa-check mr-1"></i> ซ่อมเสร็จสิ้น</span>
                                </div>
                                <button class="text-xs text-ocean-400 hover:text-white transition-colors">ดูรายละเอียด
                                    <i class="fa-solid fa-arrow-right ml-1"></i></button>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </section>

                <!-- ========================================== -->
                <!-- VIEW 5: USER MANAGEMENT (Admin Only) -->
                <!-- ========================================== -->
                <?php if ($user['role'] === 'admin'): ?>
                <section id="view-users" class="view-section hidden space-y-6">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-white mb-1">จัดการผู้ใช้งานระบบ</h2>
                            <p class="text-text-muted text-sm">ตรวจสอบและแก้ไขสิทธิ์การใช้งานของสมาชิก</p>
                        </div>
                        <button onclick="openAddUserModal()"
                            class="bg-ocean-500 hover:bg-ocean-400 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all shadow-[0_0_15px_rgba(0,180,216,0.3)] flex items-center gap-2">
                            <i class="fa-solid fa-user-plus"></i> เพิ่มผู้ใช้งาน
                        </button>
                    </div>

                    <div class="glass-card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-ocean-800 text-text-muted text-xs uppercase tracking-wider">
                                    <tr>
                                        <th class="px-6 py-4 font-semibold">ชื่อ-นามสกุล</th>
                                        <th class="px-6 py-4 font-semibold">หน่วยงาน/แผนก</th>
                                        <th class="px-6 py-4 font-semibold">ระดับสิทธิ์</th>
                                        <th class="px-6 py-4 font-semibold text-center">สถานะ</th>
                                        <th class="px-6 py-4 font-semibold text-right">ดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    <?php foreach ($users_list as $u): ?>
                                    <tr class="hover:bg-white/5 transition-colors group" id="user-row-<?= $u['id'] ?>">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-ocean-600 flex items-center justify-center text-white text-xs font-bold">
                                                    <?= mb_substr($u['full_name'], 0, 1) ?>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-white"><?= htmlspecialchars($u['full_name']) ?></p>
                                                    <p class="text-xs text-text-muted">@<?= htmlspecialchars($u['username']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-text-muted">
                                            <?= htmlspecialchars($u['position_name'] ?? 'ไม่ระบุ') ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <select onchange="updateUserRole(<?= $u['id'] ?>, this.value)" 
                                                    class="bg-ocean-800 text-xs text-white border border-white/10 rounded px-2 py-1 focus:outline-none focus:border-ocean-500">
                                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User (ผู้ใช้ทั่วไป)</option>
                                                <option value="staff" <?= $u['role'] === 'staff' ? 'selected' : '' ?>>Staff (เจ้าหน้าที่ IT)</option>
                                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin (ผู้ดูแลระบบ)</option>
                                            </select>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" <?= $u['is_active'] ? 'checked' : '' ?> 
                                                       onchange="toggleUserStatus(<?= $u['id'] ?>, this.checked)"
                                                       class="sr-only peer">
                                                <div class="w-11 h-6 bg-ocean-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-status-completed"></div>
                                            </label>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <button onclick="resetPassword(<?= $u['id'] ?>)" class="text-ocean-400 hover:text-white transition-colors text-xs">
                                                <i class="fa-solid fa-key mr-1"></i> Reset Pass
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- VIEW 6: MY JOBS (Staff/Admin Only) -->
                <!-- ========================================== -->
                <?php if ($user['role'] !== 'user'): ?>
                <section id="view-my-jobs" class="view-section hidden space-y-6">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-white mb-1">งานที่ได้รับมอบหมาย</h2>
                        <p class="text-text-muted text-sm">รายการซ่อมที่คุณเป็นผู้รับผิดชอบ</p>
                    </div>
                    <div id="my-jobs-container" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                        <!-- Content loaded via JS -->
                    </div>
                </section>
                <?php endif; ?>
                
                <!-- ========================================== -->
                <!-- VIEW 4: ASSETS (Placeholder) -->
                <!-- ========================================== -->
                <section id="view-assets" class="view-section hidden space-y-6">
                    <div class="text-center py-20">
                        <div
                            class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-ocean-800 text-ocean-500 mb-4 shadow-[0_0_30px_rgba(0,180,216,0.2)]">
                            <i class="fa-solid fa-server text-4xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-2">ระบบจัดการครุภัณฑ์ไอที (Asset Management)</h2>
                        <p class="text-text-muted max-w-lg mx-auto">หน้าจอนี้สำหรับเชื่อมโยงฐานข้อมูลครุภัณฑ์,
                            ดูสเปกเครื่อง, วันหมดประกัน และประวัติการซ่อมย้อนหลัง (Service History)
                            เพื่อวิเคราะห์การแทงจำหน่าย</p>
                        <button onclick="switchView('dashboard')"
                            class="mt-6 bg-ocean-700 hover:bg-ocean-600 text-white px-6 py-2 rounded-lg text-sm border border-white/10 transition-colors">
                            กลับไปหน้าหลัก
                        </button>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Modal: Add/Edit User -->
    <div id="userModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
        <div class="glass-card w-full max-w-lg overflow-hidden animate-in fade-in zoom-in duration-300">
            <div class="p-6 border-b border-white/10 flex justify-between items-center">
                <h3 class="text-lg font-bold text-white" id="userModalTitle">เพิ่มผู้ใช้งานใหม่</h3>
                <button onclick="closeUserModal()" class="text-text-muted hover:text-white transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <form id="userForm" onsubmit="handleUserSubmit(event)" class="p-6 space-y-4">
                <input type="hidden" name="id" id="user_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-text-muted">Username</label>
                        <input type="text" name="username" id="user_username" required class="dark-input w-full px-3 py-2 rounded-lg text-sm" placeholder="เช่น it01">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-text-muted">ชื่อ-นามสกุล</label>
                        <input type="text" name="full_name" id="user_full_name" required class="dark-input w-full px-3 py-2 rounded-lg text-sm" placeholder="ชื่อ สกุล">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-text-muted">ระดับสิทธิ์</label>
                        <select name="role" id="user_role" required class="dark-input w-full px-3 py-2 rounded-lg text-sm">
                            <option value="user">User (ผู้ใช้ทั่วไป)</option>
                            <option value="staff">Staff (เจ้าหน้าที่ IT)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-text-muted">หน่วยงาน/ตำแหน่ง</label>
                        <select name="position_id" id="user_position_id" class="dark-input w-full px-3 py-2 rounded-lg text-sm">
                            <option value="">ไม่ระบุ</option>
                            <?php foreach ($positions as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['position_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-1 md:col-span-2">
                        <label class="text-xs font-medium text-text-muted">เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" id="user_phone" class="dark-input w-full px-3 py-2 rounded-lg text-sm" placeholder="08x-xxxxxxx">
                    </div>
                </div>
                <div class="pt-4 border-t border-white/10 flex justify-end gap-3">
                    <button type="button" onclick="closeUserModal()" class="px-4 py-2 rounded-lg text-sm text-text-muted hover:text-white transition-colors">ยกเลิก</button>
                    <button type="submit" class="bg-ocean-500 hover:bg-ocean-400 text-white px-6 py-2 rounded-lg text-sm font-bold transition-all">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast flex items-center">
        <i class="fa-solid fa-circle-check text-status-normal text-xl mr-3"></i>
        <div>
            <h4 class="font-bold text-sm">สำเร็จ!</h4>
            <p id="toast-message" class="text-xs text-text-muted mt-0.5">บันทึกข้อมูลเรียบร้อยแล้ว แจ้งเตือนผ่าน Line
                แล้ว</p>
        </div>
    </div>

    <script>
        // --- User Management Actions ---
        async function updateUserRole(userId, newRole) {
            try {
                const response = await fetch('/api/update_user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ userId, role: newRole })
                });
                const result = await response.json();
                if (result.success) {
                    showToast('อัปเดตสิทธิ์ผู้ใช้งานเรียบร้อยแล้ว');
                } else {
                    alert('เกิดข้อผิดพลาด: ' + result.error);
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function toggleUserStatus(userId, isActive) {
            try {
                const response = await fetch('/api/update_user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ userId, is_active: isActive ? 1 : 0 })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(isActive ? 'เปิดใช้งานบัญชีแล้ว' : 'ปิดใช้งานบัญชีแล้ว');
                } else {
                    alert('เกิดข้อผิดพลาด: ' + result.error);
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function resetPassword(userId) {
            if (!confirm('ยืนยันการรีเซ็ตรหัสผ่านเป็น "password" หรือไม่?')) return;
            try {
                const response = await fetch('/api/update_user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ userId, reset_password: true })
                });
                const result = await response.json();
                if (result.success) {
                    alert('รีเซ็ตรหัสผ่านเป็น "password" เรียบร้อยแล้ว');
                } else {
                    alert('เกิดข้อผิดพลาด: ' + result.error);
                }
            } catch (err) {
                console.error(err);
            }
        }

        // Initialize first view
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize Tom Select
            if (document.getElementById('deptSelect')) {
                new TomSelect("#deptSelect", {
                    create: false,
                    sortField: { field: "text", direction: "asc" },
                    placeholder: "พิมพ์ค้นหาหน่วยงาน...",
                    allowEmptyOption: false,
                });
            }

            <?php if ($user['role'] === 'user'): ?>
            if (typeof switchView === 'function') switchView('create-ticket');
            <?php else: ?>
            if (typeof switchView === 'function') switchView('dashboard');
            <?php endif; ?>
        });
    </script>
    <!-- Real API Integration -->
    <script>
    window.CURRENT_USER = <?= json_encode($user) ?>;
    window.IS_ADMIN = <?= $user['role'] === 'admin' ? 'true' : 'false' ?>;
    window.STAFF_LIST = <?= json_encode($staff_list) ?>;
    
    window.deleteTicket = async function(id) {
        if (!confirm('⚠️ ยืนยันการลบรายการแจ้งซ่อมนี้อย่างถาวร?')) return;
        
        try {
            const res = await fetch('/api/tickets.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const json = await res.json();
            if (json.success) {
                if (typeof showToast === 'function') showToast('✅ ลบรายการแจ้งซ่อมเรียบร้อยแล้ว');
                else alert('✅ ลบรายการแจ้งซ่อมเรียบร้อยแล้ว');
                
                // รีโหลดรายการถ้าอยู่ในหน้า ticket list
                if (typeof loadTickets === 'function') loadTickets();
                else window.location.reload();
            } else {
                if (typeof showToast === 'function') showToast('❌ ' + (json.error || 'ไม่สามารถลบได้'), true);
                else alert('❌ ' + (json.error || 'ไม่สามารถลบได้'));
            }
        } catch(e) {
            if (typeof showToast === 'function') showToast('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ', true);
            else alert('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ');
        }
    };
    </script>
    <script src="/js/app.js?v=<?= time() ?>"></script>
</body>
</html>