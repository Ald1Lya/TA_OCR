<?php
session_start();

// Hancurin semua session
$_SESSION = [];
session_destroy();

// Redirect ke halaman login
header('Location: ../index.php');
exit;
?>
