<section class="booking" id="booking">
    <div class="section-header">
        <h2 class="section-title">Booking Online</h2>
        <p class="section-subtitle">Pesan slot Anda sekarang. Pilih datang ke Outlet atau Panggilan.</p>
    </div>
    <div class="booking-container">

        <!-- Gunakan ID unik bfForm agar tidak konflik dengan elemen lain -->
        <form id="bfForm" novalidate>

            <div class="form-group" style="background:#eefbff;padding:15px;border-radius:15px;border:1px solid #00B4D8;">
                <label for="bf_tipe">Lokasi Terapi *</label>
                <select id="bf_tipe" name="tipe_kunjungan" required>
                    <option value="Outlet">Datang ke Outlet (Cabang)</option>
                    <option value="Home Care">Panggilan ke Rumah (+ Ongkir)</option>
                    <option value="Hotel Visit">Panggilan ke Hotel (Premium/Normal)</option>
                </select>
                <small id="bf_tipe_info" style="color:#666;display:block;margin-top:5px;">Silakan datang ke outlet kami sesuai jadwal.</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="bf_nama">Nama Lengkap *</label>
                    <input type="text" id="bf_nama" name="nama" required placeholder="Masukkan nama Anda">
                </div>
                <div class="form-group">
                    <label for="bf_telepon">Nomor Telepon/WA *</label>
                    <input type="tel" id="bf_telepon" name="telepon" required placeholder="08xx-xxxx-xxxx">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="bf_cabang">Pilih Cabang Terdekat *</label>
                    <select id="bf_cabang" name="cabang" required>
                        <option value="">-- Pilih Cabang / Area --</option>
                        <option value="Peunayong 1">Peunayong 1</option>
                        <option value="Peunayong 2">Peunayong 2</option>
                        <option value="Neusu">Neusu</option>
                        <option value="Batoh">Batoh</option>
                        <option value="Lampeneurut">Lampeneurut</option>
                        <option value="Sigli">Sigli</option>
                        <option value="Bireuen">Bireuen</option>
                        <option value="Takengon">Takengon</option>
                        <option value="Sabang">Sabang</option>
                        <option value="Langsa">Langsa</option>
                        <option value="Lhokseumawe">Lhokseumawe</option>
                        <option value="Subulussalam">Subulussalam</option>
                    </select>
                    <div id="bf_wa_info" style="display:none;margin-top:10px;padding:9px 13px;background:#f0fff4;border:1px solid #68d391;border-radius:10px;font-size:0.83rem;color:#276749;font-weight:600;"></div>
                </div>

                <div class="form-group">
                    <label for="bf_layanan">Pilih Layanan *</label>
                    <select id="bf_layanan" name="layanan" required>
                        <option value="">-- Pilih Layanan --</option>
                        <optgroup label="Paket Hemat">
                            <option value="Paket 1 (100K)">Paket 1 (100K)</option>
                            <option value="Paket 2 (120K)">Paket 2 (120K)</option>
                            <option value="Paket 3 (140K)">Paket 3 (140K)</option>
                            <option value="Paket 4 (150K)">Paket 4 (150K)</option>
                            <option value="Paket 5 (150K)">Paket 5 (150K)</option>
                            <option value="Paket 6 (170K)">Paket 6 (170K)</option>
                            <option value="Paket 7 (200K)">Paket 7 (200K)</option>
                        </optgroup>
                        <optgroup label="Layanan Satuan">
                            <option value="Refleksi Kaki (60K)">Refleksi Kaki (60K)</option>
                            <option value="Full Body Massage (120K)">Full Body Massage (120K)</option>
                            <option value="Hot Stone Therapy (80K)">Hot Stone Therapy (80K)</option>
                            <option value="Pijat Punggung (70K)">Pijat Punggung (70K)</option>
                            <option value="Terapi Kepala (40K)">Terapi Kepala (40K)</option>
                            <option value="Terapi Bahu & Leher (50K)">Terapi Bahu &amp; Leher (50K)</option>
                        </optgroup>
                        <optgroup label="Panggilan (Home/Hotel)">
                            <option value="Home Care (Normal + Ongkir)">Home Care (Normal + Ongkir)</option>
                            <option value="Hotel Visit Normal (+ Ongkir)">Hotel Visit (Normal + Ongkir)</option>
                            <option value="Hotel Berbintang - Bundling">Hotel Berbintang ⭐ (Bundling)</option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <div class="form-group" id="bf_alamat_box" style="display:none;">
                <label for="bf_alamat">Alamat Lengkap / Nama Hotel &amp; No. Kamar *</label>
                <textarea id="bf_alamat" name="alamat" placeholder="Contoh: Jl. Mawar No. 10 atau Hotel Hermes Kamar 302"></textarea>
                <small style="color:#e53e3e;">*Dikenakan biaya bensin Rp 20.000 – Rp 30.000 (tergantung jarak).</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="bf_tanggal">Tanggal *</label>
                    <input type="date" id="bf_tanggal" name="tanggal" required>
                </div>
                <div class="form-group">
                    <label for="bf_waktu">Waktu *</label>
                    <input type="time" id="bf_waktu" name="waktu" required>
                </div>
            </div>

            <div class="form-group">
                <label for="bf_catatan">Catatan Tambahan</label>
                <textarea id="bf_catatan" name="catatan" placeholder="Preferensi terapis (Pria/Wanita), keluhan sakit, dll."></textarea>
            </div>

            <button type="button" id="bf_submit" class="submit-btn" onclick="bfKirim()">
                📱 Kirim Reservasi via WhatsApp
            </button>
        </form>
    </div>
