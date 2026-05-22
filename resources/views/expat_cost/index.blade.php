<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <!-- TITLE -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Expat Cost</h1>
              <a href="{{ route('expat.cost.create') }}" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Create Expat Cost </a>
            </div>
            <div class="card shadow mb-4">
              <!-- HEADER -->
              <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                  <h6 class="m-0 font-weight-bold text-primary"> Expat Cost Data </h6>
                  <div class="d-flex align-items-center">
                    <!-- DOWNLOAD TEMPLATE
                    <a href="{{ route('expat.template.cost') }}" class="btn btn-info btn-sm mr-2">
                      <i class="fas fa-download"></i> Download Template </a> -->
                    <!-- IMPORT -->
                    <!-- <form id="importForm" action="{{ route('expat.import.cost') }}" method="POST" enctype="multipart/form-data"> @csrf <div class="input-group input-group-sm">
                        <input type="file" name="file" id="fileInput" class="d-none" accept=".xlsx,.xls,.csv">
                        <button type="button" class="btn btn-primary btn-sm" id="btnUpload">
                          <i class="fas fa-upload"></i> Upload Excel </button>
                        <span id="fileName" class="form-control form-control-sm text-truncate" style="max-width:200px;"> No file selected </span>
                        <div class="input-group-append">
                          <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel"></i> Import </button>
                        </div>
                      </div>
                    </form> -->
                  </div>
                </div>
                <!-- PROGRESS BAR -->
                <div class="progress mt-3" style="height:18px; display:none;" id="uploadProgress">
                  <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%" id="progressBar">0%</div>
                </div>
              </div>
              <!-- BODY -->
              <div class="card-body"> @if ($message = Session::get('success')) <div class="alert alert-success">
                  {{ $message }}
                </div> @endif <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable">
                    <thead class="thead-light">
                      <tr>
                        <th width="50">ID</th>
                        <th>NPK</th>
                        <th>Name</th>
                        <th>Component</th>
                        <th>Amount</th>
                        <th>Transaction Date</th>
                        <th>Remark</th> 
                        <th width="120">Action</th>
                      </tr>
                    </thead>
                    <tbody> @foreach($data as $row) <tr>
                        <td>{{ $row->id }}</td>
                        <td>
                          <span class="badge badge-dark">
                            {{ $row->npk }}
                          </span>
                        </td>
                        <td>{{ $row->NAMA_KARYAWAN }}</td>
                        <td>{{ $row->component_name }}</td>
                        <td> Rp {{ number_format($row->amount,0,',','.') }}
                        </td>
                        <td>{{ $row->transactions_date }}</td>
                        <td>{{ $row->remark }}</td>
                        <td class="text-center">
                          <a href="{{ route('expat.cost.edit',$row->id) }}" class="btn btn-primary btn-circle btn-sm">
                            <i class="fas fa-edit"></i>
                          </a>
                          <button class="btn btn-danger btn-circle btn-sm btn-delete" data-link="{{ route('expat.cost.delete',$row->id) }}" data-npk="{{ $row->npk }}" data-toggle="modal" data-target="#deleteModal">
                            <i class="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr> @endforeach </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
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
                <a id="deleteLink" href="">
                  <button class="btn btn-danger">Delete</button>
                </a>
              </div>
            </div>
          </div>
        </div> @include('layout.footer') </body>
  <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
  <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
  <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    $('.btn-delete').click(function() {
      let link = $(this).data('link');
      let npk = $(this).data('npk');
      $('#deleteLink').attr('href', link);
      $('#deleteText').text('Apakah anda yakin ingin menghapus expat cost NPK ' + npk + ' ?');
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
        success: function() {
          $('#progressBar').css('width', '100%');
          $('#progressBar').text('100%');
          Swal.fire({
            icon: 'success',
            title: 'Import berhasil',
            text: 'Halaman akan diperbarui...',
            showConfirmButton: false,
            timer: 1500
          });
          setTimeout(function() {
            location.reload();
          }, 1500);
        },
        error: function() {
          Swal.fire({
            icon: 'error',
            title: 'Import gagal'
          });
        }
      });
    });
  </script>
</html>