<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<style>
    /* ── Variables & Setup ── */
    body {
        font-family: 'Nunito', sans-serif;
    }

    /* Background */
    #content-wrapper {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 55%, #1a3a8f 100%) !important;
        min-height: 100vh;
    }

    /* Glassmorphism navbar */
    .topbar {
        position: relative;
        z-index: 999;
        background: rgba(255, 255, 255, .12) !important;
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, .15) !important;
        box-shadow: none !important;
    }

    .topbar .brand-name {
        color: #fff !important;
        font-weight: 800;
        font-size: .92rem;
    }

    .topbar .nav-link {
        color: rgba(255, 255, 255, .85) !important;
        font-size: .83rem;
        font-weight: 600;
        padding: .3rem .65rem !important;
        border-radius: .4rem;
    }

    .topbar .nav-link:hover {
        color: #fff !important;
        background: rgba(255, 255, 255, .12);
    }

    .topbar .nav-link.act {
        color: #fff !important;
        background: rgba(255, 255, 255, .18);
        border-bottom: 2px solid rgba(255, 255, 255, .7);
    }

    .topbar .btn-keluar {
        color: #fff !important;
        border-color: rgba(255, 255, 255, .4) !important;
        background: rgba(255, 255, 255, .1) !important;
        font-size: .75rem !important;
    }

    .topbar .btn-keluar:hover {
        background: rgba(255, 255, 255, .22) !important;
    }

    .topbar .navbar-toggler {
        border-color: rgba(255, 255, 255, .4) !important;
        padding: .2rem .4rem;
    }

    .topbar .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255,0.85)' stroke-linecap='round' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    @media(max-width:767.98px) {
        .topbar .navbar-collapse {
            background: rgba(26, 58, 143, .96);
            border-top: 1px solid rgba(255, 255, 255, .12);
            margin-top: .25rem;
            padding: .45rem .5rem .55rem;
            border-radius: 0 0 .5rem .5rem;
        }

        .topbar .nav-link {
            padding: .38rem .25rem !important;
        }

        .topbar .d-flex.ml-auto {
            margin-top: .4rem;
            padding-top: .4rem;
            border-top: 1px solid rgba(255, 255, 255, .1);
            width: 100%;
            justify-content: space-between;
        }
    }

    /* Heading */
    .pg-title {
        color: #fff !important;
        font-weight: 800;
        font-size: 1.05rem;
    }

    .pg-sub {
        color: rgba(255, 255, 255, .72) !important;
        font-size: .78rem;
    }

    /* ── UI Elements ── */
    .main-card {
        background: #fff;
        border: none;
        border-radius: .8rem;
        overflow: hidden;
        box-shadow: 0 .5rem 2.5rem rgba(0, 0, 0, .2);
        margin: 10px auto;
        width: 100%;
        text-align: center;
    }

    .mc-header {
        padding: .75rem 1.25rem;
        border-bottom: 1px solid #eaecf4;
        background: #f8f9fc;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .5rem;
        font-size: .95rem;
    }

    /* ── Stepper ── */
    .stepper-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 2rem;
    }

    .stepper-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        position: relative;
    }

    .stepper-item:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 18px;
        left: 50%;
        width: 100%;
        height: 3px;
        background: #e3e6f0;
        z-index: 0;
        transition: background .3s;
    }

    .stepper-item.completed:not(:last-child)::after {
        background: #4e73df;
    }

    .stepper-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #e3e6f0;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        z-index: 1;
        transition: all .3s;
    }

    .stepper-item.active .stepper-circle {
        background: #4e73df;
        transform: scale(1.15);
        box-shadow: 0 0 0 4px rgba(78, 115, 223, .15);
    }

    .stepper-item.completed .stepper-circle {
        background: #1cc88a;
    }

    .stepper-label {
        margin-top: .5rem;
        font-size: .8rem;
        font-weight: 600;
        color: #b7b9cc;
    }

    .stepper-item.active .stepper-label {
        color: #4e73df;
    }

    .stepper-item.completed .stepper-label {
        color: #1cc88a;
    }

    /* ── Panels & Review Rows ── */
    .step-panel {
        display: none;
        animation: fadeIn .4s ease;
    }

    .step-panel.active {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .review-row {
        display: flex;
        justify-content: space-between;
        padding: .65rem 0;
        border-bottom: 1px solid #f0f2f8;
        font-size: .85rem;
    }

    .review-row:last-child {
        border-bottom: none;
    }

    .review-label {
        color: #858796;
    }

    .review-value {
        font-weight: 700;
        color: #3a3b45;
        text-align: right;
        max-width: 60%;
    }

    /* Form Field Adjustments */
    .form-container {
        max-width: 700px;
        margin: 0 auto;
    }

    /* Footer */
    .sticky-footer {
        background: rgba(255, 255, 255, .08) !important;
        border-top: 1px solid rgba(255, 255, 255, .12) !important;
    }

    .sticky-footer span {
        color: rgba(255, 255, 255, .5) !important;
        font-size: .74rem;
    }

    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-up {
        animation: fadeUp .4s ease;
    }
</style>

<body id="page-top">
    @include('sweetalert::alert')

    <div id="wrapper">
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">

                {{-- Navbar --}}
                <nav class="navbar navbar-expand-md navbar-light bg-white topbar mb-4 static-top shadow">
                    <div class="d-flex align-items-center">
                        <img src="{{ asset('img/chutex.svg') }}" style="width:36px;" class="mr-2">
                        <span class="font-weight-bold text-white" style="font-size:1.05rem;">E-HRIS</span>
                    </div>
                    <button class="navbar-toggler ml-auto" type="button" data-toggle="collapse"
                        data-target="#navbarCuti" aria-controls="navbarCuti" aria-expanded="false"
                        aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarCuti">
                        <ul class="navbar-nav mr-auto ml-3">
                            <li class="nav-item">
                                <a class="nav-link act" href="{{ route('pengajuan-cuti.form') }}">
                                    <i class="fas fa-file-alt fa-sm mr-1"></i> Pengajuan Cuti
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('pengajuan-cuti.riwayat') }}">
                                    <i class="fas fa-tasks fa-sm mr-1"></i> Riwayat Pengajuan
                                </a>
                            </li>
                        </ul>
                        <div class="d-flex align-items-center ml-auto">
                            <span class="mr-3 text-white small d-none d-md-inline" style="opacity:0.9;">
                                <i class="fas fa-user-circle"></i>
                                {{ $employee->NAMA_KARYAWAN }} &mdash; {{ $employee->DEPARTEMENT }}
                            </span>
                            <a href="{{ route('pengajuan-cuti.login') }}" class="btn btn-sm btn-keluar">
                                <i class="fas fa-sign-out-alt fa-sm"></i> Keluar
                            </a>
                        </div>
                    </div>
                </nav>

                {{-- ── Main Content ── --}}
                <div class="container-fluid px-3 px-md-4 fade-up" style="max-width: 900px; padding-bottom: 2rem;">

                    {{-- Header --}}
                    <div class="mb-4 text-white">
                        <h1 class="h3 font-weight-bold mb-1"><i class="fas fa-calendar-check mr-2"></i>Pengajuan Cuti
                        </h1>
                        <p class="mb-0" style="font-size:.9rem; opacity:0.9;">Isi form di bawah untuk mengajukan cuti
                            Anda kepada atasan.</p>
                    </div>

                    {{-- Form Card --}}
                    <div class="main-card text-left mb-4">
                        <div class="mc-header">
                            <span class="mc-title font-weight-bold" style="color: #3a3b45;"><i
                                    class="fas fa-file-signature text-primary mr-2"></i>Formulir Pengajuan Cuti</span>
                        </div>
                        <div class="card-body p-4 p-md-5">

                            {{-- Stepper UI --}}
                            <div class="stepper-wrapper">
                                <div class="stepper-item active" id="step-ind-1">
                                    <div class="stepper-circle" id="sc-1"><i class="fas fa-file-alt fa-sm"></i></div>
                                    <div class="stepper-label">Isi Form</div>
                                </div>
                                <div class="stepper-item" id="step-ind-2">
                                    <div class="stepper-circle" id="sc-2"><i class="fas fa-search fa-sm"></i></div>
                                    <div class="stepper-label">Review Data</div>
                                </div>
                            </div>

                            {{-- Final Form Content --}}
                            <form id="cuti-form" action="{{ route('pengajuan-cuti.submit-form') }}" method="POST">
                                @csrf
                                <input type="hidden" name="npk" value="{{ $employee->NPK }}">
                                <input type="hidden" name="nama" value="{{ $employee->NAMA_KARYAWAN }}">
                                <input type="hidden" name="bagian" value="{{ $employee->DEPARTEMENT }}">

                                {{-- ══ STEP 1: Form Input ══ --}}
                                <div class="step-panel active" id="panel-1">
                                    <div class="form-container">
                                        {{-- Karyawan Profil Box --}}
                                        <div class="alert alert-primary d-flex align-items-center mb-4 border-0"
                                            style="background:#eef2cf; border-radius:.6rem;">
                                            <i class="fas fa-id-badge fa-2x text-primary mr-3"></i>
                                            <div>
                                                <div class="font-weight-bold text-gray-900" style="font-size:.95rem;">
                                                    {{ $employee->NAMA_KARYAWAN }}</div>
                                                <div class="text-muted" style="font-size:.8rem;">{{ $employee->NPK }}
                                                    &middot; {{ $employee->DEPARTEMENT }}</div>
                                            </div>
                                        </div>

                                        {{-- Input Fields --}}
                                        <div class="form-group mb-4">
                                            <label class="font-weight-bold text-gray-700 small">Jenis Cuti <span
                                                    class="text-danger">*</span></label>
                                            <select class="form-control" name="jenis_cuti" id="jenis_cuti" required>
                                                <option value="" disabled selected>— Pilih Jenis Cuti —</option>
                                                @foreach ($masterLeaveType as $item)
                                                    <option value="{{ $item->id }}" data-name="{{ $item->name }}" {{ ($employee->JK == $item->gender_type || $item->gender_type == 'A') ? '' : 'disabled' }}>{{ $item->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 form-group mb-4">
                                                <label class="font-weight-bold text-gray-700 small">Tanggal Mulai <span
                                                        class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="tanggal_mulai"
                                                    id="tanggal_mulai" required>
                                            </div>
                                            <div class="col-md-6 form-group mb-4">
                                                <label class="font-weight-bold text-gray-700 small">Tanggal Selesai
                                                    <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="tanggal_selesai"
                                                    id="tanggal_selesai" required>
                                            </div>
                                        </div>

                                        <div class="form-group mb-4">
                                            <label class="font-weight-bold text-gray-700 small">Jumlah Hari</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-light text-primary"><i
                                                            class="fas fa-calendar-day"></i></span>
                                                </div>
                                                <input type="hidden" name="jumlah_hari" id="jumlah_hari_val">
                                                <input type="text" class="form-control bg-light"
                                                    id="jumlah_hari_display" readonly
                                                    placeholder="Otomatis dihitung...">
                                            </div>
                                            <small id="sisa_cuti_info" class="form-text mt-2 font-weight-bold"
                                                style="display:none;"></small>
                                        </div>

                                        <div class="form-group mb-4">
                                            <label class="font-weight-bold text-gray-700 small">Keterangan / Alasan
                                                <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="keterangan" id="keterangan" rows="3"
                                                required placeholder="Alasan cuti..."></textarea>
                                        </div>

                                        <div class="d-flex justify-content-between border-top pt-4 mt-2">
                                            <a href="{{ route('pengajuan-cuti.login') }}"
                                                class="btn btn-light text-gray-700 px-4">Batal</a>
                                            <button type="button" class="btn btn-primary px-4 shadow-sm"
                                                onclick="goToReview()">Review <i
                                                    class="fas fa-arrow-right ml-1"></i></button>
                                        </div>
                                    </div>
                                </div>


                                {{-- ══ STEP 2: Review Form ══ --}}
                                <div class="step-panel" id="panel-2">
                                    <div class="form-container">
                                        <h6 class="text-primary font-weight-bold mb-3"><i
                                                class="fas fa-clipboard-check mr-2"></i>Review Pengajuan</h6>
                                        <p class="text-muted small mb-4">Pastikan data pengajuan cuti Anda di bawah ini
                                            sudah benar sebelum melakukan konfirmasi akhir.</p>

                                        <div class="bg-light p-3 p-md-4 rounded mb-4 border">
                                            <div class="review-row">
                                                <span class="review-label">Jenis Cuti</span>
                                                <span class="review-value" id="rv-jenis"></span>
                                            </div>
                                            <div class="review-row">
                                                <span class="review-label">Tanggal Mulai</span>
                                                <span class="review-value" id="rv-mulai"></span>
                                            </div>
                                            <div class="review-row">
                                                <span class="review-label">Tanggal Selesai</span>
                                                <span class="review-value" id="rv-selesai"></span>
                                            </div>
                                            <div class="review-row">
                                                <span class="review-label">Total Hari</span>
                                                <span class="review-value text-primary" id="rv-hari"></span>
                                            </div>
                                            <div class="review-row">
                                                <span class="review-label">Alasan</span>
                                                <span class="review-value" id="rv-ket"></span>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between pt-2">
                                            <button type="button" class="btn btn-light px-4 text-gray-700"
                                                onclick="backToForm()">
                                                <i class="fas fa-arrow-left mr-1"></i> Kembali
                                            </button>
                                            <button type="submit" class="btn btn-success px-5 shadow-sm">
                                                <i class="fas fa-paper-plane mr-1"></i> Kirim
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->

                <footer class="sticky-footer mt-auto">
                    <div class="text-center py-1">
                        <span>Copyright &copy; PT. Chutex International Indonesia {{ date('Y') }}</span>
                    </div>
                </footer>

            </div>
            <!-- End of Main Content -->
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    @include('layout.footerscript')

    <script>
        const elJenis = document.getElementById('jenis_cuti'),
            elMulai = document.getElementById('tanggal_mulai'),
            elSelesai = document.getElementById('tanggal_selesai'),
            elHariDisp = document.getElementById('jumlah_hari_display'),
            elHariVal = document.getElementById('jumlah_hari_val'),
            elSisaInfo = document.getElementById('sisa_cuti_info');

        /* ── Utilities ── */
        const formatDate = (val) => {
            if (!val) return '-';
            const [y, m, d] = val.split('-');
            return `${d} ${['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][parseInt(m) - 1]} ${y}`;
        };

        const updateDisplayItem = (id, html) => { document.getElementById(id).innerHTML = html; };
        const switchPanel = (hideParam, showParam, isNext) => {
            document.getElementById(`panel-${hideParam}`).classList.remove('active');
            document.getElementById(`panel-${showParam}`).classList.add('active');

            let s1 = document.getElementById('step-ind-1'),
                s2 = document.getElementById('step-ind-2'),
                sc1 = document.getElementById('sc-1');

            if (isNext) {
                s1.classList.remove('active'); s1.classList.add('completed');
                sc1.innerHTML = '<i class="fas fa-check fa-sm"></i>';
                s2.classList.add('active');
            } else {
                s2.classList.remove('active');
                s1.classList.remove('completed'); s1.classList.add('active');
                sc1.innerHTML = '<i class="fas fa-file-alt fa-sm"></i>';
            }
        };

        const holidays = @json($holidays ?? []);

        /* ── Date Logic ── */
        const handleDateChange = () => {
            let diff = 0;
            if (elMulai.value && elSelesai.value) {
                let start = new Date(elMulai.value);
                let end = new Date(elSelesai.value);

                // Loop setiap hari dari rentang start sampai end
                if (end >= start) {
                    let current = new Date(start);
                    while (current <= end) {
                        let dayOfWeek = current.getDay();

                        // ISO format YYYY-MM-DD
                        // Penting: pastikan timezone tidak bergeser saat convert ke ISO
                        let tempDate = new Date(current.getTime() - (current.getTimezoneOffset() * 60000));
                        let dateString = tempDate.toISOString().split('T')[0];

                        // 0 = Sunday, 6 = Saturday
                        let isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
                        let isHoliday = holidays.includes(dateString);

                        if (!isWeekend && !isHoliday) {
                            diff++;
                        }
                        // tambah 1 hari
                        current.setDate(current.getDate() + 1);
                    }
                }
            }

            if (diff > 0) {
                elHariDisp.value = `${diff} Hari`;
                elHariVal.value = diff;
                if (elJenis.value) checkBalance(diff);
            } else {
                // Jika tanggal terbalik (selesai < mulai) atau range 100% jatuh di hari libur
                elHariDisp.value = elMulai.value && elSelesai.value ? 'Tgl tdk valid / Libur' : '';
                elHariVal.value = '';
                elSisaInfo.style.display = 'none';
            }
        };

        [elMulai, elSelesai, elJenis].forEach(el => el.addEventListener('change', handleDateChange));

        /* ── Fetch Sisa Cuti API ── */
        const checkBalance = (days) => {
            if (!elJenis.value) return;
            elSisaInfo.style.display = 'block';
            elSisaInfo.className = 'form-text text-info mt-2 small font-weight-bold';
            elSisaInfo.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i> Memeriksa sisa cuti...';

            fetch(`{{ route('pengajuan-cuti.get-leave-balance') }}?npk={{ $employee->NPK }}&leave_type_id=${elJenis.value}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        if (res.sisa <= 0) {
                            elSisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold';
                            elSisaInfo.innerHTML = `<i class="fas fa-times-circle mr-1"></i> ${res.keterangan}. Anda tidak memiliki sisa cuti.`;
                        } else if (days > res.sisa) {
                            elSisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold';
                            elSisaInfo.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i> ${res.keterangan}. Sisa cuti tidak mencukupi untuk ${days} hari.`;
                        } else {
                            elSisaInfo.className = 'form-text text-success mt-2 small font-weight-bold';
                            elSisaInfo.innerHTML = `<i class="fas fa-check-circle mr-1"></i> ${res.keterangan}. Sisa cuti mencukupi.`;
                        }
                    } else {
                        elSisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold';
                        elSisaInfo.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Gagal mengecek data cuti.';
                    }
                }).catch(() => {
                    elSisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold';
                    elSisaInfo.innerHTML = '<i class="fas fa-wifi mr-1"></i> Koneksi bermasalah saat mengecek cuti.';
                });
        };

        /* ── Panel Navigations ── */
        window.goToReview = () => {
            const elKet = document.getElementById('keterangan');

            if (!elJenis.value) return elJenis.reportValidity();
            if (!elMulai.value) return elMulai.reportValidity();
            if (!elSelesai.value) return elSelesai.reportValidity();
            if (!elKet.value.trim()) return elKet.reportValidity();

            if (!elHariVal.value || elHariVal.value <= 0) {
                return alert('Format tanggal tidak valid (mulai harus lebih awal dari selesai).');
            }

            const leaveName = elJenis.options[elJenis.selectedIndex].getAttribute('data-name');

            updateDisplayItem('rv-jenis', `<span class="badge badge-primary px-2 py-1">${leaveName}</span>`);
            updateDisplayItem('rv-mulai', formatDate(elMulai.value));
            updateDisplayItem('rv-selesai', formatDate(elSelesai.value));
            updateDisplayItem('rv-hari', `${elHariVal.value} Hari`);
            updateDisplayItem('rv-ket', elKet.value);

            switchPanel(1, 2, true);
        };

        window.backToForm = () => switchPanel(2, 1, false);
    </script>
</body>

</html>