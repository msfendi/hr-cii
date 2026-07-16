<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Edit Payroll Adjusment</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Edit Payroll Adjusment </h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('payroll-adjusments.update',$data->id) }}"> @csrf {{-- ================= NPK (READONLY) ================= --}}
                  <div>
                    <label>NPK :</label>
                    <input type="text" class="form-control" value="{{ $data->npk }}" readonly>
                    {{-- tetap dikirim ke controller --}}
                    <input type="hidden" name="npk" value="{{ $data->npk }}">
                  </div>
                  <br>
                  {{-- ================= PERIOD SELECT2 ================= --}}
                  <div>
                    <label>Period :</label>
                    <select name="period_id" class="form-control select2" required> @foreach($periods as $period) <option value="{{ $period->id }}" {{ $period->id == $data->period_id ? 'selected' : '' }}>
                        {{ $period->name }}
                      </option> @endforeach </select>
                  </div>
                  <br>
                  {{-- ================= ADJUSMENT ================= --}}
                  <div>
                    <label>Adjusment :</label>
                    <input type="number" name="adjusment" class="form-control" value="{{ $data->adjusment }}" required>
                  </div>
                  <br>
                  <div>
                    <label>Keterangan :</label>
                    <input type="text" name="keterangan" class="form-control" value="{{ $data->keterangan }}" required>
                  </div>
                  <br>
                  <div class="row">
                    <div class="col-12">
                      <button type="submit" class="btn btn-primary btn-block"> Update Payroll Adjusment </button>
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
          placeholder: 'Select Period'
        });
      });
    </script>
  </body>
</html>