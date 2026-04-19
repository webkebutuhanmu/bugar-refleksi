<?php
/**
 * absensi_kasir.php - UPDATE v2
 * * UPDATE:
 * - Tampilkan kolom Shift, Status (Tepat Waktu / Terlambat), Alasan
 * - Popup alasan terlambat saat scan terapis yang terlambat
 */
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) {
    header("Location: pilih_cabang.php"); exit;
}

$kasir_id   = $_SESSION['user_id'];
$branch_id  = $_SESSION['active_branch'];
$nama_kasir = $_SESSION['nama'];
$nama_cabang = $pdo->query("SELECT nama_cabang FROM branches WHERE id = $branch_id")->fetchColumn();

$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$dbFoto = $stmtProfil->fetchColumn();
$foto_profil = "../assets/default_user.png";
if (!empty($dbFoto) && file_exists("../uploads/profil/" . $dbFoto)) {
    $foto_profil = "../uploads/profil/" . $dbFoto;
}

$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
$current_time = date('H:i:s');
if ($current_time >= $settings['shift_pagi_start'] && $current_time <= $settings['shift_pagi_end']) {
    $label_shift = "PAGI";
    $color_shift = "var(--accent-blue)";
} else {
    $label_shift = "MALAM";
    $color_shift = "var(--accent-red)";
}

$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$proto     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
$AJAX_ABSENSI_URL = $proto.'://'.$host.$scriptDir.'/ajax_absensi.php';
$AJAX_IZIN_URL    = $proto.'://'.$host.$scriptDir.'/ajax_izin_sakit.php';

// ── Data izin/sakit hari ini ────────────────────────────────────────────────
$sRow3       = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulaiK   = $sRow3['jam_mulai_hari'] ?? '08:00:00';
$tglBisnisK  = (date('H:i:s') < $jamMulaiK) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

