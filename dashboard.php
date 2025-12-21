<?php
include 'config.php';
require_login();

// FLASH MESSAGE UNTUK NOTIFIKASI LOGIN BERHASIL
$flash_message = null;

// SOLUSI: Cek apakah notifikasi sudah pernah ditampilkan dalam session ini
if (isset($_SESSION['login_success']) && $_SESSION['login_success']) {
    // Cek flag khusus untuk mencegah notifikasi muncul berulang
    if (!isset($_SESSION['notification_shown'])) {
        $flash_message = [
            'type' => 'success',
            'message' => 'Login berhasil! Selamat datang, ' . $_SESSION['full_name'] . '!'
        ];
        
        // Tandai bahwa notifikasi sudah ditampilkan
        $_SESSION['notification_shown'] = true;
    }
    
    // Hapus flag login_success agar tidak muncul lagi saat refresh
    unset($_SESSION['login_success']);
}

// Ambil data user
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

// Ambil data event dengan band_photo jika ada
$events_query = "SELECT * FROM events ORDER BY event_date DESC LIMIT 5";
$events_result = mysqli_query($conn, $events_query);

// Hitung statistik
$total_events_query = "SELECT COUNT(*) as total FROM events";
$total_events_result = mysqli_query($conn, $total_events_query);
$total_events = mysqli_fetch_assoc($total_events_result)['total'];

$upcoming_events_query = "SELECT COUNT(*) as upcoming FROM events WHERE event_date >= CURDATE()";
$upcoming_events_result = mysqli_query($conn, $upcoming_events_query);
$upcoming_events = mysqli_fetch_assoc($upcoming_events_result)['upcoming'];

// Hitung statistik tiket
$my_tickets_query = "SELECT COUNT(*) as total FROM tickets WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $my_tickets_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$my_tickets_result = mysqli_stmt_get_result($stmt);
$my_tickets = mysqli_fetch_assoc($my_tickets_result)['total'];

// Ambil event mendatang untuk countdown
$countdown_events_query = "SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 1";
$countdown_result = mysqli_query($conn, $countdown_events_query);
$next_event = mysqli_fetch_assoc($countdown_result);

// Cek apakah user adalah admin
$is_admin = is_admin();
$is_user = !$is_admin; // User biasa (non-admin)

