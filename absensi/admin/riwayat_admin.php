<?php 
session_start();
require_once '../koneksi.php';
include '../header.php';
date_default_timezone_set('Asia/Jakarta');

$filter_type = $_GET['filter_type'] ?? 'mingguan';
$tgl_hari_ini = date('Y-m-d');

if ($filter_type === 'mingguan') {
    $tgl_dari = date('Y-m-d', strtotime('monday this week'));
    $tgl_sampai = date('Y-m-d', strtotime('sunday this week'));
    $label_periode = 'Minggu Ini (' . date('d M', strtotime($tgl_dari)) . ' - ' . date('d M Y', strtotime($tgl_sampai)) . ')';
} elseif ($filter_type === 'bulanan') {
    $tgl_dari = date('Y-m-01');
    $tgl_sampai = date('Y-m-t');
    $label_periode = 'Bulan Ini (' . date('F Y') . ')';
} elseif ($filter_type === 'tahunan') {
    $tgl_dari = date('Y-01-01');
    $tgl_sampai = date('Y-12-31');
    $label_periode = 'Tahun ' . date('Y');
} elseif ($filter_type === 'custom') {
    $tgl_dari   = $_GET['tgl_dari']   ?? date('Y-m-01');
    $tgl_sampai = $_GET['tgl_sampai'] ?? $tgl_hari_ini;
    $label_periode = date('d M Y', strtotime($tgl_dari)) . ' - ' . date('d M Y', strtotime($tgl_sampai));
} else {
    $tgl_dari = date('Y-m-d', strtotime('monday this week'));
    $tgl_sampai = date('Y-m-d', strtotime('sunday this week'));
    $label_periode = 'Minggu Ini';
}

$export_url = "export_excel_admin.php?filter_type={$filter_type}&tgl_dari={$tgl_dari}&tgl_sampai={$tgl_sampai}";
$branches = $pdo->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
?>

