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
              <h1 class="h3 mb-0 text-gray-800">Create Outsource</h1>
              <a href="{{ route('outsource.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back </a>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Tambah Outsource </h6>
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

                <form action="{{ route('outsource.store') }}" method="POST">
                  @csrf
                  <div class="form-group row">
                    <label for="NPK" class="col-sm-2 col-form-label">NPK</label>
                    <div class="col-sm-10">
                      <input type="text" id="NPK" class="form-control" value="{{ $nextNpk }}" disabled>
                      <small class="form-text text-muted">NPK dibuat otomatis (increment dari NPK terakhir).</small>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label for="NAMA" class="col-sm-2 col-form-label">Nama</label>
                    <div class="col-sm-10">
                      <input type="text" name="NAMA" id="NAMA" class="form-control" value="{{ old('NAMA') }}" placeholder="Masukkan nama outsource" required>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label for="VENDOR" class="col-sm-2 col-form-label">Vendor</label>
                    <div class="col-sm-10">
                      <input type="text" name="VENDOR" id="VENDOR" class="form-control" value="{{ old('VENDOR') }}" placeholder="Masukkan nama vendor (opsional)">
                    </div>
                  </div>
                  <div class="form-group row">
                    <div class="col-sm-10 offset-sm-2">
                      <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save
                      </button>
                      <a href="{{ route('outsource.index') }}" class="btn btn-secondary">Cancel</a>
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