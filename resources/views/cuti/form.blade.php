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

    /* Leave blocks (multi pengajuan) */
    .leave-block {
        text-align: left;
        background: #fbfbfe;
        transition: box-shadow .2s;
    }

    .leave-block:hover {
        box-shadow: 0 .15rem .75rem rgba(0, 0, 0, .06);
    }

    .btn-remove-leave {
        line-height: 1;
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

    /* Flatpickr — selaraskan warna dengan tema biru aplikasi */
    .flatpickr-day.selected,
    .flatpickr-day.selected:hover {
        background: #4e73df;
        border-color: #4e73df;
    }

    .flatpickr-day.today {
        border-color: #4e73df;
    }

    .flatpickr-day.flatpickr-disabled,
    .flatpickr-day.flatpickr-disabled:hover {
        color: #d3d6e0;
        text-decoration: line-through;
    }
</style>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

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
                            Anda kepada atasan. Anda bisa menambahkan lebih dari satu jenis cuti sekaligus — setiap
                            jenis cuti akan diajukan sebagai pengajuan approval yang terpisah, jenis cuti yang sama
                            hanya boleh dipilih sekali, dan tanggalnya tidak boleh tumpang tindih antar pengajuan.</p>
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
                                                    {{ $employee->NAMA_KARYAWAN }}
                                                </div>
                                                <div class="text-muted" style="font-size:.8rem;">{{ $employee->NPK }}
                                                    &middot; {{ $employee->DEPARTEMENT }}</div>
                                            </div>
                                        </div>

                                        {{-- Kontainer blok cuti — diisi lewat JS agar bisa ditambah/dihapus dinamis --}}
                                        <div id="leaves-container"></div>

                                        <div class="text-center mb-4">
                                            <button type="button" class="btn btn-outline-primary btn-sm px-3" id="btnTambahCuti">
                                                <i class="fas fa-plus mr-1"></i> Tambah Pengajuan Cuti Lain
                                            </button>
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
                                            sudah benar sebelum melakukan konfirmasi akhir. Setiap jenis cuti akan
                                            diajukan sebagai pengajuan approval terpisah.</p>

                                        <div id="review-container"></div>

                                        <div class="d-flex justify-content-between pt-2">
                                            <button type="button" class="btn btn-light px-4 text-gray-700"
                                                onclick="backToForm()">
                                                <i class="fas fa-arrow-left mr-1"></i> Kembali
                                            </button>
                                            <button type="submit" class="btn btn-success px-5 shadow-sm">
                                                <i class="fas fa-paper-plane mr-1"></i> Kirim Semua Pengajuan
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

    <!-- Flatpickr: dipakai agar tanggal yang sudah dipilih di satu blok cuti bisa didisable di blok lainnya -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

    <script>
        flatpickr.localize(flatpickr.l10ns.id);

        /* ══ Data dari server ══ */
        // masterLeaveType sudah difilter di controller sesuai gender karyawan (gender_type 'A' = semua)
        const leaveTypeOptions = @json($masterLeaveType->map(function ($t) {
            return ['id' => $t->id, 'name' => $t->name];
        }));
        const holidays = @json($holidays ?? []);
        const employeeNpk = @json($employee->NPK);
        const getBalanceUrl = @json(route('pengajuan-cuti.get-leave-balance'));

        const now = new Date();
        const todayStr = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().split('T')[0];

        let leaveIndex = 0;

        /* ── Utilities ── */
        const formatDate = (val) => {
            if (!val) return '-';
            const [y, m, d] = val.split('-');
            return `${d} ${['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][parseInt(m) - 1]} ${y}`;
        };

        // tanggal_mulai dan tanggal_selesai dihitung INCLUSIVE (keduanya adalah hari cuti).
        // Rentang [tanggal_mulai, tanggal_selesai] — melewati akhir pekan & hari libur.
        const computeWorkingDays = (startVal, endVal) => {
            if (!startVal || !endVal) return 0;
            let start = new Date(startVal);
            let end = new Date(endVal);
            if (end < start) return -1;

            let diff = 0;
            let current = new Date(start);
            while (current <= end) {
                let dayOfWeek = current.getDay();
                let tempDate = new Date(current.getTime() - (current.getTimezoneOffset() * 60000));
                let dateString = tempDate.toISOString().split('T')[0];
                let isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
                let isHoliday = holidays.includes(dateString);
                if (!isWeekend && !isHoliday) diff++;
                current.setDate(current.getDate() + 1);
            }
            return diff;
        };

        /* ── Cek tumpang tindih tanggal antar blok (interval tertutup/inclusive [mulai, selesai]) ── */
        const getBlockRange = (block) => {
            const mulai = block.querySelector('.leave-mulai').value;
            const selesai = block.querySelector('.leave-selesai').value;
            if (!mulai || !selesai) return null;
            return { start: mulai, end: selesai };
        };

        const rangesOverlap = (a, b) => (a.start <= b.end) && (b.start <= a.end);

        const checkOverlaps = () => {
            const blocks = Array.from(document.querySelectorAll('.leave-block'));
            let anyOverlap = false;

            blocks.forEach(b => {
                const info = b.querySelector('.leave-overlap-info');
                info.style.display = 'none';
                info.innerHTML = '';
            });

            for (let i = 0; i < blocks.length; i++) {
                const rangeI = getBlockRange(blocks[i]);
                if (!rangeI) continue;
                for (let j = 0; j < i; j++) {
                    const rangeJ = getBlockRange(blocks[j]);
                    if (!rangeJ) continue;
                    if (rangesOverlap(rangeI, rangeJ)) {
                        anyOverlap = true;
                        const info = blocks[i].querySelector('.leave-overlap-info');
                        info.style.display = 'block';
                        info.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i> Tanggal bertumpang tindih dengan Cuti #${j + 1}.`;
                    }
                }
            }
            return !anyOverlap;
        };

        /* ── Satu jenis cuti hanya boleh dipilih di satu blok — nonaktifkan di blok lain ── */
        const updateJenisAvailability = () => {
            const blocks = Array.from(document.querySelectorAll('.leave-block'));
            const selectedValues = blocks
                .map(b => b.querySelector('.leave-jenis').value)
                .filter(v => v);

            blocks.forEach(b => {
                const select = b.querySelector('.leave-jenis');
                const currentVal = select.value;
                Array.from(select.options).forEach(opt => {
                    if (!opt.value) return; // lewati placeholder "— Pilih Jenis Cuti —"
                    opt.disabled = selectedValues.includes(opt.value) && opt.value !== currentVal;
                });
            });
        };

        /* ── Datepicker (flatpickr): tanggal yang sudah dipakai di satu blok didisable di blok lain ──
           Catatan: ini hanya bantuan UX (mencegah memilih tanggal yang PERSIS berada di dalam rentang
           blok lain). checkOverlaps() tetap jadi penjaga utama untuk kasus rentang baru yang "membungkus"
           rentang blok lain tanpa titik ujungnya sendiri jatuh di dalam rentang tsb. */
        function initBlockDatepickers(block) {
            const mulaiInput = block.querySelector('.leave-mulai');
            const selesaiInput = block.querySelector('.leave-selesai');

            flatpickr(mulaiInput, {
                dateFormat: 'Y-m-d',
                minDate: 'today',
                disableMobile: true,
                onChange: () => {
                    handleBlockDateChange(block);
                    refreshAllDatepickersDisabledDates();
                }
            });

            flatpickr(selesaiInput, {
                dateFormat: 'Y-m-d',
                minDate: 'today',
                disableMobile: true,
                onChange: () => {
                    handleBlockDateChange(block);
                    refreshAllDatepickersDisabledDates();
                }
            });
        }

        function destroyBlockDatepickers(block) {
            const mulaiInput = block.querySelector('.leave-mulai');
            const selesaiInput = block.querySelector('.leave-selesai');
            if (mulaiInput._flatpickr) mulaiInput._flatpickr.destroy();
            if (selesaiInput._flatpickr) selesaiInput._flatpickr.destroy();
        }

        function refreshAllDatepickersDisabledDates() {
            const blocks = Array.from(document.querySelectorAll('.leave-block'));
            const withRanges = blocks.map(b => ({ block: b, range: getBlockRange(b) }));

            blocks.forEach(b => {
                const others = withRanges.filter(r => r.block !== b && r.range);
                const disableRanges = others.map(r => ({ from: r.range.start, to: r.range.end }));

                const mulaiInput = b.querySelector('.leave-mulai');
                const selesaiInput = b.querySelector('.leave-selesai');
                if (mulaiInput._flatpickr) mulaiInput._flatpickr.set('disable', disableRanges);
                if (selesaiInput._flatpickr) selesaiInput._flatpickr.set('disable', disableRanges);
            });
        }

        /* ── Membangun 1 blok cuti ── */
        function createLeaveBlockHTML(idx) {
            const optionsHtml = leaveTypeOptions.map(o =>
                `<option value="${o.id}" data-name="${o.name.replace(/"/g, '&quot;')}">${o.name}</option>`
            ).join('');

            return `
            <div class="leave-block border rounded p-3 p-md-4 mb-4 position-relative" data-index="${idx}">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-leave"
                    style="position:absolute; top:.65rem; right:.65rem; display:none;" title="Hapus cuti ini">
                    <i class="fas fa-times"></i>
                </button>

                <div class="font-weight-bold text-primary small mb-3">
                    <i class="fas fa-calendar-alt mr-1"></i> Pengajuan Cuti #${idx + 1}
                </div>

                <div class="form-group mb-4">
                    <label class="font-weight-bold text-gray-700 small">Jenis Cuti <span class="text-danger">*</span></label>
                    <select class="form-control leave-jenis" name="leaves[${idx}][jenis_cuti]" required>
                        <option value="" disabled selected>— Pilih Jenis Cuti —</option>
                        ${optionsHtml}
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group mb-4">
                        <label class="font-weight-bold text-gray-700 small">Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="text" class="form-control leave-mulai" name="leaves[${idx}][tanggal_mulai]"
                            placeholder="Pilih tanggal" autocomplete="off" readonly required>
                    </div>
                    <div class="col-md-6 form-group mb-4">
                        <label class="font-weight-bold text-gray-700 small">Tanggal Selesai <span class="text-danger">*</span></label>
                        <input type="text" class="form-control leave-selesai" name="leaves[${idx}][tanggal_selesai]"
                            placeholder="Pilih tanggal" autocomplete="off" readonly required>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label class="font-weight-bold text-gray-700 small">Jumlah Hari</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light text-primary"><i class="fas fa-calendar-day"></i></span>
                        </div>
                        <input type="text" class="form-control bg-light leave-hari-display" readonly
                            placeholder="Otomatis dihitung...">
                    </div>
                    <small class="form-text mt-2 font-weight-bold leave-sisa-info" style="display:none;"></small>
                    <small class="form-text mt-1 font-weight-bold text-danger leave-overlap-info" style="display:none;"></small>
                </div>

                <div class="form-group mb-0">
                    <label class="font-weight-bold text-gray-700 small">Keterangan / Alasan <span class="text-danger">*</span></label>
                    <textarea class="form-control leave-keterangan" name="leaves[${idx}][keterangan]" rows="3"
                        required placeholder="Alasan cuti..."></textarea>
                </div>
            </div>`;
        }

        function appendLeaveBlock() {
            const idx = leaveIndex++;
            const container = document.getElementById('leaves-container');
            const wrapper = document.createElement('div');
            wrapper.innerHTML = createLeaveBlockHTML(idx);
            const block = wrapper.firstElementChild;
            container.appendChild(block);
            initBlockDatepickers(block);
            updateRemoveButtonsVisibility();
            updateJenisAvailability();
            refreshAllDatepickersDisabledDates();
        }

        function updateRemoveButtonsVisibility() {
            const blocks = document.querySelectorAll('.leave-block');
            blocks.forEach(b => {
                const btn = b.querySelector('.btn-remove-leave');
                btn.style.display = blocks.length > 1 ? 'inline-block' : 'none';
            });
        }

        function renumberBlockLabels() {
            document.querySelectorAll('.leave-block').forEach((b, i) => {
                b.querySelector('.font-weight-bold.text-primary.small').innerHTML =
                    `<i class="fas fa-calendar-alt mr-1"></i> Pengajuan Cuti #${i + 1}`;
            });
        }

        /* ── Hitung hari & cek sisa saldo per blok ── */
        function handleBlockDateChange(block) {
            const jenis = block.querySelector('.leave-jenis');
            const mulai = block.querySelector('.leave-mulai');
            const selesai = block.querySelector('.leave-selesai');
            const hariDisp = block.querySelector('.leave-hari-display');
            const sisaInfo = block.querySelector('.leave-sisa-info');

            // Tanggal selesai tidak boleh sebelum tanggal mulai (boleh sama = cuti 1 hari)
            if (mulai.value && selesai._flatpickr) {
                selesai._flatpickr.set('minDate', mulai.value);
                if (selesai.value && selesai.value < mulai.value) {
                    selesai._flatpickr.clear();
                }
            }

            const diff = computeWorkingDays(mulai.value, selesai.value);

            if (diff > 0) {
                hariDisp.value = `${diff} Hari`;
            } else {
                hariDisp.value = mulai.value && selesai.value ? 'Tgl tdk valid / Libur' : '';
                if (!jenis.value || !mulai.value) {
                    sisaInfo.style.display = 'none';
                }
            }

            if (jenis.value && mulai.value) {
                checkBlockBalance(block, diff);
            }

            checkOverlaps();
        }

        function checkBlockBalance(block, days) {
            const jenis = block.querySelector('.leave-jenis');
            const mulai = block.querySelector('.leave-mulai');
            const selesai = block.querySelector('.leave-selesai');
            const hariDisp = block.querySelector('.leave-hari-display');
            const sisaInfo = block.querySelector('.leave-sisa-info');

            sisaInfo.style.display = 'block';
            sisaInfo.className = 'form-text text-info mt-2 small font-weight-bold leave-sisa-info';
            sisaInfo.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i> Memeriksa sisa cuti...';

            const params = new URLSearchParams({
                npk: employeeNpk,
                leave_type_id: jenis.value,
                start_date: mulai.value
            });

            fetch(`${getBalanceUrl}?${params.toString()}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        sisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold leave-sisa-info';
                        sisaInfo.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Gagal mengecek data cuti.';
                        return;
                    }

                    // Batasi tanggal selesai sesuai sisa saldo cuti yang tersedia
                    if (res.max_end_date) {
                        if (selesai._flatpickr) selesai._flatpickr.set('maxDate', res.max_end_date);
                        selesai.dataset.maxEnd = res.max_end_date;
                        if (selesai.value && selesai.value > res.max_end_date) {
                            if (selesai._flatpickr) selesai._flatpickr.clear();
                            hariDisp.value = '';
                        }
                    } else {
                        if (selesai._flatpickr) selesai._flatpickr.set('maxDate', null);
                        delete selesai.dataset.maxEnd;
                    }

                    const recalculatedDiff = computeWorkingDays(mulai.value, selesai.value);
                    const effectiveDiff = selesai.value ? recalculatedDiff : days;

                    if (res.sisa <= 0) {
                        sisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold leave-sisa-info';
                        sisaInfo.innerHTML = `<i class="fas fa-times-circle mr-1"></i> ${res.keterangan}. Anda tidak memiliki sisa cuti.`;
                    } else if (effectiveDiff > res.sisa) {
                        sisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold leave-sisa-info';
                        sisaInfo.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i> ${res.keterangan}. Sisa cuti tidak mencukupi.`;
                    } else {
                        const maxInfo = res.max_end_date ? ` Maks. tanggal selesai: <strong>${formatDate(res.max_end_date)}</strong>.` : '';
                        sisaInfo.className = 'form-text text-success mt-2 small font-weight-bold leave-sisa-info';
                        sisaInfo.innerHTML = `<i class="fas fa-check-circle mr-1"></i> ${res.keterangan}. Sisa cuti mencukupi.${maxInfo}`;
                    }

                    refreshAllDatepickersDisabledDates();
                }).catch(() => {
                    sisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold leave-sisa-info';
                    sisaInfo.innerHTML = '<i class="fas fa-wifi mr-1"></i> Koneksi bermasalah saat mengecek cuti.';
                });
        }

        /* ── Event delegation: berlaku juga untuk blok yang ditambahkan belakangan ──
           Catatan: perubahan tanggal (.leave-mulai/.leave-selesai) ditangani lewat callback
           onChange flatpickr masing-masing instance (lihat initBlockDatepickers), bukan di sini. */
        document.getElementById('leaves-container').addEventListener('change', function (e) {
            if (e.target.matches('.leave-jenis')) {
                updateJenisAvailability();
                handleBlockDateChange(e.target.closest('.leave-block'));
            }
        });

        document.getElementById('leaves-container').addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.btn-remove-leave');
            if (removeBtn) {
                const block = removeBtn.closest('.leave-block');
                destroyBlockDatepickers(block);
                block.remove();
                updateRemoveButtonsVisibility();
                renumberBlockLabels();
                updateJenisAvailability();
                checkOverlaps();
                refreshAllDatepickersDisabledDates();
            }
        });

        document.getElementById('btnTambahCuti').addEventListener('click', appendLeaveBlock);

        /* ── Panel Navigations ── */
        window.goToReview = () => {
            const blocks = document.querySelectorAll('.leave-block');
            if (blocks.length === 0) {
                alert('Minimal harus ada 1 pengajuan cuti.');
                return;
            }

            if (!checkOverlaps()) {
                alert('Ada tanggal cuti yang tumpang tindih antar pengajuan. Silakan perbaiki tanggalnya terlebih dahulu.');
                return;
            }

            const chosenTypes = [];
            let reviewHtml = '';
            for (const block of blocks) {
                const jenis = block.querySelector('.leave-jenis');
                const mulai = block.querySelector('.leave-mulai');
                const selesai = block.querySelector('.leave-selesai');
                const ket = block.querySelector('.leave-keterangan');
                const nomor = block.querySelector('.font-weight-bold.text-primary.small').textContent.trim();

                if (!jenis.value) { jenis.reportValidity(); return; }
                if (!mulai.value) { mulai.reportValidity(); return; }
                if (!selesai.value) { selesai.reportValidity(); return; }
                if (!ket.value.trim()) { ket.reportValidity(); return; }

                if (chosenTypes.includes(jenis.value)) {
                    alert(`${nomor}: Jenis cuti ini sudah dipilih di pengajuan lain. Setiap jenis cuti hanya boleh dipilih sekali.`);
                    return;
                }
                chosenTypes.push(jenis.value);

                if (mulai.value < todayStr) {
                    alert(`${nomor}: Tanggal mulai tidak boleh sebelum hari ini.`);
                    return;
                }

                const diff = computeWorkingDays(mulai.value, selesai.value);
                if (!diff || diff <= 0) {
                    alert(`${nomor}: Format tanggal tidak valid (tanggal mulai harus lebih awal atau sama dengan tanggal selesai, dan bukan hanya akhir pekan/libur).`);
                    return;
                }

                const maxAttr = selesai.dataset.maxEnd;
                if (maxAttr && selesai.value > maxAttr) {
                    alert(`${nomor}: Tanggal selesai melebihi sisa saldo cuti yang tersedia.`);
                    return;
                }

                const leaveName = jenis.options[jenis.selectedIndex].getAttribute('data-name');

                reviewHtml += `
                <div class="bg-light p-3 p-md-4 rounded mb-3 border">
                    <div class="font-weight-bold text-primary small mb-2"><i class="fas fa-calendar-alt mr-1"></i> ${nomor}</div>
                    <div class="review-row">
                        <span class="review-label">Jenis Cuti</span>
                        <span class="review-value"><span class="badge badge-primary px-2 py-1">${leaveName}</span></span>
                    </div>
                    <div class="review-row">
                        <span class="review-label">Tanggal Mulai</span>
                        <span class="review-value">${formatDate(mulai.value)}</span>
                    </div>
                    <div class="review-row">
                        <span class="review-label">Tanggal Selesai</span>
                        <span class="review-value">${formatDate(selesai.value)}</span>
                    </div>
                    <div class="review-row">
                        <span class="review-label">Total Hari</span>
                        <span class="review-value text-primary">${diff} Hari</span>
                    </div>
                    <div class="review-row">
                        <span class="review-label">Alasan</span>
                        <span class="review-value">${ket.value}</span>
                    </div>
                </div>`;
            }

            document.getElementById('review-container').innerHTML = reviewHtml;
            switchPanel(1, 2, true);
        };

        window.backToForm = () => switchPanel(2, 1, false);

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

        /* ── Inisialisasi: satu blok cuti default saat halaman dimuat ── */
        document.addEventListener('DOMContentLoaded', appendLeaveBlock);
    </script>
</body>

</html>