<?php include '../header.php'; 

// Ambil info cabang karyawan
$stmtCabang = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
$stmtCabang->execute([$me['branch_id']]);
$cabang = $stmtCabang->fetch();

// Statistik ringkasan
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_absen,
        SUM(CASE WHEN status_kehadiran = 'Tepat Waktu' THEN 1 ELSE 0 END) as total_tepat,
        SUM(CASE WHEN status_kehadiran = 'Terlambat' THEN 1 ELSE 0 END) as total_terlambat
    FROM attendance WHERE user_id = ?
");
$stmtStats->execute([$user_id]);
$stats = $stmtStats->fetch();

// Rekap lengkap
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? ORDER BY id DESC LIMIT 100");
$stmt->execute([$user_id]);
$rekap = $stmt->fetchAll();
?>

<!-- Info Cabang & Statistik -->
<div class="card slide-up">
    <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--primary)"></i> Rekap Absensi Saya</div>

    <!-- Info Cabang -->
    <div style="display:flex; align-items:center; gap:10px; background:linear-gradient(135deg,#5856D6,#7E7CE6); padding:14px 18px; border-radius:14px; margin-bottom:15px; color:white;">
        <i class="fas fa-map-marker-alt" style="font-size:18px;"></i>
        <div>
            <div style="font-size:11px; font-weight:700; opacity:0.85; letter-spacing:0.5px;">CABANG SAYA</div>
            <div style="font-size:16px; font-weight:800;"><?= htmlspecialchars($cabang['nama_cabang'] ?? '-') ?></div>
        </div>
    </div>

    <!-- Statistik -->
    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:5px;">
        <div style="flex:1; min-width:90px; background:#F9F9F9; border-radius:12px; padding:14px 12px; text-align:center;">
            <div style="font-size:24px; font-weight:800; color:var(--primary);"><?= $stats['total_absen'] ?? 0 ?></div>
            <div style="font-size:11px; font-weight:700; color:#8E8E93; margin-top:3px;">TOTAL HADIR</div>
        </div>
        <div style="flex:1; min-width:90px; background:#E2F9E9; border-radius:12px; padding:14px 12px; text-align:center;">
            <div style="font-size:24px; font-weight:800; color:var(--success);"><?= $stats['total_tepat'] ?? 0 ?></div>
            <div style="font-size:11px; font-weight:700; color:var(--success); opacity:0.8; margin-top:3px;">TEPAT WAKTU</div>
        </div>
        <div style="flex:1; min-width:90px; background:#FFE5E5; border-radius:12px; padding:14px 12px; text-align:center;">
            <div style="font-size:24px; font-weight:800; color:var(--danger);"><?= $stats['total_terlambat'] ?? 0 ?></div>
            <div style="font-size:11px; font-weight:700; color:var(--danger); opacity:0.8; margin-top:3px;">TERLAMBAT</div>
        </div>
    </div>
</div>

<!-- Tabel Riwayat Lengkap -->
<div class="card slide-up delay-1">
    <div class="card-title"><i class="fas fa-history" style="color:var(--text)"></i> Riwayat Detail Absensi</div>

    <?php if(!$rekap): ?>
        <div style="text-align:center; padding:40px 20px; color:#8E8E93;">
            <i class="fas fa-inbox" style="font-size:36px; margin-bottom:12px; display:block; opacity:0.35;"></i>
            Belum ada riwayat absensi.
        </div>
    <?php else: ?>
    <div class="table-res">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Shift</th>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Status Hadir</th>
                    <th>Alasan</th>
                    <th>Tindakan SPV</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rekap as $r): ?>
                <tr>
                    <!-- Tanggal -->
                    <td>
                        <b style="color:#1C1C1E; font-size:13px;"><?= date('d M Y', strtotime($r['tanggal'])) ?></b>
                    </td>

                    <!-- Shift -->
                    <td>
                        <span style="background:#F2F2F7; padding:4px 10px; border-radius:8px; font-weight:800; font-size:12px; color:#1C1C1E;">
                            S<?= $r['shift'] ?>
                        </span>
                    </td>

                    <!-- Jam Masuk -->
                    <td>
                        <span style="color:var(--success); font-weight:700; font-size:13px;">
                            <i class="fas fa-sign-in-alt" style="font-size:10px; margin-right:3px;"></i><?= $r['waktu_masuk'] ?>
                        </span>
                    </td>

                    <!-- Jam Keluar -->
                    <td>
                        <?php if($r['waktu_keluar']): ?>
                            <span style="color:var(--danger); font-weight:700; font-size:13px;">
                                <i class="fas fa-sign-out-alt" style="font-size:10px; margin-right:3px;"></i><?= $r['waktu_keluar'] ?>
                            </span>
                        <?php else: ?>
                            <span style="background:#FFF5E5; color:var(--warning); padding:4px 10px; border-radius:8px; font-size:11px; font-weight:700;">
                                <i class="fas fa-spinner fa-spin" style="font-size:9px;"></i> Aktif
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Status Kehadiran -->
                    <td>
                        <span class="status-pill <?= $r['status_kehadiran'] == 'Tepat Waktu' ? 'pill-tepat' : 'pill-telat' ?>">
                            <?= $r['status_kehadiran'] ?>
                        </span>
                    </td>

                    <!-- Alasan (hanya tampil jika terlambat) -->
                    <td style="max-width:180px;">
                        <?php if($r['status_kehadiran'] === 'Terlambat'): ?>
                            <?php if(!empty($r['alasan_terlambat'])): ?>
                                <span style="font-size:12px; color:#555; font-style:italic;">
                                    "<?= htmlspecialchars($r['alasan_terlambat']) ?>"
                                </span>
                            <?php else: ?>
                                <span style="font-size:11px; color:#C7C7CC;">Tanpa alasan</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="font-size:11px; color:#C7C7CC;">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Tindakan SPV -->
                    <td>
                        <?php if($r['status_kehadiran'] === 'Terlambat'): ?>
                            <?php if($r['status_alasan'] === 'approved'): ?>
                                <div>
                                    <span class="status-pill pill-tepat">
                                        <i class="fas fa-check"></i> Diterima SPV
                                    </span>
                                    <div style="font-size:11px; color:var(--success); margin-top:4px; font-weight:600;">
                                        <i class="fas fa-plus-circle"></i> +5 skor dikembalikan
                                    </div>
                                </div>
                            <?php elseif($r['status_alasan'] === 'rejected'): ?>
                                <div>
                                    <span class="status-pill pill-telat">
                                        <i class="fas fa-times"></i> Ditolak SPV
                                    </span>
                                    <div style="font-size:11px; color:var(--danger); margin-top:4px; font-weight:600;">
                                        <i class="fas fa-minus-circle"></i> -5 skor (tetap)
                                    </div>
                                </div>
                            <?php elseif($r['status_alasan'] === 'pending'): ?>
                                <span style="background:#FFF5E5; color:var(--warning); padding:5px 10px; border-radius:10px; font-size:11px; font-weight:700; display:inline-block;">
                                    <i class="fas fa-hourglass-half"></i> Menunggu SPV
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