<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) {
    header("Location: pilih_cabang.php"); exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$branch_id  = $_SESSION['active_branch'];
$nama_kasir = $_SESSION['nama'];
$kasir_id   = $_SESSION['user_id'];
$nama_cabang = $pdo->query("SELECT nama_cabang FROM branches WHERE id = $branch_id")->fetchColumn();

// Notif terapis dipinjam (aktif)
$stmtNotifCount = $pdo->prepare("
    SELECT COUNT(*) FROM terapis_loans tl
    JOIN transactions t ON tl.transaction_id = t.id
    WHERE tl.from_branch_id = ? AND tl.status = 'active'
    AND t.status IN ('proses','menunggu_pembayaran')
");
$stmtNotifCount->execute([$branch_id]);
$notif_dipinjam = (int)$stmtNotifCount->fetchColumn();

// Notif sistem (24 jam)
$stmtSysCount = $pdo->prepare("
    SELECT COUNT(*) FROM branch_notifications
    WHERE (branch_id IS NULL OR branch_id = ?) AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmtSysCount->execute([$branch_id]);
$notif_system = (int)$stmtSysCount->fetchColumn();
$notif_count  = $notif_dipinjam + $notif_system;

// AUTO-RELEASE BED
$pdo->query("UPDATE beds b
             LEFT JOIN (SELECT bed_id FROM transactions WHERE status IN ('proses','menunggu_pembayaran')) t ON b.id = t.bed_id
             SET b.status = 'kosong'
             WHERE t.bed_id IS NULL AND b.status = 'terisi'");

// --- LOGIC SHIFT ---
$settings     = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
$current_time = date('H:i:s');
if ($current_time >= $settings['shift_pagi_start'] && $current_time <= $settings['shift_pagi_end']) {
    $shift_saat_ini = 'pagi';
    $label_shift    = "PAGI (" . $settings['pagi_share_company'] . ":" . $settings['pagi_share_therapist'] . ")";
    $color_shift    = "var(--accent-blue)";
} else {
    $shift_saat_ini = 'malam';
    $label_shift    = "MALAM (" . $settings['malam_share_company'] . ":" . $settings['malam_share_therapist'] . ")";
    $color_shift    = "var(--accent-red)";
}

// --- FOTO PROFIL ---
$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$dbFoto      = $stmtProfil->fetchColumn();
$foto_profil = "../assets/default_user.png";
if (!empty($dbFoto) && file_exists("../uploads/profil/" . $dbFoto)) {
    $foto_profil = "../uploads/profil/" . $dbFoto;
}

// DATA BED
$beds = $pdo->query("SELECT b.*,
    (SELECT COUNT(*) FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran')) as is_occupied,
    (SELECT t.status FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as trx_status,
    (SELECT t.id FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as transaction_id,
    (SELECT t.nama_pelanggan FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as customer_name,
    (SELECT t.no_hp_pelanggan FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as customer_hp,
    (SELECT u.nama_lengkap FROM transactions t JOIN users u ON t.terapis_id = u.id WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as terapis_name,
    (SELECT p.nama_paket FROM transactions t JOIN packages p ON t.package_id = p.id WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as nama_paket,
    (SELECT p.durasi_menit FROM transactions t JOIN packages p ON t.package_id = p.id WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as durasi_menit,
    (SELECT t.total_bayar FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as total_bayar,
    (SELECT t.created_at FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as jam_masuk,
    (SELECT t.waktu_selesai FROM transactions t WHERE t.bed_id = b.id AND t.status = 'proses' LIMIT 1) as finish_time,
    (SELECT t.jenis_shift FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as jenis_shift_trx,
    (SELECT t.payment_status FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as payment_status,
    (SELECT t.metode_pembayaran FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY FIELD(t.status,'proses','menunggu_pembayaran') LIMIT 1) as metode_pembayaran
    FROM beds b WHERE b.branch_id = $branch_id ORDER BY b.nomor_bed ASC")->fetchAll();

$packages     = $pdo->query("SELECT * FROM packages ORDER BY harga ASC")->fetchAll();
$countKosong  = 0;
$countTerisi  = 0;
foreach ($beds as $b) {
    if ($b['is_occupied'] == 0) $countKosong++;
    else $countTerisi++;
}

// LOGIC STOK PAKET
$stmtStock = $pdo->prepare("SELECT item_id, stok FROM branch_items WHERE branch_id = ?");
$stmtStock->execute([$branch_id]);
$branchStocks = [];
while ($row = $stmtStock->fetch(PDO::FETCH_ASSOC)) { $branchStocks[$row['item_id']] = (int)$row['stok']; }

$pkgRequirements = [];
$stmtPkgItems = $pdo->query("SELECT package_id, item_id, jumlah FROM package_items");
while ($row = $stmtPkgItems->fetch(PDO::FETCH_ASSOC)) {
    $pkgRequirements[$row['package_id']][] = ['item_id' => (int)$row['item_id'], 'jumlah' => (int)$row['jumlah']];
}

function isPackageAvailable($pkgId, $requirements, $stocks) {
    if (!isset($requirements[$pkgId])) return true;
    foreach ($requirements[$pkgId] as $req) {
        $itemId = $req['item_id']; $qtyNeeded = $req['jumlah'];
        if (!isset($stocks[$itemId]) || $stocks[$itemId] < $qtyNeeded) return false;
    }
    return true;
}

foreach ($packages as &$p) {
    $p['available'] = isPackageAvailable($p['id'], $pkgRequirements, $branchStocks);
}
unset($p);

// AMBIL DATA PAKET TAMBAHAN UNTUK TRANSAKSI AKTIF
$addedPkgMap = [];
$activeTrxIds = [];
foreach ($beds as $b) {
    if (!empty($b['transaction_id'])) {
        $activeTrxIds[] = intval($b['transaction_id']);
    }
}
if (!empty($activeTrxIds)) {
    try {
        $pdo->query("SELECT 1 FROM transaction_added_packages LIMIT 1");
        $ph = implode(',', array_fill(0, count($activeTrxIds), '?'));
        $stmtAP = $pdo->prepare("SELECT * FROM transaction_added_packages WHERE transaction_id IN ($ph) ORDER BY created_at ASC");
        $stmtAP->execute($activeTrxIds);
        foreach ($stmtAP->fetchAll() as $ap) {
            $addedPkgMap[$ap['transaction_id']][] = [
                'nama_paket' => $ap['nama_paket'],
                'harga'      => floatval($ap['harga']),
                'durasi'     => intval($ap['durasi_menit']),
            ];
        }
    } catch (Exception $e) {}
}

// =========================================================
// IZIN/SAKIT TERAPIS HARI INI & PERIODE
// =========================================================
$jamMulaiBisnis = $settings['jam_mulai_hari'] ?? '08:00:00';
$jamSekarang2   = date('H:i:s');
$tglBisnis      = ($jamSekarang2 < $jamMulaiBisnis) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$start_periode  = "$tglBisnis $jamMulaiBisnis";
$end_periode    = date('Y-m-d H:i:s', strtotime("$start_periode +1 day"));

$izinMapDK = [];
$cekTabelIzin = $pdo->query("SHOW TABLES LIKE 'terapis_izin'")->rowCount();
if ($cekTabelIzin > 0) {
    $stmtIzinDK = $pdo->prepare("SELECT terapis_id, jenis, status FROM terapis_izin WHERE branch_id = ? AND tanggal = ? AND status IN ('disetujui','pending')");
    $stmtIzinDK->execute([$branch_id, $tglBisnis]);
    foreach ($stmtIzinDK->fetchAll() as $iz) {
        if (!isset($izinMapDK[$iz['terapis_id']])) $izinMapDK[$iz['terapis_id']] = $iz;
    }
}

// =========================================================
// STATUS TERAPIS (Mengikuti urutan di input_transaksi.php)
// =========================================================
$sqlTerapis = "SELECT u.id, u.nama_lengkap, 
    (SELECT COUNT(*) FROM transactions t WHERE t.terapis_id = u.id AND t.status IN ('proses', 'menunggu_approval', 'menunggu_pembayaran')) as is_busy, 
    (SELECT COUNT(*) FROM terapis_loans tl JOIN transactions tlt ON tl.transaction_id = tlt.id WHERE tl.terapis_id = u.id AND tl.from_branch_id = ? AND tl.status IN ('active', 'pending') AND tlt.status IN ('proses', 'menunggu_approval', 'menunggu_pembayaran')) as is_loaned, 
    (SELECT COUNT(*) FROM transactions t2 WHERE t2.terapis_id = u.id AND t2.created_at >= ? AND t2.created_at < ? AND t2.status != 'batal') as kerja_hari_ini, 
    (SELECT MAX(t3.waktu_selesai) FROM transactions t3 WHERE t3.terapis_id = u.id AND t3.created_at >= ? AND t3.created_at < ? AND t3.status IN ('selesai','proses','menunggu_pembayaran')) as last_selesai, 
    ta.giliran as giliran_absen, ta.waktu_absen,
    (SELECT t.waktu_selesai FROM transactions t WHERE t.terapis_id = u.id AND t.status IN ('proses','menunggu_pembayaran') ORDER BY t.waktu_selesai DESC LIMIT 1) as waktu_selesai,
    (SELECT t.id FROM transactions t WHERE t.terapis_id = u.id AND t.status IN ('proses','menunggu_pembayaran') LIMIT 1) as current_trx_id,
    (SELECT tl.to_branch_id FROM terapis_loans tl JOIN transactions t ON tl.transaction_id = t.id WHERE tl.terapis_id = u.id AND tl.from_branch_id = ? AND tl.status = 'active' AND t.status IN ('proses','menunggu_pembayaran') LIMIT 1) as dipinjam_ke,
    (SELECT br.nama_cabang FROM terapis_loans tl JOIN branches br ON tl.to_branch_id = br.id JOIN transactions t ON tl.transaction_id = t.id WHERE tl.terapis_id = u.id AND tl.from_branch_id = ? AND tl.status = 'active' AND t.status IN ('proses','menunggu_pembayaran') LIMIT 1) as nama_cabang_peminjam,
    (SELECT t.waktu_selesai FROM terapis_loans tl JOIN transactions t ON tl.transaction_id = t.id WHERE tl.terapis_id = u.id AND tl.from_branch_id = ? AND tl.status = 'active' AND t.status IN ('proses','menunggu_pembayaran') LIMIT 1) as waktu_kembali_estimasi
    FROM users u LEFT JOIN terapis_attendance ta ON u.id = ta.terapis_id AND ta.branch_id = ? AND ta.tanggal = ? 
    WHERE u.role = 'terapis' AND u.home_branch_id = ? 
    ORDER BY (ta.id IS NULL) ASC, kerja_hari_ini ASC, IFNULL(ta.giliran, 9999) ASC, last_selesai ASC, u.nama_lengkap ASC";

$stmtTerapis = $pdo->prepare($sqlTerapis);
$stmtTerapis->execute([
    $branch_id, 
    $start_periode, $end_periode, 
    $start_periode, $end_periode,
    $branch_id,
    $branch_id,
    $branch_id,
    $branch_id, $tglBisnis,
    $branch_id
]);
$terapis = $stmtTerapis->fetchAll();


// SHIFT AKTIF SEKARANG
$sqlShiftAktif = "SELECT ka.id, ka.waktu_masuk, ka.omset_shift, ka.total_transaksi_shift, u.nama_lengkap as nama_kasir
                  FROM kasir_attendance ka JOIN users u ON ka.kasir_id = u.id
                  WHERE ka.branch_id = ? AND ka.status = 'aktif' AND ka.waktu_masuk >= ?
                  ORDER BY ka.waktu_masuk DESC LIMIT 1";
$stmtSA = $pdo->prepare($sqlShiftAktif);
$stmtSA->execute([$branch_id, $start_periode]);
$shiftAktif = $stmtSA->fetch();

$omsetShiftAktif = 0;
$trxShiftAktif   = 0;
if ($shiftAktif) {
    $rs = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_bayar),0) as omset FROM transactions WHERE branch_id=? AND created_at >= ? AND status != 'batal'");
    $rs->execute([$branch_id, $shiftAktif['waktu_masuk']]);
    $rsRow = $rs->fetch();
    $trxShiftAktif   = $rsRow['cnt'];
    $omsetShiftAktif = $rsRow['omset'];
}

// TAMU TERAKHIR
$sqlCust = "SELECT t.id as last_trx_id, t.nama_pelanggan, t.no_hp_pelanggan, t.total_bayar as last_bayar,
                   t.created_at as last_visit, t.tanggal_transaksi as last_tanggal,
                   t.waktu_selesai as last_selesai, t.jenis_shift as last_shift, t.status as last_status,
                   p.nama_paket as last_paket, p.durasi_menit as last_durasi,
                   u.nama_lengkap as last_terapis,
                   (SELECT COUNT(*) FROM transactions t2 WHERE t2.nama_pelanggan = t.nama_pelanggan AND t2.branch_id = ?) as total_kunjungan,
                   (SELECT COALESCE(SUM(t2.total_bayar),0) FROM transactions t2 WHERE t2.nama_pelanggan = t.nama_pelanggan AND t2.branch_id = ?) as total_spending
            FROM transactions t JOIN packages p ON t.package_id = p.id JOIN users u ON t.terapis_id = u.id
            WHERE t.branch_id = ? AND t.id IN (
                SELECT MAX(t3.id) FROM transactions t3 WHERE t3.branch_id = ? GROUP BY t3.nama_pelanggan, t3.no_hp_pelanggan
            ) ORDER BY t.created_at DESC LIMIT 10";
$stmtCust = $pdo->prepare($sqlCust);
$stmtCust->execute([$branch_id, $branch_id, $branch_id, $branch_id]);
$customers = $stmtCust->fetchAll();

// GRAFIK OMSET 7 HARI
$sqlChart = "SELECT DATE_FORMAT(tanggal_transaksi,'%d/%m') as tgl, SUM(total_bayar) as omset, COUNT(*) as jumlah
             FROM transactions WHERE branch_id = ? AND tanggal_transaksi >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY tanggal_transaksi ORDER BY tanggal_transaksi ASC";
$stmtChart = $pdo->prepare($sqlChart);
$stmtChart->execute([$branch_id]);
$chartData = $stmtChart->fetchAll();
$labels = []; $dataOmset = []; $dataTrx = [];
foreach ($chartData as $cd) {
    $labels[]    = $cd['tgl'];
    $dataOmset[] = (float)$cd['omset'];
    $dataTrx[]   = (int)$cd['jumlah'];
}

// DATA PANGGILAN AKTIF
$panggilanAktif = [];
try {
    $stmtPanggil = $pdo->prepare("
        SELECT t.id, t.nama_pelanggan, t.no_hp_pelanggan, t.waktu_mulai, t.waktu_selesai,
               t.total_bayar, t.status, t.payment_status, t.metode_pembayaran,
               t.alamat_panggilan, t.biaya_driver,
               p.nama_paket, p.durasi_menit,
               u.nama_lengkap as nama_terapis
        FROM transactions t
        JOIN packages p ON t.package_id = p.id
        JOIN users u ON t.terapis_id = u.id
        WHERE t.branch_id = ? AND t.tipe_transaksi = 'panggilan'
          AND t.status IN ('proses','menunggu_pembayaran')
        ORDER BY t.created_at DESC
    ");
    $stmtPanggil->execute([$branch_id]);
    $panggilanAktif = $stmtPanggil->fetchAll();
    
    if (!empty($panggilanAktif)) {
        $panggilanIds = array_column($panggilanAktif, 'id');
        if (!empty($panggilanIds)) {
            $ph2 = implode(',', array_fill(0, count($panggilanIds), '?'));
            try {
                $stmtAP2 = $pdo->prepare("SELECT * FROM transaction_added_packages WHERE transaction_id IN ($ph2) ORDER BY created_at ASC");
                $stmtAP2->execute($panggilanIds);
                foreach ($stmtAP2->fetchAll() as $ap2) {
                    if (!isset($addedPkgMap[$ap2['transaction_id']])) {
                        $addedPkgMap[$ap2['transaction_id']] = [];
                    }
                    $found = false;
                    foreach ($addedPkgMap[$ap2['transaction_id']] as $existing) {
                        if ($existing['nama_paket'] === $ap2['nama_paket']) { $found = true; break; }
                    }
                    if (!$found) {
                        $addedPkgMap[$ap2['transaction_id']][] = [
                            'nama_paket' => $ap2['nama_paket'],
                            'harga'      => floatval($ap2['harga']),
                            'durasi'     => intval($ap2['durasi_menit']),
                        ];
                    }
                }
            } catch (Exception $e2) {}
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kasir - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .bed-grid-big { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 18px; }
        .bed-box {
            background: var(--bg-panel); border-radius: 12px; padding: 20px 15px;
            text-align: center; border: 2px solid var(--border-color); cursor: pointer;
            transition: all 0.2s; position: relative;
            min-height: 160px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;
        }
        .bed-box:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--accent-yellow); }
        .bed-box.occupied   { border-color: var(--accent-red); background: rgba(231,76,60,0.03); }
        .bed-box.overtime   { border-color: var(--accent-yellow2); background: rgba(245,184,0,0.05); animation: pulseOT 1.5s infinite; }
        .bed-box.waiting-payment { border-color: #9b59b6; background: rgba(155,89,182,0.05); animation: pulsePayment 1.5s infinite; }
        .bed-box.available  { border-color: var(--accent-green); background: rgba(39,174,96,0.03); }
        .bed-box.selected   { border-color: var(--accent-blue); background: rgba(52,152,219,0.05); box-shadow: 0 0 0 3px rgba(52,152,219,0.2); }
        
        @keyframes pulseOT      { 0%,100%{box-shadow:0 0 0 0 rgba(245,184,0,0.4);}  50%{box-shadow:0 0 0 8px rgba(245,184,0,0);} }
        @keyframes pulsePayment { 0%,100%{box-shadow:0 0 0 0 rgba(155,89,182,0.4);}  50%{box-shadow:0 0 0 8px rgba(155,89,182,0);} }
        
        .bed-num   { font-size: 24px; font-weight: 800; color: var(--text-dark); font-family: 'Plus Jakarta Sans', sans-serif; }
        .bed-tipe  { font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .bed-info  { font-size: 11px; margin-top: 5px; padding: 8px; border-radius: 8px; width: 100%; }
        .bed-box.occupied   .bed-info { background: rgba(231,76,60,0.1); color: var(--accent-red); }
        .bed-box.overtime   .bed-info { background: rgba(245,184,0,0.15); color: var(--accent-yellow2); }
        .bed-box.waiting-payment .bed-info { background: rgba(155,89,182,0.15); color: #8e44ad; }
        
        .cust-name { font-weight: 700; font-size: 13px; color: var(--text-dark); margin-bottom: 3px; }
        .payment-badge  { display:inline-block; background:#9b59b6; color:white; padding:3px 8px; border-radius:4px; font-size:10px; font-weight:bold; animation:blink 1s infinite; margin-top:5px;}
        .overtime-badge { display:inline-block; background:var(--accent-yellow2); color:#111; padding:3px 8px; border-radius:4px; font-size:10px; font-weight:bold; animation:blink 1s infinite; margin-top:5px;}
        @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.5;} }
        
        .bed-status-legend { display:flex; gap:16px; flex-wrap:wrap; font-size:12px; margin-bottom:20px; padding:12px 20px; background: var(--bg-input); border-radius:8px; font-weight: 600; color: var(--text-muted); border: 1px solid var(--border-color); }
        .leg-item { display:flex; align-items:center; gap:8px; }
        .leg-dot  { width:12px; height:12px; border-radius:50%; }

        .shift-info-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:15px; margin-bottom:15px; }
        .shift-stat-box { background:var(--bg-panel); border-radius:12px; padding:18px 15px; text-align:center; border:1px solid var(--border-color); }
        .shift-stat-box .stat-val { font-size:22px; font-weight:800; color:var(--text-dark); margin-bottom: 5px; font-family: 'Plus Jakarta Sans', sans-serif; }
        .shift-stat-box .stat-lbl { font-size:11px; color:var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }

        .chart-toggle-btn { padding:6px 16px; border:1px solid var(--border-color); border-radius:20px; background:var(--bg-input); cursor:pointer; font-size:12px; font-weight:bold; transition:0.2s; color:var(--text-muted); }
        .chart-toggle-btn.active.green { background:var(--accent-green); border-color:var(--accent-green); color:white; }
        .chart-toggle-btn.active.blue  { background:var(--accent-blue); border-color:var(--accent-blue); color:white; }

        .paket-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:15px; }
        .pkg-tabs { display:flex; gap:10px; margin-bottom:15px; }
        .pkg-tab-btn { padding:8px 20px; border-radius:20px; border:1px solid var(--border-color); background:var(--bg-input); cursor:pointer; font-size:13px; font-weight:600; color:var(--text-muted); transition:0.2s; }
        .pkg-tab-btn.active { background:var(--text-dark); border-color:var(--text-dark); color:var(--bg-panel); }
        .pkg-grid-wrapper { display:none; }
        .pkg-grid-wrapper.show { display:block; }
        .pkg-card { background:var(--bg-panel); padding:15px; border-radius:10px; border:1px solid var(--border-color); cursor:pointer; display:flex; justify-content:space-between; align-items:center; transition: 0.2s; }
        .pkg-card:hover:not(.pkg-unavailable) { border-color:var(--accent-green); background: rgba(39,174,96,0.05); }
        .pkg-card.selected { border-color:var(--accent-green); background: rgba(39,174,96,0.1); box-shadow: 0 0 0 2px rgba(39,174,96,0.2); }
        .pkg-card.pkg-unavailable { opacity: 0.5; cursor: not-allowed; background: var(--bg-input); }

        .dash-bottom-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }

        .tamu-row { padding:12px 15px; border-bottom:1px solid var(--border-color); transition:0.2s; display:flex; justify-content:space-between; align-items:center; }
        .tamu-row:hover { background:var(--bg-input); }
        .tamu-name { font-weight:700; color:var(--text-dark); font-size:14px; margin-bottom: 3px; }

        .fab-action { position:fixed; bottom:30px; right:30px; background:var(--accent-green); color:white; padding:15px 30px; border-radius:50px; font-weight:bold; font-size:15px; box-shadow:var(--shadow-md); display:none; cursor:pointer; z-index:1000; letter-spacing: 0.5px; transition: 0.3s; }
        .fab-action:hover { transform: translateY(-3px); }

        .panggilan-table { width:100%; border-collapse:collapse; font-size:13px; }
        .panggilan-table th { background:var(--bg-input); padding:12px; text-align:left; border-bottom:1px solid var(--border-color); font-weight:700; color:var(--text-muted); }
        .panggilan-table td { padding:12px; border-bottom:1px solid var(--border-color); vertical-align:middle; color: var(--text-dark); }
        .panggilan-action-btn { padding:6px 12px; border:none; border-radius:6px; font-size:12px; font-weight:bold; cursor:pointer; margin:2px; transition: 0.2s; }
        .panggilan-action-btn:hover { filter: brightness(1.1); }

        .user-dropdown-wrap { position: relative; display: inline-block; margin-left: 10px; border-left: 1px solid var(--border-color); padding-left: 15px; }
        .btn-profile-dropdown { display: flex; align-items: center; gap: 10px; background: transparent; border: none; cursor: pointer; padding: 5px 10px; border-radius: 8px; transition: 0.2s; }
        .btn-profile-dropdown:hover { background: var(--bg-input); }
        .btn-profile-dropdown img { width: 36px !important; height: 36px !important; border-radius: 50% !important; object-fit: cover !important; border: 2px solid var(--accent-yellow) !important; }
        .btn-profile-dropdown span { font-weight: 700; color: var(--text-dark); font-size: 14px; }
        .user-dropdown-menu { position: absolute; right: 0; top: 110%; background: var(--bg-panel); min-width: 180px; box-shadow: var(--shadow-md); border-radius: 12px; border: 1px solid var(--border-color); display: none; flex-direction: column; z-index: 1000; overflow: hidden; }
        .user-dropdown-menu.show { display: flex; animation: fadeIn 0.2s; }
        .user-dropdown-menu a { padding: 12px 18px; font-size: 13px; font-weight: 600; color: var(--text-dark); text-decoration: none; transition: 0.2s; }
        .user-dropdown-menu a:hover { background: var(--bg-input); color: var(--accent-blue); padding-left: 22px; }

        .notification-bell { position:relative; cursor:pointer; color: var(--text-muted); transition: 0.3s; display: flex; align-items: center; justify-content: center; padding: 5px; }
        .notification-bell:hover { color: var(--text-dark); }
        .notification-badge { position:absolute; top:-2px; right:-2px; background:var(--accent-red); color:white; border-radius:50%; width:16px; height:16px; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:bold; }
        
        .notification-panel { position:fixed; top:0; right:-400px; width:400px; height:100%; background:var(--bg-panel); box-shadow:-5px 0 25px rgba(0,0,0,0.1); transition:right 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index:9999; overflow-y:auto; border-left: 1px solid var(--border-color); }
        .notification-panel.active { right:0; }
        .notification-header { padding:20px 25px; background:var(--bg-input); border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; }
        .notification-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:none; z-index:9998; }
        .notification-overlay.active { display:block; animation: fadeIn 0.2s; }
        
        .toast { position:fixed; top:20px; right:20px; background:var(--accent-green); color:white; padding:15px 25px; border-radius:8px; box-shadow:var(--shadow-md); z-index:3000; display:none; font-weight:bold; font-size: 14px; }
        .toast.error { background:var(--accent-red); }

        .modal-body .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        .modal-body .info-row .label { color: var(--text-muted); font-weight: 600; }
        .modal-body .info-row .value { font-weight: 700; color: var(--text-dark); text-align: right; max-width: 60%; }
        .modal-body .info-timer { text-align: center; padding: 15px; margin: 15px 0; border-radius: 8px; font-size: 24px; font-weight: bold; letter-spacing: 2px; font-family: 'DM Sans', monospace; }
        .info-timer.running { background: rgba(39,174,96,0.1); color: var(--accent-green); }
        .info-timer.overtime { background: rgba(245,184,0,0.15); color: var(--accent-yellow2); animation: blink 1s infinite; }
        .info-timer.waiting-pay { background: rgba(155,89,182,0.1); color: #8e44ad; }
    </style>
</head>
<body>
    <div class="container-layout">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-profile">
                <img src="<?= $foto_profil ?>" alt="Profil" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-yellow); margin-bottom: 10px;">
                <div class="profile-info">
                    <h3><?= htmlspecialchars($nama_kasir) ?></h3>
                    <small><?= htmlspecialchars($nama_cabang) ?></small>
                </div>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard_kasir.php" class="menu-item active"><span class="menu-abbr">DB</span><span class="menu-text">Dashboard</span></a>
                <a href="input_transaksi.php" class="menu-item"><span class="menu-abbr">IT</span><span class="menu-text">Input Transaksi</span></a>
                <a href="absensi_kasir.php" class="menu-item"><span class="menu-abbr">AT</span><span class="menu-text">Absensi Terapis</span></a>
                <a href="data_terapis_hadir.php" class="menu-item"><span class="menu-abbr">DT</span><span class="menu-text">Data Terapis</span></a>
                <a href="data_customer_kasir.php" class="menu-item"><span class="menu-abbr">DC</span><span class="menu-text">Data Customer</span></a>
                <a href="paket_layanan_kasir.php" class="menu-item"><span class="menu-abbr">PL</span><span class="menu-text">Paket Layanan</span></a>
                <a href="stok_barang.php" class="menu-item"><span class="menu-abbr">SB</span><span class="menu-text">Stok Barang</span></a>
                <a href="tutup_cabang.php" class="menu-item" style="margin-top:30px; color:var(--accent-red);"><span class="menu-abbr" style="background:rgba(231,76,60,0.1); color:var(--accent-red);">TS</span><span class="menu-text">Tutup Shift</span></a>
            </div>
            <button class="sidebar-toggle-btn" onclick="toggleSidebar()">
                <span class="menu-text">Minimize Sidebar</span>
                <span class="menu-abbr" style="display:none;">▶</span>
            </button>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div style="display:flex; align-items:center; gap:15px;">
                    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
                    <div>
                        <h1 style="margin-bottom: 5px;">Dashboard Kasir</h1>
                        <span class="badge" style="background:<?= $color_shift ?>; color:white;"><?= $label_shift ?></span>
                    </div>
                </div>
                <div class="topbar-right">
                    <div style="text-align:right; display:flex; flex-direction:column; justify-content:center;">
                        <div id="realtimeClock" style="font-size:18px; font-weight:bold; color:var(--text-dark); font-family: monospace; line-height:1;">--:--:--</div>
                        <small style="color:var(--text-muted); font-weight:600; font-size: 11px; text-transform:uppercase; margin-top:4px;"><?= date('d M Y') ?></small>
                    </div>
                    
                    <div class="notification-bell" id="notificationBell" title="Notifikasi" style="margin: 0 5px;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    </div>
                    
                    <button class="theme-btn" onclick="toggleTheme()" title="Ganti Tema">Mode Layar</button>
                    
                    <div class="user-dropdown-wrap">
                        <button class="btn-profile-dropdown" onclick="toggleUserDropdown(event)">
                            <img src="<?= $foto_profil ?>" alt="Profil">
                            <span><?= htmlspecialchars(explode(' ', $nama_kasir)[0]) ?> ▾</span>
                        </button>
                        <div class="user-dropdown-menu" id="userDropdown">
                            <a href="profil_kasir.php">Profil Saya</a>
                            <a href="pengaturan_kasir.php">Pengaturan Akun</a>
                            <div style="border-top:1px solid var(--border-color); margin:0;"></div>
                            <a href="../auth/logout_system.php" style="color:var(--accent-red);">Keluar Sistem</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Info Shift Saat Ini</span>
                    <?php if($shiftAktif): ?>
                    <small style="font-weight:600; color: var(--text-muted);">
                        Aktif sejak <?= date('H:i', strtotime($shiftAktif['waktu_masuk'])) ?>
                        &nbsp;|&nbsp; Durasi: <strong id="durasiShift" data-mulai="<?= $shiftAktif['waktu_masuk'] ?>" style="color:var(--text-dark);">--:--:--</strong>
                    </small>
                    <?php else: ?>
                    <small style="font-weight:bold; color:var(--accent-red);">Tidak ada shift aktif</small>
                    <?php endif; ?>
                </div>
                <div style="padding: 20px;">
                    <?php if($shiftAktif): ?>
                    <div class="shift-info-grid">
                        <div class="shift-stat-box" style="border-top: 3px solid var(--accent-blue);">
                            <div class="stat-lbl">Jam Buka</div>
                            <div class="stat-val"><?= date('H:i', strtotime($shiftAktif['waktu_masuk'])) ?></div>
                        </div>
                        <div class="shift-stat-box" style="border-top: 3px solid var(--accent-green);">
                            <div class="stat-lbl">Omset Shift</div>
                            <div class="stat-val">Rp <?= number_format($omsetShiftAktif/1000, 0) ?>k</div>
                        </div>
                        <div class="shift-stat-box" style="border-top: 3px solid #9b59b6;">
                            <div class="stat-lbl">Transaksi</div>
                            <div class="stat-val"><?= $trxShiftAktif ?></div>
                        </div>
                        <div class="shift-stat-box" style="border-top: 3px solid var(--accent-red);">
                            <div class="stat-lbl">Bed Terisi</div>
                            <div class="stat-val"><?= $countTerisi ?></div>
                        </div>
                        <div class="shift-stat-box" style="border-top: 3px solid var(--accent-green);">
                            <div class="stat-lbl">Bed Kosong</div>
                            <div class="stat-val"><?= $countKosong ?></div>
                        </div>
                        <div class="shift-stat-box" style="border-top: 3px solid var(--accent-yellow2);">
                            <div class="stat-lbl">Terapis Siap</div>
                            <div class="stat-val"><?= count(array_filter($terapis, fn($t) => $t['current_trx_id'] == null && $t['dipinjam_ke'] == null)) ?></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="text-align:center; padding:30px; color:var(--text-muted); font-weight:600;">
                        Belum ada shift yang dibuka. Silakan buka shift terlebih dahulu.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Status Bed & Ruangan</span>
                    <a href="input_transaksi.php" class="btn btn-primary btn-sm">Input Transaksi Baru</a>
                </div>
                <div style="padding:20px;">
                    <div class="bed-status-legend">
                        <div class="leg-item"><div class="leg-dot" style="background:var(--accent-green);"></div> Kosong</div>
                        <div class="leg-item"><div class="leg-dot" style="background:var(--accent-red);"></div> Terisi</div>
                        <div class="leg-item"><div class="leg-dot" style="background:var(--accent-yellow2);"></div> Overtime</div>
                        <div class="leg-item"><div class="leg-dot" style="background:#9b59b6;"></div> Menunggu Pembayaran</div>
                        <div class="leg-item"><div class="leg-dot" style="background:var(--accent-blue);"></div> Dipilih</div>
                    </div>

                    <div class="bed-grid-big" id="bedContainer">
                        <?php foreach($beds as $b):
                            $isOccupied   = ($b['is_occupied'] > 0);
                            $isWaitingPay = ($isOccupied && ($b['trx_status'] ?? '') === 'menunggu_pembayaran');
                            $isOvertime   = ($isOccupied && !$isWaitingPay && $b['finish_time'] && strtotime($b['finish_time']) <= time());

                            if ($isWaitingPay)   $class = 'waiting-payment';
                            elseif ($isOvertime) $class = 'overtime';
                            elseif ($isOccupied) $class = 'occupied';
                            else                 $class = 'available';

                            $trxIdVal = $b['transaction_id'] ?? '';
                            $onclick  = ($isOccupied && !empty($trxIdVal))
                                ? "showBedInfo(this)"
                                : "selectBed(this, {$b['id']})";
                        ?>
                        <div class="bed-box <?= $class ?>" onclick="<?= $onclick ?>"
                             data-id="<?= $b['id'] ?>"
                             data-trxid="<?= $trxIdVal ?>"
                             data-trxstatus="<?= htmlspecialchars($b['trx_status'] ?? '') ?>"
                             data-finish="<?= ($isWaitingPay ? '' : ($b['finish_time'] ?? '')) ?>"
                             data-customer="<?= htmlspecialchars($b['customer_name'] ?? '') ?>"
                             data-terapis="<?= htmlspecialchars($b['terapis_name'] ?? '') ?>"
                             data-paket="<?= htmlspecialchars($b['nama_paket'] ?? '') ?>"
                             data-durasi="<?= $b['durasi_menit'] ?? '' ?>"
                             data-bayar="<?= $b['total_bayar'] ?? '0' ?>"
                             data-masuk="<?= $b['jam_masuk'] ?? '' ?>"
                             data-bed="<?= htmlspecialchars($b['nomor_bed']) ?>"
                             data-tipe="<?= htmlspecialchars($b['tipe']) ?>"
                             data-paymentstatus="<?= htmlspecialchars($b['payment_status'] ?? 'unpaid') ?>">
                            
                            <div class="bed-num"><?= htmlspecialchars($b['nomor_bed']) ?></div>
                            <div class="bed-tipe"><?= htmlspecialchars($b['tipe']) ?></div>
                            
                            <?php if($isOccupied): ?>
                            <div class="bed-info">
                                <div class="cust-name"><?= htmlspecialchars(mb_substr($b['customer_name']??'',0,15)) ?></div>
                                <div style="font-size:11px; margin-top:3px; opacity:0.8; font-weight:600;"><?= htmlspecialchars(mb_substr($b['terapis_name']??'',0,15)) ?></div>
                                <div style="margin-top:5px;">
                                    <?php if($isWaitingPay): ?>
                                        <span class="payment-badge">BELUM BAYAR</span>
                                    <?php else: ?>
                                        <span class="countdown" data-finish="<?= $b['finish_time'] ?>" data-trxid="<?= $trxIdVal ?>" style="font-weight:bold; font-family:monospace; font-size:12px;">...</span>
                                        <?php if($isOvertime): ?><br><span class="overtime-badge">OVERTIME</span><?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($panggilanAktif)): ?>
            <div class="card" id="panggilanCard">
                <div class="card-header" style="background:var(--bg-input);">
                    Layanan Panggilan Aktif
                    <span class="badge badge-warning" style="margin-left:10px;"><?= count($panggilanAktif) ?> Transaksi</span>
                </div>
                <div class="table-container">
                    <table class="panggilan-table">
                        <thead>
                            <tr>
                                <th>Pelanggan & Info</th>
                                <th>Paket Layanan</th>
                                <th>Terapis</th>
                                <th>Status Waktu</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($panggilanAktif as $pg):
                                $pgIsWaiting = ($pg['status'] === 'menunggu_pembayaran');
                                $pgIsOT      = (!$pgIsWaiting && $pg['waktu_selesai'] && strtotime($pg['waktu_selesai']) <= time());
                                $pgPaketLabel = htmlspecialchars($pg['nama_paket']);
                                if (!empty($addedPkgMap[$pg['id']])) {
                                    foreach ($addedPkgMap[$pg['id']] as $apg) {
                                        $pgPaketLabel .= ' + ' . htmlspecialchars($apg['nama_paket']);
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($pg['nama_pelanggan']) ?></strong>
                                    <?php if(!empty($pg['no_hp_pelanggan'])): ?><br><small style="color:var(--text-muted); font-weight:600;"><?= htmlspecialchars($pg['no_hp_pelanggan']) ?></small><?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:600; font-size:12px;"><?= $pgPaketLabel ?></div>
                                    <small style="color:var(--text-muted);"><?= $pg['durasi_menit'] ?> menit</small>
                                </td>
                                <td><span style="font-weight:600;"><?= htmlspecialchars($pg['nama_terapis']) ?></span></td>
                                <td>
                                    <?php if($pgIsWaiting): ?>
                                        <span style="color:#8e44ad; font-weight:bold; font-size:11px; text-transform:uppercase;">Menunggu Bayar</span>
                                    <?php elseif($pgIsOT): ?>
                                        <span class="countdown" data-finish="<?= $pg['waktu_selesai'] ?>" data-trxid="<?= $pg['id'] ?>" data-tipe="panggilan" data-customer="<?= htmlspecialchars(addslashes($pg['nama_pelanggan'])) ?>" data-terapis="<?= htmlspecialchars(addslashes($pg['nama_terapis'])) ?>" data-paymentstatus="<?= $pg['payment_status'] ?>" style="color:var(--accent-red); font-weight:bold; font-family:monospace;"></span>
                                        <br><small style="color:var(--accent-red); font-weight:600;">Overtime</small>
                                    <?php else: ?>
                                        <span class="countdown" data-finish="<?= $pg['waktu_selesai'] ?>" data-trxid="<?= $pg['id'] ?>" data-tipe="panggilan" data-customer="<?= htmlspecialchars(addslashes($pg['nama_pelanggan'])) ?>" data-terapis="<?= htmlspecialchars(addslashes($pg['nama_terapis'])) ?>" data-paymentstatus="<?= $pg['payment_status'] ?>" style="color:var(--accent-green); font-weight:bold; font-family:monospace;"></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color:var(--text-dark);">Rp <?= number_format($pg['total_bayar'],0,',','.') ?></strong></td>
                                <td>
                                    <?php if($pgIsWaiting): ?>
                                        <span class="badge" style="background:rgba(155,89,182,0.1); color:#8e44ad; border:1px solid #8e44ad;">BAYAR</span>
                                    <?php elseif($pgIsOT): ?>
                                        <span class="badge" style="background:rgba(230,126,34,0.1); color:#e67e22; border:1px solid #e67e22;">OVERTIME</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">PROSES</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($pgIsWaiting): ?>
                                        <button class="panggilan-action-btn" style="background:#8e44ad; color:white;" onclick="goToPayment(<?= $pg['id'] ?>)">Proses Bayar</button>
                                        <button class="panggilan-action-btn" style="background:var(--accent-blue); color:white;" onclick="cetakStruk(<?= $pg['id'] ?>)">Struk</button>
                                    <?php else: ?>
                                        <button class="panggilan-action-btn" style="background:var(--accent-green); color:white;" onclick="showPanggilanFinish(<?= $pg['id'] ?>, '<?= htmlspecialchars(addslashes($pg['nama_pelanggan'])) ?>', '<?= htmlspecialchars(addslashes($pg['nama_terapis'])) ?>', '<?= $pg['waktu_selesai'] ?>', '<?= $pg['payment_status'] ?>')">Selesai</button>
                                        <button class="panggilan-action-btn" style="background:var(--bg-input); color:var(--text-dark); border:1px solid var(--border-color);" onclick="showAddPaketModal(<?= $pg['id'] ?>)">+ Waktu</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    Pilih Layanan Cepat
                    <small style="float:right; font-weight:normal; color:var(--text-muted);">Pilih paket lalu klik Bed kosong</small>
                </div>
                <div style="padding: 20px;">
                    <div class="pkg-tabs">
                        <button type="button" class="pkg-tab-btn active" id="dashTabPaket" onclick="switchDashPkgTab('paket')">Paket Utama</button>
                        <button type="button" class="pkg-tab-btn" id="dashTabNonPaket" onclick="switchDashPkgTab('non_paket')">Layanan Lepas</button>
                    </div>
                    
                    <div class="pkg-grid-wrapper show" id="dashGridPaket">
                        <?php $listPaketDash = array_filter($packages, fn($p) => ($p['is_paket'] ?? 1) == 1);
                        if (empty($listPaketDash)): ?>
                            <div style="text-align:center; padding:20px; color:var(--text-muted); font-size:13px;">Tidak ada paket.</div>
                        <?php else: ?>
                        <div class="paket-grid">
                            <?php foreach($listPaketDash as $p): ?>
                            <div class="pkg-card <?= $p['available'] ? '' : 'pkg-unavailable' ?>" <?= $p['available'] ? "onclick=\"selectPackage(this, {$p['id']})\"" : "onclick=\"Swal.fire('Stok Habis', 'Barang untuk paket ini tidak mencukupi di cabang.', 'error')\"" ?>>
                                <div>
                                    <strong style="font-size:13px; color:var(--text-dark); display:block; margin-bottom:4px;"><?= htmlspecialchars($p['nama_paket']) ?></strong>
                                    <small style="color:var(--text-muted); font-weight:600;"><?= $p['durasi_menit'] ?> Mnt</small>
                                </div>
                                <div>
                                    <strong style="color:var(--accent-green); font-size:14px;">Rp <?= number_format($p['harga']/1000,0) ?>k</strong>
                                    <?= !$p['available'] ? '<div style="font-size:10px; color:var(--accent-red); font-weight:bold; margin-top:5px;">Stok Habis</div>' : '' ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="pkg-grid-wrapper" id="dashGridNonPaket">
                        <?php $listNonPaketDash = array_filter($packages, fn($p) => ($p['is_paket'] ?? 1) == 0);
                        if (empty($listNonPaketDash)): ?>
                            <div style="text-align:center; padding:20px; color:var(--text-muted); font-size:13px;">Tidak ada layanan.</div>
                        <?php else: ?>
                        <div class="paket-grid">
                            <?php foreach($listNonPaketDash as $p): ?>
                            <div class="pkg-card <?= $p['available'] ? '' : 'pkg-unavailable' ?>" <?= $p['available'] ? "onclick=\"selectPackage(this, {$p['id']})\"" : "onclick=\"Swal.fire('Stok Habis', 'Barang untuk layanan ini tidak mencukupi di cabang.', 'error')\"" ?>>
                                <div>
                                    <strong style="font-size:13px; color:var(--text-dark); display:block; margin-bottom:4px;"><?= htmlspecialchars($p['nama_paket']) ?></strong>
                                    <small style="color:var(--text-muted); font-weight:600;"><?= $p['durasi_menit'] ?> Mnt</small>
                                </div>
                                <div>
                                    <strong style="color:var(--accent-green); font-size:14px;">Rp <?= number_format($p['harga']/1000,0) ?>k</strong>
                                    <?= !$p['available'] ? '<div style="font-size:10px; color:var(--accent-red); font-weight:bold; margin-top:5px;">Stok Habis</div>' : '' ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="dash-bottom-grid">
                <div class="card">
                    <div class="card-header" style="display:flex; justify-content:space-between;">
                        <span>Ketersediaan Terapis</span>
                        <a href="data_terapis_hadir.php" style="font-size:12px; color:var(--accent-blue); font-weight:600;">Kelola Terapis</a>
                    </div>
                    <div class="table-container" style="max-height: 350px; overflow-y: auto;">
                        <table>
                            <thead><tr><th>Nama Lengkap</th><th>Status Aktif</th><th>Estimasi</th></tr></thead>
                            <tbody>
                                <?php if(count($terapis) > 0): ?>
                                <?php foreach($terapis as $t):
                                    $isBusy     = ($t['current_trx_id'] != null);
                                    $isDipinjam = ($t['dipinjam_ke'] != null);
                                    $isIzinSakit = isset($izinMapDK[$t['id']]);
                                    $sudahAbsen = isset($t['waktu_absen']);
                                ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--text-dark); font-size:13px;"><?= htmlspecialchars($t['nama_lengkap']) ?></strong>
                                        <?php if($isDipinjam): ?><br><small style="color:var(--accent-red); font-weight:600;">Dipinjam ke: <?= htmlspecialchars($t['nama_cabang_peminjam']) ?></small><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($isIzinSakit): ?>
                                            <?php if($izinMapDK[$t['id']]['jenis'] === 'sakit'): ?><span class="badge badge-danger">SAKIT</span>
                                            <?php else: ?><span class="badge badge-warning">IZIN</span><?php endif; ?>
                                        <?php elseif(!$sudahAbsen): ?>
                                            <span class="badge" style="background:var(--bg-input); color:var(--text-muted); border: 1px solid var(--border-color);">BELUM ABSEN</span>
                                        <?php elseif($isDipinjam): ?>
                                            <span class="badge badge-warning">DIPINJAM</span>
                                        <?php elseif($isBusy): ?>
                                            <span class="badge badge-danger">SIBUK</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">STANDBY</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($isDipinjam && $t['waktu_kembali_estimasi']): ?>
                                            <span class="countdown" data-finish="<?= $t['waktu_kembali_estimasi'] ?>" style="font-family:monospace; font-weight:bold;">...</span>
                                        <?php elseif($isBusy && $t['waktu_selesai']): ?>
                                            <span class="countdown" data-finish="<?= $t['waktu_selesai'] ?>" style="font-family:monospace; font-weight:bold;">...</span>
                                        <?php else: ?><span style="color:var(--text-muted);">-</span><?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:20px;">Belum ada terapis</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header" style="display:flex; justify-content:space-between;">
                        <span>Kunjungan Terakhir</span>
                        <a href="data_customer_kasir.php" style="font-size:12px; color:var(--accent-blue); font-weight:600;">Lihat Semua</a>
                    </div>
                    <div style="max-height: 350px; overflow-y: auto;">
                        <?php if(count($customers) > 0): ?>
                            <?php foreach($customers as $c): ?>
                            <div class="tamu-row">
                                <div>
                                    <div class="tamu-name"><?= htmlspecialchars($c['nama_pelanggan']) ?></div>
                                    <div style="font-size:11px; color:var(--text-muted); font-weight:600;">
                                        <?= htmlspecialchars($c['no_hp_pelanggan'] ?: 'No. HP tidak tercatat') ?>
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <?php if($c['total_kunjungan'] >= 10): ?>
                                        <span class="badge" style="background:rgba(155,89,182,0.1); color:#8e44ad; border:1px solid #8e44ad;">LOYAL</span>
                                    <?php elseif($c['total_kunjungan'] >= 5): ?>
                                        <span class="badge badge-success">REGULAR</span>
                                    <?php else: ?>
                                        <span class="badge badge-primary">NEW</span>
                                    <?php endif; ?>
                                    <div style="font-size:10px; color:var(--text-muted); margin-top:5px; font-weight:bold;">
                                        <?= $c['total_kunjungan'] ?>x Datang
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align:center; color:var(--text-muted); padding:30px; font-weight:600;">Belum ada data pelanggan</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Performa 7 Hari Terakhir</span>
                    <div style="display:flex; gap:8px;">
                        <button class="chart-toggle-btn active green" id="btnOmset" onclick="switchChart('omset')">Grafik Omset</button>
                        <button class="chart-toggle-btn blue" id="btnTrx" onclick="switchChart('transaksi')">Grafik Transaksi</button>
                    </div>
                </div>
                <div style="padding: 20px;">
                    <div style="height: 200px;">
                        <canvas id="mainChart"></canvas>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div id="fabAction" class="fab-action" onclick="goToInput()">Lanjutkan Input Transaksi</div>

    <div id="infoOverlay" class="modal-overlay" onclick="if(event.target===this)closeInfo()">
        <div class="modal-box">
            <div class="modal-header" id="infoHeader" style="background: var(--bg-panel); color: var(--text-dark);">
                <div>
                    <h3 id="infoTitle" style="color: inherit;"></h3>
                    <small id="infoSubtitle" style="font-weight: 600;"></small>
                </div>
                <button class="modal-close" onclick="closeInfo()">×</button>
            </div>
            <div class="modal-body" id="infoBody"></div>
            <div class="modal-footer" id="infoFooter" style="display:none;"></div>
        </div>
    </div>

    <div id="alertOverlay" class="modal-overlay">
        <div class="modal-box" style="text-align:center; padding: 30px;">
            <h2 style="margin-bottom:10px; color: var(--text-dark);">Waktu Pijat Selesai</h2>
            <div id="alertDetail" style="color:var(--text-muted); font-size:14px; margin-bottom:20px;"></div>
            <div id="alertOvertimeInfo" style="display:none; background:rgba(245,184,0,0.1); padding:12px; border-radius:8px; margin-bottom:15px; font-size:13px; color:var(--accent-yellow2); border:1px solid var(--accent-yellow2);">
                <strong>Status Overtime:</strong> <span id="overtimeCounter" style="font-family:monospace; font-size:15px;">+00:00</span>
            </div>
            <div id="alertPaymentInfo" style="display:none; background:rgba(155,89,182,0.1); padding:12px; border-radius:8px; margin-bottom:15px; font-size:13px; color:#8e44ad; border:1px solid #8e44ad;">
                <strong>Pembayaran Belum Diselesaikan</strong>
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button class="btn btn-success" style="flex:1;" onclick="finishTransaction()">Selesaikan Pijatan</button>
                <button class="btn btn-danger" style="flex:1;" onclick="markBelumSiap()">Masih Proses (Tunda)</button>
            </div>
        </div>
    </div>

    <div id="addPaketOverlay" class="modal-overlay" onclick="if(event.target===this)closeAddPaket()">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Tambah Paket / Layanan</h3>
                <button class="modal-close" onclick="closeAddPaket()">×</button>
            </div>
            <div class="modal-body">
                <div style="background:var(--bg-input); border:1px solid var(--border-color); border-radius:8px; padding:12px; font-size:12px; color:var(--text-dark); margin-bottom:15px; font-weight:600;" id="addPaketInfo">
                    Memuat info transaksi...
                </div>
                <div class="pkg-tabs" style="margin-bottom: 15px;">
                    <button class="pkg-tab-btn active" id="addTabPaket" onclick="switchAddTab('paket')" style="flex:1; text-align:center;">Paket Utama</button>
                    <button class="pkg-tab-btn" id="addTabNonPaket" onclick="switchAddTab('non_paket')" style="flex:1; text-align:center;">Layanan Lepas</button>
                </div>
                <div id="addGridPaket" class="paket-grid" style="grid-template-columns: 1fr;"></div>
                <div id="addGridNonPaket" class="paket-grid" style="grid-template-columns: 1fr; display:none;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" id="addPaketConfirmBtn" onclick="confirmAddPaket()" disabled style="flex:2;">Tambahkan & Perpanjang</button>
                <button class="btn btn-secondary" onclick="closeAddPaket()" style="flex:1;">Batal</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <div class="notification-overlay" id="notificationOverlay"></div>
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <h3 style="margin:0; font-size:16px;">Notifikasi Cabang</h3>
            <button class="modal-close" id="closeNotification" style="color:var(--text-dark);">×</button>
        </div>
        <div id="notificationList" style="padding:15px;"></div>
    </div>

    <script>
    // Theme logic
    function toggleTheme() {
        const html = document.documentElement;
        const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        html.setAttribute('data-theme', next);
        localStorage.setItem('bugar-theme', next);
        if(mainChartInst) {
            mainChartInst.options.scales.x.ticks.color = next === 'dark' ? '#aaa' : '#666';
            mainChartInst.options.scales.y.ticks.color = next === 'dark' ? '#aaa' : '#666';
            mainChartInst.update();
        }
    }
    (function() {
        const saved = localStorage.getItem('bugar-theme');
        if (saved) document.documentElement.setAttribute('data-theme', saved);
    })();

    // Sidebar & User Dropdown Logic
    function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    
    // Deteksi apakah ini tampilan mobile (lebar layar <= 992px sesuai CSS Anda)
    if (window.innerWidth <= 992) {
        // Mode Mobile: Toggle class 'active' untuk memunculkan sidebar dari kiri
        sb.classList.toggle('active');
    } else {
        // Mode Desktop: Toggle class 'collapsed' untuk mengecilkan/membesarkan sidebar
        sb.classList.toggle('collapsed');
        
        const btnText = document.querySelector('.sidebar-toggle-btn .menu-text');
        const btnAbbr = document.querySelector('.sidebar-toggle-btn .menu-abbr');
        
        if (sb.classList.contains('collapsed')) {
            btnText.style.display = 'none';
            btnAbbr.style.display = 'inline';
        } else {
            btnText.style.display = 'inline';
            btnAbbr.style.display = 'none';
        }
    }
}

    function toggleUserDropdown(e) {
        e.stopPropagation();
        document.getElementById('userDropdown').classList.toggle('show');
    }
    
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown && dropdown.classList.contains('show') && !e.target.closest('.user-dropdown-wrap')) {
            dropdown.classList.remove('show');
        }
    });

    // ===== DATA GRAFIK =====
    const chartLabels  = <?= json_encode($labels) ?>;
    const chartOmset   = <?= json_encode($dataOmset) ?>;
    const chartTrx     = <?= json_encode($dataTrx) ?>;

    const allPackages = <?= json_encode(array_values($packages)) ?>;
    const addedPackagesMap = <?= json_encode($addedPkgMap, JSON_FORCE_OBJECT) ?>;
    let currentChart   = 'omset';
    let mainChartInst  = null;

    function buildChart(type) {
        const ctx = document.getElementById('mainChart').getContext('2d');
        if (mainChartInst) mainChartInst.destroy();
        const isOmset = (type === 'omset');
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const tColor = isDark ? '#aaa' : '#666';

        mainChartInst = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: isOmset ? 'Omset (Rp)' : 'Jumlah Transaksi',
                    data: isOmset ? chartOmset : chartTrx,
                    borderColor:     isOmset ? '#27ae60' : '#3498db',
                    backgroundColor: isOmset ? 'rgba(39,174,96,0.1)' : 'rgba(52,152,219,0.1)',
                    borderWidth: 3, fill: true, tension: 0.4,
                    pointBackgroundColor: isOmset ? '#27ae60' : '#3498db',
                    pointRadius: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: tColor }, grid: { color: isDark ? '#333' : '#eee' } },
                    y: { beginAtZero: true, ticks: { color: tColor, callback: function(v) { return isOmset ? 'Rp ' + (v/1000).toLocaleString('id-ID') + 'k' : v; } }, grid: { color: isDark ? '#333' : '#eee' } }
                }
            }
        });
    }

    function switchChart(type) {
        currentChart = type;
        document.querySelectorAll('.chart-toggle-btn').forEach(b => b.classList.remove('active','green','blue'));
        if (type === 'omset') document.getElementById('btnOmset').classList.add('active','green');
        else document.getElementById('btnTrx').classList.add('active','blue');
        buildChart(type);
    }
    buildChart('omset');

    // ===== CLOCK & DURASI =====
    setInterval(() => { document.getElementById('realtimeClock').innerText = new Date().toLocaleTimeString('id-ID'); }, 1000);
    const elDurasi = document.getElementById('durasiShift');
    if (elDurasi) {
        setInterval(() => {
            const diff  = new Date() - new Date(elDurasi.dataset.mulai);
            if (diff > 0) {
                const h = Math.floor(diff/3600000), m = Math.floor((diff%3600000)/60000), s = Math.floor((diff%60000)/1000);
                elDurasi.innerText = String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
            }
        }, 1000);
    }

    // ===== COUNTDOWN BED =====
    let currentAlertTrxId = null, currentAlertPaymentStatus = null;
    let alertedTransactions = new Set(), dismissedAlerts = new Set();
    let infoTimerInterval = null;
    const PANGGILAN_OT_THRESHOLD = 10 * 60 * 1000;

    function updateCountdowns() {
        document.querySelectorAll('.countdown').forEach(el => {
            const finish = new Date(el.dataset.finish);
            if (isNaN(finish.getTime()) || !el.dataset.finish) return;
            const diff  = finish - new Date();
            const trxId = el.getAttribute('data-trxid');
            const tipe  = el.getAttribute('data-tipe') || 'bed';

            if (diff <= 0) {
                const ov = Math.abs(diff), m  = Math.floor(ov/60000), s = Math.floor((ov%60000)/1000);
                el.innerHTML = 'OT +'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');

                if (tipe === 'panggilan') {
                    if (trxId && trxId !== '' && ov >= PANGGILAN_OT_THRESHOLD && !alertedTransactions.has('panggilan_'+trxId) && !dismissedAlerts.has('panggilan_'+trxId)) {
                        alertedTransactions.add('panggilan_'+trxId);
                        showPanggilanKembaliAlert(parseInt(trxId,10), el.getAttribute('data-customer')||'-', el.getAttribute('data-terapis')||'-', el.dataset.finish, el.getAttribute('data-paymentstatus')||'unpaid');
                    }
                } else {
                    if (trxId && trxId !== '' && !alertedTransactions.has(trxId) && !dismissedAlerts.has(trxId)) {
                        alertedTransactions.add(trxId);
                        const box = el.closest('.bed-box');
                        if (box) showFinishAlert(trxId, box.getAttribute('data-customer')||'-', box.getAttribute('data-terapis')||'-', box.getAttribute('data-bed')||'-', el.dataset.finish, box.getAttribute('data-paymentstatus')||'unpaid');
                    }
                }
                if (currentAlertTrxId == trxId) document.getElementById('overtimeCounter').innerText = '+'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
            } else {
                el.innerText = Math.floor(diff/60000) + "m " + Math.floor((diff%60000)/1000) + "s";
            }
        });
    }
    setInterval(updateCountdowns, 1000); updateCountdowns();

    // ===== BED INFO POPUP =====
    function showBedInfo(el) {
        const d = el.dataset, rawTrxId = el.getAttribute('data-trxid'), isWaitingPay = ((el.getAttribute('data-trxstatus')||'') === 'menunggu_pembayaran');
        if (!rawTrxId || rawTrxId === '' || rawTrxId === 'null') { showToast('Data transaksi tidak lengkap.', true); return; }

        const isOT = (!isWaitingPay && new Date() >= new Date(d.finish));
        document.getElementById('infoHeader').style.borderBottom = isWaitingPay ? '3px solid #8e44ad' : (isOT ? '3px solid var(--accent-yellow2)' : '3px solid var(--accent-red)');

        document.getElementById('infoTitle').innerHTML = 'Bed ' + d.bed + ' - ' + d.tipe;
        document.getElementById('infoSubtitle').innerHTML = isWaitingPay ? 'Menunggu Pembayaran' : (isOT ? 'Status: Overtime' : 'Sedang Berlangsung');
        document.getElementById('infoSubtitle').style.color = isWaitingPay ? '#8e44ad' : (isOT ? 'var(--accent-yellow2)' : 'var(--accent-red)');

        let h = '<div class="info-row"><span class="label">Pelanggan</span><span class="value">'+(d.customer||'-')+'</span></div>';
        h += '<div class="info-row"><span class="label">Terapis</span><span class="value">'+(d.terapis||'-')+'</span></div>';
        
        const addedPkgs = (addedPackagesMap[parseInt(rawTrxId, 10)] || []);
        if (addedPkgs.length > 0) {
            let pR = '<div style="color:var(--text-dark);">' + (d.paket||'-') + '</div>';
            addedPkgs.forEach(ap => { pR += '<div style="color:var(--text-muted); font-size:12px; margin-top:3px;">+ ' + ap.nama_paket + ' (' + parseInt(ap.harga).toLocaleString('id-ID') + ')</div>'; });
            h += '<div class="info-row" style="align-items:flex-start;"><span class="label">Paket Lengkap</span><span class="value">' + pR + '</span></div>';
        } else {
            h += '<div class="info-row"><span class="label">Paket Layanan</span><span class="value">' + (d.paket||'-') + '</span></div>';
        }
        
        h += '<div class="info-row"><span class="label">Total Biaya</span><span class="value">Rp '+parseInt(d.bayar||0).toLocaleString('id-ID')+'</span></div>';
        h += '<div class="info-row"><span class="label">Waktu Masuk</span><span class="value">'+fmtTime(d.masuk)+'</span></div>';

        if (isWaitingPay) h += '<div class="info-timer waiting-pay">MENUNGGU PEMBAYARAN</div>';
        else {
            h += '<div class="info-row"><span class="label">Estimasi Selesai</span><span class="value">'+(d.finish?fmtTime(d.finish):'-')+'</span></div>';
            h += '<div class="info-timer running" id="bedInfoTimer">...</div>';
        }
        document.getElementById('infoBody').innerHTML = h;

        let f = '';
        if (isWaitingPay) {
            f += '<button class="btn btn-success" onclick="goToPayment('+parseInt(rawTrxId)+')" style="flex:2;">Proses Pembayaran</button>';
            f += '<button class="btn btn-primary" onclick="cetakStruk('+parseInt(rawTrxId)+')" style="flex:1;">Cetak Struk</button>';
        } else {
            window._pendingFinish = { id: parseInt(rawTrxId,10), customer: d.customer||'-', terapis: d.terapis||'-', bed: d.bed||'-', finish: d.finish||'', paymentStatus: d.paymentstatus||'unpaid' };
            f += '<button class="btn btn-secondary" onclick="showAddPaketModal('+parseInt(rawTrxId)+')">+ Waktu</button>';
            f += '<button class="btn btn-primary" onclick="cetakStruk('+parseInt(rawTrxId)+')">Cetak Struk</button>';
            f += '<button class="btn btn-success" onclick="closeInfo();showFinishAlert(window._pendingFinish.id,window._pendingFinish.customer,window._pendingFinish.terapis,window._pendingFinish.bed,window._pendingFinish.finish,window._pendingFinish.paymentStatus)">Selesaikan</button>';
        }
        document.getElementById('infoFooter').innerHTML = f;
        document.getElementById('infoFooter').style.display = 'flex';
        document.getElementById('infoOverlay').classList.add('show');

        if (!isWaitingPay) {
            const fT = new Date(d.finish);
            if (infoTimerInterval) clearInterval(infoTimerInterval);
            infoTimerInterval = setInterval(() => {
                const t = document.getElementById('bedInfoTimer');
                if (!t) { clearInterval(infoTimerInterval); return; }
                const df = fT - new Date();
                if (df <= 0) {
                    t.className = 'info-timer overtime';
                    t.innerHTML = 'OVERTIME +'+String(Math.floor(Math.abs(df)/60000)).padStart(2,'0')+':'+String(Math.floor((Math.abs(df)%60000)/1000)).padStart(2,'0');
                } else {
                    t.className = 'info-timer running';
                    t.innerHTML = 'SISA '+String(Math.floor(df/60000)).padStart(2,'0')+':'+String(Math.floor((df%60000)/1000)).padStart(2,'0');
                }
            }, 1000);
        }
    }

    function closeInfo() { document.getElementById('infoOverlay').classList.remove('show'); if(infoTimerInterval) clearInterval(infoTimerInterval); }
    function goToPayment(id) { closeInfo(); window.location.href='proses_pembayaran.php?transaction_id='+id; }
    function cetakStruk(id) { window.open('cetak_struk.php?transaction_id='+id, '_blank'); }
    function fmtTime(s) { const d=new Date(s); return String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0'); }

    // ===== FINISH & ALERTS =====
    function showPanggilanFinish(trxId, customer, terapis, finishTime, paymentStatus) {
        if (!trxId || isNaN(parseInt(trxId,10))) return;
        currentAlertTrxId = parseInt(trxId, 10); currentAlertPaymentStatus = paymentStatus || 'unpaid';
        const isOT = (new Date() >= new Date(finishTime));
        document.getElementById('alertDetail').innerHTML = '<strong>Layanan Panggilan</strong><br>Pelanggan: '+customer+'<br>Terapis: '+terapis;
        document.getElementById('alertOvertimeInfo').style.display = isOT ? 'block' : 'none';
        if (isOT) document.getElementById('overtimeCounter').innerText = '+'+String(Math.floor((new Date()-new Date(finishTime))/60000)).padStart(2,'0')+':'+String(Math.floor(((new Date()-new Date(finishTime))%60000)/1000)).padStart(2,'0');
        document.getElementById('alertPaymentInfo').style.display = (paymentStatus==='unpaid')?'block':'none';
        document.getElementById('alertOverlay').classList.add('show');
    }
    function showPanggilanKembaliAlert(trxId, customer, terapis, finishTime, paymentStatus) {
        if (!trxId) return;
        currentAlertTrxId = trxId; currentAlertPaymentStatus = paymentStatus || 'unpaid';
        const ov = Math.abs(new Date() - new Date(finishTime)), ovM = Math.floor(ov/60000), ovS = Math.floor((ov%60000)/1000);
        Swal.fire({
            title: 'Konfirmasi Panggilan',
            html: 'Pelanggan: ' + customer + '<br>Terapis: ' + terapis + '<br><br>Waktu panggilan overtime: +' + String(ovM).padStart(2,'0') + ':' + String(ovS).padStart(2,'0') + '<br>Apakah terapis sudah kembali?',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Sudah Kembali', cancelButtonText: 'Tunda (5 Mnt)', allowOutsideClick: false
        }).then(r => {
            if (r.isConfirmed) finishTransaction();
            else {
                dismissedAlerts.add('panggilan_'+trxId);
                setTimeout(() => { dismissedAlerts.delete('panggilan_'+trxId); alertedTransactions.delete('panggilan_'+trxId); }, 300000);
                currentAlertTrxId = null; showToast('Mengingatkan kembali 5 menit lagi.');
            }
        });
    }
    function showFinishAlert(trxId, customer, terapis, bed, finishTime, paymentStatus) {
        if (!trxId || isNaN(parseInt(trxId,10))) return;
        currentAlertTrxId = parseInt(trxId,10); currentAlertPaymentStatus = paymentStatus||'unpaid';
        const isOT = (new Date() >= new Date(finishTime));
        document.getElementById('alertDetail').innerHTML = 'Bed: '+bed+'<br>Pelanggan: '+customer+'<br>Terapis: '+terapis;
        document.getElementById('alertOvertimeInfo').style.display = isOT ? 'block' : 'none';
        if (isOT) document.getElementById('overtimeCounter').innerText = '+'+String(Math.floor((new Date()-new Date(finishTime))/60000)).padStart(2,'0')+':'+String(Math.floor(((new Date()-new Date(finishTime))%60000)/1000)).padStart(2,'0');
        document.getElementById('alertPaymentInfo').style.display = (paymentStatus==='unpaid')?'block':'none';
        document.getElementById('alertOverlay').classList.add('show');
    }
    function closeAlert(){document.getElementById('alertOverlay').classList.remove('show');currentAlertTrxId=null;}
    function showToast(m, err=false){const t=document.getElementById('toast');t.innerHTML=m;t.className='toast'+(err?' error':'');t.style.display='block';setTimeout(()=>t.style.display='none',4000);}

    function finishTransaction() {
        if(!currentAlertTrxId){closeAlert();return;}
        const cid = parseInt(currentAlertTrxId,10);
        fetch('ajax_finish_transaction.php?action=selesai&transaction_id='+cid, { method: 'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=selesai&transaction_id='+cid})
        .then(r=>r.json()).then(d => {
            if(d.success){
                if(d.waiting_payment){
                    closeAlert();
                    Swal.fire({title:'Pijatan Selesai',text:'Pembayaran belum diselesaikan. Lanjut ke proses pembayaran?',icon:'info',showCancelButton:true,confirmButtonText:'Proses Pembayaran',cancelButtonText:'Nanti Saja'})
                    .then(r=>{ if(r.isConfirmed) window.location.href='proses_pembayaran.php?transaction_id='+(d.transaction_id||cid); else location.reload(); });
                } else { showToast(d.message); closeAlert(); setTimeout(()=>location.reload(),800); }
            } else { showToast(d.error||'Gagal',true); }
        }).catch(()=>showToast('Server error',true));
    }
    function markBelumSiap() {
        if(!currentAlertTrxId){closeAlert();return;}
        dismissedAlerts.add(String(currentAlertTrxId));
        fetch('ajax_finish_transaction.php?action=belum_siap&transaction_id='+currentAlertTrxId, { method: 'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=belum_siap&transaction_id='+currentAlertTrxId})
        .then(r=>r.json()).then(d=>{if(d.success)showToast('Status overtime berjalan.'); closeAlert();});
    }

    // ===== SELEKSI BED & PAKET CEPAT =====
    let selBed=null, selPkg=null;
    function selectBed(el,id){document.querySelectorAll('.bed-box').forEach(e=>e.classList.remove('selected'));el.classList.add('selected');selBed=id;checkFab();}
    function selectPackage(el,id){document.querySelectorAll('.pkg-card').forEach(e=>e.classList.remove('selected'));el.classList.add('selected');selPkg=id;checkFab();}
    function checkFab(){document.getElementById('fabAction').style.display=(selBed||selPkg)?'block':'none';}
    function goToInput(){let u='input_transaksi.php?';if(selBed)u+='bed_id='+selBed+'&';if(selPkg)u+='package_id='+selPkg;window.location.href=u;}
    function switchDashPkgTab(tab) {
        document.getElementById('dashTabPaket').classList.toggle('active', tab === 'paket');
        document.getElementById('dashTabNonPaket').classList.toggle('active', tab === 'non_paket');
        document.getElementById('dashGridPaket').classList.toggle('show', tab === 'paket');
        document.getElementById('dashGridNonPaket').classList.toggle('show', tab === 'non_paket');
    }

    // ===== TAMBAH PAKET MODAL =====
    let _addPaketTrxId=null, _addPaketSelectedId=null, _addPaketSelectedDurasi=0, _addPaketSelectedHarga=0, _addPaketTab='paket';
    function showAddPaketModal(trxId) {
        _addPaketTrxId = trxId; _addPaketSelectedId = null; document.getElementById('addPaketConfirmBtn').disabled = true;
        document.getElementById('addPaketInfo').innerHTML = 'Pilih paket tambahan untuk Transaksi #'+trxId;
        switchAddTab('paket'); document.getElementById('addPaketOverlay').classList.add('show'); closeInfo();
    }
    function closeAddPaket() { document.getElementById('addPaketOverlay').classList.remove('show'); }
    function switchAddTab(tab) {
        _addPaketTab = tab; _addPaketSelectedId = null; document.getElementById('addPaketConfirmBtn').disabled = true;
        document.getElementById('addTabPaket').classList.toggle('active', tab === 'paket');
        document.getElementById('addTabNonPaket').classList.toggle('active', tab === 'non_paket');
        renderAddPaketGrid(tab === 'paket' ? 'addGridPaket' : 'addGridNonPaket', tab === 'paket' ? 1 : 0);
        document.getElementById('addGridPaket').style.display = tab === 'paket' ? 'grid' : 'none';
        document.getElementById('addGridNonPaket').style.display = tab === 'non_paket' ? 'grid' : 'none';
    }
    function renderAddPaketGrid(gridId, isPaketVal) {
        const grid = document.getElementById(gridId);
        const filtered = allPackages.filter(p => parseInt(p.is_paket) === isPaketVal);
        if(!filtered.length) { grid.innerHTML = '<p style="padding:20px;color:#999;text-align:center;">Tidak ada layanan.</p>'; return; }
        let h = '';
        filtered.forEach(p => {
            const availClass = p.available ? '' : 'pkg-unavailable';
            const clickAttr = p.available ? `onclick="selectAddPaket(this, ${p.id}, ${p.durasi_menit}, ${p.harga})"` : `onclick="Swal.fire('Stok Habis', 'Barang untuk paket ini tidak mencukupi di cabang.', 'error')"`;
            const availWarn = p.available ? '' : '<div style="font-size:10px; color:var(--accent-red); font-weight:bold; margin-top:5px;">Stok Habis</div>';
            h += `<div class="pkg-card ${availClass}" ${clickAttr}>
                    <div><strong style="font-size:13px; color:var(--text-dark);">${p.nama_paket}</strong><br><small style="color:var(--text-muted);">${p.durasi_menit} mnt</small></div>
                    <div><strong style="color:var(--accent-green); font-size:14px;">Rp ${(p.harga/1000).toLocaleString('id-ID')}k</strong>${availWarn}</div>
                  </div>`;
        });
        grid.innerHTML = h;
    }
    function selectAddPaket(el, id, dur, harga) {
        document.querySelectorAll('#addPaketOverlay .pkg-card').forEach(c=>c.classList.remove('selected'));
        el.classList.add('selected'); _addPaketSelectedId=id; _addPaketSelectedDurasi=dur; _addPaketSelectedHarga=harga;
        const btn = document.getElementById('addPaketConfirmBtn'); btn.disabled=false; btn.textContent = 'Tambahkan +'+dur+'mnt (Rp '+(harga).toLocaleString('id-ID')+')';
    }
    function confirmAddPaket() {
        if(!_addPaketTrxId || !_addPaketSelectedId) return;
        const tId = _addPaketTrxId, pId = _addPaketSelectedId;
        closeAddPaket();
        Swal.fire({
            title: 'Konfirmasi', text: 'Perpanjang waktu '+_addPaketSelectedDurasi+' menit?', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Tambahkan'
        }).then(r => {
            if(r.isConfirmed) {
                fetch('ajax_tambah_paket.php', { method: 'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'transaction_id='+tId+'&package_id='+pId })
                .then(res=>res.json()).then(d => { if(d.success) Swal.fire('Berhasil',d.message,'success').then(()=>location.reload()); else Swal.fire('Gagal',d.error,'error'); });
            }
        });
    }

    // ===== NOTIFIKASI POLLING =====
    function loadNotifications() {
        fetch('ajax_get_loan_notifications.php?_t='+Date.now()).then(r=>r.json()).then(d=>{
            if(d.success) {
                const badge = document.getElementById('notificationBell');
                if(d.count > 0) {
                    if(!document.getElementById('notifBadgeReal')) badge.innerHTML += `<span id="notifBadgeReal" class="notification-badge">${d.count}</span>`;
                    else document.getElementById('notifBadgeReal').innerText = d.count;
                } else {
                    const b = document.getElementById('notifBadgeReal'); if(b) b.remove();
                }
                renderNotificationPanel(d.dipinjam||[], d.system_notifs||[]);
            }
        }).catch(()=>{});
    }
    function renderNotificationPanel(dipinjam, sys) {
        const list = document.getElementById('notificationList');
        if(dipinjam.length===0 && sys.length===0) { list.innerHTML='<p style="text-align:center; padding:20px; color:var(--text-muted);">Tidak ada notifikasi</p>'; return; }
        let h = '';
        if(dipinjam.length>0) {
            h += '<div style="font-size:12px; font-weight:bold; color:var(--text-muted); margin-bottom:10px; text-transform:uppercase;">Terapis Dipinjam</div>';
            dipinjam.forEach(n => {
                h += `<div style="background:var(--bg-input); padding:15px; border-radius:8px; border-left:4px solid var(--accent-yellow2); margin-bottom:10px;">
                        <strong style="color:var(--text-dark); font-size:13px;">${n.nama_terapis}</strong> dipinjam ke <strong>${n.cabang_peminjam}</strong><br>
                        <small style="color:var(--text-muted);">Pelanggan: ${n.nama_pelanggan} | Bed: ${n.nomor_bed||'-'}</small>
                      </div>`;
            });
        }
        if(sys.length>0) {
            h += '<div style="font-size:12px; font-weight:bold; color:var(--text-muted); margin:15px 0 10px; text-transform:uppercase;">Pembaruan Sistem</div>';
            sys.forEach(n => {
                h += `<div style="background:var(--bg-input); padding:15px; border-radius:8px; border-left:4px solid var(--accent-blue); margin-bottom:10px;">
                        <strong style="color:var(--text-dark); font-size:13px;">${n.judul}</strong><br>
                        <small style="color:var(--text-muted);">${n.pesan}</small>
                      </div>`;
            });
        }
        list.innerHTML = h;
    }
    document.getElementById('notificationBell').addEventListener('click', () => {
        document.getElementById('notificationPanel').classList.add('active');
        document.getElementById('notificationOverlay').classList.add('active');
    });
    document.getElementById('closeNotification').addEventListener('click', () => {
        document.getElementById('notificationPanel').classList.remove('active');
        document.getElementById('notificationOverlay').classList.remove('active');
    });
    document.getElementById('notificationOverlay').addEventListener('click', () => {
        document.getElementById('notificationPanel').classList.remove('active');
        document.getElementById('notificationOverlay').classList.remove('active');
    });
    setInterval(loadNotifications, 5000); loadNotifications();
    </script>
</body>
</html>