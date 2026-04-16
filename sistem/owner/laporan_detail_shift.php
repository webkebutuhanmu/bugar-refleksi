<?php
require_once '../config/database.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['owner', 'admin'])) { header("Location: ../auth/login_system.php"); exit; }

$attendance_id = $_GET['id'] ?? 0;

$sqlShift = "SELECT ka.*, u.nama_lengkap as nama_kasir, b.nama_cabang, sl.catatan_tutup 
             FROM kasir_attendance ka
             JOIN users u ON ka.kasir_id = u.id
             JOIN branches b ON ka.branch_id = b.id
             LEFT JOIN shift_logs sl ON ka.id = sl.attendance_id
             WHERE ka.id = ?";
$stmtShift = $pdo->prepare($sqlShift);
$stmtShift->execute([$attendance_id]);
$shift = $stmtShift->fetch();

if (!$shift) { die("Data Laporan tidak ditemukan."); }

$waktu_masuk = $shift['waktu_masuk'];
$waktu_keluar = $shift['waktu_keluar'];
$kasir_id = $shift['kasir_id'];
$branch_id = $shift['branch_id'];

$sqlTrx = "SELECT t.*, p.nama_paket, ut.nama_lengkap as nama_terapis 
           FROM transactions t
           JOIN packages p ON t.package_id = p.id
           JOIN users ut ON t.terapis_id = ut.id
           WHERE t.kasir_id = ? 
           AND t.branch_id = ? 
           AND t.created_at >= ? 
           AND t.created_at <= ?
           ORDER BY t.created_at ASC";
$stmtTrx = $pdo->prepare($sqlTrx);
$stmtTrx->execute([$kasir_id, $branch_id, $waktu_masuk, $waktu_keluar]);
$transactions = $stmtTrx->fetchAll();

$addedPackages = [];
if (!empty($transactions)) {
    try {
        $pdo->query("SELECT 1 FROM transaction_added_packages LIMIT 1");
        $trxIds = array_column($transactions, 'id');
        $placeholders = implode(',', array_fill(0, count($trxIds), '?'));
        $stmtAdded = $pdo->prepare("SELECT * FROM transaction_added_packages WHERE transaction_id IN ($placeholders) ORDER BY created_at ASC");
        $stmtAdded->execute($trxIds);
        foreach ($stmtAdded->fetchAll() as $ap) {
            $addedPackages[$ap['transaction_id']][] = $ap;
        }
    } catch (Exception $e) { }
}

$sqlExpenses = "SELECT * FROM shift_expenses WHERE attendance_id = ? ORDER BY created_at ASC";
$stmtExpenses = $pdo->prepare($sqlExpenses);
$stmtExpenses->execute([$attendance_id]);
$pengeluaran = $stmtExpenses->fetchAll();

$totalPengeluaran = $shift['total_pengeluaran'] ?? 0;

$total_perusahaan = 0;
$total_terapis = 0;
$terapisStats = [];

$totalTunai = 0;     $countTunai = 0;
$totalTransfer = 0;  $countTransfer = 0;
$totalQris = 0;      $countQris = 0;
$totalDebit = 0;     $countDebit = 0;
$totalBayarNanti = 0;$countBayarNanti = 0;

foreach ($transactions as $t) {
    $total_perusahaan += $t['omset_cabang'];
    $total_terapis += $t['omset_terapis'];

    $nama = $t['nama_terapis'];
    if (!isset($terapisStats[$nama])) {
        $terapisStats[$nama] = ['count' => 0, 'komisi' => 0];
    }
    $terapisStats[$nama]['count']++;
    $terapisStats[$nama]['komisi'] += $t['omset_terapis'];
    
    $metode = $t['metode_pembayaran'] ?? '';
    $bayar = floatval($t['total_bayar']);
    
    switch ($metode) {
        case 'tunai': $totalTunai += $bayar; $countTunai++; break;
        case 'transfer': $totalTransfer += $bayar; $countTransfer++; break;
        case 'qris': $totalQris += $bayar; $countQris++; break;
        case 'debit': $totalDebit += $bayar; $countDebit++; break;
        case 'bayar_nanti': $totalBayarNanti += $bayar; $countBayarNanti++; break;
    }
}

