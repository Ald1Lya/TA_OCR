<?php
session_start();
if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') {

    shell_exec("taskkill /F /IM python.exe");
}

$_SESSION = [];
session_destroy();

header('Location: ../index.php');
exit;
?>
