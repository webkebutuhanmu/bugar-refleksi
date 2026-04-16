<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'terapis') {
    header("Location: ../auth/login_system.php"); exit;
}

$terapis_id   = $_SESSION['user_id'];
$nama_terapis = $_SESSION['nama'];

// Ambil data user & cabang
$stmtUser = $pdo->prepare(
    "SELECT u.*, b.nama_cabang
     FROM users u LEFT JOIN branches b ON u.home_branch_id = b.id
     WHERE u.id = ?"
);
$stmtUser->execute([$terapis_id]);
$userData    = $stmtUser->fetch(PDO::FETCH_ASSOC);
$nama_cabang = $userData['nama_cabang'] ?? 'Belum ditentukan';
$foto_url    = (!empty($userData['foto_profil']) && file_exists("../assets/uploads/" . $userData['foto_profil']))
               ? "../assets/uploads/" . $userData['foto_profil']
               : "../assets/default_user.png";

// Ambil SEMUA pelanggaran (tidak dibatalkan) — tidak reset harian
$stmtPel = $pdo->prepare(
    "SELECT id, kategori, judul, deskripsi, tanggal, waktu_kejadian,
            status, catatan_leader, created_at
     FROM pelanggaran
     WHERE terapis_id = ? AND status != 'dibatalkan'
     ORDER BY created_at DESC"
);
$stmtPel->execute([$terapis_id]);
$riwayatPelanggaran = $stmtPel->fetchAll(PDO::FETCH_ASSOC);
$jumlahPelanggaran  = count($riwayatPelanggaran);

// Hitung skor
$skorReward = max(0, 100 - ($jumlahPelanggaran * 2));

// Warna skor
if ($skorReward >= 80)      { $skorColor = '#27ae60'; $skorLabel = 'Sangat Baik'; $skorEmoji = '🏆'; }
elseif ($skorReward >= 60)  { $skorColor = '#f39c12'; $skorLabel = 'Cukup Baik';  $skorEmoji = '👍'; }
elseif ($skorReward >= 40)  { $skorColor = '#e67e22'; $skorLabel = 'Perlu Perhatian'; $skorEmoji = '⚠️'; }
else                        { $skorColor = '#e74c3c'; $skorLabel = 'Kritis'; $skorEmoji = '🚨'; }

// SVG circle besar: r=70, cx=cy=80
$bigR    = 70;
$bigCirc = round(2 * M_PI * $bigR, 2);
$bigOff  = round($bigCirc * (100 - $skorReward) / 100, 2);

// Breakdown per kategori
$breakdown = [];
foreach ($riwayatPelanggaran as $p) {
    $kat = $p['kategori'];
    if (!isset($breakdown[$kat])) $breakdown[$kat] = 0;
    $breakdown[$kat]++;
}

