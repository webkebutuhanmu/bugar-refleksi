<?php
/**
 * absensi_terapis.php - FULL VERSION
 * UPDATE: Penambahan fitur Absen Keluar (Pulang)
 */
session_start();
require_once '../sistem/config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'terapis') { 
    header("Location: login.php"); exit; 
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$terapis_id = (int)$_SESSION['user_id'];

// ── Data user & cabang ────────────────────────────────────────────────────────
$stU = $pdo->prepare(
    "SELECT u.*, b.nama_cabang
     FROM users u
     LEFT JOIN branches b ON u.home_branch_id = b.id
     WHERE u.id = ?"
);
$stU->execute([$terapis_id]);
$userData = $stU->fetch(PDO::FETCH_ASSOC);

if (!$userData) { session_destroy(); header("Location: ../auth/login_system.php"); exit; }

$foto_url    = (!empty($userData['foto_profil']) && file_exists("../sistem/assets/uploads/".$userData['foto_profil']))
               ? "../sistem/assets/uploads/".$userData['foto_profil'] : "../sistem/assets/default_user.png";
$nama_cabang = $userData['nama_cabang'] ?? 'Belum ditentukan';
$no_hp       = $userData['no_hp'] ?? '-';
$branch_id   = (int)($userData['home_branch_id'] ?? 0);

