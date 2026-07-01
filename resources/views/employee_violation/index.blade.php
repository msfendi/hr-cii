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
              <h1 class="h3 mb-0 text-gray-800">Employee Violation</h1>
              <!-- @canRoute('employee-violation.create') -->
              <a href="{{ route('employee-violation.create') }}" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Create Employee Violation </a>
              <!-- @endcanRoute -->
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Data Employee Violation </h6>
              </div>
              <div class="card-body"> @if ($message = Session::get('success')) <div class="alert alert-success">
                  {{ $message }}
                </div> @endif <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable">
                    <thead>
                      <tr>
                        <th width="50">ID</th>
                        <th>Period</th>
                        <th>NPK</th>
                        <th>Nama Karyawan</th>
                        <th>Department</th>
                        <th>Percentage</th>
                        <th width="120">Action</th>
                      </tr>
                    </thead>
                    <tbody> @foreach($data as $row) <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->name ?? '-' }}</td>
                        <td>{{ $row->npk }}</td>
                        <td>{{ $row->employee_name ?? '-' }}</td>
                        <td>{{ $row->department ?? '-' }}</td>
                        <td>{{ number_format($row->percentage, 2) }}%</td>
                        <td class="text-center">
                          @canRoute('employee-violation.edit')
                          <a href="{{ route('employee-violation.edit',$row->id) }}" class="btn btn-primary btn-circle btn-sm">
                            <i class="fas fa-edit"></i>
                          </a>
                          @endcanRoute
                          @canRoute('employee-violation.delete')
                          <button class="btn btn-danger btn-circle btn-sm btn-delete" data-link="{{ route('employee-violation.delete',$row->id) }}" data-npk="{{ $row->npk }}" data-toggle="modal" data-target="#deleteModal">
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
            <a id="deleteLink" href="">
              <button class="btn btn-danger"> Delete </button>
            </a>
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
      $('#deleteLink').attr('href', link);
      $('#deleteText').text('Apakah anda yakin ingin menghapus data pelanggaran NPK ' + npk + ' ?');
    });
  </script>
</html>