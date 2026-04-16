<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

$role     = $_SESSION['role'] ?? null;
$branchId = $_SESSION['user_branch_id'] ?? null;

session_write_close();
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($role !== 'leader' || !$branchId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'tambah_bed') {
    $nomorBed = trim($_POST['nomor_bed'] ?? '');
    $tipe     = $_POST['tipe'] ?? 'Regular';

    $allowedTipe = ['Regular','Atas','Bawah','Laki-laki','Perempuan'];
    if (!$nomorBed) {
        echo json_encode(['success' => false, 'message' => 'Nomor bed tidak boleh kosong']);
        exit;
    }
    if (strlen($nomorBed) > 10) {
        echo json_encode(['success' => false, 'message' => 'Nomor bed maksimal 10 karakter']);
        exit;
    }
    if (!in_array($tipe, $allowedTipe)) {
        $tipe = 'Regular';
    }

    // Cek duplikat dalam cabang ini
    $cek = $pdo->prepare("SELECT COUNT(*) FROM beds WHERE branch_id = ? AND nomor_bed = ?");
    $cek->execute([$branchId, $nomorBed]);
    if ($cek->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Nomor bed "' . $nomorBed . '" sudah ada di cabang ini']);
        exit;
    }

    $ins = $pdo->prepare("INSERT INTO beds (branch_id, nomor_bed, tipe, status) VALUES (?, ?, ?, 'kosong')");
    if ($ins->execute([$branchId, $nomorBed, $tipe])) {
        echo json_encode(['success' => true, 'message' => 'Bed "' . $nomorBed . '" berhasil ditambahkan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan bed ke database']);
    }
    exit;
}

if ($action === 'hapus_bed') {
    $bedId = (int)($_POST['bed_id'] ?? 0);
    if (!$bedId) {
        echo json_encode(['success' => false, 'message' => 'ID bed tidak valid']);
        exit;
    }

    // Pastikan bed milik cabang ini
    $cekBranch = $pdo->prepare("SELECT nomor_bed FROM beds WHERE id = ? AND branch_id = ?");
    $cekBranch->execute([$bedId, $branchId]);
    $bedRow = $cekBranch->fetch();
    if (!$bedRow) {
        echo json_encode(['success' => false, 'message' => 'Bed tidak ditemukan']);
        exit;
    }

    // Cek apakah bed sedang digunakan
    $cekPakai = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE bed_id = ? AND status IN ('proses','menunggu_pembayaran','menunggu_approval')");
    $cekPakai->execute([$bedId]);
    if ($cekPakai->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Bed "' . $bedRow['nomor_bed'] . '" sedang digunakan, tidak bisa dihapus']);
        exit;
    }

    $del = $pdo->prepare("DELETE FROM beds WHERE id = ? AND branch_id = ?");
    if ($del->execute([$bedId, $branchId])) {
        echo json_encode(['success' => true, 'message' => 'Bed "' . $bedRow['nomor_bed'] . '" berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus bed']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali: ' . $action]);