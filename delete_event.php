<?php
include 'config.php';
require_admin();

// Ambil semua event untuk dipilih hapus
$query = "SELECT * FROM events ORDER BY event_date DESC";
$result = mysqli_query($conn, $query);

// Proses hapus event jika ada parameter id
if (isset($_GET['id'])) {
    $event_id = $_GET['id'];
    
    try {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        // Ambil data event termasuk google_event_id
        $event_query = "SELECT * FROM events WHERE id = ?";
        $stmt = mysqli_prepare($conn, $event_query);
        mysqli_stmt_bind_param($stmt, "i", $event_id);
        mysqli_stmt_execute($stmt);
        $event_result = mysqli_stmt_get_result($stmt);
        $event = mysqli_fetch_assoc($event_result);

        if ($event) {
            // 1. HAPUS SEMUA TIKET TERKAIT TERLEBIH DAHULU
            $delete_tickets_query = "DELETE FROM tickets WHERE event_id = ?";
            $delete_tickets_stmt = mysqli_prepare($conn, $delete_tickets_query);
            mysqli_stmt_bind_param($delete_tickets_stmt, "i", $event_id);
            mysqli_stmt_execute($delete_tickets_stmt);
            
            // 2. Hapus dari Google Calendar jika ada google_event_id
            $google_success = false;
            if (!empty($event['google_event_id'])) {
                $google_result = deleteEventFromGoogleCalendar($event['google_event_id']);
                $google_success = $google_result['success'];
            }
            
            // 3. Hapus dari database
            $delete_query = "DELETE FROM events WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($delete_stmt, "i", $event_id);
            
            if (mysqli_stmt_execute($delete_stmt)) {
                mysqli_commit($conn); // Commit transaction
                
                if ($google_success) {
                    $_SESSION['success'] = "✅ Event '{$event['event_name']}' berhasil dihapus dari sistem dan Google Calendar!";
                } else {
                    $_SESSION['success'] = "✅ Event '{$event['event_name']}' berhasil dihapus dari sistem!";
                }
            } else {
                mysqli_rollback($conn); // Rollback jika ada error
                $_SESSION['error'] = "❌ Gagal menghapus event '{$event['event_name']}': " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error'] = "❌ Event tidak ditemukan";
        }
        
    } catch (Exception $e) {
        mysqli_rollback($conn); // Rollback jika ada exception
        $_SESSION['error'] = "❌ Error: " . $e->getMessage();
    }
    
    header("Location: delete_event.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hapus Event - LIVE FEST</title>
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

        nav ul li a:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        nav ul li a.active {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            border-color: #ff6b6b;
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

        .danger-badge {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 10px 0;
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

        /* ====== DANGER ZONE MOBILE ====== */
        .danger-zone {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe6e6 100%);
            border: 2px solid #ff6b6b;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .danger-zone h4 {
            color: #d32f2f;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }

        .danger-zone ul {
            margin: 10px 0 10px 20px;
            font-size: 0.85rem;
        }

        .danger-zone li {
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ====== SEARCH BOX MOBILE ====== */
        .search-box {
            margin-bottom: 15px;
        }

        .search-container {
            position: relative;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #ff6b6b;
            border-radius: 50px;
            font-size: 0.95rem;
            background: #fff5f5;
            box-shadow: 0 2px 10px rgba(255, 107, 107, 0.1);
        }

        .search-box input:focus {
            outline: none;
            border-color: #d32f2f;
            background: white;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #ff6b6b;
            font-size: 1.1rem;
        }

        /* ====== EVENTS TABLE MOBILE ====== */
        .events-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            position: relative;
        }

        .events-table::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }

        /* Mobile Card View */
        .event-cards {
            display: none;
            flex-direction: column;
            gap: 1rem;
            padding: 1rem;
        }

        .event-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            border: 2px solid #ffe6e6;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }

        .event-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.1);
        }

        .event-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.8rem;
        }

        .event-name {
            font-weight: 700;
            color: #333;
            font-size: 1rem;
            flex: 1;
            margin-right: 1rem;
        }

        .event-type {
            color: #ff6b6b;
            font-weight: 600;
            font-size: 0.8rem;
            background: rgba(255, 107, 107, 0.1);
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            white-space: nowrap;
        }

        .event-info {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #555;
        }

        .info-item i {
            color: #ff6b6b;
            width: 16px;
        }

        .info-value {
            font-weight: 500;
            color: #333;
        }

        .tickets-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: rgba(255, 107, 107, 0.05);
            border-radius: 6px;
        }

        .ticket-count {
            font-weight: 700;
            font-size: 1.2rem;
            color: #333;
        }

        .ticket-status {
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
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

        .event-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-delete-action {
            flex: 1;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 0.7rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .btn-delete-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 107, 0.3);
        }

        .google-calendar-badge {
            font-size: 0.7rem;
            color: #1a73e8;
            background: rgba(26, 115, 232, 0.1);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-top: 0.5rem;
        }

        /* Desktop Table View */
        .events-table table {
            width: 100%;
            border-collapse: collapse;
            display: table;
        }

        @media (max-width: 768px) {
            .events-table table {
                display: none;
            }
            
            .event-cards {
                display: flex;
            }
        }

        @media (min-width: 769px) {
            .event-cards {
                display: none;
            }
        }

        /* Table Styling */
        thead {
            background: linear-gradient(135deg, #fff5f5 0%, #ffebee 100%);
        }

        th, td {
            padding: 1rem 0.8rem;
            text-align: left;
            border-bottom: 1px solid #ffe6e6;
        }

        th {
            font-weight: 600;
            color: #d32f2f;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #fff5f5 0%, #ffebee 100%);
            z-index: 10;
        }

        tbody tr:hover {
            background: linear-gradient(135deg, #fff5f5 0%, #ffebee 100%);
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* No Events */
        .no-events {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }

        .no-events i {
            font-size: 3rem;
            color: #ff6b6b;
            margin-bottom: 1rem;
            display: block;
        }

        .no-events h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .no-events p {
            margin-bottom: 1.5rem;
            font-size: 1rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Confirmation Modal */
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
            padding: 1rem;
        }

        .modal-content {
            background: white;
            max-width: 500px;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 1rem;
            text-align: center;
            position: relative;
        }

        .modal-body {
            padding: 1.5rem;
            text-align: center;
        }

        .modal-footer {
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        @media (min-width: 576px) {
            .modal-footer {
                flex-direction: row;
            }
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
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
            
            .dashboard-header h2 {
                font-size: 2.2rem;
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

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn:hover,
            .btn-delete-action:hover {
                transform: none;
            }
            
            .event-card:hover {
                transform: none;
            }
        }
    </style>
</head>
<body>
    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <i class="fas fa-trash-alt" style="font-size: 2.5rem; color: #ff6b6b; margin-bottom: 1rem;"></i>
                <h4 id="modalEventName" style="color: #333; margin-bottom: 1rem;"></h4>
                <p style="color: #666; margin-bottom: 1rem;">Apakah Anda yakin ingin menghapus event ini?</p>
                <div style="background: #fff5f5; padding: 1rem; border-radius: 8px; text-align: left;">
                    <p style="color: #d32f2f; font-size: 0.9rem; margin: 0;">
                        <i class="fas fa-exclamation-circle"></i> 
                        <strong>PERHATIAN:</strong> Tindakan ini tidak dapat dibatalkan. Event akan dihapus dari:
                    </p>
                    <ul style="margin: 10px 0 0 20px; color: #666; font-size: 0.9rem;">
                        <li>Database sistem</li>
                        <li>Google Calendar</li>
                        <li><strong>Semua tiket terkait</strong></li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button id="confirmDelete" class="btn-delete-action" style="flex: 1;">
                    <i class="fas fa-trash-alt"></i> Ya, Hapus
                </button>
                <button onclick="closeModal()" class="btn" style="flex: 1; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-times"></i> Batal
                </button>
            </div>
        </div>
    </div>

    <!-- Header dari add_event.php -->
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
            <h2>Hapus Event</h2>
            <p>Pilih event yang ingin dihapus dari sistem dan Google Calendar</p>
            <div class="danger-badge">
            </div>
            <div style="margin-top: 0.8rem;">
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

        <div class="danger-zone">
            
            <p><strong>PERHATIAN:</strong> Tindakan menghapus event tidak dapat dibatalkan. Pastikan Anda benar-benar yakin sebelum menghapus.</p>
            <ul>
                <li><i class="fas fa-database"></i> Event akan dihapus dari database sistem</li>
                <li><i class="fab fa-google"></i> Event akan dihapus dari Google Calendar</li>
                <li><i class="fas fa-ticket-alt"></i> <strong>Semua tiket terkait akan dihapus</strong></li>
                <li><i class="fas fa-history"></i> Tindakan ini tidak dapat dikembalikan (irreversible)</li>
            </ul>
        </div>

        <div class="search-box">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Cari event berdasarkan nama, lokasi, atau jenis...">
            </div>
        </div>

        <div class="events-table">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <!-- Desktop Table View -->
                <table>
                    <thead>
                        <tr>
                            <th>📝 Event Details</th>
                            <th>🎯 Jenis</th>
                            <th>📅 Tanggal & Waktu</th>
                            <th>📍 Lokasi</th>
                            <th>💰 Harga</th>
                            <th>🎫 Tiket</th>
                            <th>⚡ Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="eventsTable">
                        <?php 
                        $row_index = 0;
                        while ($event = mysqli_fetch_assoc($result)): 
                            $row_index++;
                        ?>
                        <tr>
                            <td>
                                <span class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></span>
                                <?php if (!empty($event['band_name'])): ?>
                                    <small style="color: #667eea; font-size: 0.85rem;">
                                        <i class="fas fa-guitar"></i> <?php echo htmlspecialchars($event['band_name']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <span class="event-type"><?php echo htmlspecialchars($event['event_type']); ?></span>
                            </td>
                            
                            <td>
                                <div style="font-size: 0.9rem;">
                                    <?php echo date('d M Y', strtotime($event['event_date'])); ?><br>
                                    <span style="color: #ff6b6b; font-size: 0.85rem;">
                                        <?php echo date('H:i', strtotime($event['event_time'])); ?>
                                    </span>
                                </div>
                            </td>
                            
                            <td style="max-width: 200px; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($event['location']); ?>
                            </td>
                            
                            <td style="font-weight: 600; color: #2e7d32;">
                                Rp <?php echo number_format($event['price'], 0, ',', '.'); ?>
                            </td>
                            
                            <td>
                                <div style="text-align: center;">
                                    <div style="font-weight: 700; font-size: 1.1rem;"><?php echo $event['available_tickets']; ?></div>
                                    <?php if ($event['available_tickets'] <= 0): ?>
                                        <span style="font-size: 0.7rem; padding: 0.2rem 0.5rem; background: rgba(255, 107, 107, 0.2); color: #b71c1c; border-radius: 10px;">HABIS</span>
                                    <?php elseif ($event['available_tickets'] <= 10): ?>
                                        <span style="font-size: 0.7rem; padding: 0.2rem 0.5rem; background: rgba(250, 112, 154, 0.2); color: #d32f2f; border-radius: 10px;">TERBATAS</span>
                                    <?php else: ?>
                                        <span style="font-size: 0.7rem; padding: 0.2rem 0.5rem; background: rgba(67, 233, 123, 0.2); color: #2e7d32; border-radius: 10px;">TERSEDIA</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td>
                                <div class="table-actions">
                                    <button onclick="showConfirmation(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars(addslashes($event['event_name'])); ?>')" 
                                            class="btn-delete-action">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </button>
                                </div>
                                <?php if (!empty($event['google_event_id'])): ?>
                                    <div class="google-calendar-badge">
                                        <i class="fab fa-google"></i> Google Calendar
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- Mobile Card View -->
                <div class="event-cards" id="eventCards">
                    <?php 
                    mysqli_data_seek($result, 0); // Reset pointer
                    while ($event = mysqli_fetch_assoc($result)): 
                    ?>
                    <div class="event-card" data-search="<?php echo strtolower(htmlspecialchars($event['event_name'] . ' ' . $event['event_type'] . ' ' . $event['location'])); ?>">
                        <div class="event-card-header">
                            <div class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></div>
                            <div class="event-type"><?php echo htmlspecialchars($event['event_type']); ?></div>
                        </div>
                        
                        <div class="event-info">
                            <?php if (!empty($event['band_name'])): ?>
                            <div class="info-item">
                                <i class="fas fa-guitar"></i>
                                <span class="info-value"><?php echo htmlspecialchars($event['band_name']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-item">
                                <i class="fas fa-calendar"></i>
                                <span class="info-value"><?php echo date('d M Y', strtotime($event['event_date'])); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <span class="info-value"><?php echo date('H:i', strtotime($event['event_time'])); ?> WIB</span>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span class="info-value"><?php echo htmlspecialchars($event['location']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-money-bill-wave"></i>
                                <span class="info-value">Rp <?php echo number_format($event['price'], 0, ',', '.'); ?></span>
                            </div>
                        </div>
                        
                        <div class="tickets-info">
                            <div>
                                <div style="font-size: 0.8rem; color: #666;">Tiket Tersedia</div>
                                <div class="ticket-count"><?php echo $event['available_tickets']; ?></div>
                            </div>
                            <div>
                                <?php if ($event['available_tickets'] <= 0): ?>
                                    <span class="ticket-status status-soldout">HABIS</span>
                                <?php elseif ($event['available_tickets'] <= 10): ?>
                                    <span class="ticket-status status-limited">TERBATAS</span>
                                <?php else: ?>
                                    <span class="ticket-status status-available">TERSEDIA</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="event-actions">
                            <button onclick="showConfirmation(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars(addslashes($event['event_name'])); ?>')" 
                                    class="btn-delete-action">
                                <i class="fas fa-trash-alt"></i> Hapus Event
                            </button>
                        </div>
                        
                        <?php if (!empty($event['google_event_id'])): ?>
                            <div class="google-calendar-badge">
                                <i class="fab fa-google"></i> Google Calendar
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
            <div class="no-events">
                <i class="fas fa-calendar-times"></i>
                <h3>📭 Tidak ada event yang ditemukan</h3>
                <p>Belum ada event yang bisa dihapus. Tambahkan event terlebih dahulu!</p>
                <a href="add_event.php" class="btn" style="margin-top: 1rem; padding: 0.8rem 1.5rem;">
                    <i class="fas fa-plus-circle"></i> Tambah Event
                </a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer dari add_event.php -->
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
        // Confirmation modal
        let currentEventId = null;
        let currentEventName = '';
        
        function showConfirmation(eventId, eventName) {
            currentEventId = eventId;
            currentEventName = eventName;
            
            const modal = document.getElementById('confirmationModal');
            const eventNameElement = document.getElementById('modalEventName');
            
            eventNameElement.textContent = eventName;
            modal.style.display = 'flex';
            
            // Add event listener to confirm button
            document.getElementById('confirmDelete').onclick = function() {
                window.location.href = `delete_event.php?id=${currentEventId}`;
            };
        }
        
        function closeModal() {
            document.getElementById('confirmationModal').style.display = 'none';
            currentEventId = null;
            currentEventName = '';
        }

        // Search functionality for both table and cards
        function initializeSearch() {
            const searchInput = document.getElementById('searchInput');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                // Search in table rows
                const tableRows = document.querySelectorAll('#eventsTable tr');
                tableRows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (searchTerm === '' || text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Search in cards
                const cards = document.querySelectorAll('.event-card');
                cards.forEach(card => {
                    const cardData = card.getAttribute('data-search');
                    if (searchTerm === '' || (cardData && cardData.includes(searchTerm))) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Show no results message if needed
                const visibleTableRows = Array.from(tableRows).filter(row => row.style.display !== 'none');
                const visibleCards = Array.from(cards).filter(card => card.style.display !== 'none');
                showNoResultsMessage(visibleTableRows.length === 0 && visibleCards.length === 0);
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
                    <button onclick="clearSearch()" class="btn" style="margin-top: 1rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-redo"></i> Tampilkan Semua Event
                    </button>
                `;
                
                const eventsTable = document.querySelector('.events-table');
                if (eventsTable) {
                    eventsTable.appendChild(message);
                }
            } else if (!show && message) {
                message.remove();
            }
        }

        // Clear search function
        window.clearSearch = function() {
            document.getElementById('searchInput').value = '';
            
            // Show all table rows
            const tableRows = document.querySelectorAll('#eventsTable tr');
            tableRows.forEach(row => {
                row.style.display = '';
            });
            
            // Show all cards
            const cards = document.querySelectorAll('.event-card');
            cards.forEach(card => {
                card.style.display = 'block';
            });
            
            // Remove no results message
            const message = document.getElementById('noResultsMessage');
            if (message) {
                message.remove();
            }
        };

        // Initialize everything
        document.addEventListener('DOMContentLoaded', function() {
            initializeSearch();
            
            // Close modal on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });
            
            // Close modal on outside click
            document.getElementById('confirmationModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            
            // Touch device improvements
            if ('ontouchstart' in window) {
                // Add touch feedback for buttons
                const buttons = document.querySelectorAll('.btn, .btn-delete-action');
                buttons.forEach(btn => {
                    btn.addEventListener('touchstart', function() {
                        this.style.transform = 'scale(0.98)';
                    });
                    
                    btn.addEventListener('touchend', function() {
                        this.style.transform = '';
                    });
                });
            }
        });

        // Handle orientation change
        window.addEventListener('orientationchange', function() {
            setTimeout(() => {
                window.scrollTo(0, 0);
            }, 100);
        });
    </script>
</body>
</html>
<?php
// Tutup koneksi database
mysqli_close($conn);
?>