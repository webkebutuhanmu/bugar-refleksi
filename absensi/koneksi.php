<?php
$host   = 'localhost';
$user   = 'root';
$pass   = '';
$dbname = 'absensi_bugar';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) { }

// ============================================================
// AUTO-RESET JAM 08:00 PAGI
//
// Setiap hari jam 08:00, semua karyawan shift malam yang masih
// aktif (waktu_keluar IS NULL) akan otomatis di-checkout dengan
// waktu keluar = 07:59:59.
//
// Kondisi yang di-reset:
//   1. Tanggal absen = hari ini DAN waktu_masuk < 08:00:00
//      (karyawan shift malam yang masuk dini hari)
//   2. Tanggal absen < hari ini
//      (karyawan yang belum checkout dari hari sebelumnya)
//
// Query ini idempotent — aman dijalankan setiap page load
// karena hanya memengaruhi baris dengan waktu_keluar IS NULL.
// ============================================================
date_default_timezone_set('Asia/Jakarta');
$_jam_reset    = strtotime('08:00:00');
$_jam_sekarang = strtotime(date('H:i:s'));
$_tgl_hari_ini = date('Y-m-d');

if ($_jam_sekarang >= $_jam_reset) {
    try {
        $pdo->prepare("
            UPDATE attendance
            SET    waktu_keluar = '07:59:59'
            WHERE  waktu_keluar IS NULL
              AND  (
                       tanggal < :tgl
                    OR (tanggal = :tgl2 AND waktu_masuk < '08:00:00')
                   )
        ")->execute([
            ':tgl'  => $_tgl_hari_ini,
            ':tgl2' => $_tgl_hari_ini,
        ]);
    } catch(PDOException $e) { /* silent — jangan ganggu UI */ }
}
?>