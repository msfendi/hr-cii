{{--
    Layout Form Recruitment (versi AI) - redesign.

    Arah desain: "SB Admin, versi 2026".
      - DNA SB Admin tetap: Nunito, palet #4e73df & kawan-kawan, ground #f4f6fb,
        kartu putih, sidebar rail.
      - Yang dimodernkan: radius lebih besar, hierarki tipografi lebih tegas,
        ritme 8px, kontrol 42px, ikon monokrom, tanpa bayangan (pemisah = garis).

    Aturan app-screen yang dipegang:
      - warna hanya untuk data (chip status AI) & satu aksi utama
      - permukaan dipisah 1px border, bukan bayangan
      - satu keluarga radius, satu keluarga ikon
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title', 'Formulir Pendaftaran')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Formulir pendaftaran rekrutmen dengan pengisian otomatis dari dokumen.">

    <link rel="icon" type="image/x-icon" href="{{ asset('storage/images/signature_icon.ico') }}">
    <link href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:400,500,600,700,800" rel="stylesheet">
    <!-- Bootstrap 4 (SB Admin 2): grid, collapse & utility class -->
    <link href="{{ asset('css/sb-admin-2.min.css') }}" rel="stylesheet">

    <style>
        :root {
            /* Permukaan */
            --rf-ground: #f4f6fb;
            --rf-surface: #ffffff;
            --rf-raised: #f8fafd;
            --rf-active: #eef1f7;

            /* Garis: 3 tingkat */
            --rf-border: #e4e8f0;
            --rf-rule: #eef1f7;
            --rf-control: #d6dbe8;

            /* Teks */
            --rf-text: #232636;
            --rf-muted: #5b6070;
            --rf-soft: #6b7280;

            /* Aksen (SB Admin primary, digelapkan agar lolos 4.5:1) */
            --rf-primary: #4267d0;
            --rf-primary-hover: #3557bb;
            --rf-primary-weak: #eef2fd;

            /* Status (data) */
            --rf-positive: #1a7f4b;
            --rf-positive-bg: #e6f7ee;
            --rf-positive-ink: #0d3f25;
            --rf-negative: #c0262c;
            --rf-negative-bg: #fdeceb;
            --rf-negative-ink: #7d1a1e;
            --rf-notice-bg: #fff8e6;
            --rf-notice-ink: #8a5a00;
            --rf-notice-border: #f3d28a;

            /* Bentuk */
            --rf-radius: 12px;
            --rf-radius-sm: 8px;
            --rf-radius-xs: 6px;

            /* Ritme */
            --rf-gap: 20px;
            --rf-pad: 24px;
            --rf-control-h: 42px;

            --rf-ring: 0 0 0 4px rgba(66, 103, 208, .13);
        }

        body.rf-body {
            background-color: var(--rf-ground);
            color: var(--rf-muted);
            font-family: Nunito, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: .875rem;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ══════════ TOPBAR ══════════ */
        .rf-topbar {
            background: var(--rf-surface);
            border-bottom: 1px solid var(--rf-border);
            position: sticky; top: 0; z-index: 1030;
        }
        .rf-topbar-inner {
            max-width: 1400px; margin: 0 auto;
            padding: .625rem 1.25rem;
            display: flex; align-items: center; gap: .875rem; min-width: 0;
        }
        .rf-brand { display: flex; align-items: center; gap: .5rem; flex-shrink: 0; }
        .rf-brand img { width: 26px; height: 26px; }
        .rf-brand-name { font-weight: 800; font-size: .9375rem; color: var(--rf-text); letter-spacing: -.01em; }
        .rf-brand-sep { width: 1px; height: 18px; background: var(--rf-border); }
        .rf-brand-page { font-weight: 600; font-size: .8125rem; color: var(--rf-muted); white-space: nowrap; }

        /* Steps: satu garis, titik aktif berisi */
        .rf-steps { display: flex; align-items: center; gap: .25rem; margin-left: auto; }
        .rf-step {
            display: flex; align-items: center; gap: .4rem;
            font-size: .75rem; font-weight: 600; color: var(--rf-muted);
            padding: .25rem .6rem .25rem .25rem; border-radius: 999px; white-space: nowrap;
        }
        .rf-step + .rf-step::before {
            content: ""; width: 14px; height: 1px; background: var(--rf-border); margin-right: .35rem;
        }
        .rf-step .rf-step-dot {
            width: 20px; height: 20px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .6875rem; font-weight: 700; font-variant-numeric: tabular-nums;
            background: var(--rf-active); color: var(--rf-muted); flex-shrink: 0;
        }
        .rf-step.is-active { color: var(--rf-text); background: var(--rf-primary-weak); }
        .rf-step.is-active .rf-step-dot { background: var(--rf-primary); color: #fff; }
        .rf-step.is-done .rf-step-dot { background: var(--rf-positive); color: #fff; }

        @media (max-width: 767.98px) {
            .rf-topbar-inner { padding: .5rem 1rem; }
            .rf-step { padding: .2rem; background: transparent; }
            .rf-step + .rf-step::before { display: none; }
            .rf-step-label { display: none; }
            .rf-brand-page, .rf-brand-sep { display: none; }
        }

        /* ══════════ SHELL ══════════ */
        .rf-shell {
            max-width: 1400px; margin: 0 auto;
            padding: 1.5rem 1.25rem 4rem;
            display: grid;
            grid-template-columns: 312px minmax(0, 1fr);
            gap: 1.75rem; align-items: start;
        }
        .rf-shell > * { min-width: 0; }
        @media (max-width: 1199.98px) {
            .rf-shell { grid-template-columns: minmax(0, 1fr); gap: 1.25rem; }
        }
        @media (max-width: 991.98px) {
            .rf-shell { padding: 1.25rem 1rem 5.75rem; }
        }

        .rf-rail {
            position: sticky; top: 76px;
            display: flex; flex-direction: column; gap: var(--rf-gap); min-width: 0;
        }
        @media (max-width: 1199.98px) { .rf-rail { position: static; } }

        /* ══════════ PANEL ══════════ */
        .rf-panel {
            background: var(--rf-surface);
            border: 1px solid var(--rf-border);
            border-radius: var(--rf-radius);
            min-width: 0; overflow: hidden;
        }
        .rf-panel-head {
            display: flex; align-items: center; gap: .5rem;
            padding: .875rem 1rem;
            font-weight: 700; font-size: .8125rem; color: var(--rf-text);
            letter-spacing: .01em;
        }
        .rf-panel-head > i { color: var(--rf-soft); font-size: .8125rem; }
        .rf-panel-head .rf-count {
            margin-left: auto; font-size: .75rem; font-weight: 600; color: var(--rf-muted);
            font-variant-numeric: tabular-nums;
        }
        .rf-panel-body { padding: 0 1rem 1rem; }

        .rf-progress { height: 5px; background: var(--rf-rule); border-radius: 999px; overflow: hidden; }
        .rf-progress-bar { height: 100%; width: 0; background: var(--rf-primary); border-radius: 999px; transition: width .3s ease; }
        .rf-progress-meta {
            display: flex; justify-content: space-between; gap: .5rem;
            font-size: .75rem; color: var(--rf-muted); margin: .5rem 0 .875rem;
        }
        .rf-progress-meta span:last-child { font-variant-numeric: tabular-nums; font-weight: 700; color: var(--rf-text); }

        /* ══════════ DAFTAR DOKUMEN ══════════ */
        .rf-doc {
            display: flex; align-items: center; gap: .625rem;
            padding: .5rem .375rem; margin: 0 -.375rem;
            border-radius: var(--rf-radius-sm);
            transition: background-color .15s;
        }
        .rf-doc + .rf-doc { box-shadow: inset 0 1px 0 var(--rf-rule); }
        .rf-doc:hover { background: var(--rf-raised); }
        .rf-doc:hover .rf-doc-icon { transform: scale(1.1); color: var(--rf-text); }
        .rf-doc.is-busy { background: var(--rf-primary-weak); }
        .rf-doc.is-error { background: var(--rf-negative-bg); }

        .rf-doc-icon {
            width: 18px; height: 18px; flex: 0 0 18px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .8125rem; color: var(--rf-soft);
            transition: color .2s ease, transform .2s ease;
        }
        .rf-doc.is-uploaded .rf-doc-icon { color: var(--rf-positive); }
        .rf-doc.is-busy .rf-doc-icon { color: var(--rf-primary); animation: rf-pulse 1.1s ease-in-out infinite; }
        .rf-doc.is-error .rf-doc-icon { color: var(--rf-negative); }

        .rf-doc-main { min-width: 0; flex: 1; overflow: hidden; }
        .rf-doc-title {
            font-size: .8125rem; font-weight: 600; color: var(--rf-text); line-height: 1.35;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .rf-doc-title .rf-req { color: var(--rf-negative); font-weight: 700; }
        .rf-doc-sub {
            font-size: .71875rem; color: var(--rf-soft); line-height: 1.4;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .rf-doc-sub.is-ok { color: var(--rf-positive); }
        .rf-doc-sub.is-err { color: var(--rf-negative); }
        .rf-doc-actions { display: flex; gap: .125rem; flex-shrink: 0; }

        .rf-icon-btn {
            width: 28px; height: 28px; border-radius: var(--rf-radius-xs);
            border: 1px solid transparent; background: transparent; color: var(--rf-soft);
            font-size: .75rem; display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: color .15s, background-color .15s, border-color .15s;
        }
        .rf-icon-btn:hover { color: var(--rf-text); background: var(--rf-active); }
        .rf-icon-btn:focus-visible { outline: 2px solid var(--rf-primary); outline-offset: 1px; }
        .rf-icon-btn.is-primary { color: var(--rf-primary); background: var(--rf-primary-weak); }
        .rf-icon-btn.is-primary:hover { color: #fff; background: var(--rf-primary); }
        .rf-icon-btn.is-danger:hover { color: var(--rf-negative); background: var(--rf-negative-bg); }
        .rf-icon-btn:disabled { opacity: .4; cursor: not-allowed; }

        /* ══════════ CHIP ══════════ */
        .rf-chip {
            display: inline-flex; align-items: center; gap: .25rem;
            font-size: .71875rem; font-weight: 600; line-height: 1.5;
            padding: .125rem .5rem; border-radius: 999px;
            border: 1px solid transparent; white-space: nowrap; flex: none;
            font-variant-numeric: tabular-nums;
        }
        .rf-chip-neutral { background: var(--rf-raised); color: var(--rf-muted); border-color: var(--rf-border); }
        .rf-chip-primary { background: var(--rf-primary-weak); color: #2f4fae; border-color: #d5defa; }
        .rf-chip-warning { background: var(--rf-notice-bg); color: var(--rf-notice-ink); border-color: var(--rf-notice-border); }
        .rf-chip-success { background: var(--rf-positive-bg); color: var(--rf-positive-ink); border-color: #b9e6cd; }

        /* Tombol proses semua dokumen */
        .rf-btn-all {
            width: 100%; margin-top: .75rem;
            display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
            font-family: inherit; font-size: .8125rem; font-weight: 700;
            color: var(--rf-primary); background: var(--rf-primary-weak);
            border: 1px solid #d5defa; border-radius: var(--rf-radius-sm);
            padding: .5625rem .75rem; cursor: pointer;
            transition: background-color .15s, border-color .15s, transform .12s, opacity .15s;
        }
        .rf-btn-all:hover:not(:disabled) { background: #e3eafd; border-color: #bfcdf2; }
        .rf-btn-all:active:not(:disabled) { transform: translateY(1px); }
        .rf-btn-all:disabled { background: var(--rf-raised); color: var(--rf-soft); border-color: var(--rf-border); cursor: not-allowed; }
        .rf-btn-all.is-busy i { animation: rf-pulse 1.1s ease-in-out infinite; }

        /* ══════════ DAFTAR ISI ══════════ */
        .rf-toc-item {
            display: flex; align-items: center; gap: .625rem;
            padding: .4375rem .5rem; margin: 0 -.25rem;
            border-radius: var(--rf-radius-sm);
            font-size: .8125rem; font-weight: 600; color: var(--rf-muted);
            cursor: pointer; border: 0; width: calc(100% + .5rem);
            background: none; text-align: left; transition: background-color .15s, color .15s;
        }
        .rf-toc-item:hover { background: var(--rf-raised); color: var(--rf-text); }
        .rf-toc-item .rf-toc-icon { color: var(--rf-soft); font-size: .75rem; width: 14px; text-align: center; }
        .rf-toc-item .rf-toc-count { margin-left: auto; font-size: .71875rem; color: var(--rf-soft); font-weight: 600; font-variant-numeric: tabular-nums; }
        .rf-toc-item.is-warn .rf-toc-count { color: var(--rf-notice-ink); }
        @media (max-width: 1199.98px) { .rf-toc { display: none; } }

        /* ══════════ HEADER HALAMAN ══════════ */
        .rf-pagehead {
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end;
            gap: .75rem 1rem; margin-bottom: 1.25rem;
        }
        .rf-pagehead-main { min-width: 0; }
        .rf-eyebrow {
            display: block; font-size: .6875rem; font-weight: 700; letter-spacing: .08em;
            color: var(--rf-primary); margin-bottom: .25rem;
        }
        .rf-pagehead h1 {
            font-size: 1.375rem; font-weight: 800; color: var(--rf-text);
            margin: 0; letter-spacing: -.015em; line-height: 1.25;
        }
        .rf-pagehead p { margin: .25rem 0 0; font-size: .8125rem; color: var(--rf-muted); max-width: 62ch; }
        .rf-summary { display: flex; flex-wrap: wrap; gap: .375rem; }

        /* ══════════ SECTION ══════════ */
        .rf-section {
            --rf-tone: #4267d0;
            --rf-tone-weak: #f3f6fe;
            --rf-tone-border: #dbe3fa;
            background: var(--rf-surface);
            border: 1px solid var(--rf-border);
            border-radius: var(--rf-radius);
            margin-bottom: var(--rf-gap);
            overflow: hidden;
            animation: rf-rise .4s cubic-bezier(.22,.68,.32,1) both;
        }
        .rf-section.tone-primary { --rf-tone: #3f63cf; --rf-tone-weak: #f3f6fe; --rf-tone-border: #dbe3fa; }
        .rf-section.tone-info    { --rf-tone: #1d8fa3; --rf-tone-weak: #f0fafc; --rf-tone-border: #cfebf1; }
        .rf-section.tone-success { --rf-tone: #1a8a55; --rf-tone-weak: #f0faf5; --rf-tone-border: #cfeadd; }
        .rf-section.tone-warning { --rf-tone: #9a6b00; --rf-tone-weak: #fdf9ee; --rf-tone-border: #f0e2bd; }
        .rf-section.tone-danger  { --rf-tone: #b3312f; --rf-tone-weak: #fdf4f3; --rf-tone-border: #f2d6d3; }
        .rf-section.tone-violet  { --rf-tone: #6d4bc7; --rf-tone-weak: #f7f4fe; --rf-tone-border: #e3daf9; }
        .rf-section.tone-slate   { --rf-tone: #5b6478; --rf-tone-weak: #f6f7fa; --rf-tone-border: #e2e5ec; }

        .rf-section-head {
            display: flex; align-items: center; gap: .75rem;
            padding: .875rem 1.25rem; cursor: pointer; user-select: none;
            transition: background-color .2s ease;
        }
        .rf-section-head:hover { background: var(--rf-tone-weak); }
        .rf-section.is-open .rf-section-head {
            background: var(--rf-tone-weak);
            border-bottom: 1px solid var(--rf-tone-border);
        }
        .rf-sec-index {
            font-size: .6875rem; font-weight: 700; color: var(--rf-tone);
            font-variant-numeric: tabular-nums; letter-spacing: .04em;
            width: 18px; flex: 0 0 18px; opacity: .75;
        }
        .rf-sec-icon {
            width: 20px; height: 20px; flex: 0 0 20px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .9375rem; color: var(--rf-tone);
            transition: transform .25s ease;
        }
        .rf-section-head:hover .rf-sec-icon { transform: scale(1.12) rotate(-4deg); }
        .rf-sec-title { font-weight: 700; font-size: .9375rem; color: var(--rf-text); letter-spacing: -.005em; }
        .rf-sec-chevron { margin-left: .25rem; color: var(--rf-soft); font-size: .6875rem; transition: transform .25s ease, color .2s; }
        .rf-section.is-open .rf-sec-chevron { transform: rotate(180deg); color: var(--rf-tone); }
        .rf-section-head .rf-chip { margin-left: auto; border-color: var(--rf-tone-border); }
        .rf-section-body { padding: 1.25rem 1.25rem .75rem; }

        @keyframes rf-rise {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: none; }
        }
        @keyframes rf-pop {
            from { opacity: 0; transform: scale(.92); }
            to   { opacity: 1; transform: none; }
        }
        @keyframes rf-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: .45; transform: scale(.9); }
        }
        @keyframes rf-ai-glow {
            0%   { box-shadow: inset 3px 0 0 var(--rf-primary), 0 0 0 0 rgba(66,103,208,.22); }
            100% { box-shadow: inset 3px 0 0 var(--rf-primary), 0 0 0 6px rgba(66,103,208,0); }
        }

        /* Grid field: 16px horizontal, 20px vertikal (BS4 tidak punya utilitas gap) */
        .rf-formgrid { margin-left: -8px; margin-right: -8px; margin-bottom: -20px; }
        .rf-formgrid > [class*="col-"] { padding-left: 8px; padding-right: 8px; margin-bottom: 20px; }

        /* ══════════ FIELD ══════════ */
        .rf-field { position: relative; }
        .rf-field-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: .5rem; flex-wrap: wrap; margin-bottom: .625rem;
        }
        .rf-label {
            font-size: .8125rem; font-weight: 600; color: var(--rf-text);
            margin: 0; line-height: 1.35;
        }
        .rf-label .rf-req { color: var(--rf-negative); font-weight: 700; margin-left: .125rem; }

        .rf-control { position: relative; display: block; min-width: 0; }

        .rf-input, .rf-select, .rf-textarea {
            width: 100%;
            min-height: var(--rf-control-h);
            font-family: inherit;
            font-size: .875rem; font-weight: 400; color: var(--rf-text);
            background: var(--rf-surface);
            border: 1px solid var(--rf-control);
            border-radius: var(--rf-radius-sm);
            padding: .5625rem .75rem;
            transition: border-color .15s, box-shadow .15s, background-color .15s;
        }
        .rf-textarea { min-height: 88px; line-height: 1.6; resize: vertical; padding-top: .625rem; }
        .rf-input::placeholder, .rf-textarea::placeholder { color: var(--rf-soft); }
        .rf-input:hover, .rf-select:hover, .rf-textarea:hover { border-color: #bcc4d6; }
        .rf-input:focus, .rf-select:focus, .rf-textarea:focus {
            outline: none; border-color: var(--rf-primary);
            box-shadow: var(--rf-ring); /* taste-skill-ignore: focus ring, bukan bayangan saat diam */
        }
        .rf-input:disabled, .rf-select:disabled, .rf-textarea:disabled {
            background: var(--rf-raised); color: var(--rf-muted);
            cursor: not-allowed; opacity: .7; border-color: var(--rf-rule);
        }
        .rf-select {
            appearance: none; padding-right: 2.25rem; cursor: pointer;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%235b6070'%3e%3cpath d='M8 11L3 6h10z'/%3e%3c/svg%3e");
            background-repeat: no-repeat; background-position: right .75rem center; background-size: 10px;
            background-color: var(--rf-surface);
        }
        .rf-input[type="date"]::-webkit-calendar-picker-indicator { opacity: .45; cursor: pointer; }
        .rf-input[type="date"]::-webkit-calendar-picker-indicator:hover { opacity: .85; }

        /* ══════════ RADIO (jenis kelamin, KB) ══════════ */
        .rf-radios { display: flex; flex-wrap: wrap; gap: .5rem; min-height: var(--rf-control-h); align-items: center; }

        .rf-radio { position: relative; display: inline-flex; }

        /* Input asli tetap ada (aksesibilitas + submit), tapi transparan. */
        .rf-radio input {
            position: absolute; inset: 0; width: 100%; height: 100%;
            margin: 0; opacity: 0; cursor: pointer;
        }

        .rf-radio span {
            display: inline-flex; align-items: center; gap: .45rem;
            min-height: var(--rf-control-h); padding: .5rem 1rem;
            font-size: .875rem; font-weight: 600; color: var(--rf-muted);
            background: var(--rf-surface);
            border: 1px solid var(--rf-control); border-radius: var(--rf-radius-sm);
            transition: border-color .15s, background-color .15s, color .15s, box-shadow .15s, transform .12s;
        }

        .rf-radio span::before {
            content: ""; width: 14px; height: 14px; flex: none;
            border-radius: 50%; border: 2px solid #c3cad8; background: #fff;
            transition: border-color .15s, background .15s;
        }

        .rf-radio:hover span { border-color: #bcc4d6; color: var(--rf-text); }
        .rf-radio:active span { transform: scale(.985); }

        .rf-radio input:checked + span {
            color: #2f4fae; border-color: var(--rf-primary);
            background: var(--rf-primary-weak); box-shadow: inset 0 0 0 1px var(--rf-primary);
        }

        .rf-radio input:checked + span::before {
            border-color: var(--rf-primary);
            background: radial-gradient(circle, var(--rf-primary) 0 38%, transparent 40%), #fff;
        }

        /* Radio di dalam kartu daftar (gender saudara/anak) — lebih ringkas */
        .rf-radios-sm { min-height: 36px; gap: .375rem; }
        .rf-radios-sm .rf-radio span { min-height: 32px; padding: .25rem .625rem; font-size: .75rem; gap: .375rem; }
        .rf-radios-sm .rf-radio span::before { width: 12px; height: 12px; }

        .rf-radio input:focus-visible + span {
            outline: none; border-color: var(--rf-primary); box-shadow: var(--rf-ring);
        }

        .rf-radios.is-ai .rf-radio input:checked + span { animation: rf-ai-glow 1s ease-out; }

        .rf-field.has-error .rf-radio span { border-color: var(--rf-negative); }

        /* Field hasil AI */
        .rf-input.is-ai, .rf-select.is-ai, .rf-textarea.is-ai {
            background-color: #fbfcff; border-color: #bfcdf2;
            box-shadow: inset 3px 0 0 var(--rf-primary); /* penanda field terisi AI */
            animation: rf-ai-glow 1s ease-out;
        }
        .rf-ai-mark {
            display: inline-block; width: 3px; height: 14px; border-radius: 2px;
            background: var(--rf-primary); margin-right: .25rem; vertical-align: -2px;
        }

        /* ══════════ GRUP (ayah/ibu) — kolom, bukan kartu ══════════ */
        .rf-group-cols { display: grid; grid-template-columns: minmax(0, 1fr); gap: .875rem; }
        .rf-group-col { min-width: 0; }
        .rf-sub-title {
            display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;
            font-size: .8125rem; font-weight: 700; color: var(--rf-text);
            margin: 0 0 .75rem; letter-spacing: .01em;
        }
        .rf-sub .rf-label { display: block; margin-bottom: .375rem; }

        /* ══════════ ENTRY (daftar berulang) ══════════ */
        .rf-entry {
            background: var(--rf-surface);
            border: 1px solid var(--rf-border);
            border-radius: var(--rf-radius-sm);
            margin-bottom: .75rem; overflow: hidden;
            animation: rf-rise .3s cubic-bezier(.22,.68,.32,1) both;
            transition: border-color .2s ease;
        }
        .rf-entry:hover { border-color: var(--rf-tone-border, var(--rf-border)); }
        .rf-entry:last-of-type { margin-bottom: .5rem; }
        .rf-entry-head {
            display: flex; align-items: center; gap: .5rem;
            padding: .5rem .75rem; background: var(--rf-raised);
            border-bottom: 1px solid var(--rf-rule);
        }
        .rf-entry-num {
            width: 20px; height: 20px; flex: 0 0 20px; border-radius: var(--rf-radius-xs);
            background: var(--rf-tone-weak, var(--rf-surface)); border: 1px solid var(--rf-tone-border, var(--rf-border)); color: var(--rf-tone, var(--rf-muted));
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .65625rem; font-weight: 700; font-variant-numeric: tabular-nums;
        }
        .rf-entry-title { font-size: .78125rem; font-weight: 600; color: var(--rf-text); margin-right: auto; }
        .rf-entry-del {
            width: 26px; height: 26px; border-radius: var(--rf-radius-xs);
            border: 0; background: transparent; color: var(--rf-soft);
            font-size: .71875rem; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            transition: color .15s, background-color .15s;
        }
        .rf-entry-del:hover { color: var(--rf-negative); background: var(--rf-negative-bg); }
        .rf-entry-body { padding: .875rem .875rem .75rem; }

        .rf-entry-grid { display: flex; flex-wrap: wrap; gap: .75rem 1rem; }
        .rf-entry-grid > * { flex: 1 1 200px; min-width: 0; }
        .rf-entry-grid > .rf-span-2 { flex: 1 1 400px; }
        .rf-entry-grid > .rf-span-full { flex: 1 1 100%; }
        .rf-entry .rf-input, .rf-entry .rf-select, .rf-entry .rf-textarea {
            font-size: .8125rem; min-height: 36px; padding: .4375rem .625rem;
        }
        .rf-entry .rf-textarea { min-height: 64px; }
        .rf-entry .rf-label { font-size: .75rem; display: block; margin-bottom: .375rem; }

        .rf-list-empty { font-size: .8125rem; color: var(--rf-soft); padding: .25rem 0 .5rem; }

        .rf-btn-add {
            width: 100%;
            border: 1px dashed var(--rf-control); background: var(--rf-surface); color: var(--rf-muted);
            font-family: inherit; font-size: .8125rem; font-weight: 600;
            border-radius: var(--rf-radius-sm);
            padding: .5625rem .75rem; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
            transition: color .18s, background-color .18s, border-color .18s;
        }
        .rf-btn-add:hover { background: var(--rf-primary-weak); border-color: var(--rf-primary); color: var(--rf-primary); }
        .rf-btn-add i { font-size: .75rem; }
        .rf-btn-add-inline { width: auto; padding: .3125rem .625rem; font-size: .75rem; }

        .rf-check {
            display: inline-flex; align-items: center; gap: .5rem;
            font-size: .8125rem; color: var(--rf-text); cursor: pointer;
        }
        .rf-check input { accent-color: var(--rf-primary); width: 1rem; height: 1rem; }

        /* Checkbox "Sama dengan alamat asal" */
        .rf-same {
            display: flex; align-items: center; gap: .5rem;
            margin: 0 0 .5rem; padding: .375rem .625rem;
            font-size: .8125rem; font-weight: 600; color: var(--rf-muted);
            background: var(--rf-raised); border: 1px dashed var(--rf-control);
            border-radius: var(--rf-radius-xs); cursor: pointer;
            transition: color .15s, border-color .15s, background-color .15s;
        }
        .rf-same:hover { color: var(--rf-text); border-color: var(--rf-primary); }
        .rf-same input { accent-color: var(--rf-primary); width: 1rem; height: 1rem; flex: none; }
        .rf-same:has(input:checked) {
            color: var(--rf-primary); border-style: solid;
            border-color: var(--rf-primary); background: var(--rf-primary-weak);
        }

        /* Field yang dikunci karena menyalin alamat asal */
        .rf-field.is-locked .rf-input,
        .rf-field.is-locked .rf-textarea,
        .rf-field.is-locked .rf-select {
            pointer-events: none; background: var(--rf-raised); color: var(--rf-muted);
        }

        /* ══════════ CHIP AI (data) ══════════ */
        .rf-ai-chip {
            animation: rf-pop .22s ease both;
            display: inline-flex; align-items: center; gap: .25rem;
            font-size: .71875rem; font-weight: 600; line-height: 1.5;
            padding: .0625rem .4375rem; border-radius: 999px;
            border: 1px solid transparent; white-space: nowrap; flex: none;
        }
        .rf-ai-chip.ok       { background: var(--rf-positive-bg); color: var(--rf-positive-ink); border-color: #b9e6cd; }
        .rf-ai-chip.low      { background: var(--rf-notice-bg); color: var(--rf-notice-ink); border-color: var(--rf-notice-border); }
        .rf-ai-chip.conflict { background: var(--rf-negative-bg); color: var(--rf-negative-ink); border-color: #f4c3c0; }
        .rf-ai-wrap {
            display: inline-flex; align-items: center; justify-content: flex-end;
            gap: .375rem; flex: 0 0 auto;
        }
        .rf-conflict-wrap { margin-top: .375rem; }
        .rf-conflict-wrap:empty { display: none; margin: 0; }
        .rf-ai-alt {
            font-size: .75rem; line-height: 1.45; color: var(--rf-negative-ink);
            background: var(--rf-negative-bg); border: 1px solid #f4c3c0;
            border-radius: var(--rf-radius-xs); padding: .375rem .625rem;
            white-space: normal;
        }
        .rf-ai-alt > b { display: block; font-weight: 700; margin-bottom: .25rem; }
        .rf-ai-alt-item { display: block; }
        .rf-ai-alt-item + .rf-ai-alt-item { margin-top: .15rem; }
        .rf-ai-alt-item b { font-weight: 700; }

        /* ══════════ TOMBOL ══════════ */
        .rf-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
            transition: background-color .15s, color .15s, border-color .15s, transform .12s ease;
            font-family: inherit; font-size: .8125rem; font-weight: 700;
            border-radius: var(--rf-radius-sm);
            padding: .5rem 1rem; min-height: 38px;
            cursor: pointer; border: 1px solid transparent;
        }
        .rf-btn-sm {
            font-size: .75rem; font-weight: 600;
            padding: .25rem .65rem; min-height: 29px;
            border-radius: var(--rf-radius-xs); gap: .3rem;
        }
        .rf-btn-primary { background: var(--rf-primary); color: #fff; }
        .rf-btn-primary:hover { background: var(--rf-primary-hover); color: #fff; }
        .rf-btn:active { transform: translateY(1px); }
        .rf-btn-outline { background: var(--rf-surface); color: var(--rf-text); border-color: var(--rf-control); }
        .rf-btn-outline:hover { background: var(--rf-raised); }
        .rf-btn-lg { font-size: .9375rem; padding: .75rem 1.5rem; min-height: 46px; border-radius: 10px; }
        .rf-btn-reset-fields {
            color: var(--rf-muted);
            border-color: var(--rf-control);
            background: transparent;
        }
        .rf-btn-reset-fields:hover {
            color: var(--rf-negative);
            border-color: #f4c3c0;
            background: var(--rf-negative-bg);
        }

        /* ══════════ CATATAN & ERROR ══════════ */
        .rf-note {
            display: flex; align-items: flex-start; gap: .625rem;
            padding: .75rem .875rem; background: var(--rf-raised);
            border: 1px solid var(--rf-border);
            border-radius: var(--rf-radius-sm);
            font-size: .78125rem; line-height: 1.55; color: var(--rf-muted);
        }
        .rf-note > span { flex: 1; min-width: 0; }
        .rf-note b, .rf-note strong { color: var(--rf-text); }
        .rf-note i { color: var(--rf-soft); margin-top: .15rem; flex-shrink: 0; }
        .rf-note.is-warn { background: var(--rf-notice-bg); border-color: var(--rf-notice-border); color: var(--rf-notice-ink); }
        .rf-note.is-warn i { color: var(--rf-notice-ink); }

        .rf-error {
            font-size: .75rem; color: var(--rf-negative);
            margin: .375rem 0 0; font-weight: 600;
            display: flex; align-items: center; gap: .35rem;
        }
        .rf-field.has-error > .rf-field-head .rf-label { color: var(--rf-negative); }
        .rf-input.is-invalid, .rf-select.is-invalid, .rf-textarea.is-invalid,
        .rf-input[aria-invalid="true"], .rf-select[aria-invalid="true"], .rf-textarea[aria-invalid="true"] {
            border-color: var(--rf-negative); background-color: #fffafa;
        }
        .rf-input.is-invalid:focus, .rf-select.is-invalid:focus, .rf-textarea.is-invalid:focus {
            box-shadow: 0 0 0 4px rgba(192, 38, 44, .12); /* taste-skill-ignore: focus ring */
        }

        /* ══════════ RINGKASAN ERROR ══════════ */
        .rf-alert {
            border: 1px solid #f4c3c0; border-radius: var(--rf-radius);
            background: var(--rf-surface); overflow: hidden; margin-bottom: var(--rf-gap);
        }
        .rf-alert-head {
            display: flex; align-items: flex-start; gap: .625rem;
            padding: .875rem 1rem; background: var(--rf-negative-bg);
            border-bottom: 1px solid #f4c3c0;
        }
        .rf-alert-head > i { color: var(--rf-negative); margin-top: .15rem; }
        .rf-alert-head b { display: block; font-size: .875rem; font-weight: 700; color: var(--rf-negative-ink); }
        .rf-alert-head span { font-size: .78125rem; color: var(--rf-negative-ink); opacity: .85; }
        .rf-alert ul { margin: 0; padding: .875rem 1rem .875rem 2rem; max-height: 220px; overflow: auto; }
        .rf-alert li { font-size: .8125rem; color: var(--rf-text); }
        .rf-alert li + li { margin-top: .25rem; }

        /* ══════════ ACTION BAR (mobile) ══════════ */
        .rf-actionbar {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 1020;
            background: var(--rf-surface); border-top: 1px solid var(--rf-border);
            padding: .625rem 1rem; display: none; gap: .75rem; align-items: center;
        }
        @media (max-width: 1199.98px) { .rf-actionbar { display: flex; } }
        .rf-actionbar .rf-progress { height: 4px; }

        /* ══════════ BLOK KIRIM ══════════ */
        .rf-submitblock {
            background: var(--rf-surface); border: 1px solid var(--rf-border);
            border-radius: var(--rf-radius); padding: 1.25rem;
        }
        .rf-submitblock .rf-declare {
            display: flex; align-items: flex-start; gap: .625rem;
            padding: .875rem; border: 1px solid var(--rf-rule);
            border-radius: var(--rf-radius-sm); background: var(--rf-raised);
            cursor: pointer; margin-bottom: 1rem;
        }
        .rf-submitblock .rf-declare input { accent-color: var(--rf-primary); width: 1rem; height: 1rem; margin-top: .15rem; flex: none; }
        .rf-submitblock .rf-declare span { font-size: .8125rem; color: var(--rf-muted); line-height: 1.6; }
        .rf-submit-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1rem; }
        .rf-submit-actions small { font-size: .78125rem; color: var(--rf-muted); }

        @media (max-width: 575.98px) {
            .rf-pagehead h1 { font-size: 1.25rem; }
            .rf-section-head { padding: .75rem 1rem; gap: .625rem; }
            .rf-section-body { padding: 1rem 1rem .5rem; }
            .rf-entry-grid > * { flex: 1 1 100%; }
            .rf-entry-grid > .rf-span-2, .rf-entry-grid > .rf-span-full { flex: 1 1 100%; }
            .rf-icon-btn { width: 30px; height: 30px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .rf-section, .rf-entry, .rf-ai-chip { animation: none !important; }
            .rf-doc.is-busy .rf-doc-icon, .rf-input.is-ai { animation: none !important; }
            .rf-btn, .rf-doc-icon, .rf-sec-icon, .rf-sec-chevron { transition: none !important; }
        }
    </style>

    @stack('styles')
</head>

<body class="rf-body">

    <header class="rf-topbar">
        <div class="rf-topbar-inner">
            <div class="rf-brand">
                <img src="{{ asset('img/chutex.svg') }}" alt="Chutex">
                <span class="rf-brand-name">E-HRIS</span>
            </div>
            <span class="rf-brand-sep d-none d-sm-block"></span>
            <span class="rf-brand-page d-none d-sm-block">@yield('page_title', 'Formulir Pendaftaran')</span>

            <nav class="rf-steps">@yield('steps')</nav>
        </div>
    </header>

    <main>@yield('content')</main>

    <footer class="py-3 text-center" style="font-size:.75rem;color:var(--rf-muted);">
        Copyright &copy; PT. Chutex International Indonesia {{ date('Y') }}
    </footer>

    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/sweetalert/sweetalert.all.js') }}"></script>

    @stack('scripts')
</body>
</html>
