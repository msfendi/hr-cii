<!DOCTYPE html>
<html lang="id">
@include('layout.header')
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<style>
    div.dataTables_wrapper div.dataTables_filter input {
        border-radius: 20px;
        padding: 5px 15px;
        border: 1px solid #d1d3e2;
    }
    .table-rekap th {
        background-color: #4e73df;
        color: white;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        border: none;
    }
</style>

<body id="page-top">
@include('sweetalert::alert')

<div id="wrapper">
    @if(Session::has('face_attendance_npk') || auth()->check())
        @include('layout.sidebar')
    @endif

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @if(Session::has('face_attendance_npk') || auth()->check())
                @include('layout.navbar')
            @endif

            <div class="container-fluid py-3">

                <!-- Header -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                        <i class="fas fa-history text-primary mr-2"></i> Rekapitulasi Presensi Karyawan
                    </h1>
                    <div class="mt-2 mt-sm-0">
                        <a href="{{ route('face.attendance.index') }}" class="btn btn-sm btn-primary shadow-sm rounded-pill px-3">
                            <i class="fas fa-camera fa-sm text-white-50 mr-1"></i> Kamera Presensi
                        </a>
                    </div>
                </div>

                <!-- Card Profil Ringkas -->
                <div class="card shadow mb-4 border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8 mb-3 mb-md-0">
                                <h5 class="font-weight-bold text-gray-900 mb-1">{{ $biodata->NAMA_KARYAWAN ?? 'Karyawan' }}</h5>
                                <span class="badge badge-light border text-muted mr-2">NPK: {{ $biodata->NPK ?? '-' }}</span>
                                <span class="badge badge-info">{{ $biodata->DEPT ?? '-' }}</span>
                            </div>
                            <div class="col-md-4 text-md-right">
                                <form method="GET" action="{{ route('face.attendance.rekap') }}" class="form-inline justify-content-md-end">
                                    <label class="mr-2 font-weight-bold text-gray-700 small">Pilih Bulan:</label>
                                    <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm rounded-pill mr-2" onchange="this.form.submit()">
                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">
                                        <i class="fas fa-filter mr-1"></i> Filter
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Table Rekapitulasi Presensi -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-white d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-calendar-alt mr-1"></i> Log Presensi Bulan {{ \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y') }}
                        </h6>
                        <span class="badge badge-primary px-3 py-1 font-weight-bold">
                            Total: {{ count($groupedLogs) }} Hari Kerja
                        </span>
                    </div>
                    <div class="card-body">
                        
                        <!-- Tampilan Desktop (DataTables) -->
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-bordered table-hover table-rekap" id="tableRekapDesktop" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="5%" class="text-center">#</th>
                                        <th width="20%">Tanggal & Hari</th>
                                        <th width="20%" class="text-center">Jam Masuk (Clock In)</th>
                                        <th width="20%" class="text-center">Jam Pulang (Clock Out)</th>
                                        <th width="20%" class="text-center">Mode Presensi</th>
                                        <th width="15%" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $no = 1; @endphp
                                    @forelse($groupedLogs as $log)
                                    <tr>
                                        <td class="text-center font-weight-bold">{{ $no++ }}</td>
                                        <td>
                                            <span class="font-weight-bold text-gray-900 d-block">{{ $log['formatted_date'] }}</span>
                                            <small class="text-muted">{{ $log['day_name'] }}</small>
                                        </td>
                                        <td class="text-center">
                                            @if($log['clock_in'])
                                                <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 0.85rem;">
                                                    <i class="fas fa-sign-in-alt mr-1"></i> {{ $log['clock_in'] }}
                                                </span>
                                            @else
                                                <span class="badge badge-light border text-muted px-2 py-1">Belum Scan</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($log['clock_out'])
                                                <span class="badge badge-warning px-3 py-2 font-weight-bold text-dark" style="font-size: 0.85rem;">
                                                    <i class="fas fa-sign-out-alt mr-1"></i> {{ $log['clock_out'] }}
                                                </span>
                                            @else
                                                <span class="badge badge-light border text-muted px-2 py-1">Belum Scan</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @foreach($log['modes'] as $mode)
                                                <span class="badge badge-{{ $mode == 'Wajah' ? 'primary' : 'info' }} px-2 py-1 mr-1">
                                                    <i class="fas {{ $mode == 'Wajah' ? 'fa-user-check' : 'fa-fingerprint' }} mr-1"></i> {{ $mode }}
                                                </span>
                                            @endforeach
                                        </td>
                                        <td class="text-center">
                                            @if($log['clock_in'] && $log['clock_out'])
                                                <span class="badge badge-success px-2 py-1"><i class="fas fa-check-double mr-1"></i> Lengkap</span>
                                            @elseif($log['clock_in'])
                                                <span class="badge badge-info px-2 py-1"><i class="fas fa-sign-in-alt mr-1"></i> Clock In Sahaja</span>
                                            @else
                                                <span class="badge badge-warning px-2 py-1"><i class="fas fa-sign-out-alt mr-1"></i> Clock Out Sahaja</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-info-circle fa-2x mb-2 d-block text-secondary"></i>
                                            Belum ada data presensi untuk bulan ini.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Tampilan Mobile (Card List) -->
                        <div class="d-block d-md-none">
                            @forelse($groupedLogs as $log)
                            <div class="card mb-3 border-left-{{ ($log['clock_in'] && $log['clock_out']) ? 'success' : 'info' }} shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-weight-bold text-gray-900">{{ $log['formatted_date'] }} ({{ $log['day_name'] }})</span>
                                        <span class="badge badge-{{ ($log['clock_in'] && $log['clock_out']) ? 'success' : 'info' }}">
                                            {{ ($log['clock_in'] && $log['clock_out']) ? 'Lengkap' : 'Partial' }}
                                        </span>
                                    </div>
                                    <div class="row text-center border-top border-bottom py-2 my-2">
                                        <div class="col-6 border-right">
                                            <small class="text-muted d-block">Jam Masuk</small>
                                            <strong class="text-success">{{ $log['clock_in'] ?? '-' }}</strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Jam Pulang</small>
                                            <strong class="text-warning">{{ $log['clock_out'] ?? '-' }}</strong>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small text-muted">
                                        <span>Mode: {{ implode(', ', $log['modes']) }}</span>
                                        <span>Total Scan: {{ $log['total_scans'] }}x</span>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <div class="text-center py-4 text-muted">
                                Belum ada data presensi.
                            </div>
                            @endforelse
                        </div>

                    </div>
                </div>

            </div>
        </div>

        @include('layout.footer')

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        $('#tableRekapDesktop').DataTable({
            "order": [[0, "asc"]],
            "pageLength": 25,
            "language": {
                "search": "Cari Presensi:",
                "lengthMenu": "Tampilkan _MENU_ data",
                "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ hari",
                "infoEmpty": "Menampilkan 0 data",
                "zeroRecords": "Tidak ada data presensi yang sesuai",
                "paginate": {
                    "first": "Awal",
                    "last": "Akhir",
                    "next": "Berikutnya",
                    "previous": "Sebelumnya"
                }
            }
        });
    });
</script>

</body>
</html>
