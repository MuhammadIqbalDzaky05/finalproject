# finalproject
FINAL PROJECT KELOMPOK 39
# Deskripsi Project WEB
Proyek Web Sistem Manajemen Event Mahasiswa (LiveFest) adalah sebuah platform berbasis web yang dirancang untuk mengelola (admin) dan memesan tiket berbagai event (user), khususnya konser musik dan webinar, dalam satu sistem terpusat. Sistem ini dibangun sebagai proyek akhir mahasiswa dengan tujuan mengintegrasikan proses pemesanan tiket secara digital untuk meningkatkan efisiensi, keamanan, dan kenyamanan pengguna.
# Cara Run Lokal
# Prasyarat
- vscode = Text Editor / IDE
Untuk menulis, mengedit, dan mengelola kode program Anda.
- xampp = Paket Server Lokal
Berisi Apache (web server), MySQL (database), dan PHP (bahasa pemrograman).
- PHP 8.x
- MySQL
- Composer

# Teknologi
- Backend: PHP native
- Database: MySQL
- Frontend:
## css framework
bootstrap
## cdn (content delivery network)
cdns.cloudflare.com/.../all.min.js = ikon library
npm/chart.js = grafik library
code.jquery.com/jquery-3.6.0.min.js = javascript library

# file .env ini private ya pak/bu karena repo di public
DB_HOST = localhost
DB_NAME = nama database 
DB_USER = database user
DB_PASS= password database

API_BASE_URL=
API_KEY=api key google

# Alur Kerja Lokal:
1. Tulis Kode di VS Code → file disimpan di folder C:\xampp\htdocs\nama-project\
2. XAMPP menjalankan server (Apache & MySQL) di laptop
3. Browser mengakses http://localhost/nama-project/
4. Apache (XAMPP) membaca kode PHP/HTML dan menampilkan hasilnya di browser
5. Database (MySQL di XAMPP) menyimpan & mengelola data aplikasi project

# Routing Endpoints
*Halaman (root)*
- index.php, dashboard.php
- login.php, register.php, logout.php, reset_password_direct.php (lupa password/reset langsung)
- vendor/google/apiclient-services/src/books/notification.php

*Admin*
- login teredirect ke index.php
- dashboard.php (tampilan utama)
- events.php (daftar event)
- add_event.php (tambah event)
- edit_events.php (edit event)
- edit_event_form.php (form edit event)
- delete_event.php (hapus event)
- analytics.php (analisa total event, pendapatan)
- manage_tickets.php (kelola tiket)

*User*
- login teredirect ke index.php
- dashboard.php (tampilan utama)
- events.php (daftar event)
- buy_ticket.php (beli tiket)
- my_tickets.php (tiket saya)

*API/JSON*
- composer.json
- credentials.json
- google_calendar_real_log.json
- service-account.json
- - Notifikasi in-app: vendor/google/apiclient-services/src/books/notification.php
- Charts: 
## css framework
bootstrap
## cdn (content delivery network)
cdns.cloudflare.com/.../all.min.js = ikon library
npm/chart.js = grafik library
code.jquery.com/jquery-3.6.0.min.js = javascript library

*CSV*
- CSV: vendor/google/apiclient-services/src/AlertCenter/csv.php

# Keterangan
- ERD, Mock Up Sistem, diagram arsitektur, ADBO (Diagram UML) ada pada laporan
- database.sql ada di drive pengumpulan
- uji coba web ada di drive pengumpulan
