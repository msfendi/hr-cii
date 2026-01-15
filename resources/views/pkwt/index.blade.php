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
                    <h1 class="h3 mb-0 text-gray-800">PKWT</h1>
                </div>
                
                <!-- DataTales Example -->
                <div class="card shadow mb-2">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">PKWT</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th width="80px">NPK</th>
                                        <th width="200px">NAMA</th>
                                        <th>JENIS KELAMIN</th>
                                        <th>TGL LAHIR</th>
                                        <th>TMK</th>
                                        <th>USIA</th>
                                        <th>BAGIAN</th>
                                        <th>KTP</th>
                                        <th>TUTUPBUKU</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pkwts as $pkwt)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $pkwt->NPK }}</td>
                                        <td>{{ $pkwt->NAMA }}</td>
                                        <td>{{ $pkwt->JK }}</td>
                                        <td>{{ $pkwt->TGLLAHIR }}</td>
                                        <td>{{ $pkwt->TMK }}</td>
                                        <td>{{ $pkwt->USIA }}</td>
                                        <td>{{ $pkwt->BAGIAN }}</td>
                                        <td>{{ $pkwt->KTP }}</td>
                                        <td>{{ $pkwt->TUTUPBUKU }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Content Row -->

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->
@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<!-- Page level custom scripts -->
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
{{-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script> --}}
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>
</html>