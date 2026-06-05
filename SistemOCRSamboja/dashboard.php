<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (strtolower($_SESSION['role']) === 'admin') {
    header('Location: admin/dashboard_admin.php');
    exit;
}

require_once 'proses/config.php';

$user_id    = $_SESSION['user_id'];
$user       = [];
$last_login = 'Belum tersedia';

$sql_user = "SELECT username, role, last_login 
             FROM staf_kecamatan 
             WHERE id = ?";
$stmt = mysqli_prepare($db, $sql_user);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result_user = mysqli_stmt_get_result($stmt);

    if ($result_user && mysqli_num_rows($result_user) > 0) {
        $user = mysqli_fetch_assoc($result_user);
        if (!empty($user['last_login'])) {
            $last_login = date('l, d M Y - H:i', strtotime($user['last_login']));
        }
    }
    mysqli_stmt_close($stmt);
}

$stats = [
    'total_proses'   => 0,
    'total_berhasil' => 0,
    'total_gagal'    => 0,
    'total_pending'  => 0,
    'avg_skor'       => 0
];

$sql_stats = "SELECT
    COUNT(*) AS total_proses,
    SUM(CASE WHEN status_proses = 'finalized' THEN 1 ELSE 0 END) AS total_berhasil,
    SUM(CASE WHEN status_proses = 'failed' THEN 1 ELSE 0 END) AS total_gagal,
    SUM(CASE WHEN status_proses = 'pending_review' THEN 1 ELSE 0 END) AS total_pending,
    AVG(CASE WHEN status_proses = 'finalized' THEN skor_kepercayaan ELSE NULL END) AS avg_skor
FROM log_ocr 
WHERE id_staf = ?";

$stmt_stats = mysqli_prepare($db, $sql_stats);
mysqli_stmt_bind_param($stmt_stats, "i", $user_id);
mysqli_stmt_execute($stmt_stats);
$result_stats = mysqli_stmt_get_result($stmt_stats);

if ($result_stats) {
    $stats = mysqli_fetch_assoc($result_stats);
}
mysqli_stmt_close($stmt_stats);

$total_proses   = (int)$stats['total_proses'];
$total_berhasil = (int)$stats['total_berhasil'];
$total_gagal    = (int)$stats['total_gagal'];
$total_pending  = (int)$stats['total_pending'];

$tingkat_keberhasilan = ($total_proses > 0) ? ($total_berhasil / $total_proses) * 100 : 0;
$avg_akurasi          = ((float) $stats['avg_skor']) * 100;

// Kalkulasi Persentase
$pct_berhasil = ($total_proses > 0) ? round(($total_berhasil / $total_proses) * 100, 1) : 0;
$pct_gagal    = ($total_proses > 0) ? round(($total_gagal / $total_proses) * 100, 1) : 0;
$pct_pending  = ($total_proses > 0) ? round(($total_pending / $total_proses) * 100, 1) : 0;

$pie_data_values = [$total_berhasil, $total_gagal, $total_pending];
$pie_data_labels = ['Berhasil', 'Gagal', 'Perlu Cek'];

$riwayat_terkini = [];
$sql_riwayat = "SELECT 
    lo.waktu_upload AS waktu_proses,
    lo.nama_file_asli AS nama_file,
    lo.status_proses AS status,
    lo.skor_kepercayaan AS akurasi,
    sk.nama_lengkap AS operator_nama
FROM log_ocr lo
LEFT JOIN staf_kecamatan sk ON lo.id_staf = sk.id
WHERE lo.id_staf = ?
ORDER BY lo.waktu_upload DESC
LIMIT 5";

$stmt_riwayat = mysqli_prepare($db, $sql_riwayat);
mysqli_stmt_bind_param($stmt_riwayat, "i", $user_id);
mysqli_stmt_execute($stmt_riwayat);
$result_riwayat = mysqli_stmt_get_result($stmt_riwayat);

if ($result_riwayat) {
    while ($row = mysqli_fetch_assoc($result_riwayat)) {
        $riwayat_terkini[] = $row;
    }
}
mysqli_stmt_close($stmt_riwayat);

$bar_labels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
$bar_data   = array_fill(0, 7, 0);

$sql_ocr_harian = "SELECT 
    WEEKDAY(waktu_upload) AS hari_index, 
    COUNT(*) AS total
FROM log_ocr
WHERE id_staf = ? AND waktu_upload >= NOW() - INTERVAL 7 DAY
GROUP BY hari_index";

$stmt_harian = mysqli_prepare($db, $sql_ocr_harian);
mysqli_stmt_bind_param($stmt_harian, "i", $user_id);
mysqli_stmt_execute($stmt_harian);
$result_ocr_harian = mysqli_stmt_get_result($stmt_harian);

if ($result_ocr_harian) {
    while ($row = mysqli_fetch_assoc($result_ocr_harian)) {
        $index = (int)$row['hari_index'];
        if ($index >= 0 && $index <= 6) {
            $bar_data[$index] = (int)$row['total'];
        }
    }
}
mysqli_stmt_close($stmt_harian);

