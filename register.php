<?php
include 'config.php';

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = clean_input($_POST['username']);
    $password = ($_POST['password']);
    $confirm_password = clean_input($_POST['confirm_password']);
    $email = clean_input($_POST['email']);
    $full_name = clean_input($_POST['full_name']);
    
    // Validasi
    if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
        $error_message = "Semua field harus diisi!";
    } elseif ($password !== $confirm_password) {
        $error_message = "Password dan konfirmasi password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error_message = "Password minimal 6 karakter!";
    } else {
        // Cek apakah username sudah ada
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error_message = "Username atau email sudah terdaftar!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user baru
            $insert_query = "INSERT INTO users (username, password, email, full_name) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "ssss", $username, $hashed_password, $email, $full_name);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Pendaftaran berhasil! Silakan login.";
                $_POST = array(); // Reset form
            } else {
                $error_message = "Terjadi kesalahan saat mendaftar. Silakan coba lagi.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Event Mahasiswa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: gradientBG 15s ease infinite;
            background-size: 400% 400%;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(10px);
            transform: translateY(0);
            animation: floatIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h2 {
            text-align: center;
            margin-bottom: 40px;
            color: #333;
            font-size: 32px;
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }

        h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(to right, #667eea, #764ba2);
            border-radius: 2px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f9f9f9;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 40px;
            color: #667eea;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        input:focus + .input-icon {
            color: #764ba2;
            transform: scale(1.1);
        }

        .password-strength {
            height: 4px;
            background: #e1e1e1;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
            position: relative;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            border-radius: 2px;
            transition: all 0.4s ease;
        }

        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            letter-spacing: 1px;
        }

        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        button:active {
            transform: translateY(-1px);
        }

        button:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        button:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(40, 40);
                opacity: 0;
            }
        }

        .links {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            padding: 5px 0;
        }

        .links a:hover {
            color: #764ba2;
        }

        .links a:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(to right, #667eea, #764ba2);
            transition: width 0.3s ease;
        }

        .links a:hover:after {
            width: 100%;
        }

        .success {
            background: linear-gradient(to right, #d4edda, #c3e6cb);
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            animation: slideDown 0.5s ease;
            border-left: 5px solid #28a745;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success i {
            font-size: 20px;
        }

        .error {
            background: linear-gradient(to right, #f8d7da, #f5c6cb);
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            animation: shake 0.5s ease;
            border-left: 5px solid #dc3545;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error i {
            font-size: 20px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo i {
            font-size: 48px;
            background: linear-gradient(to right, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            display: inline-block;
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            h2 {
                font-size: 26px;
            }
        }

        /* Floating animation for decorative elements */
        .floating {
            position: absolute;
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: -1;
        }

        .floating:nth-child(1) {
            top: 10%;
            left: 10%;
            animation: float 20s infinite linear;
        }

        .floating:nth-child(2) {
            top: 20%;
            right: 15%;
            animation: float 25s infinite linear reverse;
        }

        .floating:nth-child(3) {
            bottom: 15%;
            left: 15%;
            animation: float 30s infinite linear;
        }

        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(100px, 100px) rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Decorative floating elements -->
    <div class="floating"></div>
    <div class="floating"></div>
    <div class="floating"></div>
    
    <div class="container">
        <div class="logo">
            <i class="fas fa-user-plus"></i>
        </div>
        
        <h2>Daftar Akun Baru</h2>
        
        <?php if (!empty($success_message)): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="registerForm">
            <div class="form-group">
                <label><i class="far fa-user"></i> Nama Lengkap</label>
                <input type="text" name="full_name" value="<?php echo isset($_POST['full_name']) ? $_POST['full_name'] : ''; ?>" required>
                <div class="input-icon"><i class="fas fa-user"></i></div>
            </div>
            
            <div class="form-group">
                <label><i class="far fa-id-card"></i> Username</label>
                <input type="text" name="username" value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>" required>
                <div class="input-icon"><i class="fas fa-at"></i></div>
            </div>
            
            <div class="form-group">
                <label><i class="far fa-envelope"></i> Email</label>
                <input type="email" name="email" value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>" required>
                <div class="input-icon"><i class="fas fa-envelope"></i></div>
            </div>
            
            <div class="form-group">
                <label><i class="far fa-key"></i> Password</label>
                <input type="password" name="password" id="password" required oninput="checkPasswordStrength()">
                <div class="input-icon"><i class="fas fa-lock"></i></div>
                <div class="password-strength">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="far fa-key"></i> Konfirmasi Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required oninput="checkPasswordMatch()">
                <div class="input-icon"><i class="fas fa-lock"></i></div>
                <small id="passwordMatch" style="display:none; color:#28a745; margin-top:5px;">
                    <i class="fas fa-check"></i> Password cocok
                </small>
                <small id="passwordNotMatch" style="display:none; color:#dc3545; margin-top:5px;">
                    <i class="fas fa-times"></i> Password tidak cocok
                </small>
            </div>
            
            <button type="submit" id="submitBtn">
                <i class="fas fa-user-plus"></i> Daftar Sekarang
            </button>
        </form>
        
        <div class="links">
            <p>Sudah punya akun? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login di sini</a></p>
        </div>
        
        <div class="form-footer">
            <p><i class="far fa-copyright"></i> 2025 LIVEFEST.</p>
        </div>
    </div>

    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;
            let color = '#dc3545';
            
            if (password.length >= 6) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            if (strength >= 75) {
                color = '#28a745';
            } else if (strength >= 50) {
                color = '#ffc107';
            } else if (strength >= 25) {
                color = '#fd7e14';
            }
            
            strengthBar.style.width = strength + '%';
            strengthBar.style.background = color;
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const match = document.getElementById('passwordMatch');
            const notMatch = document.getElementById('passwordNotMatch');
            
            if (confirm.length === 0) {
                match.style.display = 'none';
                notMatch.style.display = 'none';
                return;
            }
            
            if (password === confirm) {
                match.style.display = 'block';
                notMatch.style.display = 'none';
            } else {
                match.style.display = 'none';
                notMatch.style.display = 'block';
            }
        }
        
        // Form submission animation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mendaftarkan...';
            submitBtn.disabled = true;
        });
        
        // Input focus effects
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-5px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>

<?php mysqli_close($conn);?>
