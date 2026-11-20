<?php
/**
 * "What's New" — dua mode:
 *
 *   mode = 'modal' (default sejak 17 Nov 2026 — HRIS v1 & v2)
 *     Tampil sebagai dialog sekali sehari (localStorage menyimpan tanggal
 *     terakhir dibuka), plus floating button yang bisa membuka ulang kapan
 *     saja. Dialog bisa ditutup lewat tombol X, klik latar, atau Esc.
 *
 *   mode = 'slider' (warisan, tidak dipakai halaman mana pun)
 *     Banner mendatar di atas konten. Tombol tutup menyimpan sidik isi banner
 *     di localStorage; banner baru/berubah otomatis muncul lagi. Disimpan
 *     hanya agar bisa dipakai lagi bila suatu saat dibutuhkan.
 *
 * Prinsip umum:
 * - Gagal senyap: DB/tabel bermasalah => tidak merender apa pun.
 * - Tanpa dependensi (CSS + JS vanilla).
 * - Video langsung autoplay dalam keadaan bisu (syarat browser); suara bisa
 *   dinyalakan lewat kontrol video.
 * - Menghormati prefers-reduced-motion (slideshow otomatis dimatikan).
 */
$banners = \App\Support\WhatsNew::aktif();
$sidik = \App\Support\WhatsNew::sidik($banners);
$jumlah = $banners->count();
$mode = $mode ?? 'modal';
$modal = $mode === 'modal';
?>
@if ($jumlah > 0)

@if ($modal)
<button type="button" class="wn-fab" id="wn-fab" aria-haspopup="dialog" aria-controls="wn-modal"
        aria-label="Buka pengumuman What's New" title="What's New">
    <i class="fas fa-bullhorn" aria-hidden="true"></i>
    <span class="wn-fab-teks">What's New</span>
    <span class="wn-fab-tanda" id="wn-fab-tanda" aria-hidden="true"></span>
</button>

<div class="wn-overlay" id="wn-overlay" hidden>
    <div class="wn wn--modal" id="wn-modal" role="dialog" aria-modal="true"
         aria-label="Pengumuman What's New" data-wn-mode="modal" data-wn-sidik="{{ $sidik }}" tabindex="-1">
        @include('partials._whats_new_isi', ['banners' => $banners, 'jumlah' => $jumlah, 'modal' => true])
    </div>
</div>
@else
<section class="wn" id="wn-slider" aria-label="What's New" data-wn-mode="slider" data-wn-sidik="{{ $sidik }}">
    <script>
        /* Sembunyikan sebelum sempat dicat bila pengguna sudah menutup
           banner versi ini (isi banner tidak berubah). */
        try {
            if (window.localStorage.getItem('chutex_whats_new') === '{{ $sidik }}') {
                var wnEl = document.getElementById('wn-slider');
                if (wnEl) { wnEl.style.display = 'none'; }
            }
        } catch (e) { /* localStorage diblokir: tetap tampil */ }
    </script>
    @include('partials._whats_new_isi', ['banners' => $banners, 'jumlah' => $jumlah, 'modal' => false])
</section>
@endif

