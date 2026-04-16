<?php
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');
if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

// --- DEFAULT FILTER 7 HARI TERAKHIR ---
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d', strtotime('-6 days'));
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

// Total Omset
$sqlOmset = "SELECT SUM(total_bayar) FROM transactions WHERE tanggal_transaksi BETWEEN ? AND ?";
$stmtOmset = $pdo->prepare($sqlOmset);
$stmtOmset->execute([$tgl_awal, $tgl_akhir]);
$totalOmset = $stmtOmset->fetchColumn() ?? 0;

// Total Transaksi
$sqlTrx = "SELECT COUNT(*) FROM transactions WHERE tanggal_transaksi BETWEEN ? AND ?";
$stmtTrx = $pdo->prepare($sqlTrx);
$stmtTrx->execute([$tgl_awal, $tgl_akhir]);
$totalTransaksi = $stmtTrx->fetchColumn();

// Total Cabang
$totalCabang = $pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn();

// Total Terapis
$totalTerapis = $pdo->query("SELECT COUNT(*) FROM users WHERE role='terapis'")->fetchColumn();

// Omset Per Cabang
$sqlCabang = "SELECT b.nama_cabang, COALESCE(SUM(t.total_bayar), 0) as omset
              FROM branches b
              LEFT JOIN transactions t ON b.id = t.branch_id AND (t.tanggal_transaksi BETWEEN ? AND ?)
              GROUP BY b.id, b.nama_cabang";
$stmtCabang = $pdo->prepare($sqlCabang);
$stmtCabang->execute([$tgl_awal, $tgl_akhir]);
$dataCabang = $stmtCabang->fetchAll();

// Top 5 Terapis PER CABANG
$allBranches = $pdo->query("SELECT id, nama_cabang FROM branches ORDER BY nama_cabang ASC")->fetchAll();

$topTerapisPerCabang = [];
foreach ($allBranches as $branch) {
    $sqlTop = "SELECT 
                u.nama_lengkap,
                ? as nama_cabang,
                COALESCE(COUNT(t.id), 0) as total_transaksi,
                COALESCE(SUM(t.omset_terapis), 0) as komisi_terapis
                FROM users u
                LEFT JOIN transactions t ON u.id = t.terapis_id 
                    AND t.branch_id = ? 
                    AND (t.tanggal_transaksi BETWEEN ? AND ?)
                WHERE u.role = 'terapis' 
                AND (u.home_branch_id = ? OR u.branch_id = ?)
                GROUP BY u.id, u.nama_lengkap
                ORDER BY komisi_terapis DESC 
                LIMIT 5";
    $stmtTop = $pdo->prepare($sqlTop);
    $stmtTop->execute([$branch['nama_cabang'], $branch['id'], $tgl_awal, $tgl_akhir, $branch['id'], $branch['id']]);
    $terapisData = $stmtTop->fetchAll();
    
    if (count($terapisData) > 0) {
        $topTerapisPerCabang[$branch['nama_cabang']] = $terapisData;
    }
}

// =====================================================
// GRAFIK OMSET & TRANSAKSI DINAMIS (Harian/Mingguan/Bulanan)
// =====================================================
$grafikDates = [];
$start_dt = new DateTime($tgl_awal);
$end_dt = new DateTime($tgl_akhir);
$diff_days = $start_dt->diff($end_dt)->days;

if ($diff_days <= 14) {
    // Harian (<= 14 hari)
    $sqlGroup = "DATE(t.tanggal_transaksi)";
    $interval = new DateInterval('P1D');
    $end_dt_clone = clone $end_dt;
    $end_dt_clone->modify('+1 day'); // include end date
    $period = new DatePeriod($start_dt, $interval, $end_dt_clone);
    foreach ($period as $dt) {
        $grafikDates[$dt->format('Y-m-d')] = $dt->format('d/m');
    }
    $label_group = "Harian";
} elseif ($diff_days <= 90) {
    // Mingguan (15 - 90 hari) -> Max 3 bulan
    $sqlGroup = "YEARWEEK(t.tanggal_transaksi, 3)"; // Mode 3 = ISO week (Senin)
    $curr = clone $start_dt;
    $end_dt_clone = clone $end_dt;
    while ($curr <= $end_dt_clone) {
        $key = $curr->format('oW'); // Format MySQL YEARWEEK
        if (!isset($grafikDates[$key])) {
            $wStart = clone $curr; 
            $wStart->setISODate((int)$curr->format('o'), (int)$curr->format('W'));
            $wEnd = clone $wStart; 
            $wEnd->modify('+6 days');
            $grafikDates[$key] = $wStart->format('d/m') . ' - ' . $wEnd->format('d/m');
        }
        $curr->modify('+1 day');
    }
    $label_group = "Mingguan";
} else {
    // Bulanan (> 90 hari)
    $sqlGroup = "DATE_FORMAT(t.tanggal_transaksi, '%Y-%m')";
    $curr = new DateTime($start_dt->format('Y-m-01'));
    $end_dt_clone = clone $end_dt;
    while ($curr <= $end_dt_clone) {
        $key = $curr->format('Y-m');
        $grafikDates[$key] = $curr->format('M Y'); // Misal: Jan 2026
        $curr->modify('+1 month');
    }
    $label_group = "Bulanan";
}

