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

              <div class="d-flex gap-2">
                @canRoute('ijin-meninggalkan-pekerjaan.export')
                <button class="btn btn-success btn-sm mr-1" data-toggle="modal" data-target="#modalExportIjin">
                  <i class="fas fa-file-excel"></i> Export Excel
                </button>
                @endcanRoute

                @canRoute('ijin-meninggalkan-pekerjaan.create')
                <a href="{{ route('ijin-meninggalkan-pekerjaan.create') }}" class="btn btn-primary btn-sm">
                  <i class="fas fa-plus"></i> Create Ijin
                </a>
                @endcanRoute
              </div>
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
                        <td>{{ $row->jam_keluar ? \Carbon\Carbon::parse($row->jam_keluar)->format('H:i') : '-' }}</td>
                        <td>{{ $row->rencana_kembali ? \Carbon\Carbon::parse($row->rencana_kembali)->format('H:i') : '-' }}</td>
                        <td>{{ $row->jam_kembali ? \Carbon\Carbon::parse($row->jam_kembali)->format('H:i') : '-' }}</td>
                        <td>
                          @if($row->sesi)
                            {{ $row->sesi }}
                            ({{ $row->time_start ? \Carbon\Carbon::parse($row->time_start)->format('H:i') : '-' }}
                            - {{ $row->time_end ? \Carbon\Carbon::parse($row->time_end)->format('H:i') : '-' }})
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

    <!-- Modal Export Excel Ijin Meninggalkan Pekerjaan -->
    <div class="modal fade" id="modalExportIjin" tabindex="-1" role="dialog" aria-labelledby="modalExportIjinLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalExportIjinLabel">
              <i class="fas fa-file-excel text-success mr-1"></i> Export Ijin Meninggalkan Pekerjaan
            </h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <!-- Mode selector -->
            <div class="form-group">
              <label class="d-block font-weight-bold">Rentang Waktu</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="ijin_export_mode" id="ijinModeMonthly" value="monthly" checked>
                <label class="form-check-label" for="ijinModeMonthly">Bulanan</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="ijin_export_mode" id="ijinModeCustom" value="custom">
                <label class="form-check-label" for="ijinModeCustom">Custom Range</label>
              </div>
            </div>

            <!-- Monthly picker -->
            <div class="form-group" id="groupIjinMonth">
              <label for="ijinMonth">Bulan</label>
              <input type="month" class="form-control" id="ijinMonth" value="{{ date('Y-m') }}">
            </div>

            <!-- Custom range picker -->
            <div id="groupIjinRange" style="display:none;">
              <div class="form-row">
                <div class="form-group col-md-6">
                  <label for="ijinStartDate">Dari Tanggal</label>
                  <input type="date" class="form-control" id="ijinStartDate">
                </div>
                <div class="form-group col-md-6">
                  <label for="ijinEndDate">Sampai Tanggal</label>
                  <input type="date" class="form-control" id="ijinEndDate">
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success" id="btnDoExportIjin">
              <i class="fas fa-download mr-1"></i> Download
            </button>
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

    <script>
      // ── Export Ijin Meninggalkan Pekerjaan ──────────────────────────────────
      $(function () {

        // Toggle fields
        $('input[name="ijin_export_mode"]').on('change', function () {
          if ($(this).val() === 'monthly') {
            $('#groupIjinMonth').show();
            $('#groupIjinRange').hide();
          } else {
            $('#groupIjinMonth').hide();
            $('#groupIjinRange').show();
          }
        });

        function endOfMonth(ym) {
          var d = new Date(ym + '-01');
          return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10);
        }

        $('#btnDoExportIjin').on('click', function () {
          var mode = $('input[name="ijin_export_mode"]:checked').val();
          var params = { mode: mode };

          if (mode === 'monthly') {
            var month = $('#ijinMonth').val();
            if (!month) {
              alert('Pilih bulan terlebih dahulu.');
              return;
            }
            params.month = month;
          } else {
            var start = $('#ijinStartDate').val();
            var end   = $('#ijinEndDate').val();
            if (!start || !end) {
              alert('Pilih rentang tanggal terlebih dahulu.');
              return;
            }
            if (end < start) {
              alert('Tanggal akhir tidak boleh sebelum tanggal mulai.');
              return;
            }
            params.start_date = start;
            params.end_date   = end;
          }

          var url = '{{ route("ijin-meninggalkan-pekerjaan.export") }}?' + $.param(params);

          var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyiapkan...');

          fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
              if (!response.ok) {
                return response.json().then(function (err) {
                  throw new Error(err.error || 'Gagal membuat file export.');
                });
              }
              return response.blob();
            })
            .then(function (blob) {
              var dlUrl = window.URL.createObjectURL(blob);
              var a = document.createElement('a');
              a.href = dlUrl;
              a.download = 'Ijin_Meninggalkan.xlsx';
              document.body.appendChild(a);
              a.click();
              a.remove();
              $('#modalExportIjin').modal('hide');
            })
            .catch(function (err) {
              alert(err.message);
            })
            .finally(function () {
              $btn.prop('disabled', false).html('<i class="fas fa-download mr-1"></i> Download');
            });
        });

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