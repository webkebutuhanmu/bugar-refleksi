<?php
/**
 * ajax_absensi.php - UPDATE v3 (Sinkron dengan Pengaturan Owner)
 * * UPDATE:
 * - Shift dan batas waktu absen sekarang otomatis membaca tabel `settings`
 * - Jika absen di luar jam shift → status terlambat, WAJIB isi alasan
 * - Data shift & status terlihat di halaman kasir
 * - Terapis hanya bisa absen di cabangnya sendiri
 * - Fitur Absen Keluar (Pulang)
 */

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

// Panggil session_start agar $_SESSION['user_id'] dan role terbaca
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action              = trim($_POST['action']           ?? $_GET['action']           ?? '');
$post_barcode        = trim($_POST['barcode']          ?? '');
$post_absen_id       = (int)($_POST['absen_id']        ?? 0);
$post_alasan         = trim($_POST['alasan_terlambat'] ?? '');

require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

$role          = $_SESSION['role']          ?? null;
$user_id       = $_SESSION['user_id']       ?? null;
$active_branch = $_SESSION['active_branch'] ?? null;
session_write_close();

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ── Ambil Pengaturan dari Database ────────────────────────────────────────────
try {
    $s = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
} catch (Exception $e) { 
    $s = null; 
}

$jamMulai       = $s['jam_mulai_hari']    ?? '08:00:00';
$pagi_start     = $s['shift_pagi_start']  ?? '08:00:00';
$pagi_end       = $s['shift_pagi_end']    ?? '10:00:00';
$malam_start    = $s['shift_malam_start'] ?? '16:00:00';
$malam_end      = $s['shift_malam_end']   ?? '18:00:00';

$jamSekarang   = date('H:i:s');
$tanggalBisnis = ($jamSekarang < $jamMulai) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

/**
 * ★ DETEKSI SHIFT & STATUS KEHADIRAN (SINKRON DENGAN PENGATURAN OWNER)
 */
function detectShiftStatus($pagi_start, $pagi_end, $malam_start, $malam_end)
{
    $nowTime = date('H:i:s');

    // Jika waktu saat ini lebih kecil dari mulainya shift malam, maka dianggap Shift Pagi
    if ($nowTime < $malam_start) {
        $shift = 'pagi';
        $tepat = ($nowTime >= $pagi_start && $nowTime <= $pagi_end);
        $label = 'Pagi (' . date('H:i', strtotime($pagi_start)) . ' - ' . date('H:i', strtotime($pagi_end)) . ')';
    } else {
        // Selebihnya masuk Shift Malam
        $shift = 'malam';
        $tepat = ($nowTime >= $malam_start && $nowTime <= $malam_end);
        $label = 'Malam (' . date('H:i', strtotime($malam_start)) . ' - ' . date('H:i', strtotime($malam_end)) . ')';
    }

    return [
        'shift_type'       => $shift,
        'status_kehadiran' => $tepat ? 'tepat_waktu' : 'terlambat',
        'is_terlambat'     => !$tepat,
        'jam_absen'        => date('H:i'),
        'label_shift'      => $label
    ];
}

function extractBarcode($raw)
{
    $raw = trim($raw ?? '');
    if ($raw === '') return '';
    $json = json_decode($raw, true);
    if (is_array($json) && !empty($json['barcode'])) return trim($json['barcode']);
    if (strpos($raw, '?') !== false || strpos($raw, 'http') === 0) {
        $qs = parse_url($raw, PHP_URL_QUERY) ?? '';
        parse_str($qs, $p);
        if (!empty($p['barcode'])) return trim($p['barcode']);
        if (!empty($p['id']))      return trim($p['id']);
    }
    return $raw;
}

