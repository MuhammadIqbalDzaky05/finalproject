<?php
// HAPUS session_start() di sini karena sudah ada di config.php
// Hanya include config.php

// TAMBAHKAN TIMEZONE di awal
date_default_timezone_set('Asia/Jakarta');

include 'config.php';
require_login();

// Generate CSRF token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$event_id = $_GET['event_id'] ?? 0;
$success_message = "";
$error_message = "";
$formatted_time = "";
$ticket_code = "";
$purchase_time = ""; // TAMBAHKAN INI

// Validasi event_id
if (!is_numeric($event_id) || $event_id <= 0) {
    header('Location: events.php');
    exit();
}

// Ambil data user untuk mendapatkan email
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($user_result);

// Ambil data event
$event_query = "SELECT * FROM events WHERE id = ?";
$stmt = mysqli_prepare($conn, $event_query);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$event_result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($event_result);

if (!$event) {
    header('Location: events.php');
    exit();
}

// Cek apakah tiket masih tersedia
if ($event['available_tickets'] <= 0) {
    $_SESSION['error'] = "❌ Maaf, tiket untuk event ini sudah habis!";
    header('Location: events.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validasi CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "❌ Token keamanan tidak valid!";
        header('Location: events.php');
        exit();
    }
    
    $attendee_name = clean_input($_POST['attendee_name']);
    $attendee_email = clean_input($_POST['attendee_email']);
    $quantity = intval($_POST['quantity']);
    
    // Validasi input
    if (empty($attendee_name) || empty($attendee_email) || $quantity < 1) {
        $error_message = "❌ Harap isi semua field dengan benar!";
    } elseif (!filter_var($attendee_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "❌ Format email tidak valid!";
    } elseif ($quantity > $event['available_tickets']) {
        $error_message = "❌ Jumlah tiket melebihi ketersediaan!";
    } else {
        // Mulai transaksi
        mysqli_begin_transaction($conn);
        
        try {
            // WAKTU PEMBELIAN REAL-TIME - PANGGIL SEBELUM GENERATE TICKET CODE
            $purchase_time = date('Y-m-d H:i:s'); // Ini waktu sebenarnya
            
            // Generate ticket code yang unik DENGAN TIMESTAMP
            $ticket_code = '';
            $ticket_code_unique = false;
            $attempts = 0;
            
            do {
                // TAMBAHKAN TIMESTAMP DI TICKET CODE: ymdHis = TahunBulanTanggalJamMenitDetik
                $random_part = strtoupper(substr(md5(uniqid()), 0, 4));
                $ticket_code = 'TKT' . date('ymdHis') . $random_part; // PASTIKAN ADA JamMenitDetik
                
                // Cek apakah ticket_code sudah ada
                $check_sql = "SELECT COUNT(*) as count FROM tickets WHERE ticket_code = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "s", $ticket_code);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $check_data = mysqli_fetch_assoc($check_result);
                
                if ($check_data['count'] == 0) {
                    $ticket_code_unique = true;
                }
                $attempts++;
            } while (!$ticket_code_unique && $attempts < 10);
            
            if (!$ticket_code_unique) {
                throw new Exception("Gagal generate kode tiket unik");
            }
            
            $total_price = $event['price'] * $quantity;
            
            // **PASTIKAN purchase_time SUDAH DIATUR DI ATAS**
            
            // Insert ticket DENGAN purchase_time
            $sql = "INSERT INTO tickets (event_id, user_id, ticket_code, attendee_name, attendee_email, quantity, total_price, purchase_time, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')";
            
            $stmt = mysqli_prepare($conn, $sql);
            // Parameter: event_id, user_id, ticket_code, attendee_name, attendee_email, quantity, total_price, purchase_time
            mysqli_stmt_bind_param($stmt, "iisssids", 
                $event_id, 
                $_SESSION['user_id'], 
                $ticket_code, 
                $attendee_name, 
                $attendee_email, 
                $quantity, 
                $total_price,
                $purchase_time // WAKTU YANG SUDAH DIATUR
            );
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Gagal menyimpan tiket: " . mysqli_error($conn));
            }
            
            $ticket_id = mysqli_insert_id($conn);
            
            // Update available tickets dengan row lock
            $update_sql = "UPDATE events SET available_tickets = available_tickets - ? WHERE id = ? AND available_tickets >= ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "iii", $quantity, $event_id, $quantity);
            mysqli_stmt_execute($update_stmt);
            
            $affected_rows = mysqli_stmt_affected_rows($update_stmt);
            
            if ($affected_rows == 0) {
                throw new Exception("Tiket tidak cukup atau event tidak ditemukan");
            }
            
            // Insert ke dalam system log dengan waktu pembelian
            $log_sql = "INSERT INTO system_logs (user_id, action, description, timestamp) VALUES (?, 'ticket_purchase', ?, ?)";
            $log_stmt = mysqli_prepare($conn, $log_sql);
            $log_description = "Pembelian " . $quantity . " tiket untuk event: " . $event['event_name'] . 
                               " (Ticket ID: " . $ticket_id . ", Kode: " . $ticket_code . ")";
            mysqli_stmt_bind_param($log_stmt, "iss", $_SESSION['user_id'], $log_description, $purchase_time);
            mysqli_stmt_execute($log_stmt);
            
            // Commit transaksi
            mysqli_commit($conn);
            
            // **Format waktu untuk tampilan - PAKAI TIMEZONE YANG SAMA**
            $formatted_time = date('l, d F Y', strtotime($purchase_time)) . 
                             " pukul " . date('H:i:s', strtotime($purchase_time)) . " WIB";
            
            $success_message = "🎉 Pembelian tiket berhasil! 
                              <br><strong>Kode Tiket: " . $ticket_code . "</strong>
                              <br>📅 Event: " . htmlspecialchars($event['event_name']) . "
                              <br>👤 Attendee: " . htmlspecialchars($attendee_name) . "
                              <br>🎫 Jumlah: " . $quantity . " tiket
                              <br>💰 Total: <strong>Rp" . number_format($total_price, 0, ',', '.') . "</strong>
                              <br>🕐 Waktu Pembelian: <strong>" . $formatted_time . "</strong>";
            
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            mysqli_rollback($conn);
            $error_message = "❌ Gagal memproses pembelian: " . $e->getMessage();
        }
    }
}

