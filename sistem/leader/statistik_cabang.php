<?php
require_once 'layout_header.php'; // Pastikan layout_header sudah session_start
$branch_id = $_SESSION['user_branch_id'];

// 1. AMBIL JAM MULAI
$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulai = $setting['jam_mulai_hari'] ?? '08:00:00';

// 2. SET RENTANG BULAN DIPILIH
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Format Filter: Y-m-01 08:00:00 s/d Y-(m+1)-01 08:00:00
$start_date = "$tahun-$bulan-01 $jamMulai";
$end_date   = date('Y-m-d H:i:s', strtotime("$start_date +1 month"));

// 3. QUERY TOTAL (Dalam rentang waktu)
$stmtStats = $pdo->prepare("
    SELECT COUNT(*) as total_transaksi, SUM(total_bayar) as total_omset
    FROM transactions 
    WHERE branch_id = ? AND status = 'selesai'
    AND created_at >= ? AND created_at < ?
");
$stmtStats->execute([$branch_id, $start_date, $end_date]);
$stats = $stmtStats->fetch();

// 4. QUERY TOP PAKET
$stmtPaket = $pdo->prepare("
    SELECT p.nama_paket, COUNT(t.id) as jumlah_laku
    FROM transactions t
    JOIN packages p ON t.package_id = p.id
    WHERE t.branch_id = ? AND t.status = 'selesai'
    AND t.created_at >= ? AND t.created_at < ?
    GROUP BY p.nama_paket
    ORDER BY jumlah_laku DESC LIMIT 5
");
$stmtPaket->execute([$branch_id, $start_date, $end_date]);
$topPaket = $stmtPaket->fetchAll();

// 5. QUERY GRAFIK HARIAN (Grouping by Tanggal Bisnis)
// Expression: Jika jam < 08:00, tanggal = kemarin.
$expDate = "DATE(IF(TIME(created_at) < '$jamMulai', DATE_SUB(created_at, INTERVAL 1 DAY), created_at))";

$stmtHarian = $pdo->prepare("
    SELECT $expDate as tanggal, SUM(total_bayar) as omset, COUNT(*) as jumlah
    FROM transactions
    WHERE branch_id = ? AND status = 'selesai'
    AND created_at >= ? AND created_at < ?
    GROUP BY tanggal
    ORDER BY tanggal DESC
");
$stmtHarian->execute([$branch_id, $start_date, $end_date]);
$dataHarian = $stmtHarian->fetchAll();
?>

<h2 class="page-title">Statistik & Laporan</h2>

<div class="card mb-3">
    <form method="GET" class="d-flex align-items-center gap-2">
        <label>Pilih Periode:</label>
        <select name="bulan" class="form-control w-auto">
            <?php for($i=1;$i<=12;$i++): ?>
                <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $i==$bulan ? 'selected':'' ?>>Bulan <?= $i ?></option>
            <?php endfor; ?>
        </select>
        <select name="tahun" class="form-control w-auto">
            <option value="2025" <?= $tahun=='2025'?'selected':'' ?>>2025</option>
            <option value="2026" <?= $tahun=='2026'?'selected':'' ?>>2026</option>
        </select>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <h3>Ringkasan Bulan <?= $bulan ?>-<?= $tahun ?></h3>
            <table class="table">
                <tr>
                    <td>Total Transaksi</td>
                    <td class="text-end fw-bold"><?= number_format($stats['total_transaksi']) ?></td>
                </tr>
                <tr>
                    <td>Total Omset</td>
                    <td class="text-end fw-bold text-success">Rp <?= number_format($stats['total_omset'], 0, ',', '.') ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <h3>Paket Terlaris</h3>
            <table class="table table-sm">
                <?php foreach($topPaket as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nama_paket']) ?></td>
                    <td class="text-end"><?= $p['jumlah_laku'] ?> x</td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <h3>Rincian Harian (Shift Start: <?= substr($jamMulai,0,5) ?>)</h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr><th>Tanggal Bisnis</th><th>Jumlah Pelanggan</th><th class="text-end">Omset</th></tr>
            </thead>
            <tbody>
                <?php foreach($dataHarian as $h): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($h['tanggal'])) ?></td>
                    <td><?= $h['jumlah'] ?></td>
                    <td class="text-end">Rp <?= number_format($h['omset'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($dataHarian)): ?>
                    <tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>