// Mengambil Data Omset sesuai Grup (Harian/Mingguan/Bulanan)
$sqlOmsetPerCabang = "SELECT t.branch_id, b.nama_cabang,
                       $sqlGroup as tanggal,
                       SUM(t.total_bayar) as omset
                       FROM transactions t
                       JOIN branches b ON t.branch_id = b.id
                       WHERE t.tanggal_transaksi BETWEEN ? AND ?
                       GROUP BY t.branch_id, b.nama_cabang, $sqlGroup
                       ORDER BY b.nama_cabang ASC, tanggal ASC";
$stmtOmsetCabang = $pdo->prepare($sqlOmsetPerCabang);
$stmtOmsetCabang->execute([$tgl_awal, $tgl_akhir]);
$rawOmsetCabang = $stmtOmsetCabang->fetchAll();

$omsetByCabang = [];
foreach ($rawOmsetCabang as $row) {
    $omsetByCabang[$row['nama_cabang']][$row['tanggal']] = floatval($row['omset']);
}

// Mengambil Data Transaksi sesuai Grup (Harian/Mingguan/Bulanan)
$sqlTrxPerCabang = "SELECT t.branch_id, b.nama_cabang,
                     $sqlGroup as tanggal,
                     COUNT(*) as total
                     FROM transactions t
                     JOIN branches b ON t.branch_id = b.id
                     WHERE t.tanggal_transaksi BETWEEN ? AND ?
                     GROUP BY t.branch_id, b.nama_cabang, $sqlGroup
                     ORDER BY b.nama_cabang ASC, tanggal ASC";
$stmtTrxCabang = $pdo->prepare($sqlTrxPerCabang);
$stmtTrxCabang->execute([$tgl_awal, $tgl_akhir]);
$rawTrxCabang = $stmtTrxCabang->fetchAll();

$trxByCabang = [];
foreach ($rawTrxCabang as $row) {
    $trxByCabang[$row['nama_cabang']][$row['tanggal']] = intval($row['total']);
}

// Mapping Final untuk Chart JS
$grafikOmsetPerCabang = [];
$grafikOmsetTotal = [];
$grafikTrxPerCabang = [];
$grafikTrxTotal = [];

foreach ($grafikDates as $key => $label) {
    $totalHariOmset = 0;
    $totalHariTrx = 0;
    foreach ($allBranches as $br) {
        $nama = $br['nama_cabang'];
        
        $valO = $omsetByCabang[$nama][$key] ?? 0;
        $grafikOmsetPerCabang[$nama][] = $valO;
        $totalHariOmset += $valO;
        
        $valT = $trxByCabang[$nama][$key] ?? 0;
        $grafikTrxPerCabang[$nama][] = $valT;
        $totalHariTrx += $valT;
    }
    $grafikOmsetTotal[] = $totalHariOmset;
    $grafikTrxTotal[] = $totalHariTrx;
}

$grafikLabels = array_values($grafikDates);


// CABANG YANG SEDANG BUKA
$sqlCabangBuka = "SELECT b.id, b.nama_cabang,
                  ka.kasir_id, u.nama_lengkap as nama_kasir, ka.waktu_masuk,
                  (SELECT COUNT(*) FROM transactions t WHERE t.branch_id = b.id AND t.status = 'proses') as customer_aktif
                  FROM branches b
                  JOIN kasir_attendance ka ON b.id = ka.branch_id AND ka.status = 'aktif'
                  JOIN users u ON ka.kasir_id = u.id
                  ORDER BY b.nama_cabang ASC";
