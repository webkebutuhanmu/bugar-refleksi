<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header("Location: ../auth/login.php");
    exit;
}

$pesan = "";
$tipe = "";

// Tambah Leader Baru
if (isset($_POST['tambah'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama = $_POST['nama_lengkap'];
    $branch_id = $_POST['branch_id'];
    $no_hp = isset($_POST['no_hp']) ? $_POST['no_hp'] : '';

    $cek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $cek->execute([$username]);
    if ($cek->fetch()) {
        $pesan = "Username sudah digunakan!";
        $tipe = "danger";
    } else {
        try {
            $sql = "INSERT INTO users (username, password, nama_lengkap, role, branch_id, no_hp) VALUES (?, ?, ?, 'leader', ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $password, $nama, $branch_id, $no_hp]);
            $pesan = "Leader berhasil ditambahkan!";
            $tipe = "success";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'no_hp') !== false) {
                $sql = "INSERT INTO users (username, password, nama_lengkap, role, branch_id) VALUES (?, ?, ?, 'leader', ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $password, $nama, $branch_id]);
                $pesan = "Leader berhasil ditambahkan!";
                $tipe = "success";
            } else {
                $pesan = "Error: " . $e->getMessage();
                $tipe = "danger";
            }
        }
    }
}

// Update Leader
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $username = $_POST['username'];
    $nama = $_POST['nama_lengkap'];
    $branch_id = $_POST['branch_id'];
    $no_hp = isset($_POST['no_hp']) ? $_POST['no_hp'] : '';

    try {
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username = ?, password = ?, nama_lengkap = ?, branch_id = ?, no_hp = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $password, $nama, $branch_id, $no_hp, $id]);
        } else {
            $sql = "UPDATE users SET username = ?, nama_lengkap = ?, branch_id = ?, no_hp = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $nama, $branch_id, $no_hp, $id]);
        }
        $pesan = "Leader berhasil diupdate!";
        $tipe = "success";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'no_hp') !== false) {
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username = ?, password = ?, nama_lengkap = ?, branch_id = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $password, $nama, $branch_id, $id]);
            } else {
                $sql = "UPDATE users SET username = ?, nama_lengkap = ?, branch_id = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $nama, $branch_id, $id]);
            }
            $pesan = "Leader berhasil diupdate!";
            $tipe = "success";
        } else {
            $pesan = "Error: " . $e->getMessage();
            $tipe = "danger";
        }
    }
}

// Hapus Leader
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    $sql = "DELETE FROM users WHERE id = ? AND role = 'leader'";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$id])) {
        $pesan = "Leader berhasil dihapus!";
        $tipe = "success";
    }
}

