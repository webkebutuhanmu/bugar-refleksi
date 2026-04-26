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
        <i class="fas fa-fingerprint" style="color:var(--primary)"></i> Presensi Supervisor
    </div>

    <?php if (!$absen): ?>

        <div style="text-align:center; padding:20px; background:#F2F2F7; border-radius:15px; margin-bottom:20px;">
            <div style="font-size:11px; font-weight:700; color:#8E8E93; letter-spacing:1px; margin-bottom:4px;">SHIFT TERDETEKSI</div>
            <div style="font-size:18px; font-weight:800; color:var(--primary); margin:5px 0;"><?= $nama_shift ?></div>
            <div style="font-size:13px; color:#1C1C1E;">Mulai: <b style="color:var(--success)"><?= date('H:i', strtotime($set['s1_mulai'])) ?></b> &nbsp;|&nbsp; Batas Toleransi: <b style="color:var(--danger)"><?= date('H:i', strtotime($batas_toleransi)) ?></b></div>
        </div>

        <?php if ($is_late): ?>
        <div style="background:linear-gradient(135deg,#FF3B30,#FF6B60); border-radius:14px; padding:18px 20px; margin-bottom:20px; color:white;">
            <div style="font-size:15px; font-weight:800; margin-bottom:6px;"><i class="fas fa-exclamation-triangle"></i> ANDA TERLAMBAT</div>
            <div style="font-size:12px; opacity:0.92; line-height:1.5;">Keterlambatan SPV akan langsung dilaporkan ke Owner. Credit score berkurang 5 poin, menunggu approval Owner.</div>
        </div>
        <?php endif; ?>

        <form id="formAbsen" action="../proses.php?action=absen_masuk_spv" method="POST">
            <input type="hidden" name="shift" value="<?= $shift_aktif ?>">

            <?php if ($is_late): ?>
                <label style="font-size:11px; color:#8E8E93; font-weight:800; letter-spacing:0.5px; display:block; margin-bottom:8px;">ALASAN KETERLAMBATAN</label>
                <textarea name="alasan" id="alasanTeks" placeholder="Tulis alasan keterlambatan..." style="width:100%; border-radius:12px; padding:15px; border:1.5px solid #E5E5EA; margin-bottom:20px; font-size:14px; background:#F9F9F9; outline:none; font-family:inherit; box-sizing:border-box; resize:vertical;" rows="3" required></textarea>
            <?php endif; ?>

            <button type="button" onclick="konfirAbsen('masuk', <?= $is_late ? 'true' : 'false' ?>)" style="background:<?= $is_late ? 'var(--danger)' : 'var(--success)' ?>; color:white; border:none; width:100%; padding:16px; border-radius:12px; font-size:16px; font-weight:bold; cursor:pointer; box-shadow:0 4px 15px rgba(0,0,0,0.15); margin-bottom:15px;">
                <i class="fas fa-sign-in-alt"></i> <?= $is_late ? 'Absen & Laporkan Owner' : 'Absen Masuk' ?>
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
            <div style="font-size:13px; color:#8E8E93; margin-bottom:20px;">Anda telah mengajukan <?= $absen['status_kehadiran'] ?> ke Owner untuk hari ini.</div>
            
            <?php if ($absen['status_alasan'] === 'approved'): ?>
                <span style="background:#E2F9E9; color:var(--success); padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700;"><i class="fas fa-check"></i> Disetujui Owner</span>
            <?php elseif ($absen['status_alasan'] === 'rejected'): ?>
                <span style="background:#FFE5E5; color:var(--danger); padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700;"><i class="fas fa-times"></i> Ditolak Owner</span>
            <?php else: ?>
                <span style="background:#FFF5E5; color:var(--warning); padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700;"><i class="fas fa-hourglass-half"></i> Menunggu Owner</span>
            <?php endif; ?>
        </div>

    <?php elseif ($absen && $absen['waktu_keluar'] == NULL): ?>

        <div style="text-align:center; padding:30px 0;">
            <div style="width:70px; height:70px; background:#FFF5E5; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;"><i class="fas fa-briefcase" style="font-size:28px; color:var(--warning);"></i></div>
            <div style="font-size:13px; color:#8E8E93; font-weight:600; margin-bottom:6px;">WAKTU MASUK</div>
            <div style="font-size:32px; font-weight:800; color:var(--text); margin-bottom:10px;"><?= $absen['waktu_masuk'] ?></div>
            <span class="status-pill <?= $absen['status_kehadiran'] == 'Tepat Waktu' ? 'pill-tepat' : 'pill-telat' ?>" style="margin-bottom:20px; display:inline-block;"><?= $absen['status_kehadiran'] ?></span>
            <button onclick="konfirAbsen('pulang', false)" style="background:var(--danger); color:white; border:none; width:100%; padding:16px; border-radius:12px; font-size:16px; font-weight:bold; cursor:pointer;"><i class="fas fa-sign-out-alt"></i> Absen Pulang</button>
        </div>

    <?php else: ?>

        <div style="text-align:center; padding:40px 0;">
            <div style="width:80px; height:80px; background:#E2F9E9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;"><i class="fas fa-check" style="font-size:35px; color:var(--success);"></i></div>
            <div style="font-size:20px; font-weight:800; color:var(--success); margin-bottom:8px;">KERJA SELESAI</div>
        </div>

    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>

<script>
function konfirAbsen(jenis, isLate) {
    if (jenis === 'masuk' && isLate) {
        const el = document.getElementById('alasanTeks');
        if (!el || el.value.trim().length < 5) return Swal.fire('Gagal', 'Alasan keterlambatan harus diisi minimal 5 karakter!', 'error');
    }
    const c = jenis === 'pulang' ? {title:'Absen Pulang?',text:'Pastikan tugas selesai.',icon:'question',color:'#FF3B30'} : (isLate ? {title:'Absen Terlambat?',text:'Laporan ke Owner. Skor -5 poin.',icon:'warning',color:'#FF3B30'} : {title:'Konfirmasi Absen',text:'Tercatat hadir tepat waktu.',icon:'question',color:'#34C759'});
    Swal.fire({
        title: c.title, text: c.text, icon: c.icon, showCancelButton: true, confirmButtonColor: c.color, cancelButtonColor: '#E5E5EA', cancelButtonText: '<span style="color:#1C1C1E;font-weight:bold;">Batal</span>', confirmButtonText: 'Ya, Lanjutkan'
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
        inputPlaceholder: `Tulis alasan ${jenis} ke Owner...`,
        html: `<p style="font-size:13px; color:var(--danger); margin-top:5px;">Perhatian: Skor Anda terpotong 5 poin dan akan diteruskan ke Owner. Jika disetujui, skor kembali.</p>`,
        showCancelButton: true,
        confirmButtonText: 'Kirim ke Owner',
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