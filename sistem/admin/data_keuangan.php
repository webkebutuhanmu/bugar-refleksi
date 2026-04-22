<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { 
    header("Location: ../auth/login_system.php"); 
    exit; 
}

$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulai = $setting['jam_mulai_hari'] ?? '08:00:00';

$filter_type = $_GET['filter'] ?? 'hari';
$branch_id = $_GET['branch'] ?? 'all';
$tgl_custom = $_GET['tgl_custom'] ?? date('Y-m-d');
$bulan_custom = $_GET['bulan_custom'] ?? date('Y-m');
$tahun_custom = $_GET['tahun_custom'] ?? date('Y');
$tgl_start_range = $_GET['tgl_start'] ?? date('Y-m-01');
$tgl_end_range   = $_GET['tgl_end'] ?? date('Y-m-d');

if ($filter_type == 'hari' && !isset($_GET['tgl_custom'])) {
    if (date('H:i:s') < $jamMulai) { $tgl_custom = date('Y-m-d', strtotime('-1 day')); }
}

$start_date = ""; $end_date = ""; $periode_text = "";
if ($filter_type == 'hari') {
    $start_date = "$tgl_custom $jamMulai";
    $end_date   = date('Y-m-d H:i:s', strtotime("$start_date +1 day"));
    $periode_text = date('d F Y', strtotime($tgl_custom));
} elseif ($filter_type == 'bulan') {
    $start_date = "$bulan_custom-01 $jamMulai";
    $end_date   = date('Y-m-d H:i:s', strtotime("$start_date +1 month"));
    $periode_text = date('F Y', strtotime($start_date));
} elseif ($filter_type == 'tahun') { 
    $start_date = "$tahun_custom-01-01 $jamMulai";
    $end_date   = date('Y-m-d H:i:s', strtotime("$start_date +1 year"));
    $periode_text = 'Tahun ' . $tahun_custom;
} elseif ($filter_type == 'range') {
    $start_date = "$tgl_start_range $jamMulai";
    $end_date   = date('Y-m-d H:i:s', strtotime("$tgl_end_range +1 day $jamMulai")); 
    $periode_text = date('d M Y', strtotime($tgl_start_range)) . " s.d " . date('d M Y', strtotime($tgl_end_range));
}

$where_range = "AND created_at >= ? AND created_at < ?";
$params_trx = [$start_date, $end_date];
$params_exp = [$start_date, $end_date];
$where_branch_trx = "";
if ($branch_id != 'all') {
    $where_branch_trx = "AND t.branch_id = ?";
    $params_trx[] = $branch_id;
    $params_exp[] = $branch_id;
}

$sqlSummary = "SELECT SUM(t.total_bayar) as total_bruto, SUM(t.omset_cabang) as total_netto_kotor, SUM(CASE WHEN t.commission_status = 'pending' THEN t.omset_terapis ELSE 0 END) as total_hutang_komisi, COUNT(t.id) as total_trx FROM transactions t WHERE 1=1 $where_range $where_branch_trx";
$stmtSum = $pdo->prepare($sqlSummary);
$stmtSum->execute($params_trx);
$summary = $stmtSum->fetch();

$sqlExp = "SELECT COALESCE(SUM(se.jumlah), 0) FROM shift_expenses se WHERE 1=1 $where_range " . ($branch_id != 'all' ? "AND se.branch_id = ?" : "");
$stmtExp = $pdo->prepare($sqlExp);
$stmtExp->execute($params_exp);
$totalPengeluaran = $stmtExp->fetchColumn();

$totalBruto = $summary['total_bruto'] ?? 0;
$nettoKotor = $summary['total_netto_kotor'] ?? 0; 
$profitBersih = $nettoKotor - $totalPengeluaran;

