@extends('layouts.app')

@section('title', 'Setting Organization Chart')

@section('content')
<div class="container-fluid ocs-wrap">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800"><i class="fas fa-sitemap mr-2"></i>Setting Organization Chart</h1>
        </div>
        <a href="{{ route('org-chart.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            <i class="fas fa-project-diagram mr-1"></i> Lihat Chart
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <strong>Periksa input:</strong>
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if (!($labelColumn ?? true))
        <div class="alert alert-warning">
            <strong>Kolom <code>label</code> belum ada di tabel <code>org_chart_settings</code>.</strong>
            Sementara nama Company memakai nilai bawaan
            <em>{{ \App\Models\OrgChartSetting::COMPANY_NAME_FALLBACK }}</em>.
            Jalankan <code>php artisan migrate</code> di server supaya kolomnya dibuat permanen.
        </div>
    @endif

    <div class="row">
        {{-- Form tambah node --}}
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle mr-1"></i> Tambah Node</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('org-chart.settings.store') }}">
                        @csrf
                        <div class="form-group">
                            <label class="small font-weight-bold">ID_DEPT <span class="text-danger js-dept-star">*</span></label>
                            <select name="id_dept" class="form-control form-control-sm select2-dept" data-placeholder="-- cari &amp; pilih department --">
                                <option value=""></option>
                                @foreach ($departments as $d)
                                    <option value="{{ $d->ID_DEPT }}" {{ old('id_dept') == $d->ID_DEPT ? 'selected' : '' }}>
                                        {{ $d->DEPARTEMENT }} ({{ $d->ID_DEPT }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Sumber: DEPT.ID_DEPT &mdash; <strong>wajib untuk level Department</strong>, boleh kosong untuk level Company.</small>
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">Level <span class="text-danger">*</span></label>
                            <select name="level" class="form-control form-control-sm js-level" required>
                                <option value="">-- pilih level --</option>
                                @foreach ($levels as $val => $label)
                                    <option value="{{ $val }}" {{ old('level') == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Hanya Company dan Department.</small>
                        </div>
                        <div class="form-group js-label-group">
                            <label class="small font-weight-bold">Nama Company <span class="text-danger js-label-star">*</span></label>
                            <input type="text" name="label" class="form-control form-control-sm" maxlength="150"
                                placeholder="mis. PT. CHUTEX INTERNATIONAL INDONESIA" value="{{ old('label') }}">
                            <small class="text-muted">Dipakai sebagai nama node level <strong>Company</strong> (level tertinggi) — <strong>wajib diisi</strong>, tidak hardcode.</small>
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">Parent Node</label>
                            <select name="parent_id" class="form-control form-control-sm select2-parent" data-placeholder="-- tanpa parent (root) --">
                                <option value="">-- tanpa parent (root) --</option>
                                @foreach ($settings as $s)
                                    <option value="{{ $s->id }}" {{ old('parent_id') == $s->id ? 'selected' : '' }}>
                                        #{{ $s->id }} - {{ $s->display_name }} ({{ $s->level_label }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label class="small font-weight-bold">Urutan</label>
                                <input type="number" name="sort_order" class="form-control form-control-sm" min="0" value="{{ old('sort_order', 0) }}">
                            </div>
                            <div class="form-group col-6 d-flex align-items-end">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="custom-control-label small" for="is_active">Aktif</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">
                            <i class="fas fa-save mr-1"></i> Simpan Node
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Daftar node (DataTables) --}}
        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-1"></i> Node Terdaftar ({{ $settings->count() }})</h6>
                </div>
                <div class="card-body">
                    @if ($settings->isEmpty())
                        <div class="text-center text-muted py-4">
                            Belum ada node. Tambahkan ID_DEPT + level di form kiri.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-bordered" id="nodeTable" style="width:100%;">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>ID_DEPT</th>
                                        <th>Nama Dept</th>
                                        <th>Nama Company</th>
                                        <th>Level</th>
                                        <th>Parent</th>
                                        <th>Urutan</th>
                                        <th>Status</th>
                                        <th class="text-right" data-orderable="false">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($settings as $s)
                                    <tr>
                                        <td>{{ $s->id }}</td>
                                        <td><code>{{ $s->id_dept ?: '-' }}</code></td>
                                        <td>{{ ($s->id_dept && isset($deptNames[$s->id_dept])) ? $deptNames[$s->id_dept] : '-' }}</td>
                                        <td>
                                            @if ($s->level === 'company')
                                                <span class="font-weight-bold" style="color:#1d3f86;">{{ $s->display_name }}</span>
                                            @else
                                                {{ $s->label ?: '-' }}
                                            @endif
                                        </td>
                                        <td><span class="badge badge-info">{{ $s->level_label }}</span></td>
                                        <td>{{ $s->parent_id ? '#' . $s->parent_id : '-' }}</td>
                                        <td>{{ $s->sort_order }}</td>
                                        <td>
                                            @if ($s->is_active)
                                                <span class="badge badge-success">Aktif</span>
                                            @else
                                                <span class="badge badge-secondary">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="text-right text-nowrap">
                                            <button type="button" class="btn btn-warning btn-sm py-0 px-2 js-edit-node"
                                                data-toggle="modal" data-target="#editModal{{ $s->id }}" title="Edit node">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <form method="POST" action="{{ route('org-chart.settings.destroy', $s->id) }}"
                                                class="d-inline js-delete-node"
                                                data-label="#{{ $s->id }} {{ $s->display_name }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm py-0 px-2" title="Hapus node">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         MODAL EDIT — dirender DI LUAR <table>.
         Sebelumnya modal berada di dalam <tbody>, sehingga browser
         mengangkat node modal keluar dari tabel dan popup-nya muncul
         melayang menutupi baris lain (rusak).
         ============================================================ --}}
    @foreach ($settings as $s)
        <div class="modal fade" id="editModal{{ $s->id }}" tabindex="-1" role="dialog" aria-labelledby="editModalLabel{{ $s->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('org-chart.settings.update', $s->id) }}" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header py-2">
                        <h6 class="modal-title font-weight-bold" id="editModalLabel{{ $s->id }}">
                            Edit Node #{{ $s->id }} — {{ $s->display_name }}
                        </h6>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="small font-weight-bold">ID_DEPT <span class="text-danger js-dept-star">*</span></label>
                            <select name="id_dept" class="form-control form-control-sm select2-dept" data-placeholder="-- cari &amp; pilih department --">
                                @foreach ($departments as $d)
                                    <option value="{{ $d->ID_DEPT }}" {{ $s->id_dept == $d->ID_DEPT ? 'selected' : '' }}>
                                        {{ $d->DEPARTEMENT }} ({{ $d->ID_DEPT }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Wajib untuk level Department; boleh kosong untuk level Company.</small>
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">Level <span class="text-danger">*</span></label>
                            <select name="level" class="form-control form-control-sm js-level" required>
                                @foreach ($levels as $val => $label)
                                    <option value="{{ $val }}" {{ $s->level == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group js-label-group">
                            <label class="small font-weight-bold">Nama Company <span class="text-danger js-label-star">*</span></label>
                            <input type="text" name="label" class="form-control form-control-sm" maxlength="150"
                                placeholder="mis. PT. CHUTEX INTERNATIONAL INDONESIA" value="{{ $s->label }}">
                            <small class="text-muted">Nama node level Company (level tertinggi) dari input ini — wajib diisi, bukan dari ID_DEPT.</small>
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">Parent Node</label>
                            <select name="parent_id" class="form-control form-control-sm select2-parent" data-placeholder="-- tanpa parent (root) --">
                                <option value="">-- tanpa parent (root) --</option>
                                @foreach ($settings as $opt)
                                    @if ($opt->id !== $s->id)
                                        <option value="{{ $opt->id }}" {{ $s->parent_id == $opt->id ? 'selected' : '' }}>
                                            #{{ $opt->id }} - {{ $opt->display_name }} ({{ $opt->level_label }})
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label class="small font-weight-bold">Urutan</label>
                                <input type="number" name="sort_order" class="form-control form-control-sm" min="0" value="{{ $s->sort_order }}">
                            </div>
                            <div class="form-group col-6 d-flex align-items-end">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="editActive{{ $s->id }}" name="is_active" value="1" {{ $s->is_active ? 'checked' : '' }}>
                                    <label class="custom-control-label small" for="editActive{{ $s->id }}">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection

@push('styles')
<style>
    /* ============================================================
       RUANG (spacing) halaman Setting Org Chart — 2026-11-11
       Sebelumnya semua elemen rapat: label-input berdempet, sel tabel
       tipis, kontrol DataTables menempel card. Berikut penyesuaiannya.
       ============================================================ */
    .ocs-wrap .card { border: 1px solid #e8ecf1; }
    .ocs-wrap .card-header { padding: 1.15rem 1.5rem; }
    .ocs-wrap .card-body { padding: 1.75rem 1.6rem 1.5rem; }

    /* Header halaman */
    .ocs-wrap h1.h3 { margin-bottom: .5rem !important; }
    .ocs-wrap .d-sm-flex.align-items-center.justify-content-between { margin-bottom: 2.1rem !important; }
    .ocs-wrap .d-sm-flex .small { line-height: 1.5; }

    /* Label & input */
    .ocs-wrap .form-group { margin-bottom: 1.4rem; }
    .ocs-wrap .form-group > label { margin-bottom: .5rem; display: block; }
    .ocs-wrap .form-group small.text-muted { display: block; margin-top: .35rem; line-height: 1.4; }
    .ocs-wrap .form-control-sm { height: calc(2rem + 2px); padding: .5rem .8rem; }
    .ocs-wrap .select2-container--default .select2-selection--single {
        height: 38px;
        padding: .35rem .45rem;
    }
    .ocs-wrap .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 26px;
    }
    .ocs-wrap .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
        right: 6px;
    }
    .ocs-wrap .custom-switch { padding-left: 2.6rem; padding-top: .2rem; }
    .ocs-wrap .btn { padding: .45rem 1.05rem; }
    .ocs-wrap .btn-block, .ocs-wrap .btn.w-100 { margin-top: .35rem; }

    /* Tabel node + kontrol DataTables */
    .ocs-wrap #nodeTable { margin-bottom: .35rem; }
    .ocs-wrap #nodeTable thead th { padding: 1rem 1.1rem; border-bottom-width: 2px; }
    .ocs-wrap #nodeTable tbody td { padding: 1rem 1.1rem; vertical-align: middle; }
    .ocs-wrap .dataTables_wrapper .row:first-child { margin-bottom: 1.35rem; align-items: center; }
    .ocs-wrap .dataTables_wrapper .row:last-child { margin-top: 1.35rem; align-items: center; }
    .ocs-wrap .dataTables_wrapper .dataTables_filter input {
        padding: .5rem .8rem;
        margin-left: .55rem;
        border: 1px solid #d7dde5;
        border-radius: 6px;
    }
    .ocs-wrap .dataTables_wrapper .dataTables_length select {
        padding: .35rem 1.6rem .35rem .65rem;
        margin: 0 .4rem;
    }
    .ocs-wrap .dataTables_wrapper .pagination { margin-bottom: 0; }
    .ocs-wrap .badge { padding: .45em .75em; font-weight: 600; }
    .ocs-wrap td .btn { padding: .38rem .6rem; }
    .ocs-wrap td form.js-delete-node { margin-left: .6rem; }

    /* Modal edit */
    .ocs-wrap + .modal .modal-header,
    .modal .modal-header { padding: 1rem 1.25rem; }
    .modal .modal-body { padding: 1.35rem 1.25rem .5rem; }
    .modal .modal-footer { padding: .85rem 1.25rem 1.1rem; }
</style>
@endpush

@push('scripts')
{{-- SweetAlert2 versi lokal (offline-friendly). File vendor mengekspos
     window.Sweetalert2, bukan window.Swal, jadi di JS dipakai alias.
     Catatan: jQuery + DataTables sudah dimuat layout, jadi tidak dimuat ulang
     di sini (memuat dua kali bisa memicu inisialisasi ganda). --}}
<script src="{{ asset('vendor/sweetalert/sweetalert.all.js') }}"></script>
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script>
$(function () {
    // Halaman ini hanya boleh memasang handler SEKALI. Bila blok script ikut
    // ter-render ganda (pernah terjadi karena directive Blade di dalam komentar
    // HTML/CSS), handler jadi dobel: DataTables init dobel + submit dobel.
    if (window.__orgChartSettingsReady) return;
    window.__orgChartSettingsReady = true;

    // --- Select2: ID_DEPT & Parent Node (bisa diketik) ---
    // dropdownParent wajib untuk select di dalam modal, kalau tidak dropdown
    // muncul di belakang backdrop.
    $('.select2-dept, .select2-parent').each(function () {
        var $el = $(this);
        var $modal = $el.closest('.modal');
        $el.select2({
            width: '100%',
            allowClear: true,
            placeholder: $el.attr('data-placeholder') || '-- pilih --',
            dropdownParent: $modal.length ? $modal : $(document.body)
        });
    });

    // --- Level menentukan field: Company pakai Nama Company, Department pakai ID_DEPT ---
    // Level tertinggi (Company) memakai nama company manual, bukan nama dept.
    function syncLevelFields(scope) {
        var $scope = $(scope);
        var level = String($scope.find('.js-level').val() || '').toLowerCase();
        var isCompany = level === 'company';

        $scope.find('.js-label-group').toggle(isCompany);
        $scope.find('.js-dept-star').toggle(!isCompany);
        $scope.find('.js-label-star').toggle(isCompany);
        // ID_DEPT wajib hanya untuk Department.
        $scope.find('select[name="id_dept"]').prop('required', !isCompany);
    }

    $('.js-level').each(function () { syncLevelFields($(this).closest('form')); });
    $(document).on('change', '.js-level', function () { syncLevelFields($(this).closest('form')); });

    // --- DataTables: pencarian, urut, dan pagination pada Node Terdaftar ---
    // Tabel diinisialisasi TEPAT SEKALI.
    // Bila init dijalankan dua kali (script ter-render ganda, back/forward cache,
    // atau instance sudah dibuat script lain) DataTables melempar warning
    // "Cannot reinitialise DataTable" (datatables.net/tn/3). Karena itu:
    //   1. instance yang sudah ada dilewati (isDataTable -> return),
    //   2. option destroy:true dipasang sebagai jaring pengaman agar init ulang
    //      (kalau sampai terjadi) menghancurkan instance lama tanpa warning.
    (function initNodeTable() {
        var table = document.getElementById('nodeTable');
        if (!table || !window.jQuery || !$.fn || !$.fn.DataTable) return;
        if ($.fn.DataTable.isDataTable(table)) return;

        $(table).DataTable({
            destroy: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: [8] },
                { className: 'text-right text-nowrap', targets: [8] }
            ],
            language: {
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ node',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ node',
                infoEmpty: 'Tidak ada node',
                infoFiltered: '(difilter dari _MAX_ total node)',
                zeroRecords: 'Node tidak ditemukan',
                emptyTable: 'Belum ada node',
                paginate: { first: 'Awal', last: 'Akhir', next: 'Berikut', previous: 'Sebelum' }
            }
        });
    })();

    // --- Hapus node dengan konfirmasi SweetAlert2 ---
    // Catatan: vendor lokal mengekspos window.Sweetalert2 (bukan window.Swal).
    // Kalau dua-duanya tidak ada (mis. JS gagal dimuat), pakai confirm() bawaan
    // browser supaya tombol hapus tidak mati.
    $(document).on('submit', 'form.js-delete-node', function (e) {
        e.preventDefault();
        var form = this;
        var label = $(form).data('label') || 'node ini';
        var SwalNS = window.Swal || window.Sweetalert2;

        if (!SwalNS || typeof SwalNS.fire !== 'function') {
            if (window.confirm('Hapus node ' + label + '?')) {
                HTMLFormElement.prototype.submit.call(form);
            }
            return;
        }

        SwalNS.fire({
            icon: 'warning',
            title: 'Hapus node?',
            html: 'Node <strong>' + label + '</strong> akan dihapus.<br>' +
                  '<small class="text-muted">Child node otomatis dipindahkan ke parent node ini.</small>',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            focusCancel: true
        }).then(function (res) {
            if (res.isConfirmed) {
                // submit native supaya handler ini tidak terpanggil ulang
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    });
});
</script>
@endpush