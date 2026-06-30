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
               <h1 class="h3 mb-0 text-gray-800">
                  Tambah Permission
               </h1>
            </div>
            {{-- ===============================
            FORM
            =============================== --}}
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">
                     Form Permission
                  </h6>
               </div>
               <div class="card-body">
                  <form action="{{ route('permission.store') }}" method="POST">
                     @csrf
                     <div class="form-group">
                        <label>Nama Permission</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Contoh: Lihat Biodata" required>
                     </div>

                     <div class="form-group">
                        <label>Route Name</label>
                        <input type="text" name="route_name" class="form-control" list="route-list" value="{{ old('route_name') }}" placeholder="Contoh: biodata.index" required>
                        <datalist id="route-list">
                           @foreach ($routeNames as $rn)
                              <option value="{{ $rn }}">
                           @endforeach
                        </datalist>
                        <small class="text-muted">Pilih dari daftar nama route yang sudah terdaftar di aplikasi.</small>
                     </div>

                     <div class="form-group">
                        <label>Group (untuk pengelompokan tampilan)</label>
                        <input type="text" name="group" class="form-control" value="{{ old('group') }}" placeholder="Contoh: Biodata">
                     </div>

                     <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" class="form-control">{{ old('description') }}</textarea>
                     </div>

                     <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                     <a href="{{ route('permission.index') }}" class="btn btn-secondary">Batal</a>
                  </form>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
   </body>
</html>