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

                <!-- ============================
                     TABLE 1 : Attendance Log
                     ============================ -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-fingerprint mr-1"></i> Data Absensi Finger
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3 align-items-end">
                            <div class="col-xl-3 col-md-5">
                                <label class="small font-weight-bold text-gray-700">Tanggal</label>
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input class="form-control" type="date" id="fromdate" name="fromdate" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-xl-auto col-md-auto mt-2 mt-md-0">
                                <button class="btn btn-primary" id="btnExport">
                                    <i class="fas fa-file-excel mr-1"></i> Export Absensi
                                </button>
                                <button class="btn btn-success ml-1" id="btnSync">
                                    <i class="fas fa-sync-alt mr-1"></i> Sync
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>PIN/Barcode</th>
                                        <th>NPK</th>
                                        <th>Nama Karyawan</th>
                                        <th>Bagian</th>
                                        <th>Jam Masuk</th>
                                        <th>Jam Pulang</th>
                                        <th>Total Scan</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============================
                     TABLE 2 : Not Finger Today
                     ============================ -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between" style="background-color:#fff5f5;">
                        <h6 class="m-0 font-weight-bold text-danger">
                            <i class="fas fa-user-times mr-1"></i> Karyawan Belum Finger Hari Ini
                        </h6>
                        <button class="btn btn-danger btn-sm" id="btnExportNotFinger">
                            <i class="fas fa-file-excel mr-1"></i> Export Belum Finger
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover" id="dataTableNotFinger" width="100%" cellspacing="0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>PIN/Barcode</th>
                                        <th>NPK</th>
                                        <th>Nama Karyawan</th>
                                        <th>Bagian</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

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
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>
</html>

<script>
    $(document).ready(function() {

        // ─────────────────────────────────────────────
        // TABLE 1 — Attendance Log
        // ─────────────────────────────────────────────
        var table = $('#dataTable').DataTable({
            destroy: true,
            processing: true,
            ajax: {
                url: "{{ route('attendance-finger.index') }}",
                data: function (d) {
                    d.date = $('#fromdate').val();
                }
            },
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'pin',         name: 'pin'},
                {data: 'npk',         name: 'npk'},
                {data: 'nama',        name: 'nama'},
                {data: 'bagian',      name: 'bagian'},
                {data: 'jam_masuk',   name: 'jam_masuk'},
                {data: 'jam_pulang',  name: 'jam_pulang'},
                {data: 'total_scan',  name: 'total_scan'}
            ]
        });

        // ─────────────────────────────────────────────
        // TABLE 2 — Not Finger
        // ─────────────────────────────────────────────
        var tableNotFinger = $('#dataTableNotFinger').DataTable({
            destroy: true,
            processing: true,
            ajax: {
                url: "{{ route('attendance-finger.not-finger') }}",
                data: function (d) {
                    d.date = $('#fromdate').val();
                }
            },
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'pin',         name: 'pin'},
                {data: 'npk',         name: 'npk'},
                {data: 'nama',        name: 'nama'},
                {data: 'bagian',      name: 'bagian'},
            ]
        });

        // ─────────────────────────────────────────────
        // Date change — reload both tables
        // ─────────────────────────────────────────────
        $('#fromdate').change(function() {
            table.ajax.reload();
            tableNotFinger.ajax.reload();
        });

        // ─────────────────────────────────────────────
        // SYNC button
        // ─────────────────────────────────────────────
        $('#btnSync').click(function(e) {
            e.preventDefault();
            var date = $('#fromdate').val();
            if (date == '') {
                Swal.fire({ icon: 'error', title: 'Oops...', text: 'Please select date first!' });
                return;
            }

            Swal.fire({
                title: 'Syncing Data...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: "{{ route('attendance-finger.sync') }}",
                type: "POST",
                data: { _token: "{{ csrf_token() }}", date: date },
                success: function(response) {
                    Swal.fire({ icon: 'success', title: 'Success', text: response.message })
                        .then((result) => {
                            if (result.isConfirmed) {
                                table.ajax.reload();
                                tableNotFinger.ajax.reload();
                            }
                        });
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error', title: 'Error',
                        text: xhr.responseJSON ? xhr.responseJSON.message : 'Something went wrong!',
                    });
                }
            });
        });

        // ─────────────────────────────────────────────
        // EXPORT ATTENDANCE button
        // ─────────────────────────────────────────────
        $('#btnExport').click(function(e) {
            e.preventDefault();
            var date = $('#fromdate').val();
            if (date == '') {
                Swal.fire({ icon: 'error', title: 'Oops...', text: 'Please select date first!' });
                return;
            }

            Swal.fire({ title: 'Exporting...', text: 'Please wait', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch("{{ route('attendance-finger.export') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': "{{ csrf_token() }}" },
                body: JSON.stringify({ date: date })
            })
            .then(response => {
                if (response.ok) return response.blob();
                throw new Error('Network response was not ok.');
            })
            .then(blob => {
                Swal.close();
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url; a.download = 'attendance_finger_' + date + '.xlsx';
                document.body.appendChild(a); a.click(); a.remove();
            })
            .catch(() => {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong during export!' });
            });
        });

        // ─────────────────────────────────────────────
        // EXPORT NOT-FINGER button
        // ─────────────────────────────────────────────
        $('#btnExportNotFinger').click(function(e) {
            e.preventDefault();
            var date = $('#fromdate').val();
            if (date == '') {
                Swal.fire({ icon: 'error', title: 'Oops...', text: 'Please select date first!' });
                return;
            }

            Swal.fire({ title: 'Exporting...', text: 'Please wait', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch("{{ route('attendance-finger.export-not-finger') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': "{{ csrf_token() }}" },
                body: JSON.stringify({ date: date })
            })
            .then(response => {
                if (response.ok) return response.blob();
                throw new Error('Network response was not ok.');
            })
            .then(blob => {
                Swal.close();
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url; a.download = 'not_finger_' + date + '.xlsx';
                document.body.appendChild(a); a.click(); a.remove();
            })
            .catch(() => {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong during export!' });
            });
        });
    });
</script>