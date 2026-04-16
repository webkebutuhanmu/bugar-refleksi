# Bugar Refleksi - Website Terapi Pijat Refleksi & Home Care Aceh

Website profesional untuk layanan terapi pijat refleksi dengan 12 cabang di Aceh. Mendukung booking online, layanan panggilan ke rumah dan hotel.

## 📁 Struktur Folder

```
bugar-refleksi/
├── index.php                 # File utama (entry point)
├── assets/
│   ├── css/
│   │   └── style.css        # Semua styling CSS
│   └── js/
│       └── script.js        # Semua JavaScript
└── includes/
    ├── header.php           # Navigasi & Promo Banner
    ├── hero.php             # Hero Section
    ├── features.php         # Keunggulan/Features
    ├── paket.php            # Paket & Harga
    ├── layanan.php          # Layanan/Services
    ├── galeri.php           # Gallery
    ├── testimoni.php        # Testimonials
    ├── booking.php          # Form Booking
    ├── faq.php              # FAQ Section
    ├── cabang.php           # 12 Cabang
    ├── kontak.php           # Kontak Section
    └── footer.php           # Footer
```

## 🚀 Cara Instalasi

### 1. Setup Server PHP

**Menggunakan XAMPP/WAMP:**
- Copy folder `bugar-refleksi` ke `htdocs` (XAMPP) atau `www` (WAMP)
- Akses via browser: `http://localhost/bugar-refleksi`

**Menggunakan PHP Built-in Server:**
```bash
cd bugar-refleksi
php -S localhost:8000
```
Akses via browser: `http://localhost:8000`

### 2. Persyaratan
- PHP 7.4 atau lebih tinggi
- Web server (Apache/Nginx) atau PHP built-in server

## 📝 Komponen Utama

### 1. **Header (header.php)**
- Navigasi responsif
- Search box
- Mobile menu toggle
- Promo banner

### 2. **Hero Section (hero.php)**
- Hero text dengan CTA buttons
- Statistik (15+ Tahun, 12 Cabang, 24 Jam)
- Hero image placeholder

### 3. **Features (features.php)**
- 4 fitur utama:
  - Terapis Profesional
  - Layanan Panggilan
  - Harga Terjangkau
  - Buka Setiap Hari

### 4. **Paket & Harga (paket.php)**
- 3 kategori layanan:
  - Paket Hemat (Outlet)
  - Non Paket
  - Layanan Panggilan (Home/Hotel)
- 7 paket dengan harga berbeda
- Toggle antar kategori

### 5. **Layanan (layanan.php)**
- 6 jenis layanan:
  - Refleksi Kaki
  - Full Body Massage
  - Hot Stone Therapy
  - Pijat Punggung
  - Terapi Kepala
  - Terapi Bahu & Leher

### 6. **Galeri (galeri.php)**
- Grid gallery dengan 6 gambar
- Hover effects
- Responsive layout

### 7. **Testimoni (testimoni.php)**
- Slider testimoni pelanggan
- Rating bintang
- Auto-scroll dengan controls

### 8. **Booking (booking.php)**
- Form reservasi lengkap:
  - Nama & Kontak
  - Layanan & Terapis
  - Tanggal & Waktu
  - Alamat
  - Catatan
- Validasi form

### 9. **FAQ (faq.php)**
- 5 pertanyaan umum
- Accordion style
- Expand/collapse animation

### 10. **Cabang (cabang.php)**
- 12 cabang di seluruh Aceh
- Alamat lengkap
- Grid layout responsif

### 11. **Kontak (kontak.php)**
- 3 metode kontak:
  - WhatsApp
  - Telepon
  - Email
- Buttons dengan hover effects

### 12. **Footer (footer.php)**
- Informasi perusahaan
- Quick links
- Informasi kontak
- Copyright

## 🎨 Fitur CSS (style.css)

