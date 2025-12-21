<?php
include 'config.php';
require_admin();

// Ambil data event berdasarkan ID
$event_id = $_GET['id'] ?? 0;
$query = "SELECT * FROM events WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($result);

if (!$event) {
    header("Location: edit_events.php");
    exit();
}

// Proses update event
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_name = mysqli_real_escape_string($conn, $_POST['event_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $price = floatval($_POST['price']);
    $available_tickets = intval($_POST['available_tickets']);
    $organizer = mysqli_real_escape_string($conn, $_POST['organizer']);
    $event_type = mysqli_real_escape_string($conn, $_POST['event_type']);
    $band_name = mysqli_real_escape_string($conn, $_POST['band_name'] ?? '');
    
    // HANDLE UPLOAD FOTO BAND
    $band_photo = $event['band_photo'] ?? ''; // Simpan foto lama sebagai default
    
    if (isset($_FILES['band_photo']) && $_FILES['band_photo']['error'] == 0) {
        $target_dir = "uploads/";
        
        // Buat folder uploads jika belum ada
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Generate nama file unik
        $file_extension = pathinfo($_FILES['band_photo']['name'], PATHINFO_EXTENSION);
        $file_name = 'band_' . time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $file_name;
        
        // Validasi file
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if ($_FILES['band_photo']['size'] <= $max_size) {
                if (move_uploaded_file($_FILES['band_photo']['tmp_name'], $target_file)) {
                    // Hapus foto lama jika ada dan bukan foto default
                    if (!empty($event['band_photo']) && file_exists($target_dir . $event['band_photo'])) {
                        unlink($target_dir . $event['band_photo']);
                    }
                    
                    $band_photo = $file_name;
                    $_SESSION['success'] = "✅ Foto band berhasil diupload!";
                } else {
                    $_SESSION['error'] = "❌ Gagal mengupload foto band!";
                }
            } else {
                $_SESSION['error'] = "❌ Ukuran file terlalu besar! Maksimal 2MB.";
            }
        } else {
            $_SESSION['error'] = "❌ Format file tidak didukung! Gunakan JPG, PNG, GIF, atau WEBP.";
        }
    }
    
    // Update di database - TAMBAH KOLOM band_photo dan band_name
    $query = "UPDATE events SET 
              event_name=?, 
              description=?, 
              event_date=?, 
              event_time=?, 
              location=?, 
              price=?, 
              available_tickets=?, 
              organizer=?, 
              event_type=?,
              band_name=?,
              band_photo=?
              WHERE id=?";
    
    $stmt = mysqli_prepare($conn, $query);
    
    mysqli_stmt_bind_param($stmt, "sssssdissssi", 
        $event_name, 
        $description, 
        $event_date, 
        $event_time, 
        $location, 
        $price, 
        $available_tickets, 
        $organizer, 
        $event_type,
        $band_name,
        $band_photo,
        $event_id
    );
    
    if (mysqli_stmt_execute($stmt)) {
        // Update ke Google Calendar jika ada google_event_id
        if (!empty($event['google_event_id'])) {
            $event_data = [
                'event_name' => $event_name,
                'location' => $location,
                'description' => $description,
                'price' => $price,
                'available_tickets' => $available_tickets,
                'organizer' => $organizer,
                'event_type' => $event_type,
                'band_name' => $band_name,
                'start_datetime' => $event_date . 'T' . $event_time . ':00',
                'end_datetime' => $event_date . 'T' . date('H:i:s', strtotime($event_time . ' +2 hours')),
            ];

            if (function_exists('updateEventInGoogleCalendar')) {
                $google_result = updateEventInGoogleCalendar($event['google_event_id'], $event_data);
                
                if ($google_result['success']) {
                    $_SESSION['success'] = "✅ Event berhasil diupdate di sistem dan Google Calendar!";
                } else {
                    $_SESSION['success'] = "✅ Event berhasil diupdate di sistem! (Google Calendar: " . $google_result['error'] . ")";
                }
            } else {
                $_SESSION['success'] = "✅ Event berhasil diupdate di sistem! (Fungsi Google Calendar tidak tersedia)";
            }
        } else {
            $_SESSION['success'] = "✅ Event berhasil diupdate di sistem!";
        }
        
        header("Location: edit_events.php");
        exit();
    } else {
        $error = "❌ Gagal mengupdate event: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit Event - LIVE FEST</title>
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

        /* ====== FORM CONTAINER MOBILE ====== */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .form-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .form-header h3 {
            font-size: 1.3rem;
            color: #333;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Google Calendar Info */
        .google-sync-info {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f4ff 100%);
            border: 1px solid rgba(66, 133, 244, 0.2);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .google-icon {
            font-size: 1.5rem;
            color: #1a73e8;
            background: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(26, 115, 232, 0.2);
            flex-shrink: 0;
        }

        /* ====== FORM GRID MOBILE ====== */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-section {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid rgba(102, 126, 234, 0.1);
            position: relative;
            overflow: hidden;
        }

        .form-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* ====== FORM GROUPS MOBILE ====== */
        .form-group {
            margin-bottom: 15px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            color: #667eea;
            width: 16px;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
            font-family: 'Segoe UI', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23667eea' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }

        /* Currency Input */
        .currency-input {
            position: relative;
        }

        .currency-symbol {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: bold;
            color: #667eea;
            z-index: 1;
            font-size: 1rem;
        }

        .currency-input input {
            padding-left: 45px;
        }

        /* Photo Preview */
        .photo-preview-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 8px;
            margin-top: 10px;
        }

        .photo-preview {
            width: 100%;
            max-width: 250px;
            height: 150px;
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 2px solid white;
            cursor: pointer;
        }

        .photo-info {
            font-size: 0.8rem;
            color: #666;
            text-align: center;
            background: white;
            padding: 8px 12px;
            border-radius: 4px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        /* File Upload */
        .file-upload-area {
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: rgba(102, 126, 234, 0.03);
            margin-top: 10px;
        }

        .file-upload-area i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
            display: block;
        }

        .file-upload-area h4 {
            color: #333;
            margin-bottom: 5px;
            font-size: 1rem;
        }

        .file-upload-area p {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .file-upload-area input[type="file"] {
            display: none;
        }

        /* ====== FORM ACTIONS MOBILE ====== */
        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid rgba(0, 0, 0, 0.05);
        }

        .btn-submit {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 44px;
        }

        .btn-back {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            text-align: center;
            min-height: 44px;
        }

        /* ====== MODAL MOBILE ====== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            padding: 15px;
        }

        .modal-content {
            max-width: 90%;
            max-height: 80%;
            border-radius: 8px;
            overflow: hidden;
            object-fit: contain;
        }

        .modal-close {
            position: fixed;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            z-index: 10001;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 50%;
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
            
            .form-container {
                padding: 25px;
                max-width: 800px;
                margin: 0 auto;
            }
            
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .form-actions {
                flex-direction: row;
                justify-content: center;
            }
            
            .btn-submit, .btn-back {
                flex: 1;
                max-width: 200px;
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
            
            .form-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn-submit:hover,
            .btn-back:hover {
                transform: none;
            }
            
            .file-upload-area:active {
                background: rgba(102, 126, 234, 0.1);
                transform: scale(0.98);
            }
        }
    </style>
</head>
<body>
    <!-- Modal for Image Preview -->
    <div id="imageModal" class="modal">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <!-- Header yang sama dengan file lain -->
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
            <h2>Edit Event</h2>
            <p>Perbarui detail event dengan informasi terbaru. Semua perubahan akan disimpan ke sistem dan Google Calendar jika sudah terintegrasi.</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($event['google_event_id'])): ?>
            <div class="google-sync-info">
                <div class="google-icon">
                    <i class="fab fa-google"></i>
                </div>
                <div>
                    <h4 style="margin: 0; color: #1a73e8;">📅 Google Calendar Sync Active</h4>
                    <p style="margin: 5px 0 0 0; color: #666;">Perubahan akan otomatis diupdate di Google Calendar</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <div class="form-header">
                <h3><i class="fas fa-edit"></i> Edit Event: <?php echo htmlspecialchars($event['event_name']); ?></h3>
            </div>

            <form method="POST" enctype="multipart/form-data" id="editEventForm">
                <div class="form-grid">
                    <!-- Informasi Event -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-info-circle"></i> Informasi Event
                        </div>
                        
                        <div class="form-group">
                            <label for="event_name"><i class="fas fa-heading"></i> Nama Event</label>
                            <input type="text" id="event_name" name="event_name" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($event['event_name']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="event_type"><i class="fas fa-tag"></i> Jenis Event</label>
                            <select id="event_type" name="event_type" class="form-control" required>
                                <option value="Music Event" <?php echo ($event['event_type'] ?? '') == 'Music Event' ? 'selected' : ''; ?>>🎵 Music Event</option>
                                <option value="Seminar" <?php echo ($event['event_type'] ?? '') == 'Seminar' ? 'selected' : ''; ?>>🎓 Seminar</option>
                                <option value="Workshop" <?php echo ($event['event_type'] ?? '') == 'Workshop' ? 'selected' : ''; ?>>🔧 Workshop</option>
                                <option value="Competition" <?php echo ($event['event_type'] ?? '') == 'Competition' ? 'selected' : ''; ?>>🏆 Competition</option>
                                <option value="Festival" <?php echo ($event['event_type'] ?? '') == 'Festival' ? 'selected' : ''; ?>>🎪 Festival</option>
                                <option value="Sports" <?php echo ($event['event_type'] ?? '') == 'Sports' ? 'selected' : ''; ?>>⚽ Sports</option>
                                <option value="Charity" <?php echo ($event['event_type'] ?? '') == 'Charity' ? 'selected' : ''; ?>>❤️ Charity</option>
                                <option value="Conference" <?php echo ($event['event_type'] ?? '') == 'Conference' ? 'selected' : ''; ?>>💼 Conference</option>
                                <option value="Exhibition" <?php echo ($event['event_type'] ?? '') == 'Exhibition' ? 'selected' : ''; ?>>🖼️ Exhibition</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="description"><i class="fas fa-align-left"></i> Deskripsi Event</label>
                            <textarea id="description" name="description" rows="4" 
                                      class="form-control" required><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Waktu & Lokasi -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-calendar-alt"></i> Waktu & Lokasi
                        </div>

                        <div class="form-group">
                            <label for="event_date"><i class="fas fa-calendar-day"></i> Tanggal Event</label>
                            <input type="date" id="event_date" name="event_date" 
                                   class="form-control"
                                   value="<?php echo $event['event_date']; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="event_time"><i class="fas fa-clock"></i> Waktu Event</label>
                            <input type="time" id="event_time" name="event_time" 
                                   class="form-control"
                                   value="<?php echo date('H:i', strtotime($event['event_time'])); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="location"><i class="fas fa-map-marker-alt"></i> Lokasi</label>
                            <input type="text" id="location" name="location" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($event['location']); ?>" required>
                        </div>
                    </div>

                    <!-- Informasi Band -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-music"></i> Informasi Band
                        </div>

                        <div class="form-group">
                            <label for="band_name"><i class="fas fa-users"></i> Nama Band/Artist</label>
                            <input type="text" id="band_name" name="band_name" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($event['band_name'] ?? ''); ?>"
                                   placeholder="Masukkan nama band atau artist">
                        </div>

                        <?php if (!empty($event['band_photo']) && file_exists('uploads/' . $event['band_photo'])): ?>
                        <div class="form-group">
                            <label><i class="fas fa-image"></i> Foto Band Saat Ini</label>
                            <div class="photo-preview-container">
                                <img src="uploads/<?php echo htmlspecialchars($event['band_photo']); ?>" 
                                     alt="Foto <?php echo htmlspecialchars($event['band_name'] ?? 'Band'); ?>"
                                     class="photo-preview"
                                     onclick="openModal(this.src)">
                                <div class="photo-info">
                                    File: <?php echo htmlspecialchars($event['band_photo']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label><i class="fas fa-camera"></i> Upload Foto Band Baru</label>
                            <div class="file-upload-area" onclick="document.getElementById('band_photo').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h4>Klik untuk Upload Foto</h4>
                                <p>Format: JPG, PNG, GIF, WEBP | Maksimal: 2MB</p>
                                <?php if (!empty($event['band_photo'])): ?>
                                <p class="photo-info">Kosongkan jika tidak ingin mengganti foto</p>
                                <?php endif; ?>
                            </div>
                            <input type="file" id="band_photo" name="band_photo" accept="image/*" 
                                   onchange="previewPhoto(event)">
                        </div>
                    </div>

                    <!-- Tiket & Harga -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-ticket-alt"></i> Tiket & Harga
                        </div>

                        <div class="form-group">
                            <label for="price"><i class="fas fa-money-bill-wave"></i> Harga Tiket (Rp)</label>
                            <div class="currency-input">
                                <span class="currency-symbol">Rp</span>
                                <input type="number" id="price" name="price" 
                                       class="form-control"
                                       value="<?php echo $event['price']; ?>" 
                                       required
                                       min="1"
                                       step="1"
                                       autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="available_tickets"><i class="fas fa-ticket-alt"></i> Jumlah Tiket Tersedia</label>
                            <input type="number" id="available_tickets" name="available_tickets" 
                                   class="form-control"
                                   min="1" 
                                   value="<?php echo $event['available_tickets']; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="organizer"><i class="fas fa-building"></i> Penyelenggara</label>
                            <input type="text" id="organizer" name="organizer" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($event['organizer']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i>
                        Update Event <?php echo !empty($event['google_event_id']) ? '& Google Calendar' : ''; ?>
                    </button>
                    <a href="edit_events.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Event
                    </a>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer yang sama dengan file lain -->
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
        // Set tanggal minimal hari ini
        const today = new Date().toISOString().split('T')[0];
        const eventDateInput = document.getElementById('event_date');
        if (eventDateInput) {
            eventDateInput.min = today;
        }

        // Preview foto sebelum upload
        function previewPhoto(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Hapus preview lama jika ada
                    const oldPreview = document.querySelector('.photo-preview');
                    const uploadArea = document.querySelector('.file-upload-area');
                    
                    if (oldPreview) {
                        oldPreview.src = e.target.result;
                    } else {
                        // Buat container preview baru
                        const previewContainer = document.createElement('div');
                        previewContainer.className = 'photo-preview-container';
                        
                        const preview = document.createElement('img');
                        preview.src = e.target.result;
                        preview.className = 'photo-preview';
                        preview.onclick = () => openModal(preview.src);
                        
                        const info = document.createElement('div');
                        info.className = 'photo-info';
                        info.textContent = 'Preview foto baru';
                        
                        previewContainer.appendChild(preview);
                        previewContainer.appendChild(info);
                        
                        // Sisipkan setelah upload area
                        uploadArea.parentNode.insertBefore(previewContainer, uploadArea.nextSibling);
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        // Image modal functionality
        function initializeModal() {
            window.openModal = function(src) {
                const modal = document.getElementById('imageModal');
                const modalImg = document.getElementById('modalImage');
                
                if (src) {
                    modalImg.src = src;
                    modal.style.display = 'flex';
                } else {
                    modal.style.display = 'none';
                }
            };
            
            window.closeModal = function() {
                document.getElementById('imageModal').style.display = 'none';
            };
            
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
        }

        // Animasi saat form submit
        document.getElementById('editEventForm')?.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.btn-submit');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            submitBtn.disabled = true;
            
            // Simulasi loading animation
            setTimeout(() => {
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }, 2000);
        });

        // Add animation untuk form sections
        const formSections = document.querySelectorAll('.form-section');
        formSections.forEach((section, index) => {
            section.style.animationDelay = `${(index + 1) * 0.1}s`;
        });

        // Initialize everything
        document.addEventListener('DOMContentLoaded', function() {
            initializeModal();
            
            // Format price input
            const priceInput = document.getElementById('price');
            if (priceInput) {
                priceInput.addEventListener('input', function() {
                    // Remove non-numeric characters
                    this.value = this.value.replace(/[^\d]/g, '');
                });
            }

            // Validasi tanggal
            const eventDate = document.getElementById('event_date');
            if (eventDate) {
                eventDate.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (selectedDate < today) {
                        alert('⚠️ Tanggal event tidak boleh di masa lalu!');
                        this.value = today.toISOString().split('T')[0];
                    }
                });
            }

            // Auto close alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });

            // Touch-friendly improvements
            const buttons = document.querySelectorAll('.btn-submit, .btn-back, .file-upload-area');
            buttons.forEach(button => {
                button.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                button.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        });
    </script>
</body>
</html>
<?php
// Tutup koneksi database
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);
?>