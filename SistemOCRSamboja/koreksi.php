<?php
session_start();

// Validasi login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Redirect admin
if (strtolower($_SESSION['role']) === 'admin') {
    header('Location: admin/dashboard_admin.php');
    exit;
}

require_once 'proses/config.php';

// Ambil log_id dari parameter URL
$log_id = $_GET['log_id'] ?? $_GET['id'] ?? null;
if (!$log_id) {
    die('Log ID tidak ditemukan');
}

// Ambil data log OCR
$sql  = "SELECT 
            nik_terdeteksi, 
            nik_final, 
            nama_file_sistem, 
            status_proses 
        FROM log_ocr 
        WHERE log_id = ?";
$stmt = mysqli_prepare($db, $sql);

if (!$stmt) {
    die('Database error');
}

mysqli_stmt_bind_param($stmt, "i", $log_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    die('Data log tidak ditemukan');
}

$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Tentukan NIK yang digunakan
$is_corrected = !empty($data['nik_final']);
$nik_saat_ini = $is_corrected
    ? $data['nik_final']
    : ($data['nik_terdeteksi'] ?? 'TIDAK DITEMUKAN');

// Validasi gambar
$nama_file            = $data['nama_file_sistem'] ?? '';
$path_gambar_browser  = 'public/images/' . $nama_file;
$gambar_exists        = ($nama_file && file_exists($path_gambar_browser));

// Flash data form
$old   = $_SESSION['old'] ?? [];
$error = $_SESSION['error'] ?? null;
unset($_SESSION['old'], $_SESSION['error']);

// Riwayat koreksi (audit log)
$audit_logs = [];

$audit_sql = "SELECT 
                lo.waktu_upload AS waktu_koreksi,
                lo.nik_final AS nik_terkoreksi,
                lo.catatan_koreksi AS catatan,
                sk.nama_lengkap AS operator_nama
            FROM log_ocr lo
            LEFT JOIN staf_kecamatan sk ON lo.id_staf = sk.id
            WHERE lo.log_id = ?
              AND lo.catatan_koreksi IS NOT NULL
            ORDER BY lo.waktu_upload DESC";

$stmt_audit = mysqli_prepare($db, $audit_sql);

