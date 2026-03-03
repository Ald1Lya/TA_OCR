<?php
session_start();

// 1. Cek Admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once '../proses/config.php';

// --- KONFIGURASI PATH (JANGAN DIUBAH) ---
$bat_file = "C:/laragon/www/TugasAkhirCapstone/SistemOCRSamboja/PYTHON_OCR/start_ocr.bat";
$log_file = "C:/laragon/www/TugasAkhirCapstone/SistemOCRSamboja/PYTHON_OCR/ocr_log.txt";
$port_flask = 5000;

// Fungsi Cek Status Port
function isOcrRunning($host, $port) {
    $connection = @fsockopen($host, $port, $errno, $errstr, 1);
    if (is_resource($connection)) {
        fclose($connection);
        return true; 
    }
    return false; 
}

// --- AJAX HANDLER (UNTUK UPDATE REALTIME TANPA RELOAD) ---
if (isset($_GET['ajax_status'])) {
    header('Content-Type: application/json');
    $running = isOcrRunning('127.0.0.1', $port_flask);
    
    // Baca 5 baris terakhir log
    $logs = ["Menunggu sistem..."];
    if (file_exists($log_file)) {
        $lines = file($log_file);
        $logs = array_slice($lines, -5); // Ambil 5 terakhir
        // Bersihkan whitespace
        $logs = array_map('trim', $logs);
    }

    echo json_encode([
        'status' => $running,
        'logs' => $logs
    ]);
    exit;
}


$status = isOcrRunning('127.0.0.1', $port_flask);

