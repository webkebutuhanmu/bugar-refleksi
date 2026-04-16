<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'leader') {
    header("Location: ../auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$branchId = $_SESSION['user_branch_id'];

$pesan = "";
$tipe = "";

// Update Profil
if (isset($_POST['update_profil'])) {
    $nama = $_POST['nama_lengkap'];
    $username = $_POST['username'];
    $no_hp = isset($_POST['no_hp']) ? $_POST['no_hp'] : '';
    
    if (!empty($_FILES['foto_profil']['name'])) {
        $targetDir = "../uploads/profil/";
        if (!is_dir($targetDir)) { mkdir($targetDir, 0777, true); }
        
        $fileExt = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
        $newFileName = "leader_" . $userId . "_" . time() . "." . $fileExt;
        $targetFile = $targetDir . $newFileName;
        
        if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $targetFile)) {
            $stmtOld = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
            $stmtOld->execute([$userId]);
            $oldFoto = $stmtOld->fetchColumn();
            if ($oldFoto && file_exists($targetDir . $oldFoto)) { unlink($targetDir . $oldFoto); }
            
            try {
                $sql = "UPDATE users SET nama_lengkap = ?, username = ?, no_hp = ?, foto_profil = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama, $username, $no_hp, $newFileName, $userId]);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'no_hp') !== false) {
                    $sql = "UPDATE users SET nama_lengkap = ?, username = ?, foto_profil = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nama, $username, $newFileName, $userId]);
                } else { throw $e; }
            }
        }
    } else {
        try {
            $sql = "UPDATE users SET nama_lengkap = ?, username = ?, no_hp = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nama, $username, $no_hp, $userId]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'no_hp') !== false) {
                $sql = "UPDATE users SET nama_lengkap = ?, username = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama, $username, $userId]);
            } else { throw $e; }
        }
    }
    
    $pesan = "Profil berhasil diupdate!";
    $tipe = "success";
}