if ($stmt_audit) {
    mysqli_stmt_bind_param($stmt_audit, "i", $log_id);
    mysqli_stmt_execute($stmt_audit);
    $audit_result = mysqli_stmt_get_result($stmt_audit);

    while ($row = mysqli_fetch_assoc($audit_result)) {
        $audit_logs[] = $row;
    }
    mysqli_stmt_close($stmt_audit);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Koreksi Data - Sistem OCR KTP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex text-gray-800 antialiased">

    <?php include 'includes/navbar.php'; ?>

    <main class="flex-1 ml-64 p-8 transition-all duration-300">

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Koreksi Data OCR</h1>
                <p class="text-sm text-gray-500 mt-1">ID Dokumen: <span class="font-mono text-gray-700"><?php echo htmlspecialchars($log_id); ?></span></p>
            </div>
      <div class="flex items-center gap-3">
            <a href="hasilriwayat.php?id=<?php echo htmlspecialchars($log_id); ?>" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm">
                <i data-feather="arrow-left" class="w-4 h-4 mr-2"></i> 
                Kembali ke Hasil OCR
            </a>

            <a href="riwayat.php?id=<?php echo htmlspecialchars($log_id); ?>" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm">
                <i data-feather="list" class="w-4 h-4 mr-2"></i> 
                Kembali ke Riwayat Upload
            </a>
        </div>
       
        </div>

        <?php if($error): ?>
            <div class="mb-6 flex items-center p-4 text-red-800 rounded-lg bg-red-50 border border-red-200 shadow-sm" role="alert">
                <i data-feather="alert-circle" class="flex-shrink-0 w-5 h-5 mr-3"></i>
                <div class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <div class="lg:col-span-7 flex flex-col gap-6">
                <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-200 h-full flex flex-col">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-semibold text-gray-900">Preview Gambar KTP</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            <?php echo htmlspecialchars($nama_file); ?>
                        </span>
                    </div>
                    
                    <div class="flex-1 flex items-center justify-center rounded-lg bg-gray-100/50 border-2 border-dashed border-gray-200 p-2 overflow-hidden min-h-[400px]">
                        <?php if ($gambar_exists): ?>
                            <img src="<?php echo htmlspecialchars($path_gambar_browser); ?>" alt="KTP" class="object-contain h-full w-full rounded-lg transition-transform hover:scale-[1.02]">
                        <?php else: ?>
                            <div class="flex flex-col items-center text-gray-400">
                                <i data-feather="image" class="h-12 w-12 mb-3 opacity-50"></i>
                                <span class="text-sm font-medium text-gray-500">Gambar tidak ditemukan di server</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-5 flex flex-col gap-6">
                
                <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-base font-semibold text-gray-900">Data Ekstraksi</h3>
                        <?php if($is_corrected): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                <i data-feather="check-circle" class="w-3 h-3 mr-1"></i> Telah Dikoreksi
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                <i data-feather="cpu" class="w-3 h-3 mr-1"></i> Raw OCR
                            </span>
                        <?php endif; ?>
                    </div>

                    <form action="proses/proses_koreksi.php" method="POST" class="space-y-5">
                        <input type="hidden" name="log_id" value="<?php echo htmlspecialchars($log_id); ?>">

                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">NIK Saat Ini (Di Database)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-feather="database" class="h-4 w-4 text-gray-400"></i>
                                </div>
                                <input type="text" value="<?php echo htmlspecialchars($nik_saat_ini); ?>" disabled 
                                       class="block w-full pl-10 pr-3 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 font-mono text-sm shadow-sm cursor-not-allowed">
                            </div>
                        </div>

                        <hr class="border-gray-100">

                        <div>
                            <label for="nik-terkoreksi" class="block text-xs font-medium text-gray-700 uppercase tracking-wider mb-2">Revisi NIK</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-feather="edit-2" class="h-4 w-4 text-green-500"></i>
                                </div>
                                <input type="text" id="nik-terkoreksi" name="nik_terkoreksi"
                                       value="<?php echo htmlspecialchars($old['nik_terkoreksi'] ?? $nik_saat_ini); ?>"
                                       placeholder="Masukkan 16 digit angka..."
                                       class="block w-full pl-10 pr-3 py-2.5 rounded-lg border-2 border-gray-200 focus:border-green-500 focus:ring-0 text-gray-900 font-mono text-base shadow-sm transition-colors"
                                       required autofocus>
                            </div>
                        </div>

                        <div>
                            <label for="alasan" class="block text-xs font-medium text-gray-700 uppercase tracking-wider mb-2">Alasan Perubahan</label>
                            <div class="relative">
                                <select id="alasan" name="alasan" required
                                        class="block w-full pl-3 pr-10 py-2.5 rounded-lg border-gray-300 text-sm focus:border-green-500 focus:ring-green-500 shadow-sm bg-white appearance-none">
                                    <option value="" disabled <?php echo empty($old['alasan']) ? 'selected' : ''; ?>>-- Pilih alasan --</option>
                                    <option value="salah_angka" <?php echo ($old['alasan'] ?? '')=='salah_angka'?'selected':''; ?>>Salah tebak angka oleh sistem</option>
                                    <option value="angka_kurang" <?php echo ($old['alasan'] ?? '')=='angka_kurang'?'selected':''; ?>>Jumlah digit terbaca kurang dari 16</option>
                                    <option value="area_tidak_terbaca" <?php echo ($old['alasan'] ?? '')=='area_tidak_terbaca'?'selected':''; ?>>Area NIK buram / cacat fisik</option>
                                    <option value="lainnya" <?php echo ($old['alasan'] ?? '')=='lainnya'?'selected':''; ?>>Alasan lainnya</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                    <i data-feather="chevron-down" class="h-4 w-4"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="catatan" class="block text-xs font-medium text-gray-700 uppercase tracking-wider mb-2">Catatan Tambahan (Opsional)</label>
                            <textarea id="catatan" name="catatan" rows="3" placeholder="Tulis rincian jika perlu..."
                                      class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500 resize-none py-2.5"><?php echo htmlspecialchars($old['catatan'] ?? ''); ?></textarea>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full flex justify-center items-center gap-2 rounded-lg bg-green-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all active:scale-[0.98]">
                                <i data-feather="save" class="h-4 w-4"></i>
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-200">
                    <div class="flex items-center gap-2 mb-6">
                        <i data-feather="clock" class="w-5 h-5 text-gray-400"></i>
                        <h3 class="text-base font-semibold text-gray-900">Riwayat Revisi</h3>
                    </div>

                    <?php if (empty($audit_logs)): ?>
                        <div class="text-center py-6 bg-gray-50 rounded-lg border border-gray-100">
                            <p class="text-sm text-gray-500">Belum ada riwayat koreksi.</p>
                        </div>
                    <?php else: ?>
                        <div class="relative border-l-2 border-gray-100 ml-3 space-y-6 pb-2">
                            <?php foreach ($audit_logs as $log): ?>
                                <div class="relative pl-6">
                                    <span class="absolute -left-[9px] top-1.5 h-4 w-4 rounded-full bg-green-100 border-2 border-green-500 flex items-center justify-center"></span>
                                    
                                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-semibold text-gray-900">
                                                <?= htmlspecialchars($log['operator_nama'] ?? 'System') ?>
                                            </span>
                                            <span class="text-[11px] text-gray-500">
                                                <?= date('d M Y, H:i', strtotime($log['waktu_koreksi'] ?? '')) ?>
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-600 mb-1">
                                            Ubah ke: <span class="font-mono font-medium text-gray-900 bg-white px-1.5 py-0.5 rounded border border-gray-200"><?= htmlspecialchars($log['nik_terkoreksi'] ?? '') ?></span>
                                        </div>
                                        <?php if (!empty($log['catatan'])): ?>
                                            <p class="text-xs text-gray-500 bg-white p-2 rounded border border-gray-100 mt-2">
                                                <i data-feather="message-square" class="w-3 h-3 inline mr-1 text-gray-400"></i>
                                                <?= htmlspecialchars($log['catatan']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <script>
        // Render feather icons
        feather.replace();
    </script>
</body>
</html>