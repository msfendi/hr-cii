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
              <h1 class="h3 mb-0 text-gray-800">Data Kantin</h1>
              @canRoute('canteens.create')
              <a href="{{ route('canteens.create') }}" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Kantin
              </a>
              @endcanRoute
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Daftar Kantin</h6>
              </div>
              <div class="card-body">
                @if ($message = Session::get('success'))
                  <div class="alert alert-success">{{ $message }}</div>
                @endif
                <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable">
                    <thead>
                      <tr>
                        <th width="50">ID</th>
                        <th>Nama Kantin</th>
                        <th>Lokasi</th>
                        <th>PIC</th>
                        <th>No. HP PIC</th>
                        <th width="90">Status</th>
                        <th width="120">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($data as $row)
                      <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->location }}</td>
                        <td>{{ $row->pic_name }}</td>
                        <td>{{ $row->pic_phone }}</td>
                        <td>
                          @if($row->is_active)
                            <span class="badge badge-success">Aktif</span>
                          @else
                            <span class="badge badge-secondary">Nonaktif</span>
                          @endif
                        </td>
                        <td class="text-center">
                          @canRoute('canteens.edit')
                          <a href="{{ route('canteens.edit',$row->id) }}" class="btn btn-primary btn-circle btn-sm">
                            <i class="fas fa-edit"></i>
                          </a>
                          @endcanRoute
                          @canRoute('canteens.delete')
                          <button class="btn btn-danger btn-circle btn-sm btn-delete" data-link="{{ route('canteens.delete',$row->id) }}" data-name="{{ $row->name }}" data-toggle="modal" data-target="#deleteModal">
                            <i class="fas fa-trash"></i>
                          </button>
                          @endcanRoute
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
    <div class="modal fade" id="deleteModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Hapus Kantin</h5>
            <button class="close" data-dismiss="modal"><span>&times;</span></button>
          </div>
          <div class="modal-body"><p id="deleteText"></p></div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-dismiss="modal">Batal</button>
            <form id="deleteForm" method="POST" action="">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-danger">Hapus</button>
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
  <script>
    $('.btn-delete').click(function() {
      let link = $(this).data('link');
      let name = $(this).data('name');
      $('#deleteForm').attr('action', link);
      $('#deleteText').text('Apakah anda yakin ingin menghapus kantin "' + name + '" ?');
    });
  </script>
</html>