<?php
session_start();

// 1. Validasi login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 2. Redirect admin (Admin tidak boleh di sini)
if (strtolower($_SESSION['role']) === 'admin') {
    header('Location: admin/dashboard_admin.php');
    exit;
}

date_default_timezone_set('Asia/Jakarta');
require_once 'proses/config.php';

if (!$db) {
    session_destroy();
    header('Location: index.php?msg=db_error');
    exit;
}

$user_id = $_SESSION['user_id'];

// 3. Cek status akun (Harus Aktif)
$sql_cek = "SELECT status FROM staf_kecamatan WHERE id = ?";
$stmt = mysqli_prepare($db, $sql_cek);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result_cek = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result_cek);
mysqli_stmt_close($stmt);

if (!$user || strtolower(trim($user['status'])) !== 'aktif') {
    session_destroy();
    header('Location: index.php?msg=akses_ditolak');
    exit;
}

// 4. Ambil riwayat OCR terbaru (DIBATASI: Hanya milik sendiri)
$riwayat = [];
$sql = "SELECT 
            lo.log_id, lo.waktu_upload AS waktu_proses, lo.nama_file_asli AS nama_file,
            lo.status_proses AS status
        FROM log_ocr lo
        WHERE lo.id_staf = ? 
        ORDER BY lo.waktu_upload DESC
        LIMIT 4";

$stmt_log = mysqli_prepare($db, $sql);
mysqli_stmt_bind_param($stmt_log, "i", $user_id);
mysqli_stmt_execute($stmt_log);
$result = mysqli_stmt_get_result($stmt_log);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $riwayat[] = $row;
    }
}
mysqli_stmt_close($stmt_log);

