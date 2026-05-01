<?php
/**
 * export_excel_admin.php
 * Letakkan file ini di: /admin/export_excel_admin.php
 *
 * Parameter GET yang didukung:
 *   filter_type  : mingguan | bulanan | tahunan | custom
 *   tgl_dari     : Y-m-d  (wajib jika filter_type=custom)
 *   tgl_sampai   : Y-m-d  (wajib jika filter_type=custom)
 *   detail_uid   : int    → export 1 orang saja
 *   branch_id    : int    → export 1 cabang saja
 *   (tanpa keduanya = export semua cabang)
 */

session_start();
require_once '../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// ── Auth: hanya owner ──────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit; }

// ── Autoload PhpSpreadsheet ────────────────────────────────────────────────
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill};

// ── Parameter Filter ───────────────────────────────────────────────────────
$filter_type  = $_GET['filter_type'] ?? 'mingguan';
$tgl_hari_ini = date('Y-m-d');

switch ($filter_type) {
    case 'bulanan':
        $tgl_dari   = date('Y-m-01');
        $tgl_sampai = date('Y-m-t');
        $label      = 'Bulan_' . date('F_Y');
        break;
    case 'tahunan':
        $tgl_dari   = date('Y-01-01');
        $tgl_sampai = date('Y-12-31');
        $label      = 'Tahun_' . date('Y');
        break;
    case 'custom':
        $tgl_dari   = $_GET['tgl_dari']   ?? date('Y-m-01');
        $tgl_sampai = $_GET['tgl_sampai'] ?? $tgl_hari_ini;
        $label      = date('d_M_Y', strtotime($tgl_dari)) . '_sd_' . date('d_M_Y', strtotime($tgl_sampai));
        break;
    default: // mingguan
        $tgl_dari   = date('Y-m-d', strtotime('monday this week'));
        $tgl_sampai = date('Y-m-d', strtotime('sunday this week'));
        $label      = 'Minggu_' . date('d_M', strtotime($tgl_dari)) . '_' . date('d_M_Y', strtotime($tgl_sampai));
        break;
}

$label_display = str_replace('_', ' ', $label);

// ── Scope export ───────────────────────────────────────────────────────────
$detail_uid = isset($_GET['detail_uid']) && is_numeric($_GET['detail_uid']) ? (int)$_GET['detail_uid'] : 0;
$branch_id  = isset($_GET['branch_id'])  && is_numeric($_GET['branch_id'])  ? (int)$_GET['branch_id']  : 0;

// ── Warna ──────────────────────────────────────────────────────────────────
$C_DARK      = '1C1C1E';
$C_DARK2     = '3A3A3C';
$C_PRIMARY   = '5856D6';
$C_PRIMARY_L = '7E7CE6';
$C_SUCCESS   = '28CD41';
$C_DANGER    = 'FF3B30';
$C_WARNING   = 'FF9500';
$C_ALT_ROW   = 'F2F2F7';
$C_BORDER    = 'E5E5EA';
$C_WHITE     = 'FFFFFF';
$C_GREEN_HDR = '1D6F42';

// ── Helper: terapkan style ─────────────────────────────────────────────────
function applyStyle($sheet, $range, array $opt): void
{
    $s = $sheet->getStyle($range);
    if (!empty($opt['bold']))       $s->getFont()->setBold(true);
    if (!empty($opt['size']))       $s->getFont()->setSize($opt['size']);
    if (!empty($opt['fg']))         $s->getFont()->getColor()->setARGB('FF' . $opt['fg']);
    if (!empty($opt['bg']))         $s->getFill()->setFillType(Fill::FILL_SOLID)
                                          ->getStartColor()->setARGB('FF' . $opt['bg']);
    if (!empty($opt['ha']))         $s->getAlignment()->setHorizontal($opt['ha']);
    if (!empty($opt['va']))         $s->getAlignment()->setVertical($opt['va']);
    if (!empty($opt['wrap']))       $s->getAlignment()->setWrapText(true);
    if (!empty($opt['border'])) {
        $bc = $opt['bc'] ?? 'E5E5EA';
        $bs = ['style' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . $bc]];
        $s->getBorders()->applyFromArray(['allBorders' => $bs]);
    }
}