$branches = $pdo->query("SELECT * FROM branches ORDER BY nama_cabang")->fetchAll();
$leaders = $pdo->query("SELECT u.*, b.nama_cabang 
                        FROM users u 
                        LEFT JOIN branches b ON u.branch_id = b.id 
                        WHERE u.role = 'leader' 
                        ORDER BY b.nama_cabang, u.nama_lengkap")->fetchAll();

$sqlStats = "SELECT b.id, b.nama_cabang, COUNT(u.id) as total_leader
             FROM branches b
             LEFT JOIN users u ON u.branch_id = b.id AND u.role = 'leader'
             GROUP BY b.id, b.nama_cabang
             ORDER BY b.nama_cabang";
$branchStats = $pdo->query($sqlStats)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Leader - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
</head>
<body>
    <div class="container-layout">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>BUGAR REFLEKSI</h2>
                <small>Owner Panel</small>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard_owner.php" class="menu-item">Dashboard</a>
                <a href="data_cabang.php" class="menu-item">Data Cabang</a>
                <a href="data_leader.php" class="menu-item active">Data Leader</a>
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
                <a href="../auth/logout_system.php" class="menu-item" style="color: var(--accent-red); margin-top: 30px;">Keluar Sistem</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div style="display:flex; align-items:center; gap:15px;">
                    <button class="mobile-toggle" onclick="toggleMobileMenu()">☰</button>
                    <h1>Data Leader</h1>
                </div>
                <div class="topbar-right">
                    <button onclick="openModal('modalTambah')" class="btn btn-primary">Tambah Leader</button>
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <?php if ($pesan): ?><div class="alert alert-<?= $tipe ?>"><?= $pesan ?></div><?php endif; ?>

            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">Statistik Leader per Cabang</div>
                <div class="card-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                    <?php foreach ($branchStats as $stat): ?>
                    <div style="background: var(--bg-input); padding: 15px; border-radius: 8px; border-left: 4px solid var(--text-dark);">
                        <h6 style="color: var(--text-muted); margin-bottom: 5px; font-size: 14px; font-weight:600; text-transform:uppercase;"><?= htmlspecialchars($stat['nama_cabang']) ?></h6>
                        <div style="font-size: 24px; font-weight: bold; color: var(--text-dark);"><?= $stat['total_leader'] ?> Leader</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Daftar Semua Leader</div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Leader</th>
                                <th>Username</th>
                                <th>No. HP</th>
                                <th>Cabang</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($leaders as $leader): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($leader['nama_lengkap']) ?></strong></td>
                                <td><?= htmlspecialchars($leader['username']) ?></td>
                                <td><?= htmlspecialchars($leader['no_hp'] ?? '-') ?></td>
                                <td><span class="badge" style="background:var(--bg-input); color:var(--text-dark); border:1px solid var(--border-color);"><?= htmlspecialchars($leader['nama_cabang'] ?? 'Belum Ditentukan') ?></span></td>
                                <td>
                                    <div style="display:flex; gap:5px;">
                                        <button class="btn btn-sm btn-warning" onclick='editLeader(<?= htmlspecialchars(json_encode($leader)) ?>)'>Edit</button>
                                        <a href="?hapus=<?= $leader['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus leader ini?')">Hapus</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($leaders) == 0): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding:30px;">Belum ada leader yang terdaftar</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('modalTambah')">&times;</span>
            <h2 style="margin-bottom:20px;">Tambah Leader Baru</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" class="form-control" name="nama_lengkap" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" class="form-control" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <div class="form-group">
                    <label>No. HP</label>
                    <input type="text" class="form-control" name="no_hp">
                </div>
                <div class="form-group">
                    <label>Cabang</label>
                    <select class="form-control" name="branch_id" required>
                        <option value="">Pilih Cabang</option>
                        <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['nama_cabang']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalTambah')">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary" style="flex: 1;">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('modalEdit')">&times;</span>
            <h2 style="margin-bottom:20px;">Edit Leader</h2>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" class="form-control" name="nama_lengkap" id="edit_nama" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" class="form-control" name="username" id="edit_username" required>
                </div>
                <div class="form-group">
                    <label>Password Baru <small style="text-transform:none; font-weight:normal;">(Kosongkan jika tidak diubah)</small></label>
                    <input type="password" class="form-control" name="password">
                </div>
                <div class="form-group">
                    <label>No. HP</label>
                    <input type="text" class="form-control" name="no_hp" id="edit_hp">
                </div>
                <div class="form-group">
                    <label>Cabang</label>
                    <select class="form-control" name="branch_id" id="edit_branch" required>
                        <option value="">Pilih Cabang</option>
                        <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['nama_cabang']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalEdit')">Batal</button>
                    <button type="submit" name="update" class="btn btn-warning" style="flex: 1;">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
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

        function toggleMobileMenu() { document.getElementById('sidebar').classList.toggle('active'); }
        function toggleSubmenu(el) { el.classList.toggle('active'); el.nextElementSibling.classList.toggle('open'); }

        function openModal(modalId) { document.getElementById(modalId).style.display = 'block'; }
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        function editLeader(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_nama').value = data.nama_lengkap;
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_hp').value = data.no_hp || '';
            document.getElementById('edit_branch').value = data.branch_id || '';
            openModal('modalEdit');
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        setTimeout(function() {
            const alert = document.querySelector('.alert');
            if (alert) alert.style.display = 'none';
        }, 5000);
    </script>
</body>
</html>