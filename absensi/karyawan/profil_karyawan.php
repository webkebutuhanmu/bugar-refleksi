<?php 
include '../header.php'; 
$success_msg = $_GET['success'] ?? '';
$error_msg   = $_GET['error']   ?? '';

// Hitung total absen dan keterlambatan
$stmtStats = $pdo->prepare("SELECT 
    COUNT(*) as total_absen,
    SUM(CASE WHEN status_kehadiran = 'Terlambat' THEN 1 ELSE 0 END) as total_terlambat,
    SUM(CASE WHEN status_kehadiran = 'Tepat Waktu' THEN 1 ELSE 0 END) as total_tepat
    FROM attendance WHERE user_id = ?");
$stmtStats->execute([$user_id]);
$stats = $stmtStats->fetch();
?>

<div class="card slide-up">
    <div class="card-title"><i class="fas fa-user-circle" style="color:var(--primary)"></i> Profil Akun</div>

    <!-- Avatar & Info Utama -->
    <div style="display:flex; align-items:center; gap:20px; padding:20px; background:linear-gradient(135deg, #5856D6, #7E7CE6); border-radius:16px; margin-bottom:20px; color:white;">
        <div style="width:72px; height:72px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; border:3px solid rgba(255,255,255,0.4);">
            <i class="fas fa-user" style="font-size:30px;"></i>
        </div>
        <div>
            <div style="font-size:20px; font-weight:800; letter-spacing:-0.3px;"><?= htmlspecialchars($me['nama_lengkap']) ?></div>
            <div style="font-size:13px; opacity:0.85; margin:3px 0;">@<?= htmlspecialchars($me['username']) ?></div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                <span style="background:rgba(255,255,255,0.2); padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase;"><?= $me['role'] ?></span>
                <span style="background:rgba(255,149,0,0.3); padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700;"><i class="fas fa-star"></i> Skor <?= $me['credit_score'] ?></span>
            </div>
        </div>
    </div>

    <!-- Detail Info -->
    <div style="display:grid; gap:10px; margin-bottom:5px;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:13px 16px; background:#F9F9F9; border-radius:12px;">
            <span style="color:#8E8E93; font-size:13px; display:flex; align-items:center; gap:8px;"><i class="fas fa-id-card" style="width:16px; color:var(--primary);"></i> Nama Lengkap</span>
            <span style="font-weight:700; font-size:13px; color:#1C1C1E;"><?= htmlspecialchars($me['nama_lengkap']) ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:13px 16px; background:#F9F9F9; border-radius:12px;">
            <span style="color:#8E8E93; font-size:13px; display:flex; align-items:center; gap:8px;"><i class="fas fa-at" style="width:16px; color:var(--primary);"></i> Username</span>
            <span style="font-weight:700; font-size:13px; color:#1C1C1E;"><?= htmlspecialchars($me['username']) ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:13px 16px; background:#F9F9F9; border-radius:12px;">
            <span style="color:#8E8E93; font-size:13px; display:flex; align-items:center; gap:8px;"><i class="fas fa-user-tag" style="width:16px; color:var(--primary);"></i> Role / Jabatan</span>
            <span style="font-weight:700; font-size:13px; color:#1C1C1E; text-transform:capitalize;"><?= $me['role'] ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:13px 16px; background:#FFF5E5; border-radius:12px; border:1px solid rgba(255,149,0,0.15);">
            <span style="color:#8E8E93; font-size:13px; display:flex; align-items:center; gap:8px;"><i class="fas fa-star" style="width:16px; color:var(--warning);"></i> Credit Score</span>
            <span style="font-weight:800; font-size:17px; color:<?= $me['credit_score'] < 80 ? 'var(--danger)' : 'var(--warning)' ?>;"><?= $me['credit_score'] ?></span>
        </div>
    </div>
</div>

<!-- Statistik Absensi -->
<div class="card slide-up delay-1">
    <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--success)"></i> Statistik Absensi</div>
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <div style="flex:1; min-width:100px; background:linear-gradient(135deg,#34C759,#30D158); border-radius:14px; padding:18px 15px; text-align:center; color:white;">
            <div style="font-size:28px; font-weight:800;"><?= $stats['total_absen'] ?? 0 ?></div>
            <div style="font-size:11px; font-weight:700; opacity:0.9; margin-top:3px;">TOTAL HADIR</div>
        </div>
        <div style="flex:1; min-width:100px; background:linear-gradient(135deg,#34C759,#30D158); border-radius:14px; padding:18px 15px; text-align:center; color:white;">
            <div style="font-size:28px; font-weight:800;"><?= $stats['total_tepat'] ?? 0 ?></div>
            <div style="font-size:11px; font-weight:700; opacity:0.9; margin-top:3px;">TEPAT WAKTU</div>
        </div>
        <div style="flex:1; min-width:100px; background:linear-gradient(135deg,#FF3B30,#FF6B60); border-radius:14px; padding:18px 15px; text-align:center; color:white;">
            <div style="font-size:28px; font-weight:800;"><?= $stats['total_terlambat'] ?? 0 ?></div>
            <div style="font-size:11px; font-weight:700; opacity:0.9; margin-top:3px;">TERLAMBAT</div>
        </div>
    </div>
