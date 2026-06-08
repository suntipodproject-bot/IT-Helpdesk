<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in → go to dashboard
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$success = '';

// Fetch positions for dropdown
$db = getDB();
try {
    $posStmt = $db->query('SELECT id, position_name FROM position ORDER BY id ASC');
    $positions = $posStmt->fetchAll();
} catch (Exception $e) {
    $positions = []; // Fallback
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $position_id = $_POST['position_id'] ?? null;

    if ($username && $password && $confirm_password && $full_name && $position_id) {
        if ($password !== $confirm_password) {
            $error = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
        } else {
            $db = getDB();
            // Check if username exists
            $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว';
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                // default role = 'user', active = 1
                $stmt = $db->prepare('INSERT INTO users (username, password, full_name, position_id, role, is_active) VALUES (?, ?, ?, ?, ?, 1)');
                try {
                    $stmt->execute([$username, $hashed_password, $full_name, $position_id, 'user']);
                    $success = 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ';
                } catch (Exception $e) {
                    $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
                }
            }
        }
    } else {
        $error = 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครผู้ใช้งาน | IT Service Helpdesk</title>

    <!-- Theme Initialization -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            /* Light Mode Default - Hospital Teal/Blue Theme */
            --bg-main-1: #f8fafc; /* Slate 50 */
            --bg-main-2: #f1f5f9; /* Slate 100 */
            --bg-main-3: #e2e8f0; /* Slate 200 */
            --card-bg: #ffffff;
            --card-border: #cbd5e1;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --input-text: #0f172a;
            --primary-accent: #0d9488;
            --primary-accent-hover: #0f766e;
            --accent-glow: rgba(13, 148, 136, 0.15);
            --orb-opacity: 0.05;
        }

        html.dark {
            /* Dark Mode Theme */
            --bg-main-1: #071324;
            --bg-main-2: #0f213a;
            --bg-main-3: #071324;
            --card-bg: rgba(24, 48, 80, 0.7);
            --card-border: rgba(0, 180, 216, 0.2);
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --input-bg: rgba(15, 33, 58, 0.8);
            --input-border: rgba(255, 255, 255, 0.1);
            --input-text: #ffffff;
            --primary-accent: #00b4d8;
            --primary-accent-hover: #0077b6;
            --accent-glow: rgba(0, 180, 216, 0.3);
            --orb-opacity: 0.15;
        }

        body { font-family: 'Sarabun', sans-serif; }
        .bg-animated {
            background: linear-gradient(135deg, var(--bg-main-1) 0%, var(--bg-main-2) 40%, var(--bg-main-3) 100%);
            min-height: 100vh;
        }
        .glass-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-main);
        }
        
        html.dark .glass-card {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        
        .dark-input {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--input-text);
            transition: all 0.3s;
        }
        .dark-input:focus {
            outline: none;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        .dark-input::placeholder { color: var(--text-muted); }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-accent), var(--primary-accent-hover));
            transition: all 0.3s;
            box-shadow: 0 0 20px var(--accent-glow);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 0 30px var(--accent-glow);
        }
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            transition: all 0.3s;
            box-shadow: 0 0 20px rgba(16,185,129,0.3);
        }
        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 0 30px rgba(16,185,129,0.5);
        }
        .floating-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: var(--orb-opacity);
            animation: float 8s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-20px) scale(1.05); }
        }

        /* Light Mode Text Overrides */
        html:not(.dark) h1 {
            color: var(--text-main) !important;
        }
        html:not(.dark) label {
            color: var(--text-main) !important;
        }
        html:not(.dark) .text-slate-400 {
            color: var(--text-muted) !important;
        }
        html:not(.dark) .text-cyan-400 {
            color: var(--primary-accent) !important;
        }
        html:not(.dark) .text-cyan-400:hover {
            color: var(--primary-accent-hover) !important;
        }
        html:not(.dark) .text-cyan-400 i {
            color: var(--primary-accent) !important;
        }
        html:not(.dark) .border-white\/10 {
            border-color: var(--card-border) !important;
        }
    </style>
