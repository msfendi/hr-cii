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
                  Role &amp; Permission
               </h1>
            </div>
            {{-- ===============================
            TABLE
            =============================== --}}
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">
                     Daftar Role
                  </h6>
               </div>
               <div class="card-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm" id="dataTable">
                        <thead>
                           <tr>
                              <th>Role</th>
                              <th>Guard Name</th>
                              <th width="150" class="text-center">Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach ($roles as $role)
                           <tr>
                              <td>{{ $role->name }}</td>
                              <td>{{ $role->guard_name }}</td>
                              <td class="text-center">
                                 <a href="{{ route('role-permission.edit', $role->id) }}"
                                    class="btn btn-primary btn-sm">
                                 <i class="fas fa-key"></i> Atur Permission
                                 </a>
                              </td>
                           </tr>
                           @endforeach
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script>
         $(function(){

         $('#dataTable').DataTable({
         pageLength:10,
         ordering:true,
         responsive:true,
         autoWidth:false
         });

         });

      </script>
   </body>
</html>