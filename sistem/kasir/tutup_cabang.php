<?php
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if ($_SESSION['role'] != 'kasir') { header("Location: ../auth/login_system.php"); exit; }
if (!isset($_SESSION['active_branch'])) { header("Location: pilih_cabang.php"); exit; }

$kasir_id = $_SESSION['user_id'];
$session_id = $_SESSION['session_id'];
$attendance_id = $_SESSION['attendance_id'];
$branch_id = $_SESSION['active_branch'];
$waktu_buka = $_SESSION['waktu_buka'];

$nama_kasir = $_SESSION['nama'];
$nama_cabang = $pdo->query("SELECT nama_cabang FROM branches WHERE id = $branch_id")->fetchColumn();

// Foto profil
$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$foto_profil = $stmtProfil->fetchColumn();
$foto_profil = (!empty($foto_profil) && file_exists("../uploads/profil/" . $foto_profil)) ? "../uploads/profil/" . $foto_profil : "../assets/default_user.png";


// --- HITUNG RINCIAN OMSET (FIX: PISAHKAN BIAYA DRIVER & HOTEL DARI OMSET) ---
$sqlShift = "SELECT 
             COUNT(*) as total_transaksi,
             COALESCE(SUM(total_bayar - COALESCE(biaya_driver, 0) - COALESCE(harga_admin_hotel, 0)), 0) as omset_gross,
             COALESCE(SUM(total_bayar), 0) as total_uang_masuk,
             COALESCE(SUM(omset_cabang), 0) as omset_netto,
             COALESCE(SUM(omset_terapis), 0) as omset_terapis,
             COALESCE(SUM(biaya_driver), 0) as total_biaya_driver,
             COALESCE(SUM(harga_admin_hotel), 0) as total_admin_hotel
             FROM transactions 
             WHERE kasir_id = ? 
             AND branch_id = ? 
             AND created_at >= ?
             AND status IN ('selesai','proses','menunggu_pembayaran')";
$stmtShift = $pdo->prepare($sqlShift);
$stmtShift->execute([$kasir_id, $branch_id, $waktu_buka]);
$dataShift = $stmtShift->fetch();

// =====================================================
// PENGELUARAN YANG SUDAH DICATAT
// =====================================================
$sqlExpenses = "SELECT * FROM shift_expenses WHERE attendance_id = ? ORDER BY created_at ASC";
$stmtExpenses = $pdo->prepare($sqlExpenses);
$stmtExpenses->execute([$attendance_id]);
$pengeluaran = $stmtExpenses->fetchAll();

$totalPengeluaran = 0;
foreach ($pengeluaran as $p) { $totalPengeluaran += $p['jumlah']; }

// =====================================================
// BREAKDOWN METODE PEMBAYARAN
// =====================================================
$sqlMetode = "SELECT metode_pembayaran, COUNT(*) as jumlah, COALESCE(SUM(total_bayar), 0) as total
              FROM transactions 
              WHERE kasir_id = ? AND branch_id = ? AND created_at >= ? AND status IN ('selesai','proses','menunggu_pembayaran')
              GROUP BY metode_pembayaran ORDER BY total DESC";
$stmtMetode = $pdo->prepare($sqlMetode);
$stmtMetode->execute([$kasir_id, $branch_id, $waktu_buka]);
$metodeData = $stmtMetode->fetchAll();

$metodeBreakdown = [];
$totalTunai = 0; $totalTransfer = 0; $totalQris = 0; $totalDebit = 0; $totalBayarNanti = 0;
$countTunai = 0; $countTransfer = 0; $countQris = 0; $countDebit = 0; $countBayarNanti = 0;

foreach ($metodeData as $m) {
    $key = $m['metode_pembayaran'] ?? 'belum_bayar';
    $metodeBreakdown[$key] = $m;
    switch ($key) {
        case 'tunai': $totalTunai = $m['total']; $countTunai = $m['jumlah']; break;
        case 'transfer': $totalTransfer = $m['total']; $countTransfer = $m['jumlah']; break;
        case 'qris': $totalQris = $m['total']; $countQris = $m['jumlah']; break;
        case 'debit': $totalDebit = $m['total']; $countDebit = $m['jumlah']; break;
        case 'bayar_nanti': $totalBayarNanti = $m['total']; $countBayarNanti = $m['jumlah']; break;
    }
}
$totalNonTunai = $totalTransfer + $totalQris + $totalDebit;
$countNonTunai = $countTransfer + $countQris + $countDebit;

