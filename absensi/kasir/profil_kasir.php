<?php 
include '../header.php'; 
$success_msg = $_GET['success'] ?? '';
$error_msg   = $_GET['error']   ?? '';
$active_tab  = $_GET['tab']     ?? 'profil';

// Ambil info cabang kasir
$stmtBranch = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
$stmtBranch->execute([$me['branch_id']]);
$branch = $stmtBranch->fetch();

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
    <div class="card-title"><i class="fas fa-user-circle" style="color:var(--primary)"></i> Profil Akun Kasir</div>

    <!-- Avatar & Info Utama -->
    <div style="display:flex; align-items:center; gap:20px; padding:20px; background:linear-gradient(135deg, #5856D6, #7E7CE6); border-radius:16px; margin-bottom:20px; color:white;">
        
        <!-- Avatar Area -->
        <div style="position:relative; width:72px; height:72px; flex-shrink:0;">
            <?php if (!empty($me['foto_profil']) && file_exists('../' . $me['foto_profil'])): ?>
            <!-- Jika ada foto: klik foto untuk melihat versi HD -->
            <img id="avatarImg" src="../<?= htmlspecialchars($me['foto_profil']) ?>?v=<?= time() ?>"
                 onclick="lihatFotoHD(this.src)" 
                 style="width:72px; height:72px; border-radius:50%; object-fit:cover; border:3px solid rgba(255,255,255,0.4); cursor:pointer;">
            <?php else: ?>
            <!-- Jika belum ada foto: klik ikon akan membuka pilih foto -->
            <div id="avatarImg" onclick="document.getElementById('inputFoto').click()" style="width:72px; height:72px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; align-items:center; justify-content:center; border:3px solid rgba(255,255,255,0.4); cursor:pointer;">
                <i class="fas fa-user" style="font-size:30px;"></i>
            </div>
            <?php endif; ?>
            
            <!-- Ikon Kamera Kecil untuk Mengganti Foto -->
            <div onclick="document.getElementById('inputFoto').click()" style="position:absolute; bottom:0; right:0; background:#fff; border-radius:50%; width:22px; height:22px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,0.2); cursor:pointer;">
                <i class="fas fa-camera" style="font-size:10px; color:#5856D6;"></i>
            </div>
        </div>
        <input type="file" id="inputFoto" accept="image/*,.heic,.heif" style="display:none" onchange="bukaModalCrop(this)">

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
        <?php
        $info_rows = [
            ['fas fa-id-card',       'var(--primary)', 'Nama Lengkap',   htmlspecialchars($me['nama_lengkap'])],
            ['fas fa-at',            'var(--primary)', 'Username',        htmlspecialchars($me['username'])],
            ['fas fa-user-tag',      'var(--primary)', 'Role / Jabatan',  ucfirst($me['role'])],
            ['fas fa-birthday-cake', '#FF9500',        'Tempat, Tgl Lahir', htmlspecialchars($me['ttl'] ?? '-')],
            ['fas fa-phone',         '#34C759',        'No. HP',           htmlspecialchars($me['no_hp'] ?? '-')],
            ['fas fa-envelope',      '#5856D6',        'Email',            htmlspecialchars($me['email'] ?? '-')],
            ['fas fa-map-marker-alt','var(--danger)',  'Cabang',           htmlspecialchars($branch['nama_cabang'] ?? '-')],
        ];
        foreach ($info_rows as $r):
        ?>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:13px 16px; background:#F9F9F9; border-radius:12px;">
            <span style="color:#8E8E93; font-size:13px; display:flex; align-items:center; gap:8px;"><i class="<?= $r[0] ?>" style="width:16px; color:<?= $r[1] ?>;"></i> <?= $r[2] ?></span>
            <span style="font-weight:700; font-size:13px; color:#1C1C1E;"><?= $r[3] ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:13px 16px; background:#FFF5E5; border-radius:12px; border:1px solid rgba(255,149,0,0.15);">
            <span style="color:#8E8E93; font-size:13px; display:flex; align-items:center; gap:8px;"><i class="fas fa-star" style="width:16px; color:var(--warning);"></i> Credit Score</span>
            <span style="font-weight:800; font-size:17px; color:<?= $me['credit_score'] < 80 ? 'var(--danger)' : 'var(--warning)' ?>;"><?= $me['credit_score'] ?></span>
        </div>
    </div>

    <!-- Tombol Edit Profil -->
    <button onclick="toggleEditProfil()" id="btnEditProfil"
        style="width:100%; margin-top:14px; background:var(--primary); color:white; padding:13px; border-radius:12px; font-size:14px; font-weight:700; border:none; cursor:pointer; box-shadow:0 4px 15px rgba(88,86,214,0.25);">
        <i class="fas fa-pencil-alt"></i> Edit Profil
    </button>

    <!-- Form Edit Profil (tersembunyi) -->
    <div id="formEditWrap" style="display:none; margin-top:16px; border-top:1.5px solid #F0F0F0; padding-top:16px;">
        <?php if($success_msg === '2'): ?>
        <div style="background:#E2F9E9; border-left:4px solid var(--success); padding:13px 16px; border-radius:12px; margin-bottom:14px; font-size:13px; color:#1a7a35; font-weight:600; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-check-circle"></i> Profil berhasil diperbarui!
        </div>
        <?php elseif($success_msg === '3'): ?>
        <div style="background:#E2F9E9; border-left:4px solid var(--success); padding:13px 16px; border-radius:12px; margin-bottom:14px; font-size:13px; color:#1a7a35; font-weight:600; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-check-circle"></i> Foto profil berhasil diperbarui!
        </div>
        <?php elseif($active_tab === 'profil' && !empty($error_msg)): ?>
        <div style="background:#FFE5E5; border-left:4px solid var(--danger); padding:13px 16px; border-radius:12px; margin-bottom:14px; font-size:13px; color:#cc0000; font-weight:600; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?>
        </div>
        <?php endif; ?>

        <form id="formEditProfil" action="../proses.php?action=update_profil" method="POST">
            <?php
            $fields = [
                ['nama_lengkap', 'fas fa-id-card',       'NAMA LENGKAP',        'text',  $me['nama_lengkap'], true],
                ['username',     'fas fa-at',             'USERNAME',             'text',  $me['username'],     true],
                ['ttl',          'fas fa-birthday-cake',  'TEMPAT, TANGGAL LAHIR','text',  $me['ttl'] ?? '',    false],
                ['no_hp',        'fas fa-phone',          'NO. HP / WA',          'tel',   $me['no_hp'] ?? '',  false],
                ['email',        'fas fa-envelope',       'EMAIL',                'email', $me['email'] ?? '',  false],
            ];
            foreach ($fields as [$name, $icon, $label, $type, $val, $req]):
            ?>
            <label style="font-size:11px; color:#8E8E93; font-weight:800; letter-spacing:0.5px; display:block; margin-bottom:7px;"><?= $label ?></label>
            <div style="position:relative; margin-bottom:14px;">
                <i class="<?= $icon ?>" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--primary); font-size:14px;"></i>
                <input type="<?= $type ?>" name="<?= $name ?>" value="<?= htmlspecialchars($val) ?>" <?= $req ? 'required' : '' ?>
                    placeholder="<?= $label ?>"
                    style="width:100%; border:1.5px solid #E5E5EA; border-radius:12px; padding:13px 15px 13px 40px; font-size:14px; outline:none; font-family:inherit; box-sizing:border-box; transition:border-color 0.2s;"
                    onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='#E5E5EA'">
            </div>
            <?php endforeach; ?>

            <button type="button" onclick="konfirEditProfil()"
                style="width:100%; background:var(--primary); color:white; padding:14px; border-radius:12px; font-size:14px; font-weight:700; border:none; cursor:pointer; box-shadow:0 4px 15px rgba(88,86,214,0.3);">
                <i class="fas fa-save"></i> Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<!-- Statistik Absensi -->
