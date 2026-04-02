<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            {{-- ================= TITLE ================= --}}
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Daftar THR Process</h1>
              <div>
                <a href="{{ route('thr-process.generate') }}" class="btn btn-sm btn-success shadow-sm">
                  <i class="fas fa-plus fa-sm text-white-50"></i> Generate THR </a>
                <a href="{{ route('thr-periods.create') }}" class="btn btn-sm btn-primary shadow-sm">
                  <i class="fas fa-plus fa-sm text-white-50"></i> Create THR Period </a>
              </div>
            </div>
            {{-- ================= TABLE ================= --}}
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Data THR Process </h6>
              </div>
              <div class="card-body">
                {{-- ALERT --}} @foreach(['success','error','warning','info'] as $msg) @if ($message = Session::get($msg)) <div class="alert alert-{{ $msg }} alert-block">
                  <button type="button" class="close" data-dismiss="alert">×</button>
                  <strong>{{ $message }}</strong>
                </div> @endif @endforeach <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Period</th>
                        <th>Process Date</th>
                        <th>Total THR</th>
                        <th>Employee Count</th>
                        <th>Export Status</th>
                        <th>THR File</th>
                        <th>Bank Format</th>
                        <th>Approval Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody> @foreach($periods as $period) <tr>
                        <td>{{ $period->id }}</td>
                        <td>{{ $period->name }}</td>
                        <td>{{ $period->processed_at }}</td>
                        <td> Rp {{ number_format($period->total_thr,0,',','.') }}
                        </td>
                        <td>{{ $period->employee_count }}</td>
                        {{-- EXPORT STATUS --}}
                        <td class="text-center"> @if(!$period->export_status) <span class="badge badge-secondary">Not Generated</span> @elseif($period->export_status=='processing') <span class="badge badge-warning">
                            <i class="fas fa-spinner fa-spin"></i> Processing </span> @elseif($period->export_status=='finished') <span class="badge badge-success">Finished</span> @endif </td>
                        {{-- FILE DOWNLOAD --}}
                        <td class="text-center"> @if($period->export_status=='finished' && $period->file_excel) <a class="btn btn-success btn-sm" href="{{ asset('storage/'.$period->file_excel) }}" target="_blank">
                            <i class="fas fa-file-excel"></i> Excel </a> @endif @if($period->export_status=='finished' && $period->file_pdf) <a class="btn btn-danger btn-sm" href="{{ asset('storage/'.$period->file_pdf) }}" target="_blank">
                            <i class="fas fa-file-pdf"></i> PDF </a> @endif 
                          @if($period->export_status=='finished' && $period->file_peng) <a class="btn btn-secondary btn-sm" href="{{ asset('storage/'.$period->file_peng) }}" target="_blank">
                            <i class="fas fa-file-pdf"></i> Pengeluaran </a> @endif </td>
                        {{-- BANK --}}
                        <td class="text-center"> @if($period->approve_status=='finish' && $period->export_status=='finished') <a class="btn btn-primary btn-sm" href="{{ asset('storage/'.$period->file_bank_active) }}" target="_blank"> Active </a>
                          <a class="btn btn-secondary btn-sm" href="{{ asset('storage/'.$period->file_bank_resign) }}" target="_blank"> Resign </a> @endif
                        </td>
                        {{-- APPROVAL --}}
                        <td class="text-center"> @if($period->approve_status=='finish') <span class="badge badge-success">Approved</span> @else <span class="badge badge-warning">
                            <i class="fas fa-spinner fa-spin"></i> Waiting </span> @endif </td>
                        {{-- ACTION --}}
                        <td class="text-center">
                          <button class="btn btn-info btn-circle btn-sm btn-detail" data-id="{{ $period->id }}" data-period="{{ $period->name }}">
                            <i class="fas fa-eye"></i>
                          </button> @if(!$period->export_status) <a href="#" data-url="{{ route('thr.export.export',$period->id) }}" class="btn btn-warning btn-circle btn-sm btn-export">
                            <i class="fas fa-database"></i>
                          </a> @endif <a class="btn btn-danger btn-circle btn-sm btn-delete-thr" data-id="{{ $period->id }}" data-period="{{ $period->name }}" data-toggle="modal" data-target="#deleteModal">
                            <i class="fas fa-trash"></i>
                          </a>
                        </td>
                      </tr> @endforeach </tbody>
                  </table>
                </div>
              </div>
            </div>
            {{-- ================= DETAIL TABLE ================= --}}
            <div id="thr-detail-container" style="display:none;" class="mt-4">
              <div class="card shadow">
                <div class="card-header">
                  <h6 id="detail-title" class="m-0 font-weight-bold text-primary"> Data THR Details </h6>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="table-details">
                      <thead>
                        <tr>
                          <th>Run ID</th>
                          <th>NPK</th>
                          <th>Name</th>
                          <th>Basic Salary</th>
                          <th>Allowance</th>
                          <th>Working Months</th>
                          <th>THR Amount</th>
                          <th>Slip</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        {{-- ================= DELETE MODAL ================= --}}
        <div class="modal fade" id="deleteModal">
          <div class="modal-dialog modal-md">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Delete Record</h5>
                <button class="close" data-dismiss="modal">x</button>
              </div>
              <div class="modal-body">
                <p id="modal-text-thr"></p>
              </div>
              <form id="delete-form" method="POST"> @csrf @method('DELETE') <div class="modal-footer">
                  <button class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                  <button class="btn btn-danger" type="submit">Delete</button>
                </div>
              </form>
            </div>
          </div>
        </div> @include('layout.footer') </body>
  {{-- ================= JS ================= --}}
  <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
  <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
  <script>
    function formatRupiah(number) {
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
      }).format(number);
    }
    $(document).on('click', '.btn-export', function(e) {
      e.preventDefault();
      let url = $(this).data('url');
      Swal.fire({
        title: "Generate Export?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, generate!"
      }).then((result) => {
        if (result.isConfirmed) {
          Swal.fire({
            title: "Processing...",
            allowOutsideClick: false,
            didOpen: () => {
              Swal.showLoading()
              setTimeout(function() {
                window.location.href = url + '?refresh=1';
              }, 500)
            }
          });
        }
      });
    });
    $('.btn-delete-thr').on('click', function() {
      let id = $(this).data('id');
      let period = $(this).data('period');
      $('#delete-form').attr('action', '/thr-process/delete/' + id);
      $("#modal-text-thr").text('Apakah anda yakin ingin menghapus THR periode ' + period + ' ?');
    });
    let tableDetails = null;
    $('.btn-detail').on('click', function() {
      let id = $(this).data('id');
      let period = $(this).data('period');
      $('#detail-title').text('Data THR Details (' + period + ')');
      if ($('#thr-detail-container').is(':visible') && $('#thr-detail-container').data('id') == id) {
        $('#thr-detail-container').hide();
        return;
      }
      $('#thr-detail-container').show().data('id', id);
      if (tableDetails) {
        tableDetails.destroy();
      }
      tableDetails = $('#table-details').DataTable({
        processing: true,
        ajax: '/thr-process/details/' + id,
        columns: [{
          data: 'run_id'
        }, {
          data: 'employee_npk'
        }, {
          data: 'employee_name'
        }, {
          data: 'basic_salary',
          render: d => formatRupiah(d)
        }, {
          data: 'allowance',
          render: d => formatRupiah(d)
        }, {
          data: 'working_years'
        }, {
          data: 'thr',
          render: d => formatRupiah(d)
        }, {
          data: null,
          render: (data, type, row) => {
            let viewUrl = "/employee-thr/show/" + row.run_id + "/" + row.employee_npk;
            return `

				<a href="${viewUrl}"
class="btn btn-primary btn-circle btn-sm">
					<i class="fa fa-eye"></i>
				</a>`;
          }
        }]
      });
    });
  </script>
</html>