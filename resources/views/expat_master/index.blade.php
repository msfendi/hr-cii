<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <!-- TITLE -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Expat Master</h1>
              <a href="{{ route('expat.master.create') }}" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Create Expat </a>
            </div>
            <div class="card shadow mb-4">
              <!-- HEADER -->
              <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                  <h6 class="m-0 font-weight-bold text-primary"> Expat Master Data </h6>
                  <div class="d-flex align-items-center">
                    <button class="btn btn-success btn-sm mr-2" data-toggle="modal" data-target="#rekapModal">
                        <i class="fas fa-file-excel"></i>
                        Download Rekap Expat
                    </button>
                    <!-- DOWNLOAD TEMPLATE -->
                        @canRoute('expat.template.master')
                    <a href="{{ route('expat.template.master') }}" class="btn btn-info btn-sm mr-2">
                      <i class="fas fa-download"></i> Download Template </a>
                      @endcanRoute
                    <!-- IMPORT -->
                        @canRoute('expat.import.master')
                    <form id="importForm" action="{{ route('expat.import.master') }}" method="POST" enctype="multipart/form-data"> @csrf <div class="input-group input-group-sm">
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
                    @endcanRoute
                  </div>
                </div>
                <!-- PROGRESS BAR -->
                <div class="progress mt-3" style="height:18px; display:none;" id="uploadProgress">
                  <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%" id="progressBar"> 0% </div>
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
                        <th>Position</th>
                        <th>Date of Birth</th>
                        <th>Place</th>
                        <th>Nationality</th>
                        <th>House Address</th>
                        <th>Direct Report</th>
                        <th>NPWP</th>
                        <th>Joining</th>
                        <th>End</th>
                        <th>Passport</th>
                        <th>Passport Exp</th>
                        <th>KITAS Exp</th>
                        <th>KITAS Status</th>
                        <th>RPTKA Exp</th>
                        <th>RPTKA Status</th>
                        <th>Lease End</th>
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
                        <td>{{ $row->name }}</td>
                        <td> @if($row->position) <span class="badge badge-primary">
                            {{ $row->position }}
                          </span> @endif </td>
                        <td>{{ $row->TGLLAHIR }}</td>
                        <td>{{ $row->place }}</td>
                        <td>{{ $row->nationality }}</td>
                        <td>{{ $row->house_address }}</td>
                        <td>{{ $row->direct_report }}</td>
                        <td>{{ $row->npwp }}</td>
                        <td> @if($row->joining_date) <span class="badge badge-secondary">
                            {{ $row->joining_date }}
                          </span> @endif </td>
                        <td>@if($row->end_date) <span class="badge badge-secondary">
                            {{ $row->end_date }}
                          </span> @endif</td>
                        <td>{{ $row->passport_number }}</td>
                        <td> @if($row->passport_expiry) <span class="badge badge-secondary">
                            {{ $row->passport_expiry }}
                          </span> @endif </td>
                        <td> @if($row->kitas_expiry) <span class="badge badge-secondary">
                            {{ $row->kitas_expiry }}
                          </span> @endif </td>
                          <td>
                            @php
                            $today = \Carbon\Carbon::today();

                            /* ================= KITAS ================= */
                            $kitasStatus = '-';
                            $kitasClass = 'secondary';

                            if($row->kitas_expiry){

                                $diff = $today->diffInDays(
                                    \Carbon\Carbon::parse($row->kitas_expiry),
                                    false
                                );

                                if($diff < 0){
                                    $kitasStatus = 'EXPIRED';
                                    $kitasClass = 'danger';
                                }elseif($diff <= 30){
                                    $kitasStatus = $diff.' Days Left';
                                    $kitasClass = 'warning';
                                }else{
                                    $kitasStatus = $diff.' Days';
                                    $kitasClass = 'success';
                                }
                            }
                            @endphp
                            <span class="badge badge-{{ $kitasClass }}">
                                {{ $kitasStatus }}
                          </td>
                        <td> @if($row->rptka_expiry) <span class="badge badge-secondary">
                            {{ $row->rptka_expiry }}
                          </span> @endif </td>
                          <td>
                            
                            @php
                            /* ================= RPTKA ================= */
                            $rptkaStatus = '-';
                            $rptkaClass = 'secondary';

                            if($row->rptka_expiry){

                                $diff = $today->diffInDays(
                                    \Carbon\Carbon::parse($row->rptka_expiry),
                                    false
                                );

                                if($diff < 0){
                                    $rptkaStatus = 'EXPIRED';
                                    $rptkaClass = 'danger';
                                }elseif($diff <= 30){
                                    $rptkaStatus = $diff.' Days Left';
                                    $rptkaClass = 'warning';
                                }else{
                                    $rptkaStatus = $diff.' Days';
                                    $rptkaClass = 'success';
                                }
                            }
                            @endphp
                            <span class="badge badge-{{ $rptkaClass }}">
                                {{ $rptkaStatus }}
                          </td>
                        <td>@if($row->lease_enddate) <span class="badge badge-secondary">
                            {{ $row->lease_enddate }}
                          </span> @endif</td>
                          <td class="text-center">
                          @canRoute('expat.master.edit')
                          <a href="{{ route('expat.master.edit',$row->id) }}" class="btn btn-primary btn-circle btn-sm">
                            <i class="fas fa-edit"></i>
                          </a>
                          @endcanRoute
                          @canRoute('expat.master.delete')
                          <button class="btn btn-danger btn-circle btn-sm btn-delete" data-link="{{ route('expat.master.delete',$row->id) }}" data-npk="{{ $row->npk }}" data-toggle="modal" data-target="#deleteModal">
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
                <button class="btn btn-danger"> Delete </button>
              </a>
            </div>
          </div>
        </div>
      </div> 
      <!-- REKAP MODAL -->
<div class="modal fade" id="rekapModal">
    <div class="modal-dialog">
        <form method="GET" action="{{ route('expat.rekap.export') }}">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Download Rekap Expat</h5>
                    <button class="close" data-dismiss="modal">
                        <span>×</span>
                    </button>
                </div>

                <div class="modal-body">

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-file-excel"></i>
                        Download
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>
@include('layout.footer') </body>
  <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
  <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
  <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    $('.btn-delete').click(function() {
      let link = $(this).data('link');
      let npk = $(this).data('npk');
      $('#deleteLink').attr('href', link);
      $('#deleteText').text('Apakah anda yakin ingin menghapus expat NPK ' + npk + ' ?');
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