<?php
session_start();
// Redirect ke index.php (yang sekarang menjadi halaman login)
header('Location: index.php');
exit();
?>