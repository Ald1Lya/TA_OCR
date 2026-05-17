<?php
session_start();

// --- 1. AJAX HANDLER UNTUK AUTO REFRESH RIWAYAT ---
// Bagian ini akan dipanggil oleh Javascript setiap 5 detik
if (isset($_GET['ajax_history'])) {
    require_once 'proses/config.php'; // Pastikan path config benar
    $user_id = $_SESSION['user_id'];
    
    // Ambil 5 data terbaru
    $sql = "SELECT lo.log_id, lo.waktu_upload, lo.nama_file_asli, lo.status_proses 
            FROM log_ocr lo WHERE lo.id_staf = ? ORDER BY lo.waktu_upload DESC LIMIT 5";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $output = '';
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Logika Badge Status
            $badgeClass = 'bg-gray-100 text-gray-600';
            $statusText = 'Pending';
            
            if ($row['status_proses'] === 'finalized' || $row['status_proses'] === 'Berhasil') {
                $badgeClass = 'bg-green-100 text-green-700 border border-green-200';
                $statusText = 'Berhasil';
            } elseif ($row['status_proses'] === 'pending_review' || $row['status_proses'] === 'pending') {
                $badgeClass = 'bg-yellow-100 text-yellow-700 border border-yellow-200';
                $statusText = 'Proses';
            } elseif ($row['status_proses'] === 'failed') {
                $badgeClass = 'bg-red-100 text-red-700 border border-red-200';
                $statusText = 'Gagal';
            }

            // Hitung Waktu
            $selisih = time() - strtotime($row['waktu_upload']);
            if ($selisih < 60) $waktu = "Baru saja";
            elseif ($selisih < 3600) $waktu = floor($selisih / 60) . " menit lalu";
            elseif ($selisih < 86400) $waktu = floor($selisih / 3600) . " jam lalu";
            else $waktu = date('d M', strtotime($row['waktu_upload']));

            $output .= '
            <div class="flex items-center justify-between border-b border-gray-100 pb-3 last:border-0 animate-fade-in">
                <div class="max-w-[150px]">
                    <p class="text-sm font-semibold text-gray-800 truncate" title="'.htmlspecialchars($row['nama_file_asli']).'">
                        '.htmlspecialchars($row['nama_file_asli']).'
                    </p>
                    <p class="text-[10px] text-gray-400">'.$waktu.'</p>
                </div>
                <span class="text-[10px] font-bold px-2 py-1 rounded-md uppercase shadow-sm '.$badgeClass.'">
                    '.$statusText.'
                </span>
            </div>';
        }
    } else {
        $output = '<p class="text-gray-400 italic text-sm text-center py-4">Belum ada aktivitas.</p>';
    }
    echo $output;
    exit; // Stop eksekusi biar gak ngerender halaman utuh
}

// --- LOGIKA HALAMAN UTAMA ---
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
if (strtolower($_SESSION['role']) === 'admin') { header('Location: admin/dashboard_admin.php'); exit; }

date_default_timezone_set('Asia/Jakarta');
require_once 'proses/config.php';