<div class="card slide-up delay-1">
    <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--success)"></i> Statistik Absensi</div>
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <div style="flex:1; min-width:100px; background:linear-gradient(135deg,#5856D6,#7E7CE6); border-radius:14px; padding:18px 15px; text-align:center; color:white;">
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
        <i class="fas fa-check-circle" style="font-size:16px;"></i> Password berhasil diubah!
    </div>
    <?php elseif($active_tab !== 'profil' && !empty($error_msg)): ?>
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

<!-- ===================== MODAL LIHAT FOTO HD ===================== -->
<div id="modalLihatFoto" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:10000; align-items:center; justify-content:center; padding:20px; flex-direction:column;">
    <div style="position:absolute; top:20px; right:20px; color:white; font-size:30px; cursor:pointer;" onclick="document.getElementById('modalLihatFoto').style.display='none'">
        <i class="fas fa-times"></i>
    </div>
    <img id="imgHD" src="" style="max-width:100%; max-height:80vh; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.5); object-fit:contain;">
</div>

<!-- ===================== MODAL CROP FOTO ===================== -->
<div id="modalCrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; border-radius:20px; width:100%; max-width:420px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.4);">
        <div style="background:linear-gradient(135deg,#5856D6,#7E7CE6); padding:16px 20px; display:flex; justify-content:space-between; align-items:center; color:white;">
            <span style="font-weight:800; font-size:16px;"><i class="fas fa-crop-alt"></i> Atur Foto Profil</span>
            <button onclick="tutupModalCrop()" style="background:rgba(255,255,255,0.2); border:none; color:white; width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:16px;">×</button>
        </div>

        <div style="background:#1C1C1E; position:relative; overflow:hidden; height:300px; cursor:grab;" id="cropArea"
             onmousedown="startDrag(event)" ontouchstart="startDragTouch(event)">
            <canvas id="cropCanvas" style="position:absolute; top:0; left:0;"></canvas>
            <div id="cropGuide" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
                 width:220px; height:220px; border-radius:50%; border:3px dashed rgba(255,255,255,0.7);
                 pointer-events:none; box-shadow:0 0 0 999px rgba(0,0,0,0.5);"></div>
        </div>

        <div style="padding:14px 20px 4px; background:#F9F9F9;">
            <div style="display:flex; align-items:center; gap:12px;">
                <button onclick="zoomFoto(-0.1)" style="background:#E5E5EA; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:16px; font-weight:800;">−</button>
                <div style="flex:1;">
                    <input type="range" id="sliderZoom" min="0.5" max="3" step="0.05" value="1"
                        oninput="setZoom(parseFloat(this.value))"
                        style="width:100%; accent-color:#5856D6;">
                </div>
                <button onclick="zoomFoto(0.1)" style="background:#E5E5EA; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:16px; font-weight:800;">+</button>
            </div>
            <p style="text-align:center; font-size:11px; color:#8E8E93; margin:6px 0 10px;">Geser untuk atur posisi · Scroll / Pinch untuk zoom</p>
        </div>

        <div style="display:flex; gap:10px; padding:0 20px 20px;">
            <button onclick="tutupModalCrop()" style="flex:1; padding:13px; border-radius:12px; border:1.5px solid #E5E5EA; background:white; font-size:14px; font-weight:700; color:#666; cursor:pointer;">Batal</button>
            <button onclick="simpanFoto()" style="flex:2; padding:13px; border-radius:12px; border:none; background:var(--primary); color:white; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 15px rgba(88,86,214,0.3);">
                <i class="fas fa-check"></i> Gunakan Foto Ini
            </button>
        </div>
    </div>
