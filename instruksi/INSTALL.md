# 🚀 Panduan Instalasi Bugar Refleksi

Panduan lengkap untuk menginstall dan menjalankan website Bugar Refleksi.

## 📋 Persyaratan Sistem

### Minimum Requirements:
- PHP 7.4 atau lebih tinggi
- Web Server (Apache/Nginx) atau PHP Built-in Server
- Browser modern (Chrome, Firefox, Safari, Edge)

### Recommended Requirements:
- PHP 8.0+
- Apache 2.4+ dengan mod_rewrite enabled
- MySQL 5.7+ (jika menggunakan database)
- SSL Certificate (untuk HTTPS)

## 📦 Metode Instalasi

### Metode 1: XAMPP (Windows/Mac/Linux)

#### 1. Download dan Install XAMPP
```
https://www.apachefriends.org/download.html
```

#### 2. Copy Project ke htdocs
```bash
# Windows
C:\xampp\htdocs\bugar-refleksi

# Mac/Linux
/Applications/XAMPP/htdocs/bugar-refleksi
```

#### 3. Start Apache
- Buka XAMPP Control Panel
- Klik "Start" pada Apache

#### 4. Akses Website
```
http://localhost/bugar-refleksi
```

---

### Metode 2: WAMP (Windows Only)

#### 1. Download dan Install WAMP
```
https://www.wampserver.com/en/
```

#### 2. Copy Project ke www
```bash
C:\wamp64\www\bugar-refleksi
```

#### 3. Start Server
- Klik icon WAMP di system tray
- Pastikan icon berwarna hijau

#### 4. Akses Website
```
http://localhost/bugar-refleksi
```

---

### Metode 3: PHP Built-in Server (Semua Platform)

#### 1. Buka Terminal/Command Prompt

#### 2. Navigate ke Folder Project
```bash
cd path/to/bugar-refleksi
```

#### 3. Jalankan PHP Server
```bash
php -S localhost:8000
```

#### 4. Akses Website
```
http://localhost:8000
```

**Keuntungan:**
- Tidak perlu install XAMPP/WAMP
- Cepat untuk development
- Ringan

**Kekurangan:**
- Hanya untuk development (bukan production)
- Tidak mendukung .htaccess
- Single-threaded

---

### Metode 4: MAMP (Mac Only)

#### 1. Download dan Install MAMP
```
https://www.mamp.info/en/downloads/
```

#### 2. Copy Project ke htdocs
```bash
/Applications/MAMP/htdocs/bugar-refleksi
```

#### 3. Start Server
- Buka MAMP
- Klik "Start Servers"

#### 4. Akses Website
```
http://localhost:8888/bugar-refleksi
```

---

### Metode 5: Production Server (Linux/cPanel/VPS)

#### A. Via cPanel

1. **Login ke cPanel**

2. **Upload Files**
   - Buka "File Manager"
   - Navigate ke `public_html`
   - Upload semua file project
   - Extract jika berupa ZIP

3. **Set Permissions**
   ```
   Folders: 755
   Files: 644
   ```

4. **Akses Website**
   ```
   http://yourdomain.com
   ```

#### B. Via SSH (VPS/Dedicated Server)

1. **Connect via SSH**
   ```bash
   ssh user@your-server-ip
   ```

2. **Navigate ke Web Root**
   ```bash
   cd /var/www/html
   # atau
   cd /home/username/public_html
   ```

3. **Clone atau Upload Project**
   
   Via Git:
   ```bash
   git clone https://github.com/yourusername/bugar-refleksi.git
   cd bugar-refleksi
   ```
   
   Via SCP:
   ```bash
   scp -r bugar-refleksi user@server:/var/www/html/
   ```

4. **Set Ownership dan Permissions**
   ```bash
   sudo chown -R www-data:www-data bugar-refleksi
   sudo chmod -R 755 bugar-refleksi
   sudo chmod 644 bugar-refleksi/config.php
   ```

5. **Configure Apache Virtual Host**
   ```bash
   sudo nano /etc/apache2/sites-available/bugarrefleksi.conf
   ```
   
   ```apache
   <VirtualHost *:80>
       ServerName bugarrefleksi.com
       ServerAlias www.bugarrefleksi.com
       DocumentRoot /var/www/html/bugar-refleksi
       
       <Directory /var/www/html/bugar-refleksi>
           AllowOverride All
           Require all granted
       </Directory>
       
       ErrorLog ${APACHE_LOG_DIR}/bugarrefleksi_error.log
       CustomLog ${APACHE_LOG_DIR}/bugarrefleksi_access.log combined
   </VirtualHost>
   ```

6. **Enable Site dan Rewrite Module**
   ```bash
   sudo a2ensite bugarrefleksi.conf
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

7. **Setup SSL (Recommended)**
   ```bash
   sudo apt install certbot python3-certbot-apache
   sudo certbot --apache -d bugarrefleksi.com -d www.bugarrefleksi.com
   ```

---

## ⚙️ Konfigurasi

### 1. Edit config.php

```bash
nano config.php
```

Update bagian berikut:

```php
// Website URL
define('SITE_URL', 'http://yourdomain.com');

