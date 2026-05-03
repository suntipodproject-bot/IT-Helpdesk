<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in → go to dashboard
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, username, password, full_name, role FROM users WHERE username = ? AND is_active = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['last_activity'] = time();
            header('Location: /index.php');
            exit;
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    } else {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | IT Service Helpdesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .bg-animated {
            background: linear-gradient(135deg, #071324 0%, #0f213a 40%, #071324 100%);
            min-height: 100vh;
        }
        .glass-card {
            background: rgba(24, 48, 80, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 180, 216, 0.2);
        }
        .dark-input {
            background: rgba(15, 33, 58, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
            transition: all 0.3s;
        }
        .dark-input:focus {
            outline: none;
            border-color: #00b4d8;
            box-shadow: 0 0 0 3px rgba(0,180,216,0.2);
        }
        .dark-input::placeholder { color: #64748b; }
        .btn-primary {
            background: linear-gradient(135deg, #00b4d8, #0077b6);
            transition: all 0.3s;
            box-shadow: 0 0 20px rgba(0,180,216,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 0 30px rgba(0,180,216,0.5);
        }
        .floating-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            animation: float 8s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-20px) scale(1.05); }
        }
    </style>
</head>
<body class="bg-animated flex items-center justify-center relative overflow-hidden">

    <!-- Decorative Orbs -->
    <div class="floating-orb w-96 h-96 bg-blue-500 top-0 -left-20"></div>
    <div class="floating-orb w-80 h-80 bg-cyan-400 bottom-0 -right-20" style="animation-delay:3s"></div>

    <div class="relative z-10 w-full max-w-md px-4">

        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-700 mb-4 shadow-[0_0_40px_rgba(0,180,216,0.4)]">
                <i class="fa-solid fa-microchip text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white">IT Helpdesk</h1>
            <p class="text-slate-400 mt-1 text-sm">ระบบบริหารจัดการแจ้งซ่อมไอที</p>
        </div>

        <!-- Login Card -->
        <div class="glass-card rounded-2xl p-8 shadow-2xl">

            <?php if ($error): ?>
            <div class="mb-5 flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="/login.php" id="loginForm">
                <div class="space-y-5">

                    <!-- Username -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">ชื่อผู้ใช้งาน</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-cyan-400">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <input type="text" name="username" id="username" required autocomplete="username"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   class="dark-input w-full pl-11 pr-4 py-3 rounded-xl text-sm"
                                   placeholder="กรอก username">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">รหัสผ่าน</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-cyan-400">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <input type="password" name="password" id="password" required autocomplete="current-password"
                                   class="dark-input w-full pl-11 pr-11 py-3 rounded-xl text-sm"
                                   placeholder="กรอกรหัสผ่าน">
                            <button type="button" onclick="togglePassword()"
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-white transition-colors">
                                <i class="fa-solid fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" id="submitBtn"
                            class="btn-primary w-full text-white font-semibold py-3 rounded-xl flex items-center justify-center gap-2">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        เข้าสู่ระบบ
                    </button>
                </div>
            </form>

            <!-- Default credentials hint -->
            <div class="mt-6 pt-5 border-t border-white/10">
                <p class="text-xs text-slate-500 text-center mb-3">บัญชีเริ่มต้น: <span class="text-cyan-400">admin</span> / <span class="text-cyan-400">password</span></p>
                <div class="text-center">
                    <a href="/register.php" class="text-sm text-cyan-400 hover:text-cyan-300 transition-colors inline-flex items-center gap-2">
                        <i class="fa-solid fa-user-plus"></i> สมัครสมาชิกใหม่
                    </a>
                </div>
            </div>
        </div>

        <p class="text-center text-slate-600 text-xs mt-6">
            <?= APP_NAME ?> v<?= APP_VERSION ?> · ฝ่ายเทคโนโลยีสารสนเทศ
        </p>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon  = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
        document.getElementById('loginForm').addEventListener('submit', () => {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังเข้าสู่ระบบ...';
            btn.disabled = true;
        });
    </script>
</body>
</html>
