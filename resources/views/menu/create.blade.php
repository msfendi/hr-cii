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
                  Tambah Menu
               </h1>
            </div>
            {{-- ===============================
            FORM
            =============================== --}}
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">
                     Form Menu
                  </h6>
               </div>
               <div class="card-body">
                  <form action="{{ route('menu.store') }}" method="POST">
                     @csrf

                     <div class="form-group">
                        <label>Parent Menu</label>
                        <select name="parent_id" class="form-control">
                           <option value="">-- Tanpa Parent (Menu Utama) --</option>
                           @foreach ($parents as $parent)
                              <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>{{ $parent->name }}</option>
                           @endforeach
                        </select>
                     </div>

                     <div class="form-group">
                        <label>Nama Menu</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                     </div>

                     <div class="form-group">
                        <label>Route Name (kosongkan jika ini parent dropdown saja)</label>

                        <select name="route_name" id="route_name" class="form-control select2">
                           <option value="">-- Parent Menu (Tanpa Route) --</option>

                           @foreach($permissions as $permission)
                                 <option value="{{ $permission->route_name }}"
                                    {{ old('route_name') == $permission->route_name ? 'selected' : '' }}>
                                    {{ $permission->group }} - {{ $permission->name }}
                                 </option>
                           @endforeach
                        </select>
                     </div>

                     <div class="form-group">
                        <label>Icon (FontAwesome class)</label>
                        <input type="text" name="icon" class="form-control" value="{{ old('icon') }}" placeholder="Contoh: fas fa-users-cog">
                     </div>

                     <div class="form-group">
                        <label>Permission yang menentukan visibilitas menu ini</label>
                        <select id="permission_id" name="permission_id" class="form-control">
                           <option value="">-- Selalu Tampil (tanpa permission) --</option>
                           @foreach ($permissions as $permission)
                              <option value="{{ $permission->id }}" {{ old('permission_id') == $permission->id ? 'selected' : '' }}>
                                 {{ $permission->group }} - {{ $permission->name }}
                              </option>
                           @endforeach
                        </select>
                     </div>

                     <div class="form-group">
                        <label>Order</label>
                        <input type="number" name="order" class="form-control" value="{{ old('order', 0) }}">
                     </div>

                     <div class="form-group form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" value="1" checked>
                        <label class="form-check-label">Aktif</label>
                     </div>

                     <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                     <a href="{{ route('menu.index') }}" class="btn btn-secondary">Batal</a>
                  </form>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
   </body>
   
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script src="{{asset('vendor/jquery/select2.min.js')}}"></script>
      <script>
         $("#permission_id").select2({
         allowClear:true,
         placeholder:'Pilih Permissions'
         });
         $('#route_name').select2({
            placeholder: '-- Pilih Route --',
            allowClear: true,
            width: '100%'
         });
      </script>
</html>