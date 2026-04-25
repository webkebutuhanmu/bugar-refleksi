<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/koneksi.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

$stmtMe = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtMe->execute([$user_id]);
$me = $stmtMe->fetch();

// Format penamaan file sesuai role
$x = ($role === 'supervisor') ? 'spv' : $role;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bugar App</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary: #5856D6; --primary-light: #7E7CE6; --success: #34C759; --danger: #FF3B30; --warning: #FF9500; --bg: #F2F2F7; --card-bg: #FFFFFF; --text: #1C1C1E; --sidebar: #1C1C1E; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: var(--bg); color: var(--text); margin: 0; display: flex; height: 100vh; height: 100dvh; overflow: hidden; }
        .fade-in { animation: fadeIn 0.6s ease-out; } .slide-up { animation: slideUp 0.5s ease-out forwards; opacity: 0; transform: translateY(15px); }
        .delay-1 { animation-delay: 0.1s; } .delay-2 { animation-delay: 0.2s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } } @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }
        .sidebar { width: 260px; background: var(--sidebar); color: white; display: flex; flex-direction: column; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1000; box-shadow: 2px 0 15px rgba(0,0,0,0.1); overflow-y: auto; -webkit-overflow-scrolling: touch; }
        .sidebar::-webkit-scrollbar { width: 0px; background: transparent; }
        .sidebar-header { padding: 25px 20px; font-size: 22px; font-weight: 800; border-bottom: 1px solid rgba(255,255,255,0.1); letter-spacing: -0.5px; background: linear-gradient(135deg, #1C1C1E, #2C2C2E); }
        .sidebar-menu { flex: 0 0 auto; padding: 15px 0; }
        .menu-item { padding: 14px 20px; color: #A1A1A6; text-decoration: none; display: flex; align-items: center; gap: 12px; font-size: 15px; font-weight: 500; transition: all 0.2s; border-left: 4px solid transparent; }
        .menu-item:hover { background: rgba(255,255,255,0.05); color: white; border-left-color: var(--primary); padding-left: 24px; }
        .menu-item i { width: 20px; text-align: center; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); background: #151515; margin-top: auto; }
        .user-info { margin-bottom: 15px; font-size: 13px; }
        .btn-logout { background: rgba(255, 59, 48, 0.1); color: var(--danger); padding: 12px; border-radius: 12px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; font-size: 14px; transition: 0.2s; border: 1px solid rgba(255, 59, 48, 0.2); cursor: pointer; }
        .btn-logout:hover { background: var(--danger); color: white; }
        .main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .navbar { background: rgba(255,255,255,0.85); backdrop-filter: blur(15px); padding: 15px 20px; display: flex; align-items: center; gap: 15px; border-bottom: 1px solid rgba(0,0,0,0.05); z-index: 10; }
        .content { padding: 20px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
        .menu-toggle { display: none; font-size: 22px; background: none; border: none; cursor: pointer; color: var(--text); padding: 0; }
        .card { background: var(--card-bg); border-radius: 20px; padding: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.04); margin-bottom: 20px; transition: transform 0.2s; border: 1px solid rgba(0,0,0,0.02); }
        .card:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,0,0,0.06); }
        .card-title { font-weight: 700; font-size: 17px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: #1C1C1E; }
        .table-res { overflow-x: auto; border-radius: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 500px; }
        th { text-align: left; background: #F9F9F9; padding: 14px 10px; color: #8E8E93; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        td { padding: 15px 10px; border-bottom: 1px solid #F2F2F7; }
        .status-pill { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block;}
        .pill-tepat { background: #E2F9E9; color: #28CD41; }
        .pill-telat { background: #FFE5E5; color: #FF3B30; }
        .bg-gradient-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
        .bg-gradient-warning { background: linear-gradient(135deg, #FF9500, #FFB340); color: white; }
        @media (max-width: 768px) { .sidebar { position: fixed; height: 100%; height: 100dvh; transform: translateX(-100%); } .sidebar.active { transform: translateX(0); } .menu-toggle { display: block; } }
        .dashboard-grid { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 25px; }
        .info-card { flex: 1; min-width: 260px; margin-bottom: 0 !important; border:none; }
        .info-card-content { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 30px 15px; height: 100%; }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">BUGAR <span style="color:var(--success)">APP</span></div>
    <div class="sidebar-menu">
        <a href="dashboard_<?= $x ?>.php" class="menu-item"><i class="fas fa-border-all"></i> Dashboard</a>
        <?php if($role !== 'owner'): ?>
            <a href="absen_<?= $x ?>.php" class="menu-item"><i class="fas fa-fingerprint"></i> Absensi</a>
        <?php endif; ?>
        <a href="riwayat_<?= $x ?>.php" class="menu-item"><i class="fas fa-list-ul"></i> Riwayat & Cabang</a>
        <?php if($role === 'supervisor'): ?>
            <a href="approval_spv.php" class="menu-item"><i class="fas fa-check-double"></i> Approval</a>
        <?php endif; ?>
        <?php if($role === 'owner'): ?>
            <a href="pelanggaran_owner.php" class="menu-item"><i class="fas fa-exclamation-triangle"></i> Pelanggaran</a>
            <a href="pengaturan_owner.php" class="menu-item"><i class="fas fa-cog"></i> Pengaturan</a>
        <?php endif; ?>
    </div>
    <div class="sidebar-footer">
        <div class="user-info">
            <div style="font-weight:700; font-size:15px;"><?= $nama ?></div>
            <div style="color:#A1A1A6; font-size:11px; letter-spacing:1px; margin-bottom:5px;"><?= strtoupper($role) ?></div>
            <?php if(in_array($role, ['karyawan', 'supervisor'])): ?>
                <div style="display:inline-block; background:rgba(255, 149, 0, 0.2); color:var(--warning); padding:3px 8px; border-radius:8px; font-weight:bold; font-size:11px;"><i class="fas fa-star"></i> Skor: <?= $me['credit_score'] ?></div>
            <?php endif; ?>
        </div>
        <a href="#" onclick="confirmLogout(event)" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar Sistem</a>
    </div>
</div>

<div class="main">
    <div class="navbar">
        <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')"><i class="fas fa-bars"></i></button>
        <div style="font-weight: 700; font-size: 18px; color:#1C1C1E;">Panel Area</div>
    </div>
    <div class="content fade-in">