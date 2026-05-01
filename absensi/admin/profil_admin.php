<?php 
include '../header.php'; 
$success_msg = $_GET['success'] ?? '';
$error_msg   = $_GET['error']   ?? '';
$active_tab  = $_GET['tab']     ?? 'profil';
?>

<div class="card slide-up">
    <div class="card-title"><i class="fas fa-user-circle" style="color:var(--primary)"></i> Profil Akun Admin</div>

    <!-- Avatar & Info Utama -->
    <div style="display:flex; align-items:center; gap:20px; padding:20px; background:linear-gradient(135deg, #1C1C1E, #3A3A3C); border-radius:16px; margin-bottom:20px; color:white;">
        <div style="position:relative; width:72px; height:72px; flex-shrink:0;">
            <?php if (!empty($me['foto_profil']) && file_exists('../' . $me['foto_profil'])): ?>
            <img id="avatarImg" src="../<?= htmlspecialchars($me['foto_profil']) ?>?v=<?= time() ?>"
                 onclick="lihatFotoHD(this.src)" 
                 style="width:72px; height:72px; border-radius:50%; object-fit:cover; border:3px solid rgba(88,86,214,0.5); cursor:pointer;">
            <?php else: ?>
            <div id="avatarImg" onclick="document.getElementById('inputFoto').click()" style="width:72px; height:72px; background:rgba(255,255,255,0.15); border-radius:50%; display:flex; align-items:center; justify-content:center; border:3px solid rgba(88,86,214,0.5); cursor:pointer;">
                <i class="fas fa-user-shield" style="font-size:28px; color:#A4A3E3;"></i>
            </div>
            <?php endif; ?>
            <div onclick="document.getElementById('inputFoto').click()" style="position:absolute; bottom:0; right:0; background:#fff; border-radius:50%; width:22px; height:22px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,0.2); cursor:pointer;">
                <i class="fas fa-camera" style="font-size:10px; color:#1C1C1E;"></i>
            </div>
        </div>
        <input type="file" id="inputFoto" accept="image/*,.heic,.heif" style="display:none" onchange="bukaModalCrop(this)">

        <div>
            <div style="font-size:20px; font-weight:800; letter-spacing:-0.3px;"><?= htmlspecialchars($me['nama_lengkap']) ?></div>
            <div style="font-size:13px; opacity:0.85; margin:3px 0;">@<?= htmlspecialchars($me['username']) ?></div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                <span style="background:rgba(88,86,214,0.2); color:#A4A3E3; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:800; text-transform:uppercase; border:1px solid rgba(88,86,214,0.3);">
                    <i class="fas fa-shield-alt"></i> ADMIN
                </span>
            </div>
        </div>
    </div>

    <!-- Detail Info -->
    <div style="display:grid; gap:10px; margin-bottom:5px;">
        <?php
        $rows_info = [
            ['fas fa-id-card',       'var(--primary)', 'Nama Lengkap',    htmlspecialchars($me['nama_lengkap'])],
            ['fas fa-at',            'var(--primary)', 'Username',         htmlspecialchars($me['username'])],
            ['fas fa-birthday-cake', '#FF9500',        'Tempat, Tgl Lahir',htmlspecialchars($me['ttl']   ?? '-')],
            ['fas fa-phone',         '#34C759',        'No. HP',           htmlspecialchars($me['no_hp']  ?? '-')],
            ['fas fa-envelope',      '#5856D6',        'Email',            htmlspecialchars($me['email']  ?? '-')],
        ];
        foreach ($rows_info as $r):
        ?>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:13px 16px; background:#F9F9F9; border-radius:12px;">
            <span style="color:#8E8E93; font-size:13px; display:flex; align-items:center; gap:8px;"><i class="<?= $r[0] ?>" style="width:16px; color:<?= $r[1] ?>;"></i> <?= $r[2] ?></span>
            <span style="font-weight:700; font-size:13px; color:#1C1C1E;"><?= $r[3] ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <button onclick="toggleEditProfil()" id="btnEditProfil" style="width:100%; margin-top:14px; background:var(--primary); color:white; padding:13px; border-radius:12px; font-size:14px; font-weight:700; border:none; cursor:pointer; box-shadow:0 4px 15px rgba(88,86,214,0.25);"><i class="fas fa-pencil-alt"></i> Edit Profil</button>

    <!-- Form Edit -->
    <div id="formEditWrap" style="display:none; margin-top:16px; border-top:1.5px solid #F0F0F0; padding-top:16px;">
        <form id="formEditProfil" action="../proses.php?action=update_profil" method="POST">
            <?php
            $fields = [
                ['nama_lengkap','fas fa-id-card',      'NAMA LENGKAP',         'text',  $me['nama_lengkap'], true],
                ['username',    'fas fa-at',            'USERNAME',              'text',  $me['username'],     true],
                ['ttl',         'fas fa-birthday-cake', 'TEMPAT, TANGGAL LAHIR','text',  $me['ttl']   ?? '',  false],
                ['no_hp',       'fas fa-phone',         'NO. HP / WA',           'tel',   $me['no_hp']  ?? '',  false],
                ['email',       'fas fa-envelope',      'EMAIL',                 'email', $me['email']  ?? '',  false],
            ];
            foreach ($fields as [$name,$icon,$label,$type,$val,$req]):
            ?>
            <label style="font-size:11px; color:#8E8E93; font-weight:800; display:block; margin-bottom:7px;"><?= $label ?></label>
            <div style="position:relative; margin-bottom:14px;">
                <i class="<?= $icon ?>" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--primary); font-size:14px;"></i>
                <input type="<?= $type ?>" name="<?= $name ?>" value="<?= htmlspecialchars($val) ?>" <?= $req ? 'required' : '' ?> style="width:100%; border:1.5px solid #E5E5EA; border-radius:12px; padding:13px 15px 13px 40px; font-size:14px; outline:none; font-family:inherit; box-sizing:border-box;">
            </div>
            <?php endforeach; ?>
            <button type="button" onclick="konfirEditProfil()" style="width:100%; background:var(--primary); color:white; padding:14px; border-radius:12px; font-size:14px; font-weight:700; border:none; cursor:pointer; box-shadow:0 4px 15px rgba(88,86,214,0.3);"><i class="fas fa-save"></i> Simpan Perubahan</button>
        </form>
    </div>
