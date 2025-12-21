<?php
include 'config.php';
require_login();

if (!is_admin()) {
    header("Location: dashboard.php");
    exit();
}

// ========== AJAX ENDPOINT UNTUK DATA REAL-TIME ==========
if (isset($_GET['get_realtime_data'])) {
    header('Content-Type: application/json');
    
    // Data untuk chart
    $analytics_query = "
        SELECT 
            e.event_type,
            COUNT(t.id) as total_tickets_sold,
            COUNT(DISTINCT t.user_id) as unique_attendees,
            SUM(e.price) as total_revenue
        FROM events e 
        LEFT JOIN tickets t ON e.id = t.event_id 
        WHERE e.event_type IS NOT NULL 
        GROUP BY e.event_type 
        ORDER BY total_tickets_sold DESC
    ";
    $analytics_result = mysqli_query($conn, $analytics_query);
    
    $total_tickets = 0;
    $total_revenue = 0;
    $event_types_data = [];
    while($row = mysqli_fetch_assoc($analytics_result)) {
        $total_tickets += $row['total_tickets_sold'];
        $total_revenue += $row['total_revenue'] ?: 0;
        $event_types_data[] = $row;
    }
    
    // Data untuk chart
    $labels = [];
    $tickets_data = [];
    $revenue_data = [];
    foreach ($event_types_data as $row) {
        $labels[] = $row['event_type'];
        $tickets_data[] = $row['total_tickets_sold'];
        $revenue_data[] = $row['total_revenue'] ?: 0;
    }
    
    // Data untuk trend 6 bulan
    $monthly_query = "
        SELECT 
            DATE_FORMAT(e.event_date, '%Y-%m') as month,
            COUNT(t.id) as tickets_sold,
            SUM(e.price) as revenue
        FROM events e 
        LEFT JOIN tickets t ON e.id = t.event_id 
        WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(e.event_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ";
    $monthly_result = mysqli_query($conn, $monthly_query);
    
    $monthly_labels = [];
    $monthly_tickets = [];
    $monthly_revenue = [];
    while($row = mysqli_fetch_assoc($monthly_result)) {
        $monthly_labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $monthly_tickets[] = $row['tickets_sold'] ?: 0;
        $monthly_revenue[] = $row['revenue'] ?: 0;
    }
    
    // Reverse untuk chronological order
    $monthly_labels = array_reverse($monthly_labels);
    $monthly_tickets = array_reverse($monthly_tickets);
    $monthly_revenue = array_reverse($monthly_revenue);
    
    // Total events
    $total_events_query = "SELECT COUNT(*) as total FROM events";
    $total_events_result = mysqli_query($conn, $total_events_query);
    $total_events = mysqli_fetch_assoc($total_events_result)['total'];
    
    // Total users
    $total_users_query = "SELECT COUNT(*) as total FROM users";
    $total_users_result = mysqli_query($conn, $total_users_query);
    $total_users = mysqli_fetch_assoc($total_users_result)['total'];
    
    // Popular events
    $popular_query = "
        SELECT 
            e.event_name,
            e.event_type,
            COUNT(t.id) as tickets_sold,
            SUM(e.price) as revenue
        FROM events e 
        LEFT JOIN tickets t ON e.id = t.event_id 
        GROUP BY e.id, e.event_name, e.event_type
        ORDER BY tickets_sold DESC
        LIMIT 5
    ";
    $popular_result = mysqli_query($conn, $popular_query);
    
    $popular_events = [];
    while($row = mysqli_fetch_assoc($popular_result)) {
        $popular_events[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('H:i:s'),
        'total_events' => $total_events,
        'total_tickets' => $total_tickets,
        'total_revenue' => $total_revenue,
        'total_users' => $total_users,
        'labels' => $labels,
        'tickets_data' => $tickets_data,
        'revenue_data' => $revenue_data,
        'monthly_labels' => $monthly_labels,
        'monthly_tickets' => $monthly_tickets,
        'monthly_revenue' => $monthly_revenue,
        'popular_events' => $popular_events,
        'event_types_data' => $event_types_data
    ]);
    
    mysqli_close($conn);
    exit();
}

// ========== KODE ASLI UNTUK TAMPILAN AWAL ==========
// Query untuk analitik jenis event paling diminati
$analytics_query = "
    SELECT 
        e.event_type,
        COUNT(t.id) as total_tickets_sold,
        COUNT(DISTINCT t.user_id) as unique_attendees,
        SUM(e.price) as total_revenue
    FROM events e 
    LEFT JOIN tickets t ON e.id = t.event_id 
    WHERE e.event_type IS NOT NULL 
    GROUP BY e.event_type 
    ORDER BY total_tickets_sold DESC
";
$analytics_result = mysqli_query($conn, $analytics_query);

// Hitung total
$total_tickets = 0;
$total_revenue = 0;
$event_types_data = [];
while($row = mysqli_fetch_assoc($analytics_result)) {
    $total_tickets += $row['total_tickets_sold'];
    $total_revenue += $row['total_revenue'] ?: 0;
    $event_types_data[] = $row;
}
// Reset pointer
mysqli_data_seek($analytics_result, 0);

// Data untuk chart
$labels = [];
$tickets_data = [];
$revenue_data = [];
foreach ($event_types_data as $row) {
    $labels[] = $row['event_type'];
    $tickets_data[] = $row['total_tickets_sold'];
    $revenue_data[] = $row['total_revenue'] ?: 0;
}

// Data untuk trend 6 bulan
$monthly_query = "
    SELECT 
        DATE_FORMAT(e.event_date, '%Y-%m') as month,
        COUNT(t.id) as tickets_sold,
        SUM(e.price) as revenue
    FROM events e 
    LEFT JOIN tickets t ON e.id = t.event_id 
    WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(e.event_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";
$monthly_result = mysqli_query($conn, $monthly_query);

$monthly_labels = [];
$monthly_tickets = [];
$monthly_revenue = [];
while($row = mysqli_fetch_assoc($monthly_result)) {
    $monthly_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthly_tickets[] = $row['tickets_sold'] ?: 0;
    $monthly_revenue[] = $row['revenue'] ?: 0;
}
// Reverse untuk chronological order
$monthly_labels = array_reverse($monthly_labels);
$monthly_tickets = array_reverse($monthly_tickets);
$monthly_revenue = array_reverse($monthly_revenue);

$total_events_query = "SELECT COUNT(*) as total FROM events";
$total_events_result = mysqli_query($conn, $total_events_query);
$total_events = mysqli_fetch_assoc($total_events_result)['total'];

// Total users
$total_users_query = "SELECT COUNT(*) as total FROM users";
$total_users_result = mysqli_query($conn, $total_users_query);
$total_users = mysqli_fetch_assoc($total_users_result)['total'];

// Popular events
$popular_query = "
    SELECT 
        e.event_name,
        e.event_type,
        COUNT(t.id) as tickets_sold,
        SUM(e.price) as revenue
    FROM events e 
    LEFT JOIN tickets t ON e.id = t.event_id 
    GROUP BY e.id, e.event_name, e.event_type
    ORDER BY tickets_sold DESC
    LIMIT 5
";
$popular_result = mysqli_query($conn, $popular_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Analitik - LIVE FEST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        /* ====== REAL-TIME BADGE ====== */
        .realtime-badge {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 0.8; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.05); }
            100% { opacity: 0.8; transform: scale(1); }
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

        /* ====== CHARTS GRID MOBILE ====== */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }

        .chart-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .chart-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-container {
            height: 200px;
            position: relative;
        }

        /* ====== RANKING SECTION MOBILE ====== */
        .ranking-section {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 15px;
            position: relative;
            overflow: hidden;
        }

        .ranking-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .ranking-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ranking-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .ranking-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #f8f9ff;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }

        .rank {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            margin-right: 10px;
            color: white;
            flex-shrink: 0;
        }

        .rank-1 {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        }

        .rank-2 {
            background: linear-gradient(135deg, #C0C0C0 0%, #A0A0A0 100%);
        }

        .rank-3 {
            background: linear-gradient(135deg, #CD7F32 0%, #A0522D 100%);
        }

        .rank-other {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .event-info {
            flex: 1;
            min-width: 0;
        }

        .event-info strong {
            font-size: 0.95rem;
            color: #333;
            display: block;
            margin-bottom: 5px;
            word-break: break-word;
        }

        .event-details {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            color: #666;
            font-size: 0.8rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .detail-item i {
            color: #667eea;
            font-size: 0.8rem;
        }

        .progress-container {
            width: 120px;
            margin-left: 10px;
            flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .ranking-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .progress-container {
                width: 100%;
                margin-left: 0;
            }
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.75rem;
            color: #666;
        }

        .progress-bar {
            width: 100%;
            height: 5px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 3px;
            transition: width 1.5s ease-out;
        }

        /* ====== NO DATA SECTION ====== */
        .no-data {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .no-data i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
            display: block;
        }

        .no-data h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .no-data p {
            color: #666;
            margin-bottom: 20px;
            font-size: 0.9rem;
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
            
            .charts-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .chart-container {
                height: 250px;
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
            
            .chart-container {
                height: 280px;
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

        .chart-container canvas {
            animation: chartIn 1s ease;
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
            <h2>Analitik Dashboard 
               
            </h2>
            <p>Lihat statistik dan analisis data event untuk pengambilan keputusan yang lebih baik</p>
            <div class="time-status">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-sync-alt refresh-btn" id="manualRefresh"></i>
                    <span id="lastUpdateTime">Terakhir diperbarui: <?php echo date('H:i:s'); ?></span>
                </span>
                
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" id="statEvents">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number" id="statEventsNumber"><?php echo $total_events; ?></div>
                <div class="stat-label">Total Event</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>Live data</span>
                </div>
            </div>

            <div class="stat-card" id="statTickets">
                <div class="stat-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-number" id="statTicketsNumber"><?php echo $total_tickets; ?></div>
                <div class="stat-label">Tiket Terjual</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>Live data</span>
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
                    <span>Live data</span>
                </div>
            </div>

            <div class="stat-card" id="statUsers">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number" id="statUsersNumber"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Pengguna</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>Live data</span>
                </div>
            </div>
        </div>

        <?php if (count($event_types_data) > 0): ?>
        <!-- Charts Grid -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Distribusi Jenis Event <span class="realtime-badge" style="margin-left: 8px;">LIVE</span></h3>
                <div class="chart-container">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> Trend 6 Bulan Terakhir <span class="realtime-badge" style="margin-left: 8px;">LIVE</span></h3>
                <div class="chart-container">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Ranking Section -->
        <div class="ranking-section">
            <h3><i class="fas fa-trophy"></i> Ranking Jenis Event Paling Diminati <span class="realtime-badge" style="margin-left: 8px;">LIVE</span></h3>
            <div class="ranking-list" id="rankingList">
                <?php
                $rank = 0;
                $max_tickets = max(array_column($event_types_data, 'total_tickets_sold')) ?: 1;
                foreach ($event_types_data as $index => $row):
                    $rank++;
                    $percentage = $max_tickets > 0 ? ($row['total_tickets_sold'] / $max_tickets) * 100 : 0;
                    $revenue = $row['total_revenue'] ?: 0;
                ?>
                <div class="ranking-item">
                    <div class="rank rank-<?php echo $rank <= 3 ? $rank : 'other'; ?>">
                        <?php echo $rank; ?>
                    </div>
                    <div class="event-info">
                        <strong><?php echo htmlspecialchars($row['event_type']); ?></strong>
                        <div class="event-details">
                            <div class="detail-item">
                                <i class="fas fa-ticket-alt"></i>
                                <span class="ticket-count"><?php echo $row['total_tickets_sold']; ?> Tiket</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-user-friends"></i>
                                <span class="attendee-count"><?php echo $row['unique_attendees']; ?> Peserta</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-money-bill-wave"></i>
                                <span class="revenue-amount">Rp<?php echo number_format($revenue, 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="progress-container">
                        <div class="progress-label">
                            <span>Popularitas</span>
                            <span class="percentage-text"><?php echo number_format($percentage, 1); ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill percentage-bar" style="width: 0%;" data-width="<?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Popular Events -->
        <?php if (mysqli_num_rows($popular_result) > 0): ?>
        <div class="ranking-section">
            <h3><i class="fas fa-fire"></i> Event Paling Populer <span class="realtime-badge" style="margin-left: 8px;">LIVE</span></h3>
            <div class="ranking-list" id="popularEventsList">
                <?php
                $popular_rank = 0;
                mysqli_data_seek($popular_result, 0);
                while($row = mysqli_fetch_assoc($popular_result)):
                    $popular_rank++;
                    $tickets = $row['tickets_sold'] ?: 0;
                    $revenue = $row['revenue'] ?: 0;
                ?>
                <div class="ranking-item">
                    <div class="rank rank-<?php echo $popular_rank <= 3 ? $popular_rank : 'other'; ?>">
                        <?php echo $popular_rank; ?>
                    </div>
                    <div class="event-info">
                        <strong><?php echo htmlspecialchars($row['event_name']); ?></strong>
                        <div class="event-details">
                            <div class="detail-item">
                                <i class="fas fa-tag"></i>
                                <span><?php echo htmlspecialchars($row['event_type']); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-ticket-alt"></i>
                                <span class="popular-ticket-count"><?php echo $tickets; ?> Tiket</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-money-bill-wave"></i>
                                <span class="popular-revenue">Rp<?php echo number_format($revenue, 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="no-data">
            <i class="fas fa-chart-bar"></i>
            <h3>Belum Ada Data Analitik</h3>
            <p>Tambahkan event dan data tiket terlebih dahulu untuk melihat analitik yang komprehensif.</p>
            <div style="display: flex; flex-direction: column; gap: 0.8rem; align-items: center; margin-top: 1.5rem;">
                <a href="add_event.php" class="btn" style="width: 100%; max-width: 250px; justify-content: center;">
                    <i class="fas fa-plus-circle"></i> Tambah Event
                </a>
                <a href="events.php" class="btn" style="width: 100%; max-width: 250px; justify-content: center; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-calendar-alt"></i> Lihat Semua Event
                </a>
            </div>
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
                <p>&copy; 2025 LIVE FEST. <span id="footerClock"><?php echo date('H:i:s'); ?></span></p>
            </div>
        </div>
    </footer>

    <?php if (count($event_types_data) > 0): ?>
    <script>
        // Real-time Analytics System
        let pieChart = null;
        let lineChart = null;
        let refreshTimer = null;
        let countdownTimer = null;
        let remainingSeconds = 300; // 5 minutes in seconds
        let isUpdating = false;
        
        // Chart colors
        const chartColors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
            '#9966FF', '#FF9F40', '#FF6384', '#36A2EB'
        ];
        
        // Initialize on page load
        $(document).ready(function() {
            initializeCharts();
            initializeProgressBars();
            initializeLiveClock();
            startAutoRefreshCountdown();
            
            // Manual refresh button
            $('#manualRefresh').on('click', function() {
                refreshData();
            });
            
            // Auto-refresh every 5 minutes
            setInterval(refreshData, 300000); // 5 minutes
            
            // Update footer clock every second
            setInterval(updateFooterClock, 1000);
        });
        
        // Initialize charts
        function initializeCharts() {
            // Pie Chart
            const pieCtx = document.getElementById('pieChart');
            if (pieCtx) {
                pieChart = new Chart(pieCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($tickets_data); ?>,
                            backgroundColor: chartColors,
                            borderColor: 'rgba(255, 255, 255, 0.8)',
                            borderWidth: 1.5,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    font: {
                                        size: window.innerWidth < 768 ? 10 : 11
                                    },
                                    boxWidth: 10
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.raw + ' tiket';
                                        return label;
                                    }
                                },
                                titleFont: {
                                    size: 12
                                },
                                bodyFont: {
                                    size: 11
                                }
                            }
                        },
                        animation: {
                            animateScale: true,
                            animateRotate: true,
                            duration: 1500
                        },
                        cutout: '60%'
                    }
                });
            }
            
            // Line Chart
            const lineCtx = document.getElementById('lineChart');
            if (lineCtx) {
                lineChart = new Chart(lineCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($monthly_labels); ?>,
                        datasets: [{
                            label: 'Tiket Terjual',
                            data: <?php echo json_encode($monthly_tickets); ?>,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: '#667eea',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    font: {
                                        size: window.innerWidth < 768 ? 11 : 12
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        size: window.innerWidth < 768 ? 10 : 11
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        size: window.innerWidth < 768 ? 10 : 11
                                    }
                                }
                            }
                        },
                        animation: {
                            duration: 1500
                        }
                    }
                });
            }
        }
        
        // Initialize progress bars
        function initializeProgressBars() {
            const progressBars = document.querySelectorAll('.percentage-bar');
            progressBars.forEach(bar => {
                const width = bar.getAttribute('data-width');
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });
        }
        
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
        
        // Start auto-refresh countdown
        function startAutoRefreshCountdown() {
            clearInterval(countdownTimer);
            remainingSeconds = 300; // Reset to 5 minutes
            
            countdownTimer = setInterval(function() {
                remainingSeconds--;
                
                const minutes = Math.floor(remainingSeconds / 60);
                const seconds = remainingSeconds % 60;
                
                $('#nextUpdateCountdown').text(`Auto refresh: ${minutes}:${seconds.toString().padStart(2, '0')}`);
                
                if (remainingSeconds <= 0) {
                    clearInterval(countdownTimer);
                    refreshData();
                }
            }, 1000);
        }
        
        // Refresh data function
        function refreshData() {
            if (isUpdating) return;
            
            isUpdating = true;
            showRefreshIndicator();
            
            // Animate refresh button
            $('#manualRefresh').css('animation', 'spin 1s linear');
            
            $.ajax({
                url: 'analytics.php?get_realtime_data=1',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        updateAllData(response);
                        showNotification('Data berhasil diperbarui!', 'success');
                        startAutoRefreshCountdown(); // Reset countdown
                    } else {
                        showNotification('Gagal memperbarui data', 'error');
                    }
                },
                error: function() {
                    showNotification('Koneksi error, coba lagi nanti', 'error');
                },
                complete: function() {
                    $('#manualRefresh').css('animation', '');
                    isUpdating = false;
                    setTimeout(() => {
                        $('.auto-refresh-indicator').remove();
                    }, 2000);
                }
            });
        }
        
        // Update all data with new response
        function updateAllData(data) {
            // Update timestamp
            updateLastUpdateTime();
            
            // Update stats cards with animation
            updateStatCard('#statEventsNumber', data.total_events);
            updateStatCard('#statTicketsNumber', data.total_tickets);
            updateStatCard('#statRevenueNumber', 'Rp' + formatNumber(data.total_revenue));
            updateStatCard('#statUsersNumber', data.total_users);
            
            // Update charts
            if (pieChart) {
                pieChart.data.labels = data.labels;
                pieChart.data.datasets[0].data = data.tickets_data;
                pieChart.update('active');
            }
            
            if (lineChart) {
                lineChart.data.labels = data.monthly_labels;
                lineChart.data.datasets[0].data = data.monthly_tickets;
                lineChart.update('active');
            }
            
            // Update ranking list
            updateRankingList(data.event_types_data);
            
            // Update popular events
            updatePopularEvents(data.popular_events);
            
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
        
        // Update ranking list
        function updateRankingList(eventTypesData) {
            if (!eventTypesData || eventTypesData.length === 0) return;
            
            const maxTickets = Math.max(...eventTypesData.map(item => item.total_tickets_sold || 0));
            
            // Update each ranking item
            $('.ranking-item').each(function(index) {
                if (index < eventTypesData.length) {
                    const data = eventTypesData[index];
                    const percentage = maxTickets > 0 ? (data.total_tickets_sold / maxTickets) * 100 : 0;
                    const revenue = data.total_revenue || 0;
                    
                    // Update text content
                    $(this).find('strong').text(data.event_type);
                    $(this).find('.ticket-count').text(data.total_tickets_sold + ' Tiket');
                    $(this).find('.attendee-count').text(data.unique_attendees + ' Peserta');
                    $(this).find('.revenue-amount').text('Rp' + formatNumber(revenue));
                    $(this).find('.percentage-text').text(percentage.toFixed(1) + '%');
                    
                    // Animate progress bar
                    const progressBar = $(this).find('.percentage-bar');
                    progressBar.attr('data-width', percentage + '%');
                    
                    setTimeout(() => {
                        progressBar.css('width', percentage + '%');
                    }, 300 + (index * 100));
                    
                    // Animate rank change
                    $(this).css('animation', 'statUpdate 0.5s ease');
                    setTimeout(() => {
                        $(this).css('animation', '');
                    }, 500);
                }
            });
        }
        
        // Update popular events
        function updatePopularEvents(popularEvents) {
            if (!popularEvents || popularEvents.length === 0) return;
            
            $('.ranking-item').each(function(index) {
                if (index < popularEvents.length) {
                    const data = popularEvents[index];
                    const tickets = data.tickets_sold || 0;
                    const revenue = data.revenue || 0;
                    
                    // Update text content
                    $(this).find('strong').text(data.event_name);
                    $(this).find('.popular-ticket-count').text(tickets + ' Tiket');
                    $(this).find('.popular-revenue').text('Rp' + formatNumber(revenue));
                    
                    // Animate update
                    $(this).css('animation', 'statUpdate 0.5s ease');
                    setTimeout(() => {
                        $(this).css('animation', '');
                    }, 500);
                }
            });
        }
        
        // Show refresh indicator
        function showRefreshIndicator() {
            $('.auto-refresh-indicator').remove();
            
            const indicator = $(`
                <div class="auto-refresh-indicator">
                    <i class="fas fa-sync-alt fa-spin"></i>
                    Memperbarui data...
                    <div class="refresh-progress">
                        <div class="refresh-progress-bar"></div>
                    </div>
                </div>
            `);
            
            $('body').append(indicator);
            
            // Animate progress bar
            setTimeout(() => {
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
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Handle orientation change
        window.addEventListener('orientationchange', function() {
            setTimeout(() => {
                window.scrollTo(0, 0);
                
                // Re-initialize charts on orientation change
                if (pieChart) pieChart.resize();
                if (lineChart) lineChart.resize();
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
    <?php endif; ?>
</body>
</html>
<?php
// Tutup koneksi database
mysqli_close($conn);
?>