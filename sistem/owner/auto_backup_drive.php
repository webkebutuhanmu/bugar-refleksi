<?php
// File: owner/auto_backup_drive.php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'owner') { die("Access Denied"); }

// 1. Cek apakah sudah backup hari ini
$setting = $pdo->query("SELECT last_auto_backup FROM settings WHERE id=1")->fetch();
$hari_ini = date('Y-m-d');

if ($setting && $setting['last_auto_backup'] === $hari_ini) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'skipped', 'message' => 'Sudah backup hari ini']);
    exit;
}

try {
    // 2. Generate Konten SQL Database
    $tables = [];
    $query = $pdo->query('SHOW TABLES');
    while ($row = $query->fetch(PDO::FETCH_NUM)) { $tables[] = $row[0]; }

    $sqlContent = "-- Auto Backup Bugar Refleksi\n";
    $sqlContent .= "-- Waktu: " . date('Y-m-d H:i:s') . "\n\n";
    $sqlContent .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $q = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $sqlContent .= "DROP TABLE IF EXISTS `$table`;\n" . $q[1] . ";\n\n";
        $data = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
        foreach ($data as $row) {
            $values = array_map(function($v) use ($pdo) { return is_null($v) ? 'NULL' : $pdo->quote($v); }, $row);
            $sqlContent .= "INSERT INTO `$table` VALUES(" . implode(',', $values) . ");\n";
        }
        $sqlContent .= "\n";
    }
    $sqlContent .= "SET FOREIGN_KEY_CHECKS=1;";

    // ========================================================
    // 3. MASUKKAN KODE RAHASIA ANDA DI SINI
    // ========================================================
    $clientId     = '803807591707-e4rnh3e1urep5s692vi6289mvprt0av6.apps.googleusercontent.com';
    $clientSecret = 'YOUR_CLIENT_SECRET'; // Ganti dengan client secret Anda
    $refreshToken = 'YOUR_REFRESH_TOKEN'; // Ganti dengan refresh token Anda
    $folderId     = '1Kxg-zdD5MNYS6MJGNWswoStVCwmAoxEW'; // Folder ID Anda
    $fileName     = 'AutoBackup_' . date('d-m-Y_H-i') . '.sql';

    // 4. Dapatkan Access Token Baru dari Google
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type'    => 'refresh_token'
    ]));
    $tokenRes = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($tokenRes['access_token'])) {
        throw new Exception("Gagal mendapat token akses dari Google.");
    }
    $accessToken = $tokenRes['access_token'];

    // 5. Upload File Backup Hari Ini ke Google Drive
    $metadata = json_encode(['name' => $fileName, 'parents' => [$folderId]]);
    $boundary = '-------' . md5(time());
    $dataUpload = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$metadata\r\n" .
                  "--$boundary\r\nContent-Type: text/plain\r\n\r\n$sqlContent\r\n--$boundary--";

    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: multipart/related; boundary=$boundary",
        "Content-Length: " . strlen($dataUpload)
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataUpload);
    $uploadRes = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) { throw new Exception("Gagal kirim file. HTTP: $httpCode."); }

    // ========================================================
    // 6. FITUR AUTO-CLEANUP (Hapus File Lebih Dari 7 Hari)
    // ========================================================
    // Hitung tanggal 7 hari yang lalu
    $seven_days_ago = date('Y-m-d\TH:i:s\Z', strtotime('-7 days'));
    
    // Cari file di dalam folder tersebut yang dibuat sebelum 7 hari lalu
    $searchQuery = "parents in '$folderId' and createdTime < '$seven_days_ago' and trashed = false";
    $searchUrl = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($searchQuery) . '&fields=files(id,name)';

    $chList = curl_init($searchUrl);
    curl_setopt($chList, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chList, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
    $listRes = json_decode(curl_exec($chList), true);
    curl_close($chList);

    // Jika ada file lama yang ditemukan, hapus satu per satu
    if (isset($listRes['files']) && count($listRes['files']) > 0) {
        foreach ($listRes['files'] as $oldFile) {
            $deleteUrl = 'https://www.googleapis.com/drive/v3/files/' . $oldFile['id'];
            $chDel = curl_init($deleteUrl);
            curl_setopt($chDel, CURLOPT_CUSTOMREQUEST, "DELETE"); // Perintah Hapus Permanen
            curl_setopt($chDel, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chDel, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
            curl_exec($chDel);
            curl_close($chDel);
        }
    }

    // 7. Tandai di database agar hari ini tidak kirim lagi
    $pdo->prepare("UPDATE settings SET last_auto_backup = ? WHERE id = 1")->execute([$hari_ini]);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Backup otomatis berhasil & File lama dibersihkan!']);

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}