<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Evaluation Questionnaire</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                  <h6 class="m-0 font-weight-bold text-primary"> Data Questionnaire </h6>
                  <div class="d-flex align-items-center">
                    <a href="{{ route('evaluation-questionnaire.template') }}" class="btn btn-info btn-sm mr-2">
                      <i class="fas fa-download"></i> Download Template </a>
                    <form id="importForm" action="{{ route('evaluation-questionnaire.import') }}" method="POST" enctype="multipart/form-data"> @csrf <div class="input-group input-group-sm">
                        <input type="file" name="file" id="fileInput" class="d-none" accept=".xlsx,.xls,.csv">
                        <button type="button" class="btn btn-primary btn-sm" id="btnUpload">
                          <i class="fas fa-upload"></i> Upload Excel </button>
                        <span id="fileName" class="form-control form-control-sm text-truncate" style="max-width:200px;"> No file selected </span>
                        <div class="input-group-append">
                          <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel"></i> Import </button>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
                <div class="progress mt-3" style="height:18px; display:none;" id="uploadProgress">
                  <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%" id="progressBar">0%</div>
                </div>
              </div>
              <div class="card-body"> @if ($message = Session::get('success')) <div class="alert alert-success">{{ $message }}</div> @endif <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Jobscope</th>
                        <th>Question</th>
                        <th>Option A</th>
                        <th>Option B</th>
                        <th>Option C</th>
                        <th>Option D</th>
                        <th>Answer</th>
                        <th width="100">Action</th>
                      </tr>
                    </thead>
                    <tbody> @foreach($data as $row) <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->jobscope->job_name ?? '-' }}</td>
                        <td>{{ $row->question }}</td>
                        <td>{{ $row->optiona }}</td>
                        <td>{{ $row->optionb }}</td>
                        <td>{{ $row->optionc }}</td>
                        <td>{{ $row->optiond }}</td>
                        <td>{{ $row->correct_answer }}</td>
                        <td class="text-center">
                          <button class="btn btn-danger btn-circle btn-sm btn-delete" data-link="{{ route('evaluation-questionnaire.delete',$row->id) }}" data-question="{{ $row->question }}" data-toggle="modal" data-target="#deleteModal">
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
        {{-- DELETE MODAL --}}
        <div class="modal fade" id="deleteModal">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5>Delete</h5>
                <button class="close" data-dismiss="modal">
                  <span>×</span>
                </button>
              </div>
              <div class="modal-body">
                <p id="deleteText"></p>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a id="deleteLink">
                  <button class="btn btn-danger">Delete</button>
                </a>
              </div>
            </div>
          </div>
        </div> @include('layout.footer') <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
        <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
        <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
          $('.btn-delete').click(function() {
            let link = $(this).data('link');
            let question = $(this).data('question');
            $('#deleteLink').attr('href', link);
            $('#deleteText').text('Yakin hapus question: ' + question + ' ?');
          });
        </script>
        <script>
          $('#btnUpload').click(function() {
            $('#fileInput').click();
          });
          $('#fileInput').change(function() {
            let file = this.files[0];
            if (!file) return;
            let ext = file.name.split('.').pop().toLowerCase();
            if (!['xlsx', 'xls', 'csv'].includes(ext)) {
              Swal.fire('Error', 'Format harus Excel', 'error');
              $(this).val('');
              return;
            }
            $('#fileName').text(file.name);
          });
          $('#importForm').submit(function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            $('#uploadProgress').show();
            $.ajax({
              url: $(this).attr('action'),
              type: 'POST',
              data: formData,
              processData: false,
              contentType: false,
              xhr: function() {
                let xhr = new XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                  let percent = Math.round((evt.loaded / evt.total) * 100);
                  $('#progressBar').css('width', percent + '%').text(percent + '%');
                });
                return xhr;
              },
              success: function() {
                Swal.fire('Success', 'Import berhasil', 'success');
                setTimeout(() => location.reload(), 1500);
              },
              error: function() {
                Swal.fire('Error', 'Import gagal', 'error');
              }
            });
          });
        </script>
  </body>
</html>