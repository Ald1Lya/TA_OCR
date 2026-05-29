<?php
session_start();
require_once '../proses/config.php';

// Proteksi akses admin
if (
    !isset($_SESSION['user_id']) ||
    strtolower($_SESSION['role']) !== 'admin'
) {
    header('Location: ../index.php');
    exit;
}

$currentUser = [
    'username' => $_SESSION['username'],
    'nama'     => $_SESSION['nama_lengkap'],
    'role'     => $_SESSION['role']
];

$totalOperator = 0;
$totalScan7Hari = 0;
$aktivitas     = [];

if ($db) {
        $qOperator = mysqli_query(
        $db,
        "SELECT COUNT(id) AS total FROM staf_kecamatan WHERE role = 'operator'"
    );
    if ($qOperator) {
        $totalOperator = mysqli_fetch_assoc($qOperator)['total'];
    }

        $qScan = mysqli_query(
        $db,
        "SELECT COUNT(log_id) AS total FROM log_ocr WHERE waktu_upload >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    if ($qScan) {
        $totalScan7Hari = mysqli_fetch_assoc($qScan)['total'];
    }

        $qAktivitas = mysqli_query(
        $db,
        "SELECT 
            lo.waktu_upload,
            lo.nama_file_asli,
            COALESCE(lo.nik_final, lo.nik_terdeteksi) AS nik,
            lo.status_proses,
            sk.nama_lengkap AS operator_nama
        FROM log_ocr lo
        LEFT JOIN staf_kecamatan sk ON lo.id_staf = sk.id
        ORDER BY lo.waktu_upload DESC
        LIMIT 5"
    );

    if ($qAktivitas) {
        while ($row = mysqli_fetch_assoc($qAktivitas)) {
            $aktivitas[] = $row;
        }
    }
}

