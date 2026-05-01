<?php 
include '../header.php'; 
$tgl_sekarang = date('Y-m-d');

// Ambil ID cabang milik kasir yang sedang login
$branch_id = $me['branch_id'];

// Ambil nama cabang untuk ditampilkan di dashboard
$stmtCabang = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
$stmtCabang->execute([$branch_id]);
$nama_cabang = $stmtCabang->fetchColumn() ?: 'Cabang Tidak Diketahui';

// Hitung total staf KHUSUS di cabang kasir ini (kecuali owner)
$stmtStaf = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role != 'owner' AND branch_id = ?");
$stmtStaf->execute([$branch_id]);
$total_staf = $stmtStaf->fetchColumn();

// Hitung total staf yang hadir hari ini KHUSUS di cabang ini
$stmtHadir = $pdo->prepare("
    SELECT COUNT(*) FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.tanggal = ? AND u.branch_id = ?
");
$stmtHadir->execute([$tgl_sekarang, $branch_id]);
$total_hadir = $stmtHadir->fetchColumn();
?>

<div class="dashboard-grid">
    <div class="card info-card slide-up bg-gradient-primary">
        <div class="info-card-content">
            <i class="fas fa-users" style="font-size: 30px; margin-bottom: 10px;"></i>
            <div style="font-size: 11px; font-weight: 700; opacity:0.8;">TOTAL STAF CABANG</div>
            <div style="font-size: 32px; font-weight: 800;"><?= $total_staf ?></div>
            <div style="font-size: 10px; opacity:0.8; margin-top:5px; text-transform:uppercase; font-weight:700;">
                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($nama_cabang) ?>
            </div>
        </div>
    </div>
    
    <div class="card info-card slide-up delay-1 bg-gradient-warning">
        <div class="info-card-content">
            <i class="fas fa-clock" style="font-size: 30px; margin-bottom: 10px;"></i>
            <div style="font-size: 11px; font-weight: 700; opacity:0.8;">HADIR HARI INI</div>
            <div style="font-size: 32px; font-weight: 800;"><?= $total_hadir ?></div>
            <div style="font-size: 10px; opacity:0.8; margin-top:5px; text-transform:uppercase; font-weight:700;">
                DI CABANG SAYA
            </div>
        </div>
    </div>
</div>

<div class="card slide-up delay-2">
    <div class="card-title" style="justify-content: space-between; flex-wrap: wrap; gap:10px;">
        <span><i class="fas fa-bolt" style="color:var(--warning)"></i> Aktivitas Absensi Terbaru</span>
        <span style="font-size: 11px; background: #F2F2F7; padding: 5px 12px; border-radius: 12px; color: #1C1C1E; font-weight: 700;">
            <i class="fas fa-map-marker-alt" style="color:var(--danger);"></i> <?= htmlspecialchars($nama_cabang) ?>
        </span>
    </div>
    
    <div class="table-res">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Karyawan</th>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Menampilkan daftar absen KHUSUS karyawan di branch_id yang sama dengan kasir
                // Memanggil u.role untuk menampilkan jabatan
                $stmtList = $pdo->prepare("
                    SELECT a.*, u.nama_lengkap, u.role 
                    FROM attendance a 
                    JOIN users u ON a.user_id = u.id 
                    WHERE u.branch_id = ? 
                    ORDER BY a.id DESC LIMIT 15
                ");
                $stmtList->execute([$branch_id]);
                $rows = $stmtList->fetchAll();

                if (!$rows):
                ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding:30px; color:#8E8E93; font-weight:600;">
                        <i class="fas fa-inbox" style="font-size:24px; opacity:0.4; display:block; margin-bottom:10px;"></i>
                        Belum ada aktivitas absensi di cabang ini
                    </td>
                </tr>
                <?php else: foreach($rows as $r): ?>
                <tr>
                    <!-- Kolom Tanggal -->
                    <td>
                        <b style="color:#1C1C1E; font-size:13px;"><?= date('d M Y', strtotime($r['tanggal'])) ?></b>
                        <div style="font-size:11px; color:#8E8E93; margin-top:3px; font-weight:600;">Shift <?= $r['shift'] ?></div>
                    </td>
                    
                    <!-- Kolom Karyawan & Role -->
                    <td>
                        <b style="color:#1C1C1E; font-size:13px;"><?= htmlspecialchars($r['nama_lengkap']) ?></b>
                        <div style="margin-top:3px;">
                            <span style="font-size:10px; background:#F2F2F7; padding:2px 6px; border-radius:6px; text-transform:uppercase; font-weight:700; color:#8E8E93;">
                                <?= htmlspecialchars($r['role']) ?>
                            </span>
                        </div>
                    </td>
                    
                    <!-- Kolom Jam Masuk -->
                    <td>
                        <?php if(in_array($r['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <span style="color:#C7C7CC; font-weight:bold;">-</span>
                        <?php else: ?>
                            <span style="color:var(--success); font-weight:700; font-size:13px;">
                                <i class="fas fa-sign-in-alt" style="font-size:10px; margin-right:3px;"></i><?= $r['waktu_masuk'] ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    
                    <!-- Kolom Jam Keluar -->
                    <td>
                        <?php if(in_array($r['status_kehadiran'], ['Sakit', 'Izin'])): ?>
                            <span style="color:#C7C7CC; font-weight:bold;">-</span>
                        <?php elseif($r['waktu_keluar']): ?>
                            <span style="color:var(--danger); font-weight:700; font-size:13px;">
                                <i class="fas fa-sign-out-alt" style="font-size:10px; margin-right:3px;"></i><?= $r['waktu_keluar'] ?>
                            </span>
                        <?php else: ?>
                            <span style="background:#FFF5E5; color:var(--warning); padding:4px 10px; border-radius:8px; font-size:11px; font-weight:700;">
                                <i class="fas fa-spinner fa-spin" style="font-size:9px;"></i> Aktif
                            </span>
                        <?php endif; ?>
                    </td>
                    
                    <!-- Kolom Status Kehadiran -->
                    <td>
                        <span class="status-pill <?= $r['status_kehadiran'] == 'Tepat Waktu' ? 'pill-tepat' : ($r['status_kehadiran'] == 'Terlambat' ? 'pill-telat' : '') ?>" 
                              style="<?= in_array($r['status_kehadiran'], ['Sakit', 'Izin']) ? 'background:#FFF5F5; color:var(--danger);' : '' ?>">
                            <?= $r['status_kehadiran'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../footer.php'; ?>