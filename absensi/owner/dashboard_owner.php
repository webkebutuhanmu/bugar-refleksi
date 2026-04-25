<?php 
include '../header.php'; 
$tgl_sekarang = date('Y-m-d');
?>
<div class="dashboard-grid">
    <div class="card info-card slide-up bg-gradient-primary">
        <div class="info-card-content">
            <i class="fas fa-users" style="font-size: 30px; margin-bottom: 10px;"></i>
            <div style="font-size: 11px; font-weight: 700; opacity:0.8;">TOTAL STAF</div>
            <div style="font-size: 32px; font-weight: 800;"><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'owner'")->fetchColumn() ?></div>
        </div>
    </div>
    <div class="card info-card slide-up delay-1 bg-gradient-warning">
        <div class="info-card-content">
            <i class="fas fa-clock" style="font-size: 30px; margin-bottom: 10px;"></i>
            <div style="font-size: 11px; font-weight: 700; opacity:0.8;">HADIR HARI INI</div>
            <div style="font-size: 32px; font-weight: 800;"><?= $pdo->query("SELECT COUNT(*) FROM attendance WHERE tanggal = '$tgl_sekarang'")->fetchColumn() ?></div>
        </div>
    </div>
</div>

<div class="card slide-up delay-2">
    <div class="card-title"><i class="fas fa-bolt" style="color:var(--warning)"></i> Aktivitas Absensi Terbaru</div>
    <div class="table-res">
        <table>
            <thead><tr><th>Karyawan</th><th>Cabang</th><th>Waktu</th><th>Status</th></tr></thead>
            <tbody>
                <?php
                $stmt = $pdo->query("SELECT a.*, u.nama_lengkap, b.nama_cabang FROM attendance a JOIN users u ON a.user_id = u.id LEFT JOIN branches b ON u.branch_id = b.id ORDER BY a.id DESC LIMIT 15");
                while($r = $stmt->fetch()):
                ?>
                <tr>
                    <td><b style="color:#1C1C1E;"><?= $r['nama_lengkap'] ?></b></td>
                    <td><span style="font-size:12px; color:#8E8E93;"><?= $r['nama_cabang'] ?? '-' ?></span></td>
                    <td><b style="color:var(--success)">In:</b> <?= $r['waktu_masuk'] ?></td>
                    <td><span class="status-pill <?= $r['status_kehadiran'] == 'Tepat Waktu' ? 'pill-tepat' : 'pill-telat' ?>"><?= $r['status_kehadiran'] ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../footer.php'; ?>