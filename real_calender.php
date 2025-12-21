<?php
echo "=== TEST SEDERHANA ===\n";

// Test 1: Cek file
echo "1. Cek credentials.json: ";
if (file_exists('credentials.json')) {
    echo "✅ ADA\n";
} else {
    echo "❌ TIDAK ADA\n";
}

// Test 2: Cek vendor
echo "2. Cek vendor/autoload.php: ";
if (file_exists('vendor/autoload.php')) {
    echo "✅ ADA\n";
} else {
    echo "❌ TIDAK ADA\n";
}

// Test 3: Cek Google Client
echo "3. Cek Google Client: ";
if (file_exists('vendor/google/apiclient/src/Google/Client.php')) {
    echo "✅ ADA\n";
} else {
    echo "❌ TIDAK ADA\n";
}

// Test 4: Load autoload
echo "4. Load autoload... ";
require_once 'vendor/autoload.php';
echo "✅ BERHASIL\n";

// Test 5: Buat Google Client
echo "5. Buat Google Client... ";
try {
    $client = new Google_Client();
    echo "✅ BERHASIL\n";
} catch (Exception $e) {
    echo "❌ GAGAL: " . $e->getMessage() . "\n";
}

echo "=== TEST SELESAI ===\n";
?>