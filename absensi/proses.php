<?php
session_start();
require_once 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;
$jam = date('H:i:s');
$tgl = date('Y-m-d');

if (!$user_id && $action !== 'logout') { header("Location: login.php"); exit; }

$f = ($role === 'supervisor') ? 'spv' : $role; // Menyesuaikan nama folder dan akhiran file

if ($action === 'absen_masuk') {
    $set = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
    $shift = $_POST['shift'];
    $alasan = $_POST['alasan'] ?? '';
    $batas = ($shift == 1) ? $set['s1_batas'] : $set['s2_batas'];
    
    $status = 'Tepat Waktu'; $status_alasan = 'none';

    if ($role !== 'kasir' && $role !== 'owner') {
        if ($jam > $batas) {
            $status = 'Terlambat'; $status_alasan = 'pending';
            $pdo->query("UPDATE users SET credit_score = credit_score - 5 WHERE id = $user_id");
        }
    }
    $stmt = $pdo->prepare("INSERT INTO attendance (user_id, tanggal, waktu_masuk, status_kehadiran, shift, alasan_terlambat, status_alasan) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $tgl, $jam, $status, $shift, $alasan, $status_alasan]);
    header("Location: $f/dashboard_$f.php");
}

elseif ($action === 'absen_keluar') {
    $stmt = $pdo->prepare("UPDATE attendance SET waktu_keluar = ? WHERE user_id = ? AND tanggal = ? AND waktu_keluar IS NULL");
    $stmt->execute([$jam, $user_id, $tgl]);
    header("Location: $f/dashboard_$f.php");
}

elseif ($action === 'add_branch' && ($role === 'supervisor' || $role === 'owner')) {
    $pdo->prepare("INSERT INTO branches (nama_cabang) VALUES (?)")->execute([$_GET['nama']]);
    header("Location: $f/riwayat_$f.php");
}

elseif ($action === 'edit_branch' && ($role === 'supervisor' || $role === 'owner')) {
    $pdo->prepare("UPDATE branches SET nama_cabang = ? WHERE id = ?")->execute([$_GET['nama'], $_GET['id']]);
    header("Location: $f/riwayat_$f.php");
}

elseif ($action === 'del_cabang' && ($role === 'supervisor' || $role === 'owner')) {
    $pdo->prepare("DELETE FROM branches WHERE id = ?")->execute([$_GET['id']]);
    header("Location: $f/riwayat_$f.php");
}

elseif ($action === 'save_user' && ($role === 'supervisor' || $role === 'owner')) {
    $pass = password_hash($_POST['pass'], PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, branch_id, credit_score) VALUES (?, ?, ?, ?, ?, 100)")->execute([$_POST['user'], $pass, $_POST['nama'], $_POST['role'], $_GET['branch_id']]);
    header("Location: $f/riwayat_$f.php");
}

elseif ($action === 'edit_user' && ($role === 'supervisor' || $role === 'owner')) {
    if(!empty($_GET['pass'])) {
        $hash = password_hash($_GET['pass'], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET nama_lengkap = ?, credit_score = ?, password = ? WHERE id = ?")->execute([$_GET['nama'], $_GET['skor'], $hash, $_GET['id']]);
    } else {
        $pdo->prepare("UPDATE users SET nama_lengkap = ?, credit_score = ? WHERE id = ?")->execute([$_GET['nama'], $_GET['skor'], $_GET['id']]);
    }
    header("Location: $f/riwayat_$f.php");
}

elseif ($action === 'del_staf' && ($role === 'supervisor' || $role === 'owner')) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['id']]);
    header("Location: $f/riwayat_$f.php");
}

elseif ($action === 'approve_alasan' && $role === 'supervisor') {
    $uid = $pdo->query("SELECT user_id FROM attendance WHERE id = ".$_GET['id'])->fetchColumn();
    $pdo->prepare("UPDATE attendance SET status_alasan = ? WHERE id = ?")->execute([$_GET['status'], $_GET['id']]);

    if ($_GET['status'] === 'approved') {
        $pdo->query("UPDATE users SET credit_score = credit_score + 5 WHERE id = $uid");
    }
    header("Location: $f/approval_$f.php");
}

elseif ($action === 'update_jam' && $role === 'owner') {
    $pdo->prepare("UPDATE settings SET s1_mulai = ?, s1_batas = ?, s2_mulai = ?, s2_batas = ? WHERE id = 1")->execute([$_POST['s1_mulai'], $_POST['s1_batas'], $_POST['s2_mulai'], $_POST['s2_batas']]);
    header("Location: $f/pengaturan_$f.php?success=1");
}

elseif ($action === 'logout') {
    session_destroy();
    header("Location: login.php");
}
?>