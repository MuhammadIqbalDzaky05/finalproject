<?php
// File: reset_password_direct.php - VERSI PERBAIKAN
include 'config.php';

header('Content-Type: text/plain');

// Debug logging
error_log("Reset password request received");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    
    error_log("Username: $username, Password length: " . strlen($new_password));
    
    if (empty($username) || empty($new_password)) {
        echo "error|Username dan password baru harus diisi!";
        exit();
    }
    
    if (strlen($new_password) < 6) {
        echo "error|Password minimal 6 karakter!";
        exit();
    }
    
    // Cek apakah username ada di database
    $query = "SELECT * FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        error_log("User found: " . $user['username']);
        
        // Hash password baru DENGAN PASSWORD_DEFAULT
        // Ini akan menggunakan algoritma terbaru (bcrypt)
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        error_log("New password hash: $hashed_password");
        
        // Update password di database
        $update_query = "UPDATE users SET password = ? WHERE username = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $username);
        
        if (mysqli_stmt_execute($update_stmt)) {
            error_log("Password updated successfully for user: $username");
            
            // Verifikasi hash yang disimpan
            $verify_query = "SELECT password FROM users WHERE username = ?";
            $verify_stmt = mysqli_prepare($conn, $verify_query);
            mysqli_stmt_bind_param($verify_stmt, "s", $username);
            mysqli_stmt_execute($verify_stmt);
            $verify_result = mysqli_stmt_get_result($verify_stmt);
            $updated_user = mysqli_fetch_assoc($verify_result);
            
            // Verifikasi hash
            if (password_verify($new_password, $updated_user['password'])) {
                error_log("Hash verification PASSED");
                echo "success|Password berhasil direset! Silakan login dengan password baru.";
            } else {
                error_log("Hash verification FAILED");
                echo "error|Error: Hash tidak valid setelah update!";
            }
        } else {
            error_log("Failed to update password: " . mysqli_error($conn));
            echo "error|Gagal mereset password. Error: " . mysqli_error($conn);
        }
    } else {
        error_log("Username not found: $username");
        echo "error|Username tidak ditemukan!";
    }
} else {
    echo "error|Metode request tidak valid!";
}

mysqli_close($conn);
?>