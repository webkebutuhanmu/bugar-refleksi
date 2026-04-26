<?php
session_start();
require_once 'koneksi.php';

if (isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit; 
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
        if ($password === $user['password']) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nama'] = $user['nama_lengkap'];
        
        $folder = ($user['role'] === 'supervisor') ? 'spv' : $user['role'];
        header("Location: $folder/dashboard_$folder.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login | Bugar App</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #5856D6;
            --primary-hover: #4544b1;
            --bg-light: #F2F2F7;
            --text-dark: #1C1C1E;
            --text-gray: #8E8E93;
            --danger: #FF3B30;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* Animasi Logo Hidup (Floating) */
        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .login-container {
            width: 100%;
            max-width: 400px;
        }

        .login-card {
            background: white;
            padding: 40px 35px;
            border-radius: 35px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.02);
            text-align: center;
        }

        /* Area Logo Tanpa Shape */
        .logo-box {
            margin-bottom: 30px;
        }

        .logo-link {
            display: inline-block;
            text-decoration: none;
            /* Efek Logo Hidup */
            animation: logoFloat 4s ease-in-out infinite;
        }

        .logo-img {
            width: 90px;
            height: auto;
            display: block;
            margin: 0 auto 10px;
        }

        .app-name {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0;
            letter-spacing: -0.5px;
        }

        .app-tagline {
            font-size: 13px;
            color: var(--text-gray);
            margin-top: 5px;
            font-weight: 600;
        }

        /* Form Input Statis (Tidak Hidup) */
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: var(--text-gray);
            margin-bottom: 8px;
            margin-left: 5px;
            text-transform: uppercase;
        }

        .input-wrapper { position: relative; }

        .input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #C7C7CC;
        }

        input {
            width: 100%;
            padding: 16px 16px 16px 50px;
            border: 2px solid #F2F2F7;
            background-color: #F2F2F7;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            outline: none;
            color: var(--text-dark);
        }

        /* Focus hanya merubah border, tidak ada transformasi/gerakan */
        input:focus {
            background-color: white;
            border-color: var(--primary);
        }

        .btn-login {
            width: 100%;
            padding: 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 8px 20px rgba(88, 86, 214, 0.2);
            transition: background 0.3s ease;
        }

        .btn-login:hover { background: var(--primary-hover); }

        .error-msg {
            background: #FFE5E5;
            color: var(--danger);
            padding: 12px 15px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-card">
            
            <div class="logo-box">
                <a href="login.php" class="logo-link">
                    <img src="https://www.dropbox.com/scl/fi/w50ceujd91ufw5gfc7boo/logo_bugar.png?rlkey=ns2z427ahk8dj87uhfiwxj8ro&st=c5kszi61&raw=1" alt="Logo Bugar" class="logo-img">
                    <h1 class="app-name">Bugar App</h1>
                    <p class="app-tagline">Management System Branch</p>
                </a>
            </div>

            <?php if ($error): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <input type="text" name="username" placeholder="Masukkan username" required autocomplete="off">
                        <i class="fas fa-user"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" placeholder="Masukkan password" required>
                        <i class="fas fa-lock"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    Masuk Sekarang <i class="fas fa-arrow-right" style="margin-left:8px;"></i>
                </button>
            </form>
            
            <p style="text-align:center; margin-top:30px; font-size:11px; color:#C7C7CC; font-weight:700;">
                &copy; <?= date('Y') ?> BUGAR APP MANAGEMENT
            </p>
        </div>
    </div>

</body>
</html>