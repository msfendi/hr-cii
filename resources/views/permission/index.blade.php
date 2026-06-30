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
                  Permissions
               </h1>
               <a href="{{ route('permission.create') }}" class="btn btn-primary btn-sm">
                  <i class="fas fa-plus"></i> Tambah Permission
               </a>
            </div>
            {{-- ===============================
            TABLE
            =============================== --}}
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">
                     Daftar Permission
                  </h6>
               </div>
               <div class="card-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm" id="dataTable">
                        <thead>
                           <tr>
                              <th>Group</th>
                              <th>Nama</th>
                              <th>Route Name</th>
                              <th>Deskripsi</th>
                              <th width="120" class="text-center">Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach ($permissions as $permission)
                           <tr>
                              <td>{{ $permission->group }}</td>
                              <td>{{ $permission->name }}</td>
                              <td><code>{{ $permission->route_name }}</code></td>
                              <td>{{ $permission->description }}</td>
                              {{-- ================= ACTION ================= --}}
                              <td class="text-center">
                                 <a href="{{ route('permission.edit', $permission->id) }}"
                                    class="btn btn-warning btn-sm">
                                 <i class="fas fa-edit"></i>
                                 </a>
                                 <button
                                    class="btn btn-danger btn-sm btn-delete"
                                    data-id="{{ $permission->id }}">
                                 <i class="fas fa-trash"></i>
                                 </button>
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
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>
         $(function(){

         $('#dataTable').DataTable({
         pageLength:10,
         ordering:true,
         responsive:true,
         autoWidth:false
         });


         /*
         =====================================
         DELETE CONFIRM
         =====================================
         */

         $('.btn-delete').click(function(){

         let id=$(this).data('id');

         Swal.fire({
         title:'Hapus Permission?',
         text:'Data permission akan dihapus!',
         icon:'warning',
         showCancelButton:true,
         confirmButtonColor:'#d33',
         confirmButtonText:'Ya Hapus'
         }).then((result)=>{

         if(result.isConfirmed){

         $.ajax({
         url:'/permission/'+id,
         type:'POST',
         data:{
         _token:'{{ csrf_token() }}',
         _method:'DELETE'
         },
         success:function(){

         Swal.fire({
         icon:'success',
         title:'Berhasil dihapus',
         timer:1200,
         showConfirmButton:false
         });

         setTimeout(()=>location.reload(),1200);

         },
         error:function(){
         Swal.fire('Error','Gagal menghapus data','error');
         }
         });

         }

         });

         });

         });

      </script>
   </body>
</html>