<!DOCTYPE html>
<html lang="en">

@include('layout.header')

<body class="bg-gradient-primary">

@include('sweetalert::alert')

<style>
    /* ==========================================================
       BLUE & WHITE THEME - 2026-11-05
       - Solid blue gradient (matches company logo screenshot)
       - White glass cards, tighter spacing
       - No logic changes
       ========================================================== */

    :root{
        --blue-900:#1d3f86;
        --blue-700:#274b8f;
        --blue-500:#3a6fa8;
    }

    body.bg-gradient-primary{
        background:linear-gradient(135deg, var(--blue-900) 0%, var(--blue-700) 50%, var(--blue-500) 100%);
        background-attachment: fixed;
        min-height:100vh;
        overflow-x:hidden;
    }

    /* Grid tipis-overlay (putih sangat halus) */
    body.bg-gradient-primary::before{
        content:'';
        position:fixed;
        inset:0;
        pointer-events:none;
        background-image:
            linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
        background-size:46px 46px;
        mask-image: radial-gradient(circle at 50% 40%, #000 0%, transparent 78%);
        -webkit-mask-image: radial-gradient(circle at 50% 40%, #000 0%, transparent 78%);
        z-index:0;
    }

    /* Orb dekoratif (biru muda / putih, lembut) */
    .bg-orb{
        position:fixed;
        border-radius:50%;
        filter:blur(70px);
        opacity:.28;
        pointer-events:none;
        z-index:0;
        animation:orbFloat 16s ease-in-out infinite;
    }
    .bg-orb.a{ width:340px; height:340px; background:#ffffff; top:-120px; left:-100px; opacity:.16; }
    .bg-orb.b{ width:300px; height:300px; background:#5b9bd5; bottom:-100px; right:-60px; animation-delay:-5s; }
    .bg-orb.c{ width:220px; height:220px; background:#8fb8e8; top:42%; left:8%; animation-delay:-9s; opacity:.18; }
    @keyframes orbFloat{
        0%,100%{ transform:translate3d(0,0,0) scale(1); }
        33%    { transform:translate3d(26px,-22px,0) scale(1.08); }
        66%    { transform:translate3d(-20px,18px,0) scale(.95); }
    }

    .container-center{
        position:relative !important;
        top:auto !important;
        left:auto !important;
        transform:none !important;
        margin:0 auto !important;
        min-height:100vh;
        display:flex;
        flex-direction:column;
        justify-content:center;
        align-items:center;
        padding:20px 16px;
    }
    .container-center > *{ position:relative; z-index:1; }

    @keyframes fadeDown{
        from{ opacity:0; transform:translateY(-20px); }
        to  { opacity:1; transform:translateY(0); }
    }
    @keyframes bioSpin{ to{ transform:rotate(360deg); } }

    /* ---- HERO ---- */
    .hero-head{
        text-align:center;
        margin-bottom:64px;
        animation:fadeDown .8s ease both;
    }
    .logo-ring{
        position:relative;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:96px; height:96px;
        border-radius:50%;
        background:rgba(255,255,255,.08);
        border:1px solid rgba(255,255,255,.30);
        backdrop-filter:blur(10px);
        box-shadow:0 14px 36px rgba(10,25,60,.35), inset 0 0 26px rgba(255,255,255,.08);
        margin-bottom:8px;
    }
    .logo-ring::before{
        content:'';
        position:absolute;
        inset:-8px;
        border-radius:50%;
        border:2px solid transparent;
        border-top-color:#ffffff;
        border-right-color:rgba(255,255,255,.45);
        animation:bioSpin 8s linear infinite;
    }
    .logo-ring .hris-logo{
        width:72px; height:auto; margin:0;
        filter:drop-shadow(0 6px 14px rgba(10,25,60,.40));
    }

    .greet-line{
        font-size:.98rem;
        color:rgba(255,255,255,.92);
        letter-spacing:.4px;
        min-height:1.4rem;
        margin-bottom:2px;
        font-weight:700;
    }
    .greet-line .greet-static{ color:#fff; }

    .type-text{
        font-size:1.55rem;
        font-weight:300;
        color:#fff;
        text-shadow:0 3px 18px rgba(10,25,60,.30);
        min-height:2rem;
        letter-spacing:.6px;
    }
    .type-caret{
        display:inline-block;
        width:3px;
        height:1.02em;
        vertical-align:-.14em;
        margin-left:4px;
        background:#ffffff;
        box-shadow:0 0 8px rgba(255,255,255,.8);
        animation:caretBlink 1s steps(1) infinite;
    }
    @keyframes caretBlink{ 0%,49%{opacity:1} 50%,100%{opacity:0} }

    .greet-sub{
        color:rgba(255,255,255,.85);
        font-size:.84rem;
        margin-top:6px;
        margin-bottom:0;
        letter-spacing:.2px;
    }

    .ai-badge-row{ margin-bottom:8px; }
    .ai-badge-row .ai-badge{ margin-right:0; }

    /* ---- AI INTEGRATED badge: putih dengan teks biru ---- */
    .ai-badge{
        display:inline-flex;
        align-items:center;
        font-weight:800;
        font-size:.84rem;
        letter-spacing:.2px;
        text-transform:none;
        margin-right:.35rem;
        padding:.2rem .6rem;
        border-radius:999px;
        color:var(--blue-700);
        background:#ffffff;
        box-shadow:0 4px 14px rgba(10,25,60,.28);
        white-space:nowrap;
        -webkit-text-fill-color:var(--blue-700);
    }
    .ai-badge i{
        font-size:.8rem;
        color:var(--blue-700);
        -webkit-text-fill-color:var(--blue-700);
    }

    /* ---- MENU CARDS: padat, glass putih ---- */
    .menu-wrapper{
        width:100%;
        max-width:960px;
        margin:0 auto;
        padding:0;
    }
    /* Jarak antar card dirapatkan */
    .menu-wrapper .row{
        --bs-gutter-x:.6rem;
        --bs-gutter-y:.6rem;
        row-gap:0;
        margin-left:-.3rem;
        margin-right:-.3rem;
    }
    .menu-wrapper .row > [class*="col-"]{
        padding:.3rem;
    }

    .menu-card{
        border:1px solid rgba(255,255,255,.28);
        border-radius:14px;
        background:linear-gradient(140deg, rgba(255,255,255,.18) 0%, rgba(255,255,255,.08) 100%);
        backdrop-filter:blur(10px);
        transition:.28s cubic-bezier(.2,.8,.2,1);
        overflow:hidden;
        position:relative;
        box-shadow:0 8px 20px rgba(10,25,60,.22);
        height:100%;
        display:block;
    }
    .menu-card::before{
        content:'';
        position:absolute;
        top:0; left:0; right:0;
        height:2px;
        background:linear-gradient(90deg,transparent,rgba(255,255,255,.85),transparent);
        opacity:.7;
        transition:.3s;
    }
    .menu-card:hover{
        transform:translateY(-3px);
        background:linear-gradient(140deg, rgba(255,255,255,.28) 0%, rgba(255,255,255,.14) 100%);
        border-color:rgba(255,255,255,.55);
        box-shadow:0 14px 28px rgba(10,25,60,.34);
    }
    .menu-card:hover::before{ opacity:1; }

    .menu-card-body{
        display:flex;
        align-items:center;
        gap:.6rem;
        padding:.55rem .7rem;
    }
    .menu-icon-wrapper{
        flex:0 0 auto;
        width:38px; height:38px;
        border-radius:11px;
        display:flex;
        justify-content:center;
        align-items:center;
        background:#ffffff;
        box-shadow:0 4px 12px rgba(10,25,60,.20);
        transition:.28s cubic-bezier(.2,.8,.2,1);
    }
    .menu-icon-wrapper i{
        font-size:1.05rem;
        color:var(--blue-700) !important; /* semua icon seragam biru */
        transition:transform .3s ease;
    }
    .menu-card:hover .menu-icon-wrapper{
        background:var(--blue-700);
        transform:rotate(-6deg) scale(1.06);
    }
    .menu-card:hover .menu-icon-wrapper i{
        color:#fff !important;
        transform:scale(1.08);
    }
    .menu-card-label{
        min-width:0;
        flex:1 1 auto;
    }
    .menu-card h5{
        font-size:.84rem;
        margin:0;
        font-weight:700;
        color:#ffffff;
        letter-spacing:.1px;
        line-height:1.25;
        word-break:break-word;
    }
    .menu-card-sub{
        font-size:.65rem;
        color:rgba(255,255,255,.70);
        margin:.05rem 0 0;
        letter-spacing:.2px;
    }
    .menu-card-arrow{
        flex:0 0 auto;
        color:rgba(255,255,255,.45);
        font-size:.7rem;
        transition:.3s;
    }
    .menu-card:hover .menu-card-arrow{
        color:#ffffff;
        transform:translateX(3px);
    }

    /* ---- Responsive ---- */
    @media (max-width:1200px){
        .menu-wrapper{ max-width:100%; }
    }
    @media (min-width:992px){
        .menu-wrapper .row > [class*="col-"]{ flex:0 0 25%; max-width:25%; }
    }
    @media (max-width:991.98px){
        .menu-wrapper .row > [class*="col-"]{ flex:0 0 33.3333%; max-width:33.3333%; }
    }
    @media (max-width:767.98px){
        .menu-wrapper .row > [class*="col-"]{ flex:0 0 50%; max-width:50%; }
    }
    @media (max-width:575.98px){
        .container-center{ padding:18px 10px; }
        .logo-ring{ width:80px; height:80px; margin-bottom:6px; }
        .logo-ring .hris-logo{ width:58px; }
        .greet-line{ font-size:.82rem; }
        .type-text{ font-size:1.1rem; min-height:1.6rem; }
        .greet-sub{ font-size:.72rem; }
        .hero-head{ margin-bottom:44px; }
        .menu-card-body{ padding:.5rem .55rem; gap:.5rem; }
        .menu-icon-wrapper{ width:34px; height:34px; border-radius:10px; }
        .menu-icon-wrapper i{ font-size:.95rem; }
        .menu-card h5{ font-size:.74rem; }
        .menu-card-sub{ display:none; }
        .menu-card-arrow{ display:none; }
        .ai-badge{ font-size:.72rem; padding:.16rem .42rem; letter-spacing:.4px; }
    }

    /* Hormati preferensi reduced-motion */
    @media (prefers-reduced-motion: reduce){
        *, *::before, *::after{
            animation-duration:.001ms !important;
            animation-iteration-count:1 !important;
            transition-duration:.001ms !important;
        }
        .type-caret{ animation:none; opacity:1; }
    }

</style>

<div class="bg-orb a"></div>
<div class="bg-orb b"></div>
<div class="bg-orb c"></div>

<div class="container-fluid container-center">

    {{-- ===== HERO: logo + greeting with typewriter animation ===== --}}
    <div class="hero-head">
        <div class="logo-ring">
            <img src="{{ asset('img/chutex.svg') }}" class="hris-logo" alt="Chutex Logo">
        </div>

        {{-- Company name only (no "Welcome, Sir/Madam Staff of ..."). --}}
        <div class="greet-line">
            <span class="greet-static">PT. Chutex International Indonesia</span>
        </div>

        <div class="type-text" id="typeText" aria-live="polite" aria-atomic="true">
            <span id="typeTextInner"></span><span class="type-caret" aria-hidden="true"></span>
        </div>

        <div class="greet-sub">
            <div class="ai-badge-row">
                <span class="ai-badge"><i class="fas fa-brain mr-1"></i><span class="ai-badge-text">AI Integrated</span></span>
            </div>
            <div class="greet-sub-text">Human Resource Information System &mdash; secure, fast, and intelligent.</div>
        </div>
    </div>

    {{-- ===== MENU CARDS ===== --}}
    <div class="menu-wrapper">
        <div class="row justify-content-center">

            @php
                // Card menu dikontrol dari DB (card_menu_settings) agar admin bisa show/hide
                // lewat halaman Pengaturan Card Menu HRIS.
                $hrisCards = \App\Models\CardMenuSetting::visibleCards();
            @endphp

            @forelse($hrisCards as $card)
            <div class="col-lg-3 col-md-4 col-6">

                <a href="{{ $card['route'] ?: '#' }}" class="text-decoration-none d-block h-100">

                    <div class="card menu-card shadow-sm" @if($card['color']) style="--accent: {{ match($card['color']) { 'success' => '#34d399', 'warning' => '#fbbf24', 'danger' => '#fb7185', 'info' => '#38bdf8', 'secondary' => '#94a3b8', default => '#22d3ee' } }};" @endif>

                        <div class="menu-card-body">

                            <div class="menu-icon-wrapper">
                                <i class="{{ $card['icon'] ?: 'fas fa-circle' }} text-{{ $card['color'] ?: 'primary' }}"></i>
                            </div>

                            <div class="menu-card-label">
                                <h5>{{ $card['name'] }}</h5>
                                <p class="menu-card-sub">{{ $card['subtitle'] ?? '' }}</p>
                            </div>

                            <i class="fas fa-chevron-right menu-card-arrow" aria-hidden="true"></i>

                        </div>

                    </div>

                </a>

            </div>
            @empty
            <div class="col-12 text-center text-white">
                <p class="mb-0"><i class="fas fa-info-circle mr-1"></i> No modules are currently visible.</p>
            </div>
            @endforelse

        </div>
    </div>

</div>

@include('layout.footerscript')

{{-- ==========================================================
     TYPEWRITER ANIMATION
     Types each greeting sentence letter by letter, then deletes it
     and moves to the next one (endless cycle).
     Respects prefers-reduced-motion: shows full text immediately.
     ========================================================== --}}
<script>
(function () {
    'use strict';

    var el = document.getElementById('typeTextInner');
    if (!el) { return; }

    var sentences = [
        'Welcome to CHUTEX HRIS',
        'Effortless Attendance with Face Recognition',
        'Your attendance is recorded in seconds',
        'Secure, Fast, and Intelligent HR Data',
        'Please select the module you need'
    ];

    var reduceMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion) {
        el.textContent = sentences[0];
        return;
    }

    var TYPE_SPEED = 55;    // ms per character
    var DELETE_SPEED = 28;  // ms per character while deleting
    var HOLD_TIME = 2200;   // pause after typing completes
    var GAP_TIME = 500;     // pause before typing the next sentence

    var sIdx = 0;
    var cIdx = 0;
    var deleting = false;

    function tick() {
        var sentence = sentences[sIdx];
        var delay;

        if (!deleting) {
            cIdx += 1;
            el.textContent = sentence.slice(0, cIdx);
            delay = TYPE_SPEED;

            if (cIdx === sentence.length) {
                deleting = true;
                delay = HOLD_TIME;
            }
        } else {
            cIdx -= 1;
            el.textContent = sentence.slice(0, cIdx);
            delay = DELETE_SPEED;

            if (cIdx === 0) {
                deleting = false;
                sIdx = (sIdx + 1) % sentences.length;
                delay = GAP_TIME;
            }
        }

        window.setTimeout(tick, delay);
    }

    // Start slightly later so the entrance feels smooth.
    window.setTimeout(tick, 400);
})();
</script>

</body>
</html>