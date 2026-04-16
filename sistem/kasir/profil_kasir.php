<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) { 
    header("Location: ../auth/login_system.php"); exit; 
}

$kasir_id = $_SESSION['user_id'];
$branch_id = $_SESSION['active_branch'] ?? 0;
$nama_kasir = $_SESSION['nama'];

// Ambil Nama Cabang
$stmtCabang = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
$stmtCabang->execute([$branch_id]);
$nama_cabang = $stmtCabang->fetchColumn();

$pesan = "";
$tipe = "";

// PROSES UPLOAD FOTO
if (isset($_POST['upload_foto'])) {
    $target_dir = "../uploads/profil/";
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
    
    $file_name = basename($_FILES["foto"]["name"]);
    $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $new_name = "profil_" . $kasir_id . "_" . time() . "." . $file_type;
    $target_file = $target_dir . $new_name;
    
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'heic', 'webp'];
    
    if (in_array($file_type, $allowed_types)) {
        if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
            $stmt = $pdo->prepare("UPDATE users SET foto_profil = ? WHERE id = ?");
            $stmt->execute([$new_name, $kasir_id]);
            $pesan = "Foto profil berhasil diperbarui!";
            $tipe = "success";
        } else {
            $pesan = "Gagal mengunggah foto.";
            $tipe = "danger";
        }
    } else {
        $pesan = "Format file tidak diizinkan. Gunakan JPG atau PNG.";
        $tipe = "danger";
    }
}

// Ambil Data Profil User
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$kasir_id]);
$user = $stmt->fetch();

// Ambil Foto Profil Terkini
$foto_profil = (!empty($user['foto_profil']) && file_exists("../uploads/profil/" . $user['foto_profil'])) 
               ? "../uploads/profil/" . $user['foto_profil'] 
               : "../assets/default_user.png";

// Ambil Statistik
$stmtStats = $pdo->prepare("SELECT COUNT(*) as total_shift, COALESCE(SUM(total_transaksi_shift), 0) as total_trx, COALESCE(SUM(omset_shift), 0) as total_omset FROM kasir_attendance WHERE kasir_id = ? AND status = 'selesai'");
$stmtStats->execute([$kasir_id]);
$stats = $stmtStats->fetch();