</div>

<!-- Form tersembunyi kirim foto -->
<form id="formFoto" action="../proses.php?action=update_foto" method="POST" style="display:none;">
    <input type="hidden" name="foto_data" id="fotoDataInput">
</form>

<!-- Library Konversi File iOS (HEIC) ke JPG -->
<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>

<?php include '../footer.php'; ?>
<script>
// ===== Toggle Edit Profil =====
function toggleEditProfil() {
    const wrap = document.getElementById('formEditWrap');
    const btn  = document.getElementById('btnEditProfil');
    if (wrap.style.display === 'none') {
        wrap.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-times"></i> Tutup';
        btn.style.background = '#8E8E93';
        btn.style.boxShadow = 'none';
    } else {
        wrap.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-pencil-alt"></i> Edit Profil';
        btn.style.background = 'var(--primary)';
        btn.style.boxShadow = '0 4px 15px rgba(88,86,214,0.25)';
    }
}
<?php if(in_array($success_msg,['2', '3']) || ($active_tab === 'profil' && !empty($error_msg))): ?>
document.addEventListener('DOMContentLoaded', () => toggleEditProfil());
<?php endif; ?>

function konfirEditProfil() {
    const nama = document.querySelector('[name="nama_lengkap"]').value.trim();
    const uname = document.querySelector('[name="username"]').value.trim();
    if (!nama || !uname) return Swal.fire('Gagal', 'Nama dan username wajib diisi!', 'error');
    Swal.fire({
        title: 'Simpan Perubahan?', text: 'Data profil akan diperbarui.',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#5856D6', cancelButtonText: 'Batal', confirmButtonText: 'Ya, Simpan'
    }).then(r => { if (r.isConfirmed) document.getElementById('formEditProfil').submit(); });
}

