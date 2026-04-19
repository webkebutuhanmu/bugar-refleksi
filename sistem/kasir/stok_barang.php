<?php
// File: kasir/stok_barang.php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) {
    header("Location: pilih_cabang.php"); exit;
}

$kasir_id   = $_SESSION['user_id'];
$branch_id  = $_SESSION['active_branch'];
$nama_kasir = $_SESSION['nama'];

$stmt = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$nama_cabang = $stmt->fetchColumn();

// Foto profil
$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$foto_profil = $stmtProfil->fetchColumn();
$foto_profil = (!empty($foto_profil) && file_exists("../uploads/profil/" . $foto_profil)) ? "../uploads/profil/" . $foto_profil : "../assets/default_user.png";

$swal_script = "";

// =====================================================
// ACTION: Tambah Item Baru (master)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tambah_item') {
    try {
        $nama_item = trim($_POST['nama_item'] ?? '');
        $satuan    = trim($_POST['satuan'] ?? 'pcs');
        $stok_awal = intval($_POST['stok_awal'] ?? 0);
        $stok_min  = intval($_POST['stok_minimum'] ?? 5);

        if (empty($nama_item)) throw new Exception("Nama barang wajib diisi!");

        $cek = $pdo->prepare("SELECT id FROM items WHERE LOWER(nama_item) = LOWER(?)");
        $cek->execute([$nama_item]);
        $existing = $cek->fetch();

        if ($existing) {
            $item_id = $existing['id'];
        } else {
            $pdo->prepare("INSERT INTO items (nama_item, satuan) VALUES (?, ?)")->execute([$nama_item, $satuan]);
            $item_id = $pdo->lastInsertId();
        }

        $cekCabang = $pdo->prepare("SELECT id FROM branch_items WHERE branch_id = ? AND item_id = ?");
        $cekCabang->execute([$branch_id, $item_id]);
        if ($cekCabang->fetch()) {
            throw new Exception("Barang '$nama_item' sudah ada di cabang ini!");
        }

        $pdo->prepare("INSERT INTO branch_items (branch_id, item_id, stok, stok_minimum) VALUES (?, ?, ?, ?)")
            ->execute([$branch_id, $item_id, $stok_awal, $stok_min]);

        if ($stok_awal > 0) {
            $pdo->prepare("INSERT INTO item_usage_log (branch_id, item_id, jumlah, tipe, keterangan, created_by) VALUES (?, ?, ?, 'tambah', 'Stok awal', ?)")
                ->execute([$branch_id, $item_id, $stok_awal, $kasir_id]);
        }

        $swal_script = "Swal.fire({title:'Berhasil!',text:'Barang berhasil ditambahkan.',icon:'success',timer:2000,showConfirmButton:false});";
    } catch (Exception $e) {
        $swal_script = "Swal.fire('Gagal!', '" . addslashes($e->getMessage()) . "', 'error');";
    }
}

// =====================================================
// ACTION: Tambah Stok (restock)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tambah_stok') {
    try {
        $bi_id  = intval($_POST['branch_item_id'] ?? 0);
        $jumlah = intval($_POST['jumlah_tambah'] ?? 0);
        $ket    = trim($_POST['keterangan'] ?? 'Restock');

        if ($jumlah <= 0) throw new Exception("Jumlah harus lebih dari 0!");

        $stmtBI = $pdo->prepare("SELECT bi.*, i.nama_item FROM branch_items bi JOIN items i ON bi.item_id = i.id WHERE bi.id = ? AND bi.branch_id = ?");
        $stmtBI->execute([$bi_id, $branch_id]);
        $bi = $stmtBI->fetch();
        if (!$bi) throw new Exception("Data barang tidak ditemukan!");

        $pdo->prepare("UPDATE branch_items SET stok = stok + ? WHERE id = ?")->execute([$jumlah, $bi_id]);
        $pdo->prepare("INSERT INTO item_usage_log (branch_id, item_id, jumlah, tipe, keterangan, created_by) VALUES (?, ?, ?, 'tambah', ?, ?)")
            ->execute([$branch_id, $bi['item_id'], $jumlah, $ket, $kasir_id]);

        $swal_script = "Swal.fire({title:'Berhasil!',text:'Stok " . addslashes($bi['nama_item']) . " ditambah $jumlah.',icon:'success',timer:2000,showConfirmButton:false});";
    } catch (Exception $e) {
        $swal_script = "Swal.fire('Gagal!', '" . addslashes($e->getMessage()) . "', 'error');";
    }
}

