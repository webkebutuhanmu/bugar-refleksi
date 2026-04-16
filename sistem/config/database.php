<?php
// Cek dulu: Jika belum ada session yang aktif, baru mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost'; 
$db = 'refleksi_bugar'; 
$user = 'root'; 
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // =====================================================
    // FIX TIMEZONE: Sinkronkan timezone MySQL dengan PHP
    // Ini WAJIB agar NOW(), CURRENT_TIMESTAMP, CURDATE() 
    // menghasilkan waktu Asia/Jakarta (WIB / UTC+7)
    // Tanpa ini, di hosting (InfinityFree dll) MySQL pakai UTC
    // sehingga created_at beda 7 jam & omset tidak masuk
    // =====================================================
    $pdo->exec("SET time_zone = '+07:00'");
    
} catch (PDOException $e) {
    die("Koneksi Gagal: " . $e->getMessage());
}

// =====================================================
// API KEYS
// Ganti nilai di bawah ini dengan API Key milik Anda
// =====================================================
define('GOOGLE_PLACES_API_KEY', 'AIzaSyDVHBtW3nW9HinbBh4YTmbqTmEkVusW3CA');
?>