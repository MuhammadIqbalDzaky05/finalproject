<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

$client = new Google\Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->addScope(Google\Service\Calendar::CALENDAR);
$client->setAccessType('offline');

// HARUS SAMA PERSIS dengan auth_google.php
$redirect_uri = 'http://127.0.0.1/auth_google_callback.php';
$client->setRedirectUri($redirect_uri);

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        file_put_contents(__DIR__ . '/token.json', json_encode($token));
        $_SESSION['success'] = "✅ Connected to Google Calendar!";
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ Error: " . $e->getMessage();
    }
    header('Location: my_tickets.php');
    exit;
}

if (isset($_GET['error'])) {
    $_SESSION['error'] = "Google error: " . $_GET['error'];
    header('Location: my_tickets.php');
    exit;
}
?>