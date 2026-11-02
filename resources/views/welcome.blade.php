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
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
