<?php
/**
 * Handler navigasi internal — menerima POST dengan log_id,
 * simpan ke session, lalu redirect ke halaman tujuan.
 * Ini menghilangkan ID dari URL tanpa mengubah logika aplikasi.
 */
session_start();
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
$tujuan  = $_POST['tujuan'] ?? '';

$halaman_valid = ['koreksi', 'hasilriwayat'];

if ($log_id <= 0 || !in_array($tujuan, $halaman_valid, true)) {
    header('Location: ../riwayat.php');
    exit;
}

// Simpan log_id ke session agar halaman tujuan bisa membacanya tanpa URL param
$_SESSION['active_log_id'] = $log_id;

header('Location: ../' . $tujuan . '.php');
exit;
?>
