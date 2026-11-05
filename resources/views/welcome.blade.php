<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>CHUTEX HRIS - Sistem Informasi Kepegawaian</title>
    
    <link rel="icon" type="image/x-icon" href="{{asset('storage/images/signature_icon.ico')}}">
    <link href="{{asset('vendor/fontawesome-free/css/all.min.css')}}" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900&display=swap" rel="stylesheet">
    <link href="{{asset('css/sb-admin-2.min.css')}}" rel="stylesheet">
    
    <style>
        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .feature-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0,0,0,.175)!important;
            border-left-color: #4e73df;
        }
        .icon-circle {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
        }
        .btn-hero {
            padding: 0.75rem 2rem;
            font-size: 1rem;
            border-radius: 2rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        .btn-hero:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
        }
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* ===== Core Module: Biometric Wajah ===== */
        .bio-module {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 45%, #2c5364 100%);
            color: #fff;
        }
        .bio-module::before {
            content: '';
            position: absolute;
            width: 320px;
            height: 320px;
            right: -110px;
            top: -130px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(79,172,254,.35) 0%, rgba(79,172,254,0) 70%);
        }
        .bio-module::after {
            content: '';
            position: absolute;
            width: 260px;
            height: 260px;
            left: -90px;
            bottom: -120px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0,242,254,.22) 0%, rgba(0,242,254,0) 70%);
        }
        .bio-module > * { position: relative; z-index: 1; }

        .bio-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .35rem .9rem;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.22);
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            backdrop-filter: blur(6px);
        }
        .bio-badge .pulse-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 0 0 rgba(74,222,128,.7);
            animation: bioPulse 2s infinite;
        }
        @keyframes bioPulse {
            0%   { box-shadow: 0 0 0 0 rgba(74,222,128,.7); }
            70%  { box-shadow: 0 0 0 10px rgba(74,222,128,0); }
            100% { box-shadow: 0 0 0 0 rgba(74,222,128,0); }
        }

        .bio-scan {
            position: relative;
            width: 168px;
            height: 168px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 50% 40%, rgba(79,172,254,.30), rgba(0,0,0,.30));
            border: 1px solid rgba(255,255,255,.20);
            margin: 0 auto;
            overflow: hidden;
        }
        .bio-scan .face-glyph {
            font-size: 4.4rem;
            color: rgba(255,255,255,.92);
            filter: drop-shadow(0 4px 12px rgba(0,0,0,.45));
            z-index: 2;
        }
        .bio-scan .scan-line {
            position: absolute;
            left: 8%; right: 8%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #00f2fe, #4facfe, transparent);
            box-shadow: 0 0 14px rgba(0,242,254,.9);
            animation: bioScan 2.6s ease-in-out infinite;
            z-index: 3;
        }
        @keyframes bioScan {
            0%, 100% { top: 12%; opacity: .25; }
            50%      { top: 86%; opacity: 1; }
        }
        .bio-scan .ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 1px dashed rgba(255,255,255,.28);
            animation: bioSpin 18s linear infinite;
        }
        @keyframes bioSpin { to { transform: rotate(360deg); } }

        .bio-feature {
            display: flex;
            gap: .85rem;
            align-items: flex-start;
            padding: .8rem .9rem;
            border-radius: 12px;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            transition: all .25s ease;
            height: 100%;
        }
        .bio-feature:hover {
            background: rgba(255,255,255,.13);
            transform: translateY(-3px);
            border-color: rgba(79,172,254,.55);
        }
        .bio-feature .bio-ico {
            flex: 0 0 auto;
            width: 38px; height: 38px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg,#4facfe,#00f2fe);
            color: #06283d;
            font-size: 1rem;
        }
        .bio-feature h6 {
            color: #fff;
            font-weight: 700;
            font-size: .9rem;
            margin-bottom: .15rem;
        }
        .bio-feature p {
            color: rgba(255,255,255,.72);
            font-size: .78rem;
            margin: 0;
            line-height: 1.45;
        }

        .bio-stat {
            text-align: center;
            padding: .85rem .5rem;
            border-radius: 12px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.10);
        }
        .bio-stat .v { font-size: 1.5rem; font-weight: 800; color: #fff; line-height: 1.1; }
        .bio-stat .l { font-size: .7rem; letter-spacing: .8px; text-transform: uppercase; color: rgba(255,255,255,.62); }

        .bio-cta {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            padding: .7rem 1.4rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: .88rem;
            color: #06283d;
            background: linear-gradient(135deg,#4facfe,#00f2fe);
            transition: all .25s ease;
            text-decoration: none;
        }
        .bio-cta:hover {
            color: #06283d;
            transform: translateY(-2px);
            box-shadow: 0 .6rem 1.4rem rgba(0,242,254,.35);
        }
        .bio-ghost {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            padding: .7rem 1.4rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: .88rem;
            color: #fff;
            border: 1px solid rgba(255,255,255,.35);
            transition: all .25s ease;
            text-decoration: none;
        }
        .bio-ghost:hover { color: #fff; background: rgba(255,255,255,.12); transform: translateY(-2px); }

        @media (max-width:767px) {
            .bio-scan { width: 138px; height: 138px; }
            .bio-scan .face-glyph { font-size: 3.5rem; }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="hero-gradient text-white py-5">
        <div class="container">
            <div class="row align-items-center py-5">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h1 class="display-4 font-weight-bold mb-3">CHUTEX HRIS</h1>
                    <p class="lead mb-4">Sistem Informasi Kepegawaian Terpadu</p>
                    <p class="mb-4">Mengelola data karyawan, presensi, penggajian, dan administrasi HR dalam satu platform terintegrasi untuk meningkatkan efisiensi operasional perusahaan.</p>
                    <div class="d-flex flex-wrap gap-2">
                        @auth
                            <a href="{{ url('/home') }}" class="btn btn-light btn-hero shadow-lg mr-2 mb-2">
                                <i class="fas fa-home mr-2"></i> Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-light btn-hero shadow-lg mr-2 mb-2">
                                <i class="fas fa-sign-in-alt mr-2"></i> Masuk Sistem
                            </a>
                            @if (Route::has('self-register-face'))
                            <a href="{{ route('self-register-face') }}" class="btn btn-outline-light btn-hero shadow-sm mb-2">
                                <i class="fas fa-user-plus mr-2"></i> Registrasi Wajah
                            </a>
                            @endif
                        @endauth
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-building fa-10x opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== CORE MODULE: BIOMETRIC WAJAH ===== -->
    <div class="container my-5 py-4">
        <div class="card bio-module shadow-lg border-0">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">

                    <!-- Kolom kiri: copy + CTA -->
                    <div class="col-lg-7">
                        <span class="bio-badge mb-3">
                            <span class="pulse-dot"></span> Core Module
                        </span>

                        <h2 class="h2 font-weight-bold text-white mb-2">
                            Biometric Wajah
                        </h2>

                        <p class="mb-3" style="color:rgba(255,255,255,.78); max-width: 640px;">
                            Modul inti biometrik CHUTEX HRIS yang mengubah wajah karyawan menjadi
                            identitas digital. Presensi tercatat otomatis dalam hitungan detik,
                            tanpa absen manual, dan terlindungi dari pemecahan
                            sistem dengan deteksi hidup (liveness) berlapis.
                        </p>

                        <div class="row g-2 mb-4">
                            <div class="col-6 col-md-3">
                                <div class="bio-stat">
                                    <div class="v">&lt; 3s</div>
                                    <div class="l">Verifikasi</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bio-stat">
                                    <div class="v">68</div>
                                    <div class="l">Landmark</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bio-stat">
                                    <div class="v">99.9%</div>
                                    <div class="l">Akurasi</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bio-stat">
                                    <div class="v">24/7</div>
                                    <div class="l">Tersedia</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('face-attendance.login') }}" class="bio-cta">
                                <i class="fas fa-camera-retro"></i> Presensi Wajah Sekarang
                            </a>
                            @if (Route::has('self-register-face'))
                            <a href="{{ route('self-register-face') }}" class="bio-ghost">
                                <i class="fas fa-id-badge"></i> Daftarkan Wajah Saya
                            </a>
                            @endif
                        </div>
                    </div>

                    <!-- Kolom kanan: visual scan + status engine -->
                    <div class="col-lg-5">
                        <div class="bio-scan mb-3">
                            <span class="ring"></span>
                            <i class="fas fa-user-secret face-glyph"></i>
                            <span class="scan-line"></span>
                        </div>

                        <div class="text-center mb-3" style="color:rgba(255,255,255,.62); font-size:.78rem;">
                            <i class="fas fa-microchip mr-1"></i>
                            Face-api.js &middot; TinyFaceDetector &middot; FaceLandmark68
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <div class="bio-feature">
                                    <div class="bio-ico"><i class="fas fa-bolt"></i></div>
                                    <div>
                                        <h6>Realtime</h6>
                                        <p>Absen tanpa antre, langsung tercatat.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bio-feature">
                                    <div class="bio-ico"><i class="fas fa-map-marker-alt"></i></div>
                                    <div>
                                        <h6>Geo-Validasi</h6>
                                        <p>Terverifikasi dalam radius area kerja.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bio-feature">
                                    <div class="bio-ico"><i class="fas fa-user-shield"></i></div>
                                    <div>
                                        <h6>Anti-Spoofing</h6>
                                        <p>Liveness: kedip &amp; gerak kepala wajib.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bio-feature">
                                    <div class="bio-ico"><i class="fas fa-database"></i></div>
                                    <div>
                                        <h6>Terenkripsi</h6>
                                        <p>Template wajah disimpan aman di server.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="container my-5 py-4">
        <div class="text-center mb-5">
            <h2 class="h2 font-weight-bold text-gray-800 mb-2">Modul Utama Sistem</h2>
            <p class="text-muted">Fitur lengkap untuk mendukung operasional HR perusahaan</p>
        </div>

        <div class="row">
            <!-- Face Recognition & Attendance -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card feature-card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <div class="icon-circle bg-primary text-white">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h5 class="font-weight-bold text-gray-800 mb-3">Presensi Wajah & GPS</h5>
                        <p class="text-muted small mb-0">Sistem presensi biometrik berbasis pengenalan wajah dengan validasi lokasi GPS untuk akurasi kehadiran karyawan.</p>
                    </div>
                </div>
            </div>

            <!-- Employee Data Management -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card feature-card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <div class="icon-circle bg-success text-white">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5 class="font-weight-bold text-gray-800 mb-3">Data Karyawan</h5>
                        <p class="text-muted small mb-0">Pengelolaan data karyawan lengkap termasuk biodata, departemen, shift kerja, dan riwayat kepegawaian secara terpusat.</p>
                    </div>
                </div>
            </div>

            <!-- Attendance Reporting -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card feature-card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <div class="icon-circle bg-info text-white">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h5 class="font-weight-bold text-gray-800 mb-3">Rekap Presensi</h5>
                        <p class="text-muted small mb-0">Laporan presensi bulanan per karyawan atau departemen dengan visualisasi data dan export CSV untuk analisis lanjutan.</p>
                    </div>
                </div>
            </div>

            <!-- Payroll Management -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card feature-card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <div class="icon-circle bg-warning text-white">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h5 class="font-weight-bold text-gray-800 mb-3">Penggajian</h5>
                        <p class="text-muted small mb-0">Modul penggajian terintegrasi dengan presensi untuk perhitungan gaji otomatis, slip gaji digital, dan pelaporan pajak.</p>
                    </div>
                </div>
            </div>

            <!-- Leave & Holiday -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card feature-card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <div class="icon-circle bg-danger text-white">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h5 class="font-weight-bold text-gray-800 mb-3">Cuti & Libur</h5>
                        <p class="text-muted small mb-0">Pengajuan cuti online dengan approval workflow, saldo cuti real-time, dan kalender hari libur nasional.</p>
                    </div>
                </div>
            </div>

            <!-- Shift Management -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card feature-card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <div class="icon-circle bg-secondary text-white">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h5 class="font-weight-bold text-gray-800 mb-3">Jadwal Shift</h5>
                        <p class="text-muted small mb-0">Penjadwalan shift fleksibel dengan aturan window presensi yang dapat dikonfigurasi per shift dan departemen.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="row mt-5">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center mb-3 mb-md-0">
                                <i class="fas fa-shield-alt fa-3x text-primary"></i>
                            </div>
                            <div class="col-md-10">
                                <h5 class="font-weight-bold text-gray-800 mb-2">Keamanan Data Terjamin</h5>
                                <p class="text-muted small mb-0">Sistem dilengkapi dengan autentikasi multi-faktor, enkripsi data, dan audit trail lengkap untuk memastikan keamanan informasi kepegawaian perusahaan.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-left mb-3 mb-md-0">
                    <p class="text-muted mb-0">
                        <strong>PT Chutex International Indonesia</strong><br>
                        Sistem Informasi Kepegawaian Terpadu
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-right">
                    <p class="text-muted mb-0">
                        Laravel v{{ Illuminate\Foundation\Application::VERSION }} | PHP v{{ PHP_VERSION }}<br>
                        &copy; {{ date('Y') }} CHUTEX HRIS. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="{{asset('vendor/jquery/jquery.min.js')}}"></script>
    <script src="{{asset('vendor/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
</body>
</html>
