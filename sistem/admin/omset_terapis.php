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
// POST ACTIONS (Bayar, Tambah Potongan, Hapus Potongan)
// ================================================================
if (isset($_POST['action']) && $_POST['action'] == 'pay_accumulation') {
    $p_terapis_id = (int)$_POST['terapis_id'];
    try {
        $pdo->beginTransaction();
        $sqlUpd     = "UPDATE transactions SET commission_status='paid', commission_paid_at=NOW() WHERE terapis_id=? AND commission_status='pending'";
        $execParams = [$p_terapis_id];
        if ($branch_filter != 'all') { $sqlUpd .= " AND branch_id=?"; $execParams[] = (int)$branch_filter; }
        $stmtUpd = $pdo->prepare($sqlUpd);
        $stmtUpd->execute($execParams);
        $count = $stmtUpd->rowCount();
        
        $pdo->prepare("UPDATE salary_deductions SET status='applied', applied_at=NOW() WHERE terapis_id=? AND status='pending'")->execute([$p_terapis_id]);
        $pdo->commit();
        echo "<script>sessionStorage.setItem('swal_msg', 'Sukses! $count transaksi dibayarkan.'); sessionStorage.setItem('swal_type', 'success'); window.location.href='omset_terapis.php?branch=$branch_filter&tab=history';</script>";
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>sessionStorage.setItem('swal_msg', 'Error: " . addslashes($e->getMessage()) . "'); sessionStorage.setItem('swal_type', 'error'); window.location.href='omset_terapis.php?branch=$branch_filter&tab=pending';</script>";
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'pay_all_branch') {
    $p_branch_id = (int)$_POST['branch_id'];
    try {
        $pdo->beginTransaction();
        $stmtIds = $pdo->prepare("SELECT DISTINCT t.terapis_id FROM transactions t WHERE t.commission_status='pending' AND t.branch_id=?");
        $stmtIds->execute([$p_branch_id]);
        $terapisIds = $stmtIds->fetchAll(PDO::FETCH_COLUMN);

        $totalTrx = 0; $now = date('Y-m-d H:i:s');
        foreach ($terapisIds as $tid) {
            $stmtU = $pdo->prepare("UPDATE transactions SET commission_status='paid', commission_paid_at=? WHERE terapis_id=? AND commission_status='pending'");
            $stmtU->execute([$now, $tid]);
            $totalTrx += $stmtU->rowCount();
            $pdo->prepare("UPDATE salary_deductions SET status='applied', applied_at=? WHERE terapis_id=? AND status='pending'")->execute([$now, $tid]);
        }
        $pdo->commit();
        $jml = count($terapisIds);
        echo "<script>sessionStorage.setItem('swal_msg', 'Sukses! $jml terapis telah dibayarkan.'); sessionStorage.setItem('swal_type', 'success'); window.location.href='omset_terapis.php?branch=$p_branch_id&tab=history';</script>";
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>sessionStorage.setItem('swal_msg', 'Error: " . addslashes($e->getMessage()) . "'); sessionStorage.setItem('swal_type', 'error'); window.location.href='omset_terapis.php?branch=$branch_filter&tab=pending';</script>";
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'add_deduction') {
    $p_tid  = (int)$_POST['terapis_id']; $p_desc = trim($_POST['deskripsi']);
    $p_jml  = (float)str_replace(['.', ','], ['', '.'], $_POST['jumlah']);
    if ($p_tid && $p_desc && $p_jml > 0) {
        $pdo->prepare("INSERT INTO salary_deductions(terapis_id,admin_id,deskripsi,jumlah,status,created_at) VALUES(?,?,?,?,'pending',NOW())")->execute([$p_tid,$admin_id,$p_desc,$p_jml]);
        echo "<script>sessionStorage.setItem('swal_msg', 'Potongan ditambahkan.'); sessionStorage.setItem('swal_type', 'success'); window.location.href='omset_terapis.php?branch=$branch_filter&tab=pending';</script>"; exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'delete_deduction') {
    $pdo->prepare("DELETE FROM salary_deductions WHERE id=? AND status='pending'")->execute([(int)$_POST['deduction_id']]);
    echo "<script>sessionStorage.setItem('swal_msg', 'Potongan dihapus.'); sessionStorage.setItem('swal_type', 'success'); window.location.href='omset_terapis.php?branch=$branch_filter&tab=pending';</script>"; exit;
}

// ================================================================
// QUERY DATA UTAMA & LOGIKA FILTER TANGGAL
// ================================================================
$branches  = $pdo->query("SELECT * FROM branches ORDER BY nama_cabang ASC")->fetchAll();
$branchMap = []; foreach ($branches as $b) { $branchMap[$b['id']] = $b['nama_cabang']; }

$trxCond   = []; $trxParams = [];
$pay_date_filter = null;

if ($tab == 'pending') {
    $trxCond[] = "t.commission_status='pending'";
} else {
    // Tab Riwayat: Cari tanggal pembayaran terakhir jika filter kosong
    if (empty($_GET['pay_date'])) {
        $stmtMax = $pdo->query("SELECT MAX(DATE(commission_paid_at)) FROM transactions WHERE commission_status='paid'");
        $maxDate = $stmtMax->fetchColumn();
        $pay_date_filter = $maxDate ?: date('Y-m-d');
    } else {
        $pay_date_filter = $_GET['pay_date'];
    }
    
    $trxCond[] = "t.commission_status='paid'";
    $trxCond[] = "DATE(t.commission_paid_at) = ?";
    $trxParams[] = $pay_date_filter;
}

if ($branch_filter != 'all') { 
    $trxCond[] = "t.branch_id=?"; 
    $trxParams[] = (int)$branch_filter; 
}
$trxWhere = "WHERE " . implode(" AND ", $trxCond);

$terapisWhere = "u.role='terapis'"; $terapisParams = [];
if ($branch_filter != 'all') { $terapisWhere .= " AND u.home_branch_id=?"; $terapisParams[] = (int)$branch_filter; }

if ($tab == 'pending') {
    $brCond = ($branch_filter != 'all') ? "AND t.branch_id=".(int)$branch_filter : "";
    $sqlT = "SELECT u.id as terapis_id, u.nama_lengkap, u.home_branch_id, b.nama_cabang as home_branch_name,
                    COALESCE(SUM(CASE WHEN t.commission_status='pending' $brCond THEN 1 ELSE 0 END),0) as total_pasien,
                    COALESCE(SUM(CASE WHEN t.commission_status='pending' $brCond THEN t.omset_terapis ELSE 0 END),0) as total_komisi,
                    MIN(CASE WHEN t.commission_status='pending' $brCond THEN t.created_at END) as tgl_mulai,
                    MAX(CASE WHEN t.commission_status='pending' $brCond THEN t.created_at END) as tgl_akhir
             FROM users u LEFT JOIN branches b ON u.home_branch_id=b.id LEFT JOIN transactions t ON u.id=t.terapis_id
             WHERE $terapisWhere GROUP BY u.id, u.nama_lengkap, u.home_branch_id, b.nama_cabang HAVING total_komisi>0
             ORDER BY b.nama_cabang ASC, total_komisi DESC";
    $stmtT = $pdo->prepare($sqlT); $stmtT->execute($terapisParams);
} else {
    $sqlT = "SELECT t.terapis_id, MAX(u.nama_lengkap) as nama_lengkap, MAX(u.home_branch_id) as home_branch_id, MAX(b.nama_cabang) as home_branch_name,
                    COUNT(t.id) as total_pasien, SUM(t.omset_terapis) as total_komisi, MIN(t.created_at) as tgl_mulai, MAX(t.created_at) as tgl_akhir,
                    t.commission_paid_at as tgl_bayar
             FROM transactions t JOIN users u ON t.terapis_id=u.id LEFT JOIN branches b ON u.home_branch_id=b.id
             $trxWhere GROUP BY t.terapis_id, t.commission_paid_at ORDER BY t.commission_paid_at DESC, b.nama_cabang ASC";
    $stmtT = $pdo->prepare($sqlT); $stmtT->execute($trxParams);
}
$data = $stmtT->fetchAll();

$dataPerCabang = []; foreach ($data as $r) { $dataPerCabang[$r['home_branch_id']][] = $r; }
$orderedBranchIds = array_keys($dataPerCabang);

// Query Detail agar tidak loading lambat (Khusus Riwayat akan difilter juga tanggal bayarnya)
$detailWhere = "WHERE t.commission_status = " . ($tab == 'pending' ? "'pending'" : "'paid'");
$detailParams = [];
if ($tab == 'history' && $pay_date_filter) {
    $detailWhere .= " AND DATE(t.commission_paid_at) = ?";
    $detailParams[] = $pay_date_filter;
}
$sqlD = "SELECT t.id, t.terapis_id, t.branch_id as trx_branch_id, b_trx.nama_cabang as nama_cabang_trx,
                t.created_at, t.nama_pelanggan, t.omset_terapis, t.commission_status, t.commission_paid_at, p.nama_paket
         FROM transactions t JOIN branches b_trx ON t.branch_id=b_trx.id LEFT JOIN packages p ON t.package_id=p.id
         $detailWhere ORDER BY t.created_at DESC";
$stmtD = $pdo->prepare($sqlD);
$stmtD->execute($detailParams);
$rawDetails = $stmtD->fetchAll();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gaji Terapis - Bugar Refleksi</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .date-range { font-size: 11px; color: var(--text-muted); background: var(--bg-input); padding: 4px 8px; border-radius: 4px; display: inline-block; }
        .badge-cabang { padding: 3px 9px; border-radius: 10px; font-size: 11px; font-weight: 600; background: rgba(52, 152, 219, 0.1); color: var(--accent-blue); }
        .no-pasien { width: 24px; height: 24px; background: var(--bg-input); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold; color: var(--text-mid); }
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
            <a href="../auth/logout_system.php" class="menu-item" style="color:var(--accent-red); margin-top:50px;"><i>🚪</i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div><h1>💰 Penggajian Terapis Per Cabang</h1></div>
            <div class="topbar-right">
                <button class="theme-toggle no-print" onclick="toggleTheme()" id="theme-btn">
                    <i class="fas fa-moon"></i> Dark
                </button>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px; padding:15px;">
            <form method="GET" style="display:flex; gap:20px; align-items:flex-end; flex-wrap:wrap;">
                <input type="hidden" name="tab" value="<?= $tab ?>">
                
                <div>
                    <label style="display:block; font-size: 12px; color: var(--text-muted); margin-bottom: 5px; font-weight:bold;">Filter Cabang:</label>
                    <select name="branch" class="form-control" onchange="this.form.submit()" style="width:230px; padding:8px;">
                        <option value="all">-- Semua Cabang --</option>
                        <?php foreach($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $branch_filter==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['nama_cabang']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if($tab == 'history'): ?>
                <div>
                    <label style="display:block; font-size: 12px; color: var(--text-muted); margin-bottom: 5px; font-weight:bold;">Tanggal Dibayar:</label>
                    <input type="date" name="pay_date" value="<?= htmlspecialchars($pay_date_filter) ?>" class="form-control" style="width:160px; padding:8px;" onchange="this.form.submit()">
                </div>
                <?php endif; ?>

                <div>
                    <?php if($branch_filter != 'all' || ($tab == 'history' && isset($_GET['pay_date']))): ?>
                        <a href="?tab=<?= $tab ?>&branch=all" class="btn btn-secondary" style="padding: 8px 15px; color:var(--accent-red);">❌ Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="nav-tabs">
            <a href="?branch=<?= $branch_filter ?>&tab=pending" class="nav-link <?= $tab=='pending'?'active':'' ?>">⌛ Tagihan Belum Dibayar</a>
            <a href="?branch=<?= $branch_filter ?>&tab=history" class="nav-link <?= $tab=='history'?'active':'' ?>">✅ Riwayat Pembayaran</a>
        </div>

        <div class="card">
            <div class="table-container">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th>Nama Terapis</th>
                            <th>Periode Pasien</th>
                            <th class="text-center">Jml Pasien</th>
                            <th class="text-right">Total Komisi</th>
                            <?php if($tab=='pending'): ?>
                            <th class="text-right">Potongan</th>
                            <th class="text-right">Netto Bayar</th>
                            <?php else: ?><th>Waktu Pembayaran</th><?php endif; ?>
                            <th class="text-center">Aksi</th>
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
                                <button onclick="openPayAllModal(<?= $hbid ?>,'<?= htmlspecialchars(addslashes($namaC)) ?>')" class="btn btn-primary btn-sm" style="float:right;">
                                    💰 Bayar Semua Cabang
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <?php foreach($rows as $d): 
                            $mid=$globalIdx++; $modalID='modal-'.$mid; 
                            $ded=$groupedDed[$d['terapis_id']]??[]; 
                            $pot=array_sum(array_column($ded,'jumlah')); 
                            $netto=$d['total_komisi']-$pot; 
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['nama_lengkap']) ?></strong></td>
                            <td><div class="date-range"><?= $d['tgl_mulai']?date('d M Y',strtotime($d['tgl_mulai'])):'-' ?> - <?= $d['tgl_akhir']?date('d M Y',strtotime($d['tgl_akhir'])):'-' ?></div></td>
                            <td class="text-center"><?= $d['total_pasien'] ?> Orang</td>
                            <td class="text-right" style="font-weight:bold; color:<?= $tab=='pending'?'var(--accent-yellow2)':'var(--accent-green)' ?>;">
                                Rp <?= number_format($d['total_komisi'],0,',','.') ?>
                            </td>
                            <?php if($tab=='pending'): ?>
                            <td class="text-right" style="color:var(--accent-red); font-weight:bold;">
                                <?= $pot>0?'- Rp '.number_format($pot,0,',','.'):'<span style="color:var(--text-muted);">-</span>' ?>
                            </td>
                            <td class="text-right" style="font-weight:bold; color:var(--accent-green);">
                                Rp <?= number_format($netto,0,',','.') ?>
                            </td>
                            <?php else: ?><td><?= date('d/m/Y H:i',strtotime($d['tgl_bayar'])) ?></td><?php endif; ?>
                            
                            <td class="text-center">
                                <button onclick="document.getElementById('<?= $modalID ?>').style.display='block'" class="btn btn-secondary btn-sm">👁 Lihat</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <tr class="cabang-subtotal-row">
                            <td colspan="3">Subtotal <?= htmlspecialchars($namaC) ?></td>
                            <td class="text-right">Rp <?= number_format($subKomisi,0,',','.') ?></td>
                            <?php if($tab=='pending'): ?>
                            <td class="text-right" style="color:var(--accent-red);">- Rp <?= number_format($subPot,0,',','.') ?></td>
                            <td class="text-right" style="color:var(--accent-green);">Rp <?= number_format($subKomisi-$subPot,0,',','.') ?></td>
                            <td></td>
                            <?php else: ?><td colspan="2"></td><?php endif; ?>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding:50px;">
                                <i class="fas fa-folder-open" style="font-size:30px; color:var(--text-muted); margin-bottom:10px;"></i><br>
                                Tidak ada data tagihan atau riwayat pada tanggal ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="modal-pay-all-cabang" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header">
            <h3>💰 Konfirmasi Pembayaran</h3>
            <span class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</span>
        </div>
        <form method="POST" id="form-pay-all" onsubmit="return confirmPayAll();">
            <div class="modal-body" style="padding: 20px;">
                <input type="hidden" name="action" value="pay_all_branch">
                <input type="hidden" name="branch_id" id="payall-branch-id">
                <div class="payall-info-box">
                    <div class="row"><span>Cabang:</span><strong id="payall-branch-name" style="color:var(--text-dark);">-</strong></div>
                    <div class="row"><span>Total Terapis:</span><strong id="info-terapis" style="color:var(--text-dark);">-</strong></div>
                    <div class="row"><span>Total Transaksi:</span><strong id="info-trx" style="color:var(--text-dark);">-</strong></div>
                    <hr style="border:0; border-top:1px dashed var(--border-color); margin: 10px 0;">
                    <div class="row"><span>Total Komisi:</span><strong id="info-komisi" style="color:var(--accent-green); font-size:16px;">-</strong></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success" style="width:100%; padding: 12px; font-size: 14px;">🚀 Lunasi Semua Tagihan</button>
            </div>
        </form>
    </div>
</div>

<?php $globalIdx=0; foreach($orderedBranchIds as $hbid): foreach($dataPerCabang[$hbid] as $d): 
    $modalID='modal-'.$globalIdx++; 
    $dKey=($tab=='pending')?$d['terapis_id']:$d['terapis_id'].'_'.$d['tgl_bayar']; 
    $details=$groupedDetails[$dKey]??[]; 
    $ded=$groupedDed[$d['terapis_id']]??[]; 
    $pot=array_sum(array_column($ded,'jumlah')); 
    $netto=$d['total_komisi']-$pot; 
?>
<div id="<?= $modalID ?>" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Rincian: <strong style="color:var(--accent-yellow);"><?= htmlspecialchars($d['nama_lengkap']) ?></strong></h3>
            <span class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</span>
        </div>
        <div class="modal-body">
            <table class="table-detail" style="width:100%;">
                <thead>
                    <tr>
                        <th>#</th><th>Waktu Transaksi</th><th>Pasien</th><th>Paket</th><th>Cabang Layanan</th><th class="text-right">Komisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no=1; foreach($details as $det): ?>
                    <tr>
                        <td><span class="no-pasien"><?= $no++ ?></span></td>
                        <td><?= date('d/m/y H:i',strtotime($det['created_at'])) ?></td>
                        <td><?= htmlspecialchars($det['nama_pelanggan']) ?></td>
                        <td><?= htmlspecialchars($det['nama_paket']??'-') ?></td>
                        <td><span class="badge-cabang"><?= htmlspecialchars($det['nama_cabang_trx']) ?></span></td>
                        <td class="text-right" style="font-weight:bold; color:var(--accent-yellow2);">Rp <?= number_format($det['omset_terapis'],0,',','.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if($tab=='pending'): ?>
            <div class="deduction-section">
                <h4 style="margin-bottom:10px;">✂ Potongan Kasbon / Lainnya</h4>
                <?php if(count($ded)>0): ?>
                <table style="width:100%; margin-bottom:10px;">
                    <tbody>
                        <?php foreach($ded as $dd): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($dd['deskripsi']) ?></strong></td>
                            <td class="text-right" style="color:var(--accent-red); font-weight:bold;">- Rp <?= number_format($dd['jumlah'],0,',','.') ?></td>
                            <td class="text-right" style="width: 40px;">
                                <form method="POST" style="display:inline;" onsubmit="return confirmDeleteDeduction('<?= htmlspecialchars(addslashes($dd['deskripsi'])) ?>', '<?= $modalID ?>', this);">
                                    <input type="hidden" name="action" value="delete_deduction">
                                    <input type="hidden" name="deduction_id" value="<?= $dd['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">X</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <form method="POST" style="display:flex; gap:10px;">
                    <input type="hidden" name="action" value="add_deduction">
                    <input type="hidden" name="terapis_id" value="<?= $d['terapis_id'] ?>">
                    <input type="text" name="deskripsi" class="form-control" placeholder="Keterangan potongan" required style="flex:2;">
                    <input type="text" name="jumlah" class="form-control" placeholder="Nominal Rp" oninput="formatRupiah(this)" required style="flex:1;">
                    <button type="submit" class="btn btn-secondary">Tambah</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="modal-footer">
            <div class="pay-summary">
                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                    <span>Subtotal Komisi:</span><span>Rp <?= number_format($d['total_komisi'],0,',','.') ?></span>
                </div>
                <?php if($pot>0): ?>
                <div style="display:flex; justify-content:space-between; color:var(--accent-red); margin-bottom:5px;">
                    <span>Total Potongan:</span><span>- Rp <?= number_format($pot,0,',','.') ?></span>
                </div>
                <?php endif; ?>
                <hr style="border:0; border-top:1px solid var(--border-color); margin:8px 0;">
                <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:16px; color:var(--accent-green);">
                    <span>NETTO DITERIMA:</span><span>Rp <?= number_format($netto,0,',','.') ?></span>
                </div>
            </div>
            
            <?php if($tab=='pending'): ?>
            <form method="POST" onsubmit="return confirmPaySingle('<?= htmlspecialchars(addslashes($d['nama_lengkap'])) ?>', '<?= number_format($netto,0,',','.') ?>', this);">
                <input type="hidden" name="action" value="pay_accumulation">
                <input type="hidden" name="terapis_id" value="<?= $d['terapis_id'] ?>">
                <button type="submit" class="btn btn-success" style="padding: 12px 20px; font-size: 14px;">
                    💰 Bayar Rp <?= number_format($netto,0,',','.') ?>
                </button>
            </form>
            <?php else: ?>
            <div style="text-align:right;">
                <small>Tagihan ini telah dibayarkan pada:</small><br>
                <strong style="color:var(--text-dark);"><?= date('d M Y H:i',strtotime($d['tgl_bayar'])) ?></strong>
            </div>
            <?php endif; ?>
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
        Swal.fire({ 
            icon: type, 
            title: type === 'success' ? 'Berhasil!' : 'Perhatian', 
            text: msg, 
            timer: 3000, 
            showConfirmButton: false,
            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e1e1e' : '#fff',
            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
        });
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
        title: 'Konfirmasi Pembayaran',
        html: `Anda akan mencairkan komisi SEMUA terapis di cabang <b>"${branchName}"</b> sekarang. Lanjutkan?`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#27ae60', confirmButtonText: 'Ya, Bayar!', cancelButtonText: 'Batal',
        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e1e1e' : '#fff',
        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
    }).then((r) => { if (r.isConfirmed) document.getElementById('form-pay-all').submit(); });
    return false;
}

function confirmPaySingle(nama, nominal, form) {
    Swal.fire({
        title: 'Bayar Komisi',
        html: `Konfirmasi pembayaran untuk <b>${nama}</b> sebesar <b>Rp ${nominal}</b>?`,
        icon: 'question', showCancelButton: true, confirmButtonColor: '#27ae60', confirmButtonText: 'Ya, Bayar', cancelButtonText: 'Batal',
        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e1e1e' : '#fff',
        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
    }).then((r) => { if (r.isConfirmed) form.submit(); });
    return false;
}

function confirmDeleteDeduction(deskripsi, modalId, form) {
    document.getElementById(modalId).style.display = 'none';
    Swal.fire({
        title: 'Hapus Potongan?',
        html: `Potongan <b>"${deskripsi}"</b> akan dihapus dari tagihan.`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#e74c3c', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal',
        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e1e1e' : '#fff',
        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
    }).then((r) => {
        if (r.isConfirmed) form.submit();
        else document.getElementById(modalId).style.display = 'block';
    });
    return false;
}

window.onclick = function(e){ if(e.target.classList.contains('modal')) e.target.style.display='none'; }

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
</script>
</body>
</html>