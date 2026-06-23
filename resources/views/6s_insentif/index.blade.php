<!DOCTYPE html>
<html lang="en">
  @include('layout.header')
  <style>
  .select-period + .select2-container{
      width:200px !important;
  }
</style>

  <body id="page-top">

    @include('sweetalert::alert')

    <div id="wrapper">

      @include('layout.sidebar')

      <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">

          @include('layout.navbar')

          <div class="container-fluid">

            <!-- ===================================================== -->
            <!-- TITLE -->
            <!-- ===================================================== -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">

              <h1 class="h3 mb-0 text-gray-800">
                Employee 6S Assignment
              </h1>

              <a href="{{ route('employee6s.create') }}" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus fa-sm"></i>
                Create 6S Insentif
              </a>

            </div>

            <!-- ===================================================== -->
            <!-- MASTER TABLE -->
            <!-- ===================================================== -->
            <div class="card shadow mb-4">

              <div class="card-header py-3">

                <div class="d-flex justify-content-between align-items-center">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Data Employee 6S Assignment
                    </h6>

                    <select id="checkPeriod"
                            class="form-control select-period">

                        <option value="">Pilih Payroll Period</option>

                        @foreach($periods as $period)
                            <option value="{{ $period->id }}">
                                {{ $period->name }}
                            </option>
                        @endforeach

                    </select>

                </div>

            </div>

              <div class="card-body">

                <div class="table-responsive">

                  <table class="table table-bordered table-sm" id="dataTable" width="100%">

                    <thead>

                      <tr>
                        <th>ID</th>
                        <th hidden>Period ID</th>
                        <th>Period</th>
                        <th>Group</th>
                        <th>Inspector</th>
                        <th>Inspection Date</th>
                        <th>Total Score</th>
                        <th>Percentage</th>
                        <th>Insentif</th>
                        <th>Attachment</th>
                        <th width="120">Action</th>
                      </tr>

                    </thead>

                    <tbody>

                      @foreach($data as $row)

                      <tr>

                        <td>{{ $row->id }}</td>
                        <td hidden>{{ $row->period_id }}</td>
                        <td>
                          {{ $row->name }}
                        </td>

                        <td>
                          {{ $row->section_id }}
                        </td>

                        <td>
                          {{ $row->inspector }}
                        </td>

                        <td>
                          {{ \Carbon\Carbon::parse($row->inspection_date)->format('d-m-Y') }}
                        </td>

                        <td class="text-right">
                          {{ number_format($row->total_score,2,'.',',') }}
                        </td>

                        <td class="text-right">
                            {{ number_format($row->percentage,2,'.',',') }} %
                        </td>

                        <td class="text-right">
                          @php

                              $insentif =
                                  $row->percentage > 95 ? 3000000 :
                                  ($row->percentage >= 85 ? 2000000 :
                                  ($row->percentage >= 75 ? 1000000 : 0));

                          @endphp

                          {{ 'Rp ' . number_format($insentif, 0, ',', '.') }}

                      </td>

                        <td class="text-center">

                          @if($row->file_path)

                          <a href="{{ asset('storage/'.$row->file_path) }}" target="_blank" class="btn btn-info btn-sm">

                            <i class="fas fa-file-pdf"></i>

                          </a>

                          @endif

                        </td>

                        <td class="text-center">

                          <a href="{{ route('employee6s.edit',$row->id) }}" class="btn btn-warning btn-sm">

                            <i class="fas fa-edit"></i>

                          </a>

                          <button class="btn btn-danger btn-sm btn-delete-payroll_master" data-toggle="modal" data-target="#deleteModal" data-delete-link="{{ route('employee6s.destroy',$row->id) }}">

                            <i class="fas fa-trash"></i>

                          </button>

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

        <!-- ===================================================== -->
        <!-- DELETE MODAL -->
        <!-- ===================================================== -->
        <div class="modal fade" id="deleteModal" tabindex="-1">

          <div class="modal-dialog modal-md">

            <div class="modal-content">

              <div class="modal-header">

                <h5 class="modal-title">
                  Delete Record
                </h5>

                <button class="close" data-dismiss="modal">
                  <span>×</span>
                </button>

              </div>

              <div class="modal-body">

                <p id="modal-text-payroll_master"></p>

              </div>

              <div class="modal-footer">

                <button class="btn btn-secondary" data-dismiss="modal">
                  Tutup
                </button>

                <a id="btn-confirm" href="">

                  <button class="btn btn-danger" type="button">
                    Confirm
                  </button>

                </a>

              </div>

            </div>

          </div>

        </div>

        @include('layout.footer')

  </body>

  <!-- ===================================================== -->
  <!-- DATATABLE -->
  <!-- ===================================================== -->
   <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
  <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
  <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

  <script>
    $('#checkPeriod').select2({
        allowClear: true,
        placeholder: 'Pilih Payroll Period',
        width: '100%'
    });
  </script>
  <script>

  let masterTable;

  $(document).ready(function () { 

      masterTable = $('#dataTable').DataTable({
          order: [[0, 'desc']],
          pageLength: 10,
          responsive: true,
          autoWidth: false
      });

      $('#checkPeriod').select2({
          placeholder: 'Pilih Payroll Period',
          allowClear: true
      });

      $('#checkPeriod').on('change', function () {

          let periodId = $(this).val();

          if (!periodId) {
              masterTable.column(1).search('').draw();
              return;
          }

          masterTable
              .column(1)
              .search(periodId)
              .draw();

      });

  });

  </script>

  <!-- ===================================================== -->
  <!-- DELETE MODAL -->
  <!-- ===================================================== -->
  <script>
    $('.btn-delete-payroll_master').on('click', function() {
      $('#btn-confirm').attr(
        'href',
        $(this).data('delete-link')
      );
      $("#modal-text-payroll_master").text(
        'Apakah anda yakin ingin menghapus data Employee 6S Assignment?'
      );
    });
  </script>

</html>