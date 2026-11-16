{{--
    Latar 3D particle untuk seluruh halaman HRIS v2.

    Titik-titik (BufferGeometry) bermorfosis membentuk:
      logo Chutex  ->  bola  ->  gelombang  ->  torus knot  ->  logo (loop)

    Catatan teknis:
      - Three.js dimuat lokal (public/vendor/three/three.min.js), bukan CDN,
        supaya tetap jalan di jaringan kantor yang tanpa internet.
      - Bentuk logo diambil dari alpha channel public/img/chutex_logo.png
        (43,9% piksel transparan) sehingga partikel mengikuti siluet asli.
      - Jumlah partikel & pixel ratio diturunkan di layar kecil / perangkat
        lemah; animasi berhenti saat tab tidak aktif (document.hidden).
      - prefers-reduced-motion: satu frame statis, tanpa loop.
      - Kalau WebGL tidak tersedia, canvas disembunyikan dan halaman tetap
        tampil normal (latar gradien CSS di body).
--}}
<div class="v2-scene" aria-hidden="true">
    <canvas id="v2-canvas"></canvas>
    <div class="v2-vignette"></div>
    <div class="v2-grid-glow"></div>
</div>

<script src="{{ asset('vendor/three/three.min.js') }}"></script>
<script>
(function () {
    'use strict';

    var canvas = document.getElementById('v2-canvas');
    if (!canvas || typeof THREE === 'undefined') {
        if (canvas) { canvas.style.display = 'none'; }
        return;
    }

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var kecil = window.innerWidth < 768;
    var N = kecil ? 2600 : 6500;
    var W = 8.4;                 // lebar bidang logo dalam satuan dunia
    var PI2 = Math.PI * 2;
    var rand = Math.random;

    var renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: false, alpha: true });
    } catch (e) {
        canvas.style.display = 'none';
        return;
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, kecil ? 1.35 : 1.7));
    renderer.setClearColor(0x000000, 0);

    var scene = new THREE.Scene();
    var cam = new THREE.PerspectiveCamera(50, 1, 0.1, 100);
    cam.position.z = 10.4;

    var grup = new THREE.Group();
    scene.add(grup);

    // ---- sprite bulat lembut untuk tiap titik ----
    function sprite() {
        var c = document.createElement('canvas');
        c.width = c.height = 64;
        var g = c.getContext('2d');
        var r = g.createRadialGradient(32, 32, 0, 32, 32, 32);
        r.addColorStop(0, 'rgba(255,255,255,1)');
        r.addColorStop(0.35, 'rgba(255,255,255,.55)');
        r.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = r;
        g.fillRect(0, 0, 64, 64);
        return new THREE.CanvasTexture(c);
    }

    var tex = sprite();
    var geo = new THREE.BufferGeometry();
    var posisi = new Float32Array(N * 3);   // posisi aktual (diubah tiap frame)
    var warna = new Float32Array(N * 3);
    var jitter = new Float32Array(N * 3);
    var delay = new Float32Array(N);

    for (var i = 0; i < N; i++) {
        delay[i] = rand();
        // gradasi biru -> cyan, 6% titik putih sebagai kilau
        var m = Math.pow(rand(), 2.1);
        var putih = rand() < 0.06 ? 1 : 0;
        warna[i * 3] = 0.10 + 0.35 * m + putih;
        warna[i * 3 + 1] = 0.42 + 0.42 * m + putih * 0.5;
        warna[i * 3 + 2] = 0.92 + 0.08 * m;
        jitter[i * 3] = (rand() - 0.5) * 2;
        jitter[i * 3 + 1] = (rand() - 0.5) * 2;
        jitter[i * 3 + 2] = (rand() - 0.5) * 2;
    }

    geo.setAttribute('position', new THREE.BufferAttribute(posisi, 3));
    geo.setAttribute('color', new THREE.BufferAttribute(warna, 3));
    var material = new THREE.PointsMaterial({
        size: kecil ? 0.085 : 0.07,
        map: tex,
        vertexColors: true,
        transparent: true,
        opacity: 0.95,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        sizeAttenuation: true
    });
    grup.add(new THREE.Points(geo, material));

    // ---- bintang latar ----
    var bintangPos = new Float32Array(520 * 3);
    for (var s = 0; s < 520; s++) {
        var r2 = 14 + rand() * 30;
        var a2 = rand() * PI2;
        var b2 = Math.acos(2 * rand() - 1);
        bintangPos[s * 3] = r2 * Math.sin(b2) * Math.cos(a2);
        bintangPos[s * 3 + 1] = r2 * Math.cos(b2);
        bintangPos[s * 3 + 2] = r2 * Math.sin(b2) * Math.sin(a2);
    }
    var geoS = new THREE.BufferGeometry();
    geoS.setAttribute('position', new THREE.BufferAttribute(bintangPos, 3));
    var bintang = new THREE.Points(geoS, new THREE.PointsMaterial({
        size: 0.13, map: tex, color: 0x5d8fd6, transparent: true, opacity: 0.5,
        depthWrite: false, blending: THREE.AdditiveBlending
    }));
    scene.add(bintang);

    // ---- pembuat bentuk ----
    function bentuk(fn) {
        var a = new Float32Array(N * 3);
        for (var i = 0; i < N; i++) {
            var p = fn(i);
            a[i * 3] = p[0];
            a[i * 3 + 1] = p[1];
            a[i * 3 + 2] = p[2];
        }
        return a;
    }

    function miring(p, a) {
        var c = Math.cos(a), s = Math.sin(a);
        return [p[0], p[1] * c - p[2] * s, p[1] * s + p[2] * c];
    }

    var bola = bentuk(function (i) {
        var k = i + 0.5;
        var ph = Math.acos(1 - 2 * k / N);
        var th = Math.PI * (1 + Math.sqrt(5)) * k;
        var r = 3.05;
        return [r * Math.cos(th) * Math.sin(ph), r * Math.cos(ph), r * Math.sin(th) * Math.sin(ph)];
    });

    var gelombang = bentuk(function () {
        var x = (rand() - 0.5) * 10.6;
        var z = (rand() - 0.5) * 7;
        return miring([x, 0.78 * Math.sin(x * 0.9 + z * 0.6) * Math.cos(z * 0.8), z], 0.95);
    });

    var knot = bentuk(function () {
        var t = rand() * PI2;
        var r = 1.85 + 0.9 * Math.cos(3 * t);
        function j() { return (rand() - 0.5) * 0.4; }
        return [r * Math.cos(2 * t) + j(), r * Math.sin(2 * t) + j(), 1.25 * Math.sin(3 * t) + j()];
    });

    // ---- bentuk logo dari alpha PNG (fallback: bola) ----
    var logo = bola;
    var shapes = [bola, gelombang, knot];

    function pasangLogo(img) {
        try {
            var w = 260;
            var h = Math.max(1, Math.round(w * img.naturalHeight / img.naturalWidth));
            var c = document.createElement('canvas');
            c.width = w;
            c.height = h;
            var g = c.getContext('2d');
            g.drawImage(img, 0, 0, w, h);
            var d = g.getImageData(0, 0, w, h).data;
            var px = [];
            for (var y = 0; y < h; y++) {
                for (var x = 0; x < w; x++) {
                    if (d[(y * w + x) * 4 + 3] > 128) { px.push(x, y); }
                }
            }
            if (px.length < 200) { return; }
            var M = px.length / 2;
            logo = bentuk(function () {
                var q = (rand() * M | 0) * 2;
                return [
                    ((px[q] + rand()) / w - 0.5) * W,
                    -((px[q + 1] + rand()) / w - 0.5 * h / w) * W,
                    (rand() - 0.5) * 0.85
                ];
            });
            shapes[2] = logo;      // knot -> logo supaya loop mulus
            if (reduce) { render(0); }
        } catch (e) { /* biarkan bentuk bola */ }
    }

    var img = new Image();
    img.onload = function () { pasangLogo(img); };
    img.src = "{{ asset('img/chutex_logo.png') }}";

    // ---- ukuran & posisi ----
    var lastW = 0, lastH = 0;
    function resize() {
        var w = window.innerWidth, h = window.innerHeight;
        lastW = w; lastH = h;
        var aspek = w / h;
        renderer.setSize(w, h, false);
        cam.aspect = aspek;
        cam.updateProjectionMatrix();
        if (aspek > 1.15) {
            grup.position.set(2.4, 0, 0);
            grup.scale.setScalar(1);
            material.opacity = 0.95;
        } else {
            // Layar sempit / ponsel: partikel digeser ke bawah dan dibuat lebih
            // redup supaya tidak bersaing dengan judul & kartu.
            grup.position.set(0, -1.2, -0.4);
            grup.scale.setScalar(Math.max(0.52, aspek * 0.8));
            material.opacity = aspek < 0.85 ? 0.62 : 0.78;
        }
    }
    window.addEventListener('resize', resize);
    window.addEventListener('orientationchange', resize);
    if (window.ResizeObserver) {
        // Emulation / panel resize kadang tidak memicu event window.resize,
        // jadi ukuran buffer juga diikuti lewat ResizeObserver.
        new ResizeObserver(resize).observe(document.documentElement);
    }
    resize();

    // ---- interaksi ----
    var mx = 0, my = 0, smx = 0, smy = 0;
    window.addEventListener('pointermove', function (e) {
        mx = e.clientX / window.innerWidth - 0.5;
        my = e.clientY / window.innerHeight - 0.5;
    }, { passive: true });

    // ---- render ----
    function render(now) {
        var t = now / 1000;
        var durasi = 3.0;          // detik per transisi
        var tahan = 2.4;           // detik berhenti di satu bentuk
        var siklus = shapes.length * (durasi + tahan);
        var f = (t % siklus) / (durasi + tahan);
        var idx = Math.floor(f);
        var lokal = f - idx;
        var A = shapes[idx % shapes.length];
        var B = shapes[(idx + 1) % shapes.length];
        var u = Math.min(1, Math.max(0, (lokal * (durasi + tahan) - tahan) / durasi));
        var e = u * u * (3 - 2 * u);

        for (var k = 0; k < N; k++) {
            var j = k * 3;
            var uu = Math.max(0, Math.min(1, u * 1.35 - delay[k] * 0.35));
            var ee = uu * uu * (3 - 2 * uu);
            var busur = Math.sin(uu * Math.PI) * 0.9;
            posisi[j] = A[j] + (B[j] - A[j]) * ee + jitter[j] * busur;
            posisi[j + 1] = A[j + 1] + (B[j + 1] - A[j + 1]) * ee + jitter[j + 1] * busur;
            posisi[j + 2] = A[j + 2] + (B[j + 2] - A[j + 2]) * ee + jitter[j + 2] * busur;
        }
        geo.attributes.position.needsUpdate = true;

        smx += (mx - smx) * 0.05;
        smy += (my - smy) * 0.05;
        grup.rotation.y = Math.sin(t * 0.32) * 0.28 + smx * 0.5;
        grup.rotation.x = smy * 0.3;
        bintang.rotation.y = t * 0.012;
        renderer.render(scene, cam);
    }

    if (reduce) {
        // Satu frame statis: bentuk logo/bola, tanpa animasi.
        for (var k = 0; k < N; k++) {
            posisi[k * 3] = shapes[0][k * 3];
            posisi[k * 3 + 1] = shapes[0][k * 3 + 1];
            posisi[k * 3 + 2] = shapes[0][k * 3 + 2];
        }
        geo.attributes.position.needsUpdate = true;
        renderer.render(scene, cam);
        return;
    }

    var jalan = true;
    document.addEventListener('visibilitychange', function () {
        jalan = !document.hidden;
        if (jalan) { requestAnimationFrame(loop); }
    });

    var lastCheck = 0;
    function loop(now) {
        if (!jalan) { return; }
        requestAnimationFrame(loop);
        now = now || 0;
        // Jaring pengaman: sebagian lingkungan (mis. jendela di-emulate) tidak
        // memicu event resize, jadi ukuran dicek berkala.
        if (now - lastCheck > 500) {
            lastCheck = now;
            if (window.innerWidth !== lastW || window.innerHeight !== lastH) { resize(); }
        }
        render(now);
    }
    requestAnimationFrame(loop);
})();
</script>
