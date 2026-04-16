<?php
// File: sistem/admin/detail_keuangan_cabang.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { 
    header("Location: ../auth/login_system.php"); 
    exit; 
}

// Ambil Parameter
$branch_id = $_GET['id'] ?? 0;
$filter_type = $_GET['filter'] ?? 'bulan';
$tgl_custom = $_GET['tgl_custom'] ?? date('Y-m-d');
$bulan_custom = $_GET['bulan_custom'] ?? date('Y-m');
$tahun_custom = $_GET['tahun_custom'] ?? date('Y');

// Ambil Nama Cabang
$stmtCabang = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
$stmtCabang->execute([$branch_id]);
$cabang = $stmtCabang->fetch();

if (!$cabang) {
    die("Data cabang tidak ditemukan.");
}

// Setup Filter Waktu untuk Query Shift (kasir_attendance)
$where = "ka.branch_id = ?";
$params = [$branch_id];
$periode_text = "";

if ($filter_type == 'hari') {
    $where .= " AND ka.tanggal = ?";
    $params[] = $tgl_custom;
    $periode_text = date('d F Y', strtotime($tgl_custom));
} elseif ($filter_type == 'bulan') {
    $where .= " AND DATE_FORMAT(ka.tanggal, '%Y-%m') = ?";
    $params[] = $bulan_custom;
    $periode_text = date('F Y', strtotime($bulan_custom . '-01'));
} else {
    $where .= " AND YEAR(ka.tanggal) = ?";
    $params[] = $tahun_custom;
    $periode_text = 'Tahun ' . $tahun_custom;
}

// Query Data Shift (Kasir Attendance) - dengan catatan tutup dari shift_logs
$sqlShift = "SELECT 
                ka.*, 
                u.nama_lengkap as nama_kasir,
                sl.catatan_tutup,
                (SELECT COUNT(*) FROM transactions t 
                 WHERE t.kasir_id = ka.kasir_id 
                 AND t.branch_id = ka.branch_id 
                 AND t.created_at BETWEEN ka.waktu_masuk AND COALESCE(ka.waktu_keluar, NOW())) as real_trx_count,
                (SELECT COALESCE(SUM(t.omset_cabang),0) FROM transactions t 
                 WHERE t.kasir_id = ka.kasir_id 
                 AND t.branch_id = ka.branch_id 
                 AND t.created_at BETWEEN ka.waktu_masuk AND COALESCE(ka.waktu_keluar, NOW())) as real_omset_cabang
             FROM kasir_attendance ka
             JOIN users u ON ka.kasir_id = u.id
             LEFT JOIN shift_logs sl ON ka.id = sl.attendance_id
             WHERE $where
             ORDER BY ka.waktu_masuk DESC";

$stmtShift = $pdo->prepare($sqlShift);
$stmtShift->execute($params);
$shifts = $stmtShift->fetchAll();

// Hitung Total untuk Summary
$totalOmset = 0;
$totalPengeluaran = 0;

