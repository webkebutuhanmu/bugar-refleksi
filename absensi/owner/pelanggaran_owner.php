<?php 
include '../header.php'; 

// Notif aksi
$notif = $_GET['notif'] ?? '';
?>

<?php if($notif === 'approved'): ?>
<div style="background:#E2F9E9; border-left:4px solid var(--success); padding:13px 16px; border-radius:12px; margin-bottom:15px; font-size:13px; color:#1a7a35; font-weight:600; display:flex; align-items:center; gap:8px;">
    <i class="fas fa-check-circle" style="font-size:16px;"></i> Alasan SPV diterima. Credit score dikembalikan.
</div>
<?php elseif($notif === 'rejected'): ?>
<div style="background:#FFE5E5; border-left:4px solid var(--danger); padding:13px 16px; border-radius:12px; margin-bottom:15px; font-size:13px; color:#cc0000; font-weight:600; display:flex; align-items:center; gap:8px;">
    <i class="fas fa-times-circle" style="font-size:16px;"></i> Alasan SPV ditolak. Credit score dikurangi 5 poin permanen.
</div>
<?php endif; ?>

<div class="card slide-up">
    <div class="card-title" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <span><i class="fas fa-user-shield" style="color:var(--primary)"></i> Laporan Terlambat / Sakit / Izin (SPV)</span>
        <?php
        $jml_pending_spv = $pdo->query("
            SELECT COUNT(*) FROM attendance a 
            JOIN users u ON a.user_id = u.id 
            WHERE u.role = 'supervisor' 
              AND a.status_kehadiran IN ('Terlambat', 'Sakit', 'Izin') 
              AND a.status_alasan = 'pending'
        ")->fetchColumn();
        ?>
        <?php if($jml_pending_spv > 0): ?>
        <span style="background:var(--danger); color:white; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700;">
            <i class="fas fa-bell"></i> <?= $jml_pending_spv ?> Menunggu
        </span>
        <?php endif; ?>
    </div>

    <p style="font-size:13px; color:#8E8E93; margin:-5px 0 15px;">
        Masalah kehadiran Supervisor dilaporkan langsung ke Owner. Anda yang memutuskan approve atau tolak alasannya.
    </p>

    <div class="table-res">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>SPV</th>
                    <th>Cabang</th>
                    <th>Kategori</th>
                    <th>Alasan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmtSpv = $pdo->query("
                    SELECT a.*, u.nama_lengkap, b.nama_cabang, u.credit_score
                    FROM attendance a 
                    JOIN users u ON a.user_id = u.id 
                    LEFT JOIN branches b ON u.branch_id = b.id
                    WHERE u.role = 'supervisor' AND a.status_kehadiran IN ('Terlambat', 'Sakit', 'Izin')
                    ORDER BY a.id DESC
                ");
                $rows_spv = $stmtSpv->fetchAll();
                if(!$rows_spv):
                ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:30px; color:#8E8E93;">
                        <i class="fas fa-check-circle" style="color:var(--success); font-size:20px; display:block; margin-bottom:8px;"></i>
                        Tidak ada laporan masalah kehadiran SPV
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach($rows_spv as $s): ?>
                <tr>
                    <td>
                        <b style="color:#1C1C1E; font-size:13px;"><?= date('d M Y', strtotime($s['tanggal'])) ?></b>
                        <br><small style="color:#8E8E93;">Shift <?= $s['shift'] ?></small>
                    </td>
                    <td>
                        <b style="color:#1C1C1E;"><?= htmlspecialchars($s['nama_lengkap']) ?></b>
                        <br><small style="color:#8E8E93;">Skor: <b style="color:<?= $s['credit_score'] < 80 ? 'var(--danger)' : 'var(--warning)' ?>"><?= $s['credit_score'] ?></b></small>
                    </td>
                    <td><span style="font-size:12px; color:#8E8E93;"><?= htmlspecialchars($s['nama_cabang'] ?? '-') ?></span></td>
                    <td>
                        <span style="background:<?= in_array($s['status_kehadiran'], ['Sakit','Izin']) ? '#FFF5F5' : '#FFE5E5' ?>; color:var(--danger); padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold;">
                            <?= $s['status_kehadiran'] ?>
                        </span>
                        <?php if($s['status_kehadiran'] === 'Terlambat'): ?>
                            <br><small style="color:#8E8E93; font-weight:bold;"><?= $s['waktu_masuk'] ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:200px;">
                        <span style="font-size:12px; color:#555; font-style:italic;">
                            "<?= htmlspecialchars($s['alasan_terlambat'] ?: 'Tanpa alasan') ?>"
                        </span>
                    </td>
                    <td>
                        <?php if($s['status_alasan'] === 'approved'): ?>
                            <span class="status-pill pill-tepat"><i class="fas fa-check"></i> Diterima</span>
                        <?php elseif($s['status_alasan'] === 'rejected'): ?>
                            <span class="status-pill pill-telat"><i class="fas fa-times"></i> Ditolak</span>
                        <?php else: ?>
                            <span style="background:#FFF5E5; color:var(--warning); padding:5px 10px; border-radius:10px; font-size:11px; font-weight:700;">
                                <i class="fas fa-clock"></i> Pending
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($s['status_alasan'] === 'pending'): ?>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <a href="../proses.php?action=approve_spv&id=<?= $s['id'] ?>&status=approved" 
                               onclick="return konfirOwner(event, 'terima', '<?= htmlspecialchars($s['nama_lengkap']) ?>')"
                               style="background:var(--success); color:white; padding:6px 10px; border-radius:8px; text-decoration:none; font-size:11px; font-weight:700;">
                                <i class="fas fa-check"></i> Terima
                            </a>
                            <a href="../proses.php?action=approve_spv&id=<?= $s['id'] ?>&status=rejected"
                               onclick="return konfirOwner(event, 'tolak', '<?= htmlspecialchars($s['nama_lengkap']) ?>')"
                               style="background:var(--danger); color:white; padding:6px 10px; border-radius:8px; text-decoration:none; font-size:11px; font-weight:700;">
                                <i class="fas fa-times"></i> Tolak
                            </a>
                        </div>
                        <?php else: ?>
                            <span style="color:#C7C7CC; font-size:12px;">Sudah diproses</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card slide-up delay-1">
    <div class="card-title">
        <i class="fas fa-exclamation-circle" style="color:var(--danger)"></i> Log Terlambat / Sakit / Izin (Karyawan & Kasir)
    </div>

    <p style="font-size:13px; color:#8E8E93; margin:-5px 0 15px;">
        Rekap semua pengajuan karyawan beserta keputusan yang telah diambil oleh Supervisor.
    </p>

    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:15px;">
        <a href="?filter=all" style="padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; background:<?= (!isset($_GET['filter']) || $_GET['filter']=='all') ? 'var(--primary)' : '#F2F2F7' ?>; color:<?= (!isset($_GET['filter']) || $_GET['filter']=='all') ? 'white' : '#8E8E93' ?>;">Semua</a>
        <a href="?filter=pending" style="padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; background:<?= (isset($_GET['filter']) && $_GET['filter']=='pending') ? 'var(--warning)' : '#F2F2F7' ?>; color:<?= (isset($_GET['filter']) && $_GET['filter']=='pending') ? 'white' : '#8E8E93' ?>;">Pending</a>
        <a href="?filter=approved" style="padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; background:<?= (isset($_GET['filter']) && $_GET['filter']=='approved') ? 'var(--success)' : '#F2F2F7' ?>; color:<?= (isset($_GET['filter']) && $_GET['filter']=='approved') ? 'white' : '#8E8E93' ?>;">Diterima</a>
        <a href="?filter=rejected" style="padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; background:<?= (isset($_GET['filter']) && $_GET['filter']=='rejected') ? 'var(--danger)' : '#F2F2F7' ?>; color:<?= (isset($_GET['filter']) && $_GET['filter']=='rejected') ? 'white' : '#8E8E93' ?>;">Ditolak</a>
    </div>

    <div class="table-res">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Karyawan</th>
                    <th>Cabang</th>
                    <th>Kategori</th>
                    <th>Alasan</th>
                    <th>Tindakan SPV</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $filter = $_GET['filter'] ?? 'all';
                $where_filter = "";
                if($filter === 'pending')   $where_filter = "AND a.status_alasan = 'pending'";
                if($filter === 'approved')  $where_filter = "AND a.status_alasan = 'approved'";
                if($filter === 'rejected')  $where_filter = "AND a.status_alasan = 'rejected'";

                $stmtKary = $pdo->query("
                    SELECT a.*, u.nama_lengkap, b.nama_cabang, u.credit_score, u.role
                    FROM attendance a 
                    JOIN users u ON a.user_id = u.id 
                    LEFT JOIN branches b ON u.branch_id = b.id
                    WHERE u.role NOT IN ('supervisor', 'owner') 
                      AND a.status_kehadiran IN ('Terlambat', 'Sakit', 'Izin')
                      $where_filter
                    ORDER BY a.id DESC
                ");
                $rows_kary = $stmtKary->fetchAll();

                if(!$rows_kary):
                ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding:30px; color:#8E8E93;">
                        <i class="fas fa-check-circle" style="color:var(--success); font-size:20px; display:block; margin-bottom:8px;"></i>
                        Tidak ada data pelanggaran / pengajuan
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach($rows_kary as $p): ?>
                <tr>
                    <td>
                        <b style="color:#1C1C1E; font-size:13px;"><?= date('d M Y', strtotime($p['tanggal'])) ?></b>
                        <br><small style="color:#8E8E93;">Shift <?= $p['shift'] ?></small>
                    </td>
                    <td>
                        <b style="color:#1C1C1E;"><?= htmlspecialchars($p['nama_lengkap']) ?></b>
                        <br>
                        <span style="font-size:10px; background:#F2F2F7; padding:2px 6px; border-radius:6px; text-transform:uppercase; font-weight:700; color:#8E8E93;"><?= $p['role'] ?></span>
                        <small style="color:#8E8E93;"> · Skor: <b style="color:<?= $p['credit_score'] < 80 ? 'var(--danger)' : 'var(--warning)' ?>"><?= $p['credit_score'] ?></b></small>
                    </td>
                    <td><span style="font-size:12px; color:#8E8E93;"><?= htmlspecialchars($p['nama_cabang'] ?? '-') ?></span></td>
                    <td>
                        <span style="background:<?= in_array($p['status_kehadiran'], ['Sakit','Izin']) ? '#FFF5F5' : '#FFE5E5' ?>; color:var(--danger); padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold;">
                            <?= $p['status_kehadiran'] ?>
                        </span>
                        <?php if($p['status_kehadiran'] === 'Terlambat'): ?>
                            <br><small style="color:#8E8E93; font-weight:bold;"><?= $p['waktu_masuk'] ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:200px;">
                        <span style="font-size:12px; color:#555; font-style:italic;">
                            "<?= htmlspecialchars($p['alasan_terlambat'] ?: 'Tanpa alasan') ?>"
                        </span>
                    </td>
                    <td>
                        <?php if($p['status_alasan'] === 'approved'): ?>
                            <div>
                                <span class="status-pill pill-tepat"><i class="fas fa-check"></i> Diterima SPV</span>
                                <div style="font-size:11px; color:var(--success); margin-top:3px;">+5 skor dikembalikan</div>
                            </div>
                        <?php elseif($p['status_alasan'] === 'rejected'): ?>
                            <div>
                                <span class="status-pill pill-telat"><i class="fas fa-times"></i> Ditolak SPV</span>
                                <div style="font-size:11px; color:var(--danger); margin-top:3px;">-5 skor (tetap)</div>
                            </div>
                        <?php else: ?>
                            <span style="background:#FFF5E5; color:var(--warning); padding:5px 10px; border-radius:10px; font-size:11px; font-weight:700;">
                                <i class="fas fa-hourglass-half"></i> Menunggu SPV
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../footer.php'; ?>

<script>
function konfirOwner(event, tipe, nama) {
    event.preventDefault();
    const url = event.currentTarget.href;
    const isTerima = (tipe === 'terima');
    Swal.fire({
        title: isTerima ? 'Terima Alasan SPV?' : 'Tolak Alasan SPV?',
        html: isTerima
            ? `Alasan <b>${nama}</b> akan <b style="color:var(--success)">diterima</b>.<br><small style="color:#8E8E93;">Credit score dikembalikan ke poin semula.</small>`
            : `Alasan <b>${nama}</b> akan <b style="color:var(--danger)">ditolak</b>.<br><small style="color:#8E8E93;">Credit score tetap terpotong 5 poin.</small>`,
        icon: isTerima ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: isTerima ? '#34C759' : '#FF3B30',
        cancelButtonColor: '#E5E5EA',
        cancelButtonText: '<span style="color:#1C1C1E; font-weight:bold;">Batal</span>',
        confirmButtonText: isTerima ? '<i class="fas fa-check"></i> Ya, Terima' : '<i class="fas fa-times"></i> Ya, Tolak'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = url;
    });
    return false;
}
</script>