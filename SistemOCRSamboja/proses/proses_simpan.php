<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if (!isset($_GET['log_id'])) {
    die('log_id tidak ditemukan');
}

$log_id = $_GET['log_id'];

$sql = "UPDATE log_ocr
        SET nik_final = nik_terdeteksi,
            status_proses = 'finalized'
        WHERE log_id = ?
          AND status_proses = 'pending_review'";

$stmt = mysqli_prepare($db, $sql);

if (!$stmt) {
    die('Database error');
}

mysqli_stmt_bind_param($stmt, "i", $log_id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header('Location: ../upload.php');
    exit;
}

$error = mysqli_error($db);
mysqli_stmt_close($stmt);
die("Gagal menyimpan data: $error");
?>