// Cek status akun
$user_id = $_SESSION['user_id'];
$sql_cek = "SELECT status FROM staf_kecamatan WHERE id = ?";
$stmt = mysqli_prepare($db, $sql_cek);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$user || strtolower(trim($user['status'])) !== 'aktif') {
    session_destroy();
    header('Location: index.php?msg=akses_ditolak');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Upload KTP - OCR System</title>
  
    <link rel="stylesheet" href="../assets/css/style.css" />
    
    <link rel="stylesheet" href="../assets/css/cropper.min.css" />

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

        /* Animasi */
        .animate-fade-in { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex text-gray-800">
  
  <?php include 'includes/navbar.php'; ?>

  <div class="flex-1 ml-64 transition-all duration-300">
    <main class="p-6 md:p-8 space-y-6">
      
      <div class="flex justify-between items-center">
        <div>
            <h1 class="text-4xl font-bold text-gray-900 tracking-tight">Upload KTP</h1>
            <p class="text-gray-500 mt-1">Sistem OCR Otomatis Kecamatan Samboja Kuala</p>
        </div>
      </div>
      
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 space-y-6">
          <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-200">
            
            <div id="dropzone" class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 min-h-[350px] p-8 text-center transition-all hover:border-green-500 hover:bg-green-50/50 cursor-pointer group">
              
              <div id="dropzone-content" class="space-y-4 transition-all group-hover:scale-105">
                  <div class="flex h-20 w-20 mx-auto items-center justify-center rounded-full bg-green-50 text-green-600 mb-4 border border-green-100 shadow-sm group-hover:bg-green-100 transition-colors">
                    <i data-feather="upload-cloud" class="h-10 w-10"></i>
                  </div>
                  <div>
                    <p class="text-xl font-bold text-gray-900">Klik atau seret KTP ke sini</p>
                    <p class="text-sm text-gray-500 mt-1">Mendukung JPG, PNG (Maks. 5MB)</p>
                  </div>
                  <span class="inline-block rounded-lg bg-green-600 px-6 py-2.5 text-sm font-bold text-white shadow-md hover:bg-green-700 mt-4 transition-colors">Pilih File</span>
              </div>

              <div id="preview-container" class="hidden w-full h-full flex flex-col items-center justify-center">
                  <div class="relative group">
                      <img id="img-preview" src="#" class="max-h-[300px] rounded-lg shadow-md border border-gray-200 object-contain">
                      <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-lg text-white text-sm font-bold backdrop-blur-sm">
                          Klik untuk ganti
                      </div>
                  </div>
                  <div class="mt-4 flex gap-2">
                      <span class="text-xs text-green-700 font-bold bg-green-100 border border-green-200 shadow-sm px-3 py-1.5 rounded-full flex items-center gap-1">
                          <i data-feather="check" class="w-3 h-3"></i> Siap Proses
                      </span>
                  </div>
              </div>

              <input id="file-upload" type="file" accept="image/png, image/jpeg, image/jpg" class="hidden">
            </div>

            <div id="file-queue-list" class="mt-4"></div>

            <div class="flex items-center gap-3 mt-6">       
                <button id="hapus-semua" class="w-1/3 rounded-xl border border-gray-300 bg-white px-6 py-3.5 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 hover:text-red-600 flex items-center justify-center gap-2">
                    <i data-feather="trash-2" class="w-4 h-4"></i> Reset
                </button>
                <button id="proses-semua" class="flex-1 rounded-xl bg-green-600 px-6 py-3.5 text-base font-bold text-white shadow-md transition hover:bg-green-700 disabled:bg-gray-400">
                    Proses File
                </button>
            </div>
          </div>
        </div>

        <div class="lg:col-span-1">
          <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-200 sticky top-6">
            <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i data-feather="clock" class="w-5 h-5 text-gray-400"></i> Riwayat
                </h3>
                <span class="text-[10px] text-green-700 font-bold bg-green-50 border border-green-100 px-2 py-1 rounded shadow-sm animate-pulse">Live Update</span>
            </div>

            <div id="riwayat-container" class="space-y-4 min-h-[100px]">
                <div class="flex justify-center items-center py-8 text-gray-400">
                    <i data-feather="loader" class="animate-spin w-5 h-5"></i>
                </div>
            </div>
            
            <div class="mt-4 pt-4 border-t border-gray-100 text-center">
                <a href="riwayat.php" class="text-xs font-bold text-green-600 hover:text-green-700 hover:underline">Lihat Semua Riwayat →</a>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- CROP MODAL -->
  <div id="crop-modal" class="hidden fixed inset-0 z-[999] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 animate-fade-in">
    <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-lg transform transition-all scale-100 border border-gray-200">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2 text-gray-800 border-b border-gray-100 pb-3">
          <i data-feather="crop" class="text-green-600"></i> Sesuaikan Gambar
      </h2>

      <div class="relative w-full h-80 overflow-hidden rounded-xl border border-gray-200 bg-gray-100 shadow-inner">
        <img id="crop-image" class="max-w-full" />
      </div>

      <!-- KONTROL ROTASI & ZOOM -->
      <div class="mt-6 flex flex-col gap-3">
          <!-- Row 1: Rotate -->
          <div class="flex justify-between items-center bg-gray-50 p-2.5 rounded-lg border border-gray-200 shadow-sm">
            <button id="rotate-left" class="p-2 bg-white border border-gray-300 rounded-md hover:bg-gray-100 shadow-sm text-gray-600 transition-colors" title="Putar Kiri"><i data-feather="rotate-ccw" class="w-4 h-4"></i></button>
            <div class="text-center flex-1 px-4 flex items-center gap-2">
                <input type="range" id="rotate-slider" min="-180" max="180" value="0" class="w-full h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-green-600" title="Geser untuk memutar">
            </div>
            <button id="rotate-right" class="p-2 bg-white border border-gray-300 rounded-md hover:bg-gray-100 shadow-sm text-gray-600 transition-colors" title="Putar Kanan"><i data-feather="rotate-cw" class="w-4 h-4"></i></button>
          </div>

          <!-- Row 2: Zoom -->
          <div class="flex justify-center items-center gap-4 bg-gray-50 p-2.5 rounded-lg border border-gray-200 shadow-sm">
              <button id="zoom-out" class="py-2 px-4 bg-white border border-gray-300 rounded-md hover:bg-gray-100 shadow-sm text-gray-600 transition-colors flex items-center gap-1.5 text-xs font-bold w-1/2 justify-center" title="Perkecil Gambar">
                  <i data-feather="zoom-out" class="w-4 h-4"></i> Perkecil
              </button>
              <button id="zoom-in" class="py-2 px-4 bg-white border border-gray-300 rounded-md hover:bg-gray-100 shadow-sm text-gray-600 transition-colors flex items-center gap-1.5 text-xs font-bold w-1/2 justify-center" title="Perbesar Gambar">
                  <i data-feather="zoom-in" class="w-4 h-4"></i> Perbesar
              </button>
          </div>
          <p class="text-[10px] text-gray-400 text-center">*Tips: Anda juga bisa zoom menggunakan scroll mouse pada gambar.</p>
      </div>

      <div class="flex justify-end gap-3 mt-6 border-t border-gray-100 pt-4">
        <button id="cancel-crop" class="px-5 py-2.5 text-gray-600 font-bold bg-white border border-gray-300 rounded-lg hover:bg-gray-50 shadow-sm transition text-sm">Batal</button>
        <button id="save-crop" class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 font-bold shadow-md transition text-sm flex items-center gap-2">
            <i data-feather="check" class="w-4 h-4"></i> Simpan 
        </button>
      </div>
    </div>
  </div>

  <script src="../assets/js/feather.min.js"></script>
  <script src="../assets/js/sweetalert2.all.min.js"></script>
  <script src="../assets/js/cropper.min.js"></script>

  <script>
    feather.replace();

    // Variable Element
    const dropzone = document.getElementById("dropzone");
    const dropzoneContent = document.getElementById("dropzone-content");
    const previewContainer = document.getElementById("preview-container");
    const imgPreview = document.getElementById("img-preview");
    const fileInput = document.getElementById("file-upload");
    const cropModal = document.getElementById("crop-modal");
    const cropImage = document.getElementById("crop-image");
    
    let fileQueue = new DataTransfer();
    let cropper = null;
    let currentFile = null;

    // --- 1. HANDLE UPLOAD & CROP ---
    dropzone.addEventListener("click", () => fileInput.click());
    fileInput.addEventListener("change", (e) => handleFiles(e.target.files));

    const handleFiles = (files) => {
      if (!files.length) return;
      const file = files[0];
      const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
      
      if (!validTypes.includes(file.type)) {
        Swal.fire({
            icon: 'error',
            title: 'Format Salah',
            text: 'Harap upload file JPG atau PNG.',
            confirmButtonColor: '#1f2937'
        });
        fileInput.value = ''; 
        return;
      }

      currentFile = file;
      const reader = new FileReader();
      reader.onload = (e) => {
        cropImage.src = e.target.result;
        cropModal.classList.remove("hidden");
        document.getElementById("rotate-slider").value = 0;

        if (cropper) cropper.destroy();
        
        // --- [PERBAIKAN FITUR]: Menyalakan akses Zoom penuh ---
        cropper = new Cropper(cropImage, {
            viewMode: 1,
            background: false,
            responsive: true,
            autoCropArea: 0.8,
            zoomable: true,       // Boleh di-zoom
            zoomOnWheel: true,    // AKTIF: Bisa zoom pake scroll mouse!
            zoomOnTouch: true     // AKTIF: Bisa dicubit-cubit di layar HP!
        });
      };
      reader.readAsDataURL(file);
    };

    document.getElementById("save-crop").onclick = () => {
      const canvas = cropper.getCroppedCanvas({ maxWidth: 2000, maxHeight: 2000 });
      canvas.toBlob((blob) => {
        const croppedFile = new File([blob], "ready_" + currentFile.name, { type: "image/jpeg" });
        
        fileQueue = new DataTransfer(); 
        fileQueue.items.add(croppedFile);
        
        imgPreview.src = URL.createObjectURL(blob);
        dropzoneContent.classList.add("hidden");
        previewContainer.classList.remove("hidden");
        dropzone.classList.add("border-green-500", "bg-green-50/20");

        cropModal.classList.add("hidden");
        cropper.destroy();
        
        Swal.fire({
            icon: 'success',
            title: 'Gambar Siap',
            text: 'Silakan klik tombol Proses File.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
        });

      }, "image/jpeg", 0.9);
    };

    document.getElementById("cancel-crop").onclick = () => {
        cropModal.classList.add("hidden");
        if(cropper) cropper.destroy();
        fileInput.value = "";
    };

    // Rotation Control
    document.getElementById("rotate-slider").oninput = function() { cropper.rotateTo(this.value); };
    document.getElementById("rotate-left").onclick = () => cropper.rotate(-90);
    document.getElementById("rotate-right").onclick = () => cropper.rotate(90);

    // Zoom Control (Tombol Manual Baru)
    document.getElementById("zoom-in").onclick = () => cropper.zoom(0.1);
    document.getElementById("zoom-out").onclick = () => cropper.zoom(-0.1);

    // Reset Button
    document.getElementById("hapus-semua").onclick = () => {
      fileQueue = new DataTransfer();
      previewContainer.classList.add("hidden");
      dropzoneContent.classList.remove("hidden");
      dropzone.classList.remove("border-green-500", "bg-green-50/20");
      fileInput.value = "";
    };

    // --- 2. HANDLE PROSES UPLOAD (SWEETALERT) ---
    document.getElementById("proses-semua").onclick = async () => {
      if (fileQueue.files.length === 0) {
          return Swal.fire({
              icon: 'warning',
              title: 'Belum ada gambar',
              text: 'Silakan pilih gambar KTP terlebih dahulu.',
              confirmButtonColor: '#1f2937'
          });
      }
      
      const btn = document.getElementById("proses-semua");
      const originalText = btn.innerHTML;
      btn.innerHTML = `<div class="flex justify-center items-center gap-2"><i data-feather="loader" class="animate-spin w-5 h-5"></i> Mengupload...</div>`;
      feather.replace();
      btn.disabled = true;

      const formData = new FormData();
      formData.append("ktp_files[]", fileQueue.files[0]);

      try {
        const res = await fetch("proses/proses_upload.php", { method: "POST", body: formData });
        const data = await res.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Upload Berhasil!',
                text: 'Sedang mengalihkan ke proses pembacaan...',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                window.location.href = "prosesocr.php";
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Memproses',
                html: data.message || 'Terjadi kesalahan sistem.', 
                confirmButtonColor: '#dc2626'
            });
            btn.innerHTML = originalText;
            feather.replace();
            btn.disabled = false;
        }
      } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Koneksi Error',
            text: 'Gagal terhubung ke server PHP.',
            confirmButtonColor: '#dc2626'
        });
        btn.innerHTML = originalText;
        feather.replace();
        btn.disabled = false;
      }
    };

    // --- 3. AUTO REFRESH RIWAYAT (AJAX) ---
    function updateRiwayat() {
        fetch('?ajax_history=1')
            .then(response => response.text())
            .then(html => {
                const container = document.getElementById('riwayat-container');
                if (container) { 
                    container.innerHTML = html;
                }
            })
            .catch(err => console.error('Gagal update riwayat:', err));
    }

    updateRiwayat();
    setInterval(updateRiwayat, 5000);

  </script>

</body>
</html>