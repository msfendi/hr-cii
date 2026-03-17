<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Evaluation Questionnaire</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                  <h6 class="m-0 font-weight-bold text-primary"> Data Employee Evaluation</h6>
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
                        <th>NPK</th>
                        <th>Name</th>
                        <th>Dept</th>
                        <th>Jobscope</th>
                        <th>Evaluation Date</th>
                        <th>Score</th>
                        <th width="100">Action</th>
                      </tr>
                    </thead>
                    <tbody> @foreach($data as $row) <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->npk }}</td>
                        <td>{{ $row->NAMA_KARYAWAN }}</td>
                        <td>{{ $row->DEPARTEMENT }}</td>
                        <td>{{ $row->job_name }}</td>
                        <td>{{ $row->evaluation_date }}</td>
                        <td>{{ $row->score }}</td>
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