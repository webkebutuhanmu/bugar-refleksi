<?php
// File: leader/stok_cabang_leader.php
// Halaman view-only stok barang cabang untuk Leader
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'leader') {
    header("Location: ../auth/login.php");
    exit;
}

$userId   = $_SESSION['user_id'];
$branchId = $_SESSION['user_branch_id'];

// Ambil info user & cabang
$stmtUser = $pdo->prepare("SELECT u.*, b.nama_cabang FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
$stmtUser->execute([$userId]);
$userMe    = $stmtUser->fetch();
$fotoPath  = !empty($userMe['foto_profil']) ? "../uploads/profil/" . $userMe['foto_profil'] : "../assets/img/default-avatar.png";
$namaCabang = $userMe['nama_cabang'];

// Query stok barang cabang
$stmtStok = $pdo->prepare("
    SELECT bi.*, i.nama_item, i.satuan
    FROM branch_items bi
    JOIN items i ON bi.item_id = i.id
    WHERE bi.branch_id = ?
    ORDER BY bi.stok ASC, i.nama_item ASC
");
$stmtStok->execute([$branchId]);
$stokBarang = $stmtStok->fetchAll();

// Hitung statistik
$totalItems  = count($stokBarang);
$stokHabis   = 0;
$stokRendah  = 0;
$stokAman    = 0;
foreach ($stokBarang as $sb) {
    if ($sb['stok'] <= 0) $stokHabis++;
    elseif ($sb['stok'] <= $sb['stok_minimum']) $stokRendah++;
    else $stokAman++;
}

// Log penggunaan terbaru
$stmtLog = $pdo->prepare("
    SELECT iul.*, i.nama_item, i.satuan, u.nama_lengkap AS oleh
    FROM item_usage_log iul
    JOIN items i ON iul.item_id = i.id
    LEFT JOIN users u ON iul.created_by = u.id
    WHERE iul.branch_id = ?
    ORDER BY iul.created_at DESC
    LIMIT 15
");
$stmtLog->execute([$branchId]);
$recentLogs = $stmtLog->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Barang - Leader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        :root { --sidebar-w: 250px; --primary: #2c3e50; --accent: #3498db; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; }
        .sidebar { width: var(--sidebar-w); background: linear-gradient(180deg, var(--primary) 0%, #34495e 100%); height: 100vh; position: fixed; color: white; overflow-y: auto; }
        .sidebar-brand { padding: 25px 20px; text-align: center; border-bottom: 1px solid #34495e; font-weight: bold; font-size: 20px; }
        .profile-section { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .img-nav { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent); margin-bottom: 10px; }
        .nav-link { display: block; padding: 12px 20px; color: #bdc3c7; text-decoration: none; border-left: 4px solid transparent; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: #34495e; color: white; border-left: 4px solid var(--accent); }
        .main-content { margin-left: var(--sidebar-w); padding: 30px; }
        .card-custom { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
        .stat-value { font-size: 32px; font-weight: bold; margin: 5px 0; }
        .stat-label { font-size: 13px; color: #7f8c8d; }
        .stok-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .stok-badge.habis  { background: #fdedec; color: #e74c3c; }
        .stok-badge.rendah { background: #fef9e7; color: #e67e22; }
        .stok-badge.aman   { background: #e8f8f5; color: #27ae60; }
        .view-only-badge { background: #e8f4fd; color: #2980b9; border: 1px solid #aed6f1; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .table-stok th { background: #f8f9fa; font-size: 13px; color: #555; font-weight: 600; }
        .table-stok td { vertical-align: middle; font-size: 14px; }
        .row-habis  { background: #fff5f5 !important; }
        .row-rendah { background: #fffbf0 !important; }
        .search-box input { border-radius: 20px; padding: 8px 16px; border: 1px solid #ddd; width: 100%; max-width: 320px; font-size: 14px; }
        .search-box input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(52,152,219,0.15); }
        .log-item { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f1f1; font-size: 13px; }
        .log-item:last-child { border-bottom: none; }
        .log-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .log-icon.pakai  { background: #fdedec; }
        .log-icon.tambah { background: #e8f8f5; }
        .log-icon.koreksi { background: #fef9e7; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand"><i class="bi bi-building"></i> LEADER PANEL</div>
        <div class="profile-section">
            <img src="<?= $fotoPath ?>" class="img-nav" alt="Profile">
            <div style="font-weight:bold; margin-top:10px;"><?= htmlspecialchars($userMe['nama_lengkap']) ?></div>
            <small style="color: #95a5a6;"><?= htmlspecialchars($namaCabang) ?></small>
        </div>
        <div class="nav-menu">
            <a href="dashboard_leader.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="data_terapis_leader.php" class="nav-link"><i class="bi bi-people"></i> Data Terapis</a>
            <a href="monitoring_terapis.php" class="nav-link"><i class="bi bi-eye"></i> Monitoring</a>
            <a href="stok_cabang_leader.php" class="nav-link active"><i class="bi bi-box-seam"></i> Stok Barang</a>
            <a href="pelanggaran_terapis.php" class="nav-link"><i class="bi bi-exclamation-triangle"></i> Pelanggaran</a>
            <a href="profil_leader.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
        </div>
        <div style="padding: 20px; margin-top: auto;">
            <a href="../auth/logout_system.php" class="btn btn-danger w-100">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-box-seam text-primary"></i> Stok Barang Cabang</h2>
                <p class="text-muted mb-0"><?= htmlspecialchars($namaCabang) ?> &mdash; Data stok real-time</p>
            </div>
            <span class="view-only-badge"><i class="bi bi-eye"></i> View Only</span>
        </div>

        <!-- STATISTIK -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card" style="border-top: 3px solid #3498db;">
                    <div class="stat-label">Total Jenis Barang</div>
                    <div class="stat-value text-primary"><?= $totalItems ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card" style="border-top: 3px solid #27ae60;">
                    <div class="stat-label">Stok Aman</div>
                    <div class="stat-value text-success"><?= $stokAman ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card" style="border-top: 3px solid #f39c12;">
                    <div class="stat-label">Stok Rendah</div>
                    <div class="stat-value text-warning"><?= $stokRendah ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card" style="border-top: 3px solid #e74c3c;">
                    <div class="stat-label">Stok Habis</div>
                    <div class="stat-value text-danger"><?= $stokHabis ?></div>
                </div>
            </div>
        </div>

        <!-- TABEL STOK -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-table"></i> Detail Stok Barang</h5>
                <div class="search-box">
                    <input type="text" id="searchStok" placeholder="&#128269; Cari barang..." onkeyup="filterStok()">
                </div>
            </div>

            <?php if (count($stokBarang) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-stok" id="tabelStok">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Barang</th>
                            <th>Stok Saat Ini</th>
                            <th>Minimum</th>
                            <th>Status</th>
                            <th>Update Terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($stokBarang as $sb):
                            $rowClass = '';
                            $badgeClass = 'aman';
                            $badgeLabel = '&#9989; Aman';
                            if ($sb['stok'] <= 0) {
                                $rowClass   = 'row-habis';
                                $badgeClass = 'habis';
                                $badgeLabel = '&#10060; Habis';
                            } elseif ($sb['stok'] <= $sb['stok_minimum']) {
                                $rowClass   = 'row-rendah';
                                $badgeClass = 'rendah';
                                $badgeLabel = '&#9888;&#65039; Rendah';
                            }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td><?= $no++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($sb['nama_item']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($sb['satuan']) ?></small>
                            </td>
                            <td>
                                <strong style="font-size: 20px; color: <?= $sb['stok'] <= 0 ? '#e74c3c' : ($sb['stok'] <= $sb['stok_minimum'] ? '#e67e22' : '#27ae60') ?>;">
                                    <?= $sb['stok'] ?>
                                </strong>
                                <small class="text-muted"><?= $sb['satuan'] ?></small>
                            </td>
                            <td><?= $sb['stok_minimum'] ?> <?= $sb['satuan'] ?></td>
                            <td><span class="stok-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                            <td><small class="text-muted"><?= $sb['updated_at'] ? date('d/m/Y H:i', strtotime($sb['updated_at'])) : '-' ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size: 48px;"></i>
                <p class="mt-2">Belum ada data stok barang di cabang ini.</p>
                <small>Hubungi kasir untuk menambahkan stok barang.</small>
            </div>
            <?php endif; ?>
        </div>

        <!-- LOG AKTIVITAS -->
        <?php if (!empty($recentLogs)): ?>
        <div class="card-custom">
            <h5 class="mb-3"><i class="bi bi-clock-history"></i> Riwayat Aktivitas Stok Terbaru</h5>
            <div>
                <?php foreach ($recentLogs as $log):
                    $iconClass = $log['tipe'];
                    $icon = $log['tipe'] === 'tambah' ? '&#10133;' : ($log['tipe'] === 'koreksi' ? '&#9998;' : '&#9866;');
                    $warna = $log['tipe'] === 'tambah' ? '#27ae60' : ($log['tipe'] === 'koreksi' ? '#e67e22' : '#e74c3c');
                ?>
                <div class="log-item">
                    <div class="log-icon <?= $log['tipe'] ?>"><?= $icon ?></div>
                    <div style="flex: 1;">
                        <strong><?= htmlspecialchars($log['nama_item']) ?></strong>
                        <span style="color: <?= $warna ?>; font-weight: bold; margin-left: 6px;">
                            <?= $log['jumlah'] > 0 ? '+' : '' ?><?= $log['jumlah'] ?> <?= $log['satuan'] ?>
                        </span>
                        <br>
                        <small class="text-muted">
                            <?= ucfirst($log['tipe']) ?>
                            <?php if ($log['keterangan']): ?>&mdash; <?= htmlspecialchars($log['keterangan']) ?><?php endif; ?>
                            <?php if ($log['oleh']): ?> oleh <em><?= htmlspecialchars($log['oleh']) ?></em><?php endif; ?>
                        </small>
                    </div>
                    <small class="text-muted"><?= date('d/m H:i', strtotime($log['created_at'])) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script>
    function filterStok() {
        const input = document.getElementById('searchStok').value.toLowerCase();
        const rows  = document.querySelectorAll('#tabelStok tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(input) ? '' : 'none';
        });
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>