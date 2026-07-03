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
              <h1 class="h3 mb-0 text-gray-800">Edit Employee Late</h1>
              <a href="{{ route('employee-late.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back </a>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Edit Employee Late </h6>
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

                <form action="{{ route('employee-late.update', $row->id) }}" method="POST">
                  @csrf
                  @method('PUT')
                  <div class="form-group row">
                    <label for="npk" class="col-sm-2 col-form-label">NPK</label>
                    <div class="col-sm-10">
                      <input type="text" name="npk" id="npk" class="form-control" value="{{ old('npk', $row->npk) }}" placeholder="Masukkan NPK karyawan" required>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label for="date" class="col-sm-2 col-form-label">Date</label>
                    <div class="col-sm-10">
                      <input type="date" name="date" id="date" class="form-control" value="{{ old('date', \Carbon\Carbon::parse($row->date)->format('Y-m-d')) }}" required>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label for="arrival_time" class="col-sm-2 col-form-label">Arrival Time</label>
                    <div class="col-sm-10">
                      <input type="time" name="arrival_time" id="arrival_time" class="form-control" value="{{ old('arrival_time', \Carbon\Carbon::parse($row->arrival_time)->format('H:i')) }}" required>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label for="reason" class="col-sm-2 col-form-label">Reason</label>
                    <div class="col-sm-10">
                      <textarea name="reason" id="reason" class="form-control" rows="3" placeholder="Alasan keterlambatan (opsional)">{{ old('reason', $row->reason) }}</textarea>
                    </div>
                  </div>
                  <div class="form-group row">
                    <div class="col-sm-10 offset-sm-2">
                      <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update
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
  </body>
</html>
