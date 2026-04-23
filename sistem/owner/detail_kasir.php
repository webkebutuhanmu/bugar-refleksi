<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

$id = $_GET['id'] ?? 0;
$pesan_sukses = "";
$pesan_error = "";

// ========================================================
// LOGIKA HAPUS RIWAYAT SHIFT (Bukan Hapus Akun)
// ========================================================
if (isset($_GET['hapus_shift'])) {
    $shift_id = $_GET['hapus_shift'];
    try {
        $pdo->beginTransaction();
        
        // 1. Hapus dari shift_logs terlebih dahulu (Foreign Key)
        $pdo->prepare("DELETE FROM shift_logs WHERE attendance_id = ?")->execute([$shift_id]);
        
        // 2. Hapus dari kasir_attendance
        $pdo->prepare("DELETE FROM kasir_attendance WHERE id = ?")->execute([$shift_id]);
        
        $pdo->commit();
        $pesan_sukses = "Riwayat shift berhasil dihapus!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $pesan_error = "Gagal menghapus shift: " . $e->getMessage();
    }
}

// ========================================================
// AMBIL DATA KASIR
// ========================================================
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'kasir'");
$stmt->execute([$id]);
$kasir = $stmt->fetch();

if (!$kasir) {
    header("Location: data_kasir.php");
    exit;
}

