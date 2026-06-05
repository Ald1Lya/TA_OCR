<?php

$is_cli = !isset($_SERVER['HTTP_HOST']);

if (!$is_cli) {
    session_start();
    if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
        http_response_code(403);
        die('Akses ditolak. Halaman ini hanya bisa diakses oleh Administrator yang sudah login.');
    }
}

require_once '../proses/config.php';

// Umur maksimal file dalam hari
$max_age_days = 30;

$line_break = $is_cli ? "\n" : "\n<br>";

echo "=== Memulai Proses Pembersihan Data KTP Lama (Lebih dari {$max_age_days} hari) ==={$line_break}";

// Hitung batas tanggal
$limit_date = date('Y-m-d H:i:s', strtotime("-{$max_age_days} days"));
echo "Menghapus file yang diunggah sebelum: {$limit_date}{$line_break}";

// Cari data log_ocr yang usianya lebih dari 30 hari dan masih memiliki file
$query = "SELECT log_id, nama_file_sistem FROM log_ocr WHERE waktu_upload < ? AND nama_file_sistem IS NOT NULL AND nama_file_sistem != 'TERHAPUS_OTOMATIS'";
$stmt  = mysqli_prepare($db, $query);
mysqli_stmt_bind_param($stmt, 's', $limit_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$count_deleted = 0;
$uploadDir     = realpath(__DIR__ . '/../public/images/') . DIRECTORY_SEPARATOR;

while ($row = mysqli_fetch_assoc($result)) {
    $filename = $row['nama_file_sistem'];
    $filepath = $uploadDir . $filename;

    // Hapus file fisik
    if (!empty($filename) && file_exists($filepath)) {
        if (unlink($filepath)) {
            // Update database agar sistem tahu file sudah dihapus oleh retention policy
            $update_stmt = mysqli_prepare($db, "UPDATE log_ocr SET nama_file_sistem = 'TERHAPUS_OTOMATIS' WHERE log_id = ?");
            mysqli_stmt_bind_param($update_stmt, 'i', $row['log_id']);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);

            echo "Berhasil menghapus: {$filename}{$line_break}";
            $count_deleted++;
        } else {
            echo "Gagal menghapus file (permission error): {$filename}{$line_break}";
        }
    } else {
        // File fisik tidak ada, perbaiki database saja
        $update_stmt = mysqli_prepare($db, "UPDATE log_ocr SET nama_file_sistem = 'TERHAPUS_OTOMATIS' WHERE log_id = ?");
        mysqli_stmt_bind_param($update_stmt, 'i', $row['log_id']);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
}

mysqli_stmt_close($stmt);

echo "{$line_break}=== Selesai. Total file dibersihkan: {$count_deleted} ===\n";
?>
