<!DOCTYPE html>
<html lang="en">
   @include('layout.header')
   <body id="page-top">
      <div id="wrapper">
      @include('layout.sidebar')
      <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
         @include('layout.navbar')
         <div class="container-fluid">
            <h1 class="h3 mb-4 text-gray-800">
               Update Insentif Mapping
            </h1>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">
                     Edit Mapping
                  </h6>
               </div>
               <div class="card-body">
                  <form method="POST"
                     action="{{ route('dept-insentif-role.update',$data->id) }}">
                     @csrf
                     <div class="form-group">
                        <label>Department</label>
                        <select name="id_dept" id="id_dept" class="form-control" required>
                        @foreach($departments as $dept)
                        <option value="{{ $dept->ID_DEPT }}"
                        {{ $data->id_dept==$dept->ID_DEPT?'selected':'' }}>
                        {{ $dept->ID_DEPT }} - {{ $dept->DEPARTEMENT }}
                        </option>
                        @endforeach
                        </select>
                     </div>
                     <div class="form-group">
                        <label>Role Formula</label>
                        <select name="role" id="role" class="form-control" required>
                        @foreach($roles as $role)
                        <option value="{{ $role->id }}"
                        {{ $data->role==$role->id?'selected':'' }}>
                        {{ strtoupper($role->dept . ' - ' . $role->role) }}
                        </option>
                        @endforeach
                        </select>
                     </div>
                     <button class="btn btn-primary btn-block">
                     Update Mapping
                     </button>
                  </form>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
   </body>
   <script>
      $(document).ready(function() {
        $('#id_dept').select2({
            placeholder:'Select Department',
            allowClear:true,
            width:'100%'
        });
        
        $('#role').select2({
            placeholder:'Select Role',
            allowClear:true,
            width:'100%'
        });
      });
   </script>
</html>