<!-- resources/views/food-order/canteens/edit.blade.php -->
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
            <h1 class="h3 mb-0 text-gray-800">Edit Kantin</h1>
            <a href="{{ route('canteens.index') }}" class="btn btn-sm btn-secondary shadow-sm">
              <i class="fas fa-arrow-left fa-sm text-white-50"></i> Kembali
            </a>
          </div>

          <div class="card shadow mb-4">
            <div class="card-header py-3">
              <h6 class="m-0 font-weight-bold text-primary">Form Kantin</h6>
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

              <form action="{{ route('canteens.update', $canteen->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Nama Kantin <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $canteen->name) }}" required>
                  </div>
                  <div class="form-group col-md-6">
                    <label>Lokasi</label>
                    <input type="text" name="location" class="form-control" value="{{ old('location', $canteen->location) }}">
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Nama PIC</label>
                    <input type="text" name="pic_name" class="form-control" value="{{ old('pic_name', $canteen->pic_name) }}">
                  </div>
                  <div class="form-group col-md-6">
                    <label>No. HP PIC</label>
                    <input type="text" name="pic_phone" class="form-control" value="{{ old('pic_phone', $canteen->pic_phone) }}">
                  </div>
                </div>

                <div class="form-group form-check">
                  <input type="checkbox" class="form-check-input" name="is_active" id="is_active" {{ $canteen->is_active ? 'checked' : '' }}>
                  <label class="form-check-label" for="is_active">Aktif</label>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                <a href="{{ route('canteens.index') }}" class="btn btn-secondary">Batal</a>
              </form>
            </div>
          </div>
        </div>
      </div>
  @include('layout.footer')
    </div>
  </body>
</html>