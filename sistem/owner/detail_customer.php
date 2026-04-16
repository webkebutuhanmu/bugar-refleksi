<?php
require_once '../config/database.php';
if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

$nama = $_GET['nama'] ?? '';
$hp = $_GET['hp'] ?? '';
$branch_id = $_GET['branch_id'] ?? null;

if(empty($nama)) {
    header("Location: data_customer.php");
    exit;
}

// Data ringkasan customer
if($branch_id) {
    $sqlSummary = "SELECT 
                   nama_pelanggan,
                   no_hp_pelanggan,
                   COUNT(*) as total_kunjungan,
                   SUM(total_bayar) as total_belanja,
                   MAX(tanggal_transaksi) as kunjungan_terakhir,
                   MIN(tanggal_transaksi) as kunjungan_pertama,
                   AVG(total_bayar) as rata_belanja
                   FROM transactions 
                   WHERE nama_pelanggan = ? AND no_hp_pelanggan = ? AND branch_id = ?
                   GROUP BY nama_pelanggan, no_hp_pelanggan";
    $stmtSummary = $pdo->prepare($sqlSummary);
    $stmtSummary->execute([$nama, $hp, $branch_id]);
} else {
    $sqlSummary = "SELECT 
                   nama_pelanggan,
                   no_hp_pelanggan,
                   COUNT(*) as total_kunjungan,
                   SUM(total_bayar) as total_belanja,
                   MAX(tanggal_transaksi) as kunjungan_terakhir,
                   MIN(tanggal_transaksi) as kunjungan_pertama,
                   AVG(total_bayar) as rata_belanja
                   FROM transactions 
                   WHERE nama_pelanggan = ? AND no_hp_pelanggan = ?
                   GROUP BY nama_pelanggan, no_hp_pelanggan";
    $stmtSummary = $pdo->prepare($sqlSummary);
    $stmtSummary->execute([$nama, $hp]);
}
$summary = $stmtSummary->fetch();

if(!$summary) {
    header("Location: data_customer.php");
    exit;
}

// Riwayat transaksi lengkap
if($branch_id) {
    $sqlTransactions = "SELECT t.*, 
                        p.nama_paket,
                        u.nama_lengkap as nama_terapis,
                        b.nama_cabang,
                        uk.nama_lengkap as nama_kasir
                        FROM transactions t
                        JOIN packages p ON t.package_id = p.id
                        JOIN users u ON t.terapis_id = u.id
                        JOIN branches b ON t.branch_id = b.id
                        JOIN users uk ON t.kasir_id = uk.id
                        WHERE t.nama_pelanggan = ? AND t.no_hp_pelanggan = ? AND t.branch_id = ?
                        ORDER BY t.created_at DESC";
    $stmtTransactions = $pdo->prepare($sqlTransactions);
    $stmtTransactions->execute([$nama, $hp, $branch_id]);
} else {
    $sqlTransactions = "SELECT t.*, 
                        p.nama_paket,
                        u.nama_lengkap as nama_terapis,
                        b.nama_cabang,
                        uk.nama_lengkap as nama_kasir
                        FROM transactions t
                        JOIN packages p ON t.package_id = p.id
                        JOIN users u ON t.terapis_id = u.id
                        JOIN branches b ON t.branch_id = b.id
                        JOIN users uk ON t.kasir_id = uk.id
                        WHERE t.nama_pelanggan = ? AND t.no_hp_pelanggan = ?
                        ORDER BY t.created_at DESC";
    $stmtTransactions = $pdo->prepare($sqlTransactions);
    $stmtTransactions->execute([$nama, $hp]);
}
$transactions = $stmtTransactions->fetchAll();

