<?php
/**
 * ajax_izin_sakit.php
 * Handle izin/sakit requests from terapis
 * Handle approval/rejection from leader
 */
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

$role    = $_SESSION['role']    ?? null;
$user_id = $_SESSION['user_id'] ?? null;

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

// Tanggal bisnis
$s = $pdo->query("SELECT jam_mulai_hari FROM settings WHERE id=1")->fetch();
$jamMulai      = $s['jam_mulai_hari'] ?? '08:00:00';
$tanggalBisnis = (date('H:i:s') < $jamMulai) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

// Buat tabel jika belum ada
$pdo->exec("CREATE TABLE IF NOT EXISTS `terapis_izin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `terapis_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `tanggal` DATE NOT NULL,
    `jenis` ENUM('izin','sakit') NOT NULL,
    `keterangan` TEXT NOT NULL,
    `status` ENUM('pending','disetujui','ditolak') NOT NULL DEFAULT 'pending',
    `responded_by` INT DEFAULT NULL,
    `responded_at` DATETIME DEFAULT NULL,
    `catatan_leader` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_terapis` (`terapis_id`),
    INDEX `idx_branch` (`branch_id`),
    INDEX `idx_tanggal` (`tanggal`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");

try {
    switch ($action) {

        // ═══════════════════════════════════════════════════
        // TERAPIS: Kirim permintaan izin/sakit
        // ═══════════════════════════════════════════════════
        case 'kirim_izin':
            if ($role !== 'terapis') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $jenis      = trim($_POST['jenis'] ?? '');
            $keterangan = trim($_POST['keterangan'] ?? '');
            $tanggal    = trim($_POST['tanggal'] ?? $tanggalBisnis);

            if (!in_array($jenis, ['izin', 'sakit'])) {
                echo json_encode(['success' => false, 'message' => 'Jenis harus izin atau sakit']);
                exit;
            }
            if (strlen($keterangan) < 5) {
                echo json_encode(['success' => false, 'message' => 'Keterangan wajib diisi minimal 5 karakter']);
                exit;
            }

            // Ambil branch terapis
            $stB = $pdo->prepare("SELECT home_branch_id, nama_lengkap FROM users WHERE id = ? AND role = 'terapis'");
            $stB->execute([(int)$user_id]);
            $tData = $stB->fetch();
            if (!$tData || !$tData['home_branch_id']) {
                echo json_encode(['success' => false, 'message' => 'Data terapis tidak ditemukan']);
                exit;
            }
            $branch_id = (int)$tData['home_branch_id'];

            // Cek sudah absen hari ini?
            $stC = $pdo->prepare("SELECT id FROM terapis_attendance WHERE terapis_id = ? AND tanggal = ? AND branch_id = ?");
            $stC->execute([(int)$user_id, $tanggal, $branch_id]);
            if ($stC->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Kamu sudah absen pada tanggal ini. Tidak bisa izin/sakit.']);
                exit;
            }

            // Cek sudah ada request pending?
            $stP = $pdo->prepare("SELECT id FROM terapis_izin WHERE terapis_id = ? AND tanggal = ? AND status = 'pending'");
            $stP->execute([(int)$user_id, $tanggal]);
            if ($stP->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Sudah ada permintaan yang belum direspon untuk tanggal ini']);
                exit;
            }

            // Cek sudah ada yang disetujui?
            $stA = $pdo->prepare("SELECT id FROM terapis_izin WHERE terapis_id = ? AND tanggal = ? AND status = 'disetujui'");
            $stA->execute([(int)$user_id, $tanggal]);
            if ($stA->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Sudah ada izin yang disetujui untuk tanggal ini']);
                exit;
            }

            $pdo->prepare(
                "INSERT INTO terapis_izin (terapis_id, branch_id, tanggal, jenis, keterangan) VALUES (?, ?, ?, ?, ?)"
            )->execute([(int)$user_id, $branch_id, $tanggal, $jenis, $keterangan]);

            $jenisLabel = $jenis === 'sakit' ? 'Sakit' : 'Izin';
            echo json_encode([
                'success' => true,
                'message' => "Permintaan $jenisLabel berhasil dikirim! Menunggu persetujuan Leader."
            ]);
            break;

        // ═══════════════════════════════════════════════════
        // LEADER: Setujui izin/sakit
        // ═══════════════════════════════════════════════════
        case 'approve_izin':
            if ($role !== 'leader') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $izin_id = (int)($_POST['izin_id'] ?? 0);
            $catatan = trim($_POST['catatan'] ?? '');

            if (!$izin_id) {
                echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
                exit;
            }

            $branchId = $_SESSION['user_branch_id'] ?? 0;

            $stI = $pdo->prepare("SELECT * FROM terapis_izin WHERE id = ? AND branch_id = ? AND status = 'pending'");
            $stI->execute([$izin_id, $branchId]);
            $izinData = $stI->fetch();

            if (!$izinData) {
                echo json_encode(['success' => false, 'message' => 'Data izin tidak ditemukan atau sudah direspon']);
                exit;
            }

            $pdo->prepare(
                "UPDATE terapis_izin SET status = 'disetujui', responded_by = ?, responded_at = NOW(), catatan_leader = ? WHERE id = ?"
            )->execute([(int)$user_id, $catatan ?: null, $izin_id]);

            echo json_encode(['success' => true, 'message' => 'Izin/Sakit berhasil disetujui']);
            break;

        // ═══════════════════════════════════════════════════
        // LEADER: Tolak izin/sakit
        // ═══════════════════════════════════════════════════
        case 'tolak_izin':
            if ($role !== 'leader') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $izin_id = (int)($_POST['izin_id'] ?? 0);
            $catatan = trim($_POST['catatan'] ?? '');

            if (!$izin_id) {
                echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
                exit;
            }

            $branchId = $_SESSION['user_branch_id'] ?? 0;

            $stI = $pdo->prepare("SELECT * FROM terapis_izin WHERE id = ? AND branch_id = ? AND status = 'pending'");
            $stI->execute([$izin_id, $branchId]);
            $izinData = $stI->fetch();

            if (!$izinData) {
                echo json_encode(['success' => false, 'message' => 'Data izin tidak ditemukan atau sudah direspon']);
                exit;
            }

            $pdo->prepare(
                "UPDATE terapis_izin SET status = 'ditolak', responded_by = ?, responded_at = NOW(), catatan_leader = ? WHERE id = ?"
            )->execute([(int)$user_id, $catatan ?: null, $izin_id]);

            echo json_encode(['success' => true, 'message' => 'Izin/Sakit ditolak. Terapis wajib hadir.']);
            break;

        // ═══════════════════════════════════════════════════
        // GET: Ambil daftar izin pending untuk leader
        // ═══════════════════════════════════════════════════
        case 'get_pending':
            if ($role !== 'leader') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $branchId = $_SESSION['user_branch_id'] ?? 0;

            $stL = $pdo->prepare(
                "SELECT ti.*, u.nama_lengkap, u.foto_profil 
                 FROM terapis_izin ti 
                 JOIN users u ON ti.terapis_id = u.id 
                 WHERE ti.branch_id = ? AND ti.status = 'pending'
                 ORDER BY ti.created_at DESC"
            );
            $stL->execute([$branchId]);
            $list = $stL->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $list]);
            break;

        // ═══════════════════════════════════════════════════
        // TERAPIS: Cek status izin saya
        // ═══════════════════════════════════════════════════
        case 'cek_izin_saya':
            if ($role !== 'terapis') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $tanggal = trim($_GET['tanggal'] ?? $tanggalBisnis);

            $stM = $pdo->prepare(
                "SELECT * FROM terapis_izin WHERE terapis_id = ? AND tanggal = ? ORDER BY created_at DESC LIMIT 1"
            );
            $stM->execute([(int)$user_id, $tanggal]);
            $myIzin = $stM->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $myIzin ?: null]);
            break;

        // ═══════════════════════════════════════════════════
        // KASIR: Get izin/sakit list hari ini (untuk tabel absensi kasir)
        // ═══════════════════════════════════════════════════
        case 'get_izin_today':
            if (!in_array($role, ['kasir', 'leader', 'admin', 'owner'])) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $branchIdK = $_SESSION['active_branch'] ?? ($_SESSION['user_branch_id'] ?? 0);
            $tanggalK  = trim($_GET['tanggal'] ?? $tanggalBisnis);

            $stIzToday = $pdo->prepare(
                "SELECT ti.id, ti.terapis_id, ti.jenis, ti.keterangan, ti.status, 
                        ti.catatan_leader, ti.created_at,
                        u.nama_lengkap, u.foto_profil 
                 FROM terapis_izin ti
                 JOIN users u ON ti.terapis_id = u.id
                 WHERE ti.branch_id = ? AND ti.tanggal = ?
                 ORDER BY ti.status ASC, ti.created_at DESC"
            );
            $stIzToday->execute([$branchIdK, $tanggalK]);
            $izList = $stIzToday->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'list' => $izList]);
            break;

        // ═══════════════════════════════════════════════════
        // LEADER: Cek alpha dan buat pelanggaran otomatis
        // Terapis yang izin ditolak & tidak absen = alpha
        // ═══════════════════════════════════════════════════
        case 'cek_alpha':
            if ($role !== 'leader') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $branchId = $_SESSION['user_branch_id'] ?? 0;
            $tanggal  = trim($_POST['tanggal'] ?? $tanggalBisnis);

            // Cari terapis yang izinnya ditolak DAN tidak absen hari itu
            $stAlpha = $pdo->prepare(
                "SELECT ti.*, u.nama_lengkap 
                 FROM terapis_izin ti 
                 JOIN users u ON ti.terapis_id = u.id 
                 WHERE ti.branch_id = ? 
                   AND ti.tanggal = ? 
                   AND ti.status = 'ditolak'
                   AND ti.terapis_id NOT IN (
                       SELECT terapis_id FROM terapis_attendance 
                       WHERE branch_id = ? AND tanggal = ?
                   )
                   AND ti.terapis_id NOT IN (
                       SELECT p.terapis_id FROM pelanggaran p 
                       WHERE p.branch_id = ? AND p.tanggal = ? AND p.kategori = 'alpha'
                   )"
            );
            $stAlpha->execute([$branchId, $tanggal, $branchId, $tanggal, $branchId, $tanggal]);
            $alphaList = $stAlpha->fetchAll(PDO::FETCH_ASSOC);

            $count = 0;
            foreach ($alphaList as $al) {
                $judul    = "Alpha - Tidak Hadir Tanpa Keterangan";
                $deskripsi = "Terapis " . $al['nama_lengkap'] . " tidak hadir pada tanggal " . $al['tanggal'] 
                           . ". Izin/sakit telah ditolak namun tetap tidak absen.";

                // Cek apakah pelanggaran sudah ada
                $stE = $pdo->prepare("SELECT id FROM pelanggaran WHERE terapis_id = ? AND tanggal = ? AND kategori = 'alpha' AND branch_id = ?");
                $stE->execute([$al['terapis_id'], $al['tanggal'], $branchId]);
                if (!$stE->fetch()) {
                    $pdo->prepare(
                        "INSERT INTO pelanggaran (terapis_id, branch_id, kategori, judul, deskripsi, tanggal, status, created_by) 
                         VALUES (?, ?, 'alpha', ?, ?, ?, 'aktif', ?)"
                    )->execute([$al['terapis_id'], $branchId, $judul, $deskripsi, $al['tanggal'], (int)$user_id]);
                    $count++;
                }
            }

            echo json_encode([
                'success' => true, 
                'message' => $count > 0 ? "$count pelanggaran alpha berhasil dicatat" : "Tidak ada pelanggaran alpha baru",
                'count'   => $count
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenali: ' . $action]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}