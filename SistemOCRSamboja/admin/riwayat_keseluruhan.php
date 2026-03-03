<?php
session_start();
require_once '../proses/config.php';

// 1. Cek Login & Role Admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// 2. Ambil Parameter Filter
$filterOp   = $_GET['operator_id'] ?? '';
$filterStat = $_GET['status'] ?? '';

// 3. Ambil List Operator untuk Dropdown
$listOperator = [];
$resOp = mysqli_query(
    $db,
    "SELECT id, nama_lengkap 
     FROM staf_kecamatan 
     WHERE role = 'operator' 
     ORDER BY nama_lengkap ASC"
);
if ($resOp) {
    while ($row = mysqli_fetch_assoc($resOp)) {
        $listOperator[] = $row;
    }
}

// 4. Susun Query Filter
$where = ["1=1"];

if ($filterOp !== '') {
    $where[] = "lo.id_staf = '" . mysqli_real_escape_string($db, $filterOp) . "'";
}
if ($filterStat !== '') {
    $where[] = "lo.status_proses = '" . mysqli_real_escape_string($db, $filterStat) . "'";
}

$whereSql = implode(' AND ', $where);

// 5. Hitung Summary (Total Data)
$summary = [
    'total'   => 0,
    'sukses'  => 0,
    'gagal'   => 0,
    'koreksi' => 0
];

$sqlSummary = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status_proses IN ('finalized','Berhasil') THEN 1 ELSE 0 END) AS sukses,
        SUM(CASE WHEN status_proses IN ('failed','Gagal','error_php') THEN 1 ELSE 0 END) AS gagal,
        SUM(CASE WHEN status_proses IN ('pending_review','Perlu Koreksi') THEN 1 ELSE 0 END) AS koreksi
    FROM log_ocr lo
    WHERE $whereSql
";
$resSum = mysqli_query($db, $sqlSummary);
if ($resSum) {
    $summary = mysqli_fetch_assoc($resSum);
}

// 6. Pagination Logic
$perPage    = 10;
$page       = max(1, (int)($_GET['page'] ?? 1));
$totalRows  = (int)$summary['total'];
$totalPages = max(1, ceil($totalRows / $perPage));

// Pastikan start tidak minus (jika totalRows 0)
$start = ($page - 1) * $perPage;
if ($start < 0) $start = 0;

// 7. Ambil Data Tabel
$riwayat = [];
$sqlData = "
    SELECT
        lo.log_id,
        lo.waktu_upload,
        lo.nama_file_asli,
        COALESCE(lo.nik_final, lo.nik_terdeteksi) AS nik,
        lo.status_proses,
        lo.skor_kepercayaan,
        sk.nama_lengkap AS operator
    FROM log_ocr lo
    LEFT JOIN staf_kecamatan sk ON lo.id_staf = sk.id
    WHERE $whereSql
    ORDER BY lo.waktu_upload DESC
    LIMIT $start, $perPage
";
$resData = mysqli_query($db, $sqlData);
if ($resData) {
    while ($row = mysqli_fetch_assoc($resData)) {
        $riwayat[] = $row;
    }
}

