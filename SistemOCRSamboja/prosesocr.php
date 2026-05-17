<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Proses OCR - Sistem KTP</title>

  <link rel="stylesheet" href="../assets/css/style.css" />
  
  <style>
      @font-face {
          font-family: 'Inter';
          src: url('../assets/fonts/Inter-Regular.ttf') format('truetype');
          font-weight: 400;
          font-style: normal;
      }
      @font-face {
          font-family: 'Inter';
          src: url('../assets/fonts/Inter-SemiBold.ttf') format('truetype');
          font-weight: 600;
          font-style: normal;
      }
      @font-face {
          font-family: 'Inter';
          src: url('../assets/fonts/static/Inter-Bold.ttf') format('truetype');
          font-weight: 700;
          font-style: normal;
      }
      body { font-family: 'Inter', sans-serif; }
  </style>
</head>

<body class="bg-gray-50 min-h-screen flex text-gray-800">

  <?php include 'includes/navbar.php'; ?>

  <div class="flex-1 ml-64 p-6 md:p-10">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Proses OCR</h1>

    <div class="max-w-3xl mx-auto space-y-8">

      <div class="bg-white rounded-xl shadow border border-gray-200 p-10 text-center">
        <div class="flex items-center justify-center">
          <span class="flex h-20 w-20 items-center justify-center rounded-full bg-green-100 text-green-600 shadow-sm">
            <i data-feather="refresh-cw" class="h-10 w-10 animate-spin"></i>
          </span>
        </div>

        <h2 class="mt-6 text-2xl font-semibold text-gray-900">Memproses KTP...</h2>
        <p class="mt-2 text-sm text-gray-500">Estimasi waktu</p>

        <div class="mt-8">
          <div class="flex justify-between text-sm font-medium text-gray-600 mb-2">
            <span>Progress</span>
            <span id="progressText" class="font-semibold text-green-600">0%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden shadow-inner border border-gray-300">
            <div id="progressBar" class="bg-green-600 h-3 rounded-full transition-all duration-500 shadow-sm" style="width: 0%"></div>
          </div>
        </div>
      </div>

      <div id="steps" class="space-y-4">
        <div class="flex items-center space-x-4 rounded-lg bg-gray-50 p-4 border border-gray-200 shadow-sm transition-colors">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-gray-500 shadow-inner">
            <i data-feather="file"></i>
          </span>
          <span class="text-sm font-medium text-gray-700">Validasi Format File</span>
        </div>

        <div class="flex items-center space-x-4 rounded-lg bg-gray-50 p-4 border border-gray-200 shadow-sm transition-colors">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-gray-500 shadow-inner">
            <i data-feather="crop"></i>
          </span>
          <span class="text-sm font-medium text-gray-700">Deteksi Area KTP</span>
        </div>

        <div class="flex items-center space-x-4 rounded-lg bg-gray-50 p-4 border border-gray-200 shadow-sm transition-colors">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-gray-500 shadow-inner">
            <i data-feather="key"></i>
          </span>
          <span class="text-sm font-medium text-gray-700">Ekstraksi Data NIK</span>
        </div>

        <div class="flex items-center space-x-4 rounded-lg bg-gray-50 p-4 border border-gray-200 shadow-sm transition-colors">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-gray-500 shadow-inner">
            <i data-feather="check-circle"></i>
          </span>
          <span class="text-sm font-medium text-gray-700">Validasi Akurasi</span>
        </div>
      </div>
    </div>
  </div>

