<?php
session_start();
require_once 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$action  = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$role    = $_SESSION['role']    ?? null;

$jam = date('H:i:s');
$tgl = date('Y-m-d');

if (!$user_id && $action !== 'logout') { header("Location: login.php"); exit; }

$f = ($role === 'supervisor') ? 'spv' : $role;

function isTerlambat(string $jam_masuk, string $batas, int $shift, string $s1_mulai): bool {
    if ($shift === 2 && strtotime($jam_masuk) < strtotime($s1_mulai)) {
        return true;
    }
    return strtotime($jam_masuk) > strtotime($batas);
}

function deteksiShift(array $set, string $jam): array {
    $t        = strtotime($jam);
    $s1_mulai = strtotime($set['s1_mulai']);
    $s2_mulai = strtotime($set['s2_mulai']);

    if ($t >= $s1_mulai && $t < $s2_mulai) {
        return ['shift' => 1, 'batas' => $set['s1_batas'], 'nama' => 'Shift 1 (Pagi)'];
    }
    return ['shift' => 2, 'batas' => $set['s2_batas'], 'nama' => 'Shift 2 (Malam)'];
}

if ($action === 'absen_masuk') {
    $set    = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
    $shift  = (int)($_POST['shift'] ?? 1);
    $alasan = trim($_POST['alasan'] ?? '');

    $batas = ($shift === 1) ? $set['s1_batas'] : $set['s2_batas'];

    $status        = 'Tepat Waktu';
    $status_alasan = 'none';

    if ($role !== 'kasir' && $role !== 'owner') {
        if (isTerlambat($jam, $batas, $shift, $set['s1_mulai'])) {
            $status        = 'Terlambat';
            $status_alasan = 'pending';
            $pdo->prepare("UPDATE users SET credit_score = credit_score - 5 WHERE id = ?")
                ->execute([$user_id]);
        }
    }

    $pdo->prepare("INSERT INTO attendance (user_id, tanggal, waktu_masuk, status_kehadiran, shift, alasan_terlambat, status_alasan) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([$user_id, $tgl, $jam, $status, $shift, $alasan, $status_alasan]);

    header("Location: $f/dashboard_$f.php");
    exit;
}
elseif ($action === 'absen_masuk_spv' && $role === 'supervisor') {
    $set    = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
    $shift  = (int)($_POST['shift'] ?? 1);
    $alasan = trim($_POST['alasan'] ?? '');

    $batas = ($shift === 1) ? $set['s1_batas'] : $set['s2_batas'];

    $status        = 'Tepat Waktu';
    $status_alasan = 'none';

    if (isTerlambat($jam, $batas, $shift, $set['s1_mulai'])) {
        $status        = 'Terlambat';
        $status_alasan = 'pending';
        $pdo->prepare("UPDATE users SET credit_score = credit_score - 5 WHERE id = ?")
            ->execute([$user_id]);
    }

    $pdo->prepare("INSERT INTO attendance (user_id, tanggal, waktu_masuk, status_kehadiran, shift, alasan_terlambat, status_alasan) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([$user_id, $tgl, $jam, $status, $shift, $alasan, $status_alasan]);

    header("Location: $f/dashboard_$f.php");
    exit;
}
// ============================================================
// AJUKAN SAKIT / IZIN
// ============================================================
elseif ($action === 'ajukan_izin_sakit') {
    $jenis  = $_POST['jenis'] ?? ''; 
    $alasan = trim($_POST['alasan'] ?? '');
    $shift  = (int)($_POST['shift'] ?? 1);

    if (in_array($jenis, ['Sakit', 'Izin'])) {
        // Semua role (Karyawan, Kasir, SPV) dipotong skor 5
        $pdo->prepare("UPDATE users SET credit_score = credit_score - 5 WHERE id = ?")->execute([$user_id]);
        
        // waktu_keluar disamakan dengan waktu_masuk agar tidak dianggap "Sedang Bekerja"
        $pdo->prepare("INSERT INTO attendance (user_id, tanggal, waktu_masuk, waktu_keluar, status_kehadiran, shift, alasan_terlambat, status_alasan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$user_id, $tgl, $jam, $jam, $jenis, $shift, $alasan, 'pending']);
    }

    header("Location: $f/dashboard_$f.php");
    exit;
}
elseif ($action === 'absen_keluar') {
    $pdo->prepare("UPDATE attendance SET waktu_keluar = ? WHERE user_id = ? AND tanggal = ? AND waktu_keluar IS NULL")
        ->execute([$jam, $user_id, $tgl]);
    header("Location: $f/dashboard_$f.php");
    exit;
}
elseif ($action === 'hapus_absen' && $role === 'supervisor') {
    $id_absen = (int)($_GET['id'] ?? 0);
    if ($id_absen > 0) {
        $row = $pdo->prepare("SELECT * FROM attendance WHERE id = ?");
        $row->execute([$id_absen]);
        $absenRow = $row->fetch();

        if ($absenRow) {
            // Kembalikan skor jika masih pending
            if (in_array($absenRow['status_kehadiran'], ['Terlambat', 'Sakit', 'Izin']) && $absenRow['status_alasan'] === 'pending') {
                $pdo->prepare("UPDATE users SET credit_score = credit_score + 5 WHERE id = ?")
                    ->execute([$absenRow['user_id']]);
            }
            $pdo->prepare("DELETE FROM attendance WHERE id = ?")->execute([$id_absen]);
        }
    }
    header("Location: $f/dashboard_$f.php?deleted=1");
    exit;
}
elseif ($action === 'approve_alasan' && $role === 'supervisor') {
    $id_absen   = (int)($_GET['id'] ?? 0);
    $new_status = $_GET['status'] ?? '';

    if (in_array($new_status, ['approved', 'rejected']) && $id_absen > 0) {
        $stmtChk = $pdo->prepare("
            SELECT a.user_id, u.role
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE a.id = ?
        ");
        $stmtChk->execute([$id_absen]);
        $target = $stmtChk->fetch();

        if (!$target || in_array($target['role'], ['supervisor', 'owner'])) {
            header("Location: $f/approval_$f.php");
            exit;
        }

        $uid = $target['user_id'];
        $pdo->prepare("UPDATE attendance SET status_alasan = ? WHERE id = ?")->execute([$new_status, $id_absen]);

        if ($new_status === 'approved') {
            $pdo->prepare("UPDATE users SET credit_score = credit_score + 5 WHERE id = ?")->execute([$uid]);
        }
    }
    header("Location: $f/approval_$f.php?notif=$new_status");
    exit;
}
elseif ($action === 'approve_spv' && $role === 'owner') {
    $id_absen   = (int)($_GET['id'] ?? 0);
    $new_status = $_GET['status'] ?? '';

    if (in_array($new_status, ['approved', 'rejected']) && $id_absen > 0) {
        $uid = $pdo->query("SELECT user_id FROM attendance WHERE id = $id_absen")->fetchColumn();
        $pdo->prepare("UPDATE attendance SET status_alasan = ? WHERE id = ?")->execute([$new_status, $id_absen]);
        if ($new_status === 'approved') {
            $pdo->query("UPDATE users SET credit_score = credit_score + 5 WHERE id = $uid");
        }
    }
    header("Location: $f/pelanggaran_$f.php?notif=$new_status");
    exit;
}
elseif ($action === 'add_branch' && ($role === 'supervisor' || $role === 'owner')) {
    $pdo->prepare("INSERT INTO branches (nama_cabang) VALUES (?)")->execute([$_GET['nama']]);
    header("Location: $f/riwayat_$f.php"); exit;
}
elseif ($action === 'edit_branch' && ($role === 'supervisor' || $role === 'owner')) {
    $pdo->prepare("UPDATE branches SET nama_cabang = ? WHERE id = ?")->execute([$_GET['nama'], $_GET['id']]);
    header("Location: $f/riwayat_$f.php"); exit;
}
elseif ($action === 'del_cabang' && ($role === 'supervisor' || $role === 'owner')) {
    $pdo->prepare("DELETE FROM branches WHERE id = ?")->execute([$_GET['id']]);
    header("Location: $f/riwayat_$f.php"); exit;
}
elseif ($action === 'save_user' && ($role === 'supervisor' || $role === 'owner')) {
    $pass = password_hash($_POST['pass'], PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, branch_id, credit_score) VALUES (?, ?, ?, ?, ?, 100)")
        ->execute([$_POST['user'], $pass, $_POST['nama'], $_POST['role'], $_GET['branch_id']]);
    header("Location: $f/riwayat_$f.php"); exit;
}
elseif ($action === 'edit_user' && ($role === 'supervisor' || $role === 'owner')) {
    if (!empty($_GET['pass'])) {
        $hash = password_hash($_GET['pass'], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET nama_lengkap = ?, credit_score = ?, password = ? WHERE id = ?")
            ->execute([$_GET['nama'], $_GET['skor'], $hash, $_GET['id']]);
    } else {
        $pdo->prepare("UPDATE users SET nama_lengkap = ?, credit_score = ? WHERE id = ?")
            ->execute([$_GET['nama'], $_GET['skor'], $_GET['id']]);
    }
    header("Location: $f/riwayat_$f.php"); exit;
}
elseif ($action === 'del_staf' && ($role === 'supervisor' || $role === 'owner')) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['id']]);
    header("Location: $f/riwayat_$f.php"); exit;
}
elseif ($action === 'update_jam' && $role === 'owner') {
    $pdo->prepare("UPDATE settings SET s1_mulai = ?, s1_batas = ?, s2_mulai = ?, s2_batas = ? WHERE id = 1")
        ->execute([$_POST['s1_mulai'], $_POST['s1_batas'], $_POST['s2_mulai'], $_POST['s2_batas']]);
    header("Location: $f/pengaturan_$f.php?success=1"); exit;
}
elseif ($action === 'update_password') {
    $pass_lama    = $_POST['password_lama']    ?? '';
    $pass_baru    = $_POST['password_baru']    ?? '';
    $pass_konfirm = $_POST['password_konfirm'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_hash = $stmt->fetchColumn();

    $valid_lama = password_verify($pass_lama, $current_hash) || $pass_lama === $current_hash;
    if (!$valid_lama) {
        header("Location: $f/profil_$f.php?error=" . urlencode('Password lama yang Anda masukkan salah!')); exit;
    }
    if ($pass_baru !== $pass_konfirm) {
        header("Location: $f/profil_$f.php?error=" . urlencode('Konfirmasi password baru tidak cocok!')); exit;
    }
    if (strlen($pass_baru) < 6) {
        header("Location: $f/profil_$f.php?error=" . urlencode('Password baru minimal 6 karakter!')); exit;
    }

    $new_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $user_id]);
    header("Location: $f/profil_$f.php?success=1"); exit;
}
elseif ($action === 'logout') {
    session_destroy();
    header("Location: login.php"); exit;
}
?>