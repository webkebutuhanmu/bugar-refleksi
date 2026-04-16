<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'terapis') { 
    header("Location: ../auth/login_system.php"); exit; 
}

$terapis_id = $_SESSION['user_id'];
$nama_terapis = $_SESSION['nama'];

// Cek apakah ini login pertama di sesi ini (untuk popup motivasi)
$show_motivasi = false;
if (!isset($_SESSION['motivasi_shown'])) {
    $show_motivasi = true;
    $_SESSION['motivasi_shown'] = true;
}

// 1. AMBIL JAM OPERASIONAL & TENTUKAN "HARI INI"
$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulai = $setting['jam_mulai_hari'] ?? '08:00:00';

$sekarang = new DateTime();
$jamSekarang = $sekarang->format('H:i:s');

if ($jamSekarang < $jamMulai) {
    $tglBisnis = date('Y-m-d', strtotime('-1 day'));
} else {
    $tglBisnis = date('Y-m-d');
}

$start_hari = "$tglBisnis $jamMulai";
$end_hari   = date('Y-m-d H:i:s', strtotime("$start_hari +1 day"));

// 2. AMBIL INFO CABANG (home_branch_id)
$stmtInfo = $pdo->prepare("SELECT u.*, b.nama_cabang, b.alamat 
                            FROM users u 
                            LEFT JOIN branches b ON u.home_branch_id = b.id 
                            WHERE u.id = ?");
$stmtInfo->execute([$terapis_id]);
$userData = $stmtInfo->fetch();
$foto_url = (!empty($userData['foto_profil']) && file_exists("../assets/uploads/" . $userData['foto_profil'])) 
            ? "../assets/uploads/" . $userData['foto_profil'] 
            : "../assets/default_user.png";
$nama_cabang = $userData['nama_cabang'] ?? 'Belum ditentukan';
$home_branch_id = $userData['home_branch_id'];

// 3. CEK STATUS TERAPIS (sedang proses atau standby)
$stmtStatus = $pdo->prepare("SELECT t.id, t.nama_pelanggan, t.waktu_mulai, t.durasi_menit, 
                                     t.waktu_selesai, b.nama_cabang as cabang_aktif,
                                     p.nama_paket
                              FROM transactions t
                              LEFT JOIN branches b ON t.branch_id = b.id
                              LEFT JOIN packages p ON t.package_id = p.id
                              WHERE t.terapis_id = ? AND t.status = 'proses'
                              LIMIT 1");
$stmtStatus->execute([$terapis_id]);
$transaksiAktif = $stmtStatus->fetch();
$status_terapis = $transaksiAktif ? 'sibuk' : 'standby';

// 4. CEK APAKAH SEDANG DIPINJAM KE CABANG LAIN
$stmtLoan = $pdo->prepare("SELECT tl.*, b.nama_cabang as cabang_tujuan 
                            FROM terapis_loans tl 
                            JOIN branches b ON tl.to_branch_id = b.id
                            WHERE tl.terapis_id = ? AND tl.status = 'active' 
                            LIMIT 1");
$stmtLoan->execute([$terapis_id]);
$loanAktif = $stmtLoan->fetch();

// 5. POSISI ANTRIAN DI CABANG HARI INI
// ★ Prioritas: kerja_hari_ini (sedikit kerja naik) → giliran_absen (urutan absen)
// Busy/Loaned TIDAK mempengaruhi posisi antrian, hanya visual
$posisi_antrian = null;
$total_antrian = null;
if ($home_branch_id && $status_terapis == 'standby') {
    $sqlAntrian = "SELECT u.id, u.nama_lengkap,
        (SELECT COUNT(*) FROM transactions t 
         WHERE t.terapis_id = u.id 
         AND t.status IN ('proses', 'menunggu_approval', 'menunggu_pembayaran')) as is_busy,
        (SELECT COUNT(*) FROM terapis_loans tl 
         JOIN transactions tlt ON tl.transaction_id = tlt.id 
         WHERE tl.terapis_id = u.id 
         AND tl.from_branch_id = ? 
         AND tl.status IN ('active', 'pending')
         AND tlt.status IN ('proses', 'menunggu_approval', 'menunggu_pembayaran')) as is_loaned,
        (SELECT COUNT(*) FROM transactions t2 
         WHERE t2.terapis_id = u.id 
         AND t2.created_at >= ? AND t2.created_at < ? 
         AND t2.status != 'batal') as kerja_hari_ini,
        (SELECT MAX(t3.waktu_selesai) FROM transactions t3 
         WHERE t3.terapis_id = u.id 
         AND t3.created_at >= ? AND t3.created_at < ? 
         AND t3.status IN ('selesai','proses','menunggu_pembayaran')) as last_selesai,
        ta.giliran as giliran_absen,
        ta.waktu_absen,
        ta.id as absen_id
        FROM users u
        LEFT JOIN terapis_attendance ta ON u.id = ta.terapis_id AND ta.branch_id = ? AND ta.tanggal = ?
        WHERE u.role = 'terapis' 
        AND u.home_branch_id = ?
        ORDER BY 
            (ta.id IS NULL) ASC,
            kerja_hari_ini ASC,
            IFNULL(ta.giliran, 9999) ASC,
            last_selesai ASC,
            u.nama_lengkap ASC";
    
    $stmtAntrian = $pdo->prepare($sqlAntrian);
    $stmtAntrian->execute([
        $home_branch_id,
        $start_hari, $end_hari,
        $start_hari, $end_hari,
        $home_branch_id, $tglBisnis,
        $home_branch_id
    ]);
    $antrianList = $stmtAntrian->fetchAll();
    
    // Hitung posisi: hanya terapis standby (tidak sibuk & tidak dipinjam) yang dihitung
    $standbyList = [];
    foreach ($antrianList as $item) {
        if ((int)$item['is_busy'] === 0 && (int)$item['is_loaned'] === 0) {
            $standbyList[] = $item;
        }
    }
    
    $total_antrian = count($standbyList);
    foreach ($standbyList as $idx => $item) {
        if ($item['id'] == $terapis_id) {
            $posisi_antrian = $idx + 1;
            break;
        }
    }
}

// 6. CEK STATUS ABSENSI TERAPIS HARI INI
$stmtCekAbsen = $pdo->prepare("SELECT ta.*, ats.status as sesi_status 
    FROM terapis_attendance ta 
    LEFT JOIN attendance_sessions ats ON ats.branch_id = ta.branch_id AND ats.tanggal = ta.tanggal
    WHERE ta.terapis_id = ? AND ta.tanggal = ?
    LIMIT 1");
$stmtCekAbsen->execute([$terapis_id, $tglBisnis]);
$absenHariIni = $stmtCekAbsen->fetch();
$sudahAbsen = ($absenHariIni !== false);
$giliranAbsen = $sudahAbsen ? $absenHariIni['giliran'] : null;
$metodeAbsen  = $sudahAbsen ? $absenHariIni['metode_absen'] : null;

// Cek apakah sesi absensi sedang terbuka
$stmtSesiAbsen = $pdo->prepare("SELECT id FROM attendance_sessions WHERE branch_id = ? AND tanggal = ? AND status = 'open'");
$stmtSesiAbsen->execute([$home_branch_id, $tglBisnis]);
$sesiAbsenOpen = ($stmtSesiAbsen->fetch() !== false);

// Cek izin/sakit hari ini
$stmtIzinDT = $pdo->prepare("SELECT * FROM terapis_izin WHERE terapis_id = ? AND tanggal = ? AND status IN ('disetujui','pending') ORDER BY id DESC LIMIT 1");
$stmtIzinDT->execute([$terapis_id, $tglBisnis]);
$izinHariIniDT = $stmtIzinDT->fetch(PDO::FETCH_ASSOC);
$izinDisetujuiDT = ($izinHariIniDT && $izinHariIniDT['status'] === 'disetujui');

// 7. STATISTIK OMSET
$stmtPending = $pdo->prepare("SELECT SUM(omset_terapis) FROM transactions WHERE terapis_id = ? AND commission_status = 'pending'");
$stmtPending->execute([$terapis_id]);
$totalPending = $stmtPending->fetchColumn() ?? 0;

$stmtAll = $pdo->prepare("SELECT SUM(omset_terapis) FROM transactions WHERE terapis_id = ?");
$stmtAll->execute([$terapis_id]);
$totalAll = $stmtAll->fetchColumn() ?? 0;

// 8. DATA TRANSAKSI HARIAN
$sqlHarian = "SELECT t.created_at, t.nama_pelanggan, t.omset_terapis, t.status, 
                     p.nama_paket, b.nama_cabang
              FROM transactions t
              LEFT JOIN packages p ON t.package_id = p.id
              JOIN branches b ON t.branch_id = b.id
              WHERE t.terapis_id = ? 
              AND t.created_at >= ? AND t.created_at < ?
              ORDER BY t.created_at DESC";
$stmtH = $pdo->prepare($sqlHarian);
$stmtH->execute([$terapis_id, $start_hari, $end_hari]);
$dataHarian = $stmtH->fetchAll();

$totalHariIni = 0;
foreach($dataHarian as $d) {
    if($d['status'] != 'batal') $totalHariIni += $d['omset_terapis'];
}

// 9. DATA GRAFIK OMSET 7 HARI TERAKHIR
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i day"));
    $tglLabel = date('d/m', strtotime("-$i day"));
    $chartLabels[] = $tglLabel;
    
    $stmtChart = $pdo->prepare("SELECT SUM(omset_terapis) FROM transactions 
                                 WHERE terapis_id = ? AND DATE(created_at) = ? AND status != 'batal'");
    $stmtChart->execute([$terapis_id, $tgl]);
    $chartData[] = (float)($stmtChart->fetchColumn() ?? 0);
}

// 10. KATA KATA MOTIVASI (array untuk random di JS)
$motivasiList = [
    "Setiap sentuhan terapimu membawa ketenangan bagi orang lain. Kamu luar biasa! 💆‍♂️",
    "Tanganmu adalah alat penyembuh. Teruslah berkarya dengan penuh hati! ✨",
    "Pelayanan terbaikmu hari ini adalah investasi reputasimu masa depan. Semangat! 🌟",
    "Kamu bukan sekadar terapis — kamu adalah pejuang kesehatan dan kebahagiaan! 💪",
    "Satu sesi yang kamu berikan dengan sepenuh hati bisa mengubah hari seseorang. Kamu hebat! 🌺",
    "Pekerjaan mulia dimulai dari niat yang ikhlas. Selamat bekerja, pahlawan kesehatan! 🏆",
    "Kerja keras hari ini adalah fondasi kesuksesan esok hari. Tetap semangat! 🔥",
    "Senyummu adalah awal dari pelayanan terbaik. Hari ini pasti luar biasa! 😊",
    "Ketekunanmu hari ini akan berbuah manis. Terus berusaha dan jangan menyerah! 🌸",
    "Dedikasi dan profesionalismemu adalah kebanggaan tim. Terima kasih sudah hadir! 🎯"
];
$motivasiJson = json_encode($motivasiList);

// 11. SKOR REWARD — hitung dari seluruh pelanggaran (tidak reset harian)
$stmtSkor = $pdo->prepare("SELECT COUNT(*) FROM pelanggaran WHERE terapis_id = ? AND status != 'dibatalkan'");
$stmtSkor->execute([$terapis_id]);
$jumlahPelanggaranSkor = (int)$stmtSkor->fetchColumn();
$skorRewardMini        = max(0, 100 - ($jumlahPelanggaranSkor * 2));
if ($skorRewardMini >= 80)      $skorColorMini = '#27ae60';
elseif ($skorRewardMini >= 60)  $skorColorMini = '#f39c12';
elseif ($skorRewardMini >= 40)  $skorColorMini = '#e67e22';
else                            $skorColorMini = '#e74c3c';
// SVG circle params: r=28, circumference ≈ 175.93
$miniR    = 28;
$miniCirc = round(2 * M_PI * $miniR, 2);
$miniOff  = round($miniCirc * (100 - $skorRewardMini) / 100, 2);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Terapis</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .terapis-header {
            background: linear-gradient(135deg, #2c3e50, #3498db); color: white;
            padding: 25px; border-radius: 12px; margin-bottom: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex; justify-content: space-between; align-items: center;
        }
        .profile-box { display: flex; align-items: center; gap: 15px; }
        .profile-avatar {
            width: 60px; height: 60px; border-radius: 50%; object-fit: cover;
            border: 3px solid rgba(255,255,255,0.3); background: white;
        }
        .btn-logout {
            background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
            color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 13px;
        }
        .stat-card.orange { border-left: 5px solid #e67e22; }
        .stat-card.green { border-left: 5px solid #27ae60; }
        .stat-card.blue { border-left: 5px solid #3498db; }
        .stat-card.purple { border-left: 5px solid #9b59b6; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }

        /* Status Badge */
        .status-badge-standby {
            display: inline-flex; align-items: center; gap: 6px;
            background: #e8f5e9; color: #2e7d32;
            padding: 6px 14px; border-radius: 20px; font-weight: bold; font-size: 13px;
        }
        .status-badge-sibuk {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fff3e0; color: #e65100;
            padding: 6px 14px; border-radius: 20px; font-weight: bold; font-size: 13px;
        }
        .status-badge-dipinjam {
            display: inline-flex; align-items: center; gap: 6px;
            background: #e3f2fd; color: #1565c0;
            padding: 6px 14px; border-radius: 20px; font-weight: bold; font-size: 13px;
        }
        .pulse-dot {
            width: 10px; height: 10px; border-radius: 50%;
            display: inline-block; animation: pulse 1.5s infinite;
        }
        .dot-green { background: #2e7d32; }
        .dot-orange { background: #e65100; }
        .dot-blue { background: #1565c0; }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* Info Cabang Card */
        .cabang-info-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border-radius: 12px; padding: 20px;
            margin-bottom: 20px; display: flex;
            justify-content: space-between; align-items: center;
            box-shadow: 0 4px 15px rgba(102,126,234,0.3);
        }
        .cabang-info-card .info-item { text-align: center; }
        .cabang-info-card .info-label { font-size: 11px; opacity: 0.8; text-transform: uppercase; margin-bottom: 4px; }
        .cabang-info-card .info-value { font-size: 16px; font-weight: bold; }

        /* Antrian Card */
        .antrian-card {
            background: white; border-radius: 12px; padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 20px;
            border-top: 4px solid #f39c12;
        }
        .antrian-number {
            font-size: 48px; font-weight: 900; color: #f39c12;
            line-height: 1; margin: 5px 0;
        }

        /* Motivasi Modal */
        .motivasi-overlay {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(5px);
            display: flex; align-items: center; justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        .motivasi-modal {
            background: white; border-radius: 20px; padding: 40px 35px;
            max-width: 460px; width: 90%; text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.4s ease;
            position: relative; overflow: hidden;
        }
        .motivasi-modal::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px;
            background: linear-gradient(90deg, #f093fb, #f5576c, #4facfe, #00f2fe);
        }
        .motivasi-icon {
            font-size: 64px; margin-bottom: 15px; display: block;
        }
        .motivasi-greeting {
            font-size: 14px; color: #7f8c8d; margin-bottom: 8px;
        }
        .motivasi-name {
            font-size: 24px; font-weight: 800; color: #2c3e50; margin-bottom: 20px;
        }
        .motivasi-text {
            font-size: 16px; color: #555; line-height: 1.7;
            background: #f8f9fa; border-radius: 12px; padding: 18px;
            margin-bottom: 25px; font-style: italic;
        }
        .motivasi-time {
            font-size: 12px; color: #bdc3c7; margin-bottom: 20px;
        }
        .btn-motivasi {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border: none; padding: 14px 40px;
            border-radius: 30px; font-size: 15px; font-weight: bold;
            cursor: pointer; width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-motivasi:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102,126,234,0.4);
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Loan Alert */
        .loan-alert {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white; border-radius: 12px; padding: 15px 20px;
            margin-bottom: 20px; display: flex; align-items: center; gap: 15px;
            box-shadow: 0 4px 15px rgba(79,172,254,0.3);
        }
        .chart-container { height: 200px; }
        .absen-status-banner {
            border-radius: 12px; padding: 16px 22px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .absen-status-banner.sudah { background: linear-gradient(135deg, #e8f8f0, #d5f5e3); border: 1.5px solid #27ae60; }
        .absen-status-banner.belum-open { background: linear-gradient(135deg, #fef9e7, #fdebd0); border: 1.5px solid #f39c12; }
        .absen-status-banner.belum-closed { background: linear-gradient(135deg, #fdedec, #fadbd8); border: 1.5px solid #e74c3c; }
        .absen-status-banner.izin-approved { background: linear-gradient(135deg, #fef3e7, #fdebd0); border: 1.5px solid #e67e22; }
        .absen-status-banner.sakit-approved { background: linear-gradient(135deg, #fde8e8, #fadbd8); border: 1.5px solid #c0392b; }
        .absen-status-banner.izin-approved .absen-title { color: #e67e22; }
        .absen-status-banner.sakit-approved .absen-title { color: #c0392b; }
        .absen-status-banner .absen-icon { font-size: 32px; flex-shrink: 0; }
        .absen-status-banner .absen-info { flex: 1; }
        .absen-status-banner .absen-title { font-weight: bold; font-size: 15px; }
        .absen-status-banner.sudah .absen-title { color: #1e8449; }
        .absen-status-banner.belum-open .absen-title { color: #d68910; }
        .absen-status-banner.belum-closed .absen-title { color: #c0392b; }
        .absen-status-banner .absen-sub { font-size: 12px; color: #7f8c8d; margin-top: 2px; }

        /* ===== REWARD MINI WIDGET (sidebar) ===== */
        .reward-mini-wrap {
            margin: 8px 10px 6px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            padding: 12px 10px 10px;
            text-decoration: none;
            display: block;
            transition: background 0.2s;
        }
        .reward-mini-wrap:hover { background: rgba(255,255,255,0.13); }
        .reward-mini-label {
            font-size: 10px; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; color: rgba(255,255,255,0.5);
            text-align: center; margin-bottom: 10px;
        }
        .reward-mini-body {
            display: flex; align-items: center; gap: 10px;
        }
        .reward-mini-svg-wrap {
            position: relative; width: 70px; height: 70px; flex-shrink: 0;
        }
        .reward-mini-svg-wrap svg { transform: rotate(-90deg); width: 70px; height: 70px; }
        .reward-mini-center {
            position: absolute; inset: 0;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center; line-height: 1;
        }
        .reward-mini-score {
            font-size: 20px; font-weight: 900; color: #fff;
        }
        .reward-mini-max { font-size: 9px; color: rgba(255,255,255,0.45); }
        .reward-mini-info { flex: 1; min-width: 0; }
        .reward-mini-info-title {
            font-size: 12px; font-weight: 700; color: #fff; margin-bottom: 3px;
        }
        .reward-mini-info-sub {
            font-size: 10px; color: rgba(255,255,255,0.5); line-height: 1.4;
        }
        .reward-mini-link {
            display: block; text-align: center;
            font-size: 10px; font-weight: 700;
            color: rgba(255,255,255,0.55);
            margin-top: 8px; letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <!-- MOTIVASI POPUP -->
    <?php if ($show_motivasi): ?>
    <div class="motivasi-overlay" id="motivasiOverlay">
        <div class="motivasi-modal">
            <span class="motivasi-icon" id="motivasiIcon">✨</span>
            <div class="motivasi-greeting">Selamat datang kembali,</div>
            <div class="motivasi-name"><?= htmlspecialchars($nama_terapis) ?> 👋</div>
            <div class="motivasi-text" id="motivasiText">Memuat kata-kata motivasi...</div>
            <div class="motivasi-time">
                📍 <?= htmlspecialchars($nama_cabang) ?> &bull; 
                <?= date('l, d F Y', strtotime($tglBisnis)) ?>
            </div>
            <button class="btn-motivasi" onclick="tutupMotivasi()">
                <i class="fas fa-bolt"></i> Siap Bekerja!
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="container-layout">
        <div class="sidebar">
            <div class="sidebar-header"><h2>💆 TERAPIS PANEL</h2></div>
            <div class="sidebar-menu">
                <a href="dashboard_terapis.php" class="menu-item active"><i>📊</i> Dashboard</a>
                <a href="absensi_terapis.php" class="menu-item"><i>📋</i> Absensi</a>
                <a href="riwayat_pendapatan.php" class="menu-item"><i>💰</i> Riwayat Omset</a>
                <a href="profil_terapis.php" class="menu-item"><i>👤</i> Profil Saya</a>
                <a href="../auth/logout_system.php" class="menu-item" style="color: #c0392b; margin-top: 50px;"><i>🚪</i> Logout</a>
            </div>

            <!-- REWARD MINI WIDGET -->
            <a href="skor_reward_terapis.php" class="reward-mini-wrap">
                <div class="reward-mini-label">⭐ Skor Reward</div>
                <div class="reward-mini-body">
                    <div class="reward-mini-svg-wrap">
                        <svg viewBox="0 0 70 70" xmlns="http://www.w3.org/2000/svg">
                            <circle fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="7"
                                    cx="35" cy="35" r="<?= $miniR ?>"/>
                            <circle fill="none"
                                    stroke="<?= $skorColorMini ?>"
                                    stroke-width="7"
                                    stroke-linecap="round"
                                    cx="35" cy="35" r="<?= $miniR ?>"
                                    stroke-dasharray="<?= $miniCirc ?>"
                                    stroke-dashoffset="<?= $miniOff ?>"/>
                        </svg>
                        <div class="reward-mini-center">
                            <div class="reward-mini-score" style="color:<?= $skorColorMini ?>;"><?= $skorRewardMini ?></div>
                            <div class="reward-mini-max">/ 100</div>
                        </div>
                    </div>
                    <div class="reward-mini-info">
                        <div class="reward-mini-info-title">Poin Kamu</div>
                        <div class="reward-mini-info-sub">
                            <?= $jumlahPelanggaranSkor ?> pelanggaran<br>
                            &minus;<?= $jumlahPelanggaranSkor * 2 ?> poin total
                        </div>
                    </div>
                </div>
                <div class="reward-mini-link">Lihat Detail &rarr;</div>
            </a>
            <!-- END REWARD MINI WIDGET -->

        </div>

        <div class="main-content">
            <!-- HEADER -->
            <div class="terapis-header">
                <div class="profile-box">
                    <img src="<?= $foto_url ?>" class="profile-avatar">
                    <div>
                        <h2 style="margin:0; font-size: 22px;">Halo, <?= htmlspecialchars($nama_terapis) ?></h2>
                        <p style="margin:5px 0 0; opacity: 0.9; font-size: 14px;">Shift Hari Ini: <strong><?= date('d M Y', strtotime($tglBisnis)) ?></strong></p>
                    </div>
                </div>
                <a href="profil_terapis.php" class="btn-logout"><i class="fas fa-cog"></i> Profil</a>
            </div>

            <!-- INFO CABANG -->
            <div class="cabang-info-card">
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-map-marker-alt"></i> Cabang Asal</div>
                    <div class="info-value"><?= htmlspecialchars($nama_cabang) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-circle-dot"></i> Status Saat Ini</div>
                    <div class="info-value">
                        <?php if ($loanAktif): ?>
                            <span style="background:rgba(255,255,255,0.2); padding: 4px 12px; border-radius:15px; font-size:13px;">
                                🔄 Dipinjam ke <?= htmlspecialchars($loanAktif['cabang_tujuan']) ?>
                            </span>
                        <?php elseif ($status_terapis == 'sibuk'): ?>
                            <span style="background:rgba(255,152,0,0.3); padding: 4px 12px; border-radius:15px; font-size:13px;">
                                🔴 Sibuk Melayani
                            </span>
                        <?php elseif ($izinDisetujuiDT): ?>
                            <span style="background:<?= $izinHariIniDT['jenis']==='sakit' ? 'rgba(192,57,43,0.2)' : 'rgba(230,126,34,0.2)' ?>; padding: 4px 12px; border-radius:15px; font-size:13px;">
                                <?= $izinHariIniDT['jenis']==='sakit' ? '🤒 Sakit' : '📨 Izin' ?>
                            </span>
                        <?php else: ?>
                            <span style="background:rgba(76,175,80,0.3); padding: 4px 12px; border-radius:15px; font-size:13px;">
                                🟢 Standby / Siap
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-calendar-day"></i> Transaksi Hari Ini</div>
                    <div class="info-value"><?= count($dataHarian) ?> sesi</div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-coins"></i> Omset Hari Ini</div>
                    <div class="info-value">Rp <?= number_format($totalHariIni, 0, ',', '.') ?></div>
                </div>
            </div>

            <!-- ALERT DIPINJAM -->
            <?php if ($loanAktif): ?>
            <div class="loan-alert">
                <i class="fas fa-exchange-alt" style="font-size: 28px;"></i>
                <div>
                    <strong>Kamu Sedang Dipinjam!</strong><br>
                    <small>Saat ini kamu bertugas di cabang <strong><?= htmlspecialchars($loanAktif['cabang_tujuan']) ?></strong> sejak <?= date('H:i, d M Y', strtotime($loanAktif['approved_at'])) ?></small>
                </div>
            </div>
            <?php endif; ?>

            <!-- BANNER STATUS ABSENSI -->
            <?php if ($sudahAbsen): ?>
            <div class="absen-status-banner sudah">
                <div class="absen-icon">&#9989;</div>
                <div class="absen-info">
                    <div class="absen-title">Kamu sudah absen hari ini &mdash; Giliran ke-<?= $giliranAbsen ?></div>
                    <div class="absen-sub">Dicatat pukul <?= date('H:i', strtotime($absenHariIni['waktu_absen'])) ?> &bull; Metode: <?= $metodeAbsen === 'scan' ? '&#128247; Scan Barcode' : '&#128241; Manual' ?></div>
                </div>
            </div>
            <?php elseif ($izinDisetujuiDT): ?>
            <?php $izJenisDT = $izinHariIniDT['jenis'] === 'sakit' ? 'Sakit' : 'Izin'; ?>
            <div class="absen-status-banner <?= $izinHariIniDT['jenis'] === 'sakit' ? 'sakit-approved' : 'izin-approved' ?>">
                <div class="absen-icon"><?= $izinHariIniDT['jenis'] === 'sakit' ? '&#129298;' : '&#128232;' ?></div>
                <div class="absen-info">
                    <div class="absen-title">Status Hari Ini: <?= $izJenisDT ?> (Disetujui)</div>
                    <div class="absen-sub">Pengajuan <?= strtolower($izJenisDT) ?> kamu telah disetujui Leader. Istirahat yang cukup!</div>
                </div>
            </div>
            <?php elseif ($izinHariIniDT && $izinHariIniDT['status'] === 'pending'): ?>
            <div class="absen-status-banner belum-open">
                <div class="absen-icon">&#9203;</div>
                <div class="absen-info">
                    <div class="absen-title">Pengajuan <?= ucfirst($izinHariIniDT['jenis']) ?> Menunggu Persetujuan</div>
                    <div class="absen-sub">Leader belum merespons pengajuan kamu. Silakan tunggu.</div>
                </div>
            </div>
            <?php elseif ($sesiAbsenOpen): ?>
            <div class="absen-status-banner belum-open">
                <div class="absen-icon">&#128308;</div>
                <div class="absen-info">
                    <div class="absen-title">Kamu belum absen hari ini!</div>
                    <div class="absen-sub">Sesi absensi sedang terbuka &mdash; <a href="absensi_terapis.php" style="color:#d68910; font-weight:bold;">Absen Sekarang &rarr;</a></div>
                </div>
            </div>
            <?php else: ?>
            <div class="absen-status-banner belum-closed">
                <div class="absen-icon">&#128274;</div>
                <div class="absen-info">
                    <div class="absen-title">Kamu belum absen hari ini</div>
                    <div class="absen-sub">Tunggu kasir membuka sesi absensi, atau tunjukkan barcode ke kasir.</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ALERT SEDANG MELAYANI -->
            <?php if ($transaksiAktif && !$loanAktif): ?>
            <div style="background: linear-gradient(135deg, #f093fb, #f5576c); color:white; border-radius:12px; padding:15px 20px; margin-bottom:20px; display:flex; align-items:center; gap:15px; box-shadow: 0 4px 15px rgba(240,93,251,0.3);">
                <i class="fas fa-hand-holding-heart" style="font-size: 28px;"></i>
                <div>
                    <strong>Sedang Melayani Customer</strong><br>
                    <small>
                        👤 <?= htmlspecialchars($transaksiAktif['nama_pelanggan']) ?> 
                        &bull; 📦 <?= htmlspecialchars($transaksiAktif['nama_paket'] ?? '-') ?>
                        &bull; ⏰ Mulai: <?= date('H:i', strtotime($transaksiAktif['waktu_mulai'])) ?>
                        <?php if ($transaksiAktif['waktu_selesai']): ?>
                        &bull; Selesai: <?= date('H:i', strtotime($transaksiAktif['waktu_selesai'])) ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                <!-- KARTU ANTRIAN -->
                <div class="antrian-card">
                    <div style="font-size:12px; color:#7f8c8d; text-transform:uppercase; font-weight:bold; margin-bottom:5px;">
                        <i class="fas fa-sort-numeric-up"></i> Posisi Antrian
                    </div>
                    <?php if ($status_terapis == 'sibuk'): ?>
                        <div class="antrian-number" style="color:#e74c3c;">—</div>
                        <div style="font-size:12px; color:#e74c3c; font-weight:bold;">Sedang Bertugas</div>
                    <?php elseif ($loanAktif): ?>
                        <div class="antrian-number" style="color:#3498db;">—</div>
                        <div style="font-size:12px; color:#3498db; font-weight:bold;">Dipinjam Cabang Lain</div>
                    <?php elseif ($posisi_antrian): ?>
                        <div class="antrian-number"><?= $posisi_antrian ?></div>
                        <div style="font-size:12px; color:#7f8c8d;">dari <?= $total_antrian ?> terapis standby</div>
                        <?php if ($posisi_antrian == 1): ?>
                        <div style="margin-top:8px; background:#fff8e1; color:#f57f17; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:bold;">
                            ⚡ Kamu selanjutnya!
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="antrian-number" style="color:#bdc3c7;">—</div>
                        <div style="font-size:12px; color:#bdc3c7;">Tidak dalam antrian</div>
                    <?php endif; ?>
                </div>

                <!-- SALDO BELUM CAIR -->
                <div class="stat-card orange">
                    <h3 style="color:#7f8c8d; font-size:12px; margin:0;">SALDO BELUM CAIR</h3>
                    <div class="value" style="color:#e67e22; font-size: 24px; font-weight: bold;">Rp <?= number_format($totalPending, 0, ',', '.') ?></div>
                    <div style="font-size:11px; color:#bdc3c7; margin-top:5px;">Menunggu pencairan</div>
                </div>

                <!-- TOTAL PENDAPATAN -->
                <div class="stat-card green">
                    <h3 style="color:#7f8c8d; font-size:12px; margin:0;">TOTAL PENDAPATAN</h3>
                    <div class="value" style="color:#27ae60; font-size: 24px; font-weight: bold;">Rp <?= number_format($totalAll, 0, ',', '.') ?></div>
                    <div style="font-size:11px; color:#bdc3c7; margin-top:5px;">Sepanjang karir</div>
                </div>
            </div>

            <!-- GRAFIK OMSET 7 HARI -->
            <div class="card" style="margin-bottom: 25px;">
                <div class="card-header">
                    <span>📈 Grafik Omset 7 Hari Terakhir</span>
                </div>
                <div style="padding: 15px;">
                    <div class="chart-container">
                        <canvas id="chartOmset"></canvas>
                    </div>
                </div>
            </div>

            <!-- TABEL TRANSAKSI HARIAN -->
            <div class="card">
                <div class="card-header">
                    <span>📅 Transaksi Hari Ini (Shift <?= substr($jamMulai,0,5) ?>)</span>
                    <span style="float:right; font-weight:bold; color:#27ae60;">Total: Rp <?= number_format($totalHariIni,0,',','.') ?></span>
                </div>
                <div class="table-container">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; color: #555;">
                                <th>Jam Input</th>
                                <th>Nama Customer</th>
                                <th>Paket Layanan</th>
                                <th class="text-right">Omset</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($dataHarian) > 0): ?>
                                <?php foreach($dataHarian as $d): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td>
                                        <i class="far fa-clock"></i> <?= date('H:i', strtotime($d['created_at'])) ?>
                                        <br><small style="color:#999;"><?= htmlspecialchars($d['nama_cabang']) ?></small>
                                    </td>
                                    <td><strong><?= htmlspecialchars($d['nama_pelanggan']) ?></strong></td>
                                    <td><?= htmlspecialchars($d['nama_paket']) ?></td>
                                    <td class="text-right" style="font-weight: bold; color: #2c3e50;">
                                        Rp <?= number_format($d['omset_terapis'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if($d['status'] == 'proses'): ?>
                                            <span class="badge" style="background:#fff3e0; color:#ef6c00;">Proses</span>
                                        <?php elseif($d['status'] == 'selesai'): ?>
                                            <span class="badge" style="background:#e8f5e9; color:#2e7d32;">Selesai</span>
                                        <?php else: ?>
                                            <span class="badge" style="background:#ffebee; color:#c62828;">Batal</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align: center; padding: 30px; color: #999;">Belum ada transaksi hari ini.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    // ===== MOTIVASI POPUP =====
    <?php if ($show_motivasi): ?>
    const motivasiList = <?= $motivasiJson ?>;
    const icons = ['✨','🌟','💪','🌺','🏆','🔥','😊','🎯','🌸','💆‍♂️'];
    const randomIndex = Math.floor(Math.random() * motivasiList.length);
    document.getElementById('motivasiText').textContent = motivasiList[randomIndex];
    document.getElementById('motivasiIcon').textContent = icons[randomIndex];

    function tutupMotivasi() {
        const overlay = document.getElementById('motivasiOverlay');
        overlay.style.transition = 'opacity 0.3s';
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 300);
    }

    // Tutup dengan klik luar modal
    document.getElementById('motivasiOverlay').addEventListener('click', function(e) {
        if (e.target === this) tutupMotivasi();
    });
    <?php endif; ?>

    // ===== GRAFIK OMSET =====
    const ctx = document.getElementById('chartOmset').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Omset (Rp)',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: 'rgba(52, 152, 219, 0.3)',
                borderColor: '#3498db',
                borderWidth: 2,
                borderRadius: 6,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Rp ' + context.raw.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + (value/1000).toFixed(0) + 'k';
                        }
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: { grid: { display: false } }
            }
        }
    });
    </script>
</body>
</html>