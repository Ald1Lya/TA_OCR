<?php
ob_start();
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if (!$db) {
    ob_end_clean();
    die('Koneksi database bermasalah.');
}

$sql = "
    SELECT
        lo.waktu_upload,
        lo.nama_file_asli,
        COALESCE(lo.nik_final, lo.nik_terdeteksi) AS nik,
        lo.status_proses,
        lo.skor_kepercayaan,
        sk.nama_lengkap AS operator
    FROM log_ocr lo
    LEFT JOIN staf_kecamatan sk ON lo.id_staf = sk.id
    ORDER BY lo.waktu_upload DESC
";

$result = mysqli_query($db, $sql);

if (!$result) {
    ob_end_clean();
    die('Gagal mengambil data.');
}

ob_end_clean();

$filename = 'Riwayat_OCR_' . date('Y-m-d_Hi') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM UTF-8 agar Excel membaca encoding dengan benar
fwrite($output, "\xEF\xBB\xBF");

// Gunakan delimiter titik koma (;) agar otomatis rapi dalam kolom di Excel regional Indonesia
fputcsv($output, ['No', 'Waktu Proses', 'Nama File', 'NIK', 'Status', 'Akurasi', 'Operator'], ';');

$no = 1;

while ($row = mysqli_fetch_assoc($result)) {
    $waktu = date('d/m/Y H:i', strtotime($row['waktu_upload']));

    switch ($row['status_proses']) {
        case 'finalized':   $status = 'Berhasil';   break;
        case 'pending_review': $status = 'Perlu Cek'; break;
        case 'failed':
        case 'error_php':   $status = 'Gagal';      break;
        default:            $status = 'Memproses';
    }

    $akurasi  = number_format((float) $row['skor_kepercayaan'] * 100, 1) . '%';
    $operator = $row['operator'] ?: 'Sistem';
    // Prefix ="..." mencegah Excel mengubah NIK menjadi notasi ilmiah
    $nik      = $row['nik'] ? '="' . $row['nik'] . '"' : '-';

    fputcsv($output, [$no++, $waktu, $row['nama_file_asli'], $nik, $status, $akurasi, $operator], ';');
}

fclose($output);
mysqli_close($db);
exit;
?>
