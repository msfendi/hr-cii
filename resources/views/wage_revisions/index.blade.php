<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<body id="page-top">
    @include('sweetalert::alert')

    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')

                <div class="container-fluid px-4">

                    {{-- Page Header --}}
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h4 class="mb-0 font-weight-bold text-gray-800">Penyesuaian Upah Tahunan (UMR)</h4>
                            <small class="text-muted">Naikkan gaji pokok karyawan tanpa mengubah perhitungan payroll</small>
                        </div>
                        @if ($canCreate)
                            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalBuat">
                                <i class="fas fa-plus mr-1"></i> Buat Batch
                            </button>
                        @endif
                    </div>

                    @if (session('import_report'))
                        <div class="card border-left-info shadow-sm mb-3">
                            <div class="card-body py-2">
                                <strong class="small">Hasil import terakhir</strong>
                                <div class="table-responsive mt-2" style="max-height:220px;overflow:auto;">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="thead-light">
                                            <tr><th>NPK</th><th>Status</th><th>Pesan</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach (session('import_report') as $r)
                                                <tr>
                                                    <td>{{ $r['npk'] }}</td>
                                                    <td>
                                                        <span class="badge badge-{{ $r['status'] === 'valid' ? 'success' : ($r['status'] === 'skipped' ? 'secondary' : 'danger') }}">
                                                            {{ $r['status'] }}
                                                        </span>
                                                    </td>
                                                    <td class="small">{{ $r['message'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (!$canCreate)
                        <div class="alert alert-warning py-2 small">
                            <i class="fas fa-lock mr-1"></i>
                            Akun Anda belum terdaftar pada role payroll, jadi hanya bisa melihat batch yang ada.
                        </div>
                    @endif

                    {{-- Tabel batch --}}
                    <div class="card shadow-sm mb-4">
                        <div class="card-body px-3 pt-3 pb-2">
                            <div class="table-responsive">
                                <table id="tblBatch" class="table table-bordered table-hover table-sm mb-0" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="pl-3">Tahun</th>
                                            <th>Tanggal Efektif</th>
                                            <th class="text-right">UMR Baru (acuan)</th>
                                            <th class="text-center">Non-Staff</th>
                                            <th class="text-center">Staff/Expat</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-right">Selisih/Bulan</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($batches as $b)
                                            @php
                                                $items = $b->items;
                                                $statusColor = [
                                                    'draft' => 'secondary',
                                                    'simulated' => 'info',
                                                    'approved' => 'primary',
                                                    'applying' => 'warning',
                                                    'applied' => 'success',
                                                    'rolled_back' => 'dark',
                                                    'cancelled' => 'secondary',
                                                ][$b->status] ?? 'secondary';
                                            @endphp
                                            <tr>
                                                <td class="pl-3 font-weight-bold">{{ $b->year }}</td>
                                                <td>{{ $b->effective_date ? $b->effective_date->format('d-m-Y') : '—' }}</td>
                                                <td class="text-right" data-order="{{ (float) $b->umr_new }}">Rp&nbsp;{{ number_format((float) $b->umr_new, 0, ',', '.') }}</td>
                                                <td class="text-center">{{ $items->where('segment', 'non_staff')->count() }}</td>
                                                <td class="text-center">{{ $items->where('segment', 'staff_expat')->count() }}</td>
                                                <td class="text-center"><span class="badge badge-{{ $statusColor }}">{{ $b->status }}</span></td>
                                                <td class="text-right" data-order="{{ (float) $items->sum('delta_monthly') }}">Rp&nbsp;{{ number_format((float) $items->sum('delta_monthly'), 0, ',', '.') }}</td>
                                                <td class="text-center">
                                                    <a href="{{ route('wage-revisions.show', $b->id) }}" class="btn btn-primary btn-sm" title="Detail">
                                                        <i class="fas fa-folder-open"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">
                                                    Belum ada batch. Klik <strong>Buat Batch</strong> untuk memulai penyesuaian upah tahunan.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>{{-- /container --}}
            </div>
            @include('layout.footer')
        </div>
    </div>

    @if ($canCreate)
        {{-- MODAL: BUAT BATCH --}}
        <div class="modal fade" id="modalBuat" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('wage-revisions.store') }}">
                        @csrf
                        <div class="modal-header bg-primary text-white py-3">
                            <h6 class="modal-title mb-0"><i class="fas fa-plus-circle mr-2"></i>Buat Batch Penyesuaian Upah</h6>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="small font-weight-bold">Tahun <span class="text-danger">*</span></label>
                                        <input type="number" name="year" class="form-control form-control-sm"
                                            value="{{ old('year', date('Y') + 1) }}" min="2020" max="2100" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="small font-weight-bold">Tanggal Efektif <span class="text-danger">*</span></label>
                                        <input type="date" name="effective_date" class="form-control form-control-sm"
                                            value="{{ old('effective_date', (date('Y') + 1) . '-01-01') }}" required>
                                        <small class="text-muted">Disimpan sebagai tanggal 1 bulan tersebut.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="small font-weight-bold">UMR Baru (acuan peringatan) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="number" name="umr_new" class="form-control" step="1" min="0"
                                        value="{{ old('umr_new') }}" required>
                                </div>
                                <small class="text-muted">Hanya untuk menandai gaji yang masih di bawah UMR, bukan pengunci gaji.</small>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="small font-weight-bold">Kenaikan Non-Staff (%) <span class="text-danger">*</span></label>
                                        <input type="number" name="percent" class="form-control form-control-sm" step="0.001"
                                            min="0" max="100" value="{{ old('percent', 0) }}" required>
                                        <small class="text-muted">Satu persentase untuk semua karyawan non-staff.</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="small font-weight-bold">Pembulatan <span class="text-danger">*</span></label>
                                        <select name="round_to" class="form-control form-control-sm" required>
                                            @foreach ([1, 10, 50, 100, 500, 1000] as $step)
                                                <option value="{{ $step }}" {{ (int) old('round_to', 100) === $step ? 'selected' : '' }}>
                                                    Rp {{ number_format($step, 0, ',', '.') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold">Catatan</label>
                                <input type="text" name="note" class="form-control form-control-sm" maxlength="255" value="{{ old('note') }}">
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

{{-- DataTables: tabel batch bisa dicari, diurutkan, dan dipaginasi --}}
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    @if ($batches->isNotEmpty())
        <script>
            $(function () {
                if (!$.fn.DataTable) { return; }
                $('#tblBatch').DataTable({
                    order: [[0, 'desc']],
                    pageLength: 10,
                    autoWidth: false,
                    columnDefs: [{ orderable: false, targets: [7] }],
                    language: {
                        search: 'Cari:',
                        lengthMenu: 'Tampilkan _MENU_ baris',
                        info: 'Menampilkan _START_–_END_ dari _TOTAL_ batch',
                        infoEmpty: 'Tidak ada batch',
                        infoFiltered: '(disaring dari _MAX_ batch)',
                        zeroRecords: 'Batch tidak ditemukan',
                        paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' }
                    }
                });
            });
        </script>
    @endif

</body>

</html>
