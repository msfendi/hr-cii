<!-- resources/views/food_menu/index.blade.php -->
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
            <h1 class="h3 mb-0 text-gray-800">Daftar Menu Makanan</h1>
            @canRoute('food-menus.create')
            <a href="{{ route('food-menus.create') }}" class="btn btn-sm btn-primary shadow-sm">
              <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Menu
            </a>
            @endcanRoute
          </div>

          <div class="card shadow mb-4">
            <div class="card-header py-3">
              <h6 class="m-0 font-weight-bold text-primary">Data Menu Makanan</h6>
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
                      <th width="80">Foto</th>
                      <th>Nama Menu</th>
                      <th>Kantin</th>
                      <th>Harga</th>
                      <th>Kuota</th>
                      <th>Ketersediaan</th>
                      <th width="90">Status</th>
                      <th width="120">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($data as $row)
                    <tr>
                      <td>{{ $row->id }}</td>
                      <td>
                        @if($row->photo)
                          <img src="{{ asset('storage/'.$row->photo) }}" width="50" height="50" style="object-fit:cover;border-radius:4px;">
                        @else
                          <span class="text-muted small">-</span>
                        @endif
                      </td>
                      <td>{{ $row->name }}</td>
                      <td><span class="badge badge-primary">{{ $row->canteen->name ?? '-' }}</span></td>
                      <td>Rp {{ number_format($row->price,0,',','.') }}</td>
                      <td>{{ $row->quota ?? 'Unlimited' }}</td>
                      <td class="small">
                        @if($row->available_start || $row->available_end)
                          {{ $row->available_start ? \Carbon\Carbon::parse($row->available_start)->format('d/m/Y') : '...' }}
                          s/d
                          {{ $row->available_end ? \Carbon\Carbon::parse($row->available_end)->format('d/m/Y') : '...' }}
                          <br>
                        @endif
                        @php $dates = $row->available_dates ?? []; @endphp
                        @if(!empty($dates))
                          @php
                            sort($dates);
                            $shown = array_slice($dates, 0, 2);
                            $more = count($dates) - count($shown);
                          @endphp
                          <span class="badge badge-light border" title="{{ implode(', ', array_map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m/Y'), $dates)) }}">
                            <i class="fas fa-calendar-day"></i>
                            {{ implode(', ', array_map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m'), $shown)) }}
                            @if($more > 0) +{{ $more }} lagi @endif
                          </span>
                        @endif
                        @if(!$row->available_start && !$row->available_end && empty($dates))
                          <span class="text-muted">Setiap hari</span>
                        @endif
                      </td>
                      <td>
                        @if($row->is_active)
                          <span class="badge badge-success">Aktif</span>
                        @else
                          <span class="badge badge-secondary">Nonaktif</span>
                        @endif
                      </td>
                      <td class="text-center">
                        @canRoute('food-menus.edit')
                        <a href="{{ route('food-menus.edit',$row->id) }}" class="btn btn-primary btn-circle btn-sm">
                          <i class="fas fa-edit"></i>
                        </a>
                        @endcanRoute
                        @canRoute('food-menus.delete')
                        <button class="btn btn-danger btn-circle btn-sm btn-delete" data-link="{{ route('food-menus.delete',$row->id) }}" data-name="{{ $row->name }}" data-toggle="modal" data-target="#deleteModal">
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

    <div class="modal fade" id="deleteModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Hapus Menu</h5>
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
  </body>
  <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
  <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
  <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
  <script>
    $('.btn-delete').click(function() {
      let link = $(this).data('link');
      let name = $(this).data('name');
      $('#deleteForm').attr('action', link);
      $('#deleteText').text('Apakah anda yakin ingin menghapus menu "' + name + '" ?');
    });
  </script>
</html>