$branches = $pdo->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan - Bugar Refleksi</title>
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
                <a href="dashboard_admin.php" class="menu-item"><i>🏠</i> Dashboard</a>
                <a href="data_keuangan.php" class="menu-item active"><i>💰</i> Data Keuangan</a>
                <a href="omset_terapis.php" class="menu-item"><i>💆</i> Gaji Terapis</a>
                <a href="../auth/logout_system.php" class="menu-item" style="color: #CC1A1A; margin-top: 50px;"><i>🚪</i> Logout</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div>
                    <h1>Laporan Keuangan</h1>
                    <span>Periode: <strong><?= $periode_text ?></strong></span>
                </div>
                <div class="topbar-right">
                    <button class="theme-toggle no-print" onclick="toggleTheme()" id="theme-btn">
                        <i class="fas fa-moon"></i> Dark
                    </button>
                    <button onclick="window.print()" class="btn btn-secondary no-print">🖨️ Cetak</button>
                </div>
            </div>

            <div class="card filter-box no-print" style="margin-bottom: 20px;">
                <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div>
                        <label>Filter:</label>
                        <select name="filter" class="form-control" onchange="toggleDateInput(this.value)">
                            <option value="hari" <?= $filter_type == 'hari' ? 'selected' : '' ?>>Harian</option>
                            <option value="bulan" <?= $filter_type == 'bulan' ? 'selected' : '' ?>>Bulanan</option>
                            <option value="range" <?= $filter_type == 'range' ? 'selected' : '' ?>>Rentang</option>
                        </select>
                    </div>
                    <div id="input-hari" style="display: <?= $filter_type == 'hari' ? 'block' : 'none' ?>;">
                        <input type="date" name="tgl_custom" value="<?= $tgl_custom ?>" class="form-control">
                    </div>
                    <div>
                        <select name="branch" class="form-control">
                            <option value="all">Semua Cabang</option>
                            <?php foreach($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nama_cabang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                </form>
            </div>

            <div class="card-grid">
                <div class="stat-card blue"><h3>Total Bruto</h3><div class="value">Rp <?= number_format($totalBruto, 0, ',', '.') ?></div></div>
                <div class="stat-card red"><h3>Pengeluaran</h3><div class="value">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div></div>
                <div class="stat-card green"><h3>Profit Bersih</h3><div class="value">Rp <?= number_format($profitBersih, 0, ',', '.') ?></div></div>
            </div>

            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Cabang</th>
                                <th class="text-right">Trx</th>
                                <th class="text-right">Bruto</th>
                                <th class="text-right">Netto</th>
                                <th class="text-right">Profit</th>
                                <th class="text-center no-print">Aksi</th>
                            </tr>
                        </thead>
                       <tbody>
                            <?php 
                            foreach($branches as $b) {
                                if ($branch_id != 'all' && $branch_id != $b['id']) continue;
                                
                                // 1. UBAH QUERY DI SINI: Tambahkan COALESCE agar nilai NULL jadi 0
                                $stC = $pdo->prepare("SELECT COUNT(id) as jml, COALESCE(SUM(total_bayar), 0) as b, COALESCE(SUM(omset_cabang), 0) as n FROM transactions WHERE branch_id = ? AND created_at >= ? AND created_at < ?");
                                $stC->execute([$b['id'], $start_date, $end_date]);
                                $r = $stC->fetch();
                                
                                // Tambahkan COALESCE juga di pengeluaran
                                $stE = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) FROM shift_expenses WHERE branch_id = ? AND created_at >= ? AND created_at < ?");
                                $stE->execute([$b['id'], $start_date, $end_date]);
                                $exp = $stE->fetchColumn();
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($b['nama_cabang']) ?></strong></td>
                                <td class="text-right"><?= number_format($r['jml'] ?? 0) ?></td>
                                <td class="text-right">Rp <?= number_format($r['b'] ?? 0, 0, ',', '.') ?></td>
                                <td class="text-right">Rp <?= number_format($r['n'] ?? 0, 0, ',', '.') ?></td>
                                <td class="text-right" style="color: var(--accent-green); font-weight:bold;">Rp <?= number_format(($r['n'] ?? 0) - ($exp ?? 0), 0, ',', '.') ?></td>
                                <td class="text-center no-print">
                                    <a href="detail_keuangan_cabang.php?id=<?= $b['id'] ?>&filter=<?= $filter_type ?>&tgl_custom=<?= $tgl_custom ?>" class="btn btn-sm btn-secondary">👁 Detail</a>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
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
        function toggleDateInput(type) {
            document.getElementById('input-hari').style.display = (type === 'hari') ? 'block' : 'none';
        }
    </script>
</body>
</html>