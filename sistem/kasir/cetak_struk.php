<?php
// File: kasir/cetak_struk.php
// Halaman cetak struk transaksi
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir' || !isset($_SESSION['active_branch'])) {
    header("Location: pilih_cabang.php"); exit;
}

$branch_id = $_SESSION['active_branch'];
$transaction_id = intval($_GET['transaction_id'] ?? 0);

if ($transaction_id <= 0) {
    echo "<script>window.close();</script>"; exit;
}

$stmt = $pdo->prepare("
    SELECT t.*, 
           p.nama_paket, p.durasi_menit as pkg_durasi,
           b.nomor_bed, b.tipe as bed_tipe,
           u.nama_lengkap as nama_terapis,
           br.nama_cabang, br.alamat as alamat_cabang,
           COALESCE(t.tipe_lokasi, 'rumah') as tipe_lokasi,
           COALESCE(t.harga_admin_hotel, 0) as harga_admin_hotel
    FROM transactions t 
    JOIN packages p ON t.package_id = p.id 
    LEFT JOIN beds b ON t.bed_id = b.id
    JOIN users u ON t.terapis_id = u.id
    JOIN branches br ON t.branch_id = br.id
    WHERE t.id = ? AND t.branch_id = ?
");
$stmt->execute([$transaction_id, $branch_id]);
$trx = $stmt->fetch();

if (!$trx) {
    echo "<script>window.close();</script>"; exit;
}

// Ambil nama kasir
$stmtKasir = $pdo->prepare("SELECT u.nama_lengkap FROM kasir_attendance ka JOIN users u ON ka.kasir_id = u.id WHERE ka.branch_id = ? AND ka.status = 'aktif' ORDER BY ka.waktu_masuk DESC LIMIT 1");
$stmtKasir->execute([$branch_id]);
$nama_kasir = $stmtKasir->fetchColumn() ?: $_SESSION['nama'];

$tgl_bayar = $trx['waktu_bayar'] ? date('d/m/Y H:i', strtotime($trx['waktu_bayar'])) : date('d/m/Y H:i');
$tgl_mulai = $trx['waktu_mulai'] ? date('H:i', strtotime($trx['waktu_mulai'])) : '-';
$tgl_selesai = $trx['waktu_selesai'] ? date('H:i', strtotime($trx['waktu_selesai'])) : '-';
$no_struk = str_pad($transaction_id, 6, '0', STR_PAD_LEFT);

// Cek apakah bayar nanti (belum lunas)
$isBayarNanti = ($trx['metode_pembayaran'] === 'bayar_nanti' || $trx['payment_status'] === 'unpaid');
$isPanggilan  = ($trx['tipe_transaksi'] === 'panggilan');

// Ambil paket tambahan
$addedPakets = [];
try {
    $stmtAP3 = $pdo->prepare("SELECT * FROM transaction_added_packages WHERE transaction_id = ? ORDER BY created_at ASC");
    $stmtAP3->execute([$transaction_id]);
    $addedPakets = $stmtAP3->fetchAll();
} catch (Exception $e3) {}

$metode_label = [
    'tunai'    => 'Tunai (Cash)',
    'transfer' => 'Transfer Bank',
    'qris'     => 'QRIS',
    'debit'    => 'Kartu Debit',
    'bayar_nanti' => 'Bayar Nanti',
];
$metode = $metode_label[$trx['metode_pembayaran']] ?? strtoupper($trx['metode_pembayaran'] ?? 'Belum Ditentukan');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk #<?= $no_struk ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            min-height: 100vh;
        }
        .receipt {
            background: white;
            width: 300px;
            padding: 20px 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-radius: 4px;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #ccc;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }
        .receipt-header .brand {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .receipt-header .cabang {
            font-size: 12px;
            color: #555;
            margin-top: 4px;
        }
        .receipt-header .alamat {
            font-size: 11px;
            color: #777;
            margin-top: 2px;
        }
        .receipt-no {
            text-align: center;
            font-size: 11px;
            color: #999;
            margin-bottom: 12px;
        }
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 12px;
        }
        .receipt-row .label { color: #555; flex: 0 0 45%; }
        .receipt-row .value { text-align: right; flex: 0 0 55%; font-weight: 500; word-break: break-word; }
        .divider {
            border: none;
            border-top: 1px dashed #ccc;
            margin: 10px 0;
        }
        .divider-solid {
            border: none;
            border-top: 1px solid #ccc;
            margin: 10px 0;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 15px;
            font-weight: bold;
            padding: 8px 0;
        }
        .receipt-footer {
            text-align: center;
            border-top: 2px dashed #ccc;
            padding-top: 12px;
            margin-top: 12px;
            font-size: 11px;
            color: #777;
            line-height: 1.7;
        }
        .receipt-footer .thanks {
            font-size: 13px;
            font-weight: bold;
            color: #333;
            margin-bottom: 4px;
        }
        .badge-paid {
            display: inline-block;
            border: 2px solid #27ae60;
            color: #27ae60;
            padding: 2px 10px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 11px;
            letter-spacing: 2px;
            margin: 8px 0;
        }

        .print-btn-area {
            margin-top: 20px;
            text-align: center;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .print-btn {
            padding: 10px 28px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }
        .print-btn:hover { background: #219a52; }
        .close-btn {
            padding: 10px 28px;
            background: #95a5a6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-btn:hover { background: #7f8c8d; }

        @media print {
            body { background: white; padding: 0; }
            .receipt { box-shadow: none; width: 100%; }
            .print-btn-area { display: none; }
        }
    </style>
</head>
<body>
    <div>
        <div class="receipt" id="receipt">
            <div class="receipt-header">
                <div class="brand">Bugar Refleksi</div>
                <div class="cabang"><?= htmlspecialchars($trx['nama_cabang']) ?></div>
                <?php if (!empty($trx['alamat_cabang'])): ?>
                <div class="alamat"><?= htmlspecialchars($trx['alamat_cabang']) ?></div>
                <?php endif; ?>
            </div>

            <div class="receipt-no">
                STRUK #<?= $no_struk ?><br>
                <?= $tgl_bayar ?>
            </div>

            <div class="receipt-row">
                <span class="label">Customer</span>
                <span class="value"><?= htmlspecialchars($trx['nama_pelanggan']) ?></span>
            </div>
            <?php if (!empty($trx['no_hp_pelanggan'])): ?>
            <div class="receipt-row">
                <span class="label">No. HP</span>
                <span class="value"><?= htmlspecialchars($trx['no_hp_pelanggan']) ?></span>
            </div>
            <?php endif; ?>
            <hr class="divider">
            <?php if ($isPanggilan): ?>
            <div class="receipt-row">
                <span class="label">Tipe</span>
                <span class="value">
                    <?php if (!empty($trx['tipe_lokasi']) && $trx['tipe_lokasi'] === 'hotel'): ?>
                        &#127976; Hotel
                    <?php else: ?>
                        &#128222; Panggilan
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($trx['alamat_panggilan'])): ?>
            <div class="receipt-row">
                <span class="label"><?= ($trx['tipe_lokasi'] === 'hotel') ? 'Nama Hotel' : 'Alamat' ?></span>
                <span class="value"><?= htmlspecialchars($trx['alamat_panggilan']) ?></span>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <?php if (!empty($trx['nomor_bed'])): ?>
            <div class="receipt-row">
                <span class="label">Bed</span>
                <span class="value"><?= htmlspecialchars($trx['nomor_bed']) ?> (<?= htmlspecialchars($trx['bed_tipe'] ?? '') ?>)</span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <div class="receipt-row">
                <span class="label">Layanan</span>
                <span class="value"><?= htmlspecialchars($trx['nama_paket']) ?></span>
            </div>
            <?php if (!empty($addedPakets)): ?>
            <?php foreach($addedPakets as $ap3): ?>
            <div class="receipt-row" style="color:#e67e22;">
                <span class="label">+ <?= htmlspecialchars($ap3['nama_paket']) ?></span>
                <span class="value">Rp <?= number_format($ap3['harga'],0,',','.') ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            
            <div class="receipt-row">
                <span class="label">Durasi</span>
                <span class="value"><?= $trx['durasi_menit'] ?> Menit</span>
            </div>
            <div class="receipt-row">
                <span class="label">Terapis</span>
                <span class="value"><?= htmlspecialchars($trx['nama_terapis']) ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Mulai</span>
                <span class="value"><?= $tgl_mulai ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Selesai</span>
                <span class="value"><?= $tgl_selesai ?></span>
            </div>
            <hr class="divider-solid">

            <?php
                // FIX LOGIKA: Menarik harga paket dari database (murni omset),
                // lalu menjumlahkannya dengan titipan Hotel & Driver menjadi Total Tagihan Customer
                $harga_paket_sistem = floatval($trx['total_bayar']);
                $biaya_driver       = floatval($trx['biaya_driver'] ?? 0);
                $harga_admin_hotel  = floatval($trx['harga_admin_hotel'] ?? 0);
                
                $grand_total_struk  = $harga_paket_sistem + $biaya_driver + $harga_admin_hotel;

                $ada_driver  = $isPanggilan && $biaya_driver > 0;
                $ada_hotel   = $isPanggilan && $harga_admin_hotel > 0;
                $perlu_rinci = $ada_driver || $ada_hotel;
            ?>
            <?php if ($isPanggilan && $perlu_rinci): ?>
            <div class="receipt-row">
                <span class="label">Biaya Paket</span>
                <span class="value">Rp <?= number_format($harga_paket_sistem, 0, ',', '.') ?></span>
            </div>
            <?php if ($ada_hotel): ?>
            <div class="receipt-row">
                <span class="label">Admin Hotel</span>
                <span class="value">Rp <?= number_format($harga_admin_hotel, 0, ',', '.') ?></span>
            </div>
            <?php endif; ?>
            <?php if ($ada_driver): ?>
            <div class="receipt-row">
                <span class="label">Biaya Driver</span>
                <span class="value">Rp <?= number_format($biaya_driver, 0, ',', '.') ?></span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <div class="total-row">
                <span>TOTAL BAYAR</span>
                <span>Rp <?= number_format($grand_total_struk, 0, ',', '.') ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Metode</span>
                <span class="value"><?= $metode ?></span>
            </div>
            <?php if ($trx['metode_pembayaran'] === 'tunai' && !empty($trx['jumlah_bayar'])): ?>
            <div class="receipt-row">
                <span class="label">Uang Tunai</span>
                <span class="value">Rp <?= number_format($trx['jumlah_bayar'], 0, ',', '.') ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Kembalian</span>
                <span class="value">Rp <?= number_format($trx['jumlah_bayar'] - $grand_total_struk, 0, ',', '.') ?></span>
            </div>
            <?php endif; ?>

            <div style="text-align:center; margin: 10px 0;">
                <?php if ($isBayarNanti): ?>
                <span class="badge-paid" style="border-color:#e67e22;color:#e67e22;">&#9200; BELUM LUNAS</span>
                <?php else: ?>
                <span class="badge-paid">&#10003; LUNAS</span>
                <?php endif; ?>
            </div>

            <div class="receipt-footer">
                <div class="thanks">Terima Kasih!</div>
                Semoga badan Anda segar<br>
                dan rileks kembali.<br>
                Sampai jumpa lagi!<br><br>
                Kasir: <?= htmlspecialchars($nama_kasir) ?>
            </div>
        </div>

        <div class="print-btn-area">
            <button class="print-btn" onclick="window.print()">&#128424; Cetak Struk</button>
            <button class="close-btn" onclick="window.close()">Tutup</button>
        </div>
    </div>

    <script>
        // Auto print when page loads
        window.addEventListener('load', function() {
            // Small delay to allow styles to render
            setTimeout(() => {
                window.print();
            }, 400);
        });
    </script>
</body>
</html>