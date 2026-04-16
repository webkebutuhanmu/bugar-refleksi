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

$current_branch_id = $_SESSION['active_branch'] ?? null;
$role = $_SESSION['role'] ?? null;

// Lepas session lock SETELAH database include
session_write_close();

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($role !== 'kasir' || !$current_branch_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // FIX: Cek juga transaksi 'menunggu_approval' sebagai busy
    $sql = "SELECT 
            u.id,
            u.nama_lengkap,
            u.home_branch_id,
            b.nama_cabang as branch_name,
            
            (SELECT COUNT(*) FROM transactions t 
             WHERE t.terapis_id = u.id AND t.status IN ('proses', 'menunggu_approval')) as is_busy,
            
            (SELECT COUNT(*) FROM terapis_loans tl 
             JOIN transactions t ON tl.transaction_id = t.id
             WHERE tl.terapis_id = u.id 
             AND tl.from_branch_id = u.home_branch_id 
             AND tl.status IN ('active', 'pending')
             AND t.status IN ('proses', 'menunggu_approval')) as is_loaned,
            
            (SELECT COUNT(*) FROM kasir_attendance ka 
             WHERE ka.branch_id = u.home_branch_id 
             AND ka.status = 'aktif') as branch_online_count,
             
            (SELECT GROUP_CONCAT(us.nama_lengkap SEPARATOR ', ')
             FROM kasir_attendance ka
             JOIN users us ON ka.kasir_id = us.id
             WHERE ka.branch_id = u.home_branch_id 
             AND ka.status = 'aktif') as active_kasir_names
            
            FROM users u
            JOIN branches b ON u.home_branch_id = b.id
            WHERE u.role = 'terapis'
            AND u.home_branch_id != ?
            ORDER BY b.nama_cabang ASC, u.nama_lengkap ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_branch_id]);
    $all_terapis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $terapis_data = [];
    foreach ($all_terapis as $t) {
        $is_branch_online = ($t['branch_online_count'] > 0);
        $is_available = ($t['is_busy'] == 0 && $t['is_loaned'] == 0);
        
        $terapis_data[] = [
            'id' => $t['id'],
            'nama_lengkap' => $t['nama_lengkap'],
            'branch_id' => $t['home_branch_id'],
            'branch_name' => $t['branch_name'],
            'is_branch_online' => $is_branch_online,
            'is_available' => $is_available,
            'is_busy' => ($t['is_busy'] > 0),
            'is_loaned' => ($t['is_loaned'] > 0),
            'active_kasir' => $t['active_kasir_names'],
            'online_kasir_count' => $t['branch_online_count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'terapis' => $terapis_data,
        'total' => count($terapis_data),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>