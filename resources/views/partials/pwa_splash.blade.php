{{-- Splash PWA (memakai gambar yang dikirim user: splash/chutex-hris-splash.jpg).
     Tampil HANYA saat aplikasi dibuka sebagai PWA terpasang (display-mode: standalone
     / fullscreen / iOS standalone) dan hanya sekali per sesi, lalu overlay dibuang
     dari DOM supaya tidak pernah menutupi kamera atau tombol apa pun. --}}
<div id="pwaSplash" class="pwa-splash" role="presentation" aria-hidden="true">
    <img src="{{ asset('splash/chutex-hris-splash.jpg') }}" alt="Chutex HRIS" class="pwa-splash__img">
    <div class="pwa-splash__bar"><span></span></div>
</div>

<style>
    .pwa-splash {
        position: fixed;
        inset: 0;
        z-index: 4000;
        display: none;
        background: #074eba;
        opacity: 1;
        transition: opacity .35s ease;
    }
    .pwa-splash.is-tampil { display: block; }
    .pwa-splash.is-hilang { opacity: 0; }
    .pwa-splash__img {
        display: block;
        width: 100%;
        height: 100%;
        /* contain: seluruh gambar splash terlihat utuh (tidak terpotong di layar
           sempit/lebar). Sisa ruang atas-bawah memakai warna #074eba yang sama
           dengan tepi gambar, jadi terlihat menyatu. */
        object-fit: contain;
        object-position: center;
    }
    .pwa-splash__bar {
        position: absolute;
        left: 50%;
        bottom: 26px;
        transform: translateX(-50%);
        width: 96px;
        height: 4px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .28);
        overflow: hidden;
    }
    .pwa-splash__bar span {
        display: block;
        width: 40%;
        height: 100%;
        border-radius: 999px;
        background: #7dd3fc;
        animation: pwaSplashBar 1.1s ease-in-out infinite;
    }
    @keyframes pwaSplashBar {
        0% { transform: translateX(-110%); }
        100% { transform: translateX(260%); }
    }
    @media (prefers-reduced-motion: reduce) {
        .pwa-splash__bar { display: none; }
    }
</style>

<script>
    (function () {
        var standalone = false;
        try {
            standalone = window.matchMedia('(display-mode: standalone)').matches
                || window.matchMedia('(display-mode: fullscreen)').matches
                || window.navigator.standalone === true;
        } catch (e) { standalone = false; }

        var splash = document.getElementById('pwaSplash');
        if (!splash || !splash.parentNode) { return; }

        var sudahTampil = false;
        try { sudahTampil = sessionStorage.getItem('chutexPwaSplash') === '1'; } catch (e) { sudahTampil = false; }

        // Browser biasa (bukan PWA terpasang) tidak perlu splash sama sekali.
        if (!standalone || sudahTampil) {
            splash.parentNode.removeChild(splash);
            return;
        }

        try { sessionStorage.setItem('chutexPwaSplash', '1'); } catch (e) {}

        splash.classList.add('is-tampil');
        var mulai = Date.now();
        var selesai = false;

        function tutup() {
            if (selesai) { return; }
            selesai = true;
            var tunggu = Math.max(0, 900 - (Date.now() - mulai));
            setTimeout(function () {
                splash.classList.add('is-hilang');
                setTimeout(function () {
                    if (splash.parentNode) { splash.parentNode.removeChild(splash); }
                }, 400);
            }, tunggu);
        }

        if (document.readyState === 'complete') { tutup(); } else { window.addEventListener('load', tutup); }
        setTimeout(tutup, 3500); // jaring pengaman
    })();
</script>
