<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

// Cek Role
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { 
    header("Location: ../auth/login_system.php"); 
    exit; 
}

// --- 1. LOGIC PENENTUAN HARI BISNIS (RESET JAM 08:00) ---
// Ambil jam mulai dari database
$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulaiBisnis = $setting['jam_mulai_hari'] ?? '08:00:00'; // Default 08:00

// Tentukan rentang waktu "Hari Ini"
$sekarang = new DateTime();
$jamSekarang = $sekarang->format('H:i:s');

// Jika jam sekarang < jam mulai (misal 02:00 < 08:00), hitung sebagai hari kemarin
if ($jamSekarang < $jamMulaiBisnis) {
    $tglBisnis = date('Y-m-d', strtotime('-1 day'));
} else {
    $tglBisnis = date('Y-m-d');
}

// Format rentang waktu untuk Query SQL (DATETIME)
$start_periode = "$tglBisnis $jamMulaiBisnis"; 
$end_periode   = date('Y-m-d H:i:s', strtotime("$start_periode +1 day"));

// Label periode untuk ditampilkan di layar
$labelPeriode = date('d M H:i', strtotime($start_periode)) . " s.d " . date('d M H:i', strtotime($end_periode));


// --- 2. DATA UTAMA (FILTER BERDASARKAN HARI BISNIS) ---

// Query Summary
// - Bruto, Netto, Trx: Dihitung berdasarkan rentang waktu hari ini
// - Hutang Komisi: Dihitung GLOBAL (Semua yang status='pending')
$sqlSummary = "SELECT 
    -- Hitung Data HARI INI saja
    COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN total_bayar ELSE 0 END), 0) as total_bruto,
    COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN omset_cabang ELSE 0 END), 0) as total_netto_kotor,
    COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) as total_trx,
    
    -- Hitung Hutang Komisi (GLOBAL / AKUMULASI) - Tidak boleh reset
    COALESCE(SUM(CASE WHEN commission_status = 'pending' THEN omset_terapis ELSE 0 END), 0) as total_hutang_komisi
    
    FROM transactions";

$stmtSum = $pdo->prepare($sqlSummary);
$stmtSum->execute([
    $start_periode, $end_periode, // Param Bruto
    $start_periode, $end_periode, // Param Netto
    $start_periode, $end_periode  // Param Trx
]);
$summary = $stmtSum->fetch();

// Hitung Pengeluaran HARI INI
$sqlExp = "SELECT COALESCE(SUM(jumlah), 0) FROM shift_expenses WHERE created_at >= ? AND created_at < ?";
$stmtExp = $pdo->prepare($sqlExp);
$stmtExp->execute([$start_periode, $end_periode]);
$totalPengeluaran = $stmtExp->fetchColumn();

// Kalkulasi Akhir
$totalTrx = $summary['total_trx'];
$totalBruto = $summary['total_bruto'];
$nettoKotor = $summary['total_netto_kotor'];
$totalHutangKomisi = $summary['total_hutang_komisi'];
$totalNettoBersih = $nettoKotor - $totalPengeluaran;


// --- 3. DATA PROFITABILITAS CABANG (HARI INI) ---
$branches = $pdo->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
$detailCabang = [];

foreach($branches as $b) {
    // Transaksi Cabang (Hari Ini)
    $sqlC = "SELECT 
                COUNT(id) as jml, 
                COALESCE(SUM(total_bayar),0) as bruto, 
                COALESCE(SUM(omset_cabang),0) as netto 
             FROM transactions 
             WHERE branch_id = ? AND created_at >= ? AND created_at < ?";
    $stmtC = $pdo->prepare($sqlC);
    $stmtC->execute([$b['id'], $start_periode, $end_periode]);
    $resC = $stmtC->fetch();

    // Pengeluaran Cabang (Hari Ini)
    $sqlE = "SELECT COALESCE(SUM(jumlah),0) FROM shift_expenses WHERE branch_id = ? AND created_at >= ? AND created_at < ?";
    $stmtE = $pdo->prepare($sqlE);
    $stmtE->execute([$b['id'], $start_periode, $end_periode]);
    $resE = $stmtE->fetchColumn();

    $detailCabang[] = [
        'id' => $b['id'],
        'nama_cabang' => $b['nama_cabang'],
        'jml_trx' => $resC['jml'],
        'bruto' => $resC['bruto'],
        'netto_kotor' => $resC['netto'],
        'pengeluaran' => $resE,
        'profit_bersih' => $resC['netto'] - $resE
    ];
}

