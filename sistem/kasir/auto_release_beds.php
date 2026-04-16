<?php
// =====================================================
// AUTO-RELEASE BED SCRIPT
// =====================================================

require_once 'config/database.php';
date_default_timezone_set('Asia/Jakarta');

// FIX: Lindungi bed 'proses' DAN 'menunggu_approval'
$sqlReleaseBed = "UPDATE beds b
                  LEFT JOIN (
                      SELECT bed_id 
                      FROM transactions 
                      WHERE status IN ('proses', 'menunggu_approval')
                  ) t ON b.id = t.bed_id
                  SET b.status = 'kosong'
                  WHERE t.bed_id IS NULL 
                  AND b.status = 'terisi'";
$pdo->query($sqlReleaseBed);

echo "Auto-release completed at " . date('Y-m-d H:i:s');
?>