// Ambil 10 Riwayat Kehadiran Terakhir
$stmtHadir = $pdo->prepare("SELECT ka.*, b.nama_cabang FROM kasir_attendance ka JOIN branches b ON ka.branch_id = b.id WHERE ka.kasir_id = ? ORDER BY ka.waktu_masuk DESC LIMIT 10");
$stmtHadir->execute([$kasir_id]);
$kehadiran = $stmtHadir->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Kasir</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <style>
        .grid-2 { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start; }
        
        .profile-card { background: var(--bg-panel); border-radius: 12px; padding: 30px 20px; text-align: center; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); }
        .profile-img-large { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--accent-yellow); margin-bottom: 15px; background: var(--bg-input); }
        .profile-name { font-size: 20px; font-weight: bold; color: var(--text-dark); margin-bottom: 5px; font-family: 'Plus Jakarta Sans', sans-serif; }
        .profile-role { color: var(--text-muted); font-size: 13px; font-weight: 600; text-transform: uppercase; margin-bottom: 20px; display: inline-block; padding: 4px 12px; background: var(--bg-input); border-radius: 20px; }
        
        .stat-list { text-align: left; margin-top: 20px; border-top: 1px dashed var(--border-color); padding-top: 20px; }
        .stat-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px; }
        .stat-item .lbl { color: var(--text-muted); font-weight: 600; }
        .stat-item .val { font-weight: bold; color: var(--text-dark); }
        
        .upload-section { margin-top: 20px; padding: 15px; background: var(--bg-input); border-radius: 8px; border: 1px dashed var(--border-color); }
        .upload-section input[type="file"] { width: 100%; font-size: 12px; margin-bottom: 10px; color: var(--text-dark); }

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

        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
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
                    <h1>Profil Saya</h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()" title="Ganti Tema">Mode Layar</button>
                    <div class="user-dropdown-wrap">
                        <button class="btn-profile-dropdown" onclick="toggleUserDropdown(event)">
                            <img src="<?= $foto_profil ?>" alt="Profil">
                            <span><?= htmlspecialchars(explode(' ', $nama_kasir)[0]) ?> ▾</span>
                        </button>
                        <div class="user-dropdown-menu" id="userDropdown">
                            <a href="profil_kasir.php" style="color:var(--accent-blue); background:var(--bg-input);">Profil Saya</a>
                            <a href="pengaturan_kasir.php">Pengaturan Akun</a>
                            <div style="border-top:1px solid var(--border-color); margin:0;"></div>
                            <a href="../auth/logout_system.php" style="color:var(--accent-red);">Keluar Sistem</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if($pesan): ?>
            <div class="alert alert-<?= $tipe ?>"><?= $pesan ?></div>
            <?php endif; ?>

            <div class="grid-2">
                <div class="profile-card">
                    <img src="<?= $foto_profil ?>" class="profile-img-large" alt="Foto Kasir">
                    <div class="profile-name"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                    <div class="profile-role">KASIR CABANG</div>
                    
                    <div class="stat-list">
                        <div class="stat-item">
                            <span class="lbl">Username</span>
                            <span class="val"><?= htmlspecialchars($user['username']) ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="lbl">Total Shift Selesai</span>
                            <span class="val"><?= number_format($stats['total_shift']) ?> Kali</span>
                        </div>
                        <div class="stat-item">
                            <span class="lbl">Total Transaksi</span>
                            <span class="val"><?= number_format($stats['total_trx']) ?> Trx</span>
                        </div>
                        <div class="stat-item">
                            <span class="lbl">Total Omset</span>
                            <span class="val" style="color:var(--accent-green);">Rp <?= number_format($stats['total_omset'], 0, ',', '.') ?></span>
                        </div>
                    </div>

                    <div class="upload-section">
                        <div style="font-size: 13px; font-weight: bold; margin-bottom: 10px; color: var(--text-dark); text-align: left;">Update Foto Profil</div>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="file" name="foto" accept="image/*" required>
                            <button type="submit" name="upload_foto" class="btn btn-primary btn-sm" style="width: 100%;">Upload Foto</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Riwayat Kehadiran Terakhir (10 Shift)</div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Cabang</th>
                                    <th>Masuk</th>
                                    <th>Keluar</th>
                                    <th>Transaksi</th>
                                    <th>Omset Shift</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($kehadiran) > 0): ?>
                                    <?php foreach($kehadiran as $h): ?>
                                    <tr>
                                        <td><strong><?= date('d M Y', strtotime($h['tanggal'])) ?></strong></td>
                                        <td><?= htmlspecialchars($h['nama_cabang']) ?></td>
                                        <td><?= date('H:i', strtotime($h['waktu_masuk'])) ?></td>
                                        <td>
                                            <?php if ($h['waktu_keluar']): ?>
                                                <?= date('H:i', strtotime($h['waktu_keluar'])) ?>
                                            <?php else: ?>
                                                <span class="badge badge-warning" style="font-size:10px;">Sedang Aktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $h['total_transaksi_shift'] ?> trx</td>
                                        <td><strong style="color:var(--text-dark);">Rp <?= number_format($h['omset_shift'] ?? 0, 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">Belum ada riwayat shift.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
        sb.classList.toggle('collapsed');
        const btnText = document.querySelector('.sidebar-toggle-btn .menu-text');
        const btnAbbr = document.querySelector('.sidebar-toggle-btn .menu-abbr');
        if (sb.classList.contains('collapsed')) { btnText.style.display = 'none'; btnAbbr.style.display = 'inline'; } 
        else { btnText.style.display = 'inline'; btnAbbr.style.display = 'none'; }
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