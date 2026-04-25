<?php 
include '../header.php'; 
$stmt = $pdo->query("SELECT * FROM settings WHERE id=1");
$set = $stmt->fetch();
$s1_m = !empty($set['s1_mulai']) ? date('H:i', strtotime($set['s1_mulai'])) : '08:00';
$s1_b = !empty($set['s1_batas']) ? date('H:i', strtotime($set['s1_batas'])) : '08:30';
$s2_m = !empty($set['s2_mulai']) ? date('H:i', strtotime($set['s2_mulai'])) : '20:00';
$s2_b = !empty($set['s2_batas']) ? date('H:i', strtotime($set['s2_batas'])) : '20:30';
$success = $_GET['success'] ?? 0;
?>

<div class="card slide-up">
    <div class="card-title"><i class="fas fa-sliders-h" style="color:var(--primary)"></i> Konfigurasi Shift Kerja</div>
    
    <form id="formSeting" action="../proses.php?action=update_jam" method="POST">
        <div style="background: #F9F9F9; padding: 20px; border-radius: 16px; margin-bottom: 15px; border:1px solid rgba(0,0,0,0.03);">
            <div style="font-weight: 800; margin-bottom: 20px; font-size:16px; display:flex; align-items:center; gap:8px;"><div style="width:30px; height:30px; background:#FFF5E5; border-radius:8px; display:flex; align-items:center; justify-content:center;"><i class="fas fa-sun" style="color:var(--warning)"></i></div> Shift 1 (Pagi)</div>
            <label style="font-size: 11px; color: #8E8E93; font-weight: 800; letter-spacing:0.5px;">JAM MULAI ABSEN</label>
            <input type="time" name="s1_mulai" value="<?= $s1_m ?>" required style="width:100%; border:1px solid #E5E5EA; border-radius:10px; padding:12px; margin-bottom:15px; font-size:15px; outline:none; font-family:inherit;">
            <label style="font-size: 11px; color: #8E8E93; font-weight: 800; letter-spacing:0.5px;">BATAS TOLERANSI (LEWAT = TELAT)</label>
            <input type="time" name="s1_batas" value="<?= $s1_b ?>" required style="width:100%; border:1px solid #E5E5EA; border-radius:10px; padding:12px; font-size:15px; outline:none; font-family:inherit;">
        </div>

        <div style="background: #F9F9F9; padding: 20px; border-radius: 16px; margin-bottom: 25px; border:1px solid rgba(0,0,0,0.03);">
            <div style="font-weight: 800; margin-bottom: 20px; font-size:16px; display:flex; align-items:center; gap:8px;"><div style="width:30px; height:30px; background:#E5E5EA; border-radius:8px; display:flex; align-items:center; justify-content:center;"><i class="fas fa-moon" style="color:var(--text)"></i></div> Shift 2 (Malam)</div>
            <label style="font-size: 11px; color: #8E8E93; font-weight: 800; letter-spacing:0.5px;">JAM MULAI ABSEN</label>
            <input type="time" name="s2_mulai" value="<?= $s2_m ?>" required style="width:100%; border:1px solid #E5E5EA; border-radius:10px; padding:12px; margin-bottom:15px; font-size:15px; outline:none; font-family:inherit;">
            <label style="font-size: 11px; color: #8E8E93; font-weight: 800; letter-spacing:0.5px;">BATAS TOLERANSI (LEWAT = TELAT)</label>
            <input type="time" name="s2_batas" value="<?= $s2_b ?>" required style="width:100%; border:1px solid #E5E5EA; border-radius:10px; padding:12px; font-size:15px; outline:none; font-family:inherit;">
        </div>
        
        <button type="button" onclick="konfirSimpan()" style="width: 100%; background: var(--primary); color: white; padding: 16px; border-radius: 12px; font-weight: bold; font-size:16px; border:none; cursor:pointer; box-shadow:0 4px 15px rgba(88, 86, 214, 0.3); transition:0.2s;">
            <i class="fas fa-save"></i> Terapkan Pengaturan
        </button>
    </form>
</div>

<?php include '../footer.php'; ?>

<script>
    <?php if($success): ?>
    Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Pengaturan jam berhasil diperbarui', showConfirmButton: false, timer: 2000 });
    <?php endif; ?>

    function konfirSimpan() {
        Swal.fire({
            title: 'Simpan Perubahan?',
            text: 'Jadwal absensi seluruh staf akan mengikuti jam yang baru.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#5856D6',
            cancelButtonText: 'Batal',
            confirmButtonText: 'Ya, Simpan'
        }).then((result) => {
            if (result.isConfirmed) document.getElementById('formSeting').submit();
        })
    }
</script>