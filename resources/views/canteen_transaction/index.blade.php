<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transaksi Kantin — PT Chutex International Indonesia</title>

    {{-- PERBAIKAN V33 (2026-11-09) -- PERFORMA LOAD.
         1. preconnect ke CDN supaya koneksi TLS tidak diulang per file.
         2. font Nunito: preload untuk hindari render-blocking Google Fonts.
         3. Leaflet CSS/JS: dimuat ASYNC via preload + onload (tidak
            blocking First Paint). JS Leaflet hanya dipakai miniMap
            yang inisialisasinya sudah di-guard typeof L === 'undefined'. --}}
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link rel="preload" as="style" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" /></noscript>
    <link rel="preload" as="script" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
    <script>
        // Muat Leaflet async (tidak blocking render). Fallback sync bila
        // browser tidak support rel=preload untuk script.
        (function(){
            var s = document.createElement('script');
            s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            s.async = true;
            s.defer = true;
            document.head.appendChild(s);
        })();
    </script>
    <style>
        /* PERBAIKAN V33: placeholder miniMap supaya layout tidak lompat
           (CLS) saat Leaflet CSS lambat dimuat.
           PERBAIKAN V41: tinggi disesuaikan ke 230px (sama dengan
           tinggi final mini map) supaya tidak ada layout shift. */
        #miniMap { min-height: 230px; }
        .leaflet-container { background: #eef1f7; }
    </style>

    <style>
        body { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); min-height: 100vh; }
        .canteen-card { border: none; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(0,0,0,.275); overflow: hidden; }

        {{-- PERBAIKAN V32 (2026-11-09) — PENCAAYAAN RATA.
             MASALAH: video kamera berada di atas panel putih, sedangkan
             minimap Leaflet di bawahnya berada di atas panel putih juga.
             Tapi area kamera diberi overlay gelap
             (box-shadow 0 0 0 9999px rgba(0,0,0,.35)) sementara minimap
             tidak. Akibatnya area kamera jauh lebih gelap dari minimap —
             terlihat tidak rata / membingungkan.

             PERBAIKAN:
               1. Overlay gelap pada scanner-guide-box DILEMAHKAN drastis
                  (dari .35 -> .10) supaya area di luar lingkaran tidak
                  terlalu gelap.
               2. Video diberi filter brightness/contrast ringan supaya
                  pencahayaan wajah lebih jelas dan tidak gelap.
               3. Peta dan video diberi tinggi seragam + border yang sama
                  supaya keduanya terlihat satu blok visual.
             --}}
        .scanner-guide-box {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            width: 220px; height: 260px;
            border: 2px dashed rgba(255,255,255,.85);
            border-radius: 50%;
            pointer-events: none;
            box-shadow: 0 0 0 9999px rgba(0,0,0,.10);
            transition: all .3s ease;
        }
        .scanner-guide-box.liveness-ok {
            border-color: #1cc88a;
            box-shadow: 0 0 20px rgba(28,200,138,.75), 0 0 0 9999px rgba(0,0,0,.06);
        }
        /* Pencahayaan video: naikkan brightness sedikit, naikkan contrast
           sedikit, dan kurangi saturasi supaya wajah lebih terbaca dan
           tidak gelap seperti sebelumnya. */
        #webcam {
            filter: brightness(1.16) contrast(1.08) saturate(.92);
        }
        /* Minimap: beri tampilan terang yang sama biar menyatu dengan
           blok video, dan tile tidak terlalu gelap. */
        #miniMap { background: #eef1f7; }
        .leaflet-container { background: #eef1f7; }
        .leaflet-tile-pane { filter: brightness(1.05) saturate(.95); }

        /* Layout cetak struk Bukti Scan Kantin teroptimasi */
        @media print {
            body * {
                visibility: hidden !important;
            }
            .card-header, .btn, #stepReceipt > h6, #stepReceipt > p, #stepReceipt > .row, #receiptMapCaption {
                display: none !important;
            }
            #receiptBox, #receiptBox * {
                visibility: visible !important;
            }
            #receiptBox {
                position: fixed !important;
                left: 50% !important;
                top: 15px !important;
                transform: translateX(-50%) !important;
                width: 440px !important;
                max-width: 440px !important;
                margin: 0 !important;
                padding: 18px 20px !important;
                background: #ffffff !important;
                border: 1px dashed #b0b5be !important;
                border-radius: 12px !important;
                box-shadow: none !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                z-index: 999999 !important;
            }
            #receiptBox .row {
                display: flex !important;
                flex-wrap: nowrap !important;
            }
            #receiptBox .col-5 {
                flex: 0 0 41.666667% !important;
                max-width: 41.666667% !important;
                width: 41.666667% !important;
                padding-right: 4px !important;
            }
            #receiptBox .col-7 {
                flex: 0 0 58.333333% !important;
                max-width: 58.333333% !important;
                width: 58.333333% !important;
                padding-left: 4px !important;
            }
            #receiptBox .rowline {
                display: flex !important;
                justify-content: space-between !important;
                padding: 3px 0 !important;
                border-bottom: 1px dashed #e3e6f0 !important;
            }
            #receiptFaceImg {
                width: 100% !important;
                height: 180px !important;
                object-fit: cover !important;
                border-radius: 6px !important;
                border: 1px solid #d1d3e2 !important;
            }
            #receiptMap {
                height: 180px !important;
                border-radius: 6px !important;
                border: 1px solid #d1d3e2 !important;
                overflow: hidden !important;
                position: relative !important;
            }
            .leaflet-control-zoom,
            .leaflet-control-attribution {
                display: none !important;
            }
        }

        .canteen-choice { cursor: pointer; border: 2px solid #e3e6f0; border-radius: .75rem; padding: 1rem; text-align: center; transition: all .15s; }
        .canteen-choice:hover { border-color: #4e73df; }
        .canteen-choice.selected { border-color: #4e73df; background: rgba(78,115,223,.08); box-shadow: 0 .25rem .75rem rgba(0,0,0,.08); }
        .receipt { background: #fff; border: 1px dashed #b7b9cc; border-radius: .75rem; padding: 1rem 1.25rem; }
        .receipt hr { border-top: 1px dashed #b7b9cc; }
        .receipt .rowline { display: flex; justify-content: space-between; gap: 1rem; }
        .receipt .rowline span:first-child { color: #6b6f82; font-size: .85rem; }
        .receipt .rowline span:last-child { font-weight: 600; text-align: right; }

        {{-- PERBAIKAN V37 (2026-11-09) -- SEMBUNYIKAN LIVENESS & TANTANGAN.
             Permintaan user: "Liveness dan Tantangan di hide saja cukup
             tampilkan gps dan mode".

             Yang disembunyikan (TETAP ADA di DOM supaya JS tetap bisa
             menulis status ke elemen itu tanpa error null):
               - badge #livenessBadge  ("Liveness: ON/OFF")
               - badge #challengeBadge ("Tantangan: ON")

             PERBAIKAN V38 (2026-11-10): banner #challengeAlertBox TIDAK
             lagi disembunyikan. V37 sempat menyembunyikannya karena ikut
             menebak "Tantangan" = badge, padahal itu banner instruksi.
             Efeknya user tidak pernah melihat apa yang harus dilakukan
             ("Hadapkan wajah ke KIRI layar" / "BUKA MULUT"), sehingga
             challenge mustahil di-trigger. Banner liveness tetap
             disembunyikan karena challenge dan liveness bukan mode aktif
             bersamaan.

             Yang TETAP tampil: badge GPS (#gpsBadge) dan Mode (#modeBadge),
             banner instruksi challenge, serta status text di bawah. --}}
        .d-hide-canteen-verify-badge { display: none !important; }
        #livenessAlertBox { display: none !important; }
    </style>
@include('partials.sweetalert-resign')
</head>
<body>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card canteen-card bg-white">
                <div class="card-header bg-gradient-primary text-white text-center py-3">
                    <h5 class="font-weight-bold mb-0"><i class="fas fa-utensils mr-2"></i> Transaksi Kantin</h5>
                    <small>PT Chutex International Indonesia</small>
                </div>
                <div class="card-body p-4">

                    {{-- Window saat ini --}}
                    @if($insideWindow && $currentWindow)
                        <div id="activeWindowAlert" class="alert alert-success text-center mb-3">
                            <i class="fas fa-clock mr-1"></i> Window aktif: <strong id="activeWindowText">{{ $currentWindow->name }} ({{ $currentWindow->time_start }} – {{ $currentWindow->time_end }})</strong>
                        </div>
                    @else
                        <div id="activeWindowAlert" class="alert alert-warning text-center mb-3">
                            <i class="fas fa-exclamation-triangle mr-1"></i> <span id="activeWindowText">Di luar window jam istirahat.</span>
                            <div class="small">Window tersedia:
                                @forelse($windows as $w)
                                    <span class="badge badge-light text-dark">{{ $w->name }} {{ $w->time_start }}–{{ $w->time_end }}</span>
                                @empty
                                    <em>belum dikonfigurasi admin</em>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    {{-- STEP 1: Pilih kantin
                         PERBAIKAN V32 (2026-11-09): pakai $canteensForView,
                         bukan $canteens. Di luar window jam istirahat,
                         daftar kantin TIDAK dirender sama sekali. --}}
                    <div id="stepCanteen">
                        @if($insideWindow)
                        <h6 class="font-weight-bold text-gray-800 mb-2">1. Pilih Kantin</h6>
                        <div class="row">
                            @forelse($canteensForView as $c)
                            <div class="col-6">
                                <div class="canteen-choice" data-canteen-id="{{ $c->canteen_id }}" data-canteen-name="{{ $c->name }}">
                                    <div class="mb-1"><i class="fas fa-store" style="font-size:28px; color:#4e73df;"></i></div>
                                    <strong>Kantin {{ $c->canteen_id }}</strong>
                                    <div class="small text-muted">{{ $c->name }}</div>
                                    <div class="small text-muted">{{ $c->location }}</div>
                                    <div class="small text-muted">Kontak: {{ $c->contact_person ?: $c->pic_name }}{{ ($c->contact_phone ?: $c->pic_phone) ? ' / ' . ($c->contact_phone ?: $c->pic_phone) : '' }}</div>
                                </div>
                            </div>
                            @empty
                            <div class="col-12 alert alert-danger text-center">Data kantin belum dikonfigurasi admin.</div>
                            @endforelse
                        </div>
                        @else
                        {{-- Di luar window: TIDAK ada daftar kantin sama sekali. --}}
                        <div class="text-center py-4">
                            <i class="fas fa-clock text-warning" style="font-size:44px;"></i>
                            <h6 class="font-weight-bold text-gray-800 mt-3 mb-1">Di luar jam istirahat</h6>
                            <div class="small text-muted">Daftar kantin dan transaksi hanya tersedia pada window jam istirahat.</div>
                        </div>
                        @endif
                    </div>

                    {{-- STEP 2: Input NPK --}}
                    <div id="stepNpk" class="d-none">
                        <h6 class="font-weight-bold text-gray-800 mb-2">2. Masukkan NPK</h6>
                        <div class="alert alert-info small py-2">Kantin dipilih: <strong id="chosenCanteenLabel">-</strong></div>
                        <form id="npkForm" autocomplete="off" novalidate>
                            @csrf
                            <div class="form-group">
                                <label class="sr-only">NPK</label>
                                <input type="text" id="npkInput" class="form-control form-control-lg text-center"
                                       placeholder="Contoh: C-00827" autocapitalize="characters" autocomplete="off">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" id="btnValidNpk">
                                <i class="fas fa-check mr-1"></i> Lanjut ke Scan Wajah
                            </button>
                        </form>
                        <button class="btn btn-link btn-sm mt-2" id="btnBackToCanteen"><i class="fas fa-arrow-left"></i> Ganti kantin</button>
                    </div>

                    {{-- STEP 3: Scan wajah --}}
                    <div id="stepFace" class="d-none">
                        <h6 class="font-weight-bold text-gray-800 mb-2">3. Scan Wajah</h6>
                        <div class="alert alert-info small py-2">Kantin: <strong id="faceCanteenLabel">-</strong> &nbsp;|&nbsp; NPK: <strong id="faceNpkLabel">-</strong></div>

                        {{-- PERBAIKAN V32 (2026-11-09): include partial wajah
                             liveness dihapus dari sini. Partial
                             'canteen_transaction._liveness' BERISI DUPLIKAT
                             persis dari 'face_attendance._liveness_face' (file
                             identik 100%), sehingga halaman ini menampilkan
                             DUA wajah + DUA blok <style> .lv-* sekaligus.
                             Wajah yang dipakai sekarang hanya SATU, di dalam
                             banner instruksi liveness di bawah (baris ~172),
                             memakai partial kanonik yang juga dipakai 7 halaman
                             lain (login, cuti, payroll, face attendance, dll).
                             Partial lokal '_liveness.blade.php' juga dihapus
                             agar tidak ada lagi sumber duplikasi. --}}

                        {{-- PERBAIKAN V37 (2026-11-09): badge Liveness &
                             Tantangan disembunyikan (display:none via CSS
                             class .d-hide-canteen-verify-badge). Yang
                             ditampilkan hanya GPS + Mode, sesuai permintaan
                             user. Elemen badge liveness/challenge tetap ADA
                             di DOM karena JS (liveness-check.js /
                             random-challenge.js) menulis status ke
                             elemen itu — jadi jangan dihapus, cukup
                             disembunyikan. --}}
                        <div class="text-center mb-2">
                            <span class="badge badge-secondary" id="gpsBadge">GPS: {{ $setting->require_geomap ? 'Wajib' : 'Opsional' }}</span>
                            <span class="badge badge-light border" id="modeBadge">Mode: {{ ($canteenVerificationMode ?? '') === 'random_challenge' ? 'Random Challenge' : (($canteenVerificationMode ?? '') === 'none' ? 'Tanpa Verifikasi' : 'Liveness') }}</span>
                            <span class="badge badge-info d-hide-canteen-verify-badge" id="livenessBadge">Liveness: {{ $livenessEnabled ? 'ON' : 'OFF' }}</span>
                            @if($randomChallengeEnabled)
                            <span class="badge badge-warning d-hide-canteen-verify-badge" id="challengeBadge">Tantangan: ON</span>
                            @endif
                        </div>

                        {{-- PERBAIKAN V32 (2026-11-09): banner instruksi sesuai MODE
                             yang dipilih (liveness ATAU random challenge,
                             bukan keduanya). Hanya SATU banner yang dirender
                             dalam satu waktu. Mode dikendalikan dari
                             /face-attendance/settings (face_recognition_settings). --}}
                        @if($livenessEnabled)
                        <div id="livenessAlertBox" class="alert alert-warning py-2 px-3 mb-3 shadow-sm border-warning d-flex align-items-center justify-content-center">
                            @include('face_attendance._liveness_face', ['ukuran' => 56])
                            <span id="livenessInstructionText" class="font-weight-bold ml-2">
                                Silakan berkedip atau menoleh sedikit
                            </span>
                        </div>
                        @endif

                        @if($randomChallengeEnabled)
                        {{-- PERBAIKAN V41 (2026-11-10) -- BANNER CHALLENGE IKUT STATE ASLI.

                             BUG (dilaporkan user via screenshot): banner merah
                             sudah tertulis "Tantangan terverifikasi" SEDANG
                             kamera masih disiapkan. User mengira.verifikasi
                             sudah berhasil padahal challenge BELUM dijalankan.

                             Akar masalah: teks "Tantangan terverifikasi"
                             ditulis oleh JS (random-challenge.js -> hint()
                             ketika passed()===true) DAN oleh blok deteksi
                             di halaman ini. Tapi banner di-render Blade
                             dengan class/ikon yang SUDAH "sukses" (hijau)
                             SEBELUM ada verifikasi apa pun, dan resetFaceGates()
                             tidak pernah mengembalikan state visual banner
                             ke kondisi "menunggu".

                             PERBAIKAN:
                               1. State visual banner dipusatkan di satu
                                  fungsi setChallengeBannerState(state).
                                  state: 'idle' (menunggu kamera) |
                                         'pending' (menunggu gerakan) |
                                         'passed' (terverifikasi) |
                                         'error' (gagal/timeout).
                               2. Banner SELALU mulai di state 'idle' dengan
                                  teks "Menyiapkan verifikasi wajah..." dan
                                  ikon/warna netral (abu-abu), BUKAN merah
                                  "terverifikasi".
                               3. Class warna (danger/success/info) di-set
                                  dari JS, bukan hard-coded di Blade.
                               4. resetFaceGates() mengembalikan banner ke
                                  state 'idle' supaya scan berikutnya tidak
                                  mewarisi teks "terverifikasi".
                             --}}
                        <div id="challengeAlertBox" class="alert alert-secondary py-2 px-3 mb-3 shadow-sm border-secondary d-flex align-items-center justify-content-center">
                            <i class="fas fa-random text-secondary mr-2" id="challengeAlertIcon"></i>
                            <span id="challengeInstructionText" class="font-weight-bold">
                                Menyiapkan verifikasi wajah...
                            </span>
                        </div>
                        @endif

                        @if(!($livenessEnabled || $randomChallengeEnabled))
                        <div id="noVerifyAlertBox" class="alert alert-info py-2 px-3 mb-3 shadow-sm border-info d-flex align-items-center justify-content-center">
                            <i class="fas fa-face-smile text-info mr-2"></i>
                            <span class="font-weight-bold">Posisikan wajah di tengah area kamera. Wajah langsung dicocokkan tanpa verifikasi gerakan.</span>
                        </div>
                        @endif

                        <div class="position-relative mx-auto rounded overflow-hidden" id="cameraBoxWrapper" style="max-width:360px;">
                            <video id="webcam" autoplay playsinline muted class="w-100 rounded bg-dark" style="min-height:260px; object-fit:cover; transform:scaleX(-1);"></video>
                            <div id="cameraPlaceholder" class="position-absolute d-flex flex-column align-items-center justify-content-center text-white" style="top:0; left:0; width:100%; height:100%; background:#2e3340; z-index:5;">
                                <i class="fas fa-map-marker-alt fa-3x text-warning mb-3"></i>
                                <h5 class="font-weight-bold" id="placeholderTitle">Memeriksa Area Lokasi Kerja...</h5>
                                <p class="text-gray-400 small px-4 mb-3" id="placeholderDesc">Kamera akan otomatis aktif setelah lokasi GPS Anda terverifikasi di dalam area kerja.</p>
                                <button class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="checkGPS()"><i class="fas fa-sync-alt mr-1"></i> Cek Ulang GPS</button>
                            </div>
                            <div class="scanner-guide-box" id="scannerGuide"></div>

                            <div id="faceVerifiedFlash"
                                 class="position-absolute d-none"
                                 style="top:0; left:0; width:100%; height:100%; z-index:8; border-radius:.375rem; background:rgba(28,200,138,.16); box-shadow:inset 0 0 0 4px rgba(28,200,138,.95);">
                            </div>
                            <div id="faceVerifiedBadge"
                                 class="position-absolute d-none align-items-center px-3 py-1 rounded-pill small font-weight-bold shadow"
                                 style="top: 12px; left: 12px; z-index:9; background:#1cc88a; color:#fff;">
                                <i class="fas fa-check-circle mr-1"></i> Wajah Terverifikasi
                            </div>

                            {{-- PERBAIKAN: Splash hijau snapshot wajah TERKUNGKUNG DI DALAM KOTAK KAMERA SAJA (bukan fullscreen) --}}
                            <div id="faceSnapshotFlash"
                                 class="position-absolute d-none flex-column align-items-center justify-content-center"
                                 style="top:0; left:0; width:100%; height:100%; z-index:10; border-radius:.375rem; background:rgba(0,0,0,.7); box-shadow:inset 0 0 0 4px #1cc88a;">
                                <img id="faceSnapshotImg" src="" alt="Snapshot Wajah" style="max-width:160px; max-height:160px; border-radius:12px; border:3px solid #1cc88a; box-shadow:0 4px 16px rgba(0,0,0,.5); object-fit:cover;">
                                <div class="badge badge-success px-3 py-1 font-weight-bold mt-2 shadow">
                                    <i class="fas fa-check-circle mr-1"></i> Wajah Terverifikasi
                                </div>
                            </div>
                        </div>
                        <div id="faceVerifiedMsg" class="text-center small font-weight-bold d-none mt-2"
                             style="color:#1aaf7f;">
                            <i class="fas fa-check-circle mr-1"></i>
                            <span id="faceVerifiedFlashMsg">Wajah Anda dikenali. Memproses transaksi...</span>
                        </div>
                        <div id="faceStatus" class="text-center small text-muted mt-2"></div>

                        {{-- PERBAIKAN V32 (2026-11-09): tombol "Nyalakan/Matikan
                             Kamera" dan "Scan & Simpan" DIHILANGKAN.
                             Permintaan user: "hilangkan tombol matikan nyalakan
                             camera dan scan & simpan. ketika wajah terdeteksi
                             otomatis maka langsung post".

                             Scan terjadi OTOMATIS begitu wajah terdeteksi
                             (dan semua gerbang liveness/challenge lolos).
                             Tombol "Cek Ulang GPS" di overlay kamera tetap ada
                             karena itu bukan kontrol scan. --}}
                        <div id="autoPostNotice" class="text-center small text-muted mt-2">
                            <i class="fas fa-magic mr-1"></i> Transaksi diproses otomatis saat wajah terdeteksi.
                        </div>
                        <div class="mb-3 text-left mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="font-weight-bold text-gray-700"><i class="fas fa-map-marked-alt text-primary mr-1"></i> MINI MAP LOKASI</small>
                                <span id="miniMapStatus" class="badge badge-light border small text-muted">Memuat GPS...</span>
                            </div>
                            {{-- PERBAIKAN V41 (2026-11-10): tinggi mini map dinaikkan
                                 dari 180px -> 230px supaya POLYGON AREA terlihat
                                 jelas (area kantor Chutex cukup besar, jadi di
                                 180px polygon hanya samar di tepi).
                                 Ditambah legenda supaya user tahu warna biru =
                                 area GPS resmi. --}}
                            <div id="miniMap" class="rounded border shadow-sm" style="height:230px; width:100%; z-index:1;"></div>
                            <div class="d-flex flex-wrap justify-content-center mt-1" style="gap:.75rem;">
                                <span class="small text-muted" style="font-size:0.72rem;">
                                    <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:rgba(78,115,223,.45);border:1px solid #4e73df;"></span>
                                    Area Lokasi Kerja (polygon GPS)
                                </span>
                                <span class="small text-muted" style="font-size:0.72rem;">
                                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#1cc88a;"></span>
                                    Posisi Anda
                                </span>
                            </div>
                            <small id="miniMapAccuracyText" class="text-muted d-block text-center mt-1" style="font-size:0.75rem;">Akurasi GPS: - meter</small>
                            <div class="small text-muted mt-1 text-center" id="geoInfo">GPS: <span id="geoText">menunggu izin lokasi…</span></div>
                        </div>
                        <button class="btn btn-link btn-sm mt-2" id="btnBackToNpk"><i class="fas fa-arrow-left"></i> Kembali</button>
                    </div>

                    {{-- STEP 4: Receipt / invoice --}}
                    <div id="stepReceipt" class="d-none">
                        <h6 class="font-weight-bold text-gray-800 mb-2 text-center"><i class="fas fa-receipt mr-1"></i> Bukti Scan Kantin</h6>
                        <p class="small text-muted text-center">Tunjukkan bukti ini ke pihak kantin, seperti bukti bayar QRIS.</p>
                        <div class="receipt" id="receiptBox"></div>
                        <div class="small text-muted text-center mt-1 d-none" id="receiptMapCaption">Peta lokasi hasil scan Anda</div>
                        <div class="row mt-3">
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-primary btn-block" id="btnPrintReceiptTransaction" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-primary btn-block" id="btnDone"><i class="fas fa-check mr-1"></i> Selesai</button>
                            </div>
                        </div>
                    </div>

                    <div id="globalError" class="alert alert-danger d-none mt-3"></div>

                </div>
            </div>
            <div class="text-center mt-3"><a href="/" class="text-white small"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Beranda</a></div>
        </div>
    </div>
</div>

{{-- PERBAIKAN V33 (2026-11-09) -- defer semua asset berat.
     face-api.js 1,3MB + model AI 6,6MB hanya dipakai SETELAH user
     memilih kantin & input NPK (loadFaceModels()). Memuatnya sync
     di akhir body membuat halaman terlihat hang sebelum FCP.
     defer + async: browser download paralel, execute berurutan. --}}

{{-- PERBAIKAN V38 (2026-11-10) -- HAPUS `async` DARI TAG SCRIPT INI.
     Root cause "wajah terdeteksi tapi transaksi tidak diproses":
     `defer async` TIDAK menjamin urutan eksekusi. `async` = download
     secepat mungkin, eksekusi kapan saja selesai. Script inline di
     bawah (yang membuat `new RandomChallenge(...)`) dieksekusi saat
     parse, yaitu SEBELUM random-challenge.js selesai dimuat. Akibatnya
     `typeof RandomChallenge === 'undefined'` -> randomChallenge = null
     -> blok challenge di loop deteksi dilewati -> randomChallengeVerified
     tetap false -> scanReady false -> auto-post TIDAK PERNAH jalan.

     Bukti (Chromium headless, HTTP lokal): dengan `defer async` ketiga
     global tetap `undefined` SETELAH load; dengan `defer` ketiganya
     `function`. Jadi cukup `defer` TANPA `async`:
       - defer = dieksekusi setelah parsing selesai
       - urutan eksekusi = urutan tag di dokumen (dijamin)
       - download tetap paralel (tidak memperlambat)
     Script inline tetap dijadwal ulang lewat DOMContentLoaded (lihat
     initFaceGates di bawah) supaya bebas dari urutan apa pun. --}}
<script src="{{ asset('vendor-face-api/face-api.js') }}" defer></script>
{{-- PERBAIKAN V32 (2026-11-09): liveness & random challenge DETEKTOR BERSAMA.
     Dipakai bersama oleh /face-attendance/login, /face-attendance, dan
     /canteen-transaction supaya ambang & urutan gerbang identik.
    -random-challenge.js berdiri sendiri, TIDAK bergantung liveness-check.js. --}}
<script src="{{ asset('js/liveness-check.js') }}?v=14" defer></script>
<script src="{{ asset('js/random-challenge.js') }}?v=2" defer></script>
{{-- PERBAIKAN V37 (2026-11-09) -- HAPUS FALLBACK CDN YANG BENTROK.
     SEBELUMNYA: script lokal (vendor-face-api/face-api.js) dimuat
     dengan defer async, DAN ada fallback CDN yang juga di-append.
     Karena `defer async` tidak menjaga urutan, keduanya bisa dieksekusi
     HANYA SEKALI. Hasilnya user melihat ratusan pesan:
         "Platform browser has already been set"
         "cpu/webgl/wasm backend was already registered"
         "The kernel 'X' for backend 'Y' is already registered"
     itu BUKAN error fatal, tapi membanjiri console, menggandakan
     registered backend/kernel, dan membuat state face-api.js tidak
     bisa dipercaya (detach/attach bisa salah).

     PERBAIKAN: fallback CDN hanya dimuat kalau file LOKAL benar-benar
     GAGAL dimuat (onerror), bukan secara paralel. Jadi face-api.js
     selalu dieksekusi tepat satu kali.

     PENTING: jangan set `async` pada fallback — defer saja, supaya
     urutan: face-api.js -> liveness-check.js -> random-challenge.js
     tetap terjamin. --}}
<script>
(function(){
    'use strict';
    var attempted = false;
    function loadCdnFallback(){
        if (attempted) return;
        attempted = true;
        if (typeof faceapi !== 'undefined') return; // lokal sudah OK
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js';
        s.defer = true;   // JANGAN async: jaga urutan eksekusi
        s.onerror = function(){
            console.error('[Canteen] Gagal memuat face-api.js dari lokal maupun CDN.');
        };
        document.head.appendChild(s);
    }

    // 1) Tunggu script lokal selesai (defer = dieksekusi setelah parse).
    //    Kalau loadFromUri model nanti gagal, loadFaceModels() sudah
    //    punya daftar kandidat URL sendiri.
    if (document.readyState === 'complete') {
        loadCdnFallback();
    } else {
        window.addEventListener('load', loadCdnFallback);
    }

    // 2) Kalau script lokal GAGAL (404 / jaringan), langsung pakai CDN.
    var local = document.querySelector('script[src*="vendor-face-api/face-api.js"]');
    if (local) {
        local.addEventListener('error', loadCdnFallback);
    }
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer async></script>
<script>
(function(){
'use strict';
let selectedCanteenId = null;
let selectedCanteenName = '';
let validatedNpk = '';
let cameraStream = null;
let faceLoaded = false;
let geoCoords = { latitude: null, longitude: null, accuracy: null };

// ==============================================================
// PERBAIKAN V32 (2026-11-09) -- MODE CANTEEN: LIVENESS ATAU RANDOM
// ==============================================================
// Permintaan user: "mode tersebut bisa memilih mau pakai liveness
// atau pakai random challenge, bukan keduanya".
//
// Mode dikendalikan dari /face-attendance/settings
// (face_recognition_settings.canteen_verification_mode) dan hanya
// SATU gerbang yang aktif dalam satu waktu:
//
//   liveness         -> kedip / menoleh (LivenessCheck)
//   random_challenge -> tantangan acak (hadap kiri/kanan/buka mulut)
//   none             -> wajah langsung dicocokkan (tanpa gerbang)
//
// Fallback: kalau mode tidak terkirim, default liveness = ON
// (fail-safe), random challenge = OFF.
// ==============================================================
const CANTEEN_VERIFICATION_MODE = @json($canteenVerificationMode ?? 'liveness');
const LIVENESS_REQUIRED = CANTEEN_VERIFICATION_MODE === 'liveness';
const RANDOM_CHALLENGE_REQUIRED = CANTEEN_VERIFICATION_MODE === 'random_challenge';
const RANDOM_CHALLENGE_TYPE = @json($randomChallenge['type'] ?? null);
// PERBAIKAN V39 (2026-11-10): NPK wajib (bila setting mengaktifkan) harus
// divalidasi SEBELUM auto-post, bukan mengandalkan balasan 422 dari server.
const NPK_REQUIRED = @json($npkRequired ?? true);
// PERBAIKAN V39 (2026-11-10): batas waktu request transaksi (ms).
// Server yang tidak merespons (mis. SQL Server timeout) tidak boleh membuat
// UI diam selamanya. Default 30 detik.
const AUTO_POST_TIMEOUT_MS = 30000;

let livenessVerified = !LIVENESS_REQUIRED;
let randomChallengeVerified = !RANDOM_CHALLENGE_REQUIRED;

// PERBAIKAN V32 (2026-11-09): flag anti double-post di sisi client.
// isPosting        -> sedang ada request ke server (jangan mulai lagi).
// autoPostInFlight -> auto-post sudah ditembakkan untuk frame ini.
let isPosting = false;
let autoPostInFlight = false;
// PERBAIKAN V39 (2026-11-10): bisa dioverride lewat hook debug agar
// regression test browser bisa memakai timeout pendek.
let autoPostTimeoutMs = AUTO_POST_TIMEOUT_MS;

// PERBAIKAN V38 (2026-11-10) -- LATE BINDING UNTUK GERBANG.
// SEBELUMNYA: `const randomChallenge = (typeof RandomChallenge !== 'undefined') ? new RandomChallenge(...) : null;`
// dieksekusi saat PARSE. Dengan tag script `defer async`, random-challenge.js
// belum tentu selesai dimuat -> `typeof RandomChallenge === 'undefined'`
// -> randomChallenge = null -> challenge tidak pernah di-update ->
// randomChallengeVerified tetap false -> scanReady false ->
// transaksi TIDAK PERNAH diposting, padahal wajah sudah terdeteksi.
//
// SEKARANG: variabelnya `let` dan diisi oleh initFaceGates() yang dijadwal
// pada DOMContentLoaded. DOMContentLoaded dijamin berjalan SETELAH semua
// script `defer` dieksekusi, jadi typeof RandomChallenge dijamin 'function'.
//
// Fail-safe: kalau script tetap gagal dimuat, initFaceGates() TIDAK diamkan
// hal - ia mencatat error, tampilkan pesan jelas ke user, dan membiarkan
// gate tetap tertutup (fail-closed) supaya tidak ada transaksi tanpa
// verifikasi.
let randomChallenge = null;
let liveness = null;
let faceGatesReady = false;

function initFaceGates(){
    // Random challenge (dipakai kalau mode = random_challenge).
    if (typeof RandomChallenge !== 'undefined') {
        try {
            randomChallenge = new RandomChallenge({
                // Preview kamera depan di halaman ini memakai scaleX(-1), jadi arah
                // kiri/kanan pada landmark face-api terbalik dari yang terlihat user.
                mirrored: true,
                required: RANDOM_CHALLENGE_REQUIRED,
                target: RANDOM_CHALLENGE_TYPE,
                missToleranceFrames: 12
            });
        } catch (e) {
            console.error('[Canteen] Gagal membuat RandomChallenge:', e);
            randomChallenge = null;
        }
    } else {
        console.error('[Canteen] random-challenge.js tidak termuat.');
    }

    // Liveness (dipakai kalau mode = liveness).
    if (typeof LivenessCheck !== 'undefined') {
        try {
            liveness = new LivenessCheck({ required: LIVENESS_REQUIRED, missToleranceFrames: 12 });
        } catch (e) {
            console.error('[Canteen] Gagal membuat LivenessCheck:', e);
            liveness = null;
        }
    } else {
        console.error('[Canteen] liveness-check.js tidak termuat.');
    }

    // Gerbang WAJIB punya objek. Kalau tidak, mode aktif tidak akan pernah
    // terverifikasi dan transaksi tidak akan pernah jalan. Kasih tahu user
    // dengan pesan yang jelas, bukan diam-diam menampilkan teks generik.
    const missing = [];
    if (RANDOM_CHALLENGE_REQUIRED && !randomChallenge) missing.push('Random Challenge');
    if (LIVENESS_REQUIRED && !liveness) missing.push('Liveness');
    if (missing.length > 0) {
        const msg = 'Modul ' + missing.join(' dan ') + ' gagal dimuat. Muat ulang halaman (Ctrl+F5).';
        console.error('[Canteen] Gerbang verifikasi belum siap:', msg);
        const el = document.getElementById('globalError');
        if (el) { el.textContent = msg; el.classList.remove('d-none'); }
        return;
    }

    faceGatesReady = true;

    // Bersihkan state gerbang setelah objek dibuat, supaya challenge
    // mulai dari netral dengan target dari server. resetFaceGates() sudah
    // aman dipanggil kapan saja (guard null), jadi tidak masalah kalau
    // dipanggil sebelum/sesudah init.
    try { resetFaceGates(); } catch (e) { console.warn('[Canteen] resetFaceGates:', e); }

    // PERBAIKAN V38 (2026-11-10): expose state gerbang ke window untuk
    // keperluan debugging / verifikasi end-to-end (mis. regression test
    // browser atau support yang perlu memastikan challenge benar-benar
    // terpasang). Tanpa ini, variabel di dalam IIFE tidak bisa dibaca
    // dari console.
    window.__canteenFaceGates = {
        get randomChallenge() { return randomChallenge; },
        get liveness() { return liveness; },
        get ready() { return faceGatesReady; },
        get verified() { return randomChallengeVerified; },
        get scanReady() { return scanReady; }
    };

    // PERBAIKAN V39 (2026-11-10): hook debug untuk jalur auto-post.
    //
    // Gejala produksi yang diperbaiki V39: "Tantangan terverifikasi.
    // Memproses transaksi otomatis..." lalu tidak ada request POST sama
    // sekali. Penyebabnya early-return di runAutoScan() karena guard
    // mengecek autoPostInFlight yang diset oleh pemanggilnya sendiri.
    //
    // Hook ini permite regression test browser (Playwright/Chromium)
    // menjalankan alur auto-post yang SEBENARNYA tanpa kamera fisik:
    //   window.__canteenAutoPost.simulateCamera()  -> pasang stream kamera palsu
    //   window.__canteenAutoPost.setVerified(true) -> bypass gerbang (test)
    //   window.__canteenAutoPost.triggerLoop()     -> jalankan 1 iterasi loop
    //   window.__canteenAutoPost.state()           -> baca flag internal
    // Hook ini read-only untuk produksi (tidak mengirim transaksi nyata
    // kecuali triggerLoop() dipanggil secara eksplisit).
    window.__canteenAutoPost = {
        simulateCamera: function () {
            // Stream palsu: cukup punya getTracks() supaya stopCamera()
            // dan pengecekan !cameraStream bekerja.
            cameraStream = {
                getTracks: function () { return []; },
                active: true
            };
            return true;
        },
        setVerified: function (v) {
            randomChallengeVerified = !!v;
            livenessVerified = !!v;
            refreshGateState();
            return true;
        },
        setStableFrames: function (n) { stableFrames = n; return true; },
        setTimeoutMs: function (ms) { autoPostTimeoutMs = ms; return autoPostTimeoutMs; },
        setFormValues: function (npk, canteenId) {
            // Mengisi NPK + kantin seperti form produksi (setelah validasi
            // NPK sukses). Dipakai regression test browser.
            validatedNpk = npk || '';
            selectedCanteenId = canteenId || null;
            return true;
        },
        triggerLoop: function () {
            // Menggiru kondisi loop deteksi produksi: challenge terverifikasi
            // + scanReady + stableFrames >= 3 -> harus memanggil runAutoScan().
            if (!isPosting && !autoPostInFlight && scanReady && stableFrames >= 3) {
                stableFrames = 0;
                runAutoScan();
                return true;
            }
            return false;
        },
        state: function () {
            return {
                isPosting: isPosting,
                autoPostInFlight: autoPostInFlight,
                stableFrames: stableFrames,
                scanReady: scanReady,
                randomChallengeVerified: randomChallengeVerified,
                livenessVerified: livenessVerified,
                faceGatesReady: faceGatesReady,
                hasCamera: !!cameraStream
            };
        }
    };

    // Tampilkan instruksi tantangan yang spesifik (mis. "Hadapkan wajah ke
    // KIRI layar"). random-challenge.js sudah menyediakan RandomChallenge.HINTS.
    // Tanpa ini banner hanya menampilkan teks generik sehingga user tidak
    // tahu harus melakukan gerakan apa.
    //
    // PERBAIKAN V41 (2026-11-10): banner TIDAK lagi langsung menampilkan
    // instruksi gerakan saat halaman dimuat. Waktu halaman dimuat, kamera
    // BELUM siap — verifikasi belum dijalankan. Menampilkan instruksi
    // gerakan (atau lebih buruk, "terverifikasi") pada tahap ini membuat
    // user salah paham soal status prosesnya.
    //
    // Jadi: banner mulai di state 'idle' ("Menyiapkan verifikasi wajah...").
    // Instruksi gerakan baru muncul di state 'pending' ketika loop deteksi
    // benar-benar berjalan (kamera sudah nyala + wajah terdeteksi).
    try {
        const box = document.getElementById('challengeAlertBox');
        if (RANDOM_CHALLENGE_REQUIRED && box) {
            box.classList.remove('d-none');
            if (typeof setChallengeBannerState === 'function') {
                setChallengeBannerState('idle');
            } else {
                // Fallback bila helper belum terdefinisi (urutan skrip).
                const txt = document.getElementById('challengeInstructionText');
                if (txt) txt.textContent = 'Menyiapkan verifikasi wajah...';
            }
        }
    } catch (e) {
        console.warn('[Canteen] Gagal menyiapkan banner tantangan:', e);
    }
}

// Jalankan setelah semua script `defer` selesai dieksekusi.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFaceGates);
} else {
    initFaceGates();
}

const $ = id => document.getElementById(id);
const csrf = document.querySelector('meta[name=\"csrf-token\"]').content;

function showStep(id){
  ['stepCanteen','stepNpk','stepFace','stepReceipt'].forEach(s=>$(s).classList.add('d-none'));
  $(id).classList.remove('d-none');
}
function setError(msg){
  const el = $('globalError');
  if(!msg){ el.classList.add('d-none'); el.textContent=''; return; }
  el.textContent = msg; el.classList.remove('d-none');
}
function setStatus(html){ $('faceStatus').innerHTML = html; }

// Pilih kantin
document.querySelectorAll('.canteen-choice').forEach(el=>{
  el.addEventListener('click', ()=>{
    document.querySelectorAll('.canteen-choice').forEach(x=>x.classList.remove('selected'));
    el.classList.add('selected');
    selectedCanteenId = el.dataset.canteenId;
    selectedCanteenName = el.dataset.canteenName;
    $('chosenCanteenLabel').textContent = 'Kantin ' + selectedCanteenId + ' — ' + selectedCanteenName;
    setError(null);
    showStep('stepNpk');
    setTimeout(()=>$('npkInput').focus(), 100);
  });
});
$('btnBackToCanteen').addEventListener('click', ()=>{ showStep('stepCanteen'); });

// Validasi NPK
$('npkForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const npk = $('npkInput').value.trim().toUpperCase();
  if(!npk){ setError('NPK wajib diisi.'); return; }
  if(!selectedCanteenId){ setError('Pilih Kantin 1 atau Kantin 2 terlebih dahulu.'); showStep('stepCanteen'); return; }
  setError(null);
  $('btnValidNpk').disabled = true;
  $('btnValidNpk').innerHTML = '<i class=\"fas fa-spinner fa-spin mr-1\""></i> Memvalidasi…';
  try {
    const res = await fetch(@json(route('canteen-transaction.validate-npk')), {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrf, 'Accept':'application/json' },
      body: JSON.stringify({ npk })
    });
    const data = await res.json().catch(()=>({ success:false, message:'Respons server tidak valid (HTTP ' + res.status + ').' }));

    // ==============================================================
    // PERBAIKAN 1 (mirror /login): karyawan sudah keluar (PKWT.TKK
    // terisi) -> blokir lanjut ke kamera, beri SweetAlert resign
    // seragam. Aturan: PKWT.TKK NULL = aktif, terisi = sudah keluar.
    //
    // PENTING: baca flag secara ROBUST. Server bisa mengirim
    // `inactive_employee` (kontrak /login) ATAU `inactive` (payload
    // lama FaceRecognitionService). Cek keduanya, jangan hanya satu.
    // ==============================================================
    const isInactive = !!(data.inactive_employee || data.inactive);
    // Flag sukses: `success` (kontrak /login) ATAU `ok` (payload lama).
    // JANGAN pakai `!data.success` langsung -- kalau field tidak ada,
    // nilainya undefined -> selalu falsy -> alert merah palsu.
    const isValid = (data.success === true) || (data.ok === true);

    if (isInactive) {
      if (typeof showResignAlert === 'function') {
        showResignAlert({
          message: data.message || 'Karyawan sudah keluar, tidak bisa lanjut transaksi kantin.',
          action  : 'Transaksi kantin tidak dapat dilanjutkan.',
          npk     : data.npk || npk
        });
      } else if (window.Swal) {
        Swal.fire({ icon:'error', title:'Karyawan Sudah Resign', text:data.message, confirmButtonText:'Mengerti' });
      } else {
        setError(data.message || 'NPK tidak valid.');
      }
      return;
    }

    if(!isValid || !res.ok){
      // NPK tidak valid -> SATU SweetAlert error, lalu berhenti.
      // (Dulu kode ini salah karena memakai !data.success yang
      //  undefined saat server mengirim key "ok" -> alert merah palsu.)
      if (window.Swal) {
        Swal.fire({ icon:'error', title:'NPK Tidak Valid', text:data.message || 'NPK tidak valid.', confirmButtonText:'OK' });
      }
      setError(data.message || 'NPK tidak valid.');
      return;
    }

    validatedNpk = data.npk || npk;
    $('faceCanteenLabel').textContent = 'Kantin ' + selectedCanteenId + ' — ' + selectedCanteenName;
    $('faceNpkLabel').textContent = validatedNpk;
    showStep('stepFace');

    // SATU SweetAlert sukses saja (hijau). Tidak ada alert error
    // setelah ini supaya tidak ada dua modal tumpang tindih.
    if (window.Swal) {
      Swal.fire({ icon:'success', title:'NPK Valid', text:'Silakan posisikan wajah di kamera.', confirmButtonText:'OK', timer:3000, timerProgressBar:true });
    }

    // ==============================================================
    // PERBAIKAN 2: alur kamera + GPS identik dengan /login.
    // Dulu: requestGeo(); await loadFaceModels(); await startCamera();
    //       -> GPS tidak pernah menggambar peta, kamera menyala
    //       tanpa konteks area, dan satu gagal membunuh yang lain.
    // Sekarang: initFaceCanteenFlow() = try initMiniMap ->
    //           loadFaceModels -> checkGPS(), dan checkGPS() yang
    //           memanggil startCamera() hanya bila di dalam area.
    // ==============================================================
    initFaceCanteenFlow();
  } catch(err){
    console.error('[Canteen] validasi NPK gagal:', err);
    if (window.Swal) {
      Swal.fire({ icon:'error', title:'Gagal Terhubung', text:'Gagal memvalidasi NPK. Periksa koneksi lalu coba lagi.', confirmButtonText:'OK' });
    }
    setError('Gagal memvalidasi NPK. Periksa koneksi lalu coba lagi.');
  } finally {
    $('btnValidNpk').disabled = false;
    $('btnValidNpk').innerHTML = '<i class=\"fas fa-check mr-1\""></i> Lanjut ke Scan Wajah';
  }
});
$('btnBackToNpk').addEventListener('click', ()=>{
  stopCamera();
  faceFlowStarted = false;
  // PERBAIKAN V32 (2026-11-09): reset gerbang liveness & challenge.
  // Kalau tidak, user yang kembali lalu scan ulang akan langsung lolos
  // tanpa melakukan tantangan/liveness lagi.
  resetFaceGates();
  showStep('stepNpk');
});

// ==================================================================
// Mini Map + GPS — MIRROR /face-attendance/login (2026-11-09)
// ------------------------------------------------------------------
// Kenapa dulu GPS minimap tidak muncul & kamera tidak auto-nyala:
//
// 1) Halaman lama hanya punya teks "GPS: menunggu izin lokasi" dan
//    fungsi requestGeo() yang tidak menggambar peta sama sekali.
//    Di /login peta digambar oleh Leaflet (initMiniMap +
//    updateMiniMap + polygon area kerja). Tanpa itu, tidak ada
//    pemeriksaan "di dalam area" yang memicu startCamera().
//
// 2) Flow lama memanggil startCamera() langsung setelah NPK valid,
//    tanpa menunggu GPS selesai dan tanpa memeriksa area:
//      showStep('stepFace'); requestGeo(); await loadFaceModels();
//      await startCamera();
//    Bila Leaflet/model/GPS gagal, kamera tetap tidak punya konteks
//    apa pun — dan halaman lama juga tidak punya guard multi-
//    candidate untuk model wajah.
//
// 3) Di /login urutannya benar: initFaceLoginFlow() =
//      try initMiniMap() -> loadModels() -> checkGPS()
//    checkGPS() yang menggambar marker + polygon + status badge,
//    dan hanya bila di dalam area (atau tidak ada area terdaftar)
//    baru startCamera() dipanggil. Satu langkah gagal tidak boleh
//    membunuh langkah lain (initMiniMap dilindungi try/catch, dan
//    guard `typeof L === 'undefined'` dipakai untuk Android lama).
//
// ==================================================================
const activeLocations = @json($locations);
const allWindows = @json($windows);

let initialWindowText = '';
let initialWindowAlertClass = '';

document.addEventListener('DOMContentLoaded', () => {
    if ($('activeWindowText')) initialWindowText = $('activeWindowText').innerHTML;
    if ($('activeWindowAlert')) initialWindowAlertClass = $('activeWindowAlert').className;
});

function formatPhotoFaceSrc(photo) {
    if (!photo) return '';
    photo = String(photo).trim();
    if (!photo) return '';
    if (photo.startsWith('data:image/')) return photo;
    return 'data:image/jpeg;base64,' + photo;
}

function createScanAvatarIcon() {
    return L.divIcon({
        className: 'canteen-user-marker',
        html: `
            <div style="
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: #00c08b;
                border: 2.5px solid #ffffff;
                box-shadow: 0 2px 6px rgba(0,0,0,0.35);
                display: flex;
                align-items: center;
                justify-content: center;
                transform: translate(-50%, -50%);
            ">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="#3a206e" style="display:block;">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </div>
        `,
        iconSize: [0, 0],
        iconAnchor: [0, 0]
    });
}

let miniMap = null;
let userCircle = null;
let userMarker = null;
let polyGroup = null;
let receiptMap = null;
let receiptMapGroup = null;
let receiptScanMarker = null;
let faceFlowStarted = false;
let isGpsValid = false;
let gpsWatchdog = null;

function focusOnUser(mapInstance, lat, lng, accuracy) {
    if (!mapInstance) return;
    const acc = Math.max(Number(accuracy) || 0, 0);
    const el = mapInstance.getContainer && mapInstance.getContainer();
    const w = (el && el.clientWidth) ? el.clientWidth : 320;
    const targetMeters = Math.min(Math.max(acc * 5, 250), 900);
    const mpp = 156543.03392 * Math.cos(lat * Math.PI / 180);
    const zoom = Math.max(13, Math.min(18, Math.round(
        Math.log2(mpp * w / targetMeters)
    )));
    mapInstance.setView([lat, lng], zoom, { animate: true, duration: 0.6 });
}

// ==============================================================
// PERBAIKAN V41 (2026-11-10) — AMBIL RING POLYGON DARI SEMUA BENTUK
// GEOJSON.
//
// MASALAH
// -------
// Kode lama hanya punya:
//     let geo = JSON.parse(loc.polygon_geojson);
//     let coords = geo && geo.geometry ? geo.geometry.coordinates[0] : [];
//
// Itu HANYA benar untuk GeoJSON bentuk "Feature":
//     {"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[lng,lat],...]]}}
//
// Tapi polygon bisa tersimpan dalam bentuk lain (lihat accessor
// getCoordinatesAttribute() di model AttendanceWorkLocation yang sudah
// menangani 4 bentuk):
//     1. Feature          -> {"type":"Feature","geometry":{...}}
//     2. Geometry         -> {"type":"Polygon","coordinates":[[[lng,lat],...]]}
//     3. Array polos      -> [[lng,lat],[lng,lat],...]
//     4. FeatureCollection-> {"type":"FeatureCollection","features":[...]}
//
// Untuk bentuk 2/3/4, kode lama menghasilkan coords = [] sehingga:
//   - polygon TIDAK PERNAH digambar di mini map
//   - pointInPolygon() selalu mengembalikan true (karena polygon kosong
//     -> "dalam area"), sehingga user DI LUAR area tetap lolos!
//
// Ini bug dua arah: polygon hilang dari peta DAN pemeriksaan area salah.
//
// SOLUSI
// ------
// Fungsi ini mengembalikan array [lng, lat] untuk outer ring, atau []
// kalau tidak bisa dibaca. Dipakai oleh 2 tempat:
//   - initMiniMap()      -> menggambar polygon ke peta
//   - checkGPS()         -> menentukan user di dalam/luar area
//
// Keduanya memakai FUNGSI YANG SAMA, jadi gambar di peta dan hasil
// pemeriksaan area PASTI konsisten.
// ==============================================================
function extractPolygonRing(polygonGeojson) {
    if (!polygonGeojson) return [];

    let data = polygonGeojson;
    if (typeof data === 'string') {
        try { data = JSON.parse(data); } catch (e) { return []; }
    }
    if (!data || typeof data !== 'object') return [];

    // Bentuk 3: array polos berisi titik [lng,lat].
    if (Array.isArray(data) && data.length && Array.isArray(data[0]) && typeof data[0][0] !== 'object') {
        return data
            .map(p => [parseFloat(p[0]), parseFloat(p[1])])
            .filter(p => !isNaN(p[0]) && !isNaN(p[1]));
    }

    // Bentuk 4: FeatureCollection -> ambil feature pertama.
    if (data.type === 'FeatureCollection') {
        const first = (data.features || [])[0];
        return first ? extractPolygonRing(first) : [];
    }

    // Bentuk 1: Feature -> turun ke geometry.
    if (data.type === 'Feature' && data.geometry) {
        return extractPolygonRing(data.geometry);
    }

    // Bentuk 2: Geometry Polygon / MultiPolygon.
    let coords = data.coordinates;
    if (!Array.isArray(coords) || coords.length === 0) return [];

    // MultiPolygon: coordinates[[[[lng,lat],...]]] -> ambil polygon pertama.
    if (Array.isArray(coords[0]) && Array.isArray(coords[0][0]) && Array.isArray(coords[0][0][0])) {
        coords = coords[0];
    }
    // Polygon: coordinates[[[lng,lat],...]] -> ambil outer ring.
    if (Array.isArray(coords[0]) && Array.isArray(coords[0][0])) {
        coords = coords[0];
    }
    if (!Array.isArray(coords) || coords.length === 0) return [];

    return coords
        .map(p => [parseFloat(p[0]), parseFloat(p[1])])
        .filter(p => !isNaN(p[0]) && !isNaN(p[1]));
}

function initMiniMap() {
    if (miniMap) return;
    const container = document.getElementById('miniMap');
    if (!container) return;
    if (typeof L === 'undefined') { console.warn('[Canteen] Leaflet tidak termuat, mini map dinonaktifkan.'); return; }

    miniMap = L.map('miniMap', {
        zoomControl: false,
        attributionControl: false
    }).setView([-6.2088, 106.8456], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(miniMap);

    polyGroup = L.featureGroup().addTo(miniMap);

    if (activeLocations && activeLocations.length > 0) {
        activeLocations.forEach(loc => {
            if (!loc.polygon_geojson) return;

            // PERBAIKAN V41: gambar polygon dari RING yang sudah dinormalisasi
            // (extractPolygonRing), bukan dari geoJSON mentah. Dengan begitu
            // SEMUA bentuk GeoJSON tergambar, dan ring yang digambar sama
            // persis dengan ring yang dipakai untuk cek "dalam area".
            const ring = extractPolygonRing(loc.polygon_geojson);
            if (ring.length < 3) {
                console.warn('[Canteen] Polygon area "' + (loc.name || '?') + '" tidak bisa dibaca, dilewati.');
                return;
            }

            try {
                // PERBAIKAN V43 (2026-11-10) — BUG POLYGON RUSAK DI MINIMAP.
                //
                // AKAR MASALAH:
                //   Leaflet expects polygon points as [LAT, LNG].
                //   GeoJSON stores rings as [LNG, LAT].
                //   L.polygon(ring) dengan ring [LNG, LAT] menggambar
                //   bentuk yang OK, TAPI getBounds() melaporkan lat/lng
                //   TERBALIK. Efeknya fitBounds() mengarahkan kamera ke
                //   lat ~85 (clamp Web Mercator) -> peta jadi blok abu-abu
                //   tanpa tile, polygon tidak terlihat, zoom ke 18-19.
                //
                // SOLUSI: pakai L.geoJSON() (pola /face-attendance/map).
                //   Leaflet menafsirkan GeoJSON dengan konvensi [LNG, LAT]
                //   yang benar sehingga bounds akurat dan kamera mengarah
                //   ke area yang benar.
                //
                // CATATAN: ring yang dipakai pointInPolygon() TETAP
                // [LNG, LAT] dan tidak diubah - fungsi ray-casting itu
                // bukan Leaflet, jadi tidak ada masalah konvensi di sana.
                const geoJson = {
                    type: 'Feature',
                    properties: { name: loc.name || 'Area Lokasi Kerja' },
                    geometry: {
                        type: 'Polygon',
                        coordinates: [ring]
                    }
                };

                const poly = window.L.geoJSON(geoJson, {
                    style: {
                        color: '#4e73df',
                        fillColor: '#4e73df',
                        fillOpacity: 0.3,
                        weight: 2
                    }
                }).addTo(miniMap);
                poly.bindPopup('<b>' + (loc.name || 'Area Lokasi Kerja') + '</b><br>Area Presensi Resmi');
                polyGroup.addLayer(poly);
            } catch(e) {
                console.warn('[Canteen] Gagal menggambar polygon:', e);
            }
        });

        // PERBAIKAN V41: agar polygon PASTI terlihat, bukan cuma ada di
        // layar kalau user zoom manual. Kalau ada polygon, kamera peta
        // diarahkan ke bounds polygon SEBELUM posisi user diketahui.
        // Nanti updateMiniMap() akan refocus ke user (dan polygon tetap
        // terlihat di sekitar lokasi user karena dia ada di area itu).
        try {
            if (polyGroup.getLayers().length > 0) {
                miniMap.fitBounds(polyGroup.getBounds(), { padding: [18, 18] });
            }
        } catch (e) {
            console.warn('[Canteen] Gagal memfokuskan peta ke polygon:', e);
        }
    }
}

function updateMiniMap(lat, lng, accuracy, inside) {
    initMiniMap();
    if (!miniMap) return;

    if (userCircle) miniMap.removeLayer(userCircle);
    if (userMarker) miniMap.removeLayer(userMarker);

    let color = inside ? '#1cc88a' : '#e74a3b';

    userCircle = L.circle([lat, lng], {
        radius: Math.max(accuracy, 15),
        color: color,
        fillColor: color,
        fillOpacity: 0.2,
        weight: 1
    }).addTo(miniMap);

    userMarker = L.circleMarker([lat, lng], {
        radius: 6,
        color: '#ffffff',
        fillColor: color,
        fillOpacity: 1,
        weight: 2
    }).addTo(miniMap).bindPopup('<b>Posisi Anda</b><br>' + (inside ? '<span class="text-success">Di Dalam Area</span>' : '<span class="text-danger">Di Luar Area</span>')).openPopup();

    focusOnUser(miniMap, lat, lng, accuracy);

    const statusBadge = document.getElementById('miniMapStatus');
    const accuracyText = document.getElementById('miniMapAccuracyText');

    if (statusBadge) {
        if (inside) {
            statusBadge.className = 'badge badge-success small';
            statusBadge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Dalam Area';
        } else {
            statusBadge.className = 'badge badge-danger small';
            statusBadge.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Luar Area';
        }
    }
    if (accuracyText) {
        accuracyText.innerHTML = 'Akurasi GPS: <strong>±' + accuracy + '</strong> meter';
    }
}

// Ray-casting polygon intersection (sama persis dengan /login)
function pointInPolygon(lat, lng, polygon) {
    if (!polygon || !polygon.length) return true;
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        let p_lng_i = polygon[i][0], p_lat_i = polygon[i][1];
        let p_lng_j = polygon[j][0], p_lat_j = polygon[j][1];
        let intersect = ((p_lat_i > lat) !== (p_lat_j > lat)) &&
            (lng < (p_lng_j - p_lng_i) * (lat - p_lat_i) / ((p_lat_j - p_lat_i) || 0.0000001) + p_lng_i);
        if (intersect) inside = !inside;
    }
    return inside;
}

function armGpsWatchdog() {
    clearTimeout(gpsWatchdog);
    gpsWatchdog = setTimeout(function () {
        if (geoCoords.latitude === null && !isGpsValid) {
            const ph = document.getElementById('cameraPlaceholder');
            const pt = document.getElementById('placeholderTitle');
            const pd = document.getElementById('placeholderDesc');
            if (ph) { ph.classList.remove('d-none'); ph.style.setProperty('display', 'flex', 'important'); }
            if (pt) pt.innerText = 'GPS Belum Merespons';
            if (pd) pd.innerText = 'Browser Android lama kadang menggantung izin lokasi. Aktifkan Location, beri izin "Precise", lalu klik Cek Ulang GPS.';
        }
    }, 12000);
}

function showGpsWarning(title = 'Di Luar Area Presensi', desc = 'Anda berada di luar batas polygon area kerja resmi.') {
    const ph = document.getElementById('cameraPlaceholder');
    const phTitle = document.getElementById('placeholderTitle');
    const phDesc = document.getElementById('placeholderDesc');
    if (ph) { ph.classList.remove('d-none'); ph.style.setProperty('display', 'flex', 'important'); }
    if (phTitle) phTitle.innerText = title;
    if (phDesc) phDesc.innerText = desc;

    const video = document.getElementById('webcam');
    if (video && video.srcObject) {
        let tracks = video.srcObject.getTracks();
        tracks.forEach(track => track.stop());
        video.srcObject = null;
        cameraStream = null;
    }
}

// GPS: sama seperti checkGPS() di /login — menggambar peta + badge,
// dan hanya startCamera() bila di dalam area (atau tidak ada area
// terdaftar). Kamera tidak boleh menyala di luar area kerja.
window.checkGPS = function checkGPS() {
    try { initMiniMap(); } catch (e) { console.warn('[Canteen] initMiniMap gagal:', e); }

    if (!navigator.geolocation) {
        showGpsWarning('GPS Tidak Didukung', 'Browser ini tidak mendukung geolokasi. Gunakan Chrome / Edge terbaru via HTTPS.');
        return;
    }

    armGpsWatchdog();

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            clearTimeout(gpsWatchdog);
            const accuracy = Math.round(pos.coords.accuracy);

            geoCoords.latitude = pos.coords.latitude;
            geoCoords.longitude = pos.coords.longitude;
            geoCoords.accuracy = accuracy;
            $('geoText').textContent = geoCoords.latitude.toFixed(6) + ', ' + geoCoords.longitude.toFixed(6) + ' (akurasi ±' + accuracy + 'm)';

            // PERBAIKAN V37 (2026-11-09): badge GPS menampilkan status
            // AKTUAL (koordinat + akurasi), bukan hanya "Wajib/Opsional".
            // Badge Liveness & Tantangan sudah disembunyikan, jadi GPS
            // jadi penanda utama bahwa area sudah terverifikasi.
            const gpsBadge = $('gpsBadge');
            if (gpsBadge) {
                gpsBadge.textContent = 'GPS: ' + geoCoords.latitude.toFixed(5) + ', ' + geoCoords.longitude.toFixed(5) + ' (±' + accuracy + 'm)';
                gpsBadge.classList.remove('badge-secondary', 'badge-danger');
                gpsBadge.classList.add('badge-success');
                gpsBadge.title = 'Koordinat GPS berhasil dibaca';
            }

            let inside = false;
            if (activeLocations && activeLocations.length > 0) {
                for (let loc of activeLocations) {
                    // PERBAIKAN V41 (2026-11-10): pakai extractPolygonRing()
                    // yang menangani SEMUA bentuk GeoJSON, bukan cuma
                    // Feature. Lihat komentar fungsi tersebut.
                    let coords = extractPolygonRing(loc.polygon_geojson);
                    if (coords.length >= 3 && pointInPolygon(geoCoords.latitude, geoCoords.longitude, coords)) {
                        inside = true;
                        break;
                    }
                }
            } else {
                inside = true;
            }

            updateMiniMap(geoCoords.latitude, geoCoords.longitude, accuracy, inside);

            if (inside || !activeLocations || activeLocations.length === 0) {
                isGpsValid = true;
                startCamera();
            } else {
                isGpsValid = false;
                const gpsBadge = $('gpsBadge');
                if (gpsBadge) {
                    gpsBadge.textContent = 'GPS: Di luar Area Kerja';
                    gpsBadge.classList.remove('badge-secondary', 'badge-success');
                    gpsBadge.classList.add('badge-danger');
                    gpsBadge.title = 'Koordinat berada di luar area kerja yang diizinkan';
                }
                showGpsWarning();
            }
        },
        () => {
            $('geoText').textContent = 'izin lokasi ditolak / tidak tersedia';
            const gpsBadge = $('gpsBadge');
            if (gpsBadge) {
                gpsBadge.textContent = 'GPS: Tidak Tersedia';
                gpsBadge.classList.remove('badge-secondary', 'badge-success');
                gpsBadge.classList.add('badge-danger');
                gpsBadge.title = 'Izin lokasi ditolak atau GPS tidak tersedia';
            }
            showGpsWarning('Izin GPS Ditolak', 'Harap berikan izin akses lokasi pada browser Anda.');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
};

// Kompatibilitas: nama lama requestGeo() tetap ada dan memanggil checkGPS()
function requestGeo(){ return checkGPS(); }

// Kamera — MIRROR /login (guard getUserMedia, timeout izin 20 detik,
// constraint "ideal" + fallback berurutan)
async function loadFaceModels(){
    if(typeof faceapi === 'undefined'){
        console.error('[Canteen] faceapi tidak termuat.');
        setStatus('Library wajah (face-api) gagal dimuat. Muat ulang halaman.');
        return;
    }
    const loadFrom = async (url) => {
        if (faceapi.nets.tinyFaceDetector && !faceapi.nets.tinyFaceDetector.isLoaded) {
            await faceapi.nets.tinyFaceDetector.loadFromUri(url);
        }
        if (faceapi.nets.faceLandmark68TinyNet && !faceapi.nets.faceLandmark68TinyNet.isLoaded) {
            await faceapi.nets.faceLandmark68TinyNet.loadFromUri(url);
        }
        if (faceapi.nets.faceRecognitionNet && !faceapi.nets.faceRecognitionNet.isLoaded) {
            await faceapi.nets.faceRecognitionNet.loadFromUri(url);
        }
    };
    const candidates = [
        "{{ asset('vendor-face-api/model') }}",
        "{{ asset('models') }}",
        "/vendor-face-api/model",
        "/models",
        "vendor-face-api/model",
        "models"
    ];
    for (let url of candidates) {
        try {
            await loadFrom(url);
            faceLoaded = true;
            // PERBAIKAN V37 (2026-11-09): pesan lebih informatif. Kata
            // "lingkaran" membuat user merasa STUCK, padahal yang terjadi
            // cuma model wajah sudah siap dan kamera menunggu wajah.
            setStatus('<i class="fas fa-check-circle text-success mr-1"></i> Kamera siap. Posisikan wajah di tengah area kamera.');
            return;
        } catch (err) {
            // abaikan, coba kandidat berikutnya
        }
    }
    setStatus('Model wajah belum termuat. Muat ulang halaman.');
}

async function startCamera(){
    const video = $('webcam');
    const ph = $('cameraPlaceholder'), phTitle = $('placeholderTitle'), phDesc = $('placeholderDesc');
    if (video && video.srcObject && video.srcObject.active) return;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        console.error('[Canteen] getUserMedia tidak didukung.');
        if (ph) { ph.classList.remove('d-none'); ph.style.setProperty('display','flex','important'); }
        if (phTitle) phTitle.innerText = 'Kamera Tidak Didukung Browser';
        if (phDesc) phDesc.innerText = 'Browser ini tidak mendukung akses kamera. Kamera HANYA bisa aktif lewat HTTPS atau localhost. Gunakan Chrome / Edge terbaru dan pastikan halaman diakses via HTTPS.';
        return;
    }
    let camTimeout = null;
    try {
        cameraStream = await new Promise((resolve, reject) => {
            camTimeout = setTimeout(() => { const e = new Error('Izin kamera belum diberikan dalam 20 detik.'); e.name = 'IZIN_KAMERA_TIMEOUT'; reject(e); }, 20000);
            const tryConstraints = (constraints, fallback) => {
                navigator.mediaDevices.getUserMedia(constraints).then(resolve, (err) => {
                    if (fallback && fallback.length) { tryConstraints(fallback[0], fallback.slice(1)); } else { reject(err); }
                });
            };
            tryConstraints(
                { video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }, audio: false },
                [ { video: { facingMode: 'user' }, audio: false }, { video: true, audio: false } ]
            );
        });
        clearTimeout(camTimeout);
        video.srcObject = cameraStream;
        try { await video.play(); } catch(e) { if (e.name !== 'AbortError') console.warn('Video play warning:', e); }
        if (ph) { ph.classList.add('d-none'); ph.style.setProperty('display','none','important'); }
        startDetectLoop();
        // PERBAIKAN V32 (2026-11-09): tombol Nyalakan/Matikan Kamera dihapus.
        // Kamera hidup otomatis setelah GPS terverifikasi.
        // PERBAIKAN V37 (2026-11-09): pesan tidak lagi menyebut "lingkaran".
        setStatus('<i class="fas fa-camera mr-1"></i> Kamera aktif. Posisikan wajah di tengah area kamera, transaksi diproses otomatis saat wajah terdeteksi.');
    } catch(err){
        clearTimeout(camTimeout);
        console.error('Camera error:', err);
        if (ph) { ph.classList.remove('d-none'); ph.style.setProperty('display','flex','important'); }
        var name = (err && err.name) ? err.name : '';
        if (phTitle && phDesc) {
            if (name === 'IZIN_KAMERA_TIMEOUT') { phTitle.innerText = 'Menunggu Izin Kamera'; phDesc.innerText = 'Izin kamera belum diberikan. Klik ikon kamera di address bar, pilih "Izinkan", lalu coba lagi.'; }
            else if (name === 'NotAllowedError' || name === 'SecurityError') { phTitle.innerText = 'Izin Kamera Ditolak'; phDesc.innerText = 'Klik ikon kamera di address bar browser, pilih "Izinkan", lalu coba lagi.'; }
            else if (name === 'NotFoundError' || name === 'OverconstrainedError') { phTitle.innerText = 'Kamera Tidak Ditemukan'; phDesc.innerText = 'Tidak ada kamera yang terdeteksi pada perangkat ini.'; }
            else if (name === 'NotReadableError') { phTitle.innerText = 'Kamera Sedang Dipakai Aplikasi Lain'; phDesc.innerText = 'Tutup aplikasi lain yang memakai kamera, lalu coba lagi.'; }
            else { phTitle.innerText = 'Kamera Gagal Dinyalakan'; phDesc.innerText = 'Terjadi kendala: ' + (name || String(err)) + '. Pastikan halaman diakses melalui HTTPS dan izin kamera aktif.'; }
        }
        setStatus('Kamera tidak dapat diakses. Beri izin kamera di browser.');
    }
}

// Orchestrator sama seperti initFaceLoginFlow() di /login:
// satu langkah gagal TIDAK boleh membunuh langkah lain.
function initFaceCanteenFlow() {
    if (faceFlowStarted) return;
    faceFlowStarted = true;
    try { initMiniMap(); } catch (e) { console.warn('[Canteen] initMiniMap:', e); }
    Promise.resolve().then(loadFaceModels).catch(function (e) {
        console.error('[Canteen] loadFaceModels:', e);
        faceFlowStarted = false;
    });
    try { checkGPS(); } catch (e) {
        console.error('[Canteen] checkGPS:', e);
        faceFlowStarted = false;
    }
}

function stopCamera(){
  if (detectInterval) {
    clearInterval(detectInterval);
    detectInterval = null;
  }
  if(cameraStream){
    try {
      cameraStream.getTracks().forEach(t => {
        t.stop();
        t.enabled = false;
      });
    } catch(e){}
    cameraStream = null;
  }
  const video = $('webcam');
  if (video) {
    try { video.pause(); } catch(e){}
    video.srcObject = null;
  }
  const ph2 = $('cameraPlaceholder');
  if (ph2) {
    ph2.classList.remove('d-none');
    ph2.style.setProperty('display','flex','important');
  }
  autoPostInFlight = false;
  isPosting = false;
  scanReady = false;
  stableFrames = 0;
}

// ==============================================================
// PERBAIKAN V32 (2026-11-09) — LOOP DETEKSI + GERBANG LIVENESS/
// RANDOM CHALLENGE
// ==============================================================
// PERUBAHAN dari versi lama:
//   SEBELUMNYA: hanya menghitung "stableFrames >= 3" lalu langsung
//               mengizinkan scan. Itu BUKAN liveness — hanya deteksi
//               wajah yang stabil, jadi foto statis pun bisa lolos.
//   SEKARANG  : dua gerbang sungguhan, urutannya:
//                 1. Random challenge (kalau aktif) — harus lolos dulu
//                 2. Liveness (kalau aktif) — kedip / menoleh
//               Scan baru boleh dilakukan kalau KEDUA gerbang (yang
//               aktif) sudah terpenuhi.
//
// Toleransi frame tanpa wajah: 12 frame (~1,8 detik) supaya wajah
// yang sesaat keluar kotak deteksi tidak menghapus progres.
// ==============================================================
const DETECT_MISS_TOLERANCE = 12;
let detectMissCount = 0;
let stableFrames = 0;
let scanReady = false;

function setChallengeInstruction(text){
  const el = $('challengeInstructionText');
  if (el && text) el.textContent = text;
}
function setLivenessInstruction(text){
  const el = $('livenessInstructionText');
  if (el && text) el.textContent = text;
}

// ==============================================================
// PERBAIKAN V41 (2026-11-10) — STATE VISUAL BANNER CHALLENGE.
//
// BUG: banner merah sudah tertulis "Tantangan terverifikasi" padahal
// kamera masih disiapkan. Akar masalahnya banner ditulis 3 tempat
// (Blade, random-challenge.js, loop deteksi) tanpa satu sumber
// kebenaran, sehingga state visual bisa menyatakan "terverifikasi"
// padahal verifikasi belum jalan.
//
// SOLUSI: satu fungsi ini adalah SATU-SATUNNYA tempat yang mengubah
// tampilan banner. Semua pemanggil (loop deteksi, reset, timeout)
// wajib lewat sini.
//
// STATE:
//   idle    -> kamera belum siap / menunggu verifikasi dimulai.
//              Teks: "Menyiapkan verifikasi wajah..." (abu-abu)
//   pending -> verifikasi berjalan, user harus melakukan gerakan.
//              Teks: instruksi gerakan (kuning/oranye)
//   passed  -> challenge benar-benar terverifikasi.
//              Teks: "Tantangan terverifikasi" (hijau)
//   error   -> verifikasi gagal / timeout.
//              Teks: pesan error (merah)
//
// PENTING: state 'passed' HANYA boleh dipakai kalau randomChallengeVerified
// benar-benar true. Jangan pernah menulis "Tantangan terverifikasi"
// hanya karena banner perlu di-update.
// ==============================================================
const CHALLENGE_BANNER_STATES = {
  idle:    { cls: 'alert-secondary', border: 'border-secondary', icon: 'fa-random',    iconCls: 'text-secondary', text: 'Menyiapkan verifikasi wajah...' },
  pending: { cls: 'alert-warning',   border: 'border-warning',   icon: 'fa-random',    iconCls: 'text-warning',   text: 'Ikuti tantangan yang diminta' },
  passed:  { cls: 'alert-success',   border: 'border-success',   icon: 'fa-check-circle', iconCls: 'text-success', text: 'Tantangan terverifikasi' },
  error:   { cls: 'alert-danger',    border: 'border-danger',    icon: 'fa-exclamation-triangle', iconCls: 'text-danger', text: 'Verifikasi gagal. Silakan coba lagi.' },
};

function setChallengeBannerState(state, text){
  const box = $('challengeAlertBox');
  const el  = $('challengeInstructionText');
  const ico = $('challengeAlertIcon');
  if (!box || !el) return;

  const conf = CHALLENGE_BANNER_STATES[state] || CHALLENGE_BANNER_STATES.idle;

  // Warnai ulang class alert (hapus semua varian dulu supaya tidak
  // ada class warna lama yang masih nempel).
  ['alert-secondary','alert-warning','alert-success','alert-danger',
   'border-secondary','border-warning','border-success','border-danger']
    .forEach(c => box.classList.remove(c));
  box.classList.add(conf.cls, conf.border);

  if (ico) {
    ico.className = 'fas ' + conf.icon + ' mr-2 ' + conf.iconCls;
  }

  el.textContent = (text !== undefined && text !== null && text !== '')
    ? text
    : conf.text;
}

// ==============================================================
// PERBAIKAN V43 (2026-11-10) — SPLASH HIJAU MENGIKUTI POLA FACE ATTENDANCE.
//
// Perubahan dari V41: overlay lama (blok hijau opak penuh) DIUBAH menjadi:
//   - #faceVerifiedFlash  : tint tipis + border hijau (kamera tetap terlihat)
//   - #faceVerifiedBadge  : pill hijau di kiri-atas kamera
//   - #faceVerifiedMsg    : teks status di bawah panel kamera
//   - #scannerGuide       : lingkaran scanner ikut hijau (liveness-ok)
//
// PENTING: fungsi ini HANYA boleh dipanggil setelah server mengonfirmasi
// wajah dikenali + transaksi tersimpan (res.ok && data.success).
// Memanggilnya saat challenge/liveness lolos akan memberi tanda "berhasil"
// yang palsu.
const FACE_FLASH_DURATION_MS = 1600;
let faceVerifiedFlashTimer = null;

function showFaceVerifiedFlash(message){
  const el = $('faceVerifiedFlash');
  const badge = $('faceVerifiedBadge');
  const msgWrap = $('faceVerifiedMsg');
  const msg = $('faceVerifiedFlashMsg');
  const guide = $('scannerGuide');
  if (!el) return;

  if (msg && message) {
    msg.textContent = message;
  }

  // Tampilkan overlay tint + badge + teks + lingkaran hijau.
  el.classList.remove('d-none');
  if (badge) {
    badge.classList.remove('d-none');
    badge.classList.add('d-flex');
  }
  if (msgWrap) msgWrap.classList.remove('d-none');
  if (guide) guide.classList.add('liveness-ok');

  // Bersihkan timer sebelumnya supaya tidak ada dua timer bertumpuk
  // yang bisa menyembunyikan overlay terlalu cepat.
  if (faceVerifiedFlashTimer) {
    clearTimeout(faceVerifiedFlashTimer);
  }
  faceVerifiedFlashTimer = setTimeout(function(){
    hideFaceVerifiedFlash();
  }, FACE_FLASH_DURATION_MS);
}

function hideFaceVerifiedFlash(){
  const el = $('faceVerifiedFlash');
  const badge = $('faceVerifiedBadge');
  const msgWrap = $('faceVerifiedMsg');
  const guide = $('scannerGuide');
  if (faceVerifiedFlashTimer) {
    clearTimeout(faceVerifiedFlashTimer);
    faceVerifiedFlashTimer = null;
  }
  if (el) el.classList.add('d-none');
  if (badge) { badge.classList.add('d-none'); badge.classList.remove('d-flex'); }
  if (msgWrap) msgWrap.classList.add('d-none');
  if (guide) guide.classList.remove('liveness-ok');
}

// PERBAIKAN LAYOUT (2026-11-10): flash snapshot wajah yang
// ditampilkan sesaat setelah wajah dikenali (sebelum pindah ke
// step receipt). Berbeda dari showFaceVerifiedFlash yang adalah
// overlay hijau di area kamera, ini menampilkan gambar wajah
// di kotak kamera sebentar untuk konfirmasi visual user.
// Kamera sudah dimatikan oleh pemanggil (stopCamera).
const FACE_SNAPSHOT_DURATION_MS = 1200;
let faceSnapshotFlashTimer = null;

function showFaceSnapshotFlash(dataUrl){
  // Tampilkan snapshot wajah di DALAM kotak kamera saja (bukan fullscreen)
  let overlay = document.getElementById('faceSnapshotFlash');
  const img = document.getElementById('faceSnapshotImg');
  if (img && dataUrl) img.src = dataUrl;
  if (overlay) {
    overlay.classList.remove('d-none');
    overlay.style.setProperty('display', 'flex', 'important');
  }

  // Bersihkan timer sebelumnya
  if (faceSnapshotFlashTimer) {
    clearTimeout(faceSnapshotFlashTimer);
  }
  faceSnapshotFlashTimer = setTimeout(function(){
    hideFaceSnapshotFlash();
  }, FACE_SNAPSHOT_DURATION_MS);
}

function hideFaceSnapshotFlash(){
  const overlay = document.getElementById('faceSnapshotFlash');
  if (faceSnapshotFlashTimer) {
    clearTimeout(faceSnapshotFlashTimer);
    faceSnapshotFlashTimer = null;
  }
  if (overlay) {
    overlay.classList.add('d-none');
    overlay.style.removeProperty('display');
  }
}

// ==============================================================
// PERBAIKAN V32 (2026-11-09): tombol Scan & Simpan dihapus, jadi tidak ada
// lagi tombol yang perlu di-enable/disable. Yang dipantau hanyalah:
//   - apakah semua gerbang sudah terpenuhi (scanReady)
//   - apakah sedang ada proses post yang berjalan (isPosting)
function refreshGateState(){
  const challengeOk = !RANDOM_CHALLENGE_REQUIRED || randomChallengeVerified;
  const livenessOk = !LIVENESS_REQUIRED || livenessVerified;
  scanReady = challengeOk && livenessOk;
}

let detectInterval = null;

function startDetectLoop(){
  if (detectInterval) clearInterval(detectInterval);
  detectInterval = setInterval(async ()=>{
  if(!cameraStream || !faceLoaded || typeof faceapi === 'undefined' || isPosting || autoPostInFlight) return;
  const video = $('webcam');
  if(!video || video.readyState < 2) return;
  try {
    const det = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks(true);

    if(det){
      detectMissCount = 0;
      stableFrames++;

      // ---- FAIL-SAFE (V38) ----
      // Kalau modul gerbang belum siap (script gagal dimuat), jangan diam.
      // Tanpa ini user cuma melihat "Transaksi diproses otomatis saat wajah
      // terdeteksi." padahal tidak akan pernah ada transaksi.
      if (!faceGatesReady && (RANDOM_CHALLENGE_REQUIRED || LIVENESS_REQUIRED)) {
        const msg = RANDOM_CHALLENGE_REQUIRED
          ? 'Modul Random Challenge gagal dimuat. Muat ulang halaman (Ctrl+F5).'
          : 'Modul Liveness gagal dimuat. Muat ulang halaman (Ctrl+F5).';
        setStatus('<i class="fas fa-exclamation-triangle text-danger mr-1"></i> ' + msg);
        return;
      }

      // ---- GERBANG: MODE CANTEEN (salah satu, bukan keduanya) ----
      // liveness ATAU random_challenge ATAU none (dipilih admin).
      if (RANDOM_CHALLENGE_REQUIRED && !randomChallengeVerified && randomChallenge) {
        const hasilChallenge = randomChallenge.update(det.landmarks);
        if (hasilChallenge.passed) {
          randomChallengeVerified = true;
          // PERBAIKAN V41 (2026-11-10): pakai setChallengeBannerState
          // supaya teks + warna banner pindah ke 'passed' (hijau) dalam
          // satu operasi. setChallengeInstruction yang lama hanya ganti
          // teks tanpa ganti warna — itu asal-usul banner merah yang
          // menulis "terverifikasi" padahal belum.
          setChallengeBannerState('passed', 'Tantangan terverifikasi');
          const cBadge = $('challengeBadge');
          if (cBadge) { cBadge.textContent = 'Tantangan: OK'; cBadge.classList.remove('badge-warning'); cBadge.classList.add('badge-success'); }
          setStatus('<i class="fas fa-check-circle text-success mr-1"></i> Tantangan terverifikasi. Memproses transaksi otomatis...');
        } else {
          // PERBAIKAN V41: state 'pending' (kuning) selama user belum
          // memenuhi tantangan. Teks instruksi GERAKAN yang ditampilkan.
          setChallengeBannerState('pending', hasilChallenge.hint || 'Ikuti tantangan yang diminta');
          setStatus('<i class="fas fa-random text-warning mr-1"></i> ' + (hasilChallenge.hint || 'Ikuti tantangan yang diminta'));
        }
      }

      if (LIVENESS_REQUIRED && !livenessVerified && liveness) {
        const hasilLiveness = liveness.update(det.landmarks);
        if (hasilLiveness.passed) {
          if (hasilLiveness.blink) {
            setLivenessInstruction('Kedip terdeteksi');
          } else if (hasilLiveness.turn) {
            setLivenessInstruction('Menoleh terdeteksi');
          }
          livenessVerified = true;
          const guide = $('scannerGuide');
          if (guide) guide.classList.add('liveness-ok');
          const lBadge = $('livenessBadge');
          if (lBadge) { lBadge.textContent = 'Liveness: OK'; lBadge.classList.remove('badge-info'); lBadge.classList.add('badge-success'); }
          setStatus('<i class="fas fa-check-circle text-success mr-1"></i> Wajah terverifikasi. Memproses transaksi otomatis...');
        } else {
          setLivenessInstruction(hasilLiveness.hint || 'Silakan Berkedip atau Menoleh Sedikit');
          setStatus('<i class="fas fa-eye text-warning mr-1"></i> ' + (hasilLiveness.hint || 'Silakan Berkedip atau Menoleh Sedikit'));
        }
      }

      // Mode none: cukup wajah terdeteksi stabil.
      if (!LIVENESS_REQUIRED && !RANDOM_CHALLENGE_REQUIRED) {
        const guide = $('scannerGuide');
        if (guide) guide.classList.add('liveness-ok');
        if (stableFrames >= 3) {
          setStatus('Wajah terdeteksi stabil. Memproses transaksi otomatis...');
        }
      }

      refreshGateState();

      // PERBAIKAN V32 (2026-11-09): AUTO-POST. Begitu wajah terdeteksi
      // dan semua gerbang (challenge + liveness) sudah terpenuhi,
      // transaksi langsung diposting. Tidak ada tombol manual lagi.
      //
      // Syarat auto-post:
      //   - tidak sedang ada proses post sebelumnya
      //   - tidak ada liveness/challenge yang masih tertunda
      //   - minimal 3 frame wajah stabil (lama2, biar descriptor stabil)
      // PERBAIKAN V39 (2026-11-10): autoPostInFlight TIDAK lagi di-set di sini.
      //
      // BUG V32-V38: pemanggil men-set `autoPostInFlight = true` SEBELUM
      // memanggil runAutoScan(), sementara runAutoScan() sendiri punya guard
      //   if (isPosting || autoPostInFlight) return;
      // Akibatnya runAutoScan() SELALU early-return: tidak ada fetch, tidak
      // ada error, tidak ada exception. User hanya melihat "Tantangan
      // terverifikasi. Memproses transaksi otomatis..." lalu halaman diam.
      // Dan karena flag tidak pernah di-reset (hanya di stopCamera() /
      // resetFaceGates()), auto-post tidak pernah mencoba lagi.
      //
      // FIX: flag sekarang di-set DI DALAM runAutoScan() (setelah semua
      // guard lolos), jadi guard di dalam fungsi tidak lagi bisa-menahan
      // dirinya sendiri. Pemanggil cukup-andalkan penjaga dari loop deteksi
      // yang sudah mengecek !isPosting && !autoPostInFlight.
      if (!isPosting && !autoPostInFlight && scanReady && stableFrames >= 3) {
        stableFrames = 0;
        runAutoScan();
      }
    } else {
      // Wajah tidak terdeteksi. Toleransi supaya satu/dua frame hilang
      // tidak menghapus progres yang sudah jalan.
      detectMissCount++;
      if (detectMissCount > DETECT_MISS_TOLERANCE) {
        stableFrames = 0;
        const guide = $('scannerGuide');
        if (guide) guide.classList.remove('liveness-ok');
        if (!livenessVerified) setStatus('Wajah tidak terdeteksi. Posisikan wajah di tengah kotak.');
      }
    }
  } catch(e){}
  }, 600);
}

// ==============================================================
// PERBAIKAN V32 (2026-11-09) — SCAN OTOMATIS
// ==============================================================
// SEBELUMNYA: user menekan tombol "Scan & Simpan" untuk mengirim.
// SEKARANG  : begitu wajah terdeteksi (dan semua gerbang liveness /
//             random challenge sudah terpenuhi), transaksi LANGSUNG
//             diposting. Tombol "Scan & Simpan" dan "Nyalakan/Matikan
//             Kamera" sudah dihapus dari UI.
//
// Proteksi double-post berlapis:
//   1. Sisi client  -> flag isPosting mencegah dua request bersamaan.
//   2. Sisi server  -> cek duplikat di tabel canteen / canteen_twos
//                      (npk + tanggal + window_name). Duplikat DITOLAK
//                      dan user diberi SweetAlert + tombol "Lihat Bukti".
// ==============================================================

// Ambil snapshot wajah sebagai data URI base64 (JPEG) untuk disimpan
// sebagai bukti pada "Bukti Scan Kantin".
function captureFacePhoto(){
  try {
    const video = $('webcam');
    if (!video || video.readyState < 2) return null;

    // Canvas sekecil mungkin supaya payload tidak terlalu besar.
    const maxW = 320;
    const w = video.videoWidth || 640;
    const h = video.videoHeight || 480;
    const scale = Math.min(1, maxW / w);
    const cw = Math.max(1, Math.round(w * scale));
    const ch = Math.max(1, Math.round(h * scale));

    const canvas = document.createElement('canvas');
    canvas.width = cw;
    canvas.height = ch;
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;

    // Preview kamera di halaman ini memakai scaleX(-1), jadi gambar
    // disalin apa adanya supaya bukti sesuai yang dilihat user.
    ctx.drawImage(video, 0, 0, cw, ch);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
    if (!dataUrl || dataUrl.indexOf('data:image/jpeg') !== 0) return null;
    return dataUrl;
  } catch(e){
    return null;
  }
}

// Proses scan + post. Dipanggil otomatis dari loop deteksi.
async function runAutoScan(){
  // PERBAIKAN V39 (2026-11-10): guard TIDAK boleh mengecek autoPostInFlight.
  //
  // Flag autoPostInFlight adalah "sudah ada yang menembakkan auto-post untuk
  // frame ini". Di versi lama, pemanggil menyetel flag ini SEBELUM memanggil
  // fungsi ini, sehingga guard di baris bawah selalu bernilai true dan
  // fungsi keluar SEBELUM melakukan fetch apa pun. Gejala di produksi:
  //   "Tantangan terverifikasi. Memproses transaksi otomatis..."
  // lalu tidak ada request ke server sama sekali.
  //
  // Verifikasi yang dipakai: guard di sini hanya boleh melihat flag yang
  // HANYA di-set oleh fungsi ini sendiri (isPosting), bukan flag milik
  // pemanggil. Flag autoPostInFlight baru di-set setelah semua guard lolos.
  if (isPosting) return;
  if (!cameraStream) return;

  // Gerbang WAJIB dicek ulang di sini, bukan hanya di loop deteksi.
  // Fail-closed: kalau belum lolos, jangan post.
  if (RANDOM_CHALLENGE_REQUIRED && !randomChallengeVerified) return;
  if (LIVENESS_REQUIRED && !livenessVerified) return;

  // PERBAIKAN V39 (2026-11-10): fail-closed juga untuk data form.
  // Tanpa guard ini, auto-post tetap menembak ke server aunque NPK atau
  // kantin belum dipilih, dan server membalas 422 "Pilih Kantin 1 atau
  // Kantin 2 terlebih dahulu" -- user tidak pernah melihat Transactions
  // diproses otomatis, hanya pesan gagal yang membingungkan.
  if (!selectedCanteenId) {
    setError('Pilih kantin terlebih dahulu sebelum scan wajah.');
    setStatus('Kantin belum dipilih. Silakan pilih kantin lalu scan lagi.');
    return;
  }
  if (NPK_REQUIRED && !validatedNpk) {
    setError('NPK wajib diisi dan divalidasi sebelum scan wajah.');
    setStatus('NPK belum divalidasi. Silakan isi NPK lalu scan lagi.');
    return;
  }

  // PERBAIKAN V39 (2026-11-10): penanda "sedang memproses" di-set DI DALAM
  // try, bukan sebelumnya. Kalau ada exception di antara set flag dan
  // masuknya blok try, flag akan tertinggal true selamanya dan auto-post
  // tidak akan pernah mencoba lagi (gejala "stuck" yang sama).
  try {
    isPosting = true;
    autoPostInFlight = true;

    setError(null);
    setStatus('Mendeteksi wajah &amp; membuat descriptor…');

    const video = $('webcam');
    const det = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
      .withFaceLandmarks(true).withFaceDescriptor();

    if(!det){
      setStatus('Wajah tidak terdeteksi. Posisi wajah lebih terang &amp; dekat ke kamera.');
      return;
    }

    const descriptorArray = Array.from(det.descriptor);
    const photoFace = captureFacePhoto();

    // PERBAIKAN: Snapshot flash di kotak kamera dan matikan kamera LANGSUNG setelah wajah tercapture!
    if (photoFace) {
      showFaceSnapshotFlash(photoFace);
    }
    stopCamera();

    const payload = {
      descriptor: descriptorArray,
      npk: validatedNpk,
      canteen_id: selectedCanteenId,
      latitude: geoCoords.latitude,
      longitude: geoCoords.longitude,
      photo_face: photoFace,
    };

    // PERBAIKAN V39 (2026-11-10): fetch diberi timeout.
    //
    // Tanpa batas waktu, request ke server yang tidak merespons (mis.
    // SQL Server timeout) menggantung selamanya: UI tetap menampilkan
    // "Mendeteksi wajah & membuat descriptor..." dan tidak pernah maju
    // ke step receipt maupun menampilkan error. AbortController membuat
    // kegagalan terlihat dan bisa diulang.
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), autoPostTimeoutMs);

    let res, data;
    try {
      res = await fetch(@json(route('canteen-transaction.store')), {
        method:'POST',
        headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrf, 'Accept':'application/json' },
        body: JSON.stringify(payload),
        signal: controller.signal
      });
      data = await res.json().catch(()=>({}));
    } finally {
      clearTimeout(timeoutId);
    }

    if(!res.ok || !data.success){
      // ---- DUPLIKAT: sudah pernah scan di kantin 1 / kantin 2 ----
      if (data.duplicate) {
        stopCamera();
        showDuplicateAlert(data);
        return;
      }
      if (data.duplicate_check_failed) {
        // PERBAIKAN V40 (2026-11-10): server sudah membedakan reason
        // 'schema_or_db' (tabel/database kantin belum siap) dari error
        // lain. Pesan server yang lebih spesifik ditampilkan apa adanya
        // di kotak error; baris status memakai ringkasan yang sesuai.
        const isSchemaProblem = data.reason === 'schema_or_db';
        setError(data.message || 'Sistem sedang tidak dapat memproses transaksi.');
        setStatus(isSchemaProblem
          ? 'Database kantin belum siap. Hubungi admin.'
          : 'Transaksi gagal. Silakan coba lagi beberapa saat lagi.');
        return;
      }
      setError(data.message || 'Transaksi gagal.');
      setStatus('Scan gagal. Perbaiki posisi wajah lalu coba lagi.');
      return;
    }

    // PERBAIKAN V41 (2026-11-10): SPLASH HIJAU "Wajah Terverifikasi".
    const snapshotDataUrl = photoFace || captureFacePhoto();
    if (snapshotDataUrl) {
      showFaceSnapshotFlash(snapshotDataUrl);
    }
    stopCamera();
    showStep('stepReceipt');
    renderReceipt(data.invoice);
    if(window.Swal) Swal.fire({
      icon:'success',
      title:'Berhasil!',
      text: data.message,
      confirmButtonText:'Lihat Bukti'
    });
  } catch(err){
    // PERBAIKAN V39 (2026-11-10): jangan diam. Tampilkan status supaya
    // user tahu ada masalah, bukan berpikir prosesnya masih berjalan.
    console.error('[Canteen] runAutoScan error:', err);
    const aborted = err && (err.name === 'AbortError' ||
                             /abort/i.test(String(err.message || '')));
    if (aborted) {
      setError('Server tidak merespons dalam 30 detik. Transaksi dibatalkan, silakan coba lagi.');
      setStatus('Server tidak merespons. Silakan coba lagi.');
    } else {
      setError('Terjadi kesalahan jaringan. Silakan coba lagi.');
      setStatus('Gagal mengirim transaksi. Silakan coba lagi.');
    }
  } finally {
    isPosting = false;
    autoPostInFlight = false;
  }
}

// SweetAlert untuk transaksi duplikat: beri tahu sudah scan di kantin 1
// atau kantin 2, dan sediakan tombol "Lihat Bukti Scan" supaya user bisa
// menampilkan bukti scan sebelumnya ke pihak kantin.
// PERBAIKAN V44 (2026-11-10): pesan sekarang menyebut "hari ini" (cek
// duplikat berdasarkan tanggal saja), window tidak lagi jadi syarat.
function showDuplicateAlert(data){
  const canteenNo = String(data.duplicate_canteen || '?');
  const canteenLabel = canteenNo === '2' ? 'Kantin 2' : 'Kantin ' + canteenNo;

  setError(data.message || 'Anda sudah pernah scan hari ini.');
  setStatus('Transaksi tidak dipost karena Anda sudah scan hari ini.');

  const html = '<div style="text-align:left">'
    + '<div style="margin-bottom:6px">Sudah scan di <strong>' + canteenLabel + '</strong> hari ini'
    + (data.duplicate_time ? ' (pukul ' + data.duplicate_time + ')' : '')
    + '</div>'
    + '<div class="text-muted" style="font-size:13px">Transaksi tidak diduplikasi. Tunjukkan bukti scan sebelumnya ke pihak kantin.</div>'
    + '</div>';

  if (window.Swal) {
    Swal.fire({
      icon: 'warning',
      title: 'Sudah Pernah Scan',
      html: html,
      showCancelButton: true,
      confirmButtonText: 'Lihat Bukti Scan',
      cancelButtonText: 'Tutup',
      allowOutsideClick: false
    }).then(function (result) {
      if (result && result.value) {
        loadAndShowPreviousReceipt();
      }
    });
  }
}

// Ambil bukti scan terakhir untuk NPK ini dari server, lalu tampilkan.
async function loadAndShowPreviousReceipt(){
  if (!validatedNpk) return;

  try {
    const url = @json(route('canteen-transaction.receipt.latest')) + '?npk=' + encodeURIComponent(validatedNpk);
    const res = await fetch(url, {
      headers: { 'Accept':'application/json', 'X-CSRF-TOKEN':csrf }
    });
    const data = await res.json().catch(()=>({}));

    if (!data.success || !data.receipts || data.receipts.length === 0) {
      if (window.Swal) Swal.fire({
        icon: 'info',
        title: 'Bukti Tidak Ditemukan',
        text: 'Tidak ada bukti scan yang bisa ditampilkan.'
      });
      return;
    }

    // Tampilkan yang paling baru.
    const inv = data.receipts[0];
    stopCamera();
    showStep('stepReceipt');
    renderReceipt(inv);

    if (window.Swal) {
      Swal.fire({
        icon: 'success',
        title: 'Bukti Scan Terakhir',
        text: 'Menampilkan bukti scan Anda. Tunjukkan ke pihak kantin.',
        confirmButtonText: 'Tutup'
      });
    }
  } catch(e){
    console.error('[Canteen] loadAndShowPreviousReceipt error:', e);
    if (window.Swal) Swal.fire({
      icon: 'error',
      title: 'Gagal Memuat Bukti',
      text: 'Bukti scan sebelumnya tidak dapat dimuat. Silakan hubungi admin.'
    });
  }
}

function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// ==============================================================
// PERBAIKAN V44 (2026-11-10) — MINIMAP KOORDINAT HASIL SCAN.
//
// Pada "Bukti Scan Kantin" (stepReceipt) sekarang ada peta kecil
// yang menggambar:
//   1. polygon area kerja kantin (sama seperti minimap GPS, dari
//      activeLocations — L.geoJSON supaya konvensi [lng,lat] benar), dan
//   2. marker hijau besar di titik koordinat hasil scan (lat/lng),
//      seperti contoh pada attachment user.
// Kamera peta diarahkan ke gabungan polygon + marker.
// ==============================================================
const MONTHS_ID = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// Format tanggal Indonesia panjang: "1 Januari 2026" (dipakai di bukti scan).
function formatTanggalId(dateStr){
  if (!dateStr) return '-';
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(dateStr));
  if (!m) return String(dateStr);
  const d = parseInt(m[3], 10), mo = parseInt(m[2], 10), y = m[1];
  const monthName = MONTHS_ID[mo - 1] || m[2];
  return d + ' ' + monthName + ' ' + y;
}

function drawReceiptMap(lat, lng){
  const box = $('receiptMap');
  if (!box) return;
  if (typeof lat !== 'number' || typeof lng !== 'number' || isNaN(lat) || isNaN(lng)) return;
  if (typeof L === 'undefined') { console.warn('[Canteen] Leaflet tidak termuat, peta bukti dinonaktifkan.'); return; }

  // Kalau peta sudah pernah dibuat (mis. bukti scan lama lalu scan baru),
  // buang dulu supaya tidak numpuk.
  try {
    if (receiptMap) {
      try { receiptMap.remove(); } catch(e) {}
      receiptMap = null;
      receiptMapGroup = null;
      receiptScanMarker = null;
      box.innerHTML = '';
    }

    // PERBAIKAN V44: peta bukti scan kantin sesuai referensi Gambar 2:
    // - Inisialisasi langsung dengan center [lat, lng], zoom 17 (street level), dan zoomControl: true
    // - Polygon styling hijau toska (#00c08b), semi-transparan, dengan noClip: true
    // - Pointer lingkaran toska dengan avatar siluet user di tengahnya
    receiptMap = L.map('receiptMap', {
      center: [lat, lng],
      zoom: 17,
      zoomControl: true,
      attributionControl: false,
      dragging: true,
      scrollWheelZoom: false
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(receiptMap);

    receiptMapGroup = L.featureGroup().addTo(receiptMap);

    let nearbyPolyBounds = null;

    // 1) Polygon area kerja kantin — sesuai referensi Gambar 2
    if (activeLocations && activeLocations.length > 0) {
      activeLocations.forEach(loc => {
        if (!loc.polygon_geojson) return;
        const ring = extractPolygonRing(loc.polygon_geojson);
        if (ring.length < 3) return;
        try {
          const geoJson = {
            type: 'Feature',
            properties: { name: loc.name || 'Area Lokasi Kerja' },
            geometry: { type: 'Polygon', coordinates: [ring] }
          };
          const poly = window.L.geoJSON(geoJson, {
            style: {
              color: '#00c08b',
              fillColor: '#00c08b',
              fillOpacity: 0.28,
              weight: 3,
              noClip: true
            }
          });
          poly.bindPopup('<b>' + (loc.name || 'Area Lokasi Kerja') + '</b>');
          receiptMapGroup.addLayer(poly);

          const pBounds = poly.getBounds();
          if (pBounds.isValid()) {
            const center = pBounds.getCenter();
            if (Math.abs(center.lat - lat) < 0.05 && Math.abs(center.lng - lng) < 0.05) {
              nearbyPolyBounds = pBounds;
            }
          }
        } catch(e) { console.warn('[Canteen] Gagal menggambar polygon di peta bukti:', e); }
      });
    }

    // 2) Pointer koordinat user — custom avatar badge sesuai referensi Gambar 2
    L.circleMarker([lat, lng], {
      radius: 9,
      color: '#ffffff',
      fillColor: '#1cc88a',
      fillOpacity: 1,
      weight: 2,
      noClip: true
    }).addTo(receiptMap);

    receiptScanMarker = L.marker([lat, lng], {
      icon: createScanAvatarIcon()
    });
    receiptScanMarker.bindPopup('<b>Lokasi Scan</b><br>' + lat.toFixed(6) + ', ' + lng.toFixed(6));
    receiptMapGroup.addLayer(receiptScanMarker);

    // Kunci view ke koordinat scan pada zoom 17 (street level)
    receiptMap.setView([lat, lng], 17);

    // Invalidate size dan pastikan zoom tetap street level (17)
    setTimeout(() => {
      if (!receiptMap) return;
      try { receiptMap.invalidateSize(); } catch(e){}
      try {
        if (nearbyPolyBounds && nearbyPolyBounds.isValid()) {
          nearbyPolyBounds.extend([lat, lng]);
          const calcZ = receiptMap.getBoundsZoom(nearbyPolyBounds);
          if (calcZ >= 16) {
            receiptMap.fitBounds(nearbyPolyBounds, { padding: [12, 12], maxZoom: 18 });
          } else {
            receiptMap.setView([lat, lng], 17);
          }
        } else {
          receiptMap.setView([lat, lng], 17);
        }
      } catch(e) {
        receiptMap.setView([lat, lng], 17);
      }
    }, 120);

    setTimeout(() => {
      if (!receiptMap) return;
      try { receiptMap.invalidateSize(); } catch(e){}
      if (receiptMap.getZoom() < 16) {
        receiptMap.setView([lat, lng], 17);
      }
    }, 350);
  } catch(err) {
    console.warn('[Canteen] Gagal inisialisasi peta bukti scan:', err);
  }
}

function renderReceipt(inv){
  const inside = inv.inside_geomap === null || inv.inside_geomap === undefined ? '-' : (inv.inside_geomap ? 'Ya' : 'Tidak');
  const tglId = formatTanggalId(inv.date);
  const lat = (inv.latitude !== null && inv.latitude !== undefined) ? Number(inv.latitude) : null;
  const lng = (inv.longitude !== null && inv.longitude !== undefined) ? Number(inv.longitude) : null;
  const hasCoords = lat !== null && lng !== null && !isNaN(lat) && !isNaN(lng);
  const photoSrc = formatPhotoFaceSrc(inv.photo_face);

  // Sinkronisasi header "Window aktif" dari transaksi kantin (tabel canteen / canteen_twos)
  if (inv && inv.window_name) {
    const alertBox = $('activeWindowAlert');
    const textBox = $('activeWindowText');
    if (alertBox) {
      alertBox.className = 'alert alert-success text-center mb-3';
    }
    if (textBox) {
      let winTimeSpan = '';
      if (Array.isArray(allWindows)) {
        const matched = allWindows.find(w => (w.name || w.sesi) === inv.window_name);
        if (matched && matched.time_start && matched.time_end) {
          winTimeSpan = ` (${matched.time_start} – ${matched.time_end})`;
        }
      }
      textBox.textContent = `${inv.window_name}${winTimeSpan}`;
    }
  }

  $('receiptBox').innerHTML = `
    <div class="text-center mb-2">
      <div class="font-weight-bold">PT Chutex International Indonesia</div>
      <div class="small text-muted">Bukti transaksi kantin — tunjukkan ke pihak kantin</div>
    </div>
    <hr class="my-2">
    <div class="rounded p-2 mb-2 text-center" style="background:#e8f0fe; border:1px solid #4e73df;">
      <div style="font-size:1.05rem; font-weight:700; color:#1d3f86;">${esc(inv.canteen_name || ('Kantin ' + inv.canteen_id))}</div>
      <div style="font-size:1.15rem; font-weight:700; color:#1d3f86;"><i class="far fa-calendar-alt mr-1"></i>${esc(tglId)}</div>
      <div style="font-size:1.15rem; font-weight:700; color:#1d3f86;"><i class="far fa-clock mr-1"></i>${esc(inv.time)}</div>
    </div>
    <div class="rowline"><span>ID Tiket</span><span>${esc(inv.id_ticket || inv.no || '-')}</span></div>
    <div class="rowline"><span>Lokasi</span><span>${esc(inv.canteen_location || '-')}</span></div>
    <div class="rowline"><span>Kontak</span><span>${esc(inv.contact_person || '-')}${inv.contact_phone ? ' / ' + esc(inv.contact_phone) : ''}</span></div>
    <hr class="my-2">
    <div class="rowline"><span>NPK</span><span>${esc(inv.npk)}</span></div>
    <div class="rowline"><span>Nama</span><span>${esc(inv.name)}</span></div>
    <div class="rowline"><span>Dept</span><span>${esc(inv.dept || '-')}</span></div>
    <div class="rowline"><span>Window</span><span>${esc(inv.window_name)}</span></div>
    <div class="rowline"><span>Area Geomap</span><span>${esc(inv.location_name || '-')} (${inside})</span></div>
    ${hasCoords ? `<div class="rowline"><span>Koordinat</span><span>${lat.toFixed(6)}, ${lng.toFixed(6)}</span></div>` : ''}

    {{-- HASIL SCAN WAJAH DAN MINIMAP: 1 ROW KANAN-KIRI --}}
    <div class="row no-gutters mt-2 mb-2" style="margin-left:-4px; margin-right:-4px;">
      <div class="col-6 px-1">
        <div class="small font-weight-bold text-gray-700 text-center mb-1"><i class="fas fa-camera text-primary mr-1"></i> Foto Wajah</div>
        <div class="rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden shadow-sm" style="height:170px;">
          ${photoSrc
            ? `<img src="${photoSrc}" alt="Foto Wajah" style="width:100%; height:100%; object-fit:cover;">`
            : `<div class="text-center text-muted p-2"><i class="fas fa-user fa-3x mb-2 text-gray-400"></i><div class="small">Tidak ada foto</div></div>`
          }
        </div>
      </div>
      <div class="col-6 px-1">
        <div class="small font-weight-bold text-gray-700 text-center mb-1"><i class="fas fa-map-marked-alt text-primary mr-1"></i> Lokasi Scan</div>
        ${hasCoords
          ? `<div id="receiptMap" class="rounded border shadow-sm" style="height:170px; width:100%; z-index:1;"></div>`
          : `<div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted small p-2 text-center" style="height:170px;"><i class="fas fa-map-marker-alt text-gray-400 mr-1"></i> Koordinat tidak tersedia</div>`
        }
      </div>
    </div>

    <hr class="my-2">
    <div class="text-center small text-muted">Terima kasih — selamat makan!</div>
  `;

  if (hasCoords) {
    try {
      drawReceiptMap(lat, lng);
    } catch(err) {
      console.warn('[Canteen] Gagal memuat peta bukti scan:', err);
    }
  }
}

$('btnDone').addEventListener('click', ()=>{
  stopCamera();
  validatedNpk='';
  selectedCanteenId=null;
  faceFlowStarted = false;
  isGpsValid = false;
  resetFaceGates();
  if ($('activeWindowText') && initialWindowText) $('activeWindowText').innerHTML = initialWindowText;
  if ($('activeWindowAlert') && initialWindowAlertClass) $('activeWindowAlert').className = initialWindowAlertClass;
  showStep('stepCanteen');
});

/**
 * PERBAIKAN V32 (2026-11-09) — RESET GERBANG LIVENESS / CHALLENGE.
 *
 * Dipanggil saat user kembali ke langkah NPK atau selesai transaksi, supaya
 * scan berikutnya MEWAJIBKAN tantangan + liveness lagi. Tanpa reset, user
 * bisa scan berulang tanpa melakukan tantangan.
 */
function resetFaceGates(){
  livenessVerified = !LIVENESS_REQUIRED;
  randomChallengeVerified = !RANDOM_CHALLENGE_REQUIRED;
  stableFrames = 0;
  detectMissCount = 0;
  isPosting = false;
  autoPostInFlight = false;
  if (randomChallenge && typeof randomChallenge.reset === 'function') {
    randomChallenge.reset(RANDOM_CHALLENGE_TYPE);
  }
  if (liveness && typeof liveness.reset === 'function') {
    liveness.reset();
  }
  const guide = $('scannerGuide');
  if (guide) guide.classList.remove('liveness-ok');
  const lBadge = $('livenessBadge');
  if (lBadge && LIVENESS_REQUIRED) {
    lBadge.textContent = 'Liveness: ON';
    lBadge.classList.remove('badge-success');
    lBadge.classList.add('badge-info');
  }
  const cBadge = $('challengeBadge');
  if (cBadge && RANDOM_CHALLENGE_REQUIRED) {
    cBadge.textContent = 'Tantangan: ON';
    cBadge.classList.remove('badge-success');
    cBadge.classList.add('badge-warning');
  }

  // PERBAIKAN V41 (2026-11-10): banner challenge HARUS kembali ke
  // state 'idle' saat gate di-reset. Tanpa ini, scan kedua mewarisi
  // teks "Tantangan terverifikasi" + warna hijau dari scan pertama,
  // padahal verifikasi belum dijalankan sama sekali.
  if (typeof setChallengeBannerState === 'function') {
    setChallengeBannerState('idle');
  }

  // PERBAIKAN V41: splash hijau harus hilang saat starting scan baru,
  // supaya tidak ada overlay hijau nyangkut dari scan sebelumnya.
  if (typeof hideFaceVerifiedFlash === 'function') {
    hideFaceVerifiedFlash();
  }
}

// ==============================================================
// PERBAIKAN V32 (2026-11-09) — SWEETALERT DI LUAR JAM ISTIRAHAT
// ==============================================================
// Permintaan user: "jika diluar window jam istirahat maka daftar kantin
// tidak muncul langsung munculkan sweet alert diluar jam istirahat".
//
// Daftar kantin memang TIDAK dirender (lihat $canteensForView di
// controller). Di sini kita tampilkan SweetAlert supaya alasannya jelas.
const INSIDE_WINDOW = Boolean(@json((bool) ($insideWindow ?? false)));
@php
    // PERBAIKAN V32 (2026-11-09): array window harus disiapkan di blok @php
    // terpisah. Directive @json() tidak bisa menerima closure langsung
    // (parser Blade menghitung tanda kurung dan tersesat oleh ")" di
    // ->values()), sehingga hasil kompilasi PHP rusak.
    $__windowList = collect($windows ?? [])->map(function ($w) {
        return [
            'name'  => $w->name ?? ($w->sesi ?? '-'),
            'start' => $w->time_start ?? '-',
            'end'   => $w->time_end ?? '-',
        ];
    })->values();
@endphp
const WINDOW_LIST = @json($__windowList);

function showOutsideWindowAlert(){
  if (!window.Swal) return;

  let detail = '';
  if (Array.isArray(WINDOW_LIST) && WINDOW_LIST.length > 0) {
    const badges = WINDOW_LIST.map(function(w){
      return '<span class="badge badge-secondary" style="font-size:12px">' +
             esc(w.name) + ' ' + esc(w.start) + '-' + esc(w.end) + '</span>';
    }).join(' ');
    detail = '<div style="margin-top:10px">Jadwal window:<br>' + badges + '</div>';
  }

  Swal.fire({
    icon: 'warning',
    title: 'Di Luar Jam Istirahat',
    html: 'Transaksi kantin hanya bisa dilakukan pada window jam istirahat yang dikonfigurasi admin.' + detail,
    confirmButtonText: 'Mengerti',
    allowOutsideClick: false
  });
}

if (!INSIDE_WINDOW) {
  // Tunggu sebentar supaya SweetAlert2 (dimuat via CDN) siap dipakai.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showOutsideWindowAlert);
  } else {
    showOutsideWindowAlert();
  }
}
})();
</script>
</body>
</html>
