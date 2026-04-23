<?php
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if ($_SESSION['role'] != 'owner') { 
    header("Location: ../auth/login_system.php"); 
    exit; 
}

$pesan = "";
$tipe = "";

// ========================================================
// 1. LOGIKA PENGATURAN SISTEM (SHIFT & KOMISI)
// ========================================================
if (isset($_POST['simpan_pengaturan'])) {
    $jam_mulai_hari = $_POST['jam_mulai_hari'];
    $pagi_start     = $_POST['shift_pagi_start'];
    $pagi_end       = $_POST['shift_pagi_end'];
    $malam_start    = $_POST['shift_malam_start'];
    $malam_end      = $_POST['shift_malam_end'];
    
    $pagi_comp      = $_POST['pagi_share_company'];
    $pagi_ther      = $_POST['pagi_share_therapist'];
    $malam_comp     = $_POST['malam_share_company'];
    $malam_ther     = $_POST['malam_share_therapist'];

    if (($pagi_comp + $pagi_ther != 100) || ($malam_comp + $malam_ther != 100)) {
        $pesan = "Total persentase pembagian harus 100%!";
        $tipe  = "danger";
    } else {
        $sql  = "UPDATE settings SET 
                jam_mulai_hari=?, 
                shift_pagi_start=?, shift_pagi_end=?, 
                shift_malam_start=?, shift_malam_end=?,
                pagi_share_company=?, pagi_share_therapist=?,
                malam_share_company=?, malam_share_therapist=? 
                WHERE id=1";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$jam_mulai_hari, $pagi_start, $pagi_end, $malam_start, $malam_end, $pagi_comp, $pagi_ther, $malam_comp, $malam_ther])) {
            $pesan = "Pengaturan berhasil diperbarui!";
            $tipe  = "success";
        }
    }
}

// ========================================================
// 2. LOGIKA DOWNLOAD BACKUP MANUAL
// ========================================================
if (isset($_POST['download_backup'])) {
    try {
        $tables = [];
        $query = $pdo->query('SHOW TABLES');
        while ($row = $query->fetch(PDO::FETCH_NUM)) { $tables[] = $row[0]; }

        $sqlContent = "-- Manual Backup Bugar Refleksi\n";
        $sqlContent .= "-- Waktu: " . date('Y-m-d H:i:s') . "\n\n";
        $sqlContent .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $q = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $sqlContent .= "DROP TABLE IF EXISTS `$table`;\n" . $q[1] . ";\n\n";
            $data = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
            foreach ($data as $row) {
                $values = array_map(function($v) use ($pdo) { return is_null($v) ? 'NULL' : $pdo->quote($v); }, $row);
                $sqlContent .= "INSERT INTO `$table` VALUES(" . implode(',', $values) . ");\n";
            }
            $sqlContent .= "\n";
        }
        $sqlContent .= "SET FOREIGN_KEY_CHECKS=1;";

        $fileName = 'ManualBackup_' . date('d-m-Y_H-i') . '.sql';
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename=' . $fileName);
        echo $sqlContent;
        exit;
    } catch (Exception $e) {
        $pesan = "Gagal ekspor data: " . $e->getMessage();
        $tipe = "danger";
    }
}

