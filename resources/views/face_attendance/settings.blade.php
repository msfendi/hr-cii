@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h3 mb-1 text-gray-800">Pengaturan Presensi Wajah</h1>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Atur window jam presensi, hari kerja, dan izin akses per karyawan.
            </p>
        </div>
        <a href="{{ route('face-recognition.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Wajah
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    <div class="row">
        <!-- Pengaturan Window & Hari Kerja -->
        <div class="col-xl-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-clock mr-1"></i> Window Jam Presensi &amp; Hari Kerja
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('face.attendance.settings.update-window') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="clock_in_before_minutes" class="font-weight-bold small">
                                        Menit sebelum jam kerja mulai (Clock In)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="clock_in_before_minutes"
                                            name="clock_in_before_minutes" min="0" max="240" step="1"
                                            value="{{ old('clock_in_before_minutes', $setting->clock_in_before_minutes) }}" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">menit</span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Contoh: 15 berarti Clock In dibuka pukul 07:45 untuk shift 08:00.
                                    </small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="clock_out_after_minutes" class="font-weight-bold small">
                                        Menit setelah jam kerja selesai (Clock Out)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="clock_out_after_minutes"
                                            name="clock_out_after_minutes" min="0" max="240" step="1"
                                            value="{{ old('clock_out_after_minutes', $setting->clock_out_after_minutes) }}" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">menit</span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Contoh: 15 berarti Clock Out ditutup pukul 17:15 untuk shift 17:00.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <p class="font-weight-bold small text-gray-700 mb-2">Hari Kerja Presensi Wajah</p>

                        <div class="custom-control custom-switch mb-2">
                            <input type="hidden" name="is_saturday_enabled" value="0">
                            <input class="custom-control-input" type="checkbox" id="is_saturday_enabled"
                                name="is_saturday_enabled" value="1"
                                {{ old('is_saturday_enabled', $setting->is_saturday_enabled) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_saturday_enabled">
                                Sabtu <span class="text-muted">(bisa ON/OFF)</span>
                            </label>
                        </div>

                        <div class="custom-control custom-switch mb-2">
                            <input type="hidden" name="is_sunday_enabled" value="0">
                            <input class="custom-control-input" type="checkbox" id="is_sunday_enabled"
                                name="is_sunday_enabled" value="1"
                                {{ old('is_sunday_enabled', $setting->is_sunday_enabled) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_sunday_enabled">
                                Minggu <span class="text-muted">(bisa ON/OFF)</span>
                            </label>
                        </div>

                        <div class="custom-control custom-switch mb-3">
                            <input type="hidden" name="is_holiday_enabled" value="0">
                            <input class="custom-control-input" type="checkbox" id="is_holiday_enabled"
                                name="is_holiday_enabled" value="1"
                                {{ old('is_holiday_enabled', $setting->is_holiday_enabled) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_holiday_enabled">
                                Hari Libur <span class="text-muted">(bisa ON/OFF, tanggal dari tabel holidays)</span>
                            </label>
                        </div>

                        <div class="alert alert-secondary py-2 px-3 small mb-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            Senin sampai Jumat <strong>selalu aktif</strong>. Hari libur mengikuti toggle di atas
                            dan tanggalnya diambil dari tabel <code>holidays</code> pada database CII.
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save mr-1"></i> Simpan Pengaturan Window
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daftar Hari Libur -->
        <div class="col-xl-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calendar-times mr-1"></i> Daftar Hari Libur (tabel <code>holidays</code>)
                    </h6>
                </div>
                <div class="card-body p-0">
                    @if($holidays->count() > 0)
                        <div class="table-responsive" style="max-height: 420px;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="thead-light sticky-top">
                                    <tr>
                                        <th class="px-3">Tanggal</th>
                                        <th>Nama Hari Libur</th>
                                        <th class="text-center px-3">Jenis</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($holidays as $holiday)
                                        <tr>
                                            <td class="px-3 text-nowrap">
                                                {{ $holiday->holiday_date ? \Carbon\Carbon::parse($holiday->holiday_date)->format('d-m-Y') : '-' }}
                                            </td>
                                            <td>{{ $holiday->name ?: '-' }}</td>
                                            <td class="text-center px-3">
                                                @if($holiday->is_national)
                                                    <span class="badge badge-danger">Nasional</span>
                                                @else
                                                    <span class="badge badge-secondary">Internal</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-calendar-times fa-3x mb-3"></i>
                            <p class="mb-0">Data hari libur tidak ditemukan atau tabel <code>holidays</code> belum tersedia.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Akses Presensi per Karyawan -->
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-users-cog mr-1"></i> Izin Presensi Wajah per Karyawan
                    </h6>
                    <span class="small text-muted">
                        Total karyawan terdaftar: <strong>{{ $employeeFaces->count() }}</strong>
                    </span>
                </div>
                <div class="card-body p-0">
                    @if($employeeFaces->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="px-3">NPK</th>
                                        <th>Nama</th>
                                        <th class="text-center">Presensi</th>
                                        <th class="text-center">Scope Lokasi</th>
                                        <th class="text-center px-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employeeFaces as $face)
                                        @php
                                            $scope = $face->location_scope ?: 'all';
                                            $allowed = $face->allowed_location_id_list;
                                        @endphp
                                        <tr>
                                            <td class="px-3"><strong>{{ $face->npk }}</strong></td>
                                            <td>{{ $face->name ?: '-' }}</td>
                                            <td class="text-center">
                                                @if($face->is_attendance_enabled)
                                                    <span class="badge badge-success"><i class="fas fa-check mr-1"></i>ON</span>
                                                @else
                                                    <span class="badge badge-secondary"><i class="fas fa-times mr-1"></i>OFF</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($scope === 'all')
                                                    <span class="badge badge-info">Semua Lokasi</span>
                                                @else
                                                    <span class="badge badge-warning">{{ count($allowed) }} Lokasi Dipilih</span>
                                                @endif
                                            </td>
                                            <td class="text-center px-3">
                                                <button type="button" class="btn btn-sm btn-primary btn-manage-face-access"
                                                    data-id="{{ $face->id }}"
                                                    data-npk="{{ $face->npk }}"
                                                    data-name="{{ $face->name }}"
                                                    data-enabled="{{ $face->is_attendance_enabled ? '1' : '0' }}"
                                                    data-scope="{{ $scope }}"
                                                    data-allowed="{{ json_encode($allowed) }}"
                                                    data-url="{{ route('face.attendance.settings.update-employee', $face->id) }}">
                                                    <i class="fas fa-sliders-h mr-1"></i> Kelola Akses
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-user-slash fa-3x mb-3"></i>
                            <p class="mb-0">Belum ada karyawan terdaftar pada face recognition.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Kelola Akses Presensi per Karyawan -->
<div class="modal fade" id="faceAccessModal" tabindex="-1" role="dialog" aria-labelledby="faceAccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-3">
                <h6 class="modal-title font-weight-bold" id="faceAccessModalLabel">
                    <i class="fas fa-sliders-h mr-1"></i> Kelola Izin Presensi Wajah
                </h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="faceAccessForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2 px-3 small">
                        Karyawan: <strong id="faNpk"></strong> &mdash; <span id="faName"></span>
                    </div>

                    <div class="custom-control custom-switch mb-3">
                        <input type="hidden" name="is_attendance_enabled" id="faEnabledValue" value="0">
                        <input class="custom-control-input" type="checkbox" id="faEnabled"
                            name="is_attendance_enabled" value="1">
                        <label class="custom-control-label font-weight-bold" for="faEnabled">
                            Izinkan Presensi Wajah untuk karyawan ini
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Cakupan Lokasi Geo Map</label>
                        <div class="custom-control custom-radio mb-1">
                            <input class="custom-control-input" type="radio" id="faScopeAll" name="location_scope" value="all" checked>
                            <label class="custom-control-label" for="faScopeAll">Semua Lokasi</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input class="custom-control-input" type="radio" id="faScopeSelected" name="location_scope" value="selected">
                            <label class="custom-control-label" for="faScopeSelected">Hanya Lokasi yang Dipilih</label>
                        </div>
                    </div>

                    <div class="form-group" id="faLocationPicker" style="display:none;">
                        <label class="font-weight-bold small">Pilih Lokasi Presensi</label>
                        @if($locations->count() > 0)
                            @foreach($locations as $loc)
                                <div class="custom-control custom-checkbox mb-1">
                                    <input class="custom-control-input" type="checkbox" id="faLoc{{ $loc->id }}"
                                        name="allowed_location_ids[]" value="{{ $loc->id }}">
                                    <label class="custom-control-label" for="faLoc{{ $loc->id }}">
                                        {{ $loc->name }}
                                        @if(!$loc->is_active)
                                            <span class="badge badge-secondary ml-1">Nonaktif</span>
                                        @endif
                                    </label>
                                </div>
                            @endforeach
                        @else
                            <div class="alert alert-warning py-2 px-3 small mb-0">
                                Belum ada lokasi presensi. Tambahkan lokasi pada menu Manage Geo Map terlebih dahulu.
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('faceAccessModal');
    if (!modal) return;

    const form = document.getElementById('faceAccessForm');
    const scopeAll = document.getElementById('faScopeAll');
    const scopeSelected = document.getElementById('faScopeSelected');
    const picker = document.getElementById('faLocationPicker');
    const enabled = document.getElementById('faEnabled');
    const enabledValue = document.getElementById('faEnabledValue');

    function togglePicker() {
        picker.style.display = scopeSelected.checked ? 'block' : 'none';
    }

    scopeAll.addEventListener('change', togglePicker);
    scopeSelected.addEventListener('change', togglePicker);

    enabled.addEventListener('change', function () {
        enabledValue.value = enabled.checked ? '1' : '0';
    });

    document.querySelectorAll('.btn-manage-face-access').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const d = btn.dataset;

            form.setAttribute('action', d.url);
            document.getElementById('faNpk').textContent = d.npk || '-';
            document.getElementById('faName').textContent = d.name || '-';

            const isEnabled = d.enabled === '1';
            enabled.checked = isEnabled;
            enabledValue.value = isEnabled ? '1' : '0';

            const scope = d.scope || 'all';
            scopeAll.checked = scope !== 'selected';
            scopeSelected.checked = scope === 'selected';

            let allowed = [];
            try {
                allowed = JSON.parse(d.allowed || '[]');
            } catch (e) {
                allowed = [];
            }

            document.querySelectorAll('[id^="faLoc"]').forEach(function (cb) {
                cb.checked = allowed.indexOf(parseInt(cb.value, 10)) !== -1;
            });

            togglePicker();
            $(modal).modal('show');
        });
    });
})();
</script>
@endsection
