<?php

ini_set('dispaly_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

include 'config.php';

// Jika sudah login, redirect ke dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit();
}

// NOTIFIKASI LOGIN BERHASIL
if (isset($_SESSION['login_success']) && $_SESSION['login_success']) {
    $login_message = "Login berhasil! Selamat datang, " . htmlspecialchars($_SESSION['full_name']) . "!";
    unset($_SESSION['login_success']);
}

$error_message = "";
$success_message = "";

// PROSES LOGIN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = clean_input($_POST['username']);
    $password = $_POST['password']; // JANGAN clean_input untuk password!
    
    // Debug
    error_log("Login attempt: username=$username");

    if (empty($username) || empty($password)) {
        $error_message = "Username dan password harus diisi!";
    } else {
        // Cek user di database
        $query = "SELECT * FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($result)) {
            error_log("User found, checking password...");
            error_log("DB Hash: " . $user['password']);
            error_log("Input password: $password");
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Login berhasil
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // SET SESSION UNTUK NOTIFIKASI LOGIN BERHASIL
                $_SESSION['login_success'] = true;

                error_log("Login SUCCESS for user: $username");
                
                // Redirect ke dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Password salah!";
                error_log("Login FAILED: Password mismatch for user: $username");
            }
        } else {
            $error_message = "Username tidak ditemukan!";
            error_log("Login FAILED: User not found: $username");
        }
    }
}

