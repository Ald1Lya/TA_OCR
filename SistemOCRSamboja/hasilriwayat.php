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

// Ambil log_id dari URL atau session
$log_id = null;

if (isset($_GET['id'])) {
    $log_id = (int) $_GET['id'];
} elseif (isset($_SESSION['current_log_id'])) {
    $log_id = $_SESSION['current_log_id'];
} elseif (isset($_SESSION['last_log_id'])) {
    $log_id = $_SESSION['last_log_id'];
} else {
    header('Location: riwayat.php');
    exit;
}

// Ambil data OCR berdasarkan log_id
$sql  = "SELECT * FROM log_ocr WHERE log_id = ?";
$stmt = mysqli_prepare($db, $sql);

if (!$stmt) {
    die('Database error');
}

mysqli_stmt_bind_param($stmt, "i", $log_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    echo "<script>
            alert('Data riwayat tidak ditemukan');
            window.location='riwayat.php';
          </script>";
    exit;
}

$hasil = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Data utama hasil OCR
$nik_terdeteksi = $hasil['nik_final'] 
    ?? $hasil['nik_terdeteksi'] 
    ?? 'TIDAK DITEMUKAN';

$skor      = ((float)($hasil['skor_kepercayaan'] ?? 0)) * 100;
$file_asli = $hasil['nama_file_asli'];
$status    = $hasil['status_proses'];

// Status sukses proses
$is_success = (
    in_array($status, ['pending_review', 'finalized', 'Berhasil']) &&
    !empty($nik_terdeteksi)
);

// Default tampilan skor
$skor_badge_class = 'bg-red-100 text-red-800';
$skor_text        = 'Rendah';
$progress_class   = 'bg-red-600';

// Klasifikasi skor kepercayaan
if ($skor > 95) {
    $skor_badge_class = 'bg-green-100 text-green-800';
    $skor_text        = 'Sangat Tinggi';
    $progress_class   = 'bg-green-600';
} elseif ($skor > 80) {
    $skor_badge_class = 'bg-blue-100 text-blue-800';
    $skor_text        = 'Tinggi';
    $progress_class   = 'bg-blue-600';
} elseif ($skor > 50) {
    $skor_badge_class = 'bg-yellow-100 text-yellow-800';
    $skor_text        = 'Cukup';
    $progress_class   = 'bg-yellow-600';
}

// Format NIK agar mudah dibaca
$nik_formatted = $nik_terdeteksi;
if (strlen($nik_terdeteksi) === 16 && is_numeric($nik_terdeteksi)) {
    $nik_formatted =
        substr($nik_terdeteksi, 0, 4) . ' ' .
        substr($nik_terdeteksi, 4, 4) . ' ' .
        substr($nik_terdeteksi, 8, 4) . ' ' .
        substr($nik_terdeteksi, 12, 4);
}

// Validasi file gambar
$path_gambar_browser = 'public/images/' . ($hasil['nama_file_sistem'] ?? '');
$path_gambar_server  = __DIR__ . '/public/images/' . ($hasil['nama_file_sistem'] ?? '');

