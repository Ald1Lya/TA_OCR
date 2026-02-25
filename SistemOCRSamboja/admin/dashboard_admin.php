<?php
session_start();
require_once '../proses/config.php';

// 1. SATPAM ADMIN
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$current_user = [
    'username'     => $_SESSION['username'],
    'nama_lengkap' => $_SESSION['nama_lengkap'],
    'role'         => $_SESSION['role']
];

// 2. AMBIL DATA UNTUK PANTAUAN
$total_operator = 0;
$total_scan = 0;
$aktivitas_terakhir = [];

if ($db) {
    // Hitung operator
    $res_op = mysqli_query($db, "SELECT COUNT(id) as total FROM staf_kecamatan WHERE role = 'operator'");
    if ($res_op) $total_operator = mysqli_fetch_assoc($res_op)['total'];

    // Hitung total scan
    $res_scan = mysqli_query($db, "SELECT COUNT(log_id) as total FROM log_ocr");
    if ($res_scan) $total_scan = mysqli_fetch_assoc($res_scan)['total'];

    // Ambil 5 riwayat scan terakhir dari semua operator
    $sql_recent = "SELECT lo.waktu_upload, lo.nama_file_asli, COALESCE(lo.nik_final, lo.nik_terdeteksi) AS nik, lo.status_proses, sk.nama_lengkap AS operator_nama 
                   FROM log_ocr lo 
                   LEFT JOIN staf_kecamatan sk ON lo.id_staf = sk.id 
                   ORDER BY lo.waktu_upload DESC LIMIT 5";
    $res_recent = mysqli_query($db, $sql_recent);
    if ($res_recent) {
        while ($row = mysqli_fetch_assoc($res_recent)) {
            $aktivitas_terakhir[] = $row;
        }
    }
}

// Helper status (disamakan dengan riwayat.php)
function getStatusBadgeAdmin($status) {
    $s = strtolower(trim($status));
    if ($s === 'finalized' || $s === 'berhasil') {
        return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold bg-green-50 text-green-700 border border-green-200"><span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span> Berhasil</span>';
    } elseif ($s === 'pending_review' || $s === 'perlu koreksi') {
        return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold bg-yellow-50 text-yellow-700 border border-yellow-200"><span class="relative inline-flex rounded-full h-2 w-2 bg-yellow-500"></span> Perlu Cek</span>';
    } else {
        return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold bg-red-50 text-red-700 border border-red-200"><span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span> Gagal</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - OCR KTP</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-50 min-h-screen flex text-gray-800 antialiased">

<?php include 'includes/navbar_admin.php'; ?>

<main class="flex-1 ml-64 p-8 transition-all duration-300 relative">
  
  <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Dashboard Administrator</h1>
      <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
        <i data-feather="monitor" class="w-4 h-4 text-green-600"></i>
        Pantau aktivitas sistem. Anda login sebagai <strong class="text-green-700"><?= htmlspecialchars($current_user['nama_lengkap']) ?></strong>
      </p>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center gap-4 border-l-4 border-l-green-500">
      <div class="p-4 bg-green-50 text-green-600 rounded-lg">
        <i data-feather="users" class="w-6 h-6"></i>
      </div>
      <div>
        <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Total Operator</p>
        <h3 class="text-2xl font-bold text-gray-900"><?= $total_operator ?> Orang</h3>
      </div>
    </div>

    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center gap-4 border-l-4 border-l-blue-500">
      <div class="p-4 bg-blue-50 text-blue-600 rounded-lg">
        <i data-feather="file-text" class="w-6 h-6"></i>
      </div>
      <div>
        <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Total KTP Diproses</p>
        <h3 class="text-2xl font-bold text-gray-900"><?= $total_scan ?> Dokumen</h3>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-white">
        <h2 class="text-lg font-bold text-gray-800">Aktivitas Scan Terbaru</h2>
        <a href="manajemen_operator.php" class="text-sm font-bold text-green-600 hover:text-green-800 transition">Kelola Akses &rarr;</a>
    </div>
    
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-green-50 border-b border-green-200 text-sm uppercase tracking-wider text-green-800 font-bold">
            <th class="px-6 py-4">Waktu</th>
            <th class="px-6 py-4">Operator</th>
            <th class="px-6 py-4">Nama File</th>
            <th class="px-6 py-4">Hasil NIK</th>
            <th class="px-6 py-4 text-right">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php if(empty($aktivitas_terakhir)): ?>
            <tr>
              <td colspan="5" class="px-6 py-8 text-center text-gray-500 font-medium bg-gray-50/50">
                Belum ada aktivitas scan KTP di sistem.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach($aktivitas_terakhir as $row): ?>
            <tr class="hover:bg-green-50/30 transition duration-150">
              <td class="px-6 py-4 text-sm font-medium text-gray-600">
                <?= date('d M Y, H:i', strtotime($row['waktu_upload'])) ?> WIB
              </td>
              <td class="px-6 py-4">
                <span class="font-bold text-gray-800"><?= htmlspecialchars($row['operator_nama'] ?? 'Sistem') ?></span>
              </td>
              <td class="px-6 py-4 text-sm font-medium text-gray-700">
                <?= htmlspecialchars($row['nama_file_asli']) ?>
              </td>
              <td class="px-6 py-4">
                <?php if($row['nik']): ?>
                    <span class="font-mono text-sm text-gray-800 bg-gray-100 px-2 py-1 rounded border border-gray-200"><?= htmlspecialchars($row['nik']) ?></span>
                <?php else: ?>
                    <span class="text-xs text-red-500 font-medium">Gagal deteksi</span>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4 text-right align-middle">
                <?= getStatusBadgeAdmin($row['status_proses']) ?>
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
document.addEventListener('DOMContentLoaded', () => {
    feather.replace();
});
</script>
</body>
</html>