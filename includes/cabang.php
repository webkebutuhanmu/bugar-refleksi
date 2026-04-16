<!-- =============================================
     SECTION CABANG - FULLY SELF-CONTAINED
     CSS + JS semua di dalam file ini
     ============================================= -->
<style>
.branch-card {
    opacity: 1 !important;
    transform: translateY(0) !important;
    pointer-events: all !important;
    cursor: pointer !important;
    visibility: visible !important;
    background: rgba(255, 255, 255, 0.05) !important;
    padding: 2rem;
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    overflow: hidden;
    user-select: none;
    -webkit-user-select: none;
    transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease !important;
}
.branch-card:hover {
    background: rgba(255, 255, 255, 0.12) !important;
    border-color: #FFD700 !important;
    box-shadow: 0 16px 40px rgba(0,0,0,0.4) !important;
}
.branch-click-hint {
    display: block;
    font-size: 0.72rem;
    color: rgba(255,255,255,0.45);
    margin-top: 10px;
    font-style: italic;
    pointer-events: none;
}
.branch-card:hover .branch-click-hint { color: #00B4D8; }

/* MODAL OVERLAY */
#branchModalOverlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.72);
    z-index: 99999;
    justify-content: center;
    align-items: center;
    padding: 16px;
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
}
#branchModalOverlay.br-open { display: flex; }

#branchModalBox {
    background: #ffffff;
    border-radius: 22px;
    max-width: 580px;
    width: 100%;
    padding: 30px 28px 28px;
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 24px 80px rgba(0,0,0,0.5);
    animation: brModalIn 0.28s cubic-bezier(0.34, 1.3, 0.64, 1) both;
}
@keyframes brModalIn {
    from { opacity:0; transform: scale(0.88) translateY(24px); }
    to   { opacity:1; transform: scale(1) translateY(0); }
}
#branchModalClose {
    position: absolute;
    top: 14px; right: 16px;
    background: #f0f0f0;
    border: none;
    width: 34px; height: 34px;
    border-radius: 50%;
    font-size: 1.25rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s;
    z-index: 2;
}
#branchModalClose:hover { background: #e0e0e0; }

