<?php
// File: kasir/input_transaksi.php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) { 
    header("Location: pilih_cabang.php"); exit; 
}

$kasir_id = $_SESSION['user_id'];
$branch_id = $_SESSION['active_branch'];
$nama_cabang = $pdo->query("SELECT nama_cabang FROM branches WHERE id = $branch_id")->fetchColumn();
$nama_kasir = $_SESSION['nama'];

$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$foto_profil = $stmtProfil->fetchColumn();
$foto_profil = (!empty($foto_profil) && file_exists("../uploads/profil/" . $foto_profil)) ? "../uploads/profil/" . $foto_profil : "../assets/default_user.png";

$swal_script = "";

// =====================================================
// LOGIC PENGECEKAN STOK & KETERSEDIAAN PAKET
// =====================================================
$stmtStock = $pdo->prepare("SELECT item_id, stok FROM branch_items WHERE branch_id = ?");
$stmtStock->execute([$branch_id]);
$branchStocks = [];
while ($row = $stmtStock->fetch(PDO::FETCH_ASSOC)) { $branchStocks[$row['item_id']] = (int)$row['stok']; }

$itemNames = [];
$stmtItems = $pdo->query("SELECT id, nama_item FROM items");
while ($row = $stmtItems->fetch(PDO::FETCH_ASSOC)) { $itemNames[$row['id']] = $row['nama_item']; }

$pkgRequirements = [];
$allRequiredItems = [];
$stmtPkgItems = $pdo->query("SELECT package_id, item_id, jumlah FROM package_items");
while ($row = $stmtPkgItems->fetch(PDO::FETCH_ASSOC)) {
    $pkgRequirements[$row['package_id']][] = ['item_id' => (int)$row['item_id'], 'jumlah' => (int)$row['jumlah']];
    $allRequiredItems[] = (int)$row['item_id'];
}
$allRequiredItems = array_unique($allRequiredItems);

$problematicItems = []; 
foreach ($allRequiredItems as $reqItemId) {
    if (!isset($branchStocks[$reqItemId]) || $branchStocks[$reqItemId] <= 0) {
        $name = $itemNames[$reqItemId] ?? 'Item ID #'.$reqItemId;
        if (!isset($branchStocks[$reqItemId])) $problematicItems[] = "{$name} (Belum terdaftar di cabang)";
        else $problematicItems[] = "{$name} (Habis)";
    }
}

function isPackageAvailable($pkgId, $requirements, $stocks) {
    if (!isset($requirements[$pkgId])) return true;
    foreach ($requirements[$pkgId] as $req) {
        $itemId = $req['item_id']; $qtyNeeded = $req['jumlah'];
        if (!isset($stocks[$itemId]) || $stocks[$itemId] < $qtyNeeded) return false;
    }
    return true;
}

