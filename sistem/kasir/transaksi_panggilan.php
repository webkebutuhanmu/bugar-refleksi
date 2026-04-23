<?php
// File: kasir/transaksi_panggilan.php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) {
    header("Location: pilih_cabang.php"); exit;
}

$kasir_id  = $_SESSION['user_id'];
$branch_id = $_SESSION['active_branch'];
$nama_cabang = $pdo->query("SELECT nama_cabang FROM branches WHERE id = $branch_id")->fetchColumn();

$nama_kasir = $_SESSION['nama'];
$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$foto_profil = $stmtProfil->fetchColumn();
$foto_profil = (!empty($foto_profil) && file_exists("../uploads/profil/" . $foto_profil)) ? "../uploads/profil/" . $foto_profil : "../assets/default_user.png";

$swal_script = "";

// =====================================================
// AMBIL DATA CABANG (koordinat + alamat)
// =====================================================
$stmtCabang = $pdo->prepare("SELECT nama_cabang, alamat, latitude, longitude FROM branches WHERE id = ?");
$stmtCabang->execute([$branch_id]);
$dataCabang  = $stmtCabang->fetch();
$cabang_lat  = $dataCabang['latitude']  ? floatval($dataCabang['latitude'])  : null;
$cabang_lng  = $dataCabang['longitude'] ? floatval($dataCabang['longitude']) : null;

// =====================================================
// AMBIL TARIF DRIVER DARI SETTINGS
// =====================================================
$driverSettings     = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
$driver_base_km     = isset($driverSettings['driver_rate_base_km'])     ? floatval($driverSettings['driver_rate_base_km'])   : 5.0;
$driver_base_price  = isset($driverSettings['driver_rate_base_price'])  ? intval($driverSettings['driver_rate_base_price'])  : 20000;
$driver_extra_price = isset($driverSettings['driver_rate_extra_price']) ? intval($driverSettings['driver_rate_extra_price']) : 30000;

// =====================================================
// LOGIC PENGECEKAN STOK & KETERSEDIAAN PAKET
// =====================================================
$stmtStock = $pdo->prepare("SELECT item_id, stok FROM branch_items WHERE branch_id = ?");
$stmtStock->execute([$branch_id]);
$branchStocks = [];
while ($row = $stmtStock->fetch(PDO::FETCH_ASSOC)) {
    $branchStocks[$row['item_id']] = (int)$row['stok'];
}

$itemNames = [];
$stmtItems = $pdo->query("SELECT id, nama_item FROM items");
while ($row = $stmtItems->fetch(PDO::FETCH_ASSOC)) {
    $itemNames[$row['id']] = $row['nama_item'];
}

$pkgRequirements  = [];
$allRequiredItems = [];
$stmtPkgItems = $pdo->query("SELECT package_id, item_id, jumlah FROM package_items");
while ($row = $stmtPkgItems->fetch(PDO::FETCH_ASSOC)) {
    $pkgRequirements[$row['package_id']][] = [
        'item_id' => (int)$row['item_id'],
        'jumlah'  => (int)$row['jumlah']
    ];
    $allRequiredItems[] = (int)$row['item_id'];
}
$allRequiredItems = array_unique($allRequiredItems);

$problematicItems = [];
foreach ($allRequiredItems as $reqItemId) {
    if (!isset($branchStocks[$reqItemId]) || $branchStocks[$reqItemId] <= 0) {
        $name = $itemNames[$reqItemId] ?? 'Item ID #' . $reqItemId;
        $problematicItems[] = !isset($branchStocks[$reqItemId])
            ? "{$name} (Belum ada di Stok Cabang)"
            : "{$name} (Stok: 0)";
    }
}

function isPackageAvailable($pkgId, $requirements, $stocks) {
    if (!isset($requirements[$pkgId])) return true;
    foreach ($requirements[$pkgId] as $req) {
        if (!isset($stocks[$req['item_id']])) return false;
        if ($stocks[$req['item_id']] < $req['jumlah']) return false;
    }
    return true;
}

// =====================================================
// LOGIC SUBMIT TRANSAKSI PANGGILAN
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_submit']) && $_POST['action_submit'] == 'transaksi_panggilan') {
    try {
        $package_id       = $_POST['package_id'] ?? '';
        $nama_pelanggan   = trim($_POST['nama_pelanggan'] ?? '');
        $no_hp            = trim($_POST['no_hp'] ?? '');
        $payment_mode     = $_POST['payment_mode'] ?? 'bayar_nanti';
        $alamat_panggilan = trim($_POST['alamat_panggilan'] ?? '');
        $biaya_driver     = floatval($_POST['biaya_driver'] ?? 0);
        $tipe_lokasi      = in_array($_POST['tipe_lokasi'] ?? '', ['rumah','hotel']) ? $_POST['tipe_lokasi'] : 'rumah';
        $harga_admin_hotel = ($tipe_lokasi === 'hotel') ? floatval($_POST['harga_admin_hotel'] ?? 0) : 0;

        if (!isPackageAvailable($package_id, $pkgRequirements, $branchStocks)) {
            throw new Exception("Stok barang untuk paket ini tidak mencukupi atau barang belum terdaftar di cabang!");
        }

        $terapis_id          = 0;
        $is_external         = 0;
        $terapis_home_branch = 0;
        $raw_external        = trim($_POST['terapis_id_external'] ?? '');
        $raw_lokal           = trim($_POST['terapis_id_lokal'] ?? '');

        if ($raw_external !== '' && intval($raw_external) > 0) {
            $terapis_id          = intval($raw_external);
            $is_external         = 1;
            $terapis_home_branch = intval($_POST['terapis_home_branch'] ?? 0);
        } elseif ($raw_lokal !== '' && intval($raw_lokal) > 0) {
            $terapis_id  = intval($raw_lokal);
            $is_external = 0;
        }

        if ($terapis_id <= 0)         throw new Exception("Terapis Kosong! Silakan pilih terapis.");
        if (empty($package_id))       throw new Exception("Paket belum dipilih!");
        if (empty($alamat_panggilan)) throw new Exception("Alamat panggilan wajib diisi!");
        if (empty($nama_pelanggan))   throw new Exception("Nama Pelanggan wajib diisi!");

        $stmtCekTerapis = $pdo->prepare("SELECT id, nama_lengkap FROM users WHERE id = ? AND role = 'terapis'");
        $stmtCekTerapis->execute([$terapis_id]);
        $dataTerapis = $stmtCekTerapis->fetch();
        if (!$dataTerapis) throw new Exception("Terapis dengan ID $terapis_id tidak ditemukan!");

        $stmtBusy = $pdo->prepare("SELECT COUNT(*) as busy_count FROM transactions WHERE terapis_id = ? AND status IN ('proses','menunggu_approval','menunggu_pembayaran')");
        $stmtBusy->execute([$terapis_id]);
        $busyData = $stmtBusy->fetch();
        if ($busyData['busy_count'] > 0) {
            throw new Exception("Terapis " . $dataTerapis['nama_lengkap'] . " sedang sibuk. Silakan pilih terapis lain.");
        }

        $pdo->beginTransaction();

        $stmtPkg = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
        $stmtPkg->execute([$package_id]);
        $pkg = $stmtPkg->fetch();
        if (!$pkg) throw new Exception("Paket tidak valid.");

        $harga        = $pkg['harga'];
        $durasi_menit = $pkg['durasi_menit'];

        $settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
        if (!$settings) throw new Exception("Settings sistem tidak ditemukan!");

        $jam_sekarang     = date('H:i:s');
        $shift_pagi_start = $settings['shift_pagi_start'];
        $shift_pagi_end   = $settings['shift_pagi_end'];

        if ($jam_sekarang >= $shift_pagi_start && $jam_sekarang <= $shift_pagi_end) {
            $jenis_shift    = 'pagi';
            $persen_cabang  = $settings['pagi_share_company'];
            $persen_terapis = $settings['pagi_share_therapist'];
        } else {
            $jenis_shift    = 'malam';
            $persen_cabang  = $settings['malam_share_company'];
            $persen_terapis = $settings['malam_share_therapist'];
        }

        $omset_cabang  = $harga * ($persen_cabang / 100);
        $omset_terapis = $harga * ($persen_terapis / 100);

        $status_transaksi  = 'proses';
        $payment_status    = 'unpaid';
        $metode_pembayaran = ($payment_mode === 'bayar_sekarang') ? null : 'bayar_nanti';

        $waktu_mulai   = date('Y-m-d H:i:s');
        $waktu_selesai = date('Y-m-d H:i:s', strtotime("+$durasi_menit minutes"));

        $jam_mulai_hari = $settings['jam_mulai_hari'] ?? '08:00:00';
        $tanggal_bisnis = ($jam_sekarang >= $jam_mulai_hari) ? date('Y-m-d') : date('Y-m-d', strtotime('-1 day'));
        
        // LOGIKA FIX: total_bayar hanya harga paket (untuk omset)
        // biaya_driver & harga_admin_hotel disimpan di kolomnya sendiri untuk Struk
        $total_bayar_final = $harga;

        $sqlInsert = "INSERT INTO transactions 
                      (kasir_id, branch_id, terapis_id, package_id, bed_id,
                       nama_pelanggan, no_hp_pelanggan,
                       waktu_mulai, waktu_selesai, durasi_menit,
                       total_bayar, omset_terapis, omset_cabang,
                       jenis_shift, tanggal_transaksi, status,
                       payment_status, metode_pembayaran,
                       tipe_transaksi, alamat_panggilan, biaya_driver,
                       tipe_lokasi, harga_admin_hotel)
                      VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'panggilan', ?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            $kasir_id, $branch_id, $terapis_id, $package_id,
            $nama_pelanggan, $no_hp,
            $waktu_mulai, $waktu_selesai, $durasi_menit,
            $total_bayar_final, $omset_terapis, $omset_cabang,
            $jenis_shift, $tanggal_bisnis, $status_transaksi,
            $payment_status, $metode_pembayaran,
            $alamat_panggilan, $biaya_driver,
            $tipe_lokasi, $harga_admin_hotel
        ]);
        $transaction_id = $pdo->lastInsertId();

        $stokWarnings  = [];
        $stmtPkgItems2 = $pdo->prepare("SELECT pi.*, i.nama_item, i.satuan FROM package_items pi JOIN items i ON pi.item_id = i.id WHERE pi.package_id = ?");
        $stmtPkgItems2->execute([$package_id]);
        $pkgItems = $stmtPkgItems2->fetchAll();
        foreach ($pkgItems as $pkgItem) {
            $stmtCekStok = $pdo->prepare("SELECT bi.id, bi.stok, bi.stok_minimum FROM branch_items bi WHERE bi.branch_id = ? AND bi.item_id = ?");
            $stmtCekStok->execute([$branch_id, $pkgItem['item_id']]);
            $branchItem = $stmtCekStok->fetch();
            if ($branchItem) {
                $stokSetelah = $branchItem['stok'] - $pkgItem['jumlah'];
                $pdo->prepare("UPDATE branch_items SET stok = stok - ? WHERE id = ?")->execute([$pkgItem['jumlah'], $branchItem['id']]);
                $pdo->prepare("INSERT INTO item_usage_log (branch_id, item_id, transaction_id, jumlah, tipe, keterangan, created_by) VALUES (?, ?, ?, ?, 'pakai', ?, ?)")
                    ->execute([$branch_id, $pkgItem['item_id'], $transaction_id, -$pkgItem['jumlah'], 'Paket: ' . $pkg['nama_paket'], $kasir_id]);
                if ($stokSetelah <= 0) $stokWarnings[] = $pkgItem['nama_item'] . ' HABIS! (sisa: ' . $stokSetelah . ')';
                elseif ($stokSetelah <= $branchItem['stok_minimum']) $stokWarnings[] = $pkgItem['nama_item'] . ' tinggal ' . $stokSetelah . ' ' . $pkgItem['satuan'];
            }
        }

        if ($is_external == 1) {
            if ($terapis_home_branch <= 0) throw new Exception("Data cabang asal terapis tidak valid!");
            $pdo->prepare("INSERT INTO terapis_loans (terapis_id, transaction_id, from_branch_id, to_branch_id, loan_time, status, approved_at, approved_by) VALUES (?, ?, ?, ?, NOW(), 'active', NOW(), ?)")
                ->execute([$terapis_id, $transaction_id, $terapis_home_branch, $branch_id, $kasir_id]);
            $loan_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO terapis_loan_notifications (loan_id, from_branch_id, to_branch_id, terapis_id, transaction_id, status, created_at, read_at) VALUES (?, ?, ?, ?, ?, 'read', NOW(), NOW())")
                ->execute([$loan_id, $terapis_home_branch, $branch_id, $terapis_id, $transaction_id]);
        }

        $pdo->commit();

        // Handle pelanggaran tolak pasien
        $skip_terapis_id = intval($_POST['skip_terapis_id'] ?? 0);
        $skip_keterangan = trim($_POST['skip_keterangan'] ?? '');
        if ($skip_terapis_id > 0 && !empty($skip_keterangan)) {
            $stSkipName = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
            $stSkipName->execute([$skip_terapis_id]);
            $skipNama = $stSkipName->fetchColumn() ?: 'Terapis #'.$skip_terapis_id;
            $stLeader = $pdo->prepare("SELECT id FROM users WHERE role = 'leader' AND branch_id = ? LIMIT 1");
            $stLeader->execute([$branch_id]);
            $leaderId = $stLeader->fetchColumn() ?: $kasir_id;

            $pelJudul = "Tolak Pasien - Giliran Dilompati";
            $pelDesk  = "Terapis $skipNama menolak pasien saat gilirannya tiba. Keterangan kasir: $skip_keterangan. Pasien dilayani oleh terapis lain (ID: $terapis_id). Transaksi ID: $transaction_id.";
            $pdo->prepare("INSERT INTO pelanggaran (terapis_id, branch_id, kategori, judul, deskripsi, tanggal, waktu_kejadian, status, created_by) VALUES (?, ?, 'tolak_pasien', ?, ?, ?, ?, 'aktif', ?)")->execute([$skip_terapis_id, $branch_id, $pelJudul, $pelDesk, date('Y-m-d'), date('H:i:s'), $leaderId]);
        }

        if ($payment_mode === 'bayar_sekarang') {
            if (!empty($stokWarnings)) $_SESSION['stok_warnings'] = $stokWarnings;
            header("Location: proses_pembayaran.php?transaction_id=" . $transaction_id); exit;
        } else {
            $pesan_sukses = ($is_external == 1) ? "Panggilan dimulai! Terapis cabang lain aktif. Pembayaran setelah selesai." : "Panggilan dimulai! Struk sementara dicetak. Pembayaran diminta saat selesai.";
            $pesan_js = addslashes($pesan_sukses);
            $stokWarnHtml = '';
            if (!empty($stokWarnings)) {
                $stokWarnHtml = '<br><br><div style=\"text-align:left;background:#fef9e7;padding:10px 15px;border-radius:8px;border-left:3px solid #f39c12;font-size:13px;\"><strong>Peringatan Stok:</strong><br>';
                foreach ($stokWarnings as $sw) $stokWarnHtml .= addslashes($sw) . '<br>';
                $stokWarnHtml .= '</div>';
            }
            $swal_script = "
                Swal.fire({
                    title: 'Panggilan Dimulai!',
                    html: '<b>$pesan_js</b><br><br><small style=\"color:#e67e22;\">Struk sementara akan dicetak. Pembayaran diminta saat selesai.</small>$stokWarnHtml',
                    icon: 'success',
                    timer: " . (!empty($stokWarnings) ? '6000' : '3000') . ",
                    showConfirmButton: " . (!empty($stokWarnings) ? 'true' : 'false') . "
                }).then(() => {
                    window.open('cetak_struk.php?transaction_id={$transaction_id}', '_blank');
                    window.location.href = 'dashboard_kasir.php';
                });
            ";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $swal_script = "Swal.fire('Gagal!', '" . addslashes($e->getMessage()) . "', 'error');";
    }
}

