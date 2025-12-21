<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';
require_login();

// ============ HANDLE DOWNLOAD CSV EXCEL-FRIENDLY ============
if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    $user_id = $_SESSION['user_id'];
    
    // Query data tiket
    $query = "
        SELECT 
            t.id as ticket_id,
            t.ticket_code,
            t.quantity,
            t.purchase_date,
            t.status,
            e.event_name, 
            e.event_date, 
            e.event_time,
            e.location, 
            e.event_type,
            e.price,
            e.band_name,
            u.full_name,
            u.email
        FROM tickets t 
        JOIN events e ON t.event_id = e.id 
        JOIN users u ON t.user_id = u.id
        WHERE t.user_id = ? 
        ORDER BY e.event_date DESC, t.purchase_date DESC
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Bersihkan output buffer
    ob_clean();
    
    $filename = "Laporan_Tiket_" . $_SESSION['full_name'] . "_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Buka output stream
    $output = fopen('php://output', 'w');
    
    // Tambahkan BOM untuk UTF-8 (agar Excel membaca dengan benar)
    fwrite($output, "\xEF\xBB\xBF");
    
    // ===== HEADER UTAMA =====
    fputcsv($output, ['LIVE FEST - LAPORAN TIKET PENGGUNA'], ',');
    fputcsv($output, [], ',');
    fputcsv($output, ['INFORMASI PENGGUNA'], ',');
    fputcsv($output, ['Nama Lengkap', $_SESSION['full_name']], ',');
    fputcsv($output, ['Email', $_SESSION['email'] ?? '-'], ',');
    fputcsv($output, ['Tanggal Export', date('d/m/Y H:i:s')], ',');
    fputcsv($output, [], ',');
    fputcsv($output, [], ',');
    
    // ===== HEADER TABEL DATA =====
    fputcsv($output, [
        'NO',
        'KODE TIKET',
        'NAMA EVENT',
        'JENIS EVENT',
        'TANGGAL EVENT',
        'JAM EVENT',
        'LOKASI',
        'JUMLAH TIKET',
        'HARGA SATUAN',
        'TOTAL HARGA',
        'ARTIS/GUEST STAR',
        'STATUS TIKET',
        'TANGGAL PEMBELIAN',
        'JAM PEMBELIAN',
        
    ], ',');
    
    $counter = 1;
    $total_tickets = 0;
    $total_amount = 0;
    $total_orders = 0;
    
    // ===== DATA TIKET =====
    while ($row = mysqli_fetch_assoc($result)) {
        $price_per_ticket = $row['price'];
        $total_price = $row['quantity'] * $row['price'];
        
        // Format tanggal dan waktu
        $event_date = date('d/m/Y', strtotime($row['event_date']));
        $event_time = !empty($row['event_time']) ? date('H:i', strtotime($row['event_time'])) : '19:00';
        $purchase_date = date('d/m/Y', strtotime($row['purchase_date']));
        $purchase_time = date('H:i', strtotime($row['purchase_date']));
        
        // Format status
        $status_map = [
            'active' => 'Aktif',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'pending' => 'Pending'
        ];
        $status = $status_map[$row['status']] ?? 'Tidak Diketahui';
        
        // Format guest star
        $guest_star = !empty($row['band_name']) ? $row['band_name'] : '-';
        
        // Format harga (untuk Excel)
        $price_per_ticket_formatted = $price_per_ticket; // Biarkan angka saja, Excel akan format
        $total_price_formatted = $total_price;
        
        fputcsv($output, [
            $counter,
            $row['ticket_code'] ?? 'TKT' . str_pad($row['ticket_id'], 6, '0', STR_PAD_LEFT),
            trim($row['event_name']),
            trim($row['event_type']),
            $event_date,
            $event_time,
            trim($row['location']),
            $row['quantity'],
            $price_per_ticket_formatted,
            $total_price_formatted,
            trim($guest_star),
            $status,
            $purchase_date,
            $purchase_time,
            'Transfer Bank'
        ], ',');
        
        $total_tickets += $row['quantity'];
        $total_amount += $total_price;
        $total_orders++;
        $counter++;
    }
    
    // ===== BARIS KOSONG =====
    fputcsv($output, [], ',');
    fputcsv($output, [], ',');
    
    // ===== RINGKASAN STATISTIK =====
    fputcsv($output, ['STATISTIK DAN RINGKASAN'], ',');
    fputcsv($output, [], ',');
    fputcsv($output, ['Total Jumlah Tiket:', $total_tickets], ',');
    fputcsv($output, ['Total Jumlah Order:', $total_orders], ',');
    fputcsv($output, ['Total Nilai Transaksi:', 'Rp ' . number_format($total_amount, 0, ',', '.')], ',');
    
    // Hitung rata-rata
    $avg_ticket_per_order = $total_orders > 0 ? $total_tickets / $total_orders : 0;
    $avg_value_per_order = $total_orders > 0 ? $total_amount / $total_orders : 0;
    
    fputcsv($output, ['Rata-rata Tiket per Order:', number_format($avg_ticket_per_order, 2)], ',');
    fputcsv($output, ['Rata-rata Nilai per Order:', 'Rp ' . number_format($avg_value_per_order, 0, ',', '.')], ',');
    fputcsv($output, [], ',');
    
    // ===== CATATAN DAN INFORMASI =====
    fputcsv($output, ['INFORMASI DAN CATATAN'], ',');
    fputcsv($output, [], ',');
    fputcsv($output, ['📋 INSTRUKSI TIKET:'], ',');
    fputcsv($output, ['1. Tunjukkan tiket (digital/print) saat check-in event'], ',');
    fputcsv($output, ['2. Kode tiket akan divalidasi dengan scanning'], ',');
    fputcsv($output, ['3. Tiket bersifat personal dan tidak dapat dipindahtangankan'], ',');
    fputcsv($output, ['4. Harap datang 30 menit sebelum acara dimulai'], ',');
    fputcsv($output, [], ',');
    fputcsv($output, ['⚠️ KETENTUAN:'], ',');
    fputcsv($output, ['• Tiket yang sudah dibeli tidak dapat dikembalikan'], ',');
    fputcsv($output, ['• Perubahan jadwal event akan diinformasikan via email'], ',');
    fputcsv($output, ['• Pastikan email dan kontak Anda aktif'], ',');
    fputcsv($output, [], ',');
    
    // ===== FOOTER =====
    fputcsv($output, ['Generated by: LIVE FEST Event Management System'], ',');
    fputcsv($output, ['Export Date: ' . date('d F Y, H:i:s')], ',');
    fputcsv($output, ['Contact: support@livefest.id'], ',');
    fputcsv($output, [], ',');
    fputcsv($output, ['© 2025 LIVE FEST. All Rights Reserved.'], ',');
    
    fclose($output);
    exit;
}

