<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();
$user = currentUser();

// If no need to change password, redirect to index
if (!($user['must_change_password'] ?? false) && false) { // Skip for now to test
    // Actually, I need to fetch the latest user data to be sure
    $db = getDB();
    $stmt = $db->prepare("SELECT must_change_password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    if (!$stmt->fetchColumn()) {
        header('Location: /index.php');
        exit;
    }
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 6) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } elseif ($newPass !== $confirmPass) {
        $error = 'รหัสผ่านไม่ตรงกัน';
    } else {
        $db = getDB();
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
        $stmt->execute([$hashed, $user['id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่ารหัสผ่านใหม่ | IT Helpdesk</title>

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
            --bg-main: #f8fafc; /* Slate 50 */
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
        }

        html.dark {
            /* Dark Mode Theme */
            --bg-main: #071324;
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
        }

        body { 
            font-family: 'Sarabun', sans-serif; 
            background-color: var(--bg-main); 
            color: var(--text-main); 
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
        }
        .dark-input:focus { 
            border-color: var(--primary-accent); 
            box-shadow: 0 0 0 3px var(--accent-glow);
            outline: none; 
        }
        .bg-cyan-500 {
            background-color: var(--primary-accent) !important;
        }
        .hover\:bg-cyan-400:hover {
            background-color: var(--primary-accent-hover) !important;
        }

        /* Light Mode Overrides */
        html:not(.dark) label {
            color: var(--text-main) !important;
        }
        html:not(.dark) .text-slate-400 {
            color: var(--text-muted) !important;
        }
        html:not(.dark) .border-white\/10 {
            border-color: var(--card-border) !important;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-slate-50 dark:bg-[#071324] transition-colors">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-teal-600 dark:text-teal-200">ยินดีต้อนรับคุณ <?= htmlspecialchars($user['full_name']) ?></h1>
            <p class="text-slate-600 dark:text-slate-400 mt-2">กรุณาตั้งรหัสผ่านใหม่สำหรับการใช้งานครั้งแรก</p>
        </div>

        <div class="glass-card rounded-2xl p-8 shadow-lg">
            <?php if ($success): ?>
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-500/20 text-green-400 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fa-solid fa-check text-3xl"></i>
                    </div>
                    <h2 class="text-xl font-bold mb-2">ตั้งค่าเรียบร้อย!</h2>
                    <p class="text-slate-600 dark:text-slate-400 mb-6">คุณสามารถเข้าใช้งานระบบได้ทันที</p>
                    <a href="/index.php" class="block w-full bg-cyan-500 hover:bg-cyan-400 text-white font-bold py-3 rounded-xl transition-all">
                        เข้าสู่หน้าหลัก
                    </a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                <div class="mb-4 bg-red-500/10 border border-red-500/30 text-red-400 text-sm p-3 rounded-lg flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= $error ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">รหัสผ่านใหม่</label>
                            <input type="password" name="new_password" required class="dark-input w-full px-4 py-3 rounded-xl text-sm" placeholder="อย่างน้อย 6 ตัวอักษร">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" name="confirm_password" required class="dark-input w-full px-4 py-3 rounded-xl text-sm" placeholder="กรอกรหัสผ่านอีกครั้ง">
                        </div>
                        <button type="submit" class="w-full bg-cyan-500 hover:bg-cyan-400 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-cyan-500/20">
                            บันทึกรหัสผ่าน
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
