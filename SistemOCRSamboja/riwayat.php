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

// Validasi koneksi database
if (!$db) {
    header('Location: index.php?msg=db_error');
    exit;
}

$user_id = $_SESSION['user_id'];

// Cek status akun user
$sql_cek = "SELECT status FROM staf_kecamatan WHERE id = ?";
$stmt = mysqli_prepare($db, $sql_cek);

if (!$stmt) {
    header('Location: index.php?msg=db_error');
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result_user = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt);

if (!$user || strtolower(trim($user['status'])) !== 'aktif') {
    session_destroy();
    header('Location: index.php?msg=akses_ditolak');
    exit;
}

// Filter dan pencarian
$filter_status = $_GET['status'] ?? '';
$search        = $_GET['search'] ?? '';
$highlight_id  = $_GET['highlight_id'] ?? null;

// Query riwayat OCR
$sql = "SELECT 
            lo.log_id,
            lo.waktu_upload AS waktu_proses,
            lo.nama_file_asli AS nama_file,
            lo.nama_file_sistem,
            COALESCE(lo.nik_final, lo.nik_terdeteksi) AS nik_display,
            lo.status_proses AS status,
            lo.skor_kepercayaan AS akurasi,
            sk.nama_lengkap AS operator_nama
        FROM log_ocr lo
        LEFT JOIN staf_kecamatan sk ON lo.id_staf = sk.id
        WHERE lo.id_staf = '$user_id'"; // <-- TAMBAHKAN INI

// Filter status jika dipilih
if (!empty($filter_status)) {
    $filter_status_safe = mysqli_real_escape_string($db, $filter_status);
    $sql .= " AND lo.status_proses = '$filter_status_safe'";
}

// Filter pencarian
if (!empty($search)) {
    $search_safe = mysqli_real_escape_string($db, $search);
    $sql .= " AND (
                lo.nama_file_asli LIKE '%$search_safe%' 
                OR lo.nik_final LIKE '%$search_safe%' 
                OR lo.nik_terdeteksi LIKE '%$search_safe%'
            )";
}

$sql .= " ORDER BY lo.waktu_upload DESC";

// Eksekusi query
$riwayat = [];
$result = mysqli_query($db, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $riwayat[] = $row;
    }
}

$total_rows = count($riwayat);
mysqli_close($db);

// Pagination
$perPage    = 6;
$page       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalPages = max(1, ceil($total_rows / $perPage));
$start      = ($page - 1) * $perPage;

$riwayat_page = array_slice($riwayat, $start, $perPage);