// ============ GOOGLE CALENDAR API INTEGRATION ============
$vendor_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
    
    $googleConnected = false;
    $calendarService = null;
    $google_client = null;
    $user_id = $_SESSION['user_id'];

    if (function_exists('checkGoogleCalendarConnection')) {
        $googleStatus = checkGoogleCalendarConnection();
        $googleConnected = isset($googleStatus['connected']) ? $googleStatus['connected'] : false;
    }

    if ($googleConnected) {
        try {
            if (function_exists('getUserGoogleToken')) {
                $token_data = getUserGoogleToken($user_id);
                if ($token_data && !empty($token_data['google_access_token'])) {
                    $client = new Google\Client();
                    $credentials_file = __DIR__ . '/credentials.json';
                    if (file_exists($credentials_file)) {
                        $client->setAuthConfig($credentials_file);
                        $client->addScope(Google\Service\Calendar::CALENDAR);
                        $client->setAccessType('offline');
                        $client->setPrompt('consent');
                        
                        $accessToken = json_decode($token_data['google_access_token'], true);
                        $client->setAccessToken($accessToken);
                        
                        if ($client->isAccessTokenExpired()) {
                            if ($client->getRefreshToken()) {
                                $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                                
                                if (function_exists('saveUserGoogleToken')) {
                                    saveUserGoogleToken($user_id, $newToken);
                                }
                                
                                $client->setAccessToken($newToken);
                            } else {
                                if (function_exists('disconnectUserGoogleCalendar')) {
                                    disconnectUserGoogleCalendar($user_id);
                                }
                                $_SESSION['error'] = "Sesi Google telah berakhir, silakan konek ulang.";
                                $googleConnected = false;
                            }
                        } else {
                            $googleConnected = true;
                            $calendarService = new Google\Service\Calendar($client);
                            $google_client = $client;
                        }
                    } else {
                        $googleConnected = false;
                    }
                } else {
                    $googleConnected = false;
                }
            } else {
                $googleConnected = false;
            }
        } catch (Exception $e) {
            error_log("Google Client Error: " . $e->getMessage());
            $googleConnected = false;
            $_SESSION['error'] = "Error koneksi Google: " . $e->getMessage();
        }
    }
} else {
    $googleConnected = false;
}