</div>

<div class="card slide-up delay-2">
    <div class="card-title"><i class="fas fa-lock" style="color:var(--warning)"></i> Ubah Password</div>
    <form id="formPassword" action="../proses.php?action=update_password" method="POST">
        <label style="font-size:11px; color:#8E8E93; font-weight:800; display:block; margin-bottom:7px;">PASSWORD BARU</label>
        <div style="position:relative; margin-bottom:16px;">
            <input type="password" name="password_baru" id="passBaru" placeholder="Minimal 6 karakter" required minlength="6" style="width:100%; border:1.5px solid #E5E5EA; border-radius:12px; padding:13px 46px 13px 15px; font-size:14px; outline:none; box-sizing:border-box;">
        </div>
        <button type="button" onclick="document.getElementById('formPassword').submit()" style="width:100%; background:var(--primary); color:white; padding:15px; border-radius:12px; font-size:15px; font-weight:bold; border:none; cursor:pointer;"><i class="fas fa-key"></i> Ubah Password</button>
    </form>
</div>

<!-- ===================== MODAL CROP & HD SAMA PERSIS SEPERTI OWNER/KASIR ===================== -->
<div id="modalLihatFoto" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:10000; align-items:center; justify-content:center; padding:20px; flex-direction:column;">
    <div style="position:absolute; top:20px; right:20px; color:white; font-size:30px; cursor:pointer;" onclick="document.getElementById('modalLihatFoto').style.display='none'"><i class="fas fa-times"></i></div>
    <img id="imgHD" src="" style="max-width:100%; max-height:80vh; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.5); object-fit:contain;">
</div>

<div id="modalCrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <!-- Sisa struktur crop persis dengan yang ada sebelumnya -->
    <div style="background:#fff; border-radius:20px; width:100%; max-width:420px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.4);">
        <div style="background:linear-gradient(135deg,#5856D6,#7E7CE6); padding:16px 20px; display:flex; justify-content:space-between; align-items:center; color:white;">
            <span style="font-weight:800; font-size:16px;"><i class="fas fa-crop-alt"></i> Atur Foto Profil</span>
            <button onclick="document.getElementById('modalCrop').style.display='none'" style="background:rgba(255,255,255,0.2); border:none; color:white; width:30px; height:30px; border-radius:50%; cursor:pointer;">×</button>
        </div>
        <div style="background:#1C1C1E; position:relative; overflow:hidden; height:300px; cursor:grab;" id="cropArea" onmousedown="startDrag(event)" ontouchstart="startDragTouch(event)">
            <canvas id="cropCanvas" style="position:absolute; top:0; left:0;"></canvas>
            <div id="cropGuide" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:220px; height:220px; border-radius:50%; border:3px dashed rgba(255,255,255,0.7); pointer-events:none; box-shadow:0 0 0 999px rgba(0,0,0,0.5);"></div>
        </div>
        <div style="padding:14px 20px 4px; background:#F9F9F9;">
            <div style="display:flex; align-items:center; gap:12px;">
                <button onclick="zoomFoto(-0.1)" style="background:#E5E5EA; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; font-weight:800;">−</button>
                <input type="range" id="sliderZoom" min="0.5" max="3" step="0.05" value="1" oninput="setZoom(parseFloat(this.value))" style="flex:1; accent-color:#5856D6;">
                <button onclick="zoomFoto(0.1)" style="background:#E5E5EA; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; font-weight:800;">+</button>
            </div>
            <p style="text-align:center; font-size:11px; color:#8E8E93; margin:6px 0 10px;">Geser untuk atur posisi · Scroll / Pinch untuk zoom</p>
        </div>
        <div style="display:flex; gap:10px; padding:0 20px 20px;">
            <button onclick="document.getElementById('modalCrop').style.display='none'" style="flex:1; padding:13px; border-radius:12px; border:1.5px solid #E5E5EA; background:white; font-size:14px; font-weight:700; color:#666; cursor:pointer;">Batal</button>
            <button onclick="simpanFoto()" style="flex:2; padding:13px; border-radius:12px; border:none; background:var(--primary); color:white; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 15px rgba(88,86,214,0.3);"><i class="fas fa-check"></i> Gunakan Foto Ini</button>
        </div>
    </div>
