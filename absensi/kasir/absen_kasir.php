<?php 
include '../header.php'; 
$tgl          = date('Y-m-d');
$jam_sekarang = date('H:i:s');

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND tanggal = ?");
$stmt->execute([$user_id, $tgl]);
$absen = $stmt->fetch();

$set = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();

$t_skrg   = strtotime($jam_sekarang);
$t_s1_mul = strtotime($set['s1_mulai']);
$t_s2_mul = strtotime($set['s2_mulai']);

if ($t_skrg >= $t_s1_mul && $t_skrg < $t_s2_mul) {
    $shift_aktif     = 1;
    $batas_toleransi = $set['s1_batas'];
    $nama_shift      = 'Shift 1 (Pagi)';
} else {
    $shift_aktif     = 2;
    $batas_toleransi = $set['s2_batas'];
    $nama_shift      = 'Shift 2 (Malam)';
}

if ($shift_aktif === 2 && $t_skrg < $t_s1_mul) {
    $is_late = true;
} else {
    $is_late = $t_skrg > strtotime($batas_toleransi);
}
?>

<div class="card slide-up">
    <div class="card-title">
        <i class="fas fa-fingerprint" style="color:var(--primary)"></i> Presensi Kasir
    </div>

    <?php if (!$absen): ?>

        <form id="formAbsen" action="../proses.php?action=absen_masuk" method="POST">
            <input type="hidden" name="shift" value="<?= $shift_aktif ?>">

            <div style="text-align:center; padding:20px; background:#F2F2F7; border-radius:15px; margin-bottom:20px;">
                <div style="font-size:11px; font-weight:700; color:#8E8E93; letter-spacing:1px;">SHIFT TERDETEKSI</div>
                <div style="font-size:18px; font-weight:800; color:var(--primary); margin:5px 0;"><?= $nama_shift ?></div>
                <div style="font-size:13px; color:#1C1C1E;">Batas Toleransi: <b style="color:var(--danger)"><?= date('H:i', strtotime($batas_toleransi)) ?></b></div>
                <?php if ($is_late): ?>
                <div style="margin-top:8px; font-size:12px; background:rgba(255,149,0,0.15); color:var(--warning); padding:5px 10px; border-radius:8px; display:inline-block; font-weight:600;">
                    <i class="fas fa-info-circle"></i> Terlambat (tidak kena sanksi skor untuk absen)
                </div>
                <?php endif; ?>
            </div>

            <button type="button" onclick="konfirAbsen('masuk')" style="background:var(--success); color:white; border:none; width:100%; padding:15px; border-radius:12px; font-size:16px; font-weight:bold; cursor:pointer; margin-bottom:15px;">
                <i class="fas fa-sign-in-alt"></i> Absen Masuk
            </button>
        </form>

        <div style="display:flex; gap:10px;">
            <button type="button" onclick="ajukan('Sakit', <?= $shift_aktif ?>)" style="flex:1; background:#FFF5F5; color:var(--danger); border:1px solid rgba(255,59,48,0.2); padding:12px; border-radius:12px; font-weight:bold; cursor:pointer;"><i class="fas fa-notes-medical"></i> Ajukan Sakit</button>
            <button type="button" onclick="ajukan('Izin', <?= $shift_aktif ?>)" style="flex:1; background:#F0EFFF; color:var(--primary); border:1px solid rgba(88,86,214,0.2); padding:12px; border-radius:12px; font-weight:bold; cursor:pointer;"><i class="fas fa-envelope-open-text"></i> Ajukan Izin</button>
        </div>

    <?php elseif ($absen && in_array($absen['status_kehadiran'], ['Sakit', 'Izin'])): ?>
        <div style="text-align:center; padding:30px 0;">
            <div style="width:70px; height:70px; background:#FFF5E5; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;">
                <i class="fas <?= $absen['status_kehadiran'] == 'Sakit' ? 'fa-notes-medical' : 'fa-envelope-open-text' ?>" style="font-size:30px; color:var(--warning);"></i>
            </div>
            <div style="font-size:18px; font-weight:800; color:var(--warning); margin-bottom:8px;">STATUS: <?= strtoupper($absen['status_kehadiran']) ?></div>
            <div style="font-size:13px; color:#8E8E93; margin-bottom:20px;">Anda telah mengajukan <?= $absen['status_kehadiran'] ?> untuk hari ini.</div>
            <?php if ($absen['status_alasan'] === 'approved'): ?>
                <span style="background:#E2F9E9; color:var(--success); padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700;"><i class="fas fa-check"></i> Disetujui SPV</span>
            <?php elseif ($absen['status_alasan'] === 'rejected'): ?>
                <span style="background:#FFE5E5; color:var(--danger); padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700;"><i class="fas fa-times"></i> Ditolak SPV</span>
            <?php else: ?>
                <span style="background:#FFF5E5; color:var(--warning); padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700;"><i class="fas fa-hourglass-half"></i> Menunggu Approval SPV</span>
            <?php endif; ?>
        </div>

    <?php elseif ($absen && $absen['waktu_keluar'] == NULL): ?>

        <div style="text-align:center; padding:30px 0;">
            <div style="font-size:13px; color:#8E8E93; margin-bottom:5px; font-weight:600;">WAKTU MASUK</div>
            <div style="font-size:28px; font-weight:800; color:var(--text); margin-bottom:25px;"><?= $absen['waktu_masuk'] ?></div>
            <button onclick="konfirAbsen('pulang')" style="background:var(--danger); color:white; border:none; width:100%; padding:15px; border-radius:12px; font-size:16px; font-weight:bold; cursor:pointer;">
                <i class="fas fa-sign-out-alt"></i> Absen Pulang
            </button>
        </div>

    <?php else: ?>

        <div style="text-align:center; padding:30px 0;">
            <div style="width:70px; height:70px; background:#E2F9E9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;"><i class="fas fa-check" style="font-size:30px; color:var(--success);"></i></div>
            <div style="font-size:18px; font-weight:800; color:var(--success); margin-bottom:8px;">KERJA SELESAI</div>
        </div>

    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>