.br-modal-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding-right: 36px;
}
.br-modal-num {
    width: 52px; height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, #00B4D8, #0077A8);
    color: #fff;
    font-size: 1.2rem;
    font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.br-modal-name { font-size: 1.4rem; font-weight: 700; color: #111; margin: 0 0 4px; }
.br-modal-addr { color: #666; font-size: 0.88rem; margin: 0; line-height: 1.4; }

.br-modal-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.br-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 18px; border-radius: 50px;
    font-size: 0.84rem; font-weight: 600;
    color: #fff !important; border: none; cursor: pointer;
    transition: opacity 0.2s, transform 0.2s; white-space: nowrap;
    font-family: inherit; line-height: 1;
    text-decoration: none !important;
}
.br-btn:hover { opacity: 0.87; transform: translateY(-2px); }
.br-btn-wa    { background: #25D366; }
.br-btn-maps  { background: #EA4335; }
.br-btn-phone { background: #00B4D8; }

.br-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 22px; }
.br-badge {
    background: #f0f9ff; border: 1px solid #bee3f8;
    color: #2b6cb0; padding: 5px 13px;
    border-radius: 50px; font-size: 0.78rem; font-weight: 500;
}

/* GRID TERAPIS */
.br-therapist-title {
    font-size: 0.95rem; font-weight: 700; color: #333;
    margin-bottom: 14px; padding-bottom: 8px;
    border-bottom: 2px solid #f0f0f0;
}
.br-therapist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(85px, 1fr));
    gap: 14px;
}
.br-therapist-card {
    display: flex; flex-direction: column;
    align-items: center; gap: 6px; text-align: center;
}

/* FOTO TERAPIS */
.br-therapist-photo {
    width: 72px; height: 72px;
    border-radius: 14px;
    background: linear-gradient(135deg, #e0f7ff, #b3e5fc);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem;
    border: 2px solid #e0f0f8;
    overflow: hidden;
    flex-shrink: 0;
}
.br-therapist-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center top;
    display: block;
    border-radius: 12px;
}
.br-therapist-name { font-size: 0.7rem; font-weight: 700; color: #333; line-height: 1.2; }
.br-therapist-role { font-size: 0.63rem; color: #00B4D8; font-weight: 500; }

@media (max-width: 520px) {
    #branchModalBox { padding: 20px 16px; }
    .br-modal-actions { flex-direction: column; }
    .br-btn { justify-content: center; }
}
</style>

<!-- MODAL -->
<div id="branchModalOverlay">
    <div id="branchModalBox">
        <button id="branchModalClose">&#x2715;</button>
        <div class="br-modal-header">
            <div class="br-modal-num" id="brNum">01</div>
            <div>
                <h2 class="br-modal-name" id="brName">-</h2>
                <p class="br-modal-addr" id="brAddr">-</p>
            </div>
        </div>
        <div class="br-modal-actions">
            <button class="br-btn br-btn-wa"    id="brWA"    onclick="window.open(this.dataset.url,'_blank')">💬 WhatsApp</button>
            <button class="br-btn br-btn-maps"  id="brMaps"  onclick="window.open(this.dataset.url,'_blank')">📍 Google Maps</button>
            <button class="br-btn br-btn-phone" id="brPhone" onclick="window.location.href=this.dataset.url">☎️ Telepon</button>
        </div>
        <div class="br-badges">
            <span class="br-badge" id="brHours">🕐 09.00 – 21.00</span>
            <span class="br-badge">✅ Buka Hari Ini</span>
        </div>
        <div>
            <p class="br-therapist-title">Tim Terapis</p>
            <div class="br-therapist-grid" id="brTherapists"></div>
        </div>
    </div>
</div>

<!-- SECTION CABANG -->
<section class="branches" id="cabang">
    <div class="section-header">
        <h2 class="section-title" style="color:#fff;">12 Cabang Kami di Aceh</h2>
        <p class="section-subtitle" style="color:rgba(40, 111, 8, 0.7);">Klik cabang untuk melihat detail, terapis, dan kontak langsung</p>
    </div>
    <div class="branches-grid">
        <div class="branch-card" data-branch="peunayong1">
            <div class="branch-number">01</div>
            <h3 class="branch-name">Peunayong 1</h3>
            <p class="branch-address">Jl. Medan-Banda Aceh, Komplek Pasar</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
        <div class="branch-card" data-branch="peunayong2">
            <div class="branch-number">02</div>
            <h3 class="branch-name">Peunayong 2</h3>
            <p class="branch-address">Jl. T. Panglima Nyak Makam No. 45</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
        <div class="branch-card" data-branch="neusu">
            <div class="branch-number">03</div>
            <h3 class="branch-name">Neusu</h3>
            <p class="branch-address">Jl. Hasan Saleh, Neusu Jaya, Banda Aceh</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
        <div class="branch-card" data-branch="batoh">
            <div class="branch-number">04</div>
            <h3 class="branch-name">Batoh</h3>
            <p class="branch-address">Banda Aceh</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
        <div class="branch-card" data-branch="lampeneurut">
            <div class="branch-number">05</div>
            <h3 class="branch-name">Lampeneurut</h3>
            <p class="branch-address">Aceh Besar</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
        <div class="branch-card" data-branch="sigli">
            <div class="branch-number">06</div>
            <h3 class="branch-name">Sigli</h3>
            <p class="branch-address">Pidie</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
        <div class="branch-card" data-branch="bireuen">
            <div class="branch-number">07</div>
            <h3 class="branch-name">Bireuen</h3>
            <p class="branch-address">Bireuen</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
        <div class="branch-card" data-branch="takengon">
            <div class="branch-number">08</div>
            <h3 class="branch-name">Takengon</h3>
            <p class="branch-address">Aceh Tengah</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
        <div class="branch-card" data-branch="sabang">
            <div class="branch-number">09</div>
            <h3 class="branch-name">Sabang</h3>
            <p class="branch-address">Kota Sabang</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
        <div class="branch-card" data-branch="langsa">
            <div class="branch-number">10</div>
            <h3 class="branch-name">Langsa</h3>
            <p class="branch-address">Kota Langsa</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
        <div class="branch-card" data-branch="lhokseumawe">
            <div class="branch-number">11</div>
            <h3 class="branch-name">Lhokseumawe</h3>
            <p class="branch-address">Kota Lhokseumawe</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
        <div class="branch-card" data-branch="subulussalam">
            <div class="branch-number">12</div>
            <h3 class="branch-name">Subulussalam</h3>
            <p class="branch-address">Subulussalam</p>
            <span class="branch-click-hint">Klik untuk detail →</span>
        </div>
    </div>
</section>

<script>
(function() {

    /* ================================================
       DATA CABANG
       - Ganti nomor WA & maps sesuai data asli
       - Ganti img: "assets/img/namafile.jpg" sesuai
         nama file foto yang kamu upload ke assets/img/
       - Jika foto belum ada, isi img: "" maka tampil
         ikon default otomatis
       ================================================ */
    var CABANG = {
        peunayong1: {
            num:"01", name:"Peunayong 1",
            addr:"Jl. Medan-Banda Aceh, Komplek Pasar Peunayong, Banda Aceh",
            wa:"6285167933801", phone:"6282162126499",
            maps:"https://maps.google.com/?q=Peunayong+Banda+Aceh",
            hours:"08.00 – 22.00",
            therapists:[
                { name:"Pak Rizal",  role:"Senior Terapis", img:"assets/img/yusuf.jpg"  },
                { name:"Bu Aisyah",  role:"Terapis",         img:"assets/img/terapis_aisyah.jpg" },
                { name:"Pak Dedi",   role:"Terapis",         img:"assets/img/terapis_dedi.jpg"   },
                { name:"Bu Rini",    role:"Terapis",         img:"assets/img/terapis_rini.jpg"   }
            ]
        },
        peunayong2: {
            num:"02", name:"Peunayong 2",
            addr:"Jl. T. Panglima Nyak Makam No. 45, Peunayong, Banda Aceh",
            wa:"6281234567801", phone:"6281234567801",
            maps:"https://maps.google.com/?q=Peunayong+2+Banda+Aceh",
            hours:"08.00 – 22.00 (24 Jam Hotel)",
            therapists:[
                { name:"Pak Fajar",  role:"Senior Terapis", img:"assets/img/terapis_fajar.jpg"  },
                { name:"Bu Yanti",   role:"Terapis",         img:"assets/img/terapis_yanti.jpg"  },
                { name:"Pak Hendra", role:"Terapis",         img:"assets/img/terapis_hendra.jpg" },
                { name:"Bu Sari",    role:"Terapis",         img:"assets/img/terapis_sari.jpg"   }
            ]
        },
        neusu: {
            num:"03", name:"Neusu",
            addr:"Jl. Hasan Saleh, Neusu Jaya, Kec. Baiturrahman, Kota Banda Aceh 23116",
            wa:"6281234567802", phone:"6281234567802",
            maps:"https://maps.app.goo.gl/gsUZrTDaxteWBom59",
            hours:"09.00 – 21.00",
            therapists:[
                { name:"Pak Andi",  role:"Senior Terapis", img:"assets/img/terapis_andi.jpg"  },
                { name:"Bu Nurul",  role:"Terapis",         img:"assets/img/terapis_nurul.jpg" },
                { name:"Pak Budi",  role:"Terapis",         img:"assets/img/terapis_budi.jpg"  }
            ]
        },
        batoh: {
            num:"04", name:"Batoh",
            addr:"Jl. Batoh, Kec. Lueng Bata, Kota Banda Aceh, Aceh",
            wa:"6281234567803", phone:"6281234567803",
            maps:"https://maps.google.com/?q=Batoh+Banda+Aceh",
            hours:"09.00 – 21.00",
            therapists:[
                { name:"Pak Wahyu", role:"Senior Terapis", img:"assets/img/terapis_wahyu.jpg" },
                { name:"Bu Maya",   role:"Terapis",         img:"assets/img/terapis_maya.jpg"  },
                { name:"Pak Agus",  role:"Terapis",         img:"assets/img/terapis_agus.jpg"  }
            ]
        },
        lampeneurut: {
            num:"05", name:"Lampeneurut",
            addr:"Jl. Dr. Mr. Mohd Hasan No.17-18, Lampeuneurut Gampong, Kec. Darul Imarah, Kota Banda Aceh, Aceh 23242",
            wa:"6281234567804", phone:"6281234567804",
            maps:"https://maps.app.goo.gl/TzwRY89KrN81squTA",
            hours:"07.00 – 23.00",
            therapists:[
                { name:"Pak Syahrul", role:"Senior Terapis", img:"assets/img/terapis_syahrul.jpg" },
                { name:"Bu Fitri",    role:"Terapis",         img:"assets/img/terapis_fitri.jpg"   },
                { name:"Pak Usman",   role:"Terapis",         img:"assets/img/terapis_usman.jpg"   }
            ]
        },
        sigli: {
            num:"06", name:"Sigli",
            addr:"Jl. Lkr. Keuniree, Keuniree, Kec. Pidie, Kabupaten Pidie, Aceh 24111",
            wa:"6281234567805", phone:"6281234567805",
            maps:"https://maps.app.goo.gl/E4SgArGqCqQe3Vfv8",
            hours:"Layanan 24 Jam",
            therapists:[
                { name:"Pak Zulkifli",  role:"Senior Terapis", img:"assets/img/terapis_zulkifli.jpg"  },
                { name:"Bu Hasnah",     role:"Terapis",         img:"assets/img/terapis_hasnah.jpg"    },
                { name:"Pak Iskandar",  role:"Terapis",         img:"assets/img/terapis_iskandar.jpg"  }
            ]
        },
        bireuen: {
            num:"07", name:"Bireuen",
            addr:"6P24+MP9, Pulo Ara Geudong Teungoh, Kec. Kota Juang, Kabupaten Bireuen, Aceh 24261",
            wa:"6281234567806", phone:"6281234567806",
            maps:"https://maps.app.goo.gl/75SwtGSJvvSmdiqo8",
            hours:"07.00 – 23.00",
            therapists:[
                { name:"Pak Ramli",    role:"Senior Terapis", img:"assets/img/terapis_ramli.jpg"    },
                { name:"Bu Khairiah",  role:"Terapis",         img:"assets/img/terapis_khairiah.jpg" },
                { name:"Pak Nasrul",   role:"Terapis",         img:"assets/img/terapis_nasrul.jpg"   }
            ]
        },
        takengon: {
            num:"08", name:"Takengon",
            addr:"JRRQ+6V3, Bebesen, Kec. Bebesen, Kabupaten Aceh Tengah, Aceh 24471",
            wa:"6281234567807", phone:"6281234567807",
            maps:"https://maps.app.goo.gl/eMyZy1rWgaHFJkNMA",
            hours:"07.00 – 23.00",
            therapists:[
                { name:"Pak Mukhtar", role:"Senior Terapis", img:"assets/img/terapis_mukhtar.jpg" },
                { name:"Bu Rahmah",   role:"Terapis",         img:"assets/img/terapis_rahmah.jpg"  },
                { name:"Pak Faisal",  role:"Terapis",         img:"assets/img/terapis_faisal.jpg"  }
            ]
        },
        sabang: {
            num:"09", name:"Sabang",
            addr:"V8PJ+P2X, Jl. Yos Sudarso, Cot Ba'u, Kec. Sukajaya, Kota Sabang, Aceh 24411",
            wa:"6281234567808", phone:"6281234567808",
            maps:"https://maps.app.goo.gl/Zpv3JeBuPeUVkiwL7",
            hours:"07.00 – 00.40",
            therapists:[
                { name:"Pak Tarmizi", role:"Senior Terapis", img:"assets/img/terapis_tarmizi.jpg" },
                { name:"Bu Marlina",  role:"Terapis",         img:"assets/img/terapis_marlina.jpg" },
                { name:"Pak Safwan",  role:"Terapis",         img:"assets/img/terapis_safwan.jpg"  }
            ]
        },
        langsa: {
            num:"10", name:"Langsa",
            addr:"FXH4+4VG, Jl. Lintas Sumatera, Paya Bujok Tunong, Kec. Langsa Baro, Kota Langsa, Aceh 24354",
            wa:"6281234567809", phone:"6281234567809",
            maps:"https://maps.app.goo.gl/3qcqCj5h6v7QHrdGA",
            hours:"07.00 – 23.00",
            therapists:[
                { name:"Pak Azwar",   role:"Senior Terapis", img:"assets/img/terapis_azwar.jpg"   },
                { name:"Bu Yuliana",  role:"Terapis",         img:"assets/img/terapis_yuliana.jpg" },
                { name:"Pak Rusli",   role:"Terapis",         img:"assets/img/terapis_rusli.jpg"   }
            ]
        },
        lhokseumawe: {
            num:"11", name:"Lhokseumawe",
            addr:"Jl. Merdeka No.1, Simpang Empat, Kec. Banda Sakti, Kota Lhokseumawe, Aceh",
            wa:"6281234567810", phone:"6281234567810",
            maps:"https://maps.app.goo.gl/awp3T7vSBvYw3YdUA",
            hours:"07.00 – 23.00",
            therapists:[
                { name:"Pak Hasbi",    role:"Senior Terapis", img:"assets/img/terapis_hasbi.jpg"   },
                { name:"Bu Darlina",   role:"Terapis",         img:"assets/img/terapis_darlina.jpg" },
                { name:"Pak Munawar",  role:"Terapis",         img:"assets/img/terapis_munawar.jpg" }
            ]
        },
        subulussalam: {
            num:"12", name:"Subulussalam",
            addr:"Jl. Teuku Umar, Subulussalam, Kec. Simpang Kiri, Kota Subulussalam, Aceh 24782",
            wa:"6281234567811", phone:"6281234567811",
            maps:"https://maps.app.goo.gl/wYpxrBGTGfsHeeiV6",
            hours:"07.00 – 23.00",
            therapists:[
                { name:"Pak Badruzzaman", role:"Senior Terapis", img:"assets/img/terapis_badruzzaman.jpg" },
                { name:"Bu Suryani",      role:"Terapis",         img:"assets/img/terapis_suryani.jpg"     },
                { name:"Pak Hamdan",      role:"Terapis",         img:"assets/img/terapis_hamdan.jpg"      }
            ]
        }
    };

    function g(id){ return document.getElementById(id); }

    function openModal(key) {
        var b = CABANG[key]; if (!b) return;
        g('brNum').textContent   = b.num;
        g('brName').textContent  = b.name;
        g('brAddr').textContent  = b.addr;
        g('brHours').textContent = '🕐 ' + b.hours;
        g('brWA').dataset.url    = 'https://wa.me/'+b.wa+'?text=Halo%20Bugar%20Refleksi%20Cabang%20'+encodeURIComponent(b.name)+'%2C%20saya%20ingin%20booking%20terapi';
        g('brMaps').dataset.url  = b.maps;
        g('brPhone').dataset.url = 'tel:+'+b.phone;

        /* Render foto terapis */
        var grid = g('brTherapists');
        grid.innerHTML = '';
        b.therapists.forEach(function(t) {
            var card = document.createElement('div');
            card.className = 'br-therapist-card';

            /* Buat elemen foto — pakai <img> jika ada path, fallback ke ikon */
            var fotoHtml;
            if (t.img) {
                fotoHtml = '<img src="' + t.img + '" alt="' + t.name + '" '
                    + 'onerror="this.onerror=null;this.parentNode.innerHTML=\'👤\';">';
            } else {
                fotoHtml = '👤';
            }

            card.innerHTML =
                '<div class="br-therapist-photo">' + fotoHtml + '</div>' +
                '<span class="br-therapist-name">' + t.name + '</span>' +
                '<span class="br-therapist-role">' + t.role + '</span>';

            grid.appendChild(card);
        });

        g('branchModalOverlay').classList.add('br-open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        g('branchModalOverlay').classList.remove('br-open');
        document.body.style.overflow = '';
    }

    function init() {
        g('branchModalClose').addEventListener('click', closeModal);
        g('branchModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        document.querySelectorAll('.branch-card[data-branch]').forEach(function(card) {
            card.style.cssText += ';opacity:1!important;visibility:visible!important;pointer-events:auto!important;cursor:pointer!important;';
            card.classList.add('animate');
            card.addEventListener('click', function() {
                openModal(this.getAttribute('data-branch'));
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>