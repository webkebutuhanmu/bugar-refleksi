<?php
require_once '../config/database.php';
if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

$pesan = "";
$tipe = "";

// Tambah Cabang
if (isset($_POST['tambah'])) {
    $nama = htmlspecialchars($_POST['nama_cabang']);
    $alamat = htmlspecialchars($_POST['alamat']);
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];
    $pin = $_POST['pin'];
    
    $sql = "INSERT INTO branches (nama_cabang, alamat, latitude, longitude, pin) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$nama, $alamat, $lat, $lng, $pin])) {
        $pesan = "Cabang berhasil ditambahkan!";
        $tipe = "success";
    } else {
        $pesan = "Gagal menambahkan cabang!";
        $tipe = "danger";
    }
}

// Edit Cabang
if (isset($_POST['edit'])) {
    $id = $_POST['id'];
    $nama = htmlspecialchars($_POST['nama_cabang']);
    $alamat = htmlspecialchars($_POST['alamat']);
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];
    $pin = $_POST['pin'];
    
    $sql = "UPDATE branches SET nama_cabang = ?, alamat = ?, latitude = ?, longitude = ?, pin = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$nama, $alamat, $lat, $lng, $pin, $id])) {
        $pesan = "Cabang berhasil diupdate!";
        $tipe = "success";
    } else {
        $pesan = "Gagal mengupdate cabang!";
        $tipe = "danger";
    }
}

// Hapus Cabang
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    try {
        $pdo->beginTransaction();
        
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE branch_id = ?");
        $stmtCheck->execute([$id]);
        $hasTransactions = $stmtCheck->fetchColumn() > 0;
        
        if ($hasTransactions) {
            $pesan = "Tidak bisa menghapus cabang! Masih ada data transaksi terkait.";
            $tipe = "danger";
        } else {
            $pdo->prepare("DELETE FROM beds WHERE branch_id = ?")->execute([$id]);
            $sql = "DELETE FROM branches WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id])) {
                $pdo->commit();
                $pesan = "Cabang berhasil dihapus!";
                $tipe = "success";
            } else {
                $pdo->rollBack();
                $pesan = "Gagal menghapus cabang!";
                $tipe = "danger";
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $pesan = "Error: " . $e->getMessage();
        $tipe = "danger";
    }
}

