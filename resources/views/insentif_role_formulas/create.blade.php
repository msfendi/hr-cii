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
               Create Insentif Role Formula
            </h1>
            <div class="card shadow mb-4">
               <div class="card-body">
                  <form method="POST"
                     action="{{ route('insentif-role-formulas.store') }}">
                     @csrf
                     <div class="form-group">
                        <label>Role</label>
                        <input type="text" name="role" class="form-control" required>
                     </div>
                     <div class="form-group">
                        <label>Dept</label>
                        <select name="dept" id="dept" class="form-control" required>
                           <option value="">-- Select Dept --</option>
                           @foreach($depts as $dept)
                           <option value="{{ $dept }}">{{ strtoupper($dept) }}</option>
                           @endforeach
                        </select>
                     </div>
                     <div class="form-group">
                        <label>Formula</label>
                        <textarea name="formula" class="form-control" required></textarea>
                     </div>
                     <div class="form-group">
                        <label>Status</label>
                        <select name="is_active" class="form-control">
                           <option value="1">Active</option>
                           <option value="0">Inactive</option>
                        </select>
                     </div>
                     <button class="btn btn-primary btn-block">
                     Save Formula
                     </button>
                  </form>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
      <script>
         $('#dept').select2({
         placeholder:'Select Dept',
         allowClear:true,
         width:'100%'
         });
      </script>
   </body>
</html>