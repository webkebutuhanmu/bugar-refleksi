<?php
session_start();
require_once '../config/database.php';
setlocale(LC_TIME, 'id_ID');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'terapis') { 
    header("Location: ../auth/login_system.php"); exit; 
}

$terapis_id = $_SESSION['user_id'];
$view_bulan = $_GET['bulan'] ?? null; // Format YYYY-MM

// ================================================================
// POTONGAN GAJI
// ================================================================
$potonganPending = $pdo->prepare("
    SELECT sd.*, u.nama_lengkap as admin_nama FROM salary_deductions sd
    LEFT JOIN users u ON sd.admin_id=u.id
    WHERE sd.terapis_id=? AND sd.status='pending' ORDER BY sd.created_at DESC");
$potonganPending->execute([$terapis_id]);
$potonganPending = $potonganPending->fetchAll();
$totalPotPending = array_sum(array_column($potonganPending, 'jumlah'));

$potonganApplied = $pdo->prepare("
    SELECT sd.*, u.nama_lengkap as admin_nama FROM salary_deductions sd
    LEFT JOIN users u ON sd.admin_id=u.id
    WHERE sd.terapis_id=? AND sd.status='applied' ORDER BY sd.applied_at DESC");
$potonganApplied->execute([$terapis_id]);
$potonganApplied = $potonganApplied->fetchAll();

// ================================================================
// LOGIC: TAMPILAN DETAIL BULAN (per batch pembayaran)
// ================================================================
if ($view_bulan) {
    /*
     * Setiap kali admin menekan "Bayar", semua transaksi yg dibayar
     * mendapat commission_paid_at yang sama (atau sangat berdekatan).
     * Kita group berdasarkan DATE(commission_paid_at) untuk status paid,
     * dan satu group "Belum Dibayar" untuk status pending.
     */

    // A. Batch PAID dalam bulan ini
    // "Bulan" dilihat dari tanggal transaksi (created_at), bukan paid_at
    $sqlPaid = "
        SELECT
            DATE(t.commission_paid_at) as tgl_bayar_key,
            t.commission_paid_at,
            MIN(t.created_at)  as tgl_trx_mulai,
            MAX(t.created_at)  as tgl_trx_akhir,
            COUNT(t.id)        as total_pasien,
            SUM(t.omset_terapis) as total_komisi
        FROM transactions t
        WHERE t.terapis_id = ?
          AND t.commission_status = 'paid'
          AND DATE_FORMAT(t.created_at,'%Y-%m') = ?
        GROUP BY DATE(t.commission_paid_at), t.commission_paid_at
        ORDER BY t.commission_paid_at DESC
    ";
    $stmtPaid = $pdo->prepare($sqlPaid);
    $stmtPaid->execute([$terapis_id, $view_bulan]);
    $batchPaid = $stmtPaid->fetchAll();

    // B. Transaksi PENDING bulan ini (1 batch)
    $sqlPend = "
        SELECT
            MIN(t.created_at) as tgl_trx_mulai,
            MAX(t.created_at) as tgl_trx_akhir,
            COUNT(t.id)       as total_pasien,
            SUM(t.omset_terapis) as total_komisi
        FROM transactions t
        WHERE t.terapis_id = ?
          AND t.commission_status = 'pending'
          AND DATE_FORMAT(t.created_at,'%Y-%m') = ?
    ";
    $stmtPend = $pdo->prepare($sqlPend);
    $stmtPend->execute([$terapis_id, $view_bulan]);
    $batchPending = $stmtPend->fetch();

    // C. Detail transaksi per batch (paid): group by tgl_bayar_key
    $sqlDetPaid = "
        SELECT t.created_at, t.nama_pelanggan, t.omset_terapis,
               p.nama_paket, DATE(t.commission_paid_at) as bayar_key
        FROM transactions t
        LEFT JOIN packages p ON t.package_id=p.id
        WHERE t.terapis_id=? AND t.commission_status='paid'
          AND DATE_FORMAT(t.created_at,'%Y-%m')=?
        ORDER BY t.created_at DESC
    ";
    $stmtDPaid = $pdo->prepare($sqlDetPaid);
    $stmtDPaid->execute([$terapis_id, $view_bulan]);
    $detailPaid = $stmtDPaid->fetchAll();

    $groupedPaid = [];
    foreach ($detailPaid as $d) { $groupedPaid[$d['bayar_key']][] = $d; }

    // D. Detail transaksi PENDING
    $sqlDetPend = "
        SELECT t.created_at, t.nama_pelanggan, t.omset_terapis, p.nama_paket
        FROM transactions t
        LEFT JOIN packages p ON t.package_id=p.id
        WHERE t.terapis_id=? AND t.commission_status='pending'
          AND DATE_FORMAT(t.created_at,'%Y-%m')=?
        ORDER BY t.created_at DESC
    ";
    $stmtDP = $pdo->prepare($sqlDetPend);
    $stmtDP->execute([$terapis_id, $view_bulan]);
    $detailPending = $stmtDP->fetchAll();

    // E. Potongan applied: group by DATE(applied_at)
    $appliedByDate = [];
    foreach ($potonganApplied as $ap) {
        $key = date('Y-m-d', strtotime($ap['applied_at']));
        $appliedByDate[$key][] = $ap;
    }

    $dateObj   = DateTime::createFromFormat('!Y-m', $view_bulan);
    $namaBulan = $dateObj->format('F Y');
}
// ================================================================
// LOGIC: TAMPILAN AWAL (list bulan)
// ================================================================
else {
    $sqlMonthly = "
        SELECT DATE_FORMAT(t.created_at,'%Y-%m') as kode_bulan,
               MAX(DATE_FORMAT(t.created_at,'%Y')) as tahun,
               MAX(DATE_FORMAT(t.created_at,'%m')) as bulan_angka,
               SUM(t.omset_terapis) as total_omset,
               COUNT(t.id) as total_pasien
        FROM transactions t
        WHERE t.terapis_id=?
        GROUP BY DATE_FORMAT(t.created_at,'%Y-%m')
        ORDER BY kode_bulan DESC
    ";
    $stmt = $pdo->prepare($sqlMonthly);
    $stmt->execute([$terapis_id]);
    $monthlyData = $stmt->fetchAll();

    // Total potongan applied per bulan (pakai bulan dari applied_at = bulan gajian)
    $stmtDM = $pdo->prepare("
        SELECT DATE_FORMAT(sd.applied_at,'%Y-%m') as kode_bulan,
               SUM(sd.jumlah) as total_potongan
        FROM salary_deductions sd
        WHERE sd.terapis_id=? AND sd.status='applied'
        GROUP BY DATE_FORMAT(sd.applied_at,'%Y-%m')
    ");
    $stmtDM->execute([$terapis_id]);
    $dedPerBulan = [];
    foreach ($stmtDM->fetchAll() as $row) {
        $dedPerBulan[$row['kode_bulan']] = (float)$row['total_potongan'];
    }
}

function bulanIndo($m) {
    $b=['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
        '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
    return $b[$m]??'';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Pendapatan</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .month-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;}
        .month-card{background:white;border-radius:12px;padding:20px;box-shadow:0 4px 6px rgba(0,0,0,.05);transition:.2s;cursor:pointer;border-top:5px solid #3498db;text-decoration:none;color:inherit;display:block;}
        .month-card:hover{transform:translateY(-5px);box-shadow:0 10px 15px rgba(0,0,0,.1);}
        .month-card.has-deduction{border-top-color:#e74c3c;}
        .month-title{font-size:18px;font-weight:bold;color:#2c3e50;margin-bottom:10px;}
        .month-omset{font-size:22px;font-weight:bold;color:#27ae60;margin-bottom:5px;}
        .month-detail{display:flex;justify-content:space-between;font-size:13px;color:#777;margin-top:8px;}

        /* Modal */
        .modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.5);backdrop-filter:blur(2px);}
        .modal-content{background:#fff;margin:5% auto;border-radius:10px;width:95%;max-width:720px;box-shadow:0 10px 25px rgba(0,0,0,.2);animation:slideDown .3s;}
        .modal-header{padding:15px 20px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;background:#f8f9fa;border-radius:10px 10px 0 0;}
        .modal-body{padding:0;max-height:60vh;overflow-y:auto;}
        .modal-footer-sum{padding:12px 20px;border-top:1px solid #eee;background:#f8f9fa;border-radius:0 0 10px 10px;}
        .close-btn{cursor:pointer;font-size:24px;color:#999;}
        @keyframes slideDown{from{transform:translateY(-20px);opacity:0;}to{transform:translateY(0);opacity:1;}}
        .table-detail{width:100%;border-collapse:collapse;font-size:13px;}
        .table-detail th{background:#f1f1f1;padding:10px;text-align:left;position:sticky;top:0;}
        .table-detail td{padding:10px;border-bottom:1px solid #eee;}
        .text-right{text-align:right;}

        /* Periode batch row */
        .batch-row{border-bottom:1px solid #eee;}
        .batch-row:hover{background:#fafafa;}
        .btn-detail{background:#3498db;color:white;border:none;padding:5px 12px;border-radius:4px;cursor:pointer;font-size:12px;}

        /* Status badge */
        .badge-pending{background:#fff3e0;color:#ef6c00;border:1px solid #ffe0b2;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:bold;}
        .badge-paid{background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:bold;}

        /* Potongan */
        .notif-potongan{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:15px 20px;margin-bottom:20px;}
        .notif-potongan h4{margin:0 0 10px;color:#856404;font-size:14px;}
        .pot-table{width:100%;border-collapse:collapse;font-size:13px;}
        .pot-table th{background:#fce9d0;padding:7px 10px;text-align:left;}
        .pot-table td{padding:7px 10px;border-bottom:1px solid #f5d5b5;}
        .deduction-section{background:#fff8f0;border-top:2px dashed #f0a500;padding:12px 16px;}
        .deduction-section h5{margin:0 0 8px;color:#c0392b;font-size:13px;}
        .ded-row{display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px dashed #f5d5b5;}
        .ded-row:last-child{border:none;}

        /* Summary */
        .summary-box{background:#eaf4fb;border:1px solid #b6d9f0;border-radius:6px;padding:10px 14px;font-size:13px;}
        .summary-box .row{display:flex;justify-content:space-between;padding:3px 0;}
        .summary-box .total{font-weight:bold;font-size:15px;border-top:1px solid #b6d9f0;margin-top:5px;padding-top:7px;}
        .minus{color:#c0392b;} .net{color:#27ae60;}

        /* History applied deductions table */
        .card-riwayat-pot{margin-top:30px;}
    </style>
</head>
<body>
<div class="container-layout">
    <div class="sidebar">
        <div class="sidebar-header"><h2>💆 TERAPIS PANEL</h2></div>
        <div class="sidebar-menu">
            <a href="dashboard_terapis.php"  class="menu-item"><i>📊</i> Dashboard</a>
            <a href="absensi_terapis.php"    class="menu-item"><i>📋</i> Absensi</a>
            <a href="riwayat_pendapatan.php" class="menu-item active"><i>💰</i> Riwayat Omset</a>
            <a href="profil_terapis.php"     class="menu-item"><i>👤</i> Profil Saya</a>
            <a href="skor_reward_terapis.php" class="menu-item"><i>⭐</i> Skor Reward</a>
            <a href="../auth/logout_system.php" class="menu-item" style="color:#c0392b;margin-top:50px;"><i>🚪</i> Logout</a>
        </div>
    </div>

    <div class="main-content">

        <!-- ============================================================
             NOTIFIKASI POTONGAN PENDING
             ============================================================ -->
        <?php if(count($potonganPending)>0): ?>
        <div class="notif-potongan">
            <h4>⚠️ Perhatian — Ada Potongan Gaji Yang Akan Dikenakan</h4>
            <p style="margin:0 0 10px;font-size:13px;color:#664d03;">
                Potongan berikut akan <strong>dikurangi otomatis</strong> dari komisimu saat admin melakukan pembayaran gaji.
            </p>
            <table class="pot-table">
                <thead><tr><th>#</th><th>Deskripsi</th><th>Diinput Oleh</th><th>Tanggal Input</th><th style="text-align:right;">Jumlah</th></tr></thead>
                <tbody>
                    <?php $no=1; foreach($potonganPending as $pot): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($pot['deskripsi']) ?></strong></td>
                        <td style="color:#777;"><?= htmlspecialchars($pot['admin_nama']??'Admin') ?></td>
                        <td style="color:#777;font-size:12px;"><?= date('d/m/Y H:i',strtotime($pot['created_at'])) ?></td>
                        <td style="text-align:right;font-weight:bold;color:#c0392b;">- Rp <?= number_format($pot['jumlah'],0,',','.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr style="background:#fce9d0;font-weight:bold;">
                    <td colspan="4" style="padding:8px 10px;text-align:right;">Total Potongan Pending:</td>
                    <td style="text-align:right;padding:8px 10px;color:#c0392b;">- Rp <?= number_format($totalPotPending,0,',','.') ?></td>
                </tr></tfoot>
            </table>
        </div>
        <?php endif; ?>

        <!-- ============================================================
             DAFTAR BULAN
             ============================================================ -->
        <?php if(!$view_bulan): ?>
        <div class="topbar"><h1>💰 Riwayat Pendapatan Bulanan</h1></div>

        <div class="month-grid">
            <?php if(count($monthlyData)>0): ?>
                <?php foreach($monthlyData as $m):
                    $pot      = $dedPerBulan[$m['kode_bulan']] ?? 0;
                    $netto    = $m['total_omset'] - $pot;
                    $adaPot   = $pot > 0;
                ?>
                <a href="?bulan=<?= $m['kode_bulan'] ?>" class="month-card <?= $adaPot ? 'has-deduction' : '' ?>">
                    <div class="month-title"><?= bulanIndo($m['bulan_angka']) ?> <?= $m['tahun'] ?></div>
                    <?php if($adaPot): ?>
                    <div style="font-size:12px;color:#aaa;text-decoration:line-through;margin-bottom:2px;">
                        Rp <?= number_format($m['total_omset'],0,',','.') ?>
                    </div>
                    <div class="month-omset">Rp <?= number_format($netto,0,',','.') ?></div>
                    <div style="font-size:12px;color:#c0392b;margin-bottom:4px;">
                        ✂️ Potongan: - Rp <?= number_format($pot,0,',','.') ?>
                    </div>
                    <?php else: ?>
                    <div class="month-omset">Rp <?= number_format($m['total_omset'],0,',','.') ?></div>
                    <?php endif; ?>
                    <div class="month-detail">
                        <span><i class="fas fa-user"></i> <?= $m['total_pasien'] ?> Pasien</span>
                        <span>Detail <i class="fas fa-arrow-right"></i></span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column:1/-1;text-align:center;padding:40px;color:#999;">Belum ada riwayat pendapatan.</div>
            <?php endif; ?>
        </div>

        <!-- ============================================================
             DETAIL PER BULAN (per batch pembayaran)
             ============================================================ -->
        <?php else: ?>
        <div class="topbar"><h1>Rincian: <?= $namaBulan ?></h1></div>
        <a href="riwayat_pendapatan.php" style="text-decoration:none;color:#555;display:inline-block;margin-bottom:15px;">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Bulan
        </a>

        <div class="card">
            <div class="card-header"><span>📋 Riwayat Pembayaran — <?= $namaBulan ?></span></div>
            <div class="table-container">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8f9fa;">
                            <th style="padding:12px;">Periode Transaksi</th>
                            <th class="text-center">Pasien</th>
                            <th class="text-right">Total Komisi</th>
                            <th class="text-right">Potongan</th>
                            <th class="text-right">Netto Diterima</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $grandKomisi=0; $grandPot=0; $modalIdx=0;

                    // ---- ROW: PENDING (jika ada) ----
                    if ($batchPending && $batchPending['total_pasien'] > 0):
                        $midPend = 'modal-pend-0';
                        $grandKomisi += $batchPending['total_komisi'];
                        $grandPot    += $totalPotPending;
                    ?>
                    <tr class="batch-row">
                        <td style="padding:12px;">
                            <strong><?= date('d M Y', strtotime($batchPending['tgl_trx_mulai'])) ?></strong>
                            &nbsp;&ndash;&nbsp;
                            <strong><?= date('d M Y', strtotime($batchPending['tgl_trx_akhir'])) ?></strong>
                            <br><small style="color:#aaa;">Belum ada tanggal bayar</small>
                        </td>
                        <td class="text-center"><?= $batchPending['total_pasien'] ?></td>
                        <td class="text-right" style="font-weight:bold;color:#2c3e50;">Rp <?= number_format($batchPending['total_komisi'],0,',','.') ?></td>
                        <td class="text-right" style="color:#c0392b;font-weight:bold;">
                            <?= $totalPotPending>0?'- Rp '.number_format($totalPotPending,0,',','.'):'<span style="color:#ccc;">-</span>' ?>
                        </td>
                        <td class="text-right" style="font-weight:bold;color:#27ae60;">
                            Rp <?= number_format($batchPending['total_komisi']-$totalPotPending,0,',','.') ?>
                        </td>
                        <td class="text-center"><span class="badge-pending">⏳ Menunggu</span></td>
                        <td class="text-center">
                            <button onclick="document.getElementById('<?= $midPend ?>').style.display='block'" class="btn-detail">👁️ Detail</button>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php // ---- ROWS: PAID (per batch) ----
                    foreach($batchPaid as $idx => $batch):
                        $mid       = 'modal-paid-'.$idx;
                        $bayarKey  = $batch['tgl_bayar_key'];
                        $detBatch  = $groupedPaid[$bayarKey] ?? [];
                        // Potongan applied pada tanggal bayar ini
                        $dedBatch  = $appliedByDate[$bayarKey] ?? [];
                        $potBatch  = array_sum(array_column($dedBatch, 'jumlah'));
                        $netto     = $batch['total_komisi'] - $potBatch;
                        $grandKomisi += $batch['total_komisi'];
                        $grandPot    += $potBatch;
                    ?>
                    <tr class="batch-row">
                        <td style="padding:12px;">
                            <strong><?= date('d M Y', strtotime($batch['tgl_trx_mulai'])) ?></strong>
                            &nbsp;&ndash;&nbsp;
                            <strong><?= date('d M Y', strtotime($batch['tgl_trx_akhir'])) ?></strong>
                            <br>
                            <small style="color:#27ae60;">
                                <i class="fas fa-check-circle"></i>
                                Dibayar: <?= date('d M Y', strtotime($batch['commission_paid_at'])) ?>
                            </small>
                        </td>
                        <td class="text-center"><?= $batch['total_pasien'] ?></td>
                        <td class="text-right" style="font-weight:bold;color:#2c3e50;">Rp <?= number_format($batch['total_komisi'],0,',','.') ?></td>
                        <td class="text-right" style="color:#c0392b;font-weight:bold;">
                            <?= $potBatch>0?'- Rp '.number_format($potBatch,0,',','.'):'<span style="color:#ccc;">-</span>' ?>
                        </td>
                        <td class="text-right" style="font-weight:bold;color:#27ae60;">Rp <?= number_format($netto,0,',','.') ?></td>
                        <td class="text-center"><span class="badge-paid">✅ Dibayar</span></td>
                        <td class="text-center">
                            <button onclick="document.getElementById('<?= $mid ?>').style.display='block'" class="btn-detail">👁️ Detail</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if($grandKomisi > 0): ?>
                    <tr style="background:#e8f4fc;font-weight:bold;">
                        <td colspan="2" style="padding:15px;text-align:right;">TOTAL BULAN INI:</td>
                        <td class="text-right" style="font-size:15px;color:#2c3e50;">Rp <?= number_format($grandKomisi,0,',','.') ?></td>
                        <td class="text-right" style="color:#c0392b;"><?= $grandPot>0?'- Rp '.number_format($grandPot,0,',','.'):'<span style="color:#ccc;">-</span>' ?></td>
                        <td class="text-right" style="font-size:16px;color:#27ae60;">Rp <?= number_format($grandKomisi-$grandPot,0,',','.') ?></td>
                        <td colspan="2"></td>
                    </tr>
                    <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;padding:20px;color:#999;">Tidak ada transaksi pada bulan ini.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================================
             MODAL PENDING
             ============================================================ -->
        <?php if($batchPending && $batchPending['total_pasien']>0): ?>
        <div id="modal-pend-0" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 style="margin:0;font-size:16px;">
                        Transaksi Belum Dibayar &mdash;
                        <?= date('d M Y',strtotime($batchPending['tgl_trx_mulai'])) ?> s/d <?= date('d M Y',strtotime($batchPending['tgl_trx_akhir'])) ?>
                    </h3>
                    <span class="close-btn" onclick="document.getElementById('modal-pend-0').style.display='none'">&times;</span>
                </div>
                <div class="modal-body">
                    <table class="table-detail">
                        <thead><tr><th>Tanggal &amp; Jam</th><th>Nama Customer</th><th>Paket</th><th class="text-right">Omset</th></tr></thead>
                        <tbody>
                            <?php foreach($detailPending as $d): ?>
                            <tr>
                                <td><?= date('d/m/Y',strtotime($d['created_at'])) ?><br><small style="color:#777;">Jam <?= date('H:i',strtotime($d['created_at'])) ?></small></td>
                                <td><?= htmlspecialchars($d['nama_pelanggan']) ?></td>
                                <td><?= htmlspecialchars($d['nama_paket']??'-') ?></td>
                                <td class="text-right" style="font-weight:bold;">Rp <?= number_format($d['omset_terapis'],0,',','.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if($totalPotPending>0): ?>
                    <div class="deduction-section">
                        <h5>✂️ Potongan Pending (akan dikurangi saat pembayaran)</h5>
                        <?php foreach($potonganPending as $pot): ?>
                        <div class="ded-row">
                            <span><strong><?= htmlspecialchars($pot['deskripsi']) ?></strong> <small style="color:#999;">(input: <?= date('d/m/Y',strtotime($pot['created_at'])) ?>)</small></span>
                            <span class="minus">- Rp <?= number_format($pot['jumlah'],0,',','.') ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-sum">
                    <div class="summary-box">
                        <div class="row"><span>Total Komisi (<?= $batchPending['total_pasien'] ?> pasien):</span><span>Rp <?= number_format($batchPending['total_komisi'],0,',','.') ?></span></div>
                        <?php if($totalPotPending>0): ?><div class="row"><span class="minus">Total Potongan:</span><span class="minus">- Rp <?= number_format($totalPotPending,0,',','.') ?></span></div><?php endif; ?>
                        <div class="row total"><span>💰 Estimasi Netto Diterima:</span><span class="net">Rp <?= number_format($batchPending['total_komisi']-$totalPotPending,0,',','.') ?></span></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================
             MODAL PAID (per batch)
             ============================================================ -->
        <?php foreach($batchPaid as $idx => $batch):
            $mid      = 'modal-paid-'.$idx;
            $bayarKey = $batch['tgl_bayar_key'];
            $detBatch = $groupedPaid[$bayarKey] ?? [];
            $dedBatch = $appliedByDate[$bayarKey] ?? [];
            $potBatch = array_sum(array_column($dedBatch,'jumlah'));
            $netto    = $batch['total_komisi'] - $potBatch;
        ?>
        <div id="<?= $mid ?>" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 style="margin:0;font-size:16px;">
                            Detail Pembayaran &mdash;
                            <?= date('d M Y',strtotime($batch['tgl_trx_mulai'])) ?> s/d <?= date('d M Y',strtotime($batch['tgl_trx_akhir'])) ?>
                        </h3>
                        <small style="color:#27ae60;">
                            <i class="fas fa-check-circle"></i>
                            Dibayar oleh admin pada: <strong><?= date('d M Y, H:i',strtotime($batch['commission_paid_at'])) ?></strong>
                        </small>
                    </div>
                    <span class="close-btn" onclick="document.getElementById('<?= $mid ?>').style.display='none'">&times;</span>
                </div>
                <div class="modal-body">
                    <table class="table-detail">
                        <thead><tr><th>Tanggal &amp; Jam</th><th>Nama Customer</th><th>Paket</th><th class="text-right">Omset</th></tr></thead>
                        <tbody>
                            <?php foreach($detBatch as $d): ?>
                            <tr>
                                <td><?= date('d/m/Y',strtotime($d['created_at'])) ?><br><small style="color:#777;">Jam <?= date('H:i',strtotime($d['created_at'])) ?></small></td>
                                <td><?= htmlspecialchars($d['nama_pelanggan']) ?></td>
                                <td><?= htmlspecialchars($d['nama_paket']??'-') ?></td>
                                <td class="text-right" style="font-weight:bold;">Rp <?= number_format($d['omset_terapis'],0,',','.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if(count($dedBatch)>0): ?>
                    <div class="deduction-section">
                        <h5>✂️ Potongan Yang Diterapkan Saat Pembayaran Ini</h5>
                        <?php foreach($dedBatch as $ded): ?>
                        <div class="ded-row">
                            <span><strong><?= htmlspecialchars($ded['deskripsi']) ?></strong> <small style="color:#999;">(diterapkan: <?= date('d/m/Y H:i',strtotime($ded['applied_at'])) ?>)</small></span>
                            <span class="minus">- Rp <?= number_format($ded['jumlah'],0,',','.') ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-sum">
                    <div class="summary-box">
                        <div class="row"><span>Total Komisi (<?= $batch['total_pasien'] ?> pasien):</span><span>Rp <?= number_format($batch['total_komisi'],0,',','.') ?></span></div>
                        <?php if($potBatch>0): ?><div class="row"><span class="minus">Potongan:</span><span class="minus">- Rp <?= number_format($potBatch,0,',','.') ?></span></div><?php endif; ?>
                        <div class="row total"><span>✅ Netto Diterima:</span><span class="net">Rp <?= number_format($netto,0,',','.') ?></span></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; // end $view_bulan ?>
    </div>
</div>

<script>
window.onclick = function(e){
    if(e.target.classList.contains('modal')) e.target.style.display='none';
}
</script>
</body>
</html>