<script src="../assets/js/feather.min.js"></script>
<script>
    feather.replace();

    const progressBar  = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const statusTitle  = document.querySelector('h2');
    const steps        = document.querySelectorAll('#steps > div');

    let backendFinished = false;
    let currentProgress = 0;

    document.addEventListener("DOMContentLoaded", () => {

        // --- 1. ANIMASI PROGRESS BAR (Otomatis naik ke 90%) ---
        const animationInterval = setInterval(() => {
            let increment = 0;

            if (!backendFinished) {
                // Selama Python belum kelar, progress mentok di 90%
                if (currentProgress < 90) {
                    increment = Math.random() * 0.5 + 0.2;
                }
            } else {
                // Kalau Python kasih sinyal BERES, langsung lari ke 100%
                increment = 5; 
            }

            currentProgress += increment;

            if (currentProgress >= 100) {
                currentProgress = 100;
                if (backendFinished) {
                    clearInterval(animationInterval);
                    finalizeAndRedirect();
                }
            } else if (currentProgress > 90 && !backendFinished) {
                currentProgress = 90;
            }

            updateVisuals(currentProgress);
        }, 50);


        // --- 2. TRIGGER OCR (HIT AND RUN) ---
        const formData = new FormData();
        formData.append('action', 'trigger_ocr');
        fetch('proses/proses_upload.php', { method: 'POST', body: formData });

// --- 3. KEMBALIKAN POLLING (VERSI TENDANG PAKSA) ---
        const poller = setInterval(async () => {
            if (typeof backendFinished !== 'undefined' && backendFinished) {
                clearInterval(poller);
                return;
            }

            const checkForm = new FormData();
            checkForm.append('action', 'check_status');

            try {
                const res  = await fetch('proses/proses_upload.php', { method: 'POST', body: checkForm });
                const json = await res.json();

                // [SKENARIO A]: JIKA MESIN TIMEOUT / GAGAL
                if (json.success === false) {
                    clearInterval(poller);
                    backendFinished = true; 
                    alert("Mesin OCR Kewalahan / Error: " + (json.message || "Silakan coba lagi."));
                    window.location.href = 'upload.php'; 
                    return;
                }

                // [SKENARIO B]: JIKA NORMAL / SUKSES
                if (json.success && (json.status === 'done' || json.status === 'pending_review' || json.status === 'Berhasil' || json.status === 'failed_but_continue')) {
                    clearInterval(poller);
                    backendFinished = true; 
                    
                    // UBAH TULISAN BIAR KEREN
                    const statusTitle = document.getElementById('status-title'); // Sesuaikan id kalau beda
                    if (statusTitle) statusTitle.textContent = "Berhasil! Mengalihkan...";

                    // PAKSA PINDAH HALAMAN SETELAH JEDA SETENGAH DETIK!
                    setTimeout(() => {
                        // CATATAN: Kalau link halaman hasil lu beda, ganti di sini ya!
                        window.location.href = 'hasilocr.php'; 
                    }, 500);
                }
            } catch (e) {
                console.log("Masih menunggu respons server...");
            }
        }, 1500);

        // --- FUNGSI UPDATE VISUAL UI ---
        function updateVisuals(percent) {
            const value = Math.round(percent);
            progressBar.style.width = value + '%';
            progressText.textContent = value + '%';

            const currentStepIndex = Math.floor((value - 1) / 25);

            steps.forEach((step, index) => {
                const iconContainer = step.querySelector('span');
                const text = step.querySelector('span:nth-child(2)');
                const icon = step.querySelector('i');

                if (index <= currentStepIndex) {
                    step.classList.remove('bg-gray-50', 'border-gray-200');
                    step.classList.add('bg-green-50', 'border-green-200', 'shadow-sm');

                    if (iconContainer) {
                        iconContainer.classList.remove('bg-gray-200', 'text-gray-500', 'shadow-inner');
                        iconContainer.classList.add('bg-green-100', 'text-green-600', 'shadow-sm');
                    }

                    if (text) {
                        text.classList.remove('text-gray-700');
                        text.classList.add('text-green-800', 'font-bold');
                    }

                    if (icon && index < currentStepIndex) {
                        icon.setAttribute('data-feather', 'check');
                        feather.replace();
                    }
                }
            });
        }

        // --- FUNGSI REDIRECT ---
        function finalizeAndRedirect() {
            steps.forEach(step => {
                const icon = step.querySelector('i');
                if (icon) {
                    icon.setAttribute('data-feather', 'check');
                    feather.replace();
                }
            });

            if (statusTitle) statusTitle.textContent = "Selesai! Mengalihkan...";

            setTimeout(() => {
                window.location.href = 'hasilocr.php';
            }, 800);
        }
    });
</script>

</body>
</html>