<!DOCTYPE html>
<html lang="en"> @include('layout.header')

  <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            {{-- ================= PAGE TITLE ================= --}}
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">THR Process</h1>
            </div>
            {{-- ================= CARD ================= --}}
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">THR Process</h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('thr-process.process') }}" id="thrForm"> @csrf {{-- ================= PERIOD ================= --}}
                  <div class="form-group">
                    <label>THR Period :</label>
                    <select class="form-control" id="period_id" name="period_id" required>
                      <option value="">Pilih Periode</option> @foreach($periods as $period) <option value="{{ $period->id }}">
                        {{ $period->name }}
                      </option> @endforeach
                    </select>
                  </div>
                  <div class="d-flex">

                    <button id="btnProcess" type="button" class="btn btn-primary mr-2">
                      Generate THR
                    </button>

                    <button id="btnCheck" type="button" class="btn btn-success">
                      Check THR
                    </button>

                  </div>
                </form>
              </div>
            </div>
            <div id="checkSection" style="display:none">

              <div class="card shadow">

                <div class="card-header">
                  <h6 class="m-0 font-weight-bold text-success">
                    THR Check Result
                  </h6>
                  <small class="text-muted">
                    Period :
                    <strong id="checkPeriodName"></strong>
                  </small>
                </div>

                <div class="card-body">

                  <div class="table-responsive">

                    <table class="table table-bordered table-sm w-100" id="tableThrCheck">

                      <thead>
                        <tr>
                          <th>NPK</th>
                          <th>Name</th>
                          <th>Basic Salary</th>
                          <th>Allowance</th>
                          <th>Working Months</th>
                          <th>THR</th>
                        </tr>
                      </thead>
                      <tfoot>
                        <tr>
                          <th colspan="2" class="text-right">
                            TOTAL
                          </th>
                          <th id="totalBasicSalary"></th>
                          <th id="totalAllowance"></th>
                          <th></th>
                          <th id="totalThr"></th>
                        </tr>
                      </tfoot>

                      <tbody></tbody>

                    </table>

                  </div>

                </div>

              </div>

            </div>
          </div>
        </div> @include('layout.footer')
      </div>
    </div>
    {{-- ================= JS ================= --}}
    <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
    <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{asset('vendor/jquery/select2.min.js')}}"></script>
    <script>
      $("#period_id").select2({
        allowClear: true,
        placeholder: 'Pilih Periode THR'
      });
    </script>
    <script>
      $(document).on('click', '#btnProcess', function(e) {
        e.preventDefault();
        let periodId = $('#period_id').val();
        if (!periodId) {
          Swal.fire({
            icon: 'warning',
            title: 'Periode belum dipilih'
          });
          return;
        }
        /*
        ==========================================
        ROUTES
        ==========================================
        */
        let url = "{{ route('thr-process.process') }}";
        let progressUrlTemplate =
          "{{ route('thr.process.progress', ':period_id') }}";
        let progressUrl =
          progressUrlTemplate.replace(':period_id', periodId);
        /*
        ==========================================
        CONFIRM
        ==========================================
        */
        Swal.fire({
          title: "Generate Thr?",
          text: "The thr process will begin. This may take a few minutes depending on the amount of data. Do you want to proceed?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Yes, generate!"
        }).then((result) => {
          if (!result.isConfirmed) return;
          Swal.fire({
            title: "Thr is being processed!",
            html: `
                <div class="w-100">

                    <div id="progress-status"
                        style="font-weight:600;margin-bottom:10px">
                        Initializing...
                    </div>

                    <div class="progress" style="height:25px;">
                        <div id="progress-bar"
                            class="progress-bar progress-bar-striped progress-bar-animated"
                            style="width:0%">
                            0%
                        </div>
                    </div>

                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
              Swal.showLoading();
              /*
              ==========================================
              START PROCESS (FIXED)
              ==========================================
              */
              $.ajax({
                url: url,
                type: 'POST',
                data: {
                  _token: "{{ csrf_token() }}",
                  period_id: periodId,
                  refresh: 1
                },
                error: function(xhr) {
                  console.log('Start process error', xhr.responseText);
                }
              });
              /*
              ==========================================
              POLLING PROGRESS
              ==========================================
              */
              let interval = setInterval(function() {
                $.ajax({
                  url: progressUrl,
                  type: 'GET',
                  success: function(res) {
                    let progress = res.progress ?? 0;
                    let status = res.status ?? 'Processing';
                    $('#progress-bar')
                      .css('width', progress + '%')
                      .text(progress + '%');
                    $('#progress-status')
                      .text(status);
                    if (progress >= 100) {
                      clearInterval(interval);
                      Swal.fire({
                        icon: 'success',
                        title: 'Thr Finished',
                        text: 'Thr Successfully Calculated!'
                      }).then(() => {
                        window.location.href = "{{ route('thr-process.index') }}";
                      });
                    }
                  },
                  error: function(xhr) {
                    console.log('Polling error', xhr.status);
                  }
                });
              }, 2000);
            }
          });
        });
      });
    </script>
    <script>
      function formatRupiah(value) {
        return 'Rp ' + Number(value).toLocaleString('id-ID');
      }
      let checkTable = null;
      $(document).on('click', '#btnCheck', function() {
        let periodId = $('#period_id').val();
        if (!periodId) {
          Swal.fire({
            icon: 'warning',
            title: 'Periode belum dipilih'
          });
          return;
        }
        Swal.fire({
          title: 'Calculating THR..',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });
        $.ajax({
          url: "{{ route('thr-process.check') }}",
          type: 'POST',
          data: {
            _token: "{{ csrf_token() }}",
            period_id: periodId
          },
          success: function(res) {
            Swal.close();
            $('#checkSection').show();
            let periodName = $('#period_id option:selected').text();
            $('#checkPeriodName').text(periodName);
            let rows = res.data.map(function(item) {
              let comp = JSON.parse(item.components);
              return {
                employee_npk: item.employee_npk,
                employee_name: item.employee_name,
                basic_salary: comp.basic_salary,
                allowance: comp.allowance,
                working_months: comp.working_months,
                thr: comp.thr
              };
            });
            checkTable = $('#tableThrCheck').DataTable({
              destroy: true,
              data: rows,
              columns: [{
                  data: 'employee_npk'
                },
                {
                  data: 'employee_name'
                },
                {
                  data: 'basic_salary',
                  className: 'text-right',
                  render: function(data) {
                    return formatRupiah(data);
                  }
                },
                {
                  data: 'allowance',
                  className: 'text-right',
                  render: function(data) {
                    return formatRupiah(data);
                  }
                },
                {
                  data: 'working_months',
                  className: 'text-center'
                },
                {
                  data: 'thr',
                  className: 'text-right font-weight-bold',
                  render: function(data) {
                    return formatRupiah(data);
                  }
                }
              ],
              footerCallback: function(row, data, start, end, display) {
                let api = this.api();
                let totalBasicSalary = api
                  .column(2, {
                    search: 'applied'
                  })
                  .data()
                  .reduce((a, b) => Number(a) + Number(b), 0);
                let totalAllowance = api
                  .column(3, {
                    search: 'applied'
                  })
                  .data()
                  .reduce((a, b) => Number(a) + Number(b), 0);
                let totalThr = api
                  .column(5, {
                    search: 'applied'
                  })
                  .data()
                  .reduce((a, b) => Number(a) + Number(b), 0);
                $(api.column(2).footer()).html(
                  'Rp ' + totalBasicSalary.toLocaleString('id-ID')
                );
                $(api.column(3).footer()).html(
                  'Rp ' + totalAllowance.toLocaleString('id-ID')
                );
                $(api.column(5).footer()).html(
                  '<b>Rp ' + totalThr.toLocaleString('id-ID') + '</b>'
                );
              }
            });
          },
          error: function(xhr) {
            Swal.close();
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: xhr.responseText
            });
          }
        });
      });
    </script>
  </body>

</html>