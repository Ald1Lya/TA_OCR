<?php
ignore_user_abort(true);
set_time_limit(0);
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

session_start();
header('Content-Type: application/json');
require_once 'config.php';

/* helper response */
function kirimRespon($data) {
    ob_clean();
    echo json_encode($data);
    ob_end_flush();
    exit;
}

/* cek sesi */
if (!isset($_SESSION['user_id'])) {
    kirimRespon(['success' => false, 'message' => 'Sesi habis']);
}

/* upload file */
if (isset($_FILES['ktp_files'])) {

    $files     = $_FILES['ktp_files'];
    $id_staf   = $_SESSION['user_id'];
    $uploadDir = '../public/images/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $i = count($files['name']) - 1;

    $namaAsli = basename($files['name'][$i]);
    $tmpPath  = $files['tmp_name'][$i];
    $ext      = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));
    $namaUnik = uniqid('ktp_', true) . '.' . $ext;
    $target   = $uploadDir . $namaUnik;

    if (!move_uploaded_file($tmpPath, $target)) {
        kirimRespon(['success' => false, 'message' => 'Upload gagal']);
    }

    $sql = "INSERT INTO log_ocr 
            (id_staf, nama_file_asli, status_proses, waktu_upload, nama_file_sistem)
            VALUES (?, ?, 'pending', NOW(), ?)";

    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) kirimRespon(['success' => false]);

    mysqli_stmt_bind_param($stmt, "iss", $id_staf, $namaAsli, $namaUnik);
    mysqli_stmt_execute($stmt);

    $_SESSION['current_log_id'] = mysqli_insert_id($db);
    mysqli_stmt_close($stmt);

    kirimRespon(['success' => true, 'mode' => 'upload']);
}

/* cek status ocr */
if (isset($_POST['action']) && $_POST['action'] === 'check_status') {

    if (!isset($_SESSION['current_log_id'])) {
        kirimRespon(['success' => false]);
    }

    $log_id = $_SESSION['current_log_id'];

    $sql  = "SELECT status_proses, nik_terdeteksi FROM log_ocr WHERE log_id = ?";
    $stmt = mysqli_prepare($db, $sql);

    mysqli_stmt_bind_param($stmt, "i", $log_id);
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

    if (in_array($data['status_proses'], ['failed', 'error_system'])) {
        kirimRespon(['success' => true, 'status' => 'failed_but_continue']);
    }

    kirimRespon(['success' => true, 'status' => 'processing']);
}

/* trigger ocr */
if (isset($_POST['action']) && $_POST['action'] === 'trigger_ocr') {

    if (!isset($_SESSION['current_log_id'])) {
        kirimRespon(['success' => false]);
    }

    $log_id = $_SESSION['current_log_id'];
    session_write_close();

    $sql = "SELECT nama_file_sistem, nama_file_asli FROM log_ocr WHERE log_id = ?";
    $stmt = mysqli_prepare($db, $sql);

    mysqli_stmt_bind_param($stmt, "i", $log_id);
    mysqli_stmt_execute($stmt);

    $res  = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$data) exit;

    $pathImg = realpath('../public/images/' . $data['nama_file_sistem']);

    $stmt = mysqli_prepare($db, "UPDATE log_ocr SET status_proses='processing' WHERE log_id=?");
    mysqli_stmt_bind_param($stmt, "i", $log_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    try {
        $cfile = new CURLFile($pathImg, mime_content_type($pathImg), $data['nama_file_asli']);

        $ch = curl_init('http://127.0.0.1:5000/ocr');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['file' => $cfile],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 600
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('OCR error');
        }

        $json  = json_decode($response, true);
        $nik   = $json['nik'] ?? null;
        $score = $json['score'] ?? 0;

        $statusFinal = $nik ? 'pending_review' : 'failed';

        $sql = "UPDATE log_ocr 
                SET nik_terdeteksi=?, skor_kepercayaan=?, status_proses=?
                WHERE log_id=?";

        $stmt = mysqli_prepare($db, $sql);
        mysqli_stmt_bind_param($stmt, "sdsi", $nik, $score, $statusFinal, $log_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    } catch (Exception $e) {
        $stmt = mysqli_prepare($db, "UPDATE log_ocr SET status_proses='failed' WHERE log_id=?");
        mysqli_stmt_bind_param($stmt, "i", $log_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>
