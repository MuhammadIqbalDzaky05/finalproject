<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

echo "<h2>Google API Debug</h2>";
echo "<pre>";

// 1. Cek credentials.json
echo "1. Checking credentials.json...\n";
if (file_exists(__DIR__ . '/credentials.json')) {
    $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
    echo "   ✓ File exists\n";
    echo "   Client ID: " . ($credentials['web']['client_id'] ?? 'NOT FOUND') . "\n";
    echo "   Redirect URIs: " . implode(', ', $credentials['web']['redirect_uris'] ?? []) . "\n";
} else {
    echo "   ✗ File NOT FOUND!\n";
}

// 2. Cek Client
echo "\n2. Testing Google Client...\n";
try {
    $client = new Google\Client();
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->addScope(Google\Service\Calendar::CALENDAR);
    
    $redirect_uri = 'http://localhost/auth_google_callback.php';
    $client->setRedirectUri($redirect_uri);
    
    echo "   ✓ Client created\n";
    echo "   Redirect URI: " . $redirect_uri . "\n";
    
    // Generate auth URL
    $auth_url = $client->createAuthUrl();
    echo "   Auth URL: " . $auth_url . "\n\n";
    
    echo "   <a href='" . htmlspecialchars($auth_url) . "'>Test Auth URL</a>\n";
    
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// 3. Cek token.json
echo "\n3. Checking token.json...\n";
if (file_exists(__DIR__ . '/token.json')) {
    $token = json_decode(file_get_contents(__DIR__ . '/token.json'), true);
    echo "   ✓ File exists\n";
    
    if (isset($token['access_token'])) {
        echo "   Token expiry: " . date('Y-m-d H:i:s', $token['created'] + $token['expires_in']) . "\n";
        
        $client = new Google\Client();
        $client->setAccessToken($token);
        
        if ($client->isAccessTokenExpired()) {
            echo "   Status: EXPIRED\n";
        } else {
            echo "   Status: VALID\n";
        }
    }
} else {
    echo "   ✗ File NOT FOUND (need to authenticate first)\n";
}

echo "</pre>";

echo "<h3>Quick Fixes:</h3>";
echo "<ol>";
echo "<li>Pastikan <code>credentials.json</code> valid</li>";
echo "<li>Pastikan redirect URI di Google Cloud Console sama dengan di kode</li>";
echo "<li>Clear cache browser</li>";
echo "<li>Coba mode incognito</li>";
echo "</ol>";

echo "<p><a href='my_tickets.php'>Kembali ke My Tickets</a></p>";
?>