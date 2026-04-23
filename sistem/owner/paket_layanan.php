<?php
require_once '../config/database.php';
if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

$pesan = ""; $tipe = "";

function insertBranchNotif($pdo, $type, $ref_id, $judul, $pesan_notif, $branch_id = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO branch_notifications (type, branch_id, ref_id, judul, pesan) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$type, $branch_id, $ref_id, $judul, $pesan_notif]);
    } catch (Exception $e) {}
}

function savePackageItems($pdo, $package_id, $item_ids, $item_qty) {
    $pdo->prepare("DELETE FROM package_items WHERE package_id = ?")->execute([$package_id]);
    if (!empty($item_ids)) {
        $stmtIns = $pdo->prepare("INSERT INTO package_items (package_id, item_id, jumlah) VALUES (?, ?, ?)");
        for ($i = 0; $i < count($item_ids); $i++) {
            $iid = intval($item_ids[$i]); $qty = intval($item_qty[$i] ?? 1);
            if ($iid > 0 && $qty > 0) { $stmtIns->execute([$package_id, $iid, $qty]); }
        }
    }
}

if (isset($_POST['tambah'])) {
    $nama      = htmlspecialchars($_POST['nama_paket']);
    $deskripsi = htmlspecialchars($_POST['deskripsi']);
    $durasi    = $_POST['durasi_menit'];
    $harga     = $_POST['harga'];
    $is_paket  = intval($_POST['is_paket'] ?? 1);
    if (!in_array($is_paket, [0, 1, 2])) $is_paket = 1;
    
    $sql  = "INSERT INTO packages (nama_paket, deskripsi, durasi_menit, harga, is_paket) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$nama, $deskripsi, $durasi, $harga, $is_paket])) {
        $new_id = $pdo->lastInsertId();
        savePackageItems($pdo, $new_id, $_POST['item_ids'] ?? [], $_POST['item_qty'] ?? []);
        $pesan = "Data berhasil ditambahkan!"; $tipe = "success";
        insertBranchNotif($pdo, 'paket_baru', $new_id, "Paket Baru: $nama", "Telah ditambahkan oleh Owner.", null);
    } else {
        $pesan = "Gagal menambahkan data!"; $tipe = "danger";
    }
}

if (isset($_POST['edit'])) {
    $id        = $_POST['id'];
    $nama      = htmlspecialchars($_POST['nama_paket']);
    $deskripsi = htmlspecialchars($_POST['deskripsi']);
    $durasi    = $_POST['durasi_menit'];
    $harga     = $_POST['harga'];
    $is_paket  = intval($_POST['is_paket'] ?? 1);
    if (!in_array($is_paket, [0, 1, 2])) $is_paket = 1;
    
    $sql  = "UPDATE packages SET nama_paket = ?, deskripsi = ?, durasi_menit = ?, harga = ?, is_paket = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$nama, $deskripsi, $durasi, $harga, $is_paket, $id])) {
        savePackageItems($pdo, $id, $_POST['item_ids'] ?? [], $_POST['item_qty'] ?? []);
        $pesan = "Data berhasil diupdate!"; $tipe = "success";
        insertBranchNotif($pdo, 'paket_update', $id, "Paket Diupdate: $nama", "Telah diperbarui oleh Owner.", null);
    } else {
        $pesan = "Gagal mengupdate data!"; $tipe = "danger";
    }
}

if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    $sql  = "DELETE FROM packages WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$id])) {
        $pesan = "Data berhasil dihapus!"; $tipe = "success";
    } else {
        $pesan = "Gagal menghapus data!"; $tipe = "danger";
    }
}

$tabActive = $_GET['tab'] ?? 'paket';
$tipeLabels = ['paket'=>'Paket Layanan','nonpaket'=>'Layanan Non-Paket','hotel'=>'Paket Hotel'];
$tabLabel = $tipeLabels[$tabActive] ?? 'Paket Layanan';

if ($tabActive == 'nonpaket') {
    $paket = $pdo->query("SELECT * FROM packages WHERE is_paket = 0 ORDER BY harga ASC")->fetchAll();
} elseif ($tabActive == 'hotel') {
    $paket = $pdo->query("SELECT * FROM packages WHERE is_paket = 2 ORDER BY harga ASC")->fetchAll();
} else {
    $paket = $pdo->query("SELECT * FROM packages WHERE is_paket = 1 ORDER BY harga ASC")->fetchAll();
}

