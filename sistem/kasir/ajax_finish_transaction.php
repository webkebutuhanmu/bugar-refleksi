<?php
// =====================================================
// AJAX: Selesaikan, Perpanjang, atau Tunggu Pembayaran
// UPDATE: Support status 'menunggu_pembayaran' untuk Bayar Nanti
// =====================================================

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

$role = $_SESSION['role'] ?? null;
$active_branch = $_SESSION['active_branch'] ?? null;

session_write_close();

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($role !== 'kasir' || !$active_branch) {
    echo json_encode(['success' => false, 'error' => 'Sesi habis, silakan login ulang']);
    exit;
}

// =====================================================
// BACA DATA
// =====================================================
$action = '';
$transaction_id = 0;

if (isset($_GET['action']) && $_GET['action'] !== '') $action = $_GET['action'];
if (isset($_GET['transaction_id']) && intval($_GET['transaction_id']) > 0) $transaction_id = intval($_GET['transaction_id']);

if (empty($action) && isset($_POST['action']) && $_POST['action'] !== '') $action = $_POST['action'];
if ($transaction_id <= 0 && isset($_POST['transaction_id']) && intval($_POST['transaction_id']) > 0) $transaction_id = intval($_POST['transaction_id']);

if (empty($action) && isset($_REQUEST['action'])) $action = $_REQUEST['action'];
if ($transaction_id <= 0 && isset($_REQUEST['transaction_id'])) $transaction_id = intval($_REQUEST['transaction_id']);

if ($transaction_id <= 0 || empty($action)) {
    $rawBody = file_get_contents('php://input');
    if (!empty($rawBody)) {
        parse_str($rawBody, $parsed);
        if (empty($action) && isset($parsed['action'])) $action = $parsed['action'];
        if ($transaction_id <= 0 && isset($parsed['transaction_id'])) $transaction_id = intval($parsed['transaction_id']);
    }
}

error_log("[AJAX_FINISH] action=$action, transaction_id=$transaction_id");

if ($transaction_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Transaction ID tidak valid']);
    exit;
}
if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'Action tidak ditemukan']);
    exit;
}

// =====================================================
// AMBIL DATA TRANSAKSI
// =====================================================
try {
    $stmt = $pdo->prepare("SELECT t.*, b.nomor_bed FROM transactions t LEFT JOIN beds b ON t.bed_id = b.id WHERE t.id = ?");
    $stmt->execute([$transaction_id]);
    $trx = $stmt->fetch();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    exit;
}

if (!$trx) {
    echo json_encode(['success' => false, 'error' => 'Transaksi ID ' . $transaction_id . ' tidak ditemukan']);
    exit;
}

// =====================================================
// PROSES
// =====================================================
try {
    if ($action === 'selesai') {
        try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch(Exception $ig) {}

        // =====================================================
        // CEK APAKAH INI TRANSAKSI "BAYAR NANTI" YANG BELUM DIBAYAR
        // Jika ya, ubah status ke 'menunggu_pembayaran' bukan 'selesai'
        // =====================================================
        $payment_status = $trx['payment_status'] ?? 'unpaid';
        $metode_pembayaran = $trx['metode_pembayaran'] ?? '';
        
        // Cek apakah ada paket tambahan yang belum dibayar (customer sudah bayar tapi tambah paket lagi)
        $hasUnpaidAddedPackages = false;
        try {
            $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM transaction_added_packages WHERE transaction_id = ?");
            $stmtChk->execute([$transaction_id]);
            $addedPkgCount = intval($stmtChk->fetchColumn());
            if ($addedPkgCount > 0 && $payment_status === 'unpaid') {
                $hasUnpaidAddedPackages = true;
            }
        } catch (Exception $eignore) {}
        
        if ($payment_status === 'unpaid' && ($metode_pembayaran === 'bayar_nanti' || $hasUnpaidAddedPackages)) {
            // BAYAR NANTI: Pijatan selesai tapi belum bayar â†’ Menunggu Pembayaran
            $pdo->prepare("UPDATE transactions SET status = 'menunggu_pembayaran', waktu_selesai = NOW() WHERE id = ?")
                ->execute([$transaction_id]);
            
            // BED TETAP TERISI sampai pembayaran dikonfirmasi
            // Jangan bebaskan bed!
            
            $waitMsg = $hasUnpaidAddedPackages 
                ? 'Pijatan selesai! Ada tambahan paket yang belum dibayar. Menunggu pembayaran di Bed ' . ($trx['nomor_bed'] ?? '') . '.'
                : 'Pijatan selesai! Menunggu pembayaran di Bed ' . ($trx['nomor_bed'] ?? '') . '.';
            
            echo json_encode([
                'success' => true, 
                'message' => $waitMsg,
                'bed_id' => $trx['bed_id'],
                'waiting_payment' => true,
                'has_added_packages' => $hasUnpaidAddedPackages,
                'transaction_id' => $transaction_id
            ]);
            
        } else {
            // BAYAR SEKARANG / SUDAH LUNAS: Langsung selesai
            $pdo->prepare("UPDATE transactions SET status = 'selesai', waktu_selesai = NOW() WHERE id = ?")
                ->execute([$transaction_id]);
            
            if ($trx['bed_id']) {
                $pdo->prepare("UPDATE beds SET status = 'kosong' WHERE id = ?")->execute([$trx['bed_id']]);
            }
            
            // Update loan status
            $pdo->prepare("UPDATE terapis_loans SET status = 'finished' WHERE transaction_id = ? AND status = 'active'")
                ->execute([$transaction_id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Transaksi selesai! Bed ' . ($trx['nomor_bed'] ?? '') . ' sudah dibebaskan.',
                'bed_id' => $trx['bed_id'],
                'waiting_payment' => false
            ]);
        }
        
    } elseif ($action === 'belum_siap') {
        echo json_encode([
            'success' => true, 
            'message' => 'OK, waktu overtime berjalan. Selesaikan manual nanti.',
            'overtime' => true
        ]);
        
    } elseif ($action === 'konfirmasi_bayar') {
        // =====================================================
        // KONFIRMASI PEMBAYARAN CEPAT (tanpa buka halaman baru)
        // Untuk kasus simple - redirect ke proses_pembayaran.php
        // =====================================================
        echo json_encode([
            'success' => true,
            'redirect' => 'proses_pembayaran.php?transaction_id=' . $transaction_id,
            'message' => 'Redirecting ke halaman pembayaran...'
        ]);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Action tidak valid: ' . $action]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Gagal proses: ' . $e->getMessage()]);
}
?>