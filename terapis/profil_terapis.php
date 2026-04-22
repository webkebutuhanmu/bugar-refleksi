<?php
session_start();
require_once '../sistem/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'terapis') { 
    header("Location: login.php"); exit; 
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$id_user = $_SESSION['user_id'];
$pesan = "";
$tipe_pesan = "";

// --- LOGIC 1: UPDATE PROFIL ---
if (isset($_POST['update_profil'])) {
    $username_baru = trim($_POST['username']);
    $no_hp_baru    = htmlspecialchars(trim($_POST['no_hp']));

    if (empty($username_baru)) {
        $pesan = "Username tidak boleh kosong."; $tipe_pesan = "danger";
    } else {
        $cekUser = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $cekUser->execute([$username_baru, $id_user]);
        if ($cekUser->fetch()) {
            $pesan = "Username sudah digunakan oleh akun lain."; $tipe_pesan = "danger";
        } else {
            $foto_query = "";
            $params = [$username_baru, $no_hp_baru];

            if (!empty($_FILES['foto']['name'])) {
                $target_dir = "../sistem/assets/uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $new_name = "profile_" . $id_user . "_" . time() . "." . $file_ext;
                $target_file = $target_dir . $new_name;
                $allowed = ['jpg', 'jpeg', 'png'];
                if (in_array($file_ext, $allowed)) {
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                        $foto_query = ", foto_profil = ?";
                        $params[] = $new_name;
                    } else {
                        $pesan = "Gagal mengupload foto."; $tipe_pesan = "danger";
                    }
                } else {
                    $pesan = "Format foto harus JPG atau PNG."; $tipe_pesan = "danger";
                }
            }

            if (empty($pesan)) {
                $params[] = $id_user;
                $sql = "UPDATE users SET username = ?, no_hp = ? $foto_query WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute($params)) {
                    $_SESSION['username'] = $username_baru;
                    $pesan = "Profil berhasil diperbarui!"; $tipe_pesan = "success";
                } else {
                    $pesan = "Terjadi kesalahan database."; $tipe_pesan = "danger";
                }
            }
        }
    }
}

// --- LOGIC 2: GANTI PASSWORD ---
if (isset($_POST['ganti_password'])) {
    $pass_lama = $_POST['pass_lama'];
    $pass_baru = $_POST['pass_baru'];
    $pass_konf = $_POST['pass_konf'];

    $stmtCk = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmtCk->execute([$id_user]);
    $user = $stmtCk->fetch();

    if (password_verify($pass_lama, $user['password'])) {
        if ($pass_baru === $pass_konf) {
            if (strlen($pass_baru) >= 5) {
                $new_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $id_user]);
                $pesan = "Password berhasil diubah!"; $tipe_pesan = "success";
            } else {
                $pesan = "Password baru minimal 5 karakter."; $tipe_pesan = "warning";
            }
        } else {
            $pesan = "Konfirmasi password baru tidak cocok."; $tipe_pesan = "danger";
        }
    } else {
        $pesan = "Password lama salah."; $tipe_pesan = "danger";
    }
}

// --- AMBIL DATA USER ---
$stmtMe = $pdo->prepare("SELECT u.*, b.nama_cabang FROM users u LEFT JOIN branches b ON u.home_branch_id = b.id WHERE u.id = ?");
$stmtMe->execute([$id_user]);
$me = $stmtMe->fetch();

$foto_url = "../sistem/assets/default_user.png";
if (!empty($me['foto_profil']) && file_exists("../sistem/assets/uploads/" . $me['foto_profil'])) {
    $foto_url = "../sistem/assets/uploads/" . $me['foto_profil'];
}

