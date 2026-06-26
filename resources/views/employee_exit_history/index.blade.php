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
                        <h1 class="h3 mb-0 text-gray-800">BIODATA KELUAR</h1>
                    </div>

                    <!-- Add Button in Card Header -->
                    <div class="card shadow mb-2">
                        <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-danger">KARYAWAN KELUAR / HISTORY</h6>
                            <div>
                                <select id="department_filter"
                                    class="form-control form-control-sm d-inline-block shadow-sm"
                                    style="width: 200px; margin-right: 10px;">
                                    <option value="">Select Department</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->ID_DEPT }}">{{ $dept->DEPARTEMENT }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-striped" id="dataTable" width="100%"
                                    cellspacing="0">
                                    <thead class="bg-danger text-white">
                                        <tr>
                                            <th>NO</th>
                                            <th>KTP</th>
                                            <th>NAMA_KARYAWAN</th>
                                            <th>BARCODE</th>
                                            <th>DEPARTMENT</th>
                                            <th>RIWAYAT TANGGAL KELUAR</th>
                                            <th>TOTAL RIWAYAT</th>
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
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function () {
        var table = $('#dataTable').DataTable({
            ajax: {
                url: '{{ route("employee_exit_history.get-data") }}',
                data: function (d) {
                    d.department_id = $('#department_filter').val();
                },
                dataSrc: 'data'
            },
            pageLength: 15,
            columns: [
                {
                    data: null,
                    render: function (data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'KTP' },
                { data: 'NAMA_KARYAWAN' },
                { data: 'BARCODE' },
                { data: 'DEPARTEMENT' },
                {
                    data: 'riwayat_tkk',
                    render: function (data, type, row) {
                        if (!data) return '<span class="text-muted" style="font-size:0.8em">— Tidak ada data —</span>';
                        var dates = data.split(',');
                        var html = '<div style="display:flex;flex-wrap:wrap;gap:4px;">';
                        for(var i=0; i<dates.length; i++){
                            html += '<span style="background:#fff3cd;border:1px solid #ffc107;border-radius:12px;padding:2px 8px;font-size:0.78em;color:#856404;white-space:nowrap;">📅 ' + dates[i].trim() + '</span>';
                        }
                        html += '</div>';
                        return html;
                    }
                },
                {
                    data: 'total_riwayat',
                    render: function (data, type, row) {
                        return '<span class="badge badge-info px-2 py-1">' + data + ' Kali</span>';
                    }
                }
            ]
        });

        // Event listener for department filter
        $('#department_filter').change(function () {
            table.ajax.reload();
        });
    });
</script>

</html>