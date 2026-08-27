<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<style>
</style>
<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">

                @include('layout.navbar')

                <div class="container-fluid">

                    <h1 class="h3 mb-4 text-gray-800">Audit Trail</h1>

                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <form id="filterForm" class="row g-2 mb-3">
                                <div class="col-md-3">
                                    <select class="form-control select2" id="filterEvent" name="event">
                                        <option value="">-- Semua Aksi --</option>
                                        @foreach ($events as $event)
                                            <option value="{{ $event }}">{{ strtoupper($event) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-control select2" id="filterModel" name="auditable_type">
                                        <option value="">-- Semua Model --</option>
                                        @foreach ($models as $model)
                                            <option value="{{ $model }}">{{ class_basename($model) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="date" class="form-control" id="startDate" name="start_date">
                                </div>
                                <div class="col-md-2">
                                    <input type="date" class="form-control" id="endDate" name="end_date">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" id="btnFilter" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table id="tableAudit" class="table table-bordered table-hover w-100">
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>User</th>
                                            <th>Model</th>
                                            <th>Aksi</th>
                                            <th>Detail</th>
                                        </tr>
                                    </thead>
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

<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Perubahan</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm mb-3">
                    <tr><th width="150">Waktu</th><td id="dtCreatedAt"></td></tr>
                    <tr><th>User</th><td id="dtUser"></td></tr>
                    <tr><th>Model</th><td id="dtModel"></td></tr>
                    <tr><th>Aksi</th><td id="dtEvent"></td></tr>
                    <tr><th>URL</th><td id="dtUrl"></td></tr>
                    <tr><th>IP Address</th><td id="dtIp"></td></tr>
                </table>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Data Lama</h6>
                        <pre id="dtOldValues" class="bg-light p-2" style="max-height:300px; overflow:auto;"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6>Data Baru</h6>
                        <pre id="dtNewValues" class="bg-light p-2" style="max-height:300px; overflow:auto;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

<script>
$(function () {
    $('.select2').select2({ width: '100%' });

    const table = $('#tableAudit').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('audit-trail.data') }}",
            data: function (d) {
                d.event = $('#filterEvent').val();
                d.auditable_type = $('#filterModel').val();
                d.start_date = $('#startDate').val();
                d.end_date = $('#endDate').val();
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'user_name', name: 'user_name', defaultContent: '-' },
            { data: 'model_name', name: 'auditable_type' },
            { data: 'event_badge', name: 'event', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });

    $('#btnFilter').on('click', function () {
        table.ajax.reload();
    });

    $(document).on('click', '.btn-detail', function () {
        const id = $(this).data('id');
        $.get("{{ url('audit-trail') }}/" + id, function (res) {
            $('#dtCreatedAt').text(res.created_at);
            $('#dtUser').text(res.user_name);
            $('#dtModel').text(res.model);
            $('#dtEvent').text(res.event.toUpperCase());
            $('#dtUrl').text(res.url ?? '-');
            $('#dtIp').text(res.ip_address ?? '-');
            $('#dtOldValues').text(res.old_values ? JSON.stringify(res.old_values, null, 2) : '-');
            $('#dtNewValues').text(res.new_values ? JSON.stringify(res.new_values, null, 2) : '-');
            $('#modalDetail').modal('show');
        });
    });
});
</script>
</body>

</html>