function approvalLabel(string $s): string
{
    return match($s) {
        'approved' => 'Diterima',
        'rejected' => 'Ditolak',
        'pending'  => 'Pending',
        default    => '-',
    };
}

function durasiKerja(?string $masuk, ?string $keluar, string $status): string
{
    if (!$masuk || !$keluar || in_array($status, ['Sakit', 'Izin'])) return '-';
    $m = strtotime($masuk);
    $k = strtotime($keluar);
    if ($k <= $m) return '-';
    $diff = $k - $m;
    return floor($diff / 3600) . 'j ' . floor(($diff % 3600) / 60) . 'm';
}

// ── Kumpulkan daftar cabang & staf yang akan di-export ────────────────────
if ($detail_uid > 0) {
    // Export 1 orang saja
    $stmtU = $pdo->prepare("SELECT u.*, b.nama_cabang FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
    $stmtU->execute([$detail_uid]);
    $userRow = $stmtU->fetch(PDO::FETCH_ASSOC);
    if (!$userRow) { echo "User tidak ditemukan."; exit; }

    $scope = [
        [
            'branch'    => ['id' => $userRow['branch_id'], 'nama_cabang' => $userRow['nama_cabang'] ?? 'Tanpa Cabang'],
            'staf_list' => [$userRow],
        ]
    ];
    $scope_label = 'Staf_' . preg_replace('/\s+/', '_', $userRow['nama_lengkap']);

} elseif ($branch_id > 0) {
    // Export 1 cabang saja
    $stmtB = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
    $stmtB->execute([$branch_id]);
    $branchRow = $stmtB->fetch(PDO::FETCH_ASSOC);
    if (!$branchRow) { echo "Cabang tidak ditemukan."; exit; }

    $stmtS = $pdo->prepare("SELECT * FROM users WHERE branch_id = ? AND role != 'owner' ORDER BY role, nama_lengkap");
    $stmtS->execute([$branch_id]);

    $scope = [[
        'branch'    => $branchRow,
        'staf_list' => $stmtS->fetchAll(PDO::FETCH_ASSOC),
    ]];
    $scope_label = 'Cabang_' . preg_replace('/\s+/', '_', $branchRow['nama_cabang']);

} else {
    // Export semua cabang
    $allBranches = $pdo->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $scope = [];
    foreach ($allBranches as $b) {
        $stmtS = $pdo->prepare("SELECT * FROM users WHERE branch_id = ? AND role != 'owner' ORDER BY role, nama_lengkap");
        $stmtS->execute([$b['id']]);
        $scope[] = [
            'branch'    => $b,
            'staf_list' => $stmtS->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
    $scope_label = 'Semua_Cabang';
}

// ═══════════════════════════════════════════════════════════════════════════
// BUAT WORKBOOK
// ═══════════════════════════════════════════════════════════════════════════
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Bugar App')
    ->setTitle('Laporan Absensi ' . $label_display)
    ->setSubject('Export Owner – ' . $scope_label)
    ->setDescription('Diekspor oleh: ' . $_SESSION['nama'] . ' pada ' . date('d M Y H:i'));

$firstSheet = true;

// ── Helper: buat sheet detail per cabang ──────────────────────────────────
function buildDetailSheet(Spreadsheet $spreadsheet, array $branchData, string $tgl_dari, string $tgl_sampai,
                           string $label_display, bool &$firstSheet, $pdo,
                           string $C_DARK, string $C_DARK2, string $C_PRIMARY, string $C_PRIMARY_L,
                           string $C_SUCCESS, string $C_DANGER, string $C_WARNING,
                           string $C_ALT_ROW, string $C_BORDER, string $C_WHITE, string $C_GREEN_HDR): void
{
    $b         = $branchData['branch'];
    $staf_list = $branchData['staf_list'];

    $sheetTitle = substr(preg_replace('/[\/\\\?\*\[\]:]/', '-', $b['nama_cabang']), 0, 31);

    if ($firstSheet) {
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle($sheetTitle);
        $firstSheet = false;
    } else {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle($sheetTitle);
    }

    // ── Baris judul ──
    $ws->mergeCells('A1:L1');
    $ws->setCellValue('A1', 'LAPORAN ABSENSI – ' . strtoupper($b['nama_cabang']));
    applyStyle($ws, 'A1:L1', ['bold' => true, 'size' => 13, 'fg' => $C_WHITE, 'bg' => $C_GREEN_HDR,
        'ha' => Alignment::HORIZONTAL_CENTER, 'va' => Alignment::VERTICAL_CENTER]);
    $ws->getRowDimension(1)->setRowHeight(30);

    $ws->mergeCells('A2:L2');
    $ws->setCellValue('A2', 'Periode: ' . $label_display . '   |   Diekspor: ' . date('d M Y H:i') . '   |   Owner: ' . $_SESSION['nama']);
    applyStyle($ws, 'A2:L2', ['size' => 10, 'fg' => $C_WHITE, 'bg' => $C_DARK2,
        'ha' => Alignment::HORIZONTAL_CENTER]);
    $ws->getRowDimension(2)->setRowHeight(18);
    $ws->getRowDimension(3)->setRowHeight(6);

    // ── Lebar kolom ──
    foreach (['A'=>5,'B'=>24,'C'=>14,'D'=>13,'E'=>11,'F'=>11,'G'=>11,'H'=>16,'I'=>12,'J'=>30,'K'=>16,'L'=>13] as $col => $w) {
        $ws->getColumnDimension($col)->setWidth($w);
    }

    $row = 4;

    if (!$staf_list) {
        $ws->mergeCells("A{$row}:L{$row}");
        $ws->setCellValue("A{$row}", 'Tidak ada staf di cabang ini.');
        applyStyle($ws, "A{$row}:L{$row}", ['fg' => 'C7C7CC', 'ha' => Alignment::HORIZONTAL_CENTER, 'border' => true]);
        return;
    }

    foreach ($staf_list as $u) {
        // Sub-header karyawan
        $ws->mergeCells("A{$row}:L{$row}");
        $ws->setCellValue("A{$row}", '👤 ' . $u['nama_lengkap'] . '   |   ' . ucfirst($u['role']) . '   |   Credit Score: ' . $u['credit_score']);
        applyStyle($ws, "A{$row}:L{$row}", ['bold' => true, 'size' => 10, 'fg' => $C_WHITE, 'bg' => $C_PRIMARY,
            'ha' => Alignment::HORIZONTAL_LEFT, 'border' => true, 'bc' => '4544B1']);
        $ws->getRowDimension($row)->setRowHeight(22);
        $row++;

        // Header kolom
        $hdrs = ['No','Nama','Jabatan','Tanggal','Shift','Jam Masuk','Jam Keluar','Status','Approval','Alasan','Tindakan','Durasi'];
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L'];
        foreach ($hdrs as $i => $h) { $ws->setCellValue($cols[$i] . $row, $h); }
        applyStyle($ws, "A{$row}:L{$row}", ['bold' => true, 'size' => 9, 'fg' => $C_WHITE, 'bg' => $C_DARK2,
            'ha' => Alignment::HORIZONTAL_CENTER, 'border' => true, 'bc' => $C_DARK]);
        $ws->getRowDimension($row)->setRowHeight(20);
        $row++;

        // Data absensi
        $stmtA = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id ASC");
        $stmtA->execute([$u['id'], $tgl_dari, $tgl_sampai]);
        $abs_rows = $stmtA->fetchAll(PDO::FETCH_ASSOC);

        if (!$abs_rows) {
            $ws->mergeCells("A{$row}:L{$row}");
            $ws->setCellValue("A{$row}", '(Tidak ada data pada periode ini)');
            applyStyle($ws, "A{$row}:L{$row}", ['fg' => 'C7C7CC', 'ha' => Alignment::HORIZONTAL_CENTER,
                'border' => true, 'bc' => $C_BORDER]);
            $row++;
        } else {
            $no = 1;
            foreach ($abs_rows as $a) {
                $bgBase = ($no % 2 === 0) ? $C_ALT_ROW : $C_WHITE;

                // Warna status
                [$bgStatus, $fgStatus] = match($a['status_kehadiran']) {
                    'Tepat Waktu' => ['E2F9E9', $C_SUCCESS],
                    'Terlambat'   => ['FFE5E5', $C_DANGER],
                    'Sakit'       => ['FFF5F5', $C_WARNING],
                    'Izin'        => ['F0EFFF', $C_PRIMARY_L],
                    default       => [$bgBase, $C_DARK],
                };

                $isSakitIzin = in_array($a['status_kehadiran'], ['Sakit', 'Izin']);

                $ws->setCellValue("A{$row}", $no);
                $ws->setCellValue("B{$row}", $u['nama_lengkap']);
                $ws->setCellValue("C{$row}", ucfirst($u['role']));
                $ws->setCellValue("D{$row}", date('d/m/Y', strtotime($a['tanggal'])));
                $ws->setCellValue("E{$row}", 'Shift ' . $a['shift']);
                $ws->setCellValue("F{$row}", $isSakitIzin ? '-' : $a['waktu_masuk']);
                $ws->setCellValue("G{$row}", $isSakitIzin ? '-' : ($a['waktu_keluar'] ?? 'Belum Keluar'));
                $ws->setCellValue("H{$row}", $a['status_kehadiran']);
                $ws->setCellValue("I{$row}", approvalLabel($a['status_alasan']));
                $ws->setCellValue("J{$row}", $a['alasan_terlambat'] ?? '-');
                $ws->setCellValue("K{$row}", ($a['status_alasan'] !== 'none') ? approvalLabel($a['status_alasan']) : '-');
                $ws->setCellValue("L{$row}", durasiKerja($a['waktu_masuk'], $a['waktu_keluar'], $a['status_kehadiran']));

                applyStyle($ws, "A{$row}:L{$row}", ['size' => 9, 'bg' => $bgBase,
                    'va' => Alignment::VERTICAL_CENTER, 'border' => true, 'bc' => $C_BORDER, 'wrap' => true]);

                // Status kehadiran berwarna
                applyStyle($ws, "H{$row}", ['bg' => $bgStatus, 'fg' => $fgStatus, 'bold' => true,
                    'ha' => Alignment::HORIZONTAL_CENTER]);

                // Approval berwarna
                $apColor = match($a['status_alasan']) {
                    'approved' => $C_SUCCESS,
                    'rejected' => $C_DANGER,
                    'pending'  => $C_WARNING,
                    default    => '8E8E93',
                };
                applyStyle($ws, "I{$row}", ['fg' => $apColor, 'bold' => ($a['status_alasan'] !== 'none'),
                    'ha' => Alignment::HORIZONTAL_CENTER]);

                foreach (['A','D','E','F','G','L'] as $c) {
                    applyStyle($ws, "{$c}{$row}", ['ha' => Alignment::HORIZONTAL_CENTER]);
                }

                $ws->getRowDimension($row)->setRowHeight(18);
                $row++;
                $no++;
            }

            // Baris ringkasan per karyawan
            $tp = count(array_filter($abs_rows, fn($x) => $x['status_kehadiran'] === 'Tepat Waktu'));
            $tl = count(array_filter($abs_rows, fn($x) => $x['status_kehadiran'] === 'Terlambat'));
            $sk = count(array_filter($abs_rows, fn($x) => $x['status_kehadiran'] === 'Sakit'));
            $iz = count(array_filter($abs_rows, fn($x) => $x['status_kehadiran'] === 'Izin'));

            $ws->mergeCells("A{$row}:D{$row}");
            $ws->setCellValue("A{$row}", 'RINGKASAN: Total ' . count($abs_rows) . ' hari');
            $ws->mergeCells("E{$row}:F{$row}");
            $ws->setCellValue("E{$row}", "Tepat: {$tp}");
            $ws->mergeCells("G{$row}:H{$row}");
            $ws->setCellValue("G{$row}", "Terlambat: {$tl}");
            $ws->mergeCells("I{$row}:J{$row}");
            $ws->setCellValue("I{$row}", "Sakit: {$sk}  |  Izin: {$iz}");
            $ws->mergeCells("K{$row}:L{$row}");
            $ws->setCellValue("K{$row}", 'Score: ' . $u['credit_score']);

            applyStyle($ws, "A{$row}:L{$row}", ['bold' => true, 'size' => 9, 'bg' => 'F9F9F9',
                'border' => true, 'bc' => 'C7C7CC']);
            applyStyle($ws, "E{$row}", ['fg' => $C_SUCCESS]);
            applyStyle($ws, "G{$row}", ['fg' => ($tl > 0 ? $C_DANGER : $C_DARK)]);
            applyStyle($ws, "K{$row}", ['fg' => ($u['credit_score'] < 80 ? $C_DANGER : $C_WARNING)]);
            $ws->getRowDimension($row)->setRowHeight(18);
            $row++;
        }

        $row += 2; // jarak antar karyawan
    }

    $ws->freezePane('A5');
}

// ── Jika scope > 1 cabang: buat sheet Ringkasan dulu ──────────────────────
if (count($scope) > 1) {
    $ws = $spreadsheet->getActiveSheet();
    $ws->setTitle('Ringkasan');
    $firstSheet = false;

    // Judul
    $ws->mergeCells('A1:J1');
    $ws->setCellValue('A1', 'RINGKASAN ABSENSI SEMUA CABANG – BUGAR APP');
    applyStyle($ws, 'A1:J1', ['bold' => true, 'size' => 14, 'fg' => $C_WHITE, 'bg' => $C_GREEN_HDR,
        'ha' => Alignment::HORIZONTAL_CENTER, 'va' => Alignment::VERTICAL_CENTER]);
    $ws->getRowDimension(1)->setRowHeight(32);

    $ws->mergeCells('A2:J2');
    $ws->setCellValue('A2', 'Periode: ' . $label_display . '   |   Diekspor: ' . date('d M Y H:i') . '   |   Owner: ' . $_SESSION['nama']);
    applyStyle($ws, 'A2:J2', ['size' => 10, 'fg' => $C_WHITE, 'bg' => $C_DARK2,
        'ha' => Alignment::HORIZONTAL_CENTER]);
    $ws->getRowDimension(2)->setRowHeight(18);
    $ws->getRowDimension(3)->setRowHeight(6);

    // Lebar kolom ringkasan
    foreach (['A'=>5,'B'=>22,'C'=>24,'D'=>14,'E'=>13,'F'=>13,'G'=>13,'H'=>10,'I'=>10,'J'=>13] as $col => $w) {
        $ws->getColumnDimension($col)->setWidth($w);
    }

    // Header tabel
    $hdrs = ['No','Cabang','Nama Staf','Jabatan','Total Hadir','Tepat Waktu','Terlambat','Sakit','Izin','Credit Score'];
    $cols = ['A','B','C','D','E','F','G','H','I','J'];
    foreach ($hdrs as $i => $h) { $ws->setCellValue($cols[$i] . '4', $h); }
    applyStyle($ws, 'A4:J4', ['bold' => true, 'size' => 10, 'fg' => $C_WHITE, 'bg' => $C_PRIMARY,
        'ha' => Alignment::HORIZONTAL_CENTER, 'border' => true, 'bc' => '4544B1']);
    $ws->getRowDimension(4)->setRowHeight(22);

    $row = 5;
    $no  = 1;

    foreach ($scope as $item) {
        $b = $item['branch'];
        // Baris nama cabang
        $ws->mergeCells("A{$row}:J{$row}");
        $ws->setCellValue("A{$row}", '📍 ' . strtoupper($b['nama_cabang']));
        applyStyle($ws, "A{$row}:J{$row}", ['bold' => true, 'size' => 10, 'fg' => $C_WHITE, 'bg' => $C_DARK2,
            'ha' => Alignment::HORIZONTAL_LEFT, 'border' => true, 'bc' => $C_DARK]);
        $ws->getRowDimension($row)->setRowHeight(20);
        $row++;

        if (!$item['staf_list']) {
            $ws->mergeCells("A{$row}:J{$row}");
            $ws->setCellValue("A{$row}", '(Tidak ada staf)');
            applyStyle($ws, "A{$row}:J{$row}", ['fg' => 'C7C7CC', 'ha' => Alignment::HORIZONTAL_CENTER,
                'border' => true, 'bc' => $C_BORDER]);
            $row++;
            continue;
        }

        $startRow = $row;
        foreach ($item['staf_list'] as $u) {
            $uid   = $u['id'];
            $jml_h  = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai'")->fetchColumn();
            $jml_tp = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Tepat Waktu'")->fetchColumn();
            $jml_tl = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Terlambat'")->fetchColumn();
            $jml_sk = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Sakit'")->fetchColumn();
            $jml_iz = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai' AND status_kehadiran='Izin'")->fetchColumn();

            $bgRow = ($no % 2 === 0) ? $C_ALT_ROW : $C_WHITE;

            $ws->setCellValue("A{$row}", $no);
            $ws->setCellValue("B{$row}", $b['nama_cabang']);
            $ws->setCellValue("C{$row}", $u['nama_lengkap']);
            $ws->setCellValue("D{$row}", ucfirst($u['role']));
            $ws->setCellValue("E{$row}", $jml_h);
            $ws->setCellValue("F{$row}", $jml_tp);
            $ws->setCellValue("G{$row}", $jml_tl);
            $ws->setCellValue("H{$row}", $jml_sk);
            $ws->setCellValue("I{$row}", $jml_iz);
            $ws->setCellValue("J{$row}", $u['credit_score']);

            applyStyle($ws, "A{$row}:J{$row}", ['size' => 10, 'bg' => $bgRow,
                'va' => Alignment::VERTICAL_CENTER, 'border' => true, 'bc' => $C_BORDER]);
            foreach (['A','D','E','F','G','H','I','J'] as $c) {
                applyStyle($ws, "{$c}{$row}", ['ha' => Alignment::HORIZONTAL_CENTER]);
            }

            // Warna skor & terlambat
            $scoreColor = ($u['credit_score'] < 80) ? $C_DANGER : $C_WARNING;
            $ws->getStyle("J{$row}")->getFont()->getColor()->setARGB('FF' . $scoreColor);
            $ws->getStyle("J{$row}")->getFont()->setBold(true);
            if ($jml_tl > 0) {
                $ws->getStyle("G{$row}")->getFont()->getColor()->setARGB('FF' . $C_DANGER);
                $ws->getStyle("G{$row}")->getFont()->setBold(true);
            }

            $ws->getRowDimension($row)->setRowHeight(18);
            $row++;
            $no++;
        }

        // Subtotal
        $endRow = $row - 1;
        $ws->setCellValue("B{$row}", 'SUBTOTAL');
        $ws->setCellValue("D{$row}", count($item['staf_list']) . ' staf');
        foreach (['E','F','G','H','I'] as $c) {
            $ws->setCellValue("{$c}{$row}", "=SUM({$c}{$startRow}:{$c}{$endRow})");
        }
        $ws->setCellValue("J{$row}", "=AVERAGE(J{$startRow}:J{$endRow})");
        $ws->getStyle("J{$row}")->getNumberFormat()->setFormatCode('0.0');
        applyStyle($ws, "A{$row}:J{$row}", ['bold' => true, 'size' => 10, 'bg' => 'F9F9F9',
            'border' => true, 'bc' => 'C7C7CC']);
        foreach (['E','F','G','H','I','J'] as $c) {
            applyStyle($ws, "{$c}{$row}", ['ha' => Alignment::HORIZONTAL_CENTER]);
        }
        $ws->getRowDimension($row)->setRowHeight(18);
        $row += 2;
    }

    $ws->freezePane('A5');
}

// ── Buat sheet detail per scope ────────────────────────────────────────────
foreach ($scope as $item) {
    buildDetailSheet(
        $spreadsheet, $item, $tgl_dari, $tgl_sampai, $label_display, $firstSheet, $pdo,
        $C_DARK, $C_DARK2, $C_PRIMARY, $C_PRIMARY_L,
        $C_SUCCESS, $C_DANGER, $C_WARNING,
        $C_ALT_ROW, $C_BORDER, $C_WHITE, $C_GREEN_HDR
    );
}

// ── Aktifkan sheet pertama ─────────────────────────────────────────────────
$spreadsheet->setActiveSheetIndex(0);

// ── Output ─────────────────────────────────────────────────────────────────
$filename = 'Absensi_Owner_' . $scope_label . '_' . $label . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');

(new Xlsx($spreadsheet))->save('php://output');
exit;