</div>

<!-- Ubah Password -->
<div class="card slide-up delay-2">
    <div class="card-title"><i class="fas fa-lock" style="color:var(--warning)"></i> Ubah Password</div>

    <?php if($success_msg === '1'): ?>
    <div style="background:#E2F9E9; border-left:4px solid var(--success); padding:13px 16px; border-radius:12px; margin-bottom:18px; font-size:13px; color:#1a7a35; font-weight:600; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-check-circle" style="font-size:16px;"></i> Password berhasil diubah! Silakan login kembali jika diperlukan.
    </div>
    <?php elseif(!empty($error_msg)): ?>
    <div style="background:#FFE5E5; border-left:4px solid var(--danger); padding:13px 16px; border-radius:12px; margin-bottom:18px; font-size:13px; color:#cc0000; font-weight:600; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-exclamation-circle" style="font-size:16px;"></i> <?= htmlspecialchars($error_msg) ?>
    </div>
    <?php endif; ?>

    <form id="formPassword" action="../proses.php?action=update_password" method="POST">

        <label style="font-size:11px; color:#8E8E93; font-weight:800; letter-spacing:0.5px; display:block; margin-bottom:7px;">PASSWORD LAMA</label>
        <div style="position:relative; margin-bottom:16px;">
            <input type="password" name="password_lama" id="passLama" placeholder="Masukkan password lama" required
                style="width:100%; border:1.5px solid #E5E5EA; border-radius:12px; padding:13px 46px 13px 15px; font-size:14px; outline:none; font-family:inherit; box-sizing:border-box; transition:border-color 0.2s;"
                onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='#E5E5EA'">
            <i class="fas fa-eye" onclick="togglePass('passLama', this)"
                style="position:absolute; right:15px; top:50%; transform:translateY(-50%); color:#8E8E93; cursor:pointer; font-size:15px;"></i>
        </div>

        <label style="font-size:11px; color:#8E8E93; font-weight:800; letter-spacing:0.5px; display:block; margin-bottom:7px;">PASSWORD BARU</label>
        <div style="position:relative; margin-bottom:16px;">
            <input type="password" name="password_baru" id="passBaru" placeholder="Minimal 6 karakter" required minlength="6"
                style="width:100%; border:1.5px solid #E5E5EA; border-radius:12px; padding:13px 46px 13px 15px; font-size:14px; outline:none; font-family:inherit; box-sizing:border-box; transition:border-color 0.2s;"
                onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='#E5E5EA'">
            <i class="fas fa-eye" onclick="togglePass('passBaru', this)"
                style="position:absolute; right:15px; top:50%; transform:translateY(-50%); color:#8E8E93; cursor:pointer; font-size:15px;"></i>
        </div>

        <label style="font-size:11px; color:#8E8E93; font-weight:800; letter-spacing:0.5px; display:block; margin-bottom:7px;">KONFIRMASI PASSWORD BARU</label>
        <div style="position:relative; margin-bottom:24px;">
            <input type="password" name="password_konfirm" id="passKonfirm" placeholder="Ulangi password baru" required
                style="width:100%; border:1.5px solid #E5E5EA; border-radius:12px; padding:13px 46px 13px 15px; font-size:14px; outline:none; font-family:inherit; box-sizing:border-box; transition:border-color 0.2s;"
                onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='#E5E5EA'">
            <i class="fas fa-eye" onclick="togglePass('passKonfirm', this)"
                style="position:absolute; right:15px; top:50%; transform:translateY(-50%); color:#8E8E93; cursor:pointer; font-size:15px;"></i>
        </div>

        <button type="button" onclick="konfirUbahPass()"
            style="width:100%; background:var(--primary); color:white; padding:15px; border-radius:12px; font-size:15px; font-weight:bold; border:none; cursor:pointer; box-shadow:0 4px 15px rgba(88,86,214,0.3); transition:0.2s;"
            onmouseover="this.style.background='var(--primary-light)'" onmouseout="this.style.background='var(--primary)'">
            <i class="fas fa-key"></i> Ubah Password
        </button>
    </form>
</div>

<?php include '../footer.php'; ?>
<script>
function togglePass(id, el) {
    const input = document.getElementById(id);
    if (input.type === 'password') {
        input.type = 'text';
        el.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        el.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
function konfirUbahPass() {
    const lama    = document.getElementById('passLama').value.trim();
    const baru    = document.getElementById('passBaru').value;
    const konfirm = document.getElementById('passKonfirm').value;
    if (!lama)              return Swal.fire('Gagal', 'Password lama harus diisi!', 'error');
    if (baru.length < 6)    return Swal.fire('Gagal', 'Password baru minimal 6 karakter!', 'error');
    if (baru !== konfirm)   return Swal.fire('Gagal', 'Konfirmasi password tidak cocok!', 'error');
    Swal.fire({
        title: 'Ubah Password?',
        text: 'Pastikan Anda mengingat password baru Anda.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#5856D6',
        cancelButtonText: 'Batal',
        confirmButtonText: 'Ya, Ubah'
    }).then((r) => {
        if (r.isConfirmed) document.getElementById('formPassword').submit();
    });
}
</script>