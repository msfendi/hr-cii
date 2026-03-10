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
                    <h1 class="h3 mb-0 text-gray-800">Attendance Finger</h1>
                </div>

                <!-- DataTales Example -->
                <div class="card shadow mb-2">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Attendance Finger</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2 justify-content-end">
                            <div class="col-xl-3 col-md-6">
                                <div>
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input class="date form-control" type="date" id="fromdate" name="fromdate" value="">
                                </div>
                            </div>
                            {{-- button export --}}
                            <div class="col-xl-3 col-md-6">
                                <div>
                                    <button class="btn btn-primary" id="btnExport">Export</button>
                                    <button class="btn btn-success" id="btnSync">Sync</button>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>sn</th>
                                        <th>scan_date</th>
                                        <th>pin</th>
                                        <th>att_id</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($attendanceFingers as $attendanceFinger)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $attendanceFinger->sn }}</td>
                                        <td>{{ $attendanceFinger->scan_date }}</td>
                                        <td>{{ $attendanceFinger->pin }}</td>
                                        <td>{{ $attendanceFinger->att_id }}</td>
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

<script>
    // sync data from mysql by datepicker and insert to sqlserver use ajax
    $(document).ready(function() {
        $('#btnSync').click(function(e) {
            e.preventDefault();
            var date = $('#fromdate').val();
            if (date == '') {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Please select date first!',
                });
                return;
            }

            Swal.fire({
                title: 'Syncing Data...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: "{{ route('attendance-finger.sync') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    date: date
                },
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message,
                    }).then((result) => {
                        if (result.isConfirmed) {
                            location.reload();
                        }
                    });
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON ? xhr.responseJSON.message : 'Something went wrong!',
                    });
                }
            });
        });

        $('#btnExport').click(function(e) {
            e.preventDefault();
            var date = $('#fromdate').val();
            if (date == '') {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Please select date first!',
                });
                return;
            }

            Swal.fire({
                title: 'Exporting Data...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Use fetch to handle blob response
            fetch("{{ route('attendance-finger.export') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
                body: JSON.stringify({ date: date })
            })
            .then(response => {
                if (response.ok) {
                    return response.blob();
                }
                throw new Error('Network response was not ok.');
            })
            .then(blob => {
                Swal.close();
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'attendance_finger_' + date + '.xlsx';
                document.body.appendChild(a);
                a.click();
                a.remove();
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong during export!',
                });
            });
        });
    });
</script>