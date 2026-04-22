<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bugar Refleksi - Terapi Pijat Refleksi & Home Care Aceh</title>
    <meta name="description" content="Bugar Refleksi - Pusat terapi pijat refleksi terpercaya di Aceh. Melayani 12 cabang, panggilan ke rumah, dan layanan hotel premium.">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <?php include 'includes/hero.php'; ?>
    
    <?php include 'includes/features.php'; ?>
    
    <?php include 'includes/paket.php'; ?>
    
    <?php include 'includes/layanan.php'; ?>
    
    <?php include 'includes/galeri.php'; ?>
    
    <?php include 'includes/testimoni.php'; ?>
    
    <?php include 'includes/booking.php'; ?>
    
    <?php include 'includes/faq.php'; ?>
    
    <?php include 'includes/cabang.php'; ?>
    
    <?php include 'includes/kontak.php'; ?>
    
    <?php include 'includes/footer.php'; ?>

    <!-- WhatsApp Float Button -->
    <a href="https://wa.me/628116806666" class="whatsapp-float" target="_blank" aria-label="Contact via WhatsApp">
        <img src="https://img.icons8.com/?size=100&id=uZWiLUyryScN&format=png&color=000000" alt="WhatsApp Icon" width="48" height="48">
    </a>

    <!-- Scroll to Top Button -->
    <div class="scroll-top" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Kembali ke atas">
        ↑
    </div>

    <!-- JavaScript -->
    <script src="assets/js/script.js"></script>

    <script>
        /* Tampilkan tombol scroll saat user scroll ke bawah */
        var scrollBtn = document.getElementById('scrollTopBtn');
        window.addEventListener('scroll', function() {
            if (window.scrollY > 400) {
                scrollBtn.classList.add('show');
            } else {
                scrollBtn.classList.remove('show');
            }
        });
    </script>
</body>
</html>