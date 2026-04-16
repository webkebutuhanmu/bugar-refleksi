<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// FIX KRITIS: Include database.php SEBELUM session_write_close
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

$branch_id = $_SESSION['active_branch'] ?? null;
$role = $_SESSION['role'] ?? null;

// Lepas session lock SETELAH database include
session_write_close();

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($role !== 'kasir' || !$branch_id) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// FIX: Lindungi bed dengan status proses DAN menunggu_approval
$pdo->query("UPDATE beds b
             LEFT JOIN (
                 SELECT bed_id FROM transactions WHERE status IN ('proses', 'menunggu_approval')
             ) t ON b.id = t.bed_id
             SET b.status = 'kosong'
             WHERE t.bed_id IS NULL AND b.status = 'terisi'");

$sqlBeds = "SELECT b.id, b.nomor_bed, b.tipe, b.status,
            (SELECT COUNT(*) FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_approval')) as is_occupied,
            (SELECT t.status FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_approval') ORDER BY FIELD(t.status,'proses','menunggu_approval') LIMIT 1) as trx_status
            FROM beds b 
            WHERE b.branch_id = ?
            ORDER BY b.nomor_bed ASC";
$stmtBeds = $pdo->prepare($sqlBeds);
$stmtBeds->execute([$branch_id]);
$beds = $stmtBeds->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'beds' => $beds,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>