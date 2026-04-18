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
                $badgeClass = 'bg-green-100 text-green-700';
                $statusText = 'Berhasil';
            } elseif ($row['status_proses'] === 'pending_review' || $row['status_proses'] === 'pending') {
                $badgeClass = 'bg-yellow-100 text-yellow-700';
                $statusText = 'Proses';
            } elseif ($row['status_proses'] === 'failed') {
                $badgeClass = 'bg-red-100 text-red-700';
                $statusText = 'Gagal';
            }

            // Hitung Waktu
            $selisih = time() - strtotime($row['waktu_upload']);
            if ($selisih < 60) $waktu = "Baru saja";
            elseif ($selisih < 3600) $waktu = floor($selisih / 60) . " menit lalu";
            elseif ($selisih < 86400) $waktu = floor($selisih / 3600) . " jam lalu";
            else $waktu = date('d M', strtotime($row['waktu_upload']));

            $output .= '
            <div class="flex items-center justify-between border-b border-gray-50 pb-3 last:border-0 animate-fade-in">
                <div class="max-w-[150px]">
                    <p class="text-sm font-semibold text-gray-800 truncate" title="'.htmlspecialchars($row['nama_file_asli']).'">
                        '.htmlspecialchars($row['nama_file_asli']).'
                    </p>
                    <p class="text-[10px] text-gray-400">'.$waktu.'</p>
                </div>
                <span class="text-[10px] font-bold px-2 py-1 rounded uppercase '.$badgeClass.'">
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
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
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
                  <div class="flex h-20 w-20 mx-auto items-center justify-center rounded-full bg-green-50 text-green-600 mb-4 group-hover:bg-green-100 transition-colors">
                    <i data-feather="upload-cloud" class="h-10 w-10"></i>
                  </div>
                  <div>
                    <p class="text-xl font-bold text-gray-900">Klik atau seret KTP ke sini</p>
                    <p class="text-sm text-gray-500 mt-1">Mendukung JPG, PNG (Maks. 5MB)</p>
                  </div>
                  <span class="inline-block rounded-lg bg-green-600 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-green-200 mt-4">Pilih File</span>
              </div>

              <div id="preview-container" class="hidden w-full h-full flex flex-col items-center justify-center">
                  <div class="relative group">
                      <img id="img-preview" src="#" class="max-h-[300px] rounded-lg shadow-lg border border-gray-200 object-contain">
                      <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-lg text-white text-sm font-bold">
                          Klik untuk ganti
                      </div>
                  </div>
                  <div class="mt-4 flex gap-2">
                      <span class="text-xs text-green-700 font-bold bg-green-100 px-3 py-1.5 rounded-full flex items-center gap-1">
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
                <button id="proses-semua" class="flex-1 rounded-lg bg-green-600 px-6 py-3 text-base font-semibold text-white shadow-md transition hover:bg-green-700 disabled:bg-gray-400">
                    Proses File
                </button>
            </div>
          </div>
        </div>

        <div class="lg:col-span-1">
          <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-200 sticky top-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i data-feather="clock" class="w-5 h-5 text-gray-400"></i> Riwayat
                </h3>
                <span class="text-[10px] text-gray-400 bg-gray-100 px-2 py-1 rounded animate-pulse">Live Update</span>
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

  <div id="crop-modal" class="hidden fixed inset-0 z-[999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-fade-in">
    <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-lg transform transition-all scale-100">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2 text-gray-800">
          <i data-feather="crop" class="text-green-600"></i> Sesuaikan Gambar
      </h2>

      <div class="relative w-full h-80 overflow-hidden rounded-xl border-2 border-dashed border-gray-200 bg-gray-50">
        <img id="crop-image" class="max-w-full" />
      </div>

      <div class="mt-6 flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-100">
        <button id="rotate-left" class="p-2 bg-white border rounded-md hover:bg-gray-100 shadow-sm text-gray-600"><i data-feather="rotate-ccw" class="w-4 h-4"></i></button>
        <div class="text-center flex-1 px-4">
            <input type="range" id="rotate-slider" min="-180" max="180" value="0" class="w-full h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-green-600">
        </div>
        <button id="rotate-right" class="p-2 bg-white border rounded-md hover:bg-gray-100 shadow-sm text-gray-600"><i data-feather="rotate-cw" class="w-4 h-4"></i></button>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button id="cancel-crop" class="px-5 py-2.5 text-gray-500 font-bold hover:text-gray-800 transition text-sm">Batal</button>
        <button id="save-crop" class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 font-bold shadow-lg transition text-sm flex items-center gap-2">
            <i data-feather="check"></i> Simpan 
        </button>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

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
        
        // --- [PERBAIKAN FINAL]: Sistem boleh zoom untuk Auto-Fit, User JANGAN ---
        cropper = new Cropper(cropImage, {
            viewMode: 1,
            background: false,
            responsive: true,
            autoCropArea: 0.8,
            
            zoomable: true,         // WAJIB TRUE biar sistem bisa ngecilin KTP pas diputar ke horizontal
            zoomOnWheel: false,     // TETAP MATI: Cegah zoom pakai scroll mouse
            zoomOnTouch: false      // TETAP MATI: Cegah zoom pakai cubitan jari di HP
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
            text: 'Silakan klik tombol Proses OCR.',
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
      btn.innerHTML = `<i data-feather="loader" class="animate-spin w-4 h-4"></i> Mengupload...`;
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