<?php
session_start();
include 'config.php'; 
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metode tidak diizinkan.");
}

// Ambil data dari form
$action = $_POST['action'] ?? null;
$log_id = $_POST['data_id'] ?? null;

// Validasi data
if ($action !== 'hapus_data_ktp' || empty($log_id)) {
    die("Aksi tidak valid atau ID data tidak ditemukan.");
}

try {
    // AMBIL NAMA FILE & HAPUS FILE FISIK
    $sql_get_file = "SELECT nama_file_sistem FROM log_ocr WHERE log_id = ?";
    
    $stmt_file = mysqli_prepare($db, $sql_get_file);
    
    if ($stmt_file) {
        mysqli_stmt_bind_param($stmt_file, "i", $log_id);
        mysqli_stmt_execute($stmt_file);
        
        $result_file = mysqli_stmt_get_result($stmt_file);
        
        if ($result_file && mysqli_num_rows($result_file) > 0) {
            $data = mysqli_fetch_assoc($result_file);
            $nama_file = $data['nama_file_sistem'] ?? null;

            if ($nama_file) {
                // 1. HAPUS GAMBAR ORIGINAL (Di folder public/images)
                $file_path_original = '../public/images/' . $nama_file;
                
                if (file_exists($file_path_original)) {
                    unlink($file_path_original);
                }

                // 2. HAPUS GAMBAR RAW/ANNOTATED (Di folder PYTHON_OCR)
                $python_dir = realpath(__DIR__ . '/../PYTHON_OCR/temp_uploads/');
                if (!$python_dir || !file_exists($python_dir)) {
                    // Fallback ke alamat absolute jika realpath gagal
                    $python_dir = 'C:/laragon/www/SistemOCRSamboja/PYTHON_OCR/temp_uploads/';
                }
                $python_dir = rtrim($python_dir, '/\\') . '/';

                // Cari pakai radar (glob) untuk jaga-jaga kalau namanya ada tambahan angka/teks
                $nama_tanpa_ext = pathinfo($nama_file, PATHINFO_FILENAME);
                $pola_pencarian_raw = $python_dir . 'annotated_*' . $nama_tanpa_ext . '*.*';
                
                $files_raw_ditemukan = glob($pola_pencarian_raw);
                
                if (!empty($files_raw_ditemukan)) {
                    foreach ($files_raw_ditemukan as $file_raw) {
                        if (file_exists($file_raw)) {
                            unlink($file_raw); // Sedot & Hapus file raw-nya
                        }
                    }
                }
            }
        }
        mysqli_stmt_close($stmt_file); 
    }

    // HAPUS DATA DARI DATABASE
    $sql_delete = "DELETE FROM log_ocr WHERE log_id = ?";
    
    $stmt_delete = mysqli_prepare($db, $sql_delete);
    
    if ($stmt_delete) {
        // Bind parameter
        mysqli_stmt_bind_param($stmt_delete, "i", $log_id);
        
        // Eksekusi
        $success = mysqli_stmt_execute($stmt_delete);
        
        if (!$success) {
            throw new Exception("Gagal menghapus data: " . mysqli_error($db));
        }
        
        mysqli_stmt_close($stmt_delete);
    } else {
        throw new Exception("Query Error: " . mysqli_error($db));
    }

    // Redirect kembali ke halaman riwayat
    header('Location: ../riwayat.php?msg=hapus_sukses');
    exit;

} catch (Exception $e) {
    die("Error saat menghapus data: " . $e->getMessage());
}
?>