// =====================================================
// ACTION: Koreksi Stok
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'koreksi_stok') {
    try {
        $bi_id     = intval($_POST['branch_item_id'] ?? 0);
        $stok_baru = intval($_POST['stok_baru'] ?? 0);
        $ket       = trim($_POST['keterangan_koreksi'] ?? 'Koreksi stok');

        if ($stok_baru < 0) throw new Exception("Stok tidak boleh negatif!");

        $stmtBI = $pdo->prepare("SELECT bi.*, i.nama_item FROM branch_items bi JOIN items i ON bi.item_id = i.id WHERE bi.id = ? AND bi.branch_id = ?");
        $stmtBI->execute([$bi_id, $branch_id]);
        $bi = $stmtBI->fetch();
        if (!$bi) throw new Exception("Data barang tidak ditemukan!");

        $selisih = $stok_baru - $bi['stok'];
        $pdo->prepare("UPDATE branch_items SET stok = ? WHERE id = ?")->execute([$stok_baru, $bi_id]);
        $pdo->prepare("INSERT INTO item_usage_log (branch_id, item_id, jumlah, tipe, keterangan, created_by) VALUES (?, ?, ?, 'koreksi', ?, ?)")
            ->execute([$branch_id, $bi['item_id'], $selisih, $ket, $kasir_id]);

        $swal_script = "Swal.fire({title:'Berhasil!',text:'Stok " . addslashes($bi['nama_item']) . " dikoreksi menjadi $stok_baru.',icon:'success',timer:2000,showConfirmButton:false});";
    } catch (Exception $e) {
        $swal_script = "Swal.fire('Gagal!', '" . addslashes($e->getMessage()) . "', 'error');";
    }
}

// =====================================================
// ACTION: Update Stok Minimum
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_minimum') {
    try {
        $bi_id   = intval($_POST['branch_item_id'] ?? 0);
        $min_val = intval($_POST['stok_minimum_baru'] ?? 5);
        $pdo->prepare("UPDATE branch_items SET stok_minimum = ? WHERE id = ? AND branch_id = ?")->execute([$min_val, $bi_id, $branch_id]);
        $swal_script = "Swal.fire({title:'Berhasil!',text:'Batas minimum diupdate.',icon:'success',timer:1500,showConfirmButton:false});";
    } catch (Exception $e) {
        $swal_script = "Swal.fire('Gagal!', '" . addslashes($e->getMessage()) . "', 'error');";
    }
}

// =====================================================
// ACTION: Hapus Barang dari Cabang
// =====================================================
if (isset($_GET['hapus_bi'])) {
    try {
        $bi_id = intval($_GET['hapus_bi']);
        $pdo->prepare("DELETE FROM branch_items WHERE id = ? AND branch_id = ?")->execute([$bi_id, $branch_id]);
        $swal_script = "Swal.fire({title:'Dihapus!',text:'Barang dihapus dari cabang ini.',icon:'success',timer:1500,showConfirmButton:false});";
    } catch (Exception $e) {
        $swal_script = "Swal.fire('Gagal!', '" . addslashes($e->getMessage()) . "', 'error');";
    }
}

