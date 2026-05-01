<?php
session_start();
require_once 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$error = '';
$success = '';
$step = $_GET['step'] ?? 1;
$action = $_GET['action'] ?? '';

// =========================================================================
// FUNGSI PENYAMARAN (MASKING) EMAIL DINAMIS
// =========================================================================
function maskEmail($email) {
    if (!$email || !str_contains($email, '@')) return 'Email tidak valid';
    $parts = explode("@", $email);
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);

    if ($len <= 4) {
        $masked_name = substr($name, 0, 1) . str_repeat('*', max(1, $len - 2)) . substr($name, -1);
    } elseif ($len >= 5 && $len <= 8) {
        $masked_name = substr($name, 0, 2) . str_repeat('*', $len - 3) . substr($name, -1);
    } else {
        $masked_name = substr($name, 0, 3) . str_repeat('*', $len - 5) . substr($name, -2);
    }
    return $masked_name . '@' . $domain;
}

// =========================================================================
// LOGIKA PEMROSESAN (BACKEND)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' || $action === 'kirim_otp') {
    
    // STEP 1: CEK USERNAME
    if ($action === 'cek_username') {
        $username = trim($_POST['username']);
        $stmt = $pdo->prepare("SELECT id, email, nama_lengkap FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            if (empty($user['email'])) {
                $error = "Akun ini belum mendaftarkan email. Silakan hubungi Owner/SPV.";
                $step = 1;
            } else {
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_email'] = $user['email'];
                $_SESSION['reset_nama'] = $user['nama_lengkap'];
                header("Location: lupa_password.php?step=2"); exit;
            }
        } else {
            $error = "Username tidak ditemukan di sistem.";
            $step = 1;
        }
    }

    // STEP 2 & RESEND: KIRIM OTP VIA BREVO
    elseif ($action === 'kirim_otp') {
        if (!isset($_SESSION['reset_user_id'])) { header("Location: lupa_password.php"); exit; }
        
        $uid = $_SESSION['reset_user_id'];
        $email_asli = $_SESSION['reset_email'];
        $nama = $_SESSION['reset_nama'];
        $otp = sprintf("%06d", mt_rand(100000, 999999)); 
        // Waktu kadaluarsa diatur lama di background (1 jam) agar tidak mengganggu user, 
        // namun kode lama tetap akan terhapus jika klik 'Kirim Ulang'.
        $expired = date('Y-m-d H:i:s', strtotime('+1 hour')); 

        // PENTING: Hapus kode sebelumnya agar kode lama tidak berlaku lagi
        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$uid]); 
        // Simpan kode baru
        $pdo->prepare("INSERT INTO password_resets (user_id, otp_code, expired_at) VALUES (?, ?, ?)")->execute([$uid, $otp, $expired]);

        // ================= API BREVO =================
        $api_key = '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'; 
        $url = 'https://api.brevo.com/v3/smtp/email';
        $data = [
            'sender' => ['name' => 'Bugar App System', 'email' => 'yusuf030106@gmail.com'],
            'to' => [['email' => $email_asli, 'name' => $nama]],
            'subject' => 'Kode Verifikasi Lupa Password Bugar App',
            'htmlContent' => "
                <div style='font-family:sans-serif; max-width:500px; margin:0 auto; padding:20px; border:1px solid #eee; border-radius:15px;'>
                    <h2 style='color:#5856D6; text-align:center;'>Bugar App</h2>
                    <p>Halo <b>$nama</b>,</p>
                    <p>Berikut adalah kode verifikasi (OTP) Anda untuk mereset password:</p>
                    <div style='background:#F2F2F7; padding:15px; text-align:center; font-size:32px; font-weight:bold; letter-spacing:5px; border-radius:10px; margin:20px 0;'>$otp</div>
                    <p style='color:#8E8E93; font-size:12px; text-align:center;'>Jika Anda meminta kode baru, maka kode ini otomatis tidak berlaku lagi.</p>
                </div>"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'accept: application/json', 'api-key: ' . $api_key, 'content-type: application/json' ]);
        curl_exec($ch);
        curl_close($ch);
        // =============================================

        header("Location: lupa_password.php?step=3"); exit;
    }

    // STEP 3: VALIDASI OTP
    elseif ($action === 'cek_otp') {
        if (!isset($_SESSION['reset_user_id'])) { header("Location: lupa_password.php"); exit; }
        
        $uid = $_SESSION['reset_user_id'];
        $input_otp = trim($_POST['otp']);

        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE user_id = ? AND otp_code = ?");
        $stmt->execute([$uid, $input_otp]);
        $cek = $stmt->fetch();

        if ($cek) {
            $_SESSION['otp_verified'] = true;
            header("Location: lupa_password.php?step=4"); exit;
        } else {
            $error = "Kode OTP salah atau sudah tidak berlaku.";
            $step = 3; // KUNCI PERBAIKAN: Paksa tetap di Step 3
        }
    }

    // STEP 4: GANTI PASSWORD
    elseif ($action === 'reset_password') {
        if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['reset_user_id'])) { header("Location: lupa_password.php"); exit; }

        $pass_baru = $_POST['password_baru'];
        $pass_konfirm = $_POST['password_konfirm'];

        if (strlen($pass_baru) < 6) {
            $error = "Password baru minimal 6 karakter!";
            $step = 4; // Paksa tetap di Step 4
        } elseif ($pass_baru !== $pass_konfirm) {
            $error = "Konfirmasi password tidak cocok!";
            $step = 4; // Paksa tetap di Step 4
        } else {
            $uid = $_SESSION['reset_user_id'];
            $new_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $uid]);
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$uid]);

            unset($_SESSION['reset_user_id']); unset($_SESSION['reset_email']); 
            unset($_SESSION['reset_nama']); unset($_SESSION['otp_verified']);

            $_SESSION['flash_success'] = "Password berhasil diubah! Silakan login.";
            header("Location: login.php"); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lupa Password | Bugar App</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Tambahan Library SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary: #5856D6; --primary-hover: #4544b1; --bg-light: #F2F2F7; --text-dark: #1C1C1E; --text-gray: #8E8E93; --danger: #FF3B30; --success: #34C759;}
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-light); margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .login-card { background: white; padding: 40px 35px; border-radius: 35px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.02); text-align: center; width: 100%; max-width: 400px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; font-size: 11px; font-weight: 800; color: var(--text-gray); margin-bottom: 8px; margin-left: 5px; text-transform: uppercase; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #C7C7CC; }
        input { width: 100%; padding: 16px 16px 16px 50px; border: 2px solid #F2F2F7; background-color: #F2F2F7; border-radius: 16px; font-size: 15px; font-weight: 600; outline: none; color: var(--text-dark); transition: 0.3s; }
        input:focus { background-color: white; border-color: var(--primary); }
        .btn-login { width: 100%; padding: 18px; background: var(--primary); color: white; border: none; border-radius: 16px; font-size: 16px; font-weight: 800; cursor: pointer; margin-top: 10px; box-shadow: 0 8px 20px rgba(88, 86, 214, 0.2); transition: 0.3s ease; }
        .btn-login:hover { background: var(--primary-hover); }
        .error-msg { background: #FFE5E5; color: var(--danger); padding: 12px 15px; border-radius: 12px; font-size: 13px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; text-align:left;}
        .otp-box { letter-spacing: 15px; text-align: center; padding: 16px; font-size: 24px; font-weight: 800; }
        .resend-box { margin-top: 25px; font-size: 13px; font-weight: 700; color: var(--text-gray); }
        .resend-link { color: var(--primary); text-decoration: none; border-bottom: 2px solid rgba(88,86,214,0.2); padding-bottom: 2px; transition: 0.2s; cursor: pointer; }
        .resend-link:hover { border-bottom-color: var(--primary); }
    </style>
</head>
<body>

    <div class="login-card">
        
        <?php if ($step == 1): ?>
        <i class="fas fa-search" style="font-size: 45px; color: var(--primary); margin-bottom: 15px;"></i>
        <h2 style="margin:0 0 5px; font-weight:800; color:var(--text-dark);">Cari Akun</h2>
        <p style="font-size:13px; color:var(--text-gray); margin-bottom:25px; font-weight:600;">Masukkan username Anda untuk mereset password.</p>
        <?php if ($error): ?><div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
        <form action="lupa_password.php?action=cek_username&step=1" method="POST">
            <div class="form-group"><label>Username Anda</label><div class="input-wrapper"><input type="text" name="username" placeholder="Masukkan username" required autocomplete="off"><i class="fas fa-user"></i></div></div>
            <button type="submit" class="btn-login">Cari Akun <i class="fas fa-arrow-right" style="margin-left:8px;"></i></button>
            <a href="login.php" style="display:block; margin-top:20px; font-size:13px; color:var(--text-gray); text-decoration:none; font-weight:700;">Kembali ke Login</a>
        </form>

        <?php elseif ($step == 2): ?>
        <i class="fas fa-envelope-open-text" style="font-size: 45px; color: var(--primary); margin-bottom: 15px;"></i>
        <h2 style="margin:0 0 5px; font-weight:800; color:var(--text-dark);">Konfirmasi Email</h2>
        <p style="font-size:13px; color:var(--text-gray); margin-bottom:25px; font-weight:600;">Akun ditemukan. Apakah ini email Anda?</p>
        <div style="background:#F2F2F7; padding:20px; border-radius:15px; margin-bottom:25px;">
            <i class="fas fa-shield-alt" style="color:#C7C7CC; font-size:24px; margin-bottom:10px;"></i>
            <div style="font-size:16px; font-weight:800; color:var(--text-dark); letter-spacing:1px;"><?= maskEmail($_SESSION['reset_email']) ?></div>
            <div style="font-size:11px; color:var(--text-gray); margin-top:5px;">Kode verifikasi akan dikirimkan ke alamat ini.</div>
        </div>
        <form action="lupa_password.php?action=kirim_otp&step=2" method="POST">
            <button type="submit" class="btn-login" style="background:var(--success); box-shadow:0 8px 20px rgba(52,199,89,0.2);">Ya, Kirim Kode Verifikasi</button>
            <a href="lupa_password.php?step=1" style="display:block; margin-top:20px; font-size:13px; color:var(--danger); text-decoration:none; font-weight:700;">Bukan email saya</a>
        </form>

        <?php elseif ($step == 3): ?>
        <i class="fas fa-unlock-alt" style="font-size: 45px; color: var(--primary); margin-bottom: 15px;"></i>
        <h2 style="margin:0 0 5px; font-weight:800; color:var(--text-dark);">Masukkan OTP</h2>
        <p style="font-size:13px; color:var(--success); margin-bottom:20px; font-weight:800;"><i class="fas fa-check-circle"></i> Kode verifikasi telah dikirim!</p>
        <?php if ($error): ?><div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
        
        <form action="lupa_password.php?action=cek_otp&step=3" method="POST">
            <div class="form-group"><label style="text-align:center;">KODE 6 DIGIT</label><input type="text" name="otp" class="otp-box" placeholder="••••••" maxlength="6" required autocomplete="off" autofocus oninput="this.value = this.value.replace(/[^0-9]/g, '');"></div>
            <button type="submit" class="btn-login">Verifikasi Kode</button>
        </form>
        
        <div class="resend-box">
            Belum menerima kode? <br><br>
            <span id="waitText" style="color:var(--danger);">Tunggu <span id="resendTimer">60</span> detik untuk kirim ulang</span>
            <a href="#" class="resend-link" id="resendLink" style="display:none;" onclick="konfirmasiKirimUlang(event)">Kirim Ulang Kode Verifikasi</a>
        </div>

        <script>
            let resendLeft = 60;
            const resendTimerEl = document.getElementById('resendTimer');
            const waitTextEl = document.getElementById('waitText');
            const resendLinkEl = document.getElementById('resendLink');

            const timerInt = setInterval(() => {
                resendLeft--;
                if (resendLeft <= 0) {
                    clearInterval(timerInt);
                    waitTextEl.style.display = 'none';
                    resendLinkEl.style.display = 'inline-block';
                } else {
                    resendTimerEl.innerText = resendLeft;
                }
            }, 1000);

            function konfirmasiKirimUlang(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Kirim Ulang Kode?',
                    html: 'Kode baru akan dikirimkan ke email Anda.<br><span style="color:var(--danger); font-size:13px; font-weight:bold;">Kode yang sebelumnya otomatis akan hangus.</span>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#5856D6',
                    cancelButtonColor: '#E5E5EA',
                    cancelButtonText: '<span style="color:#1C1C1E; font-weight:bold;">Batal</span>',
                    confirmButtonText: 'Ya, Kirim Ulang'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'lupa_password.php?action=kirim_otp';
                    }
                });
            }
        </script>

        <?php elseif ($step == 4): ?>
        <i class="fas fa-key" style="font-size: 45px; color: var(--success); margin-bottom: 15px;"></i>
        <h2 style="margin:0 0 5px; font-weight:800; color:var(--text-dark);">Buat Password Baru</h2>
        <p style="font-size:13px; color:var(--text-gray); margin-bottom:25px; font-weight:600;">Verifikasi berhasil! Silakan buat password baru.</p>
        <?php if ($error): ?><div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
        <form action="lupa_password.php?action=reset_password&step=4" method="POST">
            <div class="form-group"><label>Password Baru</label><div class="input-wrapper"><input type="password" name="password_baru" placeholder="Minimal 6 karakter" minlength="6" required><i class="fas fa-lock"></i></div></div>
            <div class="form-group"><label>Konfirmasi Password</label><div class="input-wrapper"><input type="password" name="password_konfirm" placeholder="Ulangi password baru" required><i class="fas fa-check-circle"></i></div></div>
            <button type="submit" class="btn-login" style="background:var(--success); box-shadow:0 8px 20px rgba(52,199,89,0.2);">Simpan Password Baru</button>
        </form>
        <?php endif; ?>

    </div>

</body>
</html>