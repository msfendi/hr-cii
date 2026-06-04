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
                        <h1 class="h3 mb-0 text-gray-800">Rekap Gender Departemen</h1>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-chart-pie mr-1"></i> Data Gender Per Departemen
                                    </h6>
                                    <a href="{{ route('biodata.index') }}" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-arrow-left"></i> Kembali
                                    </a>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive ">
                                        <table class="table table-bordered table-sm table-striped" id="dataTable"
                                            width="100%" cellspacing="0">
                                            <thead class="bg-primary text-white text-center">
                                                <tr>    
                                                    <th width="5%">NO</th>
                                                    <th>DEPARTEMENT</th>
                                                    <th width="15%">LAKI-LAKI</th>
                                                    <th width="15%">PEREMPUAN</th>
                                                    <th width="15%">TOTAL</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $totalL = 0;
                                                    $totalP = 0;
                                                    $totalAll = 0;
                                                @endphp
                                                @foreach($data as $key => $item)
                                                    @php
                                                        $totalL += $item->laki_laki;
                                                        $totalP += $item->perempuan;
                                                        $totalAll += $item->total;
                                                    @endphp
                                                    <tr>
                                                        <td class="text-center">{{ $key + 1 }}</td>
                                                        <td>{{ $item->DEPARTEMENT }}</td>
                                                        <td class="text-center">{{ $item->laki_laki }}</td>
                                                        <td class="text-center">{{ $item->perempuan }}</td>
                                                        <td class="text-center font-weight-bold">{{ $item->total }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="bg-light text-center font-weight-bold">
                                                <tr>
                                                    <td colspan="2" class="text-right pr-3">GRAND TOTAL</td>
                                                    <td>{{ $totalL }}</td>
                                                    <td>{{ $totalP }}</td>
                                                    <td>{{ $totalAll }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            @include('layout.footer')
        </div>
    </div>
    <!-- Page level plugins -->
    <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
    <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
    <script>
        $(document).ready(function () {
            $('#dataTable').DataTable();
        });
    </script>
</body>

</html>