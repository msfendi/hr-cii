<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
@include('sweetalert::alert')
<div id="wrapper">
@include('layout.sidebar')
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @include('layout.navbar')
            <div class="container-fluid">

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-stethoscope"></i> Antrian Pasien Hari Ini</h1>
                    <span class="badge badge-dark p-2" style="font-size: 1rem;">
                        <i class="fas fa-calendar-day"></i> {{ \Carbon\Carbon::today()->translatedFormat('l, d F Y') }}
                    </span>
                </div>

                <!-- Stat Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Hari Ini</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalHariIni }}</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Menunggu</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalMenunggu }}</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Diperiksa</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalDiperiksa }}</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-user-md fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Selesai</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalSelesai }}</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Queue Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list-ol"></i> Daftar Antrian</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" width="80">No. Antrian</th>
                                        <th>NPK</th>
                                        <th>Nama Karyawan</th>
                                        <th>Departemen</th>
                                        <th>Keluhan</th>
                                        <th class="text-center">Jam Masuk</th>
                                        <th class="text-center">Jam Selesai</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center" width="200">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($antrians as $antrian)
                                    @php
                                        $rowClass = '';
                                        if ($antrian->status == 'menunggu') $rowClass = 'table-warning';
                                        elseif ($antrian->status == 'diperiksa') $rowClass = 'table-info';
                                        elseif ($antrian->status == 'selesai') $rowClass = 'table-success';
                                        $bio = $karyawanMap[$antrian->NPK] ?? null;
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="text-center font-weight-bold" style="font-size: 1.2rem;">{{ $antrian->no_antrian }}</td>
                                        <td>{{ $antrian->NPK }}</td>
                                        <td>{{ $bio->NAMA_KARYAWAN ?? '-' }}</td>
                                        <td>{{ $bio->DEPARTEMENT ?? '-' }}</td>
                                        <td>{{ Str::limit($antrian->keluhan, 60) }}</td>
                                        <td class="text-center">{{ $antrian->jam_masuk ?? '-' }}</td>
                                        <td class="text-center">{{ $antrian->jam_selesai ?? '-' }}</td>
                                        <td class="text-center">
                                            @if($antrian->status == 'menunggu')
                                                <span class="badge badge-warning p-2">Menunggu</span>
                                            @elseif($antrian->status == 'diperiksa')
                                                <span class="badge badge-info p-2">Diperiksa</span>
                                            @else
                                                <span class="badge badge-success p-2">Selesai</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($antrian->status == 'menunggu')
                                                <form action="{{ route('dokter.mulai-periksa', $antrian->id) }}" method="POST" style="display:inline;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Mulai periksa pasien ini?')">
                                                        <i class="fas fa-play"></i> Mulai Periksa
                                                    </button>
                                                </form>
                                            @elseif($antrian->status == 'diperiksa')
                                                <a href="{{ route('dokter.periksa', $antrian->id) }}" class="btn btn-info btn-sm">
                                                    <i class="fas fa-edit"></i> Lanjut Periksa
                                                </a>
                                            @else
                                                <a href="{{ route('dokter.periksa', $antrian->id) }}" class="btn btn-success btn-sm">
                                                    <i class="fas fa-eye"></i> Lihat Detail
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                            Belum ada antrian hari ini.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
@include('layout.footer')
</body>
</html>
