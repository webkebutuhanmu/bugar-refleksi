<?php
/**
 * absensi_terapis.php - UPDATE v2
 *
 * UPDATE:
 * - Deteksi shift & keterlambatan saat absen
 * - Popup wajib isi alasan jika terlambat
 * - Tampilkan badge shift & status di daftar hadir
 */
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'terapis') {
    header("Location: ../auth/login_system.php"); exit;
}

$terapis_id = (int)$_SESSION['user_id'];

// ── Data user & cabang ────────────────────────────────────────────────────────
$stU = $pdo->prepare(
    "SELECT u.*, b.nama_cabang
     FROM users u
     LEFT JOIN branches b ON u.home_branch_id = b.id
     WHERE u.id = ?"
);
$stU->execute([$terapis_id]);
$userData = $stU->fetch(PDO::FETCH_ASSOC);

if (!$userData) { session_destroy(); header("Location: ../auth/login_system.php"); exit; }

$foto_url    = (!empty($userData['foto_profil']) && file_exists("../assets/uploads/".$userData['foto_profil']))
               ? "../assets/uploads/".$userData['foto_profil'] : "../assets/default_user.png";
$nama_cabang = $userData['nama_cabang'] ?? 'Belum ditentukan';
$no_hp       = $userData['no_hp'] ?? '-';
$branch_id   = (int)($userData['home_branch_id'] ?? 0);

