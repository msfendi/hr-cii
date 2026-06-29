<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Create Payroll Adjusment</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Create Payroll Adjusment </h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('payroll-adjusments.store') }}"> @csrf {{-- ================= NPK ================= --}}
                  <div>
                    <label>NPK :</label>
                    <select name="npk" class="form-control select2" required>
                      <option value="">-- Select Employee --</option> @foreach($employees as $emp) <option value="{{ $emp->NPK }}">
                        {{ $emp->NPK }} - {{ $emp->NAMA_KARYAWAN }}
                      </option> @endforeach
                    </select>
                  </div>
                  <br>
                  {{-- ================= PERIOD ================= --}}
                  <div>
                    <label>Period :</label>
                    <select name="period_id" class="form-control select2" required>
                      <option value="">-- Select Period --</option> @foreach($periods as $period) <option value="{{ $period->id }}">
                        {{ $period->name }}
                      </option> @endforeach
                    </select>
                  </div>
                  <br>
                  {{-- ================= ADJUSMENTS ================= --}}
                  <div>
                    <label>Adjusment :</label>
                    <input type="number" name="adjusment" class="form-control" required>
                  </div>
                  <br>
                  <div>
                    <label>Keterangan :</label>
                    <input type="text" name="keterangan" class="form-control" required>
                  </div>
                  <br>
                  <div class="row">
                    <div class="col-12">
                      <button type="submit" class="btn btn-primary btn-block"> Create Payroll Adjusment </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div> @include('layout.footer')
      </div>
    </div>
    {{-- ================= SELECT2 ================= --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
      $(document).ready(function() {
        $('.select2').select2({
          width: '100%',
          placeholder: 'Select Data',
          allowClear: true
        });
      });
    </script>
  </body>
</html>