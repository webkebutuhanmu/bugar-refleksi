<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'leader') {
    header("Location: ../auth/login.php");
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$userId   = $_SESSION['user_id'];
$branchId = $_SESSION['user_branch_id'];

// Settings
$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
$jamMulai = $settings['jam_mulai_hari'] ?? '08:00:00';

// Tentukan tanggal bisnis hari ini
$jamSekarang = date('H:i:s');
$tglBisnis   = ($jamSekarang < $jamMulai) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$start_hari  = "$tglBisnis $jamMulai";
$end_hari    = date('Y-m-d H:i:s', strtotime("$start_hari +1 day"));

// Ambil data user
$stmtUser = $pdo->prepare("SELECT u.*, b.nama_cabang FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
$stmtUser->execute([$userId]);
$userMe     = $stmtUser->fetch();
$fotoPath   = !empty($userMe['foto_profil']) ? "../uploads/profil/" . $userMe['foto_profil'] : "../assets/img/default-avatar.png";
$namaCabang = $userMe['nama_cabang'];

// === FILTER STATISTIK dari GET ===
$filterType   = $_GET['filter'] ?? 'harian';
$filterStart  = $_GET['tgl_start'] ?? '';
$filterEnd    = $_GET['tgl_end'] ?? '';

// Tentukan range berdasarkan filter
$today        = $tglBisnis;
$currentMonth = date('Y-m');
$currentYear  = date('Y');

if ($filterType === 'harian') {
    $statStart = $start_hari;
    $statEnd   = $end_hari;
    $statLabel = 'Hari Ini (' . date('d M Y', strtotime($tglBisnis)) . ')';
} elseif ($filterType === 'bulanan') {
    $m         = date('Y-m');
    $statStart = "$m-01 $jamMulai";
    $statEnd   = date('Y-m-d H:i:s', strtotime("$statStart +1 month"));
    $statLabel = 'Bulan Ini (' . date('M Y') . ')';
} elseif ($filterType === 'tahunan') {
    $y         = date('Y');
    $statStart = "$y-01-01 $jamMulai";
    $statEnd   = date('Y-m-d H:i:s', strtotime("$statStart +1 year"));
    $statLabel = 'Tahun Ini (' . $y . ')';
} elseif ($filterType === 'rentang' && $filterStart && $filterEnd) {
    $statStart = $filterStart . ' 00:00:00';
    $statEnd   = $filterEnd . ' 23:59:59';
    $statLabel = date('d M Y', strtotime($filterStart)) . ' – ' . date('d M Y', strtotime($filterEnd));
} else {
    $filterType = 'harian';
    $statStart  = $start_hari;
    $statEnd    = $end_hari;
    $statLabel  = 'Hari Ini (' . date('d M Y', strtotime($tglBisnis)) . ')';
}

// === QUERY STATISTIK ===
$stmtOmset = $pdo->prepare("SELECT COALESCE(SUM(total_bayar),0) FROM transactions WHERE branch_id=? AND status='selesai' AND created_at>=? AND created_at<?");
$stmtOmset->execute([$branchId, $statStart, $statEnd]);
$omsetStat = $stmtOmset->fetchColumn();

$stmtTrx = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE branch_id=? AND status!='batal' AND created_at>=? AND created_at<?");
$stmtTrx->execute([$branchId, $statStart, $statEnd]);
$trxStat = $stmtTrx->fetchColumn();

$stmtCust = $pdo->prepare("SELECT COUNT(DISTINCT nama_pelanggan) FROM transactions WHERE branch_id=? AND created_at>=? AND created_at<?");
$stmtCust->execute([$branchId, $statStart, $statEnd]);
$custStat = $stmtCust->fetchColumn();

// === SHIFT AKTIF KASIR ===
$stmtShift = $pdo->prepare("SELECT ka.id, ka.waktu_masuk, u.nama_lengkap as nama_kasir
                             FROM kasir_attendance ka JOIN users u ON ka.kasir_id = u.id
                             WHERE ka.branch_id=? AND ka.status='aktif' AND ka.waktu_masuk>=?
                             ORDER BY ka.waktu_masuk DESC LIMIT 1");
$stmtShift->execute([$branchId, $start_hari]);
$shiftAktif  = $stmtShift->fetch();
$cabangBuka  = ($shiftAktif !== false);

$current_time = date('H:i:s');
$shiftLabel   = ($current_time >= ($settings['shift_pagi_start'] ?? '08:00:00') && $current_time <= ($settings['shift_pagi_end'] ?? '14:00:00')) ? 'PAGI' : 'MALAM';
$shiftColor   = ($shiftLabel === 'PAGI') ? '#3498db' : '#9b59b6';

// Omset & transaksi shift aktif
$omsetShift = 0; $trxShift = 0;
if ($shiftAktif) {
    $rs = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_bayar),0) as omset FROM transactions WHERE branch_id=? AND created_at>=? AND status!='batal'");
    $rs->execute([$branchId, $shiftAktif['waktu_masuk']]);
    $rsRow = $rs->fetch();
    $trxShift   = $rsRow['cnt'];
    $omsetShift = $rsRow['omset'];
}

// === BED ===
$stmtBeds = $pdo->prepare("SELECT b.*,
    (SELECT COUNT(*) FROM transactions t WHERE t.bed_id=b.id AND t.status IN('proses','menunggu_pembayaran')) as is_occupied,
    (SELECT t.status FROM transactions t WHERE t.bed_id=b.id AND t.status IN('proses','menunggu_pembayaran') LIMIT 1) as trx_status,
    (SELECT t.nama_pelanggan FROM transactions t WHERE t.bed_id=b.id AND t.status IN('proses','menunggu_pembayaran') LIMIT 1) as customer_name,
    (SELECT u.nama_lengkap FROM transactions t JOIN users u ON t.terapis_id=u.id WHERE t.bed_id=b.id AND t.status IN('proses','menunggu_pembayaran') LIMIT 1) as terapis_name,
    (SELECT t.waktu_selesai FROM transactions t WHERE t.bed_id=b.id AND t.status='proses' LIMIT 1) as finish_time
    FROM beds b WHERE b.branch_id=? ORDER BY b.nomor_bed ASC");
$stmtBeds->execute([$branchId]);
$beds = $stmtBeds->fetchAll();
$countKosong = count(array_filter($beds, fn($b) => $b['is_occupied'] == 0));
$countTerisi = count(array_filter($beds, fn($b) => $b['is_occupied'] > 0));

// === TERAPIS STANDBY (sudah absen hari ini & tidak sedang melayani) ===
// BUG FIX: Ditambahkan kolom lain ke GROUP BY untuk menghindari error ONLY_FULL_GROUP_BY di MySQL/MariaDB
$today2 = $tglBisnis; // gunakan tanggal bisnis sesuai jam_mulai_hari dari pengaturan sistem
$sqlStandby = "SELECT u.id, u.nama_lengkap, u.foto_profil,
               ta.waktu_absen as jam_absen, ta.shift_type,
               (SELECT COUNT(*) FROM transactions t WHERE t.terapis_id=u.id AND t.status IN('proses','menunggu_pembayaran')) as sedang_melayani,
               (SELECT t.nama_pelanggan FROM transactions t WHERE t.terapis_id=u.id AND t.status='proses' LIMIT 1) as customer_name,
               (SELECT COUNT(*) FROM transactions t WHERE t.terapis_id=u.id AND t.branch_id=? AND t.created_at>=? AND t.status!='batal') as total_kerja_hari_ini
               FROM users u
               JOIN terapis_attendance ta ON ta.terapis_id=u.id AND ta.tanggal=? AND ta.branch_id=?
               WHERE (u.home_branch_id=? OR u.id IN (
                   SELECT tl.terapis_id FROM terapis_loans tl WHERE tl.to_branch_id=? AND tl.status='active'
               )) AND u.role='terapis'
               GROUP BY u.id, u.nama_lengkap, u.foto_profil, ta.waktu_absen, ta.shift_type, ta.giliran
               ORDER BY ta.giliran ASC";
$stmtStandby = $pdo->prepare($sqlStandby);
$stmtStandby->execute([$branchId, $start_hari, $today2, $branchId, $branchId, $branchId]);
$terapisStandby = $stmtStandby->fetchAll();

// === TERAPIS IZIN/SAKIT HARI INI ===
$stmtIzinLdr = $pdo->prepare(
    "SELECT ti.terapis_id, ti.jenis, ti.status, u.nama_lengkap, u.foto_profil
     FROM terapis_izin ti
     JOIN users u ON ti.terapis_id = u.id
     WHERE ti.branch_id = ? AND ti.tanggal = ? AND ti.status IN ('disetujui','pending')
     ORDER BY ti.created_at ASC"
);
$stmtIzinLdr->execute([$branchId, $today2]);
$terapisIzinLdr = $stmtIzinLdr->fetchAll();

// === TERAPIS DIPINJAM KELUAR ===
$stmtKeluar = $pdo->prepare("SELECT u.nama_lengkap, b.nama_cabang, l.approved_at FROM terapis_loans l JOIN users u ON l.terapis_id=u.id JOIN branches b ON l.to_branch_id=b.id WHERE l.from_branch_id=? AND l.status='active'");
$stmtKeluar->execute([$branchId]);
$terapisKeluar = $stmtKeluar->fetchAll();

// === TRANSAKSI TERBARU ===
$stmtRecent = $pdo->prepare("SELECT t.*, u.nama_lengkap as nama_terapis, b.nomor_bed FROM transactions t LEFT JOIN users u ON t.terapis_id=u.id LEFT JOIN beds b ON t.bed_id=b.id WHERE t.branch_id=? ORDER BY t.created_at DESC LIMIT 10");
$stmtRecent->execute([$branchId]);
$recentTransactions = $stmtRecent->fetchAll();

// === GRAFIK 7 HARI ===
$stmtChart = $pdo->prepare("SELECT DATE_FORMAT(tanggal_transaksi,'%d/%m') as tgl, SUM(total_bayar) as omset, COUNT(*) as jumlah FROM transactions WHERE branch_id=? AND tanggal_transaksi>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY tanggal_transaksi ORDER BY tanggal_transaksi ASC");
$stmtChart->execute([$branchId]);
$chartData = $stmtChart->fetchAll();
$labels = []; $dataOmset = []; $dataTrx = [];
foreach ($chartData as $cd) { $labels[] = $cd['tgl']; $dataOmset[] = (float)$cd['omset']; $dataTrx[] = (int)$cd['jumlah']; }

// === CEK STOK KRITIS ===
$emptyStocks = [];
$stmtZero = $pdo->prepare("SELECT i.nama_item FROM branch_items bi JOIN items i ON bi.item_id=i.id WHERE bi.branch_id=? AND bi.stok<=0");
$stmtZero->execute([$branchId]);
while ($r = $stmtZero->fetch()) { $emptyStocks[] = $r['nama_item'] . " (Stok Habis)"; }
$stmtMissing = $pdo->prepare("SELECT DISTINCT i.nama_item FROM package_items pi JOIN items i ON pi.item_id=i.id WHERE pi.item_id NOT IN(SELECT item_id FROM branch_items WHERE branch_id=?)");
$stmtMissing->execute([$branchId]);
while ($r = $stmtMissing->fetch()) { $emptyStocks[] = $r['nama_item'] . " (Belum Terdaftar)"; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Leader - <?= htmlspecialchars($namaCabang) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --sidebar-w: 250px; --primary: #2c3e50; --accent: #3498db; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; }
        .sidebar { width: var(--sidebar-w); background: linear-gradient(180deg, var(--primary) 0%, #34495e 100%); height: 100vh; position: fixed; color: white; overflow-y: auto; }
        .sidebar-brand { padding: 25px 20px; text-align: center; border-bottom: 1px solid #34495e; font-weight: bold; font-size: 20px; }
        .profile-section { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .img-nav { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent); margin-bottom: 10px; }
        .nav-link { display: block; padding: 12px 20px; color: #bdc3c7; text-decoration: none; border-left: 4px solid transparent; transition: 0.3s; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: #34495e; color: white; border-left: 4px solid var(--accent); }
        .main-content { margin-left: var(--sidebar-w); padding: 30px; }
        .card-custom { background: white; border-radius: 10px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }

        /* === STAT CARDS === */
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: 0.3s; height: 100%; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
        .stat-value { font-size: 30px; font-weight: 800; margin: 8px 0 4px; }
        .stat-label { font-size: 12px; color: #7f8c8d; }

        /* === FILTER BAR === */
        .filter-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 16px; }
        .filter-btn { padding: 6px 16px; border: 2px solid #ddd; border-radius: 20px; background: white; cursor: pointer; font-size: 13px; font-weight: 600; color: #7f8c8d; transition: 0.2s; text-decoration: none; display: inline-block; }
        .filter-btn:hover { border-color: #3498db; color: #3498db; }
        .filter-btn.active { background: #3498db; border-color: #3498db; color: white; }
        .filter-btn.active.green { background: #27ae60; border-color: #27ae60; }
        .filter-btn.active.purple { background: #8e44ad; border-color: #8e44ad; }
        .filter-btn.active.orange { background: #e67e22; border-color: #e67e22; }
        .rentang-wrap { display: flex; align-items: center; gap: 6px; }
        .rentang-wrap input[type="date"] { border: 2px solid #ddd; border-radius: 8px; padding: 5px 10px; font-size: 13px; outline: none; }
        .rentang-wrap input[type="date"]:focus { border-color: #3498db; }
        .rentang-wrap button { padding: 6px 14px; background: #2c3e50; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; }

        /* === SHIFT INFO === */
        .shift-info-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 12px; }
        .shift-stat-box { background: white; border-radius: 10px; padding: 14px 10px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #eee; }
        .shift-stat-box .sv { font-size: 20px; font-weight: 800; color: #2c3e50; margin-top: 4px; }
        .shift-stat-box .sl { font-size: 11px; color: #7f8c8d; }
        .shift-stat-box .si { font-size: 22px; }

        /* === CABANG STATUS === */
        .cabang-badge { display: inline-flex; align-items: center; gap: 7px; padding: 5px 14px; border-radius: 20px; font-weight: 700; font-size: 13px; }
        .cabang-buka  { background: #d5f5e3; color: #1e8449; border: 2px solid #27ae60; }
        .cabang-tutup { background: #fde8e8; color: #c0392b; border: 2px solid #e74c3c; }
        .dot-buka  { width:9px;height:9px;border-radius:50%;background:#27ae60;animation:pulseDot 1.5s infinite; }
        .dot-tutup { width:9px;height:9px;border-radius:50%;background:#e74c3c; }
        @keyframes pulseDot { 0%,100%{opacity:1;}50%{opacity:0.3;} }

        /* === BED GRID === */
        .bed-grid-big { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px,1fr)); gap: 12px; }
        .bed-box { background: white; border-radius: 12px; padding: 14px 10px; text-align: center; border: 2px solid #ecf0f1; box-shadow: 0 2px 8px rgba(0,0,0,0.04); min-height: 120px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; }
        .bed-box.available  { border-color: #27ae60; background: linear-gradient(135deg,#f0fdf4,#eafaf1); }
        .bed-box.occupied   { border-color: #e74c3c; background: linear-gradient(135deg,#fff5f5,#fdedec); }
        .bed-box.overtime   { border-color: #e67e22; background: linear-gradient(135deg,#fff8f0,#fef9e7); animation: pulseOT 1.5s infinite; }
        .bed-box.waiting-payment { border-color: #9b59b6; background: linear-gradient(135deg,#faf0ff,#f4ecf7); }
        @keyframes pulseOT { 0%,100%{box-shadow:0 0 0 0 rgba(230,126,34,.4);}50%{box-shadow:0 0 0 10px rgba(230,126,34,0);} }
        .bed-icon { font-size: 26px; }
        .bed-num  { font-size: 17px; font-weight: 800; color: #2c3e50; }
        .bed-tipe { font-size: 10px; color: #7f8c8d; }
        .bed-info { font-size: 10px; line-height: 1.4; text-align: center; width: 100%; }
        .bed-legend { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; padding: 8px 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 14px; }

        /* === CHART TOGGLE === */
        .chart-toggle-btn { padding: 4px 14px; border: 2px solid #ddd; border-radius: 20px; background: white; cursor: pointer; font-size: 12px; font-weight: bold; transition: 0.2s; color: #7f8c8d; }
        .chart-toggle-btn.active.green { background: #27ae60; border-color: #27ae60; color: white; }
        .chart-toggle-btn.active.blue  { background: #3498db; border-color: #3498db; color: white; }

        /* === STANDBY TABLE === */
        .terapis-standby-wrap { max-height: 280px; overflow-y: auto; }
        .terapis-standby-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f2f6; }
        .terapis-standby-row:last-child { border-bottom: none; }
        .terapis-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .terapis-name { font-weight: 600; font-size: 13px; color: #2c3e50; }
        .terapis-meta { font-size: 11px; color: #7f8c8d; }
        .badge-standby { padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: 600; flex-shrink: 0; }
        .badge-avail { background: #d5f5e3; color: #1e8449; }
        .badge-busy  { background: #fde8e8; color: #c0392b; }

        /* === KERJA BADGE === */
        .kerja-chip { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; flex-shrink: 0; }
        .kerja-chip-0    { background: #f1f2f6; color: #7f8c8d; }
        .kerja-chip-low  { background: #ebf5fb; color: #2980b9; }
        .kerja-chip-mid  { background: #eafaf1; color: #1e8449; }
        .kerja-chip-high { background: #fef9e7; color: #d35400; }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-brand"><i class="bi bi-building"></i> LEADER PANEL</div>
    <div class="profile-section">
        <img src="<?= $fotoPath ?>" class="img-nav">
        <div style="font-weight:bold;margin-top:10px;"><?= htmlspecialchars($userMe['nama_lengkap']) ?></div>
        <small style="color:#95a5a6;"><?= htmlspecialchars($namaCabang) ?></small>
    </div>
    <div class="nav-menu" style="padding:10px 0;">
        <a href="dashboard_leader.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="data_terapis_leader.php" class="nav-link"><i class="bi bi-people"></i> Data Terapis</a>
        <a href="stok_barang_leader.php" class="nav-link"><i class="bi bi-box-seam"></i> Stok Barang</a>
        <a href="monitoring_terapis.php" class="nav-link"><i class="bi bi-eye"></i> Monitoring</a>
        <a href="pelanggaran_terapis.php" class="nav-link"><i class="bi bi-exclamation-triangle"></i> Pelanggaran</a>
        <a href="profil_leader.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
    </div>
    <div style="padding:20px;margin-top:auto;"><a href="../auth/logout_system.php" class="btn btn-danger w-100"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</div>

<div class="main-content">

    <div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h2 class="mb-1"><i class="bi bi-speedometer2 text-primary"></i> Dashboard Leader</h2>
            <p class="text-muted mb-0">Shift mulai: <?= substr($jamMulai,0,5) ?> &nbsp;|&nbsp; Bisnis: <strong><?= date('d M Y', strtotime($tglBisnis)) ?></strong></p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($cabangBuka): ?>
            <div class="cabang-badge cabang-buka"><div class="dot-buka"></div> Cabang Buka</div>
            <?php else: ?>
            <div class="cabang-badge cabang-tutup"><div class="dot-tutup"></div> Cabang Tutup</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($emptyStocks)): ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4">
        <h6 class="alert-heading mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Stok Barang Kritis!</h6>
        <ul class="mb-0 mt-1">
            <?php foreach ($emptyStocks as $item): ?>
            <li><?= htmlspecialchars($item) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="card-custom mb-4" style="border-left:4px solid <?= $cabangBuka ? '#27ae60' : '#bdc3c7' ?>;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">&#128336; Info Shift Sekarang</h5>
            <?php if ($shiftAktif): ?>
            <small class="text-muted">&#128994; Aktif sejak <?= date('H:i', strtotime($shiftAktif['waktu_masuk'])) ?></small>
            <?php else: ?>
            <small class="text-danger">&#128308; Shift belum dibuka</small>
            <?php endif; ?>
        </div>
        <?php if ($shiftAktif): ?>
        <div class="shift-info-grid">
            <div class="shift-stat-box" style="border-top:3px solid #3498db;">
                <div class="si">&#128100;</div>
                <div class="sv" style="font-size:13px;word-break:break-word;"><?= htmlspecialchars($shiftAktif['nama_kasir']) ?></div>
                <div class="sl">Kasir Aktif</div>
            </div>
            <div class="shift-stat-box" style="border-top:3px solid #27ae60;">
                <div class="si">&#128176;</div>
                <div class="sv">Rp<?= number_format($omsetShift/1000,0) ?>k</div>
                <div class="sl">Omset Shift</div>
            </div>
            <div class="shift-stat-box" style="border-top:3px solid #9b59b6;">
                <div class="si">&#128203;</div>
                <div class="sv"><?= $trxShift ?></div>
                <div class="sl">Transaksi Shift</div>
            </div>
            <div class="shift-stat-box" style="border-top:3px solid #e74c3c;">
                <div class="si">&#129718;</div>
                <div class="sv"><?= $countTerisi ?></div>
                <div class="sl">Bed Terisi</div>
            </div>
            <div class="shift-stat-box" style="border-top:3px solid #2ecc71;">
                <div class="si">&#128719;</div>
                <div class="sv"><?= $countKosong ?></div>
                <div class="sl">Bed Kosong</div>
            </div>
        </div>
        <div style="padding:8px 12px;background:#f8f9fa;border-radius:8px;font-size:13px;color:#7f8c8d;">
            &#127968; Cabang: <strong><?= htmlspecialchars($namaCabang) ?></strong>
            &nbsp;|&nbsp; &#127775; Shift: <strong style="color:<?= $shiftColor ?>"><?= $shiftLabel ?></strong>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:20px;color:#7f8c8d;">
            &#128274; Belum ada shift yang dibuka oleh kasir hari ini.
        </div>
        <?php endif; ?>
    </div>

    <div class="card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">&#128202; Statistik &mdash; <span style="color:#3498db;font-size:14px;"><?= $statLabel ?></span></h5>
        </div>

        <div class="filter-bar">
            <a href="?filter=harian" class="filter-btn <?= $filterType==='harian'?'active':'' ?>">&#128336; Harian</a>
            <a href="?filter=bulanan" class="filter-btn <?= $filterType==='bulanan'?'active green':'' ?>">&#128197; Bulanan</a>
            <a href="?filter=tahunan" class="filter-btn <?= $filterType==='tahunan'?'active purple':'' ?>">&#128200; Tahunan</a>
            <div class="rentang-wrap">
                <form method="GET" style="display:flex;align-items:center;gap:6px;">
                    <input type="hidden" name="filter" value="rentang">
                    <input type="date" name="tgl_start" value="<?= htmlspecialchars($filterStart ?: date('Y-m-01')) ?>">
                    <span style="font-size:12px;color:#7f8c8d;">s/d</span>
                    <input type="date" name="tgl_end" value="<?= htmlspecialchars($filterEnd ?: date('Y-m-d')) ?>">
                    <button type="submit" class="<?= $filterType==='rentang'?'':''; ?>">&#128269; Tampilkan</button>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="stat-card" style="border-top:4px solid #27ae60;">
                    <div class="stat-label">&#128176; Total Omset</div>
                    <div class="stat-value text-success">Rp <?= number_format($omsetStat,0,',','.') ?></div>
                    <small class="text-muted">Transaksi selesai</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="border-top:4px solid #3498db;">
                    <div class="stat-label">&#128203; Total Transaksi</div>
                    <div class="stat-value text-primary"><?= number_format($trxStat,0,',','.') ?></div>
                    <small class="text-muted">Tidak termasuk batal</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="border-top:4px solid #9b59b6;">
                    <div class="stat-label">&#128101; Total Customer</div>
                    <div class="stat-value text-info"><?= number_format($custStat,0,',','.') ?></div>
                    <small class="text-muted">Unik berdasarkan nama</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">&#129718; Status Bed
                <small class="text-muted fw-normal ms-2" style="font-size:13px;"><?= $countKosong ?> Kosong | <?= $countTerisi ?> Terisi | <?= count($beds) ?> Total</small>
            </h5>
            <a href="monitoring_terapis.php" style="font-size:12px;color:#3498db;text-decoration:none;font-weight:600;">
                <i class="bi bi-pencil-square"></i> Kelola Bed
            </a>
        </div>
        <div class="bed-legend">
            <div><span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#27ae60;margin-right:4px;vertical-align:middle;"></span>Kosong</div>
            <div><span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#e74c3c;margin-right:4px;vertical-align:middle;"></span>Terisi</div>
            <div><span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#e67e22;margin-right:4px;vertical-align:middle;"></span>Overtime</div>
            <div><span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#9b59b6;margin-right:4px;vertical-align:middle;"></span>Menunggu Bayar</div>
        </div>
        <?php if (empty($beds)): ?>
        <div class="text-center text-muted py-4">
            <i class="bi bi-grid" style="font-size:40px;opacity:0.3;display:block;margin-bottom:8px;"></i>
            Belum ada bed. <a href="monitoring_terapis.php">Tambah bed</a> melalui halaman Monitoring.
        </div>
        <?php else: ?>
        <div class="bed-grid-big">
            <?php foreach ($beds as $b):
                $isOcc   = ($b['is_occupied'] > 0);
                $isWait  = ($b['trx_status'] ?? '') === 'menunggu_pembayaran';
                $isOT    = ($isOcc && !$isWait && !empty($b['finish_time']) && strtotime($b['finish_time']) <= time());
                if ($isWait)      $cls = 'waiting-payment';
                elseif ($isOT)   $cls = 'overtime';
                elseif ($isOcc)  $cls = 'occupied';
                else              $cls = 'available';
            ?>
            <div class="bed-box <?= $cls ?>">
                <div class="bed-icon">
                    <?php if ($isWait): ?>&#128176;
                    <?php elseif ($isOcc): ?>&#128134;
                    <?php else: ?>&#129718;
                    <?php endif; ?>
                </div>
                <div class="bed-num"><?= htmlspecialchars($b['nomor_bed']) ?></div>
                <div class="bed-tipe"><?= htmlspecialchars($b['tipe']) ?></div>
                <?php if ($isOcc): ?>
                <div class="bed-info">
                    <span style="font-weight:700;color:#c0392b;"><?= htmlspecialchars(mb_substr($b['customer_name']??'-',0,13)) ?></span>
                    <?php if ($b['terapis_name']): ?><br><span style="color:#7f8c8d;font-size:9px;"><?= htmlspecialchars(mb_substr($b['terapis_name'],0,13)) ?></span><?php endif; ?>
                    <?php if ($isWait): ?><br><span style="color:#9b59b6;font-size:9px;font-weight:700;">&#128176; BAYAR</span>
                    <?php elseif ($isOT): ?><br><span style="color:#e67e22;font-size:9px;font-weight:700;">&#9888; OT</span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="bed-info" style="color:#27ae60;font-weight:600;">Kosong</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="row mb-4">
        <div class="col-lg-7 mb-4">
            <div class="card-custom" style="height:100%;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">&#128200; Grafik 7 Hari Terakhir</h5>
                    <div style="display:flex;gap:8px;">
                        <button class="chart-toggle-btn active green" id="btnOmset" onclick="switchChart('omset')">&#128176; Omset</button>
                        <button class="chart-toggle-btn blue" id="btnTrx" onclick="switchChart('transaksi')">&#128203; Transaksi</button>
                    </div>
                </div>
                <div style="height:200px;"><canvas id="mainChart"></canvas></div>
            </div>
        </div>

        <div class="col-lg-5 mb-4">
            <div class="card-custom" style="height:100%;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">&#128134; Terapis Hadir Hari Ini</h5>
                    <span class="badge bg-primary"><?= count($terapisStandby) ?> Orang</span>
                </div>
                <?php if (empty($terapisStandby)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-person-x" style="font-size:36px;opacity:0.3;display:block;margin-bottom:8px;"></i>
                    Belum ada terapis yang absen hari ini.
                </div>
                <?php else: ?>
                <div class="terapis-standby-wrap">
                    <?php foreach ($terapisStandby as $ts):
                        $fotoTs = !empty($ts['foto_profil']) ? "../uploads/profil/".$ts['foto_profil'] : "../assets/img/default-avatar.png";
                        $isBusy = ($ts['sedang_melayani'] > 0);
                        $kerjaTs = (int)($ts['total_kerja_hari_ini'] ?? 0);
                        if ($kerjaTs === 0)     $kerjaChip = 'kerja-chip-0';
                        elseif ($kerjaTs <= 2)  $kerjaChip = 'kerja-chip-low';
                        elseif ($kerjaTs <= 5)  $kerjaChip = 'kerja-chip-mid';
                        else                    $kerjaChip = 'kerja-chip-high';
                    ?>
                    <div class="terapis-standby-row">
                        <img src="<?= $fotoTs ?>" class="terapis-avatar" alt="">
                        <div style="flex:1;min-width:0;">
                            <div class="terapis-name text-truncate"><?= htmlspecialchars($ts['nama_lengkap']) ?></div>
                            <div class="terapis-meta">
                                Absen: <?= $ts['jam_absen'] ? date('H:i', strtotime($ts['jam_absen'])) : '-' ?>
                                <?php if ($ts['shift_type']): ?> &middot; <?= ucfirst($ts['shift_type']) ?><?php endif; ?>
                            </div>
                            <?php if ($isBusy && $ts['customer_name']): ?>
                            <div class="terapis-meta" style="color:#e74c3c;">&#128134; <?= htmlspecialchars(mb_substr($ts['customer_name'],0,18)) ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
                            <span class="badge-standby <?= $isBusy ? 'badge-busy' : 'badge-avail' ?>">
                                <?= $isBusy ? '&#128134; Sibuk' : '&#128994; Standby' ?>
                            </span>
                            <span class="kerja-chip <?= $kerjaChip ?>" title="Jumlah pelanggan hari ini">
                                &#9996; <?= $kerjaTs ?>x
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php foreach ($terapisIzinLdr as $tiz):
                        $fotoIz = !empty($tiz['foto_profil']) ? "../uploads/profil/".$tiz['foto_profil'] : "../assets/img/default-avatar.png";
                    ?>
                    <div class="terapis-standby-row">
                        <img src="<?= $fotoIz ?>" class="terapis-avatar" alt="">
                        <div style="flex:1;min-width:0;">
                            <div class="terapis-name text-truncate"><?= htmlspecialchars($tiz['nama_lengkap']) ?></div>
                            <div class="terapis-meta">
                                <?= ucfirst($tiz['jenis']) ?>
                                <?php if ($tiz['status'] === 'pending'): ?> &middot; <span style="color:#e67e22;">Menunggu</span><?php else: ?> &middot; <span style="color:#27ae60;">Disetujui</span><?php endif; ?>
                            </div>
                        </div>
                        <span class="badge-standby" style="background:<?= $tiz['jenis']==='sakit' ? '#fde8e8' : '#fef3e7' ?>;color:<?= $tiz['jenis']==='sakit' ? '#c0392b' : '#e67e22' ?>;">
                            <?= $tiz['jenis']==='sakit' ? '&#129298; Sakit' : '&#128232; Izin' ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div style="padding-top:10px;border-top:1px solid #f1f2f6;margin-top:10px;text-align:right;">
                    <a href="monitoring_terapis.php" style="font-size:12px;color:#3498db;text-decoration:none;">Lihat Detail &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <?php if (count($terapisKeluar) > 0): ?>
    <div class="card-custom mb-4" style="border-left:4px solid #f39c12;">
        <h5 class="mb-3"><i class="bi bi-arrow-right-circle text-warning"></i> Terapis Sedang Dipinjam ke Cabang Lain</h5>
        <div class="table-responsive">
            <table class="table table-hover" style="font-size:13px;">
                <thead class="table-light"><tr><th>Nama</th><th>Ke Cabang</th><th>Sejak</th></tr></thead>
                <tbody>
                    <?php foreach ($terapisKeluar as $tk): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($tk['nama_lengkap']) ?></strong></td>
                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($tk['nama_cabang']) ?></span></td>
                        <td><?= date('d/m H:i', strtotime($tk['approved_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="card-custom">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-clock-history text-primary"></i> Transaksi Terbaru</h5>
            <a href="riwayat_pendapatan.php" style="font-size:12px;color:#3498db;text-decoration:none;">Lihat Semua &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" style="font-size:13px;">
                <thead class="table-light">
                    <tr><th>Waktu</th><th>Customer</th><th>Terapis</th><th>Bed</th><th>Total</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTransactions as $trx): ?>
                    <tr>
                        <td><?= date('d/m H:i', strtotime($trx['created_at'])) ?></td>
                        <td><?= htmlspecialchars($trx['nama_pelanggan']) ?></td>
                        <td><?= htmlspecialchars($trx['nama_terapis'] ?? '-') ?></td>
                        <td>
                            <?php if ($trx['nomor_bed']): ?><span class="badge bg-info">Bed <?= $trx['nomor_bed'] ?></span>
                            <?php else: ?><span class="badge bg-secondary">Panggilan</span><?php endif; ?>
                        </td>
                        <td>Rp <?= number_format($trx['total_bayar'],0,',','.') ?></td>
                        <td>
                            <span class="badge bg-<?= $trx['status']=='selesai'?'success':($trx['status']=='proses'?'warning':'danger') ?>">
                                <?= ucfirst($trx['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentTransactions)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">Belum ada transaksi</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const chartLabels = <?= json_encode($labels) ?>;
    const chartOmset  = <?= json_encode($dataOmset) ?>;
    const chartTrx    = <?= json_encode($dataTrx) ?>;
    let mainChartInst = null;

    function buildChart(type) {
        const ctx = document.getElementById('mainChart').getContext('2d');
        if (mainChartInst) mainChartInst.destroy();
        const isOmset = (type === 'omset');
        mainChartInst = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: isOmset ? 'Omset (Rp)' : 'Jumlah Transaksi',
                    data: isOmset ? chartOmset : chartTrx,
                    borderColor: isOmset ? '#27ae60' : '#3498db',
                    backgroundColor: isOmset ? 'rgba(39,174,96,0.1)' : 'rgba(52,152,219,0.1)',
                    borderWidth: 2, fill: true, tension: 0.4,
                    pointBackgroundColor: isOmset ? '#27ae60' : '#3498db',
                    pointRadius: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => isOmset
                                ? ' Rp ' + ctx.parsed.y.toLocaleString('id-ID')
                                : ' ' + ctx.parsed.y + ' transaksi'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: { callback: v => isOmset ? 'Rp' + (v/1000).toFixed(0) + 'k' : v, font: { size: 11 } }
                    },
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                }
            }
        });
    }

    function switchChart(type) {
        document.getElementById('btnOmset').className = 'chart-toggle-btn' + (type==='omset' ? ' active green' : '');
        document.getElementById('btnTrx').className   = 'chart-toggle-btn' + (type==='transaksi' ? ' active blue' : '');
        buildChart(type);
    }

    buildChart('omset');
</script>
</body>
</html>