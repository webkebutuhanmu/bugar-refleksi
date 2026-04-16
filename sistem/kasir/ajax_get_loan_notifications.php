<?php
// File: kasir/ajax_get_loan_notifications.php
// =====================================================
// GET Semua Notifikasi Dashboard Kasir:
//   1. Terapis yang sedang dipinjam (realtime)
//   2. Paket layanan baru/diupdate (semua cabang)
//   3. Terapis baru/update masuk ke cabang ini
// =====================================================

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

$branch_id = $_SESSION['active_branch'] ?? null;
$role      = $_SESSION['role'] ?? null;

session_write_close();
ob_end_clean();

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($role !== 'kasir' || !$branch_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // =====================================================
    // 1. TERAPIS DIPINJAM (aktif saat ini)
    // =====================================================
    $sqlDipinjam = "SELECT tl.*, 
                   u.nama_lengkap  AS nama_terapis,
                   b.nama_cabang   AS cabang_peminjam,
                   t.nama_pelanggan,
                   t.waktu_selesai,
                   t.waktu_mulai,
                   t.status        AS transaction_status,
                   p.nama_paket,
                   bd.nomor_bed
                   FROM terapis_loans tl
                   JOIN users u         ON tl.terapis_id    = u.id
                   JOIN branches b      ON tl.to_branch_id  = b.id
                   JOIN transactions t  ON tl.transaction_id = t.id
                   LEFT JOIN packages p ON t.package_id     = p.id
                   LEFT JOIN beds bd    ON t.bed_id         = bd.id
                   WHERE tl.from_branch_id = ?
                   AND tl.status = 'active'
                   AND t.status  = 'proses'
                   ORDER BY tl.loan_time DESC";

    $stmtDipinjam = $pdo->prepare($sqlDipinjam);
    $stmtDipinjam->execute([$branch_id]);
    $dipinjam = $stmtDipinjam->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // 2. NOTIFIKASI SISTEM (paket & terapis) – 7 hari terakhir
    //    branch_id IS NULL  → berlaku semua cabang (paket)
    //    branch_id = ?      → hanya cabang ini (terapis)
    // =====================================================
    $sqlSystem = "SELECT * FROM branch_notifications
                  WHERE (branch_id IS NULL OR branch_id = ?)
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  ORDER BY created_at DESC
                  LIMIT 30";

    $stmtSystem = $pdo->prepare($sqlSystem);
    $stmtSystem->execute([$branch_id]);
    $system_notifs = $stmtSystem->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // 3. HITUNG TOTAL BADGE
    //    - Terapis dipinjam aktif
    //    - Notif sistem dalam 24 jam terakhir (baru)
    // =====================================================
    $sqlNewCount = "SELECT COUNT(*) FROM branch_notifications
                    WHERE (branch_id IS NULL OR branch_id = ?)
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $stmtNew = $pdo->prepare($sqlNewCount);
    $stmtNew->execute([$branch_id]);
    $new_system_count = (int)$stmtNew->fetchColumn();

    $total_count = count($dipinjam) + $new_system_count;

    echo json_encode([
        'success'       => true,
        'dipinjam'      => $dipinjam,
        'dipinjam_count'=> count($dipinjam),
        'system_notifs' => $system_notifs,
        'new_system_count' => $new_system_count,
        'count'         => $total_count,
        'timestamp'     => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("[GET_LOAN_NOTIF] ERROR: " . $e->getMessage());
    echo json_encode([
        'success'       => false,
        'message'       => $e->getMessage(),
        'dipinjam'      => [],
        'system_notifs' => [],
        'count'         => 0
    ]);
}
?>