$gambar_exists = (
    !empty($hasil['nama_file_sistem']) &&
    file_exists($path_gambar_server)
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Hasil OCR - Sistem KTP</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen flex text-gray-800">

  <?php include 'includes/navbar.php'; ?>

  <div class="flex-1 ml-64 p-6 md:p-10 space-y-8">
    
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-900">Hasil Proses OCR</h1>
        <a href="riwayat.php" class="text-sm text-gray-500 hover:text-green-600 underline">Kembali ke Riwayat</a>
    </div>

    <?php if ($is_success): ?>
    <div class="flex items-start space-x-3 rounded-lg bg-green-50 p-4 border border-green-200">
      <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-green-600"><i data-feather="check-circle" class="h-5 w-5"></i></span>
      <div>
        <h3 class="text-sm font-semibold text-green-800">
            <?php echo ($status === 'finalized') ? 'Data Sudah Final' : 'Proses Berhasil (Menunggu Persetujuan)'; ?>
        </h3>
        <p class="text-sm text-green-700">Data NIK berhasil diekstrak. Silakan periksa.</p>
      </div>
    </div>
    <?php else: ?>
    <div class="flex items-start space-x-3 rounded-lg bg-red-50 p-4 border border-red-200">
      <span class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 text-red-600"><i data-feather="x-circle" class="h-5 w-5"></i></span>
      <div>
        <h3 class="text-sm font-semibold text-red-800">Proses Gagal / NIK Kosong</h3>
        <p class="text-sm text-red-700">NIK tidak dapat dideteksi dari file <?php echo htmlspecialchars($file_asli); ?>. Silakan lakukan koreksi manual.</p>
      </div>
    </div>
    <?php endif; ?>

    <div class="space-y-8">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div class="rounded-lg bg-white p-6 shadow-sm border border-gray-200">
          <h3 class="text-sm font-medium text-gray-500 mb-3">NIK TERDETEKSI</h3>
          <div class="flex items-center justify-between">
            <span id="nikValue" class="text-3xl font-semibold text-gray-800 tracking-wider"><?php echo htmlspecialchars($nik_formatted); ?></span>
            <button id="copyNikBtn" class="text-gray-400 hover:text-green-600 p-2 rounded-lg hover:bg-gray-100">
              <i data-feather="copy" class="h-5 w-5"></i>
            </button>
          </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm border border-gray-200">
          <div class="flex justify-between items-start mb-2">
            <h3 class="text-sm font-medium text-gray-500">SKOR KEPERCAYAAN</h3>
            <span class="px-2 py-0.5 text-xs font-medium rounded-full <?php echo $skor_badge_class; ?>"><?php echo $skor_text; ?></span>
          </div>
          <h2 class="text-3xl font-semibold <?php echo ($is_success) ? 'text-green-600' : 'text-red-600'; ?> mb-3"><?php echo number_format($skor, 1); ?>%</h2>
          <div class="w-full bg-gray-200 rounded-full h-2.5">
            <div class="<?php echo $progress_class; ?> h-2.5 rounded-full" style="width: <?php echo $skor; ?>%"></div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="rounded-lg bg-white p-6 shadow-sm border border-gray-200">
          <h3 class="text-sm font-semibold text-gray-900 mb-4">Gambar Original (<?php echo htmlspecialchars($file_asli); ?>)</h3>
          <div class="flex items-center justify-center h-64 rounded-lg bg-gray-100 border border-gray-200">
            <?php if ($gambar_exists): ?>
              <img src="<?php echo htmlspecialchars($path_gambar_browser); ?>" alt="Gambar KTP Asli" class="object-contain h-64 w-full rounded-lg">
            <?php else: ?>
              <i data-feather="file-text" class="h-12 w-12 text-gray-400"></i>
              <span class="ml-2 text-sm text-gray-500">Gambar tidak ditemukan</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="rounded-lg bg-white p-6 shadow-sm border border-gray-200">
          <h3 class="text-sm font-semibold text-gray-900 mb-4">Area NIK Terdeteksi</h3>
          <div class="flex flex-col items-center justify-center h-64 rounded-lg bg-gray-100 border-2 <?php echo ($is_success) ? 'border-green-600' : 'border-red-600'; ?> p-4">
            <span class="text-2xl font-mono text-gray-700"><?php echo htmlspecialchars($nik_terdeteksi); ?></span>
            <span class="text-xs text-gray-500 mt-2">Data hasil ekstraksi</span>
          </div>
        </div>
      </div>

<div class="rounded-lg bg-white p-6 shadow-sm border border-gray-200 mt-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Raw Pembacaan Mesin OCR (Bounding Box)</h3>
        <div class="flex items-center justify-center rounded-lg bg-gray-100 border border-gray-200 p-2 overflow-hidden">
            <?php 
                
 $python_dir = realpath(__DIR__ . '/PYTHON_OCR/temp_uploads/');
              
                // Path Hardcode disesuaikan dengan struktur final Tugas Akhir lu
                if (!$python_dir || !file_exists($python_dir)) {
                    $python_dir = 'C:/laragon/www/TugasAkhirCapstone/SistemOCRSamboja/PYTHON_OCR/temp_uploads/';
                }
              
                $python_dir = rtrim($python_dir, '/\\') . '/';

                $found_physical_path = '';
                
                if (file_exists($python_dir) && !empty($hasil['nama_file_sistem'])) {
                    // Kita cari file BERDASARKAN NAMA SISTEM, bukan waktu terbaru
                    $nama_file = $hasil['nama_file_sistem']; // cth: ktp_123.jpg
                    
                    // Target 1: Nama yang presisi (annotated_ktp_123.jpg)
                    $target_presisi = $python_dir . 'annotated_' . $nama_file;
                    
                    if (file_exists($target_presisi)) {
                        $found_physical_path = $target_presisi;
                    } else {
                  
                        $nama_murni = pathinfo($nama_file, PATHINFO_FILENAME);
                        $pencarian = glob($python_dir . '*annotated_*' . $nama_murni . '*');
                        
                        if (!empty($pencarian)) {
                            $found_physical_path = $pencarian[0];
                        }
                    }
                }

                $img_src = '';
                if (!empty($found_physical_path)) {
                    $img_data = @file_get_contents($found_physical_path);
                    if ($img_data !== false) {
                        $type = pathinfo($found_physical_path, PATHINFO_EXTENSION);
                        $type = empty($type) ? 'jpeg' : $type;
                        $img_src = 'data:image/' . $type . ';base64,' . base64_encode($img_data);
                    }
                }
                
                if (!empty($img_src)): 
            ?>
                <img src="<?php echo $img_src; ?>" alt="Annotated OCR" class="max-h-[600px] w-auto rounded-lg object-contain border border-gray-300">
            <?php else: ?>
                <div class="flex flex-col items-center py-12 text-center">
                    <i data-feather="alert-circle" class="h-12 w-12 text-red-400 mb-2"></i>
                    <span class="text-sm text-red-500 font-semibold">Gambar Raw OCR Gagal Ditemukan!</span>
                    <span class="text-xs text-gray-500 mt-2">Mencari di folder: <?php echo htmlspecialchars($python_dir); ?></span>
                </div>
            <?php endif; ?>
        </div>
      </div>


      <div class="flex flex-col md:flex-row justify-between items-center gap-4 border-t border-gray-200 pt-6">
        <a href="upload.php" class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
          <i data-feather="upload" class="h-4 w-4"></i><span>Upload Baru</span>
        </a>
        <div class="flex items-center gap-4">
          
          <a href="koreksi.php?id=<?php echo $log_id; ?>" class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
            <i data-feather="edit-3" class="h-4 w-4"></i>
            <span>
                <?php echo ($status === 'finalized') ? 'Revisi Data' : 'Koreksi Data'; ?>
            </span>
          </a>

          <?php if ($status !== 'finalized'): ?>
            <a href="proses/proses_simpan.php?log_id=<?php echo $log_id; ?>" 
               class="flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-green-700"
               <?php if (!$is_success) echo 'style="display:none;"'; ?>
               >
              <i data-feather="check" class="h-4 w-4"></i>
              <span>Simpan Final & Selesai</span>
            </a>
          <?php else: ?>
             <button disabled class="flex items-center gap-2 rounded-lg bg-gray-300 px-5 py-2.5 text-sm font-medium text-white shadow-sm cursor-not-allowed">
               <i data-feather="check" class="h-4 w-4"></i>
               <span>Data Sudah Disimpan</span>
             </button>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>

  <script>
    feather.replace();
    const copyBtn = document.getElementById('copyNikBtn');
    copyBtn.addEventListener('click', () => {
      const nik = '<?php echo htmlspecialchars($nik_terdeteksi); ?>'; 
      if (!nik || nik === 'TIDAK DITEMUKAN') {
        alert('Tidak ada NIK untuk disalin.');
        return;
      }
      navigator.clipboard.writeText(nik).then(() => {
        copyBtn.innerHTML = '<i data-feather="check" class="h-5 w-5 text-green-600"></i>';
        feather.replace();
        setTimeout(() => {
          copyBtn.innerHTML = '<i data-feather="copy" class="h-5 w-5"></i>';
          feather.replace();
        }, 1500);
      }).catch(() => alert('Gagal menyalin NIK.'));
    });
  </script>
</body>
</html>