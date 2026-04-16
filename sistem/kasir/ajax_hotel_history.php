<?php
// File: kasir/ajax_hotel_history.php
// Endpoint: ambil riwayat hotel dari transaksi sebelumnya (tipe_lokasi = 'hotel')
session_start();
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$branch_id = $_SESSION['active_branch'];
$q = trim($_GET['q'] ?? '');

try {
    // Ambil riwayat hotel unik dari cabang ini
    // Group by nama hotel (alamat_panggilan), ambil admin & driver terbaru (MAX created_at)
    if ($q !== '') {
        $stmt = $pdo->prepare("
            SELECT 
                alamat_panggilan AS nama_hotel,
                harga_admin_hotel,
                biaya_driver,
                COUNT(*) AS total_kunjungan,
                MAX(created_at) AS terakhir
            FROM transactions
            WHERE branch_id = ?
              AND tipe_lokasi = 'hotel'
              AND alamat_panggilan IS NOT NULL
              AND alamat_panggilan != ''
              AND alamat_panggilan LIKE ?
            GROUP BY alamat_panggilan, harga_admin_hotel, biaya_driver
            ORDER BY total_kunjungan DESC, terakhir DESC
            LIMIT 8
        ");
        $stmt->execute([$branch_id, '%' . $q . '%']);
    } else {
        // Tanpa keyword: tampilkan semua riwayat hotel (urut paling sering/terbaru)
        $stmt = $pdo->prepare("
            SELECT 
                alamat_panggilan AS nama_hotel,
                harga_admin_hotel,
                biaya_driver,
                COUNT(*) AS total_kunjungan,
                MAX(created_at) AS terakhir
            FROM transactions
            WHERE branch_id = ?
              AND tipe_lokasi = 'hotel'
              AND alamat_panggilan IS NOT NULL
              AND alamat_panggilan != ''
            GROUP BY alamat_panggilan, harga_admin_hotel, biaya_driver
            ORDER BY total_kunjungan DESC, terakhir DESC
            LIMIT 10
        ");
        $stmt->execute([$branch_id]);
    }

    $hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format untuk response
    $result = [];
    foreach ($hotels as $h) {
        $result[] = [
            'nama_hotel'       => $h['nama_hotel'],
            'harga_admin_hotel'=> (int)$h['harga_admin_hotel'],
            'biaya_driver'     => (int)$h['biaya_driver'],
            'total_kunjungan'  => (int)$h['total_kunjungan'],
            'terakhir'         => $h['terakhir'] ? date('d M Y', strtotime($h['terakhir'])) : '-',
        ];
    }

    echo json_encode(['success' => true, 'hotels' => $result]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}