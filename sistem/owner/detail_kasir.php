<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'kasir'");
$stmt->execute([$id]);
$kasir = $stmt->fetch();

if (!$kasir) {
    header("Location: data_kasir.php");
    exit;
}

$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulai = $setting['jam_mulai_hari'] ?? '08:00:00';

$expBusinessDate = "IF(TIME(created_at) < '$jamMulai', DATE_SUB(DATE(created_at), INTERVAL 1 DAY), DATE(created_at))";
$expBusinessDateTrx = "IF(TIME(t.created_at) < '$jamMulai', DATE_SUB(DATE(t.created_at), INTERVAL 1 DAY), DATE(t.created_at))";

$stmtOmset = $pdo->prepare("SELECT SUM(total_bayar) FROM transactions WHERE kasir_id = ?");
$stmtOmset->execute([$id]);
$totalOmset = $stmtOmset->fetchColumn() ?? 0;

$stmtTrx = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE kasir_id = ?");
$stmtTrx->execute([$id]);
$totalTrx = $stmtTrx->fetchColumn();

$stmtHari = $pdo->prepare("SELECT COUNT(DISTINCT tanggal) FROM kasir_attendance WHERE kasir_id = ?");
$stmtHari->execute([$id]);
$totalHariKerja = $stmtHari->fetchColumn();

$rataOmset = $totalHariKerja > 0 ? $totalOmset / $totalHariKerja : 0;

$sqlKehadiran = "SELECT 
                    ka.tanggal, ka.waktu_masuk, ka.waktu_keluar, b.nama_cabang,
                    (SELECT COUNT(*) FROM transactions t 
                     WHERE t.kasir_id = ka.kasir_id AND t.branch_id = ka.branch_id 
                     AND t.created_at >= ka.waktu_masuk 
                     AND t.created_at <= COALESCE(ka.waktu_keluar, NOW())) as total_trx,
                    (SELECT COALESCE(SUM(t.total_bayar),0) FROM transactions t 
                     WHERE t.kasir_id = ka.kasir_id AND t.branch_id = ka.branch_id 
                     AND t.created_at >= ka.waktu_masuk 
                     AND t.created_at <= COALESCE(ka.waktu_keluar, NOW())) as omset
                 FROM kasir_attendance ka
                 JOIN branches b ON ka.branch_id = b.id
                 WHERE ka.kasir_id = ?
                 ORDER BY ka.waktu_masuk DESC LIMIT 30";

$stmtKehadiran = $pdo->prepare($sqlKehadiran);
$stmtKehadiran->execute([$id]);
$kehadiran = $stmtKehadiran->fetchAll();

$sqlRecentTrx = "SELECT t.*, u.nama_lengkap as nama_terapis, p.nama_paket, b.nama_cabang
                 FROM transactions t
                 LEFT JOIN users u ON t.terapis_id = u.id
                 LEFT JOIN packages p ON t.package_id = p.id
                 JOIN branches b ON t.branch_id = b.id
                 WHERE t.kasir_id = ?
                 ORDER BY t.created_at DESC LIMIT 20";
