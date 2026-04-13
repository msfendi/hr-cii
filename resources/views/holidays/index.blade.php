<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')
                <div class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Holiday Master</h1>
                        <div>
                            <a href="{{ route('holidays.create') }}"
                                class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                                <i class="fas fa-plus fa-sm text-white-50"></i>
                                Create Holiday
                            </a>
                        </div>
                    </div>
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Data Master Holiday
                                </h6>
                                <div class="d-flex align-items-center">
                                    <a href="{{ route('holidays.sync') }}" class="btn btn-info btn-sm mr-2">
                                        <i class="fas fa-download"></i>
                                        Sync from API
                                    </a>
                                    <form id="importForm" action="{{ route('holidays.import') }}" method="POST"
                                        enctype="multipart/form-data">
                                        @csrf
                                        <div class="input-group input-group-sm">
                                            <input type="file" name="file" id="fileInput" class="d-none"
                                                accept=".xlsx,.xls,.csv">
                                            <button type="button" class="btn btn-primary btn-sm" id="btnUpload">
                                                <i class="fas fa-upload"></i>
                                                Upload Excel Holiday
                                            </button>
                                            <span id="fileName" class="form-control form-control-sm text-truncate"
                                                style="max-width:200px">
                                                No file selected
                                            </span>
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-success btn-sm" id="btnImport">
                                                    <i class="fas fa-file-excel"></i>
                                                    Import
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height:18px; display:none;" id="uploadProgress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                                    style="width:0%" id="progressBar">
                                    0%
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="dataTable" width="100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Tanggal</th>
                                            <th>Nama Holiday</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($data as $row)
                                            <tr>
                                                <td>{{ $row->id }}</td>
                                                <td>
                                                    {{ \Carbon\Carbon::parse($row->holiday_date)->format('d-m-Y') }}
                                                </td>
                                                <td>{{ $row->name }}</td>
                                                <td class="text-center">
                                                    <a href="{{ route('holidays.edit', $row->id) }}"
                                                        class="btn btn-primary btn-circle btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a class="btn btn-danger btn-circle btn-sm btn-delete-holiday"
                                                        data-delete-link="{{ route('holidays.delete', $row->id) }}"
                                                        data-name="{{ $row->name }}" data-toggle="modal"
                                                        data-target="#deleteModal">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- DELETE MODAL -->
            <div class="modal fade" id="deleteModal" tabindex="-1">
                <div class="modal-dialog modal-md">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Delete Holiday</h5>
                            <button class="close" data-dismiss="modal">
                                <span>×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p id="modal-text-holiday"></p>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-dismiss="modal">
                                Tutup
                            </button>
                            <a id="btn-confirm" href="">
                                <button class="btn btn-danger">
                                    Confirm
                                </button>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @include('layout.footer')
</body>
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>
<script>
    $('.btn-delete-holiday').on('click', function () {
        $('#btn-confirm').attr('href', $(this).data('delete-link'));
        $("#modal-text-holiday").text(
            'Apakah anda yakin ingin menghapus holiday ' + $(this).data('name') + ' ?'
        );
    });
    $('#btnUpload').click(function () {
        $('#fileInput').click();
    });
    $('#fileInput').change(function () {
        let file = this.files[0];
        if (!file) return;
        let allowed = ['xlsx', 'xls', 'csv'];
        let ext = file.name.split('.').pop().toLowerCase();
        if (!allowed.includes(ext)) {
            Swal.fire({
                icon: 'error',
                title: 'Format tidak valid',
                text: 'File harus Excel (.xlsx,.xls,.csv)'
            });
            $(this).val('');
            return;
        }
        $('#fileName').text(file.name);
    });
    $('#importForm').submit(function (e) {
        e.preventDefault();
        let form = this;
        let formData = new FormData(form);
        $('#uploadProgress').show();
        $.ajax({
            xhr: function () {
                let xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function (evt) {
                    if (evt.lengthComputable) {
                        let percent = Math.round((evt.loaded / evt.total) * 100);
                        $('#progressBar').css('width', percent + '%');
                        $('#progressBar').text(percent + '%');
                    }
                }, false);
                return xhr;
            },
            url: $(form).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function () {
                $('#progressBar').css('width', '100%');
                $('#progressBar').text('100%');
                Swal.fire({
                    icon: 'success',
                    title: 'Import berhasil',
                    text: 'Halaman akan diperbarui...',
                    showConfirmButton: false,
                    timer: 1500
                });
                setTimeout(function () {
                    location.reload();
                }, 1500);
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Import gagal'
                });
            }
        });
    });
</script>

</html>