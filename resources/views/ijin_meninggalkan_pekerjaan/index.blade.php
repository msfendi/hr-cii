<!DOCTYPE html>
<html lang="en">
  @include('layout.header')

  <body id="page-top">

    <div id="wrapper">
      @include('layout.sidebar')

      <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">

          @include('layout.navbar')

          <div class="container-fluid">

            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Ijin Meninggalkan Pekerjaan</h1>

              @canRoute('ijin-meninggalkan-pekerjaan.create')
              <a href="{{ route('ijin-meninggalkan-pekerjaan.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Create Ijin
              </a>
              @endcanRoute
            </div>

            <div class="card shadow mb-4">

              <div class="card-header">
                <h6 class="font-weight-bold text-primary">
                  Data Ijin Meninggalkan Pekerjaan
                </h6>
              </div>

              <div class="card-body">

                @if ($message = Session::get('success'))
                <div class="alert alert-success">
                  {{ $message }}
                </div>
                @endif

                <div class="table-responsive">

                  <table class="table table-bordered table-sm" id="dataTable">

                    <thead class="thead-light">
                      <tr>
                        <th>ID</th>
                        <th>NPK</th>
                        <th>Nama Karyawan</th>
                        <th>Dept</th>
                        <th>Tanggal</th>
                        <th>Jam Keluar</th>
                        <th>Rencana Kembali</th>
                        <th>Jam Kembali</th>
                        <th>Break</th>
                        <th>Potong Jam Kerja</th>
                        <th>Reason</th>
                        <th width="120">Action</th>
                      </tr>
                    </thead>

                    <tbody>
                      @foreach($data as $row)
                      <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->npk }}</td>
                        <td>{{ $row->NAMA_KARYAWAN }}</td>
                        <td>{{ $row->DEPARTEMENT }}</td>
                        <td>{{ $row->tanggal }}</td>
                        <td>{{ $row->jam_keluar }}</td>
                        <td>{{ $row->rencana_kembali }}</td>
                        <td>{{ $row->jam_kembali }}</td>
                        <td>
                          @if($row->break_sesi)
                            {{ $row->break_sesi }}
                            ({{ \Carbon\Carbon::parse($row->break_time_start)->format('H:i') }}
                            - {{ \Carbon\Carbon::parse($row->break_time_end)->format('H:i') }})
                          @else
                            -
                          @endif
                        </td>
                        <td class="text-center">
                          @if($row->is_deduction)
                            <span class="badge badge-danger">Dipotong</span>
                          @else
                            <span class="badge badge-secondary">Tidak Dipotong</span>
                          @endif
                        </td>
                        <td>{{ $row->reason }}</td>

                        <td class="text-center">

                          @canRoute('ijin-meninggalkan-pekerjaan.edit')
                          <a href="{{ route('ijin-meninggalkan-pekerjaan.edit',$row->id) }}" class="btn btn-primary btn-circle btn-sm">
                            <i class="fas fa-edit"></i>
                          </a>
                          @endcanRoute

                          @canRoute('ijin-meninggalkan-pekerjaan.delete')
                          <button class="btn btn-danger btn-circle btn-sm btn-delete" data-link="{{ route('ijin-meninggalkan-pekerjaan.delete',$row->id) }}" data-id="{{ $row->id }}" data-toggle="modal" data-target="#deleteModal">
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

        @include('layout.footer')

      </div>
    </div>

    <div class="modal fade" id="deleteModal">
      <div class="modal-dialog">
        <div class="modal-content">

          <div class="modal-header">
            <h5>Delete Data</h5>
            <button class="close" data-dismiss="modal">×</button>
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

    <script>
      $('.btn-delete').click(function() {
        let link = $(this).data('link');
        let id = $(this).data('id');
        $('#deleteLink').attr('href', link);
        $('#deleteText').text('Yakin hapus data ID ' + id + ' ?');
      });
    </script>

    <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
    <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

    <script>
      $(document).ready(function() {
        $('#dataTable').DataTable({
          order: [
            [0, 'desc']
          ],
          pageLength: 10,
          responsive: true,
          autoWidth: false
        });
      });
    </script>

  </body>

</html>