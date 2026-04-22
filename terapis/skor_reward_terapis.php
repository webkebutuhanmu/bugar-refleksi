<?php
session_start();
require_once '../sistem/config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'terapis') { 
    header("Location: login.php"); exit; 
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$terapis_id = $_SESSION['user_id'];
$nama_terapis = $_SESSION['nama'];

// Logika Poin & Pelanggaran tetap sesuai kode asli Anda
$stmtPel = $pdo->prepare("SELECT * FROM pelanggaran WHERE terapis_id = ? AND status != 'dibatalkan' ORDER BY created_at DESC");
$stmtPel->execute([$terapis_id]); $riwayatPelanggaran = $stmtPel->fetchAll(PDO::FETCH_ASSOC);
$jumlahPelanggaran = count($riwayatPelanggaran);
$skorReward = max(0, 100 - ($jumlahPelanggaran * 2));

// SVG Circle math
$bigR = 70; $bigCirc = round(2 * M_PI * $bigR, 2); $bigOff = round($bigCirc * (100 - $skorReward) / 100, 2);
$skorColor = ($skorReward >= 80) ? '#27ae60' : (($skorReward >= 50) ? '#f39c12' : '#e74c3c');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skor Reward</title>
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
            <a href="riwayat_pendapatan.php" class="menu-item"><i>💰</i> Riwayat Omset</a>
            <a href="profil_terapis.php" class="menu-item"><i>👤</i> Profil Saya</a>
            <a href="skor_reward_terapis.php" class="menu-item active"><i>⭐</i> Skor Reward</a>
            <a href="logout.php" class="menu-item" style="color: #e74c3c; margin-top: 50px;"><i>🚪</i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')"><i class="fas fa-bars"></i></button>
                <h1>Skor Reward Saya</h1>
            </div>
            <button class="theme-toggle" onclick="toggleTheme()" id="theme-btn"><i class="fas fa-moon"></i> Dark</button>
        </div>

        <div class="skor-hero">
            <div style="position:relative; width:160px; height:160px;">
                <svg viewBox="0 0 160 160" style="transform: rotate(-90deg); width:160px; height:160px;">
                    <circle cx="80" cy="80" r="<?= $bigR ?>" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="12"/>
                    <circle cx="80" cy="80" r="<?= $bigR ?>" fill="none" stroke="<?= $skorColor ?>" stroke-width="12" stroke-dasharray="<?= $bigCirc ?>" stroke-dashoffset="<?= $bigOff ?>" style="transition:0.8s;"/>
                </svg>
                <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                    <div style="font-size:42px; font-weight:bold; color:white;"><?= $skorReward ?></div>
                    <div style="font-size:12px; color:rgba(255,255,255,0.6);">Poin</div>
                </div>
            </div>
            <div style="flex:1;">
                <h2 style="color:white; margin-bottom:10px;">Status Kamu: <?= ($skorReward >= 80) ? 'Sangat Baik' : 'Perlu Perhatian' ?></h2>
                <p style="color:rgba(255,255,255,0.8); font-size:14px;">Total Pelanggaran: <?= $jumlahPelanggaran ?> Kali</p>
                <div style="background:rgba(255,255,255,0.1); padding:10px; border-radius:10px; margin-top:15px; font-size:12px; color:white;">
                    <i>"Setiap pelanggaran akan mengurangi 2 poin dari skor maksimal 100."</i>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 class="card-header">Riwayat Pelanggaran</h3>
            <div class="table-container">
                <table>
                    <thead><tr><th>Tanggal</th><th>Kejadian</th><th>Poin</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach($riwayatPelanggaran as $p): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($p['tanggal'])) ?></td>
                            <td><strong><?= htmlspecialchars($p['judul']) ?></strong><br><small><?= htmlspecialchars($p['deskripsi']) ?></small></td>
                            <td style="color:var(--accent-red); font-weight:bold;">-2</td>
                            <td><?= strtoupper($p['status']) ?></td>
                        </tr>
                        <?php endforeach; if($jumlahPelanggaran==0) echo "<tr><td colspan='4' style='text-align:center;'>Belum ada catatan pelanggaran. Hebat!</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
</script>
</body>
</html>