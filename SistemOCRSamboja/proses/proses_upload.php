<?php
ignore_user_abort(true);
set_time_limit(0);
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

session_start();
header('Content-Type: application/json');
require_once 'config.php';

function kirimRespon(array $data): void {
    ob_clean();
    echo json_encode($data);
    ob_end_flush();
    exit;
}

// Cek apakah server Flask OCR aktif di port 5000
function isPythonServerAlive(string $host = '127.0.0.1', int $port = 5000): bool {
    $connection = @fsockopen($host, $port, $errno, $errstr, 1);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    return false;
}

if (!isset($_SESSION['user_id'])) {
    kirimRespon(['success' => false, 'message' => 'Sesi habis']);
}

// --- UPLOAD FILE ---
if (isset($_FILES['ktp_files'])) {

    if (!isPythonServerAlive()) {
        kirimRespon(['success' => false, 'message' => 'GAGAL: Server OCR sedang MATI. Silakan hubungi Admin.']);
    }

    $files     = $_FILES['ktp_files'];
    $id_staf   = $_SESSION['user_id'];
    $uploadDir = realpath(__DIR__ . '/../public/images') . DIRECTORY_SEPARATOR;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $i        = count($files['name']) - 1;
    $namaAsli = basename($files['name'][$i]);
    $tmpPath  = $files['tmp_name'][$i];
    $ext      = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));

    // Validasi tipe file di sisi server menggunakan MIME type aktual
    $allowed_mime = ['image/jpeg', 'image/png'];
    $finfo        = finfo_open(FILEINFO_MIME_TYPE);
    $mime         = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_mime, true)) {
        kirimRespon(['success' => false, 'message' => 'Tipe file tidak diizinkan. Hanya JPG dan PNG.']);
    }

    // Validasi ukuran file maksimal 5MB
    if ($files['size'][$i] > 5 * 1024 * 1024) {
        kirimRespon(['success' => false, 'message' => 'Ukuran file melebihi batas 5MB.']);
    }

    $namaUnik = uniqid('ktp_', true) . '.' . $ext;
    $target   = $uploadDir . $namaUnik;

    if (!move_uploaded_file($tmpPath, $target)) {
        kirimRespon(['success' => false, 'message' => 'Upload gagal.']);
    }

    $sql  = "INSERT INTO log_ocr (id_staf, nama_file_asli, status_proses, waktu_upload, nama_file_sistem) VALUES (?, ?, 'pending', NOW(), ?)";
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        kirimRespon(['success' => false, 'message' => 'Kesalahan database.']);
    }

    mysqli_stmt_bind_param($stmt, 'iss', $id_staf, $namaAsli, $namaUnik);
    mysqli_stmt_execute($stmt);

    $_SESSION['current_log_id'] = mysqli_insert_id($db);
    mysqli_stmt_close($stmt);

    kirimRespon(['success' => true, 'mode' => 'upload']);
}

// --- CEK STATUS OCR ---
if (isset($_POST['action']) && $_POST['action'] === 'check_status') {
    if (!isset($_SESSION['current_log_id'])) {
        kirimRespon(['success' => false]);
    }

    $log_id = (int) $_SESSION['current_log_id'];
    $stmt   = mysqli_prepare($db, "SELECT status_proses, nik_terdeteksi FROM log_ocr WHERE log_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $log_id);
    mysqli_stmt_execute($stmt);
    $res  = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$data) {
        kirimRespon(['success' => false]);
    }

    if ($data['status_proses'] === 'pending_review' || !empty($data['nik_terdeteksi'])) {
        kirimRespon(['success' => true, 'status' => 'done']);
    }

    if (in_array($data['status_proses'], ['failed', 'error_system'], true)) {
        kirimRespon(['success' => true, 'status' => 'failed_but_continue']);
    }

    kirimRespon(['success' => true, 'status' => 'processing']);
}

// --- TRIGGER OCR KE PYTHON FLASK ---
if (isset($_POST['action']) && $_POST['action'] === 'trigger_ocr') {
    if (!isset($_SESSION['current_log_id'])) {
        kirimRespon(['success' => false]);
    }

    $log_id = (int) $_SESSION['current_log_id'];

    // Lepas kunci sesi agar halaman lain tidak terblokir selama proses OCR berlangsung
    session_write_close();

    $stmt = mysqli_prepare($db, "SELECT nama_file_sistem, nama_file_asli FROM log_ocr WHERE log_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $log_id);
    mysqli_stmt_execute($stmt);
    $res  = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$data) {
        kirimRespon(['success' => false]);
    }

    $pathImg = realpath(__DIR__ . '/../public/images/' . $data['nama_file_sistem']);

    $stmt = mysqli_prepare($db, "UPDATE log_ocr SET status_proses='processing' WHERE log_id=?");
    mysqli_stmt_bind_param($stmt, 'i', $log_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    try {
        $cfile = new CURLFile($pathImg, mime_content_type($pathImg), $data['nama_file_sistem']);

        $ch = curl_init('http://127.0.0.1:5000/ocr');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['file' => $cfile],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($http_code !== 200 || $response === false) {
            throw new Exception('OCR Error/Timeout: ' . $curl_err);
        }

        $json = json_decode($response, true);

        if (isset($json['status']) && strpos($json['status'], 'failed') !== false) {
            throw new Exception('Python merespon gagal: ' . ($json['notes'] ?? 'Unknown Error'));
        }

        $nik   = $json['nik'] ?? null;
        $score = $json['score'] ?? 0;

        $raw_text_array = $json['raw_text'] ?? [];
        $raw_text_json  = is_array($raw_text_array) ? json_encode($raw_text_array) : '';

        $statusFinal = $nik ? 'pending_review' : 'failed';

        $stmt = mysqli_prepare($db, "UPDATE log_ocr SET nik_terdeteksi=?, skor_kepercayaan=?, status_proses=?, raw_text=? WHERE log_id=?");
        mysqli_stmt_bind_param($stmt, 'sdssi', $nik, $score, $statusFinal, $raw_text_json, $log_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        kirimRespon(['success' => true, 'status' => 'done']);

    } catch (Exception $e) {
        $stmt = mysqli_prepare($db, "UPDATE log_ocr SET status_proses='failed' WHERE log_id=?");
        mysqli_stmt_bind_param($stmt, 'i', $log_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        kirimRespon(['success' => false, 'message' => 'Mesin Timeout/Kewalahan. Silakan coba lagi.']);
    }
}
?>
