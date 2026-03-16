<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Payroll Master</h1>
              <a href="{{ route('payroll-master.create') }}" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Create Payroll Master </a>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="m-0 font-weight-bold text-primary"> Data Payroll Master </h6>
                </div>
              </div>
              <div class="card-body"> @if ($message = Session::get('success')) <div class="alert alert-success">
                  {{ $message }}
                </div> @endif <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable">
                    <thead>
                      <tr>
                        <th width="50">ID</th>
                        <th>NPK</th>
                        <th>Salary</th>
                        <th>Allowance</th>
                        <th>PPH21</th>
                        <th width="120">Action</th>
                      </tr>
                    </thead>
                    <tbody> @foreach($data as $row) <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->npk }}</td>
                        <td>Rp {{ number_format($row->salary,0,',','.') }}</td>
                        <td>Rp {{ number_format($row->allowance,0,',','.') }}</td>
                        <td>Rp {{ number_format($row->pph21,0,',','.') }}</td>
                        <td class="text-center">
                          <a href="{{ route('payroll-master.edit',$row->id) }}" class="btn btn-primary btn-circle btn-sm">
                            <i class="fas fa-edit"></i>
                          </a>
                          <button class="btn btn-danger btn-circle btn-sm btn-delete" data-link="{{ route('payroll-master.delete',$row->id) }}" data-npk="{{ $row->npk }}" data-toggle="modal" data-target="#deleteModal">
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
    </div> @include('layout.footer')
  </body>
  <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
  <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
  <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    $('.btn-delete').click(function() {
      let link = $(this).data('link');
      let npk = $(this).data('npk');
      $('#deleteLink').attr('href', link);
      $('#deleteText').text('Apakah anda yakin ingin menghapus data payroll NPK ' + npk + ' ?');
    });
  </script>
</html>