// Waktu saat ini untuk pengecekan event selesai
date_default_timezone_set('Asia/Jakarta'); // Sesuaikan dengan timezone Anda
$current_datetime = date('Y-m-d H:i:s');
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Event Mahasiswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ====== MOBILE FIRST STYLES ====== */
        /* Reset dan base styles untuk mobile */
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

        /* ====== WELCOME SECTION MOBILE ====== */
        .welcome-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .welcome-section h2 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }

        .welcome-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            text-align: center;
        }

        .welcome-text p {
            color: #666;
            margin-bottom: 8px;
        }

        .admin-badge {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* ====== STATS GRID MOBILE ====== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: center;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px auto;
            color: white;
            font-size: 1.4rem;
        }

        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        /* ====== DASHBOARD SECTIONS MOBILE ====== */
        .dashboard-sections {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* ====== RECENT EVENTS MOBILE ====== */
        .recent-events {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .section-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }

        .section-header h3 {
            font-size: 1.3rem;
            color: #333;
            margin: 0;
        }

        .view-all {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* ====== EVENTS GRID MOBILE ====== */
        .events-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .event-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #eee;
        }

        .event-image-container {
            height: 160px;
            position: relative;
            overflow: hidden;
        }

        .event-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .event-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #667eea;
        }

        .event-content {
            padding: 15px;
        }

        .event-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }

        .event-organizer {
            color: #667eea;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }

        .event-details {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #666;
            font-size: 0.85rem;
        }

        .event-pricing {
            background: #f8f9ff;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            text-align: center;
        }

        .price-amount {
            font-size: 1.3rem;
            font-weight: 700;
            color: #667eea;
        }

        .available-tickets {
            background: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            color: #43e97b;
            font-weight: 600;
            display: inline-block;
            margin-top: 8px;
        }

        .buy-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            min-height: 44px;
        }

        /* ====== QUICK ACTIONS MOBILE ====== */
        .quick-actions {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
            min-height: 44px;
        }

        .action-btn i {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
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

        /* ====== NOTIFICATION MOBILE ====== */
        .login-notification {
            position: fixed;
            top: 80px;
            left: 12px;
            right: 12px;
            z-index: 9999;
        }

        .notification-content {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.95) 0%, rgba(33, 136, 56, 0.95) 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
            }
            
            .events-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .section-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                text-align: left;
            }
            
            .event-details {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .footer-content {
                flex-direction: row;
                justify-content: space-between;
                text-align: left;
            }
            
            .login-notification {
                left: auto;
                right: 20px;
                max-width: 400px;
            }
        }

        /* ====== MEDIA QUERIES UNTUK DESKTOP ====== */
        @media (min-width: 992px) {
            .container {
                max-width: 1200px;
            }
            
            .dashboard-sections {
                flex-direction: row;
                gap: 25px;
            }
            
            .recent-events {
                flex: 2;
            }
            
            .quick-actions {
                flex: 1;
                position: sticky;
                top: 100px;
                height: fit-content;
            }
            
            .events-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .event-details {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .welcome-section h2 {
                font-size: 2rem;
                text-align: left;
            }
            
            .welcome-content {
                flex-direction: row;
                justify-content: space-between;
                text-align: left;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-icon {
                width: 60px;
                height: 60px;
            }
        }

        /* ====== FIX UNTUK EVENT COMPLETED & SOLD OUT ====== */
        .event-completed-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .sold-out-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .completed-event {
            opacity: 0.9;
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
    </style>
</head>
<body>
    <!-- NOTIFIKASI LOGIN BERHASIL -->
    <?php if ($flash_message): ?>
        <div class="login-notification" id="loginNotification">
            <div class="notification-content">
                <div class="notification-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="notification-text">
                    <strong>Berhasil!</strong>
                    <p><?php echo $flash_message['message']; ?></p>
                </div>
                <button class="notification-close" onclick="closeNotification()" style="background:none;border:none;color:white;margin-left:10px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <header>
        <div class="container">
            <!-- Header utama dengan logo dan user menu -->
            <div class="header-main">
                <!-- Logo dan judul -->
                <div class="logo-area">
                    <a href="dashboard.php" class="logo-link">
                        <img src="logo/gallery2.png" alt="LIVE FEST Logo" class="logo-img" onerror="this.style.display='none'">
                        <h1 class="site-title">LIVE FEST</h1>
                    </a>
                </div>
                
                <!-- User menu -->
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                    <a href="logout.php" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> <span class="mobile-hide">Logout</span>
                    </a>
                </div>
            </div>
            
            <!-- Navigasi -->
            <nav>
                <ul>
                    <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> <span>Dashboard</span>
                    </a></li>
                    <li><a href="events.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> <span>Daftar Event</span>
                    </a></li>
                    
                    <?php if ($is_user): ?>
                        <li><a href="my_tickets.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'my_tickets.php' ? 'active' : ''; ?>">
                            <i class="fas fa-ticket-alt"></i> <span>Tiket Saya</span>
                        </a></li>
                    <?php endif; ?>
                    
                    <?php if ($is_admin): ?>
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
        <!-- Welcome Section -->
        <section class="welcome-section">
            <h2>Selamat Datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
            <div class="welcome-content">
                <div class="welcome-text">
                    <p>Nikmati pengalaman terbaik untuk berpartisipasi dalam berbagai event menarik.</p>
                    <?php if ($next_event): ?>
                        <p style="margin-top: 10px; color: #667eea; font-weight: 500;">
                            <i class="fas fa-clock"></i> Event berikutnya: <strong><?php echo htmlspecialchars($next_event['event_name']); ?></strong> 
                            pada <?php echo date('d F Y', strtotime($next_event['event_date'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php if ($is_admin): ?>
                    <div class="admin-badge">
                        <i class="fas fa-crown"></i> Administrator
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value"><?php echo $total_events; ?></div>
                <div class="stat-label">Total Event</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-music"></i>
                </div>
                <div class="stat-value"><?php echo $upcoming_events; ?></div>
                <div class="stat-label">Event Mendatang</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-value"><?php echo $my_tickets; ?></div>
                <div class="stat-label">Tiket Saya</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $is_admin ? 'Admin' : 'Mahasiswa'; ?></div>
                <div class="stat-label">Status Akun</div>
            </div>
        </div>

        <div class="dashboard-sections">
            <!-- Recent Events -->
            <section class="recent-events">
                <div class="section-header">
                    <h3><i class="fas fa-fire"></i> Event Terbaru</h3>
                    <a href="events.php" class="view-all">
                        Lihat Semua <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <div class="events-grid">
                    <?php if (mysqli_num_rows($events_result) > 0): ?>
                        <?php 
                        mysqli_data_seek($events_result, 0);
                        $card_index = 0;
                        ?>
                        <?php while($event = mysqli_fetch_assoc($events_result)): 
                            $card_index++;
                            $price = isset($event['price']) ? $event['price'] : 145000;
                            $formatted_price = 'Rp' . number_format($price, 0, ',', '.');
                            
                            // Tanggal dan waktu event
                            $event_date = $event['event_date'];
                            $event_time = '23:00:00';
                            
                            // Gabungkan tanggal dan waktu untuk pengecekan
                            $event_datetime = $event_date . ' ' . $event_time;
                            
                            // Hitung hari lagi
                            $event_date_obj = new DateTime($event_date);
                            $today_date_obj = new DateTime($current_date);
                            $days_until = $event_date_obj->diff($today_date_obj)->days;
                            
                            // Cek status event
                            $is_event_completed = false;
                            $is_event_today = ($event_date == $current_date);
                            $is_ticket_available = ($event['available_tickets'] > 0);
                            
                            if ($event_date < $current_date) {
                                $is_event_completed = true;
                            } elseif ($is_event_today && $current_time >= '23:00:00') {
                                $is_event_completed = true;
                            }
                            
                            // Tentukan badge yang akan ditampilkan
                            $show_completed_badge = $is_event_completed;
                            $show_sold_out_badge = (!$is_event_completed && !$is_ticket_available);
                        ?>
                            <div class="event-card <?php echo $is_event_completed ? 'completed-event' : ''; ?>">
                                <div class="event-image-container">
                                    <?php if (!empty($event['band_photo']) && file_exists('uploads/' . $event['band_photo'])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($event['band_photo']); ?>" 
                                             alt="<?php echo htmlspecialchars($event['event_name']); ?>"
                                             class="event-image">
                                    <?php else: ?>
                                        <div class="event-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                                            <i class="fas fa-music"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($show_completed_badge): ?>
                                        <div class="event-completed-badge">
                                            <i class="fas fa-calendar-times"></i> Event Selesai
                                        </div>
                                    <?php elseif ($show_sold_out_badge): ?>
                                        <div class="sold-out-badge">
                                            <i class="fas fa-times-circle"></i> Tiket Habis
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="event-type-badge">
                                        <i class="fas fa-tag"></i> <?php echo isset($event['event_type']) ? $event['event_type'] : 'Music'; ?>
                                    </div>
                                </div>
                                
                                <div class="event-content">
                                    <h3 class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></h3>
                                    <div class="event-organizer">
                                        <i class="fas fa-user"></i> <?php echo isset($event['organizer']) ? htmlspecialchars($event['organizer']) : 'KIAS FESTIVAL'; ?>
                                    </div>
                                    
                                    <div class="event-details">
                                        <div class="detail-item">
                                            <i class="fas fa-calendar"></i>
                                            <span><?php echo date('d M Y', strtotime($event['event_date'])); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-clock"></i>
                                            <span>15:00 - 23:00</span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($event['location']); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-hourglass-half"></i>
                                            <span>
                                                <?php if ($is_event_completed): ?>
                                                    <span style="color: #ff6b6b;">Selesai</span>
                                                <?php elseif ($is_event_today): ?>
                                                    <span style="color: #43e97b;">Hari Ini!</span>
                                                <?php else: ?>
                                                    <?php echo $days_until; ?> hari lagi
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="event-pricing">
                                        <div class="price-info">
                                            <h4 style="font-size: 0.9rem; color: #666; margin-bottom: 5px;">Harga Tiket</h4>
                                            <div class="price-amount"><?php echo $formatted_price; ?></div>
                                        </div>
                                        <div class="available-tickets">
                                            <i class="fas fa-ticket-alt"></i> 
                                            <?php if ($is_event_completed): ?>
                                                <span style="color: #ff6b6b;">Event Selesai</span>
                                            <?php elseif (!$is_ticket_available): ?>
                                                <span style="color: #95a5a6;">Tiket Habis</span>
                                            <?php else: ?>
                                                <?php echo $event['available_tickets']; ?> tersedia
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($is_user): ?>
                                    <div class="user-actions mt-2">
                                        <?php if (!$is_event_completed && $is_ticket_available): ?>
                                            <a href="buy_ticket.php?event_id=<?php echo $event['id']; ?>" class="buy-button">
                                                <i class="fas fa-shopping-cart"></i> Beli Tiket
                                            </a>
                                        <?php elseif ($is_event_completed): ?>
                                            <button class="buy-button" style="background: #ff6b6b; cursor: not-allowed;" disabled>
                                                <i class="fas fa-calendar-times"></i> Event Selesai
                                            </button>
                                        <?php elseif (!$is_ticket_available): ?>
                                            <button class="buy-button" style="background: #95a5a6; cursor: not-allowed;" disabled>
                                                <i class="fas fa-times-circle"></i> Tiket Habis
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_admin): ?>
                                    <div class="user-actions mt-2">
                                        <button class="buy-button" style="background: #95a5a6; cursor: not-allowed;" disabled>
                                            <i class="fas fa-user-shield"></i> Mode Admin
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px; background: #f8f9ff; border-radius: 12px; grid-column: 1 / -1;">
                            <i class="fas fa-calendar-times" style="font-size: 2.5rem; color: #667eea; margin-bottom: 10px;"></i>
                            <h3 style="color: #333; margin-bottom: 5px;">Belum ada event</h3>
                            <p style="color: #666;">Event akan segera hadir. Nantikan informasi terbaru!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Quick Actions -->
            <aside class="quick-actions">
                <div class="section-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                
                <div class="action-buttons">
                    <a href="events.php" class="action-btn">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Lihat Semua Event</span>
                    </a>
                    
                    <?php if ($is_user): ?>
                        <a href="my_tickets.php" class="action-btn">
                            <i class="fas fa-ticket-alt"></i>
                            <span>Tiket Saya</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($is_admin): ?>
                        <a href="add_event.php" class="action-btn">
                            <i class="fas fa-plus-circle"></i>
                            <span>Tambah Event Baru</span>
                        </a>
                        
                        <a href="analytics.php" class="action-btn">
                            <i class="fas fa-chart-bar"></i>
                            <span>Analitik & Laporan</span>
                        </a>
                        
                        <a href="manage_tickets.php" class="action-btn">
                            <i class="fas fa-tasks"></i>
                            <span>Manajemen Tiket</span>
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($next_event): ?>
                <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; color: white;">
                    <h4 style="margin-bottom: 5px; display: flex; align-items: center; gap: 8px; font-size: 1rem;">
                        <i class="fas fa-star"></i> Event Berikutnya
                    </h4>
                    <p style="font-size: 0.85rem; opacity: 0.9;"><?php echo htmlspecialchars($next_event['event_name']); ?></p>
                    <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 0.8rem;">
                        <span><i class="fas fa-calendar"></i> <?php echo date('d M', strtotime($next_event['event_date'])); ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($next_event['location']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </aside>
        </div>
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
        // Notification functions
        function closeNotification() {
            const notification = document.getElementById('loginNotification');
            if (notification) {
                notification.style.transition = 'opacity 0.3s ease';
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 300);
            }
        }

        // Auto-hide notification after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notification = document.getElementById('loginNotification');
            if (notification) {
                setTimeout(() => {
                    closeNotification();
                }, 5000);
            }
            
            // Add touch-friendly improvements
            const buttons = document.querySelectorAll('.btn, .buy-button, .action-btn, nav ul li a');
            buttons.forEach(button => {
                button.style.cursor = 'pointer';
                button.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                button.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        });

        // Simple image error handler
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('img');
            images.forEach(img => {
                img.onerror = function() {
                    this.style.display = 'none';
                };
            });
        });
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>