// ===== Password =====
function togglePass(id, el) {
    const input = document.getElementById(id);
    if (input.type === 'password') { input.type = 'text'; el.classList.replace('fa-eye','fa-eye-slash'); }
    else { input.type = 'password'; el.classList.replace('fa-eye-slash','fa-eye'); }
}
function konfirUbahPass() {
    const lama = document.getElementById('passLama').value.trim();
    const baru = document.getElementById('passBaru').value;
    const konfirm = document.getElementById('passKonfirm').value;
    if (!lama)           return Swal.fire('Gagal','Password lama harus diisi!','error');
    if (baru.length < 6) return Swal.fire('Gagal','Password baru minimal 6 karakter!','error');
    if (baru !== konfirm)return Swal.fire('Gagal','Konfirmasi password tidak cocok!','error');
    Swal.fire({ title:'Ubah Password?', text:'Pastikan Anda mengingat password baru Anda.',
        icon:'warning', showCancelButton:true, confirmButtonColor:'#5856D6',
        cancelButtonText:'Batal', confirmButtonText:'Ya, Ubah'
    }).then(r => { if (r.isConfirmed) document.getElementById('formPassword').submit(); });
}

// ===== LIHAT FOTO HD =====
function lihatFotoHD(src) {
    document.getElementById('imgHD').src = src;
    document.getElementById('modalLihatFoto').style.display = 'flex';
}

// ===== CROP / ZOOM FOTO DENGAN DUKUNGAN HEIC =====
let cropImg, cropX=0, cropY=0, cropScale=1;
let isDragging=false, dragStartX=0, dragStartY=0, dragOX=0, dragOY=0;
let lastPinchDist=0;

async function bukaModalCrop(input) {
    if (!input.files || !input.files[0]) return;
    let file = input.files[0];

    const isHeic = file.type === "image/heic" || file.type === "image/heif" || file.name.toLowerCase().endsWith(".heic");

    if (isHeic) {
        Swal.fire({title: 'Memproses Foto...', text: 'Mengonversi format iOS...', allowOutsideClick: false, didOpen: () => { Swal.showLoading() }});
        try {
            if (typeof heic2any === "undefined") throw new Error("Library HEIC tidak tersedia");
            const convertedBlob = await heic2any({ blob: file, toType: "image/jpeg", quality: 0.8 });
            file = Array.isArray(convertedBlob) ? convertedBlob[0] : convertedBlob;
            Swal.close();
        } catch (error) {
            Swal.fire('Error', 'Gagal memproses gambar format iOS. Silakan coba format JPG/PNG biasa.', 'error');
            input.value = '';
            return;
        }
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        cropImg = new Image();
        cropImg.onload = function() {
            const modal = document.getElementById('modalCrop');
            modal.style.display = 'flex'; 

            const area = document.getElementById('cropArea');
            const canvas = document.getElementById('cropCanvas');
            
            canvas.width  = area.offsetWidth;
            canvas.height = area.offsetHeight;
            
            const scaleW = canvas.width  / cropImg.width;
            const scaleH = canvas.height / cropImg.height;
            
            cropScale = Math.min(scaleW, scaleH, 1);
            
            document.getElementById('sliderZoom').value = cropScale;
            document.getElementById('sliderZoom').min   = cropScale * 0.5;
            document.getElementById('sliderZoom').max   = Math.max(3, cropScale * 10);
            
            cropX = (canvas.width  - cropImg.width  * cropScale) / 2;
            cropY = (canvas.height - cropImg.height * cropScale) / 2;
            
            renderCrop();
        };
        cropImg.onerror = function() {
            Swal.fire('Error', 'File tidak dapat dibaca sebagai gambar.', 'error');
        };
        cropImg.src = e.target.result;
    };
    reader.readAsDataURL(file);
    input.value = '';
}

function renderCrop() {
    const canvas = document.getElementById('cropCanvas');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if(canvas.width > 0 && canvas.height > 0) {
        ctx.drawImage(cropImg, cropX, cropY, cropImg.width * cropScale, cropImg.height * cropScale);
    }
}