// LOGIKA TOMBOL 
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'start') {
        if (!$status) {
            // Reset log 
            if(file_exists($log_file)) file_put_contents($log_file, "--- Booting System ---\n");
            
            pclose(popen("start /B cmd /c \"" . $bat_file . "\"", "r"));
            sleep(2); 
            $_SESSION['flash_msg'] = ['type'=>'success', 'text'=>'Sistem OCR Berhasil Dinyalakan!'];
        }
    } elseif ($_POST['action'] === 'stop') {
        if ($status) {
            shell_exec("taskkill /F /IM python.exe");
            sleep(1);
            $_SESSION['flash_msg'] = ['type'=>'success', 'text'=>'Sistem OCR Berhasil Dimatikan.'];
        }
    }
 
    header("Location: kontrol_sistem.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kontrol Server OCR</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style> 
    body { font-family: 'Inter', sans-serif; } 
    .font-mono { font-family: 'JetBrains Mono', monospace; }
</style>
</head>
<body class="bg-gray-50 min-h-screen flex text-gray-800 antialiased">

<?php include 'includes/navbar_admin.php'; ?>

<main class="flex-1 ml-64 p-8 transition-all duration-300">
  
  <div class="mb-8 flex justify-between items-end">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Kontrol Server OCR</h1>
        <p class="text-sm text-gray-500 mt-1">Panel kendali mesin Python (Flask).</p>
    </div>
    <div class="flex items-center gap-2 text-xs font-mono text-gray-400 bg-white px-3 py-1.5 rounded border border-gray-200 shadow-sm">
        <span class="w-2 h-2 rounded-full bg-gray-300" id="pulse-indicator"></span>
        Port: <?= $port_flask ?>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      
      <div class="bg-white p-10 rounded-2xl border border-gray-200 shadow-sm flex flex-col items-center justify-center text-center relative overflow-hidden group">
          <div id="status-container">
              <div class="animate-pulse flex flex-col items-center">
                   <div class="w-20 h-20 bg-gray-100 rounded-full mb-4"></div>
                   <div class="h-8 w-32 bg-gray-100 rounded mb-2"></div>
                   <div class="h-4 w-48 bg-gray-100 rounded"></div>
              </div>
          </div>
          
          <div class="mt-8 w-full bg-gray-900 rounded-lg p-3 text-left shadow-inner border border-gray-700 overflow-hidden relative">
              <div class="flex justify-between items-center mb-2 border-b border-gray-700 pb-1">
                  <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Live Log</span>
                  <div class="flex gap-1">
                      <div class="w-2 h-2 rounded-full bg-red-500"></div>
                      <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                      <div class="w-2 h-2 rounded-full bg-green-500"></div>
                  </div>
              </div>
              <div id="log-terminal" class="font-mono text-[10px] text-green-400 h-16 overflow-hidden flex flex-col justify-end">
                  <p class="opacity-50">Connecting...</p>
              </div>
          </div>
      </div>

      <div class="bg-white p-10 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-center">
          <div class="mb-8">
              <h3 class="text-lg font-bold text-gray-900">Aksi Server</h3>
              <p class="text-sm text-gray-500">Kontrol manual proses background.</p>
          </div>
          
          <form method="POST" id="controlForm" class="space-y-4">
              <input type="hidden" name="action" id="actionInput">
              
              <div id="btn-container">
                 <button type="button" disabled class="w-full py-5 bg-gray-100 text-gray-400 rounded-xl font-bold flex items-center justify-center gap-3 cursor-not-allowed">
                      Loading...
                  </button>
              </div>
          </form>

          <div class="mt-8 pt-6 border-t border-gray-100">
             <div class="flex items-center justify-between text-xs text-gray-400">
                 <span>Script: start_ocr.bat</span>
                 <span id="text-status" class="font-mono">Checking...</span>
             </div>
          </div>
      </div>

  </div>
</main>

<script>
    feather.replace();

    <?php if(isset($_SESSION['flash_msg'])): ?>
        Swal.fire({
            icon: '<?= $_SESSION['flash_msg']['type'] ?>',
            title: '<?= $_SESSION['flash_msg']['type'] == "success" ? "Sukses" : "Info" ?>',
            text: '<?= $_SESSION['flash_msg']['text'] ?>',
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>


    function updateStatus() {
        fetch('kontrol_sistem.php?ajax_status=1')
            .then(response => response.json())
            .then(data => {
                const isRunning = data.status;
                const logs = data.logs;

              
                const pulse = document.getElementById('pulse-indicator');
                pulse.className = isRunning ? "w-2 h-2 rounded-full bg-green-500 animate-ping" : "w-2 h-2 rounded-full bg-red-400";

              
                const statusContainer = document.getElementById('status-container');
                if(isRunning) {
                    statusContainer.innerHTML = `
                        <div class="relative z-10">
                            <div class="w-24 h-24 rounded-full bg-green-100 flex items-center justify-center mb-4 mx-auto shadow-lg shadow-green-50 animate-pulse">
                                <i data-feather="cpu" class="w-10 h-10 text-green-600"></i>
                            </div>
                            <h2 class="text-3xl font-black text-gray-900 mb-1 tracking-tight">ONLINE</h2>
                            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-bold uppercase tracking-wider">
                                Active
                            </span>
                            <p class="text-gray-400 text-xs mt-3">Mesin OCR siap bekerja.</p>
                        </div>
                    `;
                } else {
                    statusContainer.innerHTML = `
                        <div class="relative z-10">
                            <div class="w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4 mx-auto">
                                <i data-feather="power" class="w-10 h-10 text-gray-400"></i>
                            </div>
                            <h2 class="text-3xl font-black text-gray-400 mb-1 tracking-tight">OFFLINE</h2>
                            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[10px] font-bold uppercase tracking-wider">
                                Stopped
                            </span>
                            <p class="text-gray-400 text-xs mt-3">Layanan dimatikan.</p>
                        </div>
                    `;
                }
                feather.replace(); 

             
                const btnContainer = document.getElementById('btn-container');
                if(isRunning) {
                    btnContainer.innerHTML = `
                        <button type="button" onclick="confirmStop()" class="w-full py-5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-lg shadow-xl shadow-red-50 transition-all transform hover:-translate-y-0.5 active:scale-95 flex items-center justify-center gap-3">
                             <div class="flex items-center justify-center"><i data-feather="square" class="w-5 h-5 mr-2"></i> MATIKAN SISTEM</div>
                        </button>
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 flex gap-2 mt-4 items-center">
                            <i data-feather="info" class="text-gray-400 w-4 h-4 flex-shrink-0"></i>
                            <p class="text-[10px] text-gray-500">Matikan jika tidak digunakan.</p>
                        </div>
                    `;
                } else {
                    btnContainer.innerHTML = `
                        <button type="button" onclick="confirmStart()" class="w-full py-5 bg-gray-900 hover:bg-black text-white rounded-xl font-bold text-lg shadow-xl shadow-gray-200 transition-all transform hover:-translate-y-0.5 active:scale-95 flex items-center justify-center gap-3">
                            <div class="flex items-center justify-center"><i data-feather="play" class="w-5 h-5 mr-2"></i> NYALAKAN SISTEM</div>
                        </button>
                        <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-100 flex gap-2 mt-4 items-center">
                            <i data-feather="alert-circle" class="text-yellow-600 w-4 h-4 flex-shrink-0"></i>
                            <p class="text-[10px] text-yellow-700">Booting perlu waktu 2-3 detik.</p>
                        </div>
                    `;
                }
                feather.replace();

             
                const logDiv = document.getElementById('log-terminal');
                logDiv.innerHTML = logs.map(line => `<p class="truncate">> ${line}</p>`).join('');

              
                const txtStatus = document.getElementById('text-status');
                txtStatus.className = isRunning ? 'text-green-500 font-mono' : 'text-red-400 font-mono';
                txtStatus.innerText = isRunning ? '● Running' : '● Idle';

            })
            .catch(err => console.error(err));
    }


    updateStatus();
  
    setInterval(updateStatus, 3000);


    function confirmStart() {
        Swal.fire({
            title: 'Nyalakan OCR?',
            text: "Menjalankan Python di background...",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#111827',
            cancelButtonColor: '#d1d5db',
            confirmButtonText: 'Ya, Nyalakan!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memulai...',
                    text: 'Tunggu sebentar',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading() }
                });
                document.getElementById('actionInput').value = 'start';
                document.getElementById('controlForm').submit();
            }
        });
    }

    function confirmStop() {
        Swal.fire({
            title: 'Matikan OCR?',
            text: "Layanan upload akan terhenti.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#374151',
            confirmButtonText: 'Ya, Matikan!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('actionInput').value = 'stop';
                document.getElementById('controlForm').submit();
            }
        });
    }
</script>
</body>
</html>