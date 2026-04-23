<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

// Proteksi Akses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'owner') { 
    header("Location: ../auth/login_system.php"); 
    exit; 
}

$owner_id = $_SESSION['user_id'];
$pesan = "";
$tipe = "";

// ========================================================
// 1. PROSES UPDATE PROFIL (Nama, Username, Foto)
// ========================================================
if (isset($_POST['update_profil'])) {
    $nama_lengkap = htmlspecialchars(trim($_POST['nama_lengkap']));
    $username     = htmlspecialchars(trim($_POST['username']));

    // Cek apakah username sudah dipakai orang lain
    $stmtCek = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmtCek->execute([$username, $owner_id]);
    
    if ($stmtCek->fetch()) {
        $pesan = "Username sudah digunakan! Silakan pilih username lain.";
        $tipe  = "danger";
    } else {
        $foto_query = "";
        $params = [$username, $nama_lengkap];

        // Proses Upload Foto Jika Ada
        if (!empty($_FILES['foto']['name'])) {
            $target_dir = "../uploads/profil/";
            if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
            
            $file_name = basename($_FILES["foto"]["name"]);
            $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_name  = "owner_" . $owner_id . "_" . time() . "." . $file_type;
            $target_file = $target_dir . $new_name;
            
            $allowed_types = ['jpg', 'jpeg', 'png'];
            if (in_array($file_type, $allowed_types)) {
                if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                    // Ambil foto lama untuk dihapus
                    $stmtOld = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
                    $stmtOld->execute([$owner_id]);
                    $old_foto = $stmtOld->fetchColumn();
                    if ($old_foto && file_exists($target_dir . $old_foto)) {
                        unlink($target_dir . $old_foto);
                    }
                    
                    $foto_query = ", foto_profil = ?";
                    $params[] = $new_name;
                }
            } else {
                $pesan = "Format foto tidak valid! Hanya JPG, JPEG, PNG.";
                $tipe  = "danger";
            }
        }

        // Jika tidak ada error format foto, eksekusi update
        if ($tipe !== "danger") {
            $params[] = $owner_id;
            $sql = "UPDATE users SET username = ?, nama_lengkap = ? $foto_query WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $_SESSION['nama'] = $nama_lengkap; // Update nama di session
                $pesan = "Profil berhasil diperbarui!";
                $tipe  = "success";
            } else {
                $pesan = "Gagal memperbarui profil!";
                $tipe  = "danger";
            }
        }
    }
}

// ========================================================
// 2. PROSES GANTI PASSWORD
// ========================================================
if (isset($_POST['ganti_password'])) {
    $pass_lama    = $_POST['pass_lama'];
    $pass_baru    = $_POST['pass_baru'];
    $pass_konfirm = $_POST['pass_konfirm'];

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$owner_id]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass_lama, $user['password'])) {
        if ($pass_baru === $pass_konfirm) {
            $hash = password_hash($pass_baru, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmtUpdate->execute([$hash, $owner_id])) {
                $pesan = "Password berhasil diubah!";
                $tipe  = "success";
            }
        } else {
            $pesan = "Konfirmasi password baru tidak cocok!";
            $tipe  = "danger";
        }
    } else {
        $pesan = "Password lama salah!";
        $tipe  = "danger";
    }
}

// ========================================================
// AMBIL DATA OWNER SAAT INI
// ========================================================
$stmtOwner = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtOwner->execute([$owner_id]);
$owner = $stmtOwner->fetch();

