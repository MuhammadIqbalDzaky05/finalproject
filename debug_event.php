<?php
// debug_event.php
session_start();
require_once 'config.php';

echo "<h2>🐛 Debug Event Creation</h2>";

// Test data event
$test_event = [
    'event_name' => 'TEST DEBUG EVENT - ' . date('Y-m-d H:i:s'),
    'location' => 'Test Location Debug',
    'description' => 'This is a debug test event',
    'price' => 0,
    'available_tickets' => 5,
    'organizer' => 'Debug Organizer',
    'event_type' => 'Workshop',
    'start_datetime' => date('Y-m-d\T19:00:00', strtotime('+1 day')),
    'end_datetime' => date('Y-m-d\T21:00:00', strtotime('+1 day'))
];

echo "<h3>1. Test Data:</h3>";
echo "<pre>" . print_r($test_event, true) . "</pre>";

echo "<h3>2. Calling addEventToGoogleCalendar...</h3>";
$result = addEventToGoogleCalendar($test_event);

echo "<h3>3. Result:</h3>";
echo "<pre>" . print_r($result, true) . "</pre>";

if ($result['success']) {
    echo "<h3 style='color: green'>✅ Event Creation SUCCESS</h3>";
    echo "<p>Event ID: " . $result['event_id'] . "</p>";
    echo "<p><a href='" . $result['event_link'] . "' target='_blank'>🔗 View in Google Calendar</a></p>";
    
    // Test membuka link
    echo "<p><button onclick=\"window.open('" . $result['event_link'] . "')\">Open Calendar Link</button></p>";
} else {
    echo "<h3 style='color: red'>❌ Event Creation FAILED</h3>";
    echo "<p>Error: " . $result['error'] . "</p>";
}

echo "<h3>4. Check Token:</h3>";
if (file_exists('token.json')) {
    $token = json_decode(file_get_contents('token.json'), true);
    echo "Token exists<br>";
    echo "Access Token: " . substr($token['access_token'], 0, 20) . "...<br>";
    echo "Expires: " . date('Y-m-d H:i:s', $token['created'] + $token['expires_in']) . "<br>";
} else {
    echo "❌ Token not found";
}

echo "<h3>5. Check Log Files:</h3>";
$log_files = [
    'google_calendar_log.json',
    'google_calendar_errors.json',
    'google_calendar_simulation.json'
];

foreach ($log_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file: EXISTS<br>";
        $content = file_get_contents($file);
        echo "<pre>" . $content . "</pre>";
    } else {
        echo "❌ $file: NOT FOUND<br>";
    }
}
?>