$totalNonTunai = $totalTransfer + $totalQris + $totalDebit;
$countNonTunai = $countTransfer + $countQris + $countDebit;
$totalAll = $totalTunai + $totalNonTunai + $totalBayarNanti;

$start = new DateTime($waktu_masuk);
$end = new DateTime($waktu_keluar);
$diff = $start->diff($end);
$durasi = $diff->format('%h Jam %i Menit');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Shift #<?= $attendance_id ?> - Bugar Refleksi</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap');
        body { background: #f0f2f5; font-family: 'DM Sans', sans-serif; margin: 0; padding: 20px; color: #111; }
        .report-container { max-width: 1000px; margin: 0 auto; background: white; padding: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-radius: 12px; }
        .report-header { text-align: center; border-bottom: 2px solid #111; padding-bottom: 20px; margin-bottom: 30px; }
        .report-header h1 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
        .report-header p { color: #555; margin: 5px 0 0 0; font-size: 14px; }
        
        .meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #ddd; }
        .meta-item label { color: #555; font-size: 11px; display: block; text-transform: uppercase; letter-spacing: 0.5px; font-weight: bold; }
        .meta-item strong { color: #111; font-size: 15px; display: block; margin-top: 5px; }
        
        .section-title { font-size: 16px; color: #111; border-left: 4px solid #111; padding-left: 10px; margin: 40px 0 15px 0; font-weight: bold; text-transform: uppercase; }
        
        .finance-summary { display: flex; gap: 20px; margin-bottom: 30px; }
        .finance-card { flex: 1; padding: 20px; border-radius: 8px; color: #111; border: 1px solid #ddd; background: #fafafa; }
        .finance-card h4 { margin: 0 0 10px 0; font-size: 13px; text-transform: uppercase; color: #555; }
        .finance-card .amount { font-size: 24px; font-weight: bold; }
        
        .pay-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 15px; }
        .pay-card { padding: 15px; border-radius: 8px; border: 1px solid #ddd; text-align: center; background: #fff; }
        .pay-card .pay-name { font-size: 12px; color: #555; font-weight: bold; text-transform: uppercase; }
        .pay-card .pay-amount { font-size: 18px; font-weight: bold; margin: 8px 0 4px 0; color: #111; }
        .pay-card .pay-count { font-size: 11px; color: #777; }

        .table-simple { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px; }
        .table-simple th, .table-simple td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        .table-simple th { background: #f8f9fa; font-weight: bold; color: #111; border-top: 1px solid #ddd; border-bottom: 2px solid #ddd; text-transform: uppercase; font-size: 11px; }
        .table-simple .text-right { text-align: right; }
        
        .expense-report { background: #fafafa; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 30px; }
        .expense-report h3 { margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
        .expense-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #eee; font-size: 13px; }
        .expense-item .amount { font-weight: bold; color: #111; }
        .expense-total { border-top: 2px solid #111; padding-top: 10px; text-align: right; margin-top: 10px; font-weight: bold; font-size: 15px; }

        .print-btn-wrapper { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px dashed #ddd; }
        .btn-print { background: #111; color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: bold; text-transform: uppercase; font-family: 'DM Sans', sans-serif; }
        .btn-close { background: #fff; color: #111; border: 1px solid #111; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: bold; text-transform: uppercase; margin-left: 10px; font-family: 'DM Sans', sans-serif; }
        
        @media print {
            body { background: white; padding: 0; }
            .report-container { box-shadow: none; border: none; padding: 0; margin: 0; }
            .print-btn-wrapper { display: none; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <h1>Laporan Detail Shift</h1>
            <p>Shift ID: #<?= $attendance_id ?></p>
        </div>

        <div class="meta-grid">
            <div class="meta-item"><label>Cabang</label><strong><?= htmlspecialchars($shift['nama_cabang']) ?></strong></div>
            <div class="meta-item"><label>Kasir</label><strong><?= htmlspecialchars($shift['nama_kasir']) ?></strong></div>
            <div class="meta-item"><label>Tanggal</label><strong><?= date('d/m/Y', strtotime($shift['tanggal'])) ?></strong></div>
            <div class="meta-item"><label>Durasi Shift</label><strong><?= $durasi ?></strong></div>
            <div class="meta-item"><label>Waktu Buka</label><strong><?= date('H:i', strtotime($waktu_masuk)) ?></strong></div>
            <div class="meta-item"><label>Waktu Tutup</label><strong><?= date('H:i', strtotime($waktu_keluar)) ?></strong></div>
            <div class="meta-item"><label>Total Transaksi</label><strong><?= count($transactions) ?> Transaksi</strong></div>
            <div class="meta-item"><label>Status</label><strong>SELESAI</strong></div>
        </div>

        <div class="finance-summary">
            <div class="finance-card">
                <h4>Omset Kotor</h4>
                <div class="amount">Rp <?= number_format($totalAll, 0, ',', '.') ?></div>
                <small>Total Pendapatan</small>
            </div>
            <div class="finance-card">
                <h4>Jatah Kantor</h4>
                <div class="amount">Rp <?= number_format($total_perusahaan, 0, ',', '.') ?></div>
                <small>Sebelum Pengeluaran</small>
            </div>
            <div class="finance-card">
                <h4>Komisi Terapis</h4>
                <div class="amount">Rp <?= number_format($total_terapis, 0, ',', '.') ?></div>
                <small>Total Komisi</small>
            </div>
        </div>

        <?php if ($totalPengeluaran > 0): ?>
        <div class="expense-report">
            <h3>Pengeluaran Shift</h3>
            <?php foreach ($pengeluaran as $exp): ?>
            <div class="expense-item">
                <div class="desc"><?= htmlspecialchars($exp['keterangan']) ?></div>
                <div class="amount">- Rp <?= number_format($exp['jumlah'], 0, ',', '.') ?></div>
            </div>
            <?php endforeach; ?>
            <div class="expense-total">Total Pengeluaran: Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
        </div>

        <div class="finance-summary">
            <div class="finance-card">
                <h4>Total Pengeluaran</h4>
                <div class="amount">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
                <small>Mengurangi Omset Cabang</small>
            </div>
            <div class="finance-card" style="border: 2px solid #111;">
                <h4 style="color: #111;">Omset Bersih Cabang</h4>
                <div class="amount">Rp <?= number_format($shift['omset_shift'], 0, ',', '.') ?></div>
                <small>Setelah Dikurangi Pengeluaran</small>
            </div>
        </div>
        <?php endif; ?>

        <h3 class="section-title">Rincian Metode Pembayaran</h3>
        <div class="pay-grid">
            <div class="pay-card"><div class="pay-name">Tunai</div><div class="pay-amount">Rp <?= number_format($totalTunai, 0, ',', '.') ?></div><div class="pay-count"><?= $countTunai ?> trx</div></div>
            <div class="pay-card"><div class="pay-name">Transfer Bank</div><div class="pay-amount">Rp <?= number_format($totalTransfer, 0, ',', '.') ?></div><div class="pay-count"><?= $countTransfer ?> trx</div></div>
            <div class="pay-card"><div class="pay-name">QRIS</div><div class="pay-amount">Rp <?= number_format($totalQris, 0, ',', '.') ?></div><div class="pay-count"><?= $countQris ?> trx</div></div>
            <div class="pay-card"><div class="pay-name">Kartu Debit</div><div class="pay-amount">Rp <?= number_format($totalDebit, 0, ',', '.') ?></div><div class="pay-count"><?= $countDebit ?> trx</div></div>
        </div>

        <h3 class="section-title">Detail Transaksi</h3>
        <table class="table-simple">
            <thead>
                <tr>
                    <th>Jam</th>
                    <th>Shift</th>
                    <th>Pelanggan</th>
                    <th>Paket</th>
                    <th>Terapis</th>
                    <th>Metode</th>
                    <th class="text-right">Jatah Kantor</th>
                    <th class="text-right">Jatah Terapis</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($transactions as $trx): 
                    $label_shift = ($trx['jenis_shift'] == 'pagi') ? 'PAGI (70:30)' : 'MALAM (60:40)';
                    $metode = strtoupper(str_replace('_', ' ', $trx['metode_pembayaran'] ?? 'N/A'));
                ?>
                <tr>
                    <td><?= date('H:i', strtotime($trx['created_at'])) ?></td>
                    <td><?= $label_shift ?></td>
                    <td><?= htmlspecialchars($trx['nama_pelanggan']) ?></td>
                    <td>
                        <?= htmlspecialchars($trx['nama_paket']) ?><br>
                        <small>Rp <?= number_format($trx['total_bayar'], 0, ',', '.') ?></small>
                        <?php if (!empty($addedPackages[$trx['id']])): ?>
                            <?php foreach ($addedPackages[$trx['id']] as $ap): ?>
                            <br><small>+ <?= htmlspecialchars($ap['nama_paket']) ?> (Rp <?= number_format($ap['harga'], 0, ',', '.') ?>)</small>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($trx['nama_terapis']) ?></td>
                    <td><?= $metode ?></td>
                    <td class="text-right">Rp <?= number_format($trx['omset_cabang'], 0, ',', '.') ?></td>
                    <td class="text-right">Rp <?= number_format($trx['omset_terapis'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="border-top: 2px solid #111;">
                    <td colspan="6" class="text-right"><strong>TOTAL:</strong></td>
                    <td class="text-right"><strong>Rp <?= number_format($total_perusahaan, 0, ',', '.') ?></strong></td>
                    <td class="text-right"><strong>Rp <?= number_format($total_terapis, 0, ',', '.') ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <div style="page-break-inside: avoid;">
            <h3 class="section-title">Rekap Komisi Per Terapis</h3>
            <table class="table-simple" style="width: 50%;">
                <thead>
                    <tr>
                        <th>Nama Terapis</th>
                        <th class="text-right">Jumlah Tamu</th>
                        <th class="text-right">Total Komisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($terapisStats as $nama => $stat): ?>
                    <tr>
                        <td><?= htmlspecialchars($nama) ?></td>
                        <td class="text-right"><?= $stat['count'] ?></td>
                        <td class="text-right"><strong>Rp <?= number_format($stat['komisi'], 0, ',', '.') ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if($shift['catatan_tutup']): ?>
        <div style="background: #f8f9fa; padding: 15px; border: 1px solid #ddd; margin-top: 30px; border-radius: 5px;">
            <strong>CATATAN KASIR:</strong><br>
            <?= nl2br(htmlspecialchars($shift['catatan_tutup'])) ?>
        </div>
        <?php endif; ?>

        <div style="margin-top: 60px; display: flex; justify-content: space-between; text-align: center;">
            <div style="width: 200px;">
                <p>Diserahkan Oleh,</p>
                <br><br><br>
                <p><strong>( <?= htmlspecialchars($shift['nama_kasir']) ?> )</strong><br>Kasir</p>
            </div>
            <div style="width: 200px;">
                <p>Diterima Oleh,</p>
                <br><br><br>
                <p><strong>( ................................ )</strong><br>Penerima</p>
            </div>
        </div>

        <div class="print-btn-wrapper">
            <button class="btn-print" onclick="window.print()">Cetak Laporan</button>
            <button class="btn-close" onclick="window.close()">Tutup</button>
        </div>
    </div>
</body>
</html>