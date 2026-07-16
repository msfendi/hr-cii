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
            {{-- ===================================================== --}}
            {{-- PAGE HEADING --}}
            {{-- ===================================================== --}}
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Compensation</h1>
              @if(!$noRoleAssigned)
              <form method="POST" action="{{ route('compensation.generate') }}" id="generateForm" class="form-inline">
                @csrf
                <input type="text" name="generate_date" id="generate_date" class="form-control form-control-sm mr-2" placeholder="Select Date" required readonly>
                
              @canRoute('compensation.check')
                <button type="button" id="btnCheck" class="btn btn-info btn-sm shadow-sm mr-2" disabled>
                  <i class="fas fa-search"></i>
                  Check Compensation
                </button>
              @endcanRoute
              @canRoute('compensation.generate')
                <button type="submit" id="btnGenerate" class="btn btn-primary btn-sm shadow-sm" disabled>
                  <i class="fas fa-cogs"></i>
                  Generate Compensation
                </button>
              @endcanRoute
              </form>
              @endif
            </div>
            {{-- ===================== INFO ROLE PAYROLL ===================== --}}
            @if($noRoleAssigned)
              <div class="alert alert-danger py-2 px-3 mb-3">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Akun Anda belum terdaftar di <strong>role_payrolls</strong>, sehingga tidak ada data compensation yang bisa ditampilkan/diproses dari halaman ini.
                Silakan hubungi Admin untuk pengaturan akses.
              </div>
            @elseif($payrollRoleLabel && $payrollRoleLabel !== 'Semua (Tidak Difilter)')
              <div class="alert alert-info py-2 px-3 mb-3">
                <i class="fas fa-info-circle mr-1"></i>
                Data compensation ditampilkan sesuai akses role payroll Anda: <strong>{{ $payrollRoleLabel }}</strong>
              </div>
            @endif
            {{-- ===================================================== --}}
            {{-- DATA TABLE --}}
            {{-- ===================================================== --}}
            <div class="card shadow mb-4">
              <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                  Data Compensations
                </h6>
                <form method="GET" id="filterForm">
                  <select name="status" class="form-control form-control-sm" onchange="document.getElementById('filterForm').submit()">
                    <option value="all" {{ $filter=='all' ? 'selected':'' }}>
                      All
                    </option>
                    <option value="open" {{ $filter=='open' ? 'selected':'' }}>
                      Open
                    </option>
                    <option value="closed" {{ $filter=='closed' ? 'selected':'' }}>
                      Closed
                    </option>
                  </select>
                </form>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Period</th>
                        <th>Process Date</th>
                        <th>Total Compensation</th>
                        <th>Employee Count</th>
                        <th>File</th>
                        <th>Excel</th>
                        <th>Bank Format</th>
                        <th>Approval Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($compensations as $comp)
                      @php
                        $folder = \Carbon\Carbon::parse($comp->cutoff_date)->translatedFormat('F_Y');
                        $day = \Carbon\Carbon::parse($comp->cutoff_date)->day; // 7 atau 20

                        // file_pdf / file_csv / file_excel disimpan sebagai JSON {ROLE_KEY: filename}
                        // sejak 1 periode/tanggal bisa punya beberapa file (per role_payroll), dan
                        // file-nya berada di subfolder .../{period}/{ROLE}/{day}/.
                        $pdfFiles = json_decode($comp->file_pdf ?? '', true) ?: [];
                        $csvFiles = json_decode($comp->file_csv ?? '', true) ?: [];
                        $excelFiles = json_decode($comp->file_excel ?? '', true) ?: [];

                        // flag data lama sebelum migrasi ke JSON (masih string 1 file, disimpan
                        // FLAT di root folder period, TANPA subfolder role & TANPA subfolder hari)
                        $legacyPdf = false;
                        $legacyCsv = false;
                        $legacyExcel = false;

                        if (empty($pdfFiles) && !empty($comp->file_pdf) && !str_starts_with(trim($comp->file_pdf), '{')) {
                            $pdfFiles = [\App\Services\PayrollRoleFilterService::ROLE_ALL => $comp->file_pdf];
                            $legacyPdf = true;
                        }
                        if (empty($csvFiles) && !empty($comp->file_csv) && !str_starts_with(trim($comp->file_csv), '{')) {
                            $csvFiles = [\App\Services\PayrollRoleFilterService::ROLE_ALL => $comp->file_csv];
                            $legacyCsv = true;
                        }
                        if (empty($excelFiles) && !empty($comp->file_excel) && !str_starts_with(trim($comp->file_excel), '{')) {
                            $excelFiles = [\App\Services\PayrollRoleFilterService::ROLE_ALL => $comp->file_excel];
                            $legacyExcel = true;
                        }

                        // $fileRoleKey dikirim dari controller: null hanya utk Admin (lihat semua),
                        // selain itu di-scope PERSIS ke role_payroll user login (termasuk Payroll_ALL
                        // -> cuma lihat key "Payroll_ALL" saja, bukan semua kategori).
                        $visiblePdfKeys   = empty($fileRoleKey) ? array_keys($pdfFiles)   : [$fileRoleKey];
                        $visibleCsvKeys   = empty($fileRoleKey) ? array_keys($csvFiles)   : [$fileRoleKey];
                        $visibleExcelKeys = empty($fileRoleKey) ? array_keys($excelFiles) : [$fileRoleKey];
                      @endphp
                      <tr>
                        <td>{{ $comp->id }}</td>
                        <td>
                          {{ \Carbon\Carbon::parse($comp->cutoff_date)->translatedFormat('d F Y') }}
                        </td>
                        <td>{{ $comp->created_at }}</td>
                        <td>
                          Rp {{ number_format($comp->total_amount ?? 0,0,',','.') }}
                        </td>
                        <td>{{ $comp->total_employee ?? 0 }}</td>
                        <td class="text-center">
                          @foreach($pdfFiles as $roleKey => $fileName)
                            @if(in_array($roleKey, $visiblePdfKeys) && $fileName)
                            @php
                              $roleLabel = str_replace('Payroll_', '', $roleKey);
                              if ($legacyPdf) {
                                  $fileUrl = Storage::url('compensations/' . $folder . '/' . $fileName);
                              } else {
                                  $subFolder = \App\Services\PayrollRoleFilterService::folder($roleKey);
                                  $fileUrl = Storage::url('compensations/' . $folder . '/' . $subFolder . $day . '/' . $fileName);
                              }
                            @endphp
                            <a class="btn btn-danger btn-sm mb-1" href="{{ $fileUrl }}" target="_blank">
                              <i class="fas fa-file-pdf"></i> PDF {{ $roleLabel }}
                            </a><br>
                            @endif
                          @endforeach
                        </td>
                        <td class="text-center">
                          @foreach($excelFiles as $roleKey => $fileName)
                            @if(in_array($roleKey, $visibleExcelKeys) && $fileName)
                            @php
                              $roleLabel = str_replace('Payroll_', '', $roleKey);
                              if ($legacyExcel) {
                                  $fileUrl = Storage::url('compensations/' . $folder . '/' . $fileName);
                              } else {
                                  $subFolder = \App\Services\PayrollRoleFilterService::folder($roleKey);
                                  $fileUrl = Storage::url('compensations/' . $folder . '/' . $subFolder . $day . '/' . $fileName);
                              }
                            @endphp
                            <a class="btn btn-success btn-sm mb-1" href="{{ $fileUrl }}" target="_blank">
                              <i class="fas fa-file-excel"></i> Excel {{ $roleLabel }} (zip)
                            </a><br>
                            @endif
                          @endforeach
                        </td>
                        <td class="text-center">
                          @foreach($csvFiles as $roleKey => $fileName)
                            @if(in_array($roleKey, $visibleCsvKeys) && $fileName)
                            @php
                              $roleLabel = str_replace('Payroll_', '', $roleKey);
                              if ($legacyCsv) {
                                  $fileUrl = Storage::url('compensations/' . $folder . '/' . $fileName);
                              } else {
                                  $subFolder = \App\Services\PayrollRoleFilterService::folder($roleKey);
                                  $fileUrl = Storage::url('compensations/' . $folder . '/' . $subFolder . $day . '/' . $fileName);
                              }
                            @endphp
                            <a class="btn btn-primary btn-sm mb-1" href="{{ $fileUrl }}" target="_blank">
                              <i class="fas fa-university"></i> CSV {{ $roleLabel }}
                            </a><br>
                            @endif
                          @endforeach
                        </td>

                        <td class="text-center">
                          @if($comp->approve_status == 'finish')
                          <span class="badge badge-success">Approved</span>
                          @else
                          <span class="badge badge-warning">
                            <i class="fas fa-spinner fa-spin"></i> Waiting
                          </span>
                          @endif
                        </td>
                        <td class="text-center">
                          @canRoute('compensation.details')
                          <button class="btn btn-info btn-circle btn-sm btn-detail" data-date="{{ $comp->cutoff_date }}" data-period="{{ \Carbon\Carbon::parse($comp->cutoff_date)->translatedFormat('F_Y') }}">
                            <i class="fas fa-eye"></i>
                          </button>
                          @endcanRoute
                          @canRoute('compensation.destroy')
                          <button class="btn btn-danger btn-circle btn-sm btn-delete" data-id="{{ $comp->id }}" data-period="{{ \Carbon\Carbon::parse($comp->cutoff_date)->translatedFormat('d F Y') }}">
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
            {{-- ===================================================== --}}
            {{-- DETAIL TABLE --}}
            {{-- ===================================================== --}}
            <div id="comp-detail-container" style="display:none;" class="mt-4">
              <div class="card shadow">
                <div class="card-header">
                  <h6 id="detail-title" class="m-0 font-weight-bold text-primary">
                    Compensation Details
                  </h6>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="table-details">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>NPK</th>
                          <th>Department</th>
                          <th>TMK</th>
                          <th>Month Duration</th>
                          <th>Day Duration</th>
                          <th>End Date</th>
                          <th>Salary</th>
                          <th>Amount</th>
                          <th>Status</th>
                          <th>Active</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                      <tfoot>
                        <tr style="font-weight:bold;background:#f8f9fc">
                          <th colspan="8" class="text-right">TOTAL</th>
                          <th></th>
                          <th></th>
                          <th></th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        @include('layout.footer')
        {{-- ===================================================== --}}
        {{-- DATATABLE --}}
        {{-- ===================================================== --}}
        <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
        <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
          $('#dataTable').DataTable({
            order: [
              [0, 'desc']
            ],
            responsive: true,
            autoWidth: false
          });
        </script>
        {{-- ===================================================== --}}
        {{-- DATE PICKER --}}
        {{-- ===================================================== --}}
        <script>
          if (document.getElementById('generate_date')) {
            flatpickr("#generate_date", {
              dateFormat: "Y-m-d",
              disableMobile: true,
              enable: [
                function(date) {
                  return date.getDate() === 7 || date.getDate() === 20;
                }
              ],
              onChange: function(selectedDates, dateStr) {
                if (dateStr) {
                  $('#btnGenerate').prop('disabled', false);
                  $('#btnCheck').prop('disabled', false);
                }
              }
            });
          }
        </script>
        <script>
          let checkTable = null;
          $('#btnCheck').on('click', function() {
            let date = $('#generate_date').val();
            if (!date) {
              Swal.fire(
                'Warning',
                'Please select date first',
                'warning'
              );
              return;
            }
            Swal.fire({
              title: 'Checking Compensation',
              html: 'Please wait...',
              allowOutsideClick: false,
              didOpen: () => {
                Swal.showLoading();
              }
            });
            $.ajax({
              url: '/compensation/check',
              type: 'POST',
              data: {
                _token: '{{ csrf_token() }}',
                generate_date: date
              },
              success: function(res) {
                Swal.close();
                if (checkTable) {
                  checkTable.destroy();
                }
                $('#tableCheckResult tbody').empty();
                checkTable = $('#tableCheckResult').DataTable({
                  processing:true,
        responsive:true,
        autoWidth:false,
        destroy:true,
                  data: res.data,
                  createdRow: function(row, data) {
                    if (data.status === 'Resigned Before Contract End') {
                      $(row).addClass('table-danger');
                    }
                  },
                  columns: [{
                      data: 'npk'
                    },
                    {
                      data: 'employee_name'
                    },
                    {
                      data: 'department'
                    },
                    {
                      data: 'month_duration'
                    },
                    {
                      data: 'day_duration'
                    },
                    {
                      data: 'end_date'
                    },
                    {
                      data: 'status'
                    },
                    {
                      data: 'salary',
                      render: data => new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        minimumFractionDigits: 2
                      }).format(data ?? 0)
                    },
                    {
                      data: 'amount',
                      render: data => new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        minimumFractionDigits: 2
                      }).format(data ?? 0)
                    },
                  ],
                  footerCallback: function(row, data, start, end, display) {
                    let api = this.api();

                    function intVal(i) {
                      if (i === null || i === undefined || i === '') return 0;
                      if (typeof i === 'number') return i;
                      if (typeof i === 'string') {
                        i = i.replace(/[Rp\s]/g, '');
                        i = i.replace(/\./g, '').replace(',', '.');
                        let num = parseFloat(i);
                        return isNaN(num) ? 0 : num;
                      }
                      return 0;
                    }
                    let total = api
                      .column(8, {
                        search: 'applied'
                      })
                      .data()
                      .reduce(function(a, b) {
                        return intVal(a) + intVal(b);
                      }, 0);
                    $(api.column(8).footer()).html(
                      new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        minimumFractionDigits: 2
                      }).format(total)
                    );
                  }
                });
                $('#checkModal').modal('show');
              }
            });
          });
        </script>
        <script>
          const btnGenerate = $('#btnGenerate');
          const btnCheck = $('#btnCheck');
          $('#generate_date').on('change', function() {
            let value = $(this).val();
            if (value) {
              btnGenerate.prop('disabled', false);
              btnCheck.prop('disabled', false);
            } else {
              btnGenerate.prop('disabled', true);
              btnCheck.prop('disabled', true);
            }
          });
        </script>
        {{-- ===================================================== --}}
        {{-- ✅ SWEETALERT LOADING GENERATE --}}
        {{-- ===================================================== --}}
        <script>
          $(document).ready(function() {
            $('#generateForm').on('submit', function(e) {
              e.preventDefault(); // stop submit dulu
              const form = this;
              const date = $('#generate_date').val();

              Swal.fire({
                title: 'Generate Compensation?',
                html: 'Anda akan generate compensation untuk periode <strong>' + (date || '-') + '</strong>.<br>Proses ini tidak bisa dibatalkan setelah dijalankan.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Generate',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#4e73df',
                cancelButtonColor: '#858796',
                reverseButtons: true
              }).then((result) => {
                if (result.isConfirmed) {
                  Swal.fire({
                    title: 'Generating Compensation...',
                    html: 'Please wait, system is processing data',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                      Swal.showLoading();
                    }
                  });
                  // submit setelah swal loading tampil
                  form.submit();
                }
                // kalau cancel/dismiss, tidak ada aksi apapun (form tidak submit)
              });
            });
          });
        </script>
        {{-- ===================================================== --}}
        {{-- DETAIL AJAX --}}
        {{-- ===================================================== --}}
        <script>
          let tableDetails = null;
          $('.btn-detail').on('click', function() {
            let date = $(this).data('date');
            let period = $(this).data('period');
            $('#detail-title').text('Compensation Details (' + period + ')');
            $('#comp-detail-container').show();
            if(tableDetails){
            tableDetails.destroy();
            $('#table-details tbody').empty();
         }
            tableDetails = $('#table-details').DataTable({
              processing: true,
              responsive: true,
              ajax: '/compensation/details/' + date,
              createdRow: function(row, data) {
                if (data.is_active == 0) {
                  $(row).addClass('table-danger');
                }
              },
              columns: [{
                  data: 'id'
                },
                {
                  data: 'npk'
                },
                {
                  data: 'dept'
                },
                {
                  data: 'TMK'
                },
                {
                  data: 'month_duration'
                },
                {
                  data: 'day_duration'
                },
                {
                  data: 'end_date'
                },
                {
                  data: 'salary',
                  render: data => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 2
                  }).format(data ?? 0)
                },
                {
                  data: 'amount',
                  render: data => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 2
                  }).format(data ?? 0)
                },
                {
                  data: 'status'
                },
                {
                  data: 'is_active',
                  render: data => {
                    return data == 1 ?
                      '<span class="badge badge-success">Active</span>' :
                      '<span class="badge badge-danger">Out</span>';
                  }
                }
              ],
              footerCallback: function(row, data, start, end, display) {
                let api = this.api();

                function intVal(i) {
                  if (i === null || i === undefined || i === '') return 0;
                  if (typeof i === 'number') return i;
                  if (typeof i === 'string') {
                    i = i.replace(/[Rp\s]/g, '');
                    i = i.replace(/\./g, '').replace(',', '.');
                    let num = parseFloat(i);
                    return isNaN(num) ? 0 : num;
                  }
                  return 0;
                }
                let total = api
                  .column(8, {
                    search: 'applied'
                  })
                  .data()
                  .reduce(function(a, b) {
                    return intVal(a) + intVal(b);
                  }, 0);
                $(api.column(8).footer()).html(
                  new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 2
                  }).format(total)
                );
              }
            });
          });
        </script>
        {{-- ===================================================== --}}
        {{-- DELETE COMPENSATION --}}
        {{-- ===================================================== --}}
        <script>
          $(document).on('click', '.btn-delete', function() {
            let id = $(this).data('id');
            let period = $(this).data('period');

            Swal.fire({
              title: 'Delete Compensation?',
              html: 'Data compensation periode <strong>' + period + '</strong> beserta seluruh <strong>detail</strong> dan <strong>approval</strong>-nya akan dihapus permanen.<br>Tindakan ini tidak bisa dibatalkan.',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Ya, Hapus',
              cancelButtonText: 'Batal',
              confirmButtonColor: '#e74a3b',
              cancelButtonColor: '#858796',
              reverseButtons: true
            }).then((result) => {
              if (result.isConfirmed) {
                Swal.fire({
                  title: 'Deleting...',
                  html: 'Please wait, system is removing data',
                  allowOutsideClick: false,
                  allowEscapeKey: false,
                  didOpen: () => {
                    Swal.showLoading();
                  }
                });

                $.ajax({
                  url: '/compensation/' + id,
                  type: 'DELETE',
                  data: {
                    _token: '{{ csrf_token() }}'
                  },
                  success: function(res) {
                    Swal.fire({
                      title: 'Deleted!',
                      text: res.message || 'Compensation data has been deleted.',
                      icon: 'success'
                    }).then(() => {
                      location.reload();
                    });
                  },
                  error: function(xhr) {
                    let msg = xhr.responseJSON && xhr.responseJSON.message
                      ? xhr.responseJSON.message
                      : 'Failed to delete compensation data.';
                    Swal.fire('Error', msg, 'error');
                  }
                });
              }
              // kalau cancel/dismiss, tidak ada aksi apapun
            });
          });
        </script>
        <div class="modal fade" id="checkModal" tabindex="-1">
          <div class="modal-dialog modal-xl">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">
                  Compensation Checking Result
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                  <span>&times;</span>
                </button>
              </div>
              <div class="modal-body">
                <div class="table-responsive">
                  <table class="table table-bordered table-sm w-100" id="tableCheckResult">
                    <thead class="thead-dark">
                      <tr>
                        <th>NPK</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Month Duration</th>
                        <th>Day Duration</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Salary</th>
                        <th>Amount</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                      <tr style="font-weight:bold;background:#f8f9fc">
                        <th colspan="8" class="text-right">
                          TOTAL
                        </th>
                        <th id="totalAmount">0</th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
  </body>

</html>