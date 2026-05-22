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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #071324; color: white; }
        .glass-card { background: rgba(24, 48, 80, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(0, 180, 216, 0.2); }
        .dark-input { background: rgba(15, 33, 58, 0.8); border: 1px solid rgba(255,255,255,0.1); color: white; }
        .dark-input:focus { border-color: #00b4d8; outline: none; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold">ยินดีต้อนรับคุณ <?= htmlspecialchars($user['full_name']) ?></h1>
            <p class="text-slate-400 mt-2">กรุณาตั้งรหัสผ่านใหม่สำหรับการใช้งานครั้งแรก</p>
        </div>

        <div class="glass-card rounded-2xl p-8">
            <?php if ($success): ?>
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-500/20 text-green-400 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fa-solid fa-check text-3xl"></i>
                    </div>
                    <h2 class="text-xl font-bold mb-2">ตั้งค่าเรียบร้อย!</h2>
                    <p class="text-slate-400 mb-6">คุณสามารถเข้าใช้งานระบบได้ทันที</p>
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
                            <label class="block text-sm font-medium text-slate-300 mb-2">รหัสผ่านใหม่</label>
                            <input type="password" name="new_password" required class="dark-input w-full px-4 py-3 rounded-xl text-sm" placeholder="อย่างน้อย 6 ตัวอักษร">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">ยืนยันรหัสผ่านใหม่</label>
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
