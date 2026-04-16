<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: data_terapis.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'terapis'");
$stmt->execute([$id]);
$terapis = $stmt->fetch();
if (!$terapis) die("Terapis tidak ditemukan.");

$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulai = $setting['jam_mulai_hari'] ?? '08:00:00';

$expBusinessDate = "IF(TIME(t.created_at) < '$jamMulai', DATE_SUB(DATE(t.created_at), INTERVAL 1 DAY), DATE(t.created_at))";

$sqlPending = "SELECT 
                YEARWEEK($expBusinessDate, 1) as periode_kode,
                MIN($expBusinessDate) as tgl_mulai,
                MAX($expBusinessDate) as tgl_akhir,
                COUNT(t.id) as total_pasien,
                SUM(t.omset_terapis) as total_komisi,
                GROUP_CONCAT(DISTINCT b.nama_cabang SEPARATOR ', ') as lokasi_kerja
              FROM transactions t
              JOIN branches b ON t.branch_id = b.id
              WHERE t.terapis_id = ? AND t.commission_status = 'pending'
              GROUP BY YEARWEEK($expBusinessDate, 1)
              ORDER BY tgl_akhir DESC"; 

$stmtP = $pdo->prepare($sqlPending);
$stmtP->execute([$id]);
$pendingWeeks = $stmtP->fetchAll();

$sqlHistory = "SELECT 
                YEARWEEK($expBusinessDate, 1) as periode_kode,
                MIN($expBusinessDate) as tgl_mulai,
                MAX($expBusinessDate) as tgl_akhir,
                MAX(t.commission_paid_at) as tgl_bayar,
                COUNT(t.id) as total_pasien,
                SUM(t.omset_terapis) as total_komisi,
                GROUP_CONCAT(DISTINCT b.nama_cabang SEPARATOR ', ') as lokasi_kerja
              FROM transactions t
              JOIN branches b ON t.branch_id = b.id
              WHERE t.terapis_id = ? AND t.commission_status = 'paid'
              GROUP BY YEARWEEK($expBusinessDate, 1)
              ORDER BY tgl_bayar DESC LIMIT 20";

$stmtH = $pdo->prepare($sqlHistory);
$stmtH->execute([$id]);
$historyWeeks = $stmtH->fetchAll();

$grandTotalPending = 0;
foreach($pendingWeeks as $p) $grandTotalPending += $p['total_komisi'];
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Terapis - Owner</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <style>
        .profile-box { background: var(--bg-panel); color: var(--text-dark); padding: 25px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--border-color); box-shadow: 0 4px 15px var(--shadow-color); border-left: 5px solid var(--accent-yellow); flex-wrap: wrap; gap: 15px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
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
                <a href="data_terapis.php" class="menu-item active">Kembali ke Data Terapis</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div style="display:flex; align-items:center; gap:15px;">
                    <button class="mobile-toggle" onclick="toggleMobileMenu()">☰</button>
                    <h1>Detail Terapis</h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <div class="profile-box">
                <div>
                    <h2 style="margin:0; font-family:'Playfair Display', serif;"><?= htmlspecialchars($terapis['nama_lengkap']) ?></h2>
                    <p style="color:var(--text-muted); margin:5px 0 0; font-size:14px;">Username: <strong><?= htmlspecialchars($terapis['username']) ?></strong></p>
                </div>
                <div style="text-align: right; background: var(--bg-input); padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <div style="font-size: 12px; color: var(--text-muted); font-weight: bold; text-transform: uppercase;">Tagihan Belum Dibayar</div>
                    <div style="font-size: 24px; font-weight: bold; color: var(--accent-red2);">Rp <?= number_format($grandTotalPending, 0, ',', '.') ?></div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">Tagihan Mingguan (Belum Dibayar Admin)</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr style="background: rgba(243, 156, 18, 0.05);">
                                <th>Periode Minggu</th>
                                <th>Lokasi Kerja</th>
                                <th class="text-center">Pasien</th>
                                <th class="text-right">Komisi</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($pendingWeeks) > 0): ?>
                                <?php foreach($pendingWeeks as $w): ?>
                                <tr>
                                    <td>
                                        <strong><?= date('d M', strtotime($w['tgl_mulai'])) ?> - <?= date('d M Y', strtotime($w['tgl_akhir'])) ?></strong>
                                    </td>
                                    <td><small style="color:var(--text-muted); font-weight:bold;"><?= htmlspecialchars($w['lokasi_kerja']) ?></small></td>
                                    <td class="text-center"><?= $w['total_pasien'] ?></td>
                                    <td class="text-right">
                                        <strong style="color: var(--text-dark);">Rp <?= number_format($w['total_komisi'], 0, ',', '.') ?></strong>
                                    </td>
                                    <td class="text-center"><span class="badge badge-warning">Belum Cair</span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">Tidak ada tagihan pending.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Riwayat Komisi (Sudah Dibayar)</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr style="background: rgba(39, 174, 96, 0.05);">
                                <th>Periode Minggu</th>
                                <th>Lokasi Kerja</th>
                                <th class="text-center">Pasien</th>
                                <th class="text-right">Komisi Diterima</th>
                                <th class="text-center">Tanggal Cair</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($historyWeeks) > 0): ?>
                                <?php foreach($historyWeeks as $h): ?>
                                <tr>
                                    <td>
                                        <strong><?= date('d M', strtotime($h['tgl_mulai'])) ?> - <?= date('d M Y', strtotime($h['tgl_akhir'])) ?></strong>
                                    </td>
                                    <td><small style="color:var(--text-muted); font-weight:bold;"><?= htmlspecialchars($h['lokasi_kerja']) ?></small></td>
                                    <td class="text-center"><?= $h['total_pasien'] ?></td>
                                    <td class="text-right">
                                        <strong style="color: #27ae60;">Rp <?= number_format($h['total_komisi'], 0, ',', '.') ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <div style="font-size: 12px; font-weight: bold; color: var(--text-muted);">
                                            <?= date('d/m/Y H:i', strtotime($h['tgl_bayar'])) ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">Belum ada riwayat pembayaran.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
    </script>
</body>
</html>