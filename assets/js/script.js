window.addEventListener('load', () => {
    setTimeout(() => {
        document.querySelector('.loading-screen').classList.add('hidden');
    }, 1000);
});

// Mobile Menu
const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
const navLinks = document.querySelector('.nav-links');

mobileMenuBtn.addEventListener('click', () => {
    mobileMenuBtn.classList.toggle('active');
    navLinks.classList.toggle('active');
});

document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', () => {
        mobileMenuBtn.classList.remove('active');
        navLinks.classList.remove('active');
    });
});

// Smooth Scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// Navbar Scroll Effect
window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 100) {
        navbar.style.background = 'rgba(255, 255, 255, 0.98)';
        navbar.style.boxShadow = '0 2px 30px rgba(0, 0, 0, 0.1)';
    } else {
        navbar.style.background = 'rgba(255, 255, 255, 0.95)';
        navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.05)';
    }
});

// Pricing Toggle Logic (Updated for 3 Tabs)
const toggleBtns = document.querySelectorAll('.toggle-btn');
const paketContent = document.getElementById('paket-content');
const nonPaketContent = document.getElementById('non-paket-content');
const panggilanContent = document.getElementById('panggilan-content');

toggleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        toggleBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        const type = btn.dataset.type;
        
        // Hide all
        paketContent.style.display = 'none';
        nonPaketContent.style.display = 'none';
        panggilanContent.style.display = 'none';

        // Show selected
        if (type === 'paket') paketContent.style.display = 'grid';
        else if (type === 'non-paket') nonPaketContent.style.display = 'grid';
        else if (type === 'panggilan') panggilanContent.style.display = 'grid';
    });
});

// Helper to select service and scroll to booking
function selectService(serviceName, locationType) {
    document.getElementById('layanan').value = serviceName; // Note: might not match exactly if option doesn't exist, but good for UX
    
    const locSelect = document.getElementById('tipe_kunjungan');
    if (locationType.includes('Rumah')) locSelect.value = 'Home Care';
    else if (locationType.includes('Hotel')) locSelect.value = 'Hotel Visit';
    else locSelect.value = 'Outlet';
    
    toggleAddressField();
    
    // Try to set service dropdown if matches
    const options = document.getElementById('layanan').options;
    for(let i=0; i<options.length; i++) {
        if(options[i].text.includes(serviceName) || options[i].value.includes(serviceName)) {
            document.getElementById('layanan').selectedIndex = i;
            break;
        }
    }
}

// Booking Logic: Toggle Address Field
function toggleAddressField() {
    const tipe = document.getElementById('tipe_kunjungan').value;
    const alamatContainer = document.getElementById('alamat-container');
    const infoText = document.getElementById('tipe_info');
    const alamatInput = document.getElementById('alamat');

    if (tipe === 'Home Care' || tipe === 'Hotel Visit') {
        alamatContainer.style.display = 'block';
        alamatInput.required = true;
        
        if (tipe === 'Home Care') {
            infoText.innerText = "Terapis akan datang ke rumah Anda. Biaya bensin 20rb-30rb (sesuai jarak).";
            alamatInput.placeholder = "Alamat Lengkap Rumah (Jalan, Nomor, Patokan)";
        } else {
            infoText.innerText = "Layanan di kamar Hotel. Tersedia harga normal (+transport) & premium.";
            alamatInput.placeholder = "Nama Hotel & Nomor Kamar";
        }
    } else {
        alamatContainer.style.display = 'none';
        alamatInput.required = false;
        infoText.innerText = "Silakan datang ke outlet kami sesuai jadwal.";
    }
}

// Booking Form Submission
const bookingForm = document.getElementById('bookingForm');
bookingForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const formData = {
        nama: document.getElementById('nama').value,
        telepon: document.getElementById('telepon').value,
        cabang: document.getElementById('cabang').value,
        tipe: document.getElementById('tipe_kunjungan').value,
        alamat: document.getElementById('alamat').value,
        layanan: document.getElementById('layanan').value,
        tanggal: document.getElementById('tanggal').value,
        waktu: document.getElementById('waktu').value,
        catatan: document.getElementById('catatan').value
    };

    let locationDetails = "";
    if (formData.tipe !== 'Outlet') {
        locationDetails = `\n📍 *Alamat/Hotel:* ${formData.alamat}`;
        if (formData.tipe === 'Home Care') {
            locationDetails += `\n⚠️ _Note: Siap bayar transport (20-30k)_`;
        }
    } else {
        locationDetails = `\n📍 *Lokasi:* Datang ke Outlet ${formData.cabang}`;
    }

    const message = `
*BOOKING BARU - BUGAR REFLEKSI*

👤 Nama: ${formData.nama}
📞 Telp: ${formData.telepon}
🏠 Tipe: ${formData.tipe}${locationDetails}
💆 Layanan: ${formData.layanan}
📅 Tanggal: ${formData.tanggal}
⏰ Waktu: ${formData.waktu}
📝 Catatan: ${formData.catatan || '-'}
    `.trim();

    const whatsappUrl = `https://wa.me/6283186645262?text=${encodeURIComponent(message)}`;
    window.open(whatsappUrl, '_blank');
});

