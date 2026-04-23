<?php
require_once '../config/database.php';
if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

$id = $_GET['id'] ?? 0;

// Hapus Shift Log
if (isset($_GET['hapus_shift'])) {
    $shift_id = $_GET['hapus_shift'];
    try {
        $pdo->beginTransaction();
        
        // Hapus dari shift_logs
        $pdo->prepare("DELETE FROM shift_logs WHERE attendance_id = ?")->execute([$shift_id]);
        
        // Hapus dari kasir_attendance
        $pdo->prepare("DELETE FROM kasir_attendance WHERE id = ?")->execute([$shift_id]);
        
        $pdo->commit();
        $_SESSION['pesan_hapus'] = "Shift berhasil dihapus!";
        header("Location: detail_cabang.php?id=" . $id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['pesan_hapus'] = "Error: " . $e->getMessage();
        header("Location: detail_cabang.php?id=" . $id);
        exit;
    }
}

// Get Detail Cabang
$stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$id]);
$cabang = $stmt->fetch();

if (!$cabang) { header("Location: data_cabang.php"); exit; }

// ========================================================
// LOGIKA FILTER & PARAMETER
// ========================================================
$filter_type = $_GET['filter_type'] ?? 'limit'; 
$selected_year = $_GET['tahun'] ?? date('Y');
$selected_month = $_GET['bulan'] ?? date('m');
$tgl_awal = $_GET['tgl_awal'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';

// Base Params untuk semua query
$params = [':id' => $id];
$filter_sql = "";

if ($filter_type == 'tahunan') {
    $filter_sql = " AND YEAR(tanggal_transaksi) = :tahun"; // untuk transaksi
    $filter_sql_ka = " AND YEAR(ka.tanggal) = :tahun";    // untuk kasir_attendance
    $params[':tahun'] = $selected_year;
} elseif ($filter_type == 'bulanan') {
    $filter_sql = " AND YEAR(tanggal_transaksi) = :tahun AND MONTH(tanggal_transaksi) = :bulan";
    $filter_sql_ka = " AND YEAR(ka.tanggal) = :tahun AND MONTH(ka.tanggal) = :bulan";
    $params[':tahun'] = $selected_year;
    $params[':bulan'] = $selected_month;
} elseif ($filter_type == 'rentang') {
    if (!empty($tgl_awal) && !empty($tgl_akhir)) {
        $filter_sql = " AND tanggal_transaksi BETWEEN :tgl_awal AND :tgl_akhir";
        $filter_sql_ka = " AND ka.tanggal BETWEEN :tgl_awal AND :tgl_akhir";
        $params[':tgl_awal'] = $tgl_awal;
        $params[':tgl_akhir'] = $tgl_akhir;
    }
} else {
    // Jika 'limit' (keseluruhan), tidak ada tambahan filter tanggal
    $filter_sql = "";
    $filter_sql_ka = "";
}

// 1. Query Total Omset & Transaksi (MENGIKUTI FILTER)
$sqlStats = "SELECT SUM(total_bayar) as total_omset, COUNT(*) as total_trx 
             FROM transactions 
             WHERE branch_id = :id" . $filter_sql;
$stmtStats = $pdo->prepare($sqlStats);
$stmtStats->execute($params);
$resStats = $stmtStats->fetch();

$totalOmset = $resStats['total_omset'] ?? 0;
$totalTrx = $resStats['total_trx'] ?? 0;

// 2. Kasir Aktif Hari Ini (Selalu cek waktu sekarang, tidak kena filter)
$sqlKasirAktif = "SELECT u.nama_lengkap, ka.waktu_masuk 
                  FROM kasir_attendance ka
                  JOIN users u ON ka.kasir_id = u.id
                  WHERE ka.branch_id = ? AND ka.tanggal = CURDATE() AND ka.status = 'aktif'";
$stmtKasirAktif = $pdo->prepare($sqlKasirAktif);
$stmtKasirAktif->execute([$id]);
$kasirAktif = $stmtKasirAktif->fetchAll();

// 3. Query Riwayat Shift (MENGIKUTI FILTER)
$sqlRiwayat = "SELECT ka.*, u.nama_lengkap as nama_kasir, sl.catatan_tutup
               FROM kasir_attendance ka
               JOIN users u ON ka.kasir_id = u.id
               LEFT JOIN shift_logs sl ON ka.id = sl.attendance_id
               WHERE ka.branch_id = :id" . $filter_sql_ka;

$sqlRiwayat .= " ORDER BY ka.waktu_masuk DESC";
if ($filter_type == 'limit') { $sqlRiwayat .= " LIMIT 50"; }

$stmtRiwayat = $pdo->prepare($sqlRiwayat);
$stmtRiwayat->execute($params);
$riwayatShift = $stmtRiwayat->fetchAll();

$pesanHapus = $_SESSION['pesan_hapus'] ?? '';
unset($_SESSION['pesan_hapus']);
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail <?= htmlspecialchars($cabang['nama_cabang']) ?> - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .badge-shift { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; letter-spacing: 0.5px; }
        .bg-open { background: rgba(41, 128, 185, 0.15); color: #2980b9; border: 1px solid rgba(41, 128, 185, 0.3); }
        .bg-closed { background: rgba(39, 174, 96, 0.15); color: #27ae60; border: 1px solid rgba(39, 174, 96, 0.3); }
        .info-table td { padding: 8px 0; font-size: 14px; border-bottom: 1px dashed var(--border-color); }
        .info-table td:first-child { color: var(--text-muted); font-weight: 600; width: 140px; }
        #mapDetail { border: 2px solid var(--border-color); z-index: 1; }
        .filter-container { background: var(--bg-input); padding: 15px; border-radius: 8px; margin-bottom: 15px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 12px; font-weight: bold; color: var(--text-muted); }
        .filter-control { padding: 8px; border-radius: 5px; border: 1px solid var(--border-color); background: var(--bg-panel); color: var(--text-dark); font-size: 13px; }
    </style>
</head>
<body>
    <div class="container-layout">
        <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="https://www.dropbox.com/scl/fi/w50ceujd91ufw5gfc7boo/logo_bugar.png?rlkey=ns2z427ahk8dj87uhfiwxj8ro&st=c5kszi61&raw=1" alt="Logo Bugar" style="width: 80px; height: auto; margin-bottom: 10px; border-radius: 8px;">
        
        <h2>Owner</h2>
    </div>
    <div class="sidebar-menu">
                <a href="dashboard_owner.php" class="menu-item">Dashboard</a>
                <a href="data_cabang.php" class="menu-item active">Data Cabang</a>
                <a href="data_leader.php" class="menu-item">Data Leader</a>
                <a href="data_kasir.php" class="menu-item">Data Kasir</a>
                <a href="data_terapis.php" class="menu-item">Data Terapis</a>
                <a href="data_customer.php" class="menu-item">Data Customer</a>
                <a href="data_absensi_owner.php" class="menu-item">Data Absensi</a>
                <a href="pelanggaran_owner.php" class="menu-item">Pelanggaran</a>
                <div class="has-submenu">
                    <div class="submenu-toggle" onclick="toggleSubmenu(this)">
                        <span>Paket & Pengaturan</span>
                        <span class="arrow">▶</span>
                    </div>
                    <div class="submenu-items">
                        <a href="paket_layanan.php" class="submenu-item">Paket Layanan</a>
                        <a href="pengaturan_sistem.php" class="submenu-item">Pengaturan Sistem</a>
                    </div>
                </div>
                <a href="../auth/logout_system.php" class="menu-item" style="color: var(--accent-red); margin-top: 30px;">Keluar Sistem</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div style="display:flex; align-items:center; gap:15px;">
                    <button class="mobile-toggle" onclick="toggleMobileMenu()">☰</button>
                    <h1>Detail Cabang: <?= htmlspecialchars($cabang['nama_cabang']) ?></h1>
                </div>
                <div class="topbar-right">
                    <a href="data_cabang.php" class="btn btn-secondary">Kembali</a>
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <?php if($pesanHapus): ?>
                <div class="alert alert-success"><?= $pesanHapus ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="grid-2">
                    <div>
                        <h3 style="margin-top: 0; margin-bottom: 20px; font-family: 'Playfair Display', serif; color: var(--text-dark);">Informasi Lokasi & Performa</h3>
                        <table class="info-table" style="width: 100%;">
                            <tr>
                                <td>Alamat Lengkap</td>
                                <td><strong style="color: var(--text-dark);"><?= htmlspecialchars($cabang['alamat']) ?></strong></td>
                            </tr>
                            <tr>
                                <td>Status Saat Ini</td>
                                <td>
                                    <?php if(count($kasirAktif) > 0): ?>
                                        <span class="badge badge-success">Buka Sekarang</span>
                                        <br><small style="color: var(--text-muted); display:inline-block; margin-top:5px;">Kasir: <?= htmlspecialchars($kasirAktif[0]['nama_lengkap']) ?></small>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Tutup</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Total Omset (Filter)</td>
                                <td><strong style="color: var(--accent-red2); font-size: 16px;">Rp <?= number_format($totalOmset, 0, ',', '.') ?></strong></td>
                            </tr>
                            <tr>
                                <td>Total Transaksi (Filter)</td>
                                <td><strong style="color: var(--text-dark);"><?= number_format($totalTrx, 0, ',', '.') ?> transaksi selesai</strong></td>
                            </tr>
                            <tr>
                                <td colspan="2" style="border-bottom: none; padding-top: 15px;">
                                    <small style="color: var(--text-muted); font-style: italic;">* Data performa di atas mengikuti filter tanggal yang Anda pilih di bawah.</small>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div>
                        <div id="mapDetail" style="height: 250px; border-radius: 8px;"></div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-header">Riwayat Shift & Laporan Operasional</div>
                
                <div style="padding: 20px;">
                    <form method="GET" class="filter-container">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <div class="filter-group">
                            <label>Jenis Filter</label>
                            <select name="filter_type" id="filter_type" class="filter-control" onchange="toggleFilterFields()">
                                <option value="limit" <?= $filter_type == 'limit' ? 'selected' : '' ?>>50 Terakhir</option>
                                <option value="tahunan" <?= $filter_type == 'tahunan' ? 'selected' : '' ?>>Tahunan</option>
                                <option value="bulanan" <?= $filter_type == 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
                                <option value="rentang" <?= $filter_type == 'rentang' ? 'selected' : '' ?>>Rentang Tanggal</option>
                            </select>
                        </div>
                        <div class="filter-group field-tahunan field-bulanan" style="display:none;">
                            <label>Tahun</label>
                            <select name="tahun" class="filter-control">
                                <?php for($y=date('Y'); $y>=2024; $y--): ?>
                                <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-group field-bulanan" style="display:none;">
                            <label>Bulan</label>
                            <select name="bulan" class="filter-control">
                                <?php 
                                $months = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
                                foreach($months as $m => $name):
                                ?>
                                <option value="<?= $m ?>" <?= $selected_month == $m ? 'selected' : '' ?>><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group field-rentang" style="display:none;">
                            <label>Tgl Awal</label>
                            <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" class="filter-control">
                        </div>
                        <div class="filter-group field-rentang" style="display:none;">
                            <label>Tgl Akhir</label>
                            <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" class="filter-control">
                        </div>
                        <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                        <a href="detail_cabang.php?id=<?= $id ?>" class="btn btn-secondary">Reset</a>
                    </form>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Kasir Bertugas</th>
                                    <th>Jam Buka</th>
                                    <th>Jam Tutup</th>
                                    <th>Omset Shift</th>
                                    <th>Transaksi</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($riwayatShift) > 0): ?>
                                    <?php foreach($riwayatShift as $r): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($r['tanggal'])) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($r['nama_kasir']) ?></strong>
                                            <?php if($r['catatan_tutup']): ?>
                                                <br><small style="color: var(--accent-red); font-weight:bold;">Ada Catatan</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('H:i', strtotime($r['waktu_masuk'])) ?></td>
                                        <td><?= $r['waktu_keluar'] ? date('H:i', strtotime($r['waktu_keluar'])) : '-' ?></td>
                                        <td><strong style="color: var(--text-dark);">Rp <?= number_format($r['omset_shift'] ?? 0, 0, ',', '.') ?></strong></td>
                                        <td><?= $r['total_transaksi_shift'] ?> trx</td>
                                        <td>
                                            <?php if($r['status'] == 'aktif'): ?>
                                                <span class="badge-shift bg-open">Sedang Buka</span>
                                            <?php else: ?>
                                                <span class="badge-shift bg-closed">Selesai</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:5px;">
                                                <?php if($r['status'] == 'selesai'): ?>
                                                    <a href="laporan_detail_shift.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm" target="_blank">Lihat Laporan</a>
                                                    <a href="?id=<?= $id ?>&hapus_shift=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus shift ini?')">Hapus</a>
                                                <?php else: ?>
                                                    <button class="btn btn-warning btn-sm" disabled>Shift Live</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">Belum ada riwayat shift yang sesuai filter</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            localStorage.setItem('bugar-theme', next);
        }
        (function() {
            const saved = localStorage.getItem('bugar-theme');
            if (saved) document.documentElement.setAttribute('data-theme', saved);
        })();

        function toggleMobileMenu() { document.getElementById('sidebar').classList.toggle('active'); }
        function toggleSubmenu(el) { el.classList.toggle('active'); el.nextElementSibling.classList.toggle('open'); }

        function toggleFilterFields() {
            const type = document.getElementById('filter_type').value;
            document.querySelectorAll('.field-tahunan, .field-bulanan, .field-rentang').forEach(el => el.style.display = 'none');
            if(type === 'tahunan') document.querySelectorAll('.field-tahunan').forEach(el => el.style.display = 'flex');
            else if(type === 'bulanan') document.querySelectorAll('.field-bulanan, .field-tahunan').forEach(el => el.style.display = 'flex');
            else if(type === 'rentang') document.querySelectorAll('.field-rentang').forEach(el => el.style.display = 'flex');
        }

        document.addEventListener('DOMContentLoaded', toggleFilterFields);

        const lat = <?= $cabang['latitude'] ?? -6.200000 ?>;
        const lng = <?= $cabang['longitude'] ?? 106.816666 ?>;
        const map = L.map('mapDetail').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        L.marker([lat, lng]).addTo(map).bindPopup('<strong><?= htmlspecialchars($cabang['nama_cabang']) ?></strong>').openPopup();
    </script>
</body>
</html>