<style>
    .filter-btn { padding:10px 18px; border-radius:20px; font-size:13px; font-weight:700; text-decoration:none; background:#F2F2F7; color:#8E8E93; transition:0.2s; white-space: nowrap; }
    .filter-btn:hover { background:#E5E5EA; color:#1C1C1E; }
    .filter-btn.active { background:var(--primary); color:white; box-shadow:0 4px 10px rgba(88,86,214,0.3); }
    .btn-excel { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #1D6F42, #2E8B57); color: white; padding: 10px 18px; border-radius: 12px; text-decoration: none; font-size: 13px; font-weight: 700; box-shadow: 0 4px 12px rgba(29,111,66,0.3); transition: all 0.2s; white-space: nowrap; }
    .btn-excel:hover { background: linear-gradient(135deg, #155232, #1D6F42); box-shadow: 0 6px 18px rgba(29,111,66,0.4); transform: translateY(-1px); }
</style>

<div class="card slide-up">
    <div class="card-title" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <span><i class="fas fa-filter" style="color:var(--primary)"></i> Laporan Absensi</span>
        <a href="<?= $export_url ?>" class="btn-excel"><i class="fas fa-file-excel"></i> Export Excel Semua Cabang</a>
    </div>
    
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:15px;">
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="?filter_type=mingguan" class="filter-btn <?= $filter_type==='mingguan' ? 'active' : '' ?>"><i class="fas fa-calendar-week"></i> Mingguan</a>
            <a href="?filter_type=bulanan" class="filter-btn <?= $filter_type==='bulanan' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> Bulanan</a>
            <a href="?filter_type=tahunan" class="filter-btn <?= $filter_type==='tahunan' ? 'active' : '' ?>"><i class="fas fa-calendar"></i> Tahunan</a>
        </div>
        <form action="" method="GET" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; background:#F9F9F9; padding:8px 14px; border-radius:18px; border:1px solid rgba(0,0,0,0.05); width:fit-content;">
            <input type="hidden" name="filter_type" value="custom">
            <span style="font-size:13px; font-weight:700; color:#8E8E93; margin-right:4px;"><i class="fas fa-calendar-day"></i> Custom:</span>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="date" name="tgl_dari" value="<?= $tgl_dari ?>" required style="border:1px solid #E5E5EA; background:white; padding:8px 10px; border-radius:10px; font-size:12px; outline:none; font-weight:600; color:#1C1C1E; min-width:110px;">
                <span style="font-size:12px; color:#8E8E93; font-weight:700;">s/d</span>
                <input type="date" name="tgl_sampai" value="<?= $tgl_sampai ?>" required style="border:1px solid #E5E5EA; background:white; padding:8px 10px; border-radius:10px; font-size:12px; outline:none; font-weight:600; color:#1C1C1E; min-width:110px;">
                <button type="submit" style="background:var(--primary); color:white; border:none; padding:8px 16px; border-radius:10px; font-size:13px; font-weight:bold; cursor:pointer; transition:0.2s;">Terapkan</button>
            </div>
        </form>
    </div>

    <div style="font-size:13px; color:#8E8E93; font-weight:600; background:rgba(88, 86, 214, 0.08); padding:8px 15px; border-radius:10px; display:inline-block;">
        <i class="fas fa-info-circle" style="color:var(--primary);"></i> Menampilkan data: <b style="color:#1C1C1E;"><?= $label_periode ?></b>
    </div>
</div>

<?php foreach ($branches as $b): 
    $stmtStaf = $pdo->prepare("SELECT * FROM users WHERE branch_id = ? AND role NOT IN ('owner','admin') ORDER BY role, nama_lengkap");
    $stmtStaf->execute([$b['id']]);
    $staf_list = $stmtStaf->fetchAll();
    
    $export_url_cabang = "export_excel_admin.php?filter_type={$filter_type}&tgl_dari={$tgl_dari}&tgl_sampai={$tgl_sampai}&branch_id={$b['id']}";
?>
<div class="card slide-up" style="margin-bottom:25px;">
    <div style="display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg,#1C1C1E,#3A3A3C); padding:15px 20px; border-radius:14px; margin-bottom:18px; color:white; flex-wrap:wrap; gap:15px;">
        <div style="font-size:16px; font-weight:800;"><i class="fas fa-building" style="color:var(--primary); margin-right:8px;"></i> <?= htmlspecialchars($b['nama_cabang']) ?></div>
        <a href="<?= $export_url_cabang ?>" class="btn-excel" style="font-size:12px; padding:8px 14px;"><i class="fas fa-file-excel"></i> Export Cabang</a>
    </div>
    
    <div class="table-res">
        <table>
            <thead>
                <tr>
                    <th>Nama Staf</th>
                    <th>Role</th>
                    <th style="text-align:center;">Hadir</th>
                    <th style="text-align:center;">Tepat</th>
                    <th style="text-align:center;">Telat</th>
                    <th style="text-align:center;">Sakit/Izin</th>
                    <th style="text-align:center;">Skor</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if(!$staf_list): 
                ?>
                <tr><td colspan="7" style="text-align:center; padding:30px; color:#8E8E93; font-weight:600;">Tidak ada staf.</td></tr>
                <?php else:
                foreach ($staf_list as $u):
                    $uid = $u['id'];
                    $jml_h  = $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai'")->fetchColumn();
                    $jml_tp = $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Tepat Waktu'")->fetchColumn();
                    $jml_tl = $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Terlambat'")->fetchColumn();
                    $jml_si = $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran IN ('Sakit','Izin')")->fetchColumn();
                ?>
                <tr>
                    <td><b style="color:#1C1C1E; font-size:13px;"><?= htmlspecialchars($u['nama_lengkap']) ?></b></td>
                    <td><span style="font-size:10px; background:#F2F2F7; padding:4px 8px; border-radius:6px; font-weight:700; color:#8E8E93; text-transform:uppercase;"><?= $u['role'] ?></span></td>
                    <td style="text-align:center;"><span style="background:#F2F2F7; color:#1C1C1E; padding:4px 10px; border-radius:20px; font-weight:800; font-size:12px;"><?= $jml_h ?></span></td>
                    <td style="text-align:center;"><span style="background:#E2F9E9; color:var(--success); padding:4px 10px; border-radius:20px; font-weight:800; font-size:12px;"><?= $jml_tp ?></span></td>
                    <td style="text-align:center;"><span style="background:#FFE5E5; color:var(--danger); padding:4px 10px; border-radius:20px; font-weight:800; font-size:12px;"><?= $jml_tl ?></span></td>
                    <td style="text-align:center;"><span style="background:#FFF5F5; color:#FF9500; padding:4px 10px; border-radius:20px; font-weight:800; font-size:12px;"><?= $jml_si ?></span></td>
                    <td style="text-align:center;"><b style="color:<?= $u['credit_score'] < 80 ? 'var(--danger)' : 'var(--warning)' ?>; font-size:14px;"><?= $u['credit_score'] ?></b></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php include '../footer.php'; ?>