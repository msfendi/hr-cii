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
                  Atur Permission untuk Role: {{ $role->name }}
               </h1>
            </div>
            {{-- ===============================
            FORM
            =============================== --}}
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">
                     Daftar Permission
                  </h6>
               </div>
               <div class="card-body">
                  <form action="{{ route('role-permission.update', $role->id) }}" method="POST">
                     @csrf @method('PUT')

                     @foreach ($permissions as $group => $items)
                        <h6 class="font-weight-bold text-primary mt-3">{{ $group ?: 'Tanpa Grup' }}</h6>
                        <div class="row">
                           @foreach ($items as $permission)
                              <div class="col-md-4 form-check ml-3 mb-1">
                                 <input type="checkbox" class="form-check-input" name="permission_ids[]"
                                    value="{{ $permission->id }}"
                                    {{ in_array($permission->id, $assignedIds) ? 'checked' : '' }}>
                                 <label class="form-check-label">{{ $permission->name }}</label>
                              </div>
                           @endforeach
                        </div>
                     @endforeach

                     <button class="btn btn-primary mt-4"><i class="fas fa-save"></i> Simpan</button>
                     <a href="{{ route('role-permission.index') }}" class="btn btn-secondary mt-4">Batal</a>
                  </form>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
   </body>
</html>