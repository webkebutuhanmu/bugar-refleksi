<?php 
session_start();
require_once '../koneksi.php';
include '../header.php';

// ============================================================
// LOGIKA FILTER PERIODE
// ============================================================
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

$mode_detail = isset($_GET['detail_uid']) && is_numeric($_GET['detail_uid']);
$detail_uid  = (int)($_GET['detail_uid'] ?? 0);

// ── URL Export Excel (membawa filter aktif) ──────────────────
$export_url = "export_excel_owner.php?filter_type={$filter_type}&tgl_dari={$tgl_dari}&tgl_sampai={$tgl_sampai}";
?>

<style>
    .filter-btn { padding:10px 18px; border-radius:20px; font-size:13px; font-weight:700; text-decoration:none; background:#F2F2F7; color:#8E8E93; transition:0.2s; white-space: nowrap; }
    .filter-btn:hover { background:#E5E5EA; color:#1C1C1E; }
    .filter-btn.active { background:var(--primary); color:white; box-shadow:0 4px 10px rgba(88,86,214,0.3); }
    
    .stat-block { flex:1; min-width:85px; border-radius:14px; padding:15px 10px; text-align:center; cursor:pointer; transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 2px solid transparent; user-select: none; -webkit-tap-highlight-color: transparent; }
    .stat-block:hover { transform: translateY(-3px); box-shadow: 0 6px 15px rgba(0,0,0,0.06); }
    .stat-block:active { transform: scale(0.96); }
    .stat-block.active { border-color: currentColor; transform: scale(1.02); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    
    .data-row { transition: opacity 0.3s ease; }

    .btn-excel {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #1D6F42, #2E8B57);
        color: white;
        padding: 10px 18px;
        border-radius: 12px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(29,111,66,0.3);
        transition: all 0.2s;
        white-space: nowrap;
    }
    .btn-excel:hover { background: linear-gradient(135deg, #155232, #1D6F42); box-shadow: 0 6px 18px rgba(29,111,66,0.4); transform: translateY(-1px); }
    .btn-excel:active { transform: scale(0.97); }
</style>

<?php if ($mode_detail):
    // ============================================================
    // MODE DETAIL KARYAWAN/SPV 
    // ============================================================
    $stmtUser = $pdo->prepare("SELECT u.*, b.nama_cabang FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
    $stmtUser->execute([$detail_uid]);
    $dUser = $stmtUser->fetch();

    $stmtDetail = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal DESC, id DESC");
    $stmtDetail->execute([$detail_uid, $tgl_dari, $tgl_sampai]);
    $detail_rows = $stmtDetail->fetchAll();

    $total_h  = count($detail_rows);
    $total_tt = array_sum(array_map(fn($x) => $x['status_kehadiran'] === 'Tepat Waktu' ? 1 : 0, $detail_rows));
    $total_tl = array_sum(array_map(fn($x) => $x['status_kehadiran'] === 'Terlambat'   ? 1 : 0, $detail_rows));
    $total_sk = array_sum(array_map(fn($x) => $x['status_kehadiran'] === 'Sakit'       ? 1 : 0, $detail_rows));
    $total_iz = array_sum(array_map(fn($x) => $x['status_kehadiran'] === 'Izin'        ? 1 : 0, $detail_rows));

    // URL export khusus 1 orang (detail_uid)
    $export_url_detail = "export_excel_owner.php?filter_type={$filter_type}&tgl_dari={$tgl_dari}&tgl_sampai={$tgl_sampai}&detail_uid={$detail_uid}";
?>

<!-- Baris navigasi atas: Kembali + Tombol Export -->
<div style="margin-bottom:15px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
    <a href="riwayat_owner.php?filter_type=<?= $filter_type ?>&tgl_dari=<?= $tgl_dari ?>&tgl_sampai=<?= $tgl_sampai ?>"
       style="display:inline-flex; align-items:center; gap:8px; background:white; border:1.5px solid #E5E5EA; color:#1C1C1E; padding:10px 18px; border-radius:12px; text-decoration:none; font-size:13px; font-weight:700; box-shadow:0 2px 10px rgba(0,0,0,0.02);">
        <i class="fas fa-arrow-left" style="color:var(--primary);"></i> Kembali ke Daftar
    </a>

    <a href="<?= $export_url_detail ?>" class="btn-excel">
        <i class="fas fa-file-excel"></i> Export Excel (<?= htmlspecialchars($dUser['nama_lengkap']) ?>)
    </a>
</div>

<div class="card slide-up">
    <div style="display:flex; align-items:center; gap:18px; padding:18px; background:linear-gradient(135deg,#1C1C1E,#3A3A3C); border-radius:14px; margin-bottom:18px; color:white; flex-wrap:wrap;">
        <div style="width:60px; height:60px; background:rgba(255,255,255,0.15); border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid rgba(255,255,255,0.25);"><i class="fas fa-user" style="font-size:24px;"></i></div>
        <div>
            <div style="font-size:18px; font-weight:800;"><?= htmlspecialchars($dUser['nama_lengkap']) ?></div>
            <div style="font-size:12px; opacity:0.8;">@<?= htmlspecialchars($dUser['username']) ?> &nbsp;·&nbsp; <span style="text-transform:uppercase; font-weight:700;"><?= $dUser['role'] ?></span></div>
        </div>
        <div style="margin-left:auto; text-align:right;">
            <div style="font-size:11px; opacity:0.75; font-weight:700;">CREDIT SCORE</div>
            <div style="font-size:28px; font-weight:800; color:<?= $dUser['credit_score'] < 80 ? '#FF6B60' : '#FFD700' ?>;"><?= $dUser['credit_score'] ?></div>
        </div>
    </div>

    <div style="font-size:12px; font-weight:700; color:#8E8E93; margin-bottom:10px; letter-spacing:0.5px;"><i class="fas fa-hand-pointer"></i> KLIK BLOK UNTUK FILTER DATA:</div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:5px;">
        <div class="stat-block active" onclick="filterTable('all', this)" style="background:#F9F9F9; color:#1C1C1E;">
            <div style="font-size:26px; font-weight:800; color:var(--primary);"><?= $total_h ?></div>
            <div style="font-size:10px; font-weight:800; opacity:0.8; margin-top:2px;">SEMUA</div>
        </div>
        <div class="stat-block" onclick="filterTable('Tepat Waktu', this)" style="background:#E2F9E9; color:var(--success);">
            <div style="font-size:26px; font-weight:800;"><?= $total_tt ?></div>
            <div style="font-size:10px; font-weight:800; opacity:0.8; margin-top:2px;">TEPAT WAKTU</div>
        </div>
        <div class="stat-block" onclick="filterTable('Terlambat', this)" style="background:#FFE5E5; color:var(--danger);">
            <div style="font-size:26px; font-weight:800;"><?= $total_tl ?></div>
            <div style="font-size:10px; font-weight:800; opacity:0.8; margin-top:2px;">TERLAMBAT</div>
        </div>
        <div class="stat-block" onclick="filterTable('Sakit', this)" style="background:#FFF5F5; color:#FF9500;">
            <div style="font-size:26px; font-weight:800;"><?= $total_sk ?></div>
            <div style="font-size:10px; font-weight:800; opacity:0.8; margin-top:2px;">SAKIT</div>
        </div>
        <div class="stat-block" onclick="filterTable('Izin', this)" style="background:#F0EFFF; color:var(--primary-light);">
            <div style="font-size:26px; font-weight:800;"><?= $total_iz ?></div>
            <div style="font-size:10px; font-weight:800; opacity:0.8; margin-top:2px;">IZIN</div>
        </div>
    </div>
</div>

<div class="card slide-up delay-1">
    <div class="card-title" style="margin-bottom:20px; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <span><i class="fas fa-list-alt" style="color:var(--primary)"></i> Riwayat Lengkap Absensi <?= $label_periode ?></span>
        <?php if($detail_rows): ?>
        <a href="<?= $export_url_detail ?>" class="btn-excel" style="font-size:12px; padding:8px 14px;">
            <i class="fas fa-file-excel"></i> Export Excel
        </a>
        <?php endif; ?>
    </div>
    
    <?php if(!$detail_rows): ?>
        <div style="text-align:center; padding:30px; color:#8E8E93; font-weight:600;">Tidak ada riwayat absensi pada periode ini.</div>
    <?php else: ?>
    <div class="table-res">
        <table id="detailTable">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Shift</th>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Status</th>
                    <th>Alasan</th>
                    <th>Approval</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detail_rows as $dr): ?>
                <tr class="data-row" data-status="<?= htmlspecialchars($dr['status_kehadiran']) ?>">
                    <td><b style="color:#1C1C1E; font-size:13px;"><?= date('d M Y', strtotime($dr['tanggal'])) ?></b></td>
                    <td><span style="background:#F2F2F7; padding:4px 10px; border-radius:8px; font-weight:800; font-size:12px;">S<?= $dr['shift'] ?></span></td>
                    <td>
                        <?php if(!in_array($dr['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <span style="color:var(--success); font-weight:700;"><i class="fas fa-sign-in-alt"></i> <?= $dr['waktu_masuk'] ?></span>
                        <?php else: ?>
                            <span style="color:#C7C7CC; font-size:12px;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if(!in_array($dr['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <span style="color:var(--danger); font-weight:700;"><i class="fas fa-sign-out-alt"></i> <?= $dr['waktu_keluar'] ?? '--:--' ?></span>
                        <?php else: ?>
                            <span style="color:#C7C7CC; font-size:12px;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-pill <?= $dr['status_kehadiran'] === 'Tepat Waktu' ? 'pill-tepat' : ($dr['status_kehadiran'] === 'Terlambat' ? 'pill-telat' : '') ?>" 
                              style="<?= in_array($dr['status_kehadiran'],['Sakit','Izin']) ? 'background:#FFF5F5; color:var(--danger);' : '' ?>">
                              <?= $dr['status_kehadiran'] ?>
                        </span>
                    </td>
                    <td style="max-width:180px;">
                        <?php if(in_array($dr['status_kehadiran'], ['Terlambat','Sakit','Izin'])): ?>
                            <span style="font-size:12px; color:#555; font-style:italic;">"<?= htmlspecialchars($dr['alasan_terlambat'] ?? 'Tanpa alasan') ?>"</span>
                        <?php else: ?>
                            <span style="font-size:11px; color:#C7C7CC;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (in_array($dr['status_kehadiran'], ['Terlambat','Sakit','Izin'])): ?>
                            <?php if ($dr['status_alasan'] === 'approved'): ?><span class="status-pill pill-tepat"><i class="fas fa-check"></i> Diterima</span>
                            <?php elseif ($dr['status_alasan'] === 'rejected'): ?><span class="status-pill pill-telat"><i class="fas fa-times"></i> Ditolak</span>
                            <?php else: ?><span style="background:#FFF5E5; color:var(--warning); padding:4px 10px; border-radius:10px; font-size:11px; font-weight:700;">Pending</span><?php endif; ?>
                        <?php else: ?><span style="font-size:11px; color:#C7C7CC;">—</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr id="emptyRow" style="display:none;">
                    <td colspan="7" style="text-align:center; padding:40px; color:#8E8E93; font-weight:600; font-size:14px;">
                        Tidak ada data untuk status ini.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function filterTable(status, el) {
    document.querySelectorAll('.stat-block').forEach(b => b.classList.remove('active'));
    el.classList.add('active');

    const rows = document.querySelectorAll('.data-row');
    let count = 0;

    rows.forEach(row => {
        if (status === 'all' || row.getAttribute('data-status') === status) {
            row.style.display = '';
            row.style.opacity = '0';
            setTimeout(() => row.style.opacity = '1', 50);
            count++;
        } else {
            row.style.display = 'none';
        }
    });

    const emptyRow = document.getElementById('emptyRow');
    if (emptyRow) {
        emptyRow.style.display = (count === 0) ? '' : 'none';
    }
}
</script>

<?php else: 
// ============================================================
// MODE REKAP / SUMMARY (SEMUA CABANG & STAF)
// ============================================================
$branches = $pdo->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
?>

<div class="card slide-up">
    <div class="card-title" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <span><i class="fas fa-filter" style="color:var(--primary)"></i> Filter Periode Absensi</span>
        <!-- ══ TOMBOL EXPORT EXCEL SEMUA CABANG ══ -->
        <a href="<?= $export_url ?>" class="btn-excel">
            <i class="fas fa-file-excel"></i> Export Excel Semua Cabang
        </a>
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
                <input type="date" name="tgl_dari" value="<?= $tgl_dari ?>" required style="border:1px solid #E5E5EA; background:white; padding:8px 10px; border-radius:10px; font-size:12px; font-family:inherit; outline:none; font-weight:600; color:#1C1C1E; min-width:110px;">
                <span style="font-size:12px; color:#8E8E93; font-weight:700;">s/d</span>
                <input type="date" name="tgl_sampai" value="<?= $tgl_sampai ?>" required style="border:1px solid #E5E5EA; background:white; padding:8px 10px; border-radius:10px; font-size:12px; font-family:inherit; outline:none; font-weight:600; color:#1C1C1E; min-width:110px;">
                <button type="submit" style="background:var(--primary); color:white; border:none; padding:8px 16px; border-radius:10px; font-size:13px; font-weight:bold; cursor:pointer; transition:0.2s; white-space:nowrap;">Terapkan</button>
            </div>
        </form>
    </div>

    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <div style="font-size:13px; color:#8E8E93; font-weight:600; background:rgba(88, 86, 214, 0.08); padding:8px 15px; border-radius:10px; display:inline-block;">
            <i class="fas fa-info-circle" style="color:var(--primary);"></i> Menampilkan data: <b style="color:#1C1C1E;"><?= $label_periode ?></b>
        </div>
        <!-- Tombol export duplikat di bawah info periode, lebih mudah dilihat -->
        <a href="<?= $export_url ?>" class="btn-excel" style="font-size:12px; padding:8px 14px;">
            <i class="fas fa-download"></i> Download Excel (<?= $label_periode ?>)
        </a>
    </div>
</div>

<?php foreach ($branches as $b): 
    $stmtStaf = $pdo->prepare("SELECT * FROM users WHERE branch_id = ? AND role != 'owner' ORDER BY role, nama_lengkap");
    $stmtStaf->execute([$b['id']]);
    $staf_list = $stmtStaf->fetchAll();

    $cabang_total_hadir = $cabang_total_terlambat = $cabang_total_sakit = $cabang_total_izin = 0;
    foreach ($staf_list as $su) {
        $cabang_total_hadir     += $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id={$su['id']} AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai'")->fetchColumn();
        $cabang_total_terlambat += $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id={$su['id']} AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Terlambat'")->fetchColumn();
        $cabang_total_sakit     += $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id={$su['id']} AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Sakit'")->fetchColumn();
        $cabang_total_izin      += $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id={$su['id']} AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Izin'")->fetchColumn();
    }

    // URL export per-cabang
    $export_url_cabang = "export_excel_owner.php?filter_type={$filter_type}&tgl_dari={$tgl_dari}&tgl_sampai={$tgl_sampai}&branch_id={$b['id']}";
?>
<div class="card slide-up" style="margin-bottom:25px;">
    <div style="display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg,#1C1C1E,#3A3A3C); padding:15px 20px; border-radius:14px; margin-bottom:18px; color:white; flex-wrap:wrap; gap:15px;">
        <div style="font-size:16px; font-weight:800;"><i class="fas fa-building" style="color:var(--primary); margin-right:8px;"></i> <?= htmlspecialchars($b['nama_cabang']) ?></div>
        
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <div style="text-align:center; background:rgba(255,255,255,0.1); padding:8px 14px; border-radius:10px;"><div style="font-size:16px; font-weight:800;"><?= $cabang_total_hadir ?></div><div style="font-size:9px; font-weight:700;">TOTAL DATA</div></div>
            <div style="text-align:center; background:rgba(255,59,48,0.25); padding:8px 14px; border-radius:10px;"><div style="font-size:16px; font-weight:800; color:#FF6B60;"><?= $cabang_total_terlambat ?></div><div style="font-size:9px; font-weight:700;">TELAT</div></div>
            <div style="text-align:center; background:rgba(255,149,0,0.25); padding:8px 14px; border-radius:10px;"><div style="font-size:16px; font-weight:800; color:#FF9500;"><?= $cabang_total_sakit ?></div><div style="font-size:9px; font-weight:700;">SAKIT</div></div>
            <div style="text-align:center; background:rgba(88,86,214,0.3); padding:8px 14px; border-radius:10px;"><div style="font-size:16px; font-weight:800; color:#A4A3E3;"><?= $cabang_total_izin ?></div><div style="font-size:9px; font-weight:700;">IZIN</div></div>
            <!-- ══ TOMBOL EXPORT PER CABANG ══ -->
            <a href="<?= $export_url_cabang ?>"
               style="display:inline-flex; align-items:center; gap:6px; background:linear-gradient(135deg,#1D6F42,#2E8B57); color:white; padding:8px 14px; border-radius:10px; text-decoration:none; font-size:12px; font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,0.2); white-space:nowrap;">
                <i class="fas fa-file-excel"></i> Export
            </a>
        </div>
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
                    <th style="text-align:center;">Sakit</th>
                    <th style="text-align:center;">Izin</th>
                    <th style="text-align:center;">Skor</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if(!$staf_list): 
                ?>
                <tr><td colspan="9" style="text-align:center; padding:30px; color:#8E8E93; font-weight:600;">Tidak ada staf di cabang ini.</td></tr>
                <?php else:
                foreach ($staf_list as $u):
                    $uid = $u['id'];
                    $jml_h  = $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai'")->fetchColumn();
                    $jml_tp = $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Tepat Waktu'")->fetchColumn();
                    $jml_tl = $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Terlambat'")->fetchColumn();
                    $jml_sk = $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Sakit'")->fetchColumn();
                    $jml_iz = $pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Izin'")->fetchColumn();
                ?>
                <tr>
                    <td><b style="color:#1C1C1E; font-size:13px;"><?= htmlspecialchars($u['nama_lengkap']) ?></b></td>
                    <td><span style="font-size:10px; background:#F2F2F7; padding:4px 8px; border-radius:6px; font-weight:700; color:#8E8E93; text-transform:uppercase;"><?= $u['role'] ?></span></td>
                    
                    <td style="text-align:center;"><span style="background:#F2F2F7; color:#1C1C1E; padding:4px 10px; border-radius:20px; font-weight:800; font-size:12px;"><?= $jml_h ?></span></td>
                    <td style="text-align:center;"><span style="background:#E2F9E9; color:var(--success); padding:4px 10px; border-radius:20px; font-weight:800; font-size:12px;"><?= $jml_tp ?></span></td>
                    <td style="text-align:center;"><span style="background:#FFE5E5; color:var(--danger); padding:4px 10px; border-radius:20px; font-weight:800; font-size:12px;"><?= $jml_tl ?></span></td>
                    <td style="text-align:center;"><span style="background:#FFF5F5; color:#FF9500; padding:4px 10px; border-radius:20px; font-weight:800; font-size:12px;"><?= $jml_sk ?></span></td>
                    <td style="text-align:center;"><span style="background:#F0EFFF; color:var(--primary); padding:4px 10px; border-radius:20px; font-weight:800; font-size:12px;"><?= $jml_iz ?></span></td>
                    
                    <td style="text-align:center;"><b style="color:<?= $u['credit_score'] < 80 ? 'var(--danger)' : 'var(--warning)' ?>; font-size:14px;"><?= $u['credit_score'] ?></b></td>
                    
                    <td style="text-align:center;">
                        <div style="display:flex; gap:6px; justify-content:center; flex-wrap:wrap;">
                            <a href="riwayat_owner.php?detail_uid=<?= $u['id'] ?>&filter_type=<?= $filter_type ?>&tgl_dari=<?= $tgl_dari ?>&tgl_sampai=<?= $tgl_sampai ?>"
                               style="background:var(--primary); color:white; padding:7px 12px; border-radius:10px; text-decoration:none; font-size:12px; font-weight:bold; display:inline-flex; align-items:center; gap:4px; box-shadow:0 4px 10px rgba(88,86,214,0.3);">
                                <i class="fas fa-list"></i> Detail
                            </a>
                            <!-- ══ TOMBOL EXPORT PER ORANG ══ -->
                            <a href="export_excel_owner.php?filter_type=<?= $filter_type ?>&tgl_dari=<?= $tgl_dari ?>&tgl_sampai=<?= $tgl_sampai ?>&detail_uid=<?= $u['id'] ?>"
                               style="background:linear-gradient(135deg,#1D6F42,#2E8B57); color:white; padding:7px 12px; border-radius:10px; text-decoration:none; font-size:12px; font-weight:bold; display:inline-flex; align-items:center; gap:4px; box-shadow:0 4px 10px rgba(29,111,66,0.3);">
                                <i class="fas fa-file-excel"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; endif; ?>

<?php include '../footer.php'; ?>