// QUERY Stok
$stmtStok = $pdo->prepare("
    SELECT bi.*, i.nama_item, i.satuan 
    FROM branch_items bi 
    JOIN items i ON bi.item_id = i.id 
    WHERE bi.branch_id = ? 
    ORDER BY i.nama_item ASC
");
$stmtStok->execute([$branch_id]);
$stokBarang = $stmtStok->fetchAll();

$stmtMaster = $pdo->prepare("
    SELECT i.* FROM items i 
    WHERE i.id NOT IN (SELECT item_id FROM branch_items WHERE branch_id = ?)
    ORDER BY i.nama_item ASC
");
$stmtMaster->execute([$branch_id]);
$masterItems = $stmtMaster->fetchAll();

$totalItems    = count($stokBarang);
$stokRendah    = 0;
$stokHabis     = 0;
foreach ($stokBarang as $sb) {
    if ($sb['stok'] <= 0) $stokHabis++;
    elseif ($sb['stok'] <= $sb['stok_minimum']) $stokRendah++;
}

$stmtLog = $pdo->prepare("
    SELECT iul.*, i.nama_item, i.satuan, u.nama_lengkap as oleh
    FROM item_usage_log iul
    JOIN items i ON iul.item_id = i.id
    LEFT JOIN users u ON iul.created_by = u.id
    WHERE iul.branch_id = ?
    ORDER BY iul.created_at DESC
    LIMIT 20
");
$stmtLog->execute([$branch_id]);
$recentLogs = $stmtLog->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Barang - Kasir</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .stok-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stok-stat-box { background: var(--bg-panel); border-radius: 12px; padding: 20px 16px; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); transition: 0.3s; }
        .stok-stat-box .stat-val { font-size: 26px; font-weight: 800; color: var(--text-dark); margin-top: 5px; font-family: 'Plus Jakarta Sans', sans-serif; }
        .stok-stat-box .stat-lbl { font-size: 12px; color: var(--text-muted); margin-top: 2px; font-weight: 600; text-transform: uppercase; }

        .stok-table { width: 100%; border-collapse: collapse; }
        .stok-table th { background: var(--bg-input); padding: 12px 15px; text-align: left; font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        .stok-table td { padding: 14px 15px; border-bottom: 1px solid var(--border-color); font-size: 14px; vertical-align: middle; color: var(--text-dark); }
        .stok-table .nama-item { font-weight: 700; color: var(--text-dark); }
        .stok-table .satuan { color: var(--text-muted); font-size: 12px; }
        
        .btn-stok { border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: bold; transition: 0.2s; color: white; text-transform: uppercase; }
        .btn-stok.tambah { background: var(--accent-green); }
        .btn-stok.koreksi { background: var(--accent-blue); }
        .btn-stok.minimum { background: var(--accent-yellow2); color: #111; }
        .btn-stok.hapus { background: var(--accent-red); }

        .log-table { width: 100%; border-collapse: collapse; }
        .log-table th { background: var(--bg-input); padding: 10px 12px; text-align: left; font-size: 11px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); text-transform: uppercase; }
        .log-table td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); font-size: 13px; color: var(--text-dark); }
        .log-badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .log-badge.pakai { background: rgba(231,76,60,0.1); color: var(--accent-red); }
        .log-badge.tambah { background: rgba(39,174,96,0.1); color: var(--accent-green); }
        .log-badge.koreksi { background: rgba(52,152,219,0.1); color: var(--accent-blue); }

        .search-box { display: flex; gap: 10px; margin-bottom: 15px; }
        .search-box input { flex: 1; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; background: var(--bg-input); color: var(--text-dark); }
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
                <a href="paket_layanan_kasir.php" class="menu-item"><span class="menu-abbr">PL</span><span class="menu-text">Paket Layanan</span></a>
                <a href="stok_barang.php" class="menu-item active"><span class="menu-abbr">SB</span><span class="menu-text">Stok Barang</span></a>
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
                    <h1>Stok Barang</h1>
                </div>
                <div class="topbar-right">
                    <button onclick="openModal('modalTambahItem')" class="btn btn-success">Tambah Barang</button>
                    <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
                </div>
            </div>

            <div class="stok-stats">
                <div class="stok-stat-box" style="border-top:3px solid var(--accent-blue);">
                    <div class="stat-val"><?= $totalItems ?></div>
                    <div class="stat-lbl">Total Jenis Barang</div>
                </div>
                <div class="stok-stat-box" style="border-top:3px solid var(--accent-green);">
                    <div class="stat-val"><?= $totalItems - $stokRendah - $stokHabis ?></div>
                    <div class="stat-lbl">Stok Aman</div>
                </div>
                <div class="stok-stat-box" style="border-top:3px solid var(--accent-yellow2);">
                    <div class="stat-val"><?= $stokRendah ?></div>
                    <div class="stat-lbl">Stok Rendah</div>
                </div>
                <div class="stok-stat-box" style="border-top:3px solid var(--accent-red);">
                    <div class="stat-val"><?= $stokHabis ?></div>
                    <div class="stat-lbl">Stok Habis</div>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    Daftar Stok Barang
                    <small style="float:right; font-weight:normal; color:var(--text-muted);"><?= $totalItems ?> item</small>
                </div>
                <div style="padding:15px 20px;">
                    <div class="search-box">
                        <input type="text" id="searchStok" placeholder="Cari nama barang..." onkeyup="filterStok()">
                    </div>

                    <?php if (count($stokBarang) > 0): ?>
                    <div class="table-container">
                        <table class="stok-table" id="tabelStok">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Barang</th>
                                    <th>Stok</th>
                                    <th>Min</th>
                                    <th>Status</th>
                                    <th>Update Terakhir</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no=1; foreach($stokBarang as $sb): 
                                    $statusClass = 'badge-success';
                                    $statusLabel = 'Aman';
                                    $stokColor = 'var(--accent-green)';
                                    if ($sb['stok'] <= 0) { $statusClass = 'badge-danger'; $statusLabel = 'Habis'; $stokColor = 'var(--accent-red)'; }
                                    elseif ($sb['stok'] <= $sb['stok_minimum']) { $statusClass = 'badge-warning'; $statusLabel = 'Rendah'; $stokColor = 'var(--accent-yellow2)'; }
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <span class="nama-item"><?= htmlspecialchars($sb['nama_item']) ?></span>
                                        <br><span class="satuan"><?= htmlspecialchars($sb['satuan']) ?></span>
                                    </td>
                                    <td><strong style="font-size:18px; color:<?= $stokColor ?>"><?= $sb['stok'] ?></strong></td>
                                    <td style="color:var(--text-muted);"><?= $sb['stok_minimum'] ?></td>
                                    <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                    <td style="font-size:12px; color:var(--text-muted);"><?= date('d/m H:i', strtotime($sb['updated_at'])) ?></td>
                                    <td>
                                        <div style="display:flex; gap:5px;">
                                            <button class="btn-stok tambah" onclick="openTambahStok(<?= $sb['id'] ?>, '<?= addslashes($sb['nama_item']) ?>', <?= $sb['stok'] ?>)">Isi</button>
                                            <button class="btn-stok koreksi" onclick="openKoreksiStok(<?= $sb['id'] ?>, '<?= addslashes($sb['nama_item']) ?>', <?= $sb['stok'] ?>)">Edit</button>
                                            <button class="btn-stok minimum" onclick="openUpdateMin(<?= $sb['id'] ?>, '<?= addslashes($sb['nama_item']) ?>', <?= $sb['stok_minimum'] ?>)">Min</button>
                                            <button class="btn-stok hapus" onclick="hapusBarang(<?= $sb['id'] ?>, '<?= addslashes($sb['nama_item']) ?>')">Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div style="text-align:center; padding:40px; color:var(--text-muted); font-weight:600;">
                        Belum ada barang di cabang ini.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Riwayat Stok Terbaru</div>
                <div class="table-container">
                    <?php if (count($recentLogs) > 0): ?>
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Barang</th>
                                <th>Tipe</th>
                                <th>Jumlah</th>
                                <th>Keterangan</th>
                                <th>Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recentLogs as $log): ?>
                            <tr>
                                <td><?= date('d/m H:i', strtotime($log['created_at'])) ?></td>
                                <td><strong><?= htmlspecialchars($log['nama_item']) ?></strong></td>
                                <td><span class="log-badge <?= $log['tipe'] ?>"><?= ucfirst($log['tipe']) ?></span></td>
                                <td style="font-weight:bold; color:<?= $log['jumlah'] >= 0 ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                                    <?= $log['jumlah'] >= 0 ? '+' : '' ?><?= $log['jumlah'] ?> <?= htmlspecialchars($log['satuan']) ?>
                                </td>
                                <td><?= htmlspecialchars($log['keterangan'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($log['oleh'] ?? 'Sistem') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align:center; padding:30px; color:var(--text-muted);">Belum ada riwayat stok.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalTambahItem">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Tambah Barang Baru</h3>
                <button class="modal-close" onclick="closeModal('modalTambahItem')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="tambah_item">
                    <div class="form-group">
                        <label>Nama Barang</label>
                        <input type="text" name="nama_item" id="inputNamaItem" class="form-control" required placeholder="Contoh: Cream Pijat" list="masterItemList" autocomplete="off">
                        <datalist id="masterItemList">
                            <?php foreach($masterItems as $mi): ?>
                            <option value="<?= htmlspecialchars($mi['nama_item']) ?>" data-satuan="<?= htmlspecialchars($mi['satuan']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>Satuan</label>
                        <select name="satuan" id="inputSatuan" class="form-control">
                            <option value="pcs">pcs</option>
                            <option value="botol">botol</option>
                            <option value="lembar">lembar</option>
                            <option value="pack">pack</option>
                            <option value="tube">tube</option>
                            <option value="liter">liter</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stok Awal</label>
                        <input type="number" name="stok_awal" class="form-control" value="0" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Batas Minimum</label>
                        <input type="number" name="stok_minimum" class="form-control" value="5" min="0" required>
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%; margin-top:10px;">Simpan Barang</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalTambahStok">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Isi Stok (Restock)</h3>
                <button class="modal-close" onclick="closeModal('modalTambahStok')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="tambah_stok">
                    <input type="hidden" name="branch_item_id" id="ts_bi_id">
                    <div style="background:var(--bg-input); padding:12px; border-radius:8px; margin-bottom:15px; border:1px solid var(--border-color);">
                        <strong id="ts_nama_item" style="color:var(--text-dark);">-</strong><br>
                        <span style="font-size:12px; color:var(--text-muted);">Stok saat ini: <strong id="ts_stok_skrg">0</strong></span>
                    </div>
                    <div class="form-group">
                        <label>Jumlah Tambah</label>
                        <input type="number" name="jumlah_tambah" class="form-control" min="1" required placeholder="Masukkan jumlah">
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <input type="text" name="keterangan" class="form-control" value="Restock" required>
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%; margin-top:10px;">Tambah Stok</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalKoreksiStok">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Koreksi Stok Aktual</h3>
                <button class="modal-close" onclick="closeModal('modalKoreksiStok')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="koreksi_stok">
                    <input type="hidden" name="branch_item_id" id="ks_bi_id">
                    <div style="background:var(--bg-input); padding:12px; border-radius:8px; margin-bottom:15px; border:1px solid var(--border-color);">
                        <strong id="ks_nama_item" style="color:var(--text-dark);">-</strong><br>
                        <span style="font-size:12px; color:var(--text-muted);">Stok sistem: <strong id="ks_stok_skrg">0</strong></span>
                    </div>
                    <div class="form-group">
                        <label>Stok Baru (Aktual)</label>
                        <input type="number" name="stok_baru" id="ks_stok_baru" class="form-control" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Alasan Koreksi</label>
                        <input type="text" name="keterangan_koreksi" class="form-control" value="Koreksi stok" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Update Stok</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalUpdateMin">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Update Batas Minimum</h3>
                <button class="modal-close" onclick="closeModal('modalUpdateMin')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_minimum">
                    <input type="hidden" name="branch_item_id" id="um_bi_id">
                    <div style="background:var(--bg-input); padding:12px; border-radius:8px; margin-bottom:15px; border:1px solid var(--border-color);">
                        <strong id="um_nama_item" style="color:var(--text-dark);">-</strong>
                    </div>
                    <div class="form-group">
                        <label>Batas Minimum Baru</label>
                        <input type="number" name="stok_minimum_baru" id="um_min_val" class="form-control" min="0" required>
                    </div>
                    <button type="submit" class="btn btn-warning" style="width:100%; margin-top:10px;">Update Batas</button>
                </form>
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

    function openModal(id) { document.getElementById(id).classList.add('show'); }
    function closeModal(id) { document.getElementById(id).classList.remove('show'); }

    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); });
    });

    function openTambahStok(biId, nama, stok) {
        document.getElementById('ts_bi_id').value = biId;
        document.getElementById('ts_nama_item').textContent = nama;
        document.getElementById('ts_stok_skrg').textContent = stok;
        openModal('modalTambahStok');
    }

    function openKoreksiStok(biId, nama, stok) {
        document.getElementById('ks_bi_id').value = biId;
        document.getElementById('ks_nama_item').textContent = nama;
        document.getElementById('ks_stok_skrg').textContent = stok;
        document.getElementById('ks_stok_baru').value = stok;
        openModal('modalKoreksiStok');
    }

    function openUpdateMin(biId, nama, minVal) {
        document.getElementById('um_bi_id').value = biId;
        document.getElementById('um_nama_item').textContent = nama;
        document.getElementById('um_min_val').value = minVal;
        openModal('modalUpdateMin');
    }

    function hapusBarang(biId, nama) {
        Swal.fire({
            title: 'Hapus Barang?',
            html: 'Yakin hapus <strong>' + nama + '</strong> dari cabang ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((r) => {
            if (r.isConfirmed) window.location.href = '?hapus_bi=' + biId;
        });
    }

    function filterStok() {
        const q = document.getElementById('searchStok').value.toLowerCase();
        const rows = document.querySelectorAll('#tabelStok tbody tr');
        rows.forEach(r => {
            const txt = r.textContent.toLowerCase();
            r.style.display = txt.includes(q) ? '' : 'none';
        });
    }

    document.getElementById('inputNamaItem').addEventListener('input', function() {
        const opts = document.querySelectorAll('#masterItemList option');
        const val = this.value;
        opts.forEach(o => {
            if (o.value === val && o.dataset.satuan) {
                const sel = document.getElementById('inputSatuan');
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === o.dataset.satuan) { sel.selectedIndex = i; break; }
                }
            }
        });
    });
    </script>
    <script><?= $swal_script ?></script>
</body>
</html>