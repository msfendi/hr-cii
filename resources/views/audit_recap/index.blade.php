<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body id="page-top">
    <!-- Page Wrapper -->
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">
                @include('layout.navbar')
                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <h1 class="h3 mb-4 text-gray-800">Rekap Audit Kehadiran</h1>

                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row">
                        {{-- FORM: Lihat data rekap (GET, semua periode termasuk yang closed) --}}
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Lihat Data Rekap</h6>
                                </div>
                                <div class="card-body">
                                    <form method="GET" action="{{ route('audit-recap.index') }}" class="form-inline">
                                        <label class="mr-2">Periode Payroll</label>
                                        <select name="period_id" class="form-control mr-3" style="min-width:260px"
                                            onchange="this.form.submit()">
                                            @forelse ($allPeriods as $p)
                                                <option value="{{ $p->id }}" {{ $p->id == $periodId ? 'selected' : '' }}>
                                                    {{ $p->name }} {{ $p->is_closed ? '(Closed)' : '(Open)' }}
                                                </option>
                                            @empty
                                                <option value="">Belum ada periode payroll</option>
                                            @endforelse
                                        </select>
                                        <noscript><button type="submit"
                                                class="btn btn-secondary">Tampilkan</button></noscript>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- FORM: Generate rekap (POST, hanya periode yang masih open) --}}
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Generate Rekap</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('audit-recap.generate') }}"
                                        class="form-inline">
                                        @csrf
                                        <label class="mr-2">Periode Payroll</label>
                                        <select name="period_id" class="form-control mr-3" style="min-width:260px">
                                            @forelse ($openPeriods as $p)
                                                <option value="{{ $p->id }}" {{ $p->id == $periodId ? 'selected' : '' }}>
                                                    {{ $p->name }}
                                                    ({{ \Carbon\Carbon::parse($p->start_date)->format('d M Y') }} -
                                                    {{ \Carbon\Carbon::parse($p->end_date)->format('d M Y') }})
                                                </option>
                                            @empty
                                                <option value="">Tidak ada periode terbuka</option>
                                            @endforelse
                                        </select>

                                        <button type="submit" class="btn btn-primary" {{ $openPeriods->isEmpty() ? 'disabled' : '' }}
                                            onclick="return confirm('Generate ulang akan menimpa data rekap yang sudah ada untuk periode ini (termasuk jam yang sudah dirapikan sebelumnya). Lanjutkan?')">
                                            <i class="fas fa-sync-alt"></i> Generate
                                        </button>
                                    </form>
                                    <small class="text-muted d-block mt-2">
                                        Hanya periode yang masih <strong>terbuka</strong> (belum closed) yang bisa
                                        di-generate.
                                        Karyawan diambil dari payroll_run terbaru untuk periode tsb.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                Data Rekap
                                @if ($selectedPeriod)
                                    &mdash; {{ $selectedPeriod->name }}
                                    ({{ \Carbon\Carbon::parse($selectedPeriod->start_date)->format('d M Y') }} -
                                    {{ \Carbon\Carbon::parse($selectedPeriod->end_date)->format('d M Y') }})
                                    {{ $selectedPeriod->is_closed ? '- Closed' : '- Open' }}
                                @endif
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="dataTable" width="100%">
                                    <thead>
                                        <tr>
                                            <th>NPK</th>
                                            <th>Nama Karyawan</th>
                                            <th>Tanggal</th>
                                            <th>Subdivisi</th>
                                            <th>Dept Group</th>
                                            <th>Jam Pagi</th>
                                            <th>Jam Siang</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->
            </div>
            <!-- End of Main Content -->

            @include('layout.footer')
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<script>
    $(document).ready(function () {
        $('#dataTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("audit-recap.index") }}',
                data: function (d) {
                    d.period_id = '{{ $periodId }}';
                }
            },
            columns: [
                { data: 'NPK', name: 'NPK' },
                { data: 'NAMA_KARYAWAN', name: 'NAMA_KARYAWAN' },
                { data: 'TANGGAL', name: 'TANGGAL' },
                { data: 'SUBDIVISI', name: 'SUBDIVISI' },
                { data: 'DEPT_GROUP', name: 'DEPT_GROUP' },
                { data: 'JAM_PAGI', name: 'JAM_PAGI' },
                { data: 'JAM_SIANG', name: 'JAM_SIANG' },
                { data: 'STATUS', name: 'STATUS' }
            ],
            order: []
        });
    });
</script>

</html>