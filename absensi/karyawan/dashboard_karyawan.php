<?php 
include '../header.php'; 
$tgl_sekarang = date('Y-m-d');
$skor_kredit = $me['credit_score'] ?? 100;

$stmtAbsen = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND tanggal = ?");
$stmtAbsen->execute([$user_id, $tgl_sekarang]);
$absen_hari_ini = $stmtAbsen->fetch();

// Ambil info cabang
$stmtCabang = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
$stmtCabang->execute([$me['branch_id']]);
$cabang = $stmtCabang->fetch();

// Riwayat 10 absen terakhir untuk ditampilkan di dashboard
$stmtRiwayat = $pdo->prepare("
    SELECT * FROM attendance 
    WHERE user_id = ? 
    ORDER BY id DESC 
    LIMIT 10
");
$stmtRiwayat->execute([$user_id]);
$riwayat = $stmtRiwayat->fetchAll();
?>

<div class="dashboard-grid">
    <div class="card info-card slide-up bg-gradient-warning">
        <div class="info-card-content">
            <i class="fas fa-crown" style="font-size: 35px; margin-bottom: 15px; text-shadow: 0 4px 10px rgba(0,0,0,0.2);"></i>
            <div style="font-size: 13px; font-weight: 700; opacity:0.9; letter-spacing:1px; margin-bottom:5px;">CREDIT SCORE ANDA</div>
            <div style="font-size: 42px; font-weight: 800; text-shadow: 0 4px 15px rgba(0,0,0,0.2);"><?= $skor_kredit ?></div>
        </div>
    </div>
    
    <div class="card info-card slide-up delay-1" style="background:var(--card-bg);">
        <div class="info-card-content">
            <?php if(!$absen_hari_ini): ?>
                <div style="width:60px; height:60px; background:#F2F2F7; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:15px;">
                    <i class="fas fa-fingerprint" style="font-size:24px; color:var(--primary);"></i>
                </div>
                <div style="font-size: 14px; color: #8E8E93; font-weight: 600; margin-bottom: 15px;">STATUS: BELUM ABSEN</div>
                <a href="absen_karyawan.php" style="background:var(--primary); color:white; padding:12px 25px; border-radius:12px; text-decoration:none; font-weight:bold; width:100%; box-shadow:0 4px 15px rgba(88,86,214,0.3); transition:0.2s;">
                    <i class="fas fa-sign-in-alt"></i> Absen Masuk Sekarang
                </a>
                
            <?php elseif(in_array($absen_hari_ini['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                <div style="width:60px; height:60px; background:#FFF5E5; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:15px;">
                    <i class="fas <?= $absen_hari_ini['status_kehadiran'] == 'Sakit' ? 'fa-notes-medical' : 'fa-envelope-open-text' ?>" style="font-size:24px; color:var(--warning);"></i>
                </div>
                <div style="font-size: 14px; color: #8E8E93; font-weight: 600; margin-bottom: 15px;">STATUS: <?= strtoupper($absen_hari_ini['status_kehadiran']) ?></div>
                <div style="font-size:12px; color:#8E8E93; margin-top:8px;">Detail di <a href="absen_karyawan.php" style="color:var(--primary); font-weight:bold;">Menu Absen</a></div>
                
            <?php elseif($absen_hari_ini['waktu_keluar'] == NULL): ?>
                <div style="width:60px; height:60px; background:#FFF5E5; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:15px;">
                    <i class="fas fa-briefcase" style="font-size:24px; color:var(--warning);"></i>
                </div>
                <div style="font-size: 14px; color: #8E8E93; font-weight: 600; margin-bottom: 15px;">STATUS: SEDANG BEKERJA</div>
                <a href="absen_karyawan.php" style="background:var(--warning); color:white; padding:12px 25px; border-radius:12px; text-decoration:none; font-weight:bold; width:100%; box-shadow:0 4px 15px rgba(255,149,0,0.3); transition:0.2s;">
                    <i class="fas fa-sign-out-alt"></i> Absen Pulang (Selesai)
                </a>
                
            <?php else: ?>
                <div style="width:70px; height:70px; background:#E2F9E9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:15px;">
                    <i class="fas fa-check" style="font-size:30px; color:var(--success);"></i>
                </div>
                <div style="font-size: 18px; font-weight: 800; color: var(--success);">KERJA SELESAI</div>
                <div style="font-size:12px; color:#8E8E93; margin-top:8px;">
                    <span style="color:var(--success);"><?= $absen_hari_ini['waktu_masuk'] ?></span>
                    <i class="fas fa-arrow-right" style="font-size:9px; margin:0 4px;"></i>
                    <span style="color:var(--danger);"><?= $absen_hari_ini['waktu_keluar'] ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card slide-up delay-2">
    <div class="card-title" style="justify-content:space-between; flex-wrap:wrap; gap:8px;">
        <span><i class="fas fa-history" style="color:var(--primary)"></i> Riwayat Absensi Terbaru</span>
        <a href="riwayat_karyawan.php" style="font-size:12px; color:var(--primary); text-decoration:none; font-weight:700; background:#F0EFFF; padding:5px 12px; border-radius:20px;">
            <i class="fas fa-list"></i> Lihat Semua
        </a>
    </div>

    <div style="display:flex; align-items:center; gap:8px; background:#F9F9F9; padding:10px 14px; border-radius:10px; margin-bottom:15px; font-size:13px;">
        <i class="fas fa-map-marker-alt" style="color:var(--danger);"></i>
        <span style="color:#8E8E93;">Cabang:</span>
        <b style="color:#1C1C1E;"><?= htmlspecialchars($cabang['nama_cabang'] ?? '-') ?></b>
    </div>

    <?php if(!$riwayat): ?>
        <div style="text-align:center; padding:30px 20px; color:#8E8E93;">
            <i class="fas fa-inbox" style="font-size:30px; margin-bottom:10px; display:block; opacity:0.4;"></i>
            Belum ada riwayat absensi.
        </div>
    <?php else: ?>
    <div class="table-res">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Shift</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Status</th>
                    <th>Proses</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($riwayat as $r): ?>
                <tr>
                    <td>
                        <b style="color:#1C1C1E; font-size:13px;"><?= date('d M Y', strtotime($r['tanggal'])) ?></b>
                    </td>
                    <td>
                        <span style="background:#F2F2F7; padding:4px 8px; border-radius:8px; font-weight:bold; font-size:12px;">S<?= $r['shift'] ?></span>
                    </td>
                    <td>
                        <?php if(in_array($r['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <span style="color:#C7C7CC; font-size:13px;">-</span>
                        <?php else: ?>
                            <span style="color:var(--success); font-weight:700; font-size:13px;"><?= $r['waktu_masuk'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if(in_array($r['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <span style="color:#C7C7CC; font-size:13px;">-</span>
                        <?php elseif($r['waktu_keluar']): ?>
                            <span style="color:var(--danger); font-weight:700; font-size:13px;"><?= $r['waktu_keluar'] ?></span>
                        <?php else: ?>
                            <span style="background:#FFF5E5; color:var(--warning); padding:3px 8px; border-radius:8px; font-size:11px; font-weight:700;">
                                <i class="fas fa-spinner fa-spin" style="font-size:9px;"></i> Aktif
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
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>