// Cek transaksi menunggu pembayaran
$stmtPending = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_bayar),0) as total FROM transactions WHERE kasir_id = ? AND branch_id = ? AND created_at >= ? AND status = 'menunggu_pembayaran'");
$stmtPending->execute([$kasir_id, $branch_id, $waktu_buka]);
$pendingPay = $stmtPending->fetch();

// Detail transaksi (ditambahkan pemanggilan biaya_driver & harga_admin_hotel)
$stmtTrx = $pdo->prepare("SELECT t.*, p.nama_paket, u.nama_lengkap as nama_terapis FROM transactions t JOIN packages p ON t.package_id = p.id JOIN users u ON t.terapis_id = u.id WHERE t.kasir_id = ? AND t.branch_id = ? AND t.created_at >= ? AND t.status IN ('selesai','proses','menunggu_pembayaran') ORDER BY t.created_at DESC");
$stmtTrx->execute([$kasir_id, $branch_id, $waktu_buka]);
$transaksi = $stmtTrx->fetchAll();

$addedPackages = [];
if (!empty($transaksi)) {
    try {
        $pdo->query("SELECT 1 FROM transaction_added_packages LIMIT 1");
        $trxIds = array_column($transaksi, 'id');
        $placeholders = implode(',', array_fill(0, count($trxIds), '?'));
        $stmtAdded = $pdo->prepare("SELECT * FROM transaction_added_packages WHERE transaction_id IN ($placeholders) ORDER BY created_at ASC");
        $stmtAdded->execute($trxIds);
        foreach ($stmtAdded->fetchAll() as $ap) { $addedPackages[$ap['transaction_id']][] = $ap; }
    } catch (Exception $e) {}
}

// =====================================================
// HANDLER: TAMBAH/HAPUS PENGELUARAN (AJAX)
// =====================================================
if (isset($_POST['action']) && $_POST['action'] == 'tambah_pengeluaran') {
    header('Content-Type: application/json');
    $keterangan = trim($_POST['keterangan'] ?? ''); $jumlah = floatval($_POST['jumlah'] ?? 0);
    if (empty($keterangan)) { echo json_encode(['success' => false, 'message' => 'Keterangan harus diisi!']); exit; }
    if ($jumlah <= 0) { echo json_encode(['success' => false, 'message' => 'Jumlah harus lebih dari 0!']); exit; }
    try {
        $pdo->prepare("INSERT INTO shift_expenses (attendance_id, kasir_id, branch_id, keterangan, jumlah, created_at) VALUES (?, ?, ?, ?, ?, NOW())")->execute([$attendance_id, $kasir_id, $branch_id, $keterangan, $jumlah]);
        echo json_encode(['success' => true, 'message' => 'Pengeluaran berhasil ditambahkan!']);
    } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]); }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'hapus_pengeluaran') {
    header('Content-Type: application/json');
    $expense_id = intval($_POST['expense_id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM shift_expenses WHERE id = ? AND attendance_id = ?")->execute([$expense_id, $attendance_id]);
        echo json_encode(['success' => true, 'message' => 'Pengeluaran dihapus!']);
    } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]); }
    exit;
}