// Testimonials Auto Slide
let currentSlide = 0;
const testimonialCards = document.querySelectorAll('.testimonial-card');
const sliderDots = document.querySelectorAll('.slider-dot');

function showSlide(index) {
    testimonialCards.forEach(card => card.classList.remove('active'));
    sliderDots.forEach(dot => dot.classList.remove('active'));
    testimonialCards[index].classList.add('active');
    sliderDots[index].classList.add('active');
}

sliderDots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
        currentSlide = index;
        showSlide(currentSlide);
    });
});

setInterval(() => {
    currentSlide = (currentSlide + 1) % testimonialCards.length;
    showSlide(currentSlide);
}, 5000);

// FAQ Accordion
document.querySelectorAll('.faq-question').forEach(q => {
    q.addEventListener('click', () => {
        const item = q.parentElement;
        const isActive = item.classList.contains('active');
        document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('active'));
        if (!isActive) item.classList.add('active');
    });
});

// Scroll Top
const scrollTopBtn = document.querySelector('.scroll-top');
window.addEventListener('scroll', () => {
    if (window.scrollY > 500) scrollTopBtn.classList.add('show');
    else scrollTopBtn.classList.remove('show');
});
scrollTopBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

// Min Date
document.getElementById('tanggal').setAttribute('min', new Date().toISOString().split('T')[0]);

// ===== ANIMASI SCROLL REVEAL YANG DITINGKATKAN =====
// Intersection Observer untuk animasi scroll reveal
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const scrollObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate');
        }
    });
}, observerOptions);

// Observe section headers
document.querySelectorAll('.section-header').forEach(el => scrollObserver.observe(el));

// Observe feature cards dengan stagger delay
document.querySelectorAll('.feature-card').forEach((el, index) => {
    el.style.transitionDelay = `${index * 0.1}s`;
    scrollObserver.observe(el);
});

// Observe price cards dengan stagger delay
document.querySelectorAll('.price-card').forEach((el, index) => {
    el.style.transitionDelay = `${index * 0.1}s`;
    scrollObserver.observe(el);
});

// Observe service cards dengan stagger delay
document.querySelectorAll('.service-card').forEach((el, index) => {
    el.style.transitionDelay = `${index * 0.1}s`;
    scrollObserver.observe(el);
});

// Observe gallery items dengan stagger delay
document.querySelectorAll('.gallery-item').forEach((el, index) => {
    el.style.transitionDelay = `${index * 0.1}s`;
    scrollObserver.observe(el);
});

// Observe branch cards dengan stagger delay
document.querySelectorAll('.branch-card').forEach((el, index) => {
    el.style.transitionDelay = `${index * 0.05}s`;
    scrollObserver.observe(el);
});

// Observe FAQ items dengan stagger delay
document.querySelectorAll('.faq-item').forEach((el, index) => {
    el.style.transitionDelay = `${index * 0.1}s`;
    scrollObserver.observe(el);
});

// ===== MOBILE OPTIMIZATIONS =====

// Detect if user is on mobile
const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

// Improve mobile menu experience
if (isMobile) {
    // Close mobile menu when clicking outside
    document.addEventListener('click', (e) => {
        const navLinks = document.querySelector('.nav-links');
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        
        if (navLinks.classList.contains('active') && 
            !navLinks.contains(e.target) && 
            !mobileMenuBtn.contains(e.target)) {
            mobileMenuBtn.classList.remove('active');
            navLinks.classList.remove('active');
        }
    });
    
    // Prevent body scroll when mobile menu is open
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    mobileMenuBtn.addEventListener('click', () => {
        if (document.querySelector('.nav-links').classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    });
    
    // Re-enable body scroll when menu item is clicked
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', () => {
            document.body.style.overflow = '';
        });
    });
}

// Smooth scroll offset for fixed navbar on mobile
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            const offset = window.innerWidth <= 768 ? 70 : 80;
            const targetPosition = target.offsetTop - offset;
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
        }
    });
});

// Optimize scroll performance on mobile
let scrollTimeout;
let lastScrollTop = 0;

window.addEventListener('scroll', () => {
    clearTimeout(scrollTimeout);
    
    // Debounce scroll events for better mobile performance
    scrollTimeout = setTimeout(() => {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Hide/show navbar on scroll for more screen space on mobile
        if (isMobile && window.innerWidth <= 768) {
            const navbar = document.querySelector('.navbar');
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                navbar.style.transform = 'translateY(-100%)';
            } else {
                // Scrolling up
                navbar.style.transform = 'translateY(0)';
            }
        }
        
        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
    }, 100);
}, { passive: true });

// Add touch feedback for buttons on mobile
if (isMobile) {
    const touchElements = document.querySelectorAll('.btn, .toggle-btn, .contact-btn, .submit-btn, .price-card, .feature-card, .service-card');
    
    touchElements.forEach(el => {
        el.addEventListener('touchstart', function() {
            this.style.opacity = '0.8';
        }, { passive: true });
        
        el.addEventListener('touchend', function() {
            this.style.opacity = '1';
        }, { passive: true });
    });
}

