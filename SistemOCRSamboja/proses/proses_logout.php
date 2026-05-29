<?php
session_start();
require_once 'csrf.php';

csrf_verify();

// Admin: matikan server OCR berdasarkan PID yang tersimpan
if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') {
    $pid_file = __DIR__ . '/../PYTHON_OCR/ocr_server.pid';
    if (file_exists($pid_file)) {
        $pid = (int) file_get_contents($pid_file);
        if ($pid > 0) {
            shell_exec("taskkill /F /PID " . $pid);
        }
        @unlink($pid_file);
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

header('Location: ../index.php');
exit;
?>
