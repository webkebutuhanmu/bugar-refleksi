<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) { 
    header("Location: pilih_cabang.php"); exit; 
}

$branch_id = $_SESSION['active_branch'];
$kasir_id  = $_SESSION['user_id'];
$nama_kasir = $_SESSION['nama'];
$nama_cabang = $pdo->query("SELECT nama_cabang FROM branches WHERE id = $branch_id")->fetchColumn();

// Foto profil untuk Sidebar
$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$foto_profil = $stmtProfil->fetchColumn();
$foto_profil = (!empty($foto_profil) && file_exists("../uploads/profil/" . $foto_profil)) ? "../uploads/profil/" . $foto_profil : "../assets/default_user.png";

// --- LOGIC PENENTUAN HARI BISNIS ---
$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulaiBisnis = $setting['jam_mulai_hari'] ?? '08:00:00';
$jamSekarang = date('H:i:s');
$tglBisnis = ($jamSekarang < $jamMulaiBisnis) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

// --- LOGIC FILTER TANGGAL ---
$filter = $_GET['filter'] ?? 'semua';
$custom_start = $_GET['start_date'] ?? $tglBisnis;
$custom_end = $_GET['end_date'] ?? $tglBisnis;

$whereDate = "";
$params = [$branch_id];
$label_periode = "Semua Waktu";

if ($filter == 'hari_ini') {
    $whereDate = " AND tanggal_transaksi = ?";
    $params[] = $tglBisnis;
    $label_periode = "Hari Ini (" . date('d/m/Y', strtotime($tglBisnis)) . ")";
} elseif ($filter == 'minggu_ini') {
    $whereDate = " AND tanggal_transaksi BETWEEN ? AND ?";
    $start_week = date('Y-m-d', strtotime('monday this week', strtotime($tglBisnis)));
    $end_week = date('Y-m-d', strtotime('sunday this week', strtotime($tglBisnis)));
    $params[] = $start_week;
    $params[] = $end_week;
    $label_periode = "Minggu Ini (" . date('d/m/Y', strtotime($start_week)) . " - " . date('d/m/Y', strtotime($end_week)) . ")";
} elseif ($filter == 'bulan_ini') {
    $whereDate = " AND tanggal_transaksi BETWEEN ? AND ?";
    $start_month = date('Y-m-01', strtotime($tglBisnis));
    $end_month = date('Y-m-t', strtotime($tglBisnis));
    $params[] = $start_month;
    $params[] = $end_month;
    $label_periode = "Bulan Ini (" . date('F Y', strtotime($tglBisnis)) . ")";
} elseif ($filter == 'custom') {
    $whereDate = " AND tanggal_transaksi BETWEEN ? AND ?";
    $params[] = $custom_start;
    $params[] = $custom_end;
    $label_periode = date('d/m/Y', strtotime($custom_start)) . " s/d " . date('d/m/Y', strtotime($custom_end));
}

// Query untuk mendapatkan data customer berdasarkan filter
$sqlCustomers = "SELECT 
                 nama_pelanggan,
                 no_hp_pelanggan,
                 COUNT(*) as total_kunjungan,
                 SUM(total_bayar) as total_belanja,
                 MAX(tanggal_transaksi) as kunjungan_terakhir,
                 MIN(tanggal_transaksi) as kunjungan_pertama
                 FROM transactions 
                 WHERE nama_pelanggan != '' AND nama_pelanggan IS NOT NULL AND branch_id = ?
                 $whereDate
                 GROUP BY nama_pelanggan, no_hp_pelanggan
                 ORDER BY total_kunjungan DESC, kunjungan_terakhir DESC";
