<?php
session_start();

// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Redirect admin ke dashboard admin
if (strtolower($_SESSION['role']) === 'admin') {
    header('Location: admin/dashboard_admin.php');
    exit;
}

require_once 'proses/config.php';

// Ambil ID dari session
$user_id = $_SESSION['user_id'];

// Data user
$user = [];
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
            $last_login = date('l, H:i', strtotime($user['last_login']));
        }
    }
    mysqli_stmt_close($stmt);
}

// --- FIX 1: STATISTIK OCR PRIBADI ---
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
$avg_akurasi = ((float)$stats['avg_skor']) * 100;

// Data chart pie
$pie_data_values = [$total_berhasil, $total_gagal, $total_pending];
$pie_data_labels = ['Berhasil (Final)', 'Gagal', 'Perlu Cek'];

// --- FIX 2: RIWAYAT OCR PRIBADI (5 Terbaru) ---
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

// --- FIX 3: GRAFIK VOLUME HARIAN PRIBADI ---
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

// Helper badge status
function getStatusBadge($status) {
    switch ($status) {
        case 'finalized': return ['text' => 'Berhasil', 'class' => 'bg-green-100 text-green-700'];
        case 'pending_review': return ['text' => 'Perlu Cek', 'class' => 'bg-yellow-100 text-yellow-700'];
        case 'failed':
        case 'error_php': return ['text' => 'Gagal', 'class' => 'bg-red-100 text-red-700'];
        default: return ['text' => 'Memproses', 'class' => 'bg-gray-100 text-gray-700'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Operator - OCR KTP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap'); body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen flex text-gray-800 antialiased">

<?php if (file_exists(__DIR__.'/includes/navbar.php')) include 'includes/navbar.php'; ?>

<main class="flex-1 ml-64 p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard Operator</h1>
        <p class="text-gray-500">Ringkasan hasil kerja ekstraksi KTP Anda.</p>
    </div>

    <div class="p-6 bg-white rounded-xl border border-gray-200 shadow-sm mb-8">
        <h2 class="text-xl font-bold mb-1">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User') ?></h2>
        <p class="text-gray-500 text-sm">Terakhir login: <span class="font-semibold text-gray-700"><?= htmlspecialchars($last_login) ?></span></p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <?php
        $cards = [
            ['value'=>$total_proses,'label'=>'KTP Saya Proses','color'=>'green','icon'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2z'],
            ['value'=>number_format($tingkat_keberhasilan,1).'%','label'=>'Persentase Sukses','color'=>'emerald','icon'=>'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['value'=>number_format($avg_akurasi,1).'%','label'=>'Rata-rata Akurasi','color'=>'blue','icon'=>'M13 10V3L4 14h7v7l9-11h-7z']
        ];
        foreach($cards as $c):
        ?>
        <div class="p-6 bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-<?= $c['color'] ?>-50 text-<?= $c['color'] ?>-600 mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $c['icon'] ?>"></path></svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900"><?= $c['value'] ?></h3>
            <p class="text-sm font-medium text-gray-500"><?= $c['label'] ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="p-6 bg-white rounded-xl border border-gray-200 shadow-sm">
            <h3 class="font-bold mb-6 text-gray-800">Produktivitas</h3>
            <canvas id="barChart" height="180"></canvas>
        </div>
        <div class="p-6 bg-white rounded-xl border border-gray-200 shadow-sm flex flex-col items-center">
            <h3 class="font-bold mb-6 text-gray-800">Status Hasil Scan Saya</h3>
            <div class="relative w-64 h-64">
                <canvas id="pieChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-xl font-bold text-gray-900"><?= $total_berhasil ?></span>
                    <span class="text-xs text-gray-400">Sukses</span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-bold text-gray-800">5 Scan Terakhir Saya</h3>
            <a href="riwayat.php" class="text-sm font-bold text-green-600 hover:text-green-800">Lihat Semua &rarr;</a>
        </div>
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <a href="upload.php" class="text-sm font-bold text-green-600 hover:text-green-800">Upload KTP &rarr;</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b">
                    <tr class="text-gray-500 uppercase text-[10px] tracking-wider font-bold">
                        <th class="py-3 px-6">Waktu</th>
                        <th class="py-3 px-6">Nama File</th>
                        <th class="py-3 px-6">Status</th>
                        <th class="py-3 px-6">Skor Akurasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (count($riwayat_terkini) === 0): ?>
                        <tr><td colspan="4" class="py-10 text-center text-gray-400 italic">Belum ada aktivitas scan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($riwayat_terkini as $data): 
                            $status = getStatusBadge($data['status'] ?? '');
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="py-4 px-6 text-gray-500"><?= date('H:i', strtotime($data['waktu_proses'])) ?></td>
                            <td class="py-4 px-6 font-semibold text-gray-800"><?= htmlspecialchars($data['nama_file']) ?></td>
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase <?= $status['class'] ?>">
                                    <?= $status['text'] ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 font-mono font-bold <?= ((float)$data['akurasi'] > 0.8) ? 'text-green-600' : 'text-yellow-600' ?>">
                                <?= number_format(((float)$data['akurasi']) * 100, 1) ?>%
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
    // Bar Chart
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
            datasets: [{
                label: 'KTP di-scan',
                data: <?= json_encode($bar_data) ?>,
                backgroundColor: '#16a34a',
                borderRadius: 6
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });

    // Pie Chart
    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($pie_data_labels) ?>,
            datasets: [{
                data: <?= json_encode($pie_data_values) ?>,
                backgroundColor: ['#16a34a','#ef4444','#f59e0b'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: { cutout: '80%', plugins: { legend: { display: false } } }
    });
</script>
</body>
</html>