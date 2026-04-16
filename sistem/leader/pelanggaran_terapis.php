<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'leader') {
    header("Location: ../auth/login.php");
    exit;
}

$userId   = $_SESSION['user_id'];
$branchId = $_SESSION['user_branch_id'];

// ─────────────────────────────────────────────────
// BUAT TABEL pelanggaran JIKA BELUM ADA
// ─────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `pelanggaran` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `terapis_id`    INT NOT NULL,
    `branch_id`     INT NOT NULL,
    `kategori`      ENUM('keterlambatan','mangkir','perilaku','atribut','lainnya') NOT NULL DEFAULT 'lainnya',
    `judul`         VARCHAR(200) NOT NULL,
    `deskripsi`     TEXT,
    `tanggal`       DATE NOT NULL,
    `waktu_kejadian` TIME DEFAULT NULL,
    `referensi_absen_id` INT DEFAULT NULL COMMENT 'link ke terapis_attendance.id',
    `status`        ENUM('aktif','selesai','dibatalkan') NOT NULL DEFAULT 'aktif',
    `catatan_leader` TEXT DEFAULT NULL,
    `created_by`    INT NOT NULL,
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_terapis` (`terapis_id`),
    INDEX `idx_branch`  (`branch_id`),
    INDEX `idx_tanggal` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");

// ─────────────────────────────────────────────────
// SETTINGS & TANGGAL BISNIS
// ─────────────────────────────────────────────────
$settings  = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
$jamMulai  = $settings['jam_mulai_hari'] ?? '08:00:00';
$shiftPagiStart = $settings['shift_pagi_start'] ?? '08:00:00';
$shiftPagiEnd   = $settings['shift_pagi_end']   ?? '14:00:00';
$tglBisnis = (date('H:i:s') < $jamMulai) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

// ─────────────────────────────────────────────────
// DATA LEADER
// ─────────────────────────────────────────────────
$stmtUser = $pdo->prepare("SELECT u.*, b.nama_cabang FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
$stmtUser->execute([$userId]);
$userMe     = $stmtUser->fetch();
$fotoPath   = !empty($userMe['foto_profil']) ? "../uploads/profil/" . $userMe['foto_profil'] : "../assets/img/default-avatar.png";
$namaCabang = $userMe['nama_cabang'] ?? 'Cabang';

// ─────────────────────────────────────────────────
// SINKRON OTOMATIS: Tarik keterlambatan dari terapis_attendance
// yang belum ada di tabel pelanggaran
// ─────────────────────────────────────────────────
$stmtSyncCheck = $pdo->prepare(
    "SELECT ta.id, ta.terapis_id, ta.tanggal, ta.waktu_absen, ta.shift_type, ta.alasan_terlambat
     FROM terapis_attendance ta
     WHERE ta.branch_id = ?
       AND ta.status_kehadiran = 'terlambat'
       AND ta.id NOT IN (
           SELECT referensi_absen_id FROM pelanggaran
           WHERE referensi_absen_id IS NOT NULL AND branch_id = ?
       )
     ORDER BY ta.tanggal DESC"
);
$stmtSyncCheck->execute([$branchId, $branchId]);
$newLate = $stmtSyncCheck->fetchAll();

foreach ($newLate as $la) {
    $shift      = strtoupper($la['shift_type'] ?? 'PAGI');
    $alasan     = $la['alasan_terlambat'] ? htmlspecialchars($la['alasan_terlambat']) : 'Tidak ada alasan';
    $jamAbsen   = $la['waktu_absen'] ? date('H:i', strtotime($la['waktu_absen'])) : '-';
    $judul      = "Keterlambatan Absen Shift {$shift}";
    $deskripsi  = "Terapis absen terlambat pada pukul {$jamAbsen}. Alasan: {$alasan}";
    $waktu      = $la['waktu_absen'] ? date('H:i:s', strtotime($la['waktu_absen'])) : null;

    $pdo->prepare(
        "INSERT INTO pelanggaran (terapis_id, branch_id, kategori, judul, deskripsi, tanggal, waktu_kejadian, referensi_absen_id, status, created_by)
         VALUES (?, ?, 'keterlambatan', ?, ?, ?, ?, ?, 'aktif', ?)"
    )->execute([$la['terapis_id'], $branchId, $judul, $deskripsi, $la['tanggal'], $waktu, $la['id'], $userId]);
}

// ─────────────────────────────────────────────────
// POST HANDLER – Tambah Pelanggaran Manual
// ─────────────────────────────────────────────────
$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $terapisId  = (int)($_POST['terapis_id'] ?? 0);
        $kategori   = $_POST['kategori'] ?? 'lainnya';
        $judul      = trim($_POST['judul'] ?? '');
        $deskripsi  = trim($_POST['deskripsi'] ?? '');
        $tanggal    = $_POST['tanggal'] ?? $tglBisnis;
        $waktuKej   = $_POST['waktu_kejadian'] ?: null;

        $allowedKat = ['keterlambatan','mangkir','perilaku','atribut','lainnya'];
        if (!$terapisId) {
            $flash = ['type'=>'danger','msg'=>'Pilih terapis terlebih dahulu.'];
        } elseif (!$judul) {
            $flash = ['type'=>'danger','msg'=>'Judul pelanggaran tidak boleh kosong.'];
        } elseif (!in_array($kategori, $allowedKat)) {
            $flash = ['type'=>'danger','msg'=>'Kategori tidak valid.'];
        } else {
            $pdo->prepare(
                "INSERT INTO pelanggaran (terapis_id, branch_id, kategori, judul, deskripsi, tanggal, waktu_kejadian, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'aktif', ?)"
            )->execute([$terapisId, $branchId, $kategori, $judul, $deskripsi, $tanggal, $waktuKej, $userId]);
            $flash = ['type'=>'success','msg'=>'Pelanggaran berhasil dicatat.'];
        }
    }

    if ($action === 'update_status') {
        $pelId   = (int)($_POST['pel_id'] ?? 0);
        $status  = $_POST['status'] ?? '';
        $catatan = trim($_POST['catatan_leader'] ?? '');
        $allowedStatus = ['aktif','selesai','dibatalkan'];
        if ($pelId && in_array($status, $allowedStatus)) {
            $pdo->prepare(
                "UPDATE pelanggaran SET status=?, catatan_leader=? WHERE id=? AND branch_id=?"
            )->execute([$status, $catatan ?: null, $pelId, $branchId]);
            $flash = ['type'=>'success','msg'=>'Status pelanggaran diperbarui.'];
        }
    }

    if ($action === 'hapus') {
        $pelId = (int)($_POST['pel_id'] ?? 0);
        if ($pelId) {
            $pdo->prepare("DELETE FROM pelanggaran WHERE id=? AND branch_id=?")->execute([$pelId, $branchId]);
            $flash = ['type'=>'success','msg'=>'Pelanggaran berhasil dihapus.'];
        }
    }
}

// ─────────────────────────────────────────────────
// FILTER
// ─────────────────────────────────────────────────
$fKategori  = $_GET['kategori']   ?? '';
$fStatus    = $_GET['status_f']   ?? '';
$fTerapis   = (int)($_GET['terapis_f'] ?? 0);
$fBulan     = $_GET['bulan']      ?? '';  // format YYYY-MM
$fCari      = trim($_GET['cari']  ?? '');

// ─────────────────────────────────────────────────
// QUERY DATA PELANGGARAN
// ─────────────────────────────────────────────────
$where   = ["p.branch_id = ?"];
$params  = [$branchId];

if ($fKategori) { $where[] = "p.kategori = ?"; $params[] = $fKategori; }
if ($fStatus)   { $where[] = "p.status = ?";   $params[] = $fStatus;   }
if ($fTerapis)  { $where[] = "p.terapis_id = ?"; $params[] = $fTerapis; }
if ($fBulan)    { $where[] = "DATE_FORMAT(p.tanggal,'%Y-%m') = ?"; $params[] = $fBulan; }
if ($fCari)     { $where[] = "(u.nama_lengkap LIKE ? OR p.judul LIKE ? OR p.deskripsi LIKE ?)"; $params[] = "%$fCari%"; $params[] = "%$fCari%"; $params[] = "%$fCari%"; }

$whereSQL = implode(' AND ', $where);

$sqlData = "SELECT p.*, u.nama_lengkap, u.foto_profil,
            cb.nama_lengkap as created_by_name
            FROM pelanggaran p
            JOIN users u ON p.terapis_id = u.id
            LEFT JOIN users cb ON p.created_by = cb.id
            WHERE $whereSQL
            ORDER BY p.tanggal DESC, p.created_at DESC";
$stmtData = $pdo->prepare($sqlData);
$stmtData->execute($params);
$pelanggaran = $stmtData->fetchAll();

// ─────────────────────────────────────────────────
// STATISTIK
// ─────────────────────────────────────────────────
$stmtStat = $pdo->prepare(
    "SELECT
        COUNT(*) as total,
        SUM(kategori='keterlambatan') as terlambat,
        SUM(kategori='mangkir') as mangkir,
        SUM(kategori='perilaku') as perilaku,
        SUM(kategori='atribut') as atribut,
        SUM(kategori='lainnya') as lainnya,
        SUM(status='aktif') as aktif,
        SUM(status='selesai') as selesai,
        SUM(DATE_FORMAT(tanggal,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')) as bulan_ini
     FROM pelanggaran WHERE branch_id = ?"
);
$stmtStat->execute([$branchId]);
$stat = $stmtStat->fetch();

// ─────────────────────────────────────────────────
// LIST TERAPIS UNTUK DROPDOWN
// ─────────────────────────────────────────────────
$stmtTerapis = $pdo->prepare("SELECT id, nama_lengkap FROM users WHERE home_branch_id=? AND role='terapis' ORDER BY nama_lengkap ASC");
$stmtTerapis->execute([$branchId]);
$listTerapis = $stmtTerapis->fetchAll();

// ─────────────────────────────────────────────────
// REKAPITULASI PER TERAPIS (top offenders bulan ini)
// ─────────────────────────────────────────────────
$stmtRekap = $pdo->prepare(
    "SELECT u.id, u.nama_lengkap, u.foto_profil,
     COUNT(*) as total_pelanggaran,
     SUM(p.kategori='keterlambatan') as terlambat,
     SUM(p.kategori='mangkir') as mangkir,
     SUM(p.kategori='perilaku') as perilaku,
     SUM(p.status='aktif') as belum_selesai,
     MAX(p.tanggal) as terakhir
     FROM pelanggaran p
     JOIN users u ON p.terapis_id = u.id
     WHERE p.branch_id = ? AND DATE_FORMAT(p.tanggal,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')
     GROUP BY p.terapis_id
     ORDER BY total_pelanggaran DESC
     LIMIT 10"
);
$stmtRekap->execute([$branchId]);
$rekap = $stmtRekap->fetchAll();

// helper
$katLabel = [
    'keterlambatan' => ['label'=>'Keterlambatan','color'=>'#e67e22','bg'=>'#fef3e7','icon'=>'&#9201;'],
    'tolak_pasien'  => ['label'=>'Tolak Pasien','color'=>'#d35400','bg'=>'#fdebd0','icon'=>'&#128683;'],
    'alpha'         => ['label'=>'Alpha (Tidak Hadir)','color'=>'#c0392b','bg'=>'#fadbd8','icon'=>'&#10060;'],
    'mangkir'       => ['label'=>'Mangkir/Alpha','color'=>'#e74c3c','bg'=>'#fde8e8','icon'=>'&#10060;'],
    'perilaku'      => ['label'=>'Perilaku','color'=>'#9b59b6','bg'=>'#f4ecf7','icon'=>'&#128544;'],
    'atribut'       => ['label'=>'Atribut/Seragam','color'=>'#3498db','bg'=>'#eaf3fc','icon'=>'&#128084;'],
    'lainnya'       => ['label'=>'Lainnya','color'=>'#7f8c8d','bg'=>'#f1f2f6','icon'=>'&#128203;'],
];
$statusLabel = [
    'aktif'      => ['label'=>'Aktif','color'=>'#e74c3c','bg'=>'#fde8e8'],
    'selesai'    => ['label'=>'Selesai','color'=>'#27ae60','bg'=>'#d5f5e3'],
    'dibatalkan' => ['label'=>'Dibatalkan','color'=>'#95a5a6','bg'=>'#f1f2f6'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelanggaran Terapis - Leader Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        :root { --sidebar-w: 250px; --primary: #2c3e50; --accent: #3498db; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; margin: 0; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(180deg, var(--primary) 0%, #34495e 100%);
            height: 100vh; position: fixed; left: 0; top: 0;
            color: white; overflow-y: auto; z-index: 100;
            display: flex; flex-direction: column;
        }
        .sidebar-brand { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: bold; font-size: 20px; }
        .profile-section { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .img-nav { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent); margin-bottom: 10px; }
        .nav-menu { padding: 10px 0; flex: 1; }
        .nav-link-custom {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 20px; color: #bdc3c7; text-decoration: none;
            font-size: 14px; transition: all 0.3s; border-left: 4px solid transparent;
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background: rgba(255,255,255,0.08); color: white; border-left: 4px solid var(--accent);
        }
        .nav-link-custom i { font-size: 16px; width: 20px; text-align: center; }
        .nav-section-title { padding: 12px 20px 4px; font-size: 10px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }

        /* ── MAIN ── */
        .main-content { margin-left: var(--sidebar-w); padding: 30px; min-height: 100vh; }

        /* ── CARDS ── */
        .card-custom { background: white; border-radius: 12px; padding: 22px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 22px; }

        /* ── STAT MINI ── */
        .stat-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 14px; margin-bottom: 22px; }
        .stat-mini { background: white; border-radius: 12px; padding: 18px 14px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.06); transition: 0.25s; cursor: default; }
        .stat-mini:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
        .stat-mini .sv { font-size: 28px; font-weight: 800; }
        .stat-mini .sl { font-size: 11px; color: #7f8c8d; margin-top: 2px; }
        .stat-mini .si { font-size: 22px; line-height: 1.2; }

        /* ── FILTER BAR ── */
        .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; padding: 16px 20px; background: #f8f9fa; border-radius: 10px; margin-bottom: 18px; }
        .filter-bar select, .filter-bar input[type="text"], .filter-bar input[type="month"] {
            border: 2px solid #e0e0e0; border-radius: 8px; padding: 7px 12px;
            font-size: 13px; background: white; outline: none; transition: 0.2s;
        }
        .filter-bar select:focus, .filter-bar input:focus { border-color: var(--accent); }
        .filter-bar label { font-size: 11px; font-weight: 700; color: #7f8c8d; text-transform: uppercase; display: block; margin-bottom: 3px; }
        .btn-filter { padding: 8px 18px; background: var(--primary); color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-filter:hover { background: #34495e; }
        .btn-reset { padding: 8px 14px; background: white; color: #7f8c8d; border: 2px solid #ddd; border-radius: 8px; font-size: 13px; cursor: pointer; text-decoration: none; transition: 0.2s; }
        .btn-reset:hover { border-color: #bbb; color: #2c3e50; }

        /* ── TABLE ── */
        .table-pelanggaran { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table-pelanggaran thead th { background: #2c3e50; color: white; padding: 11px 14px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        .table-pelanggaran tbody tr { border-bottom: 1px solid #f1f2f6; transition: 0.15s; }
        .table-pelanggaran tbody tr:hover { background: #f8f9fa; }
        .table-pelanggaran tbody td { padding: 11px 14px; vertical-align: middle; }
        .kat-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .status-pill { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .avatar-sm { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .terapis-cell { display: flex; align-items: center; gap: 9px; }
        .terapis-cell .tname { font-weight: 700; color: #2c3e50; font-size: 13px; }

        /* ── ACTION BTNS IN TABLE ── */
        .btn-tbl { padding: 5px 10px; border: none; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-tbl:hover { opacity: 0.85; transform: scale(1.03); }
        .btn-tbl-green { background: #d5f5e3; color: #1e8449; }
        .btn-tbl-red   { background: #fde8e8; color: #c0392b; }
        .btn-tbl-gray  { background: #f1f2f6; color: #7f8c8d; }
        .btn-tbl-blue  { background: #eaf3fc; color: #2980b9; }

        /* ── REKAP CARD (TERAPIS TOP) ── */
        .rekap-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f2f6; }
        .rekap-row:last-child { border-bottom: none; }
        .rekap-rank { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: white; flex-shrink: 0; }
        .rank-1 { background: linear-gradient(135deg,#e74c3c,#c0392b); }
        .rank-2 { background: linear-gradient(135deg,#e67e22,#d35400); }
        .rank-3 { background: linear-gradient(135deg,#f39c12,#e67e22); }
        .rank-other { background: #95a5a6; }
        .rekap-info { flex: 1; min-width: 0; }
        .rekap-name { font-weight: 700; font-size: 13px; color: #2c3e50; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rekap-meta { font-size: 11px; color: #7f8c8d; margin-top: 2px; }
        .rekap-count { font-size: 20px; font-weight: 800; color: #e74c3c; flex-shrink: 0; }

        /* ── MODAL ── */
        .modal-overlay {
            display: none; position: fixed; top:0; left:0; width:100%; height:100%;
            background: rgba(0,0,0,0.55); z-index: 9999;
            justify-content: center; align-items: center;
        }
        .modal-overlay.show { display: flex; animation: fadeIn 0.2s; }
        @keyframes fadeIn { from{opacity:0}to{opacity:1} }
        .modal-box {
            background: white; border-radius: 16px; padding: 30px;
            max-width: 520px; width: 94%; box-shadow: 0 25px 70px rgba(0,0,0,0.25);
            animation: slideUp 0.25s;
        }
        @keyframes slideUp { from{transform:translateY(25px);opacity:0}to{transform:translateY(0);opacity:1} }
        .modal-box h5 { margin: 0 0 20px; color: #2c3e50; font-size: 17px; font-weight: 800; }
        .form-label-modal { font-size: 12px; font-weight: 700; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 4px; display: block; }
        .form-ctrl { width: 100%; padding: 9px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 13px; outline: none; transition: 0.2s; }
        .form-ctrl:focus { border-color: var(--accent); }
        .form-ctrl-sm { padding: 7px 10px; font-size: 13px; }
        .modal-footer-btns { display: flex; gap: 10px; margin-top: 22px; }
        .btn-save { flex: 1; padding: 12px; background: linear-gradient(135deg,#2c3e50,#34495e); color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; transition: 0.2s; }
        .btn-save:hover { opacity: 0.9; }
        .btn-cancel { padding: 12px 20px; background: #f1f2f6; color: #7f8c8d; border: none; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; transition: 0.2s; }
        .btn-cancel:hover { background: #e0e0e0; }

        /* ── FLASH MSG ── */
        .flash-msg { padding: 12px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
        .flash-success { background: #d5f5e3; color: #1e8449; border-left: 4px solid #27ae60; }
        .flash-danger  { background: #fde8e8; color: #c0392b; border-left: 4px solid #e74c3c; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 50px 20px; color: #bdc3c7; }
        .empty-state i { font-size: 52px; display: block; margin-bottom: 12px; opacity: 0.4; }

        /* ── SCROLL TABLE WRAPPER ── */
        .table-wrap { overflow-x: auto; border-radius: 10px; }
    </style>
</head>
<body>

<!-- ══════════════════════════ SIDEBAR ══════════════════════════ -->
<div class="sidebar">
    <div class="sidebar-brand"><i class="bi bi-building"></i> LEADER PANEL</div>
    <div class="profile-section">
        <img src="<?= $fotoPath ?>" class="img-nav" alt="Profil">
        <div style="font-weight:700; font-size:15px; margin-top:8px;"><?= htmlspecialchars($userMe['nama_lengkap']) ?></div>
        <small style="color:#95a5a6;"><?= htmlspecialchars($namaCabang) ?></small>
    </div>
    <div class="nav-menu">
        <a href="dashboard_leader.php"      class="nav-link-custom"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="data_terapis_leader.php"   class="nav-link-custom"><i class="bi bi-people"></i> Data Terapis</a>
        <a href="stok_barang_leader.php"    class="nav-link-custom"><i class="bi bi-box-seam"></i> Stok Barang</a>
        <a href="monitoring_terapis.php"    class="nav-link-custom"><i class="bi bi-eye"></i> Monitoring</a>
        <a href="pelanggaran_terapis.php"   class="nav-link-custom active"><i class="bi bi-exclamation-triangle"></i> Pelanggaran</a>
        <a href="profil_leader.php"         class="nav-link-custom"><i class="bi bi-person-circle"></i> Profil</a>
    </div>
    <div class="sidebar-footer">
        <a href="../auth/logout_system.php" class="btn btn-danger w-100">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<!-- ══════════════════════════ MAIN CONTENT ══════════════════════════ -->
<div class="main-content">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1" style="color:#2c3e50;"><i class="bi bi-exclamation-triangle-fill text-danger"></i> Pelanggaran Terapis</h2>
            <p class="text-muted mb-0">Pencatatan dan pemantauan pelanggaran terapis &mdash; <strong><?= htmlspecialchars($namaCabang) ?></strong></p>
        </div>
        <button class="btn btn-danger fw-bold px-4" onclick="showModal('modalTambah')">
            <i class="bi bi-plus-circle"></i> Catat Pelanggaran
        </button>
    </div>

    <!-- FLASH -->
    <?php if ($flash['msg']): ?>
    <div class="flash-msg flash-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
        <?= htmlspecialchars($flash['msg']) ?>
    </div>
    <?php endif; ?>

    <!-- STATISTIK -->
    <div class="stat-row">
        <div class="stat-mini" style="border-top:4px solid #e74c3c;">
            <div class="si">&#128680;</div>
            <div class="sv" style="color:#e74c3c;"><?= $stat['total'] ?></div>
            <div class="sl">Total Pelanggaran</div>
        </div>
        <div class="stat-mini" style="border-top:4px solid #e67e22;">
            <div class="si">&#9201;</div>
            <div class="sv" style="color:#e67e22;"><?= $stat['terlambat'] ?></div>
            <div class="sl">Keterlambatan</div>
        </div>
        <div class="stat-mini" style="border-top:4px solid #c0392b;">
            <div class="si">&#10060;</div>
            <div class="sv" style="color:#c0392b;"><?= $stat['mangkir'] ?></div>
            <div class="sl">Mangkir/Alpha</div>
        </div>
        <div class="stat-mini" style="border-top:4px solid #9b59b6;">
            <div class="si">&#128544;</div>
            <div class="sv" style="color:#9b59b6;"><?= $stat['perilaku'] ?></div>
            <div class="sl">Perilaku</div>
        </div>
        <div class="stat-mini" style="border-top:4px solid #3498db;">
            <div class="si">&#128084;</div>
            <div class="sv" style="color:#3498db;"><?= $stat['atribut'] ?></div>
            <div class="sl">Atribut</div>
        </div>
        <div class="stat-mini" style="border-top:4px solid #f39c12;">
            <div class="si">&#128680;</div>
            <div class="sv" style="color:#f39c12;"><?= $stat['aktif'] ?></div>
            <div class="sl">Belum Selesai</div>
        </div>
        <div class="stat-mini" style="border-top:4px solid #27ae60;">
            <div class="si">&#128197;</div>
            <div class="sv" style="color:#27ae60;"><?= $stat['bulan_ini'] ?></div>
            <div class="sl">Bulan Ini</div>
        </div>
    </div>

    <!-- KONTEN UTAMA: 2 KOLOM -->
    <div class="row g-4">

        <!-- KIRI: Tabel Pelanggaran -->
        <div class="col-xl-8">
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-list-ul text-danger"></i> Daftar Pelanggaran</h5>
                    <span class="badge bg-danger"><?= count($pelanggaran) ?> data</span>
                </div>

                <!-- FILTER -->
                <form method="GET" class="filter-bar">
                    <div>
                        <label>Cari</label>
                        <input type="text" name="cari" value="<?= htmlspecialchars($fCari) ?>" placeholder="Nama / judul..." style="min-width:160px;">
                    </div>
                    <div>
                        <label>Terapis</label>
                        <select name="terapis_f">
                            <option value="">Semua</option>
                            <?php foreach ($listTerapis as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $fTerapis==$t['id']?'selected':'' ?>>
                                <?= htmlspecialchars($t['nama_lengkap']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Kategori</label>
                        <select name="kategori">
                            <option value="">Semua</option>
                            <?php foreach ($katLabel as $k => $kv): ?>
                            <option value="<?= $k ?>" <?= $fKategori===$k?'selected':'' ?>><?= $kv['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="status_f">
                            <option value="">Semua</option>
                            <?php foreach ($statusLabel as $s => $sv): ?>
                            <option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= $sv['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Bulan</label>
                        <input type="month" name="bulan" value="<?= htmlspecialchars($fBulan) ?>">
                    </div>
                    <div class="d-flex gap-2 align-items-end">
                        <button type="submit" class="btn-filter"><i class="bi bi-search"></i> Cari</button>
                        <a href="pelanggaran_terapis.php" class="btn-reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>

                <!-- TABLE -->
                <div class="table-wrap">
                    <?php if (empty($pelanggaran)): ?>
                    <div class="empty-state">
                        <i class="bi bi-clipboard-check"></i>
                        <p class="fw-bold mb-1">Tidak ada data pelanggaran</p>
                        <small>Coba ubah filter atau catat pelanggaran baru.</small>
                    </div>
                    <?php else: ?>
                    <table class="table-pelanggaran">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Terapis</th>
                                <th>Kategori</th>
                                <th>Pelanggaran</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th style="text-align:center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no = 1; foreach ($pelanggaran as $p):
                            $kat    = $katLabel[$p['kategori']] ?? $katLabel['lainnya'];
                            $st     = $statusLabel[$p['status']] ?? $statusLabel['aktif'];
                            $foto   = !empty($p['foto_profil']) ? "../uploads/profil/".$p['foto_profil'] : "../assets/img/default-avatar.png";
                            $isOld  = ($p['referensi_absen_id'] ? true : false); // otomatis dari absensi
                        ?>
                        <tr>
                            <td style="color:#95a5a6;font-weight:700;"><?= $no++ ?></td>
                            <td>
                                <div class="terapis-cell">
                                    <img src="<?= $foto ?>" class="avatar-sm" alt="">
                                    <div>
                                        <div class="tname"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
                                        <?php if ($isOld): ?>
                                        <small style="color:#3498db; font-size:10px;"><i class="bi bi-link-45deg"></i> Auto-absensi</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="kat-badge" style="background:<?= $kat['bg'] ?>;color:<?= $kat['color'] ?>;">
                                    <?= $kat['icon'] ?> <?= $kat['label'] ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight:700; color:#2c3e50; font-size:13px; max-width:220px; line-height:1.4;">
                                    <?= htmlspecialchars($p['judul']) ?>
                                </div>
                                <?php if ($p['deskripsi']): ?>
                                <div style="font-size:11px; color:#7f8c8d; margin-top:3px; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?= htmlspecialchars(mb_substr($p['deskripsi'], 0, 80)) ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($p['waktu_kejadian']): ?>
                                <div style="font-size:10px; color:#3498db; margin-top:2px;">&#128336; <?= date('H:i', strtotime($p['waktu_kejadian'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <div style="font-weight:700; color:#2c3e50;"><?= date('d M Y', strtotime($p['tanggal'])) ?></div>
                                <div style="font-size:10px; color:#7f8c8d;">by <?= htmlspecialchars($p['created_by_name'] ?? 'Sistem') ?></div>
                            </td>
                            <td>
                                <span class="status-pill" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;">
                                    <?= $st['label'] ?>
                                </span>
                                <?php if ($p['catatan_leader']): ?>
                                <div style="font-size:10px; color:#7f8c8d; margin-top:3px; max-width:110px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($p['catatan_leader']) ?>">
                                    &#128203; <?= htmlspecialchars(mb_substr($p['catatan_leader'], 0, 30)) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:5px; justify-content:center; flex-wrap:wrap;">
                                    <button class="btn-tbl btn-tbl-blue"
                                        onclick="showDetail(<?= htmlspecialchars(json_encode([
                                            'id'         => $p['id'],
                                            'nama'       => $p['nama_lengkap'],
                                            'kategori'   => $kat['label'],
                                            'kat_icon'   => $kat['icon'],
                                            'judul'      => $p['judul'],
                                            'deskripsi'  => $p['deskripsi'] ?? '',
                                            'tanggal'    => date('d M Y', strtotime($p['tanggal'])),
                                            'waktu'      => $p['waktu_kejadian'] ? date('H:i', strtotime($p['waktu_kejadian'])) : '-',
                                            'status'     => $p['status'],
                                            'catatan'    => $p['catatan_leader'] ?? '',
                                            'created_by' => $p['created_by_name'] ?? 'Sistem',
                                            'auto'       => $isOld ? 'Ya (dari absensi)' : 'Tidak (manual)',
                                        ]), ENT_QUOTES) ?>)">
                                        <i class="bi bi-eye"></i> Detail
                                    </button>
                                    <?php if ($p['status'] === 'aktif'): ?>
                                    <button class="btn-tbl btn-tbl-green"
                                        onclick="showUpdateStatus(<?= $p['id'] ?>, 'selesai', '<?= htmlspecialchars(addslashes($p['catatan_leader'] ?? '')) ?>')">
                                        <i class="bi bi-check2"></i> Selesai
                                    </button>
                                    <button class="btn-tbl btn-tbl-gray"
                                        onclick="showUpdateStatus(<?= $p['id'] ?>, 'dibatalkan', '<?= htmlspecialchars(addslashes($p['catatan_leader'] ?? '')) ?>')">
                                        <i class="bi bi-x"></i> Batal
                                    </button>
                                    <?php elseif ($p['status'] === 'selesai'): ?>
                                    <button class="btn-tbl btn-tbl-gray"
                                        onclick="showUpdateStatus(<?= $p['id'] ?>, 'aktif', '<?= htmlspecialchars(addslashes($p['catatan_leader'] ?? '')) ?>')">
                                        <i class="bi bi-arrow-counterclockwise"></i> Buka
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn-tbl btn-tbl-red"
                                        onclick="konfirmasiHapus(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['judul'])) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- KANAN: Rekap + Info -->
        <div class="col-xl-4">

            <!-- Rekap Bulan Ini -->
            <div class="card-custom" style="border-top: 4px solid #e74c3c;">
                <h5 class="mb-3"><i class="bi bi-bar-chart-fill text-danger"></i> Rekap Pelanggaran Bulan Ini</h5>
                <?php if (empty($rekap)): ?>
                <div style="text-align:center; padding:24px; color:#bdc3c7;">
                    <i class="bi bi-emoji-smile" style="font-size:36px; display:block; margin-bottom:8px; opacity:0.4;"></i>
                    Tidak ada pelanggaran bulan ini. 
                </div>
                <?php else: ?>
                <div>
                    <?php $rank = 1; foreach ($rekap as $r):
                        $rFoto = !empty($r['foto_profil']) ? "../uploads/profil/".$r['foto_profil'] : "../assets/img/default-avatar.png";
                        $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
                    ?>
                    <div class="rekap-row">
                        <div class="rekap-rank <?= $rankClass ?>"><?= $rank ?></div>
                        <img src="<?= $rFoto ?>" class="avatar-sm" alt="">
                        <div class="rekap-info">
                            <div class="rekap-name"><?= htmlspecialchars($r['nama_lengkap']) ?></div>
                            <div class="rekap-meta">
                                <?php if ($r['terlambat']): ?><span style="color:#e67e22;">&#9201; <?= $r['terlambat'] ?> terlambat</span><?php endif; ?>
                                <?php if ($r['mangkir']): ?> &middot; <span style="color:#e74c3c;">&#10060; <?= $r['mangkir'] ?> mangkir</span><?php endif; ?>
                                <?php if ($r['perilaku']): ?> &middot; <span style="color:#9b59b6;">&#128544; <?= $r['perilaku'] ?> perilaku</span><?php endif; ?>
                            </div>
                            <?php if ($r['belum_selesai'] > 0): ?>
                            <div style="font-size:10px; color:#e74c3c; font-weight:700;">&#9679; <?= $r['belum_selesai'] ?> belum ditangani</div>
                            <?php endif; ?>
                        </div>
                        <div class="rekap-count"><?= $r['total_pelanggaran'] ?></div>
                    </div>
                    <?php $rank++; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Info Kategori -->
            <div class="card-custom" style="border-top: 4px solid #3498db;">
                <h5 class="mb-3"><i class="bi bi-info-circle text-primary"></i> Panduan Kategori</h5>
                <div style="font-size:13px; line-height:1.9;">
                    <?php foreach ($katLabel as $k => $kv): ?>
                    <div style="display:flex; align-items:center; gap:8px; padding:5px 0; border-bottom:1px solid #f1f2f6;">
                        <span class="kat-badge" style="background:<?= $kv['bg'] ?>;color:<?= $kv['color'] ?>; flex-shrink:0;">
                            <?= $kv['icon'] ?> <?= $kv['label'] ?>
                        </span>
                        <span style="font-size:11px; color:#7f8c8d;">
                            <?php
                            $desc = [
                                'keterlambatan' => 'Absen melebihi batas waktu shift',
                                'mangkir'       => 'Tidak hadir tanpa keterangan',
                                'perilaku'      => 'Sikap/etika tidak sesuai standar',
                                'atribut'       => 'Seragam/penampilan tidak sesuai',
                                'lainnya'       => 'Pelanggaran lain di luar kategori',
                            ];
                            echo $desc[$k] ?? '-';
                            ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:14px; padding:10px 14px; background:#fff8e1; border-radius:8px; border-left:4px solid #f39c12; font-size:12px; color:#7f8c8d;">
                    <i class="bi bi-magic text-warning"></i>
                    <strong>Auto-sync:</strong> Keterlambatan dari data absensi otomatis masuk ke halaman ini.
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ═══════════════════════════ MODALS ═══════════════════════════ -->

<!-- MODAL TAMBAH PELANGGARAN -->
<div class="modal-overlay" id="modalTambah">
    <div class="modal-box">
        <h5><i class="bi bi-plus-circle-fill text-danger"></i> Catat Pelanggaran Baru</h5>
        <form method="POST">
            <input type="hidden" name="action" value="tambah">
            <div class="mb-3">
                <label class="form-label-modal">Terapis <span style="color:red;">*</span></label>
                <select name="terapis_id" class="form-ctrl" required>
                    <option value="">-- Pilih Terapis --</option>
                    <?php foreach ($listTerapis as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama_lengkap']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label-modal">Kategori <span style="color:red;">*</span></label>
                    <select name="kategori" class="form-ctrl" required>
                        <?php foreach ($katLabel as $k => $kv): ?>
                        <option value="<?= $k ?>"><?= $kv['icon'] ?> <?= $kv['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label-modal">Tanggal <span style="color:red;">*</span></label>
                    <input type="date" name="tanggal" class="form-ctrl" value="<?= $tglBisnis ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label-modal">Waktu Kejadian <small style="color:#7f8c8d;">(opsional)</small></label>
                <input type="time" name="waktu_kejadian" class="form-ctrl">
            </div>
            <div class="mb-3">
                <label class="form-label-modal">Judul Pelanggaran <span style="color:red;">*</span></label>
                <input type="text" name="judul" class="form-ctrl" placeholder="Contoh: Terlambat 45 menit shift pagi" maxlength="200" required>
            </div>
            <div class="mb-3">
                <label class="form-label-modal">Deskripsi / Detail</label>
                <textarea name="deskripsi" class="form-ctrl" rows="3" placeholder="Keterangan tambahan tentang pelanggaran ini..."></textarea>
            </div>
            <div class="modal-footer-btns">
                <button type="button" class="btn-cancel" onclick="hideModal('modalTambah')">Batal</button>
                <button type="submit" class="btn-save"><i class="bi bi-check-circle"></i> Simpan Pelanggaran</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL UPDATE STATUS -->
<div class="modal-overlay" id="modalStatus">
    <div class="modal-box" style="max-width:420px;">
        <h5 id="statusModalTitle"><i class="bi bi-arrow-repeat"></i> Update Status</h5>
        <form method="POST" id="formUpdateStatus">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="pel_id" id="statusPelId">
            <input type="hidden" name="status" id="statusValue">
            <div class="mb-3">
                <label class="form-label-modal">Catatan Leader <small style="color:#7f8c8d;">(opsional)</small></label>
                <textarea name="catatan_leader" id="statusCatatan" class="form-ctrl" rows="3" placeholder="Tindakan yang telah diambil, sanksi, dll..."></textarea>
            </div>
            <div class="modal-footer-btns">
                <button type="button" class="btn-cancel" onclick="hideModal('modalStatus')">Batal</button>
                <button type="submit" class="btn-save" id="statusSubmitBtn">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL HAPUS -->
<div class="modal-overlay" id="modalHapus">
    <div class="modal-box" style="max-width:400px; text-align:center;">
        <div style="font-size:52px; margin-bottom:14px;">&#128465;</div>
        <h5 style="text-align:center;">Hapus Pelanggaran?</h5>
        <p id="hapusLabel" style="color:#7f8c8d; font-size:13px; margin:8px 0 20px;"></p>
        <form method="POST" id="formHapus">
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="pel_id" id="hapusPelId">
            <div class="modal-footer-btns" style="justify-content:center;">
                <button type="button" class="btn-cancel" onclick="hideModal('modalHapus')">Batal</button>
                <button type="submit" class="btn-save" style="background:linear-gradient(135deg,#e74c3c,#c0392b); flex:0 0 auto; padding:12px 28px;">Hapus</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DETAIL -->
<div class="modal-overlay" id="modalDetail">
    <div class="modal-box" style="max-width:480px;">
        <h5 id="detailTitle"><i class="bi bi-info-circle-fill text-primary"></i> Detail Pelanggaran</h5>
        <div id="detailBody" style="font-size:13px; line-height:2;"></div>
        <div class="modal-footer-btns" style="justify-content:flex-end; margin-top:18px;">
            <button class="btn-cancel" onclick="hideModal('modalDetail')">Tutup</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ── MODAL HELPERS ──
    function showModal(id) { document.getElementById(id).classList.add('show'); }
    function hideModal(id) { document.getElementById(id).classList.remove('show'); }

    // Tutup modal klik di luar box
    document.querySelectorAll('.modal-overlay').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('show');
        });
    });

    // ── UPDATE STATUS ──
    function showUpdateStatus(pelId, newStatus, currentCatatan) {
        const titles = { selesai:'&#9989; Tandai Selesai', aktif:'&#128280; Buka Kembali', dibatalkan:'&#9940; Batalkan Pelanggaran' };
        document.getElementById('statusModalTitle').innerHTML = '<i class="bi bi-arrow-repeat"></i> ' + (titles[newStatus] || 'Update Status');
        document.getElementById('statusPelId').value   = pelId;
        document.getElementById('statusValue').value   = newStatus;
        document.getElementById('statusCatatan').value = currentCatatan || '';
        const colors = { selesai:'#27ae60', aktif:'#3498db', dibatalkan:'#95a5a6' };
        document.getElementById('statusSubmitBtn').style.background = 'linear-gradient(135deg,' + (colors[newStatus]||'#2c3e50') + ',' + (colors[newStatus]||'#34495e') + ')';
        showModal('modalStatus');
    }

    // ── HAPUS ──
    function konfirmasiHapus(pelId, judul) {
        document.getElementById('hapusPelId').value = pelId;
        document.getElementById('hapusLabel').textContent = '"' + judul + '"';
        showModal('modalHapus');
    }

    // ── DETAIL ──
    function showDetail(data) {
        document.getElementById('detailTitle').innerHTML =
            '<i class="bi bi-info-circle-fill" style="color:#3498db;"></i> ' + escHtml(data.judul);

        const statusColors = { aktif:'#e74c3c', selesai:'#27ae60', dibatalkan:'#95a5a6' };
        const statusLabels = { aktif:'Aktif', selesai:'Selesai', dibatalkan:'Dibatalkan' };

        let html = `
            <table style="width:100%; border-collapse:collapse;">
                <tr><td style="color:#7f8c8d;width:130px;padding:5px 0;">&#128100; Terapis</td><td style="font-weight:700;">${escHtml(data.nama)}</td></tr>
                <tr><td style="color:#7f8c8d;padding:5px 0;">&#127991; Kategori</td><td>${data.kat_icon} ${escHtml(data.kategori)}</td></tr>
                <tr><td style="color:#7f8c8d;padding:5px 0;">&#128197; Tanggal</td><td>${escHtml(data.tanggal)}</td></tr>
                <tr><td style="color:#7f8c8d;padding:5px 0;">&#128336; Waktu</td><td>${escHtml(data.waktu)}</td></tr>
                <tr><td style="color:#7f8c8d;padding:5px 0;vertical-align:top;">&#128203; Deskripsi</td><td style="color:#555;">${escHtml(data.deskripsi) || '<em style="color:#bbb">Tidak ada</em>'}</td></tr>
                <tr><td style="color:#7f8c8d;padding:5px 0;">&#128680; Status</td><td><span style="background:${statusColors[data.status]};color:white;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;">${statusLabels[data.status]||data.status}</span></td></tr>
                <tr><td style="color:#7f8c8d;padding:5px 0;vertical-align:top;">&#128203; Catatan Leader</td><td style="color:#555;">${escHtml(data.catatan) || '<em style="color:#bbb">Belum ada</em>'}</td></tr>
                <tr><td style="color:#7f8c8d;padding:5px 0;">&#128100; Dicatat oleh</td><td>${escHtml(data.created_by)}</td></tr>
                <tr><td style="color:#7f8c8d;padding:5px 0;">&#129302; Sumber</td><td style="font-size:11px;color:#3498db;">${escHtml(data.auto)}</td></tr>
            </table>`;
        document.getElementById('detailBody').innerHTML = html;
        showModal('modalDetail');
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
</script>
</body>
</html>