// ========================================================
// 3. LOGIKA IMPORT BACKUP SQL (FIX ERROR 1822)
// ========================================================
if (isset($_POST['import_backup'])) {
    if ($_FILES['file_sql']['error'] == 0) {
        try {
            $sql = file_get_contents($_FILES['file_sql']['tmp_name']);
            
            // 1. FIX SYNTAX: MariaDB vs MySQL 8
            $sql = preg_replace('/DEFAULT curdate\(\)/i', 'DEFAULT (curdate())', $sql);
            $sql = preg_replace('/DEFAULT current_timestamp\(\)/i', 'DEFAULT CURRENT_TIMESTAMP', $sql);
            $sql = preg_replace('/ON UPDATE current_timestamp\(\)/i', 'ON UPDATE CURRENT_TIMESTAMP', $sql);
            
            // 2. FIX AKSES: Hapus klausa DEFINER
            $sql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/i', '', $sql);
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
            
            // 3. FIX ERROR 1822 (Missing Index): 
            // Ambil nama semua tabel yang ada di file SQL dan DROP lebih dulu.
            // Ini mencegah konflik Foreign Key pada tabel lama saat tabel baru sedang dibuat
            if (preg_match_all('/CREATE TABLE (IF NOT EXISTS )?`?([a-zA-Z0-9_]+)`?/i', $sql, $matches)) {
                $tablesToDrop = array_unique($matches[2]);
                foreach ($tablesToDrop as $tbl) {
                    $pdo->exec("DROP TABLE IF EXISTS `$tbl`");
                }
            }
            
            // 4. Pisahkan eksekusi baris SQL menggunakan pembatas koma-titik untuk keamanan
            $queries = preg_split('/;(?=[\r\n]+)/', $sql);
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $pdo->exec($query);
                }
            }
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
            $pesan = "Data berhasil diimport dari file backup tanpa kendala!";
            $tipe = "success";
        } catch (Exception $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
            $pesan = "Gagal import data: " . $e->getMessage();
            $tipe = "danger";
        }
    } else {
        $pesan = "Silakan pilih file SQL yang valid.";
        $tipe = "danger";
    }
}