// Helper Badge Status
function badgeAdminStatus($status)
{
    $s = strtolower(trim($status));
    if ($s === 'finalized' || $s === 'berhasil') {
        return '<span class="bg-green-100 text-green-700 px-2.5 py-1 rounded-md text-xs font-bold uppercase">Sukses</span>';
    }
    if ($s === 'pending_review' || $s === 'perlu koreksi') {
        return '<span class="bg-yellow-100 text-yellow-700 px-2.5 py-1 rounded-md text-xs font-bold uppercase">Koreksi</span>';
    }
    return '<span class="bg-red-100 text-red-700 px-2.5 py-1 rounded-md text-xs font-bold uppercase">Gagal</span>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Keseluruhan - Admin OCR</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-50 min-h-screen flex text-gray-800 antialiased">

<?php include 'includes/navbar_admin.php'; ?>

<main class="flex-1 ml-64 p-8 transition-all duration-300">
  
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Riwayat Keseluruhan Sistem</h1>
    <p class="text-sm text-gray-500 mt-1">Pantau performa dan hasil scan dari seluruh operator kecamatan.</p>
  </div>

  <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Pilih Operator</label>
            <select name="operator_id" class="w-full border-2 border-gray-200 rounded-lg py-2.5 px-3 focus:border-green-500 outline-none text-sm font-medium transition bg-white cursor-pointer">
                <option value="">-- Semua Operator --</option>
                <?php foreach($listOperator as $op): ?>
                    <option value="<?= $op['id'] ?>" <?= $filterOp == $op['id'] ? 'selected' : '' ?>><?= htmlspecialchars($op['nama_lengkap']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Status Scan</label>
            <select name="status" class="w-full border-2 border-gray-200 rounded-lg py-2.5 px-3 focus:border-green-500 outline-none text-sm font-medium transition bg-white cursor-pointer">
                <option value="">-- Semua Status --</option>
                <option value="finalized" <?= $filterStat === 'finalized' ? 'selected' : '' ?>>Sukses</option>
                <option value="pending_review" <?= $filterStat === 'pending_review' ? 'selected' : '' ?>>Perlu Koreksi</option>
                <option value="failed" <?= $filterStat === 'failed' ? 'selected' : '' ?>>Gagal</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-gray-900 hover:bg-gray-800 text-white font-bold py-2.5 rounded-lg text-sm transition shadow-sm">
                Terapkan Filter
            </button>
            <a href="riwayat_keseluruhan.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold py-2.5 px-4 rounded-lg text-sm transition flex items-center justify-center border border-gray-300" title="Reset">
                <i data-feather="refresh-cw" class="w-4 h-4"></i>
            </a>
        </div>

    </form>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
              <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">Total Data</h3>
              <div class="text-gray-400"><i data-feather="database" class="w-5 h-5"></i></div>
          </div>
          <div class="text-3xl font-extrabold text-gray-900"><?= number_format((float)$summary['total']) ?></div>
      </div>

      <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
              <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">Scan Sukses</h3>
              <div class="text-gray-400"><i data-feather="check-circle" class="w-5 h-5"></i></div>
          </div>
          <div class="text-3xl font-extrabold text-gray-900"><?= number_format((float)$summary['sukses']) ?></div>
      </div>

      <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
              <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">Perlu Koreksi</h3>
              <div class="text-gray-400"><i data-feather="alert-circle" class="w-5 h-5"></i></div>
          </div>
          <div class="text-3xl font-extrabold text-gray-900"><?= number_format((float)$summary['koreksi']) ?></div>
      </div>

      <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
              <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">Scan Gagal</h3>
              <div class="text-gray-400"><i data-feather="x-circle" class="w-5 h-5"></i></div>
          </div>
          <div class="text-3xl font-extrabold text-gray-900"><?= number_format((float)$summary['gagal']) ?></div>
      </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-md overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider text-gray-500 font-bold">
            <th class="px-6 py-4">Waktu</th>
            <th class="px-6 py-4">Operator</th>
            <th class="px-6 py-4">Nama File</th>
            <th class="px-6 py-4">Hasil NIK</th>
            <th class="px-6 py-4">Akurasi</th>
            <th class="px-6 py-4 text-right">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if(empty($riwayat)): ?>
            <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 font-medium bg-gray-50/50">Tidak ada data ditemukan.</td></tr>
          <?php else: ?>
            <?php foreach($riwayat as $row): ?>
            <tr class="hover:bg-gray-50 transition duration-150">
              <td class="px-6 py-4 text-sm text-gray-500 font-medium">
                <?= date('d/m/Y H:i', strtotime($row['waktu_upload'])) ?>
              </td>
              <td class="px-6 py-4">
                  <div class="flex items-center gap-2">
                      <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold uppercase">
                          <?= substr($row['operator'] ?? 'S', 0, 1) ?>
                      </div>
                      <span class="text-sm font-bold text-gray-800"><?= htmlspecialchars($row['operator'] ?? 'Sistem') ?></span>
                  </div>
              </td>
              <td class="px-6 py-4 text-sm font-medium text-gray-600 max-w-[150px] truncate" title="<?= htmlspecialchars($row['nama_file_asli']) ?>">
                <?= htmlspecialchars($row['nama_file_asli']) ?>
              </td>
              <td class="px-6 py-4">
                <?php if($row['nik']): ?>
                    <span class="font-mono text-sm text-gray-900 bg-gray-100 px-2 py-1 rounded border border-gray-200"><?= htmlspecialchars($row['nik']) ?></span>
                <?php else: ?>
                    <span class="text-xs text-red-500 font-medium">Kosong</span>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4 text-sm font-bold <?= ((float)$row['skor_kepercayaan'] > 0.8) ? 'text-green-600' : 'text-yellow-600' ?>">
                <?= number_format(((float)$row['skor_kepercayaan']) * 100, 1) ?>%
              </td>
              <td class="px-6 py-4 text-right align-middle">
                <?= badgeAdminStatus($row['status_proses']) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="bg-gray-50 border-t border-gray-200 px-6 py-4 flex items-center justify-between">
       <span class="text-sm text-gray-500 font-medium">Halaman <?= $page ?> dari <?= $totalPages ?></span>
       <div class="flex gap-2">
          <?php
            // PERBAIKAN DI SINI:
            // Pastikan menggunakan variabel $filterOp dan $filterStat yang sudah didefinisikan di atas
            $qs = "&operator_id=".urlencode($filterOp)."&status=".urlencode($filterStat);
          ?>
          <a href="?page=<?= max(1, $page - 1) ?><?= $qs ?>" class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-sm font-bold text-gray-700 hover:bg-gray-100 <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>">Prev</a>
          <a href="?page=<?= min($totalPages, $page + 1) ?><?= $qs ?>" class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-sm font-bold text-gray-700 hover:bg-gray-100 <?= $page >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?>">Next</a>
       </div>
    </div>
  </div>

</main>

<script>feather.replace();</script>
</body>
</html>