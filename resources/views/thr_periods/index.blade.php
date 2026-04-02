<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            {{-- ================= PAGE TITLE ================= --}}
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Daftar THR Period</h1>
              <div>
                <a href="{{ route('thr-periods.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                  <i class="fas fa-plus fa-sm text-white-50"></i> Create THR Period </a>
              </div>
            </div>
            {{-- ================= TABLE ================= --}}
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Data THR Period </h6>
              </div>
              <div class="card-body">
                {{-- ALERT --}} @if ($message = Session::get('success')) <div class="alert alert-success alert-block">
                  <button type="button" class="close" data-dismiss="alert">×</button>
                  <strong>{{ $message }}</strong>
                </div> @endif @if ($message = Session::get('error')) <div class="alert alert-danger alert-block">
                  <button type="button" class="close" data-dismiss="alert">×</button>
                  <strong>{{ $message }}</strong>
                </div> @endif @if ($message = Session::get('warning')) <div class="alert alert-warning alert-block">
                  <button type="button" class="close" data-dismiss="alert">×</button>
                  <strong>{{ $message }}</strong>
                </div> @endif @if ($message = Session::get('info')) <div class="alert alert-info alert-block">
                  <button type="button" class="close" data-dismiss="alert">×</button>
                  <strong>{{ $message }}</strong>
                </div> @endif <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Period Name</th>
                        <th>Cutoff Date</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody> @foreach($periods as $period) <tr>
                        <td>{{ $period->id }}</td>
                        <td>{{ $period->name }}</td>
                        <td>{{ $period->cutoff_date }}</td>
                        <td class="text-center">
                          <a class="btn btn-danger btn-circle btn-sm btn-delete-thr" data-delete-link="{{ route('thr-periods.delete',['id'=>$period->id]) }}" data-thr-name="{{ $period->name }}" data-toggle="modal" data-target="#deleteModal">
                            <i class="fas fa-trash"></i>
                          </a>
                        </td>
                      </tr> @endforeach </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
        {{-- ================= DELETE MODAL ================= --}}
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
          <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Delete Record</h5>
                <button class="close" type="button" data-dismiss="modal">
                  <span>x</span>
                </button>
              </div>
              <div class="modal-body">
                <p id="modal-text-thr"></p>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal"> Tutup </button>
                <a id="btn-confirm" href="">
                  <button class="btn btn-primary" type="button"> Confirm </button>
                </a>
              </div>
            </div>
          </div>
        </div> @include('layout.footer') </body>
  {{-- ================= DATATABLE ================= --}}
  <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
  <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
  <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
  <script>
    $('.btn-delete-thr').on('click', function() {
      $('#btn-confirm').attr('href', $(this).data('delete-link'));
      $("#modal-text-thr").text('Apakah anda yakin ingin menghapus THR period ' + $(this).data('thr-name') + '?');
    });
  </script>
</html>