// ========================================================
// 4. LOGIKA HAPUS SEMUA DATA 
// ========================================================
if (isset($_POST['hapus_semua'])) {
    $password = $_POST['password_owner'];
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        try {
            $pdo->beginTransaction();
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
            
            $tablesToClear = [
                'transactions', 'transaction_added_packages', 'terapis_attendance', 
                'kasir_attendance', 'shift_logs', 'pelanggaran', 'terapis_izin', 
                'terapis_loans', 'branch_notifications', 'item_usage_log'
            ];
            
            foreach ($tablesToClear as $table) {
                $pdo->exec("TRUNCATE TABLE `$table` ");
            }
            
            $pdo->exec("UPDATE beds SET status = 'kosong' ");
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
            $pdo->commit();
            
            $pesan = "Seluruh data operasional berhasil dihapus!";
            $tipe = "success";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pesan = "Gagal menghapus data: " . $e->getMessage();
            $tipe = "danger";
        }
    } else {
        $pesan = "Password owner salah! Data gagal dihapus.";
        $tipe = "danger";
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
        .persen-total { font-weight: bold; padding: 5px 10px; border-radius: 5px; margin-top: 5px; display: inline-block; }
        .persen-ok { background: #d4edda; color: #155724; }
        .persen-err { background: #f8d7da; color: #721c24; }
        .btn-full { width: 100%; margin-top: 10px; }
        .section-title { margin: 25px 0 10px; border-bottom: 2px solid var(--accent-yellow); padding-bottom: 5px; font-size: 16px; color: var(--text-dark); }
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
                    <div class="submenu-toggle active" onclick="toggleSubmenu(this)">
                        <span>Paket & Pengaturan</span>
                        <span class="arrow">▼</span>
                    </div>
                    <div class="submenu-items open">
                        <a href="paket_layanan.php" class="submenu-item">Paket Layanan</a>
                        <a href="pengaturan_sistem.php" class="submenu-item active">Pengaturan Sistem</a>
                    </div>
                </div>
                <a href="../auth/logout.php" class="menu-item" style="color: var(--accent-red); margin-top: 30px;">Keluar Sistem</a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar">
                <div style="display:flex; align-items:center; gap:15px;">
                    <button class="mobile-toggle" onclick="toggleMobileMenu()">☰</button>
                    <h1>Pengaturan Sistem</h1>
                </div>
                <button class="theme-btn" onclick="toggleTheme()">Mode Layar</button>
            </div>

            <?php if ($pesan): ?>
                <div class="alert alert-<?= $tipe ?>"><?= $pesan ?></div>
            <?php endif; ?>

            <div class="grid-2">
                <div class="card">
                    <div class="card-header">Konfigurasi Operasional</div>
                    <form method="POST" style="padding: 20px;">
                        <div class="form-group">
                            <label>Jam Mulai Hari (Reset Sistem)</label>
                            <input type="time" name="jam_mulai_hari" class="form-control" value="<?= $settings['jam_mulai_hari'] ?>" required>
                        </div>

                        <div class="section-title">Batas Absen Shift Pagi</div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Mulai Absen</label>
                                <input type="time" name="shift_pagi_start" class="form-control" value="<?= $settings['shift_pagi_start'] ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Selesai Absen</label>
                                <input type="time" name="shift_pagi_end" class="form-control" value="<?= $settings['shift_pagi_end'] ?>" required>
                            </div>
                        </div>

                        <div class="section-title">Batas Absen Shift Malam</div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Mulai Absen</label>
                                <input type="time" name="shift_malam_start" class="form-control" value="<?= $settings['shift_malam_start'] ?? '16:00:00' ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Selesai Absen</label>
                                <input type="time" name="shift_malam_end" class="form-control" value="<?= $settings['shift_malam_end'] ?? '18:00:00' ?>" required>
                            </div>
                        </div>

                        <div class="section-title">Pembagian Komisi</div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Pagi: Perusahaan (%)</label>
                                <input type="number" name="pagi_share_company" id="pagi_comp" class="form-control" value="<?= $settings['pagi_share_company'] ?>" oninput="cekPersen('pagi')" required>
                            </div>
                            <div class="form-group">
                                <label>Pagi: Terapis (%)</label>
                                <input type="number" name="pagi_share_therapist" id="pagi_ther" class="form-control" value="<?= $settings['pagi_share_therapist'] ?>" oninput="cekPersen('pagi')" required>
                            </div>
                        </div>
                        <div id="pagi_total" class="persen-total persen-ok">= 100% OK</div>

                        <div class="grid-2" style="margin-top:15px;">
                            <div class="form-group">
                                <label>Malam: Perusahaan (%)</label>
                                <input type="number" name="malam_share_company" id="malam_comp" class="form-control" value="<?= $settings['malam_share_company'] ?>" oninput="cekPersen('malam')" required>
                            </div>
                            <div class="form-group">
                                <label>Malam: Terapis (%)</label>
                                <input type="number" name="malam_share_therapist" id="malam_ther" class="form-control" value="<?= $settings['malam_share_therapist'] ?>" oninput="cekPersen('malam')" required>
                            </div>
                        </div>
                        <div id="malam_total" class="persen-total persen-ok">= 100% OK</div>

                        <button type="submit" name="simpan_pengaturan" class="btn btn-primary btn-full" style="margin-top: 25px;">Simpan Semua Pengaturan</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">Database Management</div>
                    <div style="padding: 20px;">
                        <div style="margin-bottom: 25px;">
                            <label style="font-weight:bold;">Backup & Import</label>
                            <form method="POST">
                                <button type="submit" name="download_backup" class="btn btn-success btn-full">Download Backup SQL</button>
                            </form>
                            <form method="POST" enctype="multipart/form-data" style="margin-top:15px;">
                                <input type="file" name="file_sql" class="form-control" accept=".sql" required>
                                <button type="submit" name="import_backup" class="btn btn-warning btn-full">Restore / Import Data</button>
                            </form>
                        </div>

                        <div style="background: rgba(231, 76, 60, 0.1); padding: 20px; border-radius: 8px; border: 1px solid var(--accent-red);">
                            <label style="font-weight:bold; color:var(--accent-red);">Reset Data Pabrik</label>
                            <p style="font-size:12px; margin-bottom:10px;">Menghapus semua transaksi dan absensi. Akun user tetap aman.</p>
                            <form method="POST">
                                <input type="password" name="password_owner" class="form-control" placeholder="Password Konfirmasi" required>
                                <button type="submit" name="hapus_semua" class="btn btn-danger btn-full" onclick="return confirm('Hapus semua data operasional?')">RESET DATA SEKARANG</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const b = document.documentElement;
            const next = b.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            b.setAttribute('data-theme', next);
            localStorage.setItem('bugar-theme', next);
        }
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

        document.addEventListener('DOMContentLoaded', () => {
            cekPersen('pagi');
            cekPersen('malam');
            const saved = localStorage.getItem('bugar-theme');
            if (saved) document.documentElement.setAttribute('data-theme', saved);
        });
    </script>
</body>
</html>