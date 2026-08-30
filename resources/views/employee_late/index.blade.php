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
              <h1 class="h3 mb-0 text-gray-800">Employee Late</h1>
              @canRoute('employee-late.create')
              <a href="{{ route('employee-late.create') }}" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Create Employee Late </a>
              @endcanRoute
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                  <!-- KIRI -->
                  <h6 class="m-0 font-weight-bold text-primary"> Data Employee Late </h6>
                  <!-- KANAN -->
                  <div class="d-flex align-items-center">
                    <!-- DOWNLOAD TEMPLATE -->
                    @canRoute('employee-late.template')
                    <a href="{{ route('employee-late.template') }}" class="btn btn-info btn-sm mr-2">
                      <i class="fas fa-download"></i> Download Template </a>
                      @endcanRoute
                    <!-- IMPORT FORM -->
                    @canRoute('employee-late.import')
                    <form id="importForm" action="{{ route('employee-late.import') }}" method="POST" enctype="multipart/form-data"> @csrf <div class="input-group input-group-sm">
                        <input type="file" name="file" id="fileInput" class="d-none" accept=".xlsx,.xls,.csv">
                        <button type="button" class="btn btn-primary btn-sm" id="btnUpload">
                          <i class="fas fa-upload"></i> Upload Excel </button>
                        <span id="fileName" class="form-control form-control-sm text-truncate" style="max-width:200px; display:inline-block;"> No file selected </span>
                        <div class="input-group-append">
                          <button type="submit" class="btn btn-success btn-sm" id="btnImport">
                            <i class="fas fa-file-excel"></i> Import </button>
                        </div>
                      </div>
                    </form>
                    @endcanRoute
                  </div>
                </div>
                <!-- PROGRESS BAR -->
                <div class="progress mt-3" style="height:18px; display:none;" id="uploadProgress">
                  <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%" id="progressBar"> 0% </div>
                </div>
              </div>
              <div class="card-body">
                @if ($message = Session::get('success'))
                  <div class="alert alert-success">
                    {{ $message }}
                  </div>
                @endif
                @if ($message = Session::get('error'))
                  <div class="alert alert-danger">
                    {{ $message }}
                  </div>
                @endif
                @if (Session::has('import_errors'))
                  <div class="alert alert-warning">
                    <strong>Baris yang dilewati saat import:</strong>
                    <ul class="mb-0">
                      @foreach (Session::get('import_errors') as $err)
                        <li>{{ $err }}</li>
                      @endforeach
                    </ul>
                  </div>
                @endif
                <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable">
                    <thead>
                      <tr>
                        <th width="50">ID</th>
                        <th>NPK</th>
                        <th>Nama Karyawan</th>
                        <th>Date</th>
                        <th>Arrival Time</th>
                        <th>Reason</th>
                        <th width="120">Action</th>
                      </tr>
                    </thead>
                    <tbody> @foreach($data as $row) <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->npk }}</td>
                        <td>{{ $row->NAMA_KARYAWAN ?? '-' }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->date)->format('d-m-Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->arrival_time)->format('H:i') }}</td>
                        <td>{{ $row->reason }}</td>
                        <td class="text-center">
                          @canRoute('employee-late.edit')
                          <a href="{{ route('employee-late.edit',$row->id) }}" class="btn btn-primary btn-circle btn-sm">
                            <i class="fas fa-edit"></i>
                          </a>
                          @endcanRoute
                          @canRoute('employee-late.delete')
                          <button class="btn btn-danger btn-circle btn-sm btn-delete" data-link="{{ route('employee-late.delete',$row->id) }}" data-npk="{{ $row->npk }}" data-toggle="modal" data-target="#deleteModal">
                            <i class="fas fa-trash"></i>
                          </button>
                          @endcanRoute
                        </td>
                      </tr> @endforeach </tbody>
                  </table>
                        </div>
                    </div>
                </div>
                <!-- Content Row -->

            </div>
            <!-- /.container-fluid -->

        </div>
    <!-- DELETE MODAL -->
    <div class="modal fade" id="deleteModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Delete Record</h5>
            <button class="close" data-dismiss="modal">
              <span>×</span>
            </button>
          </div>
          <div class="modal-body">
            <p id="deleteText"></p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-dismiss="modal"> Cancel </button>
            <form id="deleteForm" method="POST" style="display:inline;">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-danger"> Delete </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    @include('layout.footer')
  </body>
  <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
  <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
  <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    $('.btn-delete').click(function() {
      let link = $(this).data('link');
      let npk = $(this).data('npk');
      $('#deleteForm').attr('action', link);
      $('#deleteText').text('Apakah anda yakin ingin menghapus data keterlambatan NPK ' + npk + ' ?');
    });
  </script>
  <script>
    $('#btnUpload').click(function() {
      $('#fileInput').click();
    });
    $('#fileInput').change(function() {
      let file = this.files[0];
      if (!file) return;
      let allowed = ['xlsx', 'xls', 'csv'];
      let ext = file.name.split('.').pop().toLowerCase();
      if (!allowed.includes(ext)) {
        Swal.fire({
          icon: 'error',
          title: 'Format tidak valid',
          text: 'File harus Excel (.xlsx, .xls, .csv)'
        });
        $(this).val('');
        return;
      }
      $('#fileName').text(file.name);
    });
    $('#importForm').submit(function(e) {
      e.preventDefault();
      let form = this;
      let formData = new FormData(form);
      $('#uploadProgress').show();
      $.ajax({
        xhr: function() {
          let xhr = new window.XMLHttpRequest();
          xhr.upload.addEventListener("progress", function(evt) {
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
        success: function(response) {
          $('#progressBar').css('width', '100%');
          $('#progressBar').text('100%');
          Swal.fire({
            icon: 'success',
            title: 'Import selesai',
            text: 'Halaman akan diperbarui...',
            showConfirmButton: false,
            timer: 1500
          });
          setTimeout(function() {
            location.reload();
          }, 1500);
        },
        error: function(xhr) {
          let msg = 'Terjadi kesalahan saat import.';
          if (xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
          }
          Swal.fire({
            icon: 'error',
            title: 'Import gagal',
            text: msg
          });
        }
      });
    });
  </script>
</html>