<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
<!-- Page Wrapper -->
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
                    <h1 class="h3 mb-0 text-gray-800">Update Employee Attendance</h1>
                </div>
                

                <!-- Approach -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Form Update Employee Attendance</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('attendance.update', $employee->id) }}">
                            @csrf
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

                            @if ($message = Session::get('warning'))
                            <div class="alert alert-warning alert-block">
                                <button type="button" class="close" data-dismiss="alert">×</button>	
                                <strong>{{ $message }}</strong>
                            </div>
                            @endif

                            @if ($message = Session::get('info'))
                            <div class="alert alert-info alert-block">
                                <button type="button" class="close" data-dismiss="alert">×</button>	
                                <strong>{{ $message }}</strong>
                            </div>
                            @endif
                            <div>
                                <label>NPK :</label>
                                <input class="form-control" type="text" id="npk" name="npk" value="{{$employee->NPK}}" readonly>
                            </div>
                            <br>
                            <div>
                                <label>Nama Lengkap :</label>
                                <input class="form-control" type="text" id="name" name="name" value="{{$employee->NAMA_KARYAWAN}}" readonly>
                            </div>
                            <br>
                            <div>
                                <label>Tanggal :</label>
                                <input class="form-control" type="date" id="tanggal" name="tanggal" value="{{ $employee->TANGGAL }}" readonly>
                            </div>
                            <br>
                            <div>
                                <label>SubDivisi :</label>
                                <input class="form-control" type="text" id="subdivisi" name="subdivisi" value="{{$employee->SUBDIVISI}}" readonly>
                            </div>
                            <br>
                            <div>
                                <label>Jam Pagi :</label>
                                <input class="form-control" type="time" id="jam_pagi" name="jam_pagi" value="{{$employee->JAM_PAGI}}">
                            </div>
                            <br>
                            <div>
                                <label>Jam Siang :</label>
                                <input class="form-control" type="time" id="jam_siang" name="jam_siang" value="{{$employee->JAM_SIANG}}">
                            </div>
                            <br>
                            <div>
                                <label>Jam Malam :</label>
                                <input class="form-control" type="time" id="jam_malam" name="jam_malam" value="{{$employee->JAM_MALAM}}">
                            </div>
                            <br>
                            <div>
                                <label>Status :</label>
                                <input class="form-control" type="text" id="status" name="status" value="{{$employee->STATUS}}" readonly>
                            </div>
                            <br>
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-block">Update</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Content Row -->

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

@include('layout.footer')
</body>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
</html>