// Contact Information
define('CONTACT_PHONE', '+62 xxx-xxxx-xxxx');
define('CONTACT_WHATSAPP', '62xxxxxxxxxx');
define('CONTACT_EMAIL', 'your-email@domain.com');
```

### 2. Edit .htaccess (untuk Apache)

Jika menggunakan HTTPS, uncomment baris berikut:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 3. Ganti Logo

Replace file `BUGAR REFLEKSII.png` dengan logo Anda:
- Ukuran recommended: 300x100px
- Format: PNG dengan background transparent
- Lokasi: root folder project

---

## 🔧 Troubleshooting

### 1. Error 403 Forbidden

**Solusi:**
```bash
# Set permissions
chmod 755 /path/to/bugar-refleksi
chmod 644 /path/to/bugar-refleksi/index.php

# Check Apache config
sudo nano /etc/apache2/sites-available/000-default.conf
# Pastikan AllowOverride All
```

### 2. Error 404 Not Found

**Solusi:**
```bash
# Enable mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2

# Check .htaccess
# Pastikan file .htaccess ada dan readable
```

### 3. PHP Errors Muncul

**Solusi:**
Edit `php.ini`:
```ini
display_errors = Off
error_reporting = E_ALL & ~E_NOTICE & ~E_DEPRECATED
```

Atau edit `config.php`:
```php
error_reporting(0);
ini_set('display_errors', 0);
```

### 4. CSS/JS Tidak Load

**Solusi:**
```bash
# Check file paths di index.php
# Pastikan path relatif benar:
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/script.js"></script>

# Bukan:
<link rel="stylesheet" href="/assets/css/style.css">
```

### 5. WhatsApp Button Tidak Berfungsi

**Solusi:**
- Check nomor WhatsApp di `index.php` dan `footer.php`
- Format: `https://wa.me/62xxxxxxxxxx`
- Tanpa tanda +, -, atau spasi

---

## 🔒 Security Checklist

### Before Going Live:

- [ ] Update semua nomor telepon dan email
- [ ] Set `display_errors = Off` di PHP
- [ ] Enable HTTPS dengan SSL Certificate
- [ ] Set proper file permissions (755/644)
- [ ] Remove atau protect config.php
- [ ] Setup regular backups
- [ ] Enable firewall
- [ ] Update PHP ke versi terbaru
- [ ] Scan untuk malware
- [ ] Test semua forms

---

## 📊 Performance Optimization

### 1. Enable Gzip Compression
Already configured in `.htaccess`

### 2. Enable Browser Caching
Already configured in `.htaccess`

### 3. Minify CSS/JS (Optional)

**Online Tools:**
- CSS: https://cssminifier.com/
- JS: https://javascript-minifier.com/

**Replace:**
```
assets/css/style.css → assets/css/style.min.css
assets/js/script.js → assets/js/script.min.js
```

Update di `index.php`:
```html
<link rel="stylesheet" href="assets/css/style.min.css">
<script src="assets/js/script.min.js"></script>
```

### 4. Optimize Images

**Tools:**
- TinyPNG: https://tinypng.com/
- ImageOptim (Mac): https://imageoptim.com/

**Recommended:**
- Logo: PNG, < 50KB
- Gallery: JPG/WebP, < 200KB
- Icons: SVG when possible

---

## 🔄 Update & Maintenance

### Backup Strategy:

**Weekly:**
```bash
# Backup files
tar -czf backup-$(date +%Y%m%d).tar.gz bugar-refleksi/

# Backup database (jika ada)
mysqldump -u username -p database_name > backup-$(date +%Y%m%d).sql
```

**Store backups:**
- Local external drive
- Cloud storage (Google Drive, Dropbox)
- Remote server

### Update PHP:
```bash
# Check current version
php -v

# Update (Ubuntu/Debian)
sudo apt update
sudo apt upgrade php

# Restart server
sudo systemctl restart apache2
```

---

## 📞 Support

Jika mengalami masalah:

1. Check documentation di `README.md`
2. Review troubleshooting di atas
3. Search online untuk error message
4. Contact developer

---

## ✅ Checklist Setelah Install

- [ ] Website bisa diakses
- [ ] Semua halaman load dengan benar
- [ ] Navigasi berfungsi
- [ ] Forms berfungsi (booking, contact)
- [ ] WhatsApp button berfungsi
- [ ] Mobile responsive
- [ ] Search berfungsi
- [ ] FAQ accordion berfungsi
- [ ] Testimonial slider berfungsi
- [ ] All links working
- [ ] Images loading
- [ ] No console errors
- [ ] SSL enabled (production)

---

**Selamat! Website Bugar Refleksi sudah siap digunakan! 🎉**
