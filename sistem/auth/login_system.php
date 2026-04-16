<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Username dan password tidak boleh kosong!";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']        = $user['id'];
                $_SESSION['nama']           = $user['nama_lengkap'];
                $_SESSION['role']           = $user['role'];
                $_SESSION['user_branch_id'] = $user['branch_id'];

                if ($user['role'] == 'kasir') {
                    $stmtCek = $pdo->prepare("SELECT * FROM kasir_attendance WHERE kasir_id = ? AND status = 'aktif' LIMIT 1");
                    $stmtCek->execute([$user['id']]);
                    $shiftAktif = $stmtCek->fetch();
                    if ($shiftAktif) {
                        $_SESSION['active_branch']  = $shiftAktif['branch_id'];
                        $_SESSION['session_id']     = $shiftAktif['session_id'];
                        $_SESSION['attendance_id']  = $shiftAktif['id'];
                        $_SESSION['waktu_buka']     = $shiftAktif['waktu_masuk'];
                        header("Location: ../kasir/dashboard_kasir.php");
                    } else {
                        header("Location: ../kasir/pilih_cabang.php");
                    }
                    exit;
                } elseif ($user['role'] == 'owner') {
                    header("Location: ../owner/dashboard_owner.php"); exit;
                } elseif ($user['role'] == 'admin') {
                    header("Location: ../admin/dashboard_admin.php"); exit;
                } elseif ($user['role'] == 'terapis') {
                    header("Location: ../terapis/dashboard_terapis.php"); exit;
                } elseif ($user['role'] == 'leader') {
                    if (empty($user['branch_id'])) {
                        $error = "Akun Leader belum diset ke cabang manapun. Hubungi Owner.";
                    } else {
                        header("Location: ../leader/dashboard_leader.php"); exit;
                    }
                } else {
                    $error = "Role akun tidak dikenali. Hubungi Administrator.";
                }
            } else {
                $error = "Username atau password salah!";
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan pada server. Silakan coba lagi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Bugar Refleksi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ============ CSS VARIABLES — LIGHT & DARK ============ */
        :root {
            /* Light Mode: white 40%, yellow 25%, black 10%, red 10%, other 15% */
            --bg-main:        #ffffff;
            --bg-panel:       #fffdf0;
            --bg-form:        #ffffff;
            --bg-input:       #fffff8;
            --accent-yellow:  #FFD600;
            --accent-yellow2: #F5B800;
            --accent-red:     #CC1A1A;
            --accent-red2:    #E02020;
            --text-dark:      #111111;
            --text-mid:       #333333;
            --text-muted:     #888888;
            --border-color:   #E8E0C8;
            --shadow-color:   rgba(0,0,0,0.10);
            --logo-bg:        #1a1a1a;
            --divider:        #EDDE8A;
            --btn-text:       #111111;
            --toggle-bg:      #F0E060;
            --toggle-circle:  #1a1a1a;
            --input-focus-shadow: rgba(255,214,0,0.25);
            --side-bg:        #1a1a1a;
            --side-text:      #FFD600;
            --side-sub:       rgba(255,214,0,0.65);
            --badge-bg:       rgba(255,214,0,0.15);
            --badge-text:     #b38f00;
        }

        [data-theme="dark"] {
            /* Dark Mode: dark dominant, yellow 25%, white accents, red pops */
            --bg-main:        #0f0f0f;
            --bg-panel:       #1a1a1a;
            --bg-form:        #1e1e1e;
            --bg-input:       #252525;
            --accent-yellow:  #FFD600;
            --accent-yellow2: #F5B800;
            --accent-red:     #E53030;
            --accent-red2:    #FF4444;
            --text-dark:      #ffffff;
            --text-mid:       #e0e0e0;
            --text-muted:     #999999;
            --border-color:   #333333;
            --shadow-color:   rgba(0,0,0,0.5);
            --logo-bg:        #0f0f0f;
            --divider:        #3a3a1a;
            --btn-text:       #111111;
            --toggle-bg:      #2a2a2a;
            --toggle-circle:  #FFD600;
            --input-focus-shadow: rgba(255,214,0,0.2);
            --side-bg:        #FFD600;
            --side-text:      #111111;
            --side-sub:       rgba(0,0,0,0.65);
            --badge-bg:       rgba(0,0,0,0.15);
            --badge-text:     #333333;
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-main);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            transition: background 0.4s ease, color 0.4s ease;
            overflow: hidden;
        }

        /* ========== THEME TOGGLE ========== */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 24px;
            z-index: 999;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toggle-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            transition: color 0.3s;
        }

        .toggle-switch {
            width: 52px;
            height: 28px;
            background: var(--toggle-bg);
            border-radius: 50px;
            position: relative;
            cursor: pointer;
            transition: background 0.3s ease;
            border: 2px solid var(--border-color);
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: var(--toggle-circle);
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }

        [data-theme="dark"] .toggle-switch::after {
            transform: translateX(24px);
        }

        .toggle-icon {
            font-size: 14px;
        }

        /* ========== SPLIT LAYOUT ========== */
        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* ---- LEFT PANEL (BRANDING) ---- */
        .brand-panel {
            width: 45%;
            background: var(--side-bg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 50px;
            position: relative;
            overflow: hidden;
            transition: background 0.4s ease;
        }

        /* geometric decorations */
        .brand-panel::before {
            content: '';
            position: absolute;
            width: 320px;
            height: 320px;
            border: 60px solid rgba(255,214,0,0.07);
            border-radius: 50%;
            top: -80px;
            left: -80px;
            transition: border-color 0.4s;
        }

        [data-theme="dark"] .brand-panel::before {
            border-color: rgba(0,0,0,0.10);
        }

        .brand-panel::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            border: 40px solid rgba(255,214,0,0.06);
            border-radius: 50%;
            bottom: -50px;
            right: -50px;
            transition: border-color 0.4s;
        }

        [data-theme="dark"] .brand-panel::after {
            border-color: rgba(0,0,0,0.08);
        }

        .brand-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .logo-wrap {
            margin-bottom: 30px;
            animation: floatLogo 4s ease-in-out infinite;
        }

        @keyframes floatLogo {
            0%, 100% { transform: translateY(0px); }
            50%       { transform: translateY(-8px); }
        }

        .logo-wrap img {
            width: 160px;
            height: auto;
            filter: drop-shadow(0 8px 24px rgba(0,0,0,0.35));
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 900;
            color: var(--side-text);
            line-height: 1.1;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
            transition: color 0.4s;
        }

        .brand-name span {
            color: var(--accent-red2);
        }

        [data-theme="dark"] .brand-name span {
            color: var(--accent-red2);
        }

        .brand-tagline {
            font-size: 13px;
            font-weight: 500;
            color: var(--side-sub);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 40px;
            transition: color 0.4s;
        }

        .brand-divider {
            width: 60px;
            height: 3px;
            background: var(--accent-red2);
            border-radius: 2px;
            margin: 0 auto 36px;
        }

        [data-theme="dark"] .brand-divider {
            background: #111;
        }

        .brand-features {
            list-style: none;
            text-align: left;
            display: inline-block;
        }

        .brand-features li {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            font-weight: 500;
            color: var(--side-sub);
            margin-bottom: 14px;
            transition: color 0.4s;
        }

        .brand-features li .dot {
            width: 8px;
            height: 8px;
            background: var(--accent-red2);
            border-radius: 50%;
            flex-shrink: 0;
        }

        [data-theme="dark"] .brand-features li .dot {
            background: #111;
        }

        /* ---- RIGHT PANEL (FORM) ---- */
        .form-panel {
            flex: 1;
            background: var(--bg-form);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 48px;
            position: relative;
            transition: background 0.4s ease;
        }

        .form-inner {
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.6s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes slideUp {
            from { opacity:0; transform:translateY(30px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .form-header {
            margin-bottom: 36px;
        }

        .form-header .greeting {
            font-size: 13px;
            font-weight: 600;
            color: var(--accent-yellow2);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .form-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 34px;
            font-weight: 900;
            color: var(--text-dark);
            line-height: 1.15;
            transition: color 0.4s;
        }

        .form-header h1 span {
            color: var(--accent-red);
        }

        .form-header p {
            margin-top: 10px;
            font-size: 14px;
            color: var(--text-muted);
            transition: color 0.4s;
        }

        /* ---- ALERT ---- */
        .alert-error {
            background: rgba(204,26,26,0.08);
            color: var(--accent-red);
            border: 1px solid rgba(204,26,26,0.2);
            border-left: 4px solid var(--accent-red);
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%,100%  { transform: translateX(0); }
            20%,60%  { transform: translateX(-6px); }
            40%,80%  { transform: translateX(6px); }
        }

        /* ---- FORM ELEMENTS ---- */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 8px;
            transition: color 0.4s;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            opacity: 0.5;
            pointer-events: none;
            transition: opacity 0.2s;
        }

        .form-group input {
            width: 100%;
            padding: 14px 48px 14px 48px;
            background: var(--bg-input);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
            color: var(--text-dark);
            outline: none;
            transition: border-color 0.25s, box-shadow 0.25s, background 0.4s, color 0.4s;
        }

        .form-group input::placeholder {
            color: var(--text-muted);
            font-weight: 400;
        }

        .form-group input:focus {
            border-color: var(--accent-yellow);
            box-shadow: 0 0 0 4px var(--input-focus-shadow);
            background: var(--bg-form);
        }

        .form-group input:focus + .focus-line {
            width: 100%;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 16px;
            opacity: 0.45;
            transition: opacity 0.2s;
            user-select: none;
        }

        .toggle-password:hover { opacity: 0.85; }

        /* ---- BUTTON ---- */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: var(--accent-yellow);
            color: var(--btn-text);
            border: none;
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 8px;
            position: relative;
            overflow: hidden;
            transition: transform 0.15s, box-shadow 0.2s, background 0.3s;
            box-shadow: 0 4px 20px rgba(255,214,0,0.35);
        }

        .btn-login::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 60%);
            border-radius: 12px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(255,214,0,0.45);
            background: var(--accent-yellow2);
        }

        .btn-login:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(255,214,0,0.3);
        }

        .btn-login:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none;
        }

        /* Red accent btn stripe */
        .btn-stripe {
            display: block;
            height: 4px;
            background: var(--accent-red);
            border-radius: 0 0 12px 12px;
            margin-top: -4px;
            width: 100%;
            opacity: 0.7;
        }

        /* ---- SPINNER ---- */
        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0,0,0,0.2);
            border-top-color: #111;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ---- FOOTER ---- */
        .form-footer {
            margin-top: 32px;
            text-align: center;
            font-size: 12px;
            color: var(--text-muted);
            transition: color 0.4s;
        }

        .form-footer strong {
            color: var(--accent-red);
        }

        /* ---- BADGE ---- */
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--badge-bg);
            color: var(--badge-text);
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 12px;
            border: 1px solid rgba(255,214,0,0.3);
        }

        [data-theme="dark"] .role-badge {
            background: rgba(255,214,0,0.12);
            color: var(--accent-yellow);
            border-color: rgba(255,214,0,0.25);
        }

        /* ---- RESPONSIVE ---- */
        @media (max-width: 768px) {
            body { overflow: auto; }
            .wrapper { flex-direction: column; }
            .brand-panel {
                width: 100%;
                padding: 50px 30px 40px;
                flex-direction: row;
                gap: 24px;
                justify-content: center;
            }
            .logo-wrap { margin-bottom: 0; animation: none; }
            .logo-wrap img { width: 90px; }
            .brand-name { font-size: 24px; text-align: left; }
            .brand-tagline { text-align: left; margin-bottom: 0; }
            .brand-divider, .brand-features { display: none; }
            .brand-content { text-align: left; }
            .form-panel { padding: 40px 28px; }
        }
    </style>