// Handle sync to Google Calendar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sync_to_calendar'])) {
    $event_id = $_POST['event_id'];
    $user_id = $_SESSION['user_id'];
    
    if (!$googleConnected || !$calendarService) {
        $_SESSION['error'] = "Silakan koneksikan dengan Google Calendar terlebih dahulu!";
        header('Location: my_tickets.php');
        exit;
    }
    
    $query = "
        SELECT 
            e.*, 
            t.quantity,
            t.purchase_date,
            t.id as ticket_id,
            u.full_name,
            u.email
        FROM events e
        JOIN tickets t ON e.id = t.event_id
        JOIN users u ON t.user_id = u.id
        WHERE e.id = ? AND t.user_id = ?
        LIMIT 1
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $event_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $event = mysqli_fetch_assoc($result);
    
    if ($event) {
        try {
            $event_date = $event['event_date'];
            $event_time = !empty($event['event_time']) ? $event['event_time'] : '19:00:00';
            
            if (strlen($event_time) == 5) {
                $event_time .= ':00';
            }
            
            $startDateTime = $event_date . 'T' . $event_time;
            $endDateTime = date('Y-m-d\TH:i:s', strtotime($startDateTime . ' +3 hours'));
            
            $googleEvent = new Google\Service\Calendar\Event([
                'summary' => $event['event_name'] . ' - LIVE FEST (Ticket #' . $event['ticket_id'] . ')',
                'description' => '🎵 ' . ($event['band_name'] ?: $event['event_name']) . 
                               '\n📍 ' . $event['location'] . 
                               '\n🎫 ' . $event['quantity'] . ' tiket' .
                               '\n👤 ' . $event['full_name'] .
                               '\n📧 ' . $event['email'] .
                               '\n💵 Rp ' . number_format($event['price'] * $event['quantity'], 0, ',', '.') .
                               '\n📅 Beli: ' . date('d F Y H:i', strtotime($event['purchase_date'])) .
                               '\n\nInfo: ' . ($event['description'] ?? ''),
                'location' => $event['location'],
                'start' => [
                    'dateTime' => $startDateTime,
                    'timeZone' => 'Asia/Jakarta',
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => 'Asia/Jakarta',
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'popup', 'minutes' => 1440],
                        ['method' => 'popup', 'minutes' => 60],
                        ['method' => 'popup', 'minutes' => 30],
                    ]
                ],
                'colorId' => '5',
                'guestsCanSeeOtherGuests' => false,
                'guestsCanInviteOthers' => false,
            ]);
            
            $createdEvent = $calendarService->events->insert('primary', $googleEvent);
            
            $update_query = "UPDATE tickets SET google_event_id = ? WHERE id = ? AND user_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "sii", $createdEvent->getId(), $event['ticket_id'], $user_id);
            mysqli_stmt_execute($update_stmt);
            
            $_SESSION['success'] = "✅ Tiket berhasil disinkronkan ke Google Calendar! 
                                   <br><a href='https://calendar.google.com/calendar/event?eid=" . urlencode($createdEvent->getId()) . "' target='_blank' style='color: white; text-decoration: underline;'>
                                   📅 Lihat di Google Calendar
                                   </a>";
            
        } catch (Exception $e) {
            error_log("Calendar Sync Error: " . $e->getMessage());
            $_SESSION['error'] = "❌ Gagal sinkronisasi: " . $e->getMessage();
            
            if (strpos($e->getMessage(), 'invalid_grant') !== false || 
                strpos($e->getMessage(), 'token expired') !== false) {
                if (function_exists('disconnectUserGoogleCalendar')) {
                    disconnectUserGoogleCalendar($user_id);
                }
                $_SESSION['error'] .= "<br>⚠️ Token expired, silakan connect ulang Google Calendar.";
            }
        }
    } else {
        $_SESSION['error'] = "❌ Event tidak ditemukan!";
    }
    
    header('Location: my_tickets.php');
    exit;
}

// Handle disconnect Google Calendar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['disconnect_google'])) {
    $user_id = $_SESSION['user_id'];
    
    $clear_tickets_query = "UPDATE tickets SET google_event_id = NULL WHERE user_id = ?";
    $clear_tickets_stmt = mysqli_prepare($conn, $clear_tickets_query);
    mysqli_stmt_bind_param($clear_tickets_stmt, "i", $user_id);
    mysqli_stmt_execute($clear_tickets_stmt);
    
    if (function_exists('disconnectUserGoogleCalendar')) {
        if (disconnectUserGoogleCalendar($user_id)) {
            $_SESSION['success'] = "✅ Berhasil disconnect dari Google Calendar!";
            $googleConnected = false;
            $calendarService = null;
        } else {
            $_SESSION['error'] = "❌ Gagal disconnect Google Calendar!";
        }
    } else {
        $_SESSION['success'] = "✅ Token Google telah dihapus!";
        $googleConnected = false;
    }
    
    header('Location: my_tickets.php');
    exit;
}

// Handle remove sync (hapus dari Google Calendar)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_sync'])) {
    $ticket_id = $_POST['ticket_id'];
    $google_event_id = $_POST['google_event_id'];
    $user_id = $_SESSION['user_id'];
    
    if ($googleConnected && $calendarService && !empty($google_event_id)) {
        try {
            $calendarService->events->delete('primary', $google_event_id);
            
            $update_query = "UPDATE tickets SET google_event_id = NULL WHERE id = ? AND user_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ii", $ticket_id, $user_id);
            mysqli_stmt_execute($update_stmt);
            
            $_SESSION['success'] = "✅ Sinkronisasi berhasil dihapus dari Google Calendar!";
        } catch (Exception $e) {
            $update_query = "UPDATE tickets SET google_event_id = NULL WHERE id = ? AND user_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ii", $ticket_id, $user_id);
            mysqli_stmt_execute($update_stmt);
            
            $_SESSION['success'] = "✅ Sinkronisasi berhasil dihapus!";
        }
    } else {
        $_SESSION['error'] = "❌ Tidak dapat menghapus sinkronisasi!";
    }
    
    header('Location: my_tickets.php');
    exit;
}

