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

$kasir_id = $_SESSION['user_id'] ?? null;
$branch_id = $_SESSION['active_branch'] ?? null;
$session_id = $_SESSION['session_id'] ?? null;
$waktu_buka = $_SESSION['waktu_buka'] ?? null;
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

if (empty($waktu_buka) || strtotime($waktu_buka) === false) {
    $stmtWaktu = $pdo->prepare("SELECT waktu_masuk FROM kasir_attendance WHERE session_id = ? AND status = 'aktif' LIMIT 1");
    $stmtWaktu->execute([$session_id]);
    $waktu_buka_db = $stmtWaktu->fetchColumn();
    
    if ($waktu_buka_db) {
        $waktu_buka = $waktu_buka_db;
    } else {
        $waktu_buka = date('Y-m-d H:i:s');
    }
    // Re-open session briefly to save
    session_start();
    $_SESSION['waktu_buka'] = $waktu_buka;
    session_write_close();
}

$waktu_buka = date('Y-m-d H:i:s', strtotime($waktu_buka));

$sqlShift = "SELECT 
             COUNT(*) as total_trx,
             COALESCE(SUM(total_bayar), 0) as omset_shift
             FROM transactions 
             WHERE kasir_id = ? 
             AND branch_id = ? 
             AND created_at >= ?";
$stmtShift = $pdo->prepare($sqlShift);
$stmtShift->execute([$kasir_id, $branch_id, $waktu_buka]);
$dataShift = $stmtShift->fetch();

echo json_encode([
    'success' => true,
    'omset_shift' => $dataShift['omset_shift'],
    'total_trx' => $dataShift['total_trx']
]);
?>