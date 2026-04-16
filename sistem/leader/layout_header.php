<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'leader') {
    header("Location: ../auth/login_system.php"); exit;
}

$userId = $_SESSION['user_id'];
$branchId = $_SESSION['user_branch_id'];
$activePage = basename($_SERVER['PHP_SELF']);

// Ambil Data User & Foto Profil
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userMe = $stmtUser->fetch();
$fotoPath = !empty($userMe['foto_profil']) ? "../uploads/profil/" . $userMe['foto_profil'] : "../assets/img/default-avatar.png";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Leader Panel - Bugar Refleksi</title>
    <style>
        :root { --sidebar-w: 250px; --primary: #2c3e50; --accent: #3498db; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; margin: 0; display: flex; }
        .sidebar { width: var(--sidebar-w); background: var(--primary); height: 100vh; position: fixed; color: white; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; font-weight: bold; font-size: 18px; }
        .profile-section { padding: 20px; text-align: center; }
        .img-nav { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent); }
        .nav-menu { flex: 1; margin-top: 10px; }
        .nav-link { display: block; padding: 12px 20px; color: #bdc3c7; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: #34495e; color: white; border-left: 4px solid var(--accent); }
        .btn-logout { background: #c0392b; color: white; padding: 15px; text-align: center; text-decoration: none; font-weight: bold; }
        .main { margin-left: var(--sidebar-w); flex: 1; padding: 30px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background: #f8f9fa; color: #333; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .bg-blue { background: #d1ecf1; color: #0c5460; }
        .bg-orange { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">LEADER DASHBOARD</div>
    <div class="profile-section">
        <img src="<?= $fotoPath ?>" class="img-nav">
        <div style="margin-top:10px; font-size:14px; font-weight:bold;"><?= htmlspecialchars($userMe['nama_lengkap']) ?></div>
        <small style="color: #95a5a6;">Cabang ID: <?= $branchId ?></small>
    </div>
    <div class="nav-menu">
        <a href="dashboard_leader.php" class="nav-link <?= $activePage=='dashboard_leader.php'?'active':'' ?>">🏠 Dashboard</a>
        <a href="data_terapis.php" class="nav-link <?= $activePage=='data_terapis.php'?'active':'' ?>">👥 Data Terapis & Bantuan</a>
        <a href="monitoring_terapis.php" class="nav-link <?= $activePage=='monitoring_terapis.php'?'active':'' ?>">🔄 Monitoring Terapis</a>
        <a href="statistik_cabang.php" class="nav-link <?= $activePage=='statistik_cabang.php'?'active':'' ?>">📊 Statistik Omset</a>
        <a href="profil.php" class="nav-link <?= $activePage=='profil.php'?'active':'' ?>">👤 Profil Saya</a>
    </div>
    <a href="../auth/logout_system.php" class="btn-logout">KELUAR</a>
</div>

<div class="main">