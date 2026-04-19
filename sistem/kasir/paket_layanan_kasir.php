<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) { 
    header("Location: pilih_cabang.php"); exit; 
}

$branch_id = $_SESSION['active_branch'];
$kasir_id  = $_SESSION['user_id'];
$nama_kasir = $_SESSION['nama'];
$nama_cabang = $pdo->query("SELECT nama_cabang FROM branches WHERE id = $branch_id")->fetchColumn();

// Foto profil
$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$foto_profil = $stmtProfil->fetchColumn();
$foto_profil = (!empty($foto_profil) && file_exists("../uploads/profil/" . $foto_profil)) ? "../uploads/profil/" . $foto_profil : "../assets/default_user.png";

$paket = $pdo->query("SELECT * FROM packages ORDER BY harga ASC")->fetchAll();

// Ambil package_items untuk setiap paket
$packageItemsMap = [];
$stmtPI = $pdo->query("SELECT pi.*, i.nama_item, i.satuan FROM package_items pi JOIN items i ON pi.item_id = i.id ORDER BY pi.package_id, i.nama_item");
foreach ($stmtPI->fetchAll() as $pi) {
    $packageItemsMap[$pi['package_id']][] = $pi;
}

// Cek stok rendah untuk badge
$stmtLowStok = $pdo->prepare("SELECT COUNT(*) FROM branch_items WHERE branch_id = ? AND stok <= stok_minimum");
$stmtLowStok->execute([$branch_id]);
$lowStokCount = (int)$stmtLowStok->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paket Layanan - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <style>
        .packages-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; padding: 20px; }
        .package-card { background: var(--bg-panel); border-radius: 12px; padding: 25px; box-shadow: var(--shadow-sm); transition: all 0.3s ease; border: 1px solid var(--border-color); position: relative; overflow: hidden; }
        .package-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: var(--accent-blue); }
        .package-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--accent-blue); }
        .package-id { position: absolute; top: 15px; right: 15px; background: var(--bg-input); color: var(--text-muted); font-size: 11px; font-weight: bold; padding: 4px 10px; border-radius: 12px; border: 1px solid var(--border-color); }
        .package-name { font-size: 20px; font-weight: bold; color: var(--text-dark); margin-bottom: 12px; margin-top: 5px; line-height: 1.3; font-family: 'Plus Jakarta Sans', sans-serif; }
        .package-description { color: var(--text-muted); font-size: 13px; line-height: 1.6; margin-bottom: 15px; min-height: 60px; }
        
        .package-details { display: flex; gap: 15px; padding-top: 15px; border-top: 1px dashed var(--border-color); }
        .package-detail-item { flex: 1; text-align: center; }
        .detail-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: bold; margin-bottom: 5px; }
        .detail-value { font-size: 16px; font-weight: bold; color: var(--text-dark); }
        .detail-value.price { color: var(--accent-green); font-size: 18px; }
        
        .info-box { background: var(--bg-input); border-left: 5px solid var(--accent-blue); padding: 20px; border-radius: 12px; margin-bottom: 20px; border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }
        .info-box h3 { margin: 0 0 10px 0; font-size: 16px; color: var(--text-dark); }
        .info-box p { margin: 0; color: var(--text-muted); font-size: 14px; line-height: 1.6; }

        .pkg-items-list { margin-bottom: 15px; padding: 12px; background: var(--bg-input); border-radius: 8px; border-left: 3px solid var(--accent-yellow); border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }
        .pkg-items-list .pkg-item-tag { display: inline-block; background: var(--bg-panel); color: var(--text-dark); padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; margin: 3px 4px 3px 0; border: 1px solid var(--border-color); }
        .pkg-items-list .pkg-item-tag .qty { color: var(--accent-red); font-weight: bold; margin-left: 4px; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); font-weight: 600; }
        .stok-alert-badge { background: var(--accent-red); color: white; font-size: 10px; padding: 2px 7px; border-radius: 10px; margin-left: 5px; font-weight: bold; }
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
                <a href="absensi_kasir.php" class="menu-item"><span class="menu-abbr">AT</span><span class="menu-text">Absensi Terapis</span></a>
                <a href="data_terapis_hadir.php" class="menu-item"><span class="menu-abbr">DT</span><span class="menu-text">Data Terapis</span></a>
                <a href="data_customer_kasir.php" class="menu-item"><span class="menu-abbr">DC</span><span class="menu-text">Data Customer</span></a>
                <a href="paket_layanan_kasir.php" class="menu-item active"><span class="menu-abbr">PL</span><span class="menu-text">Paket Layanan</span></a>
                <a href="stok_barang.php" class="menu-item">
                    <span class="menu-abbr">SB</span>
                    <span class="menu-text">Stok Barang</span>
                    <?php if($lowStokCount > 0): ?><span class="stok-alert-badge"><?= $lowStokCount ?></span><?php endif; ?>
                </a>
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
                    <h1>Paket Layanan</h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
                </div>
            </div>

            <div class="info-box">
                <h3>Informasi Paket Layanan</h3>
                <p>
                    Berikut adalah daftar paket layanan yang tersedia di <strong><?= htmlspecialchars($nama_cabang) ?></strong>. 
                    Data ini sinkron dengan pengaturan dari Owner. Anda dapat menggunakan informasi ini untuk menawarkan paket kepada pelanggan saat melakukan transaksi.
                </p>
            </div>

            <div class="card">
                <div class="card-header">
                    Daftar Paket Layanan Tersedia
                    <small style="float: right; font-weight:normal; color:var(--text-muted);"><?= count($paket) ?> Paket</small>
                </div>
                
                <?php if(count($paket) > 0): ?>
                <div class="packages-grid">
                    <?php foreach($paket as $p): 
                        $pkgItems = $packageItemsMap[$p['id']] ?? [];
                    ?>
                    <div class="package-card">
                        <span class="package-id">ID: <?= $p['id'] ?></span>
                        <div class="package-name"><?= htmlspecialchars($p['nama_paket']) ?></div>
                        <div class="package-description">
                            <?= htmlspecialchars($p['deskripsi'] ?? 'Tidak ada deskripsi') ?>
                        </div>

                        <?php if (!empty($pkgItems)): ?>
                        <div class="pkg-items-list">
                            <div style="font-size:11px; color:var(--text-muted); margin-bottom:5px; font-weight:bold; text-transform:uppercase;">Barang yang dipakai:</div>
                            <?php foreach($pkgItems as $pi): ?>
                            <span class="pkg-item-tag">
                                <?= htmlspecialchars($pi['nama_item']) ?>
                                <span class="qty"><?= $pi['jumlah'] ?> <?= htmlspecialchars($pi['satuan']) ?></span>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="package-details">
                            <div class="package-detail-item">
                                <div class="detail-label">Durasi Waktu</div>
                                <div class="detail-value"><?= $p['durasi_menit'] ?> Mnt</div>
                            </div>
                            <div class="package-detail-item">
                                <div class="detail-label">Harga Paket</div>
                                <div class="detail-value price">Rp <?= number_format($p['harga'], 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <h3>Belum Ada Paket Layanan</h3>
                    <p>Belum ada paket layanan yang tersedia. Hubungi Owner untuk menambahkan paket.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>