<style>
    /* ---- What's New · dasar (tema terang) ---- */
    .wn,
    .wn *,
    .wn *::before,
    .wn *::after { box-sizing: border-box; }

    .wn {
        --wn-bg: #ffffff;
        --wn-bg-2: #f6f8fc;
        --wn-line: #e3e8f2;
        --wn-judul: #0f172a;
        --wn-teks: #475569;
        --wn-muted: #64748b;
        --wn-aksen: #1d3f86;
        --wn-aksen-2: #3a6fa8;
        --wn-cyan: #0e9bb5;
        position: relative;
        max-width: 1180px;
        width: calc(100% - 30px);
        margin: 12px auto 18px;
        background: var(--wn-bg);
        border: 1px solid var(--wn-line);
        border-radius: 16px;
        box-shadow: 0 8px 26px rgba(16, 24, 40, .07);
        overflow: hidden;
        font-family: inherit;
        color: var(--wn-teks);
    }
    .wn-kepala {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px 6px;
    }
    .wn-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--wn-aksen);
        background: rgba(29, 63, 134, .08);
        border-radius: 999px;
        padding: 4px 10px;
    }
    .wn-hitung { font-size: .75rem; color: var(--wn-muted); margin-left: 2px; }
    .wn-tutup {
        margin-left: auto;
        width: 30px; height: 30px;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid var(--wn-line);
        border-radius: 50%;
        background: transparent;
        color: var(--wn-muted);
        cursor: pointer;
        transition: background .18s ease, color .18s ease;
    }
    .wn-tutup:hover, .wn-tutup:focus-visible {
        background: var(--wn-bg-2);
        color: var(--wn-judul);
        outline: none;
    }
    .wn-viewport { position: relative; overflow: hidden; }
    .wn-track {
        display: flex;
        transition: transform .45s cubic-bezier(.4, 0, .2, 1);
        will-change: transform;
    }
    .wn-slide {
        flex: 0 0 100%;
        width: 100%;
        min-width: 0;
        display: grid;
        grid-template-columns: minmax(0, 420px) minmax(0, 1fr);
        gap: 20px;
        align-items: center;
        padding: 10px 16px 16px;
    }
    .wn-media {
        position: relative;
        aspect-ratio: 16 / 9;
        border-radius: 12px;
        overflow: hidden;
        background: #0b1220;
        border: 1px solid var(--wn-line);
    }
    .wn-media img,
    .wn-media iframe,
    .wn-media video {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
        border: 0;
    }
    .wn-media-teks {
        width: 100%; height: 100%;
        display: flex; align-items: center; justify-content: center;
        color: #93a4c0; font-size: 1.8rem;
        background: linear-gradient(135deg, #16233d, #0b1220);
    }
    .wn-isi { min-width: 0; }
    .wn-judul {
        margin: 0 0 8px;
        font-size: 1.2rem;
        line-height: 1.35;
        font-weight: 700;
        color: var(--wn-judul);
    }
    .wn-teks {
        margin: 0 0 14px;
        font-size: .94rem;
        line-height: 1.6;
        color: var(--wn-teks);
        white-space: pre-line;
        max-height: 6.4em;
        overflow: auto;
    }
    .wn-tombol {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: .55rem 1.05rem;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--wn-aksen), var(--wn-aksen-2));
        color: #ffffff !important;
        font-weight: 600;
        font-size: .9rem;
        text-decoration: none !important;
        box-shadow: 0 6px 16px rgba(29, 63, 134, .22);
        transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }
    .wn-tombol:hover,
    .wn-tombol:focus,
    .wn-tombol:active { text-decoration: none !important; color: #ffffff !important; filter: brightness(1.06); transform: translateY(-1px); }
    .wn-tombol:focus-visible { outline: 2px solid var(--wn-cyan); outline-offset: 2px; }

    .wn-panah {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 34px; height: 34px;
        display: none;
        align-items: center; justify-content: center;
        border-radius: 50%;
        border: 1px solid var(--wn-line);
        background: var(--wn-bg);
        color: var(--wn-aksen);
        box-shadow: 0 4px 14px rgba(16, 24, 40, .14);
        cursor: pointer;
    }
    .wn-panah:hover { background: var(--wn-bg-2); }
    .wn-panah-kiri { left: 8px; }
    .wn-panah-kanan { right: 8px; }

    .wn-titik {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 0 16px 14px;
    }
    .wn-titik-item {
        width: 8px; height: 8px;
        border-radius: 999px;
        border: 0;
        padding: 0;
        background: #cbd5e1;
        cursor: pointer;
        transition: width .2s ease, background .2s ease;
    }
    .wn-titik-item.aktif { width: 22px; background: var(--wn-aksen); }

    @media (min-width: 992px) {
        .wn-panah { display: inline-flex; }
    }
    /* Layar sempit: media di atas, teks di bawah. Panah disembunyikan
       supaya tidak menutupi teks — navigasi lewat titik dan geser. */
    @media (max-width: 991.98px) {
        .wn-slide { grid-template-columns: minmax(0, 1fr); gap: 12px; }
        .wn-judul { font-size: 1.08rem; }
        .wn-teks { max-height: 6em; }
    }
    @media (max-width: 575.98px) {
        .wn-slide { padding: 8px 12px 12px; }
        .wn-judul { font-size: 1.02rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .wn-track { transition: none; }
        .wn-tombol { transition: none; }
    }

    /* ---- Mode modal (HRIS v2) ---- */
    .wn-overlay {
        position: fixed;
        inset: 0;
        z-index: 1060;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(6, 12, 24, .62);
        -webkit-backdrop-filter: blur(2px);
        backdrop-filter: blur(2px);
        animation: wn-muncul .18s ease-out;
    }
    .wn-overlay[hidden] { display: none; }
    @keyframes wn-muncul { from { opacity: 0; } to { opacity: 1; } }
    .wn--modal {
        width: min(1000px, 100%);
        max-height: 92vh;
        margin: 0;
        overflow: auto;
        border-radius: 18px;
        box-shadow: 0 30px 80px rgba(0, 0, 0, .45);
    }
    .wn--modal .wn-slide { grid-template-columns: minmax(0, 400px) minmax(0, 1fr); }
    /* Banner tanpa media: di dalam modal kotak placeholder 16:9 hanya
       menghasilkan bidang gelap kosong yang mendorong judul ke bawah lipatan.
       Sembunyikan; judul + ringkasan langsung terlihat. */
    .wn--modal .wn-media--none,
    .wn--modal .wn-media--teks { display: none; }
    .wn--modal .wn-tutup { width: 34px; height: 34px; }
    body.wn-terkunci { overflow: hidden; }

    /* Floating button pembuka modal.
       Posisi: kanan bawah, TEPAT DI ATAS tombol AI Assistant (yang memakai
       right:22px; bottom:22px; tinggi ±58px). Bila AI Assistant tidak hadir
       (mis. halaman login), skrip di bawah menambahkan .wn-fab-sendiri agar
       tombol turun ke dasar layar dan tidak menyisakan ruang kosong. */
    .wn-fab {
        position: fixed;
        right: 22px;
        bottom: 92px;
        z-index: 1040;
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 10px 16px 10px 13px;
        border-radius: 999px;
        border: 1px solid rgba(34, 211, 238, .45);
        background: linear-gradient(135deg, rgba(29, 63, 134, .97), rgba(58, 111, 168, .97));
        color: #ffffff;
        font-size: 12.5px;
        font-weight: 600;
        letter-spacing: .01em;
        text-decoration: none !important;
        box-shadow: 0 12px 30px rgba(15, 37, 87, .32);
        cursor: pointer;
        transition: transform .16s ease, box-shadow .16s ease;
    }
    .wn-fab.wn-fab-sendiri { bottom: 22px; }
    .wn-fab:hover, .wn-fab:focus-visible {
        transform: translateY(-2px);
        box-shadow: 0 16px 36px rgba(15, 37, 87, .42);
        color: #ffffff;
        outline: none;
    }
    .wn-fab i { font-size: 14px; }
    .wn-fab-tanda {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #22d3ee;
        box-shadow: 0 0 0 3px rgba(34, 211, 238, .25);
    }
    .wn-fab-tanda[hidden] { display: none; }
    @media (max-width: 640px) {
        .wn-fab { right: 14px; bottom: 84px; padding: 11px; }
        .wn-fab.wn-fab-sendiri { bottom: 14px; }
        .wn-fab-teks { display: none; }
        .wn--modal { max-height: 94vh; }
    }

    /* ---- Modal di layar sempit ----------------------------------------
       .wn--modal .wn-slide di atas memaksa dua kolom (400px + 1fr) dan
       spesifisitasnya LEBIH TINGGI daripada media query dasar di atas,
       sehingga di layar < 992px kolom teks menyusut jadi 0 px dan isinya
       menumpuk di atas video/gambar. Blok ini diletakkan SETELAH aturan
       modal agar menang urutan, dan mengembalikan susunan jadi satu kolom:
       media di atas, judul/teks/tombol di bawah. */
    @media (max-width: 991.98px) {
        .wn--modal .wn-slide {
            grid-template-columns: minmax(0, 1fr);
            align-items: start;
            gap: 14px;
        }
        .wn--modal .wn-media { width: 100%; }
        /* Poster (biasanya potret) tidak dipotong: biarkan tinggi mengikuti
           gambar, dibatasi 52vh supaya teks tetap terlihat. */
        .wn--modal .wn-media--gambar { aspect-ratio: auto; }
        .wn--modal .wn-media--gambar img {
            width: 100%; height: auto; max-height: 52vh;
            object-fit: contain; background: #0b1220;
        }
    }
    @media (max-width: 640px) {
        .wn-overlay { padding: 10px; align-items: flex-start; }
        .wn--modal {
            width: 100%;
            max-height: calc(100dvh - 20px);
            border-radius: 16px;
        }
        .wn--modal .wn-kepala { padding: 10px 12px 4px; }
        .wn--modal .wn-slide { padding: 8px 12px 12px; gap: 12px; }
        .wn--modal .wn-judul { font-size: 1rem; margin-bottom: 6px; }
        /* Teks tidak lagi dipotong 6em: biarkan modal yang menggulir. */
        .wn--modal .wn-teks { max-height: none; font-size: .9rem; }
        .wn--modal .wn-titik { padding: 0 12px 12px; }
    }

    /* ---- What's New · tema gelap (HRIS v2) ---- */
    html[data-v2-theme="dark"] .wn {
        --wn-bg: #0d1524;
        --wn-bg-2: rgba(255, 255, 255, .08);
        --wn-line: rgba(255, 255, 255, .10);
        --wn-judul: #eef3fb;
        --wn-teks: #c7d3e6;
        --wn-muted: #8fa1bd;
        --wn-aksen: #3b8cff;
        --wn-aksen-2: #1f5fd0;
        --wn-cyan: #22d3ee;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .35);
    }
    html[data-v2-theme="dark"] .wn-badge {
        color: #22d3ee;
        background: rgba(34, 211, 238, .12);
    }
    html[data-v2-theme="dark"] .wn-titik-item { background: rgba(255, 255, 255, .22); }
    html[data-v2-theme="dark"] .wn-titik-item.aktif { background: #3b8cff; }
    html[data-v2-theme="dark"] .wn-media { border-color: rgba(255, 255, 255, .10); }
</style>

<script>
    (function () {
        var akar = document.getElementById('wn-modal') || document.getElementById('wn-slider');
        if (!akar) { return; }

        var modal = akar.getAttribute('data-wn-mode') === 'modal';
        var overlay = document.getElementById('wn-overlay');
        var fab = document.getElementById('wn-fab');
        var fabTanda = document.getElementById('wn-fab-tanda');

        /* Floating button menumpang di atas tombol AI Assistant. Bila tombol itu
           tidak dirender (halaman login, atau chatbot), turunkan ke dasar layar
           supaya tidak ada celah kosong menggantung. Dicek setelah DOM siap:
           partial AI Assistant bisa saja dirender setelah partial ini. */
        function aturPosisiFab() {
            if (!fab) { return; }
            if (!document.getElementById('v2-ai-fab')) {
                fab.classList.add('wn-fab-sendiri');
            } else {
                fab.classList.remove('wn-fab-sendiri');
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', aturPosisiFab);
        } else {
            aturPosisiFab();
        }
        var track = document.getElementById('wn-track');
        var viewport = document.getElementById('wn-viewport');
        var posisi = document.getElementById('wn-posisi');
        var slides = track ? Array.prototype.slice.call(track.children) : [];
        var titik = Array.prototype.slice.call(akar.querySelectorAll('[data-wn-dot]'));
        var total = slides.length;
        var aktif = 0;
        var timer = null;
        var JEDA = 7000;
        var KUNCI_TANGGAL = 'chutex_wn_tanggal';

        function hariIni() {
            var d = new Date();
            var b = function (n) { return (n < 10 ? '0' : '') + n; };
            return d.getFullYear() + '-' + b(d.getMonth() + 1) + '-' + b(d.getDate());
        }

        function bacaKunci() {
            try { return window.localStorage.getItem(KUNCI_TANGGAL); } catch (e) { return null; }
        }

        function tulisKunci() {
            try { window.localStorage.setItem(KUNCI_TANGGAL, hariIni()); } catch (e) { /* diabaikan */ }
        }

        /* Video slide aktif diputar otomatis (bisu); slide lain dihentikan. */
        function aturMedia() {
            slides.forEach(function (s, i) {
                var video = s.querySelector('[data-wn-video]');
                if (video) {
                    if (i === aktif) {
                        video.muted = true;
                        var janji = video.play();
                        if (janji && janji.catch) { janji.catch(function () { /* diblokir browser */ }); }
                    } else if (!video.paused) {
                        video.pause();
                    }
                }

                var embed = s.querySelector('.wn-embed');
                if (embed) {
                    var src = embed.getAttribute('data-src');
                    if (!src) { return; }
                    if (i === aktif) {
                        if (embed.getAttribute('src') !== src) { embed.setAttribute('src', src); }
                    } else if (embed.hasAttribute('src')) {
                        embed.removeAttribute('src');
                    }
                }
            });
        }

        function ke(index) {
            if (total === 0) { return; }
            aktif = (index + total) % total;
            track.style.transform = 'translateX(' + (-aktif * 100) + '%)';

            slides.forEach(function (s, i) {
                s.setAttribute('aria-hidden', i === aktif ? 'false' : 'true');
            });

            titik.forEach(function (t, i) {
                t.classList.toggle('aktif', i === aktif);
                t.setAttribute('aria-selected', i === aktif ? 'true' : 'false');
            });

            if (posisi) { posisi.textContent = String(aktif + 1); }

            /* Di mode modal, media baru dinyalakan saat dialog terbuka supaya
               tidak ada video berjalan di balik overlay. */
            if (!modal || (overlay && !overlay.hidden)) { aturMedia(); }
        }

        function berikutnya() { ke(aktif + 1); }
        function sebelumnya() { ke(aktif - 1); }

        function bisaAnimasi() {
            return !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        }

        function mulai() {
            hentikan();
            if (total < 2 || !bisaAnimasi()) { return; }
            timer = window.setInterval(berikutnya, JEDA);
        }

        function hentikan() {
            if (timer) { window.clearInterval(timer); timer = null; }
        }

        akar.querySelectorAll('[data-wn-next]').forEach(function (b) {
            b.addEventListener('click', function () { berikutnya(); mulai(); });
        });
        akar.querySelectorAll('[data-wn-prev]').forEach(function (b) {
            b.addEventListener('click', function () { sebelumnya(); mulai(); });
        });
        titik.forEach(function (t) {
            t.addEventListener('click', function () {
                ke(parseInt(t.getAttribute('data-wn-dot'), 10) || 0);
                mulai();
            });
        });

        /* Berhenti saat kursor/fokus di dalam panel: video bisa ditonton. */
        akar.addEventListener('mouseenter', hentikan);
        akar.addEventListener('mouseleave', function () { if (!modal) { mulai(); } });
        akar.addEventListener('focusin', hentikan);
        akar.addEventListener('focusout', function () { if (!modal) { mulai(); } });
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) { hentikan(); } else if (!modal) { mulai(); }
        });

        /* Geser di layar sentuh. */
        var awalX = null, awalY = null;
        if (viewport) {
            viewport.addEventListener('touchstart', function (e) {
                awalX = e.touches[0].clientX;
                awalY = e.touches[0].clientY;
            }, { passive: true });

            viewport.addEventListener('touchend', function (e) {
                if (awalX === null) { return; }
                var dx = e.changedTouches[0].clientX - awalX;
                var dy = e.changedTouches[0].clientY - awalY;
                awalX = null; awalY = null;
                if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
                    if (dx < 0) { berikutnya(); } else { sebelumnya(); }
                    mulai();
                }
            }, { passive: true });

            viewport.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowRight') { berikutnya(); mulai(); }
                if (e.key === 'ArrowLeft') { sebelumnya(); mulai(); }
            });
        }

        /* ---------- mode modal: buka / tutup ---------- */
        function bukaModal() {
            if (!overlay) { return; }
            overlay.hidden = false;
            document.body.classList.add('wn-terkunci');
            tulisKunci();
            if (fabTanda) { fabTanda.hidden = true; }
            ke(aktif);
            mulai();
            if (akar.focus) { akar.focus({ preventScroll: true }); }
        }

        function tutupModal() {
            if (!overlay) { return; }
            overlay.hidden = true;
            document.body.classList.remove('wn-terkunci');
            hentikan();
            /* Hentikan video & embed supaya tidak berjalan di balik layar. */
            slides.forEach(function (s) {
                var v = s.querySelector('[data-wn-video]');
                if (v && !v.paused) { v.pause(); }
                var e = s.querySelector('.wn-embed');
                if (e && e.hasAttribute('src')) { e.removeAttribute('src'); }
            });
            if (fab) { fab.focus({ preventScroll: true }); }
        }

        /* Tombol tutup: beda perlakuan antara slider dan modal. */
        var tutup = akar.querySelector('[data-wn-tutup]');
        if (tutup) {
            tutup.addEventListener('click', function () {
                if (modal) { tutupModal(); return; }
                hentikan();
                try {
                    window.localStorage.setItem('chutex_whats_new', akar.getAttribute('data-wn-sidik') || '1');
                } catch (e) { /* localStorage diblokir: cukup sembunyikan */ }
                akar.style.display = 'none';
            });
        }

        if (modal) {
            if (fab) { fab.addEventListener('click', bukaModal); }
            if (overlay) {
                overlay.addEventListener('click', function (e) {
                    if (e.target === overlay) { tutupModal(); }
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && overlay && !overlay.hidden) { tutupModal(); }
            });

            ke(0);

            /* Muncul otomatis sekali sehari. */
            if (bacaKunci() !== hariIni()) {
                window.setTimeout(bukaModal, 450);
            } else if (fabTanda) {
                fabTanda.hidden = true;
            }
        } else {
            ke(0);
            mulai();
        }
    })();
</script>
@endif