// --- BARCODE ID ---
$barcode_id = $me['barcode_id'] ?? null;
if (empty($barcode_id)) {
    $barcode_id = 'TRP' . str_pad($id_user, 5, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE users SET barcode_id = ? WHERE id = ?")->execute([$barcode_id, $id_user]);
}

$nama_cabang = $me['nama_cabang'] ?? 'Belum ditentukan';
$no_hp       = $me['no_hp'] ?? '-';

// --- BADGE VARIABLES UNTUK KOMPONEN_BADGE.PHP ---
$badge_nama     = $me['nama_lengkap'];
$badge_role     = 'Terapis Profesional';
$badge_id       = $barcode_id;
$badge_cabang   = $nama_cabang;
$badge_hp       = $no_hp;
$badge_foto     = $foto_url;
$badge_logo_url = "https://www.dropbox.com/scl/fi/w50ceujd91ufw5gfc7boo/logo_bugar.png?rlkey=ns2z427ahk8dj87uhfiwxj8ro&st=t8gsw8y0&raw=1";
$badge_qr_data  = json_encode([
    'barcode' => $barcode_id,
    'nama'    => $me['nama_lengkap'],
    'role'    => 'terapis',
    'cabang'  => $nama_cabang,
    'hp'      => $no_hp,
    'app'     => 'Bugar Refleksi'
], JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Bugar Refleksi</title>
    <link rel="stylesheet" href="assets/style_terapis.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Gaya yang sudah disesuaikan dengan Variabel Dark Mode CSS */
        .profile-header {
            background: var(--bg-panel); padding: 30px; border-radius: 10px;
            box-shadow: 0 4px 15px var(--shadow-color); text-align: center; margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        .profile-img-container { position: relative; width: 120px; height: 120px; margin: 0 auto 15px; }
        .profile-img {
            width: 100%; height: 100%; object-fit: cover; border-radius: 50%;
            border: 4px solid var(--border-color); box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .camera-icon {
            position: absolute; bottom: 5px; right: 5px; background: var(--accent-red);
            color: white; width: 35px; height: 35px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; border: 2px solid var(--bg-panel);
        }
        .section-title {
            color: var(--text-dark); border-bottom: 2px dashed var(--border-color); 
            padding-bottom: 10px; margin-bottom: 20px; font-size: 18px;
            font-family: 'Playfair Display', serif;
        }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .alert-success { background: rgba(39, 174, 96, 0.15); color: #27ae60; border: 1px solid rgba(39,174,96,0.3); }
        .alert-danger { background: rgba(231, 76, 60, 0.15); color: #e74c3c; border: 1px solid rgba(231,76,60,0.3); }
        .alert-warning { background: rgba(243, 156, 18, 0.15); color: #f39c12; border: 1px solid rgba(243,156,18,0.3); }
        
        .form-label { font-weight: bold; font-size: 13px; color: var(--text-muted); display: block; margin-bottom: 5px; }

        @media print {
            .sidebar, .topbar, .card, #editSection, .alert { display: none !important; }
            body { background: white !important; }
            .id-card-section { display: block !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>
    <div class="container-layout">
        
        <div class="sidebar no-print" id="sidebar">
            <div class="sidebar-header"><h2>💆 TERAPIS PANEL</h2></div>
            <div class="sidebar-menu">
                <a href="dashboard_terapis.php" class="menu-item"><i>📊</i> Dashboard</a>
                <a href="absensi_terapis.php" class="menu-item"><i>📋</i> Absensi</a>
                <a href="riwayat_pendapatan.php" class="menu-item"><i>💰</i> Riwayat Omset</a>
                <a href="profil_terapis.php" class="menu-item active"><i>👤</i> Profil Saya</a>
                <a href="skor_reward_terapis.php" class="menu-item"><i>⭐</i> Skor Reward</a>
                <a href="logout.php" class="menu-item" style="color: var(--accent-red); margin-top: 50px;"><i>🚪</i> Logout</a>
            </div>
        </div>

        <div class="main-content">
            
            <div class="topbar no-print">
                <div class="topbar-left">
                    <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')"><i class="fas fa-bars"></i></button>
                    <h1>Profil Saya</h1>
                </div>
                <div class="topbar-right">
                    <a href="dashboard_terapis.php" class="btn btn-secondary" style="margin-right: 10px;">&#8592; Kembali</a>
                    <button class="theme-toggle" onclick="toggleTheme()" id="theme-btn"><i class="fas fa-moon"></i> Dark</button>
                </div>
            </div>

            <?php if($pesan): ?>
                <div class="alert alert-<?= $tipe_pesan ?> no-print"><?= $pesan ?></div>
            <?php endif; ?>

            <?php include '../sistem/includes/komponen_badge.php'; ?>

            <div id="editSection" class="grid-2 no-print">
                
                <div class="card" style="padding: 0; overflow: hidden;">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="profile-header">
                            <div class="profile-img-container">
                                <img src="<?= $foto_url ?>" id="previewFoto" class="profile-img" alt="Foto Profil">
                                <label for="inputFoto" class="camera-icon"><i class="fas fa-camera"></i></label>
                                <input type="file" name="foto" id="inputFoto" style="display: none;" accept="image/*" onchange="previewImage(this)">
                            </div>
                            <h2 style="margin: 0; color: var(--text-dark);"><?= htmlspecialchars($me['nama_lengkap']) ?></h2>
                            <span class="badge" style="background: rgba(243, 156, 18, 0.15); color: #f39c12; padding: 5px 10px; border-radius: 15px; font-size: 12px; margin-top: 5px; display: inline-block; border: 1px solid rgba(243,156,18,0.3);">
                                Terapis Profesional
                            </span>
                        </div>
                        
                        <div style="padding: 0 20px 20px;">
                            <h3 class="section-title">&#9999;&#65039; Edit Biodata</h3>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label class="form-label">Username (Login)</label>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($me['username']) ?>" required>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label class="form-label">Nama Lengkap (Terkunci)</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($me['nama_lengkap']) ?>" readonly style="background: var(--bg-input); opacity: 0.7; cursor: not-allowed;">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label class="form-label">No. Handphone / WhatsApp</label>
                                <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($me['no_hp'] ?? '') ?>" placeholder="08xx...">
                            </div>
                            
                            <button type="submit" name="update_profil" style="width: 100%; padding: 12px; background: var(--accent-red); border: none; color: white; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s;">
                                Simpan Perubahan Profil
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card" style="padding: 20px; align-self: start;">
                    <h3 class="section-title">&#128274; Ganti Password</h3>
                    <form method="POST">
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label class="form-label">Password Lama</label>
                            <input type="password" name="pass_lama" class="form-control" required placeholder="******">
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="pass_baru" class="form-control" required placeholder="Minimal 5 karakter">
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" name="pass_konf" class="form-control" required placeholder="Ulangi password baru">
                        </div>
                        <button type="submit" name="ganti_password" style="width: 100%; padding: 12px; background: var(--accent-yellow); border: none; color: #000; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s;">
                            Update Password
                        </button>
                    </form>
                    
                    <div style="margin-top: 30px; background: rgba(243, 156, 18, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid var(--accent-yellow);">
                        <small style="color: var(--text-muted);"><strong>Tips Keamanan:</strong><br>Gunakan password yang sulit ditebak. Jangan gunakan tanggal lahir atau nama panggilan.</small>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <script>
    // Fitur Ganti Foto
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) { document.getElementById('previewFoto').src = e.target.result; };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Fitur Dark/Light Mode Universal
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