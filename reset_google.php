<?php
session_start();

// Hapus semua file token dan session
if (file_exists(__DIR__ . '/token.json')) {
    unlink(__DIR__ . '/token.json');
}

unset($_SESSION['google_access_token']);
unset($_SESSION['redirect_uri']);

$_SESSION['success'] = "✅ Google connection reset successfully!";
header('Location: my_tickets.php');
exit;
?>