try {

    switch ($action) {

        // ═══════════════════════════════════════════════════════════════════════
        case 'buka_absen':
            if ($role !== 'kasir') { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
            $branch_id = (int)$active_branch;
            if (!$branch_id) { echo json_encode(['success'=>false,'message'=>'Branch tidak ditemukan']); exit; }

            $st = $pdo->prepare("SELECT id FROM attendance_sessions WHERE branch_id=? AND tanggal=? AND status='open'");
            $st->execute([$branch_id, $tanggalBisnis]);
            if ($st->fetch()) { echo json_encode(['success'=>false,'message'=>'Absensi sudah dibuka untuk hari ini']); exit; }

            $pdo->prepare("INSERT INTO attendance_sessions (branch_id,tanggal,dibuka_oleh,status,waktu_buka) VALUES (?,?,?,'open',NOW())")
                ->execute([$branch_id, $tanggalBisnis, $user_id]);
            echo json_encode(['success'=>true,'message'=>'Absensi berhasil dibuka!']);
            break;

        // ═══════════════════════════════════════════════════════════════════════
        case 'tutup_absen':
            if ($role !== 'kasir') { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
            $branch_id = (int)$active_branch;

            $st = $pdo->prepare("UPDATE attendance_sessions SET status='closed',waktu_tutup=NOW() WHERE branch_id=? AND tanggal=? AND status='open'");
            $st->execute([$branch_id, $tanggalBisnis]);
            echo json_encode($st->rowCount() > 0
                ? ['success'=>true, 'message'=>'Absensi berhasil ditutup']
                : ['success'=>false,'message'=>'Tidak ada sesi absensi yang terbuka']);
            break;

        // ═══════════════════════════════════════════════════════════════════════
        case 'scan_absen':
            if ($role !== 'kasir') { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
            $branch_id = (int)$active_branch;
            $barcode   = extractBarcode($post_barcode);
            if ($barcode === '') { echo json_encode(['success'=>false,'message'=>'Barcode tidak boleh kosong']); exit; }

            $stT = $pdo->prepare("SELECT id,nama_lengkap,home_branch_id,foto_profil FROM users WHERE barcode_id=? AND role='terapis'");
            $stT->execute([$barcode]);
            $terapis = $stT->fetch();
            if (!$terapis) { echo json_encode(['success'=>false,'message'=>'Barcode tidak ditemukan atau bukan terapis']); exit; }

            // Validasi cabang
            $terapis_home_branch = (int)($terapis['home_branch_id'] ?? 0);
            if ($terapis_home_branch !== $branch_id) {
                $stBr1 = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id=?");
                $stBr1->execute([$terapis_home_branch]);
                $nmCbT = $stBr1->fetchColumn() ?: '?';
                $stBr2 = $pdo->prepare("SELECT nama_cabang FROM branches WHERE id=?");
                $stBr2->execute([$branch_id]);
                $nmCbK = $stBr2->fetchColumn() ?: '?';
                echo json_encode(['success'=>false,'message'=>$terapis['nama_lengkap'].' adalah terapis cabang "'.$nmCbT.'". Tidak dapat absen di cabang "'.$nmCbK.'".']);
                exit;
            }

            // Cek duplikat
            $stC = $pdo->prepare("SELECT id FROM terapis_attendance WHERE terapis_id=? AND tanggal=? AND branch_id=?");
            $stC->execute([$terapis['id'], $tanggalBisnis, $branch_id]);
            if ($stC->fetch()) { echo json_encode(['success'=>false,'message'=>$terapis['nama_lengkap'].' sudah absen hari ini']); exit; }

            // ★ DETEKSI SHIFT OTOMATIS BERDASARKAN DATABASE PENGATURAN OWNER
            $shiftInfo = detectShiftStatus($pagi_start, $pagi_end, $malam_start, $malam_end);

            // Jika terlambat DAN belum ada alasan → kembalikan flag terlambat
            if ($shiftInfo['is_terlambat'] && empty($post_alasan)) {
                echo json_encode([
                    'success'       => false,
                    'terlambat'     => true,
                    'shift_type'    => $shiftInfo['shift_type'],
                    'label_shift'   => $shiftInfo['label_shift'],
                    'jam_absen'     => $shiftInfo['jam_absen'],
                    'nama_terapis'  => $terapis['nama_lengkap'],
                    'barcode'       => $barcode,
                    'message'       => $terapis['nama_lengkap'] . ' terlambat! Shift ' . $shiftInfo['label_shift'] . '. Wajib isi alasan.'
                ]);
                exit;
            }

            // Giliran
            $stG = $pdo->prepare("SELECT COALESCE(MAX(giliran),0)+1 FROM terapis_attendance WHERE branch_id=? AND tanggal=?");
            $stG->execute([$branch_id, $tanggalBisnis]);
            $nextGiliran = (int)$stG->fetchColumn();

            $stS = $pdo->prepare("SELECT id FROM attendance_sessions WHERE branch_id=? AND tanggal=? AND status='open'");
            $stS->execute([$branch_id, $tanggalBisnis]);
            $sesi = $stS->fetch();

            $alasan = $shiftInfo['is_terlambat'] ? $post_alasan : null;
            $pdo->prepare(
                "INSERT INTO terapis_attendance (session_id,terapis_id,branch_id,tanggal,waktu_absen,giliran,metode_absen,shift_type,status_kehadiran,alasan_terlambat) VALUES (?,?,?,?,NOW(),?,'scan',?,?,?)"
            )->execute([
                $sesi ? $sesi['id'] : null, $terapis['id'], $branch_id, $tanggalBisnis,
                $nextGiliran, $shiftInfo['shift_type'], $shiftInfo['status_kehadiran'], $alasan
            ]);

            $sLabel = $shiftInfo['is_terlambat']
                ? ' (TERLAMBAT - Shift '.ucfirst($shiftInfo['shift_type']).')'
                : ' (Tepat Waktu - Shift '.ucfirst($shiftInfo['shift_type']).')';

            echo json_encode([
                'success' => true,
                'message' => $terapis['nama_lengkap'].' berhasil absen! Giliran ke-'.$nextGiliran.$sLabel,
                'data'    => [
                    'nama'=>$terapis['nama_lengkap'], 'giliran'=>$nextGiliran, 'waktu'=>date('H:i:s'),
                    'foto'=>$terapis['foto_profil'], 'shift_type'=>$shiftInfo['shift_type'],
                    'status_kehadiran'=>$shiftInfo['status_kehadiran']
                ]
            ]);
            break;

        // ═══════════════════════════════════════════════════════════════════════
        case 'manual_absen':
            if ($role !== 'terapis') {
                echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
            }

            $stB = $pdo->prepare("SELECT home_branch_id, nama_lengkap FROM users WHERE id=? AND role='terapis'");
            $stB->execute([(int)$user_id]);
            $tData = $stB->fetch();
            if (!$tData) { echo json_encode(['success'=>false,'message'=>'Data terapis tidak ditemukan']); exit; }
            if (empty($tData['home_branch_id'])) { echo json_encode(['success'=>false,'message'=>'Cabang belum diatur, hubungi admin']); exit; }

            $branch_id = (int)$tData['home_branch_id'];

            $stS = $pdo->prepare("SELECT id FROM attendance_sessions WHERE branch_id=? AND tanggal=? AND status='open'");
            $stS->execute([$branch_id, $tanggalBisnis]);
            $sesi = $stS->fetch();
            if (!$sesi) { echo json_encode(['success'=>false,'message'=>'Absensi belum dibuka oleh kasir. Silakan hubungi kasir.']); exit; }

            $stC = $pdo->prepare("SELECT id FROM terapis_attendance WHERE terapis_id=? AND tanggal=? AND branch_id=?");
            $stC->execute([(int)$user_id, $tanggalBisnis, $branch_id]);
            if ($stC->fetch()) { echo json_encode(['success'=>false,'message'=>'Kamu sudah absen hari ini']); exit; }

            // ★ DETEKSI SHIFT OTOMATIS BERDASARKAN DATABASE PENGATURAN OWNER
            $shiftInfo = detectShiftStatus($pagi_start, $pagi_end, $malam_start, $malam_end);

            // Jika terlambat DAN belum ada alasan → kembalikan flag, jangan simpan
            if ($shiftInfo['is_terlambat'] && empty($post_alasan)) {
                echo json_encode([
                    'success'     => false,
                    'terlambat'   => true,
                    'shift_type'  => $shiftInfo['shift_type'],
                    'label_shift' => $shiftInfo['label_shift'],
                    'jam_absen'   => $shiftInfo['jam_absen'],
                    'message'     => 'Kamu terlambat! Shift ' . $shiftInfo['label_shift'] . '. Wajib isi alasan keterlambatan.'
                ]);
                exit;
            }

            $stG = $pdo->prepare("SELECT COALESCE(MAX(giliran),0)+1 FROM terapis_attendance WHERE branch_id=? AND tanggal=?");
            $stG->execute([$branch_id, $tanggalBisnis]);
            $nextGiliran = (int)$stG->fetchColumn();

            $alasan = $shiftInfo['is_terlambat'] ? $post_alasan : null;
            $pdo->prepare(
                "INSERT INTO terapis_attendance (session_id,terapis_id,branch_id,tanggal,waktu_absen,giliran,metode_absen,shift_type,status_kehadiran,alasan_terlambat) VALUES (?,?,?,?,NOW(),?,'manual',?,?,?)"
            )->execute([
                $sesi['id'], (int)$user_id, $branch_id, $tanggalBisnis,
                $nextGiliran, $shiftInfo['shift_type'], $shiftInfo['status_kehadiran'], $alasan
            ]);

            $sLabel = $shiftInfo['is_terlambat']
                ? ' (Terlambat - Shift '.ucfirst($shiftInfo['shift_type']).')'
                : ' (Tepat Waktu - Shift '.ucfirst($shiftInfo['shift_type']).')';

            echo json_encode([
                'success'          => true,
                'message'          => 'Absen berhasil! Giliran kamu ke-'.$nextGiliran.$sLabel,
                'giliran'          => $nextGiliran,
                'waktu'            => date('H:i:s'),
                'shift_type'       => $shiftInfo['shift_type'],
                'status_kehadiran' => $shiftInfo['status_kehadiran']
            ]);
            break;

        // ═══════════════════════════════════════════════════════════════════════
        case 'cek_status':
            $branch_id = null;
            if ($role === 'kasir') {
                $branch_id = (int)$active_branch;
            } elseif ($role === 'terapis') {
                $stB = $pdo->prepare("SELECT home_branch_id FROM users WHERE id=?");
                $stB->execute([(int)$user_id]);
                $branch_id = (int)$stB->fetchColumn();
            }
            if (!$branch_id) { echo json_encode(['success'=>false,'message'=>'Branch tidak ditemukan']); exit; }

            $stS = $pdo->prepare("SELECT * FROM attendance_sessions WHERE branch_id=? AND tanggal=? ORDER BY id DESC LIMIT 1");
            $stS->execute([$branch_id, $tanggalBisnis]);
            $sesi     = $stS->fetch(PDO::FETCH_ASSOC);
            $sesiOpen = ($sesi && $sesi['status'] === 'open');

            // ★ Include shift data, penambahan ta.waktu_keluar 
            $stL = $pdo->prepare(
                "SELECT ta.id, ta.terapis_id, ta.giliran, ta.waktu_absen, ta.waktu_keluar, ta.metode_absen,
                        ta.shift_type, ta.status_kehadiran, ta.alasan_terlambat,
                        u.nama_lengkap, u.foto_profil
                 FROM terapis_attendance ta
                 JOIN users u ON ta.terapis_id = u.id
                 WHERE ta.branch_id=? AND ta.tanggal=?
                 ORDER BY ta.giliran ASC"
            );
            $stL->execute([$branch_id, $tanggalBisnis]);
            $absenList = $stL->fetchAll(PDO::FETCH_ASSOC);

            $stT = $pdo->prepare("SELECT COUNT(*) FROM users WHERE home_branch_id=? AND role='terapis'");
            $stT->execute([$branch_id]);
            $totalTerapis = (int)$stT->fetchColumn();

            $sudahAbsen  = false;
            $giliranSaya = null;
            if ($role === 'terapis') {
                foreach ($absenList as $a) {
                    if ((int)$a['terapis_id'] === (int)$user_id) {
                        $sudahAbsen  = true;
                        $giliranSaya = (int)$a['giliran'];
                        break;
                    }
                }
            }

            echo json_encode([
                'success'       => true,
                'sesi_open'     => $sesiOpen,
                'sesi'          => $sesi ? ['id'=>$sesi['id'],'waktu_buka'=>$sesi['waktu_buka'],'waktu_tutup'=>$sesi['waktu_tutup'],'status'=>$sesi['status']] : null,
                'absen_list'    => $absenList,
                'total_terapis' => $totalTerapis,
                'total_hadir'   => count($absenList),
                'tanggal'       => $tanggalBisnis,
                'sudah_absen'   => $sudahAbsen,
                'giliran_saya'  => $giliranSaya
            ]);
            break;

        // ═══════════════════════════════════════════════════════════════════════
        case 'hapus_absen':
            if ($role !== 'kasir') { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
            $branch_id = (int)$active_branch;
            if (!$post_absen_id) { echo json_encode(['success'=>false,'message'=>'ID absensi tidak valid']); exit; }

            $stD = $pdo->prepare("DELETE FROM terapis_attendance WHERE id=? AND branch_id=? AND tanggal=?");
            $stD->execute([$post_absen_id, $branch_id, $tanggalBisnis]);

            if ($stD->rowCount() > 0) {
                $stR = $pdo->prepare("SELECT id FROM terapis_attendance WHERE branch_id=? AND tanggal=? ORDER BY waktu_absen ASC");
                $stR->execute([$branch_id, $tanggalBisnis]);
                $newG = 1;
                foreach ($stR->fetchAll() as $ab) {
                    $pdo->prepare("UPDATE terapis_attendance SET giliran=? WHERE id=?")->execute([$newG++, $ab['id']]);
                }
                echo json_encode(['success'=>true,'message'=>'Absensi berhasil dihapus']);
            } else {
                echo json_encode(['success'=>false,'message'=>'Absensi tidak ditemukan']);
            }
            break;

        // ═══════════════════════════════════════════════════════════════════════
        case 'absen_keluar':
            if (!$user_id || $role !== 'terapis') {
                echo json_encode(['success' => false, 'message' => 'Sesi tidak valid']);
                exit;
            }

            // [FIX] Perbaikan sintaks yang error di sini
            $absen_id = (int)($_POST['absen_id'] ?? 0);

            // Update waktu keluar
            $st = $pdo->prepare("UPDATE terapis_attendance SET waktu_keluar = NOW() WHERE id = ? AND terapis_id = ?");
            if ($st->execute([$absen_id, $user_id])) {
                echo json_encode(['success' => true, 'message' => 'Berhasil absen pulang. Terima kasih atas kerja kerasnya!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal memproses absen pulang']);
            }
            break;

        default:
            echo json_encode(['success'=>false,'message'=>'Action tidak dikenali: '.$action]);
    }

} catch (PDOException $e) {
    $msg = (strpos($e->getMessage(), "Unknown column") !== false)
        ? 'Kolom shift belum ada. Jalankan migration_shift_absensi.sql dulu.'
        : 'Database error: '.$e->getMessage();
    echo json_encode(['success'=>false,'message'=>$msg]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}