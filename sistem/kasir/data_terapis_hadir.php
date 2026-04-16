<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) { 
    header("Location: pilih_cabang.php"); exit; 
}

$kasir_id = $_SESSION['user_id'];
$branch_id = $_SESSION['active_branch'];
$nama_kasir = $_SESSION['nama'];
$nama_cabang = $pdo->query("SELECT nama_cabang FROM branches WHERE id = $branch_id")->fetchColumn();

// Foto Profil Kasir (Masih dipanggil untuk di Sidebar kiri)
$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$dbFoto = $stmtProfil->fetchColumn();
$foto_profil = (!empty($dbFoto) && file_exists("../uploads/profil/" . $dbFoto)) ? "../uploads/profil/" . $dbFoto : "../assets/default_user.png";

// --- LOGIC PENENTUAN HARI BISNIS ---
$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulaiBisnis = $setting['jam_mulai_hari'] ?? '08:00:00';

$sekarang = new DateTime();
$jamSekarang = $sekarang->format('H:i:s');

if ($jamSekarang < $jamMulaiBisnis) {
    $tglBisnis = date('Y-m-d', strtotime('-1 day'));
} else {
    $tglBisnis = date('Y-m-d');
}

$start_periode = "$tglBisnis $jamMulaiBisnis";
$end_periode   = date('Y-m-d H:i:s', strtotime("$start_periode +1 day"));

$pesan = ""; $tipe_pesan = "";

// =================================================================================
// QUERY REALTIME TERAPIS (seluruh hari bisnis)
// =================================================================================
$sqlTerapis = "SELECT u.id, u.nama_lengkap, u.home_branch_id,
               (SELECT MAX(waktu_selesai) FROM transactions t WHERE t.terapis_id = u.id AND t.status = 'proses') as jam_bebas,
               (SELECT COUNT(*) FROM transactions t WHERE t.terapis_id = u.id AND t.branch_id = ? AND t.created_at >= ? AND t.created_at < ? AND t.status != 'batal') as trx_hari_ini_lokal,
               (SELECT COUNT(*) FROM transactions t WHERE t.terapis_id = u.id AND t.branch_id != ? AND t.created_at >= ? AND t.created_at < ? AND t.status != 'batal') as trx_hari_ini_pinjam,
               (SELECT COUNT(*) FROM transactions t WHERE t.terapis_id = u.id AND t.created_at >= ? AND t.created_at < ? AND t.status != 'batal') as trx_hari_ini_total,
               (SELECT COUNT(*) FROM transactions t WHERE t.terapis_id = u.id AND t.branch_id = ? AND t.created_at >= ? AND t.created_at < ? AND t.status != 'batal') as trx_total_cabang,
               (SELECT COUNT(*) FROM transactions t WHERE t.terapis_id = u.id AND t.branch_id = ? AND t.status != 'batal') as trx_alltime_cabang,
               (SELECT COALESCE(SUM(t.omset_terapis), 0) FROM transactions t WHERE t.terapis_id = u.id AND t.branch_id = ? AND t.created_at >= ? AND t.created_at < ? AND t.status != 'batal') as omset_lokal,
               (SELECT COALESCE(SUM(t.omset_terapis), 0) FROM transactions t WHERE t.terapis_id = u.id AND t.branch_id != ? AND t.created_at >= ? AND t.created_at < ? AND t.status != 'batal') as omset_pinjam,
               (SELECT COALESCE(SUM(t.omset_terapis), 0) FROM transactions t WHERE t.terapis_id = u.id AND t.created_at >= ? AND t.created_at < ? AND t.status != 'batal') as omset_total,
               (SELECT GROUP_CONCAT(DISTINCT br.nama_cabang SEPARATOR ', ') FROM transactions t JOIN branches br ON t.branch_id = br.id WHERE t.terapis_id = u.id AND t.branch_id != ? AND t.created_at >= ? AND t.created_at < ? AND t.status != 'batal') as nama_cabang_kerja_lain,
               (SELECT t.id FROM transactions t WHERE t.terapis_id = u.id AND t.status = 'proses' LIMIT 1) as current_transaction_id,
               (SELECT t.bed_id FROM transactions t WHERE t.terapis_id = u.id AND t.status = 'proses' LIMIT 1) as current_bed_id,
               (SELECT t.nama_pelanggan FROM transactions t WHERE t.terapis_id = u.id AND t.status = 'proses' LIMIT 1) as customer_name,
               (SELECT b.nomor_bed FROM transactions t JOIN beds b ON t.bed_id = b.id WHERE t.terapis_id = u.id AND t.status = 'proses' LIMIT 1) as bed_number,
               (SELECT tl.to_branch_id FROM terapis_loans tl JOIN transactions t ON tl.transaction_id = t.id WHERE tl.terapis_id = u.id AND tl.from_branch_id = ? AND tl.status = 'active' AND t.status = 'proses' LIMIT 1) as dipinjam_ke_cabang,
               (SELECT br.nama_cabang FROM terapis_loans tl JOIN branches br ON tl.to_branch_id = br.id JOIN transactions t ON tl.transaction_id = t.id WHERE tl.terapis_id = u.id AND tl.from_branch_id = ? AND tl.status = 'active' AND t.status = 'proses' LIMIT 1) as nama_cabang_peminjam,
               (SELECT t.waktu_selesai FROM terapis_loans tl JOIN transactions t ON tl.transaction_id = t.id WHERE tl.terapis_id = u.id AND tl.from_branch_id = ? AND tl.status = 'active' AND t.status = 'proses' LIMIT 1) as waktu_kembali_estimasi
               FROM users u
               WHERE u.role = 'terapis' AND u.home_branch_id = ?
               ORDER BY trx_total_cabang DESC, u.nama_lengkap ASC";