</section>

<script>
(function () {

    /* === NOMOR WA PER CABANG — ganti dengan nomor asli === */
    var WA = {
        "Peunayong 1":  "6285167933801",
        "Peunayong 2":  "6281234567801",
        "Neusu":        "6285167933801",
        "Batoh":        "6281234567803",
        "Lampeneurut":  "6281234567804",
        "Sigli":        "6281234567805",
        "Bireuen":      "6281234567806",
        "Takengon":     "6281234567807",
        "Sabang":       "6281234567808",
        "Langsa":       "6281234567809",
        "Lhokseumawe":  "6281234567810",
        "Subulussalam": "6281234567811"
    };
    var WA_UTAMA = "6283186645262";

    /* === UPDATE TOMBOL SAAT CABANG DIPILIH === */
    function onCabangChange() {
        var cabang = document.getElementById('bf_cabang').value;
        var infoBox = document.getElementById('bf_wa_info');
        var btn = document.getElementById('bf_submit');

        if (cabang && WA[cabang]) {
            infoBox.style.display = 'block';
            infoBox.textContent = '✅ Booking akan dikirim ke WhatsApp Cabang ' + cabang;
            btn.textContent = '📱 Kirim ke WA Cabang ' + cabang;
            btn.style.background = '#22863a';
        } else {
            infoBox.style.display = 'none';
            btn.textContent = '📱 Kirim Reservasi via WhatsApp';
            btn.style.background = '';
        }
    }

    /* === TOGGLE ALAMAT === */
    function onTipeChange() {
        var tipe = document.getElementById('bf_tipe').value;
        var box = document.getElementById('bf_alamat_box');
        var info = document.getElementById('bf_tipe_info');
        if (tipe === 'Home Care') {
            box.style.display = 'block';
            info.textContent = 'Terapis datang ke rumah Anda. + biaya transport Rp 20.000–30.000.';
        } else if (tipe === 'Hotel Visit') {
            box.style.display = 'block';
            info.textContent = 'Terapis datang ke hotel Anda. + biaya transport & charge hotel.';
        } else {
            box.style.display = 'none';
            info.textContent = 'Silakan datang ke outlet kami sesuai jadwal.';
        }
    }

    /* === BIND EVENTS === */
    document.getElementById('bf_cabang').addEventListener('change', onCabangChange);
    document.getElementById('bf_tipe').addEventListener('change', onTipeChange);

})();

