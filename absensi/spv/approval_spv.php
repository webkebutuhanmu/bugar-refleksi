<?php 
include '../header.php'; 
$stmt = $pdo->prepare("SELECT a.*, u.nama_lengkap FROM attendance a JOIN users u ON a.user_id = u.id WHERE u.branch_id = ? AND a.status_kehadiran = 'Terlambat' AND a.status_alasan = 'pending'");
$stmt->execute([$me['branch_id']]);
$pending = $stmt->fetchAll();
?>
<div class="card">
    <div class="card-title"><i class="fas fa-gavel" style="color:var(--warning)"></i> Approval Alasan Terlambat</div>
    <?php if(!$pending): ?><p>Tidak ada data terlambat yang menunggu persetujuan.</p><?php else: ?>
        <div class="table-res">
            <table>
                <tr><th>Nama</th><th>Tgl & Jam</th><th>Alasan</th><th>Aksi</th></tr>
                <?php foreach($pending as $p): ?>
                <tr>
                    <td><?= $p['nama_lengkap'] ?></td>
                    <td><?= $p['tanggal'] ?><br><small><?= $p['waktu_masuk'] ?></small></td>
                    <td><i>"<?= htmlspecialchars($p['alasan_terlambat']) ?>"</i></td>
                    <td>
                        <a href="../proses.php?action=approve_alasan&id=<?= $p['id'] ?>&status=approved" class="btn btn-success" style="background:var(--success); color:white; padding:5px 10px; text-decoration:none; border-radius:5px;">Terima</a>
                        <a href="../proses.php?action=approve_alasan&id=<?= $p['id'] ?>&status=rejected" class="btn btn-danger" style="background:var(--danger); color:white; padding:5px 10px; text-decoration:none; border-radius:5px;">Tolak</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include '../footer.php'; ?>