<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'leader') {
    header("Location: ../auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$branchId = $_SESSION['user_branch_id'];

$pesan = "";
$tipe = "";

// =====================================================
// HELPER: Insert notifikasi ke branch_notifications
// =====================================================
function insertTerapisNotif($pdo, $type, $ref_id, $judul, $pesan_notif, $branch_id) {
    try {
        $stmt = $pdo->prepare("INSERT INTO branch_notifications (type, branch_id, ref_id, judul, pesan) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$type, $branch_id, $ref_id, $judul, $pesan_notif]);
    } catch (Exception $e) {
        error_log("[TERAPIS_NOTIF] Gagal insert notif: " . $e->getMessage());
    }
}

// =================================================================================
// AKSI: TAMBAH TERAPIS
// =================================================================================
if (isset($_POST['tambah_terapis'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $nama_lengkap = htmlspecialchars(trim($_POST['nama_lengkap']));
    $no_hp = trim($_POST['no_hp']);
    
    // Cek username duplikat
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmtCheck->execute([$username]);
    if ($stmtCheck->fetch()) {
        $pesan = "Username sudah digunakan!";
        $tipe = "danger";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, nama_lengkap, no_hp, role, home_branch_id, branch_id) VALUES (?, ?, ?, ?, 'terapis', ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$username, $hashed_password, $nama_lengkap, $no_hp, $branchId, $branchId])) {
            $new_id = $pdo->lastInsertId();
            $pesan = "Terapis berhasil ditambahkan!";
            $tipe = "success";
            
            // Kirim notifikasi ke cabang ini
            insertTerapisNotif(
                $pdo, 'terapis_baru', $new_id,
                "👤 Terapis Baru: $nama_lengkap",
                "Terapis baru \"$nama_lengkap\" telah ditambahkan di cabang ini oleh Leader.",
                $branchId
            );
        } else {
            $pesan = "Gagal menambahkan terapis!";
            $tipe = "danger";
        }
    }
}

// =================================================================================
// AKSI: EDIT TERAPIS
// =================================================================================
if (isset($_POST['edit_terapis'])) {
    $terapis_id = $_POST['terapis_id'];
    $nama_lengkap = htmlspecialchars(trim($_POST['nama_lengkap']));
    $no_hp = trim($_POST['no_hp']);
    $password = trim($_POST['password']);
    
    // Update nama dan no_hp
    $sql = "UPDATE users SET nama_lengkap = ?, no_hp = ?";
    $params = [$nama_lengkap, $no_hp];
    
    // Jika password diisi, update juga password
    if (!empty($password)) {
        $sql .= ", password = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    $sql .= " WHERE id = ? AND role = 'terapis' AND home_branch_id = ?";
    $params[] = $terapis_id;
    $params[] = $branchId;
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        $pesan = "Data terapis berhasil diupdate!";
        $tipe = "success";
        
        // Kirim notifikasi
        insertTerapisNotif(
            $pdo, 'terapis_update', $terapis_id,
            "✏️ Terapis Diupdate: $nama_lengkap",
            "Data terapis \"$nama_lengkap\" telah diperbarui oleh Leader.",
            $branchId
        );
    } else {
        $pesan = "Gagal mengupdate data terapis!";
        $tipe = "danger";
    }
}

// =================================================================================
// AKSI: HAPUS TERAPIS
// =================================================================================
if (isset($_GET['hapus'])) {
    $terapis_id = $_GET['hapus'];
    
    // Ambil nama terapis untuk notifikasi
    $stmtNama = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id = ? AND role = 'terapis' AND home_branch_id = ?");
    $stmtNama->execute([$terapis_id, $branchId]);
    $namaTerapis = $stmtNama->fetchColumn();
    
    if ($namaTerapis) {
        // Cek apakah terapis punya transaksi pending
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE terapis_id = ? AND commission_status = 'pending'");
        $stmtCheck->execute([$terapis_id]);
        $pendingCount = $stmtCheck->fetchColumn();
        
        if ($pendingCount > 0) {
            $pesan = "Tidak bisa hapus terapis yang masih punya komisi pending!";
            $tipe = "danger";
        } else {
            $sql = "DELETE FROM users WHERE id = ? AND role = 'terapis' AND home_branch_id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$terapis_id, $branchId])) {
                $pesan = "Terapis berhasil dihapus!";
                $tipe = "success";
            } else {
                $pesan = "Gagal menghapus terapis!";
                $tipe = "danger";
            }
        }
    } else {
        $pesan = "Terapis tidak ditemukan atau bukan milik cabang ini!";
        $tipe = "danger";
    }
}

// =================================================================================
// MODE 1: LIHAT RIWAYAT (Jika ?view_history=ID ada di URL)
// =================================================================================
if (isset($_GET['view_history'])) {
    $terapisId = $_GET['view_history'];
    
    // 1. AMBIL JAM MULAI
    $setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
    $jamMulai = $setting['jam_mulai_hari'] ?? '08:00:00';
    
    // Expression Tanggal Bisnis (Untuk History Grouping)
    $expBusinessDate = "IF(TIME(t.created_at) < '$jamMulai', DATE_SUB(DATE(t.created_at), INTERVAL 1 DAY), DATE(t.created_at))";
    
    // Ambil Info Terapis
    $stmtT = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
    $stmtT->execute([$terapisId]);
    $namaTerapis = $stmtT->fetchColumn();

    // Query Riwayat Lunas (Per Minggu) - KHUSUS CABANG INI
    $sqlHistory = "SELECT 
                    YEARWEEK($expBusinessDate, 1) as periode_kode,
                    MIN($expBusinessDate) as tgl_mulai,
                    MAX($expBusinessDate) as tgl_akhir,
                    MAX(t.commission_paid_at) as tgl_bayar,
                    COUNT(t.id) as total_pasien,
                    SUM(t.omset_terapis) as total_komisi
                  FROM transactions t
                  WHERE t.terapis_id = ? 
                  AND t.branch_id = ? 
                  AND t.commission_status = 'paid'
                  GROUP BY YEARWEEK($expBusinessDate, 1)
                  ORDER BY tgl_bayar DESC LIMIT 20";
    
    $stmtH = $pdo->prepare($sqlHistory);
    $stmtH->execute([$terapisId, $branchId]);
    $historyData = $stmtH->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Komisi - Leader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>body{background:#f4f7f6;font-family:'Segoe UI',sans-serif;padding:30px;}</style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="bi bi-clock-history text-primary"></i> Riwayat Komisi (Lunas)</h3>
            <a href="data_terapis_leader.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title">Terapis: <strong><?= htmlspecialchars($namaTerapis ?? 'Unknown') ?></strong></h5>
                <p class="text-muted small">Menampilkan 20 riwayat pembayaran terakhir di cabang ini.</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="p-3">Periode Minggu</th>
                                <th class="p-3 text-center">Jml Pasien</th>
                                <th class="p-3 text-end">Total Komisi</th>
                                <th class="p-3 text-center">Tanggal Cair</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($historyData) > 0): ?>
                                <?php foreach($historyData as $h): ?>
                                <tr>
                                    <td class="p-3">
                                        <?= date('d M', strtotime($h['tgl_mulai'])) ?> - <?= date('d M Y', strtotime($h['tgl_akhir'])) ?>
                                    </td>
                                    <td class="p-3 text-center"><?= $h['total_pasien'] ?></td>
                                    <td class="p-3 text-end fw-bold text-success">
                                        Rp <?= number_format($h['total_komisi'], 0, ',', '.') ?>
                                    </td>
                                    <td class="p-3 text-center">
                                        <small><?= date('d/m/Y H:i', strtotime($h['tgl_bayar'])) ?></small><br>
                                        <span class="badge bg-success">Lunas</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center p-4 text-muted">Belum ada riwayat pembayaran lunas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
    exit; // STOP AGAR TIDAK MENAMPILKAN HALAMAN UTAMA
}
?>

<?php
// =================================================================================
// MODE 2: HALAMAN UTAMA (LIST TERAPIS & KOMISI PENDING)
// =================================================================================

// 1. AMBIL JAM MULAI
$setting = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulai = $setting['jam_mulai_hari'] ?? '08:00:00';

// Expression Tanggal Bisnis (Untuk History Grouping)
$expBusinessDate = "IF(TIME(t.created_at) < '$jamMulai', DATE_SUB(DATE(t.created_at), INTERVAL 1 DAY), DATE(t.created_at))";

// 2. HITUNG TANGGAL BISNIS (Hari Ini)
if (date('H:i:s') < $jamMulai) {
    $tglBisnis = date('Y-m-d', strtotime('-1 day'));
} else {
    $tglBisnis = date('Y-m-d');
}

// 3. SIAPKAN FILTER WAKTU (RANGE)
$periode = $_GET['periode'] ?? 'semua'; 
$start_filter = "";
$end_filter = "";

if ($periode == 'hari_ini') {
    $start_filter = "$tglBisnis $jamMulai";
    $end_filter   = date('Y-m-d H:i:s', strtotime("$start_filter +1 day"));
} 
elseif ($periode == 'minggu_ini') {
    $seninMingguIni = date('Y-m-d', strtotime("monday this week", strtotime($tglBisnis)));
    $start_filter = "$seninMingguIni $jamMulai";
    $end_filter   = date('Y-m-d H:i:s', strtotime("$start_filter +7 days"));
} 
elseif ($periode == 'bulan_ini') {
    $start_filter = date('Y-m-01', strtotime($tglBisnis)) . " $jamMulai";
    $end_filter   = date('Y-m-d H:i:s', strtotime("$start_filter +1 month"));
} 
else { // Semua (Default)
    $start_filter = "2020-01-01 00:00:00";
    $end_filter   = "2099-12-31 23:59:59";
}

$whereClause = "AND t.created_at >= ? AND t.created_at < ?";

// === DATA USER LEADER ===
$stmtUser = $pdo->prepare("SELECT u.*, b.nama_cabang FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
$stmtUser->execute([$userId]);
$userMe = $stmtUser->fetch();
$namaCabang = $userMe['nama_cabang'] ?? 'Cabang';

$fotoPath = (!empty($userMe['foto_profil']) && file_exists("../uploads/profil/".$userMe['foto_profil']))
    ? "../uploads/profil/".$userMe['foto_profil']
    : "../assets/img/default-avatar.png";

// === TERAPIS INTERNAL (Home Branch) ===
$sqlInternal = "SELECT u.id, u.username, u.nama_lengkap, u.foto_profil, u.no_hp,
               COUNT(t.id) as trx_count,
               COALESCE(SUM(t.omset_terapis), 0) as omset_val
               FROM users u
               LEFT JOIN transactions t ON u.id = t.terapis_id 
                   AND t.branch_id = ? 
                   AND t.commission_status = 'pending'
                   $whereClause
               WHERE u.role = 'terapis' AND u.home_branch_id = ?
               GROUP BY u.id, u.username, u.nama_lengkap, u.foto_profil, u.no_hp
               ORDER BY omset_val DESC";
$stmtInternal = $pdo->prepare($sqlInternal);
$stmtInternal->execute([$branchId, $start_filter, $end_filter, $branchId]);
$terapisInternal = $stmtInternal->fetchAll();

// === TERAPIS BANTUAN (Dipinjam ke cabang ini) ===
$sqlBantuan = "SELECT u.id, u.username, u.nama_lengkap, u.foto_profil, u.no_hp, b.nama_cabang as cabang_asal,
               COUNT(t.id) as trx_count,
               COALESCE(SUM(t.omset_terapis), 0) as omset_val
               FROM users u
               JOIN branches b ON u.home_branch_id = b.id
               JOIN terapis_loans tl ON u.id = tl.terapis_id
               JOIN transactions t ON tl.transaction_id = t.id 
                   AND t.branch_id = ? 
                   AND t.commission_status = 'pending'
                   $whereClause
               WHERE tl.to_branch_id = ? AND tl.status = 'active'
               GROUP BY u.id, u.username, u.nama_lengkap, u.foto_profil, u.no_hp, b.nama_cabang
               ORDER BY omset_val DESC";
$stmtBantuan = $pdo->prepare($sqlBantuan);
$stmtBantuan->execute([$branchId, $start_filter, $end_filter, $branchId]);
$terapisBantuan = $stmtBantuan->fetchAll();

// Total Omset Pending
$totalOmsetSemua = 0;
foreach($terapisInternal as $t) $totalOmsetSemua += $t['omset_val'];
foreach($terapisBantuan as $t) $totalOmsetSemua += $t['omset_val'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Terapis - Leader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        :root { --sidebar-w: 250px; --primary: #2c3e50; --accent: #3498db; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; }
        .sidebar { width: var(--sidebar-w); background: linear-gradient(180deg, var(--primary) 0%, #34495e 100%); height: 100vh; position: fixed; color: white; overflow-y: auto; }
        .main-content { margin-left: var(--sidebar-w); padding: 30px; }
        .nav-link { display: block; padding: 12px 20px; color: #bdc3c7; text-decoration: none; border-left: 4px solid transparent; }
        .nav-link:hover, .nav-link.active { background: #34495e; color: white; border-left: 4px solid var(--accent); }
        .card-custom { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .avatar-table { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-4 text-center border-bottom border-secondary">
            <h4 class="mb-0">LEADER PANEL</h4>
        </div>
        <div class="p-3 text-center border-bottom border-secondary">
            <img src="<?= $fotoPath ?>" class="rounded-circle border border-primary mb-2" width="70" height="70">
            <div class="fw-bold"><?= htmlspecialchars($userMe['nama_lengkap'] ?? 'Leader') ?></div>
            <small class="text-muted"><?= htmlspecialchars($namaCabang ?? 'Cabang') ?></small>
        </div>
        <div class="py-2">
            <a href="dashboard_leader.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
            <a href="data_terapis_leader.php" class="nav-link active"><i class="bi bi-people me-2"></i> Data Terapis</a>
            <a href="stok_barang_leader.php" class="nav-link"><i class="bi bi-box-seam"></i> Stok Barang</a>
            <a href="monitoring_terapis.php" class="nav-link"><i class="bi bi-eye me-2"></i> Monitoring</a>
            <a href="pelanggaran_terapis.php" class="nav-link"><i class="bi bi-exclamation-triangle me-2"></i> Pelanggaran</a>
            <a href="profil_leader.php" class="nav-link"><i class="bi bi-person-circle me-2"></i> Profil</a>
            </div>
        <div style="padding: 20px; margin-top: auto;"><a href="../auth/logout_system.php" class="btn btn-danger w-100"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-people text-primary"></i> Data Terapis & Komisi</h2>
                <p class="text-muted">Pantau komisi terapis yang <strong>BELUM DIBAYAR (Pending)</strong></p>
            </div>
            <div class="d-flex gap-2">
                <select class="form-select" onchange="window.location.href='?periode='+this.value">
                    <option value="semua" <?= $periode == 'semua' ? 'selected' : '' ?>>Semua (Total Pending)</option>
                    <option value="hari_ini" <?= $periode == 'hari_ini' ? 'selected' : '' ?>>Hari Ini</option>
                    <option value="minggu_ini" <?= $periode == 'minggu_ini' ? 'selected' : '' ?>>Minggu Ini</option>
                    <option value="bulan_ini" <?= $periode == 'bulan_ini' ? 'selected' : '' ?>>Bulan Ini</option>
                </select>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="bi bi-plus-circle"></i> Tambah Terapis
                </button>
            </div>
        </div>

        <?php if ($pesan): ?>
        <div class="alert alert-<?= $tipe ?> alert-dismissible fade show" role="alert">
            <?= $pesan ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card-custom text-white" style="background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);">
            <div class="row align-items-center">
                <div class="col-8">
                    <h5 class="mb-1">Total Komisi Pending (Belum Cair)</h5>
                    <small>Periode: <?= str_replace('_', ' ', ucfirst($periode)) ?></small>
                    <h2 class="mb-0 mt-2">Rp <?= number_format($totalOmsetSemua, 0, ',', '.') ?></h2>
                </div>
                <div class="col-4 text-end"><i class="bi bi-wallet2" style="font-size: 50px; opacity: 0.5;"></i></div>
            </div>
        </div>

        <div class="card-custom">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="text-primary"><i class="bi bi-people-fill"></i> Terapis Internal</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Foto</th>
                            <th>Nama</th>
                            <th>No HP</th>
                            <th>Trx Pending</th>
                            <th>Komisi Pending</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=1; foreach ($terapisInternal as $t): 
                            $foto = !empty($t['foto_profil']) ? "../uploads/profil/".$t['foto_profil'] : "../assets/img/default-avatar.png"; ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><img src="<?= $foto ?>" class="avatar-table"></td>
                            <td><strong><?= htmlspecialchars($t['nama_lengkap'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($t['no_hp'] ?? '') ?></td>
                            <td><span class="badge bg-warning text-dark"><?= $t['trx_count'] ?> pending</span></td>
                            <td class="fw-bold text-danger">Rp <?= number_format($t['omset_val'], 0, ',', '.') ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="?view_history=<?= $t['id'] ?>" class="btn btn-sm btn-info text-white">
                                        <i class="bi bi-clock-history"></i>
                                    </a>
                                    <button class="btn btn-sm btn-warning text-white btn-edit" 
                                        data-id="<?= $t['id'] ?>"
                                        data-nama="<?= htmlspecialchars($t['nama_lengkap'] ?? '') ?>"
                                        data-hp="<?= htmlspecialchars($t['no_hp'] ?? '') ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalEdit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?hapus=<?= $t['id'] ?>" 
                                       onclick="return confirm('Yakin hapus terapis <?= htmlspecialchars($t['nama_lengkap'] ?? '') ?>? Pastikan tidak ada komisi pending!')"
                                       class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($terapisInternal)) echo '<tr><td colspan="7" class="text-center text-muted">Tidak ada terapis internal.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($terapisBantuan)): ?>
        <div class="card-custom border-start border-4 border-warning">
            <h5 class="mb-3 text-warning"><i class="bi bi-arrow-left-right"></i> Terapis Bantuan</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Foto</th>
                            <th>Nama</th>
                            <th>Asal Cabang</th>
                            <th>Trx Pending</th>
                            <th>Komisi Pending</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=1; foreach ($terapisBantuan as $t): 
                            $foto = !empty($t['foto_profil']) ? "../uploads/profil/".$t['foto_profil'] : "../assets/img/default-avatar.png"; ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><img src="<?= $foto ?>" class="avatar-table"></td>
                            <td><strong><?= htmlspecialchars($t['nama_lengkap'] ?? '') ?></strong></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($t['cabang_asal'] ?? '') ?></span></td>
                            <td><span class="badge bg-warning text-dark"><?= $t['trx_count'] ?> pending</span></td>
                            <td class="fw-bold text-danger">Rp <?= number_format($t['omset_val'], 0, ',', '.') ?></td>
                            <td>
                                <a href="?view_history=<?= $t['id'] ?>" class="btn btn-sm btn-info text-white">
                                    <i class="bi bi-clock-history"></i> Riwayat
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Modal Tambah Terapis -->
    <div class="modal fade" id="modalTambah" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Tambah Terapis Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama_lengkap" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No HP</label>
                            <input type="text" name="no_hp" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_terapis" class="btn btn-success">
                            <i class="bi bi-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Terapis -->
    <div class="modal fade" id="modalEdit" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Data Terapis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="terapis_id" id="edit_terapis_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No HP</label>
                            <input type="text" name="no_hp" id="edit_no_hp" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru <small class="text-muted">(Kosongkan jika tidak ingin mengubah)</small></label>
                            <input type="password" name="password" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit_terapis" class="btn btn-warning">
                            <i class="bi bi-save"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit button click
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_terapis_id').value = this.dataset.id;
                document.getElementById('edit_nama_lengkap').value = this.dataset.nama;
                document.getElementById('edit_no_hp').value = this.dataset.hp;
            });
        });
    </script>
</body>
</html>