$foto_profil = (!empty($owner['foto_profil']) && file_exists("../uploads/profil/" . $owner['foto_profil'])) 
               ? "../uploads/profil/" . $owner['foto_profil'] 
               : "../assets/default_user.png";
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun - Owner</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <style>
        .profile-wrapper { text-align: center; margin-bottom: 20px; }
        .profile-img-preview { 
            width: 120px; height: 120px; border-radius: 50%; object-fit: cover; 
            border: 3px solid var(--accent-yellow); margin-bottom: 15px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
        }
        .file-input-wrapper {
            position: relative; overflow: hidden; display: inline-block; cursor: pointer;
        }
        .file-input-wrapper input[type=file] {
            font-size: 100px; position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer;
        }
        .btn-full { width: 100%; margin-top: 15px; }
        
        /* Dropdown Style */
        .user-dropdown-wrap { position: relative; cursor: pointer; }
        .user-dropdown-menu { display: none; position: absolute; top: 100%; right: 0; background: var(--bg-panel); min-width: 180px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; z-index: 1000; margin-top: 10px; border: 1px solid var(--border-color); }
        .user-dropdown-menu.show { display: block; }
        .user-dropdown-menu a { display: block; padding: 12px 15px; text-decoration: none; color: var(--text-dark); font-size: 13px; border-bottom: 1px solid var(--border-color); }
        .user-dropdown-menu a:hover { background: rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container-layout">
        <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="https://www.dropbox.com/scl/fi/w50ceujd91ufw5gfc7boo/logo_bugar.png?rlkey=ns2z427ahk8dj87uhfiwxj8ro&st=c5kszi61&raw=1" alt="Logo Bugar" style="width: 80px; height: auto; margin-bottom: 10px; border-radius: 8px;">
        
        <h2>Owner</h2>
    </div>
    <div class="sidebar-menu">
                <a href="dashboard_owner.php" class="menu-item">Dashboard</a>
                <a href="data_cabang.php" class="menu-item">Data Cabang</a>
                <a href="data_leader.php" class="menu-item">Data Leader</a>
                <a href="data_kasir.php" class="menu-item">Data Kasir</a>
                <a href="data_terapis.php" class="menu-item">Data Terapis</a>
                <a href="data_customer.php" class="menu-item">Data Customer</a>
                <a href="data_absensi_owner.php" class="menu-item">Data Absensi</a>
                <a href="pelanggaran_owner.php" class="menu-item">Pelanggaran</a>
                <div class="has-submenu">
                    <div class="submenu-toggle" onclick="toggleSubmenu(this)">
                        <span>Paket & Pengaturan</span>
                        <span class="arrow">▶</span>
                    </div>
                    <div class="submenu-items">
                        <a href="paket_layanan.php" class="submenu-item">Paket Layanan</a>
                        <a href="pengaturan_sistem.php" class="submenu-item">Pengaturan Sistem</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div style="display:flex; align-items:center; gap:15px;">
                    <button class="mobile-toggle" onclick="toggleMobileMenu()">☰</button>
                    <h1>Pengaturan Akun</h1>
                </div>
                
                <div class="topbar-right" style="display:flex; align-items:center; gap:15px;">
                    <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
                    
                    <div class="user-dropdown-wrap" onclick="toggleUserDropdown(event)">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <img src="<?= $foto_profil ?>" alt="Profil" style="width:35px; height:35px; border-radius:50%; object-fit:cover; border:2px solid var(--accent-yellow);">
                            <div style="text-align:left; display:none; @media(min-width:768px){display:block;}">
                                <div style="font-size:13px; font-weight:bold; color:var(--text-dark);">Halo, <?= htmlspecialchars($_SESSION['nama']) ?></div>
                            </div>
                        </div>
                        <div id="userDropdown" class="user-dropdown-menu">
                            <a href="pengaturan_akun_owner.php">Pengaturan Akun</a>
                            <a href="../auth/logout_system.php" style="color:var(--accent-red);">Keluar Sistem</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($pesan): ?>
                <div class="alert alert-<?= $tipe ?>"><?= $pesan ?></div>
            <?php endif; ?>

            <div class="grid-2">
                <div class="card">
                    <div class="card-header">Informasi Profil</div>
                    <form method="POST" enctype="multipart/form-data" style="padding: 20px;">
                        <div class="profile-wrapper">
                            <img id="previewFoto" src="<?= $foto_profil ?>" alt="Preview" class="profile-img-preview"><br>
                            <div class="file-input-wrapper btn btn-secondary btn-sm">
                                <span>Pilih Foto Baru</span>
                                <input type="file" name="foto" accept="image/jpeg, image/png, image/jpg" onchange="previewImage(this)">
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">Format: JPG/PNG (Maks 2MB)</div>
                        </div>

                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($owner['nama_lengkap']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Username Login</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($owner['username']) ?>" required>
                        </div>

                        <button type="submit" name="update_profil" class="btn btn-primary btn-full">Simpan Profil</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">Ubah Password Keamanan</div>
                    <form method="POST" style="padding: 20px;">
                        <div class="form-group">
                            <label>Password Lama</label>
                            <input type="password" name="pass_lama" class="form-control" required placeholder="Masukkan password saat ini">
                        </div>
                        <hr style="border: 1px dashed var(--border-color); margin: 20px 0;">
                        <div class="form-group">
                            <label>Password Baru</label>
                            <input type="password" name="pass_baru" class="form-control" required placeholder="Masukkan password baru">
                        </div>
                        <div class="form-group">
                            <label>Konfirmasi Password Baru</label>
                            <input type="password" name="pass_konfirm" class="form-control" required placeholder="Ulangi password baru">
                        </div>

                        <button type="submit" name="ganti_password" class="btn btn-warning btn-full" onclick="return confirm('Yakin ingin mengganti password?')">Ubah Password</button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Fitur Preview Foto sebelum diupload
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewFoto').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Toggle User Dropdown
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

        // Theme Toggle Script
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            localStorage.setItem('bugar-theme', next);
        }
        (function() {
            const saved = localStorage.getItem('bugar-theme');
            if (saved) document.documentElement.setAttribute('data-theme', saved);
        })();

        // Sidebar & Menu Mobile
        function toggleSubmenu(el) {
            el.classList.toggle('active');
            const items = el.nextElementSibling;
            items.classList.toggle('open');
        }

        function toggleMobileMenu() {
            document.getElementById('sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>