<?php
require_once '../config/database.php';
if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

$branch_id = $_GET['branch_id'] ?? null;

// Get all branches
$branches = $pdo->query("SELECT * FROM branches ORDER BY nama_cabang")->fetchAll();

// Query untuk mendapatkan data customer
if($branch_id) {
    // Filter by branch
    $sqlCustomers = "SELECT 
                     nama_pelanggan,
                     no_hp_pelanggan,
                     COUNT(*) as total_kunjungan,
                     SUM(total_bayar) as total_belanja,
                     MAX(tanggal_transaksi) as kunjungan_terakhir,
                     MIN(tanggal_transaksi) as kunjungan_pertama
                     FROM transactions 
                     WHERE nama_pelanggan != '' AND nama_pelanggan IS NOT NULL AND branch_id = ?
                     GROUP BY nama_pelanggan, no_hp_pelanggan
                     ORDER BY total_kunjungan DESC, kunjungan_terakhir DESC";
    $stmt = $pdo->prepare($sqlCustomers);
    $stmt->execute([$branch_id]);
    $customers = $stmt->fetchAll();
    
    // Get branch name
    $stmtBranch = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
    $stmtBranch->execute([$branch_id]);
    $branchName = $stmtBranch->fetchColumn();
} else {
    // All customers
    $sqlCustomers = "SELECT 
                     nama_pelanggan,
                     no_hp_pelanggan,
                     COUNT(*) as total_kunjungan,
                     SUM(total_bayar) as total_belanja,
                     MAX(tanggal_transaksi) as kunjungan_terakhir,
                     MIN(tanggal_transaksi) as kunjungan_pertama
                     FROM transactions 
                     WHERE nama_pelanggan != '' AND nama_pelanggan IS NOT NULL
                     GROUP BY nama_pelanggan, no_hp_pelanggan
                     ORDER BY total_kunjungan DESC, kunjungan_terakhir DESC";
    $customers = $pdo->query($sqlCustomers)->fetchAll();
}

// Customer stats per branch
$sqlBranchStats = "SELECT 
                   b.id,
                   b.nama_cabang,
                   COUNT(DISTINCT CONCAT(t.nama_pelanggan, '-', COALESCE(t.no_hp_pelanggan, ''))) as total_customer,
                   COUNT(t.id) as total_transaksi,
                   SUM(t.total_bayar) as total_pendapatan
                   FROM branches b
                   LEFT JOIN transactions t ON b.id = t.branch_id AND t.nama_pelanggan != '' AND t.nama_pelanggan IS NOT NULL
                   GROUP BY b.id, b.nama_cabang
                   ORDER BY b.nama_cabang";
