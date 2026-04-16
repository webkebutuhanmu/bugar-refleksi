<?php
// File: kasir/proses_pembayaran.php
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

// Foto profil
$stmtProfil = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
$stmtProfil->execute([$kasir_id]);
$foto_profil = $stmtProfil->fetchColumn();
$foto_profil = (!empty($foto_profil) && file_exists("../uploads/profil/" . $foto_profil)) ? "../uploads/profil/" . $foto_profil : "../assets/default_user.png";

$transaction_id = intval($_GET['transaction_id'] ?? $_POST['transaction_id'] ?? 0);
$swal_script = "";

if ($transaction_id <= 0) {
    header("Location: dashboard_kasir.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT t.*, p.nama_paket, p.durasi_menit as pkg_durasi, b.nomor_bed, b.tipe as bed_tipe,
           u.nama_lengkap as nama_terapis
    FROM transactions t 
    JOIN packages p ON t.package_id = p.id 
    LEFT JOIN beds b ON t.bed_id = b.id
    JOIN users u ON t.terapis_id = u.id
    WHERE t.id = ? AND t.branch_id = ?
");
$stmt->execute([$transaction_id, $branch_id]);
$trx = $stmt->fetch();

if (!$trx) {
    header("Location: dashboard_kasir.php");
    exit;
}

$already_paid = ($trx['payment_status'] === 'paid');
$is_confirm_payment = ($trx['metode_pembayaran'] === 'bayar_nanti' && $trx['status'] === 'menunggu_pembayaran');
$is_pay_before_start = ($trx['payment_status'] === 'unpaid' && $trx['status'] === 'proses' && $trx['metode_pembayaran'] !== 'bayar_nanti');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_bayar'])) {
    try {
        $metode = $_POST['metode_pembayaran'] ?? '';
        
        if (empty($metode)) throw new Exception("Pilih metode pembayaran!");
        
        $valid_methods = ['tunai', 'transfer', 'qris', 'debit'];
        if (!in_array($metode, $valid_methods)) throw new Exception("Metode pembayaran tidak valid!");
        
        $pdo->beginTransaction();
        
        if ($is_confirm_payment) {
            $pdo->prepare("UPDATE transactions SET 
                status = 'selesai', 
                payment_status = 'paid', 
                metode_pembayaran = ?, 
                waktu_bayar = NOW(),
                waktu_selesai = NOW()
                WHERE id = ? AND status = 'menunggu_pembayaran'
            ")->execute([$metode, $transaction_id]);
            
            if ($trx['bed_id']) {
                $pdo->prepare("UPDATE beds SET status = 'kosong' WHERE id = ?")->execute([$trx['bed_id']]);
            }
            
            $pdo->prepare("UPDATE terapis_loans SET status = 'finished' WHERE transaction_id = ? AND status = 'active'")
                ->execute([$transaction_id]);
            
            $pdo->commit();
            
            $pesanBed = !empty($trx['bed_id']) ? " Bed " . ($trx['nomor_bed'] ?? '') . " dibebaskan." : "";
            $pesan = addslashes("Pembayaran berhasil! Transaksi selesai." . $pesanBed);
            $swal_script = "
                Swal.fire({
                    title: 'Berhasil!',
                    html: '$pesan<br><small style=\"color:var(--text-muted);\">Apakah ingin mencetak struk?</small>',
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonColor: '#27ae60',
                    confirmButtonText: 'Cetak Struk',
                    cancelButtonText: 'Lewati'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.open('cetak_struk.php?transaction_id={$transaction_id}', '_blank');
                        setTimeout(() => { window.location.href = 'dashboard_kasir.php'; }, 500);
                    } else {
                        window.location.href = 'dashboard_kasir.php';
                    }
                });
            ";
            
        } else {
            $waktu_mulai = date('Y-m-d H:i:s');
            $durasi = $trx['durasi_menit'];
            $waktu_selesai = date('Y-m-d H:i:s', strtotime("+$durasi minutes"));
            
            $pdo->prepare("UPDATE transactions SET 
                status = 'proses', 
                payment_status = 'paid', 
                metode_pembayaran = ?, 
                waktu_bayar = NOW(),
                waktu_mulai = ?,
                waktu_selesai = ?
                WHERE id = ?
            ")->execute([$metode, $waktu_mulai, $waktu_selesai, $transaction_id]);
            
            $pdo->commit();
            
            $pesan = addslashes("Pembayaran berhasil! Pijatan dimulai.");
            $swal_script = "
                Swal.fire({
                    title: 'Pembayaran Diterima!',
                    html: '$pesan<br><small style=\"color:var(--text-muted);\">Apakah ingin mencetak struk?</small>',
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonColor: '#27ae60',
                    confirmButtonText: 'Cetak Struk',
                    cancelButtonText: 'Lewati'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.open('cetak_struk.php?transaction_id={$transaction_id}', '_blank');
                        setTimeout(() => { window.location.href = 'dashboard_kasir.php'; }, 500);
                    } else {
                        window.location.href = 'dashboard_kasir.php';
                    }
                });
            ";
        }
        
        $stmt->execute([$transaction_id, $branch_id]);
        $trx = $stmt->fetch();
        $already_paid = ($trx['payment_status'] === 'paid');
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = addslashes($e->getMessage());
        $swal_script = "Swal.fire('Gagal!', '$err', 'error');";
    }
}

$total_bayar = $trx['total_bayar'];
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Pembayaran</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .payment-container { max-width: 600px; margin: 0 auto; }
        .trx-summary { background: var(--text-dark); color: var(--bg-panel); border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: var(--shadow-md); }
        .trx-summary h2 { margin: 0 0 15px 0; font-size: 18px; color: var(--bg-panel); }
        .trx-summary .total-amount { font-size: 36px; font-weight: bold; text-align: center; margin: 15px 0; color: var(--accent-yellow); font-family: 'Plus Jakarta Sans', sans-serif; }
        .trx-detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 14px; }
        .trx-detail-row:last-child { border-bottom: none; }
        .trx-detail-row .label { opacity: 0.8; }
        .trx-detail-row .value { font-weight: 600; }

        .payment-methods { background: var(--bg-panel); border-radius: 12px; padding: 25px; box-shadow: var(--shadow-sm); margin-bottom: 20px; border: 1px solid var(--border-color); }
        .payment-methods h3 { margin: 0 0 20px 0; color: var(--text-dark); font-size: 16px; }
        .method-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .method-card { padding: 20px 15px; border: 2px solid var(--border-color); border-radius: 12px; text-align: center; cursor: pointer; transition: all 0.3s; background: var(--bg-input); position: relative; }
        .method-card:hover { border-color: var(--accent-blue); }
        .method-card.selected { border-color: var(--accent-green); background: rgba(39,174,96,0.05); }
        .method-card .method-icon { font-size: 14px; font-weight:bold; color:var(--text-muted); margin-bottom: 8px; text-transform: uppercase; }
        .method-card .method-name { font-weight: bold; color: var(--text-dark); font-size: 15px; }
        .method-card.selected .method-icon { color: var(--accent-green); }

        .cash-input-section { display: none; background: var(--bg-input); border-radius: 12px; padding: 20px; margin-top: 15px; border: 1px solid var(--border-color); }
        .cash-input-section.show { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .cash-input-section label { font-weight: bold; color: var(--text-dark); margin-bottom: 8px; display: block; font-size: 13px; }
        .cash-input-section input { width: 100%; padding: 15px; border: 2px solid var(--border-color); border-radius: 10px; font-size: 20px; font-weight: bold; text-align: right; box-sizing: border-box; background: var(--bg-panel); color: var(--text-dark); }
        .cash-input-section input:focus { border-color: var(--accent-green); outline: none; }
        
        .change-display { margin-top: 10px; padding: 12px; border-radius: 8px; text-align: center; font-weight: bold; font-size: 16px; border: 1px solid transparent; }
        .change-display.positive { background: rgba(39,174,96,0.1); color: var(--accent-green); border-color: var(--accent-green); }
        .change-display.negative { background: rgba(231,76,60,0.1); color: var(--accent-red); border-color: var(--accent-red); }
        .change-display.zero { background: var(--bg-panel); color: var(--text-muted); border-color: var(--border-color); }

        .quick-cash { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .quick-cash button { padding: 8px 14px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-panel); cursor: pointer; font-weight: bold; font-size: 13px; transition: 0.2s; color: var(--text-dark); }
        .quick-cash button:hover { border-color: var(--accent-green); color: var(--accent-green); }

        .btn-confirm-payment { width: 100%; padding: 18px; border: none; border-radius: 12px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s; color: white; background: var(--accent-green); }
        .btn-confirm-payment:hover:not(:disabled) { filter: brightness(1.1); }
        .btn-confirm-payment:disabled { background: var(--border-color); color: var(--text-muted); cursor: not-allowed; }

        .btn-back { width: 100%; padding: 14px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 14px; font-weight: bold; cursor: pointer; background: var(--bg-panel); color: var(--text-dark); margin-top: 10px; transition: 0.2s; }
        .btn-back:hover { background: var(--bg-input); }
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
                    <h1><?= $is_confirm_payment ? 'Konfirmasi Pembayaran' : 'Proses Pembayaran' ?></h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
                </div>
            </div>

            <div class="payment-container">
                <div class="trx-summary">
                    <h2>Detail Transaksi #<?= $transaction_id ?></h2>
                    <div class="trx-detail-row">
                        <span class="label">Pelanggan</span>
                        <span class="value"><?= htmlspecialchars($trx['nama_pelanggan']) ?></span>
                    </div>
                    <div class="trx-detail-row">
                        <span class="label">No. HP</span>
                        <span class="value"><?= htmlspecialchars($trx['no_hp_pelanggan'] ?: '-') ?></span>
                    </div>
                    <div class="trx-detail-row">
                        <span class="label">Paket</span>
                        <span class="value"><?= htmlspecialchars($trx['nama_paket']) ?> (<?= $trx['pkg_durasi'] ?> mnt)</span>
                    </div>
                    <div class="trx-detail-row">
                        <span class="label">Terapis</span>
                        <span class="value"><?= htmlspecialchars($trx['nama_terapis']) ?></span>
                    </div>
                    <?php if (!empty($trx['tipe_transaksi']) && $trx['tipe_transaksi'] === 'panggilan'): ?>
                    <div class="trx-detail-row">
                        <span class="label">Tipe</span>
                        <span class="value">Panggilan</span>
                    </div>
                    <?php if (!empty($trx['alamat_panggilan'])): ?>
                    <div class="trx-detail-row">
                        <span class="label">Alamat</span>
                        <span class="value"><?= htmlspecialchars($trx['alamat_panggilan']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($trx['biaya_driver']) && $trx['biaya_driver'] > 0): ?>
                    <div class="trx-detail-row">
                        <span class="label">Biaya Driver</span>
                        <span class="value">Rp <?= number_format($trx['biaya_driver'], 0, ',', '.') ?></span>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="trx-detail-row">
                        <span class="label">Bed</span>
                        <span class="value"><?= htmlspecialchars($trx['nomor_bed'] ?? '-') ?> (<?= htmlspecialchars($trx['bed_tipe'] ?? '-') ?>)</span>
                    </div>
                    <?php endif; ?>
                    <div class="trx-detail-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <?php if($already_paid): ?>
                                <span class="badge badge-success">LUNAS</span>
                            <?php elseif($is_confirm_payment): ?>
                                <span class="badge badge-warning">MENUNGGU BAYAR</span>
                            <?php else: ?>
                                <span class="badge badge-danger">BELUM BAYAR</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="total-amount">Rp <?= number_format($total_bayar, 0, ',', '.') ?></div>
                </div>

                <?php if(!$already_paid): ?>
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="action_bayar" value="1">
                    <input type="hidden" name="transaction_id" value="<?= $transaction_id ?>">
                    <input type="hidden" name="metode_pembayaran" id="selectedMethod" value="">
                    
                    <div class="payment-methods">
                        <h3>Pilih Metode Pembayaran</h3>
                        <div class="method-grid">
                            <div class="method-card" onclick="selectMethod('tunai')" data-method="tunai">
                                <div class="method-icon">Cash</div>
                                <div class="method-name">Tunai</div>
                            </div>
                            <div class="method-card" onclick="selectMethod('transfer')" data-method="transfer">
                                <div class="method-icon">Bank</div>
                                <div class="method-name">Transfer</div>
                            </div>
                            <div class="method-card" onclick="selectMethod('qris')" data-method="qris">
                                <div class="method-icon">E-Wallet</div>
                                <div class="method-name">QRIS</div>
                            </div>
                            <div class="method-card" onclick="selectMethod('debit')" data-method="debit">
                                <div class="method-icon">Card</div>
                                <div class="method-name">Debit</div>
                            </div>
                        </div>

                        <div class="cash-input-section" id="cashSection">
                            <label>Jumlah Uang Diterima</label>
                            <input type="text" id="cashInput" placeholder="0" oninput="calculateChange()">
                            
                            <div class="quick-cash">
                                <?php
                                $amounts = [50000, 100000, 150000, 200000, 250000, 300000, 500000];
                                foreach($amounts as $amt):
                                    if ($amt >= $total_bayar):
                                ?>
                                <button type="button" onclick="setCashAmount(<?= $amt ?>)">Rp <?= number_format($amt/1000,0) ?>k</button>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                                <button type="button" onclick="setCashAmount(<?= $total_bayar ?>)" style="border-color:var(--accent-green); color:var(--accent-green);">Uang Pas</button>
                            </div>
                            
                            <div class="change-display zero" id="changeDisplay">Masukkan jumlah uang</div>
                        </div>
                    </div>

                    <button type="button" id="btnConfirmPay" onclick="confirmPayment()" class="btn-confirm-payment" disabled>
                        KONFIRMASI PEMBAYARAN
                    </button>
                </form>

                <button class="btn-back" onclick="window.location.href='dashboard_kasir.php'">Kembali ke Dashboard</button>

                <?php else: ?>
                <div class="payment-methods" style="text-align: center;">
                    <h2 style="color: var(--accent-green); margin-bottom: 10px;">Transaksi Sudah Lunas</h2>
                    <p style="color: var(--text-muted);">Metode: <?= strtoupper($trx['metode_pembayaran']) ?></p>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">Waktu Bayar: <?= $trx['waktu_bayar'] ? date('d M Y H:i', strtotime($trx['waktu_bayar'])) : '-' ?></p>
                    <div style="display:flex;gap:10px;justify-content:center;">
                        <button onclick="window.open('cetak_struk.php?transaction_id=<?= $transaction_id ?>', '_blank')" class="btn btn-success">Cetak Struk</button>
                        <button class="btn btn-secondary" onclick="window.location.href='dashboard_kasir.php'">Kembali</button>
                    </div>
                </div>
                <?php endif; ?>
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

    const totalBayar = <?= $total_bayar ?>;
    let selectedMethodVal = '';

    function selectMethod(method) {
        selectedMethodVal = method;
        document.getElementById('selectedMethod').value = method;
        document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
        document.querySelector(`.method-card[data-method="${method}"]`).classList.add('selected');
        
        const cashSection = document.getElementById('cashSection');
        if (method === 'tunai') {
            cashSection.classList.add('show');
            document.getElementById('cashInput').focus();
        } else {
            cashSection.classList.remove('show');
        }
        document.getElementById('btnConfirmPay').disabled = false;
    }

    function setCashAmount(amount) {
        document.getElementById('cashInput').value = amount.toLocaleString('id-ID');
        calculateChange();
    }

    function calculateChange() {
        const raw = document.getElementById('cashInput').value.replace(/[^0-9]/g, '');
        const cashAmount = parseInt(raw) || 0;
        const change = cashAmount - totalBayar;
        const display = document.getElementById('changeDisplay');
        
        if (cashAmount === 0) {
            display.className = 'change-display zero';
            display.innerHTML = 'Masukkan jumlah uang';
        } else if (change > 0) {
            display.className = 'change-display positive';
            display.innerHTML = 'Kembalian: Rp ' + change.toLocaleString('id-ID');
        } else if (change === 0) {
            display.className = 'change-display positive';
            display.innerHTML = 'Uang Pas!';
        } else {
            display.className = 'change-display negative';
            display.innerHTML = 'Kurang: Rp ' + Math.abs(change).toLocaleString('id-ID');
        }
    }

    function confirmPayment() {
        if (!selectedMethodVal) {
            return Swal.fire('Gagal', 'Pilih metode pembayaran!', 'warning');
        }
        
        if (selectedMethodVal === 'tunai') {
            const raw = document.getElementById('cashInput').value.replace(/[^0-9]/g, '');
            const cashAmount = parseInt(raw) || 0;
            if (cashAmount < totalBayar) {
                return Swal.fire('Uang Kurang!', 'Jumlah uang tidak cukup.', 'warning');
            }
        }
        
        const methodLabels = { 'tunai': 'Tunai (Cash)', 'transfer': 'Transfer Bank', 'qris': 'QRIS', 'debit': 'Kartu Debit' };

        let changeInfo = '';
        if (selectedMethodVal === 'tunai') {
            const raw = document.getElementById('cashInput').value.replace(/[^0-9]/g, '');
            const cashAmount = parseInt(raw) || 0;
            const change = cashAmount - totalBayar;
            if (change > 0) {
                changeInfo = '<div style="margin-top:15px; padding:10px; background:rgba(39,174,96,0.1); color:var(--accent-green); border-radius:8px;">Kembalian: <strong>Rp ' + change.toLocaleString('id-ID') + '</strong></div>';
            }
        }

        Swal.fire({
            title: 'Konfirmasi Pembayaran',
            html: `
                <div style="text-align:left; padding:10px; font-size:14px; color:var(--text-dark);">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span>Total Tagihan:</span>
                        <strong>Rp ${totalBayar.toLocaleString('id-ID')}</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Metode:</span>
                        <strong>${methodLabels[selectedMethodVal]}</strong>
                    </div>
                </div>
                ${changeInfo}
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#27ae60',
            confirmButtonText: 'Konfirmasi Bayar',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) document.getElementById('paymentForm').submit();
        });
    }
    </script>

    <?php if (!empty($_SESSION['stok_warnings'])): 
        $swList = $_SESSION['stok_warnings']; unset($_SESSION['stok_warnings']);
        $swHtml = '<div style="text-align:left;font-size:13px;color:var(--text-dark);">';
        foreach ($swList as $sw) { $swHtml .= addslashes($sw) . '<br>'; }
        $swHtml .= '</div>';
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            Swal.fire({ title: "Peringatan Stok!", html: '<?= $swHtml ?>', icon: "warning", confirmButtonText: "Mengerti" });
        }, 500);
    });
    </script>
    <?php endif; ?>

    <script><?= $swal_script ?></script>
</body>
</html>