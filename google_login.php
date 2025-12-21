<?php
// google_login.php
session_start();
include 'config.php';

// Jika sudah login, redirect ke dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit();
}

require_once 'vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig('credentials.json');
$client->addScope(['email', 'profile']);
$client->setAccessType('offline');
$client->setPrompt('consent');

// Redirect URI
$redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
// var_dump($redirect_uri); die;
$client->setRedirectUri($redirect_uri);

if (isset($_GET['code'])) {
    try {
        // Tukar code dengan token
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);
        
        // Dapatkan user info
        $oauth2 = new Google_Service_Oauth2($client);
        $user_info = $oauth2->userinfo->get();
        
        $google_id = $user_info->getId();
        $email = $user_info->getEmail();
        $name = $user_info->getName();
        $picture = $user_info->getPicture();
        
        // Cek user di database
        $query = "SELECT * FROM users WHERE email = ? OR google_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ss", $email, $google_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($result)) {
            // User sudah ada, update google_id jika kosong
            if (empty($user['google_id'])) {
                $update_query = "UPDATE users SET google_id = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "si", $google_id, $user['id']);
                mysqli_stmt_execute($update_stmt);
            }
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'] ?? $name;
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_admin'] = ($user['role'] == 'admin') ? 1 : 0;
            $_SESSION['login_time'] = time();
            $_SESSION['login_method'] = 'google';
            
            // ===== TAMBAHKAN FLAG UNTUK NOTIFIKASI =====
            $_SESSION['login_success'] = true;
            $_SESSION['login_timestamp'] = time();
            $_SESSION['google_login'] = true;
            // ===========================================
            
            header('Location: dashboard.php');
            exit();
            
        } else {
            // Buat user baru
            $username = strtolower(explode('@', $email)[0]);
            
            // Cek apakah username sudah ada, jika ya tambahkan angka
            $counter = 1;
            $original_username = $username;
            while (true) {
                $check_query = "SELECT id FROM users WHERE username = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, "s", $username);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                
                if (mysqli_num_rows($check_result) == 0) {
                    break;
                }
                $username = $original_username . $counter;
                $counter++;
            }
            
            // Generate random password
            $random_password = bin2hex(random_bytes(8));
            $password_hash = password_hash($random_password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (username, email, password, full_name, google_id, role, is_admin, created_at) 
                     VALUES (?, ?, ?, ?, ?, 'user', 0, NOW())";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssss", $username, $email, $password_hash, $name, $google_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $user_id = mysqli_insert_id($conn);
                
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['full_name'] = $name;
                $_SESSION['role'] = 'user';
                $_SESSION['is_admin'] = 0;
                $_SESSION['login_time'] = time();
                $_SESSION['login_method'] = 'google';
                
                // ===== TAMBAHKAN FLAG UNTUK NOTIFIKASI =====
                $_SESSION['login_success'] = true;
                $_SESSION['login_timestamp'] = time();
                $_SESSION['google_login'] = true;
                // ===========================================
                
                header('Location: dashboard.php');
                exit();
            } else {
                $_SESSION['error'] = "Gagal membuat akun baru";
                header('Location: login.php');
                exit();
            }
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Login dengan Google gagal: " . $e->getMessage();
        header('Location: login.php');
        exit();
    }
} else {
    // Redirect ke Google OAuth
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit();
}
?>