$stmtTerapis = $pdo->prepare($sqlTerapis);
$stmtTerapis->execute([
    $branch_id, $start_periode, $end_periode,
    $branch_id, $start_periode, $end_periode,
    $start_periode, $end_periode,
    $branch_id, $start_periode, $end_periode,
    $branch_id,
    $branch_id, $start_periode, $end_periode,
    $branch_id, $start_periode, $end_periode,
    $start_periode, $end_periode,
    $branch_id, $start_periode, $end_periode,
    $branch_id,
    $branch_id,
    $branch_id,
    $branch_id
]);
$terapis = $stmtTerapis->fetchAll();

// Cek Absensi Hari Ini
$adaTabelAbsen = $pdo->query("SHOW TABLES LIKE 'terapis_attendance'")->rowCount() > 0;
$absenHariIni = [];
if ($adaTabelAbsen) {
    $stmtAbsen = $pdo->prepare("SELECT terapis_id FROM terapis_attendance WHERE branch_id = ? AND tanggal = ?");
    $stmtAbsen->execute([$branch_id, $tglBisnis]);
    foreach ($stmtAbsen->fetchAll() as $ab) {
        $absenHariIni[$ab['terapis_id']] = true;
    }
}

// Izin/Sakit hari ini
$adaTabelIzin = $pdo->query("SHOW TABLES LIKE 'terapis_izin'")->rowCount() > 0;
$izinMapDTH = [];
if ($adaTabelIzin) {
    $stmtIzinDTH = $pdo->prepare("SELECT terapis_id, jenis, status FROM terapis_izin WHERE branch_id = ? AND tanggal = ? AND status IN ('disetujui','pending')");
    $stmtIzinDTH->execute([$branch_id, $tglBisnis]);
    foreach ($stmtIzinDTH->fetchAll() as $iz) {
        if (!isset($izinMapDTH[$iz['terapis_id']])) $izinMapDTH[$iz['terapis_id']] = $iz;
    }
}

// =================================================================================
// QUERY TERAPIS DIPINJAM DARI CABANG LAIN (SEDANG KERJA DI SINI)
// =================================================================================
$sqlTeranisPinjaman = "SELECT u.id, u.nama_lengkap, u.home_branch_id,
               br_asal.nama_cabang as cabang_asal,
               t.waktu_selesai as jam_bebas,
               t.id as current_transaction_id,
               t.bed_id as current_bed_id,
               b.nomor_bed as bed_number,
               t.nama_pelanggan as customer_name
               FROM terapis_loans tl
               JOIN users u ON tl.terapis_id = u.id
               JOIN branches br_asal ON u.home_branch_id = br_asal.id
               JOIN transactions t ON tl.transaction_id = t.id
               LEFT JOIN beds b ON t.bed_id = b.id
               WHERE tl.to_branch_id = ?
               AND tl.status = 'active'
               AND t.status = 'proses'
               ORDER BY u.nama_lengkap ASC";
$stmtTP = $pdo->prepare($sqlTeranisPinjaman);
$stmtTP->execute([$branch_id]);
$terapisPinjaman = $stmtTP->fetchAll();

// =================================================================================
// QUERY DETAIL TRANSAKSI HARI INI PER TERAPIS (lokal cabang ini)
// =================================================================================
$sqlDetailTrx = "SELECT t.terapis_id, t.created_at, t.nama_pelanggan, p.nama_paket, t.total_bayar,
                        uk.nama_lengkap as nama_kasir,
                        br.nama_cabang,
                        t.branch_id
                 FROM transactions t
                 JOIN packages p ON t.package_id = p.id
                 JOIN users uk ON t.kasir_id = uk.id
                 JOIN branches br ON t.branch_id = br.id
                 WHERE t.branch_id = ?
                 AND t.created_at >= ? AND t.created_at < ?
                 AND t.status != 'batal'
                 ORDER BY t.created_at ASC";
$stmtDT = $pdo->prepare($sqlDetailTrx);
$stmtDT->execute([$branch_id, $start_periode, $end_periode]);
$allDetailTrx = $stmtDT->fetchAll();

$detailTrxMap = [];
foreach ($allDetailTrx as $dt) {
    $detailTrxMap[$dt['terapis_id']][] = $dt;
}

// =================================================================================
// QUERY DETAIL TRANSAKSI PINJAM (terapis cabang ini kerja di cabang lain)
// =================================================================================
$sqlDetailPinjam = "SELECT t.terapis_id, t.created_at, t.nama_pelanggan, p.nama_paket, t.total_bayar,
                           uk.nama_lengkap as nama_kasir,
                           br.nama_cabang,
                           t.branch_id
                    FROM transactions t
                    JOIN packages p ON t.package_id = p.id
                    JOIN users uk ON t.kasir_id = uk.id
                    JOIN branches br ON t.branch_id = br.id
                    JOIN terapis_loans tl ON tl.transaction_id = t.id
                    JOIN users ut ON tl.terapis_id = ut.id
                    WHERE tl.from_branch_id = ?
                    AND t.branch_id != ?
                    AND t.created_at >= ? AND t.created_at < ?
                    AND t.status != 'batal'
                    ORDER BY t.created_at ASC";
$stmtDP = $pdo->prepare($sqlDetailPinjam);
$stmtDP->execute([$branch_id, $branch_id, $start_periode, $end_periode]);
$allDetailPinjam = $stmtDP->fetchAll();

$detailPinjamMap = [];
foreach ($allDetailPinjam as $dp) {
    $detailPinjamMap[$dp['terapis_id']][] = $dp;
}