</head>
<body class="bg-animated flex items-center justify-center relative py-10 overflow-x-hidden">

    <!-- Decorative Orbs -->
    <div class="floating-orb w-96 h-96 bg-blue-500 top-0 -left-20"></div>
    <div class="floating-orb w-80 h-80 bg-cyan-400 bottom-0 -right-20" style="animation-delay:3s"></div>

    <div class="relative z-10 w-full max-w-lg px-4">

        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-700 mb-4 shadow-[0_0_40px_rgba(0,180,216,0.4)]">
                <i class="fa-solid fa-user-plus text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white">สมัครผู้ใช้งานใหม่</h1>
            <p class="text-slate-400 mt-1 text-sm">ระบบบริหารจัดการแจ้งซ่อมไอที</p>
        </div>

        <!-- Register Card -->
        <div class="glass-card rounded-2xl p-8 shadow-2xl">

            <?php if ($error): ?>
            <div class="mb-5 flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="mb-5 flex flex-col items-center justify-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 text-center rounded-lg px-4 py-6">
                <i class="fa-regular fa-circle-check text-4xl mb-2"></i>
                <span class="text-lg"><?= htmlspecialchars($success) ?></span>
                <a href="/login.php" class="btn-success text-white font-semibold py-2 px-6 rounded-xl flex items-center gap-2 mt-2 inline-block">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> ไปยังหน้าเข้าสู่ระบบ
                </a>
            </div>
            <?php else: ?>

            <form method="POST" action="/register.php" id="registerForm">
                <div class="space-y-5">

                    <!-- Username -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">ชื่อผู้ใช้งาน (Username) <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-cyan-400">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <input type="text" name="username" required 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   class="dark-input w-full pl-11 pr-4 py-3 rounded-xl text-sm"
                                   placeholder="ตั้งชื่อผู้ใช้งานสำหรับเข้าสู่ระบบ">
                        </div>
                    </div>

                    <!-- Full Name -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">ชื่อ-นามสกุล <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-cyan-400">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                            <input type="text" name="full_name" required 
                                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                   class="dark-input w-full pl-11 pr-4 py-3 rounded-xl text-sm"
                                   placeholder="ชื่อและนามสกุลจริง">
                        </div>
                    </div>

                    <!-- Position/Department -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">หน่วยงาน/แผนก <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-cyan-400">
                                <i class="fa-solid fa-building"></i>
                            </div>
                            <select name="position_id" required class="dark-input w-full pl-11 pr-4 py-3 rounded-xl text-sm appearance-none cursor-pointer">
                                <option value="" disabled selected>เลือกหน่วยงาน/แผนกของคุณ...</option>
                                <?php foreach ($positions as $pos): ?>
                                    <option value="<?= $pos['id'] ?>" class="bg-[#0f213a] text-white" <?= (isset($_POST['position_id']) && $_POST['position_id'] == $pos['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pos['position_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Password -->
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">รหัสผ่าน <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-cyan-400">
                                    <i class="fa-solid fa-lock"></i>
                                </div>
                                <input type="password" name="password" id="password" required 
                                       class="dark-input w-full pl-11 pr-4 py-3 rounded-xl text-sm"
                                       placeholder="ตั้งรหัสผ่าน">
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">ยืนยันรหัสผ่าน <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-cyan-400">
                                    <i class="fa-solid fa-lock"></i>
                                </div>
                                <input type="password" name="confirm_password" id="confirm_password" required 
                                       class="dark-input w-full pl-11 pr-4 py-3 rounded-xl text-sm"
                                       placeholder="ยืนยันรหัสผ่านอีกครั้ง">
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" id="submitBtn"
                            class="btn-primary w-full text-white font-semibold py-3 rounded-xl flex items-center justify-center gap-2 mt-6">
                        <i class="fa-solid fa-user-plus"></i>
                        ยืนยันการสมัครสมาชิก
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="/login.php" class="text-sm text-cyan-400 hover:text-cyan-300 transition-colors">
                            <i class="fa-solid fa-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ
                        </a>
                    </div>
                </div>
            </form>
            <?php endif; ?>

        </div>

        <p class="text-center text-slate-600 text-xs mt-6">
            IT Service Helpdesk · ฝ่ายเทคโนโลยีสารสนเทศ
        </p>
    </div>

    <script>
        <?php if (!$success): ?>
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const pass = document.getElementById('password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (pass !== confirmPass) {
                e.preventDefault();
                alert('กรุณายืนยันรหัสผ่านให้ตรงกัน');
                return false;
            }

            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึกข้อมูล...';
            btn.disabled = true;
        });
        <?php endif; ?>
    </script>
</body>
</html>
