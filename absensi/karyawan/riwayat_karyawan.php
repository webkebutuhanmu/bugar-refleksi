<?php include '../header.php'; ?>
<div class="card slide-up">
    <div class="card-title"><i class="fas fa-history" style="color:var(--text)"></i> Rekap Absensi Pribadi</div>
    <?php 
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? ORDER BY id DESC LIMIT 50");
    $stmt->execute([$user_id]); $rekap = $stmt->fetchAll();
    
    if(!$rekap): ?>
        <div style="text-align:center; padding: 40px 20px; color:#8E8E93;">Belum ada riwayat.</div>
    <?php else: ?>
        <div class="table-res">
            <table>
                <thead><tr><th>Tanggal</th><th>Shift</th><th>Waktu</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach($rekap as $r): ?>
                    <tr>
                        <td><b style="color:#1C1C1E;"><?= date('d M Y', strtotime($r['tanggal'])) ?></b></td>
                        <td><span style="background:#F2F2F7; padding:4px 8px; border-radius:8px; font-weight:bold; font-size:12px;">S<?= $r['shift'] ?></span></td>
                        <td><span style="color:var(--success);"><?= $r['waktu_masuk'] ?></span> <i class="fas fa-arrow-right"></i> <span style="color:var(--danger);"><?= $r['waktu_keluar'] ?? '--:--' ?></span></td>
                        <td><span class="status-pill <?= $r['status_kehadiran'] == 'Tepat Waktu' ? 'pill-tepat' : 'pill-telat' ?>"><?= $r['status_kehadiran'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include '../footer.php'; ?>