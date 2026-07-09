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
              <h1 class="h3 mb-0 text-gray-800">QR Login Devices</h1>
            </div>

            {{-- Panel: percobaan scan dari device yang belum terdaftar --}}
            @if($pendingAttempts->count())
            <div class="card shadow mb-4 border-left-warning">
            <div class="card-header py-3 bg-warning">
                <h6 class="m-0 font-weight-bold text-white">
                <i class="fas fa-exclamation-triangle"></i> Menunggu Persetujuan Device ({{ $pendingAttempts->count() }})
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-sm" id="pendingTable">
                    <thead>
                    <tr>
                        <th>User</th><th>NPK</th><th>Device</th><th>Tipe</th><th>IP</th><th>Waktu</th><th width="80">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($pendingAttempts as $attempt)
                    <tr>
                        <td>{{ $attempt->user->name ?? '-' }}</td>
                        <td>{{ $attempt->npk_scanned }}</td>
                        <td>
                        {{ $attempt->device_name }}<br>
                        <small class="text-muted">{{ $attempt->device_uuid }}</small>
                        </td>
                        <td><span class="badge badge-secondary">{{ ucfirst($attempt->device_type) }}</span></td>
                        <td>{{ $attempt->ip_address }}</td>
                        <td>{{ $attempt->created_at->diffForHumans() }}</td>
                        <td class="text-center">
                        @canRoute('qr-devices.store')
                        <button
                            class="btn btn-success btn-circle btn-sm btn-assign"
                            data-uuid="{{ $attempt->device_uuid }}"
                            data-name="{{ $attempt->device_name }}"
                            data-type="{{ $attempt->device_type }}"
                            data-platform="{{ $attempt->platform }}"
                            data-browser="{{ $attempt->browser }}"
                            data-userid="{{ $attempt->user_id }}"
                            title="Assign Device">
                            <i class="fas fa-check"></i>
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
            @endif

            {{-- PANEL: Device terdaftar --}}
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                  <h6 class="m-0 font-weight-bold text-primary">Data Device Terdaftar</h6>
                  @canRoute('qr-devices.store')
                  <button type="button" class="btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#assignModal">
                    <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Device Manual
                  </button>
                  @endcanRoute
                </div>
              </div>
              <div class="card-body">
                @if ($message = Session::get('success'))
                <div class="alert alert-success">
                  {{ $message }}
                </div>
                @endif
                <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable">
                    <thead>
                      <tr>
                        <th width="50">ID</th>
                        <th>User</th>
                        <th>NPK</th>
                        <th>Device</th>
                        <th>Tipe</th>
                        <th>Assign Oleh</th>
                        <th>Terakhir Dipakai</th>
                        <th>Status</th>
                        <th width="120">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                    @foreach($devices as $device)
                      <tr>
                        <td>{{ $device->id }}</td>
                        <td>{{ $device->user->name }}</td>
                        <td>{{ $device->user->npk }}</td>
                        <td>
                          {{ $device->device_name }}<br>
                          <small class="text-muted">{{ $device->device_uuid }}</small>
                        </td>
                        <td><span class="badge badge-primary">{{ ucfirst($device->device_type) }}</span></td>
                        <td>{{ $device->assignedBy->name ?? '-' }}</td>
                        <td>{{ $device->last_used_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                        <td>
                          <span class="badge badge-{{ $device->is_active ? 'success' : 'secondary' }}">
                            {{ $device->is_active ? 'Aktif' : 'Nonaktif' }}
                          </span>
                        </td>
                        
                        <td class="text-center">
                            @canRoute('qr-devices.rename')
                            <button class="btn btn-secondary btn-circle btn-sm btn-rename"
                                data-link="{{ route('qr-devices.rename', $device) }}"
                                data-current="{{ $device->device_name }}"
                                title="Rename Device">
                                <i class="fas fa-pen"></i>
                            </button>
                            @endcanRoute
                            @canRoute('qr-devices.toggle')
                            <button class="btn btn-warning btn-circle btn-sm btn-toggle"
                                data-link="{{ route('qr-devices.toggle', $device) }}"
                                title="{{ $device->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                <i class="fas {{ $device->is_active ? 'fa-ban' : 'fa-check-circle' }}"></i>
                            </button>
                            @endcanRoute
                            @canRoute('qr-devices.destroy')
                            <button class="btn btn-danger btn-circle btn-sm btn-delete"
                                data-link="{{ route('qr-devices.destroy', $device) }}"
                                data-npk="{{ $device->user->npk }}"
                                data-toggle="modal" data-target="#deleteModal">
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
          <!-- /.container-fluid -->

        </div>

    {{-- MODAL: Assign / Tambah Device --}}
    <div class="modal fade" id="assignModal">
      <div class="modal-dialog">
        <form action="{{ route('qr-devices.store') }}" method="POST" class="modal-content">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Assign Device ke User</h5>
            <button type="button" class="close" data-dismiss="modal">
              <span>×</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>User</label>
              <select name="user_id" id="assign_user_id" class="form-control" required>
                <option value="">-- Pilih User --</option>
                @foreach($users as $u)
                  <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->npk }})</option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label>Device UUID</label>
              <input type="text" name="device_uuid" id="assign_device_uuid" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Nama Device</label>
              <input type="text" name="device_name" id="assign_device_name" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Tipe Device</label>
              <select name="device_type" id="assign_device_type" class="form-control">
                <option value="desktop">Desktop</option>
                <option value="laptop">Laptop</option>
                <option value="mobile">Mobile</option>
                <option value="tablet">Tablet</option>
              </select>
            </div>
            <input type="hidden" name="platform" id="assign_platform">
            <input type="hidden" name="browser" id="assign_browser">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    {{-- MODAL: Delete (pola sama dengan payroll-master) --}}
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
    // Delete modal (sama pola dengan payroll-master, tapi pakai method DELETE via form)
    $('.btn-delete').click(function() {
      let link = $(this).data('link');
      let npk = $(this).data('npk');
      $('#deleteText').text('Apakah anda yakin ingin menghapus device untuk NPK ' + npk + ' ?');
      $('#deleteLink').off('click').on('click', function(e) {
        e.preventDefault();
        $('<form>', {
          action: link,
          method: 'POST'
        }).append('@csrf').append('@method("DELETE")').appendTo('body').submit();
      });
    });

    // Toggle aktif/nonaktif device
    $('.btn-toggle').click(function() {
      let link = $(this).data('link');
      Swal.fire({
        icon: 'question',
        title: 'Ubah status device?',
        showCancelButton: true,
        confirmButtonText: 'Ya, ubah',
        cancelButtonText: 'Batal'
      }).then((result) => {
        if (result.isConfirmed) {
          $('<form>', {
            action: link,
            method: 'POST'
          })
          .append('@csrf').append('@method("PATCH")')
          .appendTo('body').submit();
        }
      });
    });

    // Auto-fill modal assign dari data pending attempt
    $('.btn-assign').click(function () {

        let userId   = $(this).data('userid');
        let uuid     = $(this).data('uuid');
        let name     = $(this).data('name');
        let type     = $(this).data('type');
        let platform = $(this).data('platform');
        let browser  = $(this).data('browser');

        Swal.fire({
            icon: 'question',
            title: 'Assign Device?',
            text: 'Device ini akan langsung didaftarkan ke user.',
            showCancelButton: true,
            confirmButtonText: 'Ya, Assign',
            cancelButtonText: 'Batal'
        }).then((result) => {

            if (result.isConfirmed) {

                $('<form>', {
                    action: "{{ route('qr-devices.store') }}",
                    method: 'POST'
                })
                .append('@csrf')
                .append($('<input>', {
                    type: 'hidden',
                    name: 'user_id',
                    value: userId
                }))
                .append($('<input>', {
                    type: 'hidden',
                    name: 'device_uuid',
                    value: uuid
                }))
                .append($('<input>', {
                    type: 'hidden',
                    name: 'device_name',
                    value: name
                }))
                .append($('<input>', {
                    type: 'hidden',
                    name: 'device_type',
                    value: type
                }))
                .append($('<input>', {
                    type: 'hidden',
                    name: 'platform',
                    value: platform
                }))
                .append($('<input>', {
                    type: 'hidden',
                    name: 'browser',
                    value: browser
                }))
                .appendTo('body')
                .submit();

            }

        });

    });

    // Reset form saat modal ditutup / dibuka manual "Tambah Device Manual"
    $('#assignModal').on('hidden.bs.modal', function () {
      $(this).find('form')[0].reset();
    });

    // Init DataTables untuk kedua tabel
$('#dataTable').DataTable();
$('#pendingTable').DataTable({
    order: [[5, 'desc']] // urut berdasarkan kolom Waktu
});

// Rename device — pakai SweetAlert2 input, mirip UI "Rename" Windows di screenshot
$('.btn-rename').click(function() {
    let link = $(this).data('link');
    let current = $(this).data('current');

    Swal.fire({
        title: 'Rename Device',
        input: 'text',
        inputValue: current,
        inputPlaceholder: 'contoh: LAPTOP-A1ASN35C atau A56-Milik Dimas',
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        inputValidator: (value) => {
            if (!value) return 'Nama device tidak boleh kosong';
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $('<form>', { action: link, method: 'POST' })
                .append('@csrf')
                .append('@method("PATCH")')
                .append($('<input>', { type: 'hidden', name: 'device_name', value: result.value }))
                .appendTo('body').submit();
        }
    });
});
  </script>
</html>