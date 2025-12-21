<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

// Cek jika user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Google Calendar - User</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
        a { color: #4285F4; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; padding: 10px 20px; background: #4285F4; color: white; border-radius: 5px; margin: 5px; }
    </style>
</head>
<body>
    <h2>🔧 Debug Google Calendar API - User Side</h2>
    <p>User: <strong>" . htmlspecialchars($_SESSION['full_name'] ?? 'Unknown') . "</strong></p>
    <p>User ID: <strong>" . ($_SESSION['user_id'] ?? 'Unknown') . "</strong></p>
    
    <div style='margin: 20px 0;'>
        <a href='my_tickets.php' class='btn'>⬅ Kembali ke My Tickets</a>
        <a href='reset_user_google.php' class='btn' style='background: #EA4335;'>🔄 Reset Connection</a>
    </div>
    
    <hr>
    <pre>";
    
// 1. Cek credentials.json
echo "1. 📁 Checking credentials.json...\n";
if (file_exists(__DIR__ . '/credentials.json')) {
    $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
    echo "   ✅ File exists\n";
    
    if (isset($credentials['web'])) {
        echo "   ✅ Format: web application\n";
        echo "   📝 Client ID: " . ($credentials['web']['client_id'] ?? 'NOT FOUND') . "\n";
        
        if (isset($credentials['web']['redirect_uris'])) {
            echo "   🔗 Redirect URIs:\n";
            foreach ($credentials['web']['redirect_uris'] as $uri) {
                echo "      - " . $uri . "\n";
            }
        }
    } else {
        echo "   ❌ Invalid format (should be 'web')\n";
    }
} else {
    echo "   ❌ File NOT FOUND!\n";
    echo "   💡 Buat credentials.json dari Google Cloud Console\n";
}

// 2. Cek token.json
echo "\n2. 🔑 Checking token.json...\n";
if (file_exists(__DIR__ . '/token.json')) {
    $token = json_decode(file_get_contents(__DIR__ . '/token.json'), true);
    echo "   ✅ File exists\n";
    
    if (isset($token['access_token'])) {
        echo "   ✅ Access token found\n";
        
        // Cek expiry
        if (isset($token['created']) && isset($token['expires_in'])) {
            $expiry_time = $token['created'] + $token['expires_in'];
            $current_time = time();
            $remaining = $expiry_time - $current_time;
            
            echo "   ⏰ Token created: " . date('Y-m-d H:i:s', $token['created']) . "\n";
            echo "   ⏰ Token expires: " . date('Y-m-d H:i:s', $expiry_time) . "\n";
            echo "   ⏳ Remaining: " . floor($remaining / 3600) . "h " . floor(($remaining % 3600) / 60) . "m\n";
            
            if ($remaining > 0) {
                echo "   🟢 Token masih valid\n";
            } else {
                echo "   🔴 Token EXPIRED\n";
            }
        }
        
        // Cek scopes
        if (isset($token['scope'])) {
            echo "   📋 Scopes: " . $token['scope'] . "\n";
        }
    } else {
        echo "   ❌ No access token in file\n";
    }
} else {
    echo "   ❌ File NOT FOUND (need to authenticate first)\n";
}

// 3. Test Google Client
echo "\n3. 🤖 Testing Google Client...\n";
try {
    $client = new Google\Client();
    
    // Set credentials
    if (file_exists(__DIR__ . '/credentials.json')) {
        $client->setAuthConfig(__DIR__ . '/credentials.json');
        echo "   ✅ Credentials loaded\n";
    } else {
        echo "   ❌ Cannot load credentials\n";
    }
    
    // Set scope
    $client->addScope(Google\Service\Calendar::CALENDAR);
    echo "   ✅ Scope set: CALENDAR\n";
    
    // Set redirect URI
    $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/auth_google_callback.php';
    $client->setRedirectUri($redirect_uri);
    echo "   ✅ Redirect URI: " . $redirect_uri . "\n";
    
    // Cek token
    if (file_exists(__DIR__ . '/token.json')) {
        $accessToken = json_decode(file_get_contents(__DIR__ . '/token.json'), true);
        $client->setAccessToken($accessToken);
        
        if ($client->isAccessTokenExpired()) {
            echo "   🔴 Access token expired\n";
            
            if ($client->getRefreshToken()) {
                echo "   🔄 Refresh token available, trying to refresh...\n";
                try {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    file_put_contents(__DIR__ . '/token.json', json_encode($newToken));
                    echo "   ✅ Token refreshed successfully\n";
                } catch (Exception $e) {
                    echo "   ❌ Failed to refresh: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ❌ No refresh token available\n";
            }
        } else {
            echo "   🟢 Access token valid\n";
        }
    }
    
    // Generate auth URL
    $auth_url = $client->createAuthUrl();
    echo "\n   🔗 Auth URL generated:\n";
    echo "   " . $auth_url . "\n";
    
    // Test Calendar Service
    if (!$client->isAccessTokenExpired()) {
        echo "\n4. 📅 Testing Calendar Service...\n";
        try {
            $service = new Google\Service\Calendar($client);
            
            // Get primary calendar
            $calendar = $service->calendars->get('primary');
            echo "   ✅ Connected to Calendar: " . $calendar->getSummary() . "\n";
            
            // Test list events (limit 5)
            $events = $service->events->listEvents('primary', ['maxResults' => 3]);
            echo "   ✅ Calendar events accessible\n";
            echo "   📊 Total events: " . $events->getItems() ? count($events->getItems()) : 0 . "\n";
            
        } catch (Exception $e) {
            echo "   ❌ Calendar error: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ Client error: " . $e->getMessage() . "\n";
    echo "   💡 Error detail: " . $e->getTraceAsString() . "\n";
}

echo "\n5. 🧪 Quick Tests:\n";

// Test 1: File permissions
echo "   📂 File permissions:\n";
echo "   - credentials.json: " . (is_readable(__DIR__ . '/credentials.json') ? "✅ Readable" : "❌ Not readable") . "\n";
echo "   - token.json: " . (is_readable(__DIR__ . '/token.json') ? "✅ Readable" : "❌ Not readable") . "\n";
echo "   - token.json: " . (is_writable(__DIR__ . '/token.json') ? "✅ Writable" : "❌ Not writable") . "\n";

// Test 2: Session
echo "\n   💾 Session data:\n";
echo "   - google_access_token: " . (isset($_SESSION['google_access_token']) ? "✅ Set" : "❌ Not set") . "\n";

// Test 3: Vendor
echo "\n   📦 Vendor check:\n";
echo "   - vendor/autoload.php: " . (file_exists(__DIR__ . '/vendor/autoload.php') ? "✅ Exists" : "❌ Not found") . "\n";

echo "</pre>";

// Display auth link
echo "<h3>🔗 Test Authentication:</h3>";
echo "<p><a href='" . htmlspecialchars($auth_url ?? '#') . "' class='btn' target='_blank'>🚀 Test Google Login</a></p>";

echo "<h3>🔧 Common Fixes:</h3>";
echo "<ol>";
echo "<li>Pastikan <strong>credentials.json</strong> format benar (web application)</li>";
echo "<li>Pastikan <strong>Redirect URI</strong> di Google Cloud Console sama dengan di kode</li>";
echo "<li>Coba <a href='reset_user_google.php'>Reset Connection</a> lalu login ulang</li>";
echo "<li>Clear cache browser / coba mode incognito</li>";
echo "<li>Pastikan Google Calendar API diaktifkan di Google Cloud Console</li>";
echo "</ol>";

echo "<p><a href='my_tickets.php'>⬅ Kembali ke My Tickets</a></p>";
echo "</body></html>";