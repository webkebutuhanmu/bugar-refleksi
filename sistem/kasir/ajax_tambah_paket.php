<?php
// File: kasir/ajax_tambah_paket.php
// AJAX: Tambah paket ke transaksi aktif - perpanjang waktu + kurangi stok
// DILENGKAPI: Pengecekan Ketersediaan Stok Sebelum Insert
ob_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) {
    echo json_encode(['success'=>false,'error'=>'Sesi tidak valid.']); exit;
}

$kasir_id  = $_SESSION['user_id'];
$branch_id = $_SESSION['active_branch'];

// === MULTI-LAYER PARAMETER READING ===
$transaction_id = intval($_POST['transaction_id'] ?? 0);
$package_id     = intval($_POST['package_id'] ?? 0);

if ($transaction_id <= 0 || $package_id <= 0) {
    $raw_input = file_get_contents('php://input');
    if (!empty($raw_input)) {
        parse_str($raw_input, $parsed);
        if (!empty($parsed['transaction_id'])) $transaction_id = intval($parsed['transaction_id']);
        if (!empty($parsed['package_id']))     $package_id = intval($parsed['package_id']);
    }
}

if ($transaction_id <= 0 || $package_id <= 0) {
    echo json_encode(['success'=>false, 'error'=>'Data tidak lengkap (Trx ID: '.$transaction_id.', Pkg ID: '.$package_id.').']); exit;
}

try {
    // 1. Ambil data paket
    $stmtPkg = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
    $stmtPkg->execute([$package_id]);
    $pkg = $stmtPkg->fetch();
    if (!$pkg) { echo json_encode(['success'=>false, 'error'=>'Paket tidak ditemukan.']); exit; }

    // 2. Ambil data transaksi yang akan diperpanjang
    $stmtTrx = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND branch_id = ?");
    $stmtTrx->execute([$transaction_id, $branch_id]);
    $trx = $stmtTrx->fetch();
    if (!$trx) { echo json_encode(['success'=>false, 'error'=>'Transaksi tidak valid atau bukan di cabang ini.']); exit; }

    // =====================================================
    // 3. PENGECEKAN STOK BARANG UNTUK PAKET INI
    // =====================================================
    $stmtReq = $pdo->prepare("
        SELECT pi.item_id, pi.jumlah, i.nama_item 
        FROM package_items pi 
        JOIN items i ON pi.item_id = i.id 
        WHERE pi.package_id = ?
    ");
    $stmtReq->execute([$package_id]);
    $requirements = $stmtReq->fetchAll(PDO::FETCH_ASSOC);

    foreach ($requirements as $req) {
        $stmtCekStok = $pdo->prepare("SELECT stok FROM branch_items WHERE branch_id = ? AND item_id = ?");
        $stmtCekStok->execute([$branch_id, $req['item_id']]);
        $stokData = $stmtCekStok->fetch();
        
        if (!$stokData) {
            echo json_encode(['success'=>false, 'error'=>'Barang "'.htmlspecialchars($req['nama_item']).'" belum terdaftar di cabang ini.']); exit;
        }
        if ($stokData['stok'] < $req['jumlah']) {
            echo json_encode(['success'=>false, 'error'=>'Stok "'.htmlspecialchars($req['nama_item']).'" habis/tidak cukup!']); exit;
        }
    }

    // =====================================================
    // 4. PROSES PENAMBAHAN (Jika stok aman)
    // =====================================================
    $pdo->beginTransaction();

    $settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
    $jam_sekarang = date('H:i:s');
    
    // Tentukan shift & persentase
    if ($jam_sekarang >= $settings['shift_pagi_start'] && $jam_sekarang <= $settings['shift_pagi_end']) {
        $persen_cabang = $settings['pagi_share_company'];
        $persen_terapis = $settings['pagi_share_therapist'];
    } else {
        $persen_cabang = $settings['malam_share_company'];
        $persen_terapis = $settings['malam_share_therapist'];
    }

    $tambah_harga  = floatval($pkg['harga']);
    $tambah_durasi = intval($pkg['durasi_menit']);
    $tambah_oc     = $tambah_harga * ($persen_cabang / 100);
    $tambah_ot     = $tambah_harga * ($persen_terapis / 100);

    // Update Transaksi Utama
    $old_waktu_selesai = $trx['waktu_selesai'];
    if (new DateTime() > new DateTime($old_waktu_selesai)) {
        $new_selesai = date('Y-m-d H:i:s', strtotime("+$tambah_durasi minutes"));
    } else {
        $new_selesai = date('Y-m-d H:i:s', strtotime($old_waktu_selesai . " +$tambah_durasi minutes"));
    }

    $was_paid = ($trx['payment_status'] === 'paid');
    $new_payment_status = $was_paid ? 'unpaid' : $trx['payment_status'];
    $new_status         = $was_paid ? 'menunggu_pembayaran' : $trx['status'];

    $stmtUpd = $pdo->prepare("UPDATE transactions SET 
        total_bayar = total_bayar + ?,
        omset_cabang = omset_cabang + ?,
        omset_terapis = omset_terapis + ?,
        durasi_menit = durasi_menit + ?,
        waktu_selesai = ?,
        payment_status = ?,
        status = ?
        WHERE id = ?
    ");
    $stmtUpd->execute([$tambah_harga, $tambah_oc, $tambah_ot, $tambah_durasi, $new_selesai, $new_payment_status, $new_status, $transaction_id]);

    // Insert History Added Package
    $stmtHistory = $pdo->prepare("INSERT INTO transaction_added_packages (transaction_id, package_id, nama_paket, harga, durasi_menit, omset_cabang, omset_terapis, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmtHistory->execute([$transaction_id, $package_id, $pkg['nama_paket'], $tambah_harga, $tambah_durasi, $tambah_oc, $tambah_ot]);

    // =====================================================
    // 5. KURANGI STOK & CATAT LOG
    // =====================================================
    foreach ($requirements as $req) {
        $pdo->prepare("UPDATE branch_items SET stok = stok - ? WHERE branch_id = ? AND item_id = ?")
            ->execute([$req['jumlah'], $branch_id, $req['item_id']]);
            
        $pdo->prepare("INSERT INTO item_usage_log (branch_id, item_id, transaction_id, jumlah, tipe, keterangan, created_by) VALUES (?, ?, ?, ?, 'pakai', ?, ?)")
            ->execute([$branch_id, $req['item_id'], $transaction_id, -$req['jumlah'], 'Tambah paket: '.$pkg['nama_paket'], $kasir_id]);
    }

    $pdo->commit();

    $msgExtra = '';
    if ($was_paid) {
        $msgExtra = '<br><span style="color:#e74c3c;">Pelanggan sudah bayar sebelumnya. Status pembayaran direset, perlu bayar selisih.</span>';
    }

    echo json_encode([
        'success'    => true,
        'message'    => 'Paket <strong>'.htmlspecialchars($pkg['nama_paket']).'</strong> berhasil ditambahkan!<br>+' . $tambah_durasi . ' menit.<br>Selesai: <strong>'.date('H:i', strtotime($new_selesai)).'</strong>' . $msgExtra
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success'=>false, 'error'=>'Gagal memproses: ' . $e->getMessage()]);
}
?>