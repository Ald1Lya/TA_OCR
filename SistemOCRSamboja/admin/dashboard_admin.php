<?php
session_start();
require_once '../proses/config.php';

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

// Array untuk Grafik Admin
$bar_labels_admin = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
$bar_data_admin   = array_fill(0, 7, 0);
$global_stats     = ['berhasil' => 0, 'gagal' => 0, 'pending' => 0];

if ($db) {
    // 1. Hitung Total Operator
    $qOperator = mysqli_query($db, "SELECT COUNT(id) AS total FROM staf_kecamatan WHERE role = 'operator'");
    if ($qOperator) {
        $totalOperator = mysqli_fetch_assoc($qOperator)['total'];
    }

    // 2. Hitung Total Scan 7 Hari
    $qScan = mysqli_query($db, "SELECT COUNT(log_id) AS total FROM log_ocr WHERE waktu_upload >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    if ($qScan) {
        $totalScan7Hari = mysqli_fetch_assoc($qScan)['total'];
    }

    // 3. Ambil 5 Aktivitas Terakhir
    $qAktivitas = mysqli_query($db, "SELECT 
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

    // 4. Query untuk Bar Chart (Volume 7 Hari Global)
    $sql_chart = "SELECT WEEKDAY(waktu_upload) AS hari_index, COUNT(*) AS total 
                  FROM log_ocr 
                  WHERE waktu_upload >= NOW() - INTERVAL 7 DAY 
                  GROUP BY hari_index";
    $qChart = mysqli_query($db, $sql_chart);
    if ($qChart) {
        while ($row = mysqli_fetch_assoc($qChart)) {
            $index = (int)$row['hari_index'];
            if ($index >= 0 && $index <= 6) {
                $bar_data_admin[$index] = (int)$row['total'];
            }
        }
    }

    // 5. Query untuk Pie Chart (Distribusi Status Global)
    $sql_global = "SELECT status_proses, COUNT(*) as total FROM log_ocr GROUP BY status_proses";
    $qGlobal = mysqli_query($db, $sql_global);
    if ($qGlobal) {
        while ($row = mysqli_fetch_assoc($qGlobal)) {
            if ($row['status_proses'] === 'finalized') {
                $global_stats['berhasil'] += (int)$row['total'];
            } elseif ($row['status_proses'] === 'failed' || $row['status_proses'] === 'error_php') {
                $global_stats['gagal'] += (int)$row['total'];
            } elseif ($row['status_proses'] === 'pending_review') {
                $global_stats['pending'] += (int)$row['total'];
            }
        }
    }
}

// Kalkulasi Persentase Keseluruhan (Global)
$total_global = $global_stats['berhasil'] + $global_stats['gagal'] + $global_stats['pending'];
$pct_berhasil = ($total_global > 0) ? round(($global_stats['berhasil'] / $total_global) * 100, 1) : 0;
$pct_gagal    = ($total_global > 0) ? round(($global_stats['gagal'] / $total_global) * 100, 1) : 0;
$pct_pending  = ($total_global > 0) ? round(($global_stats['pending'] / $total_global) * 100, 1) : 0;

$pie_data_admin = [$global_stats['berhasil'], $global_stats['gagal'], $global_stats['pending']];

function statusBadge($status) {
    $status = strtolower(trim($status));
    if ($status === 'finalized' || $status === 'berhasil') {
        return '<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">Berhasil</span>';
    }
    if ($status === 'pending_review' || $status === 'perlu koreksi') {
        return '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-bold">Perlu Cek</span>';
    }
    return '<span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold">Gagal</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="../../assetimage/logo.png" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - OCR KTP</title>
    <link rel="stylesheet" href="../../assets/css/style.css" />
    <script src="../../assets/js/chart.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>

<body class="bg-gray-50 min-h-screen text-gray-800 antialiased">

<?php include 'includes/navbar_admin.php'; ?>

<main class="flex-1 ml-64 p-8">

    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Dashboard Administrator</h1>
            <p class="text-sm text-gray-500 mt-1">Pemantauan Lalu Lintas OCR Kecamatan Samboja Kuala</p>
        </div>
        <div class="text-right">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded bg-green-50 text-green-700 text-xs font-bold">
                <i data-feather="monitor" class="w-3 h-3"></i> Admin
            </span>
            <p class="text-sm font-semibold text-gray-800 mt-2">Login: <?= htmlspecialchars($currentUser['nama']) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <a href="manajemen_operator.php" class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 border-l-4 border-l-green-500 hover:bg-gray-50 transition block">
            <div class="flex items-center gap-4">
                <div class="p-4 bg-green-50 text-green-600 rounded-lg">
                    <i data-feather="users" class="w-6 h-6"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Kelola Operator</p>
                    <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= $totalOperator ?> Orang</h3>
                </div>
            </div>
        </a>

        <a href="riwayat_keseluruhan.php" class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 border-l-4 border-l-blue-500 hover:bg-gray-50 transition block">
            <div class="flex items-center gap-4">
                <div class="p-4 bg-blue-50 text-blue-600 rounded-lg">
                    <i data-feather="file-text" class="w-6 h-6"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Volume (7 Hari)</p>
                    <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= $totalScan7Hari ?> Dokumen</h3>
                </div>
            </div>
        </a>

        <a href="kontrol_sistem.php" class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 border-l-4 border-l-gray-600 hover:bg-gray-50 transition block">
            <div class="flex items-center gap-4">
                <div class="p-4 bg-gray-100 text-gray-600 rounded-lg">
                    <i data-feather="server" class="w-6 h-6"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Kontrol Sistem</p>
                    <h3 class="text-lg font-bold text-gray-900 mt-2">Peladen OCR</h3>
                </div>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="font-bold text-gray-800 mb-4">Lalu Lintas Ekstraksi Keseluruhan (7 Hari)</h3>
            <div class="relative h-56">
                <canvas id="barChartAdmin"></canvas>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 flex flex-col">
            <h3 class="font-bold text-gray-800 mb-4">Distribusi Status Global</h3>
            <div class="flex-1 flex flex-col items-center justify-center">
                <div class="relative w-32 h-32 mb-4">
                    <canvas id="pieChartAdmin"></canvas>
                </div>
                <div class="w-full space-y-2 mt-2 px-2">
                    <div class="flex justify-between items-center text-sm border-b border-gray-100 pb-1">
                        <span class="flex items-center gap-2 text-gray-600"><span class="w-3 h-3 bg-green-500 rounded-full"></span> Berhasil</span>
                        <span class="font-bold text-gray-800"><?= $global_stats['berhasil'] ?> <span class="text-gray-400 font-normal">(<?= $pct_berhasil ?>%)</span></span>
                    </div>
                    <div class="flex justify-between items-center text-sm border-b border-gray-100 pb-1">
                        <span class="flex items-center gap-2 text-gray-600"><span class="w-3 h-3 bg-red-500 rounded-full"></span> Gagal</span>
                        <span class="font-bold text-gray-800"><?= $global_stats['gagal'] ?> <span class="text-gray-400 font-normal">(<?= $pct_gagal ?>%)</span></span>
                    </div>
                    <div class="flex justify-between items-center text-sm border-b border-gray-100 pb-1">
                        <span class="flex items-center gap-2 text-gray-600"><span class="w-3 h-3 bg-yellow-400 rounded-full"></span> Perlu Cek</span>
                        <span class="font-bold text-gray-800"><?= $global_stats['pending'] ?> <span class="text-gray-400 font-normal">(<?= $pct_pending ?>%)</span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h2 class="font-bold text-gray-800">Aktivitas Scan Terbaru (Seluruh Loket)</h2>
            <a href="riwayat_keseluruhan.php" class="text-sm font-semibold text-green-600 hover:text-green-800 hover:underline">
                Lihat Semua Riwayat
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-white border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 font-semibold text-gray-500">Waktu</th>
                        <th class="px-6 py-3 font-semibold text-gray-500">Operator</th>
                        <th class="px-6 py-3 font-semibold text-gray-500">File Berkas</th>
                        <th class="px-6 py-3 font-semibold text-gray-500 text-center">Output NIK</th>
                        <th class="px-6 py-3 font-semibold text-gray-500 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (!$aktivitas): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada aktivitas scan terbaru.</td>
                        </tr>
                    <?php else: foreach ($aktivitas as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-gray-600">
                                <?= date('d/m/Y', strtotime($row['waktu_upload'])) ?> 
                                <span class="text-xs text-gray-400 ml-1"><?= date('H:i', strtotime($row['waktu_upload'])) ?></span>
                            </td>
                            <td class="px-6 py-3 font-medium text-gray-800">
                                <?= htmlspecialchars($row['operator_nama'] ?? 'Sistem') ?>
                            </td>
                            <td class="px-6 py-3 text-gray-600">
                                <?= htmlspecialchars($row['nama_file_asli']) ?>
                            </td>
                            <td class="px-6 py-3 text-center">
                                <?php if ($row['nik']): ?>
                                    <span class="font-mono bg-gray-100 px-2 py-1 rounded text-gray-700 border border-gray-200">
                                        <?= htmlspecialchars($row['nik']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs font-semibold text-red-500">Kosong</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <?= statusBadge($row['status_proses']) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script src="../../assets/js/feather.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        feather.replace();
    });

    Chart.defaults.font.family = "inherit";
    Chart.defaults.color = '#6b7280';

    // Bar Chart Admin (Kembali Normal/Kotak Biasa!)
    new Chart(document.getElementById('barChartAdmin'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($bar_labels_admin) ?>,
            datasets: [{
                label: 'Total Dokumen',
                data: <?= json_encode($bar_data_admin) ?>,
                backgroundColor: '#009914ff', // Biru solid profesional
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

    // Pie Chart Admin
    new Chart(document.getElementById('pieChartAdmin'), {
        type: 'doughnut',
        data: {
            labels: ['Berhasil', 'Gagal', 'Perlu Cek'],
            datasets: [{
                data: <?= json_encode($pie_data_admin) ?>,
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