// Paket favorit
if($branch_id) {
    $sqlFavPackage = "SELECT p.nama_paket, COUNT(*) as jumlah
                      FROM transactions t
                      JOIN packages p ON t.package_id = p.id
                      WHERE t.nama_pelanggan = ? AND t.no_hp_pelanggan = ? AND t.branch_id = ?
                      GROUP BY p.nama_paket
                      ORDER BY jumlah DESC
                      LIMIT 1";
    $stmtFav = $pdo->prepare($sqlFavPackage);
    $stmtFav->execute([$nama, $hp, $branch_id]);
} else {
    $sqlFavPackage = "SELECT p.nama_paket, COUNT(*) as jumlah
                      FROM transactions t
                      JOIN packages p ON t.package_id = p.id
                      WHERE t.nama_pelanggan = ? AND t.no_hp_pelanggan = ?
                      GROUP BY p.nama_paket
                      ORDER BY jumlah DESC
                      LIMIT 1";
    $stmtFav = $pdo->prepare($sqlFavPackage);
    $stmtFav->execute([$nama, $hp]);
}
$favPackage = $stmtFav->fetch();

// Terapis favorit
if($branch_id) {
    $sqlFavTerapis = "SELECT u.nama_lengkap, COUNT(*) as jumlah
                      FROM transactions t
                      JOIN users u ON t.terapis_id = u.id
                      WHERE t.nama_pelanggan = ? AND t.no_hp_pelanggan = ? AND t.branch_id = ?
                      GROUP BY u.nama_lengkap
                      ORDER BY jumlah DESC
                      LIMIT 1";
    $stmtFavT = $pdo->prepare($sqlFavTerapis);
    $stmtFavT->execute([$nama, $hp, $branch_id]);
} else {
    $sqlFavTerapis = "SELECT u.nama_lengkap, COUNT(*) as jumlah
                      FROM transactions t
                      JOIN users u ON t.terapis_id = u.id
                      WHERE t.nama_pelanggan = ? AND t.no_hp_pelanggan = ?
                      GROUP BY u.nama_lengkap
                      ORDER BY jumlah DESC
                      LIMIT 1";
    $stmtFavT = $pdo->prepare($sqlFavTerapis);
    $stmtFavT->execute([$nama, $hp]);
}
$favTerapis = $stmtFavT->fetch();