// Handle download ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['download_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    $user_id = $_SESSION['user_id'];
    
    $query = "
        SELECT 
            t.*,
            e.event_name, 
            e.event_date, 
            e.event_time,
            e.location, 
            e.event_type,
            e.price,
            e.band_photo,
            e.band_name,
            e.description,
            u.full_name,
            u.email
        FROM tickets t 
        JOIN events e ON t.event_id = e.id 
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND t.user_id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $ticket_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ticket = mysqli_fetch_assoc($result);
    
    if ($ticket) {
        $event_date = date('d F Y', strtotime($ticket['event_date']));
        $event_time = !empty($ticket['event_time']) ? date('H:i', strtotime($ticket['event_time'])) : '19:00';
        $purchase_date = date('d F Y H:i', strtotime($ticket['purchase_date']));
        $total_price = $ticket['quantity'] * $ticket['price'];
        $ticket_code = 'TKT' . str_pad($ticket['id'], 6, '0', STR_PAD_LEFT) . strtoupper(substr(md5($ticket['id']), 0, 6));
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Tiket - ' . htmlspecialchars($ticket['event_name']) . '</title>
            <style>
                body { 
                    font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; 
                    margin: 0;
                    padding: 30px;
                    background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }
                .ticket-container { 
                    width: 100%;
                    max-width: 600px; 
                    background: white;
                    border-radius: 20px;
                    overflow: hidden;
                    box-shadow: 0 25px 50px rgba(0,0,0,0.15);
                }
                .ticket-header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                .ticket-header::before {
                    content: \'\';
                    position: absolute;
                    top: -50%;
                    right: -50%;
                    width: 200px;
                    height: 200px;
                    background: rgba(255,255,255,0.1);
                    border-radius: 50%;
                }
                .fest-name { 
                    font-size: 16px; 
                    opacity: 0.9;
                    margin-bottom: 5px;
                    font-weight: 600;
                }
                .ticket-title { 
                    font-size: 28px; 
                    font-weight: 800; 
                    margin: 15px 0;
                    position: relative;
                    z-index: 1;
                }
                .ticket-type { 
                    background: rgba(255,255,255,0.2); 
                    padding: 10px 25px; 
                    border-radius: 25px;
                    display: inline-block;
                    margin: 10px 0;
                    font-weight: 600;
                    backdrop-filter: blur(5px);
                    position: relative;
                    z-index: 1;
                }
                .ticket-body { 
                    padding: 30px;
                }
                .info-item { 
                    background: linear-gradient(135deg, #f8f9fa 0%, #f0f2ff 100%);
                    padding: 20px;
                    border-radius: 12px;
                    border: 1px solid rgba(102, 126, 234, 0.1);
                    margin-bottom: 15px;
                    transition: all 0.3s ease;
                }
                .info-item:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
                    border-color: rgba(102, 126, 234, 0.3);
                }
                .info-label { 
                    font-weight: 700; 
                    color: #555; 
                    font-size: 14px; 
                    margin-bottom: 8px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .info-value { 
                    font-weight: 600; 
                    color: #333;
                    font-size: 16px;
                }
                .highlight { 
                    color: #667eea; 
                    font-weight: bold;
                }
                .ticket-code { 
                    text-align: center; 
                    margin: 30px 0;
                    padding: 25px;
                    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                    color: white;
                    border-radius: 15px;
                    box-shadow: 0 10px 25px rgba(79, 172, 254, 0.3);
                }
                .code-label { 
                    font-size: 14px; 
                    margin-bottom: 10px;
                    opacity: 0.9;
                }
                .code-value { 
                    font-size: 32px; 
                    font-weight: 800; 
                    letter-spacing: 3px;
                    font-family: monospace;
                    margin: 15px 0;
                    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
                }
                .barcode { 
                    font-family: "Libre Barcode 128", cursive;
                    font-size: 60px;
                    text-align: center;
                    margin: 25px 0;
                    color: #333;
                }
                .footer-note { 
                    text-align: center; 
                    font-size: 12px; 
                    color: #888;
                    margin-top: 25px;
                    padding-top: 20px;
                    border-top: 1px dashed #ddd;
                    line-height: 1.6;
                }
                .print-button {
                    text-align: center;
                    margin-top: 25px;
                }
                .btn-print {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    padding: 15px 40px;
                    border-radius: 25px;
                    font-weight: 700;
                    cursor: pointer;
                    font-size: 16px;
                    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
                }
                .btn-print:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
                }
                @media print {
                    body { 
                        background: white; 
                        margin: 0;
                        padding: 0;
                    }
                    .ticket-container { 
                        box-shadow: none; 
                        max-width: 100%;
                        border-radius: 0;
                    }
                    .print-button { display: none; }
                    .btn-print { display: none; }
                }
            </style>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body>
            <div class="ticket-container">
                <div class="ticket-header">
                    <div class="fest-name">LIVE FEST - E-TICKET</div>
                    <div class="ticket-title">' . htmlspecialchars($ticket['event_name']) . '</div>
                    <div class="ticket-type">' . htmlspecialchars($ticket['event_type']) . '</div>
                </div>
                
                <div class="ticket-body">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-user"></i> NAMA PEMEGANG TIKET</div>
                        <div class="info-value highlight">' . htmlspecialchars($ticket['full_name']) . '</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-envelope"></i> EMAIL</div>
                        <div class="info-value">' . htmlspecialchars($ticket['email']) . '</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-calendar-day"></i> TANGGAL EVENT</div>
                        <div class="info-value">' . $event_date . '</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-clock"></i> WAKTU EVENT</div>
                        <div class="info-value">' . $event_time . ' WIB</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-map-marker-alt"></i> LOKASI</div>
                        <div class="info-value">' . htmlspecialchars($ticket['location']) . '</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-ticket-alt"></i> JUMLAH TIKET</div>
                        <div class="info-value highlight">' . $ticket['quantity'] . ' tiket</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-money-bill-wave"></i> TOTAL HARGA</div>
                        <div class="info-value highlight">Rp ' . number_format($total_price, 0, ',', '.') . '</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-shopping-cart"></i> TANGGAL PEMBELIAN</div>
                        <div class="info-value">' . $purchase_date . '</div>
                    </div>
                    
                    ' . ($ticket['band_name'] ? '
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-star"></i> GUEST STAR</div>
                        <div class="info-value highlight">' . htmlspecialchars($ticket['band_name']) . '</div>
                    </div>' : '') . '
                    
                    <div class="ticket-code">
                        <div class="code-label">KODE TIKET E-TICKET</div>
                        <div class="code-value">' . $ticket_code . '</div>
                        <div class="barcode">*' . $ticket_code . '*</div>
                    </div>
                    
                    <div class="footer-note">
                        <p><strong>INSTRUKSI PENGGUNAAN TIKET:</strong></p>
                        <p>1. Tunjukkan tiket ini (digital atau print) saat check-in</p>
                        <p>2. Kode Barcode akan discan untuk validasi</p>
                        <p>3. Tiket ini bersifat personal dan tidak dapat dipindahtangankan</p>
                        <p>4. Harap datang 30 menit sebelum acara dimulai</p>
                    </div>
                    
                    <div class="print-button">
                        <button class="btn-print" onclick="window.print()">
                            <i class="fas fa-print"></i> PRINT TIKET
                        </button>
                    </div>
                </div>
            </div>
            
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const ticketContainer = document.querySelector(".ticket-container");
                    const watermark = document.createElement("div");
                    watermark.style.cssText = `
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%) rotate(-45deg);
                        font-size: 80px;
                        color: rgba(102, 126, 234, 0.1);
                        font-weight: bold;
                        pointer-events: none;
                        z-index: 1;
                        white-space: nowrap;
                    `;
                    watermark.textContent = "LIVE FEST 2025";
                    ticketContainer.style.position = "relative";
                    ticketContainer.appendChild(watermark);
                });
            </script>
        </body>
        </html>';
        
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="Tiket_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ticket['event_name']) . '_' . $ticket['id'] . '.html"');
        echo $html;
        exit;
    } else {
        $_SESSION['error'] = "❌ Tiket tidak ditemukan!";
        header('Location: my_tickets.php');
        exit;
    }
}

