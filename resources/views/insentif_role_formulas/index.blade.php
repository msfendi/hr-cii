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
            <h1 class="h3 mb-0 text-gray-800">Insentif Role Formula</h1>
            <a href="{{ route('insentif-role-formulas.create') }}"
               class="btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus"></i> Create Formula
            </a>
         </div>
         <div class="card shadow mb-4">
            <div class="card-header py-3">
               <h6 class="m-0 font-weight-bold text-primary">
                  Data Formula
               </h6>
            </div>
            <div class="card-body">
               @if(session('success'))
               <div class="alert alert-success">
                  {{ session('success') }}
               </div>
               @endif
               <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable">
                     <thead class="thead-light">
                        <tr>
                           <th>ID</th>
                           <th>Role</th>
                           <th>Dept</th>
                           <th>Formula</th>
                           <th>Status</th>
                           <th width="120">Action</th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach($data as $row)
                        <tr>
                           <td>{{ $row->id }}</td>
                           <td>
                              <span class="badge badge-primary">
                              {{ $row->role }}
                              </span>
                           </td>
                           <td>
                              <span class="badge badge-dark">
                              {{ $row->dept }}
                              </span>
                           </td>
                           <td>{{ $row->formula }}</td>
                           <td>
                              @if($row->is_active)
                              <span class="badge badge-success">Active</span>
                              @else
                              <span class="badge badge-danger">Inactive</span>
                              @endif
                           </td>
                           <td class="text-center">
                              <a href="{{ route('insentif-role-formulas.edit',$row->id) }}"
                                 class="btn btn-primary btn-circle btn-sm">
                              <i class="fas fa-edit"></i>
                              </a>
                              <button
                                 class="btn btn-danger btn-circle btn-sm btn-delete"
                                 data-link="{{ route('insentif-role-formulas.delete',$row->id) }}"
                                 data-name="{{ $row->role }}"
                                 data-toggle="modal"
                                 data-target="#deleteModal">
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
      {{-- DELETE MODAL --}}
      <div class="modal fade" id="deleteModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title">Delete Formula</h5>
                  <button class="close" data-dismiss="modal"><span>×</span></button>
               </div>
               <div class="modal-body">
                  <p id="deleteText"></p>
               </div>
               <div class="modal-footer">
                  <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <a id="deleteLink">
                  <button class="btn btn-danger">Delete</button>
                  </a>
               </div>
            </div>
         </div>
      </div>
      </div>
      @include('layout.footer')
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
      <script>
         $('.btn-delete').click(function(){
         
         let link=$(this).data('link');
         let name=$(this).data('name');
         
         $('#deleteLink').attr('href',link);
         
         $('#deleteText').text(
         'Apakah yakin ingin menghapus formula '+name+' ?'
         );
         
         });
      </script>
   </body>
</html>