function getStatusBadge($status) {
    switch ($status) {
        case 'finalized': 
            return '<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">Berhasil</span>';
        case 'pending_review': 
            return '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-bold">Perlu Cek</span>';
        case 'failed':
        case 'error_php': 
            return '<span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold">Gagal</span>';
        default: 
            return '<span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs font-bold">Proses</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="../assetimage/logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Operator - OCR KTP</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <script src="../assets/js/chart.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen text-gray-800 antialiased">

<?php if (file_exists(__DIR__.'/includes/navbar.php')) include 'includes/navbar.php'; ?>

<main class="flex-1 ml-64 p-8">
    
    <!-- Header Standar -->
    <div class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Dashboard Operator</h1>
            <p class="text-sm text-gray-500 mt-1">Sistem Ekstraksi Dokumen Administrasi Kecamatan</p>
        </div>
        <div class="text-right">
            <div class="text-sm font-semibold text-gray-800">Halo, <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User') ?></div>
            <p class="text-xs text-gray-500 mt-1">Terakhir aktif: <?= $last_login ?></p>
        </div>
    </div>

    <!-- Statistik Utama -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 flex items-center gap-4">
            <div class="p-4 bg-green-50 text-green-600 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">KTP Saya Proses</p>
                <h3 class="text-2xl font-bold text-gray-900"><?= $total_proses ?></h3>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 flex items-center gap-4">
            <div class="p-4 bg-emerald-50 text-emerald-600 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Tingkat Keberhasilan</p>
                <h3 class="text-2xl font-bold text-gray-900"><?= number_format($tingkat_keberhasilan, 1) ?>%</h3>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 flex items-center gap-4">
            <div class="p-4 bg-blue-50 text-blue-600 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Rata-rata Akurasi</p>
                <h3 class="text-2xl font-bold text-gray-900"><?= number_format($avg_akurasi, 1) ?>%</h3>
            </div>
        </div>
    </div>

    <!-- Area Grafik -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="font-bold text-gray-800 mb-4">Volume Ekstraksi Harian</h3>
            <div class="relative h-56">
                <canvas id="barChart"></canvas>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 flex flex-col">
            <h3 class="font-bold text-gray-800 mb-4">Status Validasi Dokumen</h3>
            <div class="flex-1 flex flex-col items-center justify-center">
                <div class="relative w-32 h-32 mb-4">
                    <canvas id="pieChart"></canvas>
                </div>
                <!-- Legenda Presentase -->
                <div class="w-full space-y-2 mt-2 px-2">
                    <div class="flex justify-between items-center text-sm border-b border-gray-100 pb-1">
                        <span class="flex items-center gap-2 text-gray-600"><span class="w-3 h-3 bg-green-500 rounded-full"></span> Berhasil</span>
                        <span class="font-bold text-gray-800"><?= $total_berhasil ?> <span class="text-gray-400 font-normal">(<?= $pct_berhasil ?>%)</span></span>
                    </div>
                    <div class="flex justify-between items-center text-sm border-b border-gray-100 pb-1">
                        <span class="flex items-center gap-2 text-gray-600"><span class="w-3 h-3 bg-red-500 rounded-full"></span> Gagal</span>
                        <span class="font-bold text-gray-800"><?= $total_gagal ?> <span class="text-gray-400 font-normal">(<?= $pct_gagal ?>%)</span></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="flex items-center gap-2 text-gray-600"><span class="w-3 h-3 bg-yellow-400 rounded-full"></span> Perlu Cek</span>
                        <span class="font-bold text-gray-800"><?= $total_pending ?> <span class="text-gray-400 font-normal">(<?= $pct_pending ?>%)</span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-800">Riwayat 5 Scan Terakhir</h3>
            <div class="flex gap-2">
                <a href="riwayat.php" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded text-sm font-semibold hover:bg-gray-100 transition">Lihat Semua &rarr;</a>
                <a href="upload.php" class="px-4 py-2 bg-green-600 text-white rounded text-sm font-semibold hover:bg-green-700 transition">Upload KTP</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-white border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-6 font-semibold text-gray-500">Waktu</th>
                        <th class="py-3 px-6 font-semibold text-gray-500">Nama File</th>
                        <th class="py-3 px-6 font-semibold text-gray-500 text-center">Status</th>
                        <th class="py-3 px-6 font-semibold text-gray-500 text-right">Akurasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (count($riwayat_terkini) === 0): ?>
                        <tr><td colspan="4" class="py-8 text-center text-gray-500">Belum ada dokumen yang diproses.</td></tr>
                    <?php else: ?>
                        <?php foreach ($riwayat_terkini as $data): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-6 text-gray-600">
                                <?= date('d/m/Y', strtotime($data['waktu_proses'])) ?> <span class="text-xs text-gray-400 ml-1"><?= date('H:i', strtotime($data['waktu_proses'])) ?></span>
                            </td>
                            <td class="py-3 px-6 font-medium text-gray-800"><?= htmlspecialchars($data['nama_file']) ?></td>
                            <td class="py-3 px-6 text-center"><?= getStatusBadge($data['status']) ?></td>
                            <td class="py-3 px-6 text-right">
                                <?php $acc = ((float)$data['akurasi']) * 100; ?>
                                <span class="font-mono font-medium <?= ($acc >= 97) ? 'text-green-600' : 'text-yellow-600' ?>">
                                    <?= number_format($acc, 1) ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
    Chart.defaults.font.family = "inherit";
    Chart.defaults.color = '#6b7280';

    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($bar_labels) ?>,
            datasets: [{
                data: <?= json_encode($bar_data) ?>,
                backgroundColor: '#009914ff',
                borderRadius: 4
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }, 
            scales: { 
                y: { beginAtZero: true, border: {dash: [2, 2]}, grid: {color: '#f3f4f6'}, ticks: { stepSize: 1 } },
                x: { grid: {display: false} }
            } 
        }
    });

    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($pie_data_labels) ?>,
            datasets: [{
                data: <?= json_encode($pie_data_values) ?>,
                backgroundColor: ['#009914ff', '#ef4444', '#facc15'],
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: { cutout: '75%', plugins: { legend: { display: false } } }
    });
</script>
</body>
</html>