foreach ($shifts as $s) {
    $totalOmset += $s['real_omset_cabang']; 
    $totalPengeluaran += $s['total_pengeluaran'];
}
$totalProfit = $totalOmset - $totalPengeluaran;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Keuangan - <?= htmlspecialchars($cabang['nama_cabang']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-shift { padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .bg-open { background: #e1f5fe; color: #0288d1; }
        .bg-closed { background: #e8f5e9; color: #2e7d32; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-card h3 { margin: 0 0 5px 0; font-size: 14px; color: #7f8c8d; text-transform: uppercase; }
        .stat-card .value { font-size: 22px; font-weight: bold; color: #2c3e50; }
        .stat-card small { font-size: 12px; color: #95a5a6; }
        
        @media print {
            .no-print, .sidebar, .topbar-right { display: none !important; }
            .main-content { width: 100%; margin: 0; padding: 0; }
            .card { border: 1px solid #ddd; box-shadow: none; }
            body { background: white; color: black; font-size: 12px; }
            h1 { font-size: 16px; margin-bottom: 5px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #000 !important; padding: 5px !important; }
            .stat-card { border: 1px solid #000; margin-bottom: 10px; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>
    <div class="container-layout">
        
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>&#9889; ADMIN PANEL</h2>
                <small>Bugar Refleksi System</small>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard_admin.php" class="menu-item"><i>&#127968;</i> Dashboard</a>
                <a href="data_keuangan.php" class="menu-item active"><i>&#128176;</i> Data Keuangan</a>
                <a href="omset_terapis.php" class="menu-item"><i>&#128134;</i> Omset Terapis</a>
                
                <a href="../auth/logout_system.php" class="menu-item" style="color: #c0392b; margin-top: 50px;"><i>&#128682;</i> Logout</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div>
                    <h1>Detail Keuangan Cabang</h1>
                    <span>Cabang: <strong><?= htmlspecialchars($cabang['nama_cabang']) ?></strong> | Periode: <?= $periode_text ?></span>
                </div>
                <div class="topbar-right">
                    <a href="data_keuangan.php?filter=<?= $filter_type ?>&tgl_custom=<?= $tgl_custom ?>&bulan_custom=<?= $bulan_custom ?>&tahun_custom=<?= $tahun_custom ?>" 
                       class="btn btn-secondary no-print" 
                       style="margin-right: 10px; background: #95a5a6; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">
                        &#8592; Kembali
                    </a>
                    <button onclick="window.print()" class="btn btn-primary no-print" 
                            style="background: #3498db; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">
                        &#128424; Cetak PDF
                    </button>
                    <button onclick="exportTableToExcel('detailTable', 'Laporan_<?= str_replace(' ','_',$cabang['nama_cabang']) ?>_<?= date('Ymd') ?>')" 
                            class="btn btn-success no-print" 
                            style="background: #27ae60; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer;">
                        &#128202; Export Excel
                    </button>
                </div>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="card-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 20px; display: grid; gap: 20px;">
                <div class="stat-card blue" style="border-left: 5px solid #3498db;">
                    <h3>Total Netto Kotor</h3>
                    <div class="value">Rp <?= number_format($totalOmset, 0, ',', '.') ?></div>
                    <small>Pendapatan Perusahaan (Sebelum Pengeluaran)</small>
                </div>
                <div class="stat-card red" style="border-left: 5px solid #e74c3c;">
                    <h3>Total Pengeluaran</h3>
                    <div class="value" style="color: #e74c3c;">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
                    <small>Biaya Operasional Shift (Listrik, Air, dll)</small>
                </div>
                <div class="stat-card green" style="border-left: 5px solid #27ae60;">
                    <h3>PROFIT BERSIH</h3>
                    <div class="value" style="color: #27ae60;">Rp <?= number_format($totalProfit, 0, ',', '.') ?></div>
                    <small>Netto - Pengeluaran</small>
                </div>
            </div>

            <!-- RIWAYAT SHIFT & LAPORAN (sama seperti owner detail_cabang) -->
            <div class="card">
                <div class="card-header">
                    <span>&#128220; Riwayat Shift &amp; Laporan Operasional Periode Ini</span>
                </div>
                <div class="table-container">
                    <table id="detailTable" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Kasir Bertugas</th>
                                <th>Jam Buka</th>
                                <th>Jam Tutup</th>
                                <th>Omset Shift</th>
                                <th>Transaksi</th>
                                <th style="color: #e74c3c;">Pengeluaran</th>
                                <th style="background: #e8f5e9;">Profit Shift</th>
                                <th>Status</th>
                                <th class="no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($shifts) > 0): ?>
                                <?php foreach($shifts as $s): 
                                    $netto   = $s['real_omset_cabang'];
                                    $expense = $s['total_pengeluaran'];
                                    $profit  = $netto - $expense;
                                    $masuk   = date('H:i', strtotime($s['waktu_masuk']));
                                    $keluar  = $s['waktu_keluar'] ? date('H:i', strtotime($s['waktu_keluar'])) : '-';
                                ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 10px;"><?= date('d M Y', strtotime($s['tanggal'])) ?></td>
                                    <td style="padding: 10px;">
                                        <strong><?= htmlspecialchars($s['nama_kasir']) ?></strong>
                                        <?php if(!empty($s['catatan_tutup'])): ?>
                                            <br><small style="color: #e67e22;">&#128221; Ada Catatan</small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px;"><?= $masuk ?></td>
                                    <td style="padding: 10px;">
                                        <?php if($s['waktu_keluar']): ?>
                                            <?= $keluar ?>
                                        <?php else: ?>
                                            <span style="color: #7f8c8d;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <strong>Rp <?= number_format($netto, 0, ',', '.') ?></strong>
                                    </td>
                                    <td style="padding: 10px;"><?= $s['real_trx_count'] ?> trx</td>
                                    <td style="padding: 10px; color: #e74c3c;">
                                        <?php if($expense > 0): ?>
                                            - Rp <?= number_format($expense, 0, ',', '.') ?>
                                        <?php else: ?>
                                            <span style="color:#ccc;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px; font-weight: bold; color: #27ae60; background: #e8f5e9;">
                                        Rp <?= number_format($profit, 0, ',', '.') ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?php if($s['status'] == 'aktif'): ?>
                                            <span class="badge-shift bg-open">SEDANG BUKA</span>
                                        <?php else: ?>
                                            <span class="badge-shift bg-closed">SELESAI</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px;" class="no-print">
                                        <?php if($s['status'] == 'selesai'): ?>
                                            <a href="../owner/laporan_detail_shift.php?id=<?= $s['id'] ?>" 
                                               class="btn btn-primary btn-sm" 
                                               target="_blank"
                                               style="background: #3498db; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; white-space: nowrap; display: inline-block;">
                                                &#128196; Lihat Laporan
                                            </a>
                                        <?php else: ?>
                                            <button disabled 
                                                    style="padding: 6px 12px; font-size: 12px; border: none; border-radius: 4px; background: #e67e22; color: white; cursor: not-allowed;">
                                                &#128308; Live
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <?php if($expense > 0): 
                                    $stmtExpDet = $pdo->prepare("SELECT keterangan, jumlah FROM shift_expenses WHERE attendance_id = ?");
                                    $stmtExpDet->execute([$s['id']]);
                                    $details = $stmtExpDet->fetchAll();
                                ?>
                                <tr style="background: #fffcf5; border-bottom: 2px solid #eee;">
                                    <td colspan="2"></td>
                                    <td colspan="8" style="padding: 8px 15px; font-size: 12px; color: #666;">
                                        <strong>&#8627; Rincian Pengeluaran:</strong>
                                        <?php foreach($details as $d): ?>
                                            <span style="margin-left: 10px; display: inline-block; background: #fff; border: 1px solid #ddd; padding: 2px 8px; border-radius: 10px;">
                                                <?= htmlspecialchars($d['keterangan']) ?>: 
                                                <span style="color:#e74c3c;">Rp <?= number_format($d['jumlah'], 0, ',', '.') ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #999;">
                                        Tidak ada shift kasir pada periode ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if(count($shifts) > 0): ?>
                        <tfoot style="background: #f8f9fa; font-weight: bold; border-top: 2px solid #ddd;">
                            <tr>
                                <td colspan="4" style="padding: 12px; text-align: right;">TOTAL PERIODE:</td>
                                <td style="padding: 12px; color: #3498db; font-size: 14px;">Rp <?= number_format($totalOmset, 0, ',', '.') ?></td>
                                <td style="padding: 12px;"></td>
                                <td style="padding: 12px; color: #e74c3c; font-size: 14px;">
                                    <?= $totalPengeluaran > 0 ? '- Rp ' . number_format($totalPengeluaran, 0, ',', '.') : '-' ?>
                                </td>
                                <td style="padding: 12px; color: #27ae60; font-size: 14px; background: #e8f5e9;">
                                    Rp <?= number_format($totalProfit, 0, ',', '.') ?>
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        function exportTableToExcel(tableID, filename = ''){
            var downloadLink;
            var dataType = 'application/vnd.ms-excel';
            var tableSelect = document.getElementById(tableID);
            var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
            
            filename = filename ? filename + '.xls' : 'excel_data.xls';
            downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            
            if(navigator.msSaveOrOpenBlob){
                var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
                navigator.msSaveOrOpenBlob( blob, filename);
            } else {
                downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                downloadLink.download = filename;
                downloadLink.click();
            }
        }
    </script>
</body>
</html>