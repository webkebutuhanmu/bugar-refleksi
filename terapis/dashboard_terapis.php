<?php
session_start();
require_once '../sistem/config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'terapis') { 
    header("Location: login.php"); exit; 
}

// =========================================================
// MENCEGAH BROWSER MENYIMPAN CACHE (FIX TOMBOL BACK CHROME)
// =========================================================
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
// =========================================================

$terapis_id = $_SESSION['user_id'];
$nama_terapis = $_SESSION['nama'];

$show_motivasi = false;
if (!isset($_SESSION['motivasi_shown'])) {
    $show_motivasi = true;
    $_SESSION['motivasi_shown'] = true;
}

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

$stmtInfo = $pdo->prepare("SELECT u.*, b.nama_cabang, b.alamat FROM users u LEFT JOIN branches b ON u.home_branch_id = b.id WHERE u.id = ?");
$stmtInfo->execute([$terapis_id]);
$userData = $stmtInfo->fetch();
$foto_url = (!empty($userData['foto_profil']) && file_exists("../admin/assets/uploads/" . $userData['foto_profil'])) ? "../admin/assets/uploads/" . $userData['foto_profil'] : "../admin/assets/default_user.png";
$nama_cabang = $userData['nama_cabang'] ?? 'Belum ditentukan';
$home_branch_id = $userData['home_branch_id'];

$stmtStatus = $pdo->prepare("SELECT t.id, t.nama_pelanggan, t.waktu_mulai, t.durasi_menit, t.waktu_selesai, b.nama_cabang as cabang_aktif, p.nama_paket FROM transactions t LEFT JOIN branches b ON t.branch_id = b.id LEFT JOIN packages p ON t.package_id = p.id WHERE t.terapis_id = ? AND t.status = 'proses' LIMIT 1");
$stmtStatus->execute([$terapis_id]);
$transaksiAktif = $stmtStatus->fetch();
$status_terapis = $transaksiAktif ? 'sibuk' : 'standby';

$stmtLoan = $pdo->prepare("SELECT tl.*, b.nama_cabang as cabang_tujuan FROM terapis_loans tl JOIN branches b ON tl.to_branch_id = b.id WHERE tl.terapis_id = ? AND tl.status = 'active' LIMIT 1");
$stmtLoan->execute([$terapis_id]);
$loanAktif = $stmtLoan->fetch();

$posisi_antrian = null; $total_antrian = null;
if ($home_branch_id && $status_terapis == 'standby') {
    $sqlAntrian = "SELECT u.id, u.nama_lengkap,
        (SELECT COUNT(*) FROM transactions t WHERE t.terapis_id = u.id AND t.status IN ('proses', 'menunggu_approval', 'menunggu_pembayaran')) as is_busy,
        (SELECT COUNT(*) FROM terapis_loans tl JOIN transactions tlt ON tl.transaction_id = tlt.id WHERE tl.terapis_id = u.id AND tl.from_branch_id = ? AND tl.status IN ('active', 'pending') AND tlt.status IN ('proses', 'menunggu_approval', 'menunggu_pembayaran')) as is_loaned,
        (SELECT COUNT(*) FROM transactions t2 WHERE t2.terapis_id = u.id AND t2.created_at >= ? AND t2.created_at < ? AND t2.status != 'batal') as kerja_hari_ini,
        (SELECT MAX(t3.waktu_selesai) FROM transactions t3 WHERE t3.terapis_id = u.id AND t3.created_at >= ? AND t3.created_at < ? AND t3.status IN ('selesai','proses','menunggu_pembayaran')) as last_selesai,
        ta.giliran as giliran_absen, ta.waktu_absen, ta.id as absen_id
        FROM users u LEFT JOIN terapis_attendance ta ON u.id = ta.terapis_id AND ta.branch_id = ? AND ta.tanggal = ?
        WHERE u.role = 'terapis' AND u.home_branch_id = ?
        ORDER BY (ta.id IS NULL) ASC, kerja_hari_ini ASC, IFNULL(ta.giliran, 9999) ASC, last_selesai ASC, u.nama_lengkap ASC";
    $stmtAntrian = $pdo->prepare($sqlAntrian);
    $stmtAntrian->execute([$home_branch_id, $start_hari, $end_hari, $start_hari, $end_hari, $home_branch_id, $tglBisnis, $home_branch_id]);
    $antrianList = $stmtAntrian->fetchAll();
    
    $standbyList = [];
    foreach ($antrianList as $item) { if ((int)$item['is_busy'] === 0 && (int)$item['is_loaned'] === 0) { $standbyList[] = $item; } }
    $total_antrian = count($standbyList);
    foreach ($standbyList as $idx => $item) { if ($item['id'] == $terapis_id) { $posisi_antrian = $idx + 1; break; } }
}

