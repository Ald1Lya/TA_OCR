<?php
session_start();
require_once 'config.php';
require_once 'csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

csrf_verify();

$log_id = isset($_POST['data_id']) ? (int) $_POST['data_id'] : 0;
if ($log_id <= 0) {
    die('ID data tidak valid.');
}

// Direktori penyimpanan gambar (path dinamis, tidak hardcoded)
$base_path  = realpath(__DIR__ . '/..');
$dir_public = $base_path . '/public/images/';
$dir_temp   = $base_path . '/PYTHON_OCR/temp_uploads/';

try {
    // Ambil nama file dari database sebelum dihapus
    $stmt = mysqli_prepare($db, "SELECT nama_file_sistem, nama_file_asli FROM log_ocr WHERE log_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $log_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data   = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($data && !empty($data['nama_file_sistem'])) {
        $nama_file = $data['nama_file_sistem'];

        // Hapus file gambar utama dari folder public
        $target_public = $dir_public . basename($nama_file);
        if (file_exists($target_public)) {
            @unlink($target_public);
        }

        // Hapus file annotated OCR dari folder temp Python berdasarkan nama file sistem
        $nama_murni = pathinfo($nama_file, PATHINFO_FILENAME);
        $pola       = $dir_temp . '*' . $nama_murni . '*';
        $files      = glob($pola);

        if ($files) {
            foreach ($files as $file) {
                $file = str_replace('/', DIRECTORY_SEPARATOR, $file);
                clearstatcache(true, $file);
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }

    // Hapus record dari database
    $stmt = mysqli_prepare($db, "DELETE FROM log_ocr WHERE log_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $log_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header('Location: ../riwayat.php?msg=hapus_sukses');

} catch (Exception $e) {
    header('Location: ../riwayat.php?msg=gagal');
}
exit;
?>
