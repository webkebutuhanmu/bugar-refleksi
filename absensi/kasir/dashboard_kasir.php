<?php 
include '../header.php'; 
$tgl_sekarang = date('Y-m-d');
$skor_kredit = $me['credit_score'] ?? 100;

$stmtAbsen = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND tanggal = ?");
$stmtAbsen->execute([$user_id, $tgl_sekarang]);
$absen_hari_ini = $stmtAbsen->fetch();
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
    <div style="width:60px; height:60px; background:#F2F2F7; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:15px;"><i class="fas fa-fingerprint" style="font-size:24px; color:var(--primary);"></i></div>
    <div style="font-size: 14px; color: #8E8E93; font-weight: 600; margin-bottom: 15px;">STATUS: BELUM ABSEN</div>
    <a href="absen_<?= $x ?>.php" style="background:var(--primary); color:white; padding:12px 25px; border-radius:12px; text-decoration:none; font-weight:bold; width:100%; box-shadow:0 4px 15px rgba(88,86,214,0.3); transition:0.2s;"><i class="fas fa-sign-in-alt"></i> Absen Masuk Sekarang</a>

<?php elseif(in_array($absen_hari_ini['status_kehadiran'], ['Sakit', 'Izin'])): ?>
    <div style="width:60px; height:60px; background:#FFF5E5; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:15px;"><i class="fas <?= $absen_hari_ini['status_kehadiran'] == 'Sakit' ? 'fa-notes-medical' : 'fa-envelope-open-text' ?>" style="font-size:24px; color:var(--warning);"></i></div>
    <div style="font-size: 14px; color: #8E8E93; font-weight: 600; margin-bottom: 15px;">STATUS: <?= strtoupper($absen_hari_ini['status_kehadiran']) ?></div>
    <div style="font-size:12px; color:#8E8E93; margin-top:8px;">Menunggu/Disetujui via <a href="absen_<?= $x ?>.php" style="color:var(--primary); font-weight:bold;">Menu Absen</a></div>

<?php elseif($absen_hari_ini['waktu_keluar'] == NULL): ?>
    <div style="width:60px; height:60px; background:#FFF5E5; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:15px;"><i class="fas fa-briefcase" style="font-size:24px; color:var(--warning);"></i></div>
    <div style="font-size: 14px; color: #8E8E93; font-weight: 600; margin-bottom: 15px;">STATUS: SEDANG BEKERJA</div>
    <a href="absen_<?= $x ?>.php" style="background:var(--warning); color:white; padding:12px 25px; border-radius:12px; text-decoration:none; font-weight:bold; width:100%; box-shadow:0 4px 15px rgba(255,149,0,0.3); transition:0.2s;"><i class="fas fa-sign-out-alt"></i> Absen Pulang (Selesai)</a>

<?php else: ?>
    <div style="width:70px; height:70px; background:#E2F9E9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:15px;"><i class="fas fa-check" style="font-size:30px; color:var(--success);"></i></div>
    <div style="font-size: 18px; font-weight: 800; color: var(--success);">KERJA SELESAI</div>
<?php endif; ?>
        </div>
    </div>
</div>
<?php include '../footer.php'; ?>