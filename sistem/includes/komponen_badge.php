<!-- 
    BADGE ID CARD COMPONENT - Bugar Refleksi
    Include this file in profil_terapis.php, profil_kasir.php, profil_leader.php
    
    Required PHP variables before including:
    - $badge_nama       : nama lengkap
    - $badge_role       : 'Terapis Profesional' / 'Kasir' / 'Leader Cabang'
    - $badge_id         : barcode ID (TRP00001, KSR00001, LDR00001)
    - $badge_cabang     : nama cabang
    - $badge_hp         : no hp
    - $badge_foto       : URL foto profil
    - $badge_qr_data    : JSON string untuk QR
    - $badge_logo_url   : URL logo
-->

<style>
/* ======== ID CARD / BADGE ======== */
.id-card-section { margin-bottom: 25px; }
.id-card-section > h3 {
    color: #1a1a1a; font-size: 16px; margin-bottom: 15px;
    display: flex; align-items: center; gap: 8px;
}
.badge-container {
    display: flex; gap: 32px; flex-wrap: wrap; justify-content: center;
}
.badge-card-wrapper { position: relative; }
.badge-card-wrapper .card-label {
    text-align: center; font-size: 11px; color: #999;
    font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
    margin-bottom: 8px;
}

/* Lanyard */
.lanyard-connector {
    width: 30px; height: 14px; margin: 0 auto;
    background: linear-gradient(to bottom, #d4d4d4, #b0b0b0);
    border-radius: 0 0 8px 8px;
    position: relative; z-index: 5;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.lanyard-connector::before {
    content: '';
    position: absolute; top: -16px; left: 50%; transform: translateX(-50%);
    width: 2px; height: 16px;
    background: linear-gradient(to bottom, #CC1A1A, #b0b0b0);
}
.lanyard-connector::after {
    content: '';
    position: absolute; top: -24px; left: 50%; transform: translateX(-50%);
    width: 16px; height: 10px;
    border-bottom: 3px solid #CC1A1A;
    border-radius: 0 0 50% 50%;
}

/* Card shell */
.id-card {
    width: 310px; height: 460px;
    border-radius: 16px; overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.18), 0 2px 8px rgba(0,0,0,0.08);
    position: relative; background: #ffffff;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.id-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 18px 50px rgba(0,0,0,0.22), 0 4px 12px rgba(0,0,0,0.1);
}

/* ======= KARTU DEPAN ======= */
.id-card-front { display: flex; flex-direction: column; height: 100%; position: relative; }

/* Watermark depan */
.front-watermark {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 280px; height: 280px;
    object-fit: contain;
    opacity: 0.04;
    pointer-events: none;
    z-index: 0;
    filter: grayscale(100%);
}

.front-top {
    background: linear-gradient(160deg, #1a1a1a 0%, #2a2a2a 45%, #3a1a1a 100%);
    padding: 18px 20px 55px 20px;
    position: relative; text-align: center;
    min-height: 180px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: flex-start;
    z-index: 1;
}
.front-top::after {
    content: '';
    position: absolute; bottom: -1px; left: 0; right: 0;
    height: 45px; background: #ffffff;
    border-radius: 50% 50% 0 0 / 100% 100% 0 0;
}
.front-top::before {
    content: '';
    position: absolute; top: -40px; right: -40px;
    width: 140px; height: 140px; border-radius: 50%;
    background: rgba(255,214,0,0.06); pointer-events: none;
}
.front-top-decor {
    position: absolute; bottom: 30px; left: -25px;
    width: 90px; height: 90px; border-radius: 50%;
    background: rgba(204,26,26,0.08); pointer-events: none;
}

/* Logo - tanpa lingkaran */
.front-logo-area {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 14px; position: relative; z-index: 2;
}
.front-logo-img {
    width: 38px; height: 38px;
    object-fit: contain;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,0.4));
}
.front-logo-text {
    color: #FFD600;
    font-size: 11px; font-weight: 800;
    letter-spacing: 1.5px; text-transform: uppercase;
    line-height: 1.25; text-align: left;
}
.front-logo-text span { color: #CC1A1A; font-weight: 900; }

.front-photo-wrap { position: relative; z-index: 3; margin-top: 2px; }
.front-photo {
    width: 88px; height: 88px; border-radius: 10px;
    object-fit: cover; border: 4px solid #ffffff;
    box-shadow: 0 4px 16px rgba(0,0,0,0.3); background: #e2e8f0;
}

.front-bottom {
    flex: 1; padding: 8px 22px 14px;
    display: flex; flex-direction: column;
    align-items: center; text-align: center;
    position: relative; z-index: 1;
}
.front-name {
    font-size: 17px; font-weight: 800;
    color: #1a1a1a; margin-bottom: 2px;
}
.front-role {
    font-size: 11px; color: #CC1A1A;
    font-weight: 700; font-style: italic;
    margin-bottom: 12px; letter-spacing: 0.5px;
}
.front-data { width: 100%; text-align: left; margin-bottom: 10px; }
.front-data-row {
    display: flex; align-items: center;
    padding: 4px 0; font-size: 11px; color: #444;
    border-bottom: 1px solid #f5f0e0;
}
.front-data-row:last-child { border-bottom: none; }
.front-data-label {
    width: 70px; font-weight: 700; color: #888;
    flex-shrink: 0; font-size: 10px;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.front-data-sep { margin: 0 6px; color: #ccc; }
.front-data-value { flex: 1; font-weight: 600; color: #1a1a1a; font-size: 11px; }

.front-barcode-area {
    margin-top: auto; text-align: center; width: 100%; padding-top: 6px;
    position: relative; z-index: 1;
}
.front-barcode-area canvas { max-width: 210px; height: 38px; }

.front-accent-line {
    height: 5px; position: relative; z-index: 1;
    background: linear-gradient(90deg, #CC1A1A, #FFD600, #CC1A1A);
}

/* ======= KARTU BELAKANG ======= */
.id-card-back { display: flex; flex-direction: column; height: 100%; position: relative; }

.back-watermark {
    position: absolute;
    top: 45%; left: 50%;
    transform: translate(-50%, -50%);
    width: 280px; height: 280px;
    object-fit: contain;
    opacity: 0.06;
    pointer-events: none;
    z-index: 0;
    filter: grayscale(100%);
}

.back-top {
    flex: 1; padding: 28px 22px 20px;
    display: flex; flex-direction: column;
    align-items: center; text-align: center;
    justify-content: center;
    position: relative; z-index: 1;
}
.back-name {
    font-size: 18px; font-weight: 800;
    color: #1a1a1a; margin-bottom: 10px;
}
.back-description {
    font-size: 11px; color: #777;
    line-height: 1.65; margin-bottom: 16px; max-width: 240px;
}
.back-scan-label {
    font-size: 10px; font-weight: 700; color: #CC1A1A;
    letter-spacing: 2px; text-transform: uppercase; margin-bottom: 14px;
}
.back-qr-box {
    background: rgba(255,255,255,0.92); padding: 10px; border-radius: 12px;
    border: 2px solid #f0e8d0;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    display: inline-block; position: relative; z-index: 2;
}
.badge-qr-target { display: block; line-height: 0; }
.badge-qr-target img, .badge-qr-target canvas {
    width: 150px !important; height: 150px !important;
}
.back-barcode-id {
    font-family: 'Courier New', monospace; font-size: 14px;
    font-weight: 900; letter-spacing: 3px; color: #1a1a1a; margin-top: 10px;
}

.back-bottom {
    background: linear-gradient(160deg, #1a1a1a 0%, #2a2a2a 45%, #3a1a1a 100%);
    padding: 22px 20px 18px; position: relative;
    display: flex; align-items: center;
    justify-content: space-between; min-height: 110px; z-index: 1;
}
.back-bottom::before {
    content: '';
    position: absolute; top: -1px; left: 0; right: 0;
    height: 35px; background: #ffffff;
    border-radius: 0 0 50% 50% / 0 0 100% 100%;
}
.back-bottom::after {
    content: '';
    position: absolute; top: 5px; left: -20px;
    width: 90px; height: 90px; border-radius: 50%;
    background: rgba(255,214,0,0.05); pointer-events: none;
}
.back-bottom-accent {
    position: absolute; top: 33px; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, #FFD600, transparent);
}
.back-footer-text {
    position: relative; z-index: 2;
    color: rgba(255,255,255,0.5);
    font-size: 9px; line-height: 1.5; max-width: 150px;
}
.back-footer-text strong {
    color: #FFD600; font-weight: 700;
    display: block; font-size: 10px; margin-bottom: 2px;
}
.back-brand {
    position: relative; z-index: 2;
    display: flex; flex-direction: column;
    align-items: flex-end; gap: 6px;
}
.back-brand-logo {
    width: 40px; height: 40px;
    object-fit: contain;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.5));
}
.back-brand-name {
    color: #FFD600;
    font-size: 9px; font-weight: 800;
    letter-spacing: 1.5px; text-transform: uppercase;
    line-height: 1.3; text-align: right;
}

/* Print */
.btn-print-badge {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px;
    background: linear-gradient(135deg, #1a1a1a, #333);
    color: #FFD600; border: 2px solid #FFD600;
    border-radius: 8px; font-weight: 700;
    cursor: pointer; font-size: 13px; transition: all 0.2s; margin-top: 18px;
}
.btn-print-badge:hover { background: #FFD600; color: #1a1a1a; transform: translateY(-1px); }

@media print {
    .btn-print-badge { display: none !important; }
    .id-card { box-shadow: 0 0 0 1px #ddd !important; }
    .id-card:hover { transform: none; }
}
@media (max-width: 700px) {
    .badge-container { flex-direction: column; align-items: center; gap: 24px; }
    .id-card { width: 290px; height: 440px; }
}
</style>

<!-- BADGE HTML -->
<div class="id-card-section">
    <h3>&#127959; Kartu Identitas <?= htmlspecialchars($badge_role) ?></h3>
    <div class="badge-container">

        <!-- ===== KARTU DEPAN ===== -->
        <div class="badge-card-wrapper">
            <div class="card-label">&#9650; Depan</div>
            <div class="lanyard-connector"></div>
            <div class="id-card">
                <div class="id-card-front">
                    <img src="<?= $badge_logo_url ?>" class="front-watermark" alt="">
                    <div class="front-top">
                        <div class="front-top-decor"></div>
                        <div class="front-logo-area">
                            <img src="<?= $badge_logo_url ?>" class="front-logo-img" alt="Logo">
                            <div class="front-logo-text">BUGAR<br><span>REFLEKSI</span></div>
                        </div>
                        <div class="front-photo-wrap">
                            <img src="<?= $badge_foto ?>" class="front-photo" alt="Foto"
                                 onerror="this.onerror=null; this.src='../assets/default_user.png';">
                        </div>
                    </div>
                    <div class="front-bottom">
                        <div class="front-name"><?= htmlspecialchars($badge_nama) ?></div>
                        <div class="front-role"><?= htmlspecialchars($badge_role) ?></div>
                        <div class="front-data">
                            <div class="front-data-row">
                                <span class="front-data-label">Nama</span>
                                <span class="front-data-sep">:</span>
                                <span class="front-data-value"><?= htmlspecialchars($badge_nama) ?></span>
                            </div>
                            <div class="front-data-row">
                                <span class="front-data-label">ID No</span>
                                <span class="front-data-sep">:</span>
                                <span class="front-data-value"><?= htmlspecialchars($badge_id) ?></span>
                            </div>
                            <div class="front-data-row">
                                <span class="front-data-label">Phone</span>
                                <span class="front-data-sep">:</span>
                                <span class="front-data-value"><?= htmlspecialchars($badge_hp ?: '-') ?></span>
                            </div>
                            <div class="front-data-row">
                                <span class="front-data-label">Cabang</span>
                                <span class="front-data-sep">:</span>
                                <span class="front-data-value"><?= htmlspecialchars($badge_cabang) ?></span>
                            </div>
                        </div>
                        <div class="front-barcode-area">
                            <canvas id="barcodeCanvas"></canvas>
                        </div>
                    </div>
                    <div class="front-accent-line"></div>
                </div>
            </div>
        </div>

        <!-- ===== KARTU BELAKANG ===== -->
        <div class="badge-card-wrapper">
            <div class="card-label">&#9650; Belakang</div>
            <div class="lanyard-connector"></div>
            <div class="id-card">
                <div class="id-card-back">
                    <img src="<?= $badge_logo_url ?>" class="back-watermark" alt="">
                    <div class="back-top">
                        <div class="back-name"><?= htmlspecialchars($badge_nama) ?></div>
                        <div class="back-description">
                            <?= htmlspecialchars($badge_role) ?> yang terdaftar dan tersertifikasi di Bugar Refleksi.
                            berlaku sebagai tanda pengenal resmi selama masa aktif bekerja.
                        </div>
                        <div class="back-scan-label">Scan QR Untuk Verifikasi</div>
                        <div class="back-qr-box">
                            <div id="qrBadgeCode" class="badge-qr-target"></div>
                        </div>
                        <div class="back-barcode-id"><?= htmlspecialchars($badge_id) ?></div>
                    </div>
                    <div class="back-bottom">
                        <div class="back-bottom-accent"></div>
                        <div class="back-footer-text">
                            <strong>Bugar Refleksi &copy; <?= date('Y') ?></strong>
                    
                        </div>
                        <div class="back-brand">
                            <img src="<?= $badge_logo_url ?>" class="back-brand-logo" alt="Logo">
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <div style="text-align:center;">
        <button class="btn-print-badge" onclick="window.print()">&#128424; Cetak Kartu Identitas</button>
    </div>
</div>

<!-- Badge Scripts (loaded at end of page) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
<script>
window.addEventListener('load', function() {
    try {
        if (typeof QRCode !== 'undefined') {
            var qrEl = document.getElementById('qrBadgeCode');
            if (qrEl) {
                new QRCode(qrEl, {
                    text: <?= json_encode($badge_qr_data) ?>,
                    width: 150, height: 150,
                    colorDark: '#1a1a1a', colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        }
    } catch(e) { console.warn('QR error:', e); }

    try {
        if (typeof JsBarcode !== 'undefined') {
            JsBarcode("#barcodeCanvas", <?= json_encode($badge_id) ?>, {
                format: "CODE128", width: 1.5, height: 35,
                displayValue: false, margin: 0,
                lineColor: "#1a1a1a", background: "transparent"
            });
        }
    } catch(e) { console.warn('Barcode error:', e); }
});
</script>