// Auto-generate barcode_id jika belum ada
$barcode_id = $userData['barcode_id'] ?? null;
if (empty($barcode_id)) {
    $barcode_id = 'TRP'.str_pad($terapis_id, 5, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE users SET barcode_id=? WHERE id=?")->execute([$barcode_id, $terapis_id]);
}

// ── Tanggal bisnis ────────────────────────────────────────────────────────────
$sRow        = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulai    = $sRow['jam_mulai_hari'] ?? '08:00:00';
$tglBisnis   = (date('H:i:s') < $jamMulai) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

// ── Status sesi ───────────────────────────────────────────────────────────────
$sesiOpen = false;
if ($branch_id) {
    $stS = $pdo->prepare("SELECT status FROM attendance_sessions WHERE branch_id=? AND tanggal=? ORDER BY id DESC LIMIT 1");
    $stS->execute([$branch_id, $tglBisnis]);
    $sRow2    = $stS->fetch(PDO::FETCH_ASSOC);
    $sesiOpen = ($sRow2 && $sRow2['status'] === 'open');
}

// ── Daftar hadir (include shift data) ─────────────────────────────────────────
$sudahAbsen    = false;
$giliranSaya   = null;
$myShift       = null;
$myStatus      = null;
$myAbsenId     = null;
$myWaktuKeluar = null;
$absenList     = [];
$totalTerapis  = 0;

if ($branch_id) {
    // UPDATE: Penambahan select kolom id (sebagai absen_id) dan waktu_keluar
    $stL = $pdo->prepare(
        "SELECT ta.id as absen_id, ta.terapis_id, ta.giliran, ta.waktu_absen, ta.waktu_keluar, ta.metode_absen,
                ta.shift_type, ta.status_kehadiran, ta.alasan_terlambat,
                u.nama_lengkap, u.foto_profil
         FROM terapis_attendance ta
         JOIN users u ON ta.terapis_id = u.id
         WHERE ta.branch_id=? AND ta.tanggal=?
         ORDER BY ta.giliran ASC"
    );
    $stL->execute([$branch_id, $tglBisnis]);
    $absenList = $stL->fetchAll(PDO::FETCH_ASSOC);

    $stT = $pdo->prepare("SELECT COUNT(*) FROM users WHERE home_branch_id=? AND role='terapis'");
    $stT->execute([$branch_id]);
    $totalTerapis = (int)$stT->fetchColumn();

    foreach ($absenList as $a) {
        if ((int)$a['terapis_id'] === $terapis_id) {
            $sudahAbsen    = true;
            $giliranSaya   = (int)$a['giliran'];
            $myShift       = $a['shift_type'];
            $myStatus      = $a['status_kehadiran'];
            $myAbsenId     = $a['absen_id'];
            $myWaktuKeluar = $a['waktu_keluar'];
            break;
        }
    }
}

// ── Cek status izin/sakit hari ini ──────────────────────────────────────────
$izinHariIni = null;
if ($branch_id) {
    $stIzin = $pdo->prepare(
        "SELECT * FROM terapis_izin 
         WHERE terapis_id = ? AND branch_id = ? AND tanggal = ? 
         ORDER BY id DESC LIMIT 1"
    );
    $stIzin->execute([$terapis_id, $branch_id, $tglBisnis]);
    $izinHariIni = $stIzin->fetch(PDO::FETCH_ASSOC);
}

// ── Riwayat Absensi (30 hari terakhir) ──────────────────────────────────────
$riwayatAbsensi = [];
if ($branch_id) {
    $stRiwayat = $pdo->prepare(
        "SELECT ta.tanggal, ta.waktu_absen, ta.waktu_keluar, ta.shift_type, ta.status_kehadiran, 
                ta.giliran, ta.metode_absen, ta.alasan_terlambat
         FROM terapis_attendance ta
         WHERE ta.terapis_id = ? AND ta.branch_id = ?
         ORDER BY ta.tanggal DESC, ta.waktu_absen DESC
         LIMIT 30"
    );
    $stRiwayat->execute([$terapis_id, $branch_id]);
    $riwayatAbsensi = $stRiwayat->fetchAll(PDO::FETCH_ASSOC);
}

// ── Riwayat Izin/Sakit ─────────────────────────────────────────────────────
$riwayatIzin = [];
if ($branch_id) {
    $stRiwIzin = $pdo->prepare(
        "SELECT ti.tanggal, ti.jenis, ti.keterangan, ti.status, ti.catatan_leader, ti.created_at
         FROM terapis_izin ti
         WHERE ti.terapis_id = ? AND ti.branch_id = ?
         ORDER BY ti.tanggal DESC
         LIMIT 30"
    );
    $stRiwIzin->execute([$terapis_id, $branch_id]);
    $riwayatIzin = $stRiwIzin->fetchAll(PDO::FETCH_ASSOC);
}

// ── URL AJAX ─────────────────────────────────────────────────────────────────
$proto     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$parentDir = rtrim(dirname($scriptDir), '/');
$AJAX_URL  = $proto.'://'.$host.$parentDir.'../sistem/kasir/ajax_absensi.php';
$AJAX_IZIN_URL = $proto.'://'.$host.$parentDir.'../sistem/kasir/ajax_izin_sakit.php';

// ── QR Data ──────────────────────────────────────────────────────────────────
$qr_data = json_encode([
    'barcode' => $barcode_id,
    'nama'    => $userData['nama_lengkap'],
    'cabang'  => $nama_cabang,
    'hp'      => $no_hp,
    'app'     => 'Bugar Refleksi'
], JSON_UNESCAPED_UNICODE);

function fmtWkt($dt) { return $dt ? date('H:i:s', strtotime($dt)) : '-'; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Terapis</title>
    <link rel="stylesheet" href="assets/style_terapis.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .absen-item { display: flex; align-items: center; gap: 15px; padding: 15px; border-bottom: 1px solid var(--border-color); }
        .absen-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .rank { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; }
        .r1 { background: #f1c40f; } .r2 { background: #bdc3c7; } .r3 { background: #e67e22; } .rn { background: #3498db; }
        .btn-izin { background: #f39c12; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-sakit { background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .badge-shift { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .shift-pagi { background: rgba(243, 156, 18, 0.1); color: #f39c12; }
        .shift-malam { background: rgba(142, 68, 173, 0.1); color: #8e44ad; }
        .status-tepat { background: rgba(46, 204, 113, 0.1); color: #27ae60; }
        .status-telat { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }
        .me-badge { background: #27ae60; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 5px; }
    </style>
</head>
<body>
    <div class="container-layout">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>💆 TERAPIS PANEL</h2></div>
            <div class="sidebar-menu">
                <a href="dashboard_terapis.php" class="menu-item"><i>📊</i> Dashboard</a>
                <a href="absensi_terapis.php" class="menu-item active"><i>📋</i> Absensi</a>
                <a href="riwayat_pendapatan.php" class="menu-item"><i>💰</i> Riwayat Omset</a>
                <a href="profil_terapis.php" class="menu-item"><i>👤</i> Profil Saya</a>
                <a href="skor_reward_terapis.php" class="menu-item"><i>⭐</i> Skor Reward</a>
                <a href="logout.php" class="menu-item" style="color:#c0392b;margin-top:50px;"><i>🚪</i> Logout</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div class="topbar-left">
                    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                    <h1>Absensi Harian</h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-toggle" onclick="toggleTheme()" id="theme-btn"><i class="fas fa-moon"></i> Dark</button>
                </div>
            </div>

            <div class="grid-2">
                <div>
                    <?php
                    $izinDisetujui = ($izinHariIni && $izinHariIni['status'] === 'disetujui');
                    $izinPending   = ($izinHariIni && $izinHariIni['status'] === 'pending');
                    
                    // UPDATE LOGIKA STATUS CARD
                    if ($sudahAbsen && empty($myWaktuKeluar)) { 
                        $cls='card-done'; $ico='✅'; $txt='Status: AKTIF (Bekerja)'; 
                    } elseif ($sudahAbsen && !empty($myWaktuKeluar)) {
                        $cls='card-closed'; $ico='🏠'; $txt='Status: SUDAH PULANG'; 
                    } elseif ($izinDisetujui) { 
                        $cls='card-done'; $ico='💌'; $txt='Status: Izin/Sakit'; 
                    } elseif ($sesiOpen) { 
                        $cls='card-open'; $ico='🟢'; $txt='Absensi Dibuka!'; 
                    } else { 
                        $cls='card-closed'; $ico='🔴'; $txt='Absensi Belum Dibuka'; 
                    }
                    ?>
                    <div class="absen-status-card <?= $cls ?>" style="border-top: 5px solid #3498db;">
                        <div style="font-size:40px; margin-bottom:10px;"><?= $ico ?></div>
                        <h3 style="color:var(--text-dark); margin-bottom:20px;"><?= $txt ?></h3>
                        
                        <?php if ($sudahAbsen && empty($myWaktuKeluar)): ?>
                            <div style="background:rgba(52,152,219,0.1); padding:15px; border-radius:10px; color:var(--text-dark); margin-bottom:15px;">
                                Giliran Kamu: <strong><?= $giliranSaya ?></strong><br>
                                <small>Absen Masuk: <?= date('H:i', strtotime($a['waktu_absen'])) ?></small>
                            </div>
                            <button class="btn-absen" style="background:#e74c3c;" onclick="absenPulang(<?= $myAbsenId ?>)">ABSEN KELUAR / PULANG</button>

                        <?php elseif ($sudahAbsen && !empty($myWaktuKeluar)): ?>
                            <div style="background:rgba(149,165,166,0.1); padding:15px; border-radius:10px; color:var(--text-dark);">
                                Terima kasih atas kerja kerasnya hari ini!<br>
                                <small>Waktu Pulang: <strong><?= date('H:i', strtotime($myWaktuKeluar)) ?></strong></small>
                            </div>

                        <?php elseif ($sesiOpen && !$izinPending): ?>
                            <button class="btn-absen" id="btnAbsen" onclick="doAbsen()">ABSEN SEKARANG</button>

                        <?php else: ?>
                            <button class="btn-absen" disabled>Menunggu Kasir...</button>
                        <?php endif; ?>

                        <?php if(!$sudahAbsen): ?>
                        <div style="margin-top:20px; display:flex; gap:10px; justify-content:center;">
                            <button id="btnIzin" class="btn-izin" onclick="ajukanIzinSakit('izin')">Ajukan Izin</button>
                            <button id="btnSakit" class="btn-sakit" onclick="ajukanIzinSakit('sakit')">Ajukan Sakit</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <div class="qr-card" style="border-top: 5px solid #9b59b6;">
                        <h3 style="color:var(--text-dark); margin-bottom:15px;">QR Code Absensi Saya</h3>
                        <div id="qrBox" style="display:inline-block; padding:15px; background:white; border-radius:10px;"></div>
                        <h3 style="margin-top:15px; color:var(--text-dark); letter-spacing:2px; font-weight:bold;"><?= htmlspecialchars($barcode_id) ?></h3>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border-color); padding-bottom:15px;">
                    <span>Daftar Hadir Hari Ini</span>
                    <span id="hadirBadge" style="background:rgba(52,152,219,0.2); color:#3498db; padding:5px 10px; border-radius:10px; font-size:12px;"><?= count($absenList) ?> / <?= $totalTerapis ?> Hadir</span>
                </div>
                <div id="absenListBody" style="padding:10px 0;">
                    <?php if (empty($absenList)): ?>
                        <div style="text-align:center; padding:20px; color:var(--text-muted);"><p>Belum ada yang absen hari ini</p></div>
                    <?php else: ?>
                        <?php foreach ($absenList as $a):
                            $isMe  = ((int)$a['terapis_id'] === $terapis_id);
                            $g     = (int)$a['giliran'];
                            $rc    = $g===1?'r1':($g===2?'r2':($g===3?'r3':'rn'));
                            $fPath = "../sistem/assets/uploads/".($a['foto_profil'] ?? '');
                            $fSrc  = (!empty($a['foto_profil']) && file_exists($fPath)) ? htmlspecialchars($fPath) : "../sistem/assets/default_user.png";
                            $met   = $a['metode_absen']==='scan' ? '📷 Scan' : '📱 Manual';
                            $shCls = ($a['shift_type'] ?? '') === 'pagi' ? 'shift-pagi' : 'shift-malam';
                            $stCls = ($a['status_kehadiran'] ?? '') === 'tepat_waktu' ? 'status-tepat' : 'status-telat';
                            $stLbl = ($a['status_kehadiran'] ?? '') === 'tepat_waktu' ? '✔ Tepat Waktu' : '⚠ Terlambat';
                        ?>
                        <div class="absen-item<?= $isMe ? ' me' : '' ?>" style="<?= $isMe ? 'background:rgba(46, 204, 113, 0.05);' : '' ?>">
                            <div class="rank <?= $rc ?>"><?= $g ?></div>
                            <img src="<?= $fSrc ?>" class="absen-avatar" onerror="this.src='../sistem/assets/default_user.png'">
                            <div style="flex:1;">
                                <div style="font-weight:bold; color:var(--text-dark);">
                                    <?= htmlspecialchars($a['nama_lengkap']) ?>
                                    <?php if ($isMe): ?><span class="me-badge">KAMU</span><?php endif; ?>
                                </div>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:5px;">
                                    <i class="far fa-clock"></i> <?= fmtWkt($a['waktu_absen']) ?> &bull; <?= $met ?>
                                    <?php if (!empty($a['shift_type'])): ?>
                                        &bull; <span class="badge-shift <?= $shCls ?>">Shift <?= ucfirst($a['shift_type']) ?></span>
                                    <?php endif; ?>
                                    <span class="badge-shift <?= $stCls ?>"><?= $stLbl ?></span>
                                </div>
                                
                                <?php if (!empty($a['alasan_terlambat'])): ?>
                                    <div style="font-size:10px; color:#e74c3c; margin-top:5px;">Alasan: <?= htmlspecialchars($a['alasan_terlambat']) ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($a['waktu_keluar'])): ?>
                                    <div style="font-size:10px; font-weight:bold; color:#7f8c8d; margin-top:5px;"><i class="fas fa-home"></i> Sudah Pulang (<?= date('H:i', strtotime($a['waktu_keluar'])) ?>)</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
    const AJAX_URL = <?= json_encode($AJAX_URL) ?>;
    const AJAX_IZIN_URL = <?= json_encode($AJAX_IZIN_URL) ?>;
    const MY_ID = <?= $terapis_id ?>;
    
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }
    
    function toggleTheme() {
        const b = document.documentElement; const isD = b.getAttribute('data-theme') === 'dark';
        b.setAttribute('data-theme', isD ? 'light' : 'dark'); localStorage.setItem('theme', isD ? 'light' : 'dark');
        document.getElementById('theme-btn').innerHTML = isD ? '<i class="fas fa-moon"></i> Dark' : '<i class="fas fa-sun"></i> Light';
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        const sTheme = localStorage.getItem('theme') || 'dark'; document.documentElement.setAttribute('data-theme', sTheme);
        document.getElementById('theme-btn').innerHTML = sTheme === 'dark' ? '<i class="fas fa-sun"></i> Light' : '<i class="fas fa-moon"></i> Dark';
    });

    // Inisialisasi QR Code
    new QRCode(document.getElementById('qrBox'), { 
        text: <?= json_encode($qr_data) ?>, 
        width: 140, 
        height: 140, 
        colorDark: '#000000', 
        colorLight: '#ffffff' 
    });

    // ==========================================
    // LOGIKA ABSENSI KELUAR / PULANG
    // ==========================================
    function absenPulang(id) {
        Swal.fire({
            title: 'Konfirmasi Pulang?',
            text: "Jika sudah absen keluar, kamu tidak dapat dipilih lagi oleh kasir untuk melayani customer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            confirmButtonText: 'Ya, Saya Pulang',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                var body = 'action=absen_keluar&absen_id=' + id;
                var xhr = new XMLHttpRequest();
                xhr.open('POST', AJAX_URL, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.withCredentials = true;

                xhr.onreadystatechange = function() {
                    if (xhr.readyState !== 4) return;
                    if (xhr.status === 200) {
                        var data;
                        try { data = JSON.parse(xhr.responseText); } catch(e) { return; }
                        if (data.success) {
                            Swal.fire({ title: 'Berhasil!', text: data.message, icon: 'success', timer: 2500, showConfirmButton: false })
                            .then(function() { location.reload(); });
                        } else {
                            Swal.fire('Gagal', data.message, 'error');
                        }
                    }
                };
                xhr.send(body);
            }
        });
    }

    // ==========================================
    // LOGIKA ABSENSI DAN IZIN BAWAAN
    // ==========================================
    function doAbsen() {
        Swal.fire({
            title: 'Konfirmasi Absen',
            text: 'Apakah kamu yakin ingin absen sekarang?',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#27ae60', confirmButtonText: 'Ya, Absen!', cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            kirimAbsenManual('');
        });
    }

    function kirimAbsenManual(alasan) {
        var btn = document.getElementById('btnAbsen');
        if (btn) { btn.disabled = true; btn.innerHTML = 'Memproses...'; }

        var body = 'action=manual_absen';
        if (alasan) body += '&alasan_terlambat=' + encodeURIComponent(alasan);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', AJAX_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.withCredentials = true;

        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;

            if (xhr.status === 200) {
                var data;
                try { data = JSON.parse(xhr.responseText); } catch(e) {
                    Swal.fire({ title:'Error', html:'Terjadi kesalahan sistem.', icon:'error' });
                    resetBtn(btn); return;
                }

                if (!data.success && data.terlambat) {
                    Swal.fire({
                        title: '⚠️ Kamu Terlambat!',
                        html: '<p style="margin:0 0 8px;">Jam absen: <strong>' + escH(data.jam_absen) + '</strong></p>'
                            + '<p style="margin:0;color:#e74c3c;font-weight:bold;">Kamu wajib mengisi alasan keterlambatan.</p>',
                        input: 'textarea', inputLabel: 'Alasan Terlambat',
                        inputValidator: function(value) { if (!value || value.trim().length < 5) return 'Alasan wajib diisi!'; },
                        showCancelButton: true, confirmButtonColor: '#e67e22', confirmButtonText: 'Kirim Alasan & Absen'
                    }).then(function(result2) {
                        if (result2.isConfirmed && result2.value) { kirimAbsenManual(result2.value.trim()); } 
                        else { resetBtn(btn); }
                    });
                    return;
                }

                if (data.success) {
                    Swal.fire({ title: 'Berhasil Absen!', text: data.message, icon: 'success', timer: 2500, showConfirmButton: false })
                    .then(function() { location.reload(); });
                } else {
                    Swal.fire('Gagal', data.message, 'warning'); resetBtn(btn);
                }
            } else {
                Swal.fire('Error', 'Server Error', 'error'); resetBtn(btn);
            }
        };
        xhr.send(body);
    }

    function resetBtn(btn) { if (btn) { btn.disabled=false; btn.innerHTML='ABSEN SEKARANG'; } }

    function ajukanIzinSakit(jenis) {
        var judul = jenis === 'izin' ? 'Ajukan Izin' : 'Ajukan Sakit';
        var icoColor = jenis === 'izin' ? '#e67e22' : '#e74c3c';

        Swal.fire({
            title: judul,
            text: 'Pengajuan akan dikirim ke Leader untuk disetujui.',
            input: 'textarea', inputLabel: 'Keterangan:',
            inputValidator: function(value) { if (!value || value.trim().length < 5) return 'Keterangan wajib diisi!'; },
            showCancelButton: true, confirmButtonColor: icoColor, confirmButtonText: 'Kirim Pengajuan'
        }).then(function(result) {
            if (result.isConfirmed && result.value) { kirimIzinSakit(jenis, result.value.trim()); }
        });
    }

    function kirimIzinSakit(jenis, keterangan) {
        var body = 'action=kirim_izin&jenis=' + encodeURIComponent(jenis) + '&keterangan=' + encodeURIComponent(keterangan);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', AJAX_IZIN_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.withCredentials = true;

        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            if (xhr.status === 200) {
                var data;
                try { data = JSON.parse(xhr.responseText); } catch(e) { return; }
                if (data.success) {
                    Swal.fire({ title: 'Berhasil!', text: data.message, icon: 'success', timer: 2500, showConfirmButton: false })
                    .then(function() { location.reload(); });
                } else {
                    Swal.fire('Gagal', data.message, 'warning');
                }
            }
        };
        xhr.send(body);
    }

    function escH(s) { if (s == null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    
    // Auto Refresh Logic
    function refreshData() {
        var xhr2 = new XMLHttpRequest();
        xhr2.open('GET', AJAX_URL + '?action=cek_status', true);
        xhr2.withCredentials = true;
        xhr2.onreadystatechange = function() {
            if (xhr2.readyState !== 4 || xhr2.status !== 200) return;
            var data;
            try { data = JSON.parse(xhr2.responseText); } catch(e) { return; }
            if (!data.success) return;

            document.getElementById('hadirBadge').textContent = data.total_hadir + ' / ' + data.total_terapis + ' Hadir';
        };
        xhr2.send();
    }
    setInterval(refreshData, 10000);
    </script>
</body>
</html>