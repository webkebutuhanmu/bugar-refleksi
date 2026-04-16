<?php
require_once '../config/database.php';
if ($_SESSION['role'] != 'owner') { header("Location: ../auth/login_system.php"); exit; }

$pesan = "";
$tipe = "";

if (isset($_POST['simpan_pengaturan'])) {
    $pagi_start     = $_POST['shift_pagi_start'];
    $jam_mulai_hari = $_POST['jam_mulai_hari'];
    $pagi_end       = $_POST['shift_pagi_end'];
    $pagi_comp      = $_POST['pagi_share_company'];
    $pagi_ther      = $_POST['pagi_share_therapist'];
    $malam_comp     = $_POST['malam_share_company'];
    $malam_ther     = $_POST['malam_share_therapist'];

    if (($pagi_comp + $pagi_ther != 100) || ($malam_comp + $malam_ther != 100)) {
        $pesan = "Total persentase pembagian harus 100%!";
        $tipe  = "danger";
    } else {
        $sql  = "UPDATE settings SET 
                shift_pagi_start=?, jam_mulai_hari=?, shift_pagi_end=?, 
                pagi_share_company=?, pagi_share_therapist=?,
                malam_share_company=?, malam_share_therapist=? 
                WHERE id=1";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$pagi_start, $jam_mulai_hari, $pagi_end, $pagi_comp, $pagi_ther, $malam_comp, $malam_ther])) {
            $pesan = "Pengaturan berhasil diperbarui!";
            $tipe  = "success";
        } else {
            $pesan = "Gagal menyimpan pengaturan.";
            $tipe  = "danger";
        }
    }
}

