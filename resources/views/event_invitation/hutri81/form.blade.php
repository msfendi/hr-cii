@include('layout.header')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root{
        --merah: #C8102E;
        --merah-tua: #7a0000;
        --putih: #ffffff;
        --gold: #FFD700;
    }
    html,body{
        font-family: 'Poppins', sans-serif;
        background: #7a0000;
        overflow-x: hidden;
    }
    .font-display{ font-family:'Bebas Neue', sans-serif; letter-spacing: 2px; }

    /* helper warna untuk ilustrasi SVG inline */
    .ic-red{ fill: var(--merah); }
    .ic-tua{ fill: var(--merah-tua); }
    .ic-gold{ fill: var(--gold); }
    .ic-white{ fill: #ffffff; }
    .ic-skin{ fill: #3a2a20; }

    /* ============== COVER ============== */
    #cover{
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color:#fff;
        background:
            radial-gradient(circle at 50% 20%, rgba(255,215,0,.18), transparent 55%),
            linear-gradient(160deg, var(--merah) 0%, var(--merah-tua) 60%, #3a0000 100%);
        padding: 2rem 1.5rem;
        transition: opacity .6s ease, visibility .6s ease;
    }
    #cover.hide{ opacity:0; visibility:hidden; pointer-events:none; }
    #cover h4{ opacity:.85; letter-spacing:3px; text-transform:uppercase; font-size:.85rem; }
    #cover h1{ font-size: 3rem; margin: .25rem 0 .5rem; }
    #cover .guest-box{
        border: 1.5px solid rgba(255,215,0,.6);
        border-radius: .75rem;
        padding: .9rem 1.5rem;
        margin: 1.25rem 0 2rem;
        background: rgba(255,255,255,.06);
        backdrop-filter: blur(2px);
    }
    #cover .guest-box small{ opacity:.75; display:block; letter-spacing:1px; }
    #cover .guest-box .nama{ font-size:1.25rem; font-weight:700; color: var(--gold); }
    #btn-buka{
        background: linear-gradient(135deg, var(--gold), #e6b800);
        border:none;
        color:#5c1a00;
        font-weight:700;
        padding:.85rem 2.2rem;
        border-radius: 3rem;
        box-shadow: 0 8px 25px rgba(0,0,0,.35);
        animation: pulse-btn 2s ease-in-out infinite;
    }
    @keyframes pulse-btn{
        0%,100%{ transform: scale(1); }
        50%{ transform: scale(1.05); }
    }
    .garuda-watermark{
        position:absolute;
        font-size: 12rem;
        opacity:.06;
        bottom:-2rem;
        pointer-events:none;
    }

    /* ============== BUNTING (bendera hias) ============== */
    .bunting{
        display:flex;
        justify-content:center;
        gap: 4px;
        padding-top: 8px;
        overflow:hidden;
        position: relative;
        z-index: 5;
    }
    .bunting span{
        width: 0; height: 0;
        border-left: 14px solid transparent;
        border-right: 14px solid transparent;
        border-top: 22px solid var(--putih);
        animation: sway 2.4s ease-in-out infinite;
        transform-origin: top center;
    }
    .bunting span:nth-child(odd){ border-top-color: var(--merah); animation-delay: .2s; }
    .bunting span:nth-child(3n){ border-top-color: var(--gold); animation-delay:.4s; }
    @keyframes sway{
        0%,100%{ transform: rotate(-4deg); }
        50%{ transform: rotate(4deg); }
    }

    /* ============== AKSEN: bendera mini divider ============== */
    .flag-divider{
        display:flex;
        justify-content:center;
        align-items:flex-end;
        gap: 6px;
        padding: .5rem 0;
        flex-wrap: wrap;
    }
    .mini-flag{
        display:inline-block;
        line-height:0;
        animation: sway 2.2s ease-in-out infinite;
        transform-origin: bottom center;
    }

    /* ============== AKSEN: bambu runcing ============== */
    .bambu-accent{
        position:absolute;
        opacity:.5;
        pointer-events:none;
        line-height:0;
        z-index: 1;
    }

    /* ============== AKSEN: angka 81 watermark ============== */
    .angka-81-watermark{
        position:absolute;
        font-family:'Bebas Neue', sans-serif;
        font-size: 9rem;
        color: rgba(255,215,0,.14);
        top:-18px;
        right:-8px;
        line-height:1;
        pointer-events:none;
        z-index: 0;
        user-select:none;
    }
    .badge-81{
        position:absolute;
        top:-16px;
        right:12px;
        background: linear-gradient(135deg, var(--gold), #e6b800);
        color:#5c1a00;
        font-family:'Bebas Neue', sans-serif;
        font-size:1.35rem;
        width:52px; height:52px;
        border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        box-shadow:0 6px 14px rgba(0,0,0,.25);
        border:3px solid #fff;
        transform: rotate(8deg);
        z-index: 3;
    }

    /* ============== MAIN CONTENT ============== */
    #main{ display:none; }

    /* Hero: countdown ada DI DALAM hero, jadi butuh padding-bottom yang
       cukup besar supaya kartu info putih di bawahnya tidak pernah
       menabrak angka countdown, walau di layar kecil sekalipun. */
    .hero{
        position: relative;
        overflow: hidden;
        background:
            radial-gradient(circle at 15% 10%, rgba(255,215,0,.12), transparent 50%),
            linear-gradient(160deg, var(--merah) 0%, var(--merah-tua) 100%);
        color:#fff;
        text-align:center;
        padding: 2.5rem 1.25rem 3rem;
    }
    .hero-content{ position:relative; z-index:2; }
    .hero .eyebrow{ letter-spacing:3px; font-size:.75rem; opacity:.8; text-transform:uppercase; }
    .hero h1{ font-size: 2.6rem; margin: .4rem 0 .2rem; text-shadow: 0 4px 18px rgba(0,0,0,.35); }
    .hero .sub{ opacity:.9; }
    .flag-wave{ display:inline-block; animation: wave 1.4s ease-in-out infinite; transform-origin: 70% 70%; }
    @keyframes wave{
        0%,100%{ transform: rotate(0deg); }
        25%{ transform: rotate(14deg); }
        75%{ transform: rotate(-8deg); }
    }

    /* countdown */
    .countdown{
        display:flex;
        justify-content:center;
        gap:.6rem;
        margin: 1.75rem 0 .75rem;
        flex-wrap: wrap;
    }
    .countdown .box{
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,215,0,.45);
        border-radius: .6rem;
        padding: .6rem .4rem;
        min-width: 70px;
        backdrop-filter: blur(3px);
    }
    .countdown .box .num{
        font-family:'Bebas Neue', sans-serif;
        font-size: 2rem;
        color: var(--gold);
        line-height:1;
    }
    .countdown .box .lbl{ font-size:.65rem; letter-spacing:1px; text-transform:uppercase; opacity:.85; }
    .countdown-caption{ opacity:.8; margin-bottom: 0; }

    /* info card — diberi jarak POSITIF dari hero, bukan overlap negatif,
       supaya tidak pernah menimpa countdown di atasnya. */
    .card-info-wrap{
        padding: 0 1rem;
        margin-top: 1.75rem;
    }
    .card-info-inner{
        position: relative;
        max-width: 480px;
        margin: 0 auto;
    }
    .card-info{
        background:#fff;
        border-radius: 1rem;
        box-shadow: 0 15px 40px rgba(0,0,0,.22);
        overflow:hidden;
    }
    .card-info .row-item{
        display:flex;
        gap:.85rem;
        align-items:flex-start;
        padding: .9rem 1.25rem;
        border-bottom: 1px dashed #eee;
    }
    .card-info .row-item:last-child{ border-bottom:none; }
    .card-info .row-item i{ color: var(--merah); font-size:1.1rem; margin-top:.2rem; width: 22px; text-align:center; }
    .card-info .row-item .lbl{ font-size:.72rem; text-transform:uppercase; letter-spacing:.5px; color:#999; }
    .card-info .row-item .val{ font-weight:600; color:#333; }

    /* doorprize section */
    .doorprize-section{
        position: relative;
        background: linear-gradient(180deg, #fff8e6 0%, #fff 100%);
        text-align:center;
        overflow: hidden;
    }
    .doorprize-visual{
        position:relative;
        display:inline-block;
        margin: 0 auto;
    }
    .gift-box{
        font-size: 5rem;
        display:inline-block;
        animation: gift-bounce 1.6s ease-in-out infinite;
        filter: drop-shadow(0 10px 15px rgba(0,0,0,.15));
    }
    @keyframes gift-bounce{
        0%,100%{ transform: translateY(0) rotate(-3deg); }
        50%{ transform: translateY(-14px) rotate(3deg); }
    }
    .sparkle{
        position:absolute;
        color: var(--gold);
        animation: twinkle 1.8s ease-in-out infinite;
    }
    @keyframes twinkle{
        0%,100%{ opacity:.2; transform: scale(.7); }
        50%{ opacity:1; transform: scale(1.15); }
    }

    /* ikon hadiah (mobil, kulkas, kipas, dll) yang melayang mengelilingi gift-box */
    .dp-icon{
        position:absolute;
        display:inline-block;
        line-height:1;
        font-size: 1.85rem;
        filter: drop-shadow(0 8px 10px rgba(0,0,0,.18));
        animation: dp-float 3.2s ease-in-out infinite;
    }
    @keyframes dp-float{
        0%,100%{ transform: translateY(0) rotate(-6deg); }
        50%{ transform: translateY(-16px) rotate(6deg); }
    }
    .fan-blades{
        transform-box: fill-box;
        transform-origin: center;
        animation: dp-spin 1.6s linear infinite;
    }
    @keyframes dp-spin{
        from{ transform: rotate(0deg); }
        to{ transform: rotate(360deg); }
    }

    .doorprize-badges span{
        display:inline-block;
        background: linear-gradient(135deg, var(--merah), var(--merah-tua));
        color:#fff;
        border-radius: 2rem;
        padding:.4rem 1rem;
        font-size:.78rem;
        font-weight:600;
        margin: .25rem;
    }

    /* ============== LOMBA (yel-yel, menyanyi, gerak jalan) ============== */
    .lomba-section{
        position: relative;
        background: #fff;
        text-align:center;
    }
    .lomba-grid{
        display:grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 1rem;
        max-width: 520px;
        margin: 1.5rem auto 0;
    }
    .lomba-card{
        background: linear-gradient(180deg, #fff8e6 0%, #fff 100%);
        border: 1px solid #f3e4b0;
        border-radius: 1rem;
        padding: 1rem .6rem 1.1rem;
        box-shadow: 0 8px 22px rgba(0,0,0,.08);
        transition: transform .2s ease;
    }
    .lomba-card:hover{ transform: translateY(-4px); }
    .lomba-card svg{ width: 84px; height: 66px; margin-bottom:.4rem; }
    .lomba-card h6{
        font-weight:700;
        color: var(--merah-tua);
        margin-bottom:.3rem;
        font-size:.82rem;
        text-transform: uppercase;
        letter-spacing:.4px;
    }
    .lomba-card p{ font-size:.74rem; color:#888; margin:0; line-height:1.4; }

    /* RSVP */
    .rsvp-section{ background: #fdfdfd; }
    .rsvp-choice{ display:flex; gap:.75rem; flex-wrap:wrap; }
    .rsvp-choice label{
        flex:1 1 150px;
        border: 2px solid #e5e5e5;
        border-radius: .85rem;
        padding: 1rem .75rem;
        text-align:center;
        cursor:pointer;
        transition: all .15s ease;
        margin:0;
    }
    .rsvp-choice input{ display:none; }
    .rsvp-choice label .ico{ font-size:1.8rem; display:block; margin-bottom:.35rem; }
    .rsvp-choice input:checked + label{
        border-color: var(--merah);
        background: rgba(200,16,46,.06);
        box-shadow: 0 4px 14px rgba(200,16,46,.15);
    }
    .rsvp-choice input:checked + label.hadir{ border-color:#1f9d55; background: rgba(31,157,85,.07); }

    .btn-rsvp-submit{
        background: linear-gradient(135deg, var(--merah), var(--merah-tua));
        border:none;
        color:#fff;
        font-weight:700;
        padding:.85rem;
        border-radius:.6rem;
        width:100%;
    }

    .thanks-box{ text-align:center; padding: 2rem 1rem; }
    .thanks-box .big-ico{ font-size:3.5rem; }

    footer.merdeka-footer{
        background: var(--merah-tua);
        color: rgba(255,255,255,.75);
        text-align:center;
        padding: 1.5rem;
        font-size:.8rem;
    }

    .confetti-piece{
        position: fixed;
        top: -10px;
        z-index: 2000;
        pointer-events:none;
        border-radius: 2px;
    }

    @media (max-width: 420px){
        .hero h1{ font-size: 2.1rem; }
        .countdown .box{ min-width: 60px; padding:.5rem .3rem; }
        .countdown .box .num{ font-size:1.5rem; }
        .angka-81-watermark{ font-size: 6rem; }
        .dp-icon{ font-size: 1.5rem; }
    }
</style>

<body>
@include('sweetalert::alert')

{{-- =============== COVER =============== --}}
<div id="cover">
    <div class="garuda-watermark">🦅</div>
    <h4>Undangan Resmi</h4>
    <h1 class="font-display">DIRGAHAYU<br>REPUBLIK INDONESIA<br>KE-81</h1>
    <p class="mb-0" style="opacity:.85;">PT. Chutex International Indonesia</p>

    <div class="guest-box">
        <small>Kepada Yth. Bapak/Ibu/Saudara/i</small>
        <div class="nama">{{ $nama }}</div>
        <small>NPK {{ $npk }} &bull; {{ $departemen }}</small>
    </div>

    <button id="btn-buka" onclick="bukaUndangan()">
        <i class="fas fa-envelope-open-text mr-2"></i> Buka Undangan
    </button>
</div>

{{-- =============== MAIN CONTENT =============== --}}
<div id="main">

    <div class="bunting">
        @for ($i = 0; $i < 14; $i++) <span></span> @endfor
    </div>

    <section class="hero">
        {{-- aksen angka 81 raksasa transparan di belakang judul --}}
        <div class="angka-81-watermark">81</div>

        {{-- aksen bambu runcing di kedua sudut hero --}}
        <div class="bambu-accent" style="top:6px; left:-6px;">
            <svg viewBox="0 0 100 100" width="64" height="64">
                <line x1="10" y1="90" x2="70" y2="10" stroke="#c98a4b" stroke-width="4" stroke-linecap="round"/>
                <polygon points="70,10 79,3 74,18" fill="#8a5a2b"/>
                <line x1="90" y1="90" x2="30" y2="10" stroke="#c98a4b" stroke-width="4" stroke-linecap="round"/>
                <polygon points="30,10 21,3 26,18" fill="#8a5a2b"/>
                <rect x="46" y="34" width="13" height="8" class="ic-red"/>
                <rect x="46" y="42" width="13" height="8" class="ic-white"/>
            </svg>
        </div>
        <div class="bambu-accent" style="top:6px; right:-6px; transform: scaleX(-1);">
            <svg viewBox="0 0 100 100" width="64" height="64">
                <line x1="10" y1="90" x2="70" y2="10" stroke="#c98a4b" stroke-width="4" stroke-linecap="round"/>
                <polygon points="70,10 79,3 74,18" fill="#8a5a2b"/>
                <line x1="90" y1="90" x2="30" y2="10" stroke="#c98a4b" stroke-width="4" stroke-linecap="round"/>
                <polygon points="30,10 21,3 26,18" fill="#8a5a2b"/>
                <rect x="46" y="34" width="13" height="8" class="ic-red"/>
                <rect x="46" y="42" width="13" height="8" class="ic-white"/>
            </svg>
        </div>

        <div class="hero-content">
            <div class="eyebrow">🇮🇩 Dirgahayu Republik Indonesia</div>
            <h1 class="font-display"><span class="flag-wave">🇮🇩</span> {{ $event->nama_event }}</h1>
            <p class="sub mb-0">PT. Chutex International Indonesia</p>

            <div class="countdown" id="countdown">
                <div class="box"><div class="num" id="cd-days">00</div><div class="lbl">Hari</div></div>
                <div class="box"><div class="num" id="cd-hours">00</div><div class="lbl">Jam</div></div>
                <div class="box"><div class="num" id="cd-mins">00</div><div class="lbl">Menit</div></div>
                <div class="box"><div class="num" id="cd-secs">00</div><div class="lbl">Detik</div></div>
            </div>
            <p class="small countdown-caption">menuju hari kemerdekaan</p>
        </div>
    </section>

    {{-- aksen deretan bendera merah putih mini --}}
    <!-- <div class="flag-divider">
        @for ($i = 0; $i < 9; $i++)
            <span class="mini-flag" style="animation-delay: {{ $i * 0.12 }}s;">
                <svg viewBox="0 0 20 26" width="14" height="18">
                    <rect x="9" y="0" width="1.6" height="26" fill="#8a5a2b"/>
                    <rect x="10.5" y="2" width="9" height="5" class="ic-red"/>
                    <rect x="10.5" y="7" width="9" height="5" fill="#fff" stroke="#ddd" stroke-width=".3"/>
                </svg>
            </span>
        @endfor
    </div> -->

    <div class="card-info-wrap">
        <div class="card-info-inner">
            {{-- aksen badge angka 81 nempel di sudut kartu info --}}
            <div class="badge-81">81</div>

            <div class="card-info">
                <div class="row-item">
                    <i class="fas fa-calendar-day"></i>
                    <div><div class="lbl">Tanggal</div><div class="val">{{ $event->tanggal_display }}</div></div>
                </div>
                <div class="row-item">
                    <i class="fas fa-clock"></i>
                    <div><div class="lbl">Waktu</div><div class="val">{{ $event->waktu_event }}</div></div>
                </div>
                <div class="row-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div><div class="lbl">Lokasi</div><div class="val">{{ $event->lokasi_event }}</div></div>
                </div>
                @if ($event->dress_code)
                    <div class="row-item">
                        <i class="fas fa-tshirt"></i>
                        <div><div class="lbl">Dress Code</div><div class="val">{{ $event->dress_code }}</div></div>
                    </div>
                @endif
                @if ($event->detail_event)
                    <div class="row-item">
                        <i class="fas fa-info-circle"></i>
                        <div><div class="lbl">Info Tambahan</div><div class="val" style="font-weight:400;">{{ $event->detail_event }}</div></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <br>
    {{-- =============== DOORPRIZE =============== --}}
    <section class="section doorprize-section" style="padding: 3rem 1.25rem;">
        <div class="doorprize-visual">
            <span class="sparkle" style="top:-10px; left:-25px; font-size:1.1rem;">✨</span>
            <span class="sparkle" style="top:5px; right:-30px; font-size:1.4rem; animation-delay:.6s;">✨</span>
            <span class="sparkle" style="bottom:-15px; left:10px; font-size:1rem; animation-delay:1.1s;">⭐</span>

            {{-- ikon hadiah yang melayang & berputar mengelilingi kotak hadiah --}}
            <span class="dp-icon" style="top:-14px; left:-72px; animation-delay:.1s;" title="Mobil">🚗</span>
            <span class="dp-icon" style="top:20px; right:-80px; animation-delay:.5s;" title="TV">📺</span>
            <span class="dp-icon" style="bottom:-8px; left:-62px; animation-delay:.9s;" title="Motor">🛵</span>
            <span class="dp-icon" style="bottom:-16px; right:-66px; animation-delay:1.3s;" title="Sepeda">🚲</span>
            <span class="dp-icon" style="top:-42px; left:22px; font-size:1.4rem; animation-delay:1.6s;" title="HP">📱</span>

            {{-- kulkas (ilustrasi SVG, tidak ada emoji standarnya) --}}
            <span class="dp-icon" style="top:10px; left:-108px; animation-delay:.3s;" title="Kulkas">
                <svg viewBox="0 0 40 60" width="26" height="40">
                    <rect x="4" y="2" width="32" height="56" rx="5" fill="#fff" stroke="#ccc" stroke-width="1.5"/>
                    <line x1="4" y1="18" x2="36" y2="18" stroke="#ccc" stroke-width="1.5"/>
                    <rect x="29" y="8" width="3" height="6" rx="1.5" class="ic-tua"/>
                    <rect x="29" y="26" width="3" height="10" rx="1.5" class="ic-tua"/>
                </svg>
            </span>

            {{-- kipas angin berputar (ilustrasi SVG) --}}
            <span class="dp-icon" style="top:-30px; right:-16px; animation-delay:.7s;" title="Kipas Angin">
                <svg viewBox="0 0 50 60" width="30" height="36">
                    <line x1="25" y1="40" x2="25" y2="58" stroke="#999" stroke-width="3" stroke-linecap="round"/>
                    <rect x="12" y="56" width="26" height="4" rx="2" fill="#999"/>
                    <circle cx="25" cy="25" r="18" fill="none" stroke="#bbb" stroke-width="2"/>
                    <g class="fan-blades">
                        <path d="M25 25 L25 9 A8 8 0 0 1 34 22 Z" class="ic-red"/>
                        <path d="M25 25 L39 32 A8 8 0 0 1 25 41 Z" class="ic-gold"/>
                        <path d="M25 25 L11 32 A8 8 0 0 1 25 9 Z" fill="#fff" stroke="#ddd" stroke-width=".5"/>
                    </g>
                    <circle cx="25" cy="25" r="3" fill="#666"/>
                </svg>
            </span>

            <div class="gift-box">🎁</div>
        </div>
        <h2 class="font-display mt-2" style="color: var(--merah); letter-spacing:1px;">ADA DOORPRIZE MENARIK!</h2>
        <p class="text-muted px-2" style="max-width:420px; margin:0 auto;">
            Jangan sampai terlewat! Khusus untuk yang hadir langsung di lokasi, disediakan
            <b>doorprize</b> spesial seperti mobil, kulkas, kipas angin, dan hadiah menarik lainnya
            di penghujung acara. Semakin cepat konfirmasi hadir,
            semakin besar semangat gotong royong merayakan kemerdekaan bersama-sama 🎉
        </p>
        <div class="doorprize-badges mt-3">
            <span><i class="fas fa-gift mr-1"></i> Hadiah Menarik</span>
            <span><i class="fas fa-users mr-1"></i> Wajib Hadir</span>
            <span><i class="fas fa-star mr-1"></i> Kejutan Spesial</span>
        </div>
    </section>

    {{-- =============== LOMBA: YEL-YEL, MENYANYI, GERAK JALAN =============== --}}
    <section class="section lomba-section" style="padding: 3rem 1.25rem;">
        <div class="bambu-accent" style="top:6px; left:6px;">
            <svg viewBox="0 0 100 100" width="46" height="46">
                <line x1="10" y1="90" x2="70" y2="10" stroke="#c98a4b" stroke-width="4" stroke-linecap="round"/>
                <polygon points="70,10 79,3 74,18" fill="#8a5a2b"/>
                <line x1="90" y1="90" x2="30" y2="10" stroke="#c98a4b" stroke-width="4" stroke-linecap="round"/>
                <polygon points="30,10 21,3 26,18" fill="#8a5a2b"/>
                <rect x="46" y="34" width="13" height="8" class="ic-red"/>
                <rect x="46" y="42" width="13" height="8" class="ic-white"/>
            </svg>
        </div>

        <h2 class="font-display" style="color: var(--merah); letter-spacing:1px;">MERIAHKAN DENGAN LOMBA!</h2>
        <p class="text-muted px-2 mb-0" style="max-width:460px; margin:0 auto;">
            Selain konfirmasi kehadiran, siapkan juga tim terbaikmu untuk ikut memeriahkan
            berbagai lomba khas 17 Agustus berikut ini:
        </p>

        <div class="lomba-grid">
            {{-- Lomba Yel-Yel --}}
            <div class="lomba-card">
                <svg viewBox="0 0 140 100" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="45" cy="26" r="11" class="ic-skin"/>
                    <path d="M30 44 Q45 32 60 44 L57 80 Q45 86 33 80 Z" class="ic-red"/>
                    <line x1="30" y1="44" x2="14" y2="20" stroke="#3a2a20" stroke-width="5" stroke-linecap="round"/>
                    <line x1="60" y1="44" x2="76" y2="20" stroke="#3a2a20" stroke-width="5" stroke-linecap="round"/>
                    <g class="ic-white">
                        <circle cx="12" cy="14" r="3"/><circle cx="18" cy="9" r="3"/>
                        <circle cx="7" cy="8" r="3"/><circle cx="16" cy="19" r="3"/><circle cx="8" cy="18" r="3"/>
                    </g>
                    <g class="ic-gold">
                        <circle cx="78" cy="14" r="3"/><circle cx="84" cy="9" r="3"/>
                        <circle cx="73" cy="8" r="3"/><circle cx="82" cy="19" r="3"/><circle cx="74" cy="18" r="3"/>
                    </g>

                    <circle cx="98" cy="30" r="10" class="ic-skin"/>
                    <path d="M85 46 Q98 36 111 46 L108 78 Q98 84 88 78 Z" fill="#fff" stroke="#eee" stroke-width="1"/>
                    <line x1="85" y1="46" x2="70" y2="24" stroke="#3a2a20" stroke-width="5" stroke-linecap="round"/>
                    <line x1="111" y1="46" x2="126" y2="24" stroke="#3a2a20" stroke-width="5" stroke-linecap="round"/>
                    <g class="ic-red">
                        <circle cx="68" cy="19" r="3"/><circle cx="74" cy="14" r="3"/>
                        <circle cx="63" cy="13" r="3"/><circle cx="72" cy="24" r="3"/>
                    </g>
                    <g class="ic-gold">
                        <circle cx="128" cy="19" r="3"/><circle cx="134" cy="14" r="3"/>
                        <circle cx="123" cy="13" r="3"/><circle cx="132" cy="24" r="3"/>
                    </g>
                </svg>
                <h6>Lomba Yel-Yel</h6>
                <p>Tunjukkan kekompakan &amp; kreativitas tim lewat yel-yel paling meriah!</p>
            </div>

            {{-- Lomba Menyanyi --}}
            <div class="lomba-card">
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <text x="64" y="22" font-size="15" class="ic-gold">&#9834;</text>
                    <text x="79" y="12" font-size="13" class="ic-red">&#9835;</text>
                    <text x="70" y="38" font-size="11" class="ic-tua">&#9834;</text>
                    <circle cx="42" cy="26" r="12" class="ic-skin"/>
                    <path d="M26 46 Q42 34 58 46 L55 82 Q42 88 29 82 Z" class="ic-red"/>
                    <line x1="55" y1="50" x2="66" y2="34" stroke="#3a2a20" stroke-width="5" stroke-linecap="round"/>
                    <rect x="65.5" y="12" width="2" height="14" fill="#888"/>
                    <rect x="63" y="24" width="7" height="14" rx="3.5" fill="#444"/>
                </svg>
                <h6>Lomba Menyanyi</h6>
                <p>Unjuk suara terbaikmu membawakan lagu perjuangan &amp; nasional.</p>
            </div>

            {{-- Lomba Gerak Jalan --}}
            <div class="lomba-card">
                <svg viewBox="0 0 160 100" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="26" cy="24" r="9" class="ic-skin"/>
                    <path d="M16 40 L36 40 L33 74 L19 74 Z" fill="#fff" stroke="#ddd" stroke-width="1"/>
                    <line x1="16" y1="45" x2="6" y2="70" stroke="#3a2a20" stroke-width="4" stroke-linecap="round"/>
                    <line x1="36" y1="45" x2="46" y2="66" stroke="#3a2a20" stroke-width="4" stroke-linecap="round"/>
                    <line x1="19" y1="74" x2="10" y2="94" stroke="#3a2a20" stroke-width="4" stroke-linecap="round"/>
                    <line x1="33" y1="74" x2="40" y2="92" stroke="#3a2a20" stroke-width="4" stroke-linecap="round"/>

                    <circle cx="80" cy="20" r="10" class="ic-skin"/>
                    <path d="M68 38 L92 38 L88 76 L72 76 Z" class="ic-red"/>
                    <line x1="68" y1="42" x2="56" y2="30" stroke="#3a2a20" stroke-width="5" stroke-linecap="round"/>
                    <line x1="56" y1="30" x2="56" y2="6" stroke="#8a5a2b" stroke-width="3" stroke-linecap="round"/>
                    <rect x="56" y="6" width="14" height="8" class="ic-red"/>
                    <rect x="56" y="14" width="14" height="8" fill="#fff" stroke="#ddd" stroke-width=".5"/>
                    <line x1="92" y1="42" x2="102" y2="30" stroke="#3a2a20" stroke-width="5" stroke-linecap="round"/>
                    <line x1="72" y1="76" x2="64" y2="96" stroke="#3a2a20" stroke-width="4" stroke-linecap="round"/>
                    <line x1="88" y1="76" x2="96" y2="94" stroke="#3a2a20" stroke-width="4" stroke-linecap="round"/>

                    <circle cx="134" cy="24" r="9" class="ic-skin"/>
                    <path d="M124 40 L144 40 L141 74 L127 74 Z" fill="#fff" stroke="#ddd" stroke-width="1"/>
                    <line x1="124" y1="45" x2="114" y2="66" stroke="#3a2a20" stroke-width="4" stroke-linecap="round"/>
                    <line x1="144" y1="45" x2="154" y2="70" stroke="#3a2a20" stroke-width="4" stroke-linecap="round"/>
                    <line x1="127" y1="74" x2="120" y2="92" stroke="#3a2a20" stroke-width="4" stroke-linecap="round"/>
                    <line x1="141" y1="74" x2="148" y2="94" stroke="#3a2a20" stroke-width="4" stroke-linecap="round"/>
                </svg>
                <h6>Lomba Gerak Jalan</h6>
                <p>Kompak berbaris rapi mengikuti irama, semangat juang tak pernah padam!</p>
            </div>
        </div>
    </section>

    {{-- =============== RSVP FORM / THANKS =============== --}}
    <section class="section rsvp-section" id="rsvp-section" style="padding: 3rem 1.25rem;">
        @if ($invitation && $invitation->is_confirmed)
            <div class="thanks-box" id="thanks-box">
                <div class="big-ico">{{ $invitation->is_hadir ? '🎉' : '🙏' }}</div>
                <h4 class="font-display" style="color:var(--merah);">
                    {{ $invitation->is_hadir ? 'TERIMA KASIH, SAMPAI JUMPA!' : 'TERIMA KASIH ATAS KONFIRMASINYA' }}
                </h4>
                <p class="text-muted">
                    Anda ({{ $invitation->nama }}) telah mengonfirmasi:
                    <br>
                    <span class="badge {{ $invitation->is_hadir ? 'badge-success' : 'badge-secondary' }} p-2 mt-2">
                        {{ $invitation->is_hadir ? 'BISA HADIR' : 'TIDAK BISA HADIR' }}
                    </span>
                </p>
                <button class="btn btn-outline-danger btn-sm mt-2" onclick="tampilkanForm()">
                    <i class="fas fa-pen mr-1"></i> Ubah Jawaban
                </button>
            </div>
        @endif

        <div id="form-wrapper" @if ($invitation && $invitation->is_confirmed) style="display:none;" @endif>
            <div class="text-center mb-3">
                <h4 class="font-display" style="color:var(--merah);">KONFIRMASI KEHADIRAN</h4>
                <p class="text-muted small mb-0">
                    {{ $nama }} &bull; NPK {{ $npk }} &bull; {{ $departemen }}
                </p>
            </div>

            <form id="rsvp-form" style="max-width:420px; margin:0 auto;">
                @csrf
                <div class="rsvp-choice mb-3">
                    <input type="radio" name="status" id="opt-hadir" value="hadir" required
                        {{ optional($invitation)->status === 'hadir' ? 'checked' : '' }}>
                    <label for="opt-hadir" class="hadir">
                        <span class="ico">🎉</span>
                        <b>Bisa Hadir</b>
                        <div class="small text-muted">Saya akan datang!</div>
                    </label>

                    <input type="radio" name="status" id="opt-tidak" value="tidak_hadir" required
                        {{ optional($invitation)->status === 'tidak_hadir' ? 'checked' : '' }}>
                    <label for="opt-tidak">
                        <span class="ico">🙏</span>
                        <b>Tidak Bisa Hadir</b>
                        <div class="small text-muted">Mohon maaf berhalangan</div>
                    </label>
                </div>

                <div class="form-group">
                    <label class="small text-muted">Ucapan / Doa untuk Kemerdekaan Indonesia (opsional)</label>
                    <textarea name="ucapan" class="form-control" rows="3"
                        placeholder="Tuliskan ucapan atau harapan Anda...">{{ optional($invitation)->ucapan }}</textarea>
                </div>

                <button type="submit" class="btn-rsvp-submit" id="btn-submit-rsvp">
                    <i class="fas fa-paper-plane mr-2"></i> Kirim Konfirmasi
                </button>
            </form>
        </div>
    </section>

    {{-- aksen deretan bendera merah putih sebelum footer --}}
    <!-- <div class="flag-divider" style="background:var(--merah-tua); padding: .75rem 0;">
        @for ($i = 0; $i < 9; $i++)
            <span class="mini-flag" style="animation-delay: {{ $i * 0.12 }}s;">
                <svg viewBox="0 0 20 26" width="14" height="18">
                    <rect x="9" y="0" width="1.6" height="26" fill="#c98a4b"/>
                    <rect x="10.5" y="2" width="9" height="5" class="ic-red"/>
                    <rect x="10.5" y="7" width="9" height="5" fill="#fff" stroke="#ddd" stroke-width=".3"/>
                </svg>
            </span>
        @endfor
    </div> -->

    <footer class="merdeka-footer">
        <div class="mb-1" style="font-size:1.4rem;">🇮🇩</div>
        MERDEKA! MERDEKA! MERDEKA!<br>
        PT. Chutex International Indonesia &copy; {{ date('Y') }}
    </footer>
</div>

@include('layout.footerscript')
</body>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const EVENT_DATE = new Date("{{ \Carbon\Carbon::parse($event->countdown_target)->toIso8601String() }}");
const eventId = {{ $event->id }};

/* ---------------- COVER ---------------- */
function bukaUndangan(){
    document.getElementById('cover').classList.add('hide');
    document.getElementById('main').style.display = 'block';
    document.body.style.overflow = 'auto';
    burstConfetti(60);
}
document.body.style.overflow = 'hidden';

/* ---------------- COUNTDOWN ---------------- */
function updateCountdown(){
    const now = new Date();
    let diff = EVENT_DATE - now;

    if (diff < 0) diff = 0;

    const d = Math.floor(diff / (1000 * 60 * 60 * 24));
    const h = Math.floor((diff / (1000 * 60 * 60)) % 24);
    const m = Math.floor((diff / (1000 * 60)) % 60);
    const s = Math.floor((diff / 1000) % 60);

    document.getElementById('cd-days').textContent  = String(d).padStart(2, '0');
    document.getElementById('cd-hours').textContent = String(h).padStart(2, '0');
    document.getElementById('cd-mins').textContent  = String(m).padStart(2, '0');
    document.getElementById('cd-secs').textContent  = String(s).padStart(2, '0');
}
updateCountdown();
setInterval(updateCountdown, 1000);

/* ---------------- CONFETTI (merah putih emas) ---------------- */
function burstConfetti(amount = 40){
    const colors = ['#C8102E', '#ffffff', '#FFD700'];
    for (let i = 0; i < amount; i++) {
        const el = document.createElement('div');
        el.className = 'confetti-piece';
        const size = 6 + Math.random() * 6;
        el.style.width = size + 'px';
        el.style.height = (size * 1.6) + 'px';
        el.style.left = Math.random() * 100 + 'vw';
        el.style.background = colors[Math.floor(Math.random() * colors.length)];
        el.style.opacity = .85;
        el.style.transform = `rotate(${Math.random() * 360}deg)`;
        document.body.appendChild(el);

        const duration = 2200 + Math.random() * 1800;
        const drift = (Math.random() - 0.5) * 200;

        el.animate([
            { transform: `translate(0,0) rotate(0deg)`, opacity: 1 },
            { transform: `translate(${drift}px, 100vh) rotate(${360 + Math.random()*360}deg)`, opacity: .9 }
        ], { duration, easing: 'ease-in' }).onfinish = () => el.remove();
    }
}

/* ---------------- RSVP FORM ---------------- */
function tampilkanForm(){
    document.getElementById('thanks-box').style.display = 'none';
    document.getElementById('form-wrapper').style.display = 'block';
    document.getElementById('form-wrapper').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

document.getElementById('rsvp-form').addEventListener('submit', function(e){
    e.preventDefault();

    const btn = document.getElementById('btn-submit-rsvp');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Mengirim...';

    const formData = new FormData(this);

    fetch("{{ route('event-invitation.respond', ['event' => $event->id]) }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: formData,
    })
    .then(res => res.json().then(data => ({ ok: res.ok, data })))
    .then(({ ok, data }) => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;

        if (!ok) {
            Swal.fire({ icon: 'error', title: 'Gagal', text: data.message || 'Terjadi kesalahan.' });
            return;
        }

        const isHadir = data.data.status === 'hadir';

        Swal.fire({
            icon: 'success',
            title: isHadir ? 'Sampai Jumpa! 🎉' : 'Terima Kasih 🙏',
            text: data.message,
        }).then(() => {
            if (isHadir) burstConfetti(90);
            document.getElementById('rsvp-section').innerHTML = `
                <div class="thanks-box">
                    <div class="big-ico">${isHadir ? '🎉' : '🙏'}</div>
                    <h4 class="font-display" style="color:var(--merah);">
                        ${isHadir ? 'TERIMA KASIH, SAMPAI JUMPA!' : 'TERIMA KASIH ATAS KONFIRMASINYA'}
                    </h4>
                    <p class="text-muted">
                        Jawaban Anda telah tersimpan sebagai
                        <span class="badge ${isHadir ? 'badge-success' : 'badge-secondary'} p-2">
                            ${isHadir ? 'BISA HADIR' : 'TIDAK BISA HADIR'}
                        </span>
                    </p>
                </div>
            `;
        });
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan jaringan, silakan coba lagi.' });
    });
});

setInterval(() => burstConfetti(12), 9000);
</script>
</html>