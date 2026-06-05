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
require_once 'proses/csrf.php';

// Ambil log_id dari URL atau session
$log_id = null;

if (isset($_GET['id'])) {
    $log_id = (int) $_GET['id'];
} elseif (isset($_SESSION['active_log_id'])) { // <--- INI PENYELAMATNYA LEK!
    $log_id = $_SESSION['active_log_id'];
    unset($_SESSION['active_log_id']); // Langsung bersihin biar ga nyangkut
} elseif (isset($_SESSION['current_log_id'])) {
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

$skor      = ((float) ($hasil['skor_kepercayaan'] ?? 0)) * 100;
$file_asli = $hasil['nama_file_asli'];
$status    = $hasil['status_proses'];
$raw_text  = $hasil['raw_text'] ?? '';

$is_success = (
    in_array($status, ['pending_review', 'finalized', 'Berhasil']) &&
    !empty($nik_terdeteksi)
);

$skor_badge_class = 'bg-red-100 text-red-800';
$skor_text        = 'Rendah';
$progress_class   = 'bg-red-600';

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

// Format NIK dengan spasi setiap 4 digit untuk keterbacaan
$nik_formatted = $nik_terdeteksi;
if (strlen($nik_terdeteksi) === 16 && is_numeric($nik_terdeteksi)) {
    $nik_formatted =
        substr($nik_terdeteksi, 0, 4) . ' ' .
        substr($nik_terdeteksi, 4, 4) . ' ' .
        substr($nik_terdeteksi, 8, 4) . ' ' .
        substr($nik_terdeteksi, 12, 4);
}

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
    <link rel="icon" type="image/png" href="../assetimage/logo.png" />
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
                $python_dir = realpath(__DIR__ . './PYTHON_OCR/temp_uploads/') . DIRECTORY_SEPARATOR;

                $found_physical_path = '';
                
                if (is_dir($python_dir) && !empty($hasil['nama_file_sistem'])) {
                    $nama_file = $hasil['nama_file_sistem'];
                    
                    // Cari file annotated berdasarkan nama file sistem yang spesifik
                    $target_presisi = $python_dir . 'annotated_' . $nama_file;
                    
                    if (file_exists($target_presisi)) {
                        $found_physical_path = $target_presisi;
                    } else {
                        $nama_murni = pathinfo($nama_file, PATHINFO_FILENAME);
                        $pencarian  = glob($python_dir . '*annotated_*' . $nama_murni . '*');
                        
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
    <!-- Kontainer Dropdown -->
          <div id="rawDataContainer" class="hidden mt-2">
              <div class="bg-white border border-gray-300 rounded-md p-4">
                  
                  <?php
                      $raw_data_json = json_decode($raw_text, true);
                      $raw_list      = $raw_data_json['semua_bounding_box'] ?? [];
                      $raw_ocr_score = (float) ($raw_data_json['skor_mentah_easyocr'] ?? 0);
                      $keyword_jml   = (int) ($raw_data_json['keyword_ditemukan'] ?? 0);
                      $is_16_digit   = $raw_data_json['genap_16_digit'] ?? false;

                      // Deteksi apakah skor diambil dari bounding box langsung atau rata-rata fallback
                      $is_fallback = true;
                      foreach ($raw_list as $item) {
                          if (abs((float) ($item['score'] ?? 0) - $raw_ocr_score) < 0.001) {
                              $is_fallback = false;
                              break;
                          }
                      }

                     $daftar_keyword_ktp = ["PROVINSI", "KABUPATEN", "KOTA", "NIK", "NAM", "LAHIR", "ALAMAT", "AGAMA", "DARAH", "KAWIN", "PEKERJAAN", "WARGA", "BERLAKU", "KELURAHAN", "DESA", "RT", "RW", "GOL"];

                      $list_nik_lurus     = []; 
                      $list_keyword_kotak = [];

                      foreach($raw_list as $item) {
                          $txt      = htmlspecialchars($item['text'] ?? '');
                          $scr      = (float) ($item['score'] ?? 0);
                          $cek_teks = strtoupper(preg_replace('/\s+/', '', $txt));

                          // 1. CEK KATA KUNCI (JALUR VVIP - Masuk Kotak Kanan)
                          // Termasuk kalau dia baca "NIK: 6404...", bakal nongkrong elit di sini!
                          $is_keyword = false;
                          foreach ($daftar_keyword_ktp as $kw) {
                              if (strpos($cek_teks, $kw) !== false) {
                                  $list_keyword_kotak[] = ['text' => $txt, 'score' => $scr];
                                  $is_keyword = true;
                                  break;
                              }
                          }

                        // 2. CEK KANDIDAT NIK/ANGKA MURNI (Masuk Kotak Kiri)
                          if (!$is_keyword) {
                              $jumlah_angka = preg_match_all('/\d/', $cek_teks);
                              $panjang_teks = strlen($cek_teks);

                              // JURUS BARU: Deteksi pola tanggal (DD-MM-YYYY, DD MM YYYY, DD/MM/YYYY) pada teks asli!
                              // Polanya: 2 angka + pemisah(spasi/strip/titik) + 2 angka + pemisah + 4 angka
                              $is_tanggal = preg_match('/\d{2}[\s\-\/\.]+\d{2}[\s\-\/\.]+\d{4}/', $txt);

                              // FILTER SUPER KETAT:
                              // 1. Panjang minimal 8 karakter (Buang RT/RW)
                              // 2. Angka minimal 5 digit
                              // 3. BUKAN format tanggal (is_tanggal harus false!)
                              // 4. Gak ada tanda strip sisaan
                              if ($panjang_teks >= 8 && $jumlah_angka >= 5 && !$is_tanggal && strpos($cek_teks, '-') === false) {
                                  $list_nik_lurus[] = ['text' => $txt, 'score' => $scr];
                              }
                          }
                      }
                  ?>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      
                      <!-- Kolom kiri: menampilkan data mentah OCR dan kandidat angka -->
                      <div class="flex flex-col gap-4 h-full">
                          
                          <!-- Bagian atas: daftar kandidat NIK dan angka dari hasil OCR -->
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

                      <!-- Bagian bawah: tampilan kata kunci dalam kartu -->
                          <div class="flex flex-col mt-4">
                              <h6 class="text-xs font-bold text-gray-700 uppercase mb-2 border-b border-gray-200 pb-2 flex items-center gap-1.5"><i data-feather="tag" class="w-3.5 h-3.5 text-blue-500"></i> Kata Kunci Terdeteksi</h6>
                              <div class="bg-white border border-gray-200 rounded p-3 max-h-[140px] overflow-y-auto">
                                  <?php if (empty($list_keyword_kotak)): ?>
                                      <p class="text-xs text-gray-400 italic text-center">Tidak ada kata kunci terdeteksi.</p>
                                  <?php else: ?>
                                      <!-- Gunakan dua kolom agar setiap baris maksimum dua kartu -->
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

               <!-- KOLOM KANAN: KALKULASI SISTEM ATAU KOREKSI MANUAL -->
                      <div class="flex flex-col h-full">
                          <h6 class="text-xs font-bold text-gray-700 uppercase mb-2 border-b border-gray-200 pb-2">Status Validasi Data</h6>
                          
                          <?php 
                          // Logika validasi: tentukan apakah data berasal dari koreksi manual atau hasil mesin OCR
                          // (Asumsi: skor 100 menunjukkan koreksi manual)
                          if ($skor == 100): 
                          ?>
                              <!-- TAMPILAN JIKA DATA SUDAH DIKOREKSI MANUAL -->
                              <div class="bg-green-50 border border-green-200 rounded p-4 flex-1 flex flex-col items-center justify-center text-center">
                                  <div class="bg-green-100 p-3 rounded-full mb-3">
                                      <i data-feather="user-check" class="w-8 h-8 text-green-600"></i>
                                  </div>
                                  <p class="text-xs font-bold text-green-800 uppercase mb-1">Diverifikasi Manual</p>
                                  <p class="text-3xl font-black text-green-600 mb-2">100%</p>
                                  
                                  <div class="w-full mt-2 bg-white border border-green-200 rounded p-2.5 shadow-sm">
                                      <p class="text-[10px] text-gray-600 font-medium leading-relaxed">
                                          <i data-feather="shield" class="w-3 h-3 inline text-green-500 mr-1"></i>
                                          Data ini telah dikoreksi dan divalidasi kebenarannya oleh <strong>Petugas</strong>. Proses kalkulasi mesin OCR awal telah diabaikan.
                                      </p>
                                  </div>
                              </div>

                          <?php else: ?>
                              <!-- TAMPILAN JIKA MURNI HASIL MESIN OCR (Belum Dikoreksi) -->
                              
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
                                      <i data-feather="cpu" class="w-3 h-3 inline mr-1"></i>
                                      Metode AI: <strong><?= $is_fallback ? 'Fallback (Rata-rata Gabungan)' : 'Normal (Bounding Box)' ?></strong>
                                  </p>
                              </div>

                              <!-- Info Parame  ter Validasi -->
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

                              <!-- Hasil Final OCR -->
                              <div class="bg-gray-50 border border-gray-200 rounded p-3 flex-1 flex flex-col justify-center">
                                  <p class="text-[10px] font-semibold text-gray-500 uppercase mb-1">Skor Akhir Mesin</p>
                                  <p class="text-2xl font-bold <?= $skor >= 70 ? 'text-green-600' : 'text-red-600' ?>"><?= number_format($skor, 1) ?>%</p>
                                  
                                  <div class="mt-2 bg-white border border-gray-200 rounded p-2">
                                      <?php if ($skor > ($raw_ocr_score * 100)): ?>
                                          <div class="text-[10px] text-gray-600 space-y-1 font-mono">
                                              <div class="flex justify-between border-b border-gray-100 pb-1">
                                                  <span>Skor Awal OCR:</span> 
                                                  <span><?= number_format($raw_ocr_score * 100, 1) ?>%</span>
                                              </div>
                                              <div class="flex justify-between text-green-600 pb-1">
                                                  <span>Boost (16 Digit):</span> 
                                                  <span>+15.0%</span>
                                              </div>
                                              <div class="flex justify-between font-bold text-gray-800 border-t border-gray-200 pt-1">
                                                  <span>Total Akhir:</span> 
                                                  <span><?= number_format($skor, 1) ?>%</span>
                                              </div>
                                          </div>
                                      <?php else: ?>
                                          <p class="text-[10px] text-green-700 font-medium">Validasi normal tanpa algoritma Boost.</p>
                                      <?php endif; ?>
                                      <!-- TAMBAHIN KODE INI DI BAWAH KOTAK PUTIH TADI -->
                                      <?php if ($skor >= 97 && $skor < 100): ?>
                                      <div class="mt-2 bg-blue-50 border border-blue-100 rounded p-2 flex items-start gap-1.5">
                                          <i data-feather="info" class="w-3 h-3 text-blue-600 shrink-0 mt-0.5"></i>
                                          <p class="text-[9px] leading-relaxed text-blue-800 font-medium">
                                              Skor kepercayaan mesin dibatasi maksimal 97%. Skor 100% hanya diberikan jika data telah diverifikasi manual oleh operator manusia.
                                          </p>
                                      </div>
                                      <?php endif; ?>
                                  </div>
                              </div>
                          <?php endif; ?>
                      </div>

                  </div>
              </div>
          </div>

      <div class="flex flex-col md:flex-row justify-between items-center gap-4 border-t border-gray-200 pt-6">
        <a href="upload.php" class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
          <i data-feather="upload" class="h-4 w-4"></i><span>Upload Baru</span>
        </a>
        <div class="flex items-center gap-4">

          <form method="POST" action="koreksi.php" class="inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="log_id" value="<?php echo $log_id; ?>">
            <button type="submit" class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
              <i data-feather="edit-3" class="h-4 w-4"></i>
              <span><?php echo ($status === 'finalized') ? 'Revisi Data' : 'Koreksi Data'; ?></span>
            </button>
          </form>

          <?php if ($status !== 'finalized'): ?>
            <?php if ($is_success): ?>
            <form method="POST" action="proses/proses_simpan.php" class="inline">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="log_id" value="<?php echo $log_id; ?>">
              <button type="submit" class="flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-green-700">
                <i data-feather="check" class="h-4 w-4"></i>
                <span>Simpan Final & Selesai</span>
              </button>
            </form>
            <?php endif; ?>
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