<script>
function konfirAbsen(jenis) {
    Swal.fire({
        title: 'Konfirmasi',
        text: jenis === 'masuk' ? 'Lanjutkan absen masuk?' : 'Lanjutkan absen pulang?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: jenis === 'masuk' ? '#34C759' : '#FF3B30',
        cancelButtonColor: '#E5E5EA',
        cancelButtonText: '<span style="color:#1C1C1E;font-weight:bold;">Batal</span>',
        confirmButtonText: 'Ya, Lanjutkan'
    }).then((r) => {
        if (r.isConfirmed) {
            if (jenis === 'masuk') document.getElementById('formAbsen').submit();
            else window.location.href = '../proses.php?action=absen_keluar';
        }
    });
}

function ajukan(jenis, shift) {
    Swal.fire({
        title: `Ajukan ${jenis}`,
        input: 'textarea',
        inputPlaceholder: `Tulis alasan pengajuan ${jenis} Anda...`,
        html: `<p style="font-size:13px; color:var(--danger); margin-top:5px;">Perhatian: Mengajukan Izin/Sakit akan memotong 5 poin. Skor dikembalikan jika disetujui SPV.</p>`,
        showCancelButton: true,
        confirmButtonText: 'Kirim Pengajuan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#5856D6',
        preConfirm: (alasan) => {
            if (!alasan || alasan.trim().length < 5) Swal.showValidationMessage('Alasan minimal 5 karakter!');
            return alasan;
        }
    }).then((res) => {
        if (res.isConfirmed) {
            const f = document.createElement('form');
            f.method = 'POST'; f.action = '../proses.php?action=ajukan_izin_sakit';
            const iJenis = document.createElement('input'); iJenis.type='hidden'; iJenis.name='jenis'; iJenis.value=jenis;
            const iShift = document.createElement('input'); iShift.type='hidden'; iShift.name='shift'; iShift.value=shift;
            const iAlasan = document.createElement('input'); iAlasan.type='hidden'; iAlasan.name='alasan'; iAlasan.value=res.value;
            f.appendChild(iJenis); f.appendChild(iShift); f.appendChild(iAlasan);
            document.body.appendChild(f); f.submit();
        }
    });
}
</script>