$cabangBuka = $pdo->query($sqlCabangBuka)->fetchAll();

// CUSTOMER YANG SEDANG DILAYANI
$sqlCustomerAktif = "SELECT t.*, 
                     b.nama_cabang, 
                     bed.nomor_bed, 
                     ut.nama_lengkap as nama_terapis, 
                     p.nama_paket,
                     'datang' as tipe_transaksi,
                     'rumah' as tipe_lokasi,
                     '' as alamat_panggilan
                     FROM transactions t
                     JOIN branches b ON t.branch_id = b.id
                     LEFT JOIN beds bed ON t.bed_id = bed.id
                     JOIN users ut ON t.terapis_id = ut.id
                     JOIN packages p ON t.package_id = p.id
                     WHERE t.status IN ('proses', 'menunggu_pembayaran')
                     ORDER BY b.nama_cabang ASC, t.waktu_selesai ASC";
$customerAktif = $pdo->query($sqlCustomerAktif)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Owner - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container { position: relative; height: 300px; margin: 20px 0; }
        .live-indicator {
            display: inline-block; width: 10px; height: 10px; background: #e74c3c;
            border-radius: 50%; animation: pulse 2s infinite; margin-right: 8px;
        }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        
        .customer-table-wrapper { max-height: 400px; overflow-y: auto; margin-top: 15px; }
        .branch-group { background: var(--bg-input); padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .branch-group h4 { color: var(--text-dark); margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color); font-family: 'DM Sans', sans-serif; font-size:14px; }
        
        .chart-legend-wrap { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }
        .chart-legend-btn { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; color: white; cursor: pointer; user-select: none; }
        .chart-legend-btn.hidden { opacity: 0.4; }
        .chart-legend-dot { width: 10px; height: 10px; border-radius: 50%; background: white; }
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
                <a href="dashboard_owner.php" class="menu-item active">Dashboard</a>
                <a href="data_cabang.php" class="menu-item">Data Cabang</a>
                <a href="data_leader.php" class="menu-item">Data Leader</a>
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
                    <h1>Dashboard</h1>
                </div>
                <div class="topbar-right">
                    <span style="color: var(--text-muted); font-size:14px;">Halo, <strong style="color:var(--text-dark);"><?= htmlspecialchars($_SESSION['nama']) ?></strong></span>
                    <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
                </div>
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap; padding: 15px 20px;">
                    <strong style="color:var(--text-dark); font-size:14px;">Periode Data:</strong>
                    <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" class="form-control" style="width: auto; padding:8px;" required>
                        <span style="color:var(--text-muted);">s/d</span>
                        <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" class="form-control" style="width: auto; padding:8px;" required>
                        <button type="submit" class="btn btn-primary">Terapkan</button>
                        <a href="dashboard_owner.php" class="btn btn-secondary">Reset 7 Hari</a>
                    </form>
                </div>
            </div>

            <div class="card-grid">
                <div class="stat-card">
                    <h3>Total Omset</h3>
                    <div class="value">Rp <?= number_format($totalOmset, 0, ',', '.') ?></div>
                    <small>Periode Terpilih</small>
                </div>
                <div class="stat-card">
                    <h3>Total Transaksi</h3>
                    <div class="value"><?= number_format($totalTransaksi) ?></div>
                    <small>Transaksi Selesai</small>
                </div>
                <div class="stat-card">
                    <h3>Total Cabang</h3>
                    <div class="value"><?= $totalCabang ?></div>
                    <small>Cabang Terdaftar</small>
                </div>
                <div class="stat-card">
                    <h3>Total Terapis</h3>
                    <div class="value"><?= $totalTerapis ?></div>
                    <small>Terapis Aktif</small>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <div class="card-header">Grafik Omset <?= $label_group ?> (<?= date('d/m', strtotime($tgl_awal)) ?> - <?= date('d/m/Y', strtotime($tgl_akhir)) ?>)</div>
                    <div style="padding: 20px;">
                        <div id="legendOmset" class="chart-legend-wrap"></div>
                        <div class="chart-container"><canvas id="chartOmset"></canvas></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Grafik Transaksi <?= $label_group ?> (<?= date('d/m', strtotime($tgl_awal)) ?> - <?= date('d/m/Y', strtotime($tgl_akhir)) ?>)</div>
                    <div style="padding: 20px;">
                        <div id="legendTrx" class="chart-legend-wrap"></div>
                        <div class="chart-container"><canvas id="chartTransaksi"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <span class="live-indicator"></span> Cabang Sedang Buka (<?= count($cabangBuka) ?>)
                </div>
                <?php if(count($cabangBuka) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cabang</th>
                                    <th>Kasir Aktif</th>
                                    <th>Waktu Buka</th>
                                    <th>Customer Sedang Dilayani</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cabangBuka as $cb): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($cb['nama_cabang']) ?></strong></td>
                                    <td><?= htmlspecialchars($cb['nama_kasir']) ?></td>
                                    <td><?= date('H:i', strtotime($cb['waktu_masuk'])) ?></td>
                                    <td>
                                        <?php if($cb['customer_aktif'] > 0): ?>
                                            <span class="badge badge-success"><?= $cb['customer_aktif'] ?> Customer</span>
                                        <?php else: ?>
                                            <span class="badge" style="background:var(--bg-input); color:var(--text-muted);">Tidak ada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                <p style="text-align: center; padding: 40px; color: var(--text-muted);">Tidak ada cabang yang sedang buka saat ini</p>
                <?php endif; ?>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <span class="live-indicator"></span> Customer Sedang Dilayani
                </div>
                <?php if(count($customerAktif) > 0): ?>
                    <div class="customer-table-wrapper" style="padding: 20px;">
                        <?php 
                        $groupedCustomers = [];
                        foreach($customerAktif as $c) { $groupedCustomers[$c['nama_cabang']][] = $c; }
                        ?>
                        <?php foreach($groupedCustomers as $cabang => $customers): ?>
                        <div class="branch-group">
                            <h4><?= htmlspecialchars($cabang) ?> (<?= count($customers) ?> Customer)</h4>
                            <div class="table-container">
                                <table style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Bed</th>
                                            <th>Nama Customer</th>
                                            <th>Paket</th>
                                            <th>Terapis</th>
                                            <th>Selesai</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($customers as $c): 
                                            $now    = new DateTime();
                                            $finish = new DateTime($c['waktu_selesai']);
                                            $sudahLewat   = ($now > $finish);
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($c['nomor_bed'] ?? '-') ?></strong></td>
                                            <td><?= htmlspecialchars($c['nama_pelanggan']) ?></td>
                                            <td><?= htmlspecialchars($c['nama_paket']) ?></td>
                                            <td><?= htmlspecialchars($c['nama_terapis']) ?></td>
                                            <td><?= date('H:i', strtotime($c['waktu_selesai'])) ?></td>
                                            <td>
                                                <?php if ($c['status'] === 'menunggu_pembayaran'): ?>
                                                    <span class="badge badge-warning">Menunggu Pembayaran</span>
                                                <?php elseif ($sudahLewat): ?>
                                                    <span class="badge badge-danger">Seharusnya Selesai</span>
                                                <?php else: ?>
                                                    <?php
                                                    $diff = $now->diff($finish);
                                                    $sisaMenit = ($diff->h * 60) + $diff->i;
                                                    ?>
                                                    <span class="badge badge-success">
                                                        <?= $diff->format('%H:%I') ?> lagi
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                <p style="text-align: center; padding: 40px; color: var(--text-muted);">Tidak ada customer yang sedang dilayani saat ini</p>
                <?php endif; ?>
            </div>

            <div class="grid-2">
                <div class="card">
                    <div class="card-header">Omset Per Cabang</div>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>Nama Cabang</th><th>Total Omset</th></tr></thead>
                            <tbody>
                                <?php foreach($dataCabang as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['nama_cabang']) ?></td>
                                    <td><strong style="color:var(--accent-red2);">Rp <?= number_format($c['omset'], 0, ',', '.') ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Top 5 Terapis Terbaik (Periode Ini)</div>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>Nama Terapis</th><th>Cabang</th><th>Total Trx</th><th>Komisi Terapis</th></tr></thead>
                            <tbody>
                                <?php foreach($topTerapisPerCabang as $namaCabang => $terapis): ?>
                                    <tr style="background:var(--bg-input);">
                                        <td colspan="4" style="font-weight:bold; font-size:12px;"><?= htmlspecialchars($namaCabang) ?></td>
                                    </tr>
                                    <?php foreach($terapis as $index => $t): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($t['nama_lengkap']) ?></td>
                                        <td><small style="color: var(--text-muted);"><?= htmlspecialchars($t['nama_cabang']) ?></small></td>
                                        <td><strong><?= $t['total_transaksi'] ?>x</strong></td>
                                        <td><strong style="color: var(--accent-yellow2);">Rp <?= number_format($t['komisi_terapis'], 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                <?php if(count($topTerapisPerCabang) == 0): ?>
                                <tr><td colspan="4" style="text-align: center; padding: 30px; color: var(--text-muted);">Belum ada data terapis di periode ini</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle Script
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

        // Submenu Toggle
        function toggleSubmenu(el) {
            el.classList.toggle('active');
            const items = el.nextElementSibling;
            items.classList.toggle('open');
        }

        // Mobile Menu
        function toggleMobileMenu() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Auto reload 30 detik
        setTimeout(() => location.reload(), 30000);

        // CHART SETUP
        const BRANCH_COLORS = [
            { border: '#CC1A1A', bg: 'rgba(204,26,26,0.1)' },
            { border: '#FFD600', bg: 'rgba(255,214,0,0.1)' },
            { border: '#27ae60', bg: 'rgba(39,174,96,0.1)' },
            { border: '#2980b9', bg: 'rgba(41,128,185,0.1)' },
            { border: '#8e44ad', bg: 'rgba(142,68,173,0.1)' }
        ];
        const TOTAL_COLOR = { border: '#111111', bg: 'rgba(17,17,17,0.05)' };

        const GRAFIK_LABELS = <?= json_encode($grafikLabels) ?>;
        const BRANCHES      = <?= json_encode(array_column($allBranches, 'nama_cabang')) ?>;
        const OMSET_PER_CABANG = <?= json_encode($grafikOmsetPerCabang) ?>;
        const OMSET_TOTAL      = <?= json_encode($grafikOmsetTotal) ?>;
        const TRX_PER_CABANG   = <?= json_encode($grafikTrxPerCabang) ?>;
        const TRX_TOTAL        = <?= json_encode($grafikTrxTotal) ?>;

        function buildDatasets(perCabang, total) {
            const datasets = [];
            BRANCHES.forEach((nama, i) => {
                const col = BRANCH_COLORS[i % BRANCH_COLORS.length];
                datasets.push({
                    label: nama, data: perCabang[nama] || Array(GRAFIK_LABELS.length).fill(0),
                    borderColor: col.border, backgroundColor: col.bg, borderWidth: 2, tension: 0.4, fill: false,
                    _colorHex: col.border
                });
            });
            // Theme dependent total color
            let isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            datasets.push({
                label: 'TOTAL', data: total,
                borderColor: isDark ? '#ffffff' : TOTAL_COLOR.border, 
                backgroundColor: TOTAL_COLOR.bg,
                borderWidth: 3, borderDash: [5, 5], tension: 0.4, fill: false,
                _colorHex: isDark ? '#ffffff' : TOTAL_COLOR.border
            });
            return datasets;
        }

        function renderLegend(containerId, chartInstance) {
            const wrap = document.getElementById(containerId);
            wrap.innerHTML = '';
            chartInstance.data.datasets.forEach((ds, i) => {
                const btn = document.createElement('span');
                btn.className  = 'chart-legend-btn';
                btn.style.background = ds._colorHex;
                btn.dataset.index = i;
                btn.innerHTML = `<span class="chart-legend-dot"></span>${ds.label}`;
                btn.addEventListener('click', function() {
                    const meta = chartInstance.getDatasetMeta(i);
                    meta.hidden = !meta.hidden;
                    this.classList.toggle('hidden', meta.hidden);
                    chartInstance.update();
                });
                wrap.appendChild(btn);
            });
        }

        // Setup Charts
        const ctxOmset = document.getElementById('chartOmset').getContext('2d');
        const chartOmset = new Chart(ctxOmset, {
            type: 'line',
            data: { labels: GRAFIK_LABELS, datasets: buildDatasets(OMSET_PER_CABANG, OMSET_TOTAL) },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
        renderLegend('legendOmset', chartOmset);

        const ctxTrx = document.getElementById('chartTransaksi').getContext('2d');
        const chartTrx = new Chart(ctxTrx, {
            type: 'line',
            data: { labels: GRAFIK_LABELS, datasets: buildDatasets(TRX_PER_CABANG, TRX_TOTAL) },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
        renderLegend('legendTrx', chartTrx);
    </script>
</body>
</html>