$branchStats = $pdo->query($sqlBranchStats)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Customer - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <style>
        .branch-filter {
            background: var(--bg-panel);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .branch-filter a {
            display: inline-block;
            padding: 8px 15px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }
        .branch-filter a:hover {
            border-color: var(--text-dark);
            color: var(--text-dark);
        }
        .branch-filter a.active {
            background: var(--accent-yellow);
            color: #111;
            border-color: var(--accent-yellow);
        }
        .badge-loyal { background: rgba(155, 89, 182, 0.15); color: #8e44ad; border: 1px solid rgba(155, 89, 182, 0.3); }
        .badge-new { background: rgba(52, 152, 219, 0.15); color: #2980b9; border: 1px solid rgba(52, 152, 219, 0.3); }
        .badge-regular { background: rgba(46, 204, 113, 0.15); color: #27ae60; border: 1px solid rgba(46, 204, 113, 0.3); }
    </style>
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
                <a href="data_leader.php" class="menu-item">Data Leader</a>
                <a href="data_kasir.php" class="menu-item">Data Kasir</a>
                <a href="data_terapis.php" class="menu-item">Data Terapis</a>
                <a href="data_customer.php" class="menu-item active">Data Customer</a>
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
                    <h1>Data Customer</h1>
                </div>
                <div class="topbar-right">
                    <span style="color: var(--text-muted); font-size:14px;">Total Customer: <strong style="color:var(--text-dark);"><?= count($customers) ?></strong></span>
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Customer Per Cabang</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Nama Cabang</th>
                                <th>Total Customer</th>
                                <th>Total Transaksi</th>
                                <th>Total Pendapatan</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach($branchStats as $bs): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($bs['nama_cabang']) ?></strong></td>
                                <td><strong style="color: #3498db;"><?= $bs['total_customer'] ?> customer</strong></td>
                                <td><?= $bs['total_transaksi'] ?> transaksi</td>
                                <td><strong style="color: #27ae60;">Rp <?= number_format($bs['total_pendapatan'], 0, ',', '.') ?></strong></td>
                                <td>
                                    <a href="?branch_id=<?= $bs['id'] ?>" class="btn btn-primary btn-sm">Lihat Customer</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if($branch_id): ?>
            <div class="branch-filter">
                <strong style="color: var(--text-dark); font-size:14px;">Filter Cabang:</strong>
                <a href="data_customer.php" class="<?= !$branch_id ? 'active' : '' ?>">Semua Cabang</a>
                <?php foreach($branches as $b): ?>
                <a href="?branch_id=<?= $b['id'] ?>" class="<?= $branch_id == $b['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($b['nama_cabang']) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="card-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="stat-card">
                    <h3>Total Customer <?= isset($branchName) ? '- ' . $branchName : '' ?></h3>
                    <div class="value"><?= count($customers) ?></div>
                    <small>Terdaftar</small>
                </div>
                <div class="stat-card">
                    <h3>Total Transaksi</h3>
                    <div class="value"><?= array_sum(array_column($customers, 'total_kunjungan')) ?></div>
                    <small>Keseluruhan</small>
                </div>
                <div class="stat-card">
                    <h3>Total Pendapatan</h3>
                    <div class="value">Rp <?= number_format(array_sum(array_column($customers, 'total_belanja')), 0, ',', '.') ?></div>
                    <small>Dari Customer</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    Daftar Customer <?= isset($branchName) ? '- ' . $branchName : '' ?>
                    <small style="float: right; font-weight:normal; font-family:'DM Sans', sans-serif; color:var(--text-muted);">Diurutkan dari total kunjungan</small>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Nama Customer</th>
                                <th>No HP</th>
                                <th>Kunjungan</th>
                                <th>Total Belanja</th>
                                <th>Terakhir Datang</th>
                                <th>Status</th>
                                <th width="12%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($customers) > 0): ?>
                                <?php 
                                $no = 1;
                                foreach($customers as $c): 
                                    $badge = '';
                                    if($c['total_kunjungan'] >= 10) {
                                        $badge = '<span class="badge badge-loyal">LOYAL</span>';
                                    } elseif($c['total_kunjungan'] >= 5) {
                                        $badge = '<span class="badge badge-regular">REGULAR</span>';
                                    } else {
                                        $badge = '<span class="badge badge-new">NEW</span>';
                                    }
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= htmlspecialchars($c['nama_pelanggan']) ?></strong></td>
                                    <td><?= htmlspecialchars($c['no_hp_pelanggan'] ?: '-') ?></td>
                                    <td><strong style="color: var(--accent-red2);"><?= $c['total_kunjungan'] ?>x</strong></td>
                                    <td><strong style="color: var(--text-dark);">Rp <?= number_format($c['total_belanja'], 0, ',', '.') ?></strong></td>
                                    <td><?= date('d M Y', strtotime($c['kunjungan_terakhir'])) ?></td>
                                    <td><?= $badge ?></td>
                                    <td>
                                        <a href="detail_customer.php?nama=<?= urlencode($c['nama_pelanggan']) ?>&hp=<?= urlencode($c['no_hp_pelanggan']) ?><?= $branch_id ? '&branch_id='.$branch_id : '' ?>" 
                                           class="btn btn-primary btn-sm">Detail</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan=\"8\" style=\"text-align: center; padding: 40px; color: var(--text-muted);\">
                                        Belum ada data customer di cabang ini
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
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