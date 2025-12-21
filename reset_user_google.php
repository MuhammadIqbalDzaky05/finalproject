<?php
session_start();

// Hanya untuk user yang login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Hapus token file
if (file_exists(__DIR__ . '/token.json')) {
    if (unlink(__DIR__ . '/token.json')) {
        $_SESSION['success'] = "✅ Google connection reset successfully!";
    } else {
        $_SESSION['error'] = "❌ Gagal menghapus token file";
    }
}

// Hapus session Google
unset($_SESSION['google_access_token']);

// Redirect kembali
header('Location: my_tickets.php');
exit;
?>