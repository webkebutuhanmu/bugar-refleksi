<?php 
include '../header.php'; 
$tgl_sekarang = date('Y-m-d');
?>
<div class="dashboard-grid">
    <div class="card info-card slide-up bg-gradient-primary">
        <div class="info-card-content">
            <i class="fas fa-users" style="font-size: 30px; margin-bottom: 10px;"></i>
            <div style="font-size: 11px; font-weight: 700; opacity:0.8;">TOTAL STAF (GLOBAL)</div>
            <div style="font-size: 32px; font-weight: 800;"><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'owner' AND role != 'admin'")->fetchColumn() ?></div>
        </div>
    </div>
    <div class="card info-card slide-up delay-1 bg-gradient-warning">
        <div class="info-card-content">
            <i class="fas fa-clock" style="font-size: 30px; margin-bottom: 10px;"></i>
            <div style="font-size: 11px; font-weight: 700; opacity:0.8;">HADIR HARI INI (GLOBAL)</div>
            <div style="font-size: 32px; font-weight: 800;"><?= $pdo->query("SELECT COUNT(*) FROM attendance WHERE tanggal = '$tgl_sekarang'")->fetchColumn() ?></div>
        </div>
    </div>
</div>

<div class="card slide-up delay-2">
    <div class="card-title"><i class="fas fa-bolt" style="color:var(--warning)"></i> Aktivitas Absensi Terbaru</div>
    <div class="table-res">
        <table>
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Cabang</th>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("
                    SELECT a.*, u.nama_lengkap, u.role, b.nama_cabang 
                    FROM attendance a 
                    JOIN users u ON a.user_id = u.id 
                    LEFT JOIN branches b ON u.branch_id = b.id 
                    ORDER BY a.id DESC LIMIT 20
                ");
                while($r = $stmt->fetch()):
                ?>
                <tr>
                    <td>
                        <b style="color:#1C1C1E; font-size:13px;"><?= htmlspecialchars($r['nama_lengkap']) ?></b>
                        <div style="font-size:10px; background:#F2F2F7; padding:2px 6px; border-radius:6px; text-transform:uppercase; font-weight:700; color:#8E8E93; display:inline-block; margin-top:3px;">
                            <?= htmlspecialchars($r['role']) ?>
                        </div>
                    </td>
                    <td><span style="font-size:12px; color:#8E8E93;"><?= htmlspecialchars($r['nama_cabang'] ?? '-') ?></span></td>
                    <td>
                        <?php if(in_array($r['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <span style="color:#C7C7CC; font-weight:bold;">-</span>
                        <?php else: ?>
                            <span style="color:var(--success); font-weight:700; font-size:13px;"><i class="fas fa-sign-in-alt" style="font-size:10px;"></i> <?= $r['waktu_masuk'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if(in_array($r['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <span style="color:#C7C7CC; font-weight:bold;">-</span>
                        <?php elseif($r['waktu_keluar']): ?>
                            <span style="color:var(--danger); font-weight:700; font-size:13px;"><i class="fas fa-sign-out-alt" style="font-size:10px;"></i> <?= $r['waktu_keluar'] ?></span>
                        <?php else: ?>
                            <span style="background:#FFF5E5; color:var(--warning); padding:4px 10px; border-radius:8px; font-size:11px; font-weight:700;"><i class="fas fa-spinner fa-spin" style="font-size:9px;"></i> Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-pill <?= $r['status_kehadiran'] == 'Tepat Waktu' ? 'pill-tepat' : ($r['status_kehadiran'] == 'Terlambat' ? 'pill-telat' : '') ?>" style="<?= in_array($r['status_kehadiran'], ['Sakit', 'Izin']) ? 'background:#FFF5F5; color:var(--danger);' : '' ?>"><?= $r['status_kehadiran'] ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../footer.php'; ?>