// Label & warna kategori
$katConfig = [
    'keterlambatan' => ['label' => 'Keterlambatan', 'color' => '#f39c12', 'icon' => '⏰'],
    'mangkir'       => ['label' => 'Mangkir',       'color' => '#e74c3c', 'icon' => '🚫'],
    'perilaku'      => ['label' => 'Perilaku',      'color' => '#9b59b6', 'icon' => '💬'],
    'atribut'       => ['label' => 'Atribut',       'color' => '#3498db', 'icon' => '👕'],
    'lainnya'       => ['label' => 'Lainnya',        'color' => '#7f8c8d', 'icon' => '📌'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skor Reward – <?= htmlspecialchars($nama_terapis) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========================================
           HALAMAN SKOR REWARD – FULL DETAIL
           ======================================== */

        /* ---- Circle big ---- */
        .skor-hero {
            background: linear-gradient(135deg, #2c3e50, #3d566e);
            border-radius: 16px;
            padding: 36px 28px 28px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 32px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            flex-wrap: wrap;
        }
        .skor-circle-wrap {
            position: relative;
            width: 160px; height: 160px;
            flex-shrink: 0;
        }
        .skor-circle-wrap svg {
            transform: rotate(-90deg);
            width: 160px; height: 160px;
        }
        .skor-circle-track  { fill: none; stroke: rgba(255,255,255,0.1); stroke-width: 12; }
        .skor-circle-prog   { fill: none; stroke-width: 12; stroke-linecap: round;
                              transition: stroke-dashoffset 1s ease; }
        .skor-circle-center {
            position: absolute; inset: 0;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            line-height: 1;
        }
        .skor-big-num  { font-size: 52px; font-weight: 900; color: #fff; }
        .skor-big-max  { font-size: 14px; color: rgba(255,255,255,0.45); margin-top: 2px; }
        .skor-hero-info { flex: 1; min-width: 0; color: #fff; }
        .skor-hero-label {
            font-size: 13px; text-transform: uppercase; letter-spacing: 1.5px;
            color: rgba(255,255,255,0.5); margin-bottom: 6px;
        }
        .skor-hero-title {
            font-size: 28px; font-weight: 900; margin-bottom: 6px;
        }
        .skor-hero-sub {
            font-size: 14px; color: rgba(255,255,255,0.65); margin-bottom: 18px;
        }
        .skor-stats-row {
            display: flex; gap: 14px; flex-wrap: wrap;
        }
        .skor-stat-chip {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px; padding: 10px 16px; text-align: center;
        }
        .skor-stat-chip .sv { font-size: 22px; font-weight: 800; color: #fff; }
        .skor-stat-chip .sl { font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 2px; }

        /* ---- Progress bar skor ---- */
        .skor-progress-bar-wrap {
            background: #f1f2f6; border-radius: 30px; height: 12px;
            margin-top: 14px; overflow: hidden;
        }
        .skor-progress-bar {
            height: 100%; border-radius: 30px;
            transition: width 0.8s ease;
        }

        /* ---- Breakdown cards ---- */
        .breakdown-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .breakdown-card {
            background: white;
            border-radius: 12px;
            padding: 16px 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-top: 4px solid;
            text-align: center;
        }
        .breakdown-icon  { font-size: 24px; margin-bottom: 6px; }
        .breakdown-count { font-size: 28px; font-weight: 900; }
        .breakdown-label { font-size: 12px; color: #7f8c8d; margin-top: 2px; }
        .breakdown-pts   { font-size: 11px; font-weight: 700; margin-top: 4px; }

        /* ---- Filter bar ---- */
        .filter-row {
            display: flex; gap: 8px; flex-wrap: wrap;
            margin-bottom: 16px; align-items: center;
        }
        .filter-btn {
            padding: 6px 16px; border: 2px solid #ddd; border-radius: 20px;
            background: white; cursor: pointer; font-size: 13px;
            font-weight: 600; color: #7f8c8d; transition: 0.2s;
        }
        .filter-btn:hover { border-color: #3498db; color: #3498db; }
        .filter-btn.active { background: #2c3e50; border-color: #2c3e50; color: white; }

        /* ---- Timeline list ---- */
        .timeline-list { list-style: none; padding: 0; margin: 0; }
        .timeline-item {
            display: flex; gap: 14px; margin-bottom: 14px; align-items: flex-start;
        }
        .tl-dot-col { display: flex; flex-direction: column; align-items: center; }
        .tl-dot {
            width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; margin-top: 4px;
        }
        .tl-line {
            width: 2px; background: #ecf0f1; flex: 1; margin-top: 4px; min-height: 20px;
        }
        .tl-card {
            flex: 1; background: white; border-radius: 12px;
            padding: 14px 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 4px solid;
        }
        .tl-card.status-selesai { opacity: 0.65; }
        .tl-card-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            gap: 8px; margin-bottom: 6px;
        }
        .tl-title { font-weight: 700; font-size: 14px; color: #2c3e50; line-height: 1.3; }
        .tl-badge {
            display: inline-block; padding: 2px 9px; border-radius: 20px;
            font-size: 11px; font-weight: 700; white-space: nowrap; flex-shrink: 0;
        }
        .tl-desc  { font-size: 12px; color: #7f8c8d; margin-bottom: 6px; line-height: 1.5; }
        .tl-meta  { font-size: 11px; color: #bdc3c7; display: flex; gap: 12px; flex-wrap: wrap; }
        .tl-meta span { display: flex; align-items: center; gap: 4px; }
        .tl-catatan {
            margin-top: 8px; padding: 8px 10px;
            background: #f8f9fa; border-radius: 8px;
            font-size: 12px; color: #7f8c8d; font-style: italic;
            border-left: 3px solid #bdc3c7;
        }
        .tl-poin-badge {
            display: inline-block; padding: 2px 8px; border-radius: 8px;
            font-size: 11px; font-weight: 700;
            background: #fde8e8; color: #c0392b;
        }

        /* ---- Empty state ---- */
        .empty-state {
            text-align: center; padding: 50px 20px; color: #95a5a6;
        }
        .empty-state .big-icon { font-size: 64px; margin-bottom: 16px; }
        .empty-state h3 { font-size: 20px; color: #2c3e50; margin-bottom: 8px; }
        .empty-state p  { font-size: 14px; }

        /* ---- Back button ---- */
        .btn-back {
            display: inline-flex; align-items: center; gap: 7px;
            background: white; border: 2px solid #ddd; border-radius: 10px;
            padding: 8px 18px; font-size: 13px; font-weight: 700;
            color: #2c3e50; text-decoration: none; transition: 0.2s;
            margin-bottom: 20px;
        }
        .btn-back:hover { border-color: #3498db; color: #3498db; }

        /* ---- Kategori badge colors ---- */
        .kat-keterlambatan { background: rgba(243,156,18,0.15); color: #d68910; }
        .kat-mangkir       { background: rgba(231,76,60,0.15);  color: #c0392b; }
        .kat-perilaku      { background: rgba(155,89,182,0.15); color: #8e44ad; }
        .kat-atribut       { background: rgba(52,152,219,0.15); color: #2980b9; }
        .kat-lainnya       { background: rgba(127,140,141,0.15);color: #7f8c8d; }
    </style>
</head>
<body>
<div class="container-layout">

    <!-- SIDEBAR (sama persis dengan dashboard_terapis) -->
    <div class="sidebar">
        <div class="sidebar-header"><h2>💆 TERAPIS PANEL</h2></div>
        <div class="sidebar-menu">
            <a href="dashboard_terapis.php" class="menu-item"><i>📊</i> Dashboard</a>
            <a href="absensi_terapis.php" class="menu-item"><i>📋</i> Absensi</a>
            <a href="riwayat_pendapatan.php" class="menu-item"><i>💰</i> Riwayat Omset</a>
            <a href="profil_terapis.php" class="menu-item"><i>👤</i> Profil Saya</a>
            <a href="skor_reward_terapis.php" class="menu-item active"><i>⭐</i> Skor Reward</a>
            <a href="../auth/logout_system.php" class="menu-item" style="color: #c0392b; margin-top: 50px;"><i>🚪</i> Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- ===========================
             HERO: LINGKARAN SKOR BESAR
             =========================== -->
        <div class="skor-hero">
            <!-- Lingkaran SVG -->
            <div class="skor-circle-wrap">
                <svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg">
                    <circle class="skor-circle-track" cx="80" cy="80" r="<?= $bigR ?>"/>
                    <circle class="skor-circle-prog"
                            cx="80" cy="80" r="<?= $bigR ?>"
                            stroke="<?= $skorColor ?>"
                            stroke-dasharray="<?= $bigCirc ?>"
                            stroke-dashoffset="<?= $bigOff ?>"/>
                </svg>
                <div class="skor-circle-center">
                    <div class="skor-big-num" style="color:<?= $skorColor ?>;"><?= $skorReward ?></div>
                    <div class="skor-big-max">/ 100</div>
                </div>
            </div>

            <!-- Info kanan lingkaran -->
            <div class="skor-hero-info">
                <div class="skor-hero-label">Skor Reward Kamu</div>
                <div class="skor-hero-title" style="color:<?= $skorColor ?>;">
                    <?= $skorEmoji ?> <?= $skorLabel ?>
                </div>
                <div class="skor-hero-sub">
                    <?= htmlspecialchars($nama_terapis) ?> &bull; <?= htmlspecialchars($nama_cabang) ?>
                </div>

                <!-- Progress bar -->
                <div class="skor-progress-bar-wrap">
                    <div class="skor-progress-bar"
                         style="width:<?= $skorReward ?>%; background:<?= $skorColor ?>;"></div>
                </div>

                <!-- Stat chips -->
                <div class="skor-stats-row" style="margin-top:16px;">
                    <div class="skor-stat-chip">
                        <div class="sv"><?= $jumlahPelanggaran ?></div>
                        <div class="sl">Pelanggaran</div>
                    </div>
                    <div class="skor-stat-chip">
                        <div class="sv" style="color:#e74c3c;">-<?= $jumlahPelanggaran * 2 ?></div>
                        <div class="sl">Poin Dikurangi</div>
                    </div>
                    <div class="skor-stat-chip">
                        <div class="sv" style="color:#27ae60;"><?= $skorReward ?></div>
                        <div class="sl">Poin Tersisa</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===========================
             BREAKDOWN PER KATEGORI
             =========================== -->
        <?php if ($jumlahPelanggaran > 0): ?>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <span>📊 Breakdown per Kategori</span>
            </div>
            <div style="padding:16px;">
                <div class="breakdown-grid">
                    <?php foreach ($katConfig as $key => $cfg):
                        $cnt = $breakdown[$key] ?? 0;
                        if ($cnt === 0) continue;
                    ?>
                    <div class="breakdown-card" style="border-top-color:<?= $cfg['color'] ?>;">
                        <div class="breakdown-icon"><?= $cfg['icon'] ?></div>
                        <div class="breakdown-count" style="color:<?= $cfg['color'] ?>;"><?= $cnt ?></div>
                        <div class="breakdown-label"><?= $cfg['label'] ?></div>
                        <div class="breakdown-pts" style="color:#e74c3c;">-<?= $cnt * 2 ?> poin</div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Info rumus -->
                <div style="background:#f8f9fa; border-radius:10px; padding:12px 16px; font-size:13px; color:#7f8c8d;">
                    <i class="fas fa-info-circle" style="color:#3498db;"></i>
                    <strong>Cara hitung:</strong> Skor awal <strong>100</strong> poin.
                    Setiap pelanggaran mengurangi <strong>2 poin</strong>.
                    Skor minimum adalah <strong>0</strong>.
                    Pelanggaran yang dibatalkan tidak dihitung.
                </div>
            </div>
        </div>

        <!-- ===========================
             TIMELINE PELANGGARAN
             =========================== -->
        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <span>📋 Riwayat Pelanggaran</span>
                <span style="font-size:13px; color:#7f8c8d; font-weight:normal;">
                    <?= $jumlahPelanggaran ?> pelanggaran aktif
                </span>
            </div>

            <!-- Filter kategori -->
            <div style="padding:12px 16px 0;">
                <div class="filter-row">
                    <button class="filter-btn active" onclick="filterKat(this,'semua')">Semua</button>
                    <?php foreach ($katConfig as $key => $cfg):
                        if (!isset($breakdown[$key]) || $breakdown[$key] === 0) continue;
                    ?>
                    <button class="filter-btn" onclick="filterKat(this,'<?= $key ?>')">
                        <?= $cfg['icon'] ?> <?= $cfg['label'] ?>
                        <span style="background:<?= $cfg['color'] ?>; color:white; border-radius:10px;
                                     padding:1px 6px; font-size:10px; margin-left:4px;">
                            <?= $breakdown[$key] ?>
                        </span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="padding:0 16px 16px;">
                <ul class="timeline-list" id="timelineList">
                    <?php foreach ($riwayatPelanggaran as $idx => $pv):
                        $cfg2     = $katConfig[$pv['kategori']] ?? $katConfig['lainnya'];
                        $dotColor = $cfg2['color'];
                        $tglFmt   = date('d M Y', strtotime($pv['tanggal']));
                        $tglCreat = date('d M Y, H:i', strtotime($pv['created_at']));
                        $isLast   = ($idx === count($riwayatPelanggaran) - 1);
                        $cardKls  = $pv['status'] === 'selesai' ? 'tl-card status-selesai' : 'tl-card';
                    ?>
                    <li class="timeline-item" data-kat="<?= $pv['kategori'] ?>">
                        <div class="tl-dot-col">
                            <div class="tl-dot" style="background:<?= $dotColor ?>;"></div>
                            <?php if (!$isLast): ?>
                            <div class="tl-line"></div>
                            <?php endif; ?>
                        </div>
                        <div class="<?= $cardKls ?>" style="border-left-color:<?= $dotColor ?>;">
                            <div class="tl-card-header">
                                <div class="tl-title"><?= htmlspecialchars($pv['judul']) ?></div>
                                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                                    <span class="tl-badge kat-<?= $pv['kategori'] ?>">
                                        <?= $cfg2['icon'] ?> <?= $cfg2['label'] ?>
                                    </span>
                                    <span class="tl-poin-badge">&#8722;2 poin</span>
                                </div>
                            </div>
                            <?php if (!empty($pv['deskripsi'])): ?>
                            <div class="tl-desc"><?= htmlspecialchars($pv['deskripsi']) ?></div>
                            <?php endif; ?>
                            <div class="tl-meta">
                                <span><i class="fas fa-calendar-day"></i> Kejadian: <?= $tglFmt ?></span>
                                <?php if (!empty($pv['waktu_kejadian'])): ?>
                                <span><i class="fas fa-clock"></i> <?= substr($pv['waktu_kejadian'], 0, 5) ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-circle-dot" style="color:<?= $pv['status']==='aktif' ? '#e74c3c' : '#7f8c8d' ?>;"></i>
                                    <?= $pv['status'] === 'aktif' ? 'Aktif' : 'Selesai' ?>
                                </span>
                                <span><i class="fas fa-pen-to-square"></i> Dicatat: <?= $tglCreat ?></span>
                            </div>
                            <?php if (!empty($pv['catatan_leader'])): ?>
                            <div class="tl-catatan">
                                <i class="fas fa-comment-dots"></i>
                                <strong>Catatan Leader:</strong> <?= htmlspecialchars($pv['catatan_leader']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <?php else: ?>
        <!-- Empty state -->
        <div class="card">
            <div class="empty-state">
                <div class="big-icon">🏆</div>
                <h3>Skor Sempurna!</h3>
                <p>Kamu belum memiliki catatan pelanggaran.<br>Pertahankan terus ya!</p>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- end main-content -->
</div><!-- end container-layout -->

<script>
    // ===== FILTER TIMELINE =====
    function filterKat(btn, kat) {
        // Update tombol aktif
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Filter list item
        document.querySelectorAll('#timelineList .timeline-item').forEach(item => {
            if (kat === 'semua' || item.dataset.kat === kat) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }
</script>
</body>
</html>