$cabang = $pdo->query("SELECT b.*, 
                       (SELECT COUNT(*) FROM kasir_attendance ka WHERE ka.branch_id = b.id AND ka.status = 'aktif') as is_open,
                       (SELECT u.nama_lengkap FROM kasir_attendance ka JOIN users u ON ka.kasir_id = u.id WHERE ka.branch_id = b.id AND ka.status = 'aktif' LIMIT 1) as kasir_name,
                       (SELECT ka.waktu_masuk FROM kasir_attendance ka WHERE ka.branch_id = b.id AND ka.status = 'aktif' LIMIT 1) as waktu_buka,
                       (SELECT COUNT(*) FROM transactions t WHERE t.branch_id = b.id AND t.status = 'proses') as customer_aktif
                       FROM branches b 
                       ORDER BY b.id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Cabang - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <style>
        #map, #mapEdit { height: 400px; width: 100%; border-radius: 8px; margin-top: 10px; border: 2px solid var(--border-color); z-index: 1; }
        .map-instruction { background: var(--bg-input); padding: 12px; border-radius: 5px; margin-bottom: 10px; font-size: 13px; color: var(--text-dark); border-left: 4px solid var(--accent-yellow); }
        .coord-display { background: var(--bg-input); padding: 12px; border-radius: 5px; margin-top: 10px; font-family: monospace; font-size: 13px; color: var(--text-dark); }
        .search-box-custom { background: var(--bg-panel); padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid var(--border-color); }
        .search-label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark); font-size: 14px; }
        .manual-coords { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
        .coord-input-group { display: flex; flex-direction: column; }
        .coord-input-group label { font-size: 12px; color: var(--text-muted); margin-bottom: 5px; font-weight: 600; }
        .coord-input-group input { padding: 8px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-dark); border-radius: 5px; font-family: monospace; font-size: 13px; }
        .coord-input-group input:focus { outline: none; border-color: var(--accent-yellow); }
        .btn-apply-coords { grid-column: 1 / -1; background: var(--btn-primary); color: var(--btn-primary-txt); border: none; padding: 10px; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 5px; transition: 0.3s; }
        .btn-apply-coords:hover { background: var(--accent-yellow); color: #111; }
        .info-badge { display: inline-block; background: var(--bg-input); color: var(--text-muted); padding: 8px 12px; border-radius: 5px; font-size: 12px; margin-top: 10px; border: 1px dashed var(--border-color); }
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
                <a href="data_cabang.php" class="menu-item active">Data Cabang</a>
                <a href="data_leader.php" class="menu-item">Data Leader</a>
                <a href="data_kasir.php" class="menu-item">Data Kasir</a>
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
                    <h1>Data Cabang</h1>
                </div>
                <div class="topbar-right">
                    <button onclick="openModal('modalTambah')" class="btn btn-success">Tambah Cabang</button>
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <?php if($pesan): ?><div class="alert alert-<?= $tipe ?>"><?= $pesan ?></div><?php endif; ?>

            <div class="card">
                <div class="card-header">Daftar Cabang</div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Cabang</th>
                                <th>Alamat</th>
                                <th>Status</th>
                                <th>Kasir Aktif</th>
                                <th>Customer</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cabang as $i => $c): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($c['nama_cabang']) ?></strong></td>
                                <td><?= htmlspecialchars($c['alamat']) ?></td>
                                <td>
                                    <?php if($c['is_open'] > 0): ?>
                                        <span class="badge badge-success">BUKA</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">TUTUP</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($c['kasir_name']): ?>
                                        <strong><?= htmlspecialchars($c['kasir_name']) ?></strong><br>
                                        <small style="color: var(--text-muted);">Sejak: <?= date('H:i', strtotime($c['waktu_buka'])) ?></small>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($c['customer_aktif'] > 0): ?>
                                        <span class="badge badge-success"><?= $c['customer_aktif'] ?> Aktif</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap:5px;">
                                        <a href="detail_cabang.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm">Detail</a>
                                        <button onclick="editCabang(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nama_cabang']) ?>', '<?= htmlspecialchars($c['alamat']) ?>', <?= $c['latitude'] ?>, <?= $c['longitude'] ?>, '<?= $c['pin'] ?>')" class="btn btn-warning btn-sm">Edit</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="modalTambah" class="modal">
        <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
            <span class="close" onclick="closeModal('modalTambah')">&times;</span>
            <h2 style="margin-bottom:20px;">Tambah Cabang Baru</h2>
            <form method="POST" id="formTambah">
                <div class="form-group">
                    <label>Nama Cabang</label>
                    <input type="text" name="nama_cabang" class="form-control" required placeholder="Contoh: Cabang Jakarta Pusat">
                </div>
                <div class="form-group">
                    <label>Alamat Lengkap</label>
                    <textarea name="alamat" id="alamatInput" class="form-control" rows="3" required placeholder="Akan otomatis terisi saat Anda pilih lokasi di peta"></textarea>
                </div>
                <div class="map-instruction">
                    <strong>Cara Menentukan Lokasi:</strong> Gunakan kotak pencarian, klik pada peta, atau input manual koordinat.
                </div>
                <div id="map"></div>
                <div class="coord-display">
                    <div style="margin-bottom: 10px; font-weight: bold;">Koordinat Terpilih:</div>
                    Latitude: <span id="latDisplay" style="color: var(--accent-red); font-weight: bold;">-</span> | 
                    Longitude: <span id="lngDisplay" style="color: var(--accent-red); font-weight: bold;">-</span>
                </div>
                <div class="search-box-custom">
                    <label class="search-label">Input Manual Koordinat:</label>
                    <div class="manual-coords">
                        <div class="coord-input-group">
                            <label>Latitude</label>
                            <input type="text" id="manualLat" placeholder="-6.200000" step="any">
                        </div>
                        <div class="coord-input-group">
                            <label>Longitude</label>
                            <input type="text" id="manualLng" placeholder="106.816666" step="any">
                        </div>
                        <button type="button" class="btn-apply-coords" onclick="applyManualCoords('add')">Terapkan Koordinat ke Peta</button>
                    </div>
                </div>
                <input type="hidden" name="latitude" id="latitude" required>
                <input type="hidden" name="longitude" id="longitude" required>
                <div class="form-group">
                    <label>PIN Keamanan (6 Digit)</label>
                    <input type="text" name="pin" class="form-control" required maxlength="6" placeholder="Contoh: 123456" pattern="[0-9]{6}">
                    <small style="color: var(--text-muted); display:block; margin-top:5px;">PIN digunakan kasir untuk membuka shift di cabang ini</small>
                </div>
                <button type="submit" name="tambah" class="btn btn-success" style="width: 100%; padding: 12px; font-size: 14px;">Simpan Cabang</button>
            </form>
        </div>
    </div>

    <div id="modalEdit" class="modal">
        <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
            <span class="close" onclick="closeModal('modalEdit')">&times;</span>
            <h2 style="margin-bottom:20px;">Edit Cabang</h2>
            <form method="POST" id="formEdit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Nama Cabang</label>
                    <input type="text" name="nama_cabang" id="edit_nama" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Alamat Lengkap</label>
                    <textarea name="alamat" id="edit_alamat" class="form-control" rows="3" required></textarea>
                </div>
                <div id="mapEdit"></div>
                <div class="coord-display">
                    <div style="margin-bottom: 10px; font-weight: bold;">Koordinat Terpilih:</div>
                    Latitude: <span id="latDisplayEdit" style="color: var(--accent-red); font-weight: bold;">-</span> | 
                    Longitude: <span id="lngDisplayEdit" style="color: var(--accent-red); font-weight: bold;">-</span>
                </div>
                <div class="search-box-custom">
                    <label class="search-label">Input Manual Koordinat:</label>
                    <div class="manual-coords">
                        <div class="coord-input-group">
                            <label>Latitude</label>
                            <input type="text" id="manualLatEdit" placeholder="-6.200000" step="any">
                        </div>
                        <div class="coord-input-group">
                            <label>Longitude</label>
                            <input type="text" id="manualLngEdit" placeholder="106.816666" step="any">
                        </div>
                        <button type="button" class="btn-apply-coords" onclick="applyManualCoords('edit')">Terapkan Koordinat ke Peta</button>
                    </div>
                </div>
                <input type="hidden" name="latitude" id="edit_lat" required>
                <input type="hidden" name="longitude" id="edit_lng" required>
                <div class="form-group">
                    <label>PIN Keamanan (6 Digit)</label>
                    <input type="text" name="pin" id="edit_pin" class="form-control" required maxlength="6" pattern="[0-9]{6}">
                </div>
                <button type="submit" name="edit" class="btn btn-warning" style="width: 100%; padding: 12px; font-size: 14px;">Update Cabang</button>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    
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

        let map, marker, geocoder;
        let mapEdit, markerEdit, geocoderEdit;
        
        function initMapTambah() {
            const defaultPos = [-6.200000, 106.816666];
            map = L.map('map').setView(defaultPos, 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(map);
            marker = L.marker(defaultPos, { draggable: true }).addTo(map);
            
            marker.on('dragend', function(e) {
                const latlng = e.target.getLatLng();
                updateCoordinates(latlng.lat, latlng.lng, 'add');
                reverseGeocode(latlng.lat, latlng.lng, 'add');
            });
            
            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                updateCoordinates(e.latlng.lat, e.latlng.lng, 'add');
                reverseGeocode(e.latlng.lat, e.latlng.lng, 'add');
            });
            
            geocoder = L.Control.geocoder({
                defaultMarkGeocode: false, placeholder: 'Cari alamat di sini...', errorMessage: 'Alamat tidak ditemukan',
                collapsed: false, geocoder: L.Control.Geocoder.nominatim({ geocodingQueryParams: { countrycodes: 'id', addressdetails: 1 } })
            }).on('markgeocode', function(e) {
                const latlng = e.geocode.center;
                map.setView(latlng, 16); marker.setLatLng(latlng);
                updateCoordinates(latlng.lat, latlng.lng, 'add');
                document.getElementById('alamatInput').value = e.geocode.name;
            }).addTo(map);
        }
        
        function initMapEdit(lat, lng) {
            const position = [lat, lng];
            mapEdit = L.map('mapEdit').setView(position, 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(mapEdit);
            markerEdit = L.marker(position, { draggable: true }).addTo(mapEdit);
            
            markerEdit.on('dragend', function(e) {
                const latlng = e.target.getLatLng();
                updateCoordinates(latlng.lat, latlng.lng, 'edit');
                reverseGeocode(latlng.lat, latlng.lng, 'edit');
            });
            
            mapEdit.on('click', function(e) {
                markerEdit.setLatLng(e.latlng);
                updateCoordinates(e.latlng.lat, e.latlng.lng, 'edit');
                reverseGeocode(e.latlng.lat, e.latlng.lng, 'edit');
            });
            
            geocoderEdit = L.Control.geocoder({
                defaultMarkGeocode: false, placeholder: 'Cari alamat baru...', errorMessage: 'Alamat tidak ditemukan',
                collapsed: false, geocoder: L.Control.Geocoder.nominatim({ geocodingQueryParams: { countrycodes: 'id', addressdetails: 1 } })
            }).on('markgeocode', function(e) {
                const latlng = e.geocode.center;
                mapEdit.setView(latlng, 16); markerEdit.setLatLng(latlng);
                updateCoordinates(latlng.lat, latlng.lng, 'edit');
                document.getElementById('edit_alamat').value = e.geocode.name;
            }).addTo(mapEdit);
        }
        
        function updateCoordinates(lat, lng, mode) {
            if (mode === 'add') {
                document.getElementById('latitude').value = lat; document.getElementById('longitude').value = lng;
                document.getElementById('latDisplay').textContent = lat.toFixed(6); document.getElementById('lngDisplay').textContent = lng.toFixed(6);
                document.getElementById('manualLat').value = lat.toFixed(6); document.getElementById('manualLng').value = lng.toFixed(6);
            } else {
                document.getElementById('edit_lat').value = lat; document.getElementById('edit_lng').value = lng;
                document.getElementById('latDisplayEdit').textContent = lat.toFixed(6); document.getElementById('lngDisplayEdit').textContent = lng.toFixed(6);
                document.getElementById('manualLatEdit').value = lat.toFixed(6); document.getElementById('manualLngEdit').value = lng.toFixed(6);
            }
        }
        
        function reverseGeocode(lat, lng, mode) {
            fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&addressdetails=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.display_name) {
                        if (mode === 'add') document.getElementById('alamatInput').value = data.display_name;
                        else document.getElementById('edit_alamat').value = data.display_name;
                    }
                }).catch(err => console.log('Geocoding error:', err));
        }
        
        function applyManualCoords(mode) {
            let lat, lng;
            if (mode === 'add') {
                lat = parseFloat(document.getElementById('manualLat').value);
                lng = parseFloat(document.getElementById('manualLng').value);
                if (isNaN(lat) || isNaN(lng)) return alert('Masukkan koordinat valid!');
                map.setView([lat, lng], 15); marker.setLatLng([lat, lng]);
                updateCoordinates(lat, lng, 'add'); reverseGeocode(lat, lng, 'add');
            } else {
                lat = parseFloat(document.getElementById('manualLatEdit').value);
                lng = parseFloat(document.getElementById('manualLngEdit').value);
                if (isNaN(lat) || isNaN(lng)) return alert('Masukkan koordinat valid!');
                mapEdit.setView([lat, lng], 15); markerEdit.setLatLng([lat, lng]);
                updateCoordinates(lat, lng, 'edit'); reverseGeocode(lat, lng, 'edit');
            }
        }
        
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
            if (id === 'modalTambah') {
                setTimeout(() => { if (!map) initMapTambah(); else map.invalidateSize(); }, 300);
            }
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
            if (id === 'modalTambah') {
                document.getElementById('formTambah').reset();
                document.getElementById('latDisplay').textContent = '-'; document.getElementById('lngDisplay').textContent = '-';
                if (marker) marker.setLatLng([-6.200000, 106.816666]);
            }
        }
        
        function editCabang(id, nama, alamat, lat, lng, pin) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_alamat').value = alamat;
            document.getElementById('edit_lat').value = lat;
            document.getElementById('edit_lng').value = lng;
            document.getElementById('edit_pin').value = pin;
            document.getElementById('latDisplayEdit').textContent = parseFloat(lat).toFixed(6);
            document.getElementById('lngDisplayEdit').textContent = parseFloat(lng).toFixed(6);
            document.getElementById('manualLatEdit').value = parseFloat(lat).toFixed(6);
            document.getElementById('manualLngEdit').value = parseFloat(lng).toFixed(6);
            
            openModal('modalEdit');
            setTimeout(() => {
                if (!mapEdit) initMapEdit(lat, lng);
                else { mapEdit.setView([lat, lng], 15); markerEdit.setLatLng([lat, lng]); mapEdit.invalidateSize(); }
            }, 300);
        }
        
        window.onclick = function(event) { if (event.target.classList.contains('modal')) event.target.style.display = 'none'; }
        
        document.getElementById('formTambah').addEventListener('submit', function(e) {
            if (!document.getElementById('latitude').value || !document.getElementById('longitude').value) {
                e.preventDefault(); alert('Silakan tentukan lokasi cabang pada peta.');
            }
        });
    </script>
</body>
</html>