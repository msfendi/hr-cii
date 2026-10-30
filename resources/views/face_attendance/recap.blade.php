<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<style>
    .empty-state { text-align: center; padding: 3rem 1rem; color: #858796; }
    .empty-state i { font-size: 3rem; margin-bottom: 1rem; color: #d1d3e2; }
    .recap-table th, .recap-table td { vertical-align: middle; white-space: nowrap; }
</style>

<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')

                <div class="container-fluid">

                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Rekap Absensi Wajah</h1>
                        <div class="d-flex align-items-center mt-2 mt-sm-0">
                            <a href="{{ route('face.attendance.index') }}" class="btn btn-sm btn-outline-primary mr-3">
                                <i class="fas fa-arrow-left mr-1"></i> Kembali Absen
                            </a>
                            <button class="btn btn-sm btn-light border" onclick="changeMonth(-1)" title="Bulan sebelumnya">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span class="font-weight-bold text-gray-800 mx-3">{{ $monthName }} {{ $year }}</span>
                            <button class="btn btn-sm btn-light border" onclick="changeMonth(1)" title="Bulan berikutnya">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Ringkasan --}}
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Hari Hadir</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $summary['present'] }}</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-user-check fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Terlambat</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $summary['late'] }}</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-user-clock fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pulang Cepat</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $summary['early'] }}</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-door-open fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Percobaan Ditolak</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $summary['rejected'] }}</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-ban fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Detail harian --}}
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calendar-day mr-2"></i>Detail Absensi Harian</h6>
                        </div>
                        <div class="card-body p-0">
                            @if ($attendances->isEmpty())
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times d-block"></i>
                                    <p class="mb-0">Belum ada data absensi wajah untuk bulan ini.</p>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0 recap-table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Clock In</th>
                                                <th>Clock Out</th>
                                                <th>Status</th>
                                                <th>Keterangan</th>
                                                <th>Lokasi</th>
                                                <th class="text-right">Kecocokan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($attendances as $att)
                                                <tr>
                                                    <td>
                                                        <div class="font-weight-bold">{{ $att->date->format('d M Y') }}</div>
                                                        <div class="text-xs text-muted">{{ $att->date->copy()->locale('id')->translatedFormat('l') }}</div>
                                                    </td>
                                                    <td>
                                                        @if ($att->clock_in)
                                                            <span class="badge {{ $att->is_late ? 'badge-warning' : 'badge-success' }}">{{ $att->clock_in->format('H:i:s') }}</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($att->clock_out)
                                                            <span class="badge {{ $att->is_early_out ? 'badge-warning' : 'badge-success' }}">{{ $att->clock_out->format('H:i:s') }}</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($att->is_late)
                                                            <span class="badge badge-warning">Terlambat</span>
                                                        @elseif ($att->clock_in)
                                                            <span class="badge badge-success">Hadir</span>
                                                        @else
                                                            <span class="badge badge-secondary">Tanpa clock in</span>
                                                        @endif
                                                    </td>
                                                    <td class="small text-muted">
                                                        @php
                                                            $notes = [];
                                                            if ($att->is_late) $notes[] = 'Terlambat ' . $att->late_minutes . ' menit';
                                                            if ($att->is_early_out) $notes[] = 'Pulang cepat ' . $att->early_out_minutes . ' menit';
                                                            if ($att->is_incomplete) $notes[] = 'Belum clock out';
                                                        @endphp
                                                        {{ $notes ? implode(', ', $notes) : '-' }}
                                                    </td>
                                                    <td class="small text-muted">
                                                        @if ($att->latitude !== null && $att->longitude !== null)
                                                            <a href="https://www.google.com/maps?q={{ $att->latitude }},{{ $att->longitude }}" target="_blank" rel="noopener">
                                                                {{ number_format($att->latitude, 5) }}, {{ number_format($att->longitude, 5) }}
                                                            </a>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="text-right small">
                                                        {{ $att->confidence !== null ? number_format($att->confidence * 100, 1) . '%' : '-' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Percobaan ditolak --}}
                    @if ($rejected->isNotEmpty())
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-ban mr-2"></i>Percobaan Absen Ditolak</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 recap-table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Waktu</th>
                                                <th>Aksi</th>
                                                <th>Alasan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($rejected as $r)
                                                <tr>
                                                    <td>{{ \Carbon\Carbon::parse($r->scan_date)->format('d M Y H:i:s') }}</td>
                                                    <td>{{ $r->action === 'clock_in' ? 'Masuk' : 'Keluar' }}</td>
                                                    <td class="small text-muted" style="white-space:normal;">{{ $r->rejection_reason }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            @include('layout.footer')

<script>
    const currentMonth = {{ (int) $month }};
    const currentYear = {{ (int) $year }};
    const recapUrl = @json(route('face.attendance.recap'));

    function changeMonth(delta) {
        let m = currentMonth + delta;
        let y = currentYear;
        if (m < 1)  { m = 12; y--; }
        if (m > 12) { m = 1;  y++; }
        window.location.href = recapUrl + '?month=' + m + '&year=' + y;
    }
</script>
</body>
</html>
