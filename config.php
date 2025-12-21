<?php
session_start();

// ========== KONFIGURASI DATABASE ==========
$host = "localhost";
$username = "live_admin";
$password = "Mahasiswa12345!";
$database = "live_event_management";

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_user() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: index.php");
        exit();
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        $_SESSION['error'] = "❌ Akses ditolak! Hanya administrator yang bisa mengakses halaman ini.";
        header("Location: dashboard.php");
        exit();
    }
}

// Check session timeout (30 minutes) - DITAMBAHKAN DARI VERSI 2
function check_session_timeout() {
    if (is_logged_in()) {
        $inactive = 1800; // 30 minutes in seconds
        
        // Initialize login_time if not set
        if (!isset($_SESSION['login_time'])) {
            $_SESSION['login_time'] = time();
        }
        
        if (time() - $_SESSION['login_time'] > $inactive) {
            session_unset();
            session_destroy();
            header("Location: index.php?timeout=1");
            exit();
        }
        // Update login time on activity
        $_SESSION['login_time'] = time();
    }
}

// ========== MULTI-USER GOOGLE CALENDAR FUNCTIONS ==========

// Check if user has Google Calendar connected
function isGoogleCalendarConnected($user_id) {
    global $conn;
    
    // Cek jika table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_google_tokens'");
    if ($table_check->num_rows == 0) {
        return false; // Table belum dibuat
    }
    
    $stmt = $conn->prepare("SELECT google_access_token FROM user_google_tokens WHERE user_id = ?");
    if (!$stmt) {
        return false; // Error prepare statement
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Get user's Google token from database
function getUserGoogleToken($user_id) {
    global $conn;
    
    // Cek jika table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_google_tokens'");
    if ($table_check->num_rows == 0) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT google_access_token, google_refresh_token FROM user_google_tokens WHERE user_id = ?");
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Save user's Google token to database
function saveUserGoogleToken($user_id, $token) {
    global $conn;
    
    // Pastikan table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_google_tokens'");
    if ($table_check->num_rows == 0) {
        // Buat table jika belum ada
        $conn->query("CREATE TABLE user_google_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            google_access_token TEXT NOT NULL,
            google_refresh_token TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id)
        )");
    }
    
    $access_token = json_encode($token);
    $refresh_token = $token['refresh_token'] ?? '';
    
    $stmt = $conn->prepare("REPLACE INTO user_google_tokens (user_id, google_access_token, google_refresh_token) VALUES (?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("iss", $user_id, $access_token, $refresh_token);
    return $stmt->execute();
}

// Delete user's Google token (disconnect)
function disconnectUserGoogleCalendar($user_id) {
    global $conn;
    
    $table_check = $conn->query("SHOW TABLES LIKE 'user_google_tokens'");
    if ($table_check->num_rows == 0) {
        return true; // Table tidak ada, anggap sudah ter-disconnect
    }
    
    $stmt = $conn->prepare("DELETE FROM user_google_tokens WHERE user_id = ?");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// Fungsi untuk menentukan warna event berdasarkan jenis
function getEventColor($event_type) {
    $colorMap = [
        'Music Event' => '1',    // Lavender
        'Seminar' => '2',        // Sage
        'Workshop' => '3',       // Grape
        'Competition' => '4',    // Flamingo
        'Festival' => '5',       // Banana
        'Sports' => '6',         // Tangerine
        'Charity' => '7',        // Peacock
        'Conference' => '8',     // Graphite
        'Exhibition' => '9',     // Blueberry
        'default' => '11'        // Basil
    ];
    
    return $colorMap[$event_type] ?? $colorMap['default'];
}

// Validasi format datetime
function validateDateTime($datetime) {
    if (empty($datetime)) {
        return false;
    }
    
    try {
        $dt = new DateTime($datetime);
        return $dt->format('Y-m-d\TH:i:s');
    } catch (Exception $e) {
        return false;
    }
}

// MAIN FUNCTION: Tambah event ke Google Calendar user
function addEventToUserGoogleCalendar($user_id, $event_data) {
    global $conn;
    
    // 1. Cek user sudah connect Google Calendar belum
    if (!isGoogleCalendarConnected($user_id)) {
        return [
            'success' => false,
            'auth_url' => 'auth_google.php?user_id=' . $user_id,
            'message' => '🔗 <a href="auth_google.php?user_id=' . $user_id . '" style="color: #1a73e8;">Connect Google Calendar Anda dulu</a>'
        ];
    }
    
    // 2. Kalau sudah connect, BUAT EVENT BENERAN KE GOOGLE
    try {
        require_once 'vendor/autoload.php';
        
        // Get user's token from database
        $token_data = getUserGoogleToken($user_id);
        
        // PERBAIKAN: Validasi token dari database
        if (!$token_data || empty($token_data['google_access_token'])) {
            return [
                'success' => false,
                'auth_url' => 'auth_google.php?user_id=' . $user_id,
                'message' => '❌ Token tidak ditemukan, <a href="auth_google.php?user_id=' . $user_id . '">reconnect Google Calendar</a>'
            ];
        }
        
        $access_token = json_decode($token_data['google_access_token'], true);
        
        // PERBAIKAN: Cek apakah token valid dan memiliki access_token
        if (!$access_token || !isset($access_token['access_token'])) {
            // Token invalid, hapus dari database dan minta reconnect
            disconnectUserGoogleCalendar($user_id);
            return [
                'success' => false,
                'auth_url' => 'auth_google.php?user_id=' . $user_id,
                'message' => '❌ Token tidak valid, <a href="auth_google.php?user_id=' . $user_id . '">klik di sini untuk reconnect Google Calendar</a>'
            ];
        }

        // Prepare event data for Google Calendar API
        $google_event_data = [
            'summary' => $event_data['event_name'],
            'location' => $event_data['location'],
            'description' => $event_data['description'] . 
                           "\n\n💵 Harga: Rp " . number_format($event_data['price'], 0, ',', '.') . 
                           "\n🎫 Tiket: " . $event_data['available_tickets'] .
                           "\n🏢 Penyelenggara: " . $event_data['organizer'],
            'start' => [
                'dateTime' => $event_data['start_datetime'],
                'timeZone' => 'Asia/Jakarta',
            ],
            'end' => [
                'dateTime' => $event_data['end_datetime'],
                'timeZone' => 'Asia/Jakarta',
            ]
        ];
        
        // REAL GOOGLE CALENDAR API CALL
        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($google_event_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token['access_token'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            
            // Simpan log sukses
            $log = [
                'user_id' => $user_id,
                'event_name' => $event_data['event_name'],
                'google_event_id' => $result['id'],
                'google_event_link' => $result['htmlLink'],
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'REAL_GOOGLE_CALENDAR_CREATED'
            ];
            file_put_contents('google_calendar_real_log.json', json_encode($log, JSON_PRETTY_PRINT));
            
            return [
                'success' => true,
                'event_id' => $result['id'],
                'event_link' => $result['htmlLink'],
                'message' => '🎉 Event BERHASIL masuk ke Google Calendar ' . ($_SESSION['full_name'] ?? 'Anda') . '!'
            ];
        } else {
            // Google API error
            $error = json_decode($response, true);
            
            // Jika token expired, hapus dari database
            if ($http_code === 401) {
                disconnectUserGoogleCalendar($user_id);
                return [
                    'success' => false,
                    'auth_url' => 'auth_google.php?user_id=' . $user_id,
                    'message' => '❌ Token expired, <a href="auth_google.php?user_id=' . $user_id . '">klik di sini untuk reconnect Google Calendar</a>'
                ];
            }
            
            return [
                'success' => false,
                'error' => $error['error']['message'] ?? 'Google API error',
                'message' => '❌ Gagal membuat event di Google Calendar: ' . ($error['error']['message'] ?? 'Unknown error')
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'message' => '❌ Error: ' . $e->getMessage()
        ];
    }
}

// Backup function untuk kompatibilitas (single user)
function addEventToGoogleCalendar($event_data) {
    $user_id = $_SESSION['user_id'] ?? 0;
    return addEventToUserGoogleCalendar($user_id, $event_data);
}

// UPDATE EVENT DI GOOGLE CALENDAR - YANG BARU (REAL)
function updateEventInGoogleCalendar($google_event_id, $event_data) {
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!isGoogleCalendarConnected($user_id)) {
        return [
            'success' => false,
            'error' => 'User belum connect Google Calendar'
        ];
    }
    
    // ========== KODE REAL UNTUK UPDATE KE GOOGLE ==========
    try {
        require_once 'vendor/autoload.php';
        
        // Get user's token from database
        $token_data = getUserGoogleToken($user_id);
        
        if (!$token_data || empty($token_data['google_access_token'])) {
            return [
                'success' => false,
                'error' => 'Token tidak ditemukan'
            ];
        }
        
        $access_token = json_decode($token_data['google_access_token'], true);
        
        if (!$access_token || !isset($access_token['access_token'])) {
            disconnectUserGoogleCalendar($user_id);
            return [
                'success' => false,
                'error' => 'Token tidak valid'
            ];
        }

        // Prepare updated event data for Google Calendar API
        $google_event_data = [
            'summary' => $event_data['event_name'],
            'location' => $event_data['location'],
            'description' => $event_data['description'] . 
                           "\n\n💵 Harga: Rp " . number_format($event_data['price'], 0, ',', '.') . 
                           "\n🎫 Tiket: " . $event_data['available_tickets'] .
                           "\n🏢 Penyelenggara: " . $event_data['organizer'],
            'start' => [
                'dateTime' => $event_data['start_datetime'],
                'timeZone' => 'Asia/Jakarta',
            ],
            'end' => [
                'dateTime' => $event_data['end_datetime'],
                'timeZone' => 'Asia/Jakarta',
            ]
        ];
        
        // REAL GOOGLE CALENDAR API CALL untuk UPDATE
        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $google_event_id;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); // METHOD PUT untuk update
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($google_event_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token['access_token'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            
            // Simpan log sukses update
            $log = [
                'user_id' => $user_id,
                'event_name' => $event_data['event_name'],
                'google_event_id' => $result['id'],
                'action' => 'UPDATED',
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'REAL_GOOGLE_CALENDAR_UPDATED'
            ];
            file_put_contents('google_calendar_real_log.json', json_encode($log, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            
            return [
                'success' => true,
                'message' => '✅ Event berhasil diupdate di Google Calendar!'
            ];
        } else {
            // Google API error
            $error = json_decode($response, true);
            
            if ($http_code === 401) {
                disconnectUserGoogleCalendar($user_id);
                return [
                    'success' => false,
                    'error' => 'Token expired, silakan reconnect Google Calendar'
                ];
            }
            
            return [
                'success' => false,
                'error' => $error['error']['message'] ?? 'Google API error'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// HAPUS EVENT DARI GOOGLE CALENDAR - YANG BARU (REAL)
function deleteEventFromGoogleCalendar($google_event_id) {
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!isGoogleCalendarConnected($user_id)) {
        return [
            'success' => false,
            'error' => 'User belum connect Google Calendar'
        ];
    }
    
    // ========== KODE REAL UNTUK DELETE DARI GOOGLE ==========
    try {
        require_once 'vendor/autoload.php';
        
        // Get user's token from database
        $token_data = getUserGoogleToken($user_id);
        
        if (!$token_data || empty($token_data['google_access_token'])) {
            return [
                'success' => false,
                'error' => 'Token tidak ditemukan'
            ];
        }
        
        $access_token = json_decode($token_data['google_access_token'], true);
        
        if (!$access_token || !isset($access_token['access_token'])) {
            disconnectUserGoogleCalendar($user_id);
            return [
                'success' => false,
                'error' => 'Token tidak valid'
            ];
        }

        // REAL GOOGLE CALENDAR API CALL untuk DELETE
        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $google_event_id;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE'); // METHOD DELETE
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token['access_token']
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 204) { // 204 No Content for successful delete
            // Simpan log sukses delete
            $log = [
                'user_id' => $user_id,
                'google_event_id' => $google_event_id,
                'action' => 'DELETED',
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'REAL_GOOGLE_CALENDAR_DELETED'
            ];
            file_put_contents('google_calendar_real_log.json', json_encode($log, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            
            return [
                'success' => true,
                'message' => '✅ Event berhasil dihapus dari Google Calendar!'
            ];
        } else {
            // Google API error
            $error = json_decode($response, true);
            
            if ($http_code === 401) {
                disconnectUserGoogleCalendar($user_id);
                return [
                    'success' => false,
                    'error' => 'Token expired, silakan reconnect Google Calendar'
                ];
            }
            
            // Jika event tidak ditemukan di Google Calendar, anggap success
            if ($http_code === 404) {
                return [
                    'success' => true,
                    'message' => 'Event tidak ditemukan di Google Calendar (mungkin sudah dihapus)'
                ];
            }
            
            return [
                'success' => false,
                'error' => $error['error']['message'] ?? 'Google API error'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Cek koneksi Google Calendar
function checkGoogleCalendarConnection() {
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!isGoogleCalendarConnected($user_id)) {
        return [
            'connected' => false,
            'message' => 'Belum terhubung dengan Google Calendar'
        ];
    }
    
    return [
        'connected' => true,
        'message' => 'Terhubung dengan Google Calendar'
    ];
}
?>