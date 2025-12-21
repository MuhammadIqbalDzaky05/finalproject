<?php
include 'config.php';
require_login();

if (!is_admin()) {
    header("Location: dashboard.php");
    exit();
}
?>
    
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit Events - Event Mahasiswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
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

        .btn-edit {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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

        /* ====== DASHBOARD HEADER MOBILE ====== */
        .dashboard-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .dashboard-header h2 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .dashboard-header p {
            color: #666;
            font-size: 0.95rem;
            max-width: 500px;
            margin: 0 auto;
        }

        /* ====== STATUS INDICATOR MOBILE ====== */
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-active {
            background: #43e97b;
        }

        /* ====== ALERT MESSAGES MOBILE ====== */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }

        .alert-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }

        .alert-error {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }

        /* ====== SEARCH BOX MOBILE ====== */
        .search-box {
            margin-bottom: 15px;
        }

        .search-container {
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            background: #f8f9ff;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 1rem;
        }

        /* ====== EVENTS LIST MOBILE (REPLACE TABLE) ====== */
        .events-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .event-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #eee;
            position: relative;
            overflow: hidden;
        }

        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* EVENT HEADER */
        .event-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }

        .band-photo {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e0e0e0;
            flex-shrink: 0;
        }

        .no-photo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .event-title-section {
            flex: 1;
        }

        .event-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .band-name {
            font-size: 0.85rem;
            color: #667eea;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .event-type {
            display: inline-block;
            padding: 3px 8px;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* EVENT DETAILS GRID */
        .event-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 12px;
            padding: 12px;
            background: #f8f9ff;
            border-radius: 8px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            font-size: 0.9rem;
            color: #333;
            font-weight: 600;
        }

        .date-value {
            color: #333;
        }

        .time-value {
            color: #667eea;
        }

        .price-value {
            color: #2e7d32;
        }

        .ticket-value {
            color: #333;
        }

        /* TICKET STATUS */
        .ticket-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 5px;
        }

        .status-available {
            background: rgba(67, 233, 123, 0.2);
            color: #2e7d32;
        }

        .status-limited {
            background: rgba(250, 112, 154, 0.2);
            color: #d32f2f;
        }

        .status-soldout {
            background: rgba(255, 107, 107, 0.2);
            color: #b71c1c;
        }

        /* GOOGLE CALENDAR BADGE */
        .google-calendar-badge {
            font-size: 0.7rem;
            color: #1a73e8;
            background: rgba(26, 115, 232, 0.1);
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 5px;
        }

        /* EVENT FOOTER */
        .event-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #eee;
        }

        .event-description {
            font-size: 0.85rem;
            color: #666;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
            margin-right: 10px;
        }

        .action-btn {
            padding: 8px 12px;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
            min-height: 36px;
        }

        /* NO EVENTS */
        .no-events {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .no-events i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
            display: block;
        }

        .no-events h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.4rem;
        }

        .no-events p {
            color: #666;
            margin-bottom: 20px;
            font-size: 0.95rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
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

        /* ====== MODAL FOR IMAGE PREVIEW ====== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            max-width: 90%;
            max-height: 80%;
            border-radius: 8px;
            overflow: hidden;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
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
            
            .dashboard-header h2 {
                font-size: 2.2rem;
            }
            
            .events-list {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
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
            
            .dashboard-header h2 {
                font-size: 2.5rem;
            }
            
            .events-list {
                grid-template-columns: repeat(3, 1fr);
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
        
        .event-card {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }
        
        /* Delay for cards */
        .event-card:nth-child(1) { animation-delay: 0.1s; }
        .event-card:nth-child(2) { animation-delay: 0.2s; }
        .event-card:nth-child(3) { animation-delay: 0.3s; }
        .event-card:nth-child(4) { animation-delay: 0.4s; }
        .event-card:nth-child(5) { animation-delay: 0.5s; }
        .event-card:nth-child(6) { animation-delay: 0.6s; }
        .event-card:nth-child(7) { animation-delay: 0.7s; }
        .event-card:nth-child(8) { animation-delay: 0.8s; }
        .event-card:nth-child(9) { animation-delay: 0.9s; }
        .event-card:nth-child(10) { animation-delay: 1.0s; }
    </style>
</head>
<body>
    <!-- Modal for Image Preview -->
    <div id="imageModal" class="modal">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

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
        <div class="dashboard-header">
            <h2>Kelola Event</h2>
            <p>Edit atau update event yang sudah dibuat. Klik tombol Edit untuk mengubah detail event.</p>
            <div style="margin-top: 10px; display: flex; justify-content: center; align-items: center; gap: 8px;">
                <span class="status-indicator status-active"></span>
                <span style="color: #666; font-size: 0.9rem;">Total: 
                    <?php 
                    $count_query = "SELECT COUNT(*) as total FROM events";
                    $count_result = mysqli_query($conn, $count_query);
                    $total = mysqli_fetch_assoc($count_result)['total'];
                    echo $total . " event";
                    ?>
                </span>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="search-box">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Cari event...">
            </div>
        </div>

        <div class="events-list" id="eventsList">
            <?php
            // Ambil semua event dari database
            $query = "SELECT * FROM events ORDER BY event_date DESC, event_time DESC";
            $result = mysqli_query($conn, $query);
            
            if (mysqli_num_rows($result) > 0): 
                $card_index = 0;
                while ($event = mysqli_fetch_assoc($result)): 
                    $card_index++;
            ?>
            <div class="event-card" 
                 data-name="<?php echo strtolower(htmlspecialchars($event['event_name'])); ?>"
                 data-band="<?php echo strtolower(htmlspecialchars($event['band_name'] ?? '')); ?>"
                 data-location="<?php echo strtolower(htmlspecialchars($event['location'])); ?>"
                 data-type="<?php echo strtolower(htmlspecialchars($event['event_type'])); ?>">
                
                <!-- Event Header -->
                <div class="event-header">
                    <!-- Band Photo -->
                    <?php if (!empty($event['band_photo']) && file_exists('uploads/' . $event['band_photo'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($event['band_photo']); ?>" 
                             alt="Foto <?php echo htmlspecialchars($event['band_name'] ?? 'Band'); ?>"
                             class="band-photo"
                             onclick="openModal(this.src)"
                             style="cursor: pointer;">
                    <?php else: ?>
                        <div class="no-photo" onclick="openModal(null)" style="cursor: pointer;">
                            🎵
                        </div>
                    <?php endif; ?>

                    <!-- Event Title Section -->
                    <div class="event-title-section">
                        <h3 class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></h3>
                        <?php if (!empty($event['band_name'])): ?>
                            <div class="band-name"><?php echo htmlspecialchars($event['band_name']); ?></div>
                        <?php endif; ?>
                        <span class="event-type"><?php echo htmlspecialchars($event['event_type']); ?></span>
                        
                        <?php if (!empty($event['organizer'])): ?>
                            <div style="font-size: 0.8rem; color: #666; margin-top: 3px;">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($event['organizer']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Event Details Grid -->
                <div class="event-details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Tanggal</span>
                        <span class="detail-value date-value"><?php echo date('d M Y', strtotime($event['event_date'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Waktu</span>
                        <span class="detail-value time-value"><?php echo date('H:i', strtotime($event['event_time'])); ?> WIB</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Harga</span>
                        <span class="detail-value price-value">Rp <?php echo number_format($event['price'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Tiket</span>
                        <span class="detail-value ticket-value">
                            <?php echo $event['available_tickets']; ?>
                            <?php if ($event['available_tickets'] <= 0): ?>
                                <span class="ticket-status status-soldout">HABIS</span>
                            <?php elseif ($event['available_tickets'] <= 10): ?>
                                <span class="ticket-status status-limited">TERBATAS</span>
                            <?php else: ?>
                                <span class="ticket-status status-available">TERSEDIA</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- Location -->
                <div style="font-size: 0.85rem; color: #333; margin-bottom: 8px;">
                    <i class="fas fa-map-marker-alt" style="color: #667eea; margin-right: 5px;"></i>
                    <?php echo htmlspecialchars($event['location']); ?>
                </div>

                <!-- Google Calendar Badge -->
                <?php if (!empty($event['google_event_id'])): ?>
                    <div class="google-calendar-badge">
                        <i class="fab fa-google"></i> Synced to Google Calendar
                    </div>
                <?php endif; ?>

                <!-- Event Footer -->
                <div class="event-footer">
                    <?php if (!empty($event['description'])): ?>
                        <div class="event-description">
                            <?php echo substr(htmlspecialchars($event['description']), 0, 100); ?>
                            <?php if (strlen($event['description']) > 100): ?>...<?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <a href="edit_event_form.php?id=<?php echo $event['id']; ?>" 
                       class="action-btn">
                        <i class="fas fa-edit"></i> <span class="mobile-hide">Edit</span>
                    </a>
                </div>
            </div>
            <?php 
                endwhile;
            else: 
            ?>
            <div class="no-events">
                <i class="fas fa-calendar-times"></i>
                <h3>📭 Tidak ada event yang ditemukan</h3>
                <p>Belum ada event yang dibuat. Mulai dengan menambahkan event pertama Anda!</p>
                <a href="add_event.php" class="btn" style="padding: 12px 24px;">
                    <i class="fas fa-plus-circle"></i> Tambah Event Pertama
                </a>
            </div>
            <?php endif; ?>
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
        // Modal functionality
        function openModal(src) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            
            if (src) {
                modalImg.src = src;
                modal.style.display = 'flex';
            } else {
                modal.style.display = 'none';
            }
        }

        function closeModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Close modal on outside click
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Search functionality
        function initializeSearch() {
            const searchInput = document.getElementById('searchInput');
            const eventCards = document.querySelectorAll('.event-card');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                eventCards.forEach(card => {
                    const eventName = card.dataset.name;
                    const bandName = card.dataset.band;
                    const location = card.dataset.location;
                    const eventType = card.dataset.type;
                    
                    const matchesSearch = searchTerm === '' || 
                        eventName.includes(searchTerm) ||
                        bandName.includes(searchTerm) ||
                        location.includes(searchTerm) ||
                        eventType.includes(searchTerm);
                    
                    if (matchesSearch) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Show no results message if needed
                const visibleCards = Array.from(eventCards).filter(card => card.style.display !== 'none');
                showNoResultsMessage(visibleCards.length === 0);
            });
        }

        // Show no results message
        function showNoResultsMessage(show) {
            let message = document.getElementById('noResultsMessage');
            
            if (show && !message) {
                message = document.createElement('div');
                message.id = 'noResultsMessage';
                message.className = 'no-events';
                message.innerHTML = `
                    <i class="fas fa-search"></i>
                    <h3>Event Tidak Ditemukan</h3>
                    <p>Tidak ada event yang sesuai dengan kriteria pencarian Anda.</p>
                    <button onclick="clearSearch()" class="btn" style="margin-top: 1rem;">
                        <i class="fas fa-redo"></i> Tampilkan Semua Event
                    </button>
                `;
                
                const eventsList = document.getElementById('eventsList');
                if (eventsList) {
                    eventsList.appendChild(message);
                }
            } else if (!show && message) {
                message.remove();
            }
        }

        // Clear search function
        window.clearSearch = function() {
            const searchInput = document.getElementById('searchInput');
            const eventCards = document.querySelectorAll('.event-card');
            
            searchInput.value = '';
            
            eventCards.forEach(card => {
                card.style.display = 'block';
            });
            
            const message = document.getElementById('noResultsMessage');
            if (message) {
                message.remove();
            }
        };

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeSearch();
            
            // Add touch-friendly improvements
            const buttons = document.querySelectorAll('.btn, .action-btn, nav ul li a');
            buttons.forEach(button => {
                button.style.cursor = 'pointer';
                button.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                button.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
            
            // Simple image error handler
            const images = document.querySelectorAll('img');
            images.forEach(img => {
                img.onerror = function() {
                    this.style.display = 'none';
                };
            });
            
            // Simple search placeholder animation
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                const placeholders = [
                    "Cari event...",
                    "Cari berdasarkan nama...",
                    "Cari berdasarkan band...",
                    "Cari berdasarkan lokasi..."
                ];
                let index = 0;
                
                setInterval(() => {
                    searchInput.placeholder = placeholders[index];
                    index = (index + 1) % placeholders.length;
                }, 3000);
            }
        });
    </script>
</body>
</html>
<?php
// Tutup koneksi database
mysqli_close($conn);
?>