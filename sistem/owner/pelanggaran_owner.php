<?php
/**
 * pelanggaran_owner.php
 * Owner: Melihat seluruh pelanggaran terapis di semua cabang
 * Per tabel per cabang + info leader. Owner hanya memantau, tidak bisa bertindak.
 */
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'owner') {
    header("Location: ../auth/login_system.php"); exit;
}

// ── Tanggal bisnis ──────────────────────────────────────────────────────────
$settings  = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
$jamMulai  = $settings['jam_mulai_hari'] ?? '08:00:00';
$tglBisnis = (date('H:i:s') < $jamMulai) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

// Filter periode
$tgl_awal  = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$filterKat = isset($_GET['kategori']) ? $_GET['kategori'] : '';

// ── Labels ──────────────────────────────────────────────────────────────────
$katLabel = [
    'keterlambatan' => ['label'=>'Keterlambatan','color'=>'#e67e22','bg'=>'#fef3e7'],
    'tolak_pasien'  => ['label'=>'Tolak Pasien','color'=>'#d35400','bg'=>'#fdebd0'],
    'alpha'         => ['label'=>'Alpha (Tidak Hadir)','color'=>'#c0392b','bg'=>'#fadbd8'],
    'mangkir'       => ['label'=>'Mangkir/Alpha','color'=>'#e74c3c','bg'=>'#fde8e8'],
    'perilaku'      => ['label'=>'Perilaku','color'=>'#9b59b6','bg'=>'#f4ecf7'],
    'atribut'       => ['label'=>'Atribut/Seragam','color'=>'#3498db','bg'=>'#eaf3fc'],
    'lainnya'       => ['label'=>'Lainnya','color'=>'#7f8c8d','bg'=>'#f1f2f6'],
];
$statusLabel = [
    'aktif'      => ['label'=>'Aktif','color'=>'#e74c3c','bg'=>'rgba(231,76,60,0.15)'],
    'selesai'    => ['label'=>'Selesai','color'=>'#27ae60','bg'=>'rgba(39,174,96,0.15)'],
    'dibatalkan' => ['label'=>'Dibatalkan','color'=>'#95a5a6','bg'=>'rgba(149,165,166,0.15)'],
];

// ── Ambil semua cabang ──────────────────────────────────────────────────────
$branches = $pdo->query("SELECT id, nama_cabang FROM branches ORDER BY nama_cabang ASC")->fetchAll(PDO::FETCH_ASSOC);

