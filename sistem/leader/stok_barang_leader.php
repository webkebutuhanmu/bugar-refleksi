<?php
// File: leader/stok_barang_leader.php
// View Stok Barang (Read Only) untuk Leader
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'leader') {
    header("Location: ../auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$branchId = $_SESSION['user_branch_id'];

// Ambil data user leader untuk sidebar
$stmtUser = $pdo->prepare("SELECT u.*, b.nama_cabang FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
$stmtUser->execute([$userId]);
$userMe = $stmtUser->fetch();
$fotoPath = !empty($userMe['foto_profil']) ? "../uploads/profil/" . $userMe['foto_profil'] : "../assets/img/default-avatar.png";
$namaCabang = $userMe['nama_cabang'];

// =====================================================
// QUERY: Stok Barang di Cabang Ini
// =====================================================
$stmtStok = $pdo->prepare("
    SELECT bi.*, i.nama_item, i.satuan 
    FROM branch_items bi 
    JOIN items i ON bi.item_id = i.id 
    WHERE bi.branch_id = ? 
    ORDER BY i.nama_item ASC
");
$stmtStok->execute([$branchId]);
$stokBarang = $stmtStok->fetchAll();

// Hitung statistik
$totalItems    = count($stokBarang);
$stokRendah    = 0;
$stokHabis     = 0;
foreach ($stokBarang as $sb) {
    if ($sb['stok'] <= 0) $stokHabis++;
    elseif ($sb['stok'] <= $sb['stok_minimum']) $stokRendah++;
}
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
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: 0.3s; text-align: center; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .stat-value { font-size: 32px; font-weight: bold; margin: 5px 0; }
        .card-custom { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .badge-stok { font-size: 12px; padding: 5px 10px; border-radius: 20px; font-weight: bold; }
        .bg-habis { background-color: #fdedec; color: #e74c3c; }
        .bg-rendah { background-color: #fef9e7; color: #e67e22; }
        .bg-aman { background-color: #e8f8f5; color: #27ae60; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand"><i class="bi bi-building"></i> LEADER PANEL</div>
        <div class="profile-section">
            <img src="<?= $fotoPath ?>" class="img-nav">
            <div style="font-weight:bold; margin-top:10px;"><?= htmlspecialchars($userMe['nama_lengkap']) ?></div>
            <small style="color: #95a5a6;"><?= htmlspecialchars($namaCabang) ?></small>
        </div>
        <div class="nav-menu">
            <a href="dashboard_leader.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="data_terapis_leader.php" class="nav-link"><i class="bi bi-people"></i> Data Terapis</a>
            <a href="stok_barang_leader.php" class="nav-link active"><i class="bi bi-box-seam"></i> Stok Barang</a>
            <a href="monitoring_terapis.php" class="nav-link"><i class="bi bi-eye"></i> Monitoring</a>
            <a href="pelanggaran_terapis.php" class="nav-link"><i class="bi bi-exclamation-triangle"></i> Pelanggaran</a>
            <a href="profil_leader.php" class="nav-link"><i class="bi bi-person-circle"></i> Profil</a>
        </div>
        <div style="padding: 20px; margin-top: auto;"><a href="../auth/logout_system.php" class="btn btn-danger w-100"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="mb-4">
            <h2><i class="bi bi-box-seam text-primary"></i> Data Stok Barang</h2>
            <p class="text-muted">Monitoring stok di cabang <strong><?= htmlspecialchars($namaCabang) ?></strong></p>
        </div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card border-top border-3 border-primary">
                    <div class="text-muted small">Total Item</div>
                    <div class="stat-value text-primary"><?= $totalItems ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card border-top border-3 border-success">
                    <div class="text-muted small">Stok Aman</div>
                    <div class="stat-value text-success"><?= $totalItems - $stokRendah - $stokHabis ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card border-top border-3 border-warning">
                    <div class="text-muted small">Stok Rendah</div>
                    <div class="stat-value text-warning"><?= $stokRendah ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card border-top border-3 border-danger">
                    <div class="text-muted small">Stok Habis</div>
                    <div class="stat-value text-danger"><?= $stokHabis ?></div>
                </div>
            </div>
        </div>

        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-table"></i> Daftar Barang</h5>
                <input type="text" id="searchStok" class="form-control w-25" placeholder="Cari barang...">
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tabelStok">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Nama Barang</th>
                            <th>Stok Aktual</th>
                            <th>Batas Minimum</th>
                            <th>Status</th>
                            <th>Update Terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($stokBarang) > 0): ?>
                            <?php $no=1; foreach($stokBarang as $sb): 
                                $statusClass = 'bg-aman';
                                $statusText = 'Aman';
                                if ($sb['stok'] <= 0) { 
                                    $statusClass = 'bg-habis'; 
                                    $statusText = 'Habis';
                                } elseif ($sb['stok'] <= $sb['stok_minimum']) { 
                                    $statusClass = 'bg-rendah'; 
                                    $statusText = 'Rendah';
                                }
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($sb['nama_item']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($sb['satuan']) ?></small>
                                </td>
                                <td>
                                    <span style="font-size:18px; font-weight:bold; color: <?= $sb['stok'] <= 0 ? '#e74c3c' : '#2c3e50' ?>">
                                        <?= $sb['stok'] ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?= $sb['stok_minimum'] ?></td>
                                <td><span class="badge-stok <?= $statusClass ?>"><?= $statusText ?></span></td>
                                <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($sb['updated_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data barang di cabang ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple Search
        document.getElementById('searchStok').addEventListener('keyup', function() {
            const val = this.value.toLowerCase();
            const rows = document.querySelectorAll('#tabelStok tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(val) ? '' : 'none';
            });
        });
    </script>
</body>
</html>