function statusBadge($status)
{
    $status = strtolower(trim($status));

    if ($status === 'finalized' || $status === 'berhasil') {
        return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold bg-green-50 text-green-700 border border-green-200">
                    <span class="h-2 w-2 rounded-full bg-green-500"></span> Berhasil
                </span>';
    }

    if ($status === 'pending_review' || $status === 'perlu koreksi') {
        return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold bg-yellow-50 text-yellow-700 border border-yellow-200">
                    <span class="h-2 w-2 rounded-full bg-yellow-500"></span> Perlu Cek
                </span>';
    }

    return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold bg-red-50 text-red-700 border border-red-200">
                <span class="h-2 w-2 rounded-full bg-red-500"></span> Gagal
            </span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - OCR KTP</title>

    <link rel="stylesheet" href="/TUGASAKHIRCAPSTONE/assets/css/style.css" />
    <script src="/TUGASAKHIRCAPSTONE/assets/js/chart.min.js"></script>

    <style>
        /* Deklarasi Font Inter Regular (400) */
        @font-face {
            font-family: 'Inter';
            src: url('/TUGASAKHIRCAPSTONE/assets/fonts/Inter-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
        }

        /* Deklarasi Font Inter SemiBold (600) */
        @font-face {
            font-family: 'Inter';
            src: url('/TUGASAKHIRCAPSTONE/assets/fonts/Inter-SemiBold.ttf') format('truetype');
            font-weight: 600;
            font-style: normal;
        }

        /* Deklarasi Font Inter Bold (700) */
        @font-face {
            font-family: 'Inter';
            src: url('/TUGASAKHIRCAPSTONE/assets/fonts/static/Inter-Bold.ttf') format('truetype');
            font-weight: 700;
            font-style: normal;
        }

        /* Terapkan ke body */
        body { 
            font-family: 'Inter', sans-serif; 
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex text-gray-800">

<?php include 'includes/navbar_admin.php'; ?>

<main class="flex-1 ml-64 p-8">

    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard Administrator</h1>
        <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
            <i data-feather="monitor" class="w-4 h-4 text-green-600"></i>
            Login sebagai <strong class="text-green-700">
                <?= htmlspecialchars($currentUser['nama']) ?>
            </strong>
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        
        <a href="manajemen_operator.php" class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between border-l-4 border-l-green-500 hover:shadow-md hover:-translate-y-1 hover:border-green-500 transition-all cursor-pointer group">
            <div class="flex gap-4 items-center">
                <div class="p-4 bg-green-50 rounded-lg text-green-600 group-hover:bg-green-100 transition-colors">
                    <i data-feather="users"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-500 uppercase">Kelola Operator</p>
                    <h3 class="text-2xl font-bold text-gray-800"><?= $totalOperator ?> Orang</h3>
                </div>
            </div>
            <div class="text-gray-300 group-hover:text-green-500 transition-colors">
                <i data-feather="chevron-right"></i>
            </div>
        </a>

        <a href="riwayat_keseluruhan.php" class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between border-l-4 border-l-blue-500 hover:shadow-md hover:-translate-y-1 hover:border-blue-500 transition-all cursor-pointer group">
            <div class="flex gap-4 items-center">
                <div class="p-4 bg-blue-50 rounded-lg text-blue-600 group-hover:bg-blue-100 transition-colors">
                    <i data-feather="file-text"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-500 uppercase">KTP Diproses</p>
                    <div class="flex items-baseline gap-2 mt-1">
                        <h3 class="text-2xl font-bold text-gray-800"><?= $totalScan7Hari ?></h3>
                        <span class="text-xs font-semibold text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">7 Hari Terakhir</span>
                    </div>
                </div>
            </div>
            <div class="text-gray-300 group-hover:text-blue-500 transition-colors">
                <i data-feather="chevron-right"></i>
            </div>
        </a>

        <a href="kontrol_sistem.php" class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between border-l-4 border-l-purple-500 hover:shadow-md hover:-translate-y-1 hover:border-purple-500 transition-all cursor-pointer group">
            <div class="flex gap-4 items-center">
                <div class="p-4 bg-purple-50 rounded-lg text-purple-600 group-hover:bg-purple-100 transition-colors">
                    <i data-feather="server"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-500 uppercase">Kontrol Sistem</p>
                    <h3 class="text-lg font-bold text-gray-800 mt-1">Server OCR</h3>
                </div>
            </div>
            <div class="text-gray-300 group-hover:text-purple-500 transition-colors">
                <i data-feather="chevron-right"></i>
            </div>
        </a>

    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-bold">Aktivitas Scan Terbaru</h2>
            <a href="riwayat_keseluruhan.php" class="text-sm font-bold text-green-600 hover:underline">
                Lihat Semua Riwayat Scan
            </a>
        </div>

        <table class="w-full text-left">
            <thead class="bg-green-50 text-green-800 text-sm uppercase">
                <tr>
                    <th class="px-6 py-4">Waktu</th>
                    <th class="px-6 py-4">Operator</th>
                    <th class="px-6 py-4">File</th>
                    <th class="px-6 py-4">NIK</th>
                    <th class="px-6 py-4 text-right">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100"> <?php if (!$aktivitas): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            Belum ada aktivitas scan dalam waktu dekat.
                        </td>
                    </tr>
                <?php else: foreach ($aktivitas as $row): ?>
                    <tr class="hover:bg-green-50/30 transition-colors">
                        <td class="px-6 py-4 text-sm text-gray-600">
                            <?= date('d M Y, H:i', strtotime($row['waktu_upload'])) ?> WIB
                        </td>
                        <td class="px-6 py-4 font-semibold">
                            <?= htmlspecialchars($row['operator_nama'] ?? 'Sistem') ?>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <?= htmlspecialchars($row['nama_file_asli']) ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($row['nik']): ?>
                                <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded border border-gray-200">
                                    <?= htmlspecialchars($row['nik']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-xs text-red-500">Gagal deteksi</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <?= statusBadge($row['status_proses']) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</main>

<script src="/TUGASAKHIRCAPSTONE/assets/js/feather.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    feather.replace();
});
</script>
</body>
</html>