// Lazy load images for better mobile performance
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            }
        });
    });
    
    document.querySelectorAll('img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
}

// Prevent zoom on double tap for better UX
let lastTouchEnd = 0;
document.addEventListener('touchend', (event) => {
    const now = Date.now();
    if (now - lastTouchEnd <= 300) {
        event.preventDefault();
    }
    lastTouchEnd = now;
}, false);

// Auto-resize textarea on mobile
const textareas = document.querySelectorAll('textarea');
textareas.forEach(textarea => {
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});

// Vibration feedback on important actions (if supported)
if ('vibrate' in navigator && isMobile) {
    document.querySelector('.submit-btn')?.addEventListener('click', () => {
        navigator.vibrate(50); // Short vibration on submit
    });
}
// ===== SEARCH FUNCTIONALITY =====
const searchBox = document.getElementById('searchBox');
const searchBtn = document.getElementById('searchBtn');
const searchResults = document.getElementById('searchResults');

// Data untuk pencarian
const searchData = [
    { title: 'Paket 1 - Rp 100K', description: 'Refleksi + Body Massage + Pijat Kepala', url: '#paket' },
    { title: 'Paket 2 - Rp 120K', description: 'Refleksi + Body Massage + Pijat Kepala + Totok Wajah', url: '#paket' },
    { title: 'Paket 3 - Rp 140K', description: 'Refleksi + Body Massage + Pijat Kepala', url: '#paket' },
    { title: 'Paket 4 - Rp 150K', description: 'Refleksi + Full Body Massage + Pijat Kepala', url: '#paket' },
    { title: 'Paket 5 - Rp 150K', description: 'Refleksi + Body Massage + Hot Stone Therapy', url: '#paket' },
    { title: 'Paket 6 - Rp 170K', description: 'Refleksi + Full Body Massage + Hot Stone Therapy', url: '#paket' },
    { title: 'Paket 7 - Rp 200K', description: 'Refleksi + Full Body Massage + Hot Stone Therapy + Pijat Kepala', url: '#paket' },
    { title: 'Refleksi Kaki', description: 'Terapi pijat kaki dan refleksi titik-titik penting - Rp 60K', url: '#layanan' },
    { title: 'Full Body Massage', description: 'Pijat seluruh tubuh untuk relaksasi total - Rp 120K', url: '#layanan' },
    { title: 'Hot Stone Therapy', description: 'Terapi batu panas untuk relaksasi otot - Rp 80K', url: '#layanan' },
    { title: 'Pijat Punggung', description: 'Fokus pada area punggung dan tulang belakang - Rp 70K', url: '#layanan' },
    { title: 'Terapi Kepala', description: 'Pijat kepala untuk mengurangi stres - Rp 40K', url: '#layanan' },
    { title: 'Terapi Bahu & Leher', description: 'Mengatasi ketegangan bahu dan leher - Rp 50K', url: '#layanan' },
    { title: 'Home Care', description: 'Layanan panggilan ke rumah dengan terapis profesional', url: '#paket' },
    { title: 'Hotel Visit', description: 'Layanan terapi di kamar hotel', url: '#paket' },
    { title: 'Booking Online', description: 'Reservasi layanan terapi secara online', url: '#booking' },
    { title: '12 Cabang di Aceh', description: 'Outlet tersebar di seluruh Aceh', url: '#cabang' },
    { title: 'FAQ', description: 'Pertanyaan yang sering diajukan', url: '#faq' },
    { title: 'Kontak Kami', description: 'Hubungi kami via WhatsApp, Telepon, atau Email', url: '#kontak' },
];

// Fungsi untuk melakukan pencarian
function performSearch(query) {
    const lowerQuery = query.toLowerCase().trim();
    
    if (lowerQuery === '') {
        searchResults.classList.remove('active');
        return;
    }

    const results = searchData.filter(item => 
        item.title.toLowerCase().includes(lowerQuery) || 
        item.description.toLowerCase().includes(lowerQuery)
    );

    displayResults(results);
}

// Fungsi untuk menampilkan hasil
function displayResults(results) {
    if (results.length === 0) {
        searchResults.innerHTML = '<div class="no-results">Tidak ada hasil ditemukan</div>';
        searchResults.classList.add('active');
        return;
    }

    const html = results.map(item => `
        <div class="search-result-item" onclick="navigateTo('${item.url}')">
            <div class="result-title">${item.title}</div>
            <div class="result-description">${item.description}</div>
        </div>
    `).join('');

    searchResults.innerHTML = html;
    searchResults.classList.add('active');
}

// Fungsi untuk navigasi
function navigateTo(url) {
    searchResults.classList.remove('active');
    searchBox.value = '';
    window.location.href = url;
}

// Event listeners
searchBox.addEventListener('input', (e) => {
    performSearch(e.target.value);
});

searchBtn.addEventListener('click', () => {
    performSearch(searchBox.value);
});

searchBox.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        performSearch(searchBox.value);
    }
});

// Tutup hasil pencarian jika klik di luar
document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-container')) {
        searchResults.classList.remove('active');
    }
});
