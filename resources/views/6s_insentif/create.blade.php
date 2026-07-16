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

            <!-- ===================================================== -->
            <!-- TITLE -->
            <!-- ===================================================== -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">

              <h1 class="h3 mb-0 text-gray-800">
                Create Employee 6S Assignment
              </h1>

              <a href="{{ route('employee6s.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i>
                Back
              </a>

            </div>

            <!-- ===================================================== -->
            <!-- FORM -->
            <!-- ===================================================== -->
            <div class="card shadow mb-4">

              <div class="card-header py-3">

                <h6 class="m-0 font-weight-bold text-primary">
                  Employee 6S Assignment Form
                </h6>

              </div>

              <div class="card-body">

                <form action="{{ route('employee6s.store') }}" method="POST" enctype="multipart/form-data">

                  @csrf

                  <div class="row">

                    <!-- PERIOD -->
                    <div class="col-md-6">

                      <div class="form-group">

                        <label>
                          Payroll Period <span class="text-danger">*</span>
                        </label>

                        <select id="periodSelect" name="period_id" class="form-control" required>

                          <option value="">
                            -- Select Period --
                          </option>

                          @foreach($periods as $period)

                          <option value="{{ $period->id }}">
                            {{ $period->name }}
                          </option>

                          @endforeach

                        </select>

                      </div>

                    </div>

                    <!-- Group -->
                    <div class="col-md-6">

                      <div class="form-group">

                        <label>
                          Sections <span class="text-danger">*</span>
                        </label>

                        <select id="sectionSelect" name="section_id" class="form-control" required>

                          <option value="">
                            -- Select Sections --
                          </option>

                          @foreach($sections as $section)

                          <option value="{{ $section->id }}">
                            {{ $section->name }} ({{ $section->line_start }} - {{ $section->line_end }})
                          </option>

                          @endforeach

                        </select>

                      </div>

                    </div>
                    
                    <!-- NPK -->
                    <div class="col-md-6">

                      <div class="form-group">

                        <label>
                          Employee <span class="text-danger">*</span>
                        </label>

                        <select id="npkSelect" name="npk" class="form-control" required>

                          <option value="">
                            -- Select Employee --
                          </option>

                          @foreach($employees as $employee)

                          <option value="{{ $employee->NPK }}">
                            {{ $employee->NPK }} - {{ $employee->NAMA_KARYAWAN }}
                          </option>

                          @endforeach

                        </select>

                      </div>

                    </div>

                    <!-- Inspector -->
                    <div class="col-md-6">

                      <div class="form-group">

                        <label>
                          Inspector <span class="text-danger">*</span>
                        </label>

                        <select id="inspectionSelect" name="inspector" class="form-control" required>

                          <option value="">
                            -- Select Inspector --
                          </option>

                          @foreach($employees as $employee)

                          <option value="{{ $employee->NPK }}">
                            {{ $employee->NPK }} - {{ $employee->NAMA_KARYAWAN }}
                          </option>

                          @endforeach

                        </select>

                      </div>

                    </div>

                    <!-- DATE -->
                    <div class="col-md-6">

                      <div class="form-group">

                        <label>
                          Inspection Date <span class="text-danger">*</span>
                        </label>

                        <input type="date" name="inspection_date" class="form-control" value="{{ old('inspection_date') }}" required>

                      </div>

                    </div>

                    <!-- TOTAL SCORE -->
                    <div class="col-md-6">

                      <div class="form-group">

                        <label>
                          Total Score <span class="text-danger">*</span>
                        </label>

                        <input type="number" step="0.01" name="total_score" class="form-control" value="{{ old('total_score') }}" required>

                      </div>

                    </div>

                    <!-- PERCENTAGE -->
                    <div class="col-md-6">

                      <div class="form-group">

                        <label>
                          Percentage <span class="text-danger">*</span>
                        </label>

                        <input type="number" step="0.01" name="percentage" class="form-control" value="{{ old('percentage') }}" required>

                      </div>

                    </div>

                    <!-- FILE -->
                    <div class="col-md-12">

                      <div class="form-group">

                        <label>
                          Attachment
                        </label>

                        <input type="file" name="file" class="form-control" accept=".pdf,.xls,.xlsx">

                        <small class="text-muted">
                          Supported file: PDF, XLS, XLSX
                        </small>

                      </div>

                    </div>

                  </div>

                  <hr>

                  <button type="submit" class="btn btn-success">

                    <i class="fas fa-save"></i>
                    Save

                  </button>

                  <a href="{{ route('employee6s.index') }}" class="btn btn-secondary">

                    Cancel

                  </a>

                </form>

              </div>

            </div>

          </div>
        </div>

        @include('layout.footer')

  </body>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
      $(document).ready(function() {
        $('#periodSelect').select2({
          width: '100%',
          placeholder: 'Select Period',
          allowClear: true
        });
      });
      
      $(document).ready(function() {
        $('#npkSelect').select2({
          width: '100%',
          placeholder: 'Select Employee',
          allowClear: true
        });
      });
      
      $(document).ready(function() {
        $('#sectionSelect').select2({
          width: '100%',
          placeholder: 'Select Sections',
          allowClear: true
        });
      });
      
      $(document).ready(function() {
        $('#inspectionSelect').select2({
          width: '100%',
          placeholder: 'Select Inspection',
          allowClear: true
        });
      });
    </script>

</html>