$allItems = $pdo->query("SELECT * FROM items ORDER BY nama_item ASC")->fetchAll();
$packageItemsMap = [];
$stmtPI = $pdo->query("SELECT pi.*, i.nama_item, i.satuan FROM package_items pi JOIN items i ON pi.item_id = i.id ORDER BY pi.package_id, i.nama_item");
foreach ($stmtPI->fetchAll() as $pi) {
    $packageItemsMap[$pi['package_id']][] = $pi;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paket Layanan - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <style>
        .packages-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .package-card { background: var(--bg-form); border-radius: 12px; padding: 20px; box-shadow: 0 4px 10px var(--shadow-color); border: 1px solid var(--border-color); }
        .package-name { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--text-dark); margin-bottom: 10px; }
        .package-description { color: var(--text-muted); font-size: 13px; margin-bottom: 15px; }
        
        .tab-navigation { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); overflow-x:auto; }
        .tab-item { padding: 12px 25px; cursor: pointer; border-radius: 8px 8px 0 0; font-weight: 600; font-size: 13px; color: var(--text-muted); }
        .tab-item.active { background: var(--accent-yellow); color: #111; border-bottom: 3px solid var(--accent-red); }
        
        .item-row { display: flex; gap: 10px; margin-bottom: 10px; }
        .item-row select, .item-row input { flex:1; }
        .pkg-item-tag { display: inline-block; background: var(--bg-input); padding: 4px 10px; border-radius: 12px; font-size: 11px; margin-right:5px; margin-bottom:5px; border: 1px solid var(--border-color); color:var(--text-dark); }
        .pkg-item-tag .qty { color: var(--accent-red); font-weight: bold; margin-left: 5px; }
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
                <a href="data_absensi_owner.php" class="menu-item">Data Absensi</a>
                <a href="pelanggaran_owner.php" class="menu-item">Pelanggaran</a>
                <div class="has-submenu">
                    <div class="submenu-toggle active open" onclick="toggleSubmenu(this)">
                        <span>Paket & Pengaturan</span>
                        <span class="arrow">▶</span>
                    </div>
                    <div class="submenu-items open">
                        <a href="paket_layanan.php?tab=paket" class="submenu-item <?= $tabActive == 'paket' ? 'active' : '' ?>">Paket</a>
                        <a href="paket_layanan.php?tab=nonpaket" class="submenu-item <?= $tabActive == 'nonpaket' ? 'active' : '' ?>">Non-Paket</a>
                        <a href="paket_layanan.php?tab=hotel" class="submenu-item <?= $tabActive == 'hotel' ? 'active' : '' ?>">Paket Hotel</a>
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
                    <h1>Paket Layanan</h1>
                </div>
                <div class="topbar-right">
                    <button class="btn btn-primary" onclick="openModal('modalTambah')">Tambah <?= $tabLabel ?></button>
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <?php if ($pesan): ?><div class="alert alert-<?= $tipe ?>"><?= $pesan ?></div><?php endif; ?>

            <div class="tab-navigation">
                <a href="?tab=paket" class="tab-item <?= $tabActive == 'paket' ? 'active' : '' ?>">Paket Layanan</a>
                <a href="?tab=nonpaket" class="tab-item <?= $tabActive == 'nonpaket' ? 'active' : '' ?>">Layanan Non-Paket</a>
                <a href="?tab=hotel" class="tab-item <?= $tabActive == 'hotel' ? 'active' : '' ?>">Paket Hotel</a>
            </div>

            <div class="packages-grid">
                <?php foreach($paket as $p): $pkgItems = $packageItemsMap[$p['id']] ?? []; ?>
                <div class="package-card">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span class="badge" style="background:var(--bg-input); color:var(--text-muted);">ID: <?= $p['id'] ?></span>
                        <strong style="color:var(--accent-red2); font-size:18px;">Rp <?= number_format($p['harga'], 0, ',', '.') ?></strong>
                    </div>
                    <div class="package-name"><?= htmlspecialchars($p['nama_paket']) ?></div>
                    <div class="package-description"><?= nl2br(htmlspecialchars($p['deskripsi'])) ?></div>
                    <div style="margin-bottom:15px; font-size:12px; font-weight:600; color:var(--text-muted);">
                        Durasi: <?= $p['durasi_menit'] ?> Menit
                    </div>
                    
                    <?php if (!empty($pkgItems)): ?>
                    <div style="margin-bottom:15px; border-top:1px dashed var(--border-color); padding-top:10px;">
                        <span style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px;">Barang Dipakai:</span>
                        <?php foreach($pkgItems as $pi): ?>
                        <span class="pkg-item-tag"><?= htmlspecialchars($pi['nama_item']) ?> <span class="qty"><?= $pi['jumlah'] ?> <?= $pi['satuan'] ?></span></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button class="btn btn-warning btn-edit" style="flex:1;" 
                            data-id="<?= $p['id'] ?>" data-nama="<?= htmlspecialchars($p['nama_paket']) ?>" 
                            data-deskripsi="<?= htmlspecialchars($p['deskripsi']) ?>" data-durasi="<?= $p['durasi_menit'] ?>" 
                            data-harga="<?= $p['harga'] ?>" data-ispaket="<?= $p['is_paket'] ?>" 
                            data-items='<?= json_encode($pkgItems) ?>'>Edit</button>
                        <a href="?hapus=<?= $p['id'] ?>&tab=<?= $tabActive ?>" class="btn btn-danger" onclick="return confirm('Hapus?')" style="flex:1;">Hapus</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('modalTambah')">&times;</span>
            <h2 style="margin-bottom:20px;">Tambah Item Baru</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Tipe Layanan</label>
                    <select name="is_paket" class="form-control">
                        <option value="1" <?= $tabActive == 'paket' ? 'selected' : '' ?>>Paket Layanan</option>
                        <option value="0" <?= $tabActive == 'nonpaket' ? 'selected' : '' ?>>Non-Paket</option>
                        <option value="2" <?= $tabActive == 'hotel' ? 'selected' : '' ?>>Paket Hotel</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nama Layanan</label>
                    <input type="text" name="nama_paket" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="3" required></textarea>
                </div>
                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Durasi (Menit)</label>
                        <input type="number" name="durasi_menit" class="form-control" required min="1">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Harga (Rp)</label>
                        <input type="number" name="harga" class="form-control" required min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Barang yang Dipakai (Opsional)</label>
                    <div id="tambahItemContainer"></div>
                    <button type="button" class="btn btn-secondary" onclick="addItemRow('tambahItemContainer')" style="margin-top:10px;">+ Tambah Barang</button>
                </div>
                <button type="submit" name="tambah" class="btn btn-success" style="width:100%; margin-top:15px;">Simpan Data</button>
            </form>
        </div>
    </div>

    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('modalEdit')">&times;</span>
            <h2 style="margin-bottom:20px;">Edit Item</h2>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Tipe Layanan</label>
                    <select name="is_paket" id="edit_is_paket" class="form-control">
                        <option value="1">Paket Layanan</option>
                        <option value="0">Non-Paket</option>
                        <option value="2">Paket Hotel</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nama Layanan</label>
                    <input type="text" name="nama_paket" id="edit_nama" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3" required></textarea>
                </div>
                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Durasi (Menit)</label>
                        <input type="number" name="durasi_menit" id="edit_durasi" class="form-control" required min="1">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Harga (Rp)</label>
                        <input type="number" name="harga" id="edit_harga" class="form-control" required min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Barang yang Dipakai (Opsional)</label>
                    <div id="editItemContainer"></div>
                    <button type="button" class="btn btn-secondary" onclick="addItemRow('editItemContainer')" style="margin-top:10px;">+ Tambah Barang</button>
                </div>
                <button type="submit" name="edit" class="btn btn-warning" style="width:100%; margin-top:15px;">Update Data</button>
            </form>
        </div>
    </div>

    <script>
        // Global Scripting
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
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.style.display = 'none'; }

        // Dynamic Items Handler
        const masterItems = <?= json_encode($allItems) ?>;
        function buildItemOptions(selectedId) {
            let html = '<option value="">-- Pilih Barang --</option>';
            masterItems.forEach(item => {
                const sel = (item.id == selectedId) ? 'selected' : '';
                html += `<option value="${item.id}" ${sel}>${item.nama_item} (${item.satuan})</option>`;
            });
            return html;
        }
        function addItemRow(containerId, itemId, qty) {
            const container = document.getElementById(containerId);
            const row = document.createElement('div');
            row.className = 'item-row';
            row.innerHTML = `
                <select name="item_ids[]" class="form-control">${buildItemOptions(itemId || '')}</select>
                <input type="number" name="item_qty[]" class="form-control" value="${qty || 1}" min="1" style="max-width:80px;">
                <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">X</button>
            `;
            container.appendChild(row);
        }

        // Edit Button Logic
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_id').value = this.dataset.id;
                document.getElementById('edit_nama').value = this.dataset.nama;
                document.getElementById('edit_deskripsi').value = this.dataset.deskripsi;
                document.getElementById('edit_durasi').value = this.dataset.durasi;
                document.getElementById('edit_harga').value = this.dataset.harga;
                document.getElementById('edit_is_paket').value = this.dataset.ispaket;
                
                const container = document.getElementById('editItemContainer');
                container.innerHTML = '';
                try {
                    const items = JSON.parse(this.dataset.items);
                    if (items && items.length > 0) items.forEach(item => addItemRow('editItemContainer', item.item_id, item.jumlah));
                } catch(e) {}
                openModal('modalEdit');
            });
        });
    </script>
</body>
</html>