$stmtRecentTrx = $pdo->prepare($sqlRecentTrx);
$stmtRecentTrx->execute([$id]);
$recentTrx = $stmtRecentTrx->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail <?= htmlspecialchars($kasir['nama_lengkap']) ?> - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <style>
        .profile-header { background: var(--bg-panel); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 20px; margin-bottom: 20px; box-shadow: 0 4px 15px var(--shadow-color); }
        .profile-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; background: var(--bg-input); border: 3px solid var(--accent-yellow); }
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
                    <h1>Detail Kasir</h1>
                </div>
                <div class="topbar-right">
                    <a href="data_kasir.php" class="btn btn-secondary">Kembali</a>
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <div class="profile-header">
                <?php $foto = !empty($kasir['foto_profil']) ? "../assets/uploads/".$kasir['foto_profil'] : "../assets/default_user.png"; ?>
                <img src="<?= $foto ?>" class="profile-avatar">
                <div>
                    <h2 style="margin:0; font-family:'Playfair Display', serif; color:var(--text-dark);"><?= htmlspecialchars($kasir['nama_lengkap']) ?></h2>
                    <p style="margin:5px 0; color:var(--text-muted); font-size:14px;">Username: <strong style="color:var(--text-dark);"><?= htmlspecialchars($kasir['username']) ?></strong></p>
                    <span class="badge" style="background: rgba(41, 128, 185, 0.15); color: #2980b9; border: 1px solid rgba(41, 128, 185, 0.3);">KASIR CABANG</span>
                </div>
            </div>

            <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card">
                    <h3>Total Omset</h3>
                    <div class="value">Rp <?= number_format($totalOmset, 0, ',', '.') ?></div>
                    <small>Seumur Hidup</small>
                </div>
                <div class="stat-card">
                    <h3>Total Transaksi</h3>
                    <div class="value"><?= number_format($totalTrx) ?></div>
                    <small>Transaksi Diselesaikan</small>
                </div>
                <div class="stat-card">
                    <h3>Total Shift</h3>
                    <div class="value"><?= number_format($totalHariKerja) ?></div>
                    <small>Hari Kerja</small>
                </div>
                <div class="stat-card">
                    <h3>Rata-rata/Hari</h3>
                    <div class="value" style="color:var(--accent-red2);">Rp <?= number_format($rataOmset, 0, ',', '.') ?></div>
                    <small>Performa Harian</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Riwayat Shift & Omset (30 Terakhir)</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal (Shift)</th>
                                <th>Cabang</th>
                                <th>Jam Kerja</th>
                                <th>Total Trx</th>
                                <th>Omset Shift</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($kehadiran) > 0): ?>
                                <?php foreach($kehadiran as $h): 
                                    $tglShift = date('d M Y', strtotime($h['tanggal']));
                                    $jamMasuk = date('H:i', strtotime($h['waktu_masuk']));
                                    $jamKeluar = $h['waktu_keluar'] ? date('H:i', strtotime($h['waktu_keluar'])) : 'Sekarang';
                                ?>
                                <tr>
                                    <td><strong><?= $tglShift ?></strong></td>
                                    <td><?= htmlspecialchars($h['nama_cabang']) ?></td>
                                    <td><span style="color:var(--text-muted); font-weight:bold;"><?= $jamMasuk ?> - <?= $jamKeluar ?></span></td>
                                    <td><?= $h['total_trx'] ?> trx</td>
                                    <td><strong style="color:var(--text-dark);">Rp <?= number_format($h['omset'], 0, ',', '.') ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">Belum ada data kehadiran shift.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">20 Transaksi Terakhir</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Cabang</th>
                                <th>Pelanggan</th>
                                <th>Paket</th>
                                <th>Terapis</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($recentTrx) > 0): ?>
                                <?php foreach($recentTrx as $trx): ?>
                                <tr>
                                    <td><?= date('d/m H:i', strtotime($trx['created_at'])) ?></td>
                                    <td><small style="color:var(--text-muted); font-weight:bold;"><?= htmlspecialchars($trx['nama_cabang']) ?></small></td>
                                    <td><strong><?= htmlspecialchars($trx['nama_pelanggan']) ?></strong></td>
                                    <td><?= htmlspecialchars($trx['nama_paket']) ?></td>
                                    <td><?= htmlspecialchars($trx['nama_terapis']) ?></td>
                                    <td><strong style="color:var(--text-dark);">Rp <?= number_format($trx['total_bayar'], 0, ',', '.') ?></strong></td>
                                    <td>
                                        <?php if($trx['status'] == 'proses'): ?>
                                            <span class="badge badge-warning">Proses</span>
                                        <?php elseif($trx['status'] == 'selesai'): ?>
                                            <span class="badge badge-success">Selesai</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Batal</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">Belum ada transaksi.</td></tr>
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