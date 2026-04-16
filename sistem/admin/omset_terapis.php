<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { 
    header("Location: ../auth/login_system.php"); 
    exit; 
}

$admin_id      = $_SESSION['user_id'];
$branch_filter = $_GET['branch'] ?? 'all';
$tab           = $_GET['tab'] ?? 'pending';

// ================================================================
// POST: BAYAR SATU TERAPIS
// ================================================================
if (isset($_POST['action']) && $_POST['action'] == 'pay_accumulation') {
    $p_terapis_id = (int)$_POST['terapis_id'];
    try {
        $pdo->beginTransaction();
        $sqlUpd     = "UPDATE transactions SET commission_status='paid', commission_paid_at=NOW()
                       WHERE terapis_id=? AND commission_status='pending'";
        $execParams = [$p_terapis_id];
        if ($branch_filter != 'all') { $sqlUpd .= " AND branch_id=?"; $execParams[] = (int)$branch_filter; }
        $stmtUpd = $pdo->prepare($sqlUpd);
        $stmtUpd->execute($execParams);
        $count = $stmtUpd->rowCount();
        $pdo->prepare("UPDATE salary_deductions SET status='applied', applied_at=NOW()
                        WHERE terapis_id=? AND status='pending'")->execute([$p_terapis_id]);
        $pdo->commit();
        echo "<script>
            sessionStorage.setItem('swal_msg', 'Sukses! $count transaksi dibayarkan.');
            sessionStorage.setItem('swal_type', 'success');
            window.location.href='omset_terapis.php?branch=$branch_filter&tab=history';
        </script>";
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>
            sessionStorage.setItem('swal_msg', 'Error: " . addslashes($e->getMessage()) . "');
            sessionStorage.setItem('swal_type', 'error');
            window.location.href='omset_terapis.php?branch=$branch_filter&tab=pending';
        </script>";
    }
}

// ================================================================
// POST: BAYAR SEMUA TERAPIS SATU CABANG
// ================================================================
if (isset($_POST['action']) && $_POST['action'] == 'pay_all_branch') {
    $p_branch_id = (int)$_POST['branch_id'];
    try {
        $pdo->beginTransaction();
        $stmtIds = $pdo->prepare("SELECT DISTINCT t.terapis_id FROM transactions t WHERE t.commission_status='pending' AND t.branch_id=?");
        $stmtIds->execute([$p_branch_id]);
        $terapisIds = $stmtIds->fetchAll(PDO::FETCH_COLUMN);

        $totalTrx = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($terapisIds as $tid) {
            $stmtU = $pdo->prepare("UPDATE transactions SET commission_status='paid', commission_paid_at=? WHERE terapis_id=? AND commission_status='pending'");
            $stmtU->execute([$now, $tid]);
            $totalTrx += $stmtU->rowCount();
            $pdo->prepare("UPDATE salary_deductions SET status='applied', applied_at=? WHERE terapis_id=? AND status='pending'")->execute([$now, $tid]);
        }
        $pdo->commit();
        $jml = count($terapisIds);
        echo "<script>
            sessionStorage.setItem('swal_msg', 'Sukses! $jml terapis ($totalTrx transaksi) telah dibayarkan.');
            sessionStorage.setItem('swal_type', 'success');
            window.location.href='omset_terapis.php?branch=$p_branch_id&tab=history';
        </script>";
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>
            sessionStorage.setItem('swal_msg', 'Error: " . addslashes($e->getMessage()) . "');
            sessionStorage.setItem('swal_type', 'error');
            window.location.href='omset_terapis.php?branch=$branch_filter&tab=pending';
        </script>";
    }
}

// ================================================================
// POST: TAMBAH POTONGAN
// ================================================================
if (isset($_POST['action']) && $_POST['action'] == 'add_deduction') {
    $p_tid  = (int)$_POST['terapis_id'];
    $p_desc = trim($_POST['deskripsi']);
    $p_jml  = (float)str_replace(['.', ','], ['', '.'], $_POST['jumlah']);
    if ($p_tid && $p_desc && $p_jml > 0) {
        $pdo->prepare("INSERT INTO salary_deductions(terapis_id,admin_id,deskripsi,jumlah,status,created_at)
                        VALUES(?,?,?,?,'pending',NOW())")->execute([$p_tid,$admin_id,$p_desc,$p_jml]);
        echo "<script>
            sessionStorage.setItem('swal_msg', 'Potongan ditambahkan.');
            sessionStorage.setItem('swal_type', 'success');
            window.location.href='omset_terapis.php?branch=$branch_filter&tab=pending';
        </script>";
        exit;
    }
}

// ================================================================
// POST: HAPUS POTONGAN
// ================================================================
if (isset($_POST['action']) && $_POST['action'] == 'delete_deduction') {
    $pdo->prepare("DELETE FROM salary_deductions WHERE id=? AND status='pending'")->execute([(int)$_POST['deduction_id']]);
    echo "<script>
        sessionStorage.setItem('swal_msg', 'Potongan berhasil dihapus.');
        sessionStorage.setItem('swal_type', 'success');
        window.location.href='omset_terapis.php?branch=$branch_filter&tab=pending';
    </script>";
    exit;
}

// DATA UMUM
$branches  = $pdo->query("SELECT * FROM branches ORDER BY nama_cabang ASC")->fetchAll();
$branchMap = [];
foreach ($branches as $b) { $branchMap[$b['id']] = $b['nama_cabang']; }

$trxCond   = []; $trxParams = [];
$trxCond[] = ($tab == 'pending') ? "t.commission_status='pending'" : "t.commission_status='paid'";
if ($branch_filter != 'all') { $trxCond[] = "t.branch_id=?"; $trxParams[] = (int)$branch_filter; }
$trxWhere = "WHERE " . implode(" AND ", $trxCond);

$terapisWhere = "u.role='terapis'"; $terapisParams = [];
if ($branch_filter != 'all') { $terapisWhere .= " AND u.home_branch_id=?"; $terapisParams[] = (int)$branch_filter; }

// QUERY TERAPIS
if ($tab == 'pending') {
    $brCond = ($branch_filter != 'all') ? "AND t.branch_id=".(int)$branch_filter : "";
    $sqlT = "SELECT u.id as terapis_id, u.nama_lengkap, u.home_branch_id, b.nama_cabang as home_branch_name,
                    COALESCE(SUM(CASE WHEN t.commission_status='pending' $brCond THEN 1 ELSE 0 END),0) as total_pasien,
                    COALESCE(SUM(CASE WHEN t.commission_status='pending' $brCond THEN t.omset_terapis ELSE 0 END),0) as total_komisi,
                    MIN(CASE WHEN t.commission_status='pending' $brCond THEN t.created_at END) as tgl_mulai,
                    MAX(CASE WHEN t.commission_status='pending' $brCond THEN t.created_at END) as tgl_akhir
             FROM users u
             LEFT JOIN branches b ON u.home_branch_id=b.id
             LEFT JOIN transactions t ON u.id=t.terapis_id
             WHERE $terapisWhere
             GROUP BY u.id, u.nama_lengkap, u.home_branch_id, b.nama_cabang
             HAVING total_komisi>0
             ORDER BY b.nama_cabang ASC, total_komisi DESC";
    $stmtT = $pdo->prepare($sqlT); $stmtT->execute($terapisParams);
} else {
    $sqlT = "SELECT t.terapis_id, MAX(u.nama_lengkap) as nama_lengkap,
                    MAX(u.home_branch_id) as home_branch_id, MAX(b.nama_cabang) as home_branch_name,
                    COUNT(t.id) as total_pasien, SUM(t.omset_terapis) as total_komisi,
                    MIN(t.created_at) as tgl_mulai, MAX(t.created_at) as tgl_akhir,
                    t.commission_paid_at as tgl_bayar
             FROM transactions t
             JOIN users u ON t.terapis_id=u.id
             LEFT JOIN branches b ON u.home_branch_id=b.id
             $trxWhere
             GROUP BY t.terapis_id, t.commission_paid_at
             ORDER BY t.commission_paid_at DESC, b.nama_cabang ASC LIMIT 100";
    $stmtT = $pdo->prepare($sqlT); $stmtT->execute($trxParams);
}
$data = $stmtT->fetchAll();

$dataPerCabang = []; foreach ($data as $r) { $dataPerCabang[$r['home_branch_id']][] = $r; }
$orderedBranchIds = array_keys($dataPerCabang);

// QUERY DETAIL DENGAN CABANG TRANSAKSI
$sqlD = "SELECT t.id, t.terapis_id, t.branch_id as trx_branch_id, b_trx.nama_cabang as nama_cabang_trx,
                t.created_at, t.nama_pelanggan, t.omset_terapis, t.commission_status, t.commission_paid_at, p.nama_paket
         FROM transactions t JOIN branches b_trx ON t.branch_id=b_trx.id LEFT JOIN packages p ON t.package_id=p.id
         ORDER BY t.created_at DESC";
$rawDetails = $pdo->query($sqlD)->fetchAll();
$groupedDetails = [];
foreach ($rawDetails as $d) {
    $key = ($tab=='pending') ? $d['terapis_id'] : $d['terapis_id'].'_'.$d['commission_paid_at'];
    $groupedDetails[$key][] = $d;
}

$rawDed = $pdo->query("SELECT sd.*, u.nama_lengkap as admin_nama FROM salary_deductions sd LEFT JOIN users u ON sd.admin_id=u.id ORDER BY sd.created_at ASC")->fetchAll();
$groupedDed = []; foreach ($rawDed as $d) { $groupedDed[$d['terapis_id']][] = $d; }

$pendingPerCabang = [];
if ($tab == 'pending') {
    foreach ($pdo->query("SELECT branch_id, COUNT(DISTINCT terapis_id) as jml_terapis, COUNT(id) as jml_trx, SUM(omset_terapis) as total_komisi FROM transactions WHERE commission_status='pending' GROUP BY branch_id")->fetchAll() as $r) {
        $pendingPerCabang[$r['branch_id']] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Gaji Terapis - Per Cabang</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .nav-tabs{display:flex;border-bottom:2px solid #ddd;margin-bottom:20px;}
        .nav-link{padding:10px 20px;text-decoration:none;color:#555;border-bottom:3px solid transparent;font-weight:bold;}
        .nav-link.active{border-bottom-color:#3498db;color:#3498db;}
        .nav-link:hover{background:#f9f9f9;}
        .cabang-header-row td{background:#2c3e50;color:#fff;font-weight:bold;font-size:13px;padding:10px 14px;}
        .cabang-header-row.highlighted td{background:#1a6fa3;}
        .cabang-subtotal-row td{background:#f0f4f8;border-bottom:3px solid #bdc3c7;padding:8px 12px;font-size:12px;}
        .modal{display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.55);backdrop-filter:blur(2px);}
        .modal-content{background:#fff;margin:3% auto;border-radius:10px;width:92%;max-width:860px;box-shadow:0 6px 30px rgba(0,0,0,0.2);animation:fadeIn .25s;}
        .modal-header{padding:16px 22px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:flex-start;background:#f8f9fa;border-radius:10px 10px 0 0;}
        .modal-body{padding:0;max-height:56vh;overflow-y:auto;}
        .modal-footer{padding:14px 22px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center;background:#f8f9fa;border-radius:0 0 10px 10px;flex-wrap:wrap;gap:10px;}
        .table-detail{width:100%;border-collapse:collapse;font-size:13px;}
        .table-detail th{background:#f1f1f1;padding:10px 12px;text-align:left;position:sticky;top:0;border-bottom:2px solid #ddd;}
        .table-detail td{padding:9px 12px;border-bottom:1px solid #eee;vertical-align:middle;}
        .deduction-section{background:#fff8f0;border-top:2px dashed #f0a500;padding:14px 20px;}
        .pay-summary{background:#eaf4fb;border:1px solid #b6d9f0;border-radius:6px;padding:10px 14px;font-size:13px;flex:1;}
        .btn-pay-all{background:#8e44ad;color:white;border:none;padding:6px 15px;border-radius:5px;cursor:pointer;font-size:12px;font-weight:bold;}
        .btn-green{background:#27ae60;color:white;border:none;padding:9px 22px;font-weight:bold;border-radius:4px;cursor:pointer;font-size:14px;}
        .btn-red-sm{background:#e74c3c;color:white;border:none;padding:3px 8px;border-radius:3px;cursor:pointer;font-size:11px;}
        .btn-orange{background:#e67e22;color:white;border:none;padding:6px 14px;border-radius:4px;cursor:pointer;font-size:13px;}
        .date-range{font-size:12px;color:#555;background:#eee;padding:2px 6px;border-radius:4px;display:inline-block;}
        .badge-cabang{padding:3px 9px;border-radius:10px;font-size:11px;font-weight:600;background:#e3f2fd;color:#1565c0;}
        .no-pasien{width:26px;height:26px;background:#ecf0f1;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold;color:#555;}
        @keyframes fadeIn{from{opacity:0;transform:translateY(-18px);}to{opacity:1;transform:translateY(0);}}
    </style>
</head>
<body>
<div class="container-layout">
    <div class="sidebar">
        <div class="sidebar-header"><h2>⚡ ADMIN PANEL</h2><small>Bugar Refleksi System</small></div>
        <div class="sidebar-menu">
            <a href="dashboard_admin.php" class="menu-item"><i>🏠</i> Dashboard</a>
            <a href="data_keuangan.php"   class="menu-item"><i>💰</i> Data Keuangan</a>
            <a href="omset_terapis.php"   class="menu-item active"><i>🧘</i> Gaji Terapis</a>
            <a href="../auth/logout_system.php" class="menu-item" style="color:#c0392b;margin-top:50px;"><i>🚪</i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="topbar"><h1>💰 Penggajian Terapis Per Cabang</h1></div>

        <div class="card" style="margin-bottom:20px;padding:15px;">
            <form method="GET" style="display:flex;gap:15px;align-items:center;">
                <input type="hidden" name="tab" value="<?= $tab ?>">
                <strong>Filter Cabang Home:</strong>
                <select name="branch" class="form-control" onchange="this.form.submit()" style="width:230px;">
                    <option value="all">-- Semua Cabang --</option>
                    <?php foreach($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branch_filter==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['nama_cabang']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if($branch_filter!='all'): ?><a href="?tab=<?= $tab ?>&branch=all" style="color:red;text-decoration:none;font-weight:bold;">❌ Reset</a><?php endif; ?>
            </form>
        </div>

        <div class="nav-tabs">
            <a href="?branch=<?= $branch_filter ?>&tab=pending" class="nav-link <?= $tab=='pending'?'active':'' ?>">⌛ Tagihan Belum Dibayar</a>
            <a href="?branch=<?= $branch_filter ?>&tab=history" class="nav-link <?= $tab=='history'?'active':'' ?>">✅ Riwayat Pembayaran</a>
        </div>

        <div class="card">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Nama Terapis</th>
                        <th>Periode</th>
                        <th style="text-align:center;">Pasien</th>
                        <th style="text-align:right;">Total Komisi</th>
                        <?php if($tab=='pending'): ?>
                        <th style="text-align:right;">Potongan</th>
                        <th style="text-align:right;">Netto Bayar</th>
                        <?php else: ?><th>Tanggal Dibayar</th><?php endif; ?>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(count($data)>0): $globalIdx=0; foreach($orderedBranchIds as $hbid):
                    $rows=$dataPerCabang[$hbid]; $namaC=$branchMap[$hbid]??'Cabang #'.$hbid;
                    $subKomisi=0;$subPasien=0;$subPot=0; foreach($rows as $r){ 
                        $subKomisi+=$r['total_komisi']; $subPasien+=$r['total_pasien'];
                        if($tab=='pending') $subPot+=array_sum(array_column($groupedDed[$r['terapis_id']]??[],'jumlah'));
                    }
                ?>
                    <tr class="cabang-header-row">
                        <td colspan="<?= $tab=='history'?6:7 ?>">
                            🏠 <?= htmlspecialchars($namaC) ?> (<?= count($rows) ?> terapis)
                            <?php if($tab=='pending'&&$subKomisi>0): ?>
                            <button onclick="openPayAllModal(<?= $hbid ?>,'<?= htmlspecialchars(addslashes($namaC)) ?>')" class="btn-pay-all" style="float:right;">💰 Bayar Semua Cabang</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php foreach($rows as $d): $mid=$globalIdx++; $modalID='modal-'.$mid; $ded=$groupedDed[$d['terapis_id']]??[]; $pot=array_sum(array_column($ded,'jumlah')); $netto=$d['total_komisi']-$pot; ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td><strong><?= htmlspecialchars($d['nama_lengkap']) ?></strong></td>
                        <td><div class="date-range"><?= $d['tgl_mulai']?date('d M Y',strtotime($d['tgl_mulai'])):'-' ?> - <?= $d['tgl_akhir']?date('d M Y',strtotime($d['tgl_akhir'])):'-' ?></div></td>
                        <td style="text-align:center;"><?= $d['total_pasien'] ?> Org</td>
                        <td style="text-align:right;font-weight:bold;color:<?= $tab=='pending'?'#e67e22':'#27ae60' ?>;">Rp <?= number_format($d['total_komisi'],0,',','.') ?></td>
                        <?php if($tab=='pending'): ?>
                        <td style="text-align:right;color:#c0392b;font-weight:bold;"><?= $pot>0?'- Rp '.number_format($pot,0,',','.'):'<span style="color:#ccc;">-</span>' ?></td>
                        <td style="text-align:right;font-weight:bold;color:#27ae60;">Rp <?= number_format($netto,0,',','.') ?></td>
                        <?php else: ?><td><?= date('d/m/Y H:i',strtotime($d['tgl_bayar'])) ?></td><?php endif; ?>
                        <td style="text-align:center;"><button onclick="document.getElementById('<?= $modalID ?>').style.display='block'" class="btn-detail">👁 Lihat</button></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="cabang-subtotal-row">
                        <td colspan="3">Subtotal <?= htmlspecialchars($namaC) ?></td>
                        <td style="text-align:right;font-weight:bold;">Rp <?= number_format($subKomisi,0,',','.') ?></td>
                        <?php if($tab=='pending'): ?>
                        <td style="text-align:right;font-weight:bold;">- Rp <?= number_format($subPot,0,',','.') ?></td>
                        <td style="text-align:right;font-weight:bold;color:#27ae60;">Rp <?= number_format($subKomisi-$subPot,0,',','.') ?></td>
                        <td></td>
                        <?php else: ?><td colspan="2"></td><?php endif; ?>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7" style="text-align:center;padding:40px;">Tidak ada data tagihan.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modal-pay-all-cabang" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header"><h3>💰 Bayar Semua Terapis</h3><span class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</span></div>
        <form method="POST" id="form-pay-all" onsubmit="return confirmPayAll();">
            <input type="hidden" name="action" value="pay_all_branch"><input type="hidden" name="branch_id" id="payall-branch-id">
            <div class="payall-info-box">
                <div class="row"><span>Cabang:</span><strong id="payall-branch-name">-</strong></div>
                <div class="row"><span>Total Terapis:</span><strong id="info-terapis">-</strong></div>
                <div class="row"><span>Total Transaksi:</span><strong id="info-trx">-</strong></div>
                <div class="row total"><span>Total Komisi:</span><span class="net" id="info-komisi">-</span></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn-green">🚀 Konfirmasi & Bayar Semua</button></div>
        </form>
    </div>
</div>

<?php $globalIdx=0; foreach($orderedBranchIds as $hbid): foreach($dataPerCabang[$hbid] as $d): $modalID='modal-'.$globalIdx++; $dKey=($tab=='pending')?$d['terapis_id']:$d['terapis_id'].'_'.$d['tgl_bayar']; $details=$groupedDetails[$dKey]??[]; $ded=$groupedDed[$d['terapis_id']]??[]; $pot=array_sum(array_column($ded,'jumlah')); $netto=$d['total_komisi']-$pot; ?>
<div id="<?= $modalID ?>" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Rincian: <strong><?= htmlspecialchars($d['nama_lengkap']) ?></strong></h3><span class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</span></div>
        <div class="modal-body">
            <table class="table-detail">
                <thead><tr><th>#</th><th>Waktu</th><th>Pasien</th><th>Paket</th><th>Cabang Layanan</th><th style="text-align:right;">Komisi</th></tr></thead>
                <tbody>
                    <?php $no=1; foreach($details as $det): ?>
                    <tr><td><span class="no-pasien"><?= $no++ ?></span></td><td><?= date('d/m/y H:i',strtotime($det['created_at'])) ?></td><td><?= htmlspecialchars($det['nama_pelanggan']) ?></td><td><?= htmlspecialchars($det['nama_paket']??'-') ?></td><td><span class="badge-cabang"><?= htmlspecialchars($det['nama_cabang_trx']) ?></span></td><td style="text-align:right;font-weight:bold;color:#e67e22;">Rp <?= number_format($det['omset_terapis'],0,',','.') ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if($tab=='pending'): ?>
            <div class="deduction-section">
                <h4>✂ Potongan Gaji</h4>
                <?php if(count($ded)>0): ?>
                <table class="ded-table" style="width:100%;">
                    <tbody>
                        <?php foreach($ded as $dd): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($dd['deskripsi']) ?></strong></td>
                            <td style="text-align:right;color:#c0392b;font-weight:bold;">- Rp <?= number_format($dd['jumlah'],0,',','.') ?></td>
                            <td style="text-align:right;">
                                <form method="POST" style="display:inline;" onsubmit="return confirmDeleteDeduction('<?= htmlspecialchars(addslashes($dd['deskripsi'])) ?>', '<?= $modalID ?>', this);">
                                    <input type="hidden" name="action" value="delete_deduction"><input type="hidden" name="deduction_id" value="<?= $dd['id'] ?>">
                                    <button type="submit" class="btn-red-sm">X</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <form method="POST" style="margin-top:10px;display:flex;gap:5px;">
                    <input type="hidden" name="action" value="add_deduction"><input type="hidden" name="terapis_id" value="<?= $d['terapis_id'] ?>">
                    <input type="text" name="deskripsi" placeholder="Deskripsi" required style="flex:2;"><input type="text" name="jumlah" placeholder="Nominal" oninput="formatRupiah(this)" required style="flex:1;">
                    <button type="submit" class="btn-orange">+</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <div class="pay-summary">
                <div class="row"><span>Subtotal:</span><span>Rp <?= number_format($d['total_komisi'],0,',','.') ?></span></div>
                <?php if($pot>0): ?><div class="row" style="color:#c0392b;"><span>Potongan:</span><span>- Rp <?= number_format($pot,0,',','.') ?></span></div><?php endif; ?>
                <div class="total">Netto: Rp <?= number_format($netto,0,',','.') ?></div>
            </div>
            <?php if($tab=='pending'): ?>
            <form method="POST" onsubmit="return confirmPaySingle('<?= htmlspecialchars(addslashes($d['nama_lengkap'])) ?>', '<?= number_format($netto,0,',','.') ?>', this);">
                <input type="hidden" name="action" value="pay_accumulation"><input type="hidden" name="terapis_id" value="<?= $d['terapis_id'] ?>">
                <button type="submit" class="btn-green">💰 Bayar Rp <?= number_format($netto,0,',','.') ?></button>
            </form>
            <?php else: ?><div style="text-align:right;"><small>Lunas:</small><br><strong><?= date('d M Y H:i',strtotime($d['tgl_bayar'])) ?></strong></div><?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; endforeach; ?>

<script>
const pendingCabangData = <?= json_encode($pendingPerCabang) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const msg = sessionStorage.getItem('swal_msg');
    const type = sessionStorage.getItem('swal_type');
    if (msg) {
        Swal.fire({ icon: type, title: type === 'success' ? 'Berhasil!' : 'Perhatian', text: msg, timer: 3000, showConfirmButton: false });
        sessionStorage.removeItem('swal_msg'); sessionStorage.removeItem('swal_type');
    }
});

function formatRupiah(el){ let raw=el.value.replace(/\D/g,''); el.value=raw?parseInt(raw).toLocaleString('id-ID'):''; }

function openPayAllModal(branchId, branchName){
    const info = pendingCabangData[branchId] || {};
    document.getElementById('payall-branch-id').value = branchId;
    document.getElementById('payall-branch-name').textContent = branchName;
    document.getElementById('info-terapis').textContent = (info.jml_terapis || '-') + ' Terapis';
    document.getElementById('info-trx').textContent = (info.jml_trx || '-') + ' Transaksi';
    document.getElementById('info-komisi').textContent = 'Rp ' + (info.total_komisi ? parseInt(info.total_komisi).toLocaleString('id-ID') : '-');
    document.getElementById('modal-pay-all-cabang').style.display = 'block';
}

function confirmPayAll() {
    const branchName = document.getElementById('payall-branch-name').textContent;
    Swal.fire({
        title: 'Bayar Semua Terapis?',
        html: `Bayar SEMUA terapis di cabang <b>"${branchName}"</b>?`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#8e44ad', confirmButtonText: 'Ya, Bayar!', cancelButtonText: 'Batal'
    }).then((r) => { if (r.isConfirmed) document.getElementById('form-pay-all').submit(); });
    return false;
}

function confirmPaySingle(nama, nominal, form) {
    Swal.fire({
        title: 'Konfirmasi Bayar',
        html: `Bayar komisi <b>${nama}</b> sebesar <b>Rp ${nominal}</b>?`,
        icon: 'question', showCancelButton: true, confirmButtonColor: '#27ae60', confirmButtonText: 'Ya, Bayar', cancelButtonText: 'Batal'
    }).then((r) => { if (r.isConfirmed) form.submit(); });
    return false;
}

function confirmDeleteDeduction(deskripsi, modalId, form) {
    // Sembunyikan modal rincian agar tidak menumpuk saat konfirmasi hapus muncul
    document.getElementById(modalId).style.display = 'none';
    
    Swal.fire({
        title: 'Hapus Potongan?',
        html: `Yakin ingin menghapus potongan <b>"${deskripsi}"</b>?`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
    }).then((r) => {
        if (r.isConfirmed) {
            form.submit();
        } else {
            // Jika batal, tampilkan kembali modal rinciannya
            document.getElementById(modalId).style.display = 'block';
        }
    });
    return false;
}

window.onclick=function(e){if(e.target.classList.contains('modal'))e.target.style.display='none';}
</script>
</body>
</html>