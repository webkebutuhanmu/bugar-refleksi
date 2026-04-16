# 📂 Struktur Project Bugar Refleksi

Dokumentasi lengkap struktur folder dan file dalam project Bugar Refleksi.

## 🌳 Tree Structure

```
bugar-refleksi/
│
├── 📄 index.php                      # File utama (entry point)
├── 📄 config.php                     # Konfigurasi & helper functions
├── 📄 .htaccess                      # Apache configuration
├── 📄 .gitignore                     # Git ignore rules
│
├── 📁 assets/                        # Asset files
│   ├── 📁 css/
│   │   └── 📄 style.css             # Main stylesheet (1813 baris)
│   └── 📁 js/
│       └── 📄 script.js             # Main JavaScript (492 baris)
│
├── 📁 includes/                      # PHP Include files
│   ├── 📄 header.php                # Navbar & Promo Banner
│   ├── 📄 hero.php                  # Hero Section
│   ├── 📄 features.php              # Features/Keunggulan
│   ├── 📄 paket.php                 # Paket & Harga
│   ├── 📄 layanan.php               # Services/Layanan
│   ├── 📄 galeri.php                # Gallery
│   ├── 📄 testimoni.php             # Testimonials
│   ├── 📄 booking.php               # Booking Form
│   ├── 📄 faq.php                   # FAQ Section
│   ├── 📄 cabang.php                # 12 Cabang
│   ├── 📄 kontak.php                # Contact Section
│   └── 📄 footer.php                # Footer
│
├── 📁 docs/                          # Documentation
│   ├── 📄 README.md                 # Main documentation
│   ├── 📄 INSTALL.md                # Installation guide
│   ├── 📄 CHANGELOG.md              # Version history
│   └── 📄 PROJECT_STRUCTURE.md      # This file
│
└── 📄 BUGAR REFLEKSII.png           # Logo image

```

## 📋 File Details

### Core Files

#### index.php (Main Entry Point)
- **Fungsi**: File utama yang meng-include semua komponen
- **Lines**: ~50 lines
- **Includes**:
  - All PHP components dari folder includes/
  - External CSS dan JavaScript
  - Floating WhatsApp button
  - Scroll to top button

#### config.php (Configuration)
- **Fungsi**: Konfigurasi website & helper functions
- **Lines**: ~250 lines
- **Contains**:
  - Website constants
  - Contact information
  - Database config template
  - Email config template
  - Security functions
  - Helper functions
  - Service constants

#### .htaccess (Apache Configuration)
- **Fungsi**: Server configuration untuk Apache
- **Lines**: ~85 lines
- **Features**:
  - URL rewriting
  - Compression (gzip)
  - Browser caching
  - Security headers
  - Error pages
  - PHP configuration

#### .gitignore (Git Ignore)
- **Fungsi**: Files/folders yang diabaikan Git
- **Lines**: ~120 lines
- **Ignores**:
  - IDE files
  - OS files
  - Logs
  - Backups
  - Dependencies
  - Sensitive data

---

### Assets Files

#### assets/css/style.css
- **Fungsi**: Semua styling untuk website
- **Lines**: 1813 lines
- **Sections**:
  1. Base Styles (reset, variables, body)
  2. Animations (fadeIn, scale, float, pulse, etc)
  3. Loading Screen
  4. Search Box
  5. Navigation (navbar, mobile menu)
  6. Hero Section
  7. Features
  8. Pricing (paket & toggle)
  9. Services (layanan)
  10. Gallery
  11. Testimonials (slider)
  12. Booking Form
  13. FAQ (accordion)
  14. Branches (cabang)
  15. Contact
  16. Footer
  17. Floating Buttons (WhatsApp, Scroll Top)
  18. Responsive (breakpoints: 1200px, 1024px, 768px, 480px)

**Key Features:**
- CSS Variables untuk easy theming
- Mobile-first approach
- Smooth animations
- Dark sections
- Glassmorphism effects
- Gradient backgrounds

#### assets/js/script.js
- **Fungsi**: Semua interaksi dan functionality
- **Lines**: 492 lines
- **Features**:
  1. Loading Screen Animation
  2. Mobile Menu Toggle
  3. Smooth Scroll Navigation
  4. Sticky Navbar
  5. Scroll Reveal Animations
  6. Price Category Toggle
  7. Testimonial Slider
  8. FAQ Accordion
  9. Search Functionality
  10. Form Validation
  11. Scroll to Top
  12. Mobile Optimizations
  13. Touch Feedback
  14. Lazy Loading

**Mobile Optimizations:**
- Touch event listeners
- Debounced scroll
- Vibration feedback
- iOS/Android fixes
- Performance optimization

---

### Include Files

#### includes/header.php
- **Lines**: ~40 lines
- **Contains**:
  - Loading screen overlay
  - Sticky navigation bar
  - Logo
  - Navigation menu (8 items)
  - Search box
  - Mobile menu toggle
  - Promo banner

#### includes/hero.php
- **Lines**: ~30 lines
- **Contains**:
  - Hero heading & description
  - 2 CTA buttons
  - 3 statistics (Tahun, Cabang, Jam)
  - Hero image placeholder

#### includes/features.php
- **Lines**: ~28 lines
- **Contains**:
  - Section header
  - 4 feature cards dengan icons

#### includes/paket.php
- **Lines**: ~183 lines
- **Contains**:
  - Section header
  - 3 toggle buttons (Paket, Non-Paket, Panggilan)
  - 7 price cards untuk Paket Hemat
  - 6 price cards untuk Non-Paket
  - 7 price cards untuk Panggilan (Home/Hotel)
  - Homecare indicators