// --- LOGIC SUBMIT TRANSAKSI ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_submit']) && $_POST['action_submit'] == 'transaksi') {
    try {
        $package_id = $_POST['package_id'] ?? '';
        $bed_id = $_POST['bed_id'] ?? '';
        $nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $payment_mode = $_POST['payment_mode'] ?? 'bayar_nanti'; 
        
        if (!isPackageAvailable($package_id, $pkgRequirements, $branchStocks)) {
            throw new Exception("Stok barang untuk paket ini tidak mencukupi atau barang belum terdaftar di cabang!");
        }

        $terapis_id = 0; $is_external = 0; $terapis_home_branch = 0;
        $raw_external = trim($_POST['terapis_id_external'] ?? '');
        $raw_lokal    = trim($_POST['terapis_id_lokal'] ?? '');

        if ($raw_external !== '' && intval($raw_external) > 0) {
            $terapis_id = intval($raw_external); $is_external = 1; $terapis_home_branch = intval($_POST['terapis_home_branch'] ?? 0);
        } elseif ($raw_lokal !== '' && intval($raw_lokal) > 0) {
            $terapis_id = intval($raw_lokal); $is_external = 0;
        }

        $tipe_transaksi = $_POST['tipe_transaksi'] ?? 'datang';
        $alamat_panggilan = trim($_POST['alamat_panggilan'] ?? '');
        $biaya_driver = floatval($_POST['biaya_driver'] ?? 0);

        if ($terapis_id <= 0) throw new Exception("Pilih Terapis!");
        if (empty($package_id)) throw new Exception("Pilih Paket Layanan!");
        if ($tipe_transaksi === 'datang' && empty($bed_id)) throw new Exception("Pilih Bed!");
        if ($tipe_transaksi === 'panggilan' && empty($alamat_panggilan)) throw new Exception("Alamat panggilan wajib diisi!");
        if (empty($nama_pelanggan)) throw new Exception("Nama Pelanggan wajib diisi!");

        $stmtCekTerapis = $pdo->prepare("SELECT id, nama_lengkap FROM users WHERE id = ? AND role = 'terapis'");
        $stmtCekTerapis->execute([$terapis_id]);
        $dataTerapis = $stmtCekTerapis->fetch();
        if (!$dataTerapis) throw new Exception("Terapis tidak ditemukan!");

        $stmtBusy = $pdo->prepare("SELECT COUNT(*) as busy_count FROM transactions WHERE terapis_id = ? AND status IN ('proses', 'menunggu_approval', 'menunggu_pembayaran')");
        $stmtBusy->execute([$terapis_id]);
        if ($stmtBusy->fetch()['busy_count'] > 0) throw new Exception("Terapis sedang sibuk.");

        $pdo->beginTransaction();

        $stmtPkg = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
        $stmtPkg->execute([$package_id]);
        $pkg = $stmtPkg->fetch();
        if(!$pkg) throw new Exception("Paket tidak valid.");

        $harga = $pkg['harga']; $durasi_menit = $pkg['durasi_menit'];
        $settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
        $jam_sekarang = date('H:i:s');
        
        if ($jam_sekarang >= $settings['shift_pagi_start'] && $jam_sekarang <= $settings['shift_pagi_end']) {
            $jenis_shift = 'pagi'; $persen_cabang = $settings['pagi_share_company']; $persen_terapis = $settings['pagi_share_therapist'];
        } else {
            $jenis_shift = 'malam'; $persen_cabang = $settings['malam_share_company']; $persen_terapis = $settings['malam_share_therapist'];
        }
        
        $omset_cabang = $harga * ($persen_cabang / 100); $omset_terapis = $harga * ($persen_terapis / 100);

        if ($payment_mode === 'bayar_sekarang') { $status_transaksi = 'proses'; $payment_status = 'unpaid'; $metode_pembayaran = null; } 
        else { $status_transaksi = 'proses'; $payment_status = 'unpaid'; $metode_pembayaran = 'bayar_nanti'; }
        
        $waktu_mulai = date('Y-m-d H:i:s');
        $waktu_selesai = date('Y-m-d H:i:s', strtotime("+$durasi_menit minutes"));
        $tanggal_bisnis = ($jam_sekarang >= ($settings['jam_mulai_hari'] ?? '08:00:00')) ? date('Y-m-d') : date('Y-m-d', strtotime('-1 day'));
        $total_bayar_final = $harga + $biaya_driver;
        $bed_id_insert = ($tipe_transaksi === 'panggilan') ? null : $bed_id;

        $sqlInsert = "INSERT INTO transactions (kasir_id, branch_id, terapis_id, package_id, bed_id, nama_pelanggan, no_hp_pelanggan, waktu_mulai, waktu_selesai, durasi_menit, total_bayar, omset_terapis, omset_cabang, jenis_shift, tanggal_transaksi, status, payment_status, metode_pembayaran, tipe_transaksi, alamat_panggilan, biaya_driver) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sqlInsert)->execute([$kasir_id, $branch_id, $terapis_id, $package_id, $bed_id_insert, $nama_pelanggan, $no_hp, $waktu_mulai, $waktu_selesai, $durasi_menit, $total_bayar_final, $omset_terapis, $omset_cabang, $jenis_shift, $tanggal_bisnis, $status_transaksi, $payment_status, $metode_pembayaran, $tipe_transaksi, ($tipe_transaksi === 'panggilan' ? $alamat_panggilan : null), $biaya_driver]);
        $transaction_id = $pdo->lastInsertId();

        if ($tipe_transaksi === 'datang' && !empty($bed_id)) $pdo->prepare("UPDATE beds SET status = 'terisi' WHERE id = ?")->execute([$bed_id]);

        $stokWarnings = [];
        $stmtPkgItems = $pdo->prepare("SELECT pi.*, i.nama_item, i.satuan FROM package_items pi JOIN items i ON pi.item_id = i.id WHERE pi.package_id = ?");
        $stmtPkgItems->execute([$package_id]);
        foreach ($stmtPkgItems->fetchAll() as $pkgItem) {
            $stmtCekStok = $pdo->prepare("SELECT bi.id, bi.stok, bi.stok_minimum FROM branch_items bi WHERE bi.branch_id = ? AND bi.item_id = ?");
            $stmtCekStok->execute([$branch_id, $pkgItem['item_id']]);
            $branchItem = $stmtCekStok->fetch();
            if ($branchItem) {
                $stokSetelah = $branchItem['stok'] - $pkgItem['jumlah'];
                $pdo->prepare("UPDATE branch_items SET stok = stok - ? WHERE id = ?")->execute([$pkgItem['jumlah'], $branchItem['id']]);
                $pdo->prepare("INSERT INTO item_usage_log (branch_id, item_id, transaction_id, jumlah, tipe, keterangan, created_by) VALUES (?, ?, ?, ?, 'pakai', ?, ?)")->execute([$branch_id, $pkgItem['item_id'], $transaction_id, -$pkgItem['jumlah'], 'Paket: ' . $pkg['nama_paket'], $kasir_id]);
                if ($stokSetelah <= 0) $stokWarnings[] = $pkgItem['nama_item'] . ' HABIS! (sisa: ' . $stokSetelah . ')';
                elseif ($stokSetelah <= $branchItem['stok_minimum']) $stokWarnings[] = $pkgItem['nama_item'] . ' tinggal ' . $stokSetelah . ' ' . $pkgItem['satuan'];
            }
        }

        if ($is_external == 1) {
            $pdo->prepare("INSERT INTO terapis_loans (terapis_id, transaction_id, from_branch_id, to_branch_id, loan_time, status, approved_at, approved_by) VALUES (?, ?, ?, ?, NOW(), 'active', NOW(), ?)")->execute([$terapis_id, $transaction_id, $terapis_home_branch, $branch_id, $kasir_id]);
            $loan_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO terapis_loan_notifications (loan_id, from_branch_id, to_branch_id, terapis_id, transaction_id, status, created_at, read_at) VALUES (?, ?, ?, ?, ?, 'read', NOW(), NOW())")->execute([$loan_id, $terapis_home_branch, $branch_id, $terapis_id, $transaction_id]);
        }

        // Logic Giliran Dilompati
        $skip_terapis_id = intval($_POST['skip_terapis_id'] ?? 0);
        $skip_keterangan = trim($_POST['skip_keterangan'] ?? '');
        if ($skip_terapis_id > 0 && !empty($skip_keterangan)) {
            $stSkipName = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
            $stSkipName->execute([$skip_terapis_id]);
            $skipNama = $stSkipName->fetchColumn() ?: 'Terapis #'.$skip_terapis_id;
            $stLeader = $pdo->prepare("SELECT id FROM users WHERE role = 'leader' AND branch_id = ? LIMIT 1");
            $stLeader->execute([$branch_id]);
            $leaderId = $stLeader->fetchColumn() ?: $kasir_id;
            $pdo->prepare("INSERT INTO pelanggaran (terapis_id, branch_id, kategori, judul, deskripsi, tanggal, waktu_kejadian, status, created_by) VALUES (?, ?, 'tolak_pasien', ?, ?, ?, ?, 'aktif', ?)")->execute([$skip_terapis_id, $branch_id, "Tolak Pasien", "Terapis $skipNama dilompati. Keterangan kasir: $skip_keterangan.", date('Y-m-d'), date('H:i:s'), $leaderId]);
        }
        $pdo->commit();

        if ($payment_mode === 'bayar_sekarang') {
            if (!empty($stokWarnings)) $_SESSION['stok_warnings'] = $stokWarnings;
            header("Location: proses_pembayaran.php?transaction_id=" . $transaction_id); exit;
        } else {
            $pesan_sukses = $is_external == 1 ? "Pijatan dimulai (Terapis Cabang Lain)!" : "Pijatan dimulai!";
            $swal_script = "Swal.fire({ title: 'Berhasil!', html: '" . addslashes($pesan_sukses) . "<br><small>Pembayaran dilakukan saat selesai</small>', icon: 'success', timer: 2000, showConfirmButton: false }).then(() => window.location.href = 'dashboard_kasir.php');";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $swal_script = "Swal.fire('Gagal!', '" . addslashes($e->getMessage()) . "', 'error');";
    }
}

