<?php
/**
 * data_absensi_owner.php
 * Owner: Melihat data absensi seluruh terapis di semua cabang
 * Per tabel per cabang + info leader + status: Hadir, Belum Absen, Izin, Sakit
 * Reset per hari. Owner hanya memantau, tidak bisa bertindak.
 */
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'owner') {
    header("Location: ../auth/login_system.php"); exit;
}

// ── Tanggal bisnis ──────────────────────────────────────────────────────────
$settings   = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
$jamMulai   = $settings['jam_mulai_hari'] ?? '08:00:00';
$tglBisnis  = (date('H:i:s') < $jamMulai) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$tglFilter  = isset($_GET['tanggal']) ? $_GET['tanggal'] : $tglBisnis;

// ── Ambil semua cabang ──────────────────────────────────────────────────────
$branches = $pdo->query("SELECT id, nama_cabang FROM branches ORDER BY nama_cabang ASC")->fetchAll(PDO::FETCH_ASSOC);

// ── Data per cabang ─────────────────────────────────────────────────────────
$dataCabang = [];
foreach ($branches as $br) {
    $bid = $br['id'];

    // Leader cabang
    $stL = $pdo->prepare("SELECT nama_lengkap FROM users WHERE role='leader' AND (home_branch_id = ? OR branch_id = ?) LIMIT 1");
    $stL->execute([$bid, $bid]);
    $leaderName = $stL->fetchColumn() ?: 'Belum ditentukan';

    // Terapis cabang
    $stT = $pdo->prepare("SELECT u.id, u.nama_lengkap, u.foto_profil FROM users u WHERE u.home_branch_id = ? AND u.role = 'terapis' ORDER BY u.nama_lengkap ASC");
    $stT->execute([$bid]);
    $terapisList = $stT->fetchAll(PDO::FETCH_ASSOC);

    // Absensi
    $stA = $pdo->prepare("SELECT ta.terapis_id, ta.giliran, ta.waktu_absen, ta.shift_type, ta.status_kehadiran, ta.metode_absen FROM terapis_attendance ta WHERE ta.branch_id = ? AND ta.tanggal = ?");
    $stA->execute([$bid, $tglFilter]);
    $absenMap = [];
    foreach ($stA->fetchAll(PDO::FETCH_ASSOC) as $a) { $absenMap[$a['terapis_id']] = $a; }

    // Izin/Sakit
    $stI = $pdo->prepare("SELECT terapis_id, jenis, status FROM terapis_izin WHERE branch_id = ? AND tanggal = ? AND status IN ('disetujui','pending') ORDER BY id DESC");
    $stI->execute([$bid, $tglFilter]);
    $izinMap = [];
    foreach ($stI->fetchAll(PDO::FETCH_ASSOC) as $iz) {
        if (!isset($izinMap[$iz['terapis_id']])) $izinMap[$iz['terapis_id']] = $iz;
    }

    $totalT = count($terapisList); $hadir = count($absenMap);
    $izinC = 0; $sakitC = 0; $belumC = 0;
    foreach ($terapisList as $t) {
        if (isset($absenMap[$t['id']])) continue;
        if (isset($izinMap[$t['id']])) {
            if ($izinMap[$t['id']]['jenis'] === 'sakit') $sakitC++; else $izinC++;
        } else { $belumC++; }
    }

    $dataCabang[] = [
        'branch' => $br, 'leader' => $leaderName, 'terapisList' => $terapisList,
        'absenMap' => $absenMap, 'izinMap' => $izinMap,
        'stats' => ['total'=>$totalT, 'hadir'=>$hadir, 'izin'=>$izinC, 'sakit'=>$sakitC, 'belum'=>$belumC]
    ];
}

