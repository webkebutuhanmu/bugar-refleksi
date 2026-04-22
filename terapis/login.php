<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
// Mengarah ke folder sistem untuk koneksi database
require_once '../sistem/config/database.php';

// Jika sudah login sebagai terapis, langsung ke dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] == 'terapis') {
    header("Location: dashboard_terapis.php");
    exit;
}

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
                // Pastikan HANYA terapis yang bisa login di halaman ini
                if ($user['role'] == 'terapis') {
                    session_regenerate_id(true);
                    $_SESSION['user_id']        = $user['id'];
                    $_SESSION['nama']           = $user['nama_lengkap'];
                    $_SESSION['role']           = $user['role'];
                    $_SESSION['user_branch_id'] = $user['branch_id'];

                    header("Location: dashboard_terapis.php");
                    exit;
                } else {
                    $error = "Akses Ditolak! Halaman ini khusus untuk Terapis.";
                }
            } else {
                $error = "Username atau Password salah!";
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login Terapis - Bugar Refleksi</title>
    
    <link rel="stylesheet" href="assets/style_terapis.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="login-body">
    
    <div class="theme-toggle-wrapper">
        <button onclick="toggleTheme()" class="btn-theme-login" id="themeBtn" aria-label="Toggle Theme">
            <i class="fas fa-moon"></i>
        </button>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="https://www.dropbox.com/scl/fi/w50ceujd91ufw5gfc7boo/logo_bugar.png?rlkey=ns2z427ahk8dj87uhfiwxj8ro&st=c5kszi61&raw=1" alt="Logo Bugar Refleksi" class="login-logo">
                <h1>Portal Terapis</h1>
                <p>Silakan masuk untuk memulai bekerja</p>
            </div>

            <?php if ($error): ?>
                <div class="login-alert">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" class="login-input" placeholder="Username Anda" required autocomplete="off">
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="password" class="login-input" placeholder="Password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword()" id="eyeIcon"></i>
                </div>
                
                <button type="submit" class="login-btn" id="btnLogin">
                    <span class="btn-text">MASUK SEKARANG</span>
                    <i class="fas fa-arrow-right btn-icon"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        // Fitur Tampilkan/Sembunyikan Password
        function togglePassword() {
            const pass = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pass.type === 'password') {
                pass.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pass.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Fitur Dark / Light Mode (sinkron dengan dashboard)
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-theme') === 'dark';
            const next = isDark ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            
            const btn = document.getElementById('themeBtn');
            btn.innerHTML = next === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        }

        // Load tema saat pertama kali halaman dibuka
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'dark'; // Bawaan Terapis adalah dark mode
            document.documentElement.setAttribute('data-theme', savedTheme);
            const btn = document.getElementById('themeBtn');
            if(btn) btn.innerHTML = savedTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        });

        // Loading state saat tombol masuk diklik
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnLogin');
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> MEMPROSES...';
        });
    </script>
</body>
</html>