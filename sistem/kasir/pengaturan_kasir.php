<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) { 
    header("Location: ../auth/login_system.php"); exit; 
}

$kasir_id = $_SESSION['user_id'];
$branch_id = $_SESSION['active_branch'];
$nama_kasir = $_SESSION['nama'];

// Ambil Nama Cabang
$stmtCabang = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
$stmtCabang->execute([$branch_id]);
$nama_cabang = $stmtCabang->fetchColumn();

// Ambil Foto Profil
$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$dbFoto = $stmtProfil->fetchColumn();
$foto_profil = (!empty($dbFoto) && file_exists("../uploads/profil/" . $dbFoto)) ? "../uploads/profil/" . $dbFoto : "../assets/default_user.png";

$pesan = "";
$tipe = "";

if (isset($_POST['ganti_password'])) {
    $pass_lama = $_POST['pass_lama'];
    $pass_baru = $_POST['pass_baru'];
    $pass_konfirm = $_POST['pass_konfirm'];

    // Ambil password lama dari DB
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$kasir_id]);
    $user = $stmt->fetch();

    if (password_verify($pass_lama, $user['password'])) {
        if ($pass_baru === $pass_konfirm) {
            $pass_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$pass_hash, $kasir_id]);
            
            $pesan = "Password berhasil diubah! Silakan login ulang nanti.";
            $tipe = "success";
        } else {
            $pesan = "Password baru dan konfirmasi tidak cocok!";
            $tipe = "danger";
        }
    } else {
        $pesan = "Password lama salah!";
        $tipe = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun - Kasir</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <style>
        .user-dropdown-wrap { position: relative; display: inline-block; margin-left: 10px; border-left: 1px solid var(--border-color); padding-left: 15px; }
        .btn-profile-dropdown { display: flex; align-items: center; gap: 10px; background: transparent; border: none; cursor: pointer; padding: 5px 10px; border-radius: 8px; transition: 0.2s; }
        .btn-profile-dropdown:hover { background: var(--bg-input); }
        .btn-profile-dropdown img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-yellow); }
        .btn-profile-dropdown span { font-weight: 700; color: var(--text-dark); font-size: 14px; }
        .user-dropdown-menu { position: absolute; right: 0; top: 110%; background: var(--bg-panel); min-width: 180px; box-shadow: var(--shadow-md); border-radius: 12px; border: 1px solid var(--border-color); display: none; flex-direction: column; z-index: 1000; overflow: hidden; }
        .user-dropdown-menu.show { display: flex; animation: fadeIn 0.2s; }
        .user-dropdown-menu a { padding: 12px 18px; font-size: 13px; font-weight: 600; color: var(--text-dark); text-decoration: none; transition: 0.2s; }
        .user-dropdown-menu a:hover { background: var(--bg-input); color: var(--accent-blue); padding-left: 22px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: bold; border-left: 4px solid transparent; }
        .alert-success { background: rgba(39,174,96,0.1); color: #27ae60; border-left-color: #27ae60; }
        .alert-danger { background: rgba(231,76,60,0.1); color: #e74c3c; border-left-color: #e74c3c; }
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
                <a href="data_terapis_hadir.php" class="menu-item"><span class="menu-abbr">DT</span><span class="menu-text">Data Terapis</span></a>
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
                    <h1>Pengaturan Akun</h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()" title="Ganti Tema">Mode Layar</button>
                    <div class="user-dropdown-wrap">
                        <button class="btn-profile-dropdown" onclick="toggleUserDropdown(event)">
                            <img src="<?= $foto_profil ?>" alt="Profil">
                            <span><?= htmlspecialchars(explode(' ', $nama_kasir)[0]) ?> ▾</span>
                        </button>
                        <div class="user-dropdown-menu" id="userDropdown">
                            <a href="profil_kasir.php">Profil Saya</a>
                            <a href="pengaturan_kasir.php" style="color:var(--accent-blue); background:var(--bg-input);">Pengaturan Akun</a>
                            <div style="border-top:1px solid var(--border-color); margin:0;"></div>
                            <a href="../auth/logout_system.php" style="color:var(--accent-red);">Keluar Sistem</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if($pesan): ?>
            <div class="alert alert-<?= $tipe ?>"><?= $pesan ?></div>
            <?php endif; ?>

            <div class="card" style="max-width: 600px; margin: 0 auto;">
                <div class="card-header">Ganti Password</div>
                <div style="padding: 25px;">
                    <form method="POST">
                        <div class="form-group">
                            <label>Password Lama</label>
                            <input type="password" name="pass_lama" class="form-control" required placeholder="Masukkan password saat ini">
                        </div>
                        <hr style="margin: 25px 0; border: 0; border-top: 1px dashed var(--border-color);">
                        <div class="form-group">
                            <label>Password Baru</label>
                            <input type="password" name="pass_baru" class="form-control" required placeholder="Minimal 6 karakter">
                        </div>
                        <div class="form-group">
                            <label>Konfirmasi Password Baru</label>
                            <input type="password" name="pass_konfirm" class="form-control" required placeholder="Ulangi password baru">
                        </div>
                        <button type="submit" name="ganti_password" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 15px;">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
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
    
    // Deteksi apakah ini tampilan mobile (lebar layar <= 992px sesuai CSS Anda)
    if (window.innerWidth <= 992) {
        // Mode Mobile: Toggle class 'active' untuk memunculkan sidebar dari kiri
        sb.classList.toggle('active');
    } else {
        // Mode Desktop: Toggle class 'collapsed' untuk mengecilkan/membesarkan sidebar
        sb.classList.toggle('collapsed');
        
        const btnText = document.querySelector('.sidebar-toggle-btn .menu-text');
        const btnAbbr = document.querySelector('.sidebar-toggle-btn .menu-abbr');
        
        if (sb.classList.contains('collapsed')) {
            btnText.style.display = 'none';
            btnAbbr.style.display = 'inline';
        } else {
            btnText.style.display = 'inline';
            btnAbbr.style.display = 'none';
        }
    }
}

    function toggleUserDropdown(e) {
        e.stopPropagation();
        document.getElementById('userDropdown').classList.toggle('show');
    }
    
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown && dropdown.classList.contains('show') && !e.target.closest('.user-dropdown-wrap')) {
            dropdown.classList.remove('show');
        }
    });
    </script>
</body>
</html>