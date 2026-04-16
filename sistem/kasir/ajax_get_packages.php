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

$role = $_SESSION['role'] ?? null;
$active_branch = $_SESSION['active_branch'] ?? null;

// Lepas session lock SETELAH database include
session_write_close();

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($role !== 'kasir' || !$active_branch) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT id, nama_paket, deskripsi, durasi_menit, harga FROM packages ORDER BY harga ASC");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'packages' => $packages,
        'count' => count($packages),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>