</div>

<form id="formFoto" action="../proses.php?action=update_foto" method="POST" style="display:none;"><input type="hidden" name="foto_data" id="fotoDataInput"></form>
<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
<?php include '../footer.php'; ?>
<script>
function toggleEditProfil() {
    const wrap = document.getElementById('formEditWrap');
    wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
}
function konfirEditProfil() { document.getElementById('formEditProfil').submit(); }
function lihatFotoHD(src) { document.getElementById('imgHD').src = src; document.getElementById('modalLihatFoto').style.display = 'flex'; }

// Logika Drag & Drop persis file sebelumnya
let cropImg, cropX=0, cropY=0, cropScale=1, isDragging=false, dragStartX=0, dragStartY=0, dragOX=0, dragOY=0;
async function bukaModalCrop(input) {
    if (!input.files || !input.files[0]) return; let file = input.files[0];
    if (file.type === "image/heic" || file.name.toLowerCase().endsWith(".heic")) {
        Swal.fire({title: 'Memproses Foto...', didOpen: () => Swal.showLoading()});
        const cb = await heic2any({ blob: file, toType: "image/jpeg" }); file = Array.isArray(cb) ? cb[0] : cb; Swal.close();
    }
    const reader = new FileReader();
    reader.onload = e => {
        cropImg = new Image();
        cropImg.onload = () => {
            document.getElementById('modalCrop').style.display = 'flex'; 
            const cv = document.getElementById('cropCanvas'), ar = document.getElementById('cropArea');
            cv.width = ar.offsetWidth; cv.height = ar.offsetHeight;
            cropScale = Math.min(cv.width/cropImg.width, cv.height/cropImg.height, 1);
            cropX = (cv.width - cropImg.width*cropScale)/2; cropY = (cv.height - cropImg.height*cropScale)/2;
            renderCrop();
        }; cropImg.src = e.target.result;
    }; reader.readAsDataURL(file); input.value = '';
}
function renderCrop() {
    const cv = document.getElementById('cropCanvas'), ctx = cv.getContext('2d');
    ctx.clearRect(0,0,cv.width,cv.height); if(cv.width>0) ctx.drawImage(cropImg,cropX,cropY,cropImg.width*cropScale,cropImg.height*cropScale);
}
function setZoom(v) { cropScale=v; renderCrop(); }
function zoomFoto(d) { setZoom(Math.max(0.5, cropScale+d)); }
function startDrag(e) { isDragging=true; dragStartX=e.clientX; dragStartY=e.clientY; dragOX=cropX; dragOY=cropY; e.preventDefault(); }
document.addEventListener('mousemove', e => { if(isDragging){ cropX=dragOX+(e.clientX-dragStartX); cropY=dragOY+(e.clientY-dragStartY); renderCrop(); }});
document.addEventListener('mouseup', () => isDragging=false);

function simpanFoto() {
    const cv=document.getElementById('cropCanvas'), gd=document.getElementById('cropGuide'), ar=document.getElementById('cropArea');
    const out=document.createElement('canvas'), ctx=out.getContext('2d'); out.width=out.height=600;
    const aR=ar.getBoundingClientRect(), gR=gd.getBoundingClientRect();
    ctx.beginPath(); ctx.arc(300,300,300,0,Math.PI*2); ctx.clip(); ctx.fillStyle="#fff"; ctx.fillRect(0,0,600,600);
    ctx.drawImage(cv, gR.left-aR.left, gR.top-aR.top, gR.width, gR.width, 0,0,600,600);
    const dataURL = out.toDataURL('image/jpeg', 0.95);
    document.getElementById('fotoDataInput').value = dataURL;
    document.getElementById('formFoto').submit();
}
</script>