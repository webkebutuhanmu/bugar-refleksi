<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) { 
    header("Location: pilih_cabang.php"); exit; 
}

$branch_id = $_SESSION['active_branch'];
$kasir_id  = $_SESSION['user_id'];
$nama_kasir = $_SESSION['nama'];
$nama_cabang = $pdo->query("SELECT nama_cabang FROM branches WHERE id = $branch_id")->fetchColumn();

// Foto profil
$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$foto_profil = $stmtProfil->fetchColumn();
$foto_profil = (!empty($foto_profil) && file_exists("../uploads/profil/" . $foto_profil)) ? "../uploads/profil/" . $foto_profil : "../assets/default_user.png";

$nama = $_GET['nama'] ?? '';
$hp = $_GET['hp'] ?? '';

if(empty($nama)) {
    header("Location: data_customer_kasir.php");
    exit;
}

// Data ringkasan customer (hanya di cabang ini)
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
$summary = $stmtSummary->fetch();

if(!$summary) {
    header("Location: data_customer_kasir.php");
    exit;
}

// Riwayat transaksi lengkap (hanya di cabang ini)
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
$transactions = $stmtTransactions->fetchAll();

// Paket favorit (di cabang ini)
$sqlFavPackage = "SELECT p.nama_paket, COUNT(*) as jumlah
                  FROM transactions t
                  JOIN packages p ON t.package_id = p.id
                  WHERE t.nama_pelanggan = ? AND t.no_hp_pelanggan = ? AND t.branch_id = ?
                  GROUP BY p.nama_paket
                  ORDER BY jumlah DESC
                  LIMIT 1";
$stmtFav = $pdo->prepare($sqlFavPackage);
$stmtFav->execute([$nama, $hp, $branch_id]);
$favPackage = $stmtFav->fetch();

// Terapis favorit (di cabang ini)
$sqlFavTerapis = "SELECT u.nama_lengkap, COUNT(*) as jumlah
                  FROM transactions t
                  JOIN users u ON t.terapis_id = u.id
                  WHERE t.nama_pelanggan = ? AND t.no_hp_pelanggan = ? AND t.branch_id = ?
                  GROUP BY u.nama_lengkap
                  ORDER BY jumlah DESC
                  LIMIT 1";