// ── Data per cabang ─────────────────────────────────────────────────────────
$dataCabang = [];
$sumTotal = 0; $sumAktif = 0; $sumSelesai = 0;
foreach ($branches as $br) {
    $bid = $br['id'];

    // Leader
    $stL = $pdo->prepare("SELECT nama_lengkap FROM users WHERE role='leader' AND (home_branch_id = ? OR branch_id = ?) LIMIT 1");
    $stL->execute([$bid, $bid]);
    $leaderName = $stL->fetchColumn() ?: 'Belum ditentukan';

    // Pelanggaran
    $sql = "SELECT p.*, u.nama_lengkap, u.foto_profil, cb.nama_lengkap as created_by_name
            FROM pelanggaran p
            JOIN users u ON p.terapis_id = u.id
            LEFT JOIN users cb ON p.created_by = cb.id
            WHERE p.branch_id = ? AND p.tanggal BETWEEN ? AND ?";
    $params = [$bid, $tgl_awal, $tgl_akhir];
    if ($filterKat) {
        $sql .= " AND p.kategori = ?";
        $params[] = $filterKat;
    }
    $sql .= " ORDER BY p.tanggal DESC, p.created_at DESC";
    $stP = $pdo->prepare($sql);
    $stP->execute($params);
    $pelanggaranList = $stP->fetchAll(PDO::FETCH_ASSOC);

    $aktifC = 0; $selesaiC = 0;
    foreach ($pelanggaranList as $p) {
        if ($p['status'] === 'aktif') $aktifC++;
        elseif ($p['status'] === 'selesai') $selesaiC++;
    }
    $sumTotal += count($pelanggaranList);
    $sumAktif += $aktifC;
    $sumSelesai += $selesaiC;

    $dataCabang[] = [
        'branch' => $br, 'leader' => $leaderName,
        'pelanggaranList' => $pelanggaranList,
        'stats' => ['total'=>count($pelanggaranList), 'aktif'=>$aktifC, 'selesai'=>$selesaiC]
    ];
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelanggaran Terapis - Owner</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <style>
        .filter-bar { background: var(--bg-panel); border-radius: 12px; padding: 15px 20px; border: 1px solid var(--border-color); margin-bottom: 20px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .filter-bar label { font-size: 13px; font-weight: 700; color: var(--text-dark); margin: 0; }
        .avatar-sm { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); vertical-align: middle; margin-right: 8px; }
        .detail-box { font-size: 13px; color: var(--text-dark); max-width: 280px; word-break: break-word; }
        .detail-box .desc { color: var(--text-muted); font-style: italic; margin-top: 3px; font-size: 12px; }
        .detail-box .note { color: var(--accent-red); margin-top: 3px; font-size: 12px; font-weight: 600; }
        .kat-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; border: 1px solid rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container-layout">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>BUGAR REFLEKSI</h2>
                <small>Owner Panel</small>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard_owner.php" class="menu-item">Dashboard</a>
                <a href="data_cabang.php" class="menu-item">Data Cabang</a>
                <a href="data_leader.php" class="menu-item">Data Leader</a>
                <a href="data_kasir.php" class="menu-item">Data Kasir</a>
                <a href="data_terapis.php" class="menu-item">Data Terapis</a>
                <a href="data_customer.php" class="menu-item">Data Customer</a>
                <a href="data_absensi_owner.php" class="menu-item">Data Absensi</a>
                <a href="pelanggaran_owner.php" class="menu-item active">Pelanggaran</a>
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
                    <h1>Data Pelanggaran Terapis</h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <div class="filter-bar">
                <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <label>Dari:</label>
                    <input type="date" name="tgl_awal" value="<?= htmlspecialchars($tgl_awal) ?>" class="form-control" style="width: auto; padding: 8px;">
                    <label>Sampai:</label>
                    <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($tgl_akhir) ?>" class="form-control" style="width: auto; padding: 8px;">
                    <label>Kategori:</label>
                    <select name="kategori" class="form-control" style="width: auto; padding: 8px;">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($katLabel as $k => $kv): ?>
                            <option value="<?= $k ?>" <?= $filterKat===$k ? 'selected' : '' ?>><?= $kv['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter Data</button>
                </form>
                <a href="pelanggaran_owner.php" style="font-size:13px; color:var(--text-muted); font-weight:bold; margin-left: 10px;">Reset Filter</a>
            </div>

            <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card">
                    <h3>Total Pelanggaran</h3>
                    <div class="value"><?= $sumTotal ?></div>
                </div>
                <div class="stat-card">
                    <h3>Status Aktif</h3>
                    <div class="value" style="color: var(--accent-red);"><?= $sumAktif ?></div>
                </div>
                <div class="stat-card">
                    <h3>Status Selesai</h3>
                    <div class="value" style="color: #27ae60;"><?= $sumSelesai ?></div>
                </div>
                <div class="stat-card">
                    <h3>Dibatalkan</h3>
                    <div class="value" style="color: var(--text-muted);"><?= $sumTotal - $sumAktif - $sumSelesai ?></div>
                </div>
            </div>

            <?php foreach ($dataCabang as $dc):
                $br=$dc['branch']; $pList=$dc['pelanggaranList']; $st=$dc['stats'];
            ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <div style="font-size:18px; font-weight:bold; color:var(--text-dark); font-family: 'Playfair Display', serif;"><?= htmlspecialchars($br['nama_cabang']) ?></div>
                        <div style="font-size:12px; color:var(--text-muted); font-family: 'DM Sans', sans-serif;">Leader: <strong><?= htmlspecialchars($dc['leader']) ?></strong> | <?= $st['total'] ?> Pelanggaran</div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <?php if ($st['aktif'] > 0): ?><span class="badge badge-danger"><?= $st['aktif'] ?> Aktif</span><?php endif; ?>
                        <?php if ($st['selesai'] > 0): ?><span class="badge badge-success"><?= $st['selesai'] ?> Selesai</span><?php endif; ?>
                    </div>
                </div>
                <?php if (empty($pList)): ?>
                    <p style="text-align:center; padding:20px; color:var(--text-muted);">Tidak ada pelanggaran di periode ini</p>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead><tr><th width="5%">No</th><th>Terapis</th><th>Kategori</th><th>Judul & Detail</th><th>Tanggal</th><th>Status</th><th>Dicatat Oleh</th></tr></thead>
                        <tbody>
                            <?php $no=1; foreach ($pList as $p):
                                $kat = $katLabel[$p['kategori']] ?? $katLabel['lainnya'];
                                $sts = $statusLabel[$p['status']] ?? $statusLabel['aktif'];
                                $foto = (!empty($p['foto_profil'])&&file_exists("../uploads/profil/".$p['foto_profil'])) ? "../uploads/profil/".$p['foto_profil'] : "../assets/default_user.png";
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <img src="<?= $foto ?>" class="avatar-sm" onerror="this.src='../assets/default_user.png'">
                                    <strong><?= htmlspecialchars($p['nama_lengkap']) ?></strong>
                                </td>
                                <td><span class="kat-badge" style="background:<?= $kat['bg'] ?>; color:<?= $kat['color'] ?>; border-color:<?= $kat['color'] ?>;"><?= $kat['label'] ?></span></td>
                                <td>
                                    <div class="detail-box">
                                        <strong><?= htmlspecialchars($p['judul']) ?></strong>
                                        <?php if ($p['deskripsi']): ?><div class="desc"><?= htmlspecialchars(mb_substr($p['deskripsi'], 0, 120)) ?><?= mb_strlen($p['deskripsi'])>120 ? '...' : '' ?></div><?php endif; ?>
                                        <?php if ($p['catatan_leader']): ?><div class="note">Catatan Leader: <?= htmlspecialchars(mb_substr($p['catatan_leader'], 0, 80)) ?></div><?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($p['tanggal'])) ?>
                                    <?php if ($p['waktu_kejadian']): ?><br><small style="color:var(--text-muted); font-weight:bold;"><?= date('H:i', strtotime($p['waktu_kejadian'])) ?></small><?php endif; ?>
                                </td>
                                <td><span class="badge" style="background:<?= $sts['bg'] ?>; color:<?= $sts['color'] ?>; border: 1px solid <?= $sts['color'] ?>;"><?= $sts['label'] ?></span></td>
                                <td><small style="color:var(--text-muted); font-weight:bold;"><?= htmlspecialchars($p['created_by_name'] ?? 'System') ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

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
    </script>
</body>
</html>