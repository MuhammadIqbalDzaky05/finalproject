[file name]: auth_google_user.php
[file content begin]
<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
include 'config.php';

$user_id = $_SESSION['user_id'];

$client = new Google\Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->addScope(Google\Service\Calendar::CALENDAR);
$client->setAccessType('offline');
$client->setPrompt('consent');
$client->setRedirectUri('http://localhost/livefest/auth_google_user.php'); // Sesuaikan dengan URL Anda

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);
        
        // Simpan token ke database (users table)
        $update_query = "UPDATE users SET google_token = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", json_encode($token), $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "✅ Berhasil connect ke Google Calendar!";
        } else {
            $_SESSION['error'] = "❌ Gagal menyimpan token Google Calendar.";
        }
        
        mysqli_stmt_close($stmt);
        
        header('Location: my_tickets.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ Error autentikasi: " . $e->getMessage();
        header('Location: my_tickets.php');
        exit;
    }
} else {
    // Generate auth URL
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
}
?>
[file content end]