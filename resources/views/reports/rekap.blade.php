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

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-bar"></i> Rekap Kunjungan Poliklinik
                        </h1>
                    </div>

                    <!-- Filters -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter"></i> Filter</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('report.rekap') }}">
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="dari_tanggal">Dari Tanggal</label>
                                            <input type="date" class="form-control form-control-sm" id="dari_tanggal"
                                                name="dari_tanggal"
                                                value="{{ request('dari_tanggal', now()->startOfMonth()->toDateString()) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="sampai_tanggal">Sampai Tanggal</label>
                                            <input type="date" class="form-control form-control-sm" id="sampai_tanggal"
                                                name="sampai_tanggal"
                                                value="{{ request('sampai_tanggal', now()->toDateString()) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="departemen">Departemen</label>
                                            <select class="form-control form-control-sm" id="departemen"
                                                name="departemen">
                                                <option value="">Semua</option>
                                                @foreach($departemens as $id => $nama)
                                                    <option value="{{ $id }}" {{ request('departemen') == $id ? 'selected' : '' }}>{{ $nama }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="nama">Nama / NPK</label>
                                            <select class="form-control form-control-sm" id="nama" name="nama">
                                                @if($selectedKaryawan)
                                                    <option value="{{ $selectedKaryawan->NPK }}" selected>
                                                        {{ $selectedKaryawan->NPK }} -
                                                        {{ $selectedKaryawan->NAMA_KARYAWAN }}
                                                    </option>
                                                @else
                                                    <option value="">-- Cari Nama atau NPK --</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-center">
                                        <button type="submit" class="btn btn-primary btn-sm mr-2">
                                            <i class="fas fa-search"></i> Tampilkan
                                        </button>
                                        <a href="{{ route('report.export-excel', request()->all()) }}"
                                            class="btn btn-success btn-sm">
                                            <i class="fas fa-file-excel"></i> Export Rekap ke Excel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    {{-- <div class="row">
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total
                                                Kunjungan</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalKunjungan }}
                                            </div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-hospital fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> --}}

                    <!-- Top Diagnosa & Per Departemen -->
                    {{-- <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-medkit"></i> 5
                                        Diagnosa Terbanyak</h6>
                                </div>
                                <div class="card-body">
                                    @if($diagnosaTerbanyak->count())
                                    <table class="table table-sm table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>No</th>
                                                <th>Diagnosa</th>
                                                <th class="text-center">Jumlah</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($diagnosaTerbanyak as $diagnosa => $count)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $diagnosa }}</td>
                                                <td class="text-center"><span class="badge badge-primary">{{ $count
                                                        }}</span></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @else
                                    <p class="text-muted text-center">Tidak ada data.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-building"></i>
                                        Kunjungan per Departemen</h6>
                                </div>
                                <div class="card-body">
                                    @if($perDepartemen->count())
                                    <table class="table table-sm table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>No</th>
                                                <th>Departemen</th>
                                                <th class="text-center">Jumlah</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($perDepartemen as $dept => $count)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $dept }}</td>
                                                <td class="text-center"><span class="badge badge-info">{{ $count
                                                        }}</span></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @else
                                    <p class="text-muted text-center">Tidak ada data.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div> --}}

                    <!-- Detail Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Detail Kunjungan
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Tanggal</th>
                                            <th>NPK</th>
                                            <th>Nama</th>
                                            <th>Departemen</th>
                                            <th>Diagnosa</th>
                                            <th>Tindakan</th>
                                            <th>Dokter</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($kunjungans as $index => $k)
                                            @php
                                                $bio = $karyawanMap[$k->NPK] ?? null;
                                            @endphp
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $k->tanggal_kunjungan->format('d/m/Y') }}</td>
                                                <td>{{ $k->NPK }}</td>
                                                <td>{{ $bio->NAMA_KARYAWAN ?? '-' }}</td>
                                                <td>{{ $bio->DEPARTEMENT ?? '-' }}</td>
                                                <td>{{ Str::limit($k->diagnosa, 40) }}</td>
                                                <td>{{ Str::limit($k->tindakan, 30) }}</td>
                                                <td>{{ $dokterMap[$k->dokter_id] ?? '-' }}</td>
                                                <td>
                                                    <a href="{{ route('dokter.periksa', $k->id) }}"
                                                        class="btn btn-success btn-sm">
                                                        <i class="fas fa-eye"></i> Lihat Detail
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-3">Tidak ada data
                                                    kunjungan.</td>
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
            <link
                href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css"
                rel="stylesheet" />

            <script>
                $(document).ready(function () {
                    $('#nama').select2({
                        theme: 'bootstrap4',
                        placeholder: '-- Cari Nama atau NPK --',
                        allowClear: true,
                        ajax: {
                            url: '{{ route("kunjungan.search-karyawan") }}',
                            dataType: 'json',
                            delay: 300,
                            data: function (params) {
                                return { q: params.term };
                            },
                            processResults: function (data) {
                                return { results: data.results };
                            },
                            cache: true
                        },
                        minimumInputLength: 2
                    });
                });
            </script>
</body>

</html>