$branchName = '';
if($branch_id) {
    $stmtBranch = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
    $stmtBranch->execute([$branch_id]);
    $branchName = $stmtBranch->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Customer: <?= htmlspecialchars($summary['nama_pelanggan']) ?> - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <style>
        .customer-header { background: var(--bg-panel); border: 1px solid var(--border-color); padding: 30px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px var(--shadow-color); border-left: 5px solid var(--accent-red); }
        .customer-header h2 { margin: 0 0 10px 0; font-size: 28px; color: var(--text-dark); font-family: 'Playfair Display', serif; }
        .customer-header p { margin: 5px 0; color: var(--text-muted); font-size: 14px; }
        .customer-header .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        .customer-header .info-item { background: var(--bg-input); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); }
        .customer-header .info-item label { font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 5px; text-transform: uppercase; font-weight: bold; }
        .customer-header .info-item .value { font-size: 20px; font-weight: bold; color: var(--text-dark); }
        .favorite-box { background: var(--bg-input); padding: 15px; border-radius: 8px; border-left: 4px solid var(--accent-yellow); margin-bottom: 15px; }
        .favorite-box h4 { margin: 0 0 10px 0; color: var(--text-dark); font-size: 14px; text-transform: uppercase; }
        .favorite-box p { margin: 0; font-size: 16px; font-weight: bold; color: var(--text-dark); }
        .info-table td { padding: 12px; border-bottom: 1px dashed var(--border-color); }
        .info-table td:first-child { font-weight: 600; color: var(--text-muted); width: 160px; }
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
                    <h1>Detail Customer<?= $branchName ? ' - ' . htmlspecialchars($branchName) : '' ?></h1>
                </div>
                <div class="topbar-right">
                    <a href="data_customer.php<?= $branch_id ? '?branch_id='.$branch_id : '' ?>" class="btn btn-secondary">Kembali</a>
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <div class="customer-header">
                <h2><?= htmlspecialchars($summary['nama_pelanggan']) ?></h2>
                <p>No HP: <strong><?= htmlspecialchars($summary['no_hp_pelanggan'] ?: 'Tidak tercatat') ?></strong></p>
                <?php if($branchName): ?>
                <p>Data Cabang: <strong><?= htmlspecialchars($branchName) ?></strong></p>
                <?php endif; ?>
                
                <div class="info-grid">
                    <div class="info-item">
                        <label>Total Kunjungan</label>
                        <div class="value" style="color: var(--accent-red2);"><?= $summary['total_kunjungan'] ?>x</div>
                    </div>
                    <div class="info-item">
                        <label>Total Belanja</label>
                        <div class="value">Rp <?= number_format($summary['total_belanja'], 0, ',', '.') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Rata-rata Belanja</label>
                        <div class="value">Rp <?= number_format($summary['rata_belanja'], 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <div class="card-header">Informasi Kunjungan</div>
                    <table class="info-table" style="width: 100%;">
                        <tr>
                            <td>Kunjungan Pertama</td>
                            <td><strong style="color: var(--text-dark);"><?= date('d M Y', strtotime($summary['kunjungan_pertama'])) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Kunjungan Terakhir</td>
                            <td><strong style="color: var(--text-dark);"><?= date('d M Y', strtotime($summary['kunjungan_terakhir'])) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Status Customer</td>
                            <td>
                                <?php if($summary['total_kunjungan'] >= 10): ?>
                                    <span class="badge" style="background: rgba(155, 89, 182, 0.15); color: #8e44ad; border: 1px solid rgba(155, 89, 182, 0.3);">LOYAL CUSTOMER</span>
                                <?php elseif($summary['total_kunjungan'] >= 5): ?>
                                    <span class="badge badge-success">REGULAR CUSTOMER</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">NEW CUSTOMER</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header">Preferensi Favorit</div>
                    <div class="favorite-box">
                        <h4>Paket Favorit</h4>
                        <p><?= $favPackage ? htmlspecialchars($favPackage['nama_paket']) . ' (' . $favPackage['jumlah'] . 'x)' : '-' ?></p>
                    </div>
                    <div class="favorite-box" style="border-left-color: var(--accent-red);">
                        <h4>Terapis Favorit</h4>
                        <p><?= $favTerapis ? htmlspecialchars($favTerapis['nama_lengkap']) . ' (' . $favTerapis['jumlah'] . 'x)' : '-' ?></p>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-header">Riwayat Transaksi Lengkap (<?= count($transactions) ?>)</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal & Jam</th>
                                <th>Cabang</th>
                                <th>Paket</th>
                                <th>Terapis</th>
                                <th>Kasir</th>
                                <th>Total Bayar</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($transactions as $trx): ?>
                            <tr>
                                <td>
                                    <strong><?= date('d M Y', strtotime($trx['tanggal_transaksi'])) ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= date('H:i', strtotime($trx['created_at'])) ?></small>
                                </td>
                                <td><?= htmlspecialchars($trx['nama_cabang']) ?></td>
                                <td><?= htmlspecialchars($trx['nama_paket']) ?></td>
                                <td><?= htmlspecialchars($trx['nama_terapis']) ?></td>
                                <td><?= htmlspecialchars($trx['nama_kasir']) ?></td>
                                <td><strong style="color: var(--text-dark);">Rp <?= number_format($trx['total_bayar'], 0, ',', '.') ?></strong></td>
                                <td>
                                    <?php if($trx['status'] == 'selesai'): ?>
                                        <span class="badge badge-success">Selesai</span>
                                    <?php elseif($trx['status'] == 'proses'): ?>
                                        <span class="badge badge-warning">Proses</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Batal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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