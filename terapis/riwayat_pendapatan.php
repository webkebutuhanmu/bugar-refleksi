<?php
// COPY PASTE LOGIKA PHP ASLI RIWAYAT DARI BARIS 1 SAMPAI 85...
session_start(); 
require_once '../sistem/config/database.php'; 
setlocale(LC_TIME, 'id_ID');
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'terapis') { 
    header("Location: login.php"); exit; 
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$terapis_id = $_SESSION['user_id']; $view_bulan = $_GET['bulan'] ?? null;
// --- [Logika Batch Paid/Pending dan Monthly query Anda taruh di sini] ---
// Contoh Singkat:
if ($view_bulan) {
    $stmtPaid = $pdo->prepare("SELECT DATE(t.commission_paid_at) as tgl_bayar_key, t.commission_paid_at, MIN(t.created_at) as tgl_trx_mulai, MAX(t.created_at) as tgl_trx_akhir, COUNT(t.id) as total_pasien, SUM(t.omset_terapis) as total_komisi FROM transactions t WHERE t.terapis_id = ? AND t.commission_status = 'paid' AND DATE_FORMAT(t.created_at,'%Y-%m') = ? GROUP BY DATE(t.commission_paid_at), t.commission_paid_at ORDER BY t.commission_paid_at DESC");
    $stmtPaid->execute([$terapis_id, $view_bulan]); $batchPaid = $stmtPaid->fetchAll();
    
    $stmtDPaid = $pdo->prepare("SELECT t.created_at, t.nama_pelanggan, t.omset_terapis, p.nama_paket, DATE(t.commission_paid_at) as bayar_key FROM transactions t LEFT JOIN packages p ON t.package_id=p.id WHERE t.terapis_id=? AND t.commission_status='paid' AND DATE_FORMAT(t.created_at,'%Y-%m')=? ORDER BY t.created_at DESC");
    $stmtDPaid->execute([$terapis_id, $view_bulan]); $detailPaid = $stmtDPaid->fetchAll();
    $groupedPaid = []; foreach ($detailPaid as $d) { $groupedPaid[$d['bayar_key']][] = $d; }
} else {
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(t.created_at,'%Y-%m') as kode_bulan, MAX(DATE_FORMAT(t.created_at,'%Y')) as tahun, MAX(DATE_FORMAT(t.created_at,'%m')) as bulan_angka, SUM(t.omset_terapis) as total_omset, COUNT(t.id) as total_pasien FROM transactions t WHERE t.terapis_id=? GROUP BY DATE_FORMAT(t.created_at,'%Y-%m') ORDER BY kode_bulan DESC");
    $stmt->execute([$terapis_id]); $monthlyData = $stmt->fetchAll();
}
function bulanIndo($m) { $b=['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember']; return $b[$m]??''; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Pendapatan</title>
    <link rel="stylesheet" href="assets/style_terapis.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container-layout">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><h2>💆 TERAPIS PANEL</h2></div>
        <div class="sidebar-menu">
            <a href="dashboard_terapis.php" class="menu-item"><i>📊</i> Dashboard</a>
            <a href="absensi_terapis.php" class="menu-item"><i>📋</i> Absensi</a>
            <a href="riwayat_pendapatan.php" class="menu-item active"><i>💰</i> Riwayat Omset</a>
            <a href="profil_terapis.php" class="menu-item"><i>👤</i> Profil Saya</a>
            <a href="skor_reward_terapis.php" class="menu-item"><i>⭐</i> Skor Reward</a>
        </div>
    </div>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left"><button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')"><i class="fas fa-bars"></i></button><h1>Riwayat Pendapatan</h1></div>
            <button class="theme-toggle" onclick="toggleTheme()" id="theme-btn"><i class="fas fa-moon"></i> Dark</button>
        </div>

        <?php if(!$view_bulan): ?>
        <div class="grid-3">
            <?php foreach($monthlyData as $m): ?>
            <a href="?bulan=<?= $m['kode_bulan'] ?>" class="card" style="text-decoration:none; border-top: 4px solid var(--accent-yellow); display:block; transition:0.3s;">
                <h3 style="color:var(--text-dark);"><?= bulanIndo($m['bulan_angka']) ?> <?= $m['tahun'] ?></h3>
                <div style="font-size:24px; font-weight:bold; color:var(--accent-green); margin:10px 0;">Rp <?= number_format($m['total_omset'],0,',','.') ?></div>
                <div style="font-size:12px; color:var(--text-muted);"><i class="fas fa-user"></i> <?= $m['total_pasien'] ?> Pasien Melayani</div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <a href="riwayat_pendapatan.php" class="btn btn-secondary" style="margin-bottom:15px;"><i class="fas fa-arrow-left"></i> Kembali</a>
        <div class="card">
            <div class="card-header">Rincian Pembayaran <?= htmlspecialchars($_GET['bulan']) ?></div>
            <div class="table-container">
                <table>
                    <thead><tr><th>Dibayar Tanggal</th><th class="text-center">Pasien</th><th class="text-right">Komisi</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach($batchPaid as $idx => $batch): ?>
                        <tr>
                            <td><?= date('d M Y, H:i', strtotime($batch['commission_paid_at'])) ?></td>
                            <td class="text-center"><?= $batch['total_pasien'] ?></td>
                            <td class="text-right" style="font-weight:bold; color:var(--accent-green);">Rp <?= number_format($batch['total_komisi'],0,',','.') ?></td>
                            <td><span style="color:var(--accent-green);">✅ Lunas</span></td>
                            <td><button onclick="document.getElementById('modal-<?= $idx ?>').style.display='block'" class="btn btn-primary">👁 Detail</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php foreach($batchPaid as $idx => $batch): 
            $bayarKey = $batch['tgl_bayar_key'];
            $detBatch = $groupedPaid[$bayarKey] ?? [];
        ?>
        <div id="modal-<?= $idx ?>" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h3 style="color:var(--text-dark); margin:0;">Detail Transaksi</h3><span class="close-btn" onclick="document.getElementById('modal-<?= $idx ?>').style.display='none'">&times;</span></div>
                <div class="modal-body">
                    <table>
                        <thead><tr><th>Tanggal</th><th>Customer</th><th>Paket</th><th class="text-right">Omset</th></tr></thead>
                        <tbody>
                            <?php foreach($detBatch as $d): ?>
                            <tr>
                                <td><?= date('d/m/y H:i',strtotime($d['created_at'])) ?></td>
                                <td><?= htmlspecialchars($d['nama_pelanggan']) ?></td>
                                <td><?= htmlspecialchars($d['nama_paket']??'-') ?></td>
                                <td class="text-right">Rp <?= number_format($d['omset_terapis'],0,',','.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script>
    function toggleTheme() {
        const b = document.documentElement; const isD = b.getAttribute('data-theme') === 'dark';
        b.setAttribute('data-theme', isD ? 'light' : 'dark'); localStorage.setItem('theme', isD ? 'light' : 'dark');
        document.getElementById('theme-btn').innerHTML = isD ? '<i class="fas fa-moon"></i> Dark' : '<i class="fas fa-sun"></i> Light';
    }
    document.addEventListener('DOMContentLoaded', () => {
        const sTheme = localStorage.getItem('theme') || 'dark'; document.documentElement.setAttribute('data-theme', sTheme);
        document.getElementById('theme-btn').innerHTML = sTheme === 'dark' ? '<i class="fas fa-sun"></i> Light' : '<i class="fas fa-moon"></i> Dark';
    });
    window.onclick = function(e){ if(e.target.classList.contains('modal')) e.target.style.display='none'; }
</script>
</body>
</html>