<?php
session_start();
include 'config.php'; 

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$log_id = $_POST['data_id'] ?? null;
if (empty($log_id)) die("ID data tidak ditemukan.");

try {
    // 1. AMBIL DUA JENIS NAMA FILE DARI DB
    $sql_get = "SELECT nama_file_sistem, nama_file_asli FROM log_ocr WHERE log_id = ?";
    $stmt = mysqli_prepare($db, $sql_get);
    mysqli_stmt_bind_param($stmt, "i", $log_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($data && !empty($data['nama_file_sistem'])) {
        $nama_file = $data['nama_file_sistem'];
        $nama_asli = $data['nama_file_asli'];

        $base_path = 'C:/laragon/www/TugasAkhirCapstone/SistemOCRSamboja';
        
        $dir_public = $base_path . '/public/images/';
        $dir_temp   = $base_path . '/PYTHON_OCR/temp_uploads/';

        $target_public = $dir_public . $nama_file;
        if (file_exists($target_public)) {
            @unlink($target_public);
        }

        $keywords = [];
        
        $keywords[] = pathinfo($nama_file, PATHINFO_FILENAME);
        
        if (!empty($nama_asli)) {
            $keywords[] = pathinfo($nama_asli, PATHINFO_FILENAME);
        }

        foreach ($keywords as $kunci) {
            $pola = $dir_temp . "*" . $kunci . "*";
            $files_found = glob($pola);

            if ($files_found) {
                foreach ($files_found as $file) {
                    $file = str_replace('/', '\\', $file); // Fix Windows Slash
                    clearstatcache(true, $file);
                    
                    if (file_exists($file)) {
                        @chmod($file, 0777);
                        if (!@unlink($file)) {
                            // JURUS TERAKHIR: CMD FORCE DELETE
                            shell_exec('del /F /Q "' . $file . '"');
                        }
                    }
                }
            }
        }
    }

  
    $sql_del = "DELETE FROM log_ocr WHERE log_id = ?";
    $stmt_del = mysqli_prepare($db, $sql_del);
    mysqli_stmt_bind_param($stmt_del, "i", $log_id);
    $success = mysqli_stmt_execute($stmt_del);
    mysqli_stmt_close($stmt_del);

    header('Location: ../riwayat.php?msg=hapus_sukses');

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>