<?php
require_once '../config/database.php';
if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

function getNamaCabang($pdo, $id) {
    $stmt = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}

$branch_id = $_GET['branch'] ?? null;
$namaCabangFilter = $branch_id ? getNamaCabang($pdo, $branch_id) : "Semua Cabang";

$sqlBranchStats = "SELECT 
                   b.id, b.nama_cabang,
                   (SELECT COUNT(*) FROM users u WHERE u.home_branch_id = b.id AND u.role = 'terapis') as terapis_home,
                   (SELECT COUNT(*) FROM transactions t WHERE t.branch_id = b.id AND t.commission_status = 'pending') as total_transaksi,
                   (SELECT COALESCE(SUM(t.omset_terapis), 0) FROM transactions t WHERE t.branch_id = b.id AND t.commission_status = 'pending') as total_komisi
                   FROM branches b ORDER BY b.nama_cabang";
$branchStats = $pdo->query($sqlBranchStats)->fetchAll();

$whereCabang = "";
$params = [];

if($branch_id) {
    $whereCabang = "AND u.home_branch_id = ?";
    $params[] = $branch_id;
    $namaCabangFilter = getNamaCabang($pdo, $branch_id);
}

$sqlTerapis = "SELECT u.*, 
                b.nama_cabang as home_branch_name,
                COALESCE(SUM(CASE WHEN t.commission_status = 'pending' THEN 1 ELSE 0 END), 0) as total_transaksi,
                COALESCE(SUM(CASE WHEN t.commission_status = 'pending' THEN t.omset_terapis ELSE 0 END), 0) as total_pendapatan
                FROM users u
                LEFT JOIN branches b ON u.home_branch_id = b.id
                LEFT JOIN transactions t ON u.id = t.terapis_id 
                WHERE u.role = 'terapis' $whereCabang
                GROUP BY u.id
                ORDER BY total_pendapatan DESC, u.nama_lengkap ASC";

$stmtTerapis = $pdo->prepare($sqlTerapis);
$stmtTerapis->execute($params);
$terapis = $stmtTerapis->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Terapis - Owner</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <style>
        .branch-filter { display:flex; flex-wrap:wrap; gap:10px; margin-bottom: 20px; }
        .branch-filter a { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; color: var(--text-muted); background: var(--bg-input); font-weight:600; font-size:13px; transition:0.3s; }
        .branch-filter a.active { background: var(--accent-yellow); color: #111; }
        .branch-filter a:hover:not(.active) { background: var(--border-color); color: var(--text-dark); }
        .info-box { background: var(--bg-input); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 5px solid var(--accent-yellow); }
        .info-box h4 { margin: 0 0 10px 0; color: var(--text-dark); font-size: 16px; }
        .info-box p { margin: 0; color: var(--text-muted); font-size: 13px; line-height: 1.6; }
        .avatar-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }
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
                <a href="data_terapis.php" class="menu-item active">Data Terapis</a>
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
                    <h1>Data Terapis</h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <div class="info-box">
                <h4>Informasi Halaman</h4>
                <p>
                    Sebagai Owner, Anda dapat melihat performa dan komisi pending semua terapis di sistem ini. 
                    Untuk menambah, mengedit, atau menghapus terapis, silakan hubungi Leader cabang masing-masing. 
                    Leader memiliki akses penuh untuk mengelola terapis di cabangnya.
                </p>
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">Statistik Komisi Pending per Cabang</div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cabang</th>
                                <th style="text-align: center;">Jumlah Terapis</th>
                                <th style="text-align: center;">Transaksi Pending</th>
                                <th style="text-align: right;">Total Komisi Pending</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalAllTerapis = 0; $totalAllTrx = 0; $totalAllKomisi = 0;
                            foreach($branchStats as $bs): 
                                $totalAllTerapis += $bs['terapis_home'];
                                $totalAllTrx += $bs['total_transaksi'];
                                $totalAllKomisi += $bs['total_komisi'];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($bs['nama_cabang']) ?></strong></td>
                                <td style="text-align: center;"><?= $bs['terapis_home'] ?> orang</td>
                                <td style="text-align: center;">
                                    <?php if($bs['total_transaksi'] > 0): ?>
                                        <span class="badge badge-pending"><?= $bs['total_transaksi'] ?> pending</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: bold; color: var(--accent-yellow2);">
                                    Rp <?= number_format($bs['total_komisi'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="background: var(--bg-input); font-weight: bold;">
                                <td>TOTAL</td>
                                <td style="text-align: center;"><?= $totalAllTerapis ?> orang</td>
                                <td style="text-align: center;"><?= $totalAllTrx ?> pending</td>
                                <td style="text-align: right; color: var(--accent-red2); font-size: 16px;">
                                    Rp <?= number_format($totalAllKomisi, 0, ',', '.') ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="branch-filter">
                <a href="?" class="<?= !$branch_id ? 'active' : '' ?>">Semua Cabang</a>
                <?php foreach($branchStats as $bs): ?>
                <a href="?branch=<?= $bs['id'] ?>" class="<?= $branch_id == $bs['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($bs['nama_cabang']) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    Daftar Terapis - <?= htmlspecialchars($namaCabangFilter) ?>
                    <small style="float: right; opacity: 0.7; font-weight:normal; font-family:'DM Sans', sans-serif;"><?= count($terapis) ?> Terapis</small>
                </div>
                <?php if(count($terapis) > 0): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Foto</th>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>Cabang Home</th>
                                <th style="text-align: center;">Trx Pending</th>
                                <th style="text-align: right;">Komisi Pending</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1; 
                            foreach($terapis as $t): 
                                $foto = !empty($t['foto_profil']) && file_exists("../uploads/profil/".$t['foto_profil'])
                                    ? "../uploads/profil/".$t['foto_profil']
                                    : "../assets/default_user.png";
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><img src="<?= $foto ?>" alt="Foto" class="avatar-img"></td>
                                <td><strong><?= htmlspecialchars($t['nama_lengkap']) ?></strong></td>
                                <td><?= htmlspecialchars($t['username']) ?></td>
                                <td><?= htmlspecialchars($t['home_branch_name']) ?></td>
                                <td style="text-align: center;">
                                    <?php if($t['total_transaksi'] > 0): ?>
                                        <span class="badge badge-pending"><?= $t['total_transaksi'] ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: bold; color: <?= $t['total_pendapatan'] > 0 ? 'var(--accent-yellow2)' : 'var(--text-muted)' ?>;">
                                    Rp <?= number_format($t['total_pendapatan'], 0, ',', '.') ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="detail_terapis.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm">Detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                    <h3>Tidak Ada Terapis</h3>
                    <p>Belum ada terapis yang terdaftar di <?= htmlspecialchars($namaCabangFilter) ?></p>
                </div>
                <?php endif; ?>
            </div>
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
    </script>
</body>
</html>