// PROSES LUPA PASSWORD - REQUEST RESET
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot_password'])) {
    $email = clean_input($_POST['email']);
    
    if (empty($email)) {
        $error_message = "Email harus diisi!";
    } else {
        // Cek apakah email ada di database
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($result)) {
            // Generate token reset password
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Simpan token ke database
            $query = "INSERT INTO password_resets (email, token, expiry) VALUES (?, ?, ?) 
                     ON DUPLICATE KEY UPDATE token = ?, expiry = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssss", $email, $token, $expiry, $token, $expiry);
            
            if (mysqli_stmt_execute($stmt)) {
                // Kirim email reset password (simulasi)
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                
                // Simpan di session untuk demo (biasanya dikirim via email)
                $_SESSION['reset_token'] = $token;
                $_SESSION['reset_email'] = $email;
                
                $success_message = "Link reset password telah dikirim ke email: " . htmlspecialchars($email) . 
                                 "<br><small>Link demo: <a href='reset_password.php?token=$token'>$reset_link</a></small>";
            } else {
                $error_message = "Gagal membuat token reset password!";
            }
        } else {
            $error_message = "Email tidak ditemukan!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Manajemen Event Mahasiswa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: gradientShift 10s ease infinite alternate;
            background-size: 200% 200%;
            position: relative;
            overflow-x: hidden;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Floating circles background */
        .floating-circles {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            overflow: hidden;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 15s infinite linear;
        }

        .circle:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-duration: 20s;
        }

        .circle:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            left: 80%;
            animation-duration: 25s;
            animation-delay: 2s;
        }

        .circle:nth-child(3) {
            width: 60px;
            height: 60px;
            top: 80%;
            left: 20%;
            animation-duration: 18s;
            animation-delay: 1s;
        }

        .circle:nth-child(4) {
            width: 100px;
            height: 100px;
            top: 20%;
            left: 75%;
            animation-duration: 22s;
            animation-delay: 3s;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.5;
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
                opacity: 0.8;
            }
            100% {
                transform: translateY(0) rotate(360deg);
                opacity: 0.5;
            }
        }

        .login-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            min-height: 500px;
            animation: slideUp 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            opacity: 0;
            transform: translateY(50px);
        }

        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-left {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4a6ee0 0%, #6a11cb 100%);
            overflow: hidden;
            position: relative;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
            animation: shine 3s infinite alternate;
        }

        @keyframes shine {
            from { transform: translateX(-100%); }
            to { transform: translateX(100%); }
        }

        .login-left img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.8s ease;
        }

        .login-left:hover img {
            transform: scale(1.05);
        }

        .login-left .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 30px;
            color: white;
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .login-left:hover .overlay {
            opacity: 1;
        }

        .overlay h3 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            transform: translateY(20px);
            transition: transform 0.5s ease;
        }

        .login-left:hover .overlay h3 {
            transform: translateY(0);
        }

        .overlay p {
            font-size: 1rem;
            opacity: 0.9;
            transform: translateY(20px);
            transition: transform 0.5s ease 0.1s;
        }

        .login-left:hover .overlay p {
            transform: translateY(0);
        }

        .login-right {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            color: #333;
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 700;
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }

        .login-header h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(to right, #007bff, #6a11cb);
            border-radius: 3px;
        }

        .login-header p {
            color: #666;
            font-size: 1rem;
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 25px;
            animation: fadeIn 0.8s ease forwards;
            animation-delay: calc(var(--order) * 0.1s);
            opacity: 0;
            transform: translateY(20px);
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
            transition: color 0.3s ease;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .input-with-icon input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafafa;
            font-family: 'Segoe UI', sans-serif;
        }

        .input-with-icon input:focus {
            outline: none;
            border-color: #007bff;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .input-with-icon input:focus + i {
            color: #007bff;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(to right, #007bff, #0056b3);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease 0.4s forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        .btn-login:hover {
            background: linear-gradient(to right, #0056b3, #004494);
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(0, 123, 255, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* ========== TOMBOL LUPA PASSWORD ========== */
        .forgot-password-link {
            text-align: right;
            margin-bottom: 20px;
            animation: fadeIn 0.8s ease 0.3s forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        .forgot-password-link a {
            color: #007bff;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .forgot-password-link a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        /* ========== MODAL LUPA PASSWORD ========== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-header h3 {
            color: #333;
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: #dc3545;
        }

        .modal-body {
            margin-bottom: 25px;
            color: #555;
            line-height: 1.6;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-modal {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-modal-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-modal-cancel:hover {
            background: #5a6268;
        }

        .btn-modal-confirm {
            background: linear-gradient(to right, #28a745, #218838);
            color: white;
        }

        .btn-modal-confirm:hover {
            background: linear-gradient(to right, #218838, #1e7e34);
        }

        .btn-modal-primary {
            background: linear-gradient(to right, #007bff, #0056b3);
            color: white;
        }

        .btn-modal-primary:hover {
            background: linear-gradient(to right, #0056b3, #004494);
        }

       
        /* ========== ALERT MESSAGES ========== */
        .alert-error {
            background: linear-gradient(to right, #f8d7da, #f5c6cb);
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            border: 2px solid #f5c6cb;
            font-size: 0.9rem;
            animation: shake 0.5s ease, fadeIn 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .alert-success {
            background: linear-gradient(to right, #d4edda, #c3e6cb);
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            border: 2px solid #c3e6cb;
            font-size: 0.9rem;
            animation: fadeIn 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .alert-error::before,
        .alert-success::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }

        .alert-error::before {
            background: #dc3545;
        }

        .alert-success::before {
            background: #28a745;
        }

        /* ========== LINK REGISTER ========== */
        .login-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            animation: fadeIn 0.8s ease 0.7s forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        .login-links p {
            color: #666;
            margin-bottom: 15px;
        }

        .btn-register {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(to right, #28a745, #218838);
            color: white;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-register:hover {
            background: linear-gradient(to right, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(40, 167, 69, 0.3);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        /* ========== NOTIFIKASI LOGIN BERHASIL ========== */
        .login-success-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.5s ease, fadeOut 0.5s ease 3s forwards;
            max-width: 350px;
        }

        .notification-content {
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.3);
            position: relative;
            overflow: hidden;
            animation: pulse 2s infinite;
        }

        .notification-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: rgba(255, 255, 255, 0.3);
        }

        .notification-icon {
            margin-right: 15px;
            font-size: 1.8rem;
            animation: bounce 1s ease infinite;
        }

        .notification-text {
            flex: 1;
        }

        .notification-text strong {
            font-size: 1rem;
            display: block;
            margin-bottom: 3px;
            font-weight: 600;
        }

        .notification-text p {
            font-size: 0.9rem;
            margin: 0;
            opacity: 0.9;
        }

        .notification-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0;
            margin-left: 10px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .notification-close:hover {
            opacity: 1;
        }

        /* Animations */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-20px);
                visibility: hidden;
            }
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 10px 25px rgba(40, 167, 69, 0.3);
            }
            50% {
                box-shadow: 0 10px 30px rgba(40, 167, 69, 0.5);
            }
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 400px;
            }
            
            .login-left {
                min-height: 200px;
            }
            
            .login-left img {
                height: 200px;
                object-fit: cover;
            }
            
            .login-right {
                padding: 30px;
            }
            
            .login-header h2 {
                font-size: 1.8rem;
            }
            
            .modal-content {
                width: 95%;
                padding: 20px;
            }
            
            .login-success-notification {
                left: 20px;
                right: 20px;
                max-width: none;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .login-container {
                border-radius: 15px;
            }
            
            .login-right {
                padding: 20px;
            }
            
            .btn-google {
                padding: 12px;
                font-size: 0.95rem;
            }
        }

        /* Animasi untuk ikon */
        .fas.fa-sign-in-alt {
            transition: transform 0.3s ease;
        }

        .btn-login:hover .fas.fa-sign-in-alt {
            transform: translateX(3px);
        }

        .btn-register:hover .fas.fa-user-plus {
            transform: rotate(90deg);
            transition: transform 0.3s ease;
        }

        /* Custom checkbox untuk "Ingat Saya" */
        .remember-me {
            display: flex;
            align-items: center;
            margin-top: 15px;
            animation: fadeIn 0.8s ease 0.3s forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        .remember-me input[type="checkbox"] {
            display: none;
        }

        .remember-me label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 0.9rem;
            color: #666;
        }

        .remember-me label::before {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #ddd;
            border-radius: 5px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .remember-me input[type="checkbox"]:checked + label::before {
            background: #007bff;
            border-color: #007bff;
        }

        .remember-me input[type="checkbox"]:checked + label::after {
            content: '✓';
            position: absolute;
            color: white;
            font-size: 14px;
            left: 5px;
            top: 1px;
        }

        /* Password toggle */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #007bff;
        }


        /* ========== MODAL RESET PASSWORD LANGSUNG ========== */
        .forgot-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1001;
            animation: fadeIn 0.3s ease;
        }

        .forgot-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            animation: slideDown 0.4s ease;
            overflow: hidden;
        }

        .forgot-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: linear-gradient(to right, #007bff, #0056b3);
            color: white;
        }

        .forgot-header h3 {
            margin: 0;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }

        .close-btn:hover {
            opacity: 0.8;
        }

        .forgot-body {
            padding: 25px;
        }

        .forgot-body p {
            color: #555;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .forgot-body .form-group {
            margin-bottom: 20px;
            animation: none;
            opacity: 1;
            transform: none;
        }

        .forgot-body .input-with-icon {
            position: relative;
        }

        .forgot-body .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .forgot-body .input-with-icon input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .forgot-body .input-with-icon input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
            outline: none;
        }

        .forgot-message {
            padding: 10px 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 0.9rem;
            display: none;
        }

        .forgot-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }

        .forgot-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        .btn-reset {
            width: 100%;
            padding: 14px;
            background: linear-gradient(to right, #28a745, #218838);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-reset:hover {
            background: linear-gradient(to right, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-reset:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-reset:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <!-- NOTIFIKASI LOGIN BERHASIL -->
    <?php if (!empty($login_message)): ?>
        <div class="login-success-notification">
            <div class="notification-content">
                <div class="notification-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="notification-text">
                    <strong>Berhasil!</strong>
                    <p><?php echo $login_message; ?></p>
                </div>
                <button class="notification-close" onclick="this.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Background floating circles -->
    <div class="floating-circles">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>
    
    <!-- Modal Reset Password Langsung -->
    <div id="forgotPasswordModalDirect" class="forgot-modal">
        <div class="forgot-content">
            <div class="forgot-header">
                <h3><i class="fas fa-key"></i> Reset Password</h3>
                <button class="close-btn" onclick="hideForgotModal()">&times;</button>
            </div>
            <div class="forgot-body">
                <p>Masukkan username Anda dan password baru.</p>
                
                <form id="resetPasswordForm" onsubmit="return resetPassword()">
                    <div class="form-group">
                        <label for="reset_username">Username</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="reset_username" 
                                   placeholder="Masukkan username" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Password Baru</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="new_password" 
                                   placeholder="Password baru (min 6 karakter)" 
                                   minlength="6" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" 
                                   placeholder="Ulangi password baru" 
                                   minlength="6" required>
                        </div>
                    </div>
                    
                    <div id="forgotMessage" class="forgot-message"></div>
                    
                    <button type="submit" class="btn-reset">
                        <i class="fas fa-redo"></i> Reset Password
                    </button>
                </form>
                
                <p style="font-size: 0.85rem; color: #888; margin-top: 15px; text-align: center;">
                    <i class="fas fa-info-circle"></i> Password akan langsung diganti tanpa konfirmasi admin
                </p>
            </div>
        </div>
    </div>

    <!-- Modal Lupa Password (via email) -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Lupa Password</h3>
                <button class="close-modal" onclick="closeModal('forgotPasswordModal')">×</button>
            </div>
            <div class="modal-body">
                <?php if (!empty($error_message) && isset($_POST['forgot_password'])): ?>
                    <div class="alert-error" style="margin-top: 15px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message) && isset($_POST['forgot_password'])): ?>
                    <div class="alert-success" style="margin-top: 15px;">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" id="forgotPasswordForm" style="margin-top: 20px;">
                    <div class="modal-footer">
                        <button type="button" class="btn-modal btn-modal-cancel" onclick="closeModal('forgotPasswordModal')">
                            Batal
                        </button>
                        <button type="button" class="btn-modal btn-modal-confirm" onclick="openResetPasswordDirect()">
                            <i class="fas fa-redo"></i> Reset Password Anda
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Loading -->
    <div id="loadingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Memproses Login</h3>
            </div>
            <div class="modal-body">
                <div style="text-align: center;">
                    <div class="loading-spinner" style="
                        width: 50px;
                        height: 50px;
                        border: 5px solid #f3f3f3;
                        border-top: 5px solid #007bff;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                        margin: 0 auto 20px;
                    "></div>
                    <p>Mohon tunggu, sedang memverifikasi data...</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="login-container">
        <!-- Left Side - Gambar Hindia -->
        <div class="login-left">
            <img src="uploads/banner.png" alt="Hindia Concert" onerror="handleImageError(this)">
            <div class="overlay">
                <h3>Event Mahasiswa</h3>
                <p>Sistem Manajemen Event Terpadu</p>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-header">
                <h2>Selamat Datang</h2>
                <p>Masuk ke akun Anda</p>
            </div>
            
            <?php if (!empty($error_message) && !isset($_POST['forgot_password'])): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message) && isset($_POST['forgot_password'])): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" id="loginForm">
                <div class="form-group" style="--order: 1;">
                    <label for="username">Username</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" 
                               placeholder="Masukkan username"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required
                               autocomplete="username">
                    </div>
                </div>
                
                <div class="form-group" style="--order: 2;">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="Masukkan password" required
                               autocomplete="current-password">
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <!-- LINK LUPA PASSWORD -->
                <div class="forgot-password-link">
                    <a href="javascript:void(0)" onclick="openForgotPasswordModal()">
                        <i class="fas fa-question-circle"></i> Lupa Password?
                    </a>
                </div>
                
                <div class="remember-me" style="--order: 3;">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Ingat saya</label>
                </div>
                
                <button type="submit" name="login" class="btn-login" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                
                
            </form>
        
            <div class="login-links">
                <p>Belum memiliki akun?</p>
                <a href="register.php" class="btn-register">
                    <i class="fas fa-user-plus"></i> Daftar Sekarang
                </a>
            </div>
        </div>
    </div>

    <script>
        // Fungsi untuk menangani error gambar
        function handleImageError(img) {
            console.log('Gambar tidak ditemukan: ' + img.src);
            img.style.display = 'none';
            const overlay = document.querySelector('.overlay');
            overlay.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-calendar-alt" style="font-size: 3rem; margin-bottom: 15px; color: white;"></i>
                    <h3 style="color: white; margin-bottom: 10px;">Sistem Event Mahasiswa</h3>
                    <p style="color: rgba(255,255,255,0.9);">Kelola dan ikuti event kampus dengan mudah</p>
                </div>
            `;
            overlay.style.opacity = '1';
        }
        
        // Fungsi untuk toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Fungsi untuk membuka modal
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Fungsi untuk menutup modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Reset form lupa password ketika modal ditutup
            if (modalId === 'forgotPasswordModal') {
                document.getElementById('forgotPasswordForm').reset();
            }
        }
        
        // Fungsi khusus untuk membuka modal lupa password
        function openForgotPasswordModal() {
            openModal('forgotPasswordModal');
        }
        
        // Fungsi untuk membuka modal reset password langsung
        function openResetPasswordDirect() {
            closeModal('forgotPasswordModal');
            openModal('forgotPasswordModalDirect');
        }
        
        // Fungsi untuk menutup modal reset password langsung
        function hideForgotModal() {
            closeModal('forgotPasswordModalDirect');
        }
        
        // Validasi form login sebelum submit
        function validateLoginForm() {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const loginButton = document.getElementById('loginButton');
            
            // Validasi client-side
            if (!username || !password) {
                alert('Username dan password harus diisi!');
                return false;
            }
            
            if (username.length < 3) {
                alert('Username minimal 3 karakter!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password minimal 6 karakter!');
                return false;
            }
            
            // Tampilkan loading modal
            openModal('loadingModal');
            
            // Ubah tampilan tombol login
            loginButton.disabled = true;
            loginButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            loginButton.style.opacity = '0.7';
            
            // Form akan di-submit setelah validasi
            return true;
        }
        
        // Fungsi untuk reset password langsung (AJAX)
        function resetPassword() {
            event.preventDefault(); // Mencegah form submit default
            
            const username = document.getElementById('reset_username').value.trim();
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const messageDiv = document.getElementById('forgotMessage');
            const resetBtn = document.querySelector('.btn-reset');
            const originalText = resetBtn.innerHTML;
            
            // Validasi
            if (!username) {
                showForgotMessage('Username harus diisi!', 'error');
                return false;
            }
            
            if (newPassword.length < 6) {
                showForgotMessage('Password minimal 6 karakter!', 'error');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                showForgotMessage('Password tidak cocok!', 'error');
                return false;
            }
            
            // Tampilkan loading
            resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            resetBtn.disabled = true;
            
            // Kirim request AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'reset_password_direct.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                resetBtn.innerHTML = originalText;
                resetBtn.disabled = false;
                
                if (xhr.status === 200) {
                    const response = xhr.responseText;
                    
                    if (response.includes('|')) {
                        const parts = response.split('|');
                        if (parts[0] === 'success') {
                            showForgotMessage(parts[1], 'success');
                            
                            // Kosongkan form
                            document.getElementById('reset_username').value = '';
                            document.getElementById('new_password').value = '';
                            document.getElementById('confirm_password').value = '';
                            
                            // Auto close setelah 3 detik
                            setTimeout(() => {
                                hideForgotModal();
                                // Redirect ke login setelah 1 detik
                                setTimeout(() => {
                                    window.location.href = 'index.php';
                                }, 1000);
                            }, 3000);
                        } else {
                            showForgotMessage(parts[1], 'error');
                        }
                    } else {
                        showForgotMessage(response, 'error');
                    }
                } else {
                    showForgotMessage('Error: ' + xhr.status, 'error');
                }
            };
            
            xhr.onerror = function() {
                resetBtn.innerHTML = originalText;
                resetBtn.disabled = false;
                showForgotMessage('Gagal terhubung ke server', 'error');
            };
            
            const data = 'username=' + encodeURIComponent(username) + 
                         '&new_password=' + encodeURIComponent(newPassword);
            
            xhr.send(data);
            
            return false; // Mencegah form submit
        }
        
        // Fungsi untuk menampilkan pesan di modal reset password
        function showForgotMessage(message, type) {
            const messageDiv = document.getElementById('forgotMessage');
            messageDiv.textContent = message;
            messageDiv.className = 'forgot-message ' + type;
            messageDiv.style.display = 'block';
            
            // Scroll ke pesan
            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        // Event listener saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.input-with-icon input');
            const loginForm = document.getElementById('loginForm');
            const forgotPasswordForm = document.getElementById('forgotPasswordForm');
            
            // Animasi saat input fokus
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
                
                // Efek saat typing
                input.addEventListener('input', function() {
                    if (this.value.length > 0) {
                        this.parentElement.classList.add('has-value');
                    } else {
                        this.parentElement.classList.remove('has-value');
                    }
                });
            });
            
            // Event listener untuk form login
            if (loginForm) {
                loginForm.addEventListener('submit', function() {
                document.getElementById('loginButton').innerHTML =
                    '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            });

            }
            
            // Event listener untuk form lupa password
            if (forgotPasswordForm) {
                forgotPasswordForm.addEventListener('submit', function(e) {
                    if (!validateForgotPasswordForm()) {
                        e.preventDefault();
                    }
                });
            }
            
            // Efek ripple untuk tombol
            const buttons = document.querySelectorAll('.btn-login, .btn-google, .btn-register, .btn-modal, .btn-reset');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const x = e.clientX - e.target.getBoundingClientRect().left;
                    const y = e.clientY - e.target.getBoundingClientRect().top;
                    
                    const ripple = document.createElement('span');
                    ripple.classList.add('ripple-effect');
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        if (ripple.parentNode === this) {
                            ripple.remove();
                        }
                    }, 600);
                });
            });
            
            // Style untuk ripple effect
            const style = document.createElement('style');
            style.textContent = `
                .ripple-effect {
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.7);
                    transform: scale(0);
                    animation: ripple-animation 0.6s linear;
                    width: 20px;
                    height: 20px;
                }
                
                @keyframes ripple-animation {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
                
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .input-with-icon.focused i {
                    color: #007bff;
                    transform: translateY(-50%) scale(1.1);
                }
                
                .input-with-icon.has-value i {
                    color: #28a745;
                }
                
                /* Tutup modal dengan klik di luar */
                .modal, .forgot-modal {
                    display: none;
                }
            `;
            document.head.appendChild(style);
            
            // Animasi untuk container login
            const loginContainer = document.querySelector('.login-container');
            setTimeout(() => {
                loginContainer.style.animation = 'slideUp 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards';
            }, 300);
            
            // Tutup modal dengan klik di luar content
            const modals = document.querySelectorAll('.modal, .forgot-modal');
            modals.forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }
                });
            });
            
            // Tambahkan event untuk tombol Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal('forgotPasswordModal');
                    closeModal('loadingModal');
                    hideForgotModal();
                }
            });
            
            // Auto focus ke input username saat modal reset langsung dibuka
            document.getElementById('forgotPasswordModalDirect').addEventListener('shown', function() {
                document.getElementById('reset_username').focus();
            });
        });
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>