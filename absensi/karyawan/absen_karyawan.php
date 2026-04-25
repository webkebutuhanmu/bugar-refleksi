<?php 
include '../header.php'; 
$tgl = date('Y-m-d'); $jam_sekarang = date('H:i:s');
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND tanggal = ?");
$stmt->execute([$user_id, $tgl]); $absen = $stmt->fetch();

$set = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
$shift_aktif = 1; $batas_toleransi = $set['s1_batas']; $nama_shift = "Shift 1 (Pagi)";
if ($jam_sekarang >= $set['s2_mulai'] || ($jam_sekarang < $set['s1_mulai'])) {
    $shift_aktif = 2; $batas_toleransi = $set['s2_batas']; $nama_shift = "Shift 2 (Malam)";
}

$is_late = false;
if ($jam_sekarang > $batas_toleransi) { $is_late = true; }
?>
<div class="card slide-up">
    <div class="card-title"><i class="fas fa-fingerprint" style="color:var(--primary)"></i> Presensi Sistem</div>
    <?php if(!$absen): ?>
        <form id="formAbsen" action="../proses.php?action=absen_masuk" method="POST">
            <input type="hidden" name="shift" value="<?= $shift_aktif ?>">
            <div style="text-align:center; padding: 20px; background:#F2F2F7; border-radius:15px; margin-bottom:20px;">
                <div style="font-size:11px; font-weight:700; color:#8E8E93; letter-spacing:1px;">SHIFT TERDETEKSI</div>
                <div style="font-size:18px; font-weight:800; color:var(--primary); margin:5px 0;"><?= $nama_shift ?></div>
                <div style="font-size:13px; color:#1C1C1E;">Batas Toleransi: <b style="color:var(--danger)"><?= date('H:i', strtotime($batas_toleransi)) ?></b></div>
            </div>
            <?php if($is_late): ?>
                <div style="background:#FFF5E5; border-left:4px solid var(--warning); padding:15px; border-radius:10px; margin-bottom:15px;">
                    <div style="font-weight:800; color:#856404; font-size:14px;"><i class="fas fa-exclamation-circle"></i> ANDA TERLAMBAT</div>
                    <p style="margin:5px 0 0; font-size:12px; color:#856404; line-height:1.4;">Isi alasan agar SPV dapat mengembalikan skor kredit Anda.</p>
                </div>
                <textarea name="alasan" id="alasanTeks" placeholder="Tulis alasan keterlambatan..." style="width:100%; border-radius:12px; padding:15px; border:1px solid rgba(0,0,0,0.1); margin-bottom:20px; font-size:14px; background:#F9F9F9; outline:none;" rows="3" required></textarea>
            <?php endif; ?>
            <button type="button" onclick="konfirAbsen('masuk', <?= $is_late ? 'true' : 'false' ?>)" style="background:var(--success); color:white; border:none; width:100%; padding:15px; border-radius:12px; font-size:16px; font-weight:bold; cursor:pointer;"><i class="fas fa-sign-in-alt"></i> <?= $is_late ? 'Absen & Kirim Alasan' : 'Absen Masuk' ?></button>
        </form>
    <?php elseif($absen && $absen['waktu_keluar'] == NULL): ?>
        <div style="text-align:center; padding: 30px 0;">
            <div style="font-size:24px; font-weight:800; color:var(--text); margin-bottom:25px;"><?= $absen['waktu_masuk'] ?></div>
            <button onclick="konfirAbsen('pulang', false)" style="background:var(--danger); color:white; border:none; width:100%; padding:15px; border-radius:12px; font-size:16px; font-weight:bold; cursor:pointer;"><i class="fas fa-sign-out-alt"></i> Absen Pulang</button>
        </div>
    <?php else: ?>
        <div style="text-align:center; padding:30px 0;"><h3 style="color:var(--success)">Presensi Selesai</h3></div>
    <?php endif; ?>
</div>
<?php include '../footer.php'; ?>
<script>
function konfirAbsen(jenis, isLate) {
    if (jenis === 'masuk' && isLate && document.getElementById('alasanTeks').value.trim().length < 5) return Swal.fire('Gagal', 'Alasan harus diisi!', 'error');
    Swal.fire({ title: 'Konfirmasi', text: 'Lanjutkan?', icon: 'question', showCancelButton: true, confirmButtonColor: jenis === 'masuk' ? '#34C759' : '#FF3B30', confirmButtonText: 'Ya' }).then((r) => {
        if (r.isConfirmed) { if(jenis === 'masuk') document.getElementById('formAbsen').submit(); else window.location.href = '../proses.php?action=absen_keluar'; }
    })
}
</script>