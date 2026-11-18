<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#040a16">
    <script>
        /* Tema terang/gelap HRIS v2.
           Nilai disimpan di localStorage browser (permintaan 17 Nov 2026) dan
           dipasang sebagai atribut di <html> SEBELUM CSS dimuat, supaya tidak
           ada kedipan warna saat halaman dibuka. Bawaan: TERANG. */
        (function () {
            var tema = 'light';
            try {
                var tersimpan = window.localStorage.getItem('chutex_v2_theme');
                if (tersimpan === 'dark' || tersimpan === 'light') {
                    tema = tersimpan;
                }
            } catch (e) { /* localStorage diblokir: pakai bawaan */ }
            document.documentElement.setAttribute('data-v2-theme', tema);
        })();
    </script>
    <title>@yield('judul', 'Portal HRIS v2') — Chutex International Indonesia</title>

    <link href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/sb-admin-2.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/sora/sora.css') }}" rel="stylesheet">

    <style>
        /* ================================================================
           Portal HRIS v2 — tema "3D particle" (dark navy).

           Struktur kelas lama (v2-card, v2-menu-icon, v2-badge-*, v2-btn*,
           v2-hero, v2-orb, v2-step, v2-guide, #webcam, ...) TETAP DIPAKAI
           supaya seluruh view dan skrip kamera v2 berjalan tanpa perubahan
           logic. Yang berubah hanya palet, permukaan, dan tipografi.
           ================================================================ */
        :root {
            --v2-bg: #040a16;
            --v2-bg-2: #06101f;
            --v2-fg: #eef3fb;

            /* Nama variabel lama dipertahankan (dipakai view) */
            --v2-primary: #3b8cff;
            --v2-primary-2: #5aa4ff;
            --v2-primary-3: #1f5fd0;
            --v2-info: #22d3ee;
            --v2-info-2: #12b6cf;
            --v2-success: #34d399;
            --v2-success-2: #10b981;
            --v2-warning: #fbbf24;
            --v2-danger: #f87171;
            --v2-secondary: #9fb0c8;

            --v2-blue-900: #3b8cff;
            --v2-blue-700: #3b8cff;
            --v2-blue-500: #9fb0c8;
            --v2-cyan: #22d3ee;
            --v2-sky: #22d3ee;
            --v2-ice: rgba(255, 255, 255, .10);
            --v2-navy: #3b8cff;
            --v2-accent: #3b8cff;
            --v2-violet: #a78bfa;

            --v2-surface: rgba(255, 255, 255, .045);
            --v2-surface-2: rgba(255, 255, 255, .07);
            --v2-text: #c7d3e6;
            --v2-heading: #eef3fb;
            --v2-muted: #8fa1bd;
            --v2-line: rgba(255, 255, 255, .09);
            --v2-line-strong: rgba(255, 255, 255, .16);
            --v2-shadow: 0 18px 40px rgba(0, 0, 0, .45);
            --v2-shadow-lg: 0 28px 70px rgba(0, 0, 0, .55);
            --v2-radius: 16px;
            --v2-ring: 0 0 0 3px rgba(59, 140, 255, .28);
        }

        * { -webkit-tap-highlight-color: transparent; }

        html { scroll-behavior: smooth; }

        body.v2 {
            background:
                radial-gradient(1200px 700px at 78% -10%, rgba(34, 211, 238, .10), transparent 60%),
                radial-gradient(900px 600px at 8% 8%, rgba(59, 140, 255, .12), transparent 62%),
                linear-gradient(180deg, #040a16 0%, #050d1c 55%, #040a16 100%);
            background-color: var(--v2-bg);
            color: var(--v2-text);
            font-family: 'Sora', system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-weight: 400;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        body.v2 h1, body.v2 h2, body.v2 h3, body.v2 h4, body.v2 h5, body.v2 h6 {
            color: var(--v2-heading);
            font-weight: 600;
            letter-spacing: -.01em;
        }

        /* ---------------- Latar 3D particle ---------------- */
        .v2-scene { position: fixed; inset: 0; z-index: 0; pointer-events: none; }
        #v2-canvas {
            position: fixed; inset: 0; width: 100%; height: 100%; display: block;
            opacity: 1;
        }

        .v2-vignette {
            position: fixed; inset: 0; pointer-events: none;
            background:
                radial-gradient(ellipse at 68% 42%, transparent 30%, rgba(4, 10, 22, .78) 100%),
                linear-gradient(180deg, rgba(4, 10, 22, .55) 0%, rgba(4, 10, 22, 0) 26%, rgba(4, 10, 22, .55) 100%);
        }
        .v2-grid-glow {
            position: fixed; left: 0; right: 0; bottom: 0; height: 42vh; pointer-events: none;
            background: linear-gradient(180deg, transparent, rgba(34, 211, 238, .05));
            mask-image: linear-gradient(180deg, transparent, #000 70%);
            -webkit-mask-image: linear-gradient(180deg, transparent, #000 70%);
        }

        /* ---------------- Topbar ---------------- */
        .v2-topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1030;
            padding: calc(env(safe-area-inset-top, 0px) + 12px) 0;
            background: rgba(4, 10, 22, .62);
            border-bottom: 1px solid rgba(255, 255, 255, .07);
            -webkit-backdrop-filter: blur(16px) saturate(140%);
            backdrop-filter: blur(16px) saturate(140%);
        }
        .v2-topbar-inner {
            max-width: 1160px; margin: 0 auto; padding: 0 20px;
            display: flex; align-items: center; justify-content: space-between; gap: 14px;
        }
        .v2-brand { display: inline-flex; align-items: center; gap: 11px; text-decoration: none !important; min-width: 0; }
        /* Logo memakai SVG resmi sebagai mask: bentuknya tetap, warnanya
           diatur CSS — biru di tema terang, putih di tema gelap. */
        .v2-logo-mask {
            display: block;
            flex: 0 0 auto;
            background-image: linear-gradient(135deg, #1d3f86, #3a6fa8);
            -webkit-mask: url("/img/logo-chutex.svg") no-repeat center / contain;
            mask: url("/img/logo-chutex.svg") no-repeat center / contain;
        }
        html[data-v2-theme="dark"] .v2-logo-mask {
            background-image: linear-gradient(135deg, #ffffff, #eaf1ff);
        }
        .v2-brand-logo {
            height: 26px;
            width: 37px; /* rasio asli 378 x 264 */
        }
        .v2-brand-text { display: flex; flex-direction: column; line-height: 1.12; min-width: 0; }
        .v2-brand-title { color: #fff; font-size: .95rem; font-weight: 600; letter-spacing: .01em; white-space: nowrap; }
        .v2-brand-sub {
            color: var(--v2-muted); font-size: .6rem; letter-spacing: .24em;
            text-transform: uppercase; white-space: nowrap;
        }
        .v2-nav { display: flex; align-items: center; gap: 1.35rem; }
        .v2-nav a {
            color: var(--v2-muted) !important; font-size: .7rem; letter-spacing: .18em;
            text-transform: uppercase; text-decoration: none !important; transition: color .25s ease;
            white-space: nowrap;
        }
        .v2-nav a:hover, .v2-nav a.aktif { color: #fff !important; }
        .v2-nav a.aktif { position: relative; }
        .v2-nav a.aktif::after {
            content: ""; position: absolute; left: 0; right: 0; bottom: -7px; height: 1px;
            background: linear-gradient(90deg, var(--v2-primary), var(--v2-info));
            box-shadow: 0 0 10px rgba(59, 140, 255, .8);
        }
        .v2-chip {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 6px 12px; border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .04);
            color: #cfe0f5; font-size: .62rem; letter-spacing: .16em; text-transform: uppercase;
        }
        .v2-chip i { color: var(--v2-info); font-size: .55rem; }

        /* ---------------- Kerangka halaman ---------------- */
        .v2-wrap {
            position: relative; z-index: 2;
            width: 100%; max-width: 1160px; margin: 0 auto;
            padding: calc(env(safe-area-inset-top, 0px) + 104px) 20px calc(48px + env(safe-area-inset-bottom));
            flex: 1 0 auto;
        }

        @keyframes v2-rise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        .v2-wrap > * { animation: v2-rise .75s cubic-bezier(.2, .7, .2, 1) both; }
        .v2-wrap > *:nth-child(2) { animation-delay: .06s; }
        .v2-wrap > *:nth-child(3) { animation-delay: .12s; }
        .v2-wrap > *:nth-child(4) { animation-delay: .18s; }
        .v2-wrap > *:nth-child(5) { animation-delay: .24s; }

        .v2-hero { position: relative; text-align: center; padding: 18px 0 38px; }
        .v2-hero h1, .v2-hero h2, .v2-hero h3 {
            font-weight: 300; color: #fff; letter-spacing: -.025em; line-height: 1.06;
        }
        .v2-hero h1 { font-size: clamp(2.05rem, 5vw, 3.5rem); margin-bottom: .55rem; }
        .v2-hero h2 { font-size: clamp(1.75rem, 4.2vw, 2.8rem); margin-bottom: .5rem; }
        .v2-hero p { color: var(--v2-muted); margin-bottom: 0; }

        .v2-kicker {
            display: inline-flex; align-items: center; gap: 9px;
            font-size: .68rem; letter-spacing: .32em; text-transform: uppercase;
            color: var(--v2-info); margin-bottom: 15px;
        }
        .v2-kicker::before {
            content: ""; width: 26px; height: 1px;
            background: linear-gradient(90deg, transparent, var(--v2-info));
        }

        /* Logo hero: memakai mask SVG resmi (warna mengikuti tema).
           .v2-hero-head memaksa susunan satu kolom supaya logo selalu DI ATAS
           tulisan "Human Resource Information System", bukan di sampingnya. */
        .v2-hero-head {
            display: flex; flex-direction: column; align-items: center;
            gap: 14px; margin-bottom: 15px;
        }
        .v2-hero-head .v2-kicker { margin-bottom: 0; }
        .v2-logo-hero {
            height: 104px; width: 149px; margin: 0 auto 18px;
        }
        .v2-hero-logo { display: flex; justify-content: center; }
        .v2-hero-logo img {
            height: 104px; width: auto; display: block;
            filter: brightness(0) invert(1); opacity: .96;
        }
        @media (max-width: 575.98px) {
            .v2-logo-hero { height: 76px; width: 109px; }
            .v2-hero-logo img { height: 76px; }
        }

        /* Typing line */
        .v2-type-text {
            font-size: 1.02rem; font-weight: 300; color: var(--v2-muted);
            letter-spacing: .015em; min-height: 1.8rem; margin-top: 8px;
        }
        .v2-type-caret {
            display: inline-block; width: 2px; height: 1.02em; vertical-align: -.14em;
            margin-left: 5px; background: var(--v2-info);
            box-shadow: 0 0 10px rgba(34, 211, 238, .9);
            animation: v2-caret-blink 1s steps(1) infinite;
        }
        @keyframes v2-caret-blink { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }

        /* Orb (masih dipakai view lama) */
        .v2-orb {
            width: 76px; height: 76px; margin: 0 auto 14px; position: relative; border-radius: 50%;
            background: radial-gradient(circle at 34% 28%, #ffffff 0%, #9ec6ff 36%, var(--v2-primary) 100%);
            border: 1px solid rgba(59, 140, 255, .5);
            box-shadow: 0 0 40px rgba(59, 140, 255, .45);
        }
        .v2-orb::before, .v2-orb::after {
            content: ""; position: absolute; inset: -8px; border-radius: 50%;
            border: 1px solid rgba(59, 140, 255, .3);
        }
        .v2-orb::after { inset: -16px; border-color: rgba(34, 211, 238, .32); }

        /* ---------------- Kartu kaca ---------------- */
        .v2-card {
            position: relative; overflow: hidden;
            background: linear-gradient(160deg, rgba(255, 255, 255, .07) 0%, rgba(255, 255, 255, .028) 55%, rgba(255, 255, 255, .02) 100%);
            border: 1px solid rgba(255, 255, 255, .10);
            border-radius: var(--v2-radius);
            box-shadow: var(--v2-shadow);
            color: var(--v2-text);
            -webkit-backdrop-filter: blur(14px) saturate(140%);
            backdrop-filter: blur(14px) saturate(140%);
        }
        .v2-card::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, rgba(59, 140, 255, 0) 0%, var(--v2-primary) 30%, var(--v2-info) 70%, rgba(34, 211, 238, 0) 100%);
            opacity: .75;
        }
        .v2-card-hover { transition: transform .3s cubic-bezier(.2, .7, .2, 1), box-shadow .3s ease, border-color .3s ease; }
        .v2-card:hover.v2-card-hover {
            transform: translateY(-4px);
            border-color: rgba(59, 140, 255, .42);
            box-shadow: 0 26px 60px rgba(0, 0, 0, .55), 0 0 0 1px rgba(59, 140, 255, .18);
        }
        .v2-card-title { font-weight: 600; color: var(--v2-heading); }

        /* ---------------- Badge & chip ---------------- */
        .v2-badge {
            display: inline-block; padding: 4px 11px; border-radius: 999px;
            font-size: .68rem; font-weight: 600; letter-spacing: .04em;
            border: 1px solid transparent; margin: 0 4px 4px 0;
        }
        .v2-badge-navy { background: rgba(59, 140, 255, .14); color: #9cc6ff; border-color: rgba(59, 140, 255, .38); }
        .v2-badge-cyan { background: rgba(34, 211, 238, .12); color: #7fe6f6; border-color: rgba(34, 211, 238, .36); }
        .v2-badge-green { background: rgba(52, 211, 153, .12); color: #7ff0c4; border-color: rgba(52, 211, 153, .34); }
        .v2-badge-grey { background: rgba(255, 255, 255, .06); color: #b9c8dd; border-color: rgba(255, 255, 255, .14); }
        .v2-badge-violet { background: rgba(167, 139, 250, .14); color: #cbb8ff; border-color: rgba(167, 139, 250, .34); }

        /* ---------------- Tombol ---------------- */
        .v2-btn {
            border-radius: 999px; font-weight: 600; letter-spacing: .04em;
            padding: .72rem 1.5rem; transition: .28s ease;
        }
        .v2-btn-primary, .v2-btn.v2-btn-primary {
            background: linear-gradient(120deg, var(--v2-primary) 0%, #2f7ae6 55%, #1f5fd0 100%);
            border: 1px solid rgba(59, 140, 255, .8);
            color: #fff;
            box-shadow: 0 10px 28px rgba(59, 140, 255, .28);
        }
        .v2-btn-primary:hover, .v2-btn-primary:focus, .v2-btn.v2-btn-primary:hover, .v2-btn.v2-btn-primary:focus {
            background: linear-gradient(120deg, #5aa4ff 0%, var(--v2-primary) 100%);
            border-color: #5aa4ff; color: #fff;
            box-shadow: 0 14px 34px rgba(59, 140, 255, .45);
            transform: translateY(-1px);
            text-decoration: none;
        }
        .v2-btn-ghost {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .2);
            color: #d7e3f4;
        }
        .v2-btn-ghost:hover, .v2-btn-ghost:focus {
            background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .34);
            color: #fff;
        }
        .v2-btn-outline-primary { background: transparent; border: 1px solid var(--v2-primary); color: #9cc6ff; }
        .v2-btn-outline-primary:hover { background: rgba(59, 140, 255, .16); color: #fff; }

        /* ---------------- Menu grid ---------------- */
        .v2-menu-card { display: block; text-decoration: none; color: inherit; height: 100%; }
        .v2-menu-card:hover { color: inherit; text-decoration: none; }
        .v2-menu-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem; color: #ffffff; font-weight: 600;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            transition: transform .3s ease, filter .3s ease;
        }
        .v2-menu-card:hover .v2-menu-icon { transform: translateY(-2px); filter: brightness(1.2); }

        /* ---------------- Teks data ---------------- */
        .v2-muted { color: var(--v2-muted) !important; }
        .v2-footnote { font-size: .8rem; color: var(--v2-muted); }
        .v2-divider { height: 1px; background: var(--v2-line); margin: 22px 0; }
        .v2-label { font-size: .66rem; text-transform: uppercase; letter-spacing: .14em; color: #7dd3fc; font-weight: 600; }
        .v2-value { color: #e6edf9; font-weight: 400; word-break: break-word; }
        .v2-kv-label { color: #7dd3fc; font-weight: 600; }
        .v2-kv-value { color: #e6edf9; font-weight: 400; }

        /* ---------------- Kamera / pemindai wajah ---------------- */
        .v2-camera-shell {
            background: radial-gradient(circle at 50% 35%, #16233a, #050b16 76%);
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 18px; overflow: hidden; position: relative;
            box-shadow: inset 0 0 60px rgba(0, 0, 0, .7), 0 18px 40px rgba(0, 0, 0, .45);
        }
        .v2-camera-shell::after {
            content: ""; position: absolute; left: 0; right: 0; height: 26%;
            background: linear-gradient(180deg, transparent, rgba(34, 211, 238, .22), transparent);
            animation: v2-scan 3.8s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes v2-scan {
            0% { top: -26%; opacity: 0; }
            18% { opacity: 1; }
            82% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }
        #webcam { width: 100%; display: block; }
        .v2-guide {
            position: absolute; top: 50%; left: 50%; width: 62%; max-width: 260px;
            aspect-ratio: 1/1; transform: translate(-50%, -50%);
            border: 2px dashed rgba(255, 255, 255, .75); border-radius: 50%;
            box-shadow: 0 0 30px rgba(34, 211, 238, .35);
            pointer-events: none;
        }
        .v2-guide.liveness-ok { border-color: var(--v2-success); border-style: solid; box-shadow: 0 0 34px rgba(52, 211, 153, .55); }
        .v2-overlay-text {
            position: absolute; left: 0; right: 0; bottom: 0; padding: 10px 12px;
            background: rgba(4, 10, 22, .82); color: #fff; font-size: .82rem;
            border-top: 1px solid rgba(255, 255, 255, .1);
        }
        .v2-step { display: none; }
        .v2-step.aktif { display: block; }
        .v2-meter { height: 6px; border-radius: 999px; background: rgba(255, 255, 255, .1); overflow: hidden; }
        .v2-meter > span {
            display: block; height: 100%; border-radius: 999px;
            background: linear-gradient(90deg, var(--v2-primary), var(--v2-info));
            transition: width .25s ease;
        }

        /* ---------------- Bootstrap disesuaikan ke tema gelap ---------------- */
        .v2 .card {
            background: rgba(255, 255, 255, .045);
            border: 1px solid var(--v2-line);
            border-radius: var(--v2-radius); color: var(--v2-text); box-shadow: var(--v2-shadow);
            -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px);
        }
        .v2 .card-header {
            background: rgba(255, 255, 255, .04);
            border-bottom: 1px solid var(--v2-line);
            color: var(--v2-heading); font-weight: 600;
        }
        .v2 .card-footer { background: rgba(255, 255, 255, .03); border-top: 1px solid var(--v2-line); }
        .v2 a:not(.btn):not(.v2-btn):not(.v2-menu-card):not(.v2-brand):not(.v2-nav a):not(.nav-link) { color: #7cc4ff; }
        .v2 a:not(.btn):not(.v2-btn):not(.v2-menu-card):not(.v2-brand):not(.v2-nav a):not(.nav-link):hover { color: #a8d6ff; }
        /* Tab pil: warna diatur khusus di blok .nav-pills, bukan oleh aturan
           tautan umum (yang spesifisitasnya lebih tinggi dari .nav-link.active). */
        .v2 .nav-pills .nav-item a.nav-link.active,
        .v2 .nav-pills .nav-item a.nav-link.active:hover,
        .v2 .nav-pills .nav-item a.nav-link.active i { color: #ffffff !important; }
        .v2 code {
            color: #7dd3fc; background: rgba(255, 255, 255, .06);
            padding: 2px 6px; border-radius: 6px; border: 1px solid var(--v2-line);
        }
        .v2 hr { border-top-color: var(--v2-line); }

        .v2 .table { color: var(--v2-text); margin-bottom: 0; }
        .v2 .table thead th {
            border-top: 0; border-bottom: 1px solid rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .05); color: var(--v2-muted);
            font-size: .66rem; text-transform: uppercase; letter-spacing: .14em; font-weight: 600;
            white-space: nowrap;
        }
        .v2 .table td, .v2 .table th { border-color: var(--v2-line); vertical-align: middle; }
        .v2 .table td { color: #dbe6f5; }
        .v2 .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(255, 255, 255, .025); }
        .v2 .table-hover tbody tr:hover { background-color: rgba(59, 140, 255, .08); color: #fff; }
        .v2 .table-bordered, .v2 .table-bordered td, .v2 .table-bordered th { border-color: var(--v2-line) !important; }
        .v2-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }

        .v2 .form-control, .v2 .custom-select, .v2 .form-control-plaintext {
            background-color: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .14);
            color: #eef3fb; border-radius: 12px;
            height: auto; padding: .7rem .95rem;
        }
        .v2 .form-control::placeholder { color: rgba(238, 243, 251, .34); }
        .v2 .form-control:focus, .v2 .custom-select:focus {
            background-color: rgba(255, 255, 255, .075); color: #fff;
            border-color: rgba(59, 140, 255, .75);
            box-shadow: var(--v2-ring);
        }
        .v2 .form-control:disabled, .v2 .form-control[readonly] {
            background-color: rgba(255, 255, 255, .03); color: var(--v2-muted);
        }
        .v2 .custom-select {
            background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'%3E%3Cpath fill='%239fb0c8' d='M2 0L0 2h4zm0 5L0 3h4z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right .75rem center; background-size: 8px 10px;
        }
        .v2 .custom-select option, .v2 select option { background: #081426; color: #eef3fb; }
        .v2 .input-group-text {
            background-color: rgba(255, 255, 255, .06);
            border-color: rgba(255, 255, 255, .14); color: var(--v2-muted);
        }
        .v2 label, .v2 .col-form-label { color: #cfe0f5; font-weight: 600; }
        .v2 .form-text, .v2 small { color: var(--v2-muted); }
        .v2 .form-check-input { background-color: rgba(255, 255, 255, .08); border-color: rgba(255, 255, 255, .28); }
        .v2 .custom-control-label::before { background-color: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .25); }
        .v2 .custom-control-input:checked ~ .custom-control-label::before {
            background-color: var(--v2-primary); border-color: var(--v2-primary);
        }

        /* Alert versi gelap */
        .v2 .alert { border-radius: 14px; border-width: 1px; border-left-width: 3px; }
        .v2 .alert-success { background: rgba(52, 211, 153, .10); border-color: rgba(52, 211, 153, .35); color: #b6f3d8; }
        .v2 .alert-danger { background: rgba(248, 113, 113, .10); border-color: rgba(248, 113, 113, .35); color: #ffc9c9; }
        .v2 .alert-warning { background: rgba(251, 191, 36, .10); border-color: rgba(251, 191, 36, .35); color: #ffe6a8; }
        .v2 .alert-info { background: rgba(34, 211, 238, .10); border-color: rgba(34, 211, 238, .35); color: #b5f1fb; }
        .v2 .alert a { color: #fff; text-decoration: underline; }

        .v2 .btn-secondary { background: rgba(255, 255, 255, .12); border-color: rgba(255, 255, 255, .18); color: #fff; }
        .v2 .btn-secondary:hover { background: rgba(255, 255, 255, .2); border-color: rgba(255, 255, 255, .26); color: #fff; }
        .v2 .btn-primary { background: var(--v2-primary); border-color: var(--v2-primary); color: #fff; }
        .v2 .btn-primary:hover, .v2 .btn-primary:focus { background: var(--v2-primary-2); border-color: var(--v2-primary-2); color: #fff; }
        .v2 .btn-success { background: var(--v2-success-2); border-color: var(--v2-success-2); color: #04120c; }
        .v2 .btn-success:hover { background: var(--v2-success); border-color: var(--v2-success); color: #04120c; }
        .v2 .btn-danger { background: var(--v2-danger); border-color: var(--v2-danger); color: #1a0606; }
        .v2 .btn-warning { background: var(--v2-warning); border-color: var(--v2-warning); color: #1a1300; }
        .v2 .btn-outline-primary { color: #9cc6ff; border-color: rgba(59, 140, 255, .6); }
        .v2 .btn-outline-primary:hover { background: var(--v2-primary); border-color: var(--v2-primary); color: #fff; }
        .v2 .btn-outline-secondary { color: #cfe0f5; border-color: rgba(255, 255, 255, .22); }
        .v2 .btn-outline-secondary:hover { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .3); color: #fff; }
        .v2 .btn-link { color: #7cc4ff; }
        .v2 .btn-link:hover { color: #a8d6ff; }

        /* Tab / pill (Password vs Face ID) */
        .v2 .nav-pills {
            background: rgba(255, 255, 255, .04);
            border: 1px solid var(--v2-line);
            border-radius: 999px; padding: 5px; gap: 4px;
        }
        .v2 .nav-pills .nav-item a.nav-link {
            color: var(--v2-muted); background: transparent;
            border: 1px solid transparent; border-radius: 999px;
            margin: 0; padding: 9px 14px; font-size: .84rem;
        }
        .v2 .nav-pills .nav-item a.nav-link:hover { background: rgba(255, 255, 255, .06); color: #fff; }
        .v2 .nav-pills .nav-item a.nav-link.active {
            background: linear-gradient(120deg, var(--v2-primary), #1f5fd0);
            color: #fff; border-color: rgba(59, 140, 255, .7);
            box-shadow: 0 8px 20px rgba(59, 140, 255, .32);
        }
        .v2 .nav-pills .nav-item a.nav-link.active i { color: #fff; }
        .v2 .nav-pills .nav-item a.nav-link.disabled { color: rgba(238, 243, 251, .3); background: transparent; }

        .v2 .badge-primary { background-color: rgba(59, 140, 255, .2); color: #b7d5ff; }
        .v2 .badge-info { background-color: rgba(34, 211, 238, .18); color: #b5f1fb; }
        .v2 .badge-success { background-color: rgba(52, 211, 153, .18); color: #b6f3d8; }
        .v2 .badge-warning { background-color: rgba(251, 191, 36, .18); color: #ffe6a8; }
        .v2 .badge-danger { background-color: rgba(248, 113, 113, .18); color: #ffc9c9; }
        .v2 .badge-secondary { background-color: rgba(255, 255, 255, .1); color: #cfe0f5; }
        .v2 .badge-light { background-color: rgba(255, 255, 255, .1); color: #eef3fb; }

        .v2 .dropdown-menu {
            background-color: #081426; border: 1px solid rgba(255, 255, 255, .12);
            box-shadow: var(--v2-shadow-lg); border-radius: 12px;
        }
        .v2 .dropdown-item { color: var(--v2-muted); }
        .v2 .dropdown-item:hover, .v2 .dropdown-item:focus { background-color: rgba(59, 140, 255, .12); color: #fff; }
        .v2 .dropdown-divider { border-top-color: var(--v2-line); }

        .v2 .modal-content {
            background: #081426; border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 18px; color: var(--v2-text); box-shadow: var(--v2-shadow-lg);
        }
        .v2 .modal-header, .v2 .modal-footer { border-color: var(--v2-line); }
        .v2 .modal-title { color: var(--v2-heading); }
        .v2 .list-group-item { background-color: transparent; border-color: var(--v2-line); color: var(--v2-text); }
        .v2 .page-link { background-color: rgba(255, 255, 255, .04); border-color: var(--v2-line); color: #9cc6ff; }
        .v2 .page-item.active .page-link { background-color: var(--v2-primary); border-color: var(--v2-primary); color: #fff; }

        /* ---------------- Footer ---------------- */
        .v2-footer {
            position: relative; z-index: 2;
            text-align: center; padding: 18px 16px calc(22px + env(safe-area-inset-bottom));
            background: rgba(4, 10, 22, .72);
            border-top: 1px solid rgba(255, 255, 255, .07);
            color: var(--v2-muted); font-size: .74rem; letter-spacing: .04em;
            -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);
            margin-top: auto;
        }
        .v2-footer strong { color: #eef3fb; font-weight: 600; }

        /* ---------------- Scrollbar ---------------- */
        .v2 ::-webkit-scrollbar { width: 10px; height: 10px; }
        .v2 ::-webkit-scrollbar-track { background: rgba(255, 255, 255, .03); }
        .v2 ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, .16); border-radius: 999px; }
        .v2 ::-webkit-scrollbar-thumb:hover { background: rgba(59, 140, 255, .5); }

        /* ---------------- Mobile ---------------- */
        @media (max-width: 991.98px) {
            .v2-nav { display: none; }
        }
        @media (max-width: 767.98px) {
            .v2-topbar { padding: calc(env(safe-area-inset-top, 0px) + 9px) 0; }
            .v2-topbar-inner { padding: 0 14px; }
            .v2-brand-logo { height: 22px; }
            .v2-brand-title { font-size: .86rem; }
            .v2-brand-sub { display: none; }
            .v2-wrap { padding: calc(env(safe-area-inset-top, 0px) + 84px) 14px 40px; }
            .v2-hero { padding: 8px 0 26px; }
            .v2-hero h1 { font-size: clamp(1.75rem, 8.4vw, 2.3rem); }
            .v2-hero h2 { font-size: clamp(1.55rem, 7.4vw, 2rem); }
            .v2-type-text { font-size: .92rem; }
            .v2-vignette {
                background:
                    linear-gradient(180deg, rgba(4, 10, 22, .94) 0%, rgba(4, 10, 22, .52) 34%, rgba(4, 10, 22, .55) 100%);
            }
            .v2-orb { width: 60px; height: 60px; }
            .v2-menu-icon { width: 46px; height: 46px; font-size: 1rem; }
            .v2-card {
                background: linear-gradient(160deg, rgba(14, 25, 44, .92) 0%, rgba(9, 17, 32, .88) 100%);
            }
            .v2 .table thead th, .v2 .table td { font-size: .8rem; white-space: nowrap; }
            .v2-btn, .v2 .btn { min-height: 42px; }
            .v2-input-lg { font-size: 1.02rem; }
            .v2-footer { font-size: .68rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .v2-camera-shell::after, .v2-type-caret { animation: none; }
            .v2-wrap > * { animation: none; }
            #v2-canvas { animation: none; opacity: 1; }
        }

        /* ================================================================
           TEMA TERANG (bawaan) — HTML[data-v2-theme="light"]
           ----------------------------------------------------------------
           Tema asli v2 dirancang gelap (kaca transparan di atas navy), jadi
           mode terang TIDAK cukup membalik warna latar: permukaan kaca harus
           jadi putih solid, teks aksen yang tadinya terang harus jadi biru
           tua, dan tabel/form yang memakai rgba putih harus punya border.
           Semua aturan di bawah hanya berlaku saat tema terang aktif.
           ================================================================ */
        html[data-v2-theme="light"] body.v2 {
            background:
                radial-gradient(1100px 620px at 84% -12%, rgba(34, 211, 238, .16), transparent 62%),
                radial-gradient(820px 520px at 4% 4%, rgba(59, 140, 255, .14), transparent 64%),
                linear-gradient(180deg, #f7fafe 0%, #eef3fb 58%, #f8fbfe 100%);
            background-color: #f4f8fd;
            color: #334155;
        }
        html[data-v2-theme="light"] body.v2 h1,
        html[data-v2-theme="light"] body.v2 h2,
        html[data-v2-theme="light"] body.v2 h3,
        html[data-v2-theme="light"] body.v2 h4,
        html[data-v2-theme="light"] body.v2 h5,
        html[data-v2-theme="light"] body.v2 h6 { color: #0f172a; }

        /* Latar 3D dirancang untuk kanvas gelap; di mode terang partikelnya
           tidak terlihat (aditif di atas putih) dan hanya menambah beban. */
        html[data-v2-theme="light"] .v2-scene,
        html[data-v2-theme="light"] .v2-vignette { display: none; }
        html[data-v2-theme="light"] .v2-grid-glow {
            background: linear-gradient(180deg, transparent, rgba(59, 140, 255, .07));
        }

        /* Topbar */
        html[data-v2-theme="light"] .v2-topbar {
            background: rgba(255, 255, 255, .88);
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .05);
        }
        html[data-v2-theme="light"] .v2-brand-title { color: #0f172a; }
        html[data-v2-theme="light"] .v2-brand-sub { color: #64748b; }
        html[data-v2-theme="light"] .v2-nav a { color: #475569 !important; }
        html[data-v2-theme="light"] .v2-nav a:hover,
        html[data-v2-theme="light"] .v2-nav a.aktif { color: #1d4ed8 !important; }
        html[data-v2-theme="light"] .v2-chip {
            background: #e8f1ff; border: 1px solid #cfe0fb; color: #1d4ed8;
        }

        /* Hero & tipografi */
        html[data-v2-theme="light"] .v2-hero h1,
        html[data-v2-theme="light"] .v2-hero h2,
        html[data-v2-theme="light"] .v2-hero h3 { color: #0f172a; }
        html[data-v2-theme="light"] .v2-hero p { color: #475569; }
        html[data-v2-theme="light"] .v2-kicker { color: #1d4ed8; }
        html[data-v2-theme="light"] .v2-type-text { color: #334155; }

        /* Kartu */
        html[data-v2-theme="light"] .v2-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .07);
            color: #334155;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }
        html[data-v2-theme="light"] .v2-card::before { opacity: .9; }
        html[data-v2-theme="light"] .v2-card:hover.v2-card-hover {
            border-color: #bfd8fb;
            box-shadow: 0 20px 44px rgba(15, 23, 42, .13), 0 0 0 1px rgba(59, 140, 255, .12);
        }
        html[data-v2-theme="light"] .v2-card-title { color: #0f172a; }
        html[data-v2-theme="light"] .v2-menu-card,
        html[data-v2-theme="light"] .v2-menu-card:hover { color: #334155; }
        html[data-v2-theme="light"] .v2-menu-icon {
            background: #e8f0fd; border-color: #cfdff8; color: #1d3f86;
        }

        /* Teks data */
        html[data-v2-theme="light"] .v2-muted { color: #5b6b7f !important; }
        html[data-v2-theme="light"] .v2-footnote { color: #5b6b7f; }
        html[data-v2-theme="light"] .v2-divider { background: #e2e8f0; }
        html[data-v2-theme="light"] .v2-label,
        html[data-v2-theme="light"] .v2-kv-label { color: #1d4ed8; }
        html[data-v2-theme="light"] .v2-value,
        html[data-v2-theme="light"] .v2-kv-value { color: #0f172a; }

        /* Badge */
        html[data-v2-theme="light"] .v2-badge-navy { background: #e8f1ff; color: #1d4ed8; border-color: #cfe0fb; }
        html[data-v2-theme="light"] .v2-badge-cyan { background: #e6fbff; color: #0e7490; border-color: #bff0fa; }
        html[data-v2-theme="light"] .v2-badge-green { background: #e9fbf2; color: #047857; border-color: #bdf0d8; }
        html[data-v2-theme="light"] .v2-badge-grey { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        html[data-v2-theme="light"] .v2-badge-violet { background: #f3eeff; color: #6d28d9; border-color: #e2d8ff; }

        /* Badge bawaan Bootstrap (badge-success, badge-secondary, dst). Aturan
           dasarnya di atas memakai teks mint/pucat untuk latar gelap; di kartu
           putih nilai itu nyaris tak terlihat (mis. status kontrak). Warna di
           bawah menjaga rasio kontras >= 4.5 pada latar kartu terang. */
        html[data-v2-theme="light"] .v2 .badge-primary { background-color: #e8f1ff; color: #1d4ed8; }
        html[data-v2-theme="light"] .v2 .badge-info { background-color: #e6fbff; color: #0e7490; }
        html[data-v2-theme="light"] .v2 .badge-success { background-color: #d1fae5; color: #065f46; }
        html[data-v2-theme="light"] .v2 .badge-warning { background-color: #fffbeb; color: #92400e; }
        html[data-v2-theme="light"] .v2 .badge-danger { background-color: #fef2f2; color: #991b1b; }
        html[data-v2-theme="light"] .v2 .badge-secondary { background-color: #eef2f7; color: #3f4d63; }
        html[data-v2-theme="light"] .v2 .badge-light { background-color: #f1f5f9; color: #334155; }

        /* Tombol selain primary (primary sudah kontras di dua tema) */
        html[data-v2-theme="light"] .v2-btn-ghost {
            background: #ffffff; border-color: #cbd5e1; color: #334155;
        }
        html[data-v2-theme="light"] .v2-btn-ghost:hover,
        html[data-v2-theme="light"] .v2-btn-ghost:focus {
            background: #f1f5f9; border-color: #94a3b8; color: #0f172a;
        }
        html[data-v2-theme="light"] .v2-btn-outline-primary { color: #1d4ed8; }
        html[data-v2-theme="light"] .v2-btn-outline-primary:hover { background: rgba(59, 140, 255, .1); color: #0b3ba0; }

        /* Tabel */
        html[data-v2-theme="light"] .v2 .table { color: #334155; background: transparent; }
        html[data-v2-theme="light"] .v2 .table thead th {
            background: #f1f5f9; color: #0f172a; border-color: #e2e8f0;
        }
        html[data-v2-theme="light"] .v2 .table td,
        html[data-v2-theme="light"] .v2 .table th { border-color: #e2e8f0; color: #334155; }
        html[data-v2-theme="light"] .v2 .table thead th { color: #0f172a; }
        /* Tautan di dalam .v2 memakai rantai :not() yang panjang (spesifisitas
           (0,6,1)), jadi override tema terang harus menyalin rantai itu —
           kalau tidak, tautan tetap biru muda di atas latar putih. */
        html[data-v2-theme="light"] .v2 a:not(.btn):not(.v2-btn):not(.v2-menu-card):not(.v2-brand):not(.v2-nav a):not(.nav-link) { color: #1d4ed8; }
        html[data-v2-theme="light"] .v2 a:not(.btn):not(.v2-btn):not(.v2-menu-card):not(.v2-brand):not(.v2-nav a):not(.nav-link):hover { color: #0b3ba0; }

        /* Tab pil (Password / Face ID) di tema terang: tab terpilih berlatar
           biru, jadi teks + ikonnya harus putih — bukan biru tua. */
        html[data-v2-theme="light"] .v2 .nav-pills {
            background: #eff6ff; border-color: #dbeafe;
        }
        html[data-v2-theme="light"] .v2 .nav-pills .nav-item a.nav-link { color: #1d4ed8; }
        html[data-v2-theme="light"] .v2 .nav-pills .nav-item a.nav-link:hover { background: #dbeafe; color: #12326e; }
        html[data-v2-theme="light"] .v2 .nav-pills .nav-item a.nav-link.active,
        html[data-v2-theme="light"] .v2 .nav-pills .nav-item a.nav-link.active:hover,
        html[data-v2-theme="light"] .v2 .nav-pills .nav-item a.nav-link.active i { color: #ffffff !important; }
        html[data-v2-theme="light"] .v2 .nav-pills .nav-item a.nav-link.disabled { color: #94a3b8; }
        html[data-v2-theme="light"] .v2 .table-striped tbody tr:nth-of-type(odd) { background: #f8fafc; }
        html[data-v2-theme="light"] .v2 .table-hover tbody tr:hover { background: #eff6ff; }

        /* Form */
        html[data-v2-theme="light"] .v2 .form-control,
        html[data-v2-theme="light"] .v2 .custom-select,
        html[data-v2-theme="light"] .v2 .form-control-plaintext {
            background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;
        }
        html[data-v2-theme="light"] .v2 .form-control::placeholder { color: #94a3b8; }
        html[data-v2-theme="light"] .v2 .form-control:disabled,
        html[data-v2-theme="light"] .v2 .form-control[readonly] { background: #f1f5f9; color: #64748b; }
        html[data-v2-theme="light"] .v2 .input-group-text {
            background: #f1f5f9; border-color: #cbd5e1; color: #475569;
        }
        html[data-v2-theme="light"] .v2 label,
        html[data-v2-theme="light"] .v2 .col-form-label { color: #334155; }

        /* Alert & tombol bawaan Bootstrap */
        html[data-v2-theme="light"] .v2 .alert {
            background: #ffffff; border-color: #e2e8f0; color: #334155;
        }
        html[data-v2-theme="light"] .v2 .alert-success { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        html[data-v2-theme="light"] .v2 .alert-danger { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        html[data-v2-theme="light"] .v2 .alert-warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        html[data-v2-theme="light"] .v2 .alert-info { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
        html[data-v2-theme="light"] .v2 .alert a { color: #1d4ed8; }
        html[data-v2-theme="light"] .v2 .btn-secondary { background: #f1f5f9; border-color: #cbd5e1; color: #334155; }
        html[data-v2-theme="light"] .v2 .btn-outline-secondary { color: #475569; border-color: #cbd5e1; }
        html[data-v2-theme="light"] .v2 .btn-link { color: #1d4ed8; }

        /* Dropdown, modal, list, pagination */
        html[data-v2-theme="light"] .v2 .dropdown-menu {
            background-color: #ffffff; border-color: #e2e8f0;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .14);
        }
        html[data-v2-theme="light"] .v2 .dropdown-item { color: #475569; }
        html[data-v2-theme="light"] .v2 .dropdown-item:hover,
        html[data-v2-theme="light"] .v2 .dropdown-item:focus { background-color: #eff6ff; color: #0f172a; }
        html[data-v2-theme="light"] .v2 .modal-content {
            background: #ffffff; border-color: #e2e8f0; color: #334155;
            box-shadow: 0 26px 70px rgba(15, 23, 42, .2);
        }
        html[data-v2-theme="light"] .v2 .modal-title { color: #0f172a; }
        html[data-v2-theme="light"] .v2 .list-group-item { color: #334155; border-color: #e2e8f0; }
        html[data-v2-theme="light"] .v2 .page-link {
            background-color: #ffffff; border-color: #e2e8f0; color: #1d4ed8;
        }

        /* Footer & scrollbar */
        html[data-v2-theme="light"] .v2-footer {
            background: rgba(255, 255, 255, .82);
            border-top: 1px solid #e2e8f0;
            color: #64748b;
        }
        html[data-v2-theme="light"] .v2-footer strong { color: #0f172a; }
        html[data-v2-theme="light"] .v2 ::-webkit-scrollbar-track { background: rgba(15, 23, 42, .04); }
        html[data-v2-theme="light"] .v2 ::-webkit-scrollbar-thumb { background: rgba(15, 23, 42, .18); }

        /* Kartu statistik biodata (kelas dipakai hris_v2/employee_biodata) */
        html[data-v2-theme="light"] .bio-stat-val { color: #0f172a; }
        html[data-v2-theme="light"] .bio-stat-row,
        html[data-v2-theme="light"] .bio-stat { background: #f8fafc; }

        /* Tombol ganti tema di topbar */
        .v2-theme-toggle {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 6px 12px; border-radius: 999px;
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .18);
            color: #dbe7f7; font-size: .74rem; font-weight: 600;
            letter-spacing: .03em; cursor: pointer;
            transition: background .22s ease, border-color .22s ease, color .22s ease;
        }
        .v2-theme-toggle i { font-size: .82rem; }
        .v2-theme-toggle:hover, .v2-theme-toggle:focus {
            background: rgba(59, 140, 255, .2);
            border-color: rgba(59, 140, 255, .45);
            color: #fff;
        }
        html[data-v2-theme="light"] .v2-theme-toggle {
            background: #ffffff; border-color: #dbe4f0; color: #334155;
        }
        html[data-v2-theme="light"] .v2-theme-toggle:hover,
        html[data-v2-theme="light"] .v2-theme-toggle:focus {
            background: #eff6ff; border-color: #bfd8fb; color: #1d4ed8;
        }
        @media (max-width: 767.98px) {
            html[data-v2-theme="light"] .v2-card { background: #ffffff; }
            .v2-theme-toggle-label { display: none; }
            .v2-theme-toggle { padding: 6px 9px; }
        }
    </style>
    @stack('styles')
</head>
<body class="v2">
@include('partials.pwa_splash')

@include('hris_v2._particles')

<header class="v2-topbar">
    <div class="v2-topbar-inner">
        <a href="{{ route('v2.portal') }}" class="v2-brand">
            <span class="v2-logo-mask v2-brand-logo" role="img" aria-label="PT Chutex International Indonesia"></span>
            <span class="v2-brand-text">
                <span class="v2-brand-title">Chutex HRIS</span>
                <span class="v2-brand-sub">Human Resource Information System</span>
            </span>
        </a>

        <nav class="v2-nav">
            <a href="{{ route('v2.portal') }}" class="{{ request()->routeIs('v2.portal') ? 'aktif' : '' }}">Portal</a>
            <a href="{{ route('v2.employee.login') }}" class="{{ request()->routeIs('v2.employee.*') ? 'aktif' : '' }}">Employee</a>
            <a href="{{ route('v2.public.home') }}" class="{{ request()->routeIs('v2.public.*') ? 'aktif' : '' }}">Public</a>
        </nav>

        <span class="v2-chip"><i class="fas fa-circle"></i> v2 · AI Integrated</span>

        <button type="button" class="v2-theme-toggle" id="v2-theme-toggle"
                aria-label="Ganti tema terang/gelap" title="Ganti tema terang/gelap">
            <i class="fas fa-moon" id="v2-theme-icon" aria-hidden="true"></i>
            <span class="v2-theme-toggle-label" id="v2-theme-label">Gelap</span>
        </button>
    </div>
</header>

<main class="v2-wrap">
    @if(session('success'))
        <div class="alert alert-success">{!! session('success') !!}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{!! session('error') !!}</div>
    @endif

    @yield('content')
</main>

{{-- What's New (TASK 2): modal sekali sehari + floating button pembuka.
     Diletakkan SETELAH </main> supaya tidak terkurung stacking context
     .v2-wrap (z-index:2) dan tidak tertutup .v2-footer. Halaman login
     dikecualikan supaya modal tidak menutupi form.
     Elemennya position:fixed, jadi tidak memengaruhi tata letak. --}}
@unless (in_array(request()->route()?->getName(), ['v2.login', 'v2.employee.login'], true))
    @include('partials.whats_new_slider', ['mode' => 'modal'])
@endunless

<div class="v2-footer">
    <strong>Chutex HRIS v2</strong> · AI Integrated · PT Chutex International Indonesia
</div>

<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script>
/* Typing animation — behaviour & speed identical to the HRIS v1 hero.
   Caller markup: @include('hris_v2._typewriter', ['kalimat' => ['...']]) */
(function () {
    'use strict';

    var nodes = document.querySelectorAll('[data-v2-type]');
    if (!nodes.length) { return; }

    var reduceMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var TYPE_SPEED = 55;    // ms per character
    var DELETE_SPEED = 28;  // ms per character while deleting
    var HOLD_TIME = 2200;   // pause after a sentence finishes
    var GAP_TIME = 500;     // pause before the next sentence

    Array.prototype.forEach.call(nodes, function (node) {
        var inner = node.querySelector('.v2-type-inner');
        var list = [];

        try {
            list = JSON.parse(node.getAttribute('data-v2-type') || '[]');
        } catch (e) {
            list = [];
        }

        if (!inner || !list.length) { return; }

        if (reduceMotion) {
            inner.textContent = list[0];
            return;
        }

        var sIdx = 0;
        var cIdx = 0;
        var deleting = false;

        function tick() {
            var sentence = list[sIdx];
            var delay;

            if (!deleting) {
                cIdx += 1;
                inner.textContent = sentence.slice(0, cIdx);
                delay = TYPE_SPEED;

                if (cIdx === sentence.length) {
                    deleting = true;
                    delay = HOLD_TIME;
                }
            } else {
                cIdx -= 1;
                inner.textContent = sentence.slice(0, cIdx);
                delay = DELETE_SPEED;

                if (cIdx === 0) {
                    deleting = false;
                    sIdx = (sIdx + 1) % list.length;
                    delay = GAP_TIME;
                }
            }

            window.setTimeout(tick, delay);
        }

        window.setTimeout(tick, 400);
    });
})();
</script>
@include('hris_v2._ai_button')
<script>
/* Tombol ganti tema: menyimpan pilihan ke localStorage dan memasang atribut
   di <html> (bukan body) supaya semua aturan html[data-v2-theme="..."]
   langsung berlaku tanpa memuat ulang halaman. */
(function () {
    'use strict';

    var root = document.documentElement;
    var tombol = document.getElementById('v2-theme-toggle');
    var ikon = document.getElementById('v2-theme-icon');
    var label = document.getElementById('v2-theme-label');

    if (!tombol) { return; }

    function temaAktif() {
        return root.getAttribute('data-v2-theme') === 'dark' ? 'dark' : 'light';
    }

    function perbarui() {
        var tema = temaAktif();
        var keGelap = tema === 'light';

        if (ikon) {
            ikon.className = keGelap ? 'fas fa-moon' : 'fas fa-sun';
        }
        if (label) {
            label.textContent = keGelap ? 'Gelap' : 'Terang';
        }
        tombol.setAttribute('aria-pressed', tema === 'dark' ? 'true' : 'false');
        tombol.setAttribute('title', keGelap ? 'Ubah ke tema gelap' : 'Ubah ke tema terang');
    }

    tombol.addEventListener('click', function () {
        var berikutnya = temaAktif() === 'light' ? 'dark' : 'light';

        root.setAttribute('data-v2-theme', berikutnya);

        try {
            window.localStorage.setItem('chutex_v2_theme', berikutnya);
        } catch (e) { /* mode privat: tema tetap berlaku untuk halaman ini */ }

        perbarui();
    });

    perbarui();
})();
</script>
@stack('scripts')
</body>
</html>