// Update Password
if (isset($_POST['update_password'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi = $_POST['konfirmasi_password'];
    
    $stmtCek = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmtCek->execute([$userId]);
    $currentPass = $stmtCek->fetchColumn();
    
    if (!password_verify($password_lama, $currentPass)) {
        $pesan = "Password lama tidak sesuai!"; $tipe = "danger";
    } elseif ($password_baru !== $konfirmasi) {
        $pesan = "Konfirmasi password tidak cocok!"; $tipe = "danger";
    } elseif (strlen($password_baru) < 6) {
        $pesan = "Password minimal 6 karakter!"; $tipe = "danger";
    } else {
        $newHash = password_hash($password_baru, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        $pesan = "Password berhasil diubah!"; $tipe = "success";
    }
}

// Ambil data user
$stmtUser = $pdo->prepare("SELECT u.*, b.nama_cabang FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
$stmtUser->execute([$userId]);
$userMe = $stmtUser->fetch();
$fotoPath = !empty($userMe['foto_profil']) ? "../uploads/profil/" . $userMe['foto_profil'] : "../assets/img/default-avatar.png";

// --- BARCODE ID ---
$barcode_id = $userMe['barcode_id'] ?? null;
if (empty($barcode_id)) {
    $barcode_id = 'LDR' . str_pad($userId, 5, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE users SET barcode_id = ? WHERE id = ?")->execute([$barcode_id, $userId]);
}

$nama_cabang = $userMe['nama_cabang'] ?? 'Belum ditentukan';
$no_hp       = $userMe['no_hp'] ?? '-';

// --- BADGE VARIABLES ---
$badge_nama     = $userMe['nama_lengkap'];
$badge_role     = 'Leader Cabang';
$badge_id       = $barcode_id;
$badge_cabang   = $nama_cabang;
$badge_hp       = $no_hp;
$badge_foto     = $fotoPath;
$badge_logo_url = "https://www.dropbox.com/scl/fi/w50ceujd91ufw5gfc7boo/logo_bugar.png?rlkey=ns2z427ahk8dj87uhfiwxj8ro&st=t8gsw8y0&raw=1";
$badge_qr_data  = json_encode([
    'barcode' => $barcode_id,
    'nama'    => $userMe['nama_lengkap'],
    'role'    => 'leader',
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
    <title>Profil Leader - Bugar Refleksi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --sidebar-w: 250px;
            --primary: #2c3e50;
            --accent: #3498db;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7f6;
        }
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(180deg, var(--primary) 0%, #34495e 100%);
            height: 100vh;
            position: fixed;
            color: white;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid #34495e;
            font-weight: bold;
            font-size: 20px;
        }
        .profile-section {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #34495e;
        }
        .img-nav {
            width: 80px; height: 80px;
            border-radius: 50%; object-fit: cover;
            border: 3px solid var(--accent); margin-bottom: 10px;
        }
        .nav-menu { padding: 10px 0; }
        .nav-link-custom {
            display: block; padding: 12px 20px;
            color: #bdc3c7; text-decoration: none;
            font-size: 14px; transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background: #34495e; color: white;
            border-left: 4px solid var(--accent);
        }
        .main-content {
            margin-left: var(--sidebar-w);
            padding: 30px;
        }
        .card-custom {
            background: white; border-radius: 10px;
            padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .foto-profil-large {
            width: 150px; height: 150px;
            border-radius: 50%; object-fit: cover;
            border: 5px solid var(--accent);
        }
        @media print {
            .sidebar, .card-custom, .row, .mb-4 { display: none !important; }
            .main-content { margin-left: 0 !important; }
            body { background: white !important; }
            .id-card-section { display: block !important; }
            .btn-print-badge { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-building"></i> LEADER PANEL
        </div>
        <div class="profile-section">
            <img src="<?= $fotoPath ?>" class="img-nav" alt="Profile">
            <div style="font-size:16px; font-weight:bold; margin-top:10px;">
                <?= htmlspecialchars($userMe['nama_lengkap']) ?>
            </div>
            <small style="color: #95a5a6;">
                <?= htmlspecialchars($userMe['nama_cabang']) ?>
            </small>
        </div>
        <div class="nav-menu">
            <a href="dashboard_leader.php" class="nav-link-custom"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="data_terapis_leader.php" class="nav-link-custom"><i class="bi bi-people"></i> Data Terapis</a>
            <a href="stok_barang_leader.php" class="nav-link-custom"><i class="bi bi-box-seam"></i> Stok Barang</a>
            <a href="monitoring_terapis.php" class="nav-link-custom"><i class="bi bi-eye"></i> Monitoring Terapis</a>
            <a href="pelanggaran_terapis.php" class="nav-link-custom"><i class="bi bi-exclamation-triangle"></i> Pelanggaran</a>
            <a href="profil_leader.php" class="nav-link-custom active"><i class="bi bi-person-circle"></i> Profil</a>
        </div>
        <div style="padding: 20px; margin-top: auto;">
            <a href="../auth/logout_system.php" class="btn btn-danger w-100">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="mb-4">
            <h2><i class="bi bi-person-circle text-primary"></i> Profil Saya</h2>
            <p class="text-muted">Kelola informasi profil Anda</p>
        </div>

        <?php if ($pesan): ?>
        <div class="alert alert-<?= $tipe ?> alert-dismissible fade show">
            <?= $pesan ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- BADGE CARD -->
        <?php include '../includes/komponen_badge.php'; ?>

        <div class="row">
            <!-- Info Profil -->
            <div class="col-md-4">
                <div class="card-custom text-center">
                    <img src="<?= $fotoPath ?>" class="foto-profil-large mb-3" alt="Foto Profil">
                    <h5><?= htmlspecialchars($userMe['nama_lengkap']) ?></h5>
                    <p class="text-muted mb-2"><i class="bi bi-shield-check"></i> Leader</p>
                    <p class="text-muted"><i class="bi bi-building"></i> <?= htmlspecialchars($userMe['nama_cabang']) ?></p>
                    <hr>
                    <div class="text-start">
                        <p class="mb-2"><i class="bi bi-person"></i> <strong>Username:</strong><br>
                            <span class="text-muted"><?= htmlspecialchars($userMe['username']) ?></span></p>
                        <p class="mb-0"><i class="bi bi-phone"></i> <strong>No. HP:</strong><br>
                            <span class="text-muted"><?= htmlspecialchars($userMe['no_hp'] ?? '-') ?></span></p>
                    </div>
                </div>
            </div>

            <!-- Form Edit -->
            <div class="col-md-8">
                <div class="card-custom">
                    <h5 class="mb-3"><i class="bi bi-pencil-square text-primary"></i> Edit Profil</h5>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="nama_lengkap" value="<?= htmlspecialchars($userMe['nama_lengkap']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($userMe['username']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No. HP</label>
                            <input type="text" class="form-control" name="no_hp" value="<?= htmlspecialchars($userMe['no_hp'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Foto Profil</label>
                            <input type="file" class="form-control" name="foto_profil" accept="image/*">
                            <small class="text-muted">Kosongkan jika tidak ingin mengubah foto</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cabang</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($userMe['nama_cabang']) ?>" disabled>
                            <small class="text-muted">Hubungi Owner untuk mengubah cabang</small>
                        </div>
                        <button type="submit" name="update_profil" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </form>
                </div>

                <div class="card-custom">
                    <h5 class="mb-3"><i class="bi bi-key text-warning"></i> Ubah Password</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Password Lama</label>
                            <input type="password" class="form-control" name="password_lama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru</label>
                            <input type="password" class="form-control" name="password_baru" required>
                            <small class="text-muted">Minimal 6 karakter</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" name="konfirmasi_password" required>
                        </div>
                        <button type="submit" name="update_password" class="btn btn-warning">
                            <i class="bi bi-shield-lock"></i> Ubah Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>