function setZoom(val) {
    const canvas = document.getElementById('cropCanvas');
    const cx = canvas.width  / 2;
    const cy = canvas.height / 2;
    const ratio = val / cropScale;
    cropX = cx - (cx - cropX) * ratio;
    cropY = cy - (cy - cropY) * ratio;
    cropScale = val;
    renderCrop();
}

function zoomFoto(delta) {
    const slider = document.getElementById('sliderZoom');
    const newVal = Math.min(parseFloat(slider.max), Math.max(parseFloat(slider.min), cropScale + delta));
    slider.value = newVal;
    setZoom(newVal);
}

// Drag logic
function startDrag(e) {
    isDragging = true; dragStartX = e.clientX; dragStartY = e.clientY;
    dragOX = cropX; dragOY = cropY;
    document.getElementById('cropArea').style.cursor = 'grabbing';
    e.preventDefault();
}
document.addEventListener('mousemove', e => {
    if (!isDragging) return;
    cropX = dragOX + (e.clientX - dragStartX);
    cropY = dragOY + (e.clientY - dragStartY);
    renderCrop();
});
document.addEventListener('mouseup', () => {
    isDragging = false;
    const area = document.getElementById('cropArea');
    if (area) area.style.cursor = 'grab';
});

function startDragTouch(e) {
    if (e.touches.length === 1) {
        isDragging = true;
        dragStartX = e.touches[0].clientX; dragStartY = e.touches[0].clientY;
        dragOX = cropX; dragOY = cropY;
    }
}
document.addEventListener('touchmove', e => {
    if (e.touches.length === 2) {
        const d = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
        if (lastPinchDist) { const delta = (d - lastPinchDist) * 0.005; zoomFoto(delta); }
        lastPinchDist = d; isDragging = false;
    } else if (isDragging && e.touches.length === 1) {
        cropX = dragOX + (e.touches[0].clientX - dragStartX);
        cropY = dragOY + (e.touches[0].clientY - dragStartY);
        renderCrop();
    }
}, {passive:true});
document.addEventListener('touchend', () => { isDragging = false; lastPinchDist = 0; });

document.getElementById('cropArea') && document.getElementById('cropArea').addEventListener('wheel', e => {
    e.preventDefault();
    zoomFoto(e.deltaY < 0 ? 0.07 : -0.07);
}, {passive:false});

function tutupModalCrop() { document.getElementById('modalCrop').style.display = 'none'; }

function simpanFoto() {
    const canvas  = document.getElementById('cropCanvas');
    const guide   = document.getElementById('cropGuide');
    const area    = document.getElementById('cropArea');
    
    // PERBAIKAN PENTING: Resolusi Crop diperbesar untuk HD (Resolusi Tinggi)
    const size    = 600; 
    
    const out     = document.createElement('canvas');
    out.width = out.height = size;
    const ctx = out.getContext('2d');

    const areaRect  = area.getBoundingClientRect();
    const guideRect = guide.getBoundingClientRect();
    
    const gx = guideRect.left - areaRect.left;
    const gy = guideRect.top  - areaRect.top;
    const gr = guideRect.width;

    ctx.beginPath();
    ctx.arc(size/2, size/2, size/2, 0, Math.PI*2);
    ctx.clip();
    
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, size, size);
    
    try {
        ctx.drawImage(canvas, gx, gy, gr, gr, 0, 0, size, size);
    } catch(err) {
        console.error("Gagal melakukan crop: ", err);
        Swal.fire('Error', 'Gagal memproses gambar.', 'error');
        return;
    }

    const dataURL = out.toDataURL('image/jpeg', 0.95);

    const avatarEl = document.getElementById('avatarImg');
    if (avatarEl.tagName === 'IMG') {
        avatarEl.src = dataURL;
    } else {
        const img = document.createElement('img');
        img.id = 'avatarImg';
        img.src = dataURL;
        img.onclick = function() { lihatFotoHD(this.src); };
        img.style.cssText = 'width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,0.4); cursor:pointer;';
        avatarEl.replaceWith(img);
    }

    document.getElementById('fotoDataInput').value = dataURL;
    tutupModalCrop();

    Swal.fire({
        title: 'Gunakan foto ini?', icon: 'question',
        showCancelButton: true, confirmButtonColor: '#5856D6',
        cancelButtonText: 'Batal', confirmButtonText: 'Ya, Simpan'
    }).then(r => { if (r.isConfirmed) document.getElementById('formFoto').submit(); });
}
</script>