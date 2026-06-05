<?php
session_start();
require_once 'config.php';
require_once 'csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

csrf_verify();

$log_id  = isset($_POST['log_id']) ? (int) $_POST['log_id'] : 0;
$user_id = (int) $_SESSION['user_id'];

if ($log_id <= 0) {
    header('Location: ../riwayat.php?msg=gagal');
    exit;
}

// Finalisasi OCR: hanya boleh menyimpan data milik operator yang sedang login
$sql  = "UPDATE log_ocr SET nik_final = nik_terdeteksi, status_proses = 'finalized' WHERE log_id = ? AND id_staf = ? AND status_proses = 'pending_review'";
$stmt = mysqli_prepare($db, $sql);

if (!$stmt) {
    header('Location: ../riwayat.php?msg=gagal');
    exit;
}

mysqli_stmt_bind_param($stmt, 'ii', $log_id, $user_id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header('Location: ../upload.php');
    exit;
}

mysqli_stmt_close($stmt);
header('Location: ../riwayat.php?msg=gagal');
exit;
?>
