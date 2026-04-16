<?php
// File: kasir/notifikasi_terapis_dipinjam.php
// =====================================================
// NOTIFIKASI INFORMASI: Terapis Sedang Dipinjam
// (Tanpa tombol approve/reject, hanya sebagai informasi)
// =====================================================

if (!isset($branch_id)) {
    return; // Skip jika tidak ada branch_id
}

// Ambil data terapis yang sedang dipinjam ke cabang lain (ACTIVE dengan transaksi PROSES)
$sqlTerapisDipinjam = "SELECT tl.*, 
                       u.nama_lengkap as nama_terapis,
                       b.nama_cabang as cabang_peminjam,
                       t.nama_pelanggan,
                       t.waktu_selesai,
                       t.waktu_mulai,
                       t.status as transaction_status,
                       p.nama_paket,
                       bd.nomor_bed
                       FROM terapis_loans tl
                       JOIN users u ON tl.terapis_id = u.id
                       JOIN branches b ON tl.to_branch_id = b.id
                       JOIN transactions t ON tl.transaction_id = t.id
                       LEFT JOIN packages p ON t.package_id = p.id
                       LEFT JOIN beds bd ON t.bed_id = bd.id
                       WHERE tl.from_branch_id = ? 
                       AND tl.status = 'active'
                       AND t.status = 'proses'
                       ORDER BY tl.loan_time DESC";
$stmtDipinjam = $pdo->prepare($sqlTerapisDipinjam);
$stmtDipinjam->execute([$branch_id]);
$terapisDipinjam = $stmtDipinjam->fetchAll();

// Tampilkan ACTIVE LOANS (yang sedang berjalan)
if (count($terapisDipinjam) > 0):
?>
<div class="card" style="margin-bottom: 20px; border-left: 4px solid #3498db;">
    <div class="card-header" style="background: #ebf5fb; color: #2874a6;">
        ℹ️ Terapis Sedang Melayani di Cabang Lain
        <span style="background: #3498db; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 10px;">
            <?= count($terapisDipinjam) ?> AKTIF
        </span>
    </div>
    <div style="padding: 15px;">
        <div style="background: #d5f4e6; padding: 10px; border-radius: 5px; margin-bottom: 10px; border-left: 3px solid #27ae60;">
            <small style="color: #16a085;">
                ℹ️ <strong>Info:</strong> Terapis Anda sedang dipinjam dan melayani di cabang lain. 
                Mereka akan otomatis tersedia kembali setelah transaksi selesai.
            </small>
        </div>
        
        <?php foreach($terapisDipinjam as $td): ?>
        <div style="background: #eaf2f8; padding: 12px; margin-bottom: 10px; border-radius: 5px; border-left: 3px solid #3498db;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="flex: 1;">
                    <strong style="color: #2874a6;">💆 <?= htmlspecialchars($td['nama_terapis']) ?></strong>
                    <br>
                    <small style="color: #7f8c8d;">
                        📍 Melayani di: <strong><?= htmlspecialchars($td['cabang_peminjam']) ?></strong>
                        <br>
                        🛏️ Bed <?= htmlspecialchars($td['nomor_bed']) ?> • <?= htmlspecialchars($td['nama_paket']) ?>
                        <br>
                        👤 Customer: <?= htmlspecialchars($td['nama_pelanggan']) ?>
                        <br>
                        🕐 Mulai: <?= date('H:i', strtotime($td['waktu_mulai'])) ?>
                    </small>
                </div>
                <div style="text-align: right; margin-left: 15px;">
                    <div class="countdown-loan" data-finish="<?= $td['waktu_selesai'] ?>" style="background: #2874a6; color: white; padding: 8px 12px; border-radius: 5px; font-weight: bold; font-size: 14px; white-space: nowrap;">
                        ⏳ ...
                    </div>
                    <small style="color: #7f8c8d; display: block; margin-top: 5px;">
                        Est. Selesai: <?= date('H:i', strtotime($td['waktu_selesai'])) ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <small style="color: #7f8c8d; display: block; margin-top: 10px;">
            ✅ Terapis akan kembali tersedia otomatis setelah transaksi selesai
        </small>
    </div>
</div>

<script>
// Update countdown untuk terapis dipinjam
setInterval(() => {
    document.querySelectorAll('.countdown-loan').forEach(el => {
        const finish = new Date(el.dataset.finish);
        const now = new Date();
        const diff = finish - now;
        
        if(diff <= 0) {
            const ov = Math.abs(diff);
            const m = Math.floor(ov/60000);
            const s = Math.floor((ov%60000)/1000);
            el.innerHTML = '⚠️ OT +' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
            el.style.background = '#f39c12';
        } else {
            const m = Math.floor(diff/60000);
            const s = Math.floor((diff%60000)/1000);
            el.innerHTML = '⏳ ' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
        }
    });
}, 1000);
</script>
<?php
endif;
?>