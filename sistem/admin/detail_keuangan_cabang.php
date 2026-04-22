<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { 
    header("Location: ../auth/login_system.php"); 
    exit; 
}

$branch_id = $_GET['id'] ?? 0;
$filter_type = $_GET['filter'] ?? 'bulan';
$tgl_custom = $_GET['tgl_custom'] ?? date('Y-m-d');
$bulan_custom = $_GET['bulan_custom'] ?? date('Y-m');

$stmtCabang = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
$stmtCabang->execute([$branch_id]);
$cabang = $stmtCabang->fetch();
if (!$cabang) die("Cabang tidak ditemukan.");

$where = "ka.branch_id = ?";
$params = [$branch_id];
if ($filter_type == 'hari') {
    $where .= " AND ka.tanggal = ?";
    $params[] = $tgl_custom;
} else {
    $where .= " AND DATE_FORMAT(ka.tanggal, '%Y-%m') = ?";
    $params[] = $bulan_custom;
}

$sqlShift = "SELECT ka.*, u.nama_lengkap as nama_kasir,
            (SELECT COUNT(*) FROM transactions t WHERE t.kasir_id = ka.kasir_id AND t.branch_id = ka.branch_id AND t.created_at BETWEEN ka.waktu_masuk AND COALESCE(ka.waktu_keluar, NOW())) as real_trx,
            (SELECT SUM(t.omset_cabang) FROM transactions t WHERE t.kasir_id = ka.kasir_id AND t.branch_id = ka.branch_id AND t.created_at BETWEEN ka.waktu_masuk AND COALESCE(ka.waktu_keluar, NOW())) as real_omset
             FROM kasir_attendance ka JOIN users u ON ka.kasir_id = u.id WHERE $where ORDER BY ka.waktu_masuk DESC";
$stmtShift = $pdo->prepare($sqlShift);
$stmtShift->execute($params);
$shifts = $stmtShift->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail - <?= htmlspecialchars($cabang['nama_cabang']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container-layout">
        <div class="sidebar">
            <div class="sidebar-header"><h2>⚡ ADMIN PANEL</h2></div>
            <div class="sidebar-menu">
                <a href="dashboard_admin.php" class="menu-item"><i>🏠</i> Dashboard</a>
                <a href="data_keuangan.php" class="menu-item active"><i>💰</i> Data Keuangan</a>
                <a href="../auth/logout_system.php" class="menu-item" style="color:#CC1A1A;"><i>🚪</i> Logout</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div>
                    <h1>Detail Cabang</h1>
                    <span>Unit: <?= htmlspecialchars($cabang['nama_cabang']) ?></span>
                </div>
                <div class="topbar-right">
                    <button class="theme-toggle no-print" onclick="toggleTheme()" id="theme-btn"><i class="fas fa-moon"></i> Dark</button>
                    <a href="data_keuangan.php" class="btn btn-secondary no-print">← Kembali</a>
                </div>
            </div>

            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Kasir</th>
                                <th>Buka/Tutup</th>
                                <th class="text-right">Omset</th>
                                <th class="text-right">Pengeluaran</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($shifts as $s): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($s['tanggal'])) ?></td>
                                <td><strong><?= htmlspecialchars($s['nama_kasir']) ?></strong></td>
                                <td><?= date('H:i', strtotime($s['waktu_masuk'])) ?> - <?= $s['waktu_keluar'] ? date('H:i', strtotime($s['waktu_keluar'])) : 'Live' ?></td>
                                <td class="text-right">Rp <?= number_format($s['real_omset'], 0, ',', '.') ?></td>
                                <td class="text-right" style="color:var(--accent-red);">- Rp <?= number_format($s['total_pengeluaran'], 0, ',', '.') ?></td>
                                <td><?= strtoupper($s['status']) ?></td>
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
            const body = document.documentElement;
            const newTheme = body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            document.getElementById('theme-btn').innerHTML = newTheme === 'dark' ? '<i class="fas fa-sun"></i> Light' : '<i class="fas fa-moon"></i> Dark';
        }
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.getElementById('theme-btn').innerHTML = savedTheme === 'dark' ? '<i class="fas fa-sun"></i> Light' : '<i class="fas fa-moon"></i> Dark';
        });
    </script>
</body>
</html>