/* === KIRIM BOOKING — fungsi global dipanggil dari onclick === */
function bfKirim() {
    var nama    = document.getElementById('bf_nama').value.trim();
    var telepon = document.getElementById('bf_telepon').value.trim();
    var cabang  = document.getElementById('bf_cabang').value;
    var tipe    = document.getElementById('bf_tipe').value;
    var layanan = document.getElementById('bf_layanan').value;
    var tanggal = document.getElementById('bf_tanggal').value;
    var waktu   = document.getElementById('bf_waktu').value;
    var alamat  = document.getElementById('bf_alamat') ? document.getElementById('bf_alamat').value.trim() : '';
    var catatan = document.getElementById('bf_catatan').value.trim();

    /* Validasi — tampilkan field mana yang kosong */
    var kosong = [];
    if (!nama)    kosong.push('Nama Lengkap');
    if (!telepon) kosong.push('Nomor Telepon/WA');
    if (!cabang)  kosong.push('Pilih Cabang');
    if (!layanan) kosong.push('Pilih Layanan');
    if (!tanggal) kosong.push('Tanggal');
    if (!waktu)   kosong.push('Waktu');

    if (kosong.length > 0) {
        alert('Mohon lengkapi:\n• ' + kosong.join('\n• '));
        return;
    }

    /* Format tanggal */
    var tanggalFmt = tanggal;
    try {
        var d = new Date(tanggal + 'T00:00:00');
        tanggalFmt = d.toLocaleDateString('id-ID', {weekday:'long', year:'numeric', month:'long', day:'numeric'});
    } catch(e) {}

    /* Lokasi detail */
    var lokasi = '';
    if (tipe === 'Home Care') {
        lokasi = '\n🏠 *Alamat:* ' + (alamat || '-') + '\n⚠️ _Siap bayar transport (20-30k)_';
    } else if (tipe === 'Hotel Visit') {
        lokasi = '\n🏨 *Hotel/Kamar:* ' + (alamat || '-');
    } else {
        lokasi = '\n📍 *Outlet:* ' + cabang;
    }

    var WA_MAP = {
        "Peunayong 1":  "6282162126499",
        "Peunayong 2":  "6281234567801",
        "Neusu":        "6285167933801",
        "Batoh":        "6281234567803",
        "Lampeneurut":  "6281234567804",
        "Sigli":        "6281234567805",
        "Bireuen":      "6281234567806",
        "Takengon":     "6281234567807",
        "Sabang":       "6281234567808",
        "Langsa":       "6281234567809",
        "Lhokseumawe":  "6281234567810",
        "Subulussalam": "6281234567811"
    };

    var msg =
        '🌿 *BOOKING BUGAR REFLEKSI*\n' +
        '━━━━━━━━━━━━━━━━━━━━\n' +
        '📍 *Cabang:* ' + cabang + '\n' +
        '👤 *Nama:* ' + nama + '\n' +
        '📱 *Telepon:* ' + telepon + '\n' +
        '🏷️ *Tipe:* ' + tipe + lokasi + '\n' +
        '💆 *Layanan:* ' + layanan + '\n' +
        '📅 *Tanggal:* ' + tanggalFmt + '\n' +
        '🕐 *Waktu:* ' + waktu + '\n' +
        '📝 *Catatan:* ' + (catatan || '-') + '\n' +
        '━━━━━━━━━━━━━━━━━━━━\n' +
        '_Terima kasih, kami segera konfirmasi!_ 🙏';

    var nomor = WA_MAP[cabang] || "6283186645262";
    window.open('https://wa.me/' + nomor + '?text=' + encodeURIComponent(msg), '_blank');
}
</script>