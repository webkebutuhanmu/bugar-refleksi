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

    <?php if(isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
    <div style="background:#E2F9E9; border-left:4px solid var(--success); padding:12px 16px; border-radius:12px; margin-bottom:15px; font-size:13px; color:#1a7a35; font-weight:600; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-check-circle"></i> Data absen berhasil dihapus. Karyawan dapat absen ulang.
    </div>
    <?php endif; ?>

    <div class="table-res">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Karyawan</th>
                    <th>Cabang</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Status</th>
                    <th>Proses</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("
                    SELECT a.*, u.nama_lengkap, b.nama_cabang 
                    FROM attendance a 
                    JOIN users u ON a.user_id = u.id 
                    LEFT JOIN branches b ON u.branch_id = b.id 
                    ORDER BY a.id DESC LIMIT 30
                ");
                while($r = $stmt->fetch()):
                ?>
                <tr>
                    <td>
                        <b style="color:#1C1C1E; font-size:13px;"><?= date('d M Y', strtotime($r['tanggal'])) ?></b>
                        <br><small style="color:#8E8E93; font-size:10px;">Shift <?= $r['shift'] ?></small>
                    </td>
                    <td>
                        <b style="color:#1C1C1E;"><?= htmlspecialchars($r['nama_lengkap']) ?></b>
                    </td>
                    <td>
                        <span style="font-size:12px; color:#8E8E93;"><?= htmlspecialchars($r['nama_cabang'] ?? '-') ?></span>
                    </td>
                    <td>
                        <?php if(in_array($r['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <span style="color:#C7C7CC; font-size:13px;">-</span>
                        <?php else: ?>
                            <span style="color:var(--success); font-weight:700; font-size:13px;">
                                <i class="fas fa-sign-in-alt" style="font-size:10px;"></i> <?= $r['waktu_masuk'] ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if(in_array($r['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <span style="color:#C7C7CC; font-size:13px;">-</span>
                        <?php elseif($r['waktu_keluar']): ?>
                            <span style="color:var(--danger); font-weight:700; font-size:13px;">
                                <i class="fas fa-sign-out-alt" style="font-size:10px;"></i> <?= $r['waktu_keluar'] ?>
                            </span>
                        <?php else: ?>
                            <span style="background:#FFF5E5; color:var(--warning); padding:3px 8px; border-radius:8px; font-size:11px; font-weight:700;">
                                <i class="fas fa-spinner fa-spin" style="font-size:9px;"></i> Masih Kerja
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-pill <?= $r['status_kehadiran'] == 'Tepat Waktu' ? 'pill-tepat' : ($r['status_kehadiran'] == 'Terlambat' ? 'pill-telat' : '') ?>"
                              style="<?= in_array($r['status_kehadiran'], ['Sakit', 'Izin']) ? 'background:#FFF5F5; color:var(--danger);' : '' ?>">
                            <?= $r['status_kehadiran'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if(in_array($r['status_kehadiran'], ['Terlambat', 'Sakit', 'Izin'])): ?>
                            <?php if($r['status_alasan'] === 'approved'): ?>
                                <span style="background:#E2F9E9; color:var(--success); padding:5px 10px; border-radius:10px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:4px;">
                                    <i class="fas fa-check-circle"></i> Diterima
                                </span>
                            <?php elseif($r['status_alasan'] === 'rejected'): ?>
                                <span style="background:#FFE5E5; color:var(--danger); padding:5px 10px; border-radius:10px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:4px;">
                                    <i class="fas fa-times-circle"></i> Ditolak
                                </span>
                            <?php elseif($r['status_alasan'] === 'pending'): ?>
                                <span style="background:#FFF5E5; color:var(--warning); padding:5px 10px; border-radius:10px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:4px;">
                                    <i class="fas fa-hourglass-half"></i> Belum Diproses
                                </span>
                            <?php else: ?>
                                <span style="font-size:11px; color:#C7C7CC;">—</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="font-size:11px; color:#C7C7CC;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($r['waktu_keluar'] === NULL && !in_array($r['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <button 
                                onclick="hapusAbsen(<?= $r['id'] ?>, '<?= htmlspecialchars($r['nama_lengkap']) ?>')"
                                style="background:rgba(255,59,48,0.1); color:var(--danger); border:1px solid rgba(255,59,48,0.2); padding:6px 10px; border-radius:8px; font-size:12px; cursor:pointer; font-weight:600; transition:0.2s;"
                                onmouseover="this.style.background='var(--danger)'; this.style.color='white';"
                                onmouseout="this.style.background='rgba(255,59,48,0.1)'; this.style.color='var(--danger)';"
                                title="Hapus absen — karyawan dapat absen ulang">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        <?php else: ?>
                            <span style="background:#F2F2F7; color:#C7C7CC; padding:6px 10px; border-radius:8px; font-size:11px; font-weight:600; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fas fa-lock" style="font-size:10px;"></i> Selesai
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../footer.php'; ?>

<script>
function hapusAbsen(id, nama) {
    Swal.fire({
        title: 'Hapus Absen?',
        html: `Data absen <b>${nama}</b> akan dihapus.<br><small style="color:#8E8E93;">Karyawan dapat melakukan absen ulang setelah ini.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF3B30',
        cancelButtonColor: '#E5E5EA',
        cancelButtonText: '<span style="color:#1C1C1E; font-weight:bold;">Batal</span>',
        confirmButtonText: '<i class="fas fa-trash-alt"></i> Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `../proses.php?action=hapus_absen&id=${id}`;
        }
    });
}
</script>