// Refresh data event setelah pembelian
$stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$event_result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($event_result);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Beli Tiket - LIVE FEST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ====== MOBILE FIRST STYLES ====== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            max-width: 100%;
        }

        html, body {
            width: 100%;
            overflow-x: hidden;
            font-size: 14px;
            line-height: 1.4;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            min-height: 100vh;
        }

        /* ====== CONTAINER FIX ====== */
        .container {
            width: 100%;
            padding: 0 12px;
            margin: 0 auto;
        }

        /* ====== HEADER MOBILE FIX ====== */
        header {
            background: white;
            padding: 10px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-main {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 0 0 10px 0;
            border-bottom: 1px solid #eee;
        }

        .logo-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            width: 100%;
        }

        .logo-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            gap: 8px;
        }

        .logo-img {
            height: 50px;
            width: auto;
            border-radius: 8px;
        }

        .site-title {
            font-size: 1.6rem;
            color: #667eea;
            font-weight: 700;
            text-align: center;
            margin: 0;
        }

        .user-menu {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 0 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.9rem;
            min-height: 44px;
        }

        .btn-logout {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }

        /* ====== NAVIGASI MOBILE ====== */
        nav {
            padding: 10px 0 0 0;
        }

        nav ul {
            display: flex;
            flex-wrap: wrap;
            list-style: none;
            gap: 8px;
            justify-content: center;
            padding: 0;
            margin: 0;
        }

        nav ul li {
            flex: 1 0 calc(50% - 8px);
            min-width: 140px;
        }

        nav ul li a {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 8px;
            background: #f8f9fa;
            color: #555;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            min-height: 44px;
            border: 1px solid #e9ecef;
            text-align: center;
        }

        nav ul li a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        /* ====== MAIN CONTENT MOBILE ====== */
        main {
            padding: 15px 0;
        }

        /* ====== PAGE HEADER MOBILE ====== */
        .page-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .page-header p {
            color: #666;
            font-size: 0.95rem;
            max-width: 500px;
            margin: 0 auto;
        }

        /* ====== ALERT MESSAGES MOBILE ====== */
        .alert-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        .alert-error {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        /* ====== EVENT INFO CARD MOBILE ====== */
        .event-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .event-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
        }

        .event-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .event-type {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-top: 5px;
        }

        .event-details {
            padding: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            font-weight: 600;
        }

        .price-display {
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, #f0f7ff 0%, #e6f0ff 100%);
            margin: 15px;
            border-radius: 8px;
            border: 2px dashed #4facfe;
        }

        .price-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }

        .price-amount {
            font-size: 1.8rem;
            font-weight: 800;
            color: #667eea;
        }

        /* ====== TICKET FORM MOBILE ====== */
        .ticket-form {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .form-header h3 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            background: #f8f9ff;
            font-family: 'Segoe UI', sans-serif;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* ====== QUANTITY SELECTOR MOBILE ====== */
        .quantity-selector {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-display {
            font-size: 1.5rem;
            font-weight: 700;
            min-width: 50px;
            text-align: center;
        }

        /* ====== TOTAL PRICE MOBILE ====== */
        .total-price {
            background: linear-gradient(135deg, #f0f7ff 0%, #e6f0ff 100%);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            border: 2px solid #4facfe;
        }

        .total-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }

        .total-amount {
            font-size: 1.8rem;
            font-weight: 800;
            color: #27ae60;
        }

        /* ====== FORM ACTIONS MOBILE ====== */
        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        /* ====== SUCCESS MESSAGE MOBILE ====== */
        .success-card {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
        }

        .ticket-code {
            font-size: 1.8rem;
            font-weight: 800;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            display: inline-block;
            cursor: pointer;
        }
        
        /* TAMBAHKAN STYLE UNTUK WAKTU PEMBELIAN */
        .purchase-time {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 1rem;
            text-align: center;
            border: 2px dashed rgba(255, 255, 255, 0.3);
        }

        /* ====== FOOTER MOBILE ====== */
        footer {
            background: #1a1a2e;
            color: white;
            padding: 25px 0 20px;
            margin-top: 25px;
        }

        .footer-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
            text-align: center;
        }

        /* ====== MEDIA QUERIES UNTUK TABLET ====== */
        @media (min-width: 768px) {
            html, body {
                font-size: 15px;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .header-main {
                flex-direction: row;
                justify-content: space-between;
            }
            
            .logo-area {
                flex-direction: row;
                justify-content: flex-start;
                width: auto;
            }
            
            .logo-link {
                flex-direction: row;
            }
            
            .user-menu {
                width: auto;
                justify-content: flex-end;
            }
            
            nav ul li {
                flex: none;
                width: auto;
            }
            
            nav ul li a {
                min-width: 120px;
            }
            
            .page-header h1 {
                font-size: 2.2rem;
            }
            
            .event-card {
                max-width: 700px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .ticket-form {
                max-width: 700px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .form-actions {
                flex-direction: row;
                justify-content: center;
            }
            
            .footer-content {
                flex-direction: row;
                justify-content: space-between;
                text-align: left;
            }
        }

        /* ====== MEDIA QUERIES UNTUK DESKTOP ====== */
        @media (min-width: 992px) {
            .container {
                max-width: 1200px;
            }
            
            .page-header h1 {
                font-size: 2.5rem;
            }
            
            .event-card {
                max-width: 800px;
            }
            
            .ticket-form {
                max-width: 800px;
            }
        }

        /* ====== UTILITY CLASSES ====== */
        .text-center { text-align: center; }
        .mt-1 { margin-top: 5px; }
        .mt-2 { margin-top: 10px; }
        .mt-3 { margin-top: 15px; }
        .mb-1 { margin-bottom: 5px; }
        .mb-2 { margin-bottom: 10px; }
        .mb-3 { margin-bottom: 15px; }
        .p-1 { padding: 5px; }
        .p-2 { padding: 10px; }
        .p-3 { padding: 15px; }

        /* ====== HIDE ELEMENTS ON MOBILE ====== */
        .mobile-hide {
            display: none;
        }
        
        @media (min-width: 768px) {
            .mobile-hide {
                display: block;
            }
            
            .mobile-only {
                display: none;
            }
        }
        
        /* ====== SIMPLE ANIMATIONS ====== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }
        
        /* Delay for form groups */
        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ====== LOADING OVERLAY ====== */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(5px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div style="font-weight: 600; color: #667eea; margin-top: 10px;">Memproses pembelian...</div>
        <div style="font-size: 0.8rem; color: #666; margin-top: 5px;" id="currentTime"></div>
    </div>

    <header>
        <div class="container">
            <div class="header-main">
                <div class="logo-area">
                    <a href="dashboard.php" class="logo-link">
                        <img src="logo/gallery2.png" alt="LIVE FEST Logo" class="logo-img" onerror="this.style.display='none'">
                        <h1 class="site-title">LIVE FEST</h1>
                    </a>
                </div>
                
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                    <a href="logout.php" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> <span class="mobile-hide">Logout</span>
                    </a>
                </div>
            </div>
            
            <nav>
                <ul>
                    <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> <span>Dashboard</span>
                    </a></li>
                    <li><a href="events.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> <span>Daftar Event</span>
                    </a></li>
                    
                    <?php if (is_logged_in() && !is_admin()): ?>
                        <li><a href="my_tickets.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'my_tickets.php' ? 'active' : ''; ?>">
                            <i class="fas fa-ticket-alt"></i> <span>Tiket Saya</span>
                        </a></li>
                    <?php endif; ?>
                    
                    <?php if (is_admin()): ?>
                        <li><a href="add_event.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'add_event.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus-circle"></i> <span>Tambah Event</span>
                        </a></li>
                        <li><a href="edit_events.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'edit_events.php' ? 'active' : ''; ?>">
                            <i class="fas fa-edit"></i> <span>Edit Event</span>
                        </a></li>
                        <li class="mobile-only"><a href="delete_event.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'delete_event.php' ? 'active' : ''; ?>">
                            <i class="fas fa-trash-alt"></i> <span>Hapus Event</span>
                        </a></li>
                        <li class="mobile-hide"><a href="delete_event.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'delete_event.php' ? 'active' : ''; ?>">
                            <i class="fas fa-trash-alt"></i> <span>Hapus Event</span>
                        </a></li>
                        <li><a href="analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> <span>Analitik</span>
                        </a></li>
                        <li><a href="manage_tickets.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_tickets.php' ? 'active' : ''; ?>">
                            <i class="fas fa-ticket-alt"></i> <span>Kelola Tiket</span>
                        </a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="page-header">
            <h1>Beli Tiket</h1>
            <p>Lengkapi form berikut untuk membeli tiket event</p>
            
            <!-- Waktu server saat ini -->
            <div style="background: rgba(102, 126, 234, 0.1); padding: 10px; border-radius: 8px; margin-top: 10px; font-size: 0.9rem;">
                <i class="fas fa-clock"></i> 
                Waktu server: <strong><?php echo date('d M Y, H:i:s'); ?></strong> WIB
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Event Info Card -->
        <div class="event-card">
            <div class="event-header">
                <div class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                <div class="event-type"><?php echo htmlspecialchars($event['event_type']); ?></div>
            </div>
            <div class="event-details">
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-calendar-day"></i> Tanggal</div>
                    <div class="detail-value"><?php echo date('d M Y', strtotime($event['event_date'])); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-clock"></i> Waktu</div>
                    <div class="detail-value"><?php echo date('H:i', strtotime($event['event_time'])); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-map-marker-alt"></i> Lokasi</div>
                    <div class="detail-value"><?php echo htmlspecialchars($event['location']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-ticket"></i> Tiket Tersedia</div>
                    <div class="detail-value"><?php echo $event['available_tickets']; ?> tiket</div>
                </div>
                <?php if (!empty($event['band_name'])): ?>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-star"></i> Guest Star</div>
                    <div class="detail-value"><?php echo htmlspecialchars($event['band_name']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="price-display">
                <div class="price-label">Harga Tiket</div>
                <div class="price-amount">Rp <?php echo number_format($event['price'], 0, ',', '.'); ?></div>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="success-card">
                <i class="fas fa-check-circle" style="font-size: 2.5rem; margin-bottom: 10px;"></i>
                <div style="font-size: 1.1rem; margin-bottom: 15px;"><?php echo $success_message; ?></div>
                
                <!-- Kode Tiket -->
                <div class="ticket-code" id="ticketCode" onclick="copyToClipboard('<?php echo isset($ticket_code) ? $ticket_code : ''; ?>')">
                    <i class="fas fa-ticket-alt"></i> <?php echo isset($ticket_code) ? $ticket_code : ''; ?>
                    <br><small style="font-size: 0.7rem; opacity: 0.8;">(Klik untuk menyalin)</small>
                </div>
                
                <!-- Waktu Pembelian -->
                <div class="purchase-time">
                    <i class="fas fa-clock" style="font-size: 1.5rem; margin-bottom: 5px;"></i><br>
                    <strong>Waktu Pembelian:</strong><br>
                    <?php 
                        if (isset($purchase_time) && !empty($purchase_time)) {
                            // Format yang lebih jelas
                            $hari = date('l', strtotime($purchase_time));
                            $hari_indonesia = [
                                'Sunday' => 'Minggu',
                                'Monday' => 'Senin',
                                'Tuesday' => 'Selasa',
                                'Wednesday' => 'Rabu',
                                'Thursday' => 'Kamis',
                                'Friday' => 'Jumat',
                                'Saturday' => 'Sabtu'
                            ];
                            
                            echo '<span style="font-size: 1.1rem;">';
                            echo $hari_indonesia[$hari] . ', ';
                            echo date('d F Y', strtotime($purchase_time)) . '<br>';
                            echo 'Pukul <strong>' . date('H:i:s', strtotime($purchase_time)) . ' WIB</strong>';
                            echo '</span>';
                        } else {
                            echo date('d M Y, H:i:s') . ' WIB';
                        }
                    ?>
                </div>
                
                <p style="margin: 15px 0; opacity: 0.9;">
                    <i class="fas fa-info-circle"></i> Silakan cek di "Tiket Saya" untuk detail tiket lebih lanjut
                </p>
                
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                    <a href="my_tickets.php" class="btn btn-secondary">
                        <i class="fas fa-ticket-alt"></i> Lihat Tiket Saya
                    </a>
                    <a href="events.php" class="btn btn-primary">
                        <i class="fas fa-calendar-alt"></i> Event Lainnya
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="ticket-form">
                <div class="form-header">
                    <h3><i class="fas fa-shopping-cart"></i> Form Pembelian Tiket</h3>
                    <p>Isi data diri Anda untuk pembelian tiket</p>
                    
                    <div style="background: rgba(67, 233, 123, 0.1); padding: 10px; border-radius: 8px; margin-top: 10px;">
                        <i class="fas fa-clock"></i> 
                        Waktu pembelian akan direkam: <strong><?php echo date('d M Y, H:i:s'); ?> WIB</strong>
                    </div>
                </div>

                <form method="post" action="" id="ticketForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="attendee_name">
                            <i class="fas fa-user"></i> Nama Attendee
                        </label>
                        <input type="text" id="attendee_name" name="attendee_name" 
                               value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" 
                               required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="attendee_email">
                            <i class="fas fa-envelope"></i> Email Attendee
                        </label>
                        <input type="email" id="attendee_email" name="attendee_email" 
                               value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" 
                               required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="quantity">
                            <i class="fas fa-ticket"></i> Jumlah Tiket
                        </label>
                        <div class="quantity-selector">
                            <button type="button" class="quantity-btn" onclick="changeQuantity(-1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="quantity-display" id="quantityDisplay">1</span>
                            <button type="button" class="quantity-btn" onclick="changeQuantity(1)">
                                <i class="fas fa-plus"></i>
                            </button>
                            <input type="hidden" name="quantity" id="quantityInput" value="1">
                        </div>
                        <div style="text-align: center; font-size: 0.85rem; color: #666; margin-top: 5px;">
                            Max: <?php echo $event['available_tickets']; ?> tiket
                        </div>
                    </div>

                    <div class="total-price">
                        <div class="total-label"><i class="fas fa-receipt"></i> Total Pembayaran</div>
                        <div class="total-amount" id="totalPrice">Rp <?php echo number_format($event['price'], 0, ',', '.'); ?></div>
                    </div>

                    <div class="form-actions">
                        <a href="events.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-credit-card"></i> Beli Tiket Sekarang
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div>
                    <h3 style="color: white; margin-bottom: 10px; font-size: 1.2rem;">LIVE FEST</h3>
                    <p style="color: #aaa; line-height: 1.5; font-size: 0.9rem;">Semua jenis event kampus dalam satu platform. Cari, temukan, segera daftarkan diri anda!</p>
                </div>
                <div>
                    <h4 style="color: white; margin-bottom: 10px; font-size: 1.1rem;">Kontak</h4>
                    <p style="color: #aaa; font-size: 0.9rem;"><i class="fas fa-envelope" style="margin-right: 8px;"></i>sofyantsaori1464@gmail.com</p>
                    <p style="color: #aaa; font-size: 0.9rem;"><i class="fas fa-envelope" style="margin-right: 8px;"></i>muhammadiqbaldzaky272@gmail.com</p>
                    <p style="color: #aaa; font-size: 0.9rem;"><i class="fas fa-envelope" style="margin-right: 8px;"></i>greypah92@gmail.com</p>
                </div>
                <div>
                    <h4 style="color: white; margin-bottom: 10px; font-size: 1.1rem;">Ikuti Kami</h4>
                    <div style="display: flex; gap: 12px;">
                        <a href="https://www.instagram.com/sofyantsaa/" style="color: #aaa; font-size: 1.1rem;"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.instagram.com/iqbl.dzky/" style="color: #aaa; font-size: 1.1rem;"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.instagram.com/18greypha/" style="color: #aaa; font-size: 1.1rem;"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div style="text-align: center; padding-top: 20px; border-top: 1px solid #333; color: #aaa; margin-top: 20px; font-size: 0.85rem;">
                <p>&copy; 2025 LIVE FEST.</p>
            </div>
        </div>
    </footer>

    <script>
        // Initialize ticket purchase functionality
        document.addEventListener('DOMContentLoaded', function() {
            const ticketPrice = <?php echo $event['price']; ?>;
            const maxTickets = <?php echo $event['available_tickets']; ?>;
            let currentQuantity = 1;
            const loadingOverlay = document.getElementById('loadingOverlay');
            const currentTime = document.getElementById('currentTime');

            // Update waktu real-time
            function updateCurrentTime() {
                if (currentTime) {
                    const now = new Date();
                    const formattedTime = now.toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    currentTime.textContent = "Waktu server: " + formattedTime + " WIB";
                }
            }
            
            // Update waktu setiap detik
            setInterval(updateCurrentTime, 1000);
            updateCurrentTime();

            function changeQuantity(change) {
                const quantityInput = document.getElementById('quantityInput');
                const quantityDisplay = document.getElementById('quantityDisplay');
                const totalPrice = document.getElementById('totalPrice');
                const submitBtn = document.getElementById('submitBtn');
                
                let newQuantity = currentQuantity + change;
                newQuantity = Math.max(1, Math.min(maxTickets, newQuantity));
                
                if (newQuantity !== currentQuantity) {
                    currentQuantity = newQuantity;
                    quantityInput.value = currentQuantity;
                    quantityDisplay.textContent = currentQuantity;
                    
                    const total = ticketPrice * currentQuantity;
                    totalPrice.textContent = 'Rp ' + total.toLocaleString('id-ID');
                    
                    // Animate quantity change
                    quantityDisplay.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        quantityDisplay.style.transform = 'scale(1)';
                    }, 200);
                    
                    // Update button text
                    if (submitBtn) {
                        submitBtn.innerHTML = `<i class="fas fa-credit-card"></i> Beli ${currentQuantity} Tiket (Rp${total.toLocaleString('id-ID')})`;
                    }
                }
            }

            // Make function available globally
            window.changeQuantity = changeQuantity;

            // Form submission
            const form = document.getElementById('ticketForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const attendeeName = document.getElementById('attendee_name').value.trim();
                    const attendeeEmail = document.getElementById('attendee_email').value.trim();
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    // Validation
                    if (!attendeeName) {
                        e.preventDefault();
                        alert('⚠️ Harap isi nama attendee!');
                        document.getElementById('attendee_name').focus();
                        return false;
                    }
                    
                    if (!emailPattern.test(attendeeEmail)) {
                        e.preventDefault();
                        alert('⚠️ Format email tidak valid!');
                        document.getElementById('attendee_email').focus();
                        return false;
                    }
                    
                    if (currentQuantity > maxTickets) {
                        e.preventDefault();
                        alert('❌ Jumlah tiket melebihi ketersediaan!');
                        return false;
                    }
                    
                    // Show loading overlay
                    loadingOverlay.classList.add('active');
                    
                    // Change button state
                    const submitBtn = document.getElementById('submitBtn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
                    }
                    
                    return true;
                });
            }

            // Initialize button text
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                const total = ticketPrice * currentQuantity;
                submitBtn.innerHTML = `<i class="fas fa-credit-card"></i> Beli ${currentQuantity} Tiket (Rp${total.toLocaleString('id-ID')})`;
            }
        });
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('✓ Kode tiket disalin ke clipboard: ' + text);
            }).catch(err => {
                console.error('Gagal menyalin: ', err);
            });
        }
    </script>
</body>
</html>
<?php
// Tutup koneksi database
mysqli_close($conn);
?>