</head>
<body>

<!-- THEME TOGGLE -->
<div class="theme-toggle">
    <span class="toggle-icon">☀️</span>
    <div class="toggle-switch" onclick="toggleTheme()" title="Toggle Dark/Light Mode"></div>
    <span class="toggle-icon">🌙</span>
</div>

<div class="wrapper">

    <!-- ===== LEFT: BRAND PANEL ===== -->
    <div class="brand-panel">
        <div class="brand-content">
            <div class="logo-wrap">
                <img src="https://www.dropbox.com/scl/fi/w50ceujd91ufw5gfc7boo/logo_bugar.png?rlkey=ns2z427ahk8dj87uhfiwxj8ro&st=c5kszi61&raw=1" alt="Bugar Refleksi Logo"
                     onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex'">
                <div id="logo-fallback" style="display:none; width:140px; height:140px; background:var(--accent-red); border-radius:50%; align-items:center; justify-content:center; font-size:56px; margin:0 auto;">💆</div>
            </div>

            <p class="brand-tagline">No. 1 For Your Health</p>
            <div class="brand-name">
                BUGAR<br><span>REFLEKSI</span>
            </div>
            <div class="brand-divider"></div>

            <ul class="brand-features">
                <li><span class="dot"></span> Manajemen Kasir & Shift</li>
                <li><span class="dot"></span> Monitoring Terapis Real-time</li>
                <li><span class="dot"></span> Laporan Keuangan Terpadu</li>
                <li><span class="dot"></span> Multi-Cabang Terintegrasi</li>
            </ul>
        </div>
    </div>

    <!-- ===== RIGHT: FORM PANEL ===== -->
    <div class="form-panel">
        <div class="form-inner">

            <div class="form-header">
                <div class="role-badge">⚡ Management System</div>
                <h1>Selamat<br><span>Datang</span> Kembali</h1>
                <p>Masuk ke akun Anda untuk melanjutkan aktivitas.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" autocomplete="off">

                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Masukkan username Anda"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Masukkan password Anda"
                            required
                        >
                        <span class="toggle-password" onclick="togglePassword()" id="eyeIcon">👁️</span>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="spinner" id="spinner"></span>
                    MASUK APLIKASI
                </button>
                <span class="btn-stripe"></span>

            </form>

            <div class="form-footer">
                &copy; <?= date('Y') ?> <strong>Bugar Refleksi</strong>. All rights reserved.
            </div>
        </div>
    </div>

</div>

<script>
    // ===== THEME TOGGLE =====
    function toggleTheme() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme');
        const next = current === 'light' ? 'dark' : 'light';
        html.setAttribute('data-theme', next);
        localStorage.setItem('bugar-theme', next);
    }

    // Restore saved theme
    (function() {
        const saved = localStorage.getItem('bugar-theme');
        if (saved) document.documentElement.setAttribute('data-theme', saved);
    })();

    // ===== PASSWORD TOGGLE =====
    function togglePassword() {
        const input = document.getElementById('password');
        const icon  = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = '🙈';
        } else {
            input.type = 'password';
            icon.textContent = '👁️';
        }
    }

    // ===== LOADING STATE =====
    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn     = document.getElementById('btnLogin');
        const spinner = document.getElementById('spinner');
        btn.disabled = true;
        spinner.style.display = 'inline-block';
        btn.childNodes[btn.childNodes.length - 1].textContent = ' MEMPROSES...';
    });
</script>

</body>
</html>