// =====================================================
// HANDLER: KONFIRMASI TUTUP SHIFT
// =====================================================
if (isset($_POST['konfirmasi_tutup'])) {
    if ($pendingPay['cnt'] > 0) {
        $error = "Masih ada {$pendingPay['cnt']} transaksi menunggu pembayaran! Selesaikan pembayaran terlebih dahulu.";
    } else {
        $catatan = htmlspecialchars($_POST['catatan'] ?? '');
        $waktu_tutup = date('Y-m-d H:i:s');
        
        $stmtTotalExp = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) as total FROM shift_expenses WHERE attendance_id = ?");
        $stmtTotalExp->execute([$attendance_id]);
        $totalPengeluaran = $stmtTotalExp->fetchColumn();
        
        $omsetFinal = $dataShift['omset_gross'] - $totalPengeluaran; // Ini sudah bersih dari Driver/Hotel
        
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE kasir_attendance SET waktu_keluar=?, status='selesai', omset_shift=?, total_transaksi_shift=?, total_pengeluaran=? WHERE id=?")->execute([$waktu_tutup, $omsetFinal, $dataShift['total_transaksi'], $totalPengeluaran, $attendance_id]);
            $pdo->prepare("UPDATE shift_logs SET waktu_tutup=?, omset_shift=?, total_transaksi=?, catatan_tutup=?, total_pengeluaran=? WHERE attendance_id=?")->execute([$waktu_tutup, $omsetFinal, $dataShift['total_transaksi'], $catatan, $totalPengeluaran, $attendance_id]);
            $pdo->commit();
            
            unset($_SESSION['active_branch'], $_SESSION['session_id'], $_SESSION['attendance_id'], $_SESSION['waktu_buka']);
            $_SESSION['pesan_tutup'] = "Shift Ditutup! Omset Layanan: Rp " . number_format($dataShift['omset_gross'], 0, ',', '.') . " | Pengeluaran: Rp " . number_format($totalPengeluaran, 0, ',', '.') . " | Omset Bersih Kantor: Rp " . number_format($omsetFinal, 0, ',', '.');
            header("Location: pilih_cabang.php"); exit;
        } catch (Exception $e) {
            $pdo->rollBack(); $error = "Gagal: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Tutup Shift - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .summary-box { background: var(--bg-panel); border: 1px solid var(--border-color); padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: var(--shadow-sm); border-top: 4px solid var(--accent-blue); }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 20px; }
        .s-card { background: var(--bg-input); padding: 15px; border-radius: 10px; text-align: center; border: 1px solid var(--border-color); }
        .s-card h4 { margin: 0 0 5px 0; font-size: 12px; color: var(--text-muted); text-transform: uppercase; }
        .s-card .val { font-size: 20px; font-weight: bold; color: var(--text-dark); }
        
        .expenses-section { background: var(--bg-panel); padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-top: 4px solid var(--accent-red); }
        .expense-form { display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; margin-bottom: 20px; padding: 15px; background: var(--bg-input); border-radius: 8px; border: 1px solid var(--border-color); }
        .expense-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; background: var(--bg-panel); border-left: 4px solid var(--accent-yellow); border-radius: 5px; margin-bottom: 10px; border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }
        .expense-item .desc { flex: 1; font-weight: 500; color: var(--text-dark); }
        .expense-item .amount { font-weight: bold; color: var(--accent-red); margin: 0 15px; }
        .expense-total { background: rgba(231,76,60,0.1); color: var(--accent-red); padding: 15px; border-radius: 8px; text-align: center; margin-top: 15px; border: 1px solid rgba(231,76,60,0.3); }
        .expense-total h4 { margin: 0 0 8px 0; font-size: 14px; opacity: 0.9; }
        .expense-total .val { font-size: 24px; font-weight: bold; }

        .payment-breakdown { background: var(--bg-panel); padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
        .pay-methods-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .pay-method-card { background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 10px; padding: 15px; text-align: center; }
        .pm-name { color: var(--text-muted); font-size: 11px; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
        .pm-count { color: var(--text-muted); font-size: 11px; margin-bottom: 8px; }
        .pm-amount { font-size: 16px; font-weight: bold; color: var(--text-dark); }

        .cash-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; padding-top: 20px; border-top: 1px dashed var(--border-color); }
        .cash-box { padding: 20px; border-radius: 10px; text-align: center; border: 1px solid var(--border-color); }
        .cash-box h4 { margin: 0 0 10px 0; font-size: 13px; color: var(--text-muted); text-transform: uppercase; }
        .cash-box .amount { font-size: 24px; font-weight: bold; margin: 10px 0; color: var(--text-dark); }
        
        .badge-metode { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; border: 1px solid transparent; }
        .badge-tunai { background: rgba(39,174,96,0.1); color: var(--accent-green); border-color: rgba(39,174,96,0.3); }
        .badge-transfer { background: rgba(52,152,219,0.1); color: var(--accent-blue); border-color: rgba(52,152,219,0.3); }
        .badge-qris { background: rgba(155,89,182,0.1); color: #8e44ad; border-color: rgba(155,89,182,0.3); }
        .badge-debit { background: rgba(230,126,34,0.1); color: #e67e22; border-color: rgba(230,126,34,0.3); }
        .badge-belum { background: rgba(231,76,60,0.1); color: var(--accent-red); border-color: rgba(231,76,60,0.3); }
        .badge-bayar-nanti { background: rgba(241,196,15,0.1); color: var(--accent-yellow2); border-color: rgba(241,196,15,0.3); }
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
                <a href="stok_barang.php" class="menu-item"><span class="menu-abbr">SB</span><span class="menu-text">Stok Barang</span></a>
                <a href="tutup_cabang.php" class="menu-item active" style="margin-top:30px; color:var(--accent-red);"><span class="menu-abbr" style="background:rgba(231,76,60,0.1); color:var(--accent-red);">TS</span><span class="menu-text">Tutup Shift</span></a>
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
                    <h1>Tutup Shift</h1>
                </div>
                <div class="topbar-right">
                    <span style="color:var(--text-muted); font-size:13px; font-weight:bold;">Buka: <?= date('d/m/Y H:i', strtotime($waktu_buka)) ?></span>
                    <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
                </div>
            </div>

            <?php if (isset($error)): ?>
            <div style="background: rgba(231,76,60,0.1); color: var(--accent-red); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(231,76,60,0.3); font-weight:bold;">
                Peringatan: <?= $error ?>
            </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <div class="summary-box">
                        <h3 style="margin: 0 0 10px 0; font-size: 16px; color:var(--text-dark);">Ringkasan Shift</h3>
                        <div class="summary-grid">
                            <div class="s-card">
                                <h4>Total Transaksi</h4>
                                <div class="val"><?= $dataShift['total_transaksi'] ?></div>
                            </div>
                            <div class="s-card">
                                <h4>Omset Layanan (Kotor)</h4>
                                <div class="val">Rp <?= number_format($dataShift['omset_gross'], 0, ',', '.') ?></div>
                            </div>
                            <div class="s-card" style="border-color:var(--text-dark);">
                                <h4>Omset Bersih Kantor</h4>
                                <div class="val" style="color:var(--text-dark);">Rp <?= number_format($dataShift['omset_gross'] - $totalPengeluaran, 0, ',', '.') ?></div>
                            </div>
                            <div class="s-card">
                                <h4>Jatah Kantor</h4>
                                <div class="val" style="color:var(--accent-green);">Rp <?= number_format($dataShift['omset_netto'], 0, ',', '.') ?></div>
                            </div>
                            <div class="s-card">
                                <h4>Komisi Terapis</h4>
                                <div class="val" style="color:var(--accent-blue);">Rp <?= number_format($dataShift['omset_terapis'], 0, ',', '.') ?></div>
                            </div>
                            <div class="s-card">
                                <h4>Pengeluaran Kasir</h4>
                                <div class="val" style="color:var(--accent-red);">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
                            </div>
                        </div>

                        <div style="background:var(--bg-input); border:1px dashed var(--border-color); padding:10px; border-radius:8px; margin-top:15px; text-align:center;">
                            <span style="font-size:12px; color:var(--text-muted); font-weight:bold; text-transform:uppercase;">Titipan Pihak Luar (Non-Omset):</span>
                            <strong style="color:#8e44ad; font-size:14px; margin-left:10px;">Rp <?= number_format($dataShift['total_biaya_driver'] + $dataShift['total_admin_hotel'], 0, ',', '.') ?></strong>
                            <br><small style="color:var(--text-muted);">(Biaya Driver: Rp <?= number_format($dataShift['total_biaya_driver'], 0, ',', '.') ?> | Admin Hotel: Rp <?= number_format($dataShift['total_admin_hotel'], 0, ',', '.') ?>)</small>
                        </div>
                    </div>

                    <div class="expenses-section">
                        <h3 style="margin: 0 0 20px 0; color: var(--accent-red); font-size: 16px;">Pengeluaran Shift</h3>
                        <div class="expense-form">
                            <input type="text" id="expense_desc" placeholder="Keterangan pengeluaran" class="form-control">
                            <input type="number" id="expense_amount" placeholder="Jumlah (Rp)" class="form-control" min="0" step="1000">
                            <button type="button" onclick="tambahPengeluaran()" class="btn btn-primary">Tambah</button>
                        </div>

                        <div id="expense-list">
                            <?php if (count($pengeluaran) > 0): ?>
                                <?php foreach ($pengeluaran as $exp): ?>
                                <div class="expense-item" data-id="<?= $exp['id'] ?>">
                                    <div class="desc"><?= htmlspecialchars($exp['keterangan']) ?></div>
                                    <div class="amount">Rp <?= number_format($exp['jumlah'], 0, ',', '.') ?></div>
                                    <button class="btn btn-danger btn-sm" onclick="hapusPengeluaran(<?= $exp['id'] ?>)">Hapus</button>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: var(--text-muted); padding: 20px; font-style: italic;">Belum ada pengeluaran tercatat</p>
                            <?php endif; ?>
                        </div>

                        <div class="expense-total">
                            <h4>Total Pengeluaran</h4>
                            <div class="val" id="total-expense">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
                            <small>Hanya mengurangi omset kotor menjadi bersih, TIDAK mempengaruhi komisi terapis</small>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="payment-breakdown">
                        <h3 style="margin: 0 0 20px 0; color: var(--text-dark); font-size: 16px;">Rincian Metode Pembayaran (Uang Masuk)</h3>
                        <div class="pay-methods-grid">
                            <div class="pay-method-card">
                                <div class="pm-name">Tunai</div>
                                <div class="pm-count"><?= $countTunai ?> transaksi</div>
                                <div class="pm-amount" style="color:var(--accent-green);">Rp <?= number_format($totalTunai, 0, ',', '.') ?></div>
                            </div>
                            <div class="pay-method-card">
                                <div class="pm-name">Transfer Bank</div>
                                <div class="pm-count"><?= $countTransfer ?> transaksi</div>
                                <div class="pm-amount" style="color:var(--accent-blue);">Rp <?= number_format($totalTransfer, 0, ',', '.') ?></div>
                            </div>
                            <div class="pay-method-card">
                                <div class="pm-name">QRIS</div>
                                <div class="pm-count"><?= $countQris ?> transaksi</div>
                                <div class="pm-amount" style="color:#8e44ad;">Rp <?= number_format($totalQris, 0, ',', '.') ?></div>
                            </div>
                            <div class="pay-method-card">
                                <div class="pm-name">Kartu Debit</div>
                                <div class="pm-count"><?= $countDebit ?> transaksi</div>
                                <div class="pm-amount" style="color:#e67e22;">Rp <?= number_format($totalDebit, 0, ',', '.') ?></div>
                            </div>
                        </div>

                        <div class="cash-summary">
                            <div class="cash-box" style="background:rgba(39,174,96,0.05); border-color:var(--accent-green);">
                                <h4>Total Tunai (Di Laci)</h4>
                                <div class="amount" style="color:var(--accent-green);">Rp <?= number_format($totalTunai, 0, ',', '.') ?></div>
                                <div class="count" style="color:var(--text-muted);"><?= $countTunai ?> transaksi cash</div>
                            </div>
                            <div class="cash-box" style="background:rgba(52,152,219,0.05); border-color:var(--accent-blue);">
                                <h4>Total Non-Tunai (Rekening)</h4>
                                <div class="amount" style="color:var(--accent-blue);">Rp <?= number_format($totalNonTunai, 0, ',', '.') ?></div>
                                <div class="count" style="color:var(--text-muted);"><?= $countNonTunai ?> transaksi elektronik</div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <form method="POST" id="formTutup">
                            <div class="form-group">
                                <label>Catatan Penutup (Opsional)</label>
                                <textarea name="catatan" class="form-control" rows="2" placeholder="Tinggalkan catatan penutupan shift..."></textarea>
                            </div>
                            <?php if($pendingPay['cnt'] > 0): ?>
                            <button type="button" class="btn btn-danger" disabled style="width: 100%; padding: 15px; font-weight: bold; font-size: 16px; opacity: 0.5; cursor: not-allowed;">
                                SELESAIKAN PEMBAYARAN DULU (<?= $pendingPay['cnt'] ?> pending)
                            </button>
                            <?php else: ?>
                            <button type="button" onclick="konfirmasiTutup()" class="btn btn-danger" style="width: 100%; padding: 15px; font-weight: bold; font-size: 16px;">
                                KONFIRMASI TUTUP SHIFT
                            </button>
                            <input type="hidden" name="konfirmasi_tutup" value="1">
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top:20px;">
                <div class="card-header">Detail Riwayat Transaksi Shift (<?= count($transaksi) ?> Transaksi)</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Jam</th>
                                <th>Shift</th>
                                <th>Tipe</th>
                                <th>Pelanggan</th>
                                <th>Paket & Rincian</th>
                                <th>Terapis</th>
                                <th>Metode Bayar</th>
                                <th>Total Bayar</th>
                                <th>Jatah Kantor</th>
                                <th>Komisi Terapis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($transaksi as $trx): 
                                $metode = $trx['metode_pembayaran'] ?? '';
                                $payStatus = $trx['payment_status'] ?? 'unpaid';
                                $trxStatus = $trx['status'] ?? '';
                                
                                if ($trxStatus === 'menunggu_pembayaran') { $badge_class = 'badge-belum'; $badge_text = 'BELUM BAYAR'; } 
                                elseif ($metode === 'tunai') { $badge_class = 'badge-tunai'; $badge_text = 'TUNAI'; } 
                                elseif ($metode === 'transfer') { $badge_class = 'badge-transfer'; $badge_text = 'TRANSFER'; } 
                                elseif ($metode === 'qris') { $badge_class = 'badge-qris'; $badge_text = 'QRIS'; } 
                                elseif ($metode === 'debit') { $badge_class = 'badge-debit'; $badge_text = 'DEBIT'; } 
                                elseif ($metode === 'bayar_nanti' && $payStatus === 'unpaid') { $badge_class = 'badge-belum'; $badge_text = 'BELUM BAYAR'; } 
                                elseif ($metode === 'bayar_nanti') { $badge_class = 'badge-bayar-nanti'; $badge_text = 'BAYAR NANTI'; } 
                                else { $badge_class = 'badge-belum'; $badge_text = 'N/A'; }
                            ?>
                            <tr>
                                <td><?= date('H:i', strtotime($trx['created_at'])) ?></td>
                                <td><span style="background:var(--bg-input); border:1px solid var(--border-color); padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold; color:var(--text-dark);"><?= strtoupper($trx['jenis_shift']) ?></span></td>
                                <td>
                                    <?php if(($trx['tipe_transaksi'] ?? 'datang') == 'panggilan'): ?>
                                        <span style="background:rgba(41, 128, 185, 0.1); color:var(--accent-blue); padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">PANGGILAN</span>
                                    <?php else: ?>
                                        <span style="background:rgba(39, 174, 96, 0.1); color:var(--accent-green); padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">DATANG</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color:var(--text-dark);"><?= htmlspecialchars($trx['nama_pelanggan']) ?></strong></td>
                                <td>
                                    <strong style="font-size:13px;"><?= htmlspecialchars($trx['nama_paket']) ?></strong>
                                    <?php if (!empty($addedPackages[$trx['id']])): ?>
                                        <?php foreach ($addedPackages[$trx['id']] as $ap): ?>
                                        <br><span style="color:var(--text-muted); font-size:11px;">+ <?= htmlspecialchars($ap['nama_paket']) ?> (Rp <?= number_format($ap['harga'], 0, ',', '.') ?>)</span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($trx['biaya_driver']) && $trx['biaya_driver'] > 0): ?>
                                        <br><span style="color:var(--accent-blue); font-size:11px;">+ Biaya Driver (Rp <?= number_format($trx['biaya_driver'], 0, ',', '.') ?>)</span>
                                    <?php endif; ?>
                                    <?php if (!empty($trx['harga_admin_hotel']) && $trx['harga_admin_hotel'] > 0): ?>
                                        <br><span style="color:#8e44ad; font-size:11px;">+ Admin Hotel (Rp <?= number_format($trx['harga_admin_hotel'], 0, ',', '.') ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($trx['nama_terapis']) ?></td>
                                <td><span class="badge-metode <?= $badge_class ?>"><?= $badge_text ?></span></td>
                                <td><strong style="color:var(--text-dark); font-size:14px;">Rp <?= number_format($trx['total_bayar'], 0, ',', '.') ?></strong></td>
                                <td style="color: var(--accent-green); font-weight:bold;">Rp <?= number_format($trx['omset_cabang'], 0, ',', '.') ?></td>
                                <td style="color: var(--accent-blue); font-weight:bold;">Rp <?= number_format($trx['omset_terapis'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="background:var(--bg-input); font-weight:bold; color:var(--text-dark);">
                            <tr>
                                <td colspan="7" style="text-align:right; padding:15px;">TOTAL KESELURUHAN:</td>
                                <td style="color:var(--text-dark); font-size:15px;">Rp <?= number_format($dataShift['total_uang_masuk'], 0, ',', '.') ?></td>
                                <td style="color:var(--accent-green); font-size:15px;">Rp <?= number_format($dataShift['omset_netto'], 0, ',', '.') ?></td>
                                <td style="color:var(--accent-blue); font-size:15px;">Rp <?= number_format($dataShift['omset_terapis'], 0, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
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
        sb.classList.toggle('collapsed');
        const btnText = document.querySelector('.sidebar-toggle-btn .menu-text');
        const btnAbbr = document.querySelector('.sidebar-toggle-btn .menu-abbr');
        if (sb.classList.contains('collapsed')) { btnText.style.display = 'none'; btnAbbr.style.display = 'inline'; } 
        else { btnText.style.display = 'inline'; btnAbbr.style.display = 'none'; }
    }

    function tambahPengeluaran() {
        const keterangan = document.getElementById('expense_desc').value.trim();
        const jumlah = parseFloat(document.getElementById('expense_amount').value) || 0;
        
        if (!keterangan) { Swal.fire('Error', 'Keterangan harus diisi!', 'error'); return; }
        if (jumlah <= 0) { Swal.fire('Error', 'Jumlah harus lebih dari 0!', 'error'); return; }
        
        fetch(window.location.href, {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=tambah_pengeluaran&keterangan=${encodeURIComponent(keterangan)}&jumlah=${jumlah}`
        }).then(response => response.json()).then(data => {
            if (data.success) Swal.fire('Berhasil!', data.message, 'success').then(() => location.reload());
            else Swal.fire('Error', data.message, 'error');
        }).catch(error => Swal.fire('Error', 'Terjadi kesalahan: ' + error, 'error'));
    }

    function hapusPengeluaran(id) {
        Swal.fire({
            title: 'Hapus Pengeluaran?', text: 'Data akan dihapus permanen!', icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#e74c3c', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(window.location.href, {
                    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=hapus_pengeluaran&expense_id=${id}`
                }).then(response => response.json()).then(data => {
                    if (data.success) Swal.fire('Terhapus!', data.message, 'success').then(() => location.reload());
                    else Swal.fire('Error', data.message, 'error');
                }).catch(error => Swal.fire('Error', 'Terjadi kesalahan: ' + error, 'error'));
            }
        });
    }

    function konfirmasiTutup() {
        const omsetKotorLayanan = <?= $dataShift['omset_gross'] ?>;
        const pengeluaran = <?= $totalPengeluaran ?>;
        const omsetBersih = omsetKotorLayanan - pengeluaran;
        const totalTitipan = <?= $dataShift['total_biaya_driver'] + $dataShift['total_admin_hotel'] ?>;
        
        Swal.fire({
            title: 'Tutup Shift?',
            html: `
                <div style="text-align:left; font-size:14px; color:var(--text-dark);">
                    <div style="display:flex; justify-content:space-between; margin:8px 0;">
                        <span>Omset Kotor (Hanya Layanan):</span> <strong>Rp ${omsetKotorLayanan.toLocaleString('id-ID')}</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin:8px 0; color:var(--accent-red);">
                        <span>(-) Pengeluaran Kasir:</span> <strong>Rp ${pengeluaran.toLocaleString('id-ID')}</strong>
                    </div>
                    <hr style="border:1px dashed var(--border-color);">
                    <div style="display:flex; justify-content:space-between; margin:8px 0; color:var(--accent-green); font-size:16px;">
                        <span><strong>Omset Bersih Kantor:</strong></span> <strong>Rp ${omsetBersih.toLocaleString('id-ID')}</strong>
                    </div>
                    
                    <div style="background:rgba(142, 68, 173, 0.05); padding:10px; border-radius:5px; margin: 15px 0 10px 0; font-size:13px; color:#8e44ad; border: 1px dashed rgba(142, 68, 173, 0.4);">
                        <div style="display:flex; justify-content:space-between; margin-bottom:0;">
                            <span>Titipan Pihak Luar (Driver/Hotel):</span> <strong>Rp ${totalTitipan.toLocaleString('id-ID')}</strong>
                        </div>
                    </div>
                    
                    <hr style="border:1px dashed var(--border-color);">
                    <div style="display:flex; justify-content:space-between; margin:8px 0; color:var(--accent-green);">
                        <span>Uang Tunai:</span> <strong>Rp <?= number_format($totalTunai, 0, ',', '.') ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin:8px 0; color:var(--accent-blue);">
                        <span>Non-Tunai:</span> <strong>Rp <?= number_format($totalNonTunai, 0, ',', '.') ?></strong>
                    </div>
                </div>
                <br><small style="color:var(--accent-red);">Data tidak bisa diubah setelah ditutup!</small>
            `,
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#e74c3c', confirmButtonText: 'Ya, Tutup Shift!', cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) document.getElementById('formTutup').submit();
        });
    }
    </script>
</body>
</html>