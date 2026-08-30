<!DOCTYPE html>
<html lang="en">
  @include('layout.header')
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <body id="page-top">
  @include('sweetalert::alert')
  <div id="wrapper">
  @include('layout.sidebar')
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
          @include('layout.navbar')
          <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Create Employee Late</h1>
              <a href="{{ route('employee-late.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back </a>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Tambah Employee Late </h6>
              </div>
              <div class="card-body">
                @if ($errors->any())
                  <div class="alert alert-danger">
                    <ul class="mb-0">
                      @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                      @endforeach
                    </ul>
                  </div>
                @endif

                <form action="{{ route('employee-late.store') }}" method="POST">
                  @csrf
                  <div class="form-group row">
                    <label for="npk" class="col-sm-2 col-form-label">NPK</label>
                    <div class="col-sm-10">
                      <select name="npk" id="npk" class="form-control select2" required>
                        <option value="">Pilih Karyawan</option>
                        @foreach($biodatas as $biodata)
                          <option value="{{ $biodata->NPK }}" {{ old('npk') == $biodata->NPK ? 'selected' : '' }}>
                            {{ $biodata->NPK }} - {{ $biodata->NAMA_KARYAWAN }}
                          </option>
                        @endforeach
                      </select>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label for="date" class="col-sm-2 col-form-label">Date</label>
                    <div class="col-sm-10">
                      <input type="date" name="date" id="date" class="form-control" value="{{ old('date') }}" required>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label for="arrival_time" class="col-sm-2 col-form-label">Arrival Time</label>
                    <div class="col-sm-10">
                      <input type="time" name="arrival_time" id="arrival_time" class="form-control" value="{{ old('arrival_time') }}" required>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label for="reason" class="col-sm-2 col-form-label">Reason</label>
                    <div class="col-sm-10">
                      <textarea name="reason" id="reason" class="form-control" rows="3" placeholder="Alasan keterlambatan (opsional)">{{ old('reason') }}</textarea>
                    </div>
                  </div>
                  <div class="form-group row">
                    <div class="col-sm-10 offset-sm-2">
                      <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save
                      </button>
                      <a href="{{ route('employee-late.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                  </div>
                </form>
              </div>
            </div>
            <!-- /.container-fluid -->

        </div>
        </div>
    @include('layout.footer')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
      $(document).ready(function() {
        $('.select2').select2({
          width: '100%'
        });
      });
    </script>
  </body>
</html>