### Animations
- `fadeInUp` - Fade in dari bawah
- `fadeInLeft` - Fade in dari kiri
- `fadeInRight` - Fade in dari kanan
- `scaleIn` - Scale dari kecil
- `float` - Floating animation
- `smoothPulse` - Pulse effect
- `spin` - Rotating loading
- `shimmer` - Loading shimmer
- `bounceSubtle` - Subtle bounce

### Color Variables
```css
--white: #FFFFFF
--off-white: #F8F8F8
--yellow: #FFD700
--red: #E63946
--black: #0D0D0D
--gray: #666666
--accent: #00B4D8
--green: #2a9d8f
```

### Responsive Breakpoints
- Desktop: > 1200px (4-6 kolom)
- Large Tablet: 1200px (3 kolom)
- Tablet: 1024px (2 kolom)
- Small Tablet: 601-768px (2 kolom)
- Mobile: ≤ 768px (1 kolom)
- Small Mobile: ≤ 480px (1 kolom forced)

## 🔧 Fitur JavaScript (script.js)

### Core Features
1. **Loading Screen** - Smooth transition saat load
2. **Smooth Scroll** - Smooth scrolling ke sections
3. **Mobile Menu** - Toggle mobile navigation
4. **Sticky Navbar** - Fixed navbar on scroll
5. **Scroll Animations** - Reveal on scroll animations
6. **Price Toggle** - Toggle antar paket
7. **Testimonial Slider** - Auto-scroll testimonials
8. **FAQ Accordion** - Expand/collapse FAQ
9. **Search Function** - Search layanan/paket
10. **Form Validation** - Validasi form booking
11. **Scroll to Top** - Button scroll ke atas
12. **WhatsApp Float** - Floating WA button
13. **Touch Feedback** - Visual feedback on mobile

### Mobile Optimizations
- Lazy loading images
- Touch feedback
- Debounced scroll events
- Auto-resize textarea
- Vibration feedback (if supported)
- Prevent double-tap zoom
- Hide navbar on scroll down

## 📱 Mobile Responsive

Website fully responsive dengan optimasi khusus untuk:
- Smartphone (320px - 768px)
- Tablet (768px - 1024px)
- Desktop (> 1024px)

### Mobile Features
- Touch-friendly buttons (min 44px)
- Swipeable testimonials
- Collapsible mobile menu
- Optimized font sizes
- Reduced animations
- Fast loading

## 🔌 Integrasi

### WhatsApp
- Floating button: `https://wa.me/6282162126499`
- Auto-filled message template

### Email
- Contact email: `info@bugarrefleksi.com`
- Protected from bots

### Phone
- Direct call: `+62 831-8664-5262`
- Click-to-call enabled

## 🎯 SEO Optimized

- Meta description
- Semantic HTML5
- Clean URL structure
- Fast loading time
- Mobile-first design
- Proper heading hierarchy

## ⚙️ Customization

### Mengganti Logo
Edit file `header.php`:
```html
<img src="BUGAR REFLEKSII.png" alt="Logo" height="60">
```

### Mengganti Warna
Edit file `style.css` pada `:root`:
```css
:root {
    --red: #E63946;  /* Warna primary */
    --yellow: #FFD700; /* Warna accent */
}
```

### Menambah Paket
Edit file `paket.php` dan tambahkan:
```html
<div class="price-card">
    <div class="price-header">
        <h3 class="price-title">Paket Baru</h3>
        <div class="price-amount">Rp XXX</div>
    </div>
    <!-- ... -->
</div>
```

### Menambah Cabang
Edit file `cabang.php` dan tambahkan:
```html
<div class="branch-card">
    <div class="branch-number">13</div>
    <h3 class="branch-name">Nama Cabang</h3>
    <p class="branch-address">Alamat Lengkap</p>
</div>
```

## 📞 Support

Untuk bantuan atau pertanyaan:
- WhatsApp: +62 821-6212-6499
- Email: info@bugarrefleksi.com
- Website: www.bugarrefleksi.com

## 📄 License

© 2026 Bugar Refleksi. All Rights Reserved.

---

**Dibuat dengan ❤️ untuk Bugar Refleksi**
