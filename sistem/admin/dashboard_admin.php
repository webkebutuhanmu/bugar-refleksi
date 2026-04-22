<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { 
    header("Location: ../auth/login_system.php"); 
    exit; 
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Logic Hari Bisnis
$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulaiBisnis = $setting['jam_mulai_hari'] ?? '08:00:00';
$sekarang = new DateTime();
$jamSekarang = $sekarang->format('H:i:s');

if ($jamSekarang < $jamMulaiBisnis) {
    $tglBisnis = date('Y-m-d', strtotime('-1 day'));
} else {
    $tglBisnis = date('Y-m-d');
}

$start_periode = "$tglBisnis $jamMulaiBisnis"; 
$end_periode   = date('Y-m-d H:i:s', strtotime("$start_periode +1 day"));
$labelPeriode = date('d M H:i', strtotime($start_periode)) . " s.d " . date('d M H:i', strtotime($end_periode));

// Summary Query
$sqlSummary = "SELECT 
    COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN total_bayar ELSE 0 END), 0) as total_bruto,
    COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN omset_cabang ELSE 0 END), 0) as total_netto_kotor,
    COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) as total_trx,
    COALESCE(SUM(CASE WHEN commission_status = 'pending' THEN omset_terapis ELSE 0 END), 0) as total_hutang_komisi
    FROM transactions";

$stmtSum = $pdo->prepare($sqlSummary);
$stmtSum->execute([$start_periode, $end_periode, $start_periode, $end_periode, $start_periode, $end_periode]);
$summary = $stmtSum->fetch();

$sqlExp = "SELECT COALESCE(SUM(jumlah), 0) FROM shift_expenses WHERE created_at >= ? AND created_at < ?";
$stmtExp = $pdo->prepare($sqlExp);
$stmtExp->execute([$start_periode, $end_periode]);
$totalPengeluaran = $stmtExp->fetchColumn();

$totalTrx = $summary['total_trx'];
$totalBruto = $summary['total_bruto'];
$nettoKotor = $summary['total_netto_kotor'];
$totalHutangKomisi = $summary['total_hutang_komisi'];
$totalNettoBersih = $nettoKotor - $totalPengeluaran;

// Detail Cabang
$branches = $pdo->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
$detailCabang = [];
foreach($branches as $b) {
    $stmtC = $pdo->prepare("SELECT COUNT(id) as jml, COALESCE(SUM(total_bayar),0) as bruto, COALESCE(SUM(omset_cabang),0) as netto FROM transactions WHERE branch_id = ? AND created_at >= ? AND created_at < ?");
    $stmtC->execute([$b['id'], $start_periode, $end_periode]);
    $resC = $stmtC->fetch();
    
    $stmtE = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM shift_expenses WHERE branch_id = ? AND created_at >= ? AND created_at < ?");
    $stmtE->execute([$b['id'], $start_periode, $end_periode]);
    
    $detailCabang[] = [
        'nama_cabang' => $b['nama_cabang'],
        'netto_kotor' => $resC['netto'],
        'pengeluaran' => $stmtE->fetchColumn(),
        'profit_bersih' => $resC['netto'] - $stmtE->fetchColumn()
    ];
}

$listPengeluaran = $pdo->query("SELECT se.*, b.nama_cabang, u.nama_lengkap as nama_kasir FROM shift_expenses se JOIN branches b ON se.branch_id = b.id JOIN users u ON se.kasir_id = u.id ORDER BY se.created_at DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Bugar Refleksi</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container-layout">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>⚡ ADMIN PANEL</h2>
                <small>Bugar Refleksi System</small>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard_admin.php" class="menu-item active"><i>🏠</i> Dashboard</a>
                <a href="data_keuangan.php" class="menu-item"><i>💰</i> Data Keuangan</a>
                <a href="omset_terapis.php" class="menu-item"><i>💆</i> Gaji Terapis</a>
                <a href="../auth/logout_system.php" class="menu-item" style="color: #CC1A1A; margin-top: 50px;"><i>🚪</i> Logout</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div>
                    <h1>Dashboard Live</h1>
                    <span class="badge badge-warning">⏱ <?= $labelPeriode ?></span>
                </div>
                <div class="topbar-right">
                    <button class="theme-toggle no-print" onclick="toggleTheme()" id="theme-btn">
                        <i class="fas fa-moon"></i> Dark
                    </button>
                    <div style="text-align: right;">
                        <span style="display:block; font-weight: bold; color: var(--text-dark);">Halo, <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin') ?></span>
                        <span class="badge badge-success">Administrator</span>
                    </div>
                </div>
            </div>

            <div class="card-grid">
                <div class="stat-card blue">
                    <h3>Omset Bruto</h3>
                    <div class="value">Rp <?= number_format($totalBruto, 0, ',', '.') ?></div>
                    <small><?= $totalTrx ?> Transaksi Hari Ini</small>
                </div>
                <div class="stat-card red">
                    <h3>Pengeluaran</h3>
                    <div class="value">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
                    <small>Biaya Operasional</small>
                </div>
                <div class="stat-card purple">
                    <h3>Hutang Komisi</h3>
                    <div class="value">Rp <?= number_format($totalHutangKomisi, 0, ',', '.') ?></div>
                    <small>Akumulasi Pending</small>
                </div>
                <div class="stat-card green">
                    <h3>Profit Bersih</h3>
                    <div class="value">Rp <?= number_format($totalNettoBersih, 0, ',', '.') ?></div>
                    <small>Estimasi Pendapatan</small>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <div class="card-header">Profitabilitas Cabang</div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cabang</th>
                                    <th class="text-right">Netto</th>
                                    <th class="text-right">Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($detailCabang as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['nama_cabang']) ?></strong></td>
                                    <td class="text-right">Rp <?= number_format($c['netto_kotor'], 0, ',', '.') ?></td>
                                    <td class="text-right" style="color: var(--accent-green); font-weight:bold;">Rp <?= number_format($c['profit_bersih'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Pengeluaran Terakhir</div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Unit</th>
                                    <th>Keterangan</th>
                                    <th class="text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($listPengeluaran as $lp): ?>
                                <tr>
                                    <td><?= htmlspecialchars($lp['nama_cabang']) ?></td>
                                    <td><?= htmlspecialchars($lp['keterangan']) ?></td>
                                    <td class="text-right" style="color: var(--accent-red); font-weight:bold;">Rp <?= number_format($lp['jumlah'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const body = document.documentElement;
            const isDark = body.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeBtn(newTheme);
        }

        function updateThemeBtn(theme) {
            const btn = document.getElementById('theme-btn');
            if(btn) btn.innerHTML = theme === 'dark' ? '<i class="fas fa-sun"></i> Light' : '<i class="fas fa-moon"></i> Dark';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            updateThemeBtn(savedTheme);
        });
    </script>
</body>
</html>