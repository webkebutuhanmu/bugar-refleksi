<?php
// File: kasir/ajax_search_customer.php
// AJAX: Cari riwayat customer berdasarkan nama (autocomplete)
// Mencari SEMUA customer yang pernah datang ke cabang ini (bukan hanya shift ini)
// + Info "berapa lama lalu" terakhir datang

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$role = $_SESSION['role'] ?? null;
$branch_id = $_SESSION['active_branch'] ?? null;

if ($role !== 'kasir' || !$branch_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$keyword = trim($_GET['q'] ?? '');

if (strlen($keyword) < 1) {
    echo json_encode(['success' => true, 'customers' => []]);
    exit;
}

// Helper: hitung waktu relatif
function waktuLalu($datetime) {
    if (empty($datetime)) return '';
    
    $now = new DateTime();
    $last = new DateTime($datetime);
    $diff = $now->diff($last);
    
    if ($diff->y > 0) {
        return $diff->y == 1 ? '1 tahun lalu' : $diff->y . ' tahun lalu';
    }
    if ($diff->m > 0) {
        return $diff->m == 1 ? '1 bulan lalu' : $diff->m . ' bulan lalu';
    }
    if ($diff->d >= 14) {
        $minggu = floor($diff->d / 7);
        return $minggu == 1 ? '1 minggu lalu' : $minggu . ' minggu lalu';
    }
    if ($diff->d > 0) {
        if ($diff->d == 1) return 'Kemarin';
        return $diff->d . ' hari lalu';
    }
    if ($diff->h > 0) {
        return $diff->h == 1 ? '1 jam lalu' : $diff->h . ' jam lalu';
    }
    if ($diff->i > 0) {
        return $diff->i . ' menit lalu';
    }
    return 'Baru saja';
}

try {
    $sql = "SELECT 
                sub.nama_pelanggan,
                sub.no_hp_pelanggan,
                sub.package_id,
                p.nama_paket,
                sub.total_kunjungan,
                sub.last_visit
            FROM (
                SELECT 
                    t.nama_pelanggan,
                    SUBSTRING_INDEX(GROUP_CONCAT(t.no_hp_pelanggan ORDER BY t.id DESC), ',', 1) as no_hp_pelanggan,
                    SUBSTRING_INDEX(GROUP_CONCAT(t.package_id ORDER BY t.id DESC), ',', 1) as package_id,
                    COUNT(*) as total_kunjungan,
                    MAX(t.created_at) as last_visit
                FROM transactions t
                WHERE t.branch_id = ?
                AND t.nama_pelanggan LIKE ?
                AND t.status != 'batal'
                GROUP BY t.nama_pelanggan
            ) sub
            LEFT JOIN packages p ON p.id = sub.package_id
            ORDER BY sub.last_visit DESC
            LIMIT 8";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$branch_id, '%' . $keyword . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $customers = [];
    foreach ($results as $row) {
        $customers[] = [
            'nama'        => $row['nama_pelanggan'],
            'no_hp'       => (!empty($row['no_hp_pelanggan']) && $row['no_hp_pelanggan'] !== '') ? $row['no_hp_pelanggan'] : '-',
            'package_id'  => $row['package_id'],
            'nama_paket'  => $row['nama_paket'] ?? '-',
            'kunjungan'   => (int)$row['total_kunjungan'],
            'waktu_lalu'  => waktuLalu($row['last_visit'])
        ];
    }
    
    echo json_encode(['success' => true, 'customers' => $customers]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>