// Summary
$sumTotal=$sumHadir=$sumIzin=$sumSakit=$sumBelum=0;
foreach ($dataCabang as $dc) {
    $sumTotal+=$dc['stats']['total']; $sumHadir+=$dc['stats']['hadir'];
    $sumIzin+=$dc['stats']['izin']; $sumSakit+=$dc['stats']['sakit']; $sumBelum+=$dc['stats']['belum'];
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Absensi - Owner</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <style>
        .filter-bar { background: var(--bg-panel); border-radius: 12px; padding: 15px 20px; border: 1px solid var(--border-color); margin-bottom: 20px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .filter-bar label { font-size: 13px; font-weight: 700; color: var(--text-dark); margin: 0; }
        .avatar-sm { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); vertical-align: middle; margin-right: 8px; }
        .giliran-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; font-weight: bold; font-size: 13px; color: var(--btn-primary-txt); background: var(--btn-primary); }
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
                <a href="data_cabang.php" class="menu-item">Data Cabang</a>
                <a href="data_leader.php" class="menu-item">Data Leader</a>
                <a href="data_kasir.php" class="menu-item">Data Kasir</a>
                <a href="data_terapis.php" class="menu-item">Data Terapis</a>
                <a href="data_customer.php" class="menu-item">Data Customer</a>
                <a href="data_absensi_owner.php" class="menu-item active">Data Absensi</a>
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
                    <h1>Data Absensi Terapis</h1>
                </div>
                <div class="topbar-right">
                    <span style="color: var(--text-muted); font-size:14px;">Tanggal: <strong style="color:var(--text-dark);"><?= date('d M Y', strtotime($tglFilter)) ?></strong></span>
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <div class="filter-bar">
                <label>Filter Tanggal:</label>
                <form method="GET" style="display:flex; gap:10px; align-items:center;">
                    <input type="date" name="tanggal" value="<?= htmlspecialchars($tglFilter) ?>" class="form-control" style="width: auto; padding: 8px;">
                    <button type="submit" class="btn btn-primary">Lihat Data</button>
                </form>
                <?php if ($tglFilter !== $tglBisnis): ?>
                    <a href="data_absensi_owner.php" style="font-size:13px; color:var(--accent-red); font-weight:bold;">Reset ke Hari Ini</a>
                <?php endif; ?>
            </div>

            <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <div class="stat-card">
                    <h3>Total Terapis</h3>
                    <div class="value"><?= $sumTotal ?></div>
                </div>
                <div class="stat-card">
                    <h3>Hadir</h3>
                    <div class="value" style="color: #27ae60;"><?= $sumHadir ?></div>
                </div>
                <div class="stat-card">
                    <h3>Izin</h3>
                    <div class="value" style="color: #f39c12;"><?= $sumIzin ?></div>
                </div>
                <div class="stat-card">
                    <h3>Sakit</h3>
                    <div class="value" style="color: #c0392b;"><?= $sumSakit ?></div>
                </div>
                <div class="stat-card">
                    <h3>Belum Absen</h3>
                    <div class="value" style="color: var(--text-muted);"><?= $sumBelum ?></div>
                </div>
            </div>

            <?php foreach ($dataCabang as $dc):
                $br=$dc['branch']; $st=$dc['stats']; $tList=$dc['terapisList'];
                $aMap=$dc['absenMap']; $iMap=$dc['izinMap'];
            ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <div style="font-size:18px; font-weight:bold; color:var(--text-dark); font-family: 'Playfair Display', serif;"><?= htmlspecialchars($br['nama_cabang']) ?></div>
                        <div style="font-size:12px; color:var(--text-muted); font-family: 'DM Sans', sans-serif;">Leader: <strong><?= htmlspecialchars($dc['leader']) ?></strong> | <?= $st['total'] ?> Terapis</div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <span class="badge badge-success"><?= $st['hadir'] ?> Hadir</span>
                        <?php if ($st['izin'] > 0): ?><span class="badge badge-warning"><?= $st['izin'] ?> Izin</span><?php endif; ?>
                        <?php if ($st['sakit'] > 0): ?><span class="badge badge-danger"><?= $st['sakit'] ?> Sakit</span><?php endif; ?>
                        <?php if ($st['belum'] > 0): ?><span class="badge" style="background:var(--bg-input); color:var(--text-muted);"><?= $st['belum'] ?> Belum</span><?php endif; ?>
                    </div>
                </div>
                <?php if (empty($tList)): ?>
                    <p style="text-align:center; padding:20px; color:var(--text-muted);">Belum ada terapis di cabang ini</p>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead><tr><th width="5%">No</th><th>Nama Terapis</th><th>Status</th><th>Giliran</th><th>Waktu Absen</th><th>Shift</th><th>Keterangan</th></tr></thead>
                        <tbody>
                            <?php $no=1; foreach ($tList as $t):
                                $tid=$t['id'];
                                $foto=(!empty($t['foto_profil'])&&file_exists("../uploads/profil/".$t['foto_profil'])) ? "../uploads/profil/".$t['foto_profil'] : "../assets/default_user.png";
                                $hasAbsen=isset($aMap[$tid]); $hasIzin=isset($iMap[$tid]);
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><img src="<?= $foto ?>" class="avatar-sm" onerror="this.src='../assets/default_user.png'"><?= htmlspecialchars($t['nama_lengkap']) ?></td>
                                <td>
                                    <?php if ($hasAbsen): ?>
                                        <span class="badge badge-success">Hadir</span>
                                        <?php if ($aMap[$tid]['status_kehadiran']==='terlambat'): ?><span class="badge badge-danger" style="margin-left:4px;">Terlambat</span><?php endif; ?>
                                    <?php elseif ($hasIzin): ?>
                                        <?php if ($iMap[$tid]['jenis']==='sakit'): ?><span class="badge badge-danger">Sakit</span>
                                        <?php else: ?><span class="badge badge-warning">Izin</span><?php endif; ?>
                                        <?php if ($iMap[$tid]['status']==='pending'): ?><span style="font-size:10px;color:#e67e22;margin-left:4px;">Pending</span>
                                        <?php else: ?><span style="font-size:10px;color:#27ae60;margin-left:4px;">Disetujui</span><?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--bg-input); color:var(--text-muted);">Belum Absen</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php if ($hasAbsen): ?><span class="giliran-badge"><?= $aMap[$tid]['giliran'] ?></span><?php else: ?><span style="color:var(--text-muted);">-</span><?php endif; ?></td>
                                <td><?php if ($hasAbsen && $aMap[$tid]['waktu_absen']): ?><?= date('H:i:s', strtotime($aMap[$tid]['waktu_absen'])) ?><?php else: ?><span style="color:var(--text-muted);">-</span><?php endif; ?></td>
                                <td>
                                    <?php if ($hasAbsen && $aMap[$tid]['shift_type']): ?>
                                        <?php if ($aMap[$tid]['shift_type']==='pagi'): ?><span class="badge" style="background:#fff8e1; color:#f57f17; border:1px solid #f57f17;">Pagi</span>
                                        <?php else: ?><span class="badge" style="background:#ede7f6; color:#6a1b9a; border:1px solid #6a1b9a;">Malam</span><?php endif; ?>
                                    <?php else: ?><span style="color:var(--text-muted);">-</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hasAbsen && $aMap[$tid]['status_kehadiran']==='terlambat'): ?><span style="font-size:11px; color:var(--accent-red); font-style:italic;">Terlambat</span>
                                    <?php elseif ($hasAbsen): ?><span style="font-size:11px; color:#27ae60;">Tepat Waktu</span>
                                    <?php elseif ($hasIzin): ?><span style="font-size:11px; color:var(--text-muted); font-style:italic;"><?= ucfirst($iMap[$tid]['jenis']) ?></span>
                                    <?php else: ?><span style="color:var(--text-muted);">-</span><?php endif; ?>
                                </td>
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