// --- DATA PENDUKUNG ---
$settingPeriode = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulaiBisnis = $settingPeriode['jam_mulai_hari'] ?? '08:00:00';
$jamSekarang = date('H:i:s');
$tglBisnis = ($jamSekarang < $jamMulaiBisnis) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$start_periode = "$tglBisnis $jamMulaiBisnis";
$end_periode   = date('Y-m-d H:i:s', strtotime("$start_periode +1 day"));

$packages = $pdo->query("SELECT * FROM packages ORDER BY harga ASC")->fetchAll();

$adaTabelAbsen = $pdo->query("SHOW TABLES LIKE 'terapis_attendance'")->rowCount() > 0;
$adaTabelIzin  = $pdo->query("SHOW TABLES LIKE 'terapis_izin'")->rowCount() > 0;
$absenHariIni = []; $izinHariIniMap = [];

if ($adaTabelAbsen) {
    // UPDATE: Menambahkan pemanggilan kolom waktu_keluar dan update ORDER BY
    $sqlGiliran = "SELECT u.id, u.nama_lengkap, 
                  (SELECT COUNT(*) FROM transactions t WHERE t.terapis_id = u.id AND t.status IN ('proses', 'menunggu_approval', 'menunggu_pembayaran')) as is_busy, 
                  (SELECT COUNT(*) FROM terapis_loans tl JOIN transactions tlt ON tl.transaction_id = tlt.id WHERE tl.terapis_id = u.id AND tl.from_branch_id = ? AND tl.status IN ('active', 'pending') AND tlt.status IN ('proses', 'menunggu_approval', 'menunggu_pembayaran')) as is_loaned, 
                  (SELECT COUNT(*) FROM transactions t2 WHERE t2.terapis_id = u.id AND t2.created_at >= ? AND t2.created_at < ? AND t2.status != 'batal') as kerja_hari_ini, 
                  (SELECT MAX(t3.waktu_selesai) FROM transactions t3 WHERE t3.terapis_id = u.id AND t3.created_at >= ? AND t3.created_at < ? AND t3.status IN ('selesai','proses','menunggu_pembayaran')) as last_selesai, 
                  ta.giliran as giliran_absen, ta.waktu_absen, ta.waktu_keluar 
                  FROM users u LEFT JOIN terapis_attendance ta ON u.id = ta.terapis_id AND ta.branch_id = ? AND ta.tanggal = ? 
                  WHERE u.role = 'terapis' AND u.home_branch_id = ? 
                  ORDER BY (ta.id IS NULL) ASC, (ta.waktu_keluar IS NOT NULL) ASC, kerja_hari_ini ASC, IFNULL(ta.giliran, 9999) ASC, last_selesai ASC, u.nama_lengkap ASC";
    $stmtGiliran = $pdo->prepare($sqlGiliran);
    $stmtGiliran->execute([$branch_id, $start_periode, $end_periode, $start_periode, $end_periode, $branch_id, $tglBisnis, $branch_id]);
    $giliranTerapis = $stmtGiliran->fetchAll();
    
    $stmtAbsen = $pdo->prepare("SELECT terapis_id FROM terapis_attendance WHERE branch_id = ? AND tanggal = ?");
    $stmtAbsen->execute([$branch_id, $tglBisnis]);
    foreach ($stmtAbsen->fetchAll() as $ab) { $absenHariIni[$ab['terapis_id']] = true; }
} else {
    $giliranTerapis = [];
}

if ($adaTabelIzin) {
    $stmtIzinTrx = $pdo->prepare("SELECT terapis_id, jenis, status FROM terapis_izin WHERE branch_id = ? AND tanggal = ? AND status = 'disetujui'");
    $stmtIzinTrx->execute([$branch_id, $tglBisnis]);
    foreach ($stmtIzinTrx->fetchAll() as $iz) { $izinHariIniMap[$iz['terapis_id']] = $iz; }
}

$stmtBeds = $pdo->prepare("SELECT * FROM beds WHERE branch_id = ? AND status = 'kosong' ORDER BY nomor_bed ASC");
$stmtBeds->execute([$branch_id]);
$beds = $stmtBeds->fetchAll();

