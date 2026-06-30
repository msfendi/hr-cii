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
                  Edit Permission
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
                  <form action="{{ route('permission.update', $permission->id) }}" method="POST">
                     @csrf @method('PUT')
                     <div class="form-group">
                        <label>Nama Permission</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $permission->name) }}" required>
                     </div>

                     <div class="form-group">
                        <label>Route Name</label>
                        <input type="text" name="route_name" class="form-control" list="route-list" value="{{ old('route_name', $permission->route_name) }}" required>
                        <datalist id="route-list">
                           @foreach ($routeNames as $rn)
                              <option value="{{ $rn }}">
                           @endforeach
                        </datalist>
                     </div>

                     <div class="form-group"> <label>Group</label> <select name="group" id="group" class="form-control select2"> <option value="">-- Pilih Group --</option> @foreach($groups as $group) <option value="{{ $group }}" {{ old('group', $permission->group) == $group ? 'selected' : '' }}> {{ $group }} </option> @endforeach </select> </div>

                     <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" class="form-control">{{ old('description', $permission->description) }}</textarea>
                     </div>

                     <button class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                     <a href="{{ route('permission.index') }}" class="btn btn-secondary">Batal</a>
                  </form>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
   </body>
   <script> $(function () { $('#group').select2({ placeholder: '-- Pilih Group --', allowClear: true, width: '100%' }); }); </script>
</html>