// Helper badge status
function getStatusBadge($status) {
    switch ($status) {
        case 'finalized':
        case 'Berhasil':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                        <i data-feather="check-circle" class="w-3 h-3 mr-1"></i> Berhasil
                    </span>';
        case 'pending_review':
        case 'Perlu Koreksi':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                        <i data-feather="alert-circle" class="w-3 h-3 mr-1"></i> Perlu Cek
                    </span>';
        case 'failed':
        case 'Gagal':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                        <i data-feather="x-circle" class="w-3 h-3 mr-1"></i> Gagal
                    </span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200">
                        Unknown
                    </span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Riwayat OCR KTP</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    body { font-family: 'Inter', sans-serif; }
    .custom-scrollbar::-webkit-scrollbar { height: 8px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

    /* --- ANIMASI HIGHLIGHT BARIS (BARU) --- */
    /* Ini bikin warna hijau muda, terus pelan-pelan jadi transparan dalam 3 detik */
    @keyframes fadeHighlight {
        0% { background-color: #dcfce7; } /* green-100 */
        100% { background-color: transparent; }
    }
    .highlight-row {
        animation: fadeHighlight 4s ease-out forwards;
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen flex text-gray-800 font-sans antialiased">
  
  <?php include 'includes/navbar.php'; ?>

  <main class="flex-1 ml-64 p-8 transition-all duration-300">
    
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Riwayat OCR</h1>
        <p class="text-sm text-gray-500 mt-1">Pantau dan kelola hasil ekstraksi data KTP.</p>
      </div>
      
      <div class="flex gap-3">
        <div class="inline-flex rounded-md shadow-sm" role="group">
          <a href="proses/proses_pdf.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-50 focus:z-10 focus:ring-2 focus:ring-green-500 focus:text-green-700">
            <i data-feather="file-text" class="w-4 h-4 mr-2 text-red-500"></i> PDF
          </a>
          <a href="proses/proses_excel.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-l-0 border-gray-300 rounded-r-lg hover:bg-gray-50 focus:z-10 focus:ring-2 focus:ring-green-500 focus:text-green-700">
            <i data-feather="grid" class="w-4 h-4 mr-2 text-green-600"></i> Excel
          </a>
        </div>
        <a href="upload.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-lg shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
          <i data-feather="plus" class="w-4 h-4 mr-2"></i> Upload Baru
        </a>
      </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-6">
      <div class="p-5 border-b border-gray-100 bg-gray-50/50 rounded-t-xl">
        <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
          <i data-feather="filter" class="w-4 h-4 text-gray-500"></i> Filter & Pencarian
        </h3>
      </div>
      
      <div class="p-5">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
          <div class="md:col-span-5">
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Pencarian</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i data-feather="search" class="h-4 w-4 text-gray-400"></i>
              </div>
              <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-green-500 sm:text-sm" 
                placeholder="Cari NIK atau Nama File...">
            </div>
          </div>

          <div class="md:col-span-3">
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Status</label>
            <div class="relative">
                <select name="status" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 sm:text-sm rounded-lg appearance-none bg-white">
                    <option value="">Semua Status</option>
                    <option value="finalized" <?= $filter_status === 'finalized' ? 'selected' : '' ?>>Berhasil</option>
                    <option value="pending_review" <?= $filter_status === 'pending_review' ? 'selected' : '' ?>>Perlu Koreksi</option>
                    <option value="failed" <?= $filter_status === 'failed' ? 'selected' : '' ?>>Gagal</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                    <i data-feather="chevron-down" class="h-4 w-4"></i>
                </div>
            </div>
          </div>

          <div class="md:col-span-4 flex gap-2">
            <button type="submit" class="flex-1 bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition">Terapkan</button>
            <a href="riwayat.php" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition flex items-center justify-center">
              <i data-feather="rotate-ccw" class="h-4 w-4"></i>
            </a>
          </div>
        </form>
      </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
      <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full whitespace-nowrap">
          <thead>
            <tr class="bg-gray-50/50 border-b border-gray-200 text-left">
              <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">File & Waktu</th>
              <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Preview</th>
              <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Data NIK</th>
              <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status & Akurasi</th>
              <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Operator</th>
              <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            
            <?php if (empty($riwayat_page)): ?>
              <tr>
                <td colspan="6" class="px-6 py-12 text-center">
                   <div class="flex flex-col items-center justify-center">
                     <div class="bg-gray-100 p-3 rounded-full mb-3"><i data-feather="inbox" class="h-8 w-8 text-gray-400"></i></div>
                     <p class="text-gray-500 font-medium">Data tidak ditemukan</p>
                   </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($riwayat_page as $data): ?>
                <?php
                  $server_path = __DIR__ . '/public/images/' . ($data['nama_file_sistem'] ?? '');
                  $gambar_exists = $data['nama_file_sistem'] && file_exists($server_path);
                  $path_gambar = 'public/images/' . ($data['nama_file_sistem'] ?? '');

                  // --- LOGIC HIGHLIGHT ---
                  // Cek apakah ID baris ini sama dengan ID yang baru diedit
                  $is_highlight = ($highlight_id == $data['log_id']);
                  // Kalau sama, kasih class 'highlight-row', kalau tidak kosongin aja
                  $extraClass = $is_highlight ? 'highlight-row' : 'hover:bg-gray-50';
                ?>
                <tr class="<?= $extraClass ?> transition duration-150 ease-in-out group">
                  <td class="px-6 py-4">
                    <div class="flex flex-col">
                      <span class="text-sm font-medium text-gray-900 truncate max-w-[180px]" title="<?= htmlspecialchars($data['nama_file']) ?>">
                        <?= htmlspecialchars($data['nama_file']) ?>
                      </span>
                      <span class="text-xs text-gray-500 flex items-center gap-1 mt-1">
                        <i data-feather="clock" class="w-3 h-3"></i> <?= date('d M Y, H:i', strtotime($data['waktu_proses'])) ?>
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                      <div class="h-12 w-20 rounded bg-gray-100 border border-gray-200 overflow-hidden flex items-center justify-center">
                        <?php if ($gambar_exists): ?>
                          <img src="<?= htmlspecialchars($path_gambar) ?>" class="h-full w-full object-cover">
                        <?php else: ?>
                          <i data-feather="image" class="h-5 w-5 text-gray-400"></i>
                        <?php endif; ?>
                      </div>
                  </td>
                  <td class="px-6 py-4">
                      <div class="flex items-center gap-2">
                          <?php 
                              $nik = htmlspecialchars($data['nik_display'] ?? '');
                              if(empty($nik)) {
                                  echo '<span class="text-xs text-red-500 bg-red-50 px-2 py-1 rounded">Tidak terdeteksi</span>';
                              } else {
                                  echo '<span class="font-mono text-sm text-gray-700 bg-gray-100 px-2 py-1 rounded border border-gray-200">' . $nik . '</span>';
                                  echo '
                                  <button type="button" 
                                          class="btn-copy text-gray-400 hover:text-green-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors" 
                                          data-nik="'.$nik.'" 
                                          title="Salin NIK">
                                      <i data-feather="copy" class="h-4 w-4"></i>
                                  </button>';
                              }
                          ?>
                      </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex flex-col items-start gap-2">
                       <?= getStatusBadge($data['status']) ?>
                       <div class="flex items-center text-xs">
                          <span class="text-gray-500 mr-2">Akurasi:</span>
                          <?php 
                            $akurasi = $data['akurasi'] * 100;
                            $colorClass = $akurasi > 90 ? 'text-green-600' : ($akurasi > 70 ? 'text-yellow-600' : 'text-red-600');
                          ?>
                          <span class="font-bold <?= $colorClass ?>"><?= number_format($akurasi, 1) ?>%</span>
                       </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <div class="h-6 w-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold">
                            <?= substr($data['operator_nama'] ?? '?', 0, 1) ?>
                        </div>
                        <span class="text-sm text-gray-600"><?= htmlspecialchars($data['operator_nama'] ?? 'System') ?></span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                        <a href="koreksi.php?id=<?= $data['log_id'] ?>" class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-md transition"><i data-feather="edit-3" class="w-4 h-4"></i></a>
                        <a href="hasilriwayat.php?id=<?= $data['log_id'] ?>" class="p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-md transition"><i data-feather="eye" class="w-4 h-4"></i></a>
                        
                        <form action="proses/proses_hapus_data.php" method="POST" class="inline form-hapus" onsubmit="return konfirmasiHapus(event, this);">
                            <input type="hidden" name="action" value="hapus_data_ktp">
                            <input type="hidden" name="data_id" value="<?= $data['log_id'] ?>">
                            <button type="submit" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-md transition" title="Hapus">
                                <i data-feather="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="bg-gray-50 border-t border-gray-200 px-6 py-4 flex items-center justify-between">
         <span class="text-sm text-gray-500">Hal. <?= $page ?> dari <?= $totalPages ?></span>
         <div class="inline-flex shadow-sm rounded-md">
            <a href="?page=<?= max(1, $page - 1) ?>&search=<?= $search ?>&status=<?= $filter_status ?>" class="relative inline-flex items-center px-4 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">Prev</a>
            <a href="?page=<?= min($totalPages, $page + 1) ?>&search=<?= $search ?>&status=<?= $filter_status ?>" class="relative inline-flex items-center px-4 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 <?= $page >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>">Next</a>
         </div>
      </div>
    </div>
  </main>

  <script>
    feather.replace();

    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
      }
    });

    const copyButtons = document.querySelectorAll('.btn-copy');
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const nik = this.getAttribute('data-nik');
            navigator.clipboard.writeText(nik).then(() => {
                Toast.fire({ icon: 'success', title: 'NIK disalin ke clipboard' });
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i data-feather="check" class="h-4 w-4"></i>';
                this.classList.add('text-green-600', 'bg-green-50');
                feather.replace();
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                    this.classList.remove('text-green-600', 'bg-green-50');
                    feather.replace();
                }, 2000);
            });
        });
    });

    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    const highlightId = urlParams.get('highlight_id'); // Cek ada highlight_id gak

    if (msg) {
        if (msg === 'hapus_sukses') { 
            Toast.fire({ icon: 'success', title: 'Data berhasil dihapus!' });
        } else if (msg === 'berhasil_edit') {
            Toast.fire({ icon: 'success', title: 'Data berhasil diperbarui!' });
        } else if (msg === 'gagal') {
            Toast.fire({ icon: 'error', title: 'Terjadi kesalahan sistem.' });
        } else if (msg === 'akses_ditolak') {
            Toast.fire({ icon: 'warning', title: 'Akses ditolak.' });
        }
        
        // Hapus parameter biar bersih, TAPI HATI-HATI:
        // Kalau kita hapus URL-nya langsung, nanti kalau user refresh manual, highlight-nya ilang.
        // Tapi sesuai request "bistu hilang", jadi gapapa kita bersihin URL-nya.
        // Highlight-nya sendiri udah diurus sama CSS Animation (fadeHighlight).
        window.history.replaceState(null, null, window.location.pathname);
    }

    function konfirmasiHapus(event, form) {
        event.preventDefault();
        Swal.fire({
            title: 'Hapus Data?',
            text: "Data dan file gambar akan dihapus permanen.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#4b5563',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }
  </script>
</body>
</html>