// Helper waktu
function waktuUpload($waktu) {
    $waktu_db = strtotime($waktu);
    $selisih = time() - $waktu_db;
    if ($selisih < 0) $selisih = 0;
    if ($selisih < 60) return "Baru saja";
    if ($selisih < 3600) return floor($selisih / 60) . " menit lalu";
    if ($selisih < 86400) return floor($selisih / 3600) . " jam lalu";
    return date('d M Y', $waktu_db);
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
</head>

<body class="bg-gray-50 min-h-screen flex text-gray-800">
  
  <?php include 'includes/navbar.php'; ?>

  <div class="flex-1 ml-64">
    <main class="p-6 md:p-8 space-y-6">
      <h1 class="text-4xl font-bold text-gray-900">Upload KTP</h1>
      
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 space-y-6">
          <div class="rounded-lg bg-white p-6 shadow-sm border border-gray-200">
            
            <div id="dropzone" class="relative flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 min-h-[350px] p-4 text-center transition-all hover:border-green-500 hover:bg-green-50 overflow-hidden cursor-pointer">
              
              <div id="dropzone-content" class="space-y-4">
                  <div class="flex h-16 w-16 mx-auto items-center justify-center rounded-full bg-green-100 text-green-600">
                    <i data-feather="upload-cloud" class="h-8 w-8"></i>
                  </div>
                  <div>
                    <p class="text-lg font-semibold text-gray-900">Klik atau seret file KTP ke sini</p>
                    <p class="text-sm text-gray-500">Format: JPG, PNG (Maks. 5MB)</p>
                  </div>
                  <span class="inline-block rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm">Pilih File</span>
              </div>

              <div id="preview-container" class="hidden w-full h-full flex flex-col items-center">
                  <img id="img-preview" src="#" class="max-h-[300px] rounded-md shadow-md border object-contain mb-2">
                  <p class="text-xs text-green-600 font-bold bg-green-50 px-3 py-1 rounded-full">Siap diproses!</p>
              </div>

              <input id="file-upload" type="file" accept="image/png, image/jpeg, image/jpg" class="hidden">
            </div>

            <div id="file-queue-list" class="mt-4 space-y-3"></div>

            <div class="flex items-center space-x-4 mt-6">       
                <button id="proses-semua" class="flex-1 rounded-lg bg-green-600 px-6 py-3 text-base font-semibold text-white shadow-md transition hover:bg-green-700 disabled:bg-gray-400">
                    Proses File
                </button>
                <button id="hapus-semua" class="flex-1 rounded-lg border border-gray-300 bg-white px-6 py-3 text-base font-semibold text-gray-700 shadow-md transition hover:bg-gray-100">
                    Hapus Semua
                </button>
            </div>
          </div>
        </div>

        <div class="lg:col-span-1">
          <div class="rounded-lg bg-white p-6 shadow-sm border border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <i data-feather="clock" class="w-4 h-4"></i> Riwayat Anda
            </h3>

            <?php if (empty($riwayat)): ?>
              <p class="text-gray-400 italic text-sm">Belum ada aktivitas hari ini.</p>
            <?php else: ?>
              <div class="space-y-4">
                <?php foreach ($riwayat as $item): ?>
                  <div class="flex items-center justify-between border-b border-gray-50 pb-3 last:border-0">
                    <div class="max-w-[150px]">
                      <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($item['nama_file']) ?></p>
                      <p class="text-[10px] text-gray-400"><?= waktuUpload($item['waktu_proses']) ?></p>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-1 rounded uppercase <?= $item['status'] === 'finalized' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                      <?= $item['status'] === 'finalized' ? 'Berhasil' : 'Cek' ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div id="crop-modal" class="hidden fixed inset-0 z-[999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-lg">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
          <i data-feather="crop" class="text-green-600"></i> Sesuaikan Gambar KTP
      </h2>

      <div class="relative w-full h-80 overflow-hidden rounded-lg border bg-gray-50">
        <img id="crop-image" class="max-w-full" />
      </div>

      <div class="mt-6 flex justify-between items-center bg-gray-50 p-4 rounded-lg">
        <button id="rotate-left" class="p-2 bg-white border rounded-lg hover:bg-gray-100 transition shadow-sm"><i data-feather="rotate-ccw"></i></button>
        <div class="text-center flex-1">
            <span class="text-xs font-bold text-gray-500 uppercase block mb-1">Putar Gambar</span>
            <input type="range" id="rotate-slider" min="-180" max="180" value="0" class="w-2/3 h-1 bg-green-200 rounded-lg appearance-none cursor-pointer">
        </div>
        <button id="rotate-right" class="p-2 bg-white border rounded-lg hover:bg-gray-100 transition shadow-sm"><i data-feather="rotate-cw"></i></button>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button id="cancel-crop" class="px-6 py-2 text-gray-600 font-bold hover:text-gray-900 transition">Batal</button>
        <button id="save-crop" class="px-8 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-bold shadow-lg shadow-green-100 transition">Simpan & Lanjut</button>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
  <script>
    feather.replace();

    const dropzone = document.getElementById("dropzone");
    const dropzoneContent = document.getElementById("dropzone-content");
    const previewContainer = document.getElementById("preview-container");
    const imgPreview = document.getElementById("img-preview");
    const fileInput = document.getElementById("file-upload");
    const fileQueueList = document.getElementById("file-queue-list");
    const cropModal = document.getElementById("crop-modal");
    const cropImage = document.getElementById("crop-image");
    
    let fileQueue = new DataTransfer();
    let cropper = null;
    let currentFile = null;

    // Trigger click on input file
    dropzone.addEventListener("click", () => fileInput.click());

    // Fix event delegation for Input File
    fileInput.addEventListener("change", (e) => handleFiles(e.target.files));

    const handleFiles = (files) => {
      if (!files.length) return;
      const file = files[0];
      const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
      
      if (!validTypes.includes(file.type)) {
        alert("Gunakan format JPG atau PNG.");
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
        cropper = new Cropper(cropImage, {
            viewMode: 1,
            background: false,
            responsive: true
        });
      };
      reader.readAsDataURL(file);
    };

    document.getElementById("save-crop").onclick = () => {
      const canvas = cropper.getCroppedCanvas({ maxWidth: 2000, maxHeight: 2000 });
      canvas.toBlob((blob) => {
        const croppedFile = new File([blob], "ready_" + currentFile.name, { type: "image/jpeg" });
        
        fileQueue = new DataTransfer(); // Reset queue biar cuma ada 1 yang terbaru di preview
        fileQueue.items.add(croppedFile);
        
        // Show Preview
        imgPreview.src = URL.createObjectURL(blob);
        dropzoneContent.classList.add("hidden");
        previewContainer.classList.remove("hidden");

        renderFileQueue();
        cropModal.classList.add("hidden");
        cropper.destroy();
      }, "image/jpeg", 0.9);
    };

    const renderFileQueue = () => {
      fileQueueList.innerHTML = "";
      [...fileQueue.files].forEach((file) => {
        const item = document.createElement("div");
        item.className = "flex items-center justify-between rounded-lg border border-green-200 p-3 bg-green-50/50";
        item.innerHTML = `
          <div class="flex items-center space-x-3 overflow-hidden">
            <i data-feather="file" class="text-green-600"></i>
            <span class="text-sm font-bold text-gray-700 truncate">${file.name}</span>
          </div>
          <span class="text-xs text-gray-400 font-mono">${(file.size/1024).toFixed(1)} KB</span>
        `;
        fileQueueList.appendChild(item);
      });
      feather.replace();
    };

    document.getElementById("hapus-semua").onclick = () => {
      fileQueue = new DataTransfer();
      fileQueueList.innerHTML = "";
      previewContainer.classList.add("hidden");
      dropzoneContent.classList.remove("hidden");
      fileInput.value = "";
    };

    document.getElementById("proses-semua").onclick = async () => {
      if (fileQueue.files.length === 0) return alert("Pilih file dulu.");
      
      const btn = document.getElementById("proses-semua");
      btn.innerText = "Nge-scan...";
      btn.disabled = true;

      const formData = new FormData();
      formData.append("ktp_files[]", fileQueue.files[0]);

      try {
        const res = await fetch("proses/proses_upload.php", { method: "POST", body: formData });
        const data = await res.json();
        if (data.success) window.location.href = "prosesocr.php";
        else alert(data.message);
      } catch (err) {
        alert("Gagal terhubung ke server.");
        btn.innerText = "Proses File";
        btn.disabled = false;
      }
    };

    document.getElementById("cancel-crop").onclick = () => {
        cropModal.classList.add("hidden");
        cropper.destroy();
        fileInput.value = "";
    };

    // Rotation logic
    document.getElementById("rotate-slider").oninput = function() {
        cropper.rotateTo(this.value);
    };
    document.getElementById("rotate-left").onclick = () => cropper.rotate(-90);
    document.getElementById("rotate-right").onclick = () => cropper.rotate(90);
  </script>
</body>
</html>