$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem - Owner</title>
    <link rel="stylesheet" href="../assets/style_owner.css">
    <style>
        .shift-card { padding: 25px; border-radius: 12px; background: var(--bg-panel); border: 1px solid var(--border-color); }
        .shift-card h3 { margin-top: 0; margin-bottom: 15px; color: var(--text-dark); border-bottom: 1px dashed var(--border-color); padding-bottom: 10px; }
        .persen-group { display: flex; align-items: center; gap: 10px; }
        .persen-group .form-control { text-align: center; font-weight: bold; font-size: 16px; }
        .persen-plus { font-size: 20px; font-weight: bold; color: var(--text-muted); }
        .persen-total { text-align: center; padding: 10px; border-radius: 6px; font-weight: bold; font-size: 14px; margin-top: 15px; }
        .persen-ok { background: rgba(39,174,96,0.15); color: #27ae60; border: 1px solid rgba(39,174,96,0.3); }
        .persen-err { background: rgba(231,76,60,0.15); color: #e74c3c; border: 1px solid rgba(231,76,60,0.3); }
        .info-box-styled { background: var(--bg-input); border-radius: 12px; padding: 25px; margin-bottom: 20px; border-left: 5px solid var(--accent-yellow); }
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
                        <a href="paket_layanan.php?tab=paket" class="submenu-item">Paket Layanan</a>
                        <a href="paket_layanan.php?tab=nonpaket" class="submenu-item">Layanan Non-Paket</a>
                        <a href="pengaturan_sistem.php" class="submenu-item active">Pengaturan Sistem</a>
                    </div>
                </div>
                <a href="../auth/logout_system.php" class="menu-item" style="color: var(--accent-red); margin-top: 30px;">Keluar Sistem</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div style="display:flex; align-items:center; gap:15px;">
                    <button class="mobile-toggle" onclick="toggleMobileMenu()">☰</button>
                    <h1>Pengaturan Sistem</h1>
                </div>
                <div class="topbar-right">
                    <button class="theme-btn" onclick="toggleTheme()">Dark / Light</button>
                </div>
            </div>

            <?php if($pesan): ?><div class="alert alert-<?= $tipe ?>"><?= $pesan ?></div><?php endif; ?>

            <div class="card" style="padding:0;">
                <div class="card-header" style="margin:20px 20px 0;">Pengaturan Jam Mulai Hari Bisnis & Shift</div>
                <form method="POST">
                    <div style="padding: 20px;">
                        <div class="info-box-styled">
                            <h3 style="margin-top:0; color:var(--text-dark);">Jam Mulai Hari Baru</h3>
                            <p style="color:var(--text-muted); font-size:14px; margin-bottom:15px;">
                                Tentukan jam berapa <strong>"hari baru"</strong> dimulai untuk perhitungan omset harian.<br>
                                <strong>Contoh:</strong> jika diset <strong>08:00</strong>, maka transaksi pukul 02:00 dini hari akan masuk ke omset <em>hari sebelumnya</em>, bukan hari ini.
                            </p>
                            <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
                                <div>
                                    <label style="font-weight:bold; color:var(--text-dark); display:block; margin-bottom:6px;">Jam Mulai Hari</label>
                                    <input type="time" name="jam_mulai_hari" class="form-control"
                                           value="<?= htmlspecialchars(substr($settings['jam_mulai_hari'] ?? '08:00:00', 0, 5)) ?>"
                                           required
                                           style="font-size:20px; font-weight:bold; text-align:center; width:160px; padding:10px;">
                                </div>
                                <div style="font-size:13px; color:var(--text-muted); background:var(--bg-form); padding:12px 16px; border-radius:8px; border: 1px solid var(--border-color);">
                                    <strong>Rekomendasi:</strong> sesuaikan dengan jam buka cabang. Default sistem: <strong>08:00</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid-2" style="padding: 0 20px 20px;">
                        <div class="shift-card">
                            <h3>Shift Pagi</h3>
                            <div class="form-group">
                                <label>Jam Mulai</label>
                                <input type="time" name="shift_pagi_start" class="form-control" value="<?= $settings['shift_pagi_start'] ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Jam Selesai</label>
                                <input type="time" name="shift_pagi_end" class="form-control" value="<?= $settings['shift_pagi_end'] ?>" required>
                            </div>
                            <h4 style="margin-top:20px; margin-bottom:10px; color:var(--text-dark);">Pembagian Omset Pagi</h4>
                            <div class="persen-group">
                                <div style="flex:1;">
                                    <label>Perusahaan (%)</label>
                                    <input type="number" name="pagi_share_company" id="pagi_comp" class="form-control" value="<?= $settings['pagi_share_company'] ?>" required min="0" max="100" oninput="cekPersen('pagi')">
                                </div>
                                <span class="persen-plus">+</span>
                                <div style="flex:1;">
                                    <label>Terapis (%)</label>
                                    <input type="number" name="pagi_share_therapist" id="pagi_ther" class="form-control" value="<?= $settings['pagi_share_therapist'] ?>" required min="0" max="100" oninput="cekPersen('pagi')">
                                </div>
                            </div>
                            <div class="persen-total persen-ok" id="pagi_total">= 100% OK</div>
                        </div>

                        <div class="shift-card">
                            <h3>Shift Malam (OVT)</h3>
                            <div style="font-size: 12px; padding: 10px; border-radius: 8px; background: var(--bg-input); color: var(--text-muted); margin-bottom:15px; border:1px solid var(--border-color);">
                                Shift malam otomatis dimulai setelah jam selesai Shift Pagi hingga sebelum jam mulai Shift Pagi.
                            </div>
                            <h4 style="margin-top:20px; margin-bottom:10px; color:var(--text-dark);">Pembagian Omset Malam</h4>
                            <div class="persen-group">
                                <div style="flex:1;">
                                    <label>Perusahaan (%)</label>
                                    <input type="number" name="malam_share_company" id="malam_comp" class="form-control" value="<?= $settings['malam_share_company'] ?>" required min="0" max="100" oninput="cekPersen('malam')">
                                </div>
                                <span class="persen-plus">+</span>
                                <div style="flex:1;">
                                    <label>Terapis (%)</label>
                                    <input type="number" name="malam_share_therapist" id="malam_ther" class="form-control" value="<?= $settings['malam_share_therapist'] ?>" required min="0" max="100" oninput="cekPersen('malam')">
                                </div>
                            </div>
                            <div class="persen-total persen-ok" id="malam_total">= 100% OK</div>
                        </div>
                    </div>

                    <div style="padding: 0 20px 20px;">
                        <button type="submit" name="simpan_pengaturan" class="btn btn-success" style="width: 100%; padding: 15px; font-weight: bold; font-size: 14px;">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

        function cekPersen(shift) {
            const comp = parseInt(document.getElementById(shift + '_comp').value) || 0;
            const ther = parseInt(document.getElementById(shift + '_ther').value) || 0;
            const total = comp + ther;
            const el = document.getElementById(shift + '_total');
            if (total === 100) {
                el.className = 'persen-total persen-ok';
                el.innerText = '= 100% OK';
            } else {
                el.className = 'persen-total persen-err';
                el.innerText = '= ' + total + '% (Harus 100%)';
            }
        }
    </script>
</body>
</html>