// =================================================================================
// QUERY SHIFT AKTIF SEKARANG
// =================================================================================
$sqlShiftAktif = "SELECT ka.id, ka.waktu_masuk, ka.omset_shift, ka.total_transaksi_shift,
                  u.nama_lengkap as nama_kasir
                  FROM kasir_attendance ka
                  JOIN users u ON ka.kasir_id = u.id
                  WHERE ka.branch_id = ? AND ka.status = 'aktif' AND ka.waktu_masuk >= ?
                  ORDER BY ka.waktu_masuk DESC LIMIT 1";
$stmtShiftAktif = $pdo->prepare($sqlShiftAktif);
$stmtShiftAktif->execute([$branch_id, $start_periode]);
$shiftAktif = $stmtShiftAktif->fetch();

$terapisShiftAktif   = [];
$omsetShiftAktifTotal = 0;
$trxShiftAktifTotal   = 0;

if ($shiftAktif) {
    $waktuBukaShift = $shiftAktif['waktu_masuk'];

    $sqlTrxAktif = "SELECT u.id, u.nama_lengkap,
                    COUNT(t.id) as jumlah_transaksi,
                    COALESCE(SUM(t.omset_terapis), 0) as omset_terapis,
                    COALESCE(SUM(t.total_bayar), 0) as total_bayar
                    FROM transactions t
                    JOIN users u ON t.terapis_id = u.id
                    WHERE t.branch_id = ? AND t.created_at >= ? AND t.status != 'batal'
                    GROUP BY u.id, u.nama_lengkap
                    ORDER BY jumlah_transaksi DESC, omset_terapis DESC";
    $stmtTrxAktif = $pdo->prepare($sqlTrxAktif);
    $stmtTrxAktif->execute([$branch_id, $waktuBukaShift]);
    $hasTrxShift = $stmtTrxAktif->fetchAll();

    $stmtAllTerapis = $pdo->prepare("SELECT id, nama_lengkap FROM users WHERE role = 'terapis' AND home_branch_id = ? ORDER BY nama_lengkap ASC");
    $stmtAllTerapis->execute([$branch_id]);
    $allTerapis = $stmtAllTerapis->fetchAll();

    $mapTrx = [];
    foreach ($hasTrxShift as $row) { $mapTrx[$row['id']] = $row; }

    foreach ($allTerapis as $at) {
        if (isset($mapTrx[$at['id']])) {
            $terapisShiftAktif[] = $mapTrx[$at['id']];
        } else {
            $terapisShiftAktif[] = [
                'id'               => $at['id'],
                'nama_lengkap'     => $at['nama_lengkap'],
                'jumlah_transaksi' => 0,
                'omset_terapis'    => 0,
                'total_bayar'      => 0,
            ];
        }
    }

    usort($terapisShiftAktif, function($a, $b) {
        if ($b['jumlah_transaksi'] != $a['jumlah_transaksi']) return $b['jumlah_transaksi'] - $a['jumlah_transaksi'];
        return strcmp($a['nama_lengkap'], $b['nama_lengkap']);
    });

    $omsetShiftAktifTotal = array_sum(array_column($terapisShiftAktif, 'total_bayar'));
    $trxShiftAktifTotal   = array_sum(array_column($terapisShiftAktif, 'jumlah_transaksi'));
}

// =================================================================================
// QUERY RIWAYAT SHIFT SEBELUMNYA HARI INI (sudah tutup)
// =================================================================================
$sqlShiftLalu = "SELECT ka.id, ka.waktu_masuk, ka.waktu_keluar, ka.omset_shift, ka.total_transaksi_shift,
                 u.nama_lengkap as nama_kasir
                 FROM kasir_attendance ka
                 JOIN users u ON ka.kasir_id = u.id
                 WHERE ka.branch_id = ? AND ka.status = 'selesai'
                 AND ka.waktu_masuk >= ? AND ka.waktu_masuk < ?
                 ORDER BY ka.waktu_keluar DESC";
$stmtShiftLalu = $pdo->prepare($sqlShiftLalu);
$stmtShiftLalu->execute([$branch_id, $start_periode, $end_periode]);
$shiftLalu = $stmtShiftLalu->fetchAll();

