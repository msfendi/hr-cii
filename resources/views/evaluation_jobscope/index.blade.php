<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Evaluation Jobscope</h1>
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
                        <th>Job Code</th>
                        <th>Job Name</th>
                        <th>Description</th>
                        <th>QR Code</th>
                        <th width="100">Action</th>
                      </tr>
                    </thead>
                    <tbody> @foreach($data as $row) <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->job_code }}</td>
                        <td>{{ $row->job_name }}</td>
                        <td>{{ $row->description }}</td>
                        <td class="text-center" style="width:200px;">

                        @php
                        $qrBig = QrCode::size(450)->generate(
                                url('/evaluation-employee/portal?jobscope_id='.$row->id)
                            );
                        @endphp

                        <div class="qr-preview"
                            data-qr="{{ e($qrBig) }}"
                            style="cursor:pointer">

                            {!! QrCode::size(120)->generate(
                                url('/evaluation-employee/portal?jobscope_id='.$row->id)
                            ) !!}

                        </div>

                        </td>
                        <td class="text-center">
                        @canRoute('evaluation-jobscope.delete')
                          <button class="btn btn-danger btn-circle btn-sm btn-delete" data-link="{{ route('evaluation-jobscope.delete',$row->id) }}" data-job_name="{{ $row->job_name }}" data-toggle="modal" data-target="#deleteModal">
                            <i class="fas fa-trash"></i>
                          </button>
                        @endcanRoute
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
        </div> 
        {{-- QR PREVIEW MODAL --}}
        <div class="modal fade" id="qrModal">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content text-center">

                    <div class="modal-header">
                        <h5 class="modal-title">QR Code Preview</h5>
                        <button class="close" data-dismiss="modal">
                            <span>×</span>
                        </button>
                    </div>

                    <div class="modal-body p-4">

                        <div id="qrContainer"
                            style="
                                display:flex;
                                justify-content:center;
                                align-items:center;
                                min-height:500px;
                            ">
                        </div>

                    </div>

                </div>
            </div>
        </div>
@include('layout.footer') <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
        <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
        <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
          $('.btn-delete').click(function() {
            let link = $(this).data('link');
            let job_name = $(this).data('job_name');
            $('#deleteLink').attr('href', link);
            $('#deleteText').text('Yakin hapus jobscope: ' + job_name + ' ?');
          });
        </script>

        <script>
        $('.btn-delete').click(function() {
            let link = $(this).data('link');
            let job_name = $(this).data('job_name');

            $('#deleteLink').attr('href', link);
            $('#deleteText').text('Yakin hapus jobscope: ' + job_name + ' ?');
        });

        // ✅ CLICK QR SHOW MODAL
        $(document).on('click', '.qr-preview', function () {
            let qr = $(this).data('qr');

            $('#qrContainer').html(qr);
            $('#qrModal').modal('show');
        });
        </script>
  </body>
</html>