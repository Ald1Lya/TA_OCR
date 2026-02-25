<?php
// Mencegah output sebelum header file download
ob_start();

session_start();
require_once 'config.php'; // Pastikan path config benar

// 1. Cek Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// 2. Cek Koneksi Database
if (!isset($db) || !$db) {
    ob_end_clean();
    die("Error Fatal: Koneksi database tidak tersedia.");
}

// 3. Query Data (Menggunakan Syntax MySQL)
$sql = "SELECT 
            lo.waktu_upload, 
            lo.nama_file_asli, 
            COALESCE(lo.nik_final, lo.nik_terdeteksi) AS nik, 
            lo.status_proses, 
            lo.skor_kepercayaan, 
            sk.nama_lengkap AS operator
        FROM log_ocr lo
        LEFT JOIN staf_kecamatan sk ON lo.id_staf = sk.id
        ORDER BY lo.waktu_upload DESC";

$result = mysqli_query($db, $sql);

if (!$result) {
    ob_end_clean();
    die("Query Gagal: " . mysqli_error($db));
}

// Bersihkan buffer agar file murni CSV
ob_end_clean();

// 4. Set Header Browser untuk Download
$filename = 'Riwayat_OCR_' . date('Y-m-d_Hi') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Buka output stream
$output = fopen('php://output', 'w');

// Tambahkan BOM (Byte Order Mark) agar Excel otomatis mendeteksi UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Tulis Header Kolom
fputcsv($output, ['No', 'Waktu Proses', 'Nama File', 'NIK Terdeteksi', 'Status', 'Akurasi', 'Operator']);

// 5. Loop Data
$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    
    // Format Waktu
    $waktu = date('d/m/Y H:i', strtotime($row['waktu_upload']));

    // Mapping Status (English DB -> Indo CSV)
    $status_raw = $row['status_proses'];
    $status_text = 'Memproses';
    
    switch ($status_raw) {
        case 'finalized': 
            $status_text = 'Berhasil'; 
            break;
        case 'pending_review': 
            $status_text = 'Perlu Cek'; 
            break;
        case 'failed': 
        case 'error_php': 
            $status_text = 'Gagal'; 
            break;
    }

    // Format Akurasi
    $akurasi = number_format((float)$row['skor_kepercayaan'] * 100, 1) . '%';
    
    // Format Operator
    $operator = !empty($row['operator']) ? $row['operator'] : 'Sistem';
    
    // Format NIK untuk Excel (Trik: ="NIK")
    // Ini memaksa Excel membacanya sebagai String, bukan Angka
    $nik_excel = !empty($row['nik']) ? "=\"" . $row['nik'] . "\"" : '-';

    // Tulis Baris ke CSV
    fputcsv($output, [
        $no++,
        $waktu,
        $row['nama_file_asli'],
        $nik_excel,
        $status_text,
        $akurasi,
        $operator
    ]);
}

// Tutup stream dan koneksi
fclose($output);
mysqli_close($db);
exit;
?>