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
                  Payroll Approval Setting
               </h1>
            </div>
            {{-- ===============================
            TABLE
            =============================== --}}
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">
                     Approval Component Payroll
                  </h6>
               </div>
               <div class="card-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm" id="dataTable">
                        <thead>
                           <tr>
                              <th width="80">ID</th>
                              <th>Component</th>
                              <th width="45%">Approval Flow</th>
                              <th width="120" class="text-center">Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach($data as $row)
                           <tr>
                              <td>{{ $row->id }}</td>
                              <td>
                                 <span class="font-weight-bold text-uppercase">
                                 {{ $row->component }}
                                 </span>
                              </td>
                              <td>
                                 <div class="p-2 border rounded bg-light">
                                    @foreach($row->approval_users as $index => $user)
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                       <span class="text-secondary">
                                       <b>{{ $user['npk'] }}</b>
                                       &nbsp;-&nbsp;
                                       {{ $user['name'] }}
                                       </span>
                                       <span class="badge badge-primary px-3 py-2">
                                       STEP {{ $index + 1 }}
                                       </span>
                                    </div>
                                    @endforeach
                                 </div>
                              </td>
                              {{-- ================= ACTION ================= --}}
                              <td class="text-center">
                                 @canRoute('payroll-setting.edit')
                                 <a href="{{ route('payroll-setting.edit',$row->id) }}"
                                    class="btn btn-warning btn-sm">
                                 <i class="fas fa-edit"></i>
                                 </a>
                                 @endcanRoute
                                 @canRoute('payroll-setting.delete')
                                 <button
                                    class="btn btn-danger btn-sm btn-delete"
                                    data-id="{{ $row->id }}">
                                 <i class="fas fa-trash"></i>
                                 </button>
                                 @endcanRoute
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
         title:'Hapus Setting?',
         text:'Data approval akan dihapus!',
         icon:'warning',
         showCancelButton:true,
         confirmButtonColor:'#d33',
         confirmButtonText:'Ya Hapus'
         }).then((result)=>{
         
         if(result.isConfirmed){
         
         $.ajax({
         url:'/payroll-setting/delete/'+id,
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