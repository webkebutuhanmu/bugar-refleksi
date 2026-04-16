<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir') { header("Location: ../auth/login_system.php"); exit; }

$pesan = "";
$tipe = "";
$kasir_id = $_SESSION['user_id'];

// 1. Cek shift gantung
$cekShiftSaya = $pdo->prepare("SELECT * FROM kasir_attendance WHERE kasir_id = ? AND status = 'aktif' LIMIT 1");
$cekShiftSaya->execute([$kasir_id]);
$shiftSaya = $cekShiftSaya->fetch();

if ($shiftSaya) {
    $_SESSION['active_branch'] = $shiftSaya['branch_id'];
    $_SESSION['session_id'] = $shiftSaya['session_id'];
    $_SESSION['attendance_id'] = $shiftSaya['id'];
    $_SESSION['waktu_buka'] = $shiftSaya['waktu_masuk'];
    header("Location: dashboard_kasir.php");
    exit;
}

// Proses Buka Cabang
if (isset($_POST['buka_cabang'])) {
    $branch_id = $_POST['branch_id'];
    $pin_input = $_POST['pin'];
    
    $stmtCheck = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
    $stmtCheck->execute([$branch_id]);
    $branch = $stmtCheck->fetch();

    if ($branch && $branch['pin'] === $pin_input) {
        $session_id = session_id(); 
        $waktu_masuk = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO kasir_attendance (kasir_id, branch_id, session_id, waktu_masuk, status, tanggal) 
                VALUES (?, ?, ?, ?, 'aktif', CURDATE())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$kasir_id, $branch_id, $session_id, $waktu_masuk]);

        $last_id = $pdo->lastInsertId();
        $_SESSION['active_branch'] = $branch_id;
        $_SESSION['session_id'] = $session_id;
        $_SESSION['attendance_id'] = $last_id;
        $_SESSION['waktu_buka'] = $waktu_masuk;

        header("Location: dashboard_kasir.php");
        exit;
    } else {
        $pesan = "PIN Salah! Silakan coba lagi.";
        $tipe = "danger";
    }
}

// 3. Ambil daftar cabang
$query = "SELECT b.*, 
          (SELECT COUNT(*) FROM kasir_attendance ka WHERE ka.branch_id = b.id AND ka.status = 'aktif') as is_occupied,
          (SELECT u.nama_lengkap FROM kasir_attendance ka JOIN users u ON ka.kasir_id = u.id WHERE ka.branch_id = b.id AND ka.status = 'aktif' LIMIT 1) as kasir_name
          FROM branches b 
          ORDER BY b.nama_cabang ASC";
$branches = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Cabang - Bugar Refleksi</title>
    <link rel="stylesheet" href="../assets/style_kasir.css">
    <style>
        .login-container { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: var(--bg-input); }
        .login-box { background: var(--bg-panel); padding: 40px; border-radius: 16px; box-shadow: var(--shadow-md); max-width: 450px; width: 90%; border: 1px solid var(--border-color); }
        .branch-option { 
            border: 2px solid var(--border-color); padding: 15px; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; background: var(--bg-panel); color: var(--text-dark);
        }
        .branch-option:hover:not(.disabled) { border-color: var(--accent-blue); background: var(--sidebar-hover); }
        .branch-option.selected { border-color: var(--accent-green); background: rgba(46,204,113,0.05); }
        .branch-option.disabled { background: var(--bg-input); border-color: var(--border-color); cursor: not-allowed; opacity: 0.6; }
        .pin-area { margin-top: 20px; display: none; border-top: 1px solid var(--border-color); padding-top: 20px; }
        .pin-input { width: 100%; padding: 15px; font-size: 24px; text-align: center; letter-spacing: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-input); color: var(--text-dark); outline: none; transition: 0.3s; font-weight: bold; }
        .pin-input:focus { border-color: var(--accent-yellow); }
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: 600; }
        .alert-danger { background: rgba(231,76,60,0.1); color: #e74c3c; border-left: 4px solid #e74c3c; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div style="text-align: right; margin-bottom: -20px;">
                <button class="theme-btn" onclick="toggleTheme()" style="padding: 5px 10px; font-size: 10px;">Dark/Light</button>
            </div>
            <h2 style="text-align: center; margin-bottom: 10px; font-family: 'Plus Jakarta Sans', sans-serif;">Mulai Shift</h2>
            <p style="text-align: center; color: var(--text-muted); margin-bottom: 25px; font-size: 14px;">Halo, <strong><?= htmlspecialchars($_SESSION['nama']) ?></strong><br>Silakan pilih lokasi kerja Anda</p>
            
            <?php if($pesan): ?>
            <div class="alert alert-<?= $tipe ?>">
                <?= $pesan ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="branch_id" id="selected_branch_id">
                
                <div style="max-height: 300px; overflow-y: auto; padding-right: 5px;">
                    <?php foreach($branches as $b): 
                        $isBusy = $b['is_occupied'] > 0;
                        $class = $isBusy ? 'disabled' : '';
                        $onclick = $isBusy ? '' : "selectBranch(this, {$b['id']})";
                    ?>
                    <div class="branch-option <?= $class ?>" onclick="<?= $onclick ?>">
                        <div>
                            <strong style="font-size: 15px;"><?= htmlspecialchars($b['nama_cabang']) ?></strong>
                            <?php if($isBusy): ?>
                                <br><small style="color: var(--accent-red); font-weight: 600;">Kasir: <?= htmlspecialchars($b['kasir_name']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php if($isBusy): ?>
                            <span class="badge badge-danger">Dipakai</span>
                        <?php else: ?>
                            <span class="badge badge-success">Kosong</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div id="pinArea" class="pin-area">
                    <label style="display: block; text-align: center; margin-bottom: 10px; font-size: 13px; font-weight: bold; color: var(--text-muted); text-transform: uppercase;">Masukkan PIN Cabang</label>
                    <input type="password" name="pin" class="pin-input" maxlength="6" placeholder="******" required>
                    <button type="submit" name="buka_cabang" class="btn btn-success" style="width: 100%; margin-top: 15px; padding: 15px; font-size: 15px;">BUKA SHIFT</button>
                </div>
            </form>
            
            <div style="text-align: center; margin-top: 25px;">
                <a href="../auth/logout_system.php" style="color: var(--accent-red); font-size: 13px; font-weight: bold; text-decoration: underline;">Kembali / Logout</a>
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
        (function() {
            const saved = localStorage.getItem('bugar-theme');
            if (saved) document.documentElement.setAttribute('data-theme', saved);
        })();

        function selectBranch(element, id) {
            document.querySelectorAll('.branch-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('selected_branch_id').value = id;
            document.getElementById('pinArea').style.display = 'block';
            document.querySelector('.pin-input').focus();
        }
    </script>
</body>
</html>