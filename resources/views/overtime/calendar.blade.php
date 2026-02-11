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
                    <h1 class="h3 mb-0 text-gray-800">Overtime Management</h1>
                </div>
                
                <!-- Add Button in Card Header -->
                <div class="card shadow mb-2">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Overtime Data</h6>
                        <div>
                            <div class="d-flex align-items-center flex-wrap">
                                <div class="form-inline mr-3 mb-2">
                                    <label for="department_filter" class="mr-2">Dept:</label>
                                    <div class="input-group input-group-sm mr-2">
                                        <select class="form-control" name="department" id="department_filter">
                                            <option value="">All Departments</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->BAGIAN }}">{{ $dept->BAGIAN }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <label for="date" class="mr-2">Date:</label>
                                    <div class="input-group input-group-sm mr-2">
                                        <input type="month" class="form-control" name="date" id="date" value="{{ date('Y-m') }}" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <!-- Dynamic headers -->
                                </thead>
                                <tbody>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>

<script>
    $(document).ready(function() {
        function loadTable() {
            var dateVal = $('#date').val();
            var deptVal = $('#department_filter').val();

            if (!dateVal) return;
            var monthVal = dateVal.substring(0, 7);
            var parts = monthVal.split('-');
            var year = parseInt(parts[0]);
            var month = parseInt(parts[1]);
            var daysInMonth = new Date(year, month, 0).getDate();

            if ($.fn.DataTable.isDataTable('#dataTable')) {
                $('#dataTable').DataTable().destroy();
                $('#dataTable').empty();
            }

            var columns = [
                { title: "NPK", data: "NPK" },
                { title: "NAMA", data: "NAMA_KARYAWAN" },
                { title: "BAGIAN", data: "BAGIAN" }
            ];

            for (var i = 1; i <= daysInMonth; i++) {
                var dayStr = i.toString().padStart(2, '0');
                columns.push({
                    title: dayStr,
                    data: monthVal + '-' + dayStr,
                    defaultContent: "",
                    width: "30px",
                    className: "text-center"
                });
            }

            // Summary columns
            columns.push({ title: "Kehadiran", data: "total_kehadiran", className: "text-center font-weight-bold", defaultContent: "0" });
            columns.push({ title: "1", data: "1", className: "text-center font-weight-bold", defaultContent: "0" });
            columns.push({ title: "2", data: "2", className: "text-center font-weight-bold", defaultContent: "0" });
            columns.push({ title: "Total", data: "total", className: "text-center font-weight-bold", defaultContent: "0" });
            columns.push({ title: "Lembur Khusus", data: "lembur_khusus", className: "text-center font-weight-bold", defaultContent: "0" });

            // Tambahkan kolom untuk counting yang value nya character misal CT, MA
            columns.push({ title: "CT", data: "CT", className: "text-center font-weight-bold", defaultContent: "0" });
            columns.push({ title: "MA", data: "MA", className: "text-center font-weight-bold", defaultContent: "0" });
            

            $('#dataTable').DataTable({
                ajax: {
                    url: '{{ route("overtime.calendar-data") }}',
                    data: { 
                        month: monthVal,
                        department: deptVal
                    },
                    dataSrc: 'data'
                },
                columns: columns,
                pageLength: 15,
                scrollX: true,
                autoWidth: false
            });
        }

        $('#date, #department_filter').on('change', function() {
            loadTable();
        });

        // Trigger initial load
        loadTable();
    });
</script>
</html>