$shiftTerapisData = [];
foreach ($shiftLalu as $shift) {
    $sqlTrxShift = "SELECT u.id, u.nama_lengkap,
                    COUNT(t.id) as jumlah_transaksi,
                    COALESCE(SUM(t.omset_terapis), 0) as omset_terapis,
                    COALESCE(SUM(t.total_bayar), 0) as total_bayar
                    FROM transactions t
                    JOIN users u ON t.terapis_id = u.id
                    WHERE t.branch_id = ? AND t.created_at >= ? AND t.created_at <= ? AND t.status != 'batal'
                    GROUP BY u.id, u.nama_lengkap
                    ORDER BY jumlah_transaksi DESC, omset_terapis DESC";
    $stmtTrxShift = $pdo->prepare($sqlTrxShift);
    $stmtTrxShift->execute([$branch_id, $shift['waktu_masuk'], $shift['waktu_keluar']]);
    $shiftTerapisData[$shift['id']] = $stmtTrxShift->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Terapis - Kasir</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <style>
        /* ===== Custom Styles Khusus Halaman Terapis ===== */
        .terapis-badge { display:inline-block; padding:4px 10px; border-radius:12px; font-size:10px; font-weight:800; margin-left:5px; border:1px solid transparent; letter-spacing:0.5px; }
        .badge-frequent { background:rgba(39,174,96,0.1); color:var(--accent-green); border-color:rgba(39,174,96,0.3); }
        .badge-new-here { background:rgba(243,156,18,0.1); color:var(--accent-yellow2); border-color:rgba(243,156,18,0.3); }
        .badge-borrowed { background:rgba(231,76,60,0.1); color:var(--accent-red); border-color:rgba(231,76,60,0.3); animation:pulse 2s infinite; }
        
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.6;} }
        
        .countdown-borrowed { font-size:11px; color:var(--accent-red); font-weight:bold; }
        .omset-detail { font-size:11px; margin-top:5px; padding-top:5px; border-top:1px dashed var(--border-color); }
        .omset-lokal { color:var(--accent-green); font-weight:600; display:block; margin-bottom:2px; }
        .omset-pinjam { color:var(--accent-blue); font-weight:600; display:block; }
        .omset-total { font-size:15px; font-weight:800; color:var(--accent-green); }
        .trx-pinjam-info { font-size:11px; color:var(--accent-blue); margin-top:4px; font-weight:600; }

        /* ===== Shift Card Overrides ===== */
        .shift-card { border-radius:12px; overflow:hidden; margin-bottom:20px; box-shadow:var(--shadow-sm); border:1px solid var(--border-color); background:var(--bg-panel); }
        .shift-card-header-aktif { background:linear-gradient(135deg, var(--accent-green) 0%, #2ecc71 100%); color:white; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
        .shift-card-header-lama { background:linear-gradient(135deg, var(--text-mid) 0%, var(--text-dark) 100%); color:white; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
        
        .shift-title { font-weight:800; font-size:15px; font-family:'Plus Jakarta Sans', sans-serif; letter-spacing: 0.5px; text-transform: uppercase; }
        .shift-meta { font-size:12px; opacity:0.9; margin-top:4px; font-weight:600; }
        
        .shift-stats { display:flex; gap:12px; flex-wrap:wrap; }
        .shift-stat { text-align:center; background:rgba(0,0,0,0.2); padding:8px 15px; border-radius:8px; min-width:90px; border:1px solid rgba(255,255,255,0.1); }
        .shift-stat .val { font-size:16px; font-weight:800; }
        .shift-stat .lbl { font-size:10px; opacity:0.9; text-transform:uppercase; letter-spacing:0.5px; margin-top:2px; }

        .live-dot { display:inline-block; width:10px; height:10px; background:#fff; border-radius:50%; margin-right:8px; animation:blink 1.2s infinite; vertical-align:middle; box-shadow: 0 0 8px rgba(255,255,255,0.8); }
        @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.2;} }

        .shift-empty { padding:30px; text-align:center; color:var(--text-muted); font-size:14px; font-weight:600; background:var(--bg-input); }

        .row-standby td { color:var(--text-muted); opacity: 0.7; }
        .row-busy-shift { background: rgba(52,152,219,0.05); border-left: 3px solid var(--accent-blue); }

        /* ===== ROW TERAPIS PINJAMAN ===== */
        .row-pinjaman { background: rgba(243,156,18,0.05); border-left: 3px solid var(--accent-yellow2); }
        .row-pinjaman td { color: var(--text-dark); }
        .badge-cabang-asal { display:inline-block; background:var(--accent-yellow2); color:#111; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:bold; margin-left:5px; }

        /* ===== TOMBOL DETAIL TRX ===== */
        .btn-detail-trx { display:inline-block; margin-top:6px; padding:4px 10px; background:var(--bg-input); color:var(--text-dark); border:1px solid var(--border-color); border-radius:6px; font-size:11px; font-weight:700; cursor:pointer; transition:0.2s; width: 100%; text-align: center; }
        .btn-detail-trx:hover { background:var(--accent-blue); color:white; border-color:var(--accent-blue); }

        /* ===== POPUP DETAIL TRANSAKSI ===== */
        .popup-trx-row { display:flex; align-items:center; padding:15px; border-bottom:1px solid var(--border-color); gap:15px; }
        .popup-trx-row:last-child { border-bottom:none; }
        .popup-trx-num { width:28px; height:28px; background:var(--accent-blue); color:white; border-radius:50%; font-size:12px; font-weight:bold; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .popup-trx-info { flex:1; }
        .popup-trx-info .trx-cust { font-weight:800; color:var(--text-dark); font-size:14px; }
        .popup-trx-info .trx-paket { color:var(--text-muted); font-size:12px; margin-top:3px; font-weight:600; }
        .popup-trx-info .trx-kasir { color:var(--text-muted); font-size:11px; margin-top:2px; }
        .popup-trx-jam { font-size:12px; color:var(--text-dark); white-space:nowrap; font-weight:bold; background:var(--bg-input); padding:4px 8px; border-radius:6px; }
        
        .section-divider { padding:10px 20px; background:var(--bg-input); border-bottom:1px solid var(--border-color); border-top:1px solid var(--border-color); font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; }
    </style>
</head>
<body>
    <div class="container-layout">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-profile">
                <img src="<?= $foto_profil ?>" alt="Profil">
                <div class="profile-info">
                    <h3><?= htmlspecialchars($nama_kasir) ?></h3>
                    <small><?= htmlspecialchars($nama_cabang) ?></small>
                </div>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard_kasir.php" class="menu-item"><span class="menu-abbr">DB</span><span class="menu-text">Dashboard</span></a>
                <a href="input_transaksi.php" class="menu-item"><span class="menu-abbr">IT</span><span class="menu-text">Input Transaksi</span></a>
                <a href="absensi_kasir.php" class="menu-item"><span class="menu-abbr">AT</span><span class="menu-text">Absensi Terapis</span></a>
                <a href="data_terapis_hadir.php" class="menu-item active"><span class="menu-abbr">DT</span><span class="menu-text">Data Terapis</span></a>
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
                        <h1 style="margin-bottom: 4px;">Data Terapis Hadir</h1>
                        <span style="font-size:12px; color:var(--text-muted); font-weight:600; text-transform:uppercase;">Hari Bisnis: <?= date('d M Y', strtotime($tglBisnis)) ?></span>
                    </div>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()" title="Ganti Tema">Mode Layar</button>
                </div>
            </div>

            <?php if($pesan): ?>
            <div style="padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; background: rgba(39,174,96,0.1); color: #27ae60; border-left: 4px solid #27ae60;">
                <?= $pesan ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Status & Komisi Hari Ini</span>
                    <small style="color:var(--text-muted); font-weight:normal;">Keseluruhan transaksi hari ini</small>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Terapis</th>
                                <th>Status Saat Ini</th>
                                <th style="text-align:center;">Transaksi</th>
                                <th style="text-align:right;">Pendapatan Bersih</th>
                                <th>Riwayat Cabang</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($terapis) > 0): ?>
                                <?php foreach($terapis as $t):
                                    $isBusy     = ($t['current_transaction_id'] != null);
                                    $isFrequent = ($t['trx_alltime_cabang'] >= 5);
                                    $isDipinjam = ($t['dipinjam_ke_cabang'] != null);
                                    $omsetLokal  = $t['omset_lokal'] ?? 0;
                                    $omsetPinjam = $t['omset_pinjam'] ?? 0;
                                    $omsetTotal  = $t['omset_total'] ?? 0;
                                    $trxLokal    = $t['trx_hari_ini_lokal'] ?? 0;
                                    $trxPinjam   = $t['trx_hari_ini_pinjam'] ?? 0;
                                    $trxTotal    = $t['trx_hari_ini_total'] ?? 0;
                                    $sudahAbsen  = isset($absenHariIni[$t['id']]);
                                ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--text-dark); font-size:14px;"><?= htmlspecialchars($t['nama_lengkap']) ?></strong>
                                        <?php if($isDipinjam): ?>
                                            <div style="margin-top:4px;"><span class="terapis-badge badge-borrowed">DIPINJAM: <?= htmlspecialchars($t['nama_cabang_peminjam']) ?></span></div>
                                            <div class="countdown-borrowed" data-finish="<?= $t['waktu_kembali_estimasi'] ?>" style="margin-top:2px;">Est. kembali: ...</div>
                                        <?php elseif($isFrequent): ?>
                                            <span class="terapis-badge badge-frequent">SERING</span>
                                        <?php elseif($t['trx_alltime_cabang'] == 0): ?>
                                            <span class="terapis-badge badge-new-here">BARU</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if(isset($izinMapDTH[$t['id']])): ?>
                                            <?php if($izinMapDTH[$t['id']]['jenis'] === 'sakit'): ?>
                                                <span class="badge badge-danger">SAKIT</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">IZIN</span>
                                            <?php endif; ?>
                                            <?php if($izinMapDTH[$t['id']]['status'] === 'pending'): ?>
                                            <br><small style="color:var(--accent-yellow2); font-weight:bold;">Menunggu</small>
                                            <?php endif; ?>
                                        <?php elseif(!$sudahAbsen): ?>
                                            <span class="badge" style="background:var(--bg-input); color:var(--text-muted); border: 1px solid var(--border-color);">BELUM ABSEN</span>
                                        <?php elseif($isBusy): ?>
                                            <?php if($isDipinjam): ?>
                                                <span class="badge badge-warning">DILUAR CABANG</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">SIBUK</span>
                                                <?php if($t['jam_bebas']): ?>
                                                <br><small style="color:var(--accent-red); font-weight:bold; margin-top:4px; display:inline-block;">Selesai: <span class="countdown" data-finish="<?= $t['jam_bebas'] ?>">...</span></small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-success">STANDBY</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <strong style="font-size:18px; color:var(--text-dark);"><?= $trxTotal ?></strong><span style="color:var(--text-muted); font-size:12px;">x</span>
                                        <?php if($trxTotal > 0): ?>
                                            <?php
                                            $detailData  = $detailTrxMap[$t['id']] ?? [];
                                            $pinjamData  = $detailPinjamMap[$t['id']] ?? [];
                                            $detailJson  = htmlspecialchars(json_encode($detailData), ENT_QUOTES);
                                            $pinjamJson  = htmlspecialchars(json_encode($pinjamData), ENT_QUOTES);
                                            ?>
                                            <br><button class="btn-detail-trx" onclick="showDetailTrx('<?= htmlspecialchars(addslashes($t['nama_lengkap'])) ?>', this)" data-detail="<?= $detailJson ?>" data-pinjam="<?= $pinjamJson ?>">Detail</button>
                                        <?php endif; ?>
                                        <?php if($trxPinjam > 0): ?>
                                            <div class="trx-pinjam-info">Lokal: <?= $trxLokal ?> | Pinjam: <?= $trxPinjam ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <div class="omset-total">Rp <?= number_format($omsetTotal, 0, ',', '.') ?></div>
                                        <?php if($omsetPinjam > 0): ?>
                                            <div class="omset-detail">
                                                <span class="omset-lokal">Lokal: Rp <?= number_format($omsetLokal, 0, ',', '.') ?></span>
                                                <span class="omset-pinjam">Luar (<?= htmlspecialchars($t['nama_cabang_kerja_lain'] ?? '-') ?>): Rp <?= number_format($omsetPinjam, 0, ',', '.') ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong style="color:var(--text-dark);"><?= $t['trx_total_cabang'] ?? 0 ?></strong><span style="color:var(--text-muted); font-size:12px;"> trx</span></td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (!empty($terapisPinjaman)): ?>
                                <tr>
                                    <td colspan="5" style="background:rgba(52,152,219,0.1); padding:12px 16px; font-size:12px; font-weight:bold; color:var(--accent-blue); border-top:2px solid var(--accent-blue); text-transform:uppercase;">
                                        Terapis Pinjaman Dari Cabang Lain (Melayani Di Sini)
                                    </td>
                                </tr>
                                <?php foreach($terapisPinjaman as $tp): ?>
                                <tr class="row-pinjaman">
                                    <td>
                                        <strong style="color:var(--text-dark); font-size:14px;"><?= htmlspecialchars($tp['nama_lengkap']) ?></strong>
                                        <br><span class="badge-cabang-asal">Asal: <?= htmlspecialchars($tp['cabang_asal']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-danger">SIBUK</span>
                                        <?php if($tp['jam_bebas']): ?>
                                        <br><small style="color:var(--accent-red); font-weight:bold; margin-top:4px; display:inline-block;"><span class="countdown" data-finish="<?= $tp['jam_bebas'] ?>">...</span></small>
                                        <?php endif; ?>
                                    </td>
                                    <td colspan="2">
                                        <small style="color:var(--text-muted); font-weight:600; display:block;">Pelanggan: <strong style="color:var(--text-dark);"><?= htmlspecialchars($tp['customer_name'] ?? '-') ?></strong></small>
                                        <small style="color:var(--text-muted); font-weight:600;">Bed: <strong style="color:var(--text-dark);"><?= htmlspecialchars($tp['bed_number'] ?? '-') ?></strong></small>
                                        <div style="margin-top:5px; font-size:11px; color:var(--text-muted); font-style:italic;">Komisi masuk ke cabang asal</div>
                                    </td>
                                    <td>-</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted); font-weight:600;">Belum ada terapis yang terdaftar di cabang ini.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="margin: 30px 0 15px 0;">
                <h2 style="font-family:'Plus Jakarta Sans', sans-serif; font-size:18px; color:var(--text-dark); margin:0 0 5px 0;">Omset & Transaksi Shift Sekarang</h2>
                <small style="color:var(--text-muted); font-weight:600;">Dihitung dari 0 sejak shift ini dibuka oleh kasir</small>
            </div>

            <?php if ($shiftAktif): ?>
            <div class="shift-card">
                <div class="shift-card-header-aktif">
                    <div>
                        <div class="shift-title">
                            <span class="live-dot"></span>
                            SHIFT AKTIF &mdash; <?= htmlspecialchars($shiftAktif['nama_kasir']) ?>
                        </div>
                        <div class="shift-meta">
                            Buka: <?= date('H:i', strtotime($shiftAktif['waktu_masuk'])) ?>
                            &nbsp;|&nbsp; Berlangsung: <span id="durasiShiftAktif" data-mulai="<?= $shiftAktif['waktu_masuk'] ?>">--:--:--</span>
                        </div>
                    </div>
                    <div class="shift-stats">
                        <div class="shift-stat">
                            <div class="val"><?= $trxShiftAktifTotal ?></div>
                            <div class="lbl">Transaksi</div>
                        </div>
                        <div class="shift-stat">
                            <div class="val">Rp <?= number_format($omsetShiftAktifTotal, 0, ',', '.') ?></div>
                            <div class="lbl">Omset Shift</div>
                        </div>
                    </div>
                </div>

                <div class="table-container" style="border-radius:0;">
                    <table style="margin:0;">
                        <thead>
                            <tr>
                                <th>Nama Terapis</th>
                                <th>Status Saat Ini</th>
                                <th style="text-align:center;">Transaksi (Shift Ini)</th>
                                <th style="text-align:right;">Omset Layanan</th>
                                <th style="text-align:right;">Komisi Terapis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($terapisShiftAktif)): ?>
                                <?php
                                $busyMap = []; $jamBebasMap = [];
                                foreach ($terapis as $rt) {
                                    $busyMap[$rt['id']]     = ($rt['current_transaction_id'] != null);
                                    $jamBebasMap[$rt['id']] = $rt['jam_bebas'];
                                }
                                ?>
                                <?php foreach ($terapisShiftAktif as $ta):
                                    $isBusyShift = $busyMap[$ta['id']] ?? false;
                                    $jamBebas    = $jamBebasMap[$ta['id']] ?? null;
                                    $adaTrx      = ($ta['jumlah_transaksi'] > 0);
                                    $rowClass    = $isBusyShift ? 'row-busy-shift' : (!$adaTrx ? 'row-standby' : '');
                                    $sudahAbsenShift = isset($absenHariIni[$ta['id']]);
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td><strong style="color:var(--text-dark);"><?= htmlspecialchars($ta['nama_lengkap']) ?></strong></td>
                                    <td>
                                        <?php if(isset($izinMapDTH[$ta['id']])): ?>
                                            <?php if($izinMapDTH[$ta['id']]['jenis'] === 'sakit'): ?>
                                                <span class="badge badge-danger">SAKIT</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">IZIN</span>
                                            <?php endif; ?>
                                        <?php elseif(!$sudahAbsenShift): ?>
                                            <span class="badge" style="background:var(--bg-input); color:var(--text-muted); border: 1px solid var(--border-color);">BELUM ABSEN</span>
                                        <?php elseif($isBusyShift): ?>
                                            <span class="badge badge-danger">SIBUK</span>
                                            <?php if($jamBebas): ?>
                                            <br><small style="color:var(--accent-red); font-weight:bold; margin-top:4px; display:inline-block;"><span class="countdown" data-finish="<?= $jamBebas ?>">...</span></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-success">STANDBY</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if($adaTrx): ?>
                                            <strong style="font-size:16px; color:var(--text-dark);"><?= $ta['jumlah_transaksi'] ?></strong>
                                            <span style="color:var(--text-muted); font-size:12px;">x</span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <?php if($adaTrx): ?>
                                            <strong style="color:var(--text-dark);">Rp <?= number_format($ta['total_bayar'], 0, ',', '.') ?></strong>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <?php if($adaTrx): ?>
                                            <strong style="color:var(--accent-green); font-size:15px;">Rp <?= number_format($ta['omset_terapis'], 0, ',', '.') ?></strong>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background:rgba(39,174,96,0.1); border-top:2px solid var(--accent-green);">
                                    <td colspan="2" style="color:var(--accent-green); font-weight:bold; text-transform:uppercase;">Total Shift Aktif</td>
                                    <td style="text-align:center; font-weight:bold; color:var(--text-dark);"><?= $trxShiftAktifTotal ?>x</td>
                                    <td style="text-align:right; font-weight:bold; color:var(--text-dark);">Rp <?= number_format($omsetShiftAktifTotal, 0, ',', '.') ?></td>
                                    <td style="text-align:right; font-weight:bold; color:var(--accent-green); font-size:16px;">Rp <?= number_format(array_sum(array_column($terapisShiftAktif, 'omset_terapis')), 0, ',', '.') ?></td>
                                </tr>
                            <?php else: ?>
                                <tr><td colspan="5" class="shift-empty">Belum ada transaksi di shift ini.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div style="padding:30px; text-align:center; color:var(--text-muted); font-weight:bold;">
                    Tidak ada shift yang sedang aktif saat ini. Buka shift di menu Dashboard.
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($shiftLalu)): ?>
            <div style="margin: 30px 0 15px 0;">
                <h2 style="font-family:'Plus Jakarta Sans', sans-serif; font-size:18px; color:var(--text-dark); margin:0 0 5px 0;">Riwayat Shift Sebelumnya</h2>
                <small style="color:var(--text-muted); font-weight:600;">Shift yang sudah ditutup pada hari bisnis ini</small>
            </div>

            <?php foreach ($shiftLalu as $shift):
                $waktuBukaH  = date('H:i', strtotime($shift['waktu_masuk']));
                $waktuTutupH = date('H:i', strtotime($shift['waktu_keluar']));
                $durasi      = round((strtotime($shift['waktu_keluar']) - strtotime($shift['waktu_masuk'])) / 3600, 1);
                $terapisShift = $shiftTerapisData[$shift['id']] ?? [];
            ?>
            <div class="shift-card">
                <div class="shift-card-header-lama">
                    <div>
                        <div class="shift-title">SHIFT TUTUP &mdash; <?= htmlspecialchars($shift['nama_kasir']) ?></div>
                        <div class="shift-meta">
                            Buka: <?= $waktuBukaH ?> &nbsp;|&nbsp; Tutup: <?= $waktuTutupH ?>
                            &nbsp;|&nbsp; Durasi: <?= $durasi ?> jam
                        </div>
                    </div>
                    <div class="shift-stats">
                        <div class="shift-stat">
                            <div class="val"><?= $shift['total_transaksi_shift'] ?></div>
                            <div class="lbl">Transaksi</div>
                        </div>
                        <div class="shift-stat">
                            <div class="val">Rp <?= number_format($shift['omset_shift'], 0, ',', '.') ?></div>
                            <div class="lbl">Omset Bersih</div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($terapisShift)): ?>
                <div class="table-container" style="border-radius:0;">
                    <table style="margin:0;">
                        <thead>
                            <tr style="background:var(--bg-input);">
                                <th>Terapis</th>
                                <th style="text-align:center;">Transaksi</th>
                                <th style="text-align:right;">Omset Layanan</th>
                                <th style="text-align:right;">Komisi Terapis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($terapisShift as $ts): ?>
                            <tr>
                                <td><strong style="color:var(--text-dark);"><?= htmlspecialchars($ts['nama_lengkap']) ?></strong></td>
                                <td style="text-align:center;">
                                    <strong style="font-size:15px; color:var(--text-dark);"><?= $ts['jumlah_transaksi'] ?></strong>
                                    <span style="color:var(--text-muted); font-size:12px;">x</span>
                                </td>
                                <td style="text-align:right;"><strong style="color:var(--text-dark);">Rp <?= number_format($ts['total_bayar'], 0, ',', '.') ?></strong></td>
                                <td style="text-align:right;"><strong style="color:var(--text-dark);">Rp <?= number_format($ts['omset_terapis'], 0, ',', '.') ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="background:var(--bg-input); font-weight:bold; border-top:2px solid var(--text-dark);">
                            <tr>
                                <td style="color:var(--text-dark); text-transform:uppercase;">Total Shift</td>
                                <td style="text-align:center; color:var(--text-dark);"><?= array_sum(array_column($terapisShift,'jumlah_transaksi')) ?>x</td>
                                <td style="text-align:right; color:var(--text-dark);">Rp <?= number_format(array_sum(array_column($terapisShift,'total_bayar')), 0, ',', '.') ?></td>
                                <td style="text-align:right; color:var(--text-dark);">Rp <?= number_format(array_sum(array_column($terapisShift,'omset_terapis')), 0, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                    <div class="shift-empty">Tidak ada transaksi tercatat pada shift ini.</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

    <div class="modal-overlay" id="detailPopup" onclick="if(event.target===this)closePopup()">
        <div class="modal-box" style="max-width: 500px;">
            <div class="modal-header" style="background:var(--bg-input);">
                <h3 id="popupTitle" style="color:var(--text-dark); font-size:16px;">Detail Transaksi</h3>
                <button class="modal-close" onclick="closePopup()">×</button>
            </div>
            <div class="modal-body" id="popupBody" style="padding:0;"></div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            localStorage.setItem('bugar-theme', next);
        }
        (function() { const saved = localStorage.getItem('bugar-theme'); if (saved) document.documentElement.setAttribute('data-theme', saved); })();

        function toggleSidebar() {
            const sb = document.getElementById('sidebar');
            sb.classList.toggle('collapsed');
            const btnText = document.querySelector('.sidebar-toggle-btn .menu-text');
            const btnAbbr = document.querySelector('.sidebar-toggle-btn .menu-abbr');
            if (sb.classList.contains('collapsed')) { btnText.style.display = 'none'; btnAbbr.style.display = 'inline'; } 
            else { btnText.style.display = 'inline'; btnAbbr.style.display = 'none'; }
        }

        function showDetailTrx(nama, btn) {
            const raw = btn.getAttribute('data-detail');
            const rawPinjam = btn.getAttribute('data-pinjam');
            let lokal = [], pinjam = [];
            try { lokal = JSON.parse(raw); } catch(e) {}
            try { pinjam = JSON.parse(rawPinjam); } catch(e) {}

            document.getElementById('popupTitle').innerHTML = 'Riwayat Hari Ini: ' + nama;

            let html = '';

            // Lokal
            if (lokal.length > 0) {
                html += `<div class="section-divider">LOKAL (CABANG INI)</div>`;
                lokal.forEach((row, i) => {
                    const d = new Date(row.created_at);
                    const jam = String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
                    html += `
                    <div class="popup-trx-row">
                        <div class="popup-trx-num">${i + 1}</div>
                        <div class="popup-trx-info">
                            <div class="trx-cust">${row.nama_pelanggan}</div>
                            <div class="trx-paket">${row.nama_paket}</div>
                            <div class="trx-kasir">Kasir: <strong>${row.nama_kasir}</strong></div>
                        </div>
                        <div class="popup-trx-jam">${jam}</div>
                    </div>`;
                });
            }

            // Pinjam
            if (pinjam.length > 0) {
                const byCabang = {};
                pinjam.forEach(row => {
                    const key = row.nama_cabang;
                    if (!byCabang[key]) byCabang[key] = [];
                    byCabang[key].push(row);
                });

                Object.keys(byCabang).forEach(cabang => {
                    html += `<div class="section-divider" style="background:rgba(243,156,18,0.1); color:var(--accent-yellow2);">DIPINJAM KE: ${cabang}</div>`;
                    byCabang[cabang].forEach((row, i) => {
                        const d = new Date(row.created_at);
                        const jam = String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
                        html += `
                        <div class="popup-trx-row">
                            <div class="popup-trx-num" style="background:var(--accent-yellow2);">${i + 1}</div>
                            <div class="popup-trx-info">
                                <div class="trx-cust">${row.nama_pelanggan}</div>
                                <div class="trx-paket">${row.nama_paket}</div>
                                <div class="trx-kasir">Kasir: <strong>${row.nama_kasir}</strong></div>
                            </div>
                            <div class="popup-trx-jam">${jam}</div>
                        </div>`;
                    });
                });
            }

            if (html === '') {
                html = '<div style="padding:40px; text-align:center; color:var(--text-muted); font-weight:bold;">Tidak ada data transaksi.</div>';
            }

            document.getElementById('popupBody').innerHTML = html;
            document.getElementById('detailPopup').classList.add('show');
        }

        function closePopup() { document.getElementById('detailPopup').classList.remove('show'); }
        document.addEventListener('keydown', e => { if(e.key === 'Escape') closePopup(); });

        setInterval(() => {
            // Countdown Standar
            document.querySelectorAll('.countdown').forEach(el => {
                const finish = new Date(el.dataset.finish);
                const diff = finish - new Date();
                if (diff <= 0) {
                    const ov = Math.abs(diff);
                    const m = Math.floor(ov / 60000);
                    const s = Math.floor((ov % 60000) / 1000);
                    el.innerHTML = '<span style="color:var(--accent-red); font-weight:bold;">OT +' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0') + '</span>';
                } else {
                    el.innerText = new Date(diff).toISOString().substr(11, 8);
                }
            });

            // Countdown Pinjam (Borrowed)
            document.querySelectorAll('.countdown-borrowed').forEach(el => {
                const finish = new Date(el.dataset.finish);
                const diff = finish - new Date();
                if (diff <= 0) {
                    const ov = Math.abs(diff);
                    const m = Math.floor(ov / 60000);
                    const s = Math.floor((ov % 60000) / 1000);
                    el.innerHTML = '<span style="color:var(--accent-red); font-weight:bold;">OT +' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0') + '</span>';
                } else {
                    const m = Math.floor(diff / 60000);
                    const s = Math.floor((diff % 60000) / 1000);
                    el.innerHTML = 'Est. kembali: ' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
                }
            });

            // Durasi shift aktif
            const elDurasi = document.getElementById('durasiShiftAktif');
            if (elDurasi) {
                const mulai = new Date(elDurasi.dataset.mulai);
                const diff = new Date() - mulai;
                if (diff > 0) {
                    const h = Math.floor(diff / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    const s = Math.floor((diff % 60000) / 1000);
                    elDurasi.innerText = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
                }
            }
        }, 1000);

        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>