$user_id = $_SESSION['user_id'];

// Query utama TANPA payment_method
$tickets_query = "
    SELECT 
        t.id as ticket_id,
        t.quantity,
        t.purchase_time as purchase_date,
        t.status,
        t.google_event_id as ticket_google_id,
        e.event_name, 
        e.event_date, 
        e.event_time,
        e.location, 
        e.event_type,
        e.price,
        e.band_photo,
        e.band_name,
        e.description,
        e.google_event_id as event_google_id,
        e.id as event_id
    FROM tickets t 
    JOIN events e ON t.event_id = e.id 
    WHERE t.user_id = ? 
    ORDER BY e.event_date DESC, t.purchase_date DESC
";

$stmt = mysqli_prepare($conn, $tickets_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$tickets_result = mysqli_stmt_get_result($stmt);

// Sederhanakan: hanya ambil data tiket tanpa menghitung stats
$all_tickets = [];
while($ticket = mysqli_fetch_assoc($tickets_result)) {
    $all_tickets[] = $ticket;
}
mysqli_data_seek($tickets_result, 0);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tiket Saya - LIVE FEST</title>
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

        /* ====== PAGE HEADER ====== */
        .page-header {
            background: white;
            border-radius: 10px;
            padding: 20px 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        @media (min-width: 768px) {
            .page-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 25px;
                gap: 20px;
            }
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .header-left h1 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 8px;
            font-weight: 700;
        }

        @media (min-width: 768px) {
            .header-left h1 {
                font-size: 2.2rem;
            }
        }

        @media (min-width: 992px) {
            .header-left h1 {
                font-size: 2.5rem;
            }
        }

        .header-left p {
            color: #666;
            font-size: 0.95rem;
            max-width: 600px;
            margin: 0;
        }

        .header-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        @media (min-width: 768px) {
            .header-actions {
                flex-direction: row;
                gap: 15px;
            }
        }

        .btn-action-header {
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-download-csv {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(67, 233, 123, 0.3);
        }

        .btn-download-csv:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(67, 233, 123, 0.4);
        }

        .btn-back {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-back:hover {
            background: #f8f9ff;
            transform: translateY(-3px);
        }

        /* ====== ALERT MESSAGES ====== */
        .alert-custom {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .alert-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }

        .alert-error {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }

        /* ====== GOOGLE CALENDAR SECTION ====== */
        .google-section {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            border-left: 4px solid #4285F4;
        }

        @media (min-width: 768px) {
            .google-section {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
                gap: 20px;
            }
        }

        .google-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .google-icon {
            font-size: 2.5rem;
            color: #4285F4;
            background: rgba(66, 133, 244, 0.1);
            padding: 12px;
            border-radius: 10px;
        }

        @media (min-width: 768px) {
            .google-icon {
                font-size: 3rem;
                padding: 15px;
            }
        }

        .google-text h3 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 1.1rem;
            font-weight: 700;
        }

        @media (min-width: 768px) {
            .google-text h3 {
                font-size: 1.2rem;
                margin-bottom: 8px;
            }
        }

        .google-status {
            font-weight: 700;
            margin-bottom: 5px;
        }

        .google-status.connected {
            color: #34A853;
        }

        .google-status.disconnected {
            color: #EA4335;
        }

        .google-text p {
            color: #666;
            font-size: 0.85rem;
            margin: 0;
            line-height: 1.5;
        }

        .google-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        @media (min-width: 768px) {
            .google-actions {
                flex-direction: row;
                gap: 12px;
            }
        }

        .btn-google {
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }

        @media (min-width: 768px) {
            .btn-google {
                padding: 12px 20px;
                min-width: 140px;
            }
        }

        .btn-connect {
            background: #4285F4;
            color: white;
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.3);
        }

        .btn-connect:hover {
            background: #3367d6;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(51, 103, 214, 0.4);
        }

        .btn-disconnect {
            background: #EA4335;
            color: white;
            box-shadow: 0 4px 12px rgba(234, 67, 53, 0.3);
        }

        .btn-disconnect:hover {
            background: #d23a2e;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(210, 58, 46, 0.4);
        }

        .btn-view-calendar {
            background: #34A853;
            color: white;
            box-shadow: 0 4px 12px rgba(52, 168, 83, 0.3);
        }

        .btn-view-calendar:hover {
            background: #2e8b57;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(46, 139, 87, 0.4);
        }

        /* ====== TICKETS CONTAINER ====== */
        .tickets-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* ====== TICKET ITEM ====== */
        .ticket-item {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .ticket-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .ticket-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .ticket-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        @media (min-width: 768px) {
            .ticket-header::before {
                width: 200px;
                height: 200px;
            }
        }

        .ticket-title {
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
            word-break: break-word;
        }

        @media (min-width: 768px) {
            .ticket-title {
                font-size: 1.6rem;
            }
        }

        .ticket-type {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
            font-weight: 600;
            backdrop-filter: blur(5px);
            position: relative;
            z-index: 1;
        }

        .google-sync-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.95);
            color: #34A853;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(52, 168, 83, 0.2);
            z-index: 2;
        }

        /* ====== TICKET BODY ====== */
        .ticket-body {
            padding: 20px;
        }

        @media (min-width: 768px) {
            .ticket-body {
                padding: 25px;
            }
        }

        .ticket-info {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 15px;
        }

        @media (min-width: 768px) {
            .ticket-info {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }

        .info-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #f0f2ff 100%);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid rgba(102, 126, 234, 0.1);
            transition: all 0.3s;
        }

        @media (min-width: 768px) {
            .info-item {
                padding: 20px;
            }
        }

        .info-label {
            font-weight: 700;
            color: #555;
            font-size: 0.85rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value {
            font-weight: 600;
            color: #333;
            font-size: 1rem;
        }

        @media (min-width: 768px) {
            .info-value {
                font-size: 1.1rem;
            }
        }

        /* ====== TICKET ACTIONS ====== */
        .ticket-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        @media (min-width: 768px) {
            .ticket-actions {
                flex-direction: row;
                gap: 12px;
            }
        }

        .btn-action {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-download {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 172, 254, 0.3);
        }

        .btn-download:hover {
            background: linear-gradient(135deg, #3d9bf7 0%, #00d9e6 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(61, 155, 247, 0.4);
        }

        .btn-sync {
            background: #4285F4;
            color: white;
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.3);
        }

        .btn-sync:hover {
            background: #3367d6;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(51, 103, 214, 0.4);
        }

        .btn-synced {
            background: #34A853;
            color: white;
            box-shadow: 0 4px 12px rgba(52, 168, 83, 0.3);
        }

        .btn-synced:hover {
            background: #2e8b57;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(46, 139, 87, 0.4);
        }

        .btn-remove-sync {
            background: #EA4335;
            color: white;
            box-shadow: 0 4px 12px rgba(234, 67, 53, 0.3);
        }

        .btn-remove-sync:hover {
            background: #d23a2e;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(210, 58, 46, 0.4);
        }

        .btn-connect {
            background: linear-gradient(135deg, #EA4335 0%, #FBBC05 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(234, 67, 53, 0.3);
        }

        .btn-connect:hover {
            background: linear-gradient(135deg, #d23a2e 0%, #e6ac00 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(210, 58, 46, 0.4);
        }

        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4b9c 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        /* ====== NO TICKETS ====== */
        .no-tickets {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin: 20px 0;
            position: relative;
            overflow: hidden;
        }

        .no-tickets::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .no-tickets i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
            display: block;
        }

        .no-tickets h3 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .no-tickets p {
            color: #666;
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

        /* ====== ANIMATIONS ====== */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .refresh-btn {
            transition: transform 0.3s;
        }

        .refresh-btn:hover {
            transform: rotate(180deg);
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .ticket-item:active {
                transform: scale(0.98);
            }
            
            .refresh-btn:active {
                transform: rotate(180deg);
            }
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
        <div class="page-header">
            <div class="header-left">
                <h1><i class="fas fa-ticket-alt"></i> Tiket Saya</h1>
                <p>Lihat semua tiket event yang telah Anda beli dan sinkronkan dengan Google Calendar</p>
            </div>
            <div class="header-actions">
                <?php if (count($all_tickets) > 0): ?>
                    <a href="my_tickets.php?download=csv" class="btn-action-header btn-download-csv" id="downloadCSV">
                        <i class="fas fa-file-csv"></i> Download CSV
                    </a>
                <?php endif; ?>
                <a href="dashboard.php" class="btn-action-header btn-back">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert-custom alert-success">
                <i class="fas fa-check-circle"></i>
                <div style="flex: 1;">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-custom alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div style="flex: 1;">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Google Calendar Section -->
        <div class="google-section">
            <div class="google-info">
                <i class="fab fa-google google-icon"></i>
                <div class="google-text">
                    <h3>Google Calendar Integration</h3>
                    <p class="google-status <?php echo $googleConnected ? 'connected' : 'disconnected'; ?>">
                        <i class="fas fa-<?php echo $googleConnected ? 'check-circle' : 'times-circle'; ?>"></i>
                        Status: <?php echo $googleConnected ? 'Connected' : 'Not Connected'; ?>
                    </p>
                    <p>
                        <?php echo $googleConnected ? 
                            '✅ Tiket Anda dapat disinkronkan ke Google Calendar. Event akan muncul di calendar Anda dengan reminder otomatis.' : 
                            '⚠️ Connect Google Calendar untuk menyimpan tiket secara otomatis dan mendapatkan reminder sebelum event.'; ?>
                    </p>
                </div>
            </div>
        <!-- Simple Refund Info -->
        <div style="
            background: #fff8e1;
            border: 2px dashed #ffb300;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        ">
            <h5 style="color: #333; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                <i class="fas fa-headset" style="color: #ff9800;"></i>
                Butuh Bantuan atau Refund?
            </h5>
            <p style="color: #666; margin-bottom: 10px;">
                Hubungi Admin:
            </p>
            <p style="color: #2c3e50; margin-bottom: 15px; font-weight: 600;">
                sofyantsaori1464@gmail.com<br>
                muhammadiqbaldzaky272@gmail.com<br>
                greypah92@gmail.com
            </p>
            <p style="color: #e65100; font-size: 0.9rem; font-style: italic;">
                ⚠️ Refund hanya berlaku 24 jam setelah pembelian
            </p>
        </div>
            <div class="google-actions">
                <?php if ($googleConnected): ?>
                    <a href="https://calendar.google.com" target="_blank" class="btn-google btn-view-calendar">
                        <i class="fas fa-external-link-alt"></i> Buka Calendar
                    </a>
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="disconnect_google" class="btn-google btn-disconnect" 
                                onclick="return confirm('Apakah Anda yakin ingin disconnect dari Google Calendar?')">
                            <i class="fas fa-unlink"></i> Disconnect
                        </button>
                    </form>
                <?php else: ?>
                    <a href="auth_google.php?user_id=<?php echo $user_id; ?>" class="btn-google btn-connect">
                        <i class="fab fa-google"></i> Connect Google Calendar
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (count($all_tickets) > 0): ?>
            <div class="tickets-container">
                <?php foreach($all_tickets as $ticket): 
                    $has_google_sync = !empty($ticket['ticket_google_id']);
                    $display_time = !empty($ticket['event_time']) ? date('H:i', strtotime($ticket['event_time'])) : '19:00';
                    $payment_method_display = 'Transfer Bank';
                ?>
                    <div class="ticket-item">
                        <div class="ticket-header">
                            <?php if ($has_google_sync): ?>
                                <div class="google-sync-badge">
                                    <i class="fab fa-google"></i> Tersinkron
                                </div>
                            <?php endif; ?>
                            <div class="ticket-title"><?php echo htmlspecialchars($ticket['event_name']); ?></div>
                            <div class="ticket-type">
                                <i class="fas fa-<?php echo $ticket['event_type'] == 'Music Event' ? 'music' : 
                                                    ($ticket['event_type'] == 'Seminar' ? 'graduation-cap' : 
                                                    ($ticket['event_type'] == 'Workshop' ? 'tools' : 
                                                    ($ticket['event_type'] == 'Competition' ? 'trophy' : 'calendar'))); ?>"></i>
                                <?php echo htmlspecialchars($ticket['event_type']); ?>
                            </div>
                        </div>
                        
                        <div class="ticket-body">
                            <div class="ticket-info">
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-calendar"></i> Tanggal & Waktu</div>
                                    <div class="info-value">
                                        <?php echo date('d F Y', strtotime($ticket['event_date'])); ?><br>
                                        <?php echo $display_time; ?> WIB
                                        <div class="countdown" style="font-size: 0.85rem; color: #667eea; font-weight: 600; margin-top: 5px;"></div>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-map-marker-alt"></i> Lokasi</div>
                                    <div class="info-value">
                                        <?php echo htmlspecialchars($ticket['location']); ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-ticket-alt"></i> Jumlah Tiket</div>
                                    <div class="info-value">
                                        <?php echo $ticket['quantity']; ?> tiket
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-money-bill-wave"></i> Total Pembayaran</div>
                                    <div class="info-value" style="color: #27ae60; font-weight: 700;">
                                        Rp <?php echo number_format($ticket['quantity'] * $ticket['price'], 0, ',', '.'); ?>
                                    </div>
                                </div>
                                
                                
                                </div>
                                
                                <?php if ($ticket['band_name']): ?>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-star"></i> Guest Star</div>
                                    <div class="info-value">
                                        <?php echo htmlspecialchars($ticket['band_name']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-clock"></i> Waktu Pembelian</div>
                                    <div class="info-value">
                                        <?php echo date('d F Y H:i', strtotime($ticket['purchase_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="ticket-actions">
                                <form method="POST" style="flex: 1; margin: 0;">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                    <button type="submit" name="download_ticket" class="btn-action btn-download">
                                        <i class="fas fa-download"></i> Download Tiket
                                    </button>
                                </form>
                                
                                <?php if ($has_google_sync): ?>
                                    <form method="POST" style="flex: 1; margin: 0;">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                        <input type="hidden" name="google_event_id" value="<?php echo $ticket['ticket_google_id']; ?>">
                                        <button type="submit" name="remove_sync" class="btn-action btn-remove-sync" 
                                                onclick="return confirm('Hapus sinkronisasi dari Google Calendar?')" 
                                                title="Hapus dari Google Calendar">
                                            <i class="fas fa-unlink"></i> Hapus Sync
                                        </button>
                                    </form>
                                <?php elseif ($googleConnected): ?>
                                    <form method="POST" style="flex: 1; margin: 0;">
                                        <input type="hidden" name="event_id" value="<?php echo $ticket['event_id']; ?>">
                                        <button type="submit" name="sync_to_calendar" class="btn-action btn-sync" 
                                                onclick="showSyncLoading(this)" title="Sync ke Google Calendar">
                                            <i class="fab fa-google"></i> Sync ke Calendar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="auth_google.php?user_id=<?php echo $user_id; ?>" class="btn-action btn-connect" 
                                       title="Connect Google Calendar untuk sync">
                                        <i class="fab fa-google"></i> Connect untuk Sync
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-tickets">
                <i class="fas fa-ticket-alt"></i>
                <h3>Belum Ada Tiket</h3>
                <p>Anda belum membeli tiket untuk event apapun. Jelajahi event menarik dan dapatkan pengalaman musik terbaik!</p>
                <a href="events.php" class="btn-action btn-view" style="display: inline-flex; width: auto; padding: 12px 30px;">
                    <i class="fas fa-search"></i> Jelajahi Event
                </a>
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
        // ============ FUNGSI LAINNYA ============
        document.addEventListener('DOMContentLoaded', function() {
            const downloadBtn = document.getElementById('downloadCSV');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function(e) {
                    if (!confirm('Download laporan tiket dalam format CSV?\n\nLaporan akan berisi semua tiket Anda dengan format yang rapi dan lengkap.')) {
                        e.preventDefault();
                    }
                });
            }
            
            const cards = document.querySelectorAll('.ticket-item, .google-section');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('google-section')) {
                        this.style.transform = 'translateY(0)';
                    }
                });
            });
            
            const buttons = document.querySelectorAll('.btn-action, .btn-google, .btn-logout, .btn-action-header');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.7);
                        transform: scale(0);
                        animation: ripple-animation 0.6s linear;
                        width: ${size}px;
                        height: ${size}px;
                        top: ${y}px;
                        left: ${x}px;
                        pointer-events: none;
                        z-index: 1;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple-animation {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
            
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.ticket-item').forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                item.style.transitionDelay = (index * 0.1) + 's';
                observer.observe(item);
            });
            
            // Hitung countdown untuk setiap event
            updateEventCountdowns();
        });

        function showSyncLoading(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyinkronkan...';
            button.disabled = true;
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 3000);
        }

        // ============ UPDATE COUNTDOWN EVENT ============
        function updateEventCountdowns() {
            const now = new Date();
            const countdownElements = document.querySelectorAll('.countdown');
            
            countdownElements.forEach(countdownEl => {
                const parentEl = countdownEl.parentElement;
                const text = parentEl.textContent;
                
                // Cari tanggal dan waktu dari text
                const dateMatch = text.match(/(\d{1,2} \w+ \d{4})/);
                const timeMatch = text.match(/(\d{1,2}:\d{2}) WIB/);
                
                if (dateMatch && timeMatch) {
                    const dateStr = dateMatch[1];
                    const timeStr = timeMatch[1];
                    
                    // Convert ke format Date
                    const months = {
                        'Januari': 0, 'Februari': 1, 'Maret': 2, 'April': 3,
                        'Mei': 4, 'Juni': 5, 'Juli': 6, 'Agustus': 7,
                        'September': 8, 'Oktober': 9, 'November': 10, 'Desember': 11
                    };
                    
                    const [day, monthName, year] = dateStr.split(' ');
                    const month = months[monthName];
                    
                    if (month !== undefined) {
                        const eventDate = new Date(year, month, parseInt(day), 
                                                  parseInt(timeStr.split(':')[0]), 
                                                  parseInt(timeStr.split(':')[1]));
                        
                        const diff = eventDate - now;
                        
                        if (diff > 0) {
                            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                            
                            if (days > 0) {
                                countdownEl.textContent = `⏳ ${days} hari ${hours} jam ${minutes} menit lagi`;
                                countdownEl.style.color = days > 7 ? '#34A853' : (days > 3 ? '#FBBC05' : '#EA4335');
                            } else if (hours > 0) {
                                countdownEl.textContent = `⏳ ${hours} jam ${minutes} menit lagi`;
                                countdownEl.style.color = hours > 24 ? '#34A853' : (hours > 12 ? '#FBBC05' : '#EA4335');
                            } else {
                                countdownEl.textContent = `⏳ ${minutes} menit lagi`;
                                countdownEl.style.color = minutes > 60 ? '#FBBC05' : '#EA4335';
                            }
                        } else {
                            countdownEl.textContent = '⌛ Event telah berlalu';
                            countdownEl.style.color = '#666';
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
<?php
// Tutup koneksi database
mysqli_close($conn);
?>