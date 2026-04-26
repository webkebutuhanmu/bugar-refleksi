<?php 
include '../header.php'; 
$notif = $_GET['notif'] ?? '';
?>

<?php if($notif === 'approved'): ?>
<div style="background:#E2F9E9; border-left:4px solid var(--success); padding:13px 16px; border-radius:12px; margin-bottom:15px; font-size:13px; color:#1a7a35; font-weight:600; display:flex; align-items:center; gap:8px;">
    <i class="fas fa-check-circle" style="font-size:16px;"></i> Pengajuan diterima. Credit score dikembalikan +5 poin.
</div>
<?php elseif($notif === 'rejected'): ?>
<div style="background:#FFE5E5; border-left:4px solid var(--danger); padding:13px 16px; border-radius:12px; margin-bottom:15px; font-size:13px; color:#cc0000; font-weight:600; display:flex; align-items:center; gap:8px;">
    <i class="fas fa-times-circle" style="font-size:16px;"></i> Pengajuan ditolak. Credit score tetap berkurang.
</div>
<?php endif; ?>

<div class="card slide-up">
    <div class="card-title" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <span><i class="fas fa-gavel" style="color:var(--warning)"></i> Approval Terlambat / Sakit / Izin</span>
        <?php
        $jml_pending = $pdo->query("
            SELECT COUNT(*) FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE u.role NOT IN ('supervisor', 'owner')
              AND a.status_kehadiran IN ('Terlambat', 'Sakit', 'Izin')
              AND a.status_alasan = 'pending'
        ")->fetchColumn();
        ?>
        <?php if($jml_pending > 0): ?>
        <span style="background:var(--danger); color:white; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700;">
            <i class="fas fa-bell"></i> <?= $jml_pending ?> Menunggu
        </span>
        <?php endif; ?>
    </div>

    <p style="font-size:13px; color:#8E8E93; margin:-5px 0 15px;">
        Anda dapat menyetujui alasan <b>Karyawan & Kasir</b>. Keputusan Anda menentukan pengembalian skor mereka.
    </p>

    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:15px;">
        <a href="approval_spv.php?filter=all" style="padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; background:<?= (!isset($_GET['filter']) || $_GET['filter']=='all') ? 'var(--primary)' : '#F2F2F7' ?>; color:<?= (!isset($_GET['filter']) || $_GET['filter']=='all') ? 'white' : '#8E8E93' ?>;">Semua</a>
        <a href="approval_spv.php?filter=pending" style="padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; background:<?= (isset($_GET['filter']) && $_GET['filter']=='pending') ? 'var(--warning)' : '#F2F2F7' ?>; color:<?= (isset($_GET['filter']) && $_GET['filter']=='pending') ? 'white' : '#8E8E93' ?>;">Pending</a>
        <a href="approval_spv.php?filter=approved" style="padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; background:<?= (isset($_GET['filter']) && $_GET['filter']=='approved') ? 'var(--success)' : '#F2F2F7' ?>; color:<?= (isset($_GET['filter']) && $_GET['filter']=='approved') ? 'white' : '#8E8E93' ?>;">Diterima</a>
        <a href="approval_spv.php?filter=rejected" style="padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; background:<?= (isset($_GET['filter']) && $_GET['filter']=='rejected') ? 'var(--danger)' : '#F2F2F7' ?>; color:<?= (isset($_GET['filter']) && $_GET['filter']=='rejected') ? 'white' : '#8E8E93' ?>;">Ditolak</a>
    </div>

    <div class="table-res">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Staf</th>
                    <th>Cabang</th>
                    <th style="text-align:center;">Jam Masuk</th>
                    <th>Kategori</th>
                    <th>Alasan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $filter = $_GET['filter'] ?? 'all';
                $where_filter = "";
                if($filter === 'pending')  $where_filter = "AND a.status_alasan = 'pending'";
                if($filter === 'approved') $where_filter = "AND a.status_alasan = 'approved'";
                if($filter === 'rejected') $where_filter = "AND a.status_alasan = 'rejected'";

                $stmt = $pdo->query("
                    SELECT a.*, u.nama_lengkap, u.role, u.credit_score, b.nama_cabang
                    FROM attendance a
                    JOIN users u ON a.user_id = u.id
                    LEFT JOIN branches b ON u.branch_id = b.id
                    WHERE u.role NOT IN ('supervisor', 'owner')
                      AND a.status_kehadiran IN ('Terlambat', 'Sakit', 'Izin')
                      $where_filter
                    ORDER BY a.id DESC
                ");
                $rows = $stmt->fetchAll();

                if(!$rows):
                ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:40px 20px; color:#8E8E93;">
                        <div style="width:60px; height:60px; background:#E2F9E9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 12px;"><i class="fas fa-check" style="font-size:24px; color:var(--success);"></i></div>
                        <div style="font-weight:700; font-size:15px; color:#1C1C1E; margin-bottom:5px;">Semua Beres!</div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach($rows as $p): ?>
                <tr>
                    <td>
                        <b style="color:#1C1C1E; font-size:13px;"><?= date('d M Y', strtotime($p['tanggal'])) ?></b><br>
                        <small style="color:#8E8E93;">Shift <?= $p['shift'] ?></small>
                    </td>
                    <td>
                        <b style="color:#1C1C1E;"><?= htmlspecialchars($p['nama_lengkap']) ?></b><br>
                        <span style="font-size:10px; background:#F2F2F7; padding:2px 6px; border-radius:6px; text-transform:uppercase; font-weight:700; color:#8E8E93;"><?= $p['role'] ?></span>
                        <small style="color:#8E8E93;"> · Skor: <b style="color:<?= $p['credit_score'] < 80 ? 'var(--danger)' : 'var(--warning)' ?>"><?= $p['credit_score'] ?></b></small>
                    </td>
                    <td><span style="font-size:12px; color:#8E8E93;"><?= htmlspecialchars($p['nama_cabang'] ?? '-') ?></span></td>
                    
                    <td style="text-align:center;">
                        <?php if(in_array($p['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <span style="color:#C7C7CC; font-size:13px; font-weight:bold;">-</span>
                        <?php else: ?>
                            <span style="color:var(--danger); font-weight:800; font-size:13px; background:#FFE5E5; padding:4px 10px; border-radius:8px;">
                                <i class="fas fa-sign-in-alt"></i> <?= $p['waktu_masuk'] ?>
                            </span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <span style="background:<?= in_array($p['status_kehadiran'], ['Sakit','Izin']) ? '#FFF5F5' : '#FFE5E5' ?>; color:var(--danger); padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold;">
                            <?= $p['status_kehadiran'] ?>
                        </span>
                    </td>
                    <td style="max-width:200px;"><span style="font-size:12px; color:#555; font-style:italic;">"<?= htmlspecialchars($p['alasan_terlambat'] ?: 'Tanpa alasan') ?>"</span></td>
                    <td>
                        <?php if($p['status_alasan'] === 'approved'): ?>
                            <div><span class="status-pill pill-tepat"><i class="fas fa-check"></i> Diterima</span><div style="font-size:11px; color:var(--success); margin-top:3px;">+5 skor dikembalikan</div></div>
                        <?php elseif($p['status_alasan'] === 'rejected'): ?>
                            <div><span class="status-pill pill-telat"><i class="fas fa-times"></i> Ditolak</span><div style="font-size:11px; color:var(--danger); margin-top:3px;">-5 skor (tetap)</div></div>
                        <?php else: ?>
                            <span style="background:#FFF5E5; color:var(--warning); padding:5px 10px; border-radius:10px; font-size:11px; font-weight:700;"><i class="fas fa-hourglass-half"></i> Menunggu</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($p['status_alasan'] === 'pending'): ?>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <a href="../proses.php?action=approve_alasan&id=<?= $p['id'] ?>&status=approved" onclick="return konfirApproval(event, 'terima', '<?= htmlspecialchars($p['nama_lengkap']) ?>', '<?= $p['status_kehadiran'] ?>')" style="background:var(--success); color:white; padding:7px 12px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:5px; box-shadow:0 2px 8px rgba(52, 199, 89, 0.2);"><i class="fas fa-check"></i> Terima</a>
                            <a href="../proses.php?action=approve_alasan&id=<?= $p['id'] ?>&status=rejected" onclick="return konfirApproval(event, 'tolak', '<?= htmlspecialchars($p['nama_lengkap']) ?>', '<?= $p['status_kehadiran'] ?>')" style="background:var(--danger); color:white; padding:7px 12px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:5px; box-shadow:0 2px 8px rgba(255, 59, 48, 0.2);"><i class="fas fa-times"></i> Tolak</a>
                        </div>
                        <?php else: ?>
                            <span style="color:#C7C7CC; font-size:12px; font-weight:600;"><i class="fas fa-lock"></i> Selesai</span>
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
function konfirApproval(event, tipe, nama, kategori) {
    event.preventDefault();
    const url = event.currentTarget.href;
    const isTerima = (tipe === 'terima');
    Swal.fire({
        title: isTerima ? `Terima Alasan ${kategori}?` : `Tolak Alasan ${kategori}?`,
        html: isTerima 
            ? `Alasan <b>${nama}</b> akan <b style="color:var(--success)">diterima</b>.<br><small style="color:#8E8E93;">Credit score +5 poin dikembalikan.</small>` 
            : `Alasan <b>${nama}</b> akan <b style="color:var(--danger)">ditolak</b>.<br><small style="color:#8E8E93;">Credit score tetap berkurang 5 poin.</small>`,
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