// --- 4. LOG PENGELUARAN TERAKHIR (Global, untuk info) ---
$listPengeluaran = $pdo->query("SELECT se.*, b.nama_cabang, u.nama_lengkap as nama_kasir
                                FROM shift_expenses se
                                JOIN branches b ON se.branch_id = b.id
                                JOIN users u ON se.kasir_id = u.id
                                ORDER BY se.created_at DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stat-card.blue { border-left: 5px solid #3498db; }
        .stat-card.purple { border-left: 5px solid #9b59b6; }
        .stat-card.red { border-left: 5px solid #e74c3c; }
        .stat-card.green { border-left: 5px solid #27ae60; }
        .table-expenses td { font-size: 13px; }
        .section-title { margin: 25px 0 15px; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .periode-badge { 
            background: #e3f2fd; color: #1565c0; padding: 5px 10px; 
            border-radius: 5px; font-size: 12px; font-weight: bold; 
            display: inline-block; margin-top: 5px;
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
                <a href="dashboard_admin.php" class="menu-item active"><i>🏠</i> Dashboard</a>
                <a href="data_keuangan.php" class="menu-item"><i>💰</i> Data Keuangan</a>
                <a href="omset_terapis.php" class="menu-item"><i>💆</i> Omset Terapis</a>
                
                <a href="../auth/logout_system.php" class="menu-item" style="color: #c0392b; margin-top: 50px;"><i>🚪</i> Logout</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div>
                    <h1>Dashboard Live (Hari Ini)</h1>
                    <span class="periode-badge">⏱ Periode: <?= $labelPeriode ?></span>
                </div>
                <div class="topbar-right">
                    <div style="text-align: right;">
                        <span style="display:block; font-weight: bold;">Halo, <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin') ?></span>
                        <span class="badge badge-success">Administrator</span>
                    </div>
                </div>
            </div>

            <div class="card-grid" style="grid-template-columns: repeat(4, 1fr);">
                <div class="stat-card blue">
                    <h3>Omset Hari Ini</h3>
                    <div class="value">Rp <?= number_format($totalBruto, 0, ',', '.') ?></div>
                    <small><?= $totalTrx ?> Transaksi Masuk</small>
                </div>
                <div class="stat-card red">
                    <h3>Pengeluaran Hari Ini</h3>
                    <div class="value" style="color: #e74c3c;">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
                    <small>Biaya Operasional Shift</small>
                </div>
                <div class="stat-card purple">
                    <h3>Hutang Komisi</h3>
                    <div class="value" style="color: #8e44ad;">Rp <?= number_format($totalHutangKomisi, 0, ',', '.') ?></div>
                    <small>Total Belum Dibayar (Global)</small>
                </div>
                <div class="stat-card green">
                    <h3>Profit Bersih Hari Ini</h3>
                    <div class="value" style="color: #27ae60;">Rp <?= number_format($totalNettoBersih, 0, ',', '.') ?></div>
                    <small>Netto - Pengeluaran</small>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <div class="card-header"><span>🏢 Profitabilitas Cabang (Hari Ini)</span></div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cabang</th>
                                    <th class="text-right">Jatah Kantor</th>
                                    <th class="text-right">Pengeluaran</th>
                                    <th class="text-right">Profit Bersih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($detailCabang as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['nama_cabang']) ?></strong></td>
                                    <td class="text-right">Rp <?= number_format($c['netto_kotor'], 0, ',', '.') ?></td>
                                    <td class="text-right" style="color: #e74c3c;">
                                        - Rp <?= number_format($c['pengeluaran'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-right" style="font-weight: bold; color: #27ae60;">
                                        Rp <?= number_format($c['profit_bersih'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><span>💸 Input Pengeluaran Terakhir</span></div>
                    <div class="table-container">
                        <table class="table-expenses">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Cabang</th>
                                    <th>Keterangan</th>
                                    <th class="text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($listPengeluaran) > 0): ?>
                                    <?php foreach($listPengeluaran as $lp): ?>
                                    <tr>
                                        <td>
                                            <?= date('d/m H:i', strtotime($lp['created_at'])) ?><br>
                                            <small style="color:#7f8c8d;"><?= htmlspecialchars($lp['nama_kasir']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($lp['nama_cabang']) ?></td>
                                        <td><?= htmlspecialchars($lp['keterangan']) ?></td>
                                        <td class="text-right" style="color: #e74c3c; font-weight:bold;">
                                            Rp <?= number_format($lp['jumlah'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center; padding:20px; color:#95a5a6;">Belum ada data.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div> 
    </div>
</body>
</html>