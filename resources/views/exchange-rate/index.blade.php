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

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Data Kurs USD ke IDR</h1>
                    <div>
                    @canRoute('exchange-rates.sync')
                    <button id="btn-sync-today" type="button" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm">
                        <i class="fas fa-sync fa-sm text-white-50"></i> Sync Kurs Terbaru
                    </button>
                    @endcanRoute
                    </div>
                </div>

                {{-- ===================== SESSION ALERT ===================== --}}
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif

                @if ($message = Session::get('error'))
                <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif

                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Riwayat Kurs Harian
                    </h6>

                    <div class="d-flex align-items-center flex-wrap">

                        {{-- ===================== FILTER TANGGAL (browsing histori tersimpan) ===================== --}}
                        <form method="GET" id="filterForm" class="form-inline mr-2">
                            <label class="mr-1 mb-0 small">Dari</label>
                            <input type="date" name="start_date" value="{{ $startDate }}"
                                class="form-control form-control-sm mr-2">

                            <label class="mr-1 mb-0 small">Sampai</label>
                            <input type="date" name="end_date" value="{{ $endDate }}"
                                class="form-control form-control-sm mr-2">

                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </form>

                    </div>

                    </div>
                    <div class="card-body">

                        @if(empty($rates) || $rates->isEmpty())
                        <div class="alert alert-info py-2 px-3 mb-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            Belum ada data kurs untuk rentang tanggal ini. Klik <strong>Sync Kurs Hari Ini</strong>
                            atau <strong>Sync Rentang Tanggal</strong> untuk mengambil data dari Bank Indonesia.
                        </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Kurs Jual</th>
                                        <th>Kurs Beli</th>
                                        <th>Kurs Tengah</th>
                                        <th>Sumber</th>
                                        <th>Terakhir Diupdate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rates as $rate)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $rate->rate_date->format('d/m/Y') }}</td>
                                        <td>Rp {{ number_format($rate->kurs_jual, 2, ',', '.') }}</td>
                                        <td>Rp {{ number_format($rate->kurs_beli, 2, ',', '.') }}</td>
                                        <td>Rp {{ number_format($rate->kurs_tengah, 2, ',', '.') }}</td>
                                        <td>
                                            <span class="badge badge-secondary">{{ $rate->source }}</span>
                                        </td>
                                        <td>{{ $rate->updated_at->format('d/m/Y H:i') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- /Content Row -->

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

<br>
@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function(){

    $('#dataTable').DataTable({
        order: [[1,'desc']], // urut berdasarkan tanggal terbaru
        pageLength: 30,
        responsive: true,
        autoWidth: false
    });

});
</script>

<script>

/*
=====================================================
SYNC KURS HARI INI
=====================================================
*/
$(document).on('click', '#btn-sync-today', function(){

    Swal.fire({
        title: 'Sync Kurs Terbaru?',
        text: 'Rilis kurs USD ke IDR terbaru yang tayang di situs Bank Indonesia akan diambil dan disimpan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Sync'
    }).then((result) => {

        if(!result.isConfirmed){
            return;
        }

        Swal.fire({
            title: 'Processing...',
            text: 'Mengambil data kurs dari Bank Indonesia',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: "{{ route('exchange-rates.sync') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function(res){

                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: res.message
                }).then(() => {
                    location.reload();
                });

            },
            error: function(xhr){

                let message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Gagal sync kurs hari ini';

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message
                });

            }
        });

    });

});

</script>
</html>