// ========================================================
// LOGIKA FILTER TANGGAL
// ========================================================
$tgl_awal = $_GET['tgl_awal'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';

$filter_trx = "";
$filter_att = "";
$params_trx = [$id];
$params_att = [$id];
$label_periode = "Seumur Hidup";

if ($tgl_awal != '' && $tgl_akhir != '') {
    $filter_trx = " AND DATE(created_at) BETWEEN ? AND ?";
    $filter_att = " AND tanggal BETWEEN ? AND ?";
    array_push($params_trx, $tgl_awal, $tgl_akhir);
    array_push($params_att, $tgl_awal, $tgl_akhir);
    $label_periode = date('d/m/Y', strtotime($tgl_awal)) . " - " . date('d/m/Y', strtotime($tgl_akhir));
}

$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulai = $setting['jam_mulai_hari'] ?? '08:00:00';

// Statistik Data
$stmtOmset = $pdo->prepare("SELECT SUM(total_bayar) FROM transactions WHERE kasir_id = ? $filter_trx");
$stmtOmset->execute($params_trx);
$totalOmset = $stmtOmset->fetchColumn() ?? 0;

$stmtTrx = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE kasir_id = ? $filter_trx");
$stmtTrx->execute($params_trx);
$totalTrx = $stmtTrx->fetchColumn();

$stmtHari = $pdo->prepare("SELECT COUNT(DISTINCT tanggal) FROM kasir_attendance WHERE kasir_id = ? $filter_att");
$stmtHari->execute($params_att);
$totalHariKerja = $stmtHari->fetchColumn();

$rataOmset = $totalHariKerja > 0 ? $totalOmset / $totalHariKerja : 0;

// Riwayat Kehadiran (Shift)
$sqlKehadiran = "SELECT 
                    ka.id as shift_id, ka.tanggal, ka.waktu_masuk, ka.waktu_keluar, b.nama_cabang,
                    (SELECT COUNT(*) FROM transactions t 
                     WHERE t.kasir_id = ka.kasir_id AND t.branch_id = ka.branch_id 
                     AND t.created_at >= ka.waktu_masuk 
                     AND t.created_at <= COALESCE(ka.waktu_keluar, NOW())) as total_trx,
                    (SELECT COALESCE(SUM(t.total_bayar),0) FROM transactions t 
                     WHERE t.kasir_id = ka.kasir_id AND t.branch_id = ka.branch_id 
                     AND t.created_at >= ka.waktu_masuk 
                     AND t.created_at <= COALESCE(ka.waktu_keluar, NOW())) as omset
                 FROM kasir_attendance ka
                 JOIN branches b ON ka.branch_id = b.id
                 WHERE ka.kasir_id = ? $filter_att
                 ORDER BY ka.waktu_masuk DESC LIMIT 30";
$stmtKehadiran = $pdo->prepare($sqlKehadiran);
$stmtKehadiran->execute($params_att);
$kehadiran = $stmtKehadiran->fetchAll();

// Transaksi Terakhir
$sqlRecentTrx = "SELECT t.*, u.nama_lengkap as nama_terapis, p.nama_paket, b.nama_cabang
                 FROM transactions t
                 LEFT JOIN users u ON t.terapis_id = u.id
                 LEFT JOIN packages p ON t.package_id = p.id
                 JOIN branches b ON t.branch_id = b.id
                 WHERE t.kasir_id = ? $filter_trx
                 ORDER BY t.created_at DESC LIMIT 20";
$stmtRecentTrx = $pdo->prepare($sqlRecentTrx);
$stmtRecentTrx->execute($params_trx);
$recentTrx = $stmtRecentTrx->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail <?= htmlspecialchars($kasir['nama_lengkap']) ?> - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .profile-header { background: var(--bg-panel); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 20px; margin-bottom: 20px; box-shadow: 0 4px 15px var(--shadow-color); }
        .profile-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; background: var(--bg-input); border: 3px solid var(--accent-yellow); }
        .filter-box { background: var(--bg-panel); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--border-color); display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="container-layout">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="../assets/logo_bugar.png" alt="Logo Bugar" style="width: 80px; height: auto; margin-bottom: 10px; border-radius: 8px;">
                <h2>BUGAR REFLEKSI</h2>
                <small>Owner Panel</small>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard_owner.php" class="menu-item">Dashboard</a>
                <a href="data_cabang.php" class="menu-item">Data Cabang</a>
                <a href="data_leader.php" class="menu-item">Data Leader</a>
                <a href="data_kasir.php" class="menu-item active">Data Kasir</a>
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
                    <h1>Detail Performa Kasir</h1>
                </div>
                <div class="topbar-right">
                    <a href="data_kasir.php" class="btn btn-secondary">Kembali</a>
                    <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
                </div>
            </div>

            <div class="profile-header">
                <?php $foto = !empty($kasir['foto_profil']) ? "../uploads/profil/".$kasir['foto_profil'] : "../assets/default_user.png"; ?>
                <img src="<?= $foto ?>" class="profile-avatar">
                <div>
                    <h2 style="margin:0; font-family:'Playfair Display', serif; color:var(--text-dark);"><?= htmlspecialchars($kasir['nama_lengkap']) ?></h2>
                    <p style="margin:5px 0; color:var(--text-muted); font-size:14px;">Username: <strong style="color:var(--text-dark);"><?= htmlspecialchars($kasir['username']) ?></strong></p>
                    <span class="badge" style="background: rgba(41, 128, 185, 0.15); color: #2980b9; border: 1px solid rgba(41, 128, 185, 0.3);">STATUS: KASIR</span>
                </div>
            </div>

            <div class="filter-box">
                <strong style="font-size:14px; color:var(--text-dark);">Filter Periode:</strong>
                <form method="GET" style="display:flex; gap:10px; align-items:center;">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="date" name="tgl_awal" class="form-control" style="width:auto;" value="<?= $tgl_awal ?>">
                    <span style="color:var(--text-muted);">s/d</span>
                    <input type="date" name="tgl_akhir" class="form-control" style="width:auto;" value="<?= $tgl_akhir ?>">
                    <button type="submit" class="btn btn-primary">Terapkan</button>
                    <?php if($tgl_awal): ?>
                        <a href="detail_kasir.php?id=<?= $id ?>" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card-grid">
                <div class="stat-card">
                    <h3>Total Omset</h3>
                    <div class="value">Rp <?= number_format($totalOmset, 0, ',', '.') ?></div>
                    <small><?= $label_periode ?></small>
                </div>
                <div class="stat-card">
                    <h3>Total Transaksi</h3>
                    <div class="value"><?= number_format($totalTrx) ?></div>
                    <small>Selesai</small>
                </div>
                <div class="stat-card">
                    <h3>Total Shift</h3>
                    <div class="value"><?= number_format($totalHariKerja) ?></div>
                    <small>Hari Kerja</small>
                </div>
                <div class="stat-card">
                    <h3>Rata-rata/Shift</h3>
                    <div class="value" style="color:var(--accent-red2);">Rp <?= number_format($rataOmset, 0, ',', '.') ?></div>
                    <small>Performa Omset</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Riwayat Shift & Omset</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Cabang</th>
                                <th>Jam Kerja</th>
                                <th>Transaksi</th>
                                <th>Omset</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($kehadiran) > 0): ?>
                                <?php foreach($kehadiran as $h): 
                                    $jamMasuk = date('H:i', strtotime($h['waktu_masuk']));
                                    $jamKeluar = $h['waktu_keluar'] ? date('H:i', strtotime($h['waktu_keluar'])) : 'Aktif';
                                ?>
                                <tr>
                                    <td><strong><?= date('d M Y', strtotime($h['tanggal'])) ?></strong></td>
                                    <td><?= htmlspecialchars($h['nama_cabang']) ?></td>
                                    <td><?= $jamMasuk ?> - <?= $jamKeluar ?></td>
                                    <td><?= $h['total_trx'] ?> trx</td>
                                    <td><strong>Rp <?= number_format($h['omset'], 0, ',', '.') ?></strong></td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" onclick="konfirmasiHapus(<?= $h['shift_id'] ?>, '<?= date('d/m/Y', strtotime($h['tanggal'])) ?>')">Hapus</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">Tidak ada riwayat shift dalam periode ini.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function konfirmasiHapus(shiftId, tgl) {
            Swal.fire({
                title: 'Hapus Riwayat Shift?',
                text: "Riwayat shift tanggal " + tgl + " akan dihapus. Data laporan cabang terkait juga akan hilang otomatis!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'detail_kasir.php?id=<?= $id ?>&hapus_shift=' + shiftId + '<?= $tgl_awal ? "&tgl_awal=$tgl_awal&tgl_akhir=$tgl_akhir" : "" ?>';
                }
            })
        }

        <?php if($pesan_sukses): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= $pesan_sukses ?>', timer: 2000, showConfirmButton: false });
        <?php endif; ?>

        <?php if($pesan_error): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= $pesan_error ?>' });
        <?php endif; ?>

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

        function toggleMobileMenu() { document.getElementById('sidebar').classList.toggle('active'); }
        function toggleSubmenu(el) { el.classList.toggle('active'); el.nextElementSibling.classList.toggle('open'); }
    </script>
</body>
</html>