$stmtFavT = $pdo->prepare($sqlFavTerapis);
$stmtFavT->execute([$nama, $hp, $branch_id]);
$favTerapis = $stmtFavT->fetch();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Customer: <?= htmlspecialchars($summary['nama_pelanggan']) ?> - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <style>
        .customer-header { background: var(--bg-panel); border: 1px solid var(--border-color); padding: 30px; border-radius: 12px; margin-bottom: 20px; box-shadow: var(--shadow-sm); border-left: 5px solid var(--accent-blue); }
        .customer-header h2 { margin: 0 0 10px 0; font-size: 28px; color: var(--text-dark); font-family: 'Playfair Display', serif; }
        .customer-header p { margin: 5px 0; color: var(--text-muted); font-size: 14px; font-weight: 600; }
        .customer-header .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        .customer-header .info-item { background: var(--bg-input); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); }
        .customer-header .info-item label { font-size: 11px; color: var(--text-muted); display: block; margin-bottom: 5px; text-transform: uppercase; font-weight: bold; }
        .customer-header .info-item .value { font-size: 20px; font-weight: bold; color: var(--text-dark); }
        
        .favorite-box { background: var(--bg-input); padding: 15px; border-radius: 8px; border-left: 4px solid var(--accent-yellow); margin-bottom: 15px; border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }
        .favorite-box h4 { margin: 0 0 10px 0; color: var(--text-dark); font-size: 13px; text-transform: uppercase; }
        .favorite-box p { margin: 0; font-size: 16px; font-weight: bold; color: var(--text-dark); }
        
        .info-table td { padding: 12px; border-bottom: 1px dashed var(--border-color); font-size: 14px; }
        .info-table td:first-child { font-weight: 600; color: var(--text-muted); width: 160px; }
        
        .badge-status { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; letter-spacing: 0.5px; }
        .bg-loyal { background: rgba(155,89,182,0.15); color: #8e44ad; border: 1px solid rgba(155,89,182,0.3); }
        .bg-regular { background: rgba(39,174,96,0.15); color: #27ae60; border: 1px solid rgba(39,174,96,0.3); }
        .bg-new { background: rgba(52,152,219,0.15); color: #2980b9; border: 1px solid rgba(52,152,219,0.3); }
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
                <a href="data_customer_kasir.php" class="menu-item active"><span class="menu-abbr">DC</span><span class="menu-text">Data Customer</span></a>
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
                    <h1>Detail Customer</h1>
                </div>
                <div class="topbar-right">
                    <a href="data_customer_kasir.php" class="btn btn-secondary">Kembali</a>
                    <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
                </div>
            </div>

            <div class="customer-header">
                <h2><?= htmlspecialchars($summary['nama_pelanggan']) ?></h2>
                <p>No HP: <strong style="color:var(--text-dark);"><?= htmlspecialchars($summary['no_hp_pelanggan'] ?: 'Tidak tercatat') ?></strong></p>
                <p>Data untuk cabang: <strong style="color:var(--text-dark);"><?= htmlspecialchars($nama_cabang) ?></strong></p>
                
                <div class="info-grid">
                    <div class="info-item">
                        <label>Total Kunjungan</label>
                        <div class="value" style="color:var(--accent-blue);"><?= $summary['total_kunjungan'] ?>x</div>
                    </div>
                    <div class="info-item">
                        <label>Total Belanja</label>
                        <div class="value" style="color:var(--accent-green);">Rp <?= number_format($summary['total_belanja'], 0, ',', '.') ?></div>
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
                            <td><strong style="color:var(--text-dark);"><?= date('d M Y', strtotime($summary['kunjungan_pertama'])) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Kunjungan Terakhir</td>
                            <td><strong style="color:var(--text-dark);"><?= date('d M Y', strtotime($summary['kunjungan_terakhir'])) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Status Customer</td>
                            <td>
                                <?php if($summary['total_kunjungan'] >= 10): ?>
                                    <span class="badge-status bg-loyal">LOYAL CUSTOMER</span>
                                <?php elseif($summary['total_kunjungan'] >= 5): ?>
                                    <span class="badge-status bg-regular">REGULAR CUSTOMER</span>
                                <?php else: ?>
                                    <span class="badge-status bg-new">NEW CUSTOMER</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header">Preferensi Favorit</div>
                    <div style="padding: 20px;">
                        <div class="favorite-box">
                            <h4>Paket Favorit</h4>
                            <p><?= $favPackage ? htmlspecialchars($favPackage['nama_paket']) . ' (' . $favPackage['jumlah'] . 'x)' : '-' ?></p>
                        </div>
                        <div class="favorite-box" style="border-left-color: var(--accent-red); margin-bottom: 0;">
                            <h4>Terapis Favorit</h4>
                            <p><?= $favTerapis ? htmlspecialchars($favTerapis['nama_lengkap']) . ' (' . $favTerapis['jumlah'] . 'x)' : '-' ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-header">Riwayat Transaksi di <?= htmlspecialchars($nama_cabang) ?> (<?= count($transactions) ?>)</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal & Jam</th>
                                <th>Paket</th>
                                <th>Terapis</th>
                                <th>Kasir</th>
                                <th>Total Bayar</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($transactions) > 0): ?>
                                <?php foreach($transactions as $trx): ?>
                                <tr>
                                    <td>
                                        <strong><?= date('d M Y', strtotime($trx['tanggal_transaksi'])) ?></strong><br>
                                        <small style="color: var(--text-muted); font-weight:600;"><?= date('H:i', strtotime($trx['created_at'])) ?></small>
                                    </td>
                                    <td><strong style="color:var(--text-dark); font-size:13px;"><?= htmlspecialchars($trx['nama_paket']) ?></strong></td>
                                    <td><?= htmlspecialchars($trx['nama_terapis']) ?></td>
                                    <td><small style="color:var(--text-muted); font-weight:bold;"><?= htmlspecialchars($trx['nama_kasir']) ?></small></td>
                                    <td><strong style="color: var(--accent-green);">Rp <?= number_format($trx['total_bayar'], 0, ',', '.') ?></strong></td>
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
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted); font-weight:600;">
                                        Belum ada transaksi di cabang ini
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
    </script>
</body>
</html>