#### includes/layanan.php
- **Lines**: ~39 lines
- **Contains**:
  - Section header
  - 6 service cards

#### includes/galeri.php
- **Lines**: ~33 lines
- **Contains**:
  - Section header
  - 6 gallery items dengan placeholder icons

#### includes/testimoni.php
- **Lines**: ~32 lines
- **Contains**:
  - Section header
  - Testimonial slider dengan 3 cards
  - Navigation controls
  - Rating stars

#### includes/booking.php
- **Lines**: ~103 lines
- **Contains**:
  - Booking form dengan 9 fields:
    1. Nama
    2. Email
    3. Telepon
    4. Pilih Layanan (dropdown)
    5. Pilih Terapis (dropdown)
    6. Tanggal
    7. Waktu
    8. Alamat (jika panggilan)
    9. Catatan Tambahan
  - Submit button
  - Form validation

#### includes/faq.php
- **Lines**: ~55 lines
- **Contains**:
  - Section header
  - 5 FAQ items dengan accordion

#### includes/cabang.php
- **Lines**: ~29 lines
- **Contains**:
  - Section header
  - 12 branch cards dengan:
    - Numbering (01-12)
    - Nama cabang
    - Alamat

#### includes/kontak.php
- **Lines**: ~50 lines
- **Contains**:
  - Section header
  - 3 contact buttons:
    - WhatsApp
    - Telepon
    - Email

#### includes/footer.php
- **Lines**: ~35 lines
- **Contains**:
  - Company info
  - Navigation links
  - Service links
  - Contact info
  - Copyright

---

## 📊 Statistics

### Code Statistics
```
Total Lines of Code:
- PHP:        ~850 lines
- CSS:       1813 lines
- JavaScript: 492 lines
- Total:     3155 lines

Total Files: 24 files
- PHP:  13 files
- CSS:   1 file
- JS:    1 file
- Docs:  5 files
- Config: 4 files
```

### File Sizes (Approximate)
```
index.php:      2 KB
config.php:    10 KB
style.css:     65 KB
script.js:     18 KB

Total Assets:  83 KB (before compression)
             ~25 KB (after gzip)
```

### Component Breakdown
```
Header:      40 lines
Hero:        30 lines
Features:    28 lines
Paket:      183 lines
Layanan:     39 lines
Galeri:      33 lines
Testimoni:   32 lines
Booking:    103 lines
FAQ:         55 lines
Cabang:      29 lines
Kontak:      50 lines
Footer:      35 lines
```

---

## 🔄 Data Flow

```
User Request
     ↓
index.php (Entry Point)
     ↓
config.php (Load configuration)
     ↓
Include Components:
     ├─ header.php
     ├─ hero.php
     ├─ features.php
     ├─ paket.php
     ├─ layanan.php
     ├─ galeri.php
     ├─ testimoni.php
     ├─ booking.php
     ├─ faq.php
     ├─ cabang.php
     ├─ kontak.php
     └─ footer.php
     ↓
Load Assets:
     ├─ style.css
     └─ script.js
     ↓
Render Complete Page
     ↓
User Interaction
```

---

## 🎯 Best Practices

### File Organization
✅ Separation of concerns (HTML/CSS/JS)
✅ Modular component structure
✅ Clear naming conventions
✅ Logical folder structure
✅ Comprehensive documentation

### Code Quality
✅ Semantic HTML5
✅ BEM-like CSS naming
✅ Vanilla JavaScript (no dependencies)
✅ Mobile-first responsive
✅ Cross-browser compatible
✅ Accessibility friendly

### Performance
✅ Minification ready
✅ Compression enabled
✅ Caching configured
✅ Lazy loading
✅ Optimized animations

### Security
✅ XSS protection
✅ CSRF token support
✅ Input sanitization
✅ SQL injection prevention
✅ Secure headers

---

## 🔧 Customization Points

### Easy Customization:
1. **Colors**: Edit CSS variables in `style.css`
2. **Content**: Edit individual include files
3. **Config**: Update `config.php`
4. **Logo**: Replace image file
5. **Contact Info**: Edit in multiple files

### Advanced Customization:
1. **Add Database**: Uncomment DB config in `config.php`
2. **Add Email**: Setup SMTP in `config.php`
3. **Add Pages**: Create new include files
4. **Add Features**: Extend JavaScript in `script.js`
5. **Custom Styling**: Add to `style.css`

---

## 📝 Maintenance

### Regular Tasks:
- [ ] Backup files weekly
- [ ] Update contact info when changed
- [ ] Review analytics monthly
- [ ] Check broken links
- [ ] Update testimonials
- [ ] Add new gallery images
- [ ] Review and update FAQ

### Periodic Tasks:
- [ ] Update PHP version
- [ ] Renew SSL certificate
- [ ] Update dependencies (if any)
- [ ] SEO audit quarterly
- [ ] Performance audit
- [ ] Security audit

---

## 🎓 Learning Resources

### PHP Basics:
- https://www.php.net/manual/en/
- https://www.w3schools.com/php/

### CSS Advanced:
- https://css-tricks.com/
- https://developer.mozilla.org/en-US/docs/Web/CSS

### JavaScript:
- https://javascript.info/
- https://developer.mozilla.org/en-US/docs/Web/JavaScript

### Responsive Design:
- https://web.dev/responsive-web-design-basics/
- https://www.w3schools.com/css/css_rwd_intro.asp

---

**Last Updated**: February 08, 2026
**Version**: 1.0.0
**Maintained by**: Bugar Refleksi Dev Team
