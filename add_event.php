<?php
include 'config.php';
require_login();

if (!is_admin()) {
    header("Location: dashboard.php");
    exit();
}

// Cek status koneksi Google Calendar
$googleStatus = checkGoogleCalendarConnection();
$google_connected = $googleStatus['connected'];

// ========== FORCE CONNECT GOOGLE CALENDAR DULU ==========
if (!$google_connected) {
    $_SESSION['error'] = "⚠️ Anda HARUS connect Google Calendar terlebih dahulu sebelum bisa membuat event!";
    header("Location: auth_google.php");
    exit();
}

// ========== PROSES FORM ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_name = mysqli_real_escape_string($conn, $_POST['event_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    
    // PERBAIKAN: Konversi harga dari format 50.000 menjadi 50000
    $price_input = $_POST['price'];
    $price = str_replace('.', '', $price_input); // Hapus titik
    $price = (int) $price; // Konversi ke integer
    
    $available_tickets = $_POST['available_tickets'];
    $organizer = mysqli_real_escape_string($conn, $_POST['organizer']);
    $event_type = mysqli_real_escape_string($conn, $_POST['event_type']);
    $band_name = mysqli_real_escape_string($conn, $_POST['band_name'] ?? '');
    
    // Validasi harga
    if ($price < 0) {
        $_SESSION['error'] = "❌ Harga tidak boleh negatif!";
        header("Location: add_event.php");
        exit();
    }
    
    // HANDLE UPLOAD FOTO BAND
    $band_photo = '';
    
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
                    $band_photo = $file_name;
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
    
    // Query INSERT dengan band_photo dan band_name
    $query = "INSERT INTO events (event_name, description, event_date, event_time, location, price, available_tickets, organizer, event_type, band_name, band_photo) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssssdissss", 
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
        $band_photo
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $event_id = mysqli_insert_id($conn);
        
        // ========== TAMBAH KE GOOGLE CALENDAR ==========
        $event_data = array(
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
            'event_id' => $event_id
        );

        // Panggil function Google Calendar
        $google_result = addEventToUserGoogleCalendar($_SESSION['user_id'], $event_data);
        
        // ========== PERBAIKAN UTAMA DI SINI ==========
        // Pastikan google_event_id selalu disimpan, bahkan jika fungsi Google Calendar gagal
        
        $google_event_id = null;
        $google_success = false;
        $google_event_link = null;
        
        // Cek apakah fungsi mengembalikan google_event_id
        if (isset($google_result['success']) && $google_result['success']) {
            if (!empty($google_result['google_event_id'])) {
                $google_event_id = $google_result['google_event_id'];
                $google_success = true;
            } elseif (!empty($google_result['id'])) {
                $google_event_id = $google_result['id'];
                $google_success = true;
            } elseif (!empty($google_result['event_id'])) {
                $google_event_id = $google_result['event_id'];
                $google_success = true;
            }
            
            // Simpan link ke Google Calendar jika ada
            if (!empty($google_result['event_link'])) {
                $google_event_link = $google_result['event_link'];
            } elseif (!empty($google_result['htmlLink'])) {
                $google_event_link = $google_result['htmlLink'];
            } elseif (!empty($google_result['link'])) {
                $google_event_link = $google_result['link'];
            }
        }
        
        // JIKA TIDAK ADA google_event_id, BUAT MANUAL
        if (empty($google_event_id)) {
            // Generate manual google_event_id
            $google_event_id = 'gc_' . date('YmdHis') . '_' . $event_id . '_' . rand(1000, 9999);
            $google_success = false; // Tandai sebagai manual
            
            // Log warning
            error_log("WARNING: Google Calendar tidak mengembalikan event_id. Menggunakan manual: " . $google_event_id);
        }
        
        // ========== SELALU UPDATE google_event_id KE DATABASE ==========
        $update_query = "UPDATE events SET google_event_id = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $google_event_id, $event_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            if ($google_success) {
                $success_message = "🎉 Event berhasil ditambahkan dan sudah masuk ke Google Calendar!";
                $google_link = $google_event_link ?: "#";
                $google_status = "connected";
            } else {
                // Jika google_event_id manual (Google Calendar gagal)
                $success_message = "✅ Event berhasil ditambahkan ke database! (Google Calendar gagal)";
                $google_link = "#";
                $google_status = "failed";
            }
            
            // Simpan semua data ke session untuk ditampilkan
            $_SESSION['event_success'] = $success_message;
            $_SESSION['event_name'] = $event_name;
            $_SESSION['band_name'] = $band_name ?: 'Tanpa nama band';
            $_SESSION['location'] = $location;
            $_SESSION['price'] = $price;
            $_SESSION['event_date'] = $event_date;
            $_SESSION['event_time'] = $event_time;
            $_SESSION['google_event_id'] = $google_event_id;
            $_SESSION['google_event_link'] = $google_link;
            $_SESSION['google_status'] = $google_status;
            $_SESSION['organizer'] = $organizer;
            $_SESSION['event_type'] = $event_type;
            
            // Simpan pesan error Google jika ada
            if (isset($google_result['error'])) {
                $_SESSION['google_error'] = $google_result['error'];
            }
            
        } else {
            $_SESSION['error'] = "✅ Event berhasil ditambahkan ke database! 
                     <br>⚠️ Gagal menyimpan Google Event ID: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($update_stmt);
        mysqli_stmt_close($stmt);
        
        header("Location: add_event.php");
        exit();
    } else {
        $error = "❌ Gagal menambahkan event: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tambah Event - LIVE FEST</title>
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
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-logout {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .btn-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .btn-google {
            background: linear-gradient(135deg, #4285F4 0%, #34A853 100%);
            color: white;
        }

        .btn-google:hover {
            background: linear-gradient(135deg, #3367D6 0%, #2E8B57 100%);
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
            transition: all 0.3s ease;
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

        /* ====== FORM STYLES ====== */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #eee;
        }

        .form-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 12px 12px 0 0;
            margin: -20px -20px 20px -20px;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            background: #f8f9fa;
            margin-bottom: 5px;
        }

        .form-control:focus {
            border-color: #667eea;
            background: white;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-group-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 233, 123, 0.4);
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 2px solid transparent;
        }

        .alert-success {
            background: rgba(67, 233, 123, 0.1);
            border-color: #43e97b;
            color: #2e7d32;
        }

        .alert-danger {
            background: rgba(255, 107, 107, 0.1);
            border-color: #ff6b6b;
            color: #d32f2f;
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            border-color: #ffc107;
            color: #856404;
        }

        .alert-info {
            background: rgba(79, 172, 254, 0.1);
            border-color: #4facfe;
            color: #1565c0;
        }

        /* ====== SUCCESS CARD ====== */
        .success-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
            border: 2px solid #43e97b;
            animation: fadeIn 0.5s ease;
        }

        .success-card.failed {
            border-color: #ffc107;
        }

        .success-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .success-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
        }

        .success-card.failed .success-icon {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .success-title {
            font-size: 1.5rem;
            color: #333;
            margin: 0;
        }

        .event-details-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin: 20px 0;
        }

        .event-detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8f9ff;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .detail-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .detail-content h4 {
            color: #333;
            margin-bottom: 5px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .detail-content p {
            color: #666;
            font-size: 1rem;
            line-height: 1.4;
        }

        .google-calendar-section {
            background: #f8f9ff;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            border: 2px solid #e0e0ff;
        }

        .google-calendar-section h4 {
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .google-id {
            background: #f0f0f0;
            padding: 10px 15px;
            border-radius: 8px;
            font-family: monospace;
            margin: 10px 0;
            word-break: break-all;
            font-size: 0.9rem;
        }

        .google-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .google-button {
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 15px;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
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
            
            .event-details-grid {
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
            
            .event-details-grid {
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
        
        .form-card, .success-card {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Photo Preview */
        .photo-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            margin-top: 10px;
            border: 2px solid #667eea;
            display: none;
        }
        
        /* Loading Spinner */
        .spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
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
            <h2>Tambah Event Baru</h2>
            <p>Isi form berikut untuk menambahkan event baru ke sistem dan Google Calendar</p>
        </div>
        
        <!-- Status Koneksi Google Calendar -->
        <div class="alert <?php echo $googleStatus['connected'] ? 'alert-success' : 'alert-warning'; ?>">
            <i class="fas fa-<?php echo $googleStatus['connected'] ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            Status Google Calendar: <?php echo $googleStatus['message']; ?>
            
            <?php if (!$googleStatus['connected']): ?>
                <br><small>Anda harus connect Google Calendar terlebih dahulu untuk membuat event.</small>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['event_success'])): ?>
            <!-- Tampilkan Success Card jika event berhasil dibuat -->
            <div class="success-card <?php echo ($_SESSION['google_status'] == 'failed') ? 'failed' : ''; ?>">
                <div class="success-header">
                    <div class="success-icon">
                        <?php if ($_SESSION['google_status'] == 'connected'): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            <i class="fas fa-exclamation-triangle"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="success-title"><?php echo $_SESSION['event_success']; ?></h3>
                        <p style="color: #666; margin-top: 5px;">
                            <?php 
                            if ($_SESSION['google_status'] == 'connected') {
                                echo 'Event berhasil ditambahkan ke database dan Google Calendar!';
                            } else {
                                echo 'Event berhasil ditambahkan ke database, namun gagal ke Google Calendar.';
                            }
                            ?>
                        </p>
                    </div>
                </div>

                <!-- Detail Event -->
                <div class="event-details-grid">
                    <div class="event-detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="detail-content">
                            <h4>Nama Event</h4>
                            <p><?php echo htmlspecialchars($_SESSION['event_name']); ?></p>
                        </div>
                    </div>

                    <div class="event-detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="detail-content">
                            <h4>Guest Star</h4>
                            <p><?php echo htmlspecialchars($_SESSION['band_name']); ?></p>
                        </div>
                    </div>

                    <div class="event-detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="detail-content">
                            <h4>Lokasi</h4>
                            <p><?php echo htmlspecialchars($_SESSION['location']); ?></p>
                        </div>
                    </div>

                    <div class="event-detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div class="detail-content">
                            <h4>Harga</h4>
                            <p>Rp <?php echo number_format($_SESSION['price'], 0, ',', '.'); ?></p>
                        </div>
                    </div>

                    <div class="event-detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="detail-content">
                            <h4>Tanggal & Waktu</h4>
                            <p><?php echo date('d M Y', strtotime($_SESSION['event_date'])); ?> 
                               <?php echo date('H:i', strtotime($_SESSION['event_time'])); ?></p>
                        </div>
                    </div>

                    <div class="event-detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="detail-content">
                            <h4>Penyelenggara</h4>
                            <p><?php echo htmlspecialchars($_SESSION['organizer']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Google Calendar Section -->
                <div class="google-calendar-section">
                    <h4><i class="fab fa-google"></i> Google Calendar Integration</h4>
                    
                    <?php if ($_SESSION['google_status'] == 'connected'): ?>
                        <p style="color: #2e7d32; font-weight: 600; margin-bottom: 15px;">
                            <i class="fas fa-check-circle"></i> Event berhasil ditambahkan ke Google Calendar!
                        </p>
                        
                        <p><strong>Google Event ID:</strong></p>
                        <div class="google-id"><?php echo htmlspecialchars($_SESSION['google_event_id']); ?></div>
                        
                        <p style="margin-top: 15px;">Klik tombol di bawah untuk melihat atau mengedit event di Google Calendar:</p>
                        
                        <div class="google-buttons">
                            <a href="<?php echo $_SESSION['google_event_link']; ?>" 
                               target="_blank" 
                               class="btn-google google-button">
                                <i class="fab fa-google"></i>
                                Lihat di Google Calendar
                            </a>
                            
                            <a href="https://calendar.google.com/calendar/u/0/r" 
                               target="_blank" 
                               class="btn-info google-button">
                                <i class="fas fa-calendar-alt"></i>
                                Buka Google Calendar
                            </a>
                            
                            <a href="events.php" 
                               class="btn-success google-button">
                                <i class="fas fa-eye"></i>
                                Lihat Semua Event
                            </a>
                        </div>
                        
                    <?php else: ?>
                        <p style="color: #856404; font-weight: 600; margin-bottom: 15px;">
                            <i class="fas fa-exclamation-triangle"></i> Event hanya tersimpan di database lokal
                        </p>
                        
                        <p><strong>Google Event ID (manual):</strong></p>
                        <div class="google-id"><?php echo htmlspecialchars($_SESSION['google_event_id']); ?></div>
                        
                        <?php if (isset($_SESSION['google_error'])): ?>
                            <div style="background: #fff3cd; padding: 12px; border-radius: 8px; margin: 15px 0; color: #856404;">
                                <strong>Error:</strong> <?php echo htmlspecialchars($_SESSION['google_error']); ?>
                                <?php unset($_SESSION['google_error']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <p style="margin-top: 15px;">Coba hubungkan kembali dengan Google Calendar:</p>
                        
                        <div class="google-buttons">
                            <a href="auth_google.php" 
                               class="btn-google google-button">
                                <i class="fab fa-google"></i>
                                Connect Google Calendar
                            </a>
                            
                            <a href="events.php" 
                               class="btn-success google-button">
                                <i class="fas fa-eye"></i>
                                Lihat Semua Event
                            </a>
                            
                            <a href="dashboard.php" 
                               class="btn-info google-button">
                                <i class="fas fa-home"></i>
                                Kembali ke Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tombol untuk membuat event baru -->
                <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                    <p style="margin-bottom: 15px; color: #666;">Ingin membuat event lainnya?</p>
                    <a href="add_event.php" class="btn btn-success" style="padding: 12px 30px;">
                        <i class="fas fa-plus-circle"></i> Buat Event Baru
                    </a>
                </div>
            </div>
            
            <?php 
            // Clear session data setelah ditampilkan
            unset(
                $_SESSION['event_success'],
                $_SESSION['event_name'],
                $_SESSION['band_name'],
                $_SESSION['location'],
                $_SESSION['price'],
                $_SESSION['event_date'],
                $_SESSION['event_time'],
                $_SESSION['google_event_id'],
                $_SESSION['google_event_link'],
                $_SESSION['google_status'],
                $_SESSION['organizer'],
                $_SESSION['event_type']
            ); 
            ?>
        <?php endif; ?>

        <!-- Form hanya ditampilkan jika tidak ada success message atau jika user klik "Buat Event Baru" -->
        <?php if (!isset($_SESSION['event_success'])): ?>
            <div class="form-card">
                <div class="card-header">
                    <h4 style="margin: 0;"><i class="fas fa-calendar-plus"></i> Form Tambah Event</h4>
                </div>
                
                <!-- PERBAIKAN: TAMBAH enctype="multipart/form-data" -->
                <form method="POST" enctype="multipart/form-data" id="eventForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="event_name" class="form-label">
                                <i class="fas fa-theater-masks"></i> Nama Event *
                            </label>
                            <input type="text" class="form-control" id="event_name" name="event_name" 
                                   placeholder="Contoh: Konser Musik Jazz Campus" required
                                   autocomplete="off">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="band_name" class="form-label">
                                <i class="fas fa-star"></i> Nama Guest Star
                            </label>
                            <input type="text" class="form-control" id="band_name" name="band_name" 
                                   placeholder="Contoh: The Campus Band, DJ Elektro, dll"
                                   autocomplete="off">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="event_type" class="form-label">
                                <i class="fas fa-tag"></i> Jenis Event *
                            </label>
                            <select class="form-control" id="event_type" name="event_type" required>
                                <option value="Music Festival">🎵 Music Festival</option>
                                <option value="Concert">🎤 Concert</option>
                                <option value="Acoustic Night">🎸 Acoustic Night</option>
                                <option value="Battle of Band">🥁 Battle of Band</option>
                                <option value="Seminar">🎓 Seminar</option>
                                <option value="Workshop">🔧 Workshop</option>
                                <option value="Competition">🏆 Competition</option>
                                <option value="Sports">⚽ Sports</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="band_photo" class="form-label">
                                <i class="fas fa-camera"></i> Foto Band
                            </label>
                            <input type="file" class="form-control" id="band_photo" name="band_photo" accept="image/*">
                            <small class="text-muted" style="display: block; margin-top: 5px;">Format: JPG, PNG, GIF, WEBP | Maksimal: 2MB</small>
                            <img id="photoPreview" class="photo-preview" alt="Preview foto">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="event_date" class="form-label">
                                <i class="fas fa-calendar-day"></i> Tanggal Event *
                            </label>
                            <input type="date" class="form-control" id="event_date" name="event_date" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="event_time" class="form-label">
                                <i class="fas fa-clock"></i> Waktu Event *
                            </label>
                            <input type="time" class="form-control" id="event_time" name="event_time" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">
                                <i class="fas fa-map-marker-alt"></i> Lokasi *
                            </label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   placeholder="Contoh: Aula Utama Kampus, Gedung Serba Guna" 
                                   required autocomplete="off">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label">
                                <i class="fas fa-ticket-alt"></i> Harga Tiket (Rp) *
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" class="form-control" id="price" name="price" 
                                       placeholder="50.000" 
                                       required
                                       autocomplete="off">
                            </div>
                            <small class="text-muted" style="display: block; margin-top: 5px;">Contoh: 50.000 (akan disimpan sebagai 50000)</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="available_tickets" class="form-label">
                                <i class="fas fa-ticket"></i> Jumlah Tiket Tersedia *
                            </label>
                            <input type="number" class="form-control" id="available_tickets" name="available_tickets" min="1" 
                                   placeholder="Jumlah tiket yang tersedia" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="organizer" class="form-label">
                                <i class="fas fa-building"></i> Penyelenggara *
                            </label>
                            <input type="text" class="form-control" id="organizer" name="organizer" 
                                   placeholder="Nama organisasi/komunitas penyelenggara" 
                                   required autocomplete="off">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="description" class="form-label">
                                <i class="fas fa-align-left"></i> Deskripsi Event *
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="4" 
                                      placeholder="Jelaskan detail event, lineup band, pembicara, dll..." 
                                      required></textarea>
                            <div id="charCount" style="text-align: right; font-size: 0.8rem; color: #666; margin-top: 5px;">
                                0/2000 karakter
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Informasi:</strong> Event yang dibuat akan otomatis tersimpan ke Google Calendar Anda.
                                <?php if ($google_connected): ?>
                                    <br><i class="fas fa-check" style="color: #43e97b;"></i> Status: <strong>Google Calendar terhubung</strong>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-12 text-center mt-4">
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <i class="fas fa-plus-circle"></i> 
                                Tambah Event & Simpan ke Google Calendar
                            </button>
                        </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Set tanggal minimal hari ini
            const today = new Date();
            const formattedDate = today.toISOString().split('T')[0];
            if (document.getElementById('event_date')) {
                document.getElementById('event_date').min = formattedDate;
                document.getElementById('event_date').value = formattedDate;
            }
            
            // Set waktu default (2 jam dari sekarang)
            const twoHoursLater = new Date(today.getTime() + 2 * 60 * 60 * 1000);
            const hours = twoHoursLater.getHours().toString().padStart(2, '0');
            const minutes = twoHoursLater.getMinutes().toString().padStart(2, '0');
            if (document.getElementById('event_time')) {
                document.getElementById('event_time').value = `${hours}:${minutes}`;
            }
            
            // Auto-format price input dengan titik
            const priceInput = document.getElementById('price');
            if (priceInput) {
                priceInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\./g, '');
                    value = value.replace(/\D/g, '');
                    
                    if (value.length > 3) {
                        value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    }
                    
                    e.target.value = value;
                });
            }
            
            // Preview foto sebelum upload
            const fileInput = document.getElementById('band_photo');
            const preview = document.getElementById('photoPreview');
            
            if (fileInput && preview) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    
                    if (file) {
                        // Validasi ukuran file
                        const maxSize = 2 * 1024 * 1024; // 2MB
                        if (file.size > maxSize) {
                            alert('Ukuran file terlalu besar! Maksimal 2MB.');
                            fileInput.value = '';
                            preview.style.display = 'none';
                            return;
                        }
                        
                        // Validasi tipe file
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('Format file tidak didukung! Gunakan JPG, PNG, GIF, atau WEBP.');
                            fileInput.value = '';
                            preview.style.display = 'none';
                            return;
                        }
                        
                        // Tampilkan preview
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            preview.src = e.target.result;
                            preview.style.display = 'block';
                        }
                        reader.readAsDataURL(file);
                    } else {
                        preview.style.display = 'none';
                    }
                });
            }
            
            // Character counter for description
            const description = document.getElementById('description');
            const charCount = document.getElementById('charCount');
            
            if (description && charCount) {
                description.addEventListener('input', function() {
                    const length = this.value.length;
                    charCount.textContent = `${length}/2000 karakter`;
                    
                    if (length > 1800) {
                        charCount.style.color = '#ff6b6b';
                    } else if (length > 1500) {
                        charCount.style.color = '#ff9800';
                    } else {
                        charCount.style.color = '#666';
                    }
                });
            }
            
            // Form validation and submission
            const form = document.getElementById('eventForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    // Basic validation
                    const eventName = document.getElementById('event_name').value.trim();
                    const eventDate = document.getElementById('event_date').value;
                    const eventTime = document.getElementById('event_time').value;
                    const price = document.getElementById('price').value.trim();
                    
                    if (!eventName || !eventDate || !eventTime || !price) {
                        e.preventDefault();
                        alert('⚠️ Harap lengkapi semua field yang diperlukan!');
                        return;
                    }
                    
                    // Validasi harga
                    const priceNum = parseInt(price.replace(/\./g, ''));
                    if (isNaN(priceNum) || priceNum < 0) {
                        e.preventDefault();
                        alert('❌ Harga tidak valid! Harap masukkan angka yang benar.');
                        return;
                    }
                    
                    // Validasi tanggal tidak boleh di masa lalu
                    const selectedDate = new Date(eventDate);
                    if (selectedDate < new Date(today.toDateString())) {
                        e.preventDefault();
                        alert('❌ Tanggal event tidak boleh di masa lalu!');
                        return;
                    }
                    
                    // Show loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner spinner"></i> Menambahkan Event...';
                    submitBtn.disabled = true;
                    
                    // Scroll ke atas untuk melihat notifikasi
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
            
            // Touch-friendly improvements
            const buttons = document.querySelectorAll('.btn, .btn-submit, nav ul li a, .google-button');
            buttons.forEach(button => {
                button.style.cursor = 'pointer';
                button.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                button.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
            
            // Auto-scroll to top if there's a success message
            if (window.location.hash === '#success') {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    </script>
</body>
</html>

<?php
// Tutup koneksi database
mysqli_close($conn);
?>