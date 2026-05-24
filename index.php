<?php
session_start();
// Jika sudah login, lempar ke Dashboard. Jika belum, lempar ke Sign In.sds
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: dashboard.php");
} else {
    header("Location: signin.php");
}
exit;
?>