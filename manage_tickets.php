<?php
// JANGAN panggil session_start() lagi karena sudah ada di config.php
include 'config.php'; // LINE 2

// Cek login dan admin access menggunakan function dari config.php
require_login();

// Cek apakah user adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Debug: Cek koneksi database
if (!$conn) {
    die("❌ Database connection failed");
}

// ========== AJAX ENDPOINT UNTUK DATA REAL-TIME ==========
if (isset($_GET['get_realtime_data'])) {
    header('Content-Type: application/json');
    
    // Query untuk data real-time
    $query = "SELECT t.*, e.event_name, e.event_date, e.location, u.full_name, u.email 
              FROM tickets t 
              JOIN events e ON t.event_id = e.id 
              JOIN users u ON t.user_id = u.id 
              ORDER BY t.id DESC";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        exit();
    }
    
    $tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Hitung stats
    $total_tickets = count($tickets);
    $total_revenue = 0;
    $unique_users = 0;
    $today_tickets = 0;
    $unique_users_list = [];

    if ($total_tickets > 0) {
        $total_revenue = array_sum(array_column($tickets, 'total_price'));
        
        // Hitung unique users
        foreach ($tickets as $ticket) {
            if (!in_array($ticket['user_id'], $unique_users_list)) {
                $unique_users_list[] = $ticket['user_id'];
                $unique_users++;
            }
            
            // Hitung tiket hari ini
            $purchase_date = isset($ticket['purchase_date']) ? $ticket['purchase_date'] : 
                            (isset($ticket['created_at']) ? $ticket['created_at'] : '');
            if (!empty($purchase_date) && date('Y-m-d', strtotime($purchase_date)) == date('Y-m-d')) {
                $today_tickets += $ticket['quantity'];
            }
        }
    }
    
    // Hitung average ticket price
    $avg_ticket = $total_tickets > 0 ? $total_revenue / $total_tickets : 0;
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('H:i:s'),
        'total_tickets' => $total_tickets,
        'total_revenue' => $total_revenue,
        'unique_users' => $unique_users,
        'today_tickets' => $today_tickets,
        'avg_ticket' => $avg_ticket,
        'tickets' => $tickets
    ]);
    
    exit();
}

// ========== KODE ASLI UNTUK TAMPILAN AWAL ==========
// LINE 26 - Perbaiki query: ganti 'created_at' dengan kolom yang ada
// Cek dulu struktur table tickets
$query = "SELECT t.*, e.event_name, e.event_date, e.location, u.full_name, u.email 
          FROM tickets t 
          JOIN events e ON t.event_id = e.id 
          JOIN users u ON t.user_id = u.id 
          ORDER BY t.id DESC"; // Ganti dengan t.id atau kolom lain yang ada

$result = mysqli_query($conn, $query);

// Debug: Cek jika query error
if (!$result) {
    echo "❌ Query Error: " . mysqli_error($conn);
    echo "<br>SQL: " . $query;
    echo "<br><br>⚠️ <strong>Tips:</strong> Cek struktur table tickets dengan query: SHOW COLUMNS FROM tickets";
    exit();
}

$tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Hitung stats
$total_tickets = count($tickets);
$total_revenue = 0;
$unique_users = 0;
$today_tickets = 0;
$unique_users_list = [];

if ($total_tickets > 0) {
    $total_revenue = array_sum(array_column($tickets, 'total_price'));
    
    // Hitung unique users
    foreach ($tickets as $ticket) {
        if (!in_array($ticket['user_id'], $unique_users_list)) {
            $unique_users_list[] = $ticket['user_id'];
            $unique_users++;
        }
        
        // Hitung tiket hari ini
        $purchase_date = isset($ticket['purchase_date']) ? $ticket['purchase_date'] : 
                        (isset($ticket['created_at']) ? $ticket['created_at'] : '');
        if (!empty($purchase_date) && date('Y-m-d', strtotime($purchase_date)) == date('Y-m-d')) {
            $today_tickets += $ticket['quantity'];
        }
    }
}