$stmtCekAbsen = $pdo->prepare("SELECT ta.*, ats.status as sesi_status FROM terapis_attendance ta LEFT JOIN attendance_sessions ats ON ats.branch_id = ta.branch_id AND ats.tanggal = ta.tanggal WHERE ta.terapis_id = ? AND ta.tanggal = ? LIMIT 1");
$stmtCekAbsen->execute([$terapis_id, $tglBisnis]);
$absenHariIni = $stmtCekAbsen->fetch();
$sudahAbsen = ($absenHariIni !== false);
$giliranAbsen = $sudahAbsen ? $absenHariIni['giliran'] : null;
$metodeAbsen  = $sudahAbsen ? $absenHariIni['metode_absen'] : null;

$stmtSesiAbsen = $pdo->prepare("SELECT id FROM attendance_sessions WHERE branch_id = ? AND tanggal = ? AND status = 'open'");
$stmtSesiAbsen->execute([$home_branch_id, $tglBisnis]);
$sesiAbsenOpen = ($stmtSesiAbsen->fetch() !== false);

$stmtIzinDT = $pdo->prepare("SELECT * FROM terapis_izin WHERE terapis_id = ? AND tanggal = ? AND status IN ('disetujui','pending') ORDER BY id DESC LIMIT 1");
$stmtIzinDT->execute([$terapis_id, $tglBisnis]);
$izinHariIniDT = $stmtIzinDT->fetch(PDO::FETCH_ASSOC);
$izinDisetujuiDT = ($izinHariIniDT && $izinHariIniDT['status'] === 'disetujui');

$stmtPending = $pdo->prepare("SELECT SUM(omset_terapis) FROM transactions WHERE terapis_id = ? AND commission_status = 'pending'");
$stmtPending->execute([$terapis_id]);
$totalPending = $stmtPending->fetchColumn() ?? 0;

$stmtAll = $pdo->prepare("SELECT SUM(omset_terapis) FROM transactions WHERE terapis_id = ?");
$stmtAll->execute([$terapis_id]);
$totalAll = $stmtAll->fetchColumn() ?? 0;

$sqlHarian = "SELECT t.created_at, t.nama_pelanggan, t.omset_terapis, t.status, p.nama_paket, b.nama_cabang
              FROM transactions t LEFT JOIN packages p ON t.package_id = p.id JOIN branches b ON t.branch_id = b.id
              WHERE t.terapis_id = ? AND t.created_at >= ? AND t.created_at < ? ORDER BY t.created_at DESC";
$stmtH = $pdo->prepare($sqlHarian);
$stmtH->execute([$terapis_id, $start_hari, $end_hari]);
$dataHarian = $stmtH->fetchAll();
$totalHariIni = 0; foreach($dataHarian as $d) { if($d['status'] != 'batal') $totalHariIni += $d['omset_terapis']; }

$chartLabels = []; $chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i day")); $tglLabel = date('d/m', strtotime("-$i day"));
    $chartLabels[] = $tglLabel;
    $stmtChart = $pdo->prepare("SELECT SUM(omset_terapis) FROM transactions WHERE terapis_id = ? AND DATE(created_at) = ? AND status != 'batal'");
    $stmtChart->execute([$terapis_id, $tgl]);
    $chartData[] = (float)($stmtChart->fetchColumn() ?? 0);
}

$motivasiList = [
    "Setiap sentuhan terapimu membawa ketenangan bagi orang lain. Kamu luar biasa! 💆‍♂️",
    "Tanganmu adalah alat penyembuh. Teruslah berkarya dengan penuh hati! ✨",
    "Pelayanan terbaikmu hari ini adalah investasi reputasimu masa depan. Semangat! 🌟",
    "Kamu bukan sekadar terapis — kamu adalah pejuang kesehatan dan kebahagiaan! 💪",
    "Satu sesi yang kamu berikan dengan sepenuh hati bisa mengubah hari seseorang. Kamu hebat! 🌺"
];
$motivasiJson = json_encode($motivasiList);

