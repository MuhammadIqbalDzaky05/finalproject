
<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
include 'config.php';

// Dapatkan user_id dari GET parameter atau session
$user_id = $_GET['user_id'] ?? $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    $_SESSION['error'] = "User ID tidak valid!";
    header('Location: dashboard.php');
    exit;
}

$client = new Google\Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->addScope(Google\Service\Calendar::CALENDAR);
$client->setAccessType('offline');
$client->setPrompt('consent');

// Redirect URI - sesuaikan dengan file Anda
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$redirect_uri = $base_url . dirname($_SERVER['PHP_SELF']) . 'auth_google.php';
$client->setRedirectUri($redirect_uri);

// Tambahkan state parameter untuk menyimpan user_id
$client->setState($user_id);

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        // Get state parameter untuk mendapatkan user_id
        $user_id = $_GET['state'] ?? $user_id;
        
        // Simpan token ke database untuk user ini
        if (saveUserGoogleToken($user_id, $token)) {
            $_SESSION['success'] = "✅ Berhasil connect ke Google Calendar!";
            
            // Redirect ke halaman yang sesuai
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                header('Location: add_event.php');
            } else {
                header('Location: my_tickets.php');
            }
            exit;
        } else {
            $_SESSION['error'] = "❌ Gagal menyimpan token Google Calendar.";
            header('Location: dashboard.php');
            exit;
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ Error autentikasi: " . $e->getMessage();
        header('Location: dashboard.php');
        exit;
    }
} else {
    // Generate auth URL dengan state parameter
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
}
?>