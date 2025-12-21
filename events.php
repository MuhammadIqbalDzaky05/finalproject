<?php
include 'config.php';

// Cek apakah user adalah admin
$is_admin = is_admin();
$is_user = !$is_admin; // User biasa (non-admin)
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Daftar Event - Event Mahasiswa</title>
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

        .page-header h2 {
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

        /* ====== FILTER SECTION MOBILE ====== */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .search-bar {
            position: relative;
            margin-bottom: 15px;
        }

        .search-bar i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 1rem;
        }

        .search-bar input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            background: #f8f9fa;
        }

        .filter-options {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-group select {
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
        }

        /* ====== EVENTS GRID MOBILE ====== */
        .events-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        .event-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #eee;
            position: relative;
        }

        /* EVENT STATUS BADGE */
        .event-status {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 10;
        }

        .event-status.upcoming {
            background: #43e97b;
            color: white;
        }

        .event-status.ongoing {
            background: #fa709a;
            color: white;
        }

        .event-status.past {
            background: #667eea;
            color: white;
        }

        /* EVENT PHOTO */
        .event-photo {
            height: 180px;
            position: relative;
            overflow: hidden;
        }

        .event-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .no-photo {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
        }

        .band-name-badge {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: rgba(255, 255, 255, 0.95);
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #667eea;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* EVENT CONTENT */
        .event-header {
            padding: 15px 15px 0;
        }

        .event-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .event-organizer {
            color: #667eea;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .event-details {
            padding: 15px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8f9ff;
            border-radius: 8px;
        }

        .detail-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .detail-content h4 {
            color: #333;
            margin-bottom: 2px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .detail-content p {
            color: #666;
            font-size: 0.8rem;
            line-height: 1.3;
        }

        /* EVENT DESCRIPTION */
        .event-description {
            background: #f8f9ff;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .event-description h4 {
            color: #333;
            margin-bottom: 6px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .event-description p {
            color: #555;
            line-height: 1.5;
            font-size: 0.85rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* EVENT ACTION */
        .event-action {
            text-align: center;
        }

        .buy-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            min-height: 44px;
            text-decoration: none;
        }

        .buy-button.disabled {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            cursor: not-allowed;
        }

        .ticket-info {
            text-align: center;
            margin-top: 10px;
            color: #666;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
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

        /* ADD EVENT BUTTON */
        .add-event-btn {
            text-align: center;
            margin-top: 25px;
        }

        /* FOOTER */
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
            
            .page-header h2 {
                font-size: 2.2rem;
            }
            
            .filter-options {
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
            }
            
            .events-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .detail-grid {
                grid-template-columns: repeat(2, 1fr);
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
            
            .page-header h2 {
                font-size: 2.5rem;
            }
            
            .events-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .event-photo {
                height: 200px;
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
                    <?php if (is_logged_in()): ?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <a href="logout.php" class="btn btn-logout">
                            <i class="fas fa-sign-out-alt"></i> <span class="mobile-hide">Logout</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn">
                            <i class="fas fa-sign-in-alt"></i> <span class="mobile-hide">Login</span>
                        </a>
                    <?php endif; ?>
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
        <div class="page-header">
            <h2>Semua Event Mahasiswa</h2>
            <p>Temukan pengalaman berbagai event menarik yang kami hadirkan khusus untuk Mahas.</p>
        </div>
        
        <!-- Search and Filter Section -->
        <div class="filter-section">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Cari event...">
            </div>
            
            <div class="filter-options">
                <div class="filter-group">
                    <label for="statusFilter"><i class="fas fa-filter"></i> Status Event</label>
                    <select id="statusFilter">
                        <option value="all">Semua Status</option>
                        <option value="upcoming">Coming Soon</option>
                        <option value="ongoing">Hari Ini</option>
                        <option value="past">Selesai</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="typeFilter"><i class="fas fa-tag"></i> Tipe Event</label>
                    <select id="typeFilter">
                        <option value="all">Semua Tipe</option>
                        <option value="Music Festival">Music Festival</option>
                        <option value="Concert">Concert</option>
                        <option value="Acoustic Night">Acoustic Night</option>
                        <option value="Battle of Band">Battle of Band</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="sortFilter"><i class="fas fa-sort"></i> Urutkan</label>
                    <select id="sortFilter">
                        <option value="newest">Terbaru</option>
                        <option value="oldest">Terlama</option>
                        <option value="price-low">Harga Terendah</option>
                        <option value="price-high">Harga Tertinggi</option>
                    </select>
                </div>
            </div>
        </div>
        
        <?php
        // Ambil semua event, urutkan dari yang terbaru
        $query = "SELECT * FROM events ORDER BY event_date DESC";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) > 0) {
            echo '<div class="events-grid" id="eventsContainer">';
            
            $card_index = 0;
            while($row = mysqli_fetch_assoc($result)) {
                $card_index++;
                // Format data
                $formatted_price = 'Rp' . number_format($row['price'], 0, ',', '.');
                $event_date = date('d F Y', strtotime($row['event_date']));
                $event_time = isset($row['event_time']) ? date('H:i', strtotime($row['event_time'])) : '15:00';
                $day_names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                $day_index = date('w', strtotime($row['event_date']));
                $day_name = $day_names[$day_index];
                
                // Tentukan status event
                $event_timestamp = strtotime($row['event_date']);
                $current_timestamp = time();
                $status = '';
                $status_class = '';
                
                if ($event_timestamp > $current_timestamp) {
                    $status = 'COMING SOON';
                    $status_class = 'upcoming';
                } elseif ($event_timestamp == $current_timestamp) {
                    $status = 'HARI INI';
                    $status_class = 'ongoing';
                } else {
                    $status = 'SELESAI';
                    $status_class = 'past';
                }
                
                // Short description
                $short_description = !empty($row['description']) ? 
                    substr(strip_tags($row['description']), 0, 120) . '...' : 
                    'Event musik yang akan menghadirkan pengalaman tak terlupakan.';
                ?>
                
                <div class="event-card" 
                     data-status="<?php echo $status_class; ?>"
                     data-type="<?php echo htmlspecialchars($row['event_type']); ?>"
                     data-price="<?php echo $row['price']; ?>"
                     data-date="<?php echo $row['event_date']; ?>">
                    
                    <?php if ($status): ?>
                        <div class="event-status <?php echo $status_class; ?>">
                            <?php echo $status; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Event Photo -->
                    <div class="event-photo">
                        <?php if (!empty($row['band_photo']) && file_exists('uploads/' . $row['band_photo'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($row['band_photo']); ?>" 
                                 alt="Foto <?php echo htmlspecialchars($row['band_name'] ?? 'Band'); ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="no-photo">
                                🎵
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($row['band_name'])): ?>
                            <div class="band-name-badge">
                                <i class="fas fa-guitar"></i> <?php echo htmlspecialchars($row['band_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="event-header">
                        <h3 class="event-title"><?php echo htmlspecialchars($row['event_name']); ?></h3>
                        <p class="event-organizer">
                            <i class="fas fa-user-circle"></i> oleh: <?php echo isset($row['organizer']) ? htmlspecialchars($row['organizer']) : 'KIAS FESTIVAL'; ?>
                        </p>
                    </div>

                    <div class="event-details">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="detail-content">
                                    <h4>Tanggal</h4>
                                    <p><?php echo $day_name; ?>, <?php echo $event_date; ?></p>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="detail-content">
                                    <h4>Waktu</h4>
                                    <p><?php echo $event_time; ?> WIB</p>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="detail-content">
                                    <h4>Lokasi</h4>
                                    <p><?php echo htmlspecialchars($row['location']); ?></p>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <div class="detail-content">
                                    <h4>Harga</h4>
                                    <p><?php echo $formatted_price; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="event-description">
                            <h4><i class="fas fa-info-circle"></i> Deskripsi</h4>
                            <p><?php echo htmlspecialchars($short_description); ?></p>
                        </div>

                        <?php if ($is_user): ?>
                        <div class="event-action">
                            <?php if (is_logged_in() && $row['available_tickets'] > 0 && $event_timestamp >= $current_timestamp): ?>
                                <a href="buy_ticket.php?event_id=<?php echo $row['id']; ?>" class="buy-button">
                                    <i class="fas fa-shopping-cart"></i> Beli Tiket - <?php echo $formatted_price; ?>
                                </a>
                                <p class="ticket-info">
                                    <i class="fas fa-ticket-alt"></i> 
                                    <?php echo $row['available_tickets']; ?> tiket tersedia
                                </p>
                            <?php elseif (is_logged_in() && $row['available_tickets'] <= 0): ?>
                                <button class="buy-button disabled" disabled>
                                    <i class="fas fa-times-circle"></i> Tiket Habis
                                </button>
                                <p class="ticket-info">
                                    <i class="fas fa-exclamation-circle"></i> 
                                    Maaf, tiket sudah terjual habis
                                </p>
                            <?php elseif (is_logged_in() && $event_timestamp < $current_timestamp): ?>
                                <button class="buy-button disabled" disabled>
                                    <i class="fas fa-calendar-times"></i> Event Selesai
                                </button>
                                <p class="ticket-info">
                                    <i class="fas fa-info-circle"></i> 
                                    Event ini sudah berakhir
                                </p>
                            <?php else: ?>
                                <a href="login.php" class="buy-button">
                                    <i class="fas fa-sign-in-alt"></i> Login untuk Beli Tiket
                                </a>
                                <p class="ticket-info">
                                    <i class="fas fa-lock"></i> 
                                    Silakan login untuk membeli tiket
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php
            }
            
            echo '</div>';
            
            // Tombol tambah event untuk admin
            if (is_logged_in() && is_admin()) {
                echo '<div class="add-event-btn">';
                echo '<a href="add_event.php" class="btn" style="padding: 12px 24px; font-size: 1rem;">';
                echo '<i class="fas fa-plus-circle"></i> Tambah Event Baru';
                echo '</a>';
                echo '</div>';
            }
            
        } else {
            echo '<div class="no-events">';
            echo '<i class="fas fa-calendar-times"></i>';
            echo '<h3>Belum Ada Event</h3>';
            echo '<p>Maaf, saat ini belum ada event yang tersedia. Event akan segera hadir untuk Anda!</p>';
            
            if (is_logged_in() && is_admin()) {
                echo '<a href="add_event.php" class="btn" style="padding: 12px 24px;">';
                echo '<i class="fas fa-plus-circle"></i> Tambahkan Event Pertama';
                echo '</a>';
            } else {
                echo '<p style="color: #667eea; font-weight: 500; margin-top: 15px;">Nantikan event menarik dari kami!</p>';
            }
            echo '</div>';
        }
        ?>
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
        // Filter functionality
        function initializeFilters() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const typeFilter = document.getElementById('typeFilter');
            const sortFilter = document.getElementById('sortFilter');
            const eventCards = document.querySelectorAll('.event-card');
            
            function filterEvents() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value;
                const typeValue = typeFilter.value;
                const sortValue = sortFilter.value;
                
                let visibleCards = 0;
                
                eventCards.forEach(card => {
                    const title = card.querySelector('.event-title').textContent.toLowerCase();
                    const location = card.querySelector('.detail-item:nth-child(3) p').textContent.toLowerCase();
                    const bandName = card.querySelector('.band-name-badge')?.textContent.toLowerCase() || '';
                    const cardStatus = card.dataset.status;
                    const cardType = card.dataset.type.toLowerCase();
                    
                    // Search filter
                    const matchesSearch = searchTerm === '' || 
                        title.includes(searchTerm) || 
                        location.includes(searchTerm) ||
                        bandName.includes(searchTerm);
                    
                    // Status filter
                    const matchesStatus = statusValue === 'all' || cardStatus === statusValue;
                    
                    // Type filter
                    const matchesType = typeValue === 'all' || cardType === typeValue.toLowerCase();
                    
                    // Show/hide card
                    if (matchesSearch && matchesStatus && matchesType) {
                        card.style.display = 'block';
                        visibleCards++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Sort cards
                const container = document.getElementById('eventsContainer');
                if (container) {
                    const cardsArray = Array.from(eventCards)
                        .filter(card => card.style.display !== 'none');
                    
                    // Sort based on selected option
                    cardsArray.sort((a, b) => {
                        switch(sortValue) {
                            case 'newest':
                                return new Date(b.dataset.date) - new Date(a.dataset.date);
                            case 'oldest':
                                return new Date(a.dataset.date) - new Date(b.dataset.date);
                            case 'price-low':
                                return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                            case 'price-high':
                                return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                            default:
                                return 0;
                        }
                    });
                    
                    // Reorder cards in DOM
                    cardsArray.forEach(card => {
                        container.appendChild(card);
                    });
                }
                
                // Show no results message if needed
                showNoResultsMessage(visibleCards === 0);
            }
            
            function showNoResultsMessage(show) {
                let noResults = document.getElementById('noResultsMessage');
                
                if (show && !noResults) {
                    noResults = document.createElement('div');
                    noResults.id = 'noResultsMessage';
                    noResults.className = 'no-events';
                    noResults.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h3>Tidak Ditemukan</h3>
                        <p>Tidak ada event yang sesuai dengan kriteria pencarian Anda.</p>
                        <button onclick="resetFilters()" class="btn" style="margin-top: 1rem;">
                            <i class="fas fa-redo"></i> Reset Filter
                        </button>
                    `;
                    
                    const container = document.querySelector('.events-grid') || document.querySelector('main .container');
                    container.appendChild(noResults);
                } else if (!show && noResults) {
                    noResults.remove();
                }
            }
            
            function resetFilters() {
                searchInput.value = '';
                statusFilter.value = 'all';
                typeFilter.value = 'all';
                sortFilter.value = 'newest';
                filterEvents();
            }
            
            // Add event listeners
            searchInput.addEventListener('input', filterEvents);
            statusFilter.addEventListener('change', filterEvents);
            typeFilter.addEventListener('change', filterEvents);
            sortFilter.addEventListener('change', filterEvents);
            
            // Initialize
            filterEvents();
        }
        
        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeFilters();
            
            // Add touch-friendly improvements
            const buttons = document.querySelectorAll('.btn, .buy-button, nav ul li a');
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
                    "Cari berdasarkan lokasi..."
                ];
                let index = 0;
                
                setInterval(() => {
                    searchInput.placeholder = placeholders[index];
                    index = (index + 1) % placeholders.length;
                }, 3000);
            }
        });
        
        // Reset filters function (for button)
        window.resetFilters = function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const typeFilter = document.getElementById('typeFilter');
            const sortFilter = document.getElementById('sortFilter');
            
            if (searchInput) searchInput.value = '';
            if (statusFilter) statusFilter.value = 'all';
            if (typeFilter) typeFilter.value = 'all';
            if (sortFilter) sortFilter.value = 'newest';
            
            // Trigger filter
            if (typeof filterEvents === 'function') {
                filterEvents();
            }
        };
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>