// Proses hapus tiket
if (isset($_GET['delete_ticket'])) {
    $ticket_id = mysqli_real_escape_string($conn, $_GET['delete_ticket']);
    
    // Ambil data tiket sebelum hapus (untuk update available_tickets)
    $ticket_query = "SELECT * FROM tickets WHERE id = ?";
    $stmt = mysqli_prepare($conn, $ticket_query);
    mysqli_stmt_bind_param($stmt, "i", $ticket_id);
    mysqli_stmt_execute($stmt);
    $ticket_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($ticket_data) {
        // Hapus tiket
        $delete_query = "DELETE FROM tickets WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $ticket_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            // Kembalikan available_tickets di events
            $update_query = "UPDATE events SET available_tickets = available_tickets + ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ii", $ticket_data['quantity'], $ticket_data['event_id']);
            mysqli_stmt_execute($update_stmt);
            
            $_SESSION['success'] = "✅ Tiket berhasil dihapus! Available tickets telah dikembalikan.";
        } else {
            $_SESSION['error'] = "❌ Gagal menghapus tiket.";
        }
    } else {
        $_SESSION['error'] = "❌ Tiket tidak ditemukan.";
    }
    
    header("Location: manage_tickets.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kelola Tiket - LIVE FEST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* ====== REAL-TIME BADGE ====== */
        .realtime-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }

        .realtime-clock {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: 10px;
        }

        .refresh-indicator {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 10000;
            background: rgba(67, 233, 123, 0.9);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.5s ease;
            opacity: 0;
            display: none;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .refresh-progress {
            width: 100%;
            height: 4px;
            background: rgba(255,255,255,0.3);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 5px;
        }

        .refresh-progress-bar {
            height: 100%;
            background: white;
            width: 0%;
            transition: width 1s linear;
        }

        .live-update {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #43e97b;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .live-pulse {
            width: 8px;
            height: 8px;
            background: #43e97b;
            border-radius: 50%;
            animation: blink 2s infinite;
        }

        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.3; }
            100% { opacity: 1; }
        }

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
            cursor: pointer;
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

        .time-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        /* ====== STATS GRID MOBILE ====== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card.updating {
            animation: statUpdate 0.5s ease;
        }

        @keyframes statUpdate {
            0% { background: white; }
            50% { background: #f0f7ff; }
            100% { background: white; }
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            font-size: 1.2rem;
            color: white;
        }

        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 5px;
            line-height: 1.2;
        }

        .stat-label {
            color: #666;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .stat-trend {
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .trend-up {
            color: #43e97b;
        }

        .trend-down {
            color: #ff6b6b;
        }

        /* ====== SEARCH BOX ====== */
        .search-filter {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .search-filter::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .search-box {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .search-box {
                flex-direction: column;
            }
            
            .search-box input,
            .search-box button {
                width: 100%;
            }
        }

        .search-box input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #f8f9ff;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box button {
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .search-box button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        /* ====== ALERT MESSAGES ====== */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            animation: slideIn 0.5s ease;
        }

        .alert-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }

        .alert-error {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }

        /* ====== TICKETS TABLE ====== */
        .tickets-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            position: relative;
            margin-bottom: 20px;
        }

        .tickets-table::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
        }

        th, td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            font-weight: 600;
            color: #333;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        tbody tr {
            transition: all 0.3s;
        }

        tbody tr:hover {
            background: #f8f9ff;
        }

        /* Ticket Code Styling */
        .ticket-code {
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 0.85rem;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        /* Event Info */
        .event-info strong {
            font-size: 0.95rem;
            color: #333;
            display: block;
            margin-bottom: 5px;
            word-break: break-word;
        }

        .event-info small {
            color: #666;
            font-size: 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        /* User Info */
        .user-info strong {
            font-size: 0.95rem;
            color: #333;
            display: block;
            margin-bottom: 5px;
        }

        .user-info small {
            color: #666;
            font-size: 0.8rem;
        }

        /* Quantity and Price */
        .quantity-badge, .price-badge {
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-block;
        }

        .quantity-badge {
            background: rgba(67, 233, 123, 0.1);
            color: #2e7d32;
        }

        .price-badge {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        /* Date Styling */
        .purchase-date {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .purchase-date .date {
            font-weight: 500;
            color: #333;
            font-size: 0.9rem;
        }

        .purchase-date .time {
            font-size: 0.8rem;
            color: #667eea;
        }

        /* Status Badge */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .badge-active {
            background: rgba(67, 233, 123, 0.2);
            color: #2e7d32;
        }

        .badge-completed {
            background: rgba(255, 107, 107, 0.2);
            color: #d32f2f;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }

        .action-btn {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-delete-action {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(255, 107, 107, 0.3);
        }

        .btn-delete-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
            display: block;
        }

        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .empty-state p {
            margin-bottom: 20px;
            font-size: 0.95rem;
            max-width: 500px;
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
            
            .site-title {
                text-align: left;
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
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-number {
                font-size: 1.8rem;
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
            
            .stat-card {
                padding: 25px;
            }
            
            .stat-number {
                font-size: 2rem;
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

        /* ====== MODAL STYLES ====== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            max-width: 500px;
            width: 90%;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 15px;
            text-align: center;
        }

        .modal-body {
            padding: 20px;
            text-align: center;
        }

        .modal-footer {
            padding: 15px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
        }

        /* Chart animations */
        @keyframes chartIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .refresh-btn {
            transition: transform 0.3s;
            cursor: pointer;
        }

        .refresh-btn:hover {
            transform: rotate(180deg);
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .stat-card:active {
                transform: scale(0.98);
            }
            
            .refresh-btn:active {
                transform: rotate(180deg);
            }
        }

        /* Responsive table */
        @media (max-width: 1024px) {
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                min-width: 150px;
            }
        }

        /* Row animation */
        @keyframes fadeInRow {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        tbody tr {
            animation: fadeInRow 0.5s ease;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0; color: white; font-size: 1.2rem;"><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <i class="fas fa-trash-alt" style="font-size: 2.5rem; color: #ff6b6b; margin-bottom: 1rem;"></i>
                <h4 id="modalTicketCode" style="color: #333; margin-bottom: 1rem; font-size: 1.1rem;"></h4>
                <p style="color: #666; margin-bottom: 1rem; font-size: 0.95rem;" id="modalTicketInfo"></p>
                <div style="background: #fff5f5; padding: 12px; border-radius: 8px; text-align: left;">
                    <p style="color: #d32f2f; font-size: 0.85rem; margin: 0;">
                        <i class="fas fa-exclamation-circle"></i> 
                        <strong>PERHATIAN:</strong> Tindakan ini akan:
                    </p>
                    <ul style="margin: 8px 0 0 20px; color: #666; font-size: 0.85rem;">
                        <li>Menghapus tiket dari database</li>
                        <li>Mengembalikan jumlah tiket tersedia ke event</li>
                        <li>Tidak dapat dibatalkan</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button id="confirmDelete" class="btn btn-delete-action" style="flex: 1; font-size: 0.9rem;">
                    <i class="fas fa-trash-alt"></i> Ya, Hapus
                </button>
                <button onclick="closeModal()" class="btn" style="flex: 1; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 0.9rem;">
                    <i class="fas fa-times"></i> Batal
                </button>
            </div>
        </div>
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
            <h2>Kelola Tiket 
                
            </h2>
            <p>Kelola semua tiket yang telah dibeli oleh user. Pantau penjualan dan kelola tiket dengan mudah.</p>
            <div class="time-status">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-sync-alt refresh-btn" id="manualRefresh"></i>
                    <span id="lastUpdateTime">Terakhir diperbarui: <?php echo date('H:i:s'); ?></span>
                </span>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" id="statTickets">
                <div class="stat-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-number" id="statTicketsNumber"><?php echo $total_tickets; ?></div>
                <div class="stat-label">Total Tiket Terjual</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span id="todayTicketsCount"><?php echo $today_tickets; ?> hari ini</span>
                </div>
            </div>

            <div class="stat-card" id="statRevenue">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number" id="statRevenueNumber">Rp<?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
                <div class="stat-label">Total Pendapatan</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>Klik refresh untuk update</span>
                </div>
            </div>

            <div class="stat-card" id="statUsers">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number" id="statUsersNumber"><?php echo $unique_users; ?></div>
                <div class="stat-label">User Unik</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>Klik refresh untuk update</span>
                </div>
            </div>

            <div class="stat-card" id="statAverage">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number" id="statAverageNumber">
                    <?php 
                    $avg_ticket = $total_tickets > 0 ? $total_revenue / $total_tickets : 0;
                    echo 'Rp' . number_format($avg_ticket, 0, ',', '.');
                    ?>
                </div>
                <div class="stat-label">Rata-rata per Tiket</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>Klik refresh untuk update</span>
                </div>
            </div>
        </div>

        <!-- Search Box -->
        <div class="search-filter">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Cari tiket berdasarkan kode, event, user, atau email...">
                <button onclick="searchTickets()">
                    <i class="fas fa-search"></i> Cari
                </button>
                <button onclick="clearSearch()" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="tickets-table">
            <?php if ($total_tickets > 0): ?>
            <table id="ticketsTable">
                <thead>
                    <tr>
                        <th>🎫 Kode Tiket</th>
                        <th>📅 Event</th>
                        <th>👤 User</th>
                        <th>👥 Attendee</th>
                        <th>🔢 Jumlah</th>
                        <th>💰 Total Harga</th>
                        <th>📆 Tanggal Beli</th>
                        <th>🏷️ Status</th>
                        <th>⚡ Aksi</th>
                    </tr>
                </thead>
                <tbody id="ticketsTableBody">
                    <?php foreach ($tickets as $index => $ticket): ?>
                        <tr data-ticket-id="<?php echo $ticket['id']; ?>">
                            <!-- Ticket Code -->
                            <td>
                                <span class="ticket-code"><?php echo $ticket['ticket_code']; ?></span>
                            </td>
                            
                            <!-- Event Info -->
                            <td>
                                <div class="event-info">
                                    <strong><?php echo htmlspecialchars($ticket['event_name']); ?></strong>
                                    <small>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($ticket['event_date'])); ?></span>
                                        <?php if (!empty($ticket['location'])): ?>
                                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ticket['location']); ?></span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </td>
                            
                            <!-- User Info -->
                            <td>
                                <div class="user-info">
                                    <strong><?php echo htmlspecialchars($ticket['full_name']); ?></strong>
                                    <small><i class="fas fa-envelope"></i> <?php echo $ticket['email']; ?></small>
                                </div>
                            </td>
                            
                            <!-- Attendee Info -->
                            <td>
                                <div class="user-info">
                                    <strong><?php echo htmlspecialchars($ticket['attendee_name']); ?></strong>
                                    <small><i class="fas fa-envelope"></i> <?php echo $ticket['attendee_email']; ?></small>
                                </div>
                            </td>
                            
                            <!-- Quantity -->
                            <td>
                                <span class="quantity-badge"><?php echo $ticket['quantity']; ?> tiket</span>
                            </td>
                            
                            <!-- Price -->
                            <td>
                                <span class="price-badge">Rp <?php echo number_format($ticket['total_price'], 0, ',', '.'); ?></span>
                            </td>
                            
                            <!-- Purchase Date -->
                            <td>
                                <div class="purchase-date">
                                    <?php 
                                    $purchase_date = isset($ticket['purchase_date']) ? $ticket['purchase_date'] : 
                                                    (isset($ticket['created_at']) ? $ticket['created_at'] : date('Y-m-d H:i:s'));
                                    ?>
                                    <span class="date"><?php echo date('d M Y', strtotime($purchase_date)); ?></span>
                                    <span class="time"><?php echo date('H:i', strtotime($purchase_date)); ?></span>
                                </div>
                            </td>
                            
                            <!-- Status -->
                            <td>
                                <?php
                                $event_date = strtotime($ticket['event_date']);
                                $today = time();
                                $days_diff = ($event_date - $today) / (60 * 60 * 24);
                                
                                if ($days_diff > 0) {
                                    echo '<span class="status-badge badge-active">Aktif</span>';
                                } else {
                                    echo '<span class="status-badge badge-completed">Selesai</span>';
                                }
                                ?>
                            </td>
                            
                            <!-- Actions -->
                            <td>
                                <div class="action-buttons">
                                    <button onclick="showConfirmation(
                                        '<?php echo $ticket['id']; ?>',
                                        '<?php echo addslashes($ticket['ticket_code']); ?>',
                                        '<?php echo addslashes($ticket['full_name']); ?>',
                                        '<?php echo addslashes($ticket['event_name']); ?>',
                                        '<?php echo $ticket['quantity']; ?>',
                                        '<?php echo $ticket['total_price']; ?>'
                                    )" 
                                    class="action-btn btn-delete-action">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state" id="emptyState">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>📭 Belum ada tiket yang dibeli</h3>
                    <p>User belum membeli tiket event apapun. Ayo promosikan event Anda!</p>
                    <div style="display: flex; flex-direction: column; gap: 0.8rem; align-items: center; margin-top: 1.5rem;">
                        <a href="events.php" class="btn" style="width: 100%; max-width: 250px; justify-content: center;">
                            <i class="fas fa-calendar-alt"></i> Lihat Daftar Event
                        </a>
                        <a href="analytics.php" class="btn" style="width: 100%; max-width: 250px; justify-content: center; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-chart-bar"></i> Lihat Analitik
                        </a>
                    </div>
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
                <p>&copy; 2025 LIVE FEST. <span id="footerClock"><?php echo date('H:i:s'); ?></span></p>
            </div>
        </div>
    </footer>

    <script>
        // Manual Refresh System for Tickets Management
        let isUpdating = false;
        let currentTicketId = null;
        
        // Initialize on page load
        $(document).ready(function() {
            initializeLiveClock();
            
            // Manual refresh button
            $('#manualRefresh').on('click', function() {
                refreshData();
            });
            
            // Update footer clock every second
            setInterval(updateFooterClock, 1000);
            
            // Auto-hide alerts after 5 seconds
            setTimeout(() => {
                $('.alert').fadeOut(500);
            }, 5000);
        });
        
        // Initialize live clock
        function initializeLiveClock() {
            updateLastUpdateTime();
        }
        
        // Update last update time
        function updateLastUpdateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            $('#lastUpdateTime').text(`Terakhir diperbarui: ${timeString}`);
        }
        
        // Update footer clock
        function updateFooterClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            $('#footerClock').text(timeString);
        }
        
        // Refresh data function (MANUAL ONLY)
        function refreshData() {
            if (isUpdating) return;
            
            isUpdating = true;
            showRefreshIndicator();
            
            // Animate refresh button
            $('#manualRefresh').css('animation', 'spin 1s linear');
            
            $.ajax({
                url: 'manage_tickets.php?get_realtime_data=1',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        updateAllData(response);
                        showNotification('✅ Data tiket berhasil diperbarui!', 'success');
                    } else {
                        showNotification('❌ Gagal memperbarui data', 'error');
                    }
                },
                error: function() {
                    showNotification('⚠️ Koneksi error, coba lagi nanti', 'error');
                },
                complete: function() {
                    $('#manualRefresh').css('animation', '');
                    isUpdating = false;
                    setTimeout(() => {
                        $('.refresh-indicator').remove();
                    }, 2000);
                }
            });
        }
        
        // Update all data with new response
        function updateAllData(data) {
            // Update timestamp
            updateLastUpdateTime();
            
            // Update stats cards with animation
            updateStatCard('#statTicketsNumber', data.total_tickets);
            updateStatCard('#statRevenueNumber', 'Rp' + formatNumber(data.total_revenue));
            updateStatCard('#statUsersNumber', data.unique_users);
            updateStatCard('#statAverageNumber', 'Rp' + formatNumber(data.avg_ticket));
            updateTodayTickets(data.today_tickets);
            
            // Update table if there are tickets
            if (data.tickets && data.tickets.length > 0) {
                updateTicketsTable(data.tickets);
                hideEmptyState();
            } else {
                showEmptyState();
            }
            
            // Animate stat cards
            animateStatCards();
        }
        
        // Update single stat card
        function updateStatCard(selector, newValue) {
            const element = $(selector);
            const oldValue = element.text();
            
            if (oldValue !== newValue) {
                element.parents('.stat-card').addClass('updating');
                element.fadeOut(200, function() {
                    $(this).text(newValue).fadeIn(200);
                    setTimeout(() => {
                        $(this).parents('.stat-card').removeClass('updating');
                    }, 500);
                });
            }
        }
        
        // Update today tickets count
        function updateTodayTickets(count) {
            const element = $('#todayTicketsCount');
            element.text(count + ' hari ini');
            
            // Animate if count changed
            if (parseInt(element.text()) !== parseInt(count)) {
                element.parents('.stat-card').addClass('updating');
                setTimeout(() => {
                    element.parents('.stat-card').removeClass('updating');
                }, 500);
            }
        }
        
        // Animate all stat cards
        function animateStatCards() {
            $('.stat-card').each(function(index) {
                $(this).css('animation-delay', (index * 100) + 'ms');
                $(this).addClass('updating');
                
                setTimeout(() => {
                    $(this).removeClass('updating');
                }, 500 + (index * 100));
            });
        }
        
        // Update tickets table
        function updateTicketsTable(tickets) {
            const tableBody = $('#ticketsTableBody');
            if (!tableBody.length) return;
            
            // Clear existing rows
            tableBody.empty();
            
            // Add new rows
            tickets.forEach((ticket, index) => {
                const purchaseDate = ticket.purchase_date || ticket.created_at || new Date().toISOString();
                const eventDate = new Date(ticket.event_date);
                const today = new Date();
                const daysDiff = (eventDate - today) / (1000 * 60 * 60 * 24);
                
                const row = `
                    <tr data-ticket-id="${ticket.id}">
                        <!-- Ticket Code -->
                        <td>
                            <span class="ticket-code">${ticket.ticket_code}</span>
                        </td>
                        
                        <!-- Event Info -->
                        <td>
                            <div class="event-info">
                                <strong>${escapeHtml(ticket.event_name)}</strong>
                                <small>
                                    <span><i class="fas fa-calendar"></i> ${formatDate(ticket.event_date)}</span>
                                    ${ticket.location ? `<span><i class="fas fa-map-marker-alt"></i> ${escapeHtml(ticket.location)}</span>` : ''}
                                </small>
                            </div>
                        </td>
                        
                        <!-- User Info -->
                        <td>
                            <div class="user-info">
                                <strong>${escapeHtml(ticket.full_name)}</strong>
                                <small><i class="fas fa-envelope"></i> ${ticket.email}</small>
                            </div>
                        </td>
                        
                        <!-- Attendee Info -->
                        <td>
                            <div class="user-info">
                                <strong>${escapeHtml(ticket.attendee_name)}</strong>
                                <small><i class="fas fa-envelope"></i> ${ticket.attendee_email}</small>
                            </div>
                        </td>
                        
                        <!-- Quantity -->
                        <td>
                            <span class="quantity-badge">${ticket.quantity} tiket</span>
                        </td>
                        
                        <!-- Price -->
                        <td>
                            <span class="price-badge">Rp ${formatNumber(ticket.total_price)}</span>
                        </td>
                        
                        <!-- Purchase Date -->
                        <td>
                            <div class="purchase-date">
                                <span class="date">${formatDate(purchaseDate)}</span>
                                <span class="time">${formatTime(purchaseDate)}</span>
                            </div>
                        </td>
                        
                        <!-- Status -->
                        <td>
                            ${daysDiff > 0 
                                ? '<span class="status-badge badge-active">Aktif</span>' 
                                : '<span class="status-badge badge-completed">Selesai</span>'}
                        </td>
                        
                        <!-- Actions -->
                        <td>
                            <div class="action-buttons">
                                <button onclick="showConfirmation(
                                    '${ticket.id}',
                                    '${escapeHtml(ticket.ticket_code)}',
                                    '${escapeHtml(ticket.full_name)}',
                                    '${escapeHtml(ticket.event_name)}',
                                    '${ticket.quantity}',
                                    '${ticket.total_price}'
                                )" 
                                class="action-btn btn-delete-action">
                                    <i class="fas fa-trash-alt"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                
                tableBody.append(row);
                
                // Add animation delay
                setTimeout(() => {
                    $(`tr[data-ticket-id="${ticket.id}"]`).css('animation', 'fadeInRow 0.5s ease');
                }, index * 50);
            });
        }
        
        // Show empty state
        function showEmptyState() {
            $('#emptyState').show();
            $('#ticketsTable').hide();
        }
        
        // Hide empty state
        function hideEmptyState() {
            $('#emptyState').hide();
            $('#ticketsTable').show();
        }
        
        // Show refresh indicator
        function showRefreshIndicator() {
            $('.refresh-indicator').remove();
            
            const indicator = $(`
                <div class="refresh-indicator">
                    <i class="fas fa-sync-alt fa-spin"></i>
                    Memperbarui data tiket...
                    <div class="refresh-progress">
                        <div class="refresh-progress-bar"></div>
                    </div>
                </div>
            `);
            
            $('body').append(indicator);
            
            // Show and animate progress bar
            setTimeout(() => {
                indicator.css('display', 'flex');
                indicator.css('opacity', '1');
                indicator.find('.refresh-progress-bar').css('width', '100%');
            }, 10);
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            const notification = $(`
                <div class="notification notification-${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i>
                    ${message}
                </div>
            `);
            
            notification.css({
                position: 'fixed',
                top: '20px',
                right: '12px',
                left: '12px',
                padding: '12px',
                borderRadius: '8px',
                color: 'white',
                fontWeight: '500',
                zIndex: '10000',
                boxShadow: '0 4px 15px rgba(0,0,0,0.2)',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                fontSize: '0.9rem',
                background: type === 'success' ? 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' :
                           type === 'error' ? 'linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%)' :
                           'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
            });
            
            $('body').append(notification);
            
            notification.hide().slideDown(300);
            
            setTimeout(() => {
                notification.slideUp(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
        
        // Format number with thousand separators
        function formatNumber(num) {
            return parseInt(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }
        
        // Format time
        function formatTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Search functionality
        function searchTickets() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('ticketsTable');
            if (!table) return;
            
            const tr = table.getElementsByTagName('tr');
            let visibleCount = 0;
            
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                if (found) {
                    tr[i].style.display = '';
                    visibleCount++;
                } else {
                    tr[i].style.display = 'none';
                }
            }
            
            // Show no results message if needed
            showNoResultsMessage(visibleCount === 0);
        }
        
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            const table = document.getElementById('ticketsTable');
            if (!table) return;
            
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                tr[i].style.display = '';
            }
            
            const message = document.getElementById('noResultsMessage');
            if (message) {
                message.remove();
            }
        }
        
        // Show no results message
        function showNoResultsMessage(show) {
            if (document.getElementById('noResultsMessage')) {
                return; // Message already exists
            }
            
            if (show) {
                const message = document.createElement('div');
                message.id = 'noResultsMessage';
                message.className = 'empty-state';
                message.innerHTML = `
                    <i class="fas fa-search"></i>
                    <h3>Tiket Tidak Ditemukan</h3>
                    <p>Tidak ada tiket yang sesuai dengan kriteria pencarian Anda.</p>
                    <button onclick="clearSearch()" class="btn" style="margin-top: 1rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-redo"></i> Tampilkan Semua Tiket
                    </button>
                `;
                
                const table = document.querySelector('.tickets-table');
                if (table) {
                    table.appendChild(message);
                }
            }
        }
        
        // Confirmation modal
        function showConfirmation(ticketId, ticketCode, userName, eventName, quantity, totalPrice) {
            currentTicketId = ticketId;
            
            const modal = document.getElementById('confirmationModal');
            const ticketCodeElement = document.getElementById('modalTicketCode');
            const ticketInfoElement = document.getElementById('modalTicketInfo');
            
            ticketCodeElement.textContent = `Tiket: ${ticketCode}`;
            ticketInfoElement.innerHTML = `
                User: <strong>${userName}</strong><br>
                Event: <strong>${eventName}</strong><br>
                Jumlah: <strong>${quantity} tiket</strong><br>
                Total: <strong>Rp ${parseInt(totalPrice).toLocaleString('id-ID')}</strong>
            `;
            
            modal.style.display = 'flex';
            
            // Add event listener to confirm button
            document.getElementById('confirmDelete').onclick = function() {
                window.location.href = `manage_tickets.php?delete_ticket=${currentTicketId}`;
            };
        }
        
        function closeModal() {
            document.getElementById('confirmationModal').style.display = 'none';
            currentTicketId = null;
        }
        
        // Initialize everything
        document.addEventListener('DOMContentLoaded', function() {
            // Add typing animation to search placeholder
            const searchInput = document.getElementById('searchInput');
            const placeholders = [
                "Cari tiket berdasarkan kode...",
                "Cari tiket berdasarkan event...",
                "Cari tiket berdasarkan user...",
                "Cari tiket berdasarkan email..."
            ];
            let placeholderIndex = 0;
            let charIndex = 0;
            let isDeleting = false;
            
            function typePlaceholder() {
                if (!searchInput) return;
                
                const currentPlaceholder = placeholders[placeholderIndex];
                
                if (isDeleting) {
                    searchInput.placeholder = currentPlaceholder.substring(0, charIndex - 1);
                    charIndex--;
                    
                    if (charIndex === 0) {
                        isDeleting = false;
                        placeholderIndex = (placeholderIndex + 1) % placeholders.length;
                    }
                } else {
                    searchInput.placeholder = currentPlaceholder.substring(0, charIndex + 1);
                    charIndex++;
                    
                    if (charIndex === currentPlaceholder.length) {
                        isDeleting = true;
                        setTimeout(typePlaceholder, 2000);
                        return;
                    }
                }
                
                setTimeout(typePlaceholder, isDeleting ? 50 : 100);
            }
            
            // Start typing effect
            if (searchInput) {
                setTimeout(typePlaceholder, 1000);
            }
            
            // Close modal on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });
            
            // Close modal on outside click
            const modal = document.getElementById('confirmationModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });
            }
            
            // Enter key support for search
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        searchTickets();
                    }
                });
            }
        });
        
        // Handle orientation change
        window.addEventListener('orientationchange', function() {
            setTimeout(() => {
                window.scrollTo(0, 0);
            }, 100);
        });
        
        // Add touch feedback
        if ('ontouchstart' in window) {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                
                card.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        }
    </script>
</body>
</html>
<?php
// Tutup koneksi database
mysqli_close($conn);
?>