$stmtSkor = $pdo->prepare("SELECT COUNT(*) FROM pelanggaran WHERE terapis_id = ? AND status != 'dibatalkan'");
$stmtSkor->execute([$terapis_id]);
$jumlahPelanggaranSkor = (int)$stmtSkor->fetchColumn();
$skorRewardMini = max(0, 100 - ($jumlahPelanggaranSkor * 2));
if ($skorRewardMini >= 80) $skorColorMini = '#27ae60'; elseif ($skorRewardMini >= 60) $skorColorMini = '#f39c12'; elseif ($skorRewardMini >= 40) $skorColorMini = '#e67e22'; else $skorColorMini = '#e74c3c';
$miniR = 28; $miniCirc = round(2 * M_PI * $miniR, 2); $miniOff = round($miniCirc * (100 - $skorRewardMini) / 100, 2);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Terapis</title>
    <link rel="stylesheet" href="assets/style_terapis.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .motivasi-overlay { position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); display: flex; align-items: center; justify-content: center; }
        .motivasi-modal { background: var(--bg-panel); color: var(--text-dark); border-radius: 16px; padding: 40px; max-width: 460px; text-align: center; border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .btn-motivasi { background: var(--accent-yellow); color: #000; font-weight: bold; padding: 12px 30px; border-radius: 8px; border: none; cursor: pointer; transition: 0.3s; }
        .btn-motivasi:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <?php if ($show_motivasi): ?>
    <div class="motivasi-overlay" id="motivasiOverlay">
        <div class="motivasi-modal">
            <span id="motivasiIcon" style="font-size: 50px;">✨</span>
            <div style="font-size:24px; font-weight:bold; margin:15px 0;">Halo, <?= htmlspecialchars($nama_terapis) ?></div>
            <div id="motivasiText" style="margin-bottom:25px; color:var(--text-muted); font-style:italic;">Memuat kata-kata...</div>
            <button class="btn-motivasi" onclick="document.getElementById('motivasiOverlay').style.display='none'">Siap Bekerja!</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="container-layout">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>💆 TERAPIS PANEL</h2></div>
            <div class="sidebar-menu">
                <a href="dashboard_terapis.php" class="menu-item active"><i>📊</i> Dashboard</a>
                <a href="absensi_terapis.php" class="menu-item"><i>📋</i> Absensi</a>
                <a href="riwayat_pendapatan.php" class="menu-item"><i>💰</i> Riwayat Omset</a>
                <a href="profil_terapis.php" class="menu-item"><i>👤</i> Profil Saya</a>
                <a href="skor_reward_terapis.php" class="menu-item"><i>⭐</i> Skor Reward</a>
                <a href="logout.php" class="menu-item" style="color: var(--accent-red); margin-top: 50px;"><i>🚪</i> Logout</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div class="topbar-left">
                    <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')"><i class="fas fa-bars"></i></button>
                    <h1>Dashboard</h1>
                </div>
                <div style="display:flex; align-items:center; gap:15px;">
                    <span style="font-size:13px; color:var(--text-muted); font-weight:bold;">Shift: <?= date('d M Y', strtotime($tglBisnis)) ?></span>
                    <button class="theme-toggle" onclick="toggleTheme()" id="theme-btn"><i class="fas fa-moon"></i> Dark</button>
                </div>
            </div>

            <div class="card" style="display:flex; align-items:center; gap:20px; padding:25px;">
                <img src="<?= $foto_url ?>" style="width:70px; height:70px; border-radius:50%; object-fit:cover; border:3px solid var(--accent-yellow);">
                <div>
                    <h2 style="margin:0; font-size:24px; color:var(--text-dark);">Halo, <?= htmlspecialchars($nama_terapis) ?></h2>
                    <p style="margin:5px 0 0; color:var(--text-muted); font-size:14px;">Semangat bertugas hari ini! Berikan pelayanan terbaik.</p>
                </div>
            </div>

            <div class="card" style="display:flex; justify-content:space-between; align-items:center; text-align:center; flex-wrap:wrap; gap:15px; padding:25px;">
                <div style="flex:1;">
                    <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:bold; letter-spacing:1px;">Cabang Asal</div>
                    <div style="font-size:18px; font-weight:bold; color:var(--text-dark); margin-top:5px;"><?= htmlspecialchars($nama_cabang) ?></div>
                </div>
                <div style="flex:1; border-left:1px dashed var(--border-color); border-right:1px dashed var(--border-color);">
                    <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:bold; letter-spacing:1px;">Transaksi Hari Ini</div>
                    <div style="font-size:18px; font-weight:bold; color:var(--text-dark); margin-top:5px;"><?= count($dataHarian) ?> Sesi</div>
                </div>
                <div style="flex:1;">
                    <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:bold; letter-spacing:1px;">Omset Hari Ini</div>
                    <div style="font-size:18px; font-weight:bold; color:var(--accent-green); margin-top:5px;">Rp <?= number_format($totalHariIni, 0, ',', '.') ?></div>
                </div>
            </div>

            <div class="grid-3">
                <div class="stat-card" style="border-top: 4px solid var(--accent-yellow); text-align:center;">
                    <small style="color:var(--text-muted); font-weight:bold; letter-spacing:1px;">POSISI ANTRIAN</small>
                    <div style="font-size:52px; font-weight:900; color:var(--accent-yellow); margin:10px 0;"><?= $posisi_antrian ?? '-' ?></div>
                    <small style="color:var(--text-muted);">dari <?= $total_antrian ?? 0 ?> terapis standby</small>
                </div>
                <div class="stat-card" style="border-left: 4px solid var(--accent-red);">
                    <small style="color:var(--text-muted); font-weight:bold; letter-spacing:1px;">SALDO BELUM CAIR</small>
                    <div style="font-size:28px; font-weight:bold; color:var(--text-dark); margin-top:15px;">Rp <?= number_format($totalPending, 0, ',', '.') ?></div>
                </div>
                <div class="stat-card" style="border-left: 4px solid var(--accent-green);">
                    <small style="color:var(--text-muted); font-weight:bold; letter-spacing:1px;">TOTAL PENDAPATAN</small>
                    <div style="font-size:28px; font-weight:bold; color:var(--accent-green); margin-top:15px;">Rp <?= number_format($totalAll, 0, ',', '.') ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">📈 Grafik Omset 7 Hari Terakhir</div>
                <div style="height:250px;"><canvas id="chartOmset"></canvas></div>
            </div>

            <div class="card">
                <div class="card-header">📅 Transaksi Hari Ini</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Customer</th>
                                <th>Paket</th>
                                <th style="text-align:right;">Omset</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dataHarian as $d): ?>
                            <tr>
                                <td><?= date('H:i', strtotime($d['created_at'])) ?></td>
                                <td><strong style="color:var(--text-dark);"><?= htmlspecialchars($d['nama_pelanggan']) ?></strong></td>
                                <td><?= htmlspecialchars($d['nama_paket']) ?></td>
                                <td style="text-align:right; font-weight:bold; color:var(--accent-green);">Rp <?= number_format($d['omset_terapis'], 0, ',', '.') ?></td>
                                <td><span style="background:var(--bg-input); padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold; border:1px solid var(--border-color);"><?= strtoupper($d['status']) ?></span></td>
                            </tr>
                            <?php endforeach; if(count($dataHarian)==0) echo "<tr><td colspan='5' style='text-align:center; padding:30px; color:var(--text-muted);'>Belum ada transaksi hari ini.</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>

    <script>
    <?php if ($show_motivasi): ?>
        const mList = <?= $motivasiJson ?>; const icons = ['✨','🌟','💪','🌺','🏆'];
        const rIdx = Math.floor(Math.random() * mList.length);
        document.getElementById('motivasiText').textContent = mList[rIdx];
        document.getElementById('motivasiIcon').textContent = icons[rIdx % icons.length];
    <?php endif; ?>

    // Fitur Dark/Light Mode Universal
    function toggleTheme() {
        const b = document.documentElement; const isD = b.getAttribute('data-theme') === 'dark';
        b.setAttribute('data-theme', isD ? 'light' : 'dark'); localStorage.setItem('theme', isD ? 'light' : 'dark');
        document.getElementById('theme-btn').innerHTML = isD ? '<i class="fas fa-moon"></i> Dark' : '<i class="fas fa-sun"></i> Light';
        updateChartColor(isD ? 'light' : 'dark'); // Update warna grafik otomatis
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        const sTheme = localStorage.getItem('theme') || 'dark'; document.documentElement.setAttribute('data-theme', sTheme);
        document.getElementById('theme-btn').innerHTML = sTheme === 'dark' ? '<i class="fas fa-sun"></i> Light' : '<i class="fas fa-moon"></i> Dark';
    });

    // Inisialisasi Grafik Chart.js
    const ctx = document.getElementById('chartOmset').getContext('2d');
    let myChart = new Chart(ctx, { 
        type: 'bar', 
        data: { 
            labels: <?= json_encode($chartLabels) ?>, 
            datasets: [{ 
                label: 'Omset', 
                data: <?= json_encode($chartData) ?>, 
                backgroundColor: '#FFD600', // Warna kuning accent default
                borderRadius: 4
            }] 
        }, 
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(127,140,141,0.1)' } },
                x: { grid: { display: false } }
            }
        } 
    });

    // Fungsi tambahan untuk mengubah warna grafik menyesuaikan tema (Opsional tapi rapi)
    function updateChartColor(theme) {
        myChart.options.scales.y.grid.color = theme === 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';
        myChart.update();
    }
    </script>
</body>
</html>