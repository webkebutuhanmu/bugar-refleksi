<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// FIX KRITIS: Include database.php SEBELUM session_write_close
// agar database.php tidak reopen session setelah close
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

$branch_id = $_SESSION['active_branch'] ?? null;
$role = $_SESSION['role'] ?? null;

// KRITIS: Lepas session lock SETELAH database include
session_write_close();

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($role !== 'kasir' || !$branch_id) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// FIX: Sinkronkan beds — lindungi 'proses' DAN 'menunggu_approval'
$pdo->query("UPDATE beds b
             LEFT JOIN (
                 SELECT bed_id FROM transactions WHERE status IN ('proses', 'menunggu_approval')
             ) t ON b.id = t.bed_id
             SET b.status = 'kosong'
             WHERE t.bed_id IS NULL AND b.status = 'terisi'");

// FIX: Query bed termasuk menunggu_approval
$sqlBeds = "SELECT b.id, b.nomor_bed, b.tipe, b.status,
            (SELECT COUNT(*) FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_approval')) as is_occupied,
            (SELECT t.status FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_approval') ORDER BY FIELD(t.status,'proses','menunggu_approval') LIMIT 1) as trx_status,
            (SELECT t.id FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_approval') ORDER BY FIELD(t.status,'proses','menunggu_approval') LIMIT 1) as transaction_id,
            (SELECT t.nama_pelanggan FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_approval') ORDER BY FIELD(t.status,'proses','menunggu_approval') LIMIT 1) as customer_name,
            (SELECT u.nama_lengkap FROM transactions t JOIN users u ON t.terapis_id = u.id WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_approval') ORDER BY FIELD(t.status,'proses','menunggu_approval') LIMIT 1) as terapis_name,
            (SELECT t.waktu_selesai FROM transactions t WHERE t.bed_id = b.id AND t.status = 'proses' LIMIT 1) as finish_time
            FROM beds b 
            WHERE b.branch_id = ?
            ORDER BY b.nomor_bed ASC";
$stmtBeds = $pdo->prepare($sqlBeds);
$stmtBeds->execute([$branch_id]);
$beds = $stmtBeds->fetchAll(PDO::FETCH_ASSOC);

foreach ($beds as &$bed) {
    $bed['is_overtime'] = false;
    $bed['is_pending'] = ($bed['trx_status'] ?? '') === 'menunggu_approval';
    if ($bed['is_occupied'] > 0 && $bed['finish_time'] && !$bed['is_pending']) {
        if (strtotime($bed['finish_time']) <= time()) {
            $bed['is_overtime'] = true;
            $bed['overtime_seconds'] = time() - strtotime($bed['finish_time']);
        }
    }
}

echo json_encode([
    'success' => true,
    'beds' => $beds,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>