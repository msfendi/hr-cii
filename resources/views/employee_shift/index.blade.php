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
               <h1 class="h3 mb-0 text-gray-800">Daftar Employee Shift</h1>
               <div>
                  <a href="{{ route('employee-shift.template') }}" class="btn btn-sm btn-info shadow-sm mb-1">
                     <i class="fas fa-download fa-sm text-white-50"></i> Download Template
                  </a>
                  <button type="button" class="btn btn-sm btn-success shadow-sm mb-1" data-toggle="modal" data-target="#importModal">
                     <i class="fas fa-file-import fa-sm text-white-50"></i> Import
                  </button>
                  <a href="{{ route('employee-shift.create') }}" class="btn btn-sm btn-primary shadow-sm mb-1">
                     <i class="fas fa-plus fa-sm text-white-50"></i> Create Employee Shift
                  </a>
               </div>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">Data Employee Shift</h6>
               </div>
               <div class="card-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm" id="dataTable">
                        <thead>
                           <tr>
                              <th>ID</th>
                              <th>NPK</th>
                              <th>Name</th>
                              <th>Dept</th>
                              <th>Shift</th>
                              <th>Shift Date</th>
                              <th>Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach($employeeShifts as $row)
                           <tr>
                              <td>{{ $row->id }}</td>
                              <td>{{ $row->npk }}</td>
                              <td>{{ $row->NAMA_KARYAWAN }}</td>
                              <td>{{ $row->DEPARTEMENT }}</td>
                              <td>{{ $row->shift->name ?? '-' }}</td>
                              <td>{{ $row->shift_date }}</td>
                              <td class="text-center">
                                 <a href="{{ route('employee-shift.edit',$row->id) }}"
                                    class="btn btn-primary btn-circle btn-sm">
                                 <i class="fas fa-edit"></i>
                                 </a>
                                 <a class="btn btn-danger btn-circle btn-sm btn-delete"
                                    data-delete-link="{{ route('employee-shift.delete',$row->id) }}"
                                    data-name="{{ $row->npk }}"
                                    data-toggle="modal"
                                    data-target="#deleteModal">
                                 <i class="fas fa-trash"></i>
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
      <!-- DELETE MODAL -->
      <div class="modal fade" id="deleteModal">
         <div class="modal-dialog modal-md">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title">Delete Record</h5>
                  <button class="close" data-dismiss="modal">x</button>
               </div>
               <div class="modal-body">
                  <p id="modal-text"></p>
               </div>
               <div class="modal-footer">
                  <button class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                  <a id="btn-confirm">
                  <button class="btn btn-primary">Confirm</button>
                  </a>
               </div>
            </div>
         </div>
      </div>

      <!-- Import Modal -->
      <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
         <div class="modal-dialog" role="document">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title" id="importModalLabel">Import Employee Shift</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                     <span aria-hidden="true">&times;</span>
                  </button>
               </div>
               <form action="{{ route('employee-shift.import') }}" method="POST" enctype="multipart/form-data">
                  @csrf
                  <div class="modal-body">
                     <div class="form-group">
                        <label>Pilih File</label>
                        <input type="file" name="file" class="form-control-file" required accept=".xlsx">
                     </div>
                  </div>
                  <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                     <button type="submit" class="btn btn-primary">Import Data</button>
                  </div>
               </form>
            </div>
         </div>
      </div>
      @include('layout.footer')
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
      <script>
         $('.btn-delete').on('click',function(){
         $('#btn-confirm').attr('href',$(this).data('delete-link'));
         $('#modal-text').text(
         'Apakah anda yakin ingin menghapus employee shift '+$(this).data('name')+' ?'
         );
         });
      </script>
   </body>
</html>