<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$stmt = $db->query("SELECT a.*, d.dept_name FROM assets a LEFT JOIN department d ON a.department_id = d.id ORDER BY a.asset_code ASC");
$assets = $stmt->fetchAll();

// Get base URL for QR Code
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . "://" . $host;

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Print Asset QR Codes - SNCH IT Helpdesk</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f1f5f9; }
        @media print {
            body { background: white; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .sticker-sheet { padding: 0 !important; box-shadow: none !important; }
            .sticker { break-inside: avoid; border: 1px solid #ddd !important; }
        }
        .sticker {
            width: 100%;
            border: 1px solid #e2e8f0;
            background: white;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 4px;
        }
    </style>
</head>
<body class="p-8">

    <div class="max-w-5xl mx-auto no-print mb-8 flex justify-between items-center bg-white p-6 rounded-xl shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">พิมพ์สติ๊กเกอร์รหัสครุภัณฑ์</h1>
            <p class="text-slate-500">เลือก "พิมพ์" เพื่อสั่งปริ้นลงกระดาษ A4 หรือสติ๊กเกอร์</p>
        </div>
        <div class="flex gap-3">
            <button onclick="window.close()" class="px-6 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">ปิดหน้าต่าง</button>
            <button onclick="window.print()" class="px-6 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
                <i class="fa-solid fa-print"></i> พิมพ์สติ๊กเกอร์
            </button>
        </div>
    </div>

    <div class="max-w-5xl mx-auto sticker-sheet grid grid-cols-2 md:grid-cols-3 gap-4">
        <?php foreach ($assets as $a): ?>
            <?php 
                $qrContent = $baseUrl . "/index.php?asset_code=" . urlencode($a['asset_code']);
                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrContent);
            ?>
            <div class="sticker shadow-sm">
                <div class="w-24 h-24 flex-shrink-0 bg-white border p-1">
                    <img src="<?= $qrUrl ?>" alt="QR" class="w-full h-full">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Asset ID</div>
                    <div class="text-lg font-bold text-slate-900 font-mono leading-none mb-1"><?= htmlspecialchars($a['asset_code']) ?></div>
                    <div class="text-[11px] font-semibold text-blue-600 truncate mb-0.5"><?= htmlspecialchars($a['asset_name']) ?></div>
                    <div class="text-[9px] text-slate-500 truncate italic"><?= htmlspecialchars($a['dept_name'] ?: '-') ?></div>
                    <div class="mt-2 text-[8px] text-slate-300 border-t pt-1">SNCH IT Helpdesk</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
