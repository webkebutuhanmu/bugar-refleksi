<?php
// File: sistem/admin/data_keuangan.php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { 
    header("Location: ../auth/login_system.php"); 
    exit; 
}

// --- 1. AMBIL JAM OPERASIONAL ---
$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulai = $setting['jam_mulai_hari'] ?? '08:00:00';

// --- 2. FILTER LOGIC ---
$filter_type = $_GET['filter'] ?? 'hari'; // Default hari ini
$branch_id = $_GET['branch'] ?? 'all';

// Default values variables
$tgl_custom = $_GET['tgl_custom'] ?? date('Y-m-d');
$bulan_custom = $_GET['bulan_custom'] ?? date('Y-m');
$tahun_custom = $_GET['tahun_custom'] ?? date('Y');

// Default value untuk Range (Rentang Tanggal)
$tgl_start_range = $_GET['tgl_start'] ?? date('Y-m-01'); // Default awal bulan ini
$tgl_end_range   = $_GET['tgl_end'] ?? date('Y-m-d');    // Default hari ini

// Auto mundur sehari jika filter hari ini & jam sekarang < jam mulai
if ($filter_type == 'hari' && !isset($_GET['tgl_custom'])) {
    if (date('H:i:s') < $jamMulai) {
        $tgl_custom = date('Y-m-d', strtotime('-1 day'));
    }
}

// Hitung Rentang Waktu (Start - End) berdasarkan Filter & Jam Operasional
$start_date = "";
$end_date = "";
$periode_text = "";

if ($filter_type == 'hari') {
    // Contoh: 15 Feb 08:00 s.d 16 Feb 08:00
    $start_date = "$tgl_custom $jamMulai";
    $end_date   = date('Y-m-d H:i:s', strtotime("$start_date +1 day"));
    $periode_text = date('d F Y', strtotime($tgl_custom));
    
} elseif ($filter_type == 'bulan') {
    // Contoh: 1 Feb 08:00 s.d 1 Mar 08:00
    $start_date = "$bulan_custom-01 $jamMulai";
    $end_date   = date('Y-m-d H:i:s', strtotime("$start_date +1 month"));
    $periode_text = date('F Y', strtotime($start_date));
    
} elseif ($filter_type == 'tahun') { 
    // Contoh: 1 Jan 2026 08:00 s.d 1 Jan 2027 08:00
    $start_date = "$tahun_custom-01-01 $jamMulai";
    $end_date   = date('Y-m-d H:i:s', strtotime("$start_date +1 year"));
    $periode_text = 'Tahun ' . $tahun_custom;

} elseif ($filter_type == 'range') {
    // Contoh: Dari 1 Feb s.d 3 Feb
    // Start: 1 Feb 08:00
    // End:   4 Feb 08:00 (Supaya shift tgl 3 full masuk)
    $start_date = "$tgl_start_range $jamMulai";
    // Tambah 1 hari pada end date agar mencakup shift hari terakhir sampai besok paginya
    $end_date   = date('Y-m-d H:i:s', strtotime("$tgl_end_range +1 day $jamMulai")); 
    $periode_text = date('d M Y', strtotime($tgl_start_range)) . " s.d " . date('d M Y', strtotime($tgl_end_range));
}

// --- 3. SIAPKAN QUERY PARAMS ---
// Kita filter menggunakan created_at agar presisi sesuai jam
$where_range = "AND created_at >= ? AND created_at < ?";
$params_range = [$start_date, $end_date];

// Filter Cabang
$where_branch_trx = "";
$where_branch_exp = "";
$params_trx = $params_range; // Copy param range awal
$params_exp = $params_range;

if ($branch_id != 'all') {
    $where_branch_trx = "AND t.branch_id = ?";
    $where_branch_exp = "AND se.branch_id = ?";
    $params_trx[] = $branch_id;
    $params_exp[] = $branch_id;
}

// --- QUERY 1: RINGKASAN TRANSAKSI (TOTAL) ---
// Menggunakan created_at untuk filter waktu
// Total Komisi hanya menghitung yang 'pending'
$sqlSummary = "SELECT 
            SUM(t.total_bayar) as total_bruto,
            SUM(t.omset_cabang) as total_netto_kotor, 
            SUM(CASE WHEN t.commission_status = 'pending' THEN t.omset_terapis ELSE 0 END) as total_hutang_komisi,
            COUNT(t.id) as total_trx
        FROM transactions t 
        WHERE 1=1 $where_range $where_branch_trx";

$stmtSum = $pdo->prepare($sqlSummary);
$stmtSum->execute($params_trx);
$summary = $stmtSum->fetch();

// --- QUERY 2: RINGKASAN PENGELUARAN (TOTAL) ---
$sqlExp = "SELECT COALESCE(SUM(se.jumlah), 0) as total_expense 
           FROM shift_expenses se 
           WHERE 1=1 $where_range $where_branch_exp";
$stmtExp = $pdo->prepare($sqlExp);
$stmtExp->execute($params_exp);
$totalPengeluaran = $stmtExp->fetchColumn();