$selected_bed_id = isset($_GET['bed_id']) ? intval($_GET['bed_id']) : 0;
$selected_package_id = isset($_GET['package_id']) ? intval($_GET['package_id']) : 0;
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Transaksi - Kasir</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .bed-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; margin-top: 10px; }
        .bed-item { padding: 15px; text-align: center; background: var(--bg-panel); border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: 0.2s; color: var(--text-dark); }
        .bed-item:hover { border-color: var(--accent-blue); background: rgba(52,152,219,0.05); }
        .bed-item.selected { background: var(--accent-blue); color: white; border-color: var(--accent-blue); font-weight: bold; transform: scale(1.05); box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3); }
        
        .pkg-tabs { display: flex; gap: 8px; margin-bottom: 12px; }
        .pkg-tab-btn { flex: 1; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-input); cursor: pointer; font-size: 13px; font-weight: 700; color: var(--text-muted); transition: 0.2s; }
        .pkg-tab-btn:hover { border-color: var(--text-dark); color: var(--text-dark); }
        .pkg-tab-btn.active { background: var(--text-dark); border-color: var(--text-dark); color: var(--bg-panel); }
        .pkg-grid-wrapper { display: none; }
        .pkg-grid-wrapper.show { display: block; }
        .pkg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-top: 10px; }
        .pkg-card { background: var(--bg-panel); border: 2px solid var(--border-color); border-radius: 12px; padding: 15px; cursor: pointer; transition: 0.2s; position: relative; }
        .pkg-card:hover:not(.pkg-unavailable) { border-color: var(--accent-green); background: rgba(39,174,96,0.05); transform: translateY(-2px); }
        .pkg-card.pkg-selected { border-color: var(--accent-green); background: rgba(39,174,96,0.1); box-shadow: 0 0 0 2px rgba(39,174,96,0.2); transform: translateY(-2px); }
        .pkg-card.pkg-unavailable { opacity: 0.5; cursor: not-allowed; background: var(--bg-input); }
        .pkg-card-name { font-weight: 700; color: var(--text-dark); font-size: 14px; margin-bottom: 5px; }
        .pkg-card-price { font-weight: 700; color: var(--accent-green); font-size: 14px; }
        .pkg-card-durasi { font-size: 11px; color: var(--text-muted); font-weight: 600; display: block; margin-top: 3px; }
        
        .giliran-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .giliran-table th { background: var(--bg-input); color: var(--text-muted); padding: 12px; text-align: left; }
        .giliran-table tbody tr { border-bottom: 1px solid var(--border-color); cursor: pointer; transition: 0.2s; }
        .giliran-table tbody tr.available:hover { background: var(--bg-input); }
        .giliran-table tbody tr.selected-row { background: rgba(39,174,96,0.1) !important; border-left: 4px solid var(--accent-green); }
        /* UPDATE: Tambahkan class .pulang-row pada style css ini */
        .giliran-table tbody tr.busy-row, .giliran-table tbody tr.loaned-row, .giliran-table tbody tr.izin-row, .giliran-table tbody tr.belum-absen-row, .giliran-table tbody tr.pulang-row { opacity: 0.5; cursor: not-allowed; background: var(--bg-input); }
        .giliran-no { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 13px; color: white; background: var(--text-muted); }
        .giliran-no.top { background: var(--accent-blue); }

        .payment-options { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        .payment-option { padding: 20px; border-radius: 12px; border: 2px solid var(--border-color); cursor: pointer; transition: 0.3s; text-align: center; background: var(--bg-panel); }
        .payment-option:hover { border-color: var(--text-dark); }
        .payment-option.selected { border-color: var(--accent-green); background: rgba(39,174,96,0.05); }
        .pay-title { font-size: 15px; font-weight: 700; color: var(--text-dark); margin-bottom: 5px; }
        
        .action-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        .btn-pay { padding: 15px; border-radius: 10px; font-size: 14px; font-weight: bold; cursor: pointer; border: none; transition: 0.3s; color: white; }
        .btn-pay.now { background: var(--accent-green); }
        .btn-pay.later { background: var(--accent-blue); }
        .btn-disabled { opacity: 0.5; cursor: not-allowed !important; }
        
        .autocomplete-wrapper { 
            position: relative; 
            z-index: 99999; 
        }
        .autocomplete-list { 
            position: absolute; 
            top: 100%; 
            left: 0; 
            right: 0; 
            background: var(--bg-panel); 
            border: 1px solid var(--border-color); 
            border-radius: 0 0 8px 8px; 
            max-height: 250px; 
            overflow-y: auto; 
            z-index: 99999 !important; 
            display: none; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .autocomplete-list.show { display: block; }
        .autocomplete-item { padding: 12px 15px; cursor: pointer; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .autocomplete-item:hover, .autocomplete-item.active { background: var(--bg-input); }
        .ac-nama { font-weight: 700; color: var(--text-dark); font-size: 13px; }
        .ac-hp { color: var(--text-muted); font-size: 11px; }
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
                <a href="input_transaksi.php" class="menu-item active"><span class="menu-abbr">IT</span><span class="menu-text">Input Transaksi</span></a>
                <a href="transaksi_panggilan.php" class="menu-item"><span class="menu-abbr">TP</span><span class="menu-text">Transaksi Panggilan</span></a>
                <a href="absensi_kasir.php" class="menu-item"><span class="menu-abbr">AT</span><span class="menu-text">Absensi Terapis</span></a>
                <a href="data_terapis_hadir.php" class="menu-item"><span class="menu-abbr">DT</span><span class="menu-text">Data Terapis</span></a>
                <a href="data_customer_kasir.php" class="menu-item"><span class="menu-abbr">DC</span><span class="menu-text">Data Customer</span></a>
                <a href="paket_layanan_kasir.php" class="menu-item"><span class="menu-abbr">PL</span><span class="menu-text">Paket Layanan</span></a>
                <a href="stok_barang.php" class="menu-item"><span class="menu-abbr">SB</span><span class="menu-text">Stok Barang</span></a>
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
                    <h1>Input Transaksi</h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
                </div>
            </div>

            <?php if (!empty($problematicItems)): ?>
            <div style="background: rgba(231,76,60,0.1); border-left: 4px solid var(--accent-red); color: var(--accent-red); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
                <strong style="display:block; margin-bottom:5px;">Peringatan Stok!</strong>
                Barang berikut Kosong atau Belum terdaftar di cabang. Paket terkait tidak dapat dipilih:
                <ul style="margin-top:5px; padding-left:20px; font-weight:600;">
                    <?php foreach($problematicItems as $pItem): ?>
                        <li><?= htmlspecialchars($pItem) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form id="formTransaksi" method="POST">
                <input type="hidden" name="action_submit" value="transaksi">
                <input type="hidden" name="tipe_transaksi" value="datang">
                <input type="hidden" name="bed_id" id="bedIdInput" value="<?= $selected_bed_id ?>">
                <input type="hidden" name="package_id" id="packageInput" value="<?= $selected_package_id ?>">
                <input type="hidden" name="terapis_id_lokal" id="terapisLokalHidden" value="">
                <input type="hidden" name="terapis_id_external" id="terapisIdExternal" value="">
                <input type="hidden" name="terapis_home_branch" id="terapisHomeBranch" value="0">
                <input type="hidden" name="payment_mode" id="paymentModeInput" value="">
                <input type="hidden" name="skip_terapis_id" id="skipTerapisId" value="">
                <input type="hidden" name="skip_keterangan" id="skipKeterangan" value="">

                <div class="card" style="padding:20px; margin-bottom:20px; position: relative; z-index: 50;">
                    <h3 style="margin-bottom:15px; color:var(--text-dark);">1. Pilih Bed Kosong</h3>
                    <?php if (empty($beds)): ?>
                        <div style="color:var(--accent-red); font-weight:bold; font-size:13px;">Tidak ada bed kosong tersedia saat ini.</div>
                    <?php else: ?>
                    <div class="bed-grid">
                        <?php foreach($beds as $bed): ?>
                        <div class="bed-item <?= ($selected_bed_id == $bed['id']) ? 'selected' : '' ?>" onclick="selectBed(this, <?= $bed['id'] ?>)">
                            <div style="font-size: 18px; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif;"><?= htmlspecialchars($bed['nomor_bed']) ?></div>
                            <div style="font-size: 10px; opacity: 0.8; margin-top: 3px; font-weight:600; text-transform:uppercase;"><?= htmlspecialchars($bed['tipe']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card" style="padding:20px; margin-bottom:20px; position: relative; z-index: 100; overflow: visible !important;">
                    <h3 style="margin-bottom:15px; color:var(--text-dark);">2. Data Pelanggan</h3>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>Nama Pelanggan (Cari/Ketik)</label>
                            <div class="autocomplete-wrapper">
                                <input type="text" name="nama_pelanggan" id="namaPelanggan" class="form-control" required autocomplete="off">
                                <div class="autocomplete-list" id="autocompleteList"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nomor HP (Opsional)</label>
                            <input type="text" name="no_hp" id="noHpPelanggan" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="card" style="padding:20px; margin-bottom:20px; position: relative; z-index: 10;">
                    <h3 style="margin-bottom:15px; color:var(--text-dark);">3. Paket & Layanan</h3>
                    <div class="pkg-tabs">
                        <button type="button" class="pkg-tab-btn active" id="tabPaket" onclick="switchPkgTab('paket')">Paket Utama</button>
                        <button type="button" class="pkg-tab-btn" id="tabNonPaket" onclick="switchPkgTab('non_paket')">Non Paket</button>
                        <button type="button" class="pkg-tab-btn" id="tabHotel" onclick="switchPkgTab('hotel')">Paket Hotel</button>
                    </div>

                    <div class="pkg-grid-wrapper show" id="gridPaket">
                        <div class="pkg-grid">
                            <?php foreach(array_filter($packages, fn($p) => $p['is_paket'] == 1) as $p):
                                $available = isPackageAvailable($p['id'], $pkgRequirements, $branchStocks);
                                $clickAttr = $available ? "onclick=\"selectPackageCard(this, {$p['id']}, {$p['harga']}, {$p['durasi_menit']})\"" : "";
                            ?>
                            <div class="pkg-card <?= !$available ? 'pkg-unavailable' : '' ?> <?= $selected_package_id == $p['id'] ? 'pkg-selected' : '' ?>" <?= $clickAttr ?> data-pkg-id="<?= $p['id'] ?>" data-harga="<?= $p['harga'] ?>" data-durasi="<?= $p['durasi_menit'] ?>">
                                <div class="pkg-card-name"><?= htmlspecialchars($p['nama_paket']) ?></div>
                                <div class="pkg-card-price">Rp <?= number_format($p['harga'], 0, ',', '.') ?></div>
                                <span class="pkg-card-durasi">Durasi: <?= $p['durasi_menit'] ?> mnt</span>
                                <?php if (!$available): ?><div style="font-size:10px; color:var(--accent-red); margin-top:5px; font-weight:bold;">Stok Habis</div><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="pkg-grid-wrapper" id="gridNonPaket">
                        <div class="pkg-grid">
                            <?php foreach(array_filter($packages, fn($p) => $p['is_paket'] == 0) as $p):
                                $available = isPackageAvailable($p['id'], $pkgRequirements, $branchStocks);
                                $clickAttr = $available ? "onclick=\"selectPackageCard(this, {$p['id']}, {$p['harga']}, {$p['durasi_menit']})\"" : "";
                            ?>
                            <div class="pkg-card <?= !$available ? 'pkg-unavailable' : '' ?> <?= $selected_package_id == $p['id'] ? 'pkg-selected' : '' ?>" <?= $clickAttr ?> data-pkg-id="<?= $p['id'] ?>" data-harga="<?= $p['harga'] ?>" data-durasi="<?= $p['durasi_menit'] ?>">
                                <div class="pkg-card-name"><?= htmlspecialchars($p['nama_paket']) ?></div>
                                <div class="pkg-card-price">Rp <?= number_format($p['harga'], 0, ',', '.') ?></div>
                                <span class="pkg-card-durasi">Durasi: <?= $p['durasi_menit'] ?> mnt</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="pkg-grid-wrapper" id="gridHotel">
                        <div class="pkg-grid">
                            <?php foreach(array_filter($packages, fn($p) => $p['is_paket'] == 2) as $p):
                                $available = isPackageAvailable($p['id'], $pkgRequirements, $branchStocks);
                                $clickAttr = $available ? "onclick=\"selectPackageCard(this, {$p['id']}, {$p['harga']}, {$p['durasi_menit']})\"" : "";
                            ?>
                            <div class="pkg-card <?= !$available ? 'pkg-unavailable' : '' ?> <?= $selected_package_id == $p['id'] ? 'pkg-selected' : '' ?>" <?= $clickAttr ?> data-pkg-id="<?= $p['id'] ?>" data-harga="<?= $p['harga'] ?>" data-durasi="<?= $p['durasi_menit'] ?>">
                                <div class="pkg-card-name"><?= htmlspecialchars($p['nama_paket']) ?></div>
                                <div class="pkg-card-price">Rp <?= number_format($p['harga'], 0, ',', '.') ?></div>
                                <span class="pkg-card-durasi">Durasi: <?= $p['durasi_menit'] ?> mnt</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="priceDisplay" style="display:none; background: var(--text-dark); color: white; padding: 15px 20px; border-radius: 8px; margin-top: 15px; text-align: center;">
                        <div style="font-size: 12px; opacity: 0.8; font-weight:bold; text-transform:uppercase;">Total Layanan</div>
                        <div id="priceAmount" style="font-size: 24px; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif;">Rp 0</div>
                    </div>
                </div>

                <div class="card" style="padding:20px; margin-bottom:20px; position: relative; z-index: 9;">
                    <h3 style="margin-bottom:15px; color:var(--text-dark);">4. Pilih Terapis (Giliran)</h3>
                    <div id="giliranSelectedInfo" style="display:none; background: rgba(46,204,113,0.1); border: 1px solid var(--accent-green); padding: 15px; border-radius: 8px; margin-bottom: 15px; justify-content: space-between; align-items: center;">
                        <span id="giliranSelectedText" style="color:var(--text-dark); font-weight:bold; font-size:14px;">-</span>
                        <button type="button" onclick="batalPilihGiliran()" class="btn btn-sm btn-secondary" style="background:white;">Batal</button>
                    </div>

                    <div class="table-container" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border-color); border-radius:8px;" id="giliranTableWrapper">
                        <table class="giliran-table">
                            <thead>
                                <tr>
                                    <th style="width:40px; text-align:center;">No</th>
                                    <th>Nama Terapis</th>
                                    <th style="text-align:center;">Kerja Hari Ini</th>
                                    <th style="text-align:center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach($giliranTerapis as $gt): 
                                    $isBusy = ($gt['is_busy'] > 0);
                                    $isLoaned = ($gt['is_loaned'] > 0);
                                    $sudahAbsen = isset($absenHariIni[$gt['id']]);
                                    $isIzinSakit = isset($izinHariIniMap[$gt['id']]);
                                    $sudahPulang = !empty($gt['waktu_keluar']); // UPDATE: Cek apakah terapis sudah pulang
                                    
                                    $isAvailable = (!$isBusy && !$isLoaned && $sudahAbsen && !$isIzinSakit && !$sudahPulang);
                                    
                                    $rowClass = 'available';
                                    if ($isIzinSakit) $rowClass = 'izin-row';
                                    elseif (!$sudahAbsen) $rowClass = 'belum-absen-row';
                                    elseif ($sudahPulang) $rowClass = 'pulang-row'; // UPDATE: Menambahkan class jika pulang
                                    elseif ($isBusy) $rowClass = 'busy-row';
                                    elseif ($isLoaned) $rowClass = 'loaned-row';
                                    
                                    $clickAction = $isAvailable ? "onclick=\"pilihTerapisGiliran({$gt['id']}, '" . addslashes($gt['nama_lengkap']) . "', this)\"" : "";
                                ?>
                                <tr class="<?= $rowClass ?>" <?= $clickAction ?> data-terapis-id="<?= $gt['id'] ?>">
                                    <td style="text-align:center;">
                                        <div class="giliran-no <?= ($no <= 3 && $isAvailable) ? 'top' : '' ?>"><?= $no ?></div>
                                    </td>
                                    <td>
                                        <strong style="color:var(--text-dark);"><?= htmlspecialchars($gt['nama_lengkap']) ?></strong>
                                    </td>
                                    <td style="text-align:center; font-weight:bold; color:var(--accent-blue);">
                                        <?= $gt['kerja_hari_ini'] ?>x
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($isIzinSakit): ?>
                                            <span style="font-size:11px; font-weight:bold; color:var(--text-muted); text-transform:uppercase;">Tidak Hadir</span>
                                        <?php elseif (!$sudahAbsen): ?>
                                            <span style="font-size:11px; font-weight:bold; color:var(--text-muted); text-transform:uppercase;">Belum Absen</span>
                                        <?php elseif ($sudahPulang): ?>
                                            <span style="font-size:11px; font-weight:bold; color:var(--text-muted); text-transform:uppercase;">Sudah Pulang</span>
                                        <?php elseif ($isLoaned): ?>
                                            <span style="font-size:11px; font-weight:bold; color:var(--text-muted); text-transform:uppercase;">Dipinjam</span>
                                        <?php elseif ($isBusy): ?>
                                            <span style="font-size:11px; font-weight:bold; color:var(--accent-red); text-transform:uppercase;">Sibuk</span>
                                        <?php else: ?>
                                            <span style="font-size:11px; font-weight:bold; color:var(--accent-green); text-transform:uppercase;">Ready</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php $no++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 15px; border-top: 1px dashed var(--border-color); padding-top: 15px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="togglePinjamTerapis()">+ Pinjam Terapis Cabang Lain</button>
                        
                        <div id="pinjamTerapisArea" style="display:none; margin-top:15px; background:var(--bg-input); padding:15px; border-radius:8px; border:1px solid var(--border-color);">
                            <label style="font-size:12px; font-weight:bold; color:var(--text-muted); display:block; margin-bottom:8px;">Pilih Terapis dari Cabang Lain</label>
                            <div style="display:flex; gap:10px;">
                                <select id="selectTerapisExternal" class="form-control" style="flex:1;" onchange="pilihTerapisExternal()">
                                    <option value="">-- Memuat Terapis... --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="padding:20px; position: relative; z-index: 8;">
                    <h3 style="margin-bottom:15px; color:var(--text-dark);">5. Proses</h3>
                    <div class="payment-options">
                        <div class="payment-option" id="optPayNow" onclick="selectPaymentMode('bayar_sekarang')">
                            <div class="pay-title">Bayar Sekarang</div>
                            <span style="font-size:12px; color:var(--text-muted);">Arahkan ke kasir untuk bayar</span>
                        </div>
                        <div class="payment-option" id="optPayLater" onclick="selectPaymentMode('bayar_nanti')">
                            <div class="pay-title">Bayar Nanti</div>
                            <span style="font-size:12px; color:var(--text-muted);">Pijat dulu, bayar belakangan</span>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button type="button" id="btnPayNow" onclick="validateAndSubmit('bayar_sekarang')" class="btn-pay now btn-disabled" disabled>Proses & Bayar</button>
                        <button type="button" id="btnPayLater" onclick="validateAndSubmit('bayar_nanti')" class="btn-pay later btn-disabled" disabled>Mulai Pijat</button>
                    </div>
                </div>
            </form>
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
        
        if (window.innerWidth <= 992) {
            sb.classList.toggle('active');
        } else {
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

    function selectBed(el, id) {
        document.querySelectorAll('.bed-item').forEach(i => i.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('bedIdInput').value = id;
    }

    function switchPkgTab(tab) {
        document.getElementById('tabPaket').classList.toggle('active', tab === 'paket');
        document.getElementById('tabNonPaket').classList.toggle('active', tab === 'non_paket');
        document.getElementById('tabHotel').classList.toggle('active', tab === 'hotel');
        document.getElementById('gridPaket').classList.toggle('show', tab === 'paket');
        document.getElementById('gridNonPaket').classList.toggle('show', tab === 'non_paket');
        document.getElementById('gridHotel').classList.toggle('show', tab === 'hotel');
    }

    function selectPackageCard(el, id, harga, durasi) {
        document.querySelectorAll('.pkg-card').forEach(c => c.classList.remove('pkg-selected'));
        el.classList.add('pkg-selected');
        document.getElementById('packageInput').value = id;
        const display = document.getElementById('priceDisplay');
        document.getElementById('priceAmount').textContent = 'Rp ' + parseInt(harga).toLocaleString('id-ID') + ' (' + durasi + ' mnt)';
        display.style.display = 'block';
    }

    let acTimeout = null; let acIndex = -1;
    const inputNama = document.getElementById('namaPelanggan');
    const acList = document.getElementById('autocompleteList');
    inputNama.addEventListener('input', function() {
        const val = this.value.trim(); clearTimeout(acTimeout); acIndex = -1;
        if (val.length < 1) { acList.classList.remove('show'); acList.innerHTML = ''; return; }
        acList.innerHTML = '<div style="padding:15px; text-align:center; font-size:12px; color:var(--text-muted);">Mencari data...</div>';
        acList.classList.add('show');
        acTimeout = setTimeout(() => {
            fetch('ajax_search_customer.php?q=' + encodeURIComponent(val)).then(r => r.json()).then(data => {
                if (!data.success || data.customers.length === 0) { acList.classList.remove('show'); acList.innerHTML = ''; return; }
                let html = '';
                data.customers.forEach((c) => {
                    html += `<div class="autocomplete-item" onclick="pilihCustomer('${c.nama}', '${c.no_hp || ''}', '${c.package_id || ''}')">
                        <div>
                            <div class="ac-nama">${c.nama}</div>
                            <div class="ac-hp">${c.no_hp || 'No HP -'}</div>
                        </div>
                    </div>`;
                });
                acList.innerHTML = html;
            });
        }, 300);
    });

    function pilihCustomer(nama, hp, pkgId) {
        document.getElementById('namaPelanggan').value = nama;
        if(hp && hp !== '-') document.getElementById('noHpPelanggan').value = hp;
        if(pkgId) {
            const card = document.querySelector('.pkg-card[data-pkg-id="' + pkgId + '"]:not(.pkg-unavailable)');
            if(card) {
                const isInPaket = card.closest('#gridPaket') !== null; const isInHotel = card.closest('#gridHotel') !== null;
                switchPkgTab(isInPaket ? 'paket' : (isInHotel ? 'hotel' : 'non_paket'));
                selectPackageCard(card, pkgId, card.dataset.harga, card.dataset.durasi);
            }
        }
        acList.classList.remove('show'); acList.innerHTML = '';
    }
    document.addEventListener('click', e => { if (!e.target.closest('.autocomplete-wrapper')) { acList.classList.remove('show'); } });

    function getFirstAvailableTerapis() {
        const rows = document.querySelectorAll('.giliran-table tbody tr.available');
        if (rows.length === 0) return null;
        return { id: parseInt(rows[0].dataset.terapisId), nama: rows[0].querySelector('td:nth-child(2) strong').textContent.trim() };
    }

    function pilihTerapisGiliran(id, nama, rowEl) {
        const first = getFirstAvailableTerapis();
        if (first && first.id !== id) {
            Swal.fire({
                title: 'Giliran Dilompati!',
                html: '<div style="font-size:14px; text-align:left;"><p>Terapis <strong>' + first.nama + '</strong> seharusnya yang dipilih.</p><p style="color:var(--accent-red); font-weight:bold; margin-top:10px;">Mohon isi alasan melompati giliran (Tolak Pasien):</p></div>',
                input: 'textarea', inputPlaceholder: 'Contoh: Terapis menolak...', 
                inputValidator: val => { if(!val || val.trim().length < 5) return 'Alasan wajib diisi (min 5 char)!'; },
                showCancelButton: true, confirmButtonText: 'Lompati & Pilih', confirmButtonColor: '#e74c3c'
            }).then(res => {
                if(res.isConfirmed && res.value) {
                    document.getElementById('skipTerapisId').value = first.id;
                    document.getElementById('skipKeterangan').value = res.value.trim();
                    doSelectTerapis(id, nama, rowEl);
                }
            });
        } else {
            document.getElementById('skipTerapisId').value = ''; document.getElementById('skipKeterangan').value = '';
            doSelectTerapis(id, nama, rowEl);
        }
    }

    function doSelectTerapis(id, nama, rowEl) {
        document.getElementById('terapisLokalHidden').value = id;
        document.getElementById('terapisIdExternal').value = ''; 
        
        document.querySelectorAll('.giliran-table tbody tr').forEach(tr => tr.classList.remove('selected-row'));
        rowEl.classList.add('selected-row');
        document.getElementById('giliranSelectedText').innerText = 'Terapis Terpilih: ' + nama;
        document.getElementById('giliranSelectedInfo').style.display = 'flex';
        
        document.getElementById('selectTerapisExternal').selectedIndex = 0;
    }

    function batalPilihGiliran() {
        document.getElementById('terapisLokalHidden').value = '';
        document.getElementById('terapisIdExternal').value = '';
        document.querySelectorAll('.giliran-table tbody tr').forEach(tr => tr.classList.remove('selected-row'));
        document.getElementById('giliranSelectedInfo').style.display = 'none';
        document.getElementById('selectTerapisExternal').selectedIndex = 0;
    }

    function togglePinjamTerapis() {
        const area = document.getElementById('pinjamTerapisArea');
        if(area.style.display === 'none') {
            area.style.display = 'block';
            fetch('ajax_get_terapis_other_branch.php')
            .then(r => r.json())
            .then(data => {
                let sel = document.getElementById('selectTerapisExternal');
                sel.innerHTML = '<option value="">-- Pilih Terapis --</option>';
                if(data.success && data.terapis.length > 0) {
                    let countAvail = 0;
                    data.terapis.forEach(t => {
                        if(t.is_available) {
                            countAvail++;
                            sel.innerHTML += `<option value="${t.id}" data-branch="${t.branch_id}" data-nama="${t.nama_lengkap}">${t.nama_lengkap} (Dari: ${t.branch_name})</option>`;
                        }
                    });
                    if (countAvail === 0) sel.innerHTML = '<option value="">Semua terapis luar sedang sibuk</option>';
                } else {
                    sel.innerHTML = '<option value="">Tidak ada terapis dari cabang lain</option>';
                }
            });
        } else {
            area.style.display = 'none';
        }
    }

    function pilihTerapisExternal() {
        const sel = document.getElementById('selectTerapisExternal');
        if (sel.selectedIndex < 0) return;
        
        const opt = sel.options[sel.selectedIndex];
        if(!opt.value) {
            batalPilihGiliran();
            return;
        }
        
        document.getElementById('terapisIdExternal').value = opt.value;
        document.getElementById('terapisHomeBranch').value = opt.getAttribute('data-branch');
        
        document.getElementById('terapisLokalHidden').value = '';
        document.querySelectorAll('.giliran-table tbody tr').forEach(tr => tr.classList.remove('selected-row'));
        
        document.getElementById('giliranSelectedText').innerHTML = 'Terapis Pinjaman: <span style="color:var(--accent-red);">' + opt.getAttribute('data-nama') + '</span>';
        document.getElementById('giliranSelectedInfo').style.display = 'flex';
    }

    function selectPaymentMode(mode) {
        document.getElementById('paymentModeInput').value = mode;
        document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
        if(mode === 'bayar_sekarang') {
            document.getElementById('optPayNow').classList.add('selected');
            document.getElementById('btnPayNow').classList.remove('btn-disabled'); document.getElementById('btnPayNow').disabled = false;
            document.getElementById('btnPayLater').classList.add('btn-disabled'); document.getElementById('btnPayLater').disabled = true;
        } else {
            document.getElementById('optPayLater').classList.add('selected');
            document.getElementById('btnPayLater').classList.remove('btn-disabled'); document.getElementById('btnPayLater').disabled = false;
            document.getElementById('btnPayNow').classList.add('btn-disabled'); document.getElementById('btnPayNow').disabled = true;
        }
    }

    function validateAndSubmit(mode) {
        const bed = document.getElementById('bedIdInput').value;
        const nama = document.getElementById('namaPelanggan').value;
        const pkg = document.getElementById('packageInput').value;
        const lokal = document.getElementById('terapisLokalHidden').value;
        const eksternal = document.getElementById('terapisIdExternal').value;
        
        if(!bed) return Swal.fire('Peringatan', 'Pilih Bed Kosong terlebih dahulu!', 'warning');
        if(!nama) return Swal.fire('Peringatan', 'Isi Nama Pelanggan!', 'warning');
        if(!pkg) return Swal.fire('Peringatan', 'Pilih Paket/Layanan!', 'warning');
        if((!lokal || parseInt(lokal) <= 0) && (!eksternal || parseInt(eksternal) <= 0)) {
            return Swal.fire('Peringatan', 'Pilih Terapis dari daftar giliran atau pinjam dari cabang lain!', 'warning');
        }

        document.getElementById('paymentModeInput').value = mode;
        
        const title = mode === 'bayar_sekarang' ? 'Proses Pembayaran?' : 'Mulai Pijat Sekarang?';
        const txt = mode === 'bayar_sekarang' ? 'Lanjutkan ke halaman kasir untuk pembayaran.' : 'Pijatan akan segera dimulai, bayar nanti.';
        const color = mode === 'bayar_sekarang' ? '#27ae60' : '#2980b9';

        Swal.fire({
            title: title, text: txt, icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Lanjutkan', confirmButtonColor: color
        }).then(res => { if(res.isConfirmed) document.getElementById('formTransaksi').submit(); });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const preselectedId = document.getElementById('packageInput').value;
        if (preselectedId) {
            const card = document.querySelector('.pkg-card[data-pkg-id="' + preselectedId + '"]');
            if (card) {
                const isInPaket = card.closest('#gridPaket') !== null; const isInHotel = card.closest('#gridHotel') !== null;
                switchPkgTab(isInPaket ? 'paket' : (isInHotel ? 'hotel' : 'non_paket'));
                selectPackageCard(card, preselectedId, card.dataset.harga, card.dataset.durasi);
            }
        }
    });
    </script>
    <script><?= $swal_script ?></script>
</body>
</html>