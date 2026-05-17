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

// Ambil log_id dari session
if (isset($_SESSION['current_log_id'])) {
    $log_id = $_SESSION['current_log_id'];
} elseif (isset($_SESSION['last_log_id'])) {
    $log_id = $_SESSION['last_log_id'];
} else {
    header('Location: upload.php');
    exit;
}

// Hapus session log lama
unset($_SESSION['last_log_id']);

// Ambil data OCR
$sql  = "SELECT * FROM log_ocr WHERE log_id = ?";
$stmt = mysqli_prepare($db, $sql);

if (!$stmt) {
    die('Database error');
}

mysqli_stmt_bind_param($stmt, "i", $log_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    header('Location: upload.php');
    exit;
}

$hasil = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Data hasil OCR
$nik_terdeteksi = $hasil['nik_terdeteksi'] ?? 'TIDAK DITEMUKAN';
$skor           = ((float)($hasil['skor_kepercayaan'] ?? 0)) * 100;
$file_asli      = $hasil['nama_file_asli'];
// Ambil raw_text dari database
$raw_text = $hasil['raw_text'] ?? 'Data mentah tidak tersedia.';

// Status keberhasilan sementara
$is_success = (
    $hasil['status_proses'] === 'pending_review' &&
    !empty($hasil['nik_terdeteksi'])
);

// Default tampilan skor
$skor_badge_class = 'bg-red-100 text-red-800';
$skor_text        = 'Rendah';
$progress_class   = 'bg-red-600';

// Klasifikasi skor
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
if (strlen($nik_terdeteksi) === 16) {
    $nik_formatted =
        substr($nik_terdeteksi, 0, 4) . ' ' .
        substr($nik_terdeteksi, 4, 4) . ' ' .
        substr($nik_terdeteksi, 8, 4) . ' ' .
        substr($nik_terdeteksi, 12, 4);
}