// --- HITUNG FINAL ---
$totalBruto = $summary['total_bruto'] ?? 0;
$nettoKotor = $summary['total_netto_kotor'] ?? 0; 
$totalHutangKomisi = $summary['total_hutang_komisi'] ?? 0;
$totalTrx = $summary['total_trx'] ?? 0;
$profitBersih = $nettoKotor - $totalPengeluaran;

// --- QUERY 3: DATA PER CABANG (LOOPING) ---
$branches = $pdo->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
$dataCabang = [];

foreach($branches as $b) {
    if ($branch_id != 'all' && $branch_id != $b['id']) continue; 
    
    // Params khusus loop ini: [Start, End, BranchID]
    $paramsLoop = [$start_date, $end_date, $b['id']];

    // A. Data Transaksi Cabang Ini
    $sqlCbgTrx = "SELECT 
                    COUNT(t.id) as jml_trx, 
                    COALESCE(SUM(t.total_bayar),0) as bruto, 
                    COALESCE(SUM(t.omset_cabang),0) as netto_kotor 
                  FROM transactions t 
                  WHERE 1=1 $where_range AND t.branch_id = ?";
    $stmtC = $pdo->prepare($sqlCbgTrx);
    $stmtC->execute($paramsLoop);
    $resTrx = $stmtC->fetch();

    // B. Data Pengeluaran Cabang Ini
    $sqlCbgExp = "SELECT COALESCE(SUM(se.jumlah),0) 
                  FROM shift_expenses se 
                  WHERE 1=1 $where_range AND se.branch_id = ?";
    $stmtE = $pdo->prepare($sqlCbgExp);
    $stmtE->execute($paramsLoop);
    $resExp = $stmtE->fetchColumn();

    $dataCabang[] = [
        'branch_id' => $b['id'],
        'nama_cabang' => $b['nama_cabang'],
        'jml_trx' => $resTrx['jml_trx'],
        'bruto' => $resTrx['bruto'],
        'netto_kotor' => $resTrx['netto_kotor'],
        'pengeluaran' => $resExp,
        'profit_bersih' => $resTrx['netto_kotor'] - $resExp
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stat-card.red { border-left: 5px solid #e74c3c; }
        @media print {
            .sidebar, .topbar-right, .filter-box, .btn-action, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #000 !important; padding: 5px !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
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
                <a href="omset_terapis.php" class="menu-item"><i>💆</i> Omset Terapis</a>
                <a href="../auth/logout_system.php" class="menu-item" style="color: #c0392b; margin-top: 50px;"><i>🚪</i> Logout</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div>
                    <h1>Laporan Keuangan & Profit</h1>
                    <span>Periode: <strong><?= $periode_text ?></strong> <small style="color:#7f8c8d;">(Shift: <?= substr($jamMulai,0,5) ?>)</small></span>
                </div>
                <div class="topbar-right">
                    <button onclick="window.print()" class="btn btn-primary" style="margin-right: 10px;">🖨️ Cetak PDF</button>
                    <button onclick="exportTableToExcel('laporanTable', 'Laporan_Keuangan_<?= date('Ymd') ?>')" class="btn btn-success">📊 Export Excel</button>
                </div>
            </div>

            <div class="card filter-box" style="margin-bottom: 20px;">
                <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div>
                        <label>Filter Waktu:</label><br>
                        <select name="filter" class="form-control" onchange="toggleDateInput(this.value)" style="padding: 8px;">
                            <option value="hari" <?= $filter_type == 'hari' ? 'selected' : '' ?>>Harian</option>
                            <option value="bulan" <?= $filter_type == 'bulan' ? 'selected' : '' ?>>Bulanan</option>
                            <option value="tahun" <?= $filter_type == 'tahun' ? 'selected' : '' ?>>Tahunan</option>
                            <option value="range" <?= $filter_type == 'range' ? 'selected' : '' ?>>Rentang Tanggal</option>
                        </select>
                    </div>
                    
                    <div id="input-hari" style="display: <?= $filter_type == 'hari' ? 'block' : 'none' ?>;">
                        <label>Pilih Tanggal:</label><br>
                        <input type="date" name="tgl_custom" value="<?= $tgl_custom ?>" class="form-control" style="padding: 8px;">
                    </div>
                    <div id="input-bulan" style="display: <?= $filter_type == 'bulan' ? 'block' : 'none' ?>;">
                        <label>Pilih Bulan:</label><br>
                        <input type="month" name="bulan_custom" value="<?= $bulan_custom ?>" class="form-control" style="padding: 8px;">
                    </div>
                    <div id="input-tahun" style="display: <?= $filter_type == 'tahun' ? 'block' : 'none' ?>;">
                        <label>Pilih Tahun:</label><br>
                        <input type="number" name="tahun_custom" value="<?= $tahun_custom ?>" min="2020" class="form-control" style="padding: 8px;">
                    </div>
                    
                    <div id="input-range" style="display: <?= $filter_type == 'range' ? 'flex' : 'none' ?>; gap: 10px;">
                        <div>
                            <label>Dari:</label><br>
                            <input type="date" name="tgl_start" value="<?= $tgl_start_range ?>" class="form-control" style="padding: 8px;">
                        </div>
                        <div>
                            <label>Sampai:</label><br>
                            <input type="date" name="tgl_end" value="<?= $tgl_end_range ?>" class="form-control" style="padding: 8px;">
                        </div>
                    </div>

                    <div>
                        <label>Cabang:</label><br>
                        <select name="branch" class="form-control" style="padding: 8px;">
                            <option value="all">Semua Cabang</option>
                            <?php foreach($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['nama_cabang']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">🔍 Tampilkan</button>
                </form>
            </div>

            <div class="card-grid" style="grid-template-columns: repeat(4, 1fr);">
                <div class="stat-card blue">
                    <h3>Total Bruto</h3>
                    <div class="value">Rp <?= number_format($totalBruto, 0, ',', '.') ?></div>
                    <small>Total Uang Masuk</small>
                </div>
                <div class="stat-card red">
                    <h3>Pengeluaran Operasional</h3>
                    <div class="value" style="color: #e74c3c;">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
                    <small>Listrik, Air, ATK, dll</small>
                </div>
                <div class="stat-card purple">
                    <h3>Hutang Komisi</h3>
                    <div class="value" style="color: #8e44ad;">Rp <?= number_format($totalHutangKomisi, 0, ',', '.') ?></div>
                    <small>Sisa Belum Dibayar</small>
                </div>
                <div class="stat-card green">
                    <h3>PROFIT BERSIH</h3>
                    <div class="value" style="color: #27ae60;">Rp <?= number_format($profitBersih, 0, ',', '.') ?></div>
                    <small>(Netto Kotor - Pengeluaran)</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span>🏢 Rincian Profitabilitas Per Cabang</span></div>
                <div class="table-container">
                    <table id="laporanTable">
                        <thead>
                            <tr>
                                <th>Nama Cabang</th>
                                <th class="text-right">Jml Trx</th>
                                <th class="text-right">Omset Bruto</th>
                                <th class="text-right">Netto Kotor (Jatah Kantor)</th>
                                <th class="text-right" style="color: #e74c3c;">Pengeluaran</th>
                                <th class="text-right" style="background: #e8f5e9;">PROFIT BERSIH</th>
                                <th class="text-center no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($dataCabang) > 0): ?>
                                <?php foreach($dataCabang as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['nama_cabang']) ?></strong></td>
                                    <td class="text-right"><?= number_format($c['jml_trx']) ?></td>
                                    <td class="text-right">Rp <?= number_format($c['bruto'], 0, ',', '.') ?></td>
                                    <td class="text-right">Rp <?= number_format($c['netto_kotor'], 0, ',', '.') ?></td>
                                    <td class="text-right" style="color: #e74c3c;">
                                        Rp <?= number_format($c['pengeluaran'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-right" style="font-weight: bold; color: #27ae60; background: #e8f5e9;">
                                        Rp <?= number_format($c['profit_bersih'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-center no-print">
                                        <a href="detail_keuangan_cabang.php?id=<?= $c['branch_id'] ?>&filter=<?= $filter_type ?>&tgl_custom=<?= $tgl_custom ?>&tgl_start=<?= $tgl_start_range ?>&tgl_end=<?= $tgl_end_range ?>" 
                                           class="btn btn-sm btn-primary" 
                                           style="padding: 5px 10px; font-size: 12px;">
                                            👁️ Detail
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td colspan="2">TOTAL KESELURUHAN</td>
                                    <td class="text-right">Rp <?= number_format($totalBruto, 0, ',', '.') ?></td>
                                    <td class="text-right">Rp <?= number_format($nettoKotor, 0, ',', '.') ?></td>
                                    <td class="text-right" style="color: #e74c3c;">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></td>
                                    <td class="text-right" style="color: #27ae60; background: #d4edda;">Rp <?= number_format($profitBersih, 0, ',', '.') ?></td>
                                    <td class="no-print"></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 20px;">Tidak ada data transaksi pada periode ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleDateInput(type) {
            document.getElementById('input-hari').style.display = 'none';
            document.getElementById('input-bulan').style.display = 'none';
            document.getElementById('input-tahun').style.display = 'none';
            document.getElementById('input-range').style.display = 'none';
            
            if (type === 'range') {
                document.getElementById('input-range').style.display = 'flex';
            } else {
                document.getElementById('input-' + type).style.display = 'block';
            }
        }
        
        function exportTableToExcel(tableID, filename = ''){
            var downloadLink;
            var dataType = 'application/vnd.ms-excel';
            var tableSelect = document.getElementById(tableID);
            var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
            filename = filename?filename+'.xls':'excel_data.xls';
            downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            if(navigator.msSaveOrOpenBlob){
                var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
                navigator.msSaveOrOpenBlob( blob, filename);
            }else{
                downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                downloadLink.download = filename;
                downloadLink.click();
            }
        }
    </script>
</body>
</html>