// =====================================================
// DATA PENDUKUNG
// =====================================================
$settingPeriode = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulaiBisnis = $settingPeriode['jam_mulai_hari'] ?? '08:00:00';
$jamSekarang    = date('H:i:s');
$tglBisnis      = ($jamSekarang < $jamMulaiBisnis) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$start_periode  = "$tglBisnis $jamMulaiBisnis";
$end_periode    = date('Y-m-d H:i:s', strtotime("$start_periode +1 day"));

$packages = $pdo->query("SELECT * FROM packages ORDER BY harga ASC")->fetchAll();

$stmtTrx = $pdo->prepare("SELECT terapis_id, COUNT(*) as total_hari_ini FROM transactions WHERE created_at >= ? AND created_at < ? AND status != 'batal' GROUP BY terapis_id");
$stmtTrx->execute([$start_periode, $end_periode]);
$trxHariIni = [];
foreach ($stmtTrx->fetchAll() as $row) { $trxHariIni[$row['terapis_id']] = $row['total_hari_ini']; }

$sqlGiliranTerapis = "SELECT u.id, u.nama_lengkap,
    (SELECT COUNT(*) FROM transactions t WHERE t.terapis_id = u.id AND t.status IN ('proses','menunggu_approval','menunggu_pembayaran')) as is_busy,
    (SELECT COUNT(*) FROM terapis_loans tl JOIN transactions tlt ON tl.transaction_id = tlt.id WHERE tl.terapis_id = u.id AND tl.from_branch_id = ? AND tl.status IN ('active','pending') AND tlt.status IN ('proses','menunggu_approval','menunggu_pembayaran')) as is_loaned,
    (SELECT COUNT(*) FROM transactions t2 WHERE t2.terapis_id = u.id AND t2.created_at >= ? AND t2.created_at < ? AND t2.status != 'batal') as kerja_hari_ini,
    (SELECT MAX(t3.waktu_selesai) FROM transactions t3 WHERE t3.terapis_id = u.id AND t3.created_at >= ? AND t3.created_at < ? AND t3.status IN ('selesai','proses','menunggu_pembayaran')) as last_selesai,
    ta.giliran as giliran_absen,
    ta.waktu_absen,
    ta.waktu_keluar
    FROM users u
    LEFT JOIN terapis_attendance ta ON u.id = ta.terapis_id AND ta.branch_id = ? AND ta.tanggal = ?
    WHERE u.role = 'terapis' AND u.home_branch_id = ?
    ORDER BY (ta.id IS NULL) ASC, (ta.waktu_keluar IS NOT NULL) ASC, kerja_hari_ini ASC, IFNULL(ta.giliran, 9999) ASC, last_selesai ASC, u.nama_lengkap ASC";
$stmtGiliran = $pdo->prepare($sqlGiliranTerapis);
$stmtGiliran->execute([$branch_id, $start_periode, $end_periode, $start_periode, $end_periode, $branch_id, $tglBisnis, $branch_id]);
$giliranTerapis = $stmtGiliran->fetchAll();

$absenHariIni = [];
foreach ($giliranTerapis as $gt) { if ($gt['giliran_absen'] !== null) { $absenHariIni[$gt['id']] = true; } }

$stmtIzinTrx = $pdo->prepare("SELECT terapis_id, jenis, status FROM terapis_izin WHERE branch_id = ? AND tanggal = ? AND status = 'disetujui'");
$stmtIzinTrx->execute([$branch_id, $tglBisnis]);
$izinHariIniMap = [];
foreach ($stmtIzinTrx->fetchAll() as $iz) { $izinHariIniMap[$iz['terapis_id']] = $iz; }

$sqlTerapisLokal = "SELECT DISTINCT u.id, u.nama_lengkap FROM users u
    LEFT JOIN transactions t ON u.id = t.terapis_id AND t.status IN ('proses','menunggu_approval','menunggu_pembayaran')
    LEFT JOIN terapis_loans tl ON u.id = tl.terapis_id AND tl.from_branch_id = ? AND tl.status IN ('active','pending')
    LEFT JOIN transactions tl_t ON tl.transaction_id = tl_t.id AND tl_t.status IN ('proses','menunggu_approval','menunggu_pembayaran')
    WHERE u.role = 'terapis' AND u.home_branch_id = ? AND t.id IS NULL AND tl_t.id IS NULL ORDER BY u.nama_lengkap ASC";
$stmtTerapisLokal = $pdo->prepare($sqlTerapisLokal);
$stmtTerapisLokal->execute([$branch_id, $branch_id]);
$terapisLokal = $stmtTerapisLokal->fetchAll();

