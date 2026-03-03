<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Proses OCR - Sistem KTP</title>

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Feather Icons -->
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>

<body class="bg-gray-50 min-h-screen flex text-gray-800">

  <!-- Sidebar -->
  <?php include 'includes/navbar.php'; ?>

  <!-- Konten utama -->
  <div class="flex-1 ml-64 p-6 md:p-10">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Proses OCR</h1>

    <!-- Wrapper tengah -->
    <div class="max-w-3xl mx-auto space-y-8">

      <!-- Card utama status -->
      <div class="bg-white rounded-xl shadow border border-gray-200 p-10 text-center">
        <div class="flex items-center justify-center">
          <span class="flex h-20 w-20 items-center justify-center rounded-full bg-green-100 text-green-600">
            <i data-feather="refresh-cw" class="h-10 w-10 animate-spin"></i>
          </span>
        </div>

        <h2 class="mt-6 text-2xl font-semibold text-gray-900">Memproses KTP...</h2>
        <p class="mt-2 text-sm text-gray-500">Estimasi waktu</p>

        <!-- Progress bar -->
        <div class="mt-8">
          <div class="flex justify-between text-sm font-medium text-gray-600 mb-2">
            <span>Progress</span>
            <span id="progressText" class="font-semibold text-green-600">0%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
            <div id="progressBar" class="bg-green-600 h-3 rounded-full transition-all duration-500" style="width: 0%"></div>
          </div>
        </div>
      </div>

      <!-- Langkah-langkah proses -->
      <div id="steps" class="space-y-4">
        <div class="flex items-center space-x-4 rounded-lg bg-gray-50 p-4 border border-gray-200">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-gray-500">
            <i data-feather="file"></i>
          </span>
          <span class="text-sm font-medium text-gray-700">Validasi Format File</span>
        </div>

        <div class="flex items-center space-x-4 rounded-lg bg-gray-50 p-4 border border-gray-200">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-gray-500">
            <i data-feather="crop"></i>
          </span>
          <span class="text-sm font-medium text-gray-700">Deteksi Area KTP</span>
        </div>

        <div class="flex items-center space-x-4 rounded-lg bg-gray-50 p-4 border border-gray-200">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-gray-500">
            <i data-feather="key"></i>
          </span>
          <span class="text-sm font-medium text-gray-700">Ekstraksi Data NIK</span>
        </div>

        <div class="flex items-center space-x-4 rounded-lg bg-gray-50 p-4 border border-gray-200">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-gray-500">
            <i data-feather="check-circle"></i>
          </span>
          <span class="text-sm font-medium text-gray-700">Validasi Akurasi</span>
        </div>
      </div>
    </div>
  </div>

<script>
    feather.replace();

    const progressBar  = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const statusTitle  = document.querySelector('h2');
    const steps        = document.querySelectorAll('#steps > div');

    let backendFinished = false;
    let currentProgress = 0;

    document.addEventListener("DOMContentLoaded", () => {

        // Animasi progress
        const animationInterval = setInterval(() => {
            let increment = 0;

            if (!backendFinished) {
                if (currentProgress < 90) {
                    increment = Math.random() * 0.5 + 0.2;
                }
            } else {
                increment = 4;
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

        // Trigger proses OCR
        const formData = new FormData();
        formData.append('action', 'trigger_ocr');
        fetch('proses/proses_upload.php', { method: 'POST', body: formData });

        // Polling status backend
        const poller = setInterval(async () => {
            if (backendFinished) {
                clearInterval(poller);
                return;
            }

            const checkForm = new FormData();
            checkForm.append('action', 'check_status');

            try {
                const res  = await fetch('proses/proses_upload.php', { method: 'POST', body: checkForm });
                const json = await res.json();

                if (json.success && (json.status === 'done' || json.status === 'failed_but_continue')) {
                    backendFinished = true;
                    if (statusTitle) statusTitle.textContent = "Finalisasi Data...";
                }
            } catch (e) {}
        }, 1000);

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
                    step.classList.add('bg-green-50', 'border-green-200', 'transition-all', 'duration-500');

                    if (iconContainer) {
                        iconContainer.classList.remove('bg-gray-200', 'text-gray-500');
                        iconContainer.classList.add('bg-green-100', 'text-green-600');
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