// Auto-generate barcode_id jika belum ada
$barcode_id = $userData['barcode_id'] ?? null;
if (empty($barcode_id)) {
    $barcode_id = 'TRP'.str_pad($terapis_id, 5, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE users SET barcode_id=? WHERE id=?")->execute([$barcode_id, $terapis_id]);
}

// ── Tanggal bisnis ────────────────────────────────────────────────────────────
$sRow        = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulai    = $sRow['jam_mulai_hari'] ?? '08:00:00';
$tglBisnis   = (date('H:i:s') < $jamMulai) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

// ── Status sesi ───────────────────────────────────────────────────────────────
$sesiOpen = false;
if ($branch_id) {
    $stS = $pdo->prepare("SELECT status FROM attendance_sessions WHERE branch_id=? AND tanggal=? ORDER BY id DESC LIMIT 1");
    $stS->execute([$branch_id, $tglBisnis]);
    $sRow2    = $stS->fetch(PDO::FETCH_ASSOC);
    $sesiOpen = ($sRow2 && $sRow2['status'] === 'open');
}

// ── Daftar hadir (include shift data) ─────────────────────────────────────────
$sudahAbsen   = false;
$giliranSaya  = null;
$myShift      = null;
$myStatus     = null;
$absenList    = [];
$totalTerapis = 0;

if ($branch_id) {
    $stL = $pdo->prepare(
        "SELECT ta.terapis_id, ta.giliran, ta.waktu_absen, ta.metode_absen,
                ta.shift_type, ta.status_kehadiran, ta.alasan_terlambat,
                u.nama_lengkap, u.foto_profil
         FROM terapis_attendance ta
         JOIN users u ON ta.terapis_id = u.id
         WHERE ta.branch_id=? AND ta.tanggal=?
         ORDER BY ta.giliran ASC"
    );
    $stL->execute([$branch_id, $tglBisnis]);
    $absenList = $stL->fetchAll(PDO::FETCH_ASSOC);

    $stT = $pdo->prepare("SELECT COUNT(*) FROM users WHERE home_branch_id=? AND role='terapis'");
    $stT->execute([$branch_id]);
    $totalTerapis = (int)$stT->fetchColumn();

    foreach ($absenList as $a) {
        if ((int)$a['terapis_id'] === $terapis_id) {
            $sudahAbsen  = true;
            $giliranSaya = (int)$a['giliran'];
            $myShift     = $a['shift_type'];
            $myStatus    = $a['status_kehadiran'];
            break;
        }
    }
}

// ── Cek status izin/sakit hari ini ──────────────────────────────────────────
$izinHariIni = null;
if ($branch_id) {
    $stIzin = $pdo->prepare(
        "SELECT * FROM terapis_izin 
         WHERE terapis_id = ? AND branch_id = ? AND tanggal = ? 
         ORDER BY id DESC LIMIT 1"
    );
    $stIzin->execute([$terapis_id, $branch_id, $tglBisnis]);
    $izinHariIni = $stIzin->fetch(PDO::FETCH_ASSOC);
}

// ── Riwayat Absensi (30 hari terakhir) ──────────────────────────────────────
$riwayatAbsensi = [];
if ($branch_id) {
    $stRiwayat = $pdo->prepare(
        "SELECT ta.tanggal, ta.waktu_absen, ta.shift_type, ta.status_kehadiran, 
                ta.giliran, ta.metode_absen, ta.alasan_terlambat
         FROM terapis_attendance ta
         WHERE ta.terapis_id = ? AND ta.branch_id = ?
         ORDER BY ta.tanggal DESC, ta.waktu_absen DESC
         LIMIT 30"
    );
    $stRiwayat->execute([$terapis_id, $branch_id]);
    $riwayatAbsensi = $stRiwayat->fetchAll(PDO::FETCH_ASSOC);
}

// ── Riwayat Izin/Sakit ─────────────────────────────────────────────────────
$riwayatIzin = [];
if ($branch_id) {
    $stRiwIzin = $pdo->prepare(
        "SELECT ti.tanggal, ti.jenis, ti.keterangan, ti.status, ti.catatan_leader, ti.created_at
         FROM terapis_izin ti
         WHERE ti.terapis_id = ? AND ti.branch_id = ?
         ORDER BY ti.tanggal DESC
         LIMIT 30"
    );
    $stRiwIzin->execute([$terapis_id, $branch_id]);
    $riwayatIzin = $stRiwIzin->fetchAll(PDO::FETCH_ASSOC);
}

// ── URL AJAX ─────────────────────────────────────────────────────────────────
$proto     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$parentDir = rtrim(dirname($scriptDir), '/');
$AJAX_URL  = $proto.'://'.$host.$parentDir.'/kasir/ajax_absensi.php';
$AJAX_IZIN_URL = $proto.'://'.$host.$parentDir.'/kasir/ajax_izin_sakit.php';

// ── QR Data ──────────────────────────────────────────────────────────────────
$qr_data = json_encode([
    'barcode' => $barcode_id,
    'nama'    => $userData['nama_lengkap'],
    'cabang'  => $nama_cabang,
    'hp'      => $no_hp,
    'app'     => 'Bugar Refleksi'
], JSON_UNESCAPED_UNICODE);

function fmtWkt($dt) { return $dt ? date('H:i:s', strtotime($dt)) : '-'; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi - Terapis</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        /* ── Status Card ── */
        .absen-status-card {
            background:#fff; border-radius:15px; padding:28px;
            box-shadow:0 4px 15px rgba(0,0,0,0.08); text-align:center;
            margin-bottom:18px; position:relative; overflow:hidden;
        }
        .absen-status-card::before {
            content:''; position:absolute; top:0; left:0; right:0; height:5px;
        }
        .card-open::before   { background:linear-gradient(90deg,#27ae60,#2ecc71); }
        .card-closed::before { background:linear-gradient(90deg,#e74c3c,#c0392b); }
        .card-done::before   { background:linear-gradient(90deg,#3498db,#2980b9); }
        .status-icon { font-size:50px; margin-bottom:10px; display:block; }
        .status-text { font-size:18px; font-weight:bold; color:#2c3e50; margin-bottom:6px; }
        .status-sub  { font-size:13px; color:#7f8c8d; margin-bottom:18px; }
        .btn-absen {
            padding:15px 44px; border:none; border-radius:14px; font-weight:bold;
            font-size:16px; cursor:pointer;
            background:linear-gradient(135deg,#27ae60,#2ecc71); color:#fff;
            box-shadow:0 5px 18px rgba(39,174,96,0.4); transition:all .3s;
            display:inline-flex; align-items:center; gap:10px;
        }
        .btn-absen:hover    { transform:translateY(-2px); box-shadow:0 8px 24px rgba(39,174,96,0.5); }
        .btn-absen:disabled { background:#bdc3c7; cursor:not-allowed; box-shadow:none; transform:none; }
        .giliran-display {
            margin:16px auto; padding:16px;
            background:linear-gradient(135deg,#667eea,#764ba2);
            border-radius:14px; color:#fff; max-width:250px;
        }
        .giliran-display .lbl { font-size:11px; opacity:.8; text-transform:uppercase; letter-spacing:1px; }
        .giliran-display .num { font-size:56px; font-weight:900; line-height:1; }
        .giliran-display .tme { font-size:12px; opacity:.75; margin-top:6px; }

        /* ── Shift & Status Badges ── */
        .shift-badge {
            display:inline-block; padding:3px 10px; border-radius:12px;
            font-size:11px; font-weight:bold; margin:2px;
        }
        .shift-pagi  { background:#fff8e1; color:#f57f17; }
        .shift-malam { background:#ede7f6; color:#6a1b9a; }
        .status-tepat { background:#e8f5e9; color:#2e7d32; }
        .status-telat { background:#ffebee; color:#c62828; }

        /* ── QR Card ── */
        .qr-card {
            background:#fff; border-radius:15px; padding:20px;
            box-shadow:0 4px 15px rgba(0,0,0,0.08); text-align:center;
            margin-bottom:18px; border:2px solid #eaf2ff;
        }
        .qr-card h3 { margin:0 0 3px; color:#2c3e50; font-size:15px; font-weight:700; }
        .qr-card p  { font-size:12px; color:#7f8c8d; margin:0 0 14px; }
        .qr-wrapper {
            display:inline-block; padding:12px; background:#fff;
            border:2px solid #e0e8f0; border-radius:12px;
        }
        .qr-id-label {
            margin-top:10px; font-family:'Courier New',monospace;
            font-size:16px; font-weight:900; letter-spacing:3px; color:#2c3e50;
        }
        .qr-meta {
            margin-top:6px; font-size:12px; color:#7f8c8d;
            display:flex; align-items:center; justify-content:center; gap:8px; flex-wrap:wrap;
        }
        .qr-cabang-badge {
            background:#e8f5e9; color:#27ae60; padding:2px 9px;
            border-radius:10px; font-size:11px; font-weight:bold;
        }
        .qr-help {
            margin-top:11px; font-size:11px; color:#95a5a6;
            background:#f8f9fa; padding:8px 12px; border-radius:8px; line-height:1.5;
        }

        /* ── Daftar Hadir ── */
        .absen-list-card {
            background:#fff; border-radius:15px;
            box-shadow:0 4px 15px rgba(0,0,0,0.08); overflow:hidden;
        }
        .absen-list-header {
            padding:15px 20px; border-bottom:1px solid #eee;
            display:flex; justify-content:space-between; align-items:center;
        }
        .absen-list-header h3 { margin:0; font-size:15px; color:#2c3e50; }
        .hadir-badge {
            background:#eaf2ff; color:#2980b9; padding:4px 12px;
            border-radius:14px; font-size:12px; font-weight:bold;
        }
        .absen-item {
            display:flex; align-items:center; gap:12px;
            padding:12px 20px; border-bottom:1px solid #f5f5f5;
        }
        .absen-item:last-child { border-bottom:none; }
        .absen-item.me { background:#eaf7ed; }
        .rank {
            width:34px; height:34px; border-radius:50%; flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
            font-weight:900; font-size:15px; color:#fff;
        }
        .r1 { background:linear-gradient(135deg,#f1c40f,#f39c12); }
        .r2 { background:linear-gradient(135deg,#bdc3c7,#95a5a6); }
        .r3 { background:linear-gradient(135deg,#e67e22,#d35400); }
        .rn { background:linear-gradient(135deg,#3498db,#2980b9); }
        .absen-avatar {
            width:36px; height:36px; border-radius:50%;
            object-fit:cover; border:2px solid #eee; flex-shrink:0;
        }
        .info-nama  { font-weight:600; color:#2c3e50; font-size:14px; }
        .info-waktu { font-size:11px; color:#7f8c8d; margin-top:2px; }
        .me-badge {
            background:#27ae60; color:#fff; padding:2px 8px;
            border-radius:8px; font-size:10px; font-weight:bold; margin-left:5px;
        }
        .empty-list { text-align:center; padding:34px; color:#95a5a6; }
        .empty-list i { font-size:34px; display:block; margin-bottom:8px; }
        .refresh-bar {
            padding:6px 20px; font-size:11px; color:#bdc3c7; text-align:center;
            border-top:1px solid #f5f5f5;
            display:flex; align-items:center; justify-content:center; gap:6px;
        }
        .dot-live {
            width:7px; height:7px; border-radius:50%;
            background:#27ae60; animation:blink 1.4s infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

        .alasan-text {
            font-size:10px; color:#e74c3c; font-style:italic; margin-top:2px;
            background:#fff5f5; padding:2px 6px; border-radius:4px; display:inline-block;
        }

        /* ── Tombol Izin/Sakit ── */
        .izin-card {
            background:#fff; border-radius:15px; padding:20px;
            box-shadow:0 4px 15px rgba(0,0,0,0.08); text-align:center;
            margin-bottom:18px; position:relative; overflow:hidden;
        }
        .izin-card::before {
            content:''; position:absolute; top:0; left:0; right:0; height:5px;
            background:linear-gradient(90deg,#e67e22,#f39c12);
        }
        .izin-card h3 { margin:0 0 4px; color:#2c3e50; font-size:15px; font-weight:700; }
        .izin-card p  { font-size:12px; color:#7f8c8d; margin:0 0 14px; }
        .izin-btn-group { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
        .btn-izin, .btn-sakit {
            padding:12px 28px; border:none; border-radius:12px; font-weight:bold;
            font-size:14px; cursor:pointer; color:#fff;
            display:inline-flex; align-items:center; gap:8px;
            transition:all .3s; box-shadow:0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-izin { background:linear-gradient(135deg,#e67e22,#f39c12); }
        .btn-izin:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(230,126,34,0.4); }
        .btn-sakit { background:linear-gradient(135deg,#e74c3c,#c0392b); }
        .btn-sakit:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(231,76,60,0.4); }
        .btn-izin:disabled, .btn-sakit:disabled { background:#bdc3c7; cursor:not-allowed; box-shadow:none; transform:none; }
        .izin-status-info {
            margin-top:12px; padding:10px 15px; border-radius:10px; font-size:13px;
        }
        .izin-pending { background:#fff8e1; color:#f57f17; border:1px solid #ffecb3; }
        .izin-approved { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; }
        .izin-rejected { background:#ffebee; color:#c62828; border:1px solid #ffcdd2; }

        /* ── Riwayat Absensi ── */
        .riwayat-card {
            background:#fff; border-radius:15px;
            box-shadow:0 4px 15px rgba(0,0,0,0.08); overflow:hidden;
            margin-top:18px;
        }
        .riwayat-header {
            padding:15px 20px; border-bottom:1px solid #eee;
            display:flex; justify-content:space-between; align-items:center;
        }
        .riwayat-header h3 { margin:0; font-size:15px; color:#2c3e50; }
        .riwayat-table {
            width:100%; border-collapse:collapse; font-size:12px;
        }
        .riwayat-table th {
            background:#f8f9fa; padding:10px 12px; text-align:left;
            font-weight:600; color:#7f8c8d; font-size:11px;
            text-transform:uppercase; letter-spacing:0.5px;
            border-bottom:2px solid #eee;
        }
        .riwayat-table td {
            padding:10px 12px; border-bottom:1px solid #f5f5f5; color:#2c3e50;
        }
        .riwayat-table tr:hover td { background:#f8f9fa; }
        .badge-izin {
            display:inline-block; padding:2px 8px; border-radius:8px;
            font-size:10px; font-weight:bold;
        }
        .badge-izin-type { background:#fff3e0; color:#e65100; }
        .badge-sakit-type { background:#fce4ec; color:#c62828; }
        .riwayat-empty { text-align:center; padding:30px; color:#95a5a6; font-size:13px; }
    </style>
</head>
<body>
<div class="container-layout">
    <div class="sidebar">
        <div class="sidebar-header"><h2>&#128134; TERAPIS PANEL</h2></div>
        <div class="sidebar-menu">
            <a href="dashboard_terapis.php"    class="menu-item"><i>&#128202;</i> Dashboard</a>
            <a href="absensi_terapis.php"       class="menu-item active"><i>&#128203;</i> Absensi</a>
            <a href="riwayat_pendapatan.php"    class="menu-item"><i>&#128176;</i> Riwayat Omset</a>
            <a href="profil_terapis.php"        class="menu-item"><i>&#128100;</i> Profil Saya</a>
            <a href="skor_reward_terapis.php" class="menu-item"><i>⭐</i> Skor Reward</a>
            <a href="../auth/logout_system.php" class="menu-item" style="color:#c0392b;margin-top:50px;">
                <i>&#128682;</i> Logout
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h1>&#128203; Absensi Harian</h1>
            <div class="topbar-right">
                <span style="font-size:13px;color:#7f8c8d;">&#128197; <?= date('d M Y') ?></span>
            </div>
        </div>

        <!-- ── STATUS CARD ─────────────────────────────────────────────── -->
        <?php
        $izinDisetujui = ($izinHariIni && $izinHariIni['status'] === 'disetujui');
        $izinPending   = ($izinHariIni && $izinHariIni['status'] === 'pending');
        if ($sudahAbsen) {
            $cls='card-done'; $ico='&#9989;'; $txt='Kamu Sudah Absen Hari Ini!'; $sub='Giliran kamu telah dicatat. Semangat bekerja!';
        } elseif ($izinDisetujui) {
            $izLabel = $izinHariIni['jenis'] === 'sakit' ? 'Sakit' : 'Izin';
            $cls='card-done'; $ico= ($izinHariIni['jenis'] === 'sakit' ? '&#129298;' : '&#128232;'); 
            $txt="Status Hari Ini: $izLabel"; 
            $sub="Pengajuan {$izLabel} kamu telah disetujui oleh Leader. Istirahat yang cukup!";
        } elseif ($sesiOpen) {
            $cls='card-open'; $ico='&#128994;'; $txt='Absensi Sedang Dibuka!'; $sub='Ketuk tombol di bawah untuk absen sekarang!';
        } else {
            $cls='card-closed'; $ico='&#128308;'; $txt='Absensi Belum Dibuka'; $sub='Tunggu kasir membuka sesi, atau tunjukkan QR Code ke kasir.';
        }
        ?>
        <div class="absen-status-card <?= $cls ?>">
            <span class="status-icon"><?= $ico ?></span>
            <div class="status-text"><?= $txt ?></div>
            <div class="status-sub"><?= $sub ?></div>

            <?php if ($sudahAbsen): ?>
                <div class="giliran-display">
                    <div class="lbl">Giliran Kamu</div>
                    <div class="num"><?= $giliranSaya ?></div>
                    <div class="tme">
                        <?php if ($myShift): ?>
                            <span class="shift-badge <?= $myShift==='pagi' ? 'shift-pagi' : 'shift-malam' ?>">
                                Shift <?= ucfirst($myShift) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($myStatus): ?>
                            <span class="shift-badge <?= $myStatus==='tepat_waktu' ? 'status-tepat' : 'status-telat' ?>">
                                <?= $myStatus==='tepat_waktu' ? '&#10004; Tepat Waktu' : '&#9888; Terlambat' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($izinDisetujui): ?>
                <div class="giliran-display" style="background:linear-gradient(135deg,<?= $izinHariIni['jenis']==='sakit' ? '#e74c3c,#c0392b' : '#e67e22,#f39c12' ?>);">
                    <div class="lbl"><?= $izinHariIni['jenis'] === 'sakit' ? 'SAKIT' : 'IZIN' ?></div>
                    <div class="num" style="font-size:36px;"><?= $izinHariIni['jenis'] === 'sakit' ? '&#129298;' : '&#128232;' ?></div>
                    <div class="tme">Disetujui oleh Leader</div>
                </div>
            <?php elseif ($sesiOpen && !$izinPending): ?>
                <button class="btn-absen" id="btnAbsen" onclick="doAbsen()">
                    <i class="fas fa-hand-pointer"></i> ABSEN SEKARANG
                </button>
            <?php else: ?>
                <button class="btn-absen" disabled>
                    <i class="fas fa-lock"></i> Menunggu Kasir Buka Absen
                </button>
            <?php endif; ?>
        </div>

        <!-- ── TOMBOL IZIN / SAKIT ───────────────────────────────────────── -->
        <div class="izin-card">
            <h3>&#128203; Pengajuan Izin / Sakit</h3>
            <p>Ajukan izin atau sakit kapan saja tanpa menunggu kasir buka absensi</p>

            <?php if ($izinHariIni): ?>
                <?php
                $izSt = $izinHariIni['status'];
                $izJenis = $izinHariIni['jenis'] === 'izin' ? 'Izin' : 'Sakit';
                if ($izSt === 'pending') {
                    $izCls = 'izin-pending'; $izIco = '&#9203;'; $izTxt = "Pengajuan $izJenis sedang menunggu persetujuan Leader.";
                } elseif ($izSt === 'disetujui') {
                    $izCls = 'izin-approved'; $izIco = '&#9989;'; $izTxt = "Pengajuan $izJenis telah disetujui oleh Leader.";
                } else {
                    $izCls = 'izin-rejected'; $izIco = '&#10060;';
                    $izTxt = "Pengajuan $izJenis ditolak oleh Leader.";
                    if (!empty($izinHariIni['catatan_leader'])) {
                        $izTxt .= " Catatan: " . htmlspecialchars($izinHariIni['catatan_leader']);
                    }
                    $izTxt .= " Silakan hadir dan absen seperti biasa.";
                }
                ?>
                <div class="izin-status-info <?= $izCls ?>">
                    <?= $izIco ?> <?= $izTxt ?>
                </div>
            <?php elseif ($sudahAbsen): ?>
                <div class="izin-status-info izin-approved">
                    &#9989; Kamu sudah absen hari ini. Pengajuan izin/sakit tidak diperlukan.
                </div>
            <?php else: ?>
                <div class="izin-btn-group">
                    <button class="btn-izin" id="btnIzin" onclick="ajukanIzinSakit('izin')">
                        <i class="fas fa-envelope"></i> Ajukan Izin
                    </button>
                    <button class="btn-sakit" id="btnSakit" onclick="ajukanIzinSakit('sakit')">
                        <i class="fas fa-medkit"></i> Ajukan Sakit
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── QR CODE ─────────────────────────────────────────────────── -->
        <div class="qr-card">
            <h3>&#128247; QR Code Absensi Saya</h3>
            <p>Tunjukkan QR ini ke kasir untuk scan absensi otomatis</p>
            <div class="qr-wrapper"><div id="qrBox"></div></div>
            <div class="qr-id-label"><?= htmlspecialchars($barcode_id) ?></div>
            <div class="qr-meta">
                <span>&#128100; <?= htmlspecialchars($userData['nama_lengkap']) ?></span>
                <span class="qr-cabang-badge">&#128204; <?= htmlspecialchars($nama_cabang) ?></span>
            </div>
            <div class="qr-help">
                &#128161; Kasir cukup arahkan kamera ke QR ini untuk absensi otomatis.
            </div>
        </div>

        <!-- ── DAFTAR HADIR ────────────────────────────────────────────── -->
        <div class="absen-list-card">
            <div class="absen-list-header">
                <h3>&#128101; Daftar Hadir Hari Ini</h3>
                <span class="hadir-badge" id="hadirBadge">
                    <?= count($absenList) ?> / <?= $totalTerapis ?> Hadir
                </span>
            </div>
            <div id="absenListBody">
                <?php if (empty($absenList)): ?>
                    <div class="empty-list">
                        <i>&#128203;</i>
                        <p>Belum ada yang absen hari ini</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($absenList as $a):
                        $isMe  = ((int)$a['terapis_id'] === $terapis_id);
                        $g     = (int)$a['giliran'];
                        $rc    = $g===1?'r1':($g===2?'r2':($g===3?'r3':'rn'));
                        $fPath = "../assets/uploads/".($a['foto_profil'] ?? '');
                        $fSrc  = (!empty($a['foto_profil']) && file_exists($fPath))
                                 ? htmlspecialchars($fPath) : "../assets/default_user.png";
                        $met   = $a['metode_absen']==='scan' ? '&#128247; Scan' : '&#128241; Manual';
                        $shCls = ($a['shift_type'] ?? '') === 'pagi' ? 'shift-pagi' : 'shift-malam';
                        $stCls = ($a['status_kehadiran'] ?? '') === 'tepat_waktu' ? 'status-tepat' : 'status-telat';
                        $stLbl = ($a['status_kehadiran'] ?? '') === 'tepat_waktu' ? '&#10004; Tepat Waktu' : '&#9888; Terlambat';
                    ?>
                    <div class="absen-item<?= $isMe ? ' me' : '' ?>">
                        <div class="rank <?= $rc ?>"><?= $g ?></div>
                        <img src="<?= $fSrc ?>" class="absen-avatar"
                             onerror="this.src='../assets/default_user.png'" alt="">
                        <div style="flex:1;">
                            <div class="info-nama">
                                <?= htmlspecialchars($a['nama_lengkap']) ?>
                                <?php if ($isMe): ?><span class="me-badge">KAMU</span><?php endif; ?>
                            </div>
                            <div class="info-waktu">
                                <i class="far fa-clock"></i> <?= fmtWkt($a['waktu_absen']) ?>
                                &bull; <?= $met ?>
                                <?php if (!empty($a['shift_type'])): ?>
                                    &bull; <span class="shift-badge <?= $shCls ?>">Shift <?= ucfirst($a['shift_type']) ?></span>
                                <?php endif; ?>
                                <span class="shift-badge <?= $stCls ?>"><?= $stLbl ?></span>
                            </div>
                            <?php if (!empty($a['alasan_terlambat'])): ?>
                                <div class="alasan-text">&#128221; Alasan: <?= htmlspecialchars($a['alasan_terlambat']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="refresh-bar">
                <div class="dot-live"></div> Auto-refresh setiap 10 detik
            </div>
        </div>

        <!-- ── RIWAYAT ABSENSI ──────────────────────────────────────────── -->
        <div class="riwayat-card">
            <div class="riwayat-header">
                <h3>&#128197; Riwayat Absensi Saya</h3>
                <span class="hadir-badge"><?= count($riwayatAbsensi) + count($riwayatIzin) ?> Data</span>
            </div>
            <?php if (empty($riwayatAbsensi) && empty($riwayatIzin)): ?>
                <div class="riwayat-empty">
                    <i style="font-size:30px;display:block;margin-bottom:8px;">&#128203;</i>
                    Belum ada riwayat absensi
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="riwayat-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Shift</th>
                                <th>Status</th>
                                <th>Giliran</th>
                                <th>Metode</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Gabungkan absensi + izin/sakit lalu sort by tanggal
                            $allRiwayat = [];
                            foreach ($riwayatAbsensi as $ra) {
                                $allRiwayat[] = [
                                    'tanggal' => $ra['tanggal'],
                                    'waktu'   => $ra['waktu_absen'] ? date('H:i:s', strtotime($ra['waktu_absen'])) : '-',
                                    'shift'   => $ra['shift_type'] ? ucfirst($ra['shift_type']) : '-',
                                    'status'  => $ra['status_kehadiran'],
                                    'giliran' => $ra['giliran'] ?? '-',
                                    'metode'  => $ra['metode_absen'] === 'scan' ? 'Scan' : 'Manual',
                                    'type'    => 'absen',
                                    'alasan'  => $ra['alasan_terlambat'] ?? ''
                                ];
                            }
                            foreach ($riwayatIzin as $ri) {
                                $statusMap = ['pending'=>'Menunggu','disetujui'=>'Disetujui','ditolak'=>'Ditolak'];
                                $allRiwayat[] = [
                                    'tanggal' => $ri['tanggal'],
                                    'waktu'   => date('H:i:s', strtotime($ri['created_at'])),
                                    'shift'   => '-',
                                    'status'  => $ri['jenis'].'_'.$ri['status'],
                                    'giliran' => '-',
                                    'metode'  => ucfirst($ri['jenis']),
                                    'type'    => 'izin',
                                    'keterangan' => $ri['keterangan'] ?? '',
                                    'catatan' => $ri['catatan_leader'] ?? '',
                                    'izin_status' => $ri['status']
                                ];
                            }
                            usort($allRiwayat, function($a, $b) {
                                return strcmp($b['tanggal'], $a['tanggal']);
                            });
                            foreach ($allRiwayat as $rw):
                            ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($rw['tanggal'])) ?></td>
                                <td><?= $rw['waktu'] ?></td>
                                <td>
                                    <?php if ($rw['shift'] !== '-'): ?>
                                        <span class="shift-badge <?= strtolower($rw['shift'])==='pagi' ? 'shift-pagi' : 'shift-malam' ?>">
                                            <?= $rw['shift'] ?>
                                        </span>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($rw['type'] === 'absen'): ?>
                                        <?php if ($rw['status'] === 'tepat_waktu'): ?>
                                            <span class="shift-badge status-tepat">&#10004; Tepat Waktu</span>
                                        <?php elseif ($rw['status'] === 'terlambat'): ?>
                                            <span class="shift-badge status-telat">&#9888; Terlambat</span>
                                        <?php else: ?>
                                            <span class="shift-badge" style="background:#eee;color:#666;"><?= htmlspecialchars($rw['status'] ?? '-') ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php
                                        $jenisLabel = strpos($rw['status'], 'izin_') === 0 ? 'Izin' : 'Sakit';
                                        $izSt = $rw['izin_status'] ?? '';
                                        if ($izSt === 'pending'): ?>
                                            <span class="badge-izin badge-izin-type">&#9203; <?= $jenisLabel ?> - Menunggu</span>
                                        <?php elseif ($izSt === 'disetujui'): ?>
                                            <span class="badge-izin" style="background:#e8f5e9;color:#2e7d32;">&#9989; <?= $jenisLabel ?> - Disetujui</span>
                                        <?php else: ?>
                                            <span class="badge-izin" style="background:#ffebee;color:#c62828;">&#10060; <?= $jenisLabel ?> - Ditolak</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;font-weight:bold;"><?= $rw['giliran'] ?></td>
                                <td><?= $rw['metode'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /main-content -->
</div>

<script>
const AJAX_URL = <?= json_encode($AJAX_URL) ?>;
const AJAX_IZIN_URL = <?= json_encode($AJAX_IZIN_URL) ?>;
const MY_ID    = <?= $terapis_id ?>;

// ── QR Code ──────────────────────────────────────────────────────────────────
new QRCode(document.getElementById('qrBox'), {
    text: <?= json_encode($qr_data) ?>,
    width: 180, height: 180,
    colorDark: '#1a252f', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M
});

// ── Tombol ABSEN ─────────────────────────────────────────────────────────────
function doAbsen() {
    Swal.fire({
        title: 'Konfirmasi Absen',
        text: 'Apakah kamu yakin ingin absen sekarang?',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#27ae60',
        confirmButtonText: 'Ya, Absen!',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        kirimAbsenManual('');
    });
}

function kirimAbsenManual(alasan) {
    var btn = document.getElementById('btnAbsen');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...'; }

    var body = 'action=manual_absen';
    if (alasan) body += '&alasan_terlambat=' + encodeURIComponent(alasan);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', AJAX_URL, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.withCredentials = true;

    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;

        if (xhr.status === 200) {
            var data;
            try { data = JSON.parse(xhr.responseText); } catch(e) {
                Swal.fire({ title:'Error', html:'<pre>'+escH(xhr.responseText.substring(0,500))+'</pre>', icon:'error' });
                resetBtn(btn); return;
            }

            // ★ TERLAMBAT → Tampilkan popup wajib isi alasan
            if (!data.success && data.terlambat) {
                Swal.fire({
                    title: '&#9888;&#65039; Kamu Terlambat!',
                    html: '<div style="text-align:left;font-size:14px;margin-bottom:12px;">'
                        + '<p style="margin:0 0 8px;">Jam absen: <strong>' + escH(data.jam_absen) + '</strong></p>'
                        + '<p style="margin:0 0 8px;">Shift: <strong>' + escH(data.label_shift) + '</strong></p>'
                        + '<p style="margin:0;color:#e74c3c;font-weight:bold;">Kamu wajib mengisi alasan keterlambatan untuk melanjutkan absen.</p>'
                        + '</div>',
                    input: 'textarea',
                    inputLabel: 'Alasan Terlambat',
                    inputPlaceholder: 'Contoh: Ban motor bocor di jalan...',
                    inputAttributes: { 'aria-label': 'Alasan terlambat', maxlength: 500 },
                    inputValidator: function(value) {
                        if (!value || value.trim().length < 5) return 'Alasan wajib diisi minimal 5 karakter!';
                    },
                    showCancelButton: true,
                    confirmButtonColor: '#e67e22',
                    confirmButtonText: '&#128221; Kirim Alasan & Absen',
                    cancelButtonText: 'Batal',
                    customClass: { popup: 'swal-wide' }
                }).then(function(result2) {
                    if (result2.isConfirmed && result2.value) {
                        kirimAbsenManual(result2.value.trim());
                    } else {
                        resetBtn(btn);
                    }
                });
                return;
            }

            if (data.success) {
                Swal.fire({
                    title: 'Berhasil Absen!',
                    html: escH(data.message),
                    icon: 'success', timer: 2500, showConfirmButton: false
                }).then(function() { location.reload(); });
            } else {
                Swal.fire('Gagal', escH(data.message), 'warning');
                resetBtn(btn);
            }
        } else {
            Swal.fire({ title:'HTTP Error '+xhr.status, html:'Server error', icon:'error' });
            resetBtn(btn);
        }
    };
    xhr.onerror = function() {
        Swal.fire('Koneksi Gagal', 'Tidak dapat menghubungi server.', 'error');
        resetBtn(btn);
    };
    xhr.send(body);
}

function resetBtn(btn) {
    if (btn) { btn.disabled=false; btn.innerHTML='<i class="fas fa-hand-pointer"></i> ABSEN SEKARANG'; }
}

// ── Background refresh ───────────────────────────────────────────────────────
function refreshData() {
    var xhr2 = new XMLHttpRequest();
    xhr2.open('GET', AJAX_URL + '?action=cek_status', true);
    xhr2.withCredentials = true;
    xhr2.onreadystatechange = function() {
        if (xhr2.readyState !== 4 || xhr2.status !== 200) return;
        var data;
        try { data = JSON.parse(xhr2.responseText); } catch(e) { return; }
        if (!data.success) return;

        document.getElementById('hadirBadge').textContent =
            data.total_hadir + ' / ' + data.total_terapis + ' Hadir';

        var card = document.querySelector('.absen-status-card');
        var isDone  = card && card.classList.contains('card-done');
        var isOpen  = card && card.classList.contains('card-open');
        var isClosed= card && card.classList.contains('card-closed');

        if (data.sudah_absen && !isDone)  { location.reload(); return; }
        if (data.sesi_open   && isClosed) { location.reload(); return; }
        if (!data.sesi_open  && isOpen)   { location.reload(); return; }

        // Update list with shift badges
        var body = document.getElementById('absenListBody');
        if (!data.absen_list || data.absen_list.length === 0) {
            body.innerHTML = '<div class="empty-list"><i>&#128203;</i><p>Belum ada yang absen hari ini</p></div>';
            return;
        }
        var html = '';
        data.absen_list.forEach(function(a) {
            var isMe = (parseInt(a.terapis_id) === MY_ID);
            var g    = parseInt(a.giliran) || 1;
            var rc   = g===1?'r1':(g===2?'r2':(g===3?'r3':'rn'));
            var foto = a.foto_profil ? '../assets/uploads/'+escH(a.foto_profil) : '../assets/default_user.png';
            var met  = a.metode_absen === 'scan' ? '&#128247; Scan' : '&#128241; Manual';
            var shCls = (a.shift_type||'') === 'pagi' ? 'shift-pagi' : 'shift-malam';
            var stCls = (a.status_kehadiran||'') === 'tepat_waktu' ? 'status-tepat' : 'status-telat';
            var stLbl = (a.status_kehadiran||'') === 'tepat_waktu' ? '&#10004; Tepat Waktu' : '&#9888; Terlambat';
            var shiftHtml = a.shift_type ? ' &bull; <span class="shift-badge '+shCls+'">Shift '+(a.shift_type==='pagi'?'Pagi':'Malam')+'</span>' : '';
            var alasanHtml = (a.alasan_terlambat && a.status_kehadiran==='terlambat')
                ? '<div class="alasan-text">&#128221; Alasan: '+escH(a.alasan_terlambat)+'</div>' : '';

            html += '<div class="absen-item'+(isMe?' me':'')+'"><div class="rank '+rc+'">'+g+'</div>'
                  + '<img src="'+foto+'" class="absen-avatar" onerror="this.src=\'../assets/default_user.png\'" alt="">'
                  + '<div style="flex:1"><div class="info-nama">'+escH(a.nama_lengkap)+(isMe?'<span class="me-badge">KAMU</span>':'')+'</div>'
                  + '<div class="info-waktu"><i class="far fa-clock"></i> '+fmtT(a.waktu_absen)+' &bull; '+met
                  + shiftHtml + ' <span class="shift-badge '+stCls+'">'+stLbl+'</span></div>'
                  + alasanHtml + '</div></div>';
        });
        body.innerHTML = html;
    };
    xhr2.send();
}

function fmtT(dt) {
    if (!dt) return '-';
    var d = new Date(dt.replace(' ','T'));
    if (isNaN(d)) return dt;
    return String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')+':'+String(d.getSeconds()).padStart(2,'0');
}
function escH(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Ajukan Izin / Sakit ──────────────────────────────────────────────────────
function ajukanIzinSakit(jenis) {
    var judul = jenis === 'izin' ? 'Ajukan Izin' : 'Ajukan Sakit';
    var icoColor = jenis === 'izin' ? '#e67e22' : '#e74c3c';

    Swal.fire({
        title: judul,
        html: '<div style="text-align:left;font-size:14px;">'
            + '<p>Pengajuan <strong>' + (jenis==='izin'?'Izin':'Sakit') + '</strong> untuk hari ini akan dikirim ke Leader untuk disetujui.</p>'
            + '<p style="color:#7f8c8d;font-size:12px;margin-top:8px;">Jika ditolak dan kamu tidak hadir absen, kamu akan dikenakan pelanggaran <strong>Alpha (Tidak Hadir)</strong>.</p>'
            + '</div>',
        input: 'textarea',
        inputLabel: 'Keterangan ' + (jenis==='izin'?'izin':'sakit') + ':',
        inputPlaceholder: jenis==='izin' ? 'Contoh: Ada urusan keluarga yang mendesak...' : 'Contoh: Demam tinggi, tidak bisa beraktivitas...',
        inputAttributes: { 'aria-label': 'Keterangan', maxlength: 500 },
        inputValidator: function(value) {
            if (!value || value.trim().length < 5) return 'Keterangan wajib diisi minimal 5 karakter!';
        },
        showCancelButton: true,
        confirmButtonColor: icoColor,
        confirmButtonText: '&#128228; Kirim Pengajuan',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            kirimIzinSakit(jenis, result.value.trim());
        }
    });
}

function kirimIzinSakit(jenis, keterangan) {
    var btnIzin = document.getElementById('btnIzin');
    var btnSakit = document.getElementById('btnSakit');
    if (btnIzin) { btnIzin.disabled = true; }
    if (btnSakit) { btnSakit.disabled = true; }

    var body = 'action=kirim_izin&jenis=' + encodeURIComponent(jenis) + '&keterangan=' + encodeURIComponent(keterangan);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', AJAX_IZIN_URL, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.withCredentials = true;

    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;

        if (xhr.status === 200) {
            var data;
            try { data = JSON.parse(xhr.responseText); } catch(e) {
                Swal.fire({ title:'Error', html:'<pre>'+escH(xhr.responseText.substring(0,500))+'</pre>', icon:'error' });
                if (btnIzin) btnIzin.disabled = false;
                if (btnSakit) btnSakit.disabled = false;
                return;
            }

            if (data.success) {
                Swal.fire({
                    title: 'Berhasil!',
                    html: escH(data.message),
                    icon: 'success', timer: 2500, showConfirmButton: false
                }).then(function() { location.reload(); });
            } else {
                Swal.fire('Gagal', escH(data.message), 'warning');
                if (btnIzin) btnIzin.disabled = false;
                if (btnSakit) btnSakit.disabled = false;
            }
        } else {
            Swal.fire({ title:'HTTP Error '+xhr.status, html:'Server error', icon:'error' });
            if (btnIzin) btnIzin.disabled = false;
            if (btnSakit) btnSakit.disabled = false;
        }
    };
    xhr.onerror = function() {
        Swal.fire('Koneksi Gagal', 'Tidak dapat menghubungi server.', 'error');
        if (btnIzin) btnIzin.disabled = false;
        if (btnSakit) btnSakit.disabled = false;
    };
    xhr.send(body);
}

setInterval(refreshData, 10000);
</script>
</body>
</html>