$stmt = $pdo->prepare($sqlCustomers);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Customer - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <style>
        /* Filter Bar */
        .filter-bar { background: var(--bg-panel); border-radius: 12px; padding: 15px 20px; border: 1px solid var(--border-color); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-shadow: var(--shadow-sm); }
        .filter-presets { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-filter { padding: 10px 16px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-muted); font-size: 13px; font-weight: 700; cursor: pointer; transition: 0.2s; text-decoration: none; }
        .btn-filter:hover { border-color: var(--accent-blue); color: var(--accent-blue); }
        .btn-filter.active { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }
        .filter-custom { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .filter-custom input[type="date"] { padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-input); color: var(--text-dark); font-size: 13px; outline: none; transition: 0.3s; font-family: 'DM Sans', sans-serif; font-weight: 600; }
        .filter-custom input[type="date"]:focus { border-color: var(--accent-blue); }
        
        /* Desain Blok Kotak Statistik */
        .shift-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .shift-stat-box { background: var(--bg-panel); border-radius: 12px; padding: 25px 20px; text-align: center; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); transition: 0.3s; }
        .shift-stat-box:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .shift-stat-box .stat-val { font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; font-family: 'Plus Jakarta Sans', sans-serif; }
        .shift-stat-box .stat-lbl { font-size: 13px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }

        /* Badge Status Customer */
        .badge-status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; display: inline-block; }
        .bg-loyal { background: rgba(155,89,182,0.15); color: #8e44ad; border: 1px solid rgba(155,89,182,0.3); }
        .bg-regular { background: rgba(39,174,96,0.15); color: #27ae60; border: 1px solid rgba(39,174,96,0.3); }
        .bg-new { background: rgba(52,152,219,0.15); color: #2980b9; border: 1px solid rgba(52,152,219,0.3); }

        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: flex-start; }
            .filter-custom { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body>
    <div class="container-layout">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-profile">
                <img src="<?= $foto_profil ?>" alt="Profil" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-yellow); margin-bottom: 10px;">
                <div class="profile-info">
                    <h3><?= htmlspecialchars($nama_kasir) ?></h3>
                    <small><?= htmlspecialchars($nama_cabang) ?></small>
                </div>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard_kasir.php" class="menu-item"><span class="menu-abbr">DB</span><span class="menu-text">Dashboard</span></a>
                <a href="input_transaksi.php" class="menu-item"><span class="menu-abbr">IT</span><span class="menu-text">Input Transaksi</span></a>
                <a href="absensi_kasir.php" class="menu-item"><span class="menu-abbr">AT</span><span class="menu-text">Absensi Terapis</span></a>
                <a href="data_terapis_hadir.php" class="menu-item"><span class="menu-abbr">DT</span><span class="menu-text">Data Terapis</span></a>
                <a href="data_customer_kasir.php" class="menu-item active"><span class="menu-abbr">DC</span><span class="menu-text">Data Customer</span></a>
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
                    <h1>Data Customer</h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()" title="Ganti Tema">Mode Layar</button>
                </div>
            </div>

            <div class="filter-bar">
                <div class="filter-presets">
                    <a href="?filter=semua" class="btn-filter <?= $filter == 'semua' ? 'active' : '' ?>">Semua Waktu</a>
                    <a href="?filter=hari_ini" class="btn-filter <?= $filter == 'hari_ini' ? 'active' : '' ?>">Hari Ini</a>
                    <a href="?filter=minggu_ini" class="btn-filter <?= $filter == 'minggu_ini' ? 'active' : '' ?>">Minggu Ini</a>
                    <a href="?filter=bulan_ini" class="btn-filter <?= $filter == 'bulan_ini' ? 'active' : '' ?>">Bulan Ini</a>
                </div>
                <div class="filter-custom">
                    <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="filter" value="custom">
                        <span style="font-size:13px; font-weight:700; color:var(--text-muted);">RENTANG:</span>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($custom_start) ?>" required>
                        <span style="font-size:13px; font-weight:700; color:var(--text-muted);">-</span>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($custom_end) ?>" required>
                        <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Terapkan</button>
                    </form>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <span style="font-size: 14px; font-weight: 700; color: var(--text-dark);">Periode Ditampilkan:</span> 
                <span style="font-size: 14px; font-weight: 700; color: var(--accent-blue);"><?= htmlspecialchars($label_periode) ?></span>
            </div>

            <div class="shift-info-grid">
                <div class="shift-stat-box" style="border-top: 4px solid var(--accent-blue);">
                    <div class="stat-lbl">Customer Terdaftar</div>
                    <div class="stat-val"><?= count($customers) ?></div>
                </div>
                <div class="shift-stat-box" style="border-top: 4px solid var(--accent-yellow2);">
                    <div class="stat-lbl">Total Kunjungan</div>
                    <div class="stat-val"><?= array_sum(array_column($customers, 'total_kunjungan')) ?></div>
                </div>
                <div class="shift-stat-box" style="border-top: 4px solid var(--accent-green);">
                    <div class="stat-lbl">Total Pendapatan</div>
                    <div class="stat-val" style="color:var(--accent-green);">Rp <?= number_format(array_sum(array_column($customers, 'total_belanja')), 0, ',', '.') ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Daftar Customer Cabang <?= htmlspecialchars($nama_cabang) ?></span>
                    <small style="color:var(--text-muted); font-weight:normal;">Berdasarkan filter: <?= htmlspecialchars($label_periode) ?></small>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Nama Customer</th>
                                <th>No HP</th>
                                <th style="text-align:center;">Total Kunjungan</th>
                                <th style="text-align:right;">Total Belanja</th>
                                <th style="text-align:center;">Kunjungan Terakhir</th>
                                <th style="text-align:center;">Status</th>
                                <th style="text-align:center;" width="12%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($customers) > 0): ?>
                                <?php 
                                $no = 1;
                                foreach($customers as $c): 
                                    $badgeClass = '';
                                    $badgeText = '';
                                    if($c['total_kunjungan'] >= 10) {
                                        $badgeClass = 'bg-loyal';
                                        $badgeText = 'LOYAL';
                                    } elseif($c['total_kunjungan'] >= 5) {
                                        $badgeClass = 'bg-regular';
                                        $badgeText = 'REGULAR';
                                    } else {
                                        $badgeClass = 'bg-new';
                                        $badgeText = 'NEW';
                                    }
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong style="color:var(--text-dark); font-size:14px;"><?= htmlspecialchars($c['nama_pelanggan']) ?></strong></td>
                                    <td>
                                        <span style="color:var(--text-muted); font-weight:600;">
                                            <?= htmlspecialchars($c['no_hp_pelanggan'] ?: '-') ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <strong style="color: var(--accent-blue); font-size:16px;"><?= $c['total_kunjungan'] ?></strong>
                                        <span style="color:var(--text-muted); font-size:12px;">x</span>
                                    </td>
                                    <td style="text-align:right;">
                                        <strong style="color: var(--accent-green);">Rp <?= number_format($c['total_belanja'], 0, ',', '.') ?></strong>
                                    </td>
                                    <td style="text-align:center; color:var(--text-dark); font-weight:600;">
                                        <?= date('d/m/Y', strtotime($c['kunjungan_terakhir'])) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge-status <?= $badgeClass ?>"><?= $badgeText ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="detail_customer_kasir.php?nama=<?= urlencode($c['nama_pelanggan']) ?>&hp=<?= urlencode($c['no_hp_pelanggan']) ?>" 
                                           class="btn btn-primary btn-sm" style="display:inline-block; width:100%;">
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted); font-weight:600;">
                                        Belum ada data customer untuk periode ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Theme Toggle Logic
    function toggleTheme() {
        const html = document.documentElement;
        const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        html.setAttribute('data-theme', next);
        localStorage.setItem('bugar-theme', next);
    }
    (function() { 
        const saved = localStorage.getItem('bugar-theme'); 
        if (saved) document.documentElement.setAttribute('data-theme', saved); 
    })();

    // Sidebar Minimize Logic
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
    </script>
</body>
</html>