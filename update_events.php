<?php
include 'config.php';
require_login();

function updateEventInGoogleCalendar($google_event_id, $event_data) {
    // Placeholder implementation: replace this with real Google Calendar API integration.
    // This stub prevents "undefined function" errors and returns a failure result so the app
    // continues to work without Google integration configured.
    return array(
        'success' => false,
        'error' => 'Google Calendar integration not configured.',
        // Optionally provide 'auth_url' if you have an OAuth flow to direct the user to.
        // 'auth_url' => 'https://your-auth-url.example.com'
    );
}

function addEventToGoogleCalendar($event_data) {
    // Placeholder implementation: replace this with real Google Calendar API integration.
    return array(
        'success' => false,
        'error' => 'Google Calendar integration not configured.',
        // Optionally include 'event_id' and 'event_link' on success.
    );
}

if (!is_admin()) {
    header("Location: dashboard.php");
    exit();
}

// Get event ID from URL
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($event_id == 0) {
    header("Location: events.php");
    exit();
}

// Get current event data
$query = "SELECT * FROM events WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($result);

if (!$event) {
    header("Location: events.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_name = mysqli_real_escape_string($conn, $_POST['event_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $price = $_POST['price'];
    $available_tickets = $_POST['available_tickets'];
    $organizer = mysqli_real_escape_string($conn, $_POST['organizer']);
    $event_type = mysqli_real_escape_string($conn, $_POST['event_type']);

    // Update database
    $query = "UPDATE events SET 
              event_name = ?, description = ?, event_date = ?, event_time = ?, 
              location = ?, price = ?, available_tickets = ?, organizer = ?, event_type = ?
              WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssssdisssi", 
        $event_name, $description, $event_date, $event_time, $location, 
        $price, $available_tickets, $organizer, $event_type, $event_id
    );
    
    if (mysqli_stmt_execute($stmt)) {
        // ========== UPDATE GOOGLE CALENDAR ==========
        if (!empty($event['google_event_id'])) {
            $event_data = array(
                'event_name' => $event_name,
                'location' => $location,
                'description' => $description,
                'price' => $price,
                'available_tickets' => $available_tickets,
                'organizer' => $organizer,
                'start_datetime' => $event_date . 'T' . $event_time . ':00',
                'end_datetime' => $event_date . 'T' . date('H:i:s', strtotime($event_time . ' +2 hours')),
            );
            
            $google_result = updateEventInGoogleCalendar($event['google_event_id'], $event_data);
            
            if ($google_result['success']) {
                $success = "Event berhasil diupdate dan sudah diupdate di Google Calendar! 
                           <br><a href='" . $google_result['event_link'] . "' target='_blank' style='color: white; text-decoration: underline;'>
                           🔗 Lihat Event di Google Calendar</a>";
            } else {
                // Cek jika perlu authentication
                if (isset($google_result['auth_url'])) {
                    $success = "Event berhasil diupdate di database! 
                               <br>⚠️ Untuk sync ke Google Calendar, <a href='" . $google_result['auth_url'] . "' target='_blank'>klik di sini untuk authorize</a>";
                } else {
                    $success = "Event berhasil diupdate di database, tapi gagal update di Google Calendar: " . $google_result['error'];
                }
            }
        } else {
            // Jika belum ada Google Event ID, buat baru
            $event_data = array(
                'event_name' => $event_name,
                'location' => $location,
                'description' => $description,
                'price' => $price,
                'available_tickets' => $available_tickets,
                'organizer' => $organizer,
                'start_datetime' => $event_date . 'T' . $event_time . ':00',
                'end_datetime' => $event_date . 'T' . date('H:i:s', strtotime($event_time . ' +2 hours')),
            );
            
            $google_result = addEventToGoogleCalendar($event_data);
            
            if ($google_result['success']) {
                // Simpan Google Event ID ke database
                $google_event_id = $google_result['event_id'];
                $update_query = "UPDATE events SET google_event_id = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "si", $google_event_id, $event_id);
                mysqli_stmt_execute($update_stmt);
                
                $success = "Event berhasil diupdate dan sudah ditambahkan ke Google Calendar! 
                           <br><a href='" . $google_result['event_link'] . "' target='_blank' style='color: white; text-decoration: underline;'>
                           🔗 Lihat Event di Google Calendar</a>";
            } else {
                $success = "Event berhasil diupdate di database, tapi gagal ke Google Calendar: " . $google_result['error'];
            }
        }
    } else {
        $error = "Gagal mengupdate event: " . mysqli_error($conn);
    }
    
    // Refresh event data
    $query = "SELECT * FROM events WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $event = mysqli_fetch_assoc($result);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Event - Event Mahasiswa</title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #1abc9c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 0;
            box-shadow: var(--shadow);
        }

        header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        nav ul {
            display: flex;
            list-style: none;
            flex-wrap: wrap;
            align-items: center;
        }

        nav ul li {
            margin-right: 1.5rem;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 0;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
        }

        nav ul li a:hover, nav ul li a.active {
            border-bottom: 2px solid white;
        }

        .user-menu {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn, .btn-small {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: var(--accent-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-small {
            padding: 0.3rem 0.8rem;
            font-size: 0.9rem;
        }

        .btn:hover, .btn-small:hover {
            background-color: #16a085;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-logout {
            background-color: var(--danger-color);
        }

        .btn-logout:hover {
            background-color: #c0392b;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        main {
            padding: 2rem 0;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .dashboard-header h2 {
            font-size: 2.2rem;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            font-size: 1.1rem;
            color: #666;
        }

        .form-container {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }

        input, select, textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .google-calendar-info {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .google-status {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 0.8rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 1.5rem 0;
            text-align: center;
            margin-top: 2rem;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-container {
            animation: fadeInUp 0.5s ease forwards;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Sistem Manajemen Event Mahasiswa</h1>
            <nav>
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="events.php">Daftar Event</a></li>
                    <?php if (is_logged_in()): ?>
                        <li><a href="my_tickets.php">Tiket Saya</a></li>
                        <?php if (is_admin()): ?>
                            <li><a href="add_event.php">Tambah Event</a></li>
                            <li><a href="analytics.php">Analitik</a></li>
                        <?php endif; ?>
                        <li class="user-menu">
                            <span>Halo, <?php echo $_SESSION['full_name']; ?></span>
                            <a href="logout.php" class="btn-small btn-logout">Logout</a>
                        </li>
                    <?php else: ?>
                        <li><a href="login.php" class="btn-small">Login</a></li>
                        <li><a href="register.php" class="btn-small">Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="dashboard-header">
            <h2>Update Event</h2>
            <p>Update informasi event yang sudah ada</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="google-calendar-info">
            <strong>📅 Google Calendar Integration</strong>
            <p>Perubahan event akan otomatis tersinkronisasi dengan Google Calendar.</p>
        </div>

        <?php if (!empty($event['google_event_id'])): ?>
            <div class="google-status">
                ✅ Event ini sudah terhubung dengan Google Calendar.
                <br><small>Google Event ID: <?php echo $event['google_event_id']; ?></small>
            </div>
        <?php else: ?>
            <div class="google-status">
                ⚠️ Event ini belum terhubung dengan Google Calendar. 
                <br><small>Event akan dibuat di Google Calendar saat diupdate.</small>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST">
                <div class="form-group">
                    <label for="event_name">Nama Event</label>
                    <input type="text" id="event_name" name="event_name" value="<?php echo htmlspecialchars($event['event_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="event_type">Jenis Event</label>
                    <select id="event_type" name="event_type" required>
                        <option value="Music Event" <?php echo $event['event_type'] == 'Music Event' ? 'selected' : ''; ?>>Music Event</option>
                        <option value="Seminar" <?php echo $event['event_type'] == 'Seminar' ? 'selected' : ''; ?>>Seminar</option>
                        <option value="Workshop" <?php echo $event['event_type'] == 'Workshop' ? 'selected' : ''; ?>>Workshop</option>
                        <option value="Competition" <?php echo $event['event_type'] == 'Competition' ? 'selected' : ''; ?>>Competition</option>
                        <option value="Festival" <?php echo $event['event_type'] == 'Festival' ? 'selected' : ''; ?>>Festival</option>
                        <option value="Sports" <?php echo $event['event_type'] == 'Sports' ? 'selected' : ''; ?>>Sports</option>
                        <option value="Charity" <?php echo $event['event_type'] == 'Charity' ? 'selected' : ''; ?>>Charity</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Deskripsi</label>
                    <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="event_date">Tanggal Event</label>
                    <input type="date" id="event_date" name="event_date" value="<?php echo $event['event_date']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="event_time">Waktu Event</label>
                    <input type="time" id="event_time" name="event_time" value="<?php echo $event['event_time']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="location">Lokasi</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($event['location']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="price">Harga Tiket (Rp)</label>
                    <input type="number" id="price" name="price" min="0" value="<?php echo $event['price']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="available_tickets">Jumlah Tiket Tersedia</label>
                    <input type="number" id="available_tickets" name="available_tickets" min="0" value="<?php echo $event['available_tickets']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="organizer">Penyelenggara</label>
                    <input type="text" id="organizer" name="organizer" value="<?php echo htmlspecialchars($event['organizer']); ?>" required>
                </div>

                <button type="submit" class="btn-submit">🔄 Update Event & Sinkronisasi Google Calendar</button>
                
                <div class="button-group">
                    <a href="events.php" class="btn btn-secondary" style="flex: 1;">← Kembali ke Daftar Event</a>
                    <a href="dashboard.php" class="btn btn-secondary" style="flex: 1;">📊 Ke Dashboard</a>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2023 Sistem Manajemen Event Mahasiswa. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Set tanggal minimal hari ini
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('event_date').min = today;
    </script>
</body>
</html>