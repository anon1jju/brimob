<?php
session_start();

// Hapus semua data session (nama, role, status login)
$_SESSION = [];

// Hancurkan session dari server
session_destroy();

// Kembalikan ke halaman login
header("Location: signin.php");
exit;
?>