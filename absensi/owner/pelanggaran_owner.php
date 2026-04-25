<?php 
include '../header.php'; 
?>
<div class="card">
    <div class="card-title"><i class="fas fa-exclamation-circle" style="color:var(--danger)"></i> Log Keterlambatan & Tindakan SPV</div>
    <div class="table-res">
        <table>
            <thead>
                <tr><th>Tanggal</th><th>Karyawan</th><th>Alasan</th><th>Status SPV</th></tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("SELECT a.*, u.nama_lengkap FROM attendance a JOIN users u ON a.user_id = u.id WHERE a.status_kehadiran = 'Terlambat' ORDER BY a.id DESC");
                while($p = $stmt->fetch()):
                ?>
                <tr>
                    <td><?= $p['tanggal'] ?><br><small><?= $p['waktu_masuk'] ?></small></td>
                    <td><?= $p['nama_lengkap'] ?></td>
                    <td><i style="color:#666;">"<?= $p['alasan_terlambat'] ?: 'Tanpa Alasan' ?>"</i></td>
                    <td>
                        <?php if($p['status_alasan'] == 'approved'): ?>
                            <span class="status-pill pill-tepat">DITERIMA</span>
                        <?php elseif($p['status_alasan'] == 'rejected'): ?>
                            <span class="status-pill pill-telat">DITOLAK (-5)</span>
                        <?php else: ?>
                            <span style="font-size:11px; color:#8E8E93;">PENDING</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../footer.php'; ?>