// Path gambar hasil OCR
$path_gambar_browser = 'public/images/' . ($hasil['nama_file_sistem'] ?? '');
$path_gambar_server  = $path_gambar_browser;

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
  <link rel="stylesheet" href="../assets/css/style.css" />
    <script src="../assets/js/chart.min.js"></script>
    <style>
    /* Deklarasi Font Inter Regular (400) */
    @font-face {
        font-family: 'Inter';
        src: url('../assets/fonts/Inter-Regular.ttf') format('truetype');
        font-weight: 400;
        font-style: normal;
    }

    /* Deklarasi Font Inter SemiBold (600) */
    @font-face {
        font-family: 'Inter';
        src: url('../assets/fonts/Inter-SemiBold.ttf') format('truetype');
        font-weight: 600;
        font-style: normal;
    }

    /* Deklarasi Font Inter Bold (700) */
    @font-face {
        font-family: 'Inter';
        src: url('../assets/fonts/static/Inter-Bold.ttf') format('truetype');
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

  <?php include 'includes/navbar.php'; ?>

  <div class="flex-1 ml-64 p-6 md:p-10 space-y-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">Hasil Proses OCR</h1>

    <?php if ($is_success): ?>
    <div class="flex items-start space-x-3 rounded-lg bg-green-50 p-4 border border-green-200">
      <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-green-600"><i data-feather="check-circle" class="h-5 w-5"></i></span>
      <div>
        <h3 class="text-sm font-semibold text-green-800">Proses Berhasil (Menunggu Persetujuan)</h3>
        <p class="text-sm text-green-700">Data NIK berhasil diekstrak. Silakan periksa dan simpan.</p>
      </div>
    </div>
    <?php else: ?>
    <div class="flex items-start space-x-3 rounded-lg bg-red-50 p-4 border border-red-200">
      <span class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 text-red-600"><i data-feather="x-circle" class="h-5 w-5"></i></span>
      <div>
        <h3 class="text-sm font-semibold text-red-800">Proses Gagal</h3>
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
            <span class="text-xs text-gray-500 mt-2">Area NIK yang diekstrak</span>
          </div>
        </div>
      </div>




      
<div class="rounded-lg bg-white p-6 shadow-sm border border-gray-200 mt-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Raw Pembacaan Mesin OCR (Bounding Box)</h3>
        <div class="flex items-center justify-center rounded-lg bg-gray-100 border border-gray-200 p-2 overflow-hidden">
            <?php 
                
                $python_dir = realpath(__DIR__ . '/PYTHON_OCR/temp_uploads/');
               
                if (!$python_dir || !file_exists($python_dir)) {
                    $python_dir = 'C:/laragon/www/SistemOCRSamboja/PYTHON_OCR/temp_uploads/';
                }
                
                $python_dir = rtrim($python_dir, '/\\') . '/';

                $found_physical_path = '';
                
                if (file_exists($python_dir)) {
                    $semua_file_annotated = glob($python_dir . 'annotated_*.*');
                    
                    if (!empty($semua_file_annotated)) {
                        usort($semua_file_annotated, function($a, $b) {
                            return filemtime($b) - filemtime($a);
                        });
                        
                        $found_physical_path = $semua_file_annotated[0];
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


<!-- PANEL DEBUG & ANALISIS OCR (STYLE NATIVE ADMIN PANEL) -->
      <div class="mb-6">
          <!-- Tombol Pemicu -->
          <button type="button" onclick="toggleRawData()" class="w-full flex items-center justify-between px-4 py-3 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none transition-colors">
              <div class="flex items-center gap-2 text-gray-700">
                  <i data-feather="terminal" class="w-4 h-4"></i>
                  <span class="text-sm font-medium">Lihat Detail Log OCR & Kalkulasi Sistem</span>
              </div>
              <i data-feather="chevron-down" id="chevronRaw" class="w-4 h-4 text-gray-500 transition-transform"></i>
          </button>

   <!-- Kontainer Dropdown -->
          <div id="rawDataContainer" class="hidden mt-2">
              <div class="bg-white border border-gray-300 rounded-md p-4">
                  
                  <?php 
                      // BONGKAR PAKET JSON TRANSPARAN DARI PYTHON
                      $raw_data_json = json_decode($raw_text, true);
                      $raw_list = $raw_data_json['semua_bounding_box'] ?? [];
                      $raw_ocr_score = (float)($raw_data_json['skor_mentah_easyocr'] ?? 0);
                      $keyword_jml = (int)($raw_data_json['keyword_ditemukan'] ?? 0);
                      $is_16_digit = $raw_data_json['genap_16_digit'] ?? false;
                      
                      // Cek metode skor (Normal vs Fallback)
                      $is_fallback = true;
                      foreach ($raw_list as $item) {
                          if (abs((float)($item['score'] ?? 0) - $raw_ocr_score) < 0.001) {
                              $is_fallback = false;
                              break;
                          }
                      }

                      // LOGIKA FILTER: PISAHKAN NIK (LIST) DAN KEYWORD (KOTAK-KOTAK)
                      $daftar_keyword_ktp = ["PROVINSI", "KABUPATEN", "KOTA", "NAM", "LAHIR", "ALAMAT", "AGAMA", "DARAH", "KAWIN", "PEKERJAAN", "WARGA", "BERLAKU", "KELURAHAN", "DESA", "RT", "RW", "GOL"];
                      
                      $list_nik_lurus = [];
                      $list_keyword_kotak = [];

                      foreach($raw_list as $item) {
                          $txt = htmlspecialchars($item['text'] ?? '');
                          $scr = (float)($item['score'] ?? 0);
                          $cek_teks = strtoupper(preg_replace('/\s+/', '', $txt));

                          // 1. Cek Tulisan "NIK" atau Angka KTP (Min 10 digit biar tgl lahir ga masuk)
                          if (strpos($cek_teks, 'NIK') !== false || preg_match('/\d{10,}/', $cek_teks)) {
                              $list_nik_lurus[] = ['text' => $txt, 'score' => $scr];
                              continue; // Kalau udah masuk list NIK, jangan dimasukin ke kotak keyword
                          }

                          // 2. Cek Kata Kunci KTP
                          foreach($daftar_keyword_ktp as $kw) {
                              if (strpos($cek_teks, $kw) !== false) {
                                  $list_keyword_kotak[] = ['text' => $txt, 'score' => $scr];
                                  break;
                              }
                          }
                      }
                      
                  ?>

<

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      
                      <!-- KOLOM KIRI: DATA MENTAH (DIBAGI 2 SESUAI REQUEST) -->
                      <div class="flex flex-col gap-4 h-full">
                          
                          <!-- BAGIAN ATAS: LIST LURUS NATIVE (HANYA NIK DAN ANGKA) -->
                          <div class="flex flex-col">
                              <h6 class="text-xs font-bold text-gray-700 uppercase mb-2 border-b border-gray-200 pb-2 flex items-center gap-1.5"><i data-feather="target" class="w-3.5 h-3.5 text-blue-500"></i> Area NIK & Angka</h6>
                              <div class="bg-gray-50 border border-gray-200 rounded max-h-[180px] overflow-y-auto">
                                  <?php if (empty($list_nik_lurus)): ?>
                                      <p class="text-xs text-gray-400 italic p-4 text-center">Tidak ada tulisan NIK / Angka panjang terdeteksi.</p>
                                  <?php else: ?>
                                      <ul class="divide-y divide-gray-200/60">
                                          <?php foreach($list_nik_lurus as $item): ?>
                                          <li class="flex justify-between items-start p-2.5 hover:bg-white transition-colors">
                                              <span class="text-sm font-mono text-gray-800 font-semibold break-words pr-4"><?= $item['text'] ?></span>
                                              <span class="text-[11px] font-bold text-gray-500 px-2 py-1 rounded bg-gray-200/50 whitespace-nowrap">
                                                  <?= number_format($item['score'] * 100, 1) ?>%
                                              </span>
                                          </li>
                                          <?php endforeach; ?>
                                      </ul>
                                  <?php endif; ?>
                              </div>
                          </div>

                      <!-- BAGIAN BAWAH: KEYWORD DIBUAT KOTAK-KOTAK (GRID ANTI TEMBUS) -->
                          <div class="flex flex-col mt-4">
                              <h6 class="text-xs font-bold text-gray-700 uppercase mb-2 border-b border-gray-200 pb-2 flex items-center gap-1.5"><i data-feather="tag" class="w-3.5 h-3.5 text-blue-500"></i> Kata Kunci Terdeteksi</h6>
                              <div class="bg-white border border-gray-200 rounded p-3 max-h-[140px] overflow-y-auto">
                                  <?php if (empty($list_keyword_kotak)): ?>
                                      <p class="text-xs text-gray-400 italic text-center">Tidak ada kata kunci terdeteksi.</p>
                                  <?php else: ?>
                                      <!-- GUE GANTI JADI GRID-COLS-2 BIAR MUTLAK MAKSIMAL 2 KOTAK LALU TURUN -->
                                      <div class="grid grid-cols-2 gap-2">
                                          <?php foreach($list_keyword_kotak as $kw_item): ?>
                                          <div class="flex justify-between items-center bg-gray-50 border border-gray-200 px-2 py-1.5 rounded shadow-sm overflow-hidden" title="<?= htmlspecialchars($kw_item['text']) ?>">
                                              <!-- Class truncate biar kalau teks kepanjangan otomatis dipotong titik-titik -->
                                              <span class="text-[10px] font-bold text-gray-600 uppercase truncate pr-1"><?= htmlspecialchars($kw_item['text']) ?></span>
                                              <span class="text-[9px] font-medium text-gray-400 border-l border-gray-200 pl-1.5 shrink-0"><?= number_format($kw_item['score']*100, 0) ?>%</span>
                                          </div>
                                          <?php endforeach; ?>
                                      </div>
                                  <?php endif; ?>
                              </div>
                          </div>

                      </div>

                      <!-- KOLOM KANAN: KALKULASI GATEKEEPER (TETAP SAMA) -->
                      <div class="flex flex-col h-full">
                          <h6 class="text-xs font-bold text-gray-700 uppercase mb-2 border-b border-gray-200 pb-2">Proses Kalkulasi Sistem</h6>
                          
                          <!-- Info Kandidat & Metode -->
                          <div class="bg-blue-50 border border-blue-200 rounded p-3 mb-3">
                              <div class="flex justify-between items-end mb-2">
                                  <div>
                                      <p class="text-[10px] text-blue-600 uppercase font-semibold">Kandidat Teks NIK</p>
                                      <p class="text-sm font-mono font-bold text-blue-900"><?= $nik_terdeteksi ?: 'TIDAK DITEMUKAN' ?></p>
                                  </div>
                                  <div class="text-right">
                                      <p class="text-[10px] text-blue-600 uppercase font-semibold">Skor Awal</p>
                                      <p class="text-sm font-bold text-blue-900"><?= number_format($raw_ocr_score * 100, 1) ?>%</p>
                                  </div>
                              </div>
                              <p class="text-[10px] text-blue-700 border-t border-blue-100 pt-1.5 mt-1">
                                  <i data-feather="info" class="w-3 h-3 inline mr-1"></i>
                                  Metode Pengambilan Skor: <strong><?= $is_fallback ? 'Fallback (Rata-rata Gabungan)' : 'Normal (Bounding Box)' ?></strong>
                              </p>
                          </div>

                          <!-- Info Parameter Validasi -->
                          <div class="border border-gray-200 rounded p-3 mb-3">
                              <p class="text-[10px] font-semibold text-gray-500 uppercase mb-2">Pengecekan Parameter</p>
                              <div class="flex items-center gap-2 mb-1">
                                  <i data-feather="<?= $is_16_digit ? 'check' : 'x' ?>" class="w-3.5 h-3.5 <?= $is_16_digit ? 'text-green-600' : 'text-red-600' ?>"></i>
                                  <span class="text-xs text-gray-700">Validasi 16 Digit: <strong><?= $is_16_digit ? 'Lolos' : 'Gagal' ?></strong></span>
                              </div>
                              <div class="flex items-center gap-2">
                                  <i data-feather="<?= $keyword_jml > 0 ? 'check' : 'alert-circle' ?>" class="w-3.5 h-3.5 <?= $keyword_jml > 0 ? 'text-green-600' : 'text-yellow-600' ?>"></i>
                                  <span class="text-xs text-gray-700">Keyword KTP Ditemukan: <strong><?= $keyword_jml ?> Kata</strong></span>
                              </div>
                          </div>

                          <!-- Hasil Final -->
                          <div class="bg-gray-50 border border-gray-200 rounded p-3 flex-1 flex flex-col justify-center">
                              <p class="text-[10px] font-semibold text-gray-500 uppercase mb-1">Skor Kepercayaan Final</p>
                              <p class="text-2xl font-bold <?= $skor >= 70 ? 'text-green-600' : 'text-red-600' ?>"><?= number_format($skor, 1) ?>%</p>
                              
                              <div class="mt-2 bg-white border border-gray-200 rounded p-2">
                                  <?php if ($skor == 50 && $raw_ocr_score > 0.5): ?>
                                      <p class="text-[10px] text-red-600 font-medium">Sistem memotong skor menjadi maksimal 50% karena tidak ada atribut KTP yang terdeteksi.</p>
                                  <?php elseif ($skor > ($raw_ocr_score * 100)): ?>
                                      <div class="text-[10px] text-gray-600 space-y-1 font-mono">
                                          <div class="flex justify-between border-b border-gray-100 pb-1">
                                              <span>Skor Awal OCR:</span> 
                                              <span><?= number_format($raw_ocr_score * 100, 1) ?>%</span>
                                          </div>
                                          <div class="flex justify-between text-green-600 pb-1">
                                              <span>Boost (16 Digit Valid):</span> 
                                              <span>+15.0%</span>
                                          </div>
                                          <div class="flex justify-between font-bold text-gray-800 border-t border-gray-200 pt-1">
                                              <span>Total Akhir:</span> 
                                              <span><?= number_format($skor, 1) ?>%</span>
                                          </div>
                                      </div>
                                  <?php else: ?>
                                      <p class="text-[10px] text-green-700 font-medium">Validasi normal (Sistem menggunakan skor awal OCR tanpa perubahan).</p>
                                  <?php endif; ?>
                              </div>

                          
                                      <?php if ($skor > ($raw_ocr_score * 100)): ?>
                                          <!-- Detail skor awal dan boost... -->
                                      <?php else: ?>
                                          <p class="text-[10px] text-green-700 font-medium">Validasi normal tanpa algoritma Boost.</p>
                                      <?php endif; ?>
                                      
                                      <!-- TAMBAHIN KODE INI DI BAWAHNYA -->
                                      <?php if ($skor >= 97): ?>
                                      <div class="mt-2.5 bg-blue-50/50 border border-blue-100 rounded p-2 flex items-start gap-1.5">
                                          <i data-feather="info" class="w-3 h-3 text-blue-500 shrink-0 mt-0.5"></i>
                                          <p class="text-[9px] leading-relaxed text-blue-700 font-medium">
                                              Skor kepercayaan mesin dibatasi maksimal 97%. Skor 100% hanya diberikan jika data telah diverifikasi manual oleh operator manusia.
                                          </p>
                                      </div>
                                      <?php endif; ?>
                          </div>
                      </div>

                  </div>
              </div>
          </div>

      <div class="flex flex-col md:flex-row justify-between items-center gap-4 border-t border-gray-200 pt-6">
        <a href="upload.php" class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
          <i data-feather="upload" class="h-4 w-4"></i><span>Upload Baru</span>
        </a>
        <div class="flex items-center gap-4">
          
          <a href="koreksi.php?log_id=<?php echo $log_id; ?>" class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
            <i data-feather="edit-3" class="h-4 w-4"></i><span>Koreksi Data</span>
          </a>

          <a href="proses/proses_simpan.php?log_id=<?php echo $log_id; ?>" 
             class="flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-green-700"
             <?php if (!$is_success) echo 'style="display:none;"'; // Sembunyikan kalo NIK gak ketemu ?>
             >
            <i data-feather="check" class="h-4 w-4"></i>
            <span>Simpan Final & Selesai</span>
          </a>
        </div>
      </div>
    </div>
  </div>


  <script src="../assets/js/feather.min.js"></script>
  <script src="../assets/js/sweetalert2.all.min.js"></script>
  <script src="../assets/js/cropper.min.js"></script>

  <script>
    feather.replace();

    // Fungsi buat buka/tutup dropdown Raw Data
    function toggleRawData() {
        const container = document.getElementById('rawDataContainer');
        const chevron = document.getElementById('chevronRaw');
        
        // Toggle class hidden buat nampilin/nyembunyiin
        container.classList.toggle('hidden');
        
        // Puter icon panah 180 derajat
        chevron.classList.toggle('rotate-180');
    }

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