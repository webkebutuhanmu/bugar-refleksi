<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'leader') {
    header("Location: ../auth/login.php");
    exit;
}

$userId   = $_SESSION['user_id'];
$branchId = $_SESSION['user_branch_id'];

// Ambil data user leader
$stmtUser = $pdo->prepare("SELECT u.*, b.nama_cabang FROM users u 
                           LEFT JOIN branches b ON u.branch_id = b.id 
                           WHERE u.id = ?");
$stmtUser->execute([$userId]);
$userMe     = $stmtUser->fetch();
$fotoPath   = !empty($userMe['foto_profil']) ? "../uploads/profil/" . $userMe['foto_profil'] : "../assets/img/default-avatar.png";
$namaCabang = $userMe['nama_cabang'];

// Ambil jam mulai hari bisnis dari pengaturan sistem (owner)
$settingRow   = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulaiHari = $settingRow['jam_mulai_hari'] ?? '08:00:00';
// Tanggal bisnis: jika jam sekarang < jam_mulai_hari → masih "hari kemarin" secara bisnis
$today       = (date('H:i:s') < $jamMulaiHari) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$startBisnis = $today . ' ' . $jamMulaiHari; // awal hari bisnis untuk hitung transaksi hari ini

// === TERAPIS INTERNAL + STATUS ABSENSI ===
$sqlInternal = "SELECT u.id, u.username, u.nama_lengkap, u.role, u.branch_id, u.foto_profil, u.home_branch_id,
                (SELECT COUNT(*) FROM transactions t 
                 WHERE t.terapis_id = u.id AND t.branch_id = ? AND t.status = 'proses') as sedang_melayani,
                (SELECT t.nama_pelanggan FROM transactions t 
                 WHERE t.terapis_id = u.id AND t.status = 'proses' LIMIT 1) as customer_name,
                (SELECT b.nomor_bed FROM transactions t 
                 JOIN beds b ON t.bed_id = b.id
                 WHERE t.terapis_id = u.id AND t.status = 'proses' LIMIT 1) as bed_number,
                (SELECT COUNT(*) FROM terapis_attendance ta 
                 WHERE ta.terapis_id = u.id AND ta.tanggal = ? AND ta.branch_id = ?) as sudah_absen,
                (SELECT ta.waktu_absen FROM terapis_attendance ta 
                 WHERE ta.terapis_id = u.id AND ta.tanggal = ? AND ta.branch_id = ? 
                 ORDER BY ta.waktu_absen ASC LIMIT 1) as jam_absen,
                (SELECT COUNT(*) FROM transactions t 
                 WHERE t.terapis_id = u.id AND t.branch_id = ? 
                 AND t.created_at >= ? AND t.status != 'batal') as total_kerja_hari_ini
                FROM users u
                WHERE u.home_branch_id = ? AND u.role = 'terapis'
                ORDER BY u.nama_lengkap ASC";
$stmtInternal = $pdo->prepare($sqlInternal);
$stmtInternal->execute([$branchId, $today, $branchId, $today, $branchId, $branchId, $startBisnis, $branchId]);
$terapisInternal = $stmtInternal->fetchAll();

// === TERAPIS BANTUAN ===
$sqlBantuan = "SELECT u.id, u.username, u.nama_lengkap, u.role, u.branch_id, u.foto_profil, u.home_branch_id,
               b.nama_cabang as cabang_asal, tl.approved_at,
               (SELECT COUNT(*) FROM transactions t 
                WHERE t.terapis_id = u.id AND t.branch_id = ? AND t.status = 'proses') as sedang_melayani,
               (SELECT t.nama_pelanggan FROM transactions t 
                WHERE t.terapis_id = u.id AND t.status = 'proses' LIMIT 1) as customer_name,
               (SELECT bd.nomor_bed FROM transactions t 
                JOIN beds bd ON t.bed_id = bd.id
                WHERE t.terapis_id = u.id AND t.status = 'proses' LIMIT 1) as bed_number,
               (SELECT COUNT(*) FROM terapis_attendance ta 
                WHERE ta.terapis_id = u.id AND ta.tanggal = ? AND ta.branch_id = ?) as sudah_absen,
               (SELECT ta.waktu_absen FROM terapis_attendance ta 
                WHERE ta.terapis_id = u.id AND ta.tanggal = ? AND ta.branch_id = ? 
                ORDER BY ta.waktu_absen ASC LIMIT 1) as jam_absen,
               (SELECT COUNT(*) FROM transactions t 
                WHERE t.terapis_id = u.id AND t.branch_id = ? 
                AND t.created_at >= ? AND t.status != 'batal') as total_kerja_hari_ini
               FROM terapis_loans tl
               JOIN users u ON tl.terapis_id = u.id
               JOIN branches b ON tl.from_branch_id = b.id
               WHERE tl.to_branch_id = ? AND tl.status = 'active'
               ORDER BY u.nama_lengkap ASC";
$stmtBantuan = $pdo->prepare($sqlBantuan);
$stmtBantuan->execute([$branchId, $today, $branchId, $today, $branchId, $branchId, $startBisnis, $branchId]);
$terapisBantuan = $stmtBantuan->fetchAll();

// === TERAPIS DIPINJAM KE CABANG LAIN ===
$stmtKeluar = $pdo->prepare("SELECT u.id, u.username, u.nama_lengkap, u.foto_profil,
              b.nama_cabang as cabang_tujuan, tl.approved_at
              FROM terapis_loans tl
              JOIN users u ON tl.terapis_id = u.id
              JOIN branches b ON tl.to_branch_id = b.id
              WHERE tl.from_branch_id = ? AND tl.status = 'active'
              ORDER BY u.nama_lengkap ASC");
$stmtKeluar->execute([$branchId]);
$terapisKeluar = $stmtKeluar->fetchAll();

// === DATA BED CABANG ===
$stmtBeds = $pdo->prepare("SELECT b.*,
    (SELECT COUNT(*) FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran')) as is_occupied,
    (SELECT t.status FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') LIMIT 1) as trx_status,
    (SELECT t.nama_pelanggan FROM transactions t WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') LIMIT 1) as customer_name,
    (SELECT u.nama_lengkap FROM transactions t JOIN users u ON t.terapis_id = u.id WHERE t.bed_id = b.id AND t.status IN ('proses','menunggu_pembayaran') LIMIT 1) as terapis_name,
    (SELECT t.waktu_selesai FROM transactions t WHERE t.bed_id = b.id AND t.status = 'proses' LIMIT 1) as finish_time
    FROM beds b WHERE b.branch_id = ? ORDER BY b.nomor_bed ASC");
$stmtBeds->execute([$branchId]);
$beds = $stmtBeds->fetchAll();

$bedKosong = count(array_filter($beds, fn($b) => $b['is_occupied'] == 0));
$bedTerisi = count(array_filter($beds, fn($b) => $b['is_occupied'] > 0));

// === PERMINTAAN IZIN / SAKIT PENDING ===
$stmtIzinPending = $pdo->prepare(
    "SELECT ti.*, u.nama_lengkap, u.foto_profil 
     FROM terapis_izin ti
     JOIN users u ON ti.terapis_id = u.id
     WHERE ti.branch_id = ? AND ti.status = 'pending'
     ORDER BY ti.created_at ASC"
);
$stmtIzinPending->execute([$branchId]);
$izinPendingList = $stmtIzinPending->fetchAll(PDO::FETCH_ASSOC);

// === MAP IZIN/SAKIT YANG DISETUJUI/PENDING PER TERAPIS (untuk status di tabel) ===
$stmtIzinMap = $pdo->prepare(
    "SELECT ti.terapis_id, ti.jenis, ti.status 
     FROM terapis_izin ti 
     WHERE ti.branch_id = ? AND ti.tanggal = ? AND ti.status IN ('disetujui','pending')
     ORDER BY ti.id DESC"
);
$stmtIzinMap->execute([$branchId, $today]);
$izinMapRows = $stmtIzinMap->fetchAll(PDO::FETCH_ASSOC);
$izinMap = []; // terapis_id => ['jenis'=>izin/sakit, 'status'=>disetujui/pending]
foreach ($izinMapRows as $im) {
    if (!isset($izinMap[$im['terapis_id']])) {
        $izinMap[$im['terapis_id']] = $im;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Terapis - Leader Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        :root { --sidebar-w: 250px; --primary: #2c3e50; --accent: #3498db; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; }
        .sidebar { width: var(--sidebar-w); background: linear-gradient(180deg, var(--primary) 0%, #34495e 100%); height: 100vh; position: fixed; color: white; overflow-y: auto; }
        .sidebar-brand { padding: 25px 20px; text-align: center; border-bottom: 1px solid #34495e; font-weight: bold; font-size: 20px; }
        .profile-section { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .img-nav { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent); margin-bottom: 10px; }
        .nav-menu { padding: 10px 0; }
        .nav-link { display: block; padding: 12px 20px; color: #bdc3c7; text-decoration: none; font-size: 14px; transition: all 0.3s; border-left: 4px solid transparent; }
        .nav-link:hover, .nav-link.active { background: #34495e; color: white; border-left: 4px solid var(--accent); }
        .main-content { margin-left: var(--sidebar-w); padding: 30px; }
        .card-custom { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .avatar-table { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }

        /* === BED GRID === */
        .bed-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 14px; margin-top: 16px; }
        .bed-box {
            background: white; border-radius: 12px; padding: 16px 12px;
            text-align: center; border: 2px solid #ecf0f1; position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); min-height: 130px;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; transition: all 0.2s;
        }
        .bed-box.bed-kosong  { border-color: #27ae60; background: linear-gradient(135deg, #f0fdf4, #eafaf1); }
        .bed-box.bed-terisi  { border-color: #e74c3c; background: linear-gradient(135deg, #fff5f5, #fdedec); }
        .bed-box.bed-waiting { border-color: #9b59b6; background: linear-gradient(135deg, #faf0ff, #f4ecf7); }
        .bed-box.bed-overtime { border-color: #e67e22; background: linear-gradient(135deg, #fff8f0, #fef9e7); animation: pulseOT 1.5s infinite; }
        @keyframes pulseOT { 0%,100%{box-shadow:0 0 0 0 rgba(230,126,34,0.4);} 50%{box-shadow:0 0 0 10px rgba(230,126,34,0);} }
        .bed-icon  { font-size: 28px; line-height: 1; }
        .bed-num   { font-size: 18px; font-weight: 800; color: #2c3e50; }
        .bed-tipe  { font-size: 10px; color: #7f8c8d; }
        .bed-info-small { font-size: 10px; margin-top: 3px; line-height: 1.4; text-align: center; width: 100%; }
        .bed-del-btn {
            position: absolute; top: 6px; right: 6px;
            background: #e74c3c; color: white; border: none; border-radius: 50%;
            width: 22px; height: 22px; font-size: 12px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s; line-height: 1;
        }
        .bed-box:hover .bed-del-btn { opacity: 1; }

        .absen-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .absen-hadir { background: #d5f5e3; color: #1e8449; }
        .absen-belum { background: #fde8e8; color: #c0392b; }
        .absen-izin  { background: #fff3e0; color: #e65100; }
        .absen-sakit { background: #fce4ec; color: #c62828; }

        /* === KERJA BADGE === */
        .kerja-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .kerja-0    { background: #f1f2f6; color: #7f8c8d; }
        .kerja-low  { background: #ebf5fb; color: #2980b9; }
        .kerja-mid  { background: #eafaf1; color: #1e8449; }
        .kerja-high { background: #fef9e7; color: #d35400; }

        /* === MODAL === */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.55); z-index: 9999; justify-content: center; align-items: center;
        }
        .modal-overlay.show { display: flex; }
        .modal-box { background: white; border-radius: 15px; padding: 30px; max-width: 420px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }

        /* === TOAST === */
        #toastMsg {
            position: fixed; bottom: 24px; right: 24px; min-width: 220px;
            padding: 12px 20px; border-radius: 10px; font-size: 14px; font-weight: 500;
            color: white; box-shadow: 0 5px 20px rgba(0,0,0,0.25); z-index: 99999;
            display: none; transition: all 0.3s;
        }
        #toastMsg.show { display: block; }
        #toastMsg.success { background: #27ae60; }
        #toastMsg.danger  { background: #e74c3c; }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-brand"><i class="bi bi-building"></i> LEADER PANEL</div>
    <div class="profile-section">
        <img src="<?= $fotoPath ?>" class="img-nav" alt="Profile">
        <div style="font-size:16px; font-weight:bold; margin-top:10px;"><?= htmlspecialchars($userMe['nama_lengkap']) ?></div>
        <small style="color: #95a5a6;"><?= htmlspecialchars($namaCabang) ?></small>
    </div>
    <div class="nav-menu">
        <a href="dashboard_leader.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="data_terapis_leader.php" class="nav-link"><i class="bi bi-people"></i> Data Terapis</a>
        <a href="stok_barang_leader.php" class="nav-link"><i class="bi bi-box-seam"></i> Stok Barang</a>
        <a href="monitoring_terapis.php" class="nav-link active"><i class="bi bi-eye"></i> Monitoring Terapis</a>
        <a href="pelanggaran_terapis.php" class="nav-link"><i class="bi bi-exclamation-triangle"></i> Pelanggaran</a>
        <a href="profil_leader.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
    </div>
    <div style="padding: 20px; margin-top: auto;">
        <a href="../auth/logout_system.php" class="btn btn-danger w-100"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="mb-4">
        <h2><i class="bi bi-eye text-primary"></i> Monitoring Status Terapis &amp; Bed</h2>
        <p class="text-muted mb-0">Real-time status terapis di cabang: <strong><?= htmlspecialchars($namaCabang) ?></strong></p>
    </div>

    <!-- Ringkasan -->
    <div class="row mb-4">
        <div class="col-md-3 col-6 mb-3">
            <div class="card-custom" style="border-left:4px solid #3498db; padding:18px;">
                <div style="font-size:12px; color:#7f8c8d;">Terapis Internal</div>
                <div style="font-size:32px; font-weight:800; color:#3498db;"><?= count($terapisInternal) ?></div>
                <small class="text-muted">Orang</small>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card-custom" style="border-left:4px solid #f39c12; padding:18px;">
                <div style="font-size:12px; color:#7f8c8d;">Terapis Bantuan</div>
                <div style="font-size:32px; font-weight:800; color:#f39c12;"><?= count($terapisBantuan) ?></div>
                <small class="text-muted">Orang</small>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card-custom" style="border-left:4px solid #27ae60; padding:18px;">
                <div style="font-size:12px; color:#7f8c8d;">Bed Kosong</div>
                <div style="font-size:32px; font-weight:800; color:#27ae60;"><?= $bedKosong ?></div>
                <small class="text-muted">dari <?= count($beds) ?> bed</small>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card-custom" style="border-left:4px solid #e74c3c; padding:18px;">
                <div style="font-size:12px; color:#7f8c8d;">Bed Terisi</div>
                <div style="font-size:32px; font-weight:800; color:#e74c3c;"><?= $bedTerisi ?></div>
                <small class="text-muted">sedang dipakai</small>
            </div>
        </div>
    </div>

    <!-- STATUS BED -->
    <div class="card-custom" style="border-left: 4px solid #2c3e50;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="bi bi-grid-fill"></i> Status Bed Cabang
                <small class="text-muted fw-normal ms-2" style="font-size:13px;">(<?= count($beds) ?> Total)</small>
            </h5>
            <button class="btn btn-sm btn-primary" onclick="showTambahBed()">
                <i class="bi bi-plus-circle"></i> Tambah Bed
            </button>
        </div>
        <div style="display:flex; gap:12px; flex-wrap:wrap; font-size:12px; padding:8px 12px; background:#f8f9fa; border-radius:8px; margin-bottom:14px;">
            <div><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#27ae60;margin-right:5px;vertical-align:middle;"></span>Kosong</div>
            <div><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#e74c3c;margin-right:5px;vertical-align:middle;"></span>Terisi</div>
            <div><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#e67e22;margin-right:5px;vertical-align:middle;"></span>Overtime</div>
            <div><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#9b59b6;margin-right:5px;vertical-align:middle;"></span>Menunggu Bayar</div>
            <div class="ms-auto text-muted" style="font-size:11px;"><i class="bi bi-info-circle"></i> Hover bed kosong → tombol hapus</div>
        </div>
        <div class="bed-grid">
            <?php if (empty($beds)): ?>
            <div class="text-center text-muted py-4" style="grid-column:1/-1;">
                <i class="bi bi-grid" style="font-size:40px;opacity:0.3;display:block;margin-bottom:10px;"></i>
                Belum ada bed. Klik "Tambah Bed" untuk menambahkan.
            </div>
            <?php else: ?>
            <?php foreach ($beds as $bed):
                $isOccupied = ($bed['is_occupied'] > 0);
                $isWaiting  = ($bed['trx_status'] ?? '') === 'menunggu_pembayaran';
                $isOvertime = ($isOccupied && !$isWaiting && !empty($bed['finish_time']) && strtotime($bed['finish_time']) <= time());
                if ($isWaiting)      $bedClass = 'bed-waiting';
                elseif ($isOvertime) $bedClass = 'bed-overtime';
                elseif ($isOccupied) $bedClass = 'bed-terisi';
                else                 $bedClass = 'bed-kosong';
            ?>
            <div class="bed-box <?= $bedClass ?>">
                <?php if (!$isOccupied): ?>
                <button class="bed-del-btn" onclick="hapusBed(<?= $bed['id'] ?>, '<?= htmlspecialchars(addslashes($bed['nomor_bed'])) ?>')" title="Hapus bed ini">
                    <i class="bi bi-x"></i>
                </button>
                <?php endif; ?>
                <div class="bed-icon">
                    <?php if ($isWaiting): ?>&#128176;
                    <?php elseif ($isOccupied): ?>&#128134;
                    <?php else: ?>&#129718;
                    <?php endif; ?>
                </div>
                <div class="bed-num"><?= htmlspecialchars($bed['nomor_bed']) ?></div>
                <div class="bed-tipe"><?= htmlspecialchars($bed['tipe']) ?></div>
                <?php if ($isOccupied): ?>
                <div class="bed-info-small">
                    <span style="font-weight:700;color:#c0392b;"><?= htmlspecialchars(mb_substr($bed['customer_name'] ?? '-', 0, 14)) ?></span>
                    <?php if ($bed['terapis_name']): ?>
                    <br><span style="color:#7f8c8d;font-size:9px;"><?= htmlspecialchars(mb_substr($bed['terapis_name'], 0, 14)) ?></span>
                    <?php endif; ?>
                    <?php if ($isWaiting): ?><br><span style="color:#9b59b6;font-size:9px;font-weight:700;">&#128176; BAYAR</span>
                    <?php elseif ($isOvertime): ?><br><span style="color:#e67e22;font-size:9px;font-weight:700;">&#9888; OVERTIME</span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="bed-info-small" style="color:#27ae60;font-weight:600;">Kosong</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- TERAPIS INTERNAL -->
    <div class="card-custom">
        <h5 class="mb-3"><i class="bi bi-people-fill text-primary"></i> Terapis Internal (Tetap di Cabang)</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>No</th><th>Foto</th><th>Nama Terapis</th><th>Username</th>
                        <th>Absensi Hari Ini</th><th>Kerja Hari Ini</th><th>Status Layanan</th><th>Info Layanan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($terapisInternal as $t):
                        $foto = !empty($t['foto_profil']) ? "../uploads/profil/" . $t['foto_profil'] : "../assets/img/default-avatar.png";
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><img src="<?= $foto ?>" class="avatar-table" alt="Foto"></td>
                        <td><strong><?= htmlspecialchars($t['nama_lengkap']) ?></strong></td>
                        <td><?= htmlspecialchars($t['username']) ?></td>
                        <td>
                            <?php if ($t['sudah_absen'] > 0): ?>
                                <span class="absen-badge absen-hadir"><i class="bi bi-check-circle-fill"></i> Hadir</span>
                                <?php if ($t['jam_absen']): ?>
                                <br><small class="text-muted"><?= date('H:i', strtotime($t['jam_absen'])) ?></small>
                                <?php endif; ?>
                            <?php elseif (isset($izinMap[$t['id']])): ?>
                                <?php 
                                $izData = $izinMap[$t['id']];
                                $izJenis = $izData['jenis'];
                                $izStatus = $izData['status'];
                                if ($izJenis === 'sakit'): ?>
                                    <span class="absen-badge absen-sakit"><i class="bi bi-heart-pulse-fill"></i> Sakit</span>
                                <?php else: ?>
                                    <span class="absen-badge absen-izin"><i class="bi bi-envelope-fill"></i> Izin</span>
                                <?php endif; ?>
                                <?php if ($izStatus === 'pending'): ?>
                                    <br><small class="text-muted" style="color:#e67e22;">&#9203; Menunggu Approval</small>
                                <?php else: ?>
                                    <br><small class="text-muted" style="color:#27ae60;">&#9989; Disetujui</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="absen-badge absen-belum"><i class="bi bi-x-circle-fill"></i> Belum Absen</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $kerja = (int)($t['total_kerja_hari_ini'] ?? 0);
                            if ($kerja === 0)     $kerjaClass = 'kerja-0';
                            elseif ($kerja <= 2)  $kerjaClass = 'kerja-low';
                            elseif ($kerja <= 5)  $kerjaClass = 'kerja-mid';
                            else                  $kerjaClass = 'kerja-high';
                            ?>
                            <span class="kerja-badge <?= $kerjaClass ?>">
                                <i class="bi bi-hand-index-thumb-fill"></i> <?= $kerja ?>x
                            </span>
                            <?php if ($kerja > 0): ?>
                            <br><small class="text-muted" style="font-size:10px;">pelanggan hari ini</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['sedang_melayani'] > 0): ?>
                                <span class="status-badge bg-danger text-white"><i class="bi bi-circle-fill"></i> Sibuk</span>
                            <?php else: ?>
                                <span class="status-badge bg-success text-white"><i class="bi bi-circle-fill"></i> Tersedia</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['sedang_melayani'] > 0): ?>
                                <small class="text-muted">
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($t['customer_name']) ?><br>
                                    <i class="bi bi-grid-fill"></i> Bed <?= htmlspecialchars($t['bed_number'] ?? '-') ?>
                                </small>
                            <?php else: ?><small class="text-muted">-</small><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($terapisInternal) == 0): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><em>Belum ada terapis internal</em></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TERAPIS BANTUAN MASUK -->
    <?php if (count($terapisBantuan) > 0): ?>
    <div class="card-custom" style="border-left: 4px solid #f39c12;">
        <h5 class="mb-3"><i class="bi bi-arrow-down-circle text-warning"></i> Terapis Bantuan dari Cabang Lain</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr><th>No</th><th>Foto</th><th>Nama Terapis</th><th>Asal Cabang</th><th>Sejak</th><th>Absensi</th><th>Kerja Hari Ini</th><th>Status Layanan</th><th>Info Layanan</th></tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($terapisBantuan as $t):
                        $foto = !empty($t['foto_profil']) ? "../uploads/profil/" . $t['foto_profil'] : "../assets/img/default-avatar.png";
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><img src="<?= $foto ?>" class="avatar-table" alt="Foto"></td>
                        <td><strong><?= htmlspecialchars($t['nama_lengkap']) ?></strong></td>
                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($t['cabang_asal']) ?></span></td>
                        <td><?= date('d/m H:i', strtotime($t['approved_at'])) ?></td>
                        <td>
                            <?php if ($t['sudah_absen'] > 0): ?>
                                <span class="absen-badge absen-hadir"><i class="bi bi-check-circle-fill"></i> Hadir</span>
                                <?php if ($t['jam_absen']): ?><br><small class="text-muted"><?= date('H:i', strtotime($t['jam_absen'])) ?></small><?php endif; ?>
                            <?php elseif (isset($izinMap[$t['id']])): ?>
                                <?php 
                                $izData2 = $izinMap[$t['id']];
                                if ($izData2['jenis'] === 'sakit'): ?>
                                    <span class="absen-badge absen-sakit"><i class="bi bi-heart-pulse-fill"></i> Sakit</span>
                                <?php else: ?>
                                    <span class="absen-badge absen-izin"><i class="bi bi-envelope-fill"></i> Izin</span>
                                <?php endif; ?>
                                <?php if ($izData2['status'] === 'pending'): ?>
                                    <br><small class="text-muted" style="color:#e67e22;">&#9203; Menunggu</small>
                                <?php else: ?>
                                    <br><small class="text-muted" style="color:#27ae60;">&#9989; Disetujui</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="absen-badge absen-belum"><i class="bi bi-x-circle-fill"></i> Belum Absen</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $kerja2 = (int)($t['total_kerja_hari_ini'] ?? 0);
                            if ($kerja2 === 0)     $kerjaClass2 = 'kerja-0';
                            elseif ($kerja2 <= 2)  $kerjaClass2 = 'kerja-low';
                            elseif ($kerja2 <= 5)  $kerjaClass2 = 'kerja-mid';
                            else                   $kerjaClass2 = 'kerja-high';
                            ?>
                            <span class="kerja-badge <?= $kerjaClass2 ?>">
                                <i class="bi bi-hand-index-thumb-fill"></i> <?= $kerja2 ?>x
                            </span>
                            <?php if ($kerja2 > 0): ?>
                            <br><small class="text-muted" style="font-size:10px;">pelanggan hari ini</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['sedang_melayani'] > 0): ?>
                                <span class="status-badge bg-danger text-white"><i class="bi bi-circle-fill"></i> Sibuk</span>
                            <?php else: ?>
                                <span class="status-badge bg-success text-white"><i class="bi bi-circle-fill"></i> Tersedia</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['sedang_melayani'] > 0): ?>
                                <small class="text-muted">
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($t['customer_name']) ?><br>
                                    <i class="bi bi-grid-fill"></i> Bed <?= htmlspecialchars($t['bed_number'] ?? '-') ?>
                                </small>
                            <?php else: ?><small class="text-muted">-</small><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- TERAPIS DIPINJAM KELUAR -->
    <?php if (count($terapisKeluar) > 0): ?>
    <div class="card-custom" style="border-left: 4px solid #e74c3c;">
        <h5 class="mb-3"><i class="bi bi-arrow-up-circle text-danger"></i> Terapis Sedang Dipinjam ke Cabang Lain</h5>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Terapis berikut sedang membantu di cabang lain.</div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr><th>No</th><th>Nama Terapis</th><th>Dipinjam ke Cabang</th><th>Sejak</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($terapisKeluar as $t): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($t['nama_lengkap']) ?></strong></td>
                        <td><span class="badge bg-danger"><?= htmlspecialchars($t['cabang_tujuan']) ?></span></td>
                        <td><?= date('d/m H:i', strtotime($t['approved_at'])) ?></td>
                        <td><span class="status-badge bg-warning text-dark"><i class="bi bi-arrow-right"></i> Dipinjam</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- PERMINTAAN IZIN / SAKIT -->
    <?php if (count($izinPendingList) > 0): ?>
    <div class="card-custom" style="border-left: 4px solid #e67e22;">
        <h5 class="mb-3"><i class="bi bi-envelope-exclamation text-warning"></i> Permintaan Izin / Sakit <span class="badge bg-warning text-dark"><?= count($izinPendingList) ?></span></h5>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Terapis berikut mengajukan izin/sakit. Silakan setujui atau tolak.</div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr><th>No</th><th>Nama Terapis</th><th>Jenis</th><th>Tanggal</th><th>Keterangan</th><th>Waktu Ajuan</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($izinPendingList as $iz): ?>
                    <tr id="izinRow_<?= $iz['id'] ?>">
                        <td><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($iz['nama_lengkap']) ?></strong></td>
                        <td>
                            <?php if ($iz['jenis'] === 'izin'): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-envelope"></i> Izin</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-heart-pulse"></i> Sakit</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($iz['tanggal'])) ?></td>
                        <td style="max-width:200px;font-size:13px;"><?= htmlspecialchars($iz['keterangan']) ?></td>
                        <td><?= date('d/m H:i', strtotime($iz['created_at'])) ?></td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="approveIzin(<?= $iz['id'] ?>, '<?= htmlspecialchars($iz['nama_lengkap']) ?>', '<?= $iz['jenis'] ?>')">
                                <i class="bi bi-check-circle"></i> Setujui
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="rejectIzin(<?= $iz['id'] ?>, '<?= htmlspecialchars($iz['nama_lengkap']) ?>', '<?= $iz['jenis'] ?>')">
                                <i class="bi bi-x-circle"></i> Tolak
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- CEK ALPHA (TERAPIS TIDAK HADIR SETELAH IZIN DITOLAK) -->
    <div class="card-custom" style="border-left: 4px solid #9b59b6;">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <i class="bi bi-shield-exclamation text-purple me-3" style="font-size:24px;color:#9b59b6;"></i>
                <div>
                    <strong>Cek Pelanggaran Alpha</strong>
                    <p class="mb-0 text-muted" style="font-size:13px;">Periksa terapis yang izinnya ditolak tapi tidak hadir absen hari ini. Otomatis catat pelanggaran alpha.</p>
                </div>
            </div>
            <button class="btn btn-outline-danger btn-sm" onclick="cekAlpha()" id="btnCekAlpha">
                <i class="bi bi-search"></i> Cek Sekarang
            </button>
        </div>
    </div>

    <div class="card-custom" style="background:#e8f5e9; border:none;">
        <div class="d-flex align-items-center">
            <i class="bi bi-arrow-repeat text-success me-3" style="font-size:24px;"></i>
            <div>
                <strong>Auto Refresh:</strong>
                <p class="mb-0 text-muted">Halaman ini refresh otomatis setiap 30 detik. Bed yang terisi tidak dapat dihapus.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Bed -->
<div class="modal-overlay" id="modalTambahBed">
    <div class="modal-box">
        <h5 class="mb-4"><i class="bi bi-plus-circle text-primary"></i> Tambah Bed Baru</h5>
        <div class="mb-3">
            <label class="form-label fw-bold">Nomor / Kode Bed <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="inputNomorBed"
                   placeholder="Contoh: L1, B3, VIP1, 07" maxlength="10"
                   onkeydown="if(event.key==='Enter') submitTambahBed()">
            <div class="form-text">Maks. 10 karakter, unik di cabang ini.</div>
        </div>
        <div class="mb-4">
            <label class="form-label fw-bold">Tipe Bed</label>
            <select class="form-select" id="inputTipeBed">
                <option value="Regular">Regular</option>
                <option value="Atas">Atas</option>
                <option value="Bawah">Bawah</option>
                <option value="Laki-laki">Laki-laki</option>
                <option value="Perempuan">Perempuan</option>
            </select>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary flex-fill" id="btnSubmitTambah" onclick="submitTambahBed()">
                <i class="bi bi-check-circle"></i> Tambah Bed
            </button>
            <button class="btn btn-secondary flex-fill" onclick="closeTambahBed()">Batal</button>
        </div>
    </div>
</div>

<div id="toastMsg"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const AJAX_URL = 'ajax_bed_leader.php';

    function showToast(msg, type) {
        const t = document.getElementById('toastMsg');
        t.textContent = msg;
        t.className = 'show ' + (type || 'success');
        t.style.display = 'block';
        setTimeout(() => { t.style.display = 'none'; t.className = ''; }, 3500);
    }

    function showTambahBed() {
        document.getElementById('inputNomorBed').value = '';
        document.getElementById('inputTipeBed').value = 'Regular';
        document.getElementById('modalTambahBed').classList.add('show');
        setTimeout(() => document.getElementById('inputNomorBed').focus(), 150);
    }

    function closeTambahBed() {
        document.getElementById('modalTambahBed').classList.remove('show');
    }

    function submitTambahBed() {
        const nomor = document.getElementById('inputNomorBed').value.trim();
        const tipe  = document.getElementById('inputTipeBed').value;
        if (!nomor) {
            document.getElementById('inputNomorBed').focus();
            showToast('Nomor bed tidak boleh kosong', 'danger');
            return;
        }
        const btn = document.getElementById('btnSubmitTambah');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyimpan...';

        const fd = new FormData();
        fd.append('action', 'tambah_bed');
        fd.append('nomor_bed', nomor);
        fd.append('tipe', tipe);

        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(r => { if (!r.ok) throw new Error('Server error: HTTP ' + r.status); return r.json(); })
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeTambahBed();
                    setTimeout(() => location.reload(), 900);
                } else {
                    showToast(data.message || 'Gagal menambah bed', 'danger');
                }
            })
            .catch(err => showToast('Error: ' + err.message, 'danger'))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle"></i> Tambah Bed';
            });
    }

    function hapusBed(bedId, nomorBed) {
        if (!confirm('Hapus Bed "' + nomorBed + '"?\n\nBed yang sedang dipakai tidak bisa dihapus.')) return;
        const fd = new FormData();
        fd.append('action', 'hapus_bed');
        fd.append('bed_id', bedId);

        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(r => { if (!r.ok) throw new Error('Server error: HTTP ' + r.status); return r.json(); })
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 900);
                } else {
                    showToast(data.message || 'Gagal menghapus bed', 'danger');
                }
            })
            .catch(err => showToast('Error: ' + err.message, 'danger'));
    }

    document.getElementById('modalTambahBed').addEventListener('click', function(e) {
        if (e.target === this) closeTambahBed();
    });

    // === IZIN / SAKIT FUNCTIONS ===
    const AJAX_IZIN_URL = '../kasir/ajax_izin_sakit.php';

    function approveIzin(id, nama, jenis) {
        var label = jenis === 'izin' ? 'Izin' : 'Sakit';
        if (!confirm('Setujui pengajuan ' + label + ' dari ' + nama + '?')) return;

        var fd = new FormData();
        fd.append('action', 'approve_izin');
        fd.append('izin_id', id);

        fetch(AJAX_IZIN_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    var row = document.getElementById('izinRow_' + id);
                    if (row) row.style.display = 'none';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Gagal menyetujui', 'danger');
                }
            })
            .catch(err => showToast('Error: ' + err.message, 'danger'));
    }

    function rejectIzin(id, nama, jenis) {
        var label = jenis === 'izin' ? 'Izin' : 'Sakit';
        var catatan = prompt('Tolak pengajuan ' + label + ' dari ' + nama + '.\n\nCatatan untuk terapis (opsional):');
        if (catatan === null) return; // cancelled

        var fd = new FormData();
        fd.append('action', 'tolak_izin');
        fd.append('izin_id', id);
        fd.append('catatan', catatan || '');

        fetch(AJAX_IZIN_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    var row = document.getElementById('izinRow_' + id);
                    if (row) row.style.display = 'none';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Gagal menolak', 'danger');
                }
            })
            .catch(err => showToast('Error: ' + err.message, 'danger'));
    }

    function cekAlpha() {
        var btn = document.getElementById('btnCekAlpha');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memeriksa...';

        var fd = new FormData();
        fd.append('action', 'cek_alpha');

        fetch(AJAX_IZIN_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.count > 0) {
                        showToast(data.message, 'warning');
                    } else {
                        showToast(data.message || 'Tidak ada pelanggaran alpha ditemukan.', 'success');
                    }
                } else {
                    showToast(data.message || 'Gagal memeriksa', 'danger');
                }
            })
            .catch(err => showToast('Error: ' + err.message, 'danger'))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-search"></i> Cek Sekarang';
            });
    }

    // Auto refresh 30 detik
    setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>