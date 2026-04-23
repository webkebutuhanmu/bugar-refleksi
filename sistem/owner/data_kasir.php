<?php
require_once '../config/database.php';
if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

$pesan = "";
$tipe = "";

// Tambah Kasir
if (isset($_POST['tambah'])) {
    $username = htmlspecialchars($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama = htmlspecialchars($_POST['nama_lengkap']);
    
    $sql = "INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, 'kasir')";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$username, $password, $nama])) {
        $pesan = "Kasir berhasil ditambahkan!";
        $tipe = "success";
    } else {
        $pesan = "Gagal menambahkan kasir! Username mungkin sudah ada.";
        $tipe = "danger";
    }
}

// Edit Kasir
if (isset($_POST['edit'])) {
    $id = $_POST['id'];
    $username = htmlspecialchars($_POST['username']);
    $nama = htmlspecialchars($_POST['nama_lengkap']);
    
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET username = ?, password = ?, nama_lengkap = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $password, $nama, $id]);
    } else {
        $sql = "UPDATE users SET username = ?, nama_lengkap = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $nama, $id]);
    }
    $pesan = "Kasir berhasil diupdate!";
    $tipe = "success";
}

// Hapus Kasir
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    $sql = "DELETE FROM users WHERE id = ? AND role = 'kasir'";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$id])) {
        $pesan = "Kasir berhasil dihapus!";
        $tipe = "success";
    } else {
        $pesan = "Gagal menghapus kasir!";
        $tipe = "danger";
    }
}

// Get Kasir dengan statistik
$kasir = $pdo->query("SELECT u.*,
                      (SELECT COUNT(*) FROM transactions t WHERE t.kasir_id = u.id) as total_transaksi,
                      (SELECT SUM(t.total_bayar) FROM transactions t WHERE t.kasir_id = u.id) as total_omset,
                      (SELECT COUNT(DISTINCT ka.tanggal) FROM kasir_attendance ka WHERE ka.kasir_id = u.id) as total_hari_kerja
                      FROM users u
                      WHERE u.role = 'kasir'
                      ORDER BY u.id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kasir - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
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
                <a href="data_kasir.php" class="menu-item active">Data Kasir</a>
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
                    <h1>Data Kasir</h1>
                </div>
                <div class="topbar-right">
                    <button onclick="openModal('modalTambah')" class="btn btn-success">Tambah Kasir</button>
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <?php if($pesan): ?>
            <div class="alert alert-<?= $tipe ?>"><?= $pesan ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">Daftar Kasir & Performa</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>Total Transaksi</th>
                                <th>Total Omset</th>
                                <th>Hari Kerja</th>
                                <th width="20%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($kasir as $k): ?>
                            <tr>
                                <td><?= $k['id'] ?></td>
                                <td><strong><?= htmlspecialchars($k['nama_lengkap']) ?></strong></td>
                                <td><?= htmlspecialchars($k['username']) ?></td>
                                <td><?= $k['total_transaksi'] ?> kali</td>
                                <td><strong style="color: var(--text-dark);">Rp <?= number_format($k['total_omset'] ?? 0, 0, ',', '.') ?></strong></td>
                                <td><?= $k['total_hari_kerja'] ?> hari</td>
                                <td>
                                    <div style="display:flex; gap:5px;">
                                        <a href="detail_kasir.php?id=<?= $k['id'] ?>" class="btn btn-primary btn-sm">Detail</a>
                                        <button onclick="editKasir(<?= $k['id'] ?>, '<?= htmlspecialchars($k['username']) ?>', '<?= htmlspecialchars($k['nama_lengkap']) ?>')" class="btn btn-warning btn-sm">Edit</button>
                                        <a href="?hapus=<?= $k['id'] ?>" onclick="return confirm('Yakin hapus kasir ini?')" class="btn btn-danger btn-sm">Hapus</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('modalTambah')">&times;</span>
            <h2 style="margin-bottom:20px;">Tambah Kasir Baru</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" required>
                </div>
                <button type="submit" name="tambah" class="btn btn-success" style="width:100%; margin-top:10px;">Simpan Data</button>
            </form>
        </div>
    </div>

    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('modalEdit')">&times;</span>
            <h2 style="margin-bottom:20px;">Edit Kasir</h2>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="edit_username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password <small style="text-transform:none; font-weight:normal;">(Kosongkan jika tidak diubah)</small></label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
                </div>
                <button type="submit" name="edit" class="btn btn-warning" style="width:100%; margin-top:10px;">Update Data</button>
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
        
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function editKasir(id, username, nama) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_nama').value = nama;
            openModal('modalEdit');
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>