$listPaket    = array_filter($packages, fn($p) => $p['is_paket'] == 1);
$listNonPaket = array_filter($packages, fn($p) => $p['is_paket'] == 0);
$listHotel    = array_filter($packages, fn($p) => $p['is_paket'] == 2);
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Panggilan</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .autocomplete-wrapper, .hotel-ac-wrapper, .alamat-wrapper { position: relative; z-index: 99999; }
        .autocomplete-list, .hotel-ac-list, .here-suggest-list { z-index: 99999 !important; }

        .grid-main { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 20px; }
        .card-flow { display: flex; flex-direction: column; gap: 20px; }
        
        .terapis-other-section { margin-top: 15px; padding: 15px; background: var(--bg-input); border-radius: 5px; border: 2px dashed var(--border-color); }
        .btn-load-other { background: var(--accent-blue); color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; width: 100%; font-weight: bold; transition: 0.3s; }
        .btn-load-other:hover { opacity: 0.9; }
        .terapis-item { padding: 12px; margin: 8px 0; background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: 0.2s; display: flex; justify-content: space-between; align-items: center; }
        .terapis-item:hover { transform: translateX(5px); border-color: var(--accent-green); }
        .terapis-item.busy { opacity: 0.6; cursor: not-allowed; }
        .terapis-item.busy:hover { transform: none; border-color: var(--border-color); }
        .badge-status { font-size: 10px; padding: 4px 8px; border-radius: 12px; font-weight: bold; text-transform: uppercase; }
        .bg-online { background: var(--accent-green); color: white; }
        .bg-busy { background: var(--text-muted); color: white; }
        #selectedExternalDisplay { display: none; background: var(--accent-blue); color: white; padding: 15px; border-radius: 10px; margin-bottom: 15px; flex-direction: row; justify-content: space-between; align-items: center; }
        #selectedExternalDisplay .info { flex: 1; }
        #selectedExternalDisplay .actions { display: flex; gap: 10px; align-items: center; }
        
        .payment-options { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        .payment-option { padding: 20px; border-radius: 12px; border: 2px solid var(--border-color); cursor: pointer; transition: all 0.3s ease; text-align: center; position: relative; background: var(--bg-panel); }
        .payment-option:hover { transform: translateY(-3px); }
        .payment-option.selected { border-color: var(--accent-green); background: rgba(39,174,96,0.05); }
        .payment-option .pay-title { font-size: 15px; font-weight: bold; color: var(--text-dark); margin-bottom: 5px; }
        .payment-option .pay-desc { font-size: 12px; color: var(--text-muted); line-height: 1.4; }
        
        .action-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        .btn-pay-now { background: var(--accent-green); color: white; border: none; padding: 15px; border-radius: 10px; font-size: 14px; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; }
        .btn-pay-now:hover { opacity: 0.9; }
        .btn-pay-later { background: var(--accent-blue); color: white; border: none; padding: 15px; border-radius: 10px; font-size: 14px; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; }
        .btn-pay-later:hover { opacity: 0.9; }
        .btn-disabled { opacity: 0.5; cursor: not-allowed !important; transform: none !important; }
        
        .terapis-kerja-info { display: none; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 6px; padding: 6px 10px; margin-top: 6px; font-size: 12px; color: var(--text-muted); }
        .terapis-kerja-info.show { display: block; }
        
        .autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-panel); border: 1px solid var(--border-color); border-top: none; border-radius: 0 0 10px 10px; max-height: 260px; overflow-y: auto; z-index: 1000; display: none; box-shadow: var(--shadow-md); }
        .autocomplete-list.show { display: block; }
        .autocomplete-item { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid var(--border-color); transition: background 0.15s; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item:hover, .autocomplete-item.active { background: var(--bg-input); }
        .autocomplete-item .ac-nama { font-weight: 600; color: var(--text-dark); font-size: 13px; }
        .autocomplete-item .ac-detail { font-size: 11px; color: var(--text-muted); line-height: 1.3; text-align: right; white-space: nowrap; }
        .autocomplete-item .ac-paket { display: inline-block; background: var(--text-dark); color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .autocomplete-item .ac-hp { color: var(--text-muted); font-size: 11px; }
        .autocomplete-item .ac-kunjungan { color: var(--text-mid); font-size: 10px; font-weight: 600; }
        .autocomplete-item .ac-waktu { color: var(--accent-blue); font-size: 10px; font-weight: 500; }
        .autocomplete-loading { padding: 12px; text-align: center; color: var(--text-muted); font-size: 13px; }
        
        .giliran-table-wrapper { max-height: 300px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 10px; }
        .giliran-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .giliran-table thead th { background: var(--bg-input); color: var(--text-muted); padding: 10px 12px; text-align: left; font-size: 11px; position: sticky; top: 0; z-index: 2; }
        .giliran-table tbody tr { border-bottom: 1px solid var(--border-color); cursor: pointer; transition: all 0.2s; }
        .giliran-table tbody tr.available:hover { background: var(--bg-input); }
        .giliran-table tbody tr.selected-row { background: rgba(39,174,96,0.1) !important; border-left: 4px solid var(--accent-green); }
        .giliran-table tbody tr.busy-row, .giliran-table tbody tr.loaned-row, .giliran-table tbody tr.izin-row, .giliran-table tbody tr.belum-absen-row, .giliran-table tbody tr.pulang-row { opacity: 0.55; cursor: not-allowed; background: var(--bg-input); }
        .giliran-table tbody td { padding: 10px 12px; vertical-align: middle; }
        .giliran-no { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 13px; color: white; background: var(--text-muted); }
        .giliran-no.top { background: var(--accent-blue); }
        .giliran-badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; color: white; }
        
        .giliran-selected-info { display: none; background: rgba(46,204,113,0.1); border: 1px solid var(--accent-green); color: var(--text-dark); padding: 12px 16px; border-radius: 8px; margin-top: 10px; font-size: 13px; align-items: center; justify-content: space-between; }
        .giliran-selected-info.show { display: flex; }
        .giliran-selected-info button { background: var(--bg-panel); border: 1px solid var(--border-color); color: var(--text-dark); padding: 6px 14px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 12px; }
        
        .alert-stok { background: rgba(231,76,60,0.1); border-left: 4px solid var(--accent-red); color: var(--accent-red); padding: 15px; border-radius: 5px; margin-bottom: 20px; font-size: 13px; }
        .alert-stok strong { display: block; margin-bottom: 5px; font-size: 14px; }
        
        .pkg-tabs { display: flex; gap: 8px; margin-bottom: 12px; }
        .pkg-tab-btn { flex: 1; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-input); cursor: pointer; font-size: 13px; font-weight: bold; color: var(--text-muted); transition: 0.2s; }
        .pkg-tab-btn.active { background: var(--text-dark); border-color: var(--text-dark); color: var(--bg-panel); }
        .pkg-grid-wrapper { display: none; }
        .pkg-grid-wrapper.show { display: block; }
        .pkg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-top: 10px; }
        .pkg-card { background: var(--bg-panel); border: 2px solid var(--border-color); border-radius: 12px; padding: 15px; cursor: pointer; transition: 0.2s; text-align: center; }
        .pkg-card:hover:not(.pkg-unavailable) { border-color: var(--accent-green); }
        .pkg-card.pkg-selected { border-color: var(--accent-green); background: rgba(39,174,96,0.05); }
        .pkg-card.pkg-unavailable { opacity: 0.5; cursor: not-allowed; background: var(--bg-input); }
        .pkg-card-name { font-weight: bold; color: var(--text-dark); font-size: 13px; margin-bottom: 5px; }
        .pkg-card-price { font-weight: bold; color: var(--accent-green); font-size: 13px; }
        .pkg-card-durasi { font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px; }
        .pkg-empty { text-align: center; padding: 20px; color: var(--text-muted); font-size: 13px; }
        
        .panggilan-banner { background: var(--bg-input); border-left: 4px solid var(--accent-blue); border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .panggilan-banner h3 { margin: 0 0 5px 0; color: var(--text-dark); font-size: 14px; }
        .panggilan-banner p { margin: 0; color: var(--text-muted); font-size: 12px; }
        
        #mapPanggilan { height: 280px; border-radius: 8px; border: 1px solid var(--border-color); margin-top: 10px; display: block; }
        #mapPanggilan.show { display: block; }
        
        .tipe-lokasi-wrapper { display: flex; gap: 10px; margin-bottom: 15px; }
        .tipe-lokasi-btn { flex: 1; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-panel); cursor: pointer; font-size: 13px; font-weight: bold; color: var(--text-muted); text-align: center; }
        .tipe-lokasi-btn.active { background: var(--text-dark); border-color: var(--text-dark); color: var(--bg-panel); }
        
        #adminHotelBox { display: none; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; margin-top: 10px; }
        #adminHotelBox.show { display: block; }
        
        .hotel-ac-list { position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-panel); border: 1px solid var(--border-color); border-top: none; border-radius: 0 0 8px 8px; max-height: 250px; overflow-y: auto; z-index: 9999; display: none; box-shadow: var(--shadow-md); }
        .hotel-ac-list.show { display: block; }
        .hotel-ac-item { padding: 12px 15px; cursor: pointer; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .hotel-ac-item:last-child { border-bottom: none; }
        .hotel-ac-item:hover, .hotel-ac-item.hotel-active { background: var(--bg-input); }
        .hotel-ac-nama { font-weight: bold; color: var(--text-dark); font-size: 13px; }
        .hotel-ac-detail { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        
        .map-loading { display: none; align-items: center; gap: 8px; color: var(--text-muted); font-size: 12px; margin-top: 10px; }
        .map-loading.show { display: flex; }
        .driver-info-box { display: none; background: var(--bg-input); border-radius: 8px; padding: 12px; margin-top: 10px; font-size: 13px; }
        .driver-info-box.show { display: block; }
        .driver-badge-tarif { display: inline-block; background: var(--accent-blue); color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
        .driver-badge-tarif.jauh { background: var(--accent-red); }
        .geocode-error { display: none; margin-top: 10px; background: rgba(231,76,60,0.1); border-left: 4px solid var(--accent-red); color: var(--accent-red); padding: 8px 12px; border-radius: 6px; font-size: 12px; }
        .geocode-error.show { display: block; }
        
        .here-suggest-list { position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-panel); border: 1px solid var(--border-color); border-top: none; border-radius: 0 0 8px 8px; max-height: 240px; overflow-y: auto; z-index: 9999; display: none; box-shadow: var(--shadow-md); }
        .here-suggest-list.show { display: block; }
        .here-suggest-item { padding: 12px 15px; cursor: pointer; border-bottom: 1px solid var(--border-color); }
        .here-suggest-item:hover { background: var(--bg-input); }
        .here-suggest-name { font-weight: bold; font-size: 13px; color: var(--text-dark); }
        .here-suggest-addr { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        
        #alamatPanggilan::-webkit-contacts-auto-fill-button { visibility: hidden; display: none !important; pointer-events: none; }
        #alamatPanggilan::-webkit-credentials-auto-fill-button { visibility: hidden; display: none !important; pointer-events: none; }
        
        @media (max-width: 992px) {
            .grid-main { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .action-buttons { grid-template-columns: 1fr; }
            .payment-options { grid-template-columns: 1fr; }
            .tipe-lokasi-wrapper { flex-direction: column; }
            .grid-2 { grid-template-columns: 1fr; display: flex; flex-direction: column; gap: 15px; }
        }
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
                <a href="transaksi_panggilan.php" class="menu-item active"><span class="menu-abbr">TP</span><span class="menu-text">Transaksi Panggilan</span></a>
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
                    <h1>Transaksi Panggilan</h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
                </div>
            </div>

            <?php if (!empty($problematicItems)): ?>
            <div class="alert-stok">
                <strong>Peringatan Stok!</strong>
                Barang berikut <strong>Kosong (0)</strong> atau <strong>Belum ditambahkan</strong> ke cabang ini:
                <ul style="margin-top:5px; padding-left:20px; font-weight:bold;">
                    <?php foreach($problematicItems as $pItem): ?>
                        <li><?= htmlspecialchars($pItem) ?></li>
                    <?php endforeach; ?>
                </ul>
                <small>Solusi: Buka menu Stok Barang &rarr; Tambah Barang atau Tambah Stok.</small>
            </div>
            <?php endif; ?>

            <form id="formPanggilan" method="POST">
                <input type="hidden" name="action_submit" value="transaksi_panggilan">
                <input type="hidden" name="package_id" id="packageInput" value="">
                <input type="hidden" name="terapis_id_lokal" id="terapisLokalHidden" value="">
                <input type="hidden" name="terapis_id_external" id="terapisIdExternal" value="">
                <input type="hidden" name="terapis_home_branch" id="terapisHomeBranch" value="0">
                <input type="hidden" name="payment_mode" id="paymentModeInput" value="">
                <input type="hidden" name="skip_terapis_id" id="skipTerapisId" value="">
                <input type="hidden" name="skip_keterangan" id="skipKeterangan" value="">
                <input type="hidden" name="tipe_lokasi" id="tipeLokasi" value="rumah">
                <input type="hidden" name="harga_admin_hotel" id="hargaAdminHotelInput" value="0">

                <div class="grid-main">
                    
                    <div class="card-flow">
                        <div class="card" style="padding:20px; z-index:100; overflow:visible !important;">
                            <h3 style="margin:0 0 15px 0; color:var(--text-dark); font-size:15px;">1. Info Lokasi Panggilan</h3>
                            
                            <div class="tipe-lokasi-wrapper">
                                <button type="button" class="tipe-lokasi-btn active" id="btnTipeRumah" onclick="pilihTipeLokasi('rumah')">Rumah / Tempat Tinggal</button>
                                <button type="button" class="tipe-lokasi-btn" id="btnTipeHotel" onclick="pilihTipeLokasi('hotel')">Hotel</button>
                            </div>

                            <div id="blokRumah">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-size: 12px; font-weight: bold;">Alamat Panggilan <span style="color:red;">*</span></label>
                                    <div class="alamat-wrapper">
                                        <input type="text" name="alamat_panggilan" id="alamatPanggilan" class="form-control" placeholder="Ketik nama jalan, tempat, kelurahan..." autocomplete="off">
                                        <div class="here-suggest-list" id="hereSuggestList"></div>
                                    </div>
                                    <small style="color:var(--text-muted); font-size:11px; margin-top:4px; display:block;">
                                        Ketik minimal 3 huruf, pilih dari saran — atau klik/geser pin di peta
                                    </small>
                                </div>

                                <div class="map-loading" id="mapLoading">Memuat peta...</div>
                                <div class="geocode-error" id="geocodeError">Lokasi tidak ditemukan. Coba ketik lebih lengkap atau tambahkan nama kota.</div>
                                <div id="mapPanggilan"></div>

                                <div class="driver-info-box" id="driverInfoBox">
                                    <div style="margin-bottom: 5px;">Jarak dari cabang: <strong id="jarakDisplay" style="color:var(--text-dark);">0 km</strong></div>
                                    <div>Tarif driver: <span class="driver-badge-tarif" id="tarifBadge">0</span></div>
                                    <div style="color:var(--text-muted); font-size:11px; margin-top:5px;">Estimasi jarak lurus</div>
                                </div>

                                <div class="form-group" style="margin-bottom:0; margin-top:14px;">
                                    <label style="font-size: 12px; font-weight: bold;">Biaya Driver (Rp)</label>
                                    <input type="number" name="biaya_driver" id="biayaDriver" class="form-control" min="0" value="0" placeholder="Contoh: 20000" oninput="updateDriverFee()">
                                </div>
                            </div>

                            <div id="blokHotel" style="display:none;">
                                <div class="form-group">
                                    <label style="font-size: 12px; font-weight: bold;">Nama Hotel <span style="color:red;">*</span></label>
                                    <div class="hotel-ac-wrapper">
                                        <input type="text" id="namaHotelInput" class="form-control" placeholder="Ketik nama hotel..." autocomplete="off" oninput="onHotelInput(this.value)" onfocus="onHotelFocus()">
                                        <div class="hotel-ac-list" id="hotelAcList"></div>
                                    </div>
                                    <small style="color:var(--text-muted); font-size:11px; margin-top:4px; display:block;">
                                        Ketik nama hotel — riwayat sebelumnya akan muncul otomatis
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label style="font-size: 12px; font-weight: bold;">Harga Admin Hotel (Rp) <span style="color:red;">*</span></label>
                                    <input type="number" id="hargaAdminHotel" class="form-control" min="0" value="0" placeholder="Contoh: 50000" oninput="document.getElementById('hargaAdminHotelInput').value = this.value; updateDriverFee();">
                                </div>

                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-size: 12px; font-weight: bold;">Biaya Driver (Rp)</label>
                                    <input type="number" name="biaya_driver" id="biayaDriverHotel" class="form-control" min="0" value="0" placeholder="Contoh: 20000" oninput="document.getElementById('biayaDriver').value = this.value; updateDriverFee();">
                                </div>
                            </div>
                        </div>

                        <div class="card" style="padding:20px; z-index:90; overflow:visible !important;">
                            <h3 style="margin:0 0 15px 0; color:var(--text-dark); font-size:15px;">2. Data Pelanggan</h3>
                            <div class="grid-2">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: bold;">Nama Pelanggan <span style="color:red;">*</span></label>
                                    <div class="autocomplete-wrapper">
                                        <input type="text" name="nama_pelanggan" id="namaPelanggan" class="form-control" required autocomplete="off" placeholder="Ketik nama customer...">
                                        <div class="autocomplete-list" id="autocompleteList"></div>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: bold;">No. HP (Opsional)</label>
                                    <input type="text" name="no_hp" id="noHpPelanggan" class="form-control" placeholder="Contoh: 08123456789">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-flow">
                        <div class="card" style="padding:20px; z-index:10;">
                            <h3 style="margin:0 0 15px 0; color:var(--text-dark); font-size:15px;">3. Paket & Layanan</h3>
                            <div class="pkg-tabs">
                                <button type="button" class="pkg-tab-btn active" id="tabPaket" onclick="switchPkgTab('paket')">Paketan</button>
                                <button type="button" class="pkg-tab-btn" id="tabNonPaket" onclick="switchPkgTab('non_paket')">Non Paket</button>
                                <button type="button" class="pkg-tab-btn" id="tabHotel" onclick="switchPkgTab('hotel')">Hotel</button>
                            </div>
                            
                            <div class="pkg-grid-wrapper show" id="gridPaket">
                                <?php if (empty($listPaket)): ?><div class="pkg-empty">Tidak ada paket tersedia.</div><?php else: ?>
                                <div class="pkg-grid">
                                    <?php foreach($listPaket as $p):
                                        $av = isPackageAvailable($p['id'], $pkgRequirements, $branchStocks);
                                        $cc = 'pkg-card' . (!$av ? ' pkg-unavailable' : '');
                                        $ca = $av ? "onclick=\"selectPackageCard(this, {$p['id']}, {$p['harga']}, {$p['durasi_menit']})\"" : "";
                                    ?><div class="<?= $cc ?>" <?= $ca ?> data-pkg-id="<?= $p['id'] ?>" data-harga="<?= $p['harga'] ?>" data-durasi="<?= $p['durasi_menit'] ?>">
                                        <div class="pkg-card-name"><?= htmlspecialchars($p['nama_paket']) ?></div>
                                        <div class="pkg-card-price">Rp <?= number_format($p['harga'],0,',','.') ?></div>
                                        <span class="pkg-card-durasi">Durasi: <?= $p['durasi_menit'] ?> mnt</span>
                                    </div><?php endforeach; ?>
                                </div><?php endif; ?>
                            </div>
                            
                            <div class="pkg-grid-wrapper" id="gridNonPaket">
                                <?php if (empty($listNonPaket)): ?><div class="pkg-empty">Tidak ada layanan non-paket tersedia.</div><?php else: ?>
                                <div class="pkg-grid">
                                    <?php foreach($listNonPaket as $p):
                                        $av = isPackageAvailable($p['id'], $pkgRequirements, $branchStocks);
                                        $cc = 'pkg-card' . (!$av ? ' pkg-unavailable' : '');
                                        $ca = $av ? "onclick=\"selectPackageCard(this, {$p['id']}, {$p['harga']}, {$p['durasi_menit']})\"" : "";
                                    ?><div class="<?= $cc ?>" <?= $ca ?> data-pkg-id="<?= $p['id'] ?>" data-harga="<?= $p['harga'] ?>" data-durasi="<?= $p['durasi_menit'] ?>">
                                        <div class="pkg-card-name"><?= htmlspecialchars($p['nama_paket']) ?></div>
                                        <div class="pkg-card-price">Rp <?= number_format($p['harga'],0,',','.') ?></div>
                                        <span class="pkg-card-durasi">Durasi: <?= $p['durasi_menit'] ?> mnt</span>
                                    </div><?php endforeach; ?>
                                </div><?php endif; ?>
                            </div>

                            <div class="pkg-grid-wrapper" id="gridHotel">
                                <?php if (empty($listHotel)): ?><div class="pkg-empty">Tidak ada paket hotel tersedia.</div><?php else: ?>
                                <div class="pkg-grid">
                                    <?php foreach($listHotel as $p):
                                        $av = isPackageAvailable($p['id'], $pkgRequirements, $branchStocks);
                                        $cc = 'pkg-card' . (!$av ? ' pkg-unavailable' : '');
                                        $ca = $av ? "onclick=\"selectPackageCard(this, {$p['id']}, {$p['harga']}, {$p['durasi_menit']})\"" : "";
                                    ?><div class="<?= $cc ?>" <?= $ca ?> data-pkg-id="<?= $p['id'] ?>" data-harga="<?= $p['harga'] ?>" data-durasi="<?= $p['durasi_menit'] ?>">
                                        <div class="pkg-card-name"><?= htmlspecialchars($p['nama_paket']) ?></div>
                                        <div class="pkg-card-price">Rp <?= number_format($p['harga'],0,',','.') ?></div>
                                        <span class="pkg-card-durasi">Durasi: <?= $p['durasi_menit'] ?> mnt</span>
                                    </div><?php endforeach; ?>
                                </div><?php endif; ?>
                            </div>

                            <div id="priceDisplay" style="display:none; background: var(--text-dark); color: white; padding: 15px 20px; border-radius: 8px; margin-top: 15px; text-align: center;">
                                <div style="font-size: 12px; font-weight:bold; text-transform:uppercase;">Harga Paket (Uang Masuk Sistem)</div>
                                <div id="priceAmount" style="font-size: 24px; font-weight: 800; margin-bottom: 5px;">Rp 0</div>
                                <div id="priceTitipan" style="font-size: 13px; color: var(--accent-yellow2); font-weight: bold; margin-bottom: 5px;">Titipan Luar (Driver/Hotel): Rp 0</div>
                                <div id="priceStruk" style="font-size: 14px; background: rgba(255,255,255,0.2); display: inline-block; padding: 4px 10px; border-radius: 6px;">Total Tagihan Struk: Rp 0</div>
                                <div id="priceDuration" style="font-size: 12px; opacity: 0.8; margin-top: 8px;">0 menit</div>
                            </div>
                        </div>

                        <div class="card" style="padding:20px; z-index:9;">
                            <h3 style="margin:0 0 15px 0; color:var(--text-dark); font-size:15px;">4. Pilih Terapis (Sistem Giliran)</h3>
                            
                            <div id="selectedExternalDisplay">
                                <div class="info">
                                    <div style="font-weight: bold;" id="extName">-</div>
                                    <div style="font-size: 12px; opacity: 0.8;" id="extBranch">-</div>
                                </div>
                                <div class="actions">
                                    <div id="extStatus">-</div>
                                    <button type="button" onclick="cancelExternal()" style="background: var(--bg-panel); color: var(--text-dark); border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold;">Batal</button>
                                </div>
                            </div>

                            <div class="giliran-table-wrapper" id="giliranTableWrapper">
                                <?php if (empty($giliranTerapis)): ?>
                                    <div style="padding: 20px; text-align: center; color: var(--text-muted);">Tidak ada terapis terdaftar.</div>
                                <?php else: ?>
                                <table class="giliran-table">
                                    <thead>
                                        <tr>
                                            <th style="width:50px; text-align:center;">No</th>
                                            <th>Nama Terapis</th>
                                            <th style="text-align:center;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $nomorGiliran = 1;
                                        foreach($giliranTerapis as $gt):
                                            $isBusy      = ($gt['is_busy'] > 0);
                                            $isLoaned    = ($gt['is_loaned'] > 0);
                                            $kerja       = (int)$gt['kerja_hari_ini'];
                                            $sudahAbsen  = ($gt['giliran_absen'] !== null);
                                            $isIzinSakit = isset($izinHariIniMap[$gt['id']]);
                                            $sudahPulang = !empty($gt['waktu_keluar']); // Cek apakah sudah absen keluar
                                            $isAvail     = (!$isBusy && !$isLoaned && $sudahAbsen && !$isIzinSakit && !$sudahPulang);
                                            
                                            $rowClass    = 'available';
                                            if ($isIzinSakit) $rowClass = 'izin-row';
                                            elseif (!$sudahAbsen) $rowClass = 'belum-absen-row';
                                            elseif ($sudahPulang) $rowClass = 'pulang-row'; // Menambah class jika pulang
                                            elseif ($isBusy) $rowClass = 'busy-row';
                                            elseif ($isLoaned) $rowClass = 'loaned-row';
                                            
                                            $clickAct   = $isAvail ? "onclick=\"pilihTerapisGiliran({$gt['id']}, '" . addslashes($gt['nama_lengkap']) . "', this)\"" : "";
                                        ?>
                                        <tr class="<?= $rowClass ?>" <?= $clickAct ?> data-terapis-id="<?= $gt['id'] ?>">
                                            <td style="text-align:center;"><div class="giliran-no <?= ($nomorGiliran <= 3 && $isAvail) ? 'top' : '' ?>"><?= $nomorGiliran ?></div></td>
                                            <td>
                                                <div style="font-weight:bold; color:var(--text-dark);"><?= htmlspecialchars($gt['nama_lengkap']) ?></div>
                                                <?php if ($isBusy && $gt['last_selesai']): ?>
                                                    <div style="font-size:10px; color:var(--accent-red); margin-top:2px;">Selesai: <span class="giliran-countdown" data-finish="<?= $gt['last_selesai'] ?>">...</span></div>
                                                <?php elseif ($gt['last_selesai'] && $kerja > 0): ?>
                                                    <div style="font-size:10px; color:var(--text-muted);">Terakhir: <?= date('H:i', strtotime($gt['last_selesai'])) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <?php if ($isIzinSakit): ?>
                                                    <span class="giliran-badge" style="background:var(--accent-red);">SAKIT/IZIN</span>
                                                <?php elseif (!$sudahAbsen): ?>
                                                    <span class="giliran-badge" style="background:var(--text-muted);">BELUM ABSEN</span>
                                                <?php elseif ($sudahPulang): ?>
                                                    <span class="giliran-badge" style="background:var(--text-muted);">SUDAH PULANG</span>
                                                <?php elseif ($isLoaned): ?>
                                                    <span class="giliran-badge" style="background:var(--accent-red);">DIPINJAM</span>
                                                <?php elseif ($isBusy): ?>
                                                    <span class="giliran-badge" style="background:var(--text-muted);">SIBUK</span>
                                                <?php else: ?>
                                                    <span class="giliran-badge" style="background:var(--accent-green);">READY</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php $nomorGiliran++; endforeach; ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                            </div>

                            <div class="giliran-selected-info" id="giliranSelectedInfo">
                                <span id="giliranSelectedText" style="font-weight:bold;">-</span>
                                <button type="button" onclick="batalPilihGiliran()">Batal</button>
                            </div>

                            <select class="form-control" id="terapisLokalSelect" style="display:none;" onchange="onChangeTerapis()">
                                <option value="">-- Pilih Terapis Lokal --</option>
                                <?php foreach($terapisLokal as $t):
                                    $kaliKerja  = $trxHariIni[$t['id']] ?? 0;
                                    $labelKerja = $kaliKerja > 0 ? " &middot; {$kaliKerja}x hari ini" : "";
                                ?><option value="<?= $t['id'] ?>" data-kerja="<?= $kaliKerja ?>"><?= htmlspecialchars($t['nama_lengkap']) ?><?= $labelKerja ?></option><?php endforeach; ?>
                            </select>
                            <div id="terapisKerjaInfo" class="terapis-kerja-info"></div>

                            <div class="terapis-other-section">
                                <button type="button" class="btn-load-other" id="btnLoadOther">Cari Terapis Cabang Lain</button>
                                <div id="terapisOtherList" style="margin-top: 15px; display: none;"></div>
                            </div>
                        </div>

                        <div class="card" style="padding:20px; z-index:8;">
                            <h3 style="margin:0 0 15px 0; color:var(--text-dark); font-size:15px;">5. Metode Pembayaran</h3>
                            <div class="payment-options">
                                <div class="payment-option" id="optPayNow" onclick="selectPaymentMode('bayar_sekarang')">
                                    <div class="pay-title">Bayar Sekarang</div>
                                    <div class="pay-desc">Arahkan ke kasir untuk bayar</div>
                                </div>
                                <div class="payment-option" id="optPayLater" onclick="selectPaymentMode('bayar_nanti')">
                                    <div class="pay-title">Bayar Nanti</div>
                                    <div class="pay-desc">Pijat dulu, bayar belakangan</div>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <button type="button" id="btnPayNow" onclick="validateAndSubmit('bayar_sekarang')" class="btn-pay-now btn-disabled" disabled>
                                    PROSES &amp; BAYAR
                                </button>
                                <button type="button" id="btnPayLater" onclick="validateAndSubmit('bayar_nanti')" class="btn-pay-later btn-disabled" disabled>
                                    MULAI PANGGILAN
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // MENGAKTIFKAN JS SIDEBAR & THEME KASIR
        function toggleTheme() {
            const html = document.documentElement;
            const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            localStorage.setItem('bugar-theme', next);
        }
        (function() { const saved = localStorage.getItem('bugar-theme'); if (saved) document.documentElement.setAttribute('data-theme', saved); })();

        function toggleSidebar() {
            const sb = document.querySelector('.sidebar');
            if (window.innerWidth <= 992) {
                sb.classList.toggle('active');
            } else {
                sb.classList.toggle('collapsed');
                const btnText = document.querySelector('.sidebar-toggle-btn .menu-text');
                const btnAbbr = document.querySelector('.sidebar-toggle-btn .menu-abbr');
                if (sb.classList.contains('collapsed')) {
                    btnText.style.display = 'none'; btnAbbr.style.display = 'inline';
                } else {
                    btnText.style.display = 'inline'; btnAbbr.style.display = 'none';
                }
            }
        }

        // =====================================================
        // VARIABEL DARI PHP
        // =====================================================
        const trxHariIni    = <?= json_encode($trxHariIni) ?>;
        const CABANG_LAT    = <?= $cabang_lat !== null ? $cabang_lat : 'null' ?>;
        const CABANG_LNG    = <?= $cabang_lng !== null ? $cabang_lng : 'null' ?>;
        const CABANG_NAMA   = <?= json_encode($dataCabang['nama_cabang'] ?? '') ?>;
        const CABANG_ALAMAT = <?= json_encode($dataCabang['alamat'] ?? '') ?>;
        const DRIVER_BASE_KM     = <?= $driver_base_km ?>;
        const DRIVER_BASE_PRICE  = <?= $driver_base_price ?>;
        const DRIVER_EXTRA_PRICE = <?= $driver_extra_price ?>;
        const HERE_API_KEY = 'KOAFw8oT9neUG4QLGEdoU2df3n1kObOB_7RCq_yf9iM';

        let selectedPaymentMode = '';

        // =====================================================
        // AUTO-INIT PETA SAAT HALAMAN LOAD
        // =====================================================
        document.addEventListener('DOMContentLoaded', function() {
            if (CABANG_LAT && CABANG_LNG) {
                const mapEl = document.getElementById('mapPanggilan');
                mapEl.classList.add('show');

                mapInstance = L.map('mapPanggilan', { center: [CABANG_LAT, CABANG_LNG], zoom: 14 });
                L.tileLayer('https://maps.hereapi.com/v3/base/mc/{z}/{x}/{y}/png8?apiKey=' + HERE_API_KEY + '&style=explore.day', {
                    attribution: '\u00a9 <a href="https://www.here.com/">HERE Maps</a>',
                    maxZoom: 20
                }).addTo(mapInstance);

                const iCabang = L.divIcon({
                    html: '<div style="background:var(--accent-blue);color:white;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;box-shadow:0 3px 10px rgba(0,0,0,0.3);border:2px solid white;font-weight:bold;">C</div>',
                    iconSize: [26, 26], iconAnchor: [13, 13], className: ''
                });
                markerCabang = L.marker([CABANG_LAT, CABANG_LNG], { icon: iCabang })
                    .addTo(mapInstance)
                    .bindPopup('<strong>' + CABANG_NAMA + '</strong><br><small>' + CABANG_ALAMAT + '</small>');
                markerCabang.openPopup();

                mapInstance.on('click', function(e) {
                    if (!markerTujuan) {
                        const iTujuan = L.divIcon({
                            html: '<div style="background:var(--text-dark);color:white;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;box-shadow:0 3px 10px rgba(0,0,0,0.3);border:2px solid white;font-weight:bold;">P</div>',
                            iconSize: [26, 26], iconAnchor: [13, 13], className: ''
                        });
                        markerTujuan = L.marker([e.latlng.lat, e.latlng.lng], { icon: iTujuan, draggable: true })
                            .addTo(mapInstance)
                            .bindPopup('<strong>Lokasi Pelanggan</strong><br><small>Geser pin untuk sesuaikan posisi</small>');
                        markerTujuan.openPopup();
                        markerTujuan.on('dragend', function() {
                            const pos = markerTujuan.getLatLng();
                            pindahMarkerTujuan(pos.lat, pos.lng, true);
                        });
                    }
                    pindahMarkerTujuan(e.latlng.lat, e.latlng.lng, true);
                });
            }
        });

        // =====================================================
        // LEAFLET MAP dengan HERE MAPS TILES
        // =====================================================
        let mapInstance  = null;
        let markerCabang = null;
        let markerTujuan = null;
        let garisRute    = null;

        function initMap(latTujuan, lngTujuan) {
            const mapEl = document.getElementById('mapPanggilan');
            mapEl.classList.add('show');

            if (!mapInstance) {
                let cLat = latTujuan, cLng = lngTujuan;
                if (CABANG_LAT && CABANG_LNG) { cLat = (CABANG_LAT + latTujuan) / 2; cLng = (CABANG_LNG + lngTujuan) / 2; }

                mapInstance = L.map('mapPanggilan', { center: [cLat, cLng], zoom: 13 });
                L.tileLayer('https://maps.hereapi.com/v3/base/mc/{z}/{x}/{y}/png8?apiKey=' + HERE_API_KEY + '&style=explore.day', {
                    attribution: '\u00a9 <a href="https://www.here.com/">HERE Maps</a>', maxZoom: 20
                }).addTo(mapInstance);

                if (CABANG_LAT && CABANG_LNG) {
                    const iCabang = L.divIcon({
                        html: '<div style="background:var(--accent-blue);color:white;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;box-shadow:0 3px 10px rgba(0,0,0,0.3);border:2px solid white;font-weight:bold;">C</div>',
                        iconSize: [26, 26], iconAnchor: [13, 13], className: ''
                    });
                    markerCabang = L.marker([CABANG_LAT, CABANG_LNG], { icon: iCabang }).addTo(mapInstance).bindPopup('<strong>' + CABANG_NAMA + '</strong><br><small>' + CABANG_ALAMAT + '</small>');
                    markerCabang.openPopup();
                }

                mapInstance.on('click', function(e) { pindahMarkerTujuan(e.latlng.lat, e.latlng.lng, true); });
            }

            const iTujuan = L.divIcon({
                html: '<div style="background:var(--text-dark);color:white;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;box-shadow:0 3px 10px rgba(0,0,0,0.3);border:2px solid white;font-weight:bold;">P</div>',
                iconSize: [26, 26], iconAnchor: [13, 13], className: ''
            });

            if (markerTujuan) { markerTujuan.setLatLng([latTujuan, lngTujuan]); } 
            else {
                markerTujuan = L.marker([latTujuan, lngTujuan], { icon: iTujuan, draggable: true }).addTo(mapInstance).bindPopup('<strong>Lokasi Pelanggan</strong><br><small>Geser pin untuk sesuaikan posisi</small>');
                markerTujuan.openPopup();
                markerTujuan.on('dragend', function() {
                    const pos = markerTujuan.getLatLng();
                    pindahMarkerTujuan(pos.lat, pos.lng, true);
                });
            }

            pindahMarkerTujuan(latTujuan, lngTujuan, false);

            if (CABANG_LAT && CABANG_LNG) {
                mapInstance.fitBounds(L.latLngBounds([[CABANG_LAT, CABANG_LNG], [latTujuan, lngTujuan]]), { padding: [55, 55] });
            } else {
                mapInstance.setView([latTujuan, lngTujuan], 15);
            }
        }

        // =====================================================
        // PINDAH MARKER + RECALCULATE
        // =====================================================
        function pindahMarkerTujuan(lat, lng, doReverseGeocode) {
            if (markerTujuan) markerTujuan.setLatLng([lat, lng]);
            if (CABANG_LAT && CABANG_LNG) {
                if (garisRute) mapInstance.removeLayer(garisRute);
                garisRute = L.polyline([[CABANG_LAT, CABANG_LNG], [lat, lng]], { color: '#e67e22', weight: 3, dashArray: '8 8', opacity: 0.8 }).addTo(mapInstance);
                const jarak = hitungHaversine(CABANG_LAT, CABANG_LNG, lat, lng);
                tampilInfoDriver(jarak);
            }
            if (doReverseGeocode) reverseGeocode(lat, lng);
        }

        // =====================================================
        // REVERSE GEOCODING — koordinat → alamat teks
        // =====================================================
        function reverseGeocode(lat, lng) {
            const urlRev = 'https://revgeocode.search.hereapi.com/v1/revgeocode?at=' + lat + ',' + lng + '&lang=id&limit=1&apiKey=' + HERE_API_KEY;
            document.getElementById('mapLoading').classList.add('show');
            fetch(urlRev).then(r => r.json()).then(data => {
                document.getElementById('mapLoading').classList.remove('show');
                if (data.items && data.items.length > 0) {
                    const addr = data.items[0].address;
                    const label = addr.label || (addr.street ? addr.street + (addr.city ? ', ' + addr.city : '') : '');
                    if (label) {
                        document.getElementById('alamatPanggilan').value = label;
                        document.getElementById('geocodeError').classList.remove('show');
                    }
                }
            }).catch(() => { document.getElementById('mapLoading').classList.remove('show'); });
        }

        // =====================================================
        // HAVERSINE FORMULA
        // =====================================================
        function hitungHaversine(lat1, lng1, lat2, lng2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        }

        // =====================================================
        // TAMPIL INFO JARAK + SET BIAYA DRIVER
        // =====================================================
        function tampilInfoDriver(jarakKm) {
            document.getElementById('jarakDisplay').textContent = jarakKm.toFixed(2) + ' km';
            let tarif, labelText, isJauh;
            if (jarakKm <= DRIVER_BASE_KM) {
                tarif = DRIVER_BASE_PRICE;
                labelText = '\u2264 ' + DRIVER_BASE_KM + ' km \u2192 Rp ' + DRIVER_BASE_PRICE.toLocaleString('id-ID');
                isJauh = false;
            } else {
                tarif = DRIVER_EXTRA_PRICE;
                labelText = '> ' + DRIVER_BASE_KM + ' km \u2192 Rp ' + DRIVER_EXTRA_PRICE.toLocaleString('id-ID');
                isJauh = true;
            }
            const badge = document.getElementById('tarifBadge');
            badge.textContent = labelText;
            badge.classList.toggle('jauh', isJauh);
            document.getElementById('driverInfoBox').classList.add('show');
            document.getElementById('biayaDriver').value = tarif;
            updateDriverFee();
        }

        // =====================================================
        // HERE MAPS GEOCODING AUTOCOMPLETE
        // =====================================================
        let mbTimer = null; let mbIndex = -1;
        const inputAlamat = document.getElementById('alamatPanggilan');
        const suggestList = document.getElementById('hereSuggestList');

        inputAlamat.addEventListener('input', function() {
            clearTimeout(mbTimer); mbIndex = -1;
            const q = this.value.trim();
            document.getElementById('geocodeError').classList.remove('show');
            if (q.length < 3) { suggestList.classList.remove('show'); suggestList.innerHTML = ''; return; }
            suggestList.innerHTML = '<div class="here-suggest-loading">Mencari...</div>';
            suggestList.classList.add('show');
            mbTimer = setTimeout(() => fetchMapboxSuggest(q), 400);
        });

        function fetchMapboxSuggest(q) {
            const urlAutocomp = 'https://autocomplete.search.hereapi.com/v1/autocomplete?q=' + encodeURIComponent(q) + '&in=countryCode:IDN&lang=id&limit=5&apiKey=' + HERE_API_KEY;
            fetch(urlAutocomp).then(r => r.json()).then(data => {
                if (!data.items || data.items.length === 0) {
                    suggestList.innerHTML = '<div class="here-suggest-loading">Tidak ada hasil. Coba lebih lengkap.</div>';
                    return;
                }
                let html = '';
                data.items.forEach(item => {
                    const nama = item.title || '';
                    const addr = item.address ? item.address.label || '' : '';
                    const addrShort = addr !== nama ? addr : '';
                    const itemId = encodeURIComponent(item.id || '');
                    html += `<div class="here-suggest-item" data-id="${itemId}" data-nama="${nama.replace(/"/g,'&quot;')}" onclick="pilihSuggest(this)">
                        <div class="here-suggest-name">${nama}</div>
                        ${addrShort ? '<div class="here-suggest-addr">' + addrShort + '</div>' : ''}
                    </div>`;
                });
                suggestList.innerHTML = html;
                suggestList.classList.add('show');
            }).catch(() => { suggestList.innerHTML = '<div class="here-suggest-loading">Gagal terhubung. Cek koneksi.</div>'; });
        }

        function pilihSuggest(el) {
            const itemId = decodeURIComponent(el.dataset.id);
            const nama = el.dataset.nama;
            inputAlamat.value = nama;
            suggestList.classList.remove('show'); suggestList.innerHTML = ''; mbIndex = -1;
            document.getElementById('geocodeError').classList.remove('show');
            document.getElementById('mapLoading').classList.add('show');

            const urlGeocode = 'https://geocode.search.hereapi.com/v1/geocode?q=' + encodeURIComponent(nama) + '&in=countryCode:IDN&lang=id&limit=1&apiKey=' + HERE_API_KEY;
            fetch(urlGeocode).then(r => r.json()).then(data => {
                document.getElementById('mapLoading').classList.remove('show');
                if (data.items && data.items.length > 0) {
                    const pos = data.items[0].position;
                    initMap(pos.lat, pos.lng);
                } else { document.getElementById('geocodeError').classList.add('show'); }
            }).catch(() => {
                document.getElementById('mapLoading').classList.remove('show');
                document.getElementById('geocodeError').classList.add('show');
            });
        }

        inputAlamat.addEventListener('keydown', function(e) {
            const items = suggestList.querySelectorAll('.here-suggest-item');
            if (!items.length || !suggestList.classList.contains('show')) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); mbIndex = Math.min(mbIndex + 1, items.length - 1); items.forEach((it, n) => it.classList.toggle('active', n === mbIndex)); } 
            else if (e.key === 'ArrowUp') { e.preventDefault(); mbIndex = Math.max(mbIndex - 1, 0); items.forEach((it, n) => it.classList.toggle('active', n === mbIndex)); } 
            else if (e.key === 'Enter' && mbIndex >= 0) { e.preventDefault(); items[mbIndex].click(); } 
            else if (e.key === 'Escape') { suggestList.classList.remove('show'); mbIndex = -1; }
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.alamat-wrapper')) { suggestList.classList.remove('show'); mbIndex = -1; }
        });

        // =====================================================
        // PACKAGE CARD SELECTION
        // =====================================================
        function switchPkgTab(tab) {
            ['tabPaket','tabNonPaket','tabHotel'].forEach(id => document.getElementById(id).classList.remove('active'));
            ['gridPaket','gridNonPaket','gridHotel'].forEach(id => document.getElementById(id).classList.remove('show'));
            const tabMap = { paket:'tabPaket', non_paket:'tabNonPaket', hotel:'tabHotel' };
            const gridMap = { paket:'gridPaket', non_paket:'gridNonPaket', hotel:'gridHotel' };
            document.getElementById(tabMap[tab]).classList.add('active');
            document.getElementById(gridMap[tab]).classList.add('show');
        }

        function selectPackageCard(el, id, harga, durasi) {
            document.querySelectorAll('.pkg-card').forEach(c => c.classList.remove('pkg-selected'));
            el.classList.add('pkg-selected');
            document.getElementById('packageInput').value = id;
            updateDriverFee(); 
        }

        function updateDriverFee() {
            const pkgId = document.getElementById('packageInput').value;
            if (!pkgId) return;
            const card = document.querySelector('.pkg-card[data-pkg-id="' + pkgId + '"]');
            if (!card) return;
            const biaya = parseInt(document.getElementById('biayaDriver').value) || 0;
            const adminHotel = parseInt(document.getElementById('hargaAdminHotelInput').value) || 0;
            const hargaPaket = parseInt(card.dataset.harga) || 0;
            const totalTitipan = biaya + adminHotel;
            const grandTotal = hargaPaket + totalTitipan;
            
            document.getElementById('priceAmount').textContent = 'Rp ' + hargaPaket.toLocaleString('id-ID');
            document.getElementById('priceTitipan').textContent = 'Titipan Luar (Driver/Hotel): Rp ' + totalTitipan.toLocaleString('id-ID');
            document.getElementById('priceStruk').textContent = 'Total Cetak Struk: Rp ' + grandTotal.toLocaleString('id-ID');
            
            document.getElementById('priceDuration').textContent = card.dataset.durasi + ' menit';
            document.getElementById('priceDisplay').style.display = 'block';
        }

        // =====================================================
        // TIPE LOKASI: RUMAH / HOTEL
        // =====================================================
        function pilihTipeLokasi(tipe) {
            document.getElementById('tipeLokasi').value = tipe;
            document.getElementById('btnTipeRumah').classList.toggle('active', tipe === 'rumah');
            document.getElementById('btnTipeHotel').classList.toggle('active', tipe === 'hotel');

            if (tipe === 'hotel') {
                document.getElementById('blokRumah').style.display = 'none';
                document.getElementById('blokHotel').style.display = 'block';
                document.getElementById('alamatPanggilan').value = '';
                document.getElementById('biayaDriver').value = 0;
            } else {
                document.getElementById('blokHotel').style.display = 'none';
                document.getElementById('blokRumah').style.display = 'block';
                document.getElementById('namaHotelInput').value = '';
                document.getElementById('hargaAdminHotel').value = 0;
                document.getElementById('hargaAdminHotelInput').value = 0;
                document.getElementById('biayaDriverHotel').value = 0;
                document.getElementById('biayaDriver').value = 0;
                updateDriverFee();
            }
        }

        // =====================================================
        // AUTOCOMPLETE HOTEL (riwayat dari DB)
        // =====================================================
        let hotelAcTimer = null; let hotelAcIdx = -1;
        const hotelInput = document.getElementById('namaHotelInput');
        const hotelAcList = document.getElementById('hotelAcList');

        function syncAlamatDariHotel() {
            const namaHotel = hotelInput.value.trim();
            document.getElementById('alamatPanggilan').value = namaHotel;
            updateDriverFee();
        }

        function onHotelInput(val) {
            syncAlamatDariHotel();
            clearTimeout(hotelAcTimer); hotelAcIdx = -1;
            hotelAcTimer = setTimeout(() => fetchHotelHistory(val.trim()), 250);
        }

        function onHotelFocus() { fetchHotelHistory(hotelInput.value.trim()); }

        function fetchHotelHistory(q) {
            fetch('ajax_hotel_history.php?q=' + encodeURIComponent(q)).then(r => r.json()).then(data => {
                if (!data.success || !data.hotels.length) { hotelAcList.classList.remove('show'); hotelAcList.innerHTML = ''; return; }
                renderHotelList(data.hotels);
            }).catch(() => { hotelAcList.classList.remove('show'); });
        }

        function renderHotelList(hotels) {
            let html = '';
            hotels.forEach((h, i) => {
                const adminFmt = 'Rp ' + parseInt(h.harga_admin_hotel).toLocaleString('id-ID');
                const driverFmt = h.biaya_driver > 0 ? 'Rp ' + parseInt(h.biaya_driver).toLocaleString('id-ID') : 'Gratis';
                html += `<div class="hotel-ac-item" data-nama="${escHtmlHotel(h.nama_hotel)}" data-admin="${h.harga_admin_hotel}" data-driver="${h.biaya_driver}" onclick="pilihHotel(this)">
                    <div style="flex:1;min-width:0;">
                        <div class="hotel-ac-nama">${escHtmlHotel(h.nama_hotel)}</div>
                        <div class="hotel-ac-detail">Admin: <strong>${adminFmt}</strong> &nbsp;|&nbsp; Driver: <strong>${driverFmt}</strong></div>
                    </div>
                </div>`;
            });
            hotelAcList.innerHTML = html; hotelAcList.classList.add('show');
        }

        function pilihHotel(el) {
            const nama = el.dataset.nama;
            const admin = parseInt(el.dataset.admin) || 0;
            const driver = parseInt(el.dataset.driver) || 0;
            hotelInput.value = nama; document.getElementById('alamatPanggilan').value = nama;
            document.getElementById('hargaAdminHotel').value = admin; document.getElementById('hargaAdminHotelInput').value = admin;
            document.getElementById('biayaDriverHotel').value = driver; document.getElementById('biayaDriver').value = driver;
            hotelAcList.classList.remove('show'); hotelAcList.innerHTML = ''; hotelAcIdx = -1;
            updateDriverFee();
        }

        function escHtmlHotel(str) { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

        hotelInput.addEventListener('keydown', function(e) {
            const items = hotelAcList.querySelectorAll('.hotel-ac-item');
            if (!items.length || !hotelAcList.classList.contains('show')) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); hotelAcIdx = Math.min(hotelAcIdx + 1, items.length - 1); items.forEach((it, n) => it.classList.toggle('hotel-active', n === hotelAcIdx)); items[hotelAcIdx]?.scrollIntoView({ block: 'nearest' }); } 
            else if (e.key === 'ArrowUp') { e.preventDefault(); hotelAcIdx = Math.max(hotelAcIdx - 1, 0); items.forEach((it, n) => it.classList.toggle('hotel-active', n === hotelAcIdx)); items[hotelAcIdx]?.scrollIntoView({ block: 'nearest' }); } 
            else if (e.key === 'Enter' && hotelAcIdx >= 0) { e.preventDefault(); items[hotelAcIdx].click(); } 
            else if (e.key === 'Escape') { hotelAcList.classList.remove('show'); hotelAcIdx = -1; }
        });

        document.addEventListener('click', function(e) { if (!e.target.closest('.hotel-ac-wrapper')) { hotelAcList.classList.remove('show'); hotelAcIdx = -1; } });

        // =====================================================
        // AUTOCOMPLETE CUSTOMER
        // =====================================================
        let acTimeout = null, acIndex = -1;
        const inputNama = document.getElementById('namaPelanggan');
        const acList = document.getElementById('autocompleteList');

        inputNama.addEventListener('input', function() {
            const val = this.value.trim();
            clearTimeout(acTimeout); acIndex = -1;
            if (val.length < 1) { acList.classList.remove('show'); acList.innerHTML = ''; return; }
            acList.innerHTML = '<div class="autocomplete-loading">Mencari...</div>';
            acList.classList.add('show');
            acTimeout = setTimeout(() => {
                fetch('ajax_search_customer.php?q=' + encodeURIComponent(val)).then(r => r.json()).then(data => {
                    if (!data.success || !data.customers.length) { acList.classList.remove('show'); acList.innerHTML = ''; return; }
                    let html = '';
                    data.customers.forEach(c => {
                        const hp = c.no_hp && c.no_hp !== '-' ? c.no_hp : '-';
                        html += `<div class="autocomplete-item" data-nama="${escHtml(c.nama)}" data-hp="${escHtml(hp)}" data-pkgid="${c.package_id||''}" onclick="pilihCustomer(this)">
                            <div style="flex:1;min-width:0;"><div class="ac-nama">${highlightMatch(c.nama, inputNama.value)}</div><div class="ac-hp">${hp}</div></div>
                        </div>`;
                    });
                    acList.innerHTML = html; acList.classList.add('show');
                }).catch(() => { acList.classList.remove('show'); });
            }, 250);
        });

        function pilihCustomer(el) {
            document.getElementById('namaPelanggan').value = el.dataset.nama;
            if (el.dataset.hp && el.dataset.hp !== '-') document.getElementById('noHpPelanggan').value = el.dataset.hp;
            if (el.dataset.pkgid) {
                const card = document.querySelector('.pkg-card[data-pkg-id="'+el.dataset.pkgid+'"]:not(.pkg-unavailable)');
                if (card) { switchPkgTab(card.closest('#gridPaket') ? 'paket' : (card.closest('#gridHotel') ? 'hotel' : 'non_paket')); selectPackageCard(card, el.dataset.pkgid, card.dataset.harga, card.dataset.durasi); }
            }
            acList.classList.remove('show'); acList.innerHTML = '';
        }

        inputNama.addEventListener('keydown', function(e) {
            const items = acList.querySelectorAll('.autocomplete-item');
            if (!items.length || !acList.classList.contains('show')) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); acIndex = Math.min(acIndex+1, items.length-1); items.forEach((i,n)=>i.classList.toggle('active',n===acIndex)); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); acIndex = Math.max(acIndex-1, 0); items.forEach((i,n)=>i.classList.toggle('active',n===acIndex)); }
            else if (e.key === 'Enter' && acIndex >= 0) { e.preventDefault(); items[acIndex].click(); }
            else if (e.key === 'Escape') { acList.classList.remove('show'); acIndex = -1; }
        });
        document.addEventListener('click', e => { if (!e.target.closest('.autocomplete-wrapper')) { acList.classList.remove('show'); acIndex = -1; } });
        function escHtml(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
        function highlightMatch(text, q) { if(!q) return escHtml(text); return escHtml(text).replace(new RegExp('('+q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','gi'),'<strong style="color:var(--accent-blue);">$1</strong>'); }

        // =====================================================
        // SISTEM GILIRAN TERAPIS
        // =====================================================
        let selectedGiliranId = 0;

        function getFirstAvailableTerapis() {
            const rows = document.querySelectorAll('.giliran-table tbody tr.available');
            if (rows.length === 0) return null;
            const first = rows[0];
            return { id: parseInt(first.dataset.terapisId), nama: first.querySelector('td:nth-child(2) div').textContent.trim(), row: first };
        }

        function pilihTerapisGiliran(id, nama, rowEl) {
            const firstAvailable = getFirstAvailableTerapis();
            if (firstAvailable && firstAvailable.id !== id) {
                Swal.fire({
                    title: 'Giliran Dilompati!',
                    html: '<div style="text-align:left;font-size:14px;"><p>Terapis <strong>' + escHtml(firstAvailable.nama) + '</strong> (No. 1) seharusnya dipilih terlebih dahulu.</p><p style="color:var(--accent-red);font-weight:bold;margin-top:10px;">Mohon isi alasan melompati giliran (Tolak Pasien):</p></div>',
                    input: 'textarea', inputPlaceholder: 'Contoh: Terapis menolak...', inputValidator: function(value) { if (!value || value.trim().length < 5) return 'Alasan wajib diisi (min 5 karakter)!'; },
                    showCancelButton: true, confirmButtonColor: '#e74c3c', confirmButtonText: 'Lompati & Catat', cancelButtonText: 'Batal'
                }).then(function(result) {
                    if (result.isConfirmed && result.value) {
                        document.getElementById('skipTerapisId').value = firstAvailable.id;
                        document.getElementById('skipKeterangan').value = result.value.trim();
                        doSelectTerapis(id, nama, rowEl);
                    }
                });
            } else {
                document.getElementById('skipTerapisId').value = ''; document.getElementById('skipKeterangan').value = '';
                doSelectTerapis(id, nama, rowEl);
            }
        }

        function doSelectTerapis(id, nama, rowEl) {
            selectedGiliranId = id;
            document.getElementById('terapisLokalHidden').value = id;
            resetExternal();
            document.querySelectorAll('.giliran-table tbody tr').forEach(tr => tr.classList.remove('selected-row'));
            rowEl.classList.add('selected-row');
            document.getElementById('giliranSelectedText').innerText = 'Terpilih: ' + nama;
            document.getElementById('giliranSelectedInfo').classList.add('show');
            const el = document.getElementById('terapisKerjaInfo');
            el.innerText = trxHariIni[id] > 0 ? 'Sudah melayani ' + trxHariIni[id] + ' pelanggan' : 'Belum ada transaksi hari ini';
            el.classList.add('show');
        }

        function batalPilihGiliran() {
            selectedGiliranId = 0; document.getElementById('terapisLokalHidden').value = '';
            document.querySelectorAll('.giliran-table tbody tr').forEach(tr => tr.classList.remove('selected-row'));
            document.getElementById('giliranSelectedInfo').classList.remove('show');
            document.getElementById('terapisKerjaInfo').classList.remove('show');
        }

        function onChangeTerapis() {
            const val = document.getElementById('terapisLokalSelect').value;
            document.getElementById('terapisLokalHidden').value = val;
            resetExternal();
            const el = document.getElementById('terapisKerjaInfo');
            if (val && parseInt(val) > 0) {
                const kali = trxHariIni[parseInt(val)] || 0;
                el.innerText = kali > 0 ? 'Sudah melayani ' + kali + ' pelanggan' : 'Belum ada transaksi hari ini';
                el.classList.add('show');
            } else el.classList.remove('show');
        }

        // =====================================================
        // TERAPIS CABANG LAIN (Otomatis Pilih)
        // =====================================================
        document.getElementById('btnLoadOther').addEventListener('click', function() {
            this.innerText = 'Memuat...'; this.disabled = true;
            fetch('ajax_get_terapis_other_branch.php').then(r=>r.json()).then(data => {
                this.innerText = 'Cari Terapis Cabang Lain'; this.disabled = false;
                if (data.success) { renderExternalList(data.terapis); document.getElementById('terapisOtherList').style.display='block'; }
                else Swal.fire('Error', data.message, 'error');
            }).catch(() => { this.innerText='Cari Terapis Cabang Lain'; this.disabled=false; Swal.fire('Gagal','Koneksi error','error'); });
        });

        function renderExternalList(terapis) {
            const list = document.getElementById('terapisOtherList');
            if (!terapis.length) { list.innerHTML='<div style="text-align:center;padding:10px;color:var(--text-muted);">Tidak ada terapis tersedia.</div>'; return; }
            const grouped = {};
            terapis.forEach(t => { if(!grouped[t.branch_name]) grouped[t.branch_name]=[]; grouped[t.branch_name].push(t); });
            let html = '';
            for (const [branch, items] of Object.entries(grouped)) {
                html += `<div style="margin-top:15px;font-weight:bold;border-bottom:1px solid var(--border-color);padding-bottom:5px;">${branch}</div>`;
                items.forEach(t => {
                    if (t.is_busy||t.is_loaned) { html += `<div class="terapis-item busy"><span>${t.nama_lengkap}</span><span class="badge-status bg-busy">SIBUK</span></div>`; } 
                    else { html += `<div class="terapis-item" onclick="chooseExternal(${t.id},'${t.nama_lengkap}',${t.branch_id},'${branch}')"><span>${t.nama_lengkap}</span><span class="badge-status bg-online">READY</span></div>`; }
                });
            }
            list.innerHTML = html;
        }

        function chooseExternal(id, nama, branchId, branchName) {
            const kali = trxHariIni[id]||0;
            Swal.fire({
                title:'Konfirmasi Peminjaman',
                html:`Pinjam <b>${nama}</b> dari cabang <b>${branchName}</b>?<br><br>Terapis akan langsung dipakai tanpa perlu approval.`,
                icon:'success', showCancelButton:true, confirmButtonText:'Ya, Pilih', cancelButtonText:'Batal'
            }).then(res => {
                if (res.isConfirmed) {
                    document.getElementById('terapisIdExternal').value  = parseInt(id);
                    document.getElementById('terapisHomeBranch').value  = parseInt(branchId);
                    batalPilihGiliran();
                    document.getElementById('terapisLokalHidden').value = '';
                    document.getElementById('terapisLokalSelect').value = '';
                    document.getElementById('giliranTableWrapper').style.display = 'none';
                    document.getElementById('terapisOtherList').style.display    = 'none';
                    document.getElementById('terapisKerjaInfo').classList.remove('show');
                    document.getElementById('extName').innerText   = nama;
                    document.getElementById('extBranch').innerText = branchName;
                    document.getElementById('extStatus').innerHTML = '<span class="badge-status bg-online">AUTO APPROVE</span>';
                    document.getElementById('selectedExternalDisplay').style.display = 'flex';
                }
            });
        }

        function resetExternal() {
            if (document.getElementById('terapisLokalHidden').value !== '') {
                document.getElementById('terapisIdExternal').value = ''; document.getElementById('terapisHomeBranch').value = '0';
                document.getElementById('selectedExternalDisplay').style.display = 'none';
            }
        }

        function cancelExternal() {
            document.getElementById('terapisIdExternal').value = ''; document.getElementById('terapisHomeBranch').value = '0';
            document.getElementById('selectedExternalDisplay').style.display = 'none'; document.getElementById('giliranTableWrapper').style.display = 'block';
            document.getElementById('terapisLokalSelect').value = ''; document.getElementById('terapisKerjaInfo').classList.remove('show');
            batalPilihGiliran();
        }

        // =====================================================
        // PAYMENT MODE & SUBMIT
        // =====================================================
        function selectPaymentMode(mode) {
            selectedPaymentMode = mode;
            document.getElementById('paymentModeInput').value = mode;
            document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
            if (mode === 'bayar_sekarang') {
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
            const tipe   = document.getElementById('tipeLokasi').value;
            const pkg    = document.getElementById('packageInput').value;
            const lokal  = document.getElementById('terapisLokalHidden').value;
            const ext    = document.getElementById('terapisIdExternal').value;
            const nama   = document.getElementById('namaPelanggan').value.trim();

            if (tipe === 'hotel') {
                const namaHotel = document.getElementById('namaHotelInput').value.trim();
                if (!namaHotel) return Swal.fire('Gagal','Isi Nama Hotel!','warning');
                const adminHotel = parseFloat(document.getElementById('hargaAdminHotelInput').value) || 0;
                if (adminHotel <= 0) return Swal.fire('Gagal','Isi Harga Admin Hotel!','warning');
                document.getElementById('alamatPanggilan').value = namaHotel;
                document.getElementById('biayaDriver').value = document.getElementById('biayaDriverHotel').value;
            } else {
                const alamat = document.getElementById('alamatPanggilan').value.trim();
                if (!alamat) return Swal.fire('Gagal','Isi Alamat Panggilan!','warning');
            }

            if (!nama)   return Swal.fire('Gagal','Isi Nama Pelanggan!','warning');
            if (!pkg)    return Swal.fire('Gagal','Pilih Paket!','warning');
            if ((!lokal||parseInt(lokal)<=0) && (!ext||parseInt(ext)<=0)) return Swal.fire('Gagal','Pilih Terapis dulu!','warning');

            document.getElementById('paymentModeInput').value = mode;
            Swal.fire({
                title:  mode === 'bayar_sekarang' ? 'Bayar Sekarang?' : 'Mulai Panggilan?',
                text:   mode === 'bayar_sekarang' ? 'Anda akan diarahkan ke halaman pembayaran terlebih dahulu.' : 'Panggilan akan dicatat. Struk sementara dicetak otomatis.',
                icon:   'question', showCancelButton: true, confirmButtonText: 'Ya, Lanjutkan!', cancelButtonText: 'Batal'
            }).then(r => { if (r.isConfirmed) document.getElementById('formPanggilan').submit(); });
        }

        setInterval(() => {
            document.querySelectorAll('.giliran-countdown').forEach(el => {
                const diff = new Date(el.dataset.finish) - new Date();
                if (diff <= 0) {
                    const ov = Math.abs(diff), m = Math.floor(ov/60000), s = Math.floor((ov%60000)/1000);
                    el.innerText = 'OT +'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
                } else {
                    const m = Math.floor(diff/60000), s = Math.floor((diff%60000)/1000);
                    el.innerText = String(m).padStart(2,'0')+':'+String(s).padStart(2,'0')+' lagi';
                }
            });
        }, 1000);
    </script>
    <script><?= $swal_script ?></script>
</body>
</html>