$stIzinKasir = $pdo->prepare(
    "SELECT ti.*, u.nama_lengkap, u.foto_profil 
     FROM terapis_izin ti
     JOIN users u ON ti.terapis_id = u.id
     WHERE ti.branch_id = ? AND ti.tanggal = ?
     ORDER BY ti.status ASC, ti.created_at DESC"
);
$stIzinKasir->execute([$branch_id, $tglBisnisK]);
$izinListKasir = $stIzinKasir->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Terapis - Kasir</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .scan-tabs { display: flex; gap: 8px; margin-bottom: 15px; margin-top: 12px; }
        .scan-tab { flex: 1; padding: 10px 12px; border: 1px solid var(--border-color); background: var(--bg-input); border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 700; color: var(--text-muted); transition: all 0.2s; text-align: center; }
        .scan-tab.active { border-color: var(--accent-blue); background: rgba(52,152,219,0.1); color: var(--accent-blue); }
        .scan-input-wrap { display: flex; gap: 10px; margin-bottom: 15px; }
        .scan-input { flex: 1; padding: 14px 16px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-input); color: var(--text-dark); font-size: 16px; font-weight: bold; letter-spacing: 1px; transition: 0.3s; }
        .scan-input:focus { outline: none; border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(52,152,219,0.15); }
        .camera-section { display: none; }
        .camera-section.active { display: block; }
        .manual-section { display: block; }
        .manual-section.hidden { display: none; }
        #reader { width: 100%; border-radius: 10px; overflow: hidden; border: 2px solid var(--border-color); background: #000; min-height: 220px; }
        .camera-controls { display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .btn-camera { flex: 1; padding: 10px 16px; border: none; border-radius: 8px; font-weight: bold; font-size: 13px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .cam-status { text-align: center; font-size: 12px; color: var(--text-muted); margin-top: 8px; padding: 8px; background: var(--bg-input); border-radius: 6px; font-weight: 600; }
        .cam-status.scanning { color: var(--accent-green); }
        .btn-buka-absen { padding: 16px 40px; border: none; border-radius: 12px; font-weight: bold; font-size: 16px; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 10px; width: 100%; justify-content: center; }
        .btn-buka-absen.open { background: var(--accent-green); color: white; }
        .btn-buka-absen.close { background: var(--accent-red); color: white; }
        .session-status { margin-top: 15px; padding: 12px 15px; border-radius: 8px; font-size: 13px; font-weight: bold; text-align: center; }
        .session-status.open { background: rgba(46,204,113,0.1); color: var(--accent-green); border: 1px solid var(--accent-green); }
        .session-status.closed { background: rgba(231,76,60,0.1); color: var(--accent-red); border: 1px solid var(--accent-red); }
        .giliran-badge { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; font-weight: 800; font-size: 14px; color: white; }
        .giliran-1 { background: var(--accent-yellow2); }
        .giliran-2 { background: var(--text-muted); }
        .giliran-3 { background: var(--accent-red); }
        .giliran-other { background: var(--accent-blue); }
        .terapis-avatar-sm { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); vertical-align: middle; margin-right: 8px; }
        .empty-state { text-align: center; padding: 40px; color: var(--text-muted); font-weight: 600; }
        .scan-result { display: none; padding: 12px 16px; border-radius: 8px; margin-top: 10px; font-size: 13px; font-weight: bold; }
        .scan-result.success { background: rgba(46,204,113,0.1); color: var(--accent-green); }
        .scan-result.error { background: rgba(231,76,60,0.1); color: var(--accent-red); }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container-layout">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-profile">
                <img src="<?= $foto_profil ?>" alt="Profil">
                <div class="profile-info">
                    <h3><?= htmlspecialchars($nama_kasir) ?></h3>
                    <small><?= htmlspecialchars($nama_cabang) ?></small>
                </div>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard_kasir.php" class="menu-item"><span class="menu-abbr">DB</span><span class="menu-text">Dashboard</span></a>
                <a href="input_transaksi.php" class="menu-item"><span class="menu-abbr">IT</span><span class="menu-text">Input Transaksi</span></a>
                <a href="absensi_kasir.php" class="menu-item active"><span class="menu-abbr">AT</span><span class="menu-text">Absensi Terapis</span></a>
                <a href="data_terapis_hadir.php" class="menu-item"><span class="menu-abbr">DT</span><span class="menu-text">Data Terapis</span></a>
                <a href="data_customer_kasir.php" class="menu-item"><span class="menu-abbr">DC</span><span class="menu-text">Data Customer</span></a>
                <a href="paket_layanan_kasir.php" class="menu-item"><span class="menu-abbr">PL</span><span class="menu-text">Paket Layanan</span></a>
                <a href="stok_barang.php" class="menu-item"><span class="menu-abbr">SB</span><span class="menu-text">Stok Barang</span></a>
                <a href="tutup_cabang.php" class="menu-item" style="margin-top:30px; color:var(--accent-red);"><span class="menu-abbr" style="background:rgba(231,76,60,0.1); color:var(--accent-red);">TS</span><span class="menu-text">Tutup Shift</span></a>
            </div>
            <button class="sidebar-toggle-btn" onclick="toggleSidebar()">
                <span class="menu-text">Minimize Sidebar</span>
                <span class="menu-abbr" style="display:none;">▶</span>
            </button>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div style="display:flex; align-items:center; gap:15px;">
                    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
                    <div>
                        <h1 style="margin-bottom: 5px;">Absensi Terapis</h1>
                        <span class="badge" style="background:<?= $color_shift ?>; color:white;"><?= $label_shift ?></span>
                    </div>
                </div>
                <div class="topbar-right">
                    <div style="text-align:right;">
                        <div id="realtimeClock" style="font-size:18px; font-weight:bold; color:var(--text-dark); font-family: monospace;">--:--:--</div>
                        <small style="color:var(--text-muted); font-weight:600; font-size: 11px; text-transform:uppercase;"><?= date('d M Y') ?></small>
                    </div>
                    <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
                </div>
            </div>

            <div class="grid-2">
                <div class="card" style="padding: 25px;">
                    <h3 style="margin-bottom: 5px; color: var(--text-dark);">Scan Barcode Terapis</h3>
                    <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px;">Gunakan kamera atau ketik ID barcode</p>

                    <div class="scan-tabs">
                        <button class="scan-tab active" onclick="switchScanMode('manual')" id="tabManual">Input Manual</button>
                        <button class="scan-tab" onclick="switchScanMode('camera')" id="tabCamera">Scan Kamera</button>
                    </div>

                    <div class="manual-section" id="manualSection">
                        <div class="scan-input-wrap">
                            <input type="text" id="barcodeInput" class="scan-input" placeholder="Ketik barcode..." autofocus autocomplete="off">
                            <button class="btn btn-primary" onclick="prosesBarcode()">Proses</button>
                        </div>
                    </div>

                    <div class="camera-section" id="cameraSection">
                        <div class="camera-controls">
                            <button class="btn-camera btn-success" id="btnStartCam" onclick="startCamera()">Mulai Kamera</button>
                            <button class="btn-camera btn-danger" id="btnStopCam" onclick="stopCamera()" style="display:none;">Hentikan</button>
                            <button class="btn-camera btn-secondary" id="btnFlipCam" onclick="flipCamera()" style="display:none;">Balik Kamera</button>
                        </div>
                        <div id="reader"></div>
                        <div class="cam-status" id="camStatus">Arahkan kamera ke QR Code Terapis</div>
                    </div>

                    <div id="scanResult" class="scan-result"></div>
                </div>

                <div class="card" style="padding: 25px;">
                    <h3 style="margin-bottom: 15px; color: var(--text-dark);">Sesi Absensi</h3>
                    <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">Buka sesi agar terapis dapat melakukan absensi mandiri dari perangkat mereka.</p>
                    <div id="sessionBtnWrap">
                        <button class="btn-buka-absen open" id="btnBukaAbsen" onclick="toggleAbsensi()" style="display:none;">Buka Sesi Absensi</button>
                        <button class="btn-buka-absen close" id="btnTutupAbsen" onclick="toggleAbsensi()" style="display:none;">Tutup Sesi Absensi</button>
                    </div>
                    <div id="sessionStatus" class="session-status" style="display:none;"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between;">
                    <span>Daftar Hadir Hari Ini</span>
                    <span class="badge badge-primary"><span id="countHadir">0</span> / <span id="countTotal">0</span> Terapis</span>
                </div>
                <div class="table-container" id="absenListContainer">
                    <div class="empty-state">Memuat data absensi...</div>
                </div>
            </div>

            <?php if (!empty($izinListKasir)): ?>
            <div class="card" id="izinTableWrap" style="margin-top: 20px;">
                <div class="card-header" style="display: flex; justify-content: space-between;">
                    <span>Izin / Sakit Hari Ini</span>
                    <span class="badge badge-warning"><?= count($izinListKasir) ?> Pengajuan</span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th><th>Nama Terapis</th><th>Jenis</th>
                                <th>Keterangan</th><th>Status</th><th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $noIz = 1; foreach ($izinListKasir as $iz):
                                $fotoIz = (!empty($iz['foto_profil']) && file_exists("../assets/uploads/".$iz['foto_profil']))
                                          ? "../assets/uploads/".$iz['foto_profil'] : "../assets/default_user.png";
                            ?>
                            <tr>
                                <td><?= $noIz++ ?></td>
                                <td>
                                    <img src="<?= $fotoIz ?>" class="terapis-avatar-sm" onerror="this.src='../assets/default_user.png'">
                                    <strong><?= htmlspecialchars($iz['nama_lengkap']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($iz['jenis'] === 'sakit'): ?>
                                        <span class="badge badge-danger">SAKIT</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">IZIN</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width:250px;word-break:break-word;"><?= htmlspecialchars($iz['keterangan']) ?></td>
                                <td>
                                    <?php if ($iz['status'] === 'pending'): ?>
                                        <span class="badge" style="background:var(--bg-input); color:var(--text-muted); border:1px solid var(--border-color);">MENUNGGU</span>
                                    <?php elseif ($iz['status'] === 'disetujui'): ?>
                                        <span class="badge badge-success">DISETUJUI</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">DITOLAK</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('H:i', strtotime($iz['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
    const AJAX_ABSENSI = <?= json_encode($AJAX_ABSENSI_URL) ?>;
    const AJAX_IZIN    = <?= json_encode($AJAX_IZIN_URL) ?>;

    // Theme & Sidebar Toggle
    function toggleTheme() {
        const html = document.documentElement;
        const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        html.setAttribute('data-theme', next);
        localStorage.setItem('bugar-theme', next);
    }
    (function() { const saved = localStorage.getItem('bugar-theme'); if (saved) document.documentElement.setAttribute('data-theme', saved); })();

    function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    
    // Deteksi apakah ini tampilan mobile (lebar layar <= 992px sesuai CSS Anda)
    if (window.innerWidth <= 992) {
        // Mode Mobile: Toggle class 'active' untuk memunculkan sidebar dari kiri
        sb.classList.toggle('active');
    } else {
        // Mode Desktop: Toggle class 'collapsed' untuk mengecilkan/membesarkan sidebar
        sb.classList.toggle('collapsed');
        
        const btnText = document.querySelector('.sidebar-toggle-btn .menu-text');
        const btnAbbr = document.querySelector('.sidebar-toggle-btn .menu-abbr');
        
        if (sb.classList.contains('collapsed')) {
            btnText.style.display = 'none';
            btnAbbr.style.display = 'inline';
        } else {
            btnText.style.display = 'inline';
            btnAbbr.style.display = 'none';
        }
    }
}

    // Clock
    setInterval(() => { document.getElementById('realtimeClock').innerText = new Date().toLocaleTimeString('id-ID'); }, 1000);

    function switchScanMode(mode) {
        const manualSec = document.getElementById('manualSection');
        const camSec = document.getElementById('cameraSection');
        const tabManual = document.getElementById('tabManual');
        const tabCamera = document.getElementById('tabCamera');

        if (mode === 'camera') {
            manualSec.classList.add('hidden'); camSec.classList.add('active');
            tabManual.classList.remove('active'); tabCamera.classList.add('active');
        } else {
            stopCamera();
            manualSec.classList.remove('hidden'); camSec.classList.remove('active');
            tabManual.classList.add('active'); tabCamera.classList.remove('active');
            setTimeout(() => document.getElementById('barcodeInput').focus(), 100);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const inp = document.getElementById('barcodeInput');
        if (inp) inp.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); prosesBarcode(); } });
    });

    function prosesBarcode() {
        const barcode = document.getElementById('barcodeInput').value.trim();
        if (!barcode) { showScanResult('Masukkan ID terlebih dahulu', false); return; }
        kirimAbsen(barcode, '');
        document.getElementById('barcodeInput').value = ''; document.getElementById('barcodeInput').focus();
    }

    function kirimAbsen(barcode, alasan) {
        let bodyData = 'action=scan_absen&barcode=' + encodeURIComponent(barcode);
        if (alasan) bodyData += '&alasan_terlambat=' + encodeURIComponent(alasan);

        fetch(AJAX_ABSENSI, {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: bodyData
        }).then(r => r.json()).then(data => {
            if (!data.success && data.terlambat) {
                Swal.fire({
                    title: 'Terapis Terlambat',
                    html: '<div style="text-align:left;font-size:14px;margin-bottom:10px;">'
                        + '<p>Nama: <strong>' + escH(data.nama_terapis) + '</strong></p>'
                        + '<p>Waktu Absen: <strong>' + escH(data.jam_absen) + '</strong></p>'
                        + '<p style="color:var(--accent-red);font-weight:bold;margin-top:10px;">Wajib mengisi alasan keterlambatan.</p></div>',
                    input: 'text', inputPlaceholder: 'Contoh: Macet, Ban bocor...',
                    inputValidator: (val) => { if (!val || val.trim().length < 4) return 'Alasan wajib diisi!'; },
                    showCancelButton: true, confirmButtonText: 'Simpan Alasan', confirmButtonColor: '#27ae60'
                }).then(res => {
                    if (res.isConfirmed && res.value) kirimAbsen(data.barcode, res.value.trim());
                });
                return;
            }

            if (data.success) {
                showScanResult('Berhasil: ' + data.message, true);
                loadAbsensi();
            } else {
                showScanResult('Gagal: ' + data.message, false);
            }
        }).catch(() => { showScanResult('Gagal menghubungi server', false); });
    }

    function showScanResult(msg, success) {
        const el = document.getElementById('scanResult');
        el.innerHTML = msg; el.className = 'scan-result ' + (success ? 'success' : 'error');
        el.style.display = 'block'; setTimeout(() => { el.style.display = 'none'; }, 5000);
    }

    // Camera JS
    let html5QrCode = null, currentFacingMode = 'environment', isScanning = false, lastScanTime = 0;
    function startCamera() {
        if (isScanning) return;
        html5QrCode = new Html5Qrcode("reader");
        Html5Qrcode.getCameras().then(cameras => {
            if (!cameras || cameras.length === 0) { showScanResult('Kamera tidak ditemukan', false); return; }
            html5QrCode.start({ facingMode: currentFacingMode }, { fps: 10, qrbox: { width: 250, height: 250 } }, 
            (decodedText) => {
                const now = Date.now(); if (now - lastScanTime < 3000) return; lastScanTime = now;
                kirimAbsen(decodedText, '');
            }, () => {}).then(() => {
                isScanning = true;
                document.getElementById('btnStartCam').style.display = 'none';
                document.getElementById('btnStopCam').style.display = 'inline-block';
                document.getElementById('btnFlipCam').style.display = 'inline-block';
                document.getElementById('camStatus').textContent = 'Kamera Aktif. Arahkan ke QR Code.';
                document.getElementById('camStatus').classList.add('scanning');
            }).catch(err => { showScanResult('Akses kamera ditolak.', false); html5QrCode = null; });
        });
    }
    function stopCamera() {
        if (html5QrCode && isScanning) {
            html5QrCode.stop().then(() => {
                html5QrCode.clear(); html5QrCode = null; isScanning = false;
                document.getElementById('btnStartCam').style.display = 'inline-block';
                document.getElementById('btnStopCam').style.display = 'none';
                document.getElementById('btnFlipCam').style.display = 'none';
                document.getElementById('camStatus').textContent = 'Kamera dihentikan.';
                document.getElementById('camStatus').classList.remove('scanning');
            });
        }
    }
    function flipCamera() { const wasScanning = isScanning; stopCamera(); currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment'; if (wasScanning) setTimeout(() => startCamera(), 500); }

    // Session Absen
    let sesiOpen = false;
    function toggleAbsensi() {
        const action = sesiOpen ? 'tutup_absen' : 'buka_absen';
        Swal.fire({
            title: sesiOpen ? 'Tutup sesi absensi?' : 'Buka sesi absensi?',
            text: sesiOpen ? 'Terapis tidak bisa absen via HP setelah ditutup.' : 'Terapis akan bisa absen via HP.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: sesiOpen ? '#e74c3c' : '#27ae60', confirmButtonText: 'Ya, Lanjutkan'
        }).then(result => {
            if (result.isConfirmed) {
                fetch(AJAX_ABSENSI, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=' + action })
                .then(r => r.json()).then(data => { if (data.success) loadAbsensi(); else Swal.fire('Gagal', data.message, 'error'); });
            }
        });
    }

    function hapusAbsen(absenId) {
        if(confirm('Yakin ingin menghapus data absensi ini?')) {
            fetch(AJAX_ABSENSI, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=hapus_absen&absen_id=' + absenId })
            .then(r => r.json()).then(data => { if (data.success) loadAbsensi(); });
        }
    }

    function loadAbsensi() {
        fetch(AJAX_ABSENSI + '?action=cek_status').then(r => r.json()).then(data => {
            if (!data.success) return;
            sesiOpen = data.sesi_open;
            document.getElementById('btnBukaAbsen').style.display = sesiOpen ? 'none' : 'inline-flex';
            document.getElementById('btnTutupAbsen').style.display = sesiOpen ? 'inline-flex' : 'none';
            const statusEl = document.getElementById('sessionStatus');
            if (data.sesi) {
                statusEl.style.display = 'block';
                if (sesiOpen) { statusEl.className = 'session-status open'; statusEl.innerHTML = 'Status: TERBUKA (Sejak ' + fmtTime(data.sesi.waktu_buka) + ')'; }
                else { statusEl.className = 'session-status closed'; statusEl.innerHTML = 'Status: TERTUTUP'; }
            } else { statusEl.style.display = 'block'; statusEl.className = 'session-status closed'; statusEl.innerHTML = 'Belum ada sesi hari ini.'; }
            
            document.getElementById('countHadir').textContent = data.total_hadir;
            document.getElementById('countTotal').textContent = data.total_terapis;
            
            const container = document.getElementById('absenListContainer');
            if (data.absen_list.length === 0) { container.innerHTML = '<div class="empty-state">Belum ada terapis yang hadir</div>'; return; }

            let html = '<table><thead><tr><th>Giliran</th><th>Nama Terapis</th><th>Waktu</th><th>Shift</th><th>Status</th><th>Alasan</th><th>Aksi</th></tr></thead><tbody>';
            data.absen_list.forEach(a => {
                const gc = a.giliran == 1 ? 'giliran-1' : (a.giliran == 2 ? 'giliran-2' : (a.giliran == 3 ? 'giliran-3' : 'giliran-other'));
                const stBadge = a.status_kehadiran === 'tepat_waktu' ? '<span class="badge badge-success">Tepat Waktu</span>' : '<span class="badge badge-danger">Terlambat</span>';
                const shBadge = a.shift_type === 'pagi' ? '<span class="badge badge-primary">Pagi</span>' : '<span class="badge" style="background:#9b59b6;color:white;border-color:#9b59b6;">Malam</span>';
                const alasan = (a.status_kehadiran === 'terlambat' && a.alasan_terlambat) ? '<small style="color:var(--accent-red);font-weight:bold;">'+escH(a.alasan_terlambat)+'</small>' : '-';
                
                html += `<tr>
                    <td><span class="giliran-badge ${gc}">${a.giliran}</span></td>
                    <td><strong>${escH(a.nama_lengkap)}</strong></td>
                    <td>${fmtTime(a.waktu_absen)}</td>
                    <td>${shBadge}</td>
                    <td>${stBadge}</td>
                    <td>${alasan}</td>
                    <td><button class="btn btn-danger btn-sm" onclick="hapusAbsen(${a.id})">Hapus</button></td>
                </tr>`;
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        });
    }

    function fmtTime(dt) { if (!dt) return '-'; const d = new Date(dt.replace(' ','T')); return String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0'); }
    function escH(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    loadAbsensi(); setInterval(loadAbsensi, 10000);
    </script>
</body>
</html>