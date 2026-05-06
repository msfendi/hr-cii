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
            @php use Carbon\Carbon; @endphp
            <!-- TITLE -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
               <h1 class="h3 text-gray-800">Chu Family Master</h1>
               <a href="{{ route('chu-family.create') }}"
                  class="btn btn-primary btn-sm shadow-sm">
               <i class="fas fa-plus"></i> Create Data
               </a>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <div class="d-flex justify-content-between flex-wrap">
                     <h6 class="m-0 font-weight-bold text-primary">
                        Chu Family Data
                     </h6>
                     <div class="d-flex">
                        <a href="{{ route('chu-family.export') }}"
                            class="btn btn-success btn-sm mr-2">
                            <i class="fas fa-file-excel"></i> Download Excel
                        </a>
                        <a href="{{ route('chu-family.template') }}"
                           class="btn btn-info btn-sm mr-2">
                        <i class="fas fa-download"></i> Download Template
                        </a>
                        <form id="importForm"
                           action="{{ route('chu-family.import') }}"
                           method="POST"
                           enctype="multipart/form-data">
                           @csrf
                           <div class="input-group input-group-sm">
                              <input type="file"
                                 name="file"
                                 id="fileInput"
                                 class="d-none"
                                 accept=".xlsx,.xls,.csv">
                              <button type="button"
                                 class="btn btn-primary btn-sm"
                                 id="btnUpload">
                              Upload Excel
                              </button>
                              <span id="fileName"
                                 class="form-control form-control-sm">
                              No file selected
                              </span>
                              <div class="input-group-append">
                                 <button type="submit"
                                    class="btn btn-success btn-sm">
                                 Import
                                 </button>
                              </div>
                           </div>
                        </form>
                     </div>
                  </div>
                  <div class="progress mt-3"
                     style="display:none"
                     id="uploadProgress">
                     <div class="progress-bar progress-bar-striped progress-bar-animated"
                        id="progressBar"
                        style="width:0%">0%</div>
                  </div>
               </div>
               <!-- BODY -->
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
                              <th>Name</th>
                              <th>Gender</th>
                              <th>Place</th>
                              <th>Birth Date</th>
                              <th>Age</th>
                              <th>Nationality</th>
                              <th>Passport</th>
                              <th>Passport Exp</th>
                              <th>Visa Type</th>
                              <th>Visa Exp</th>
                              <th>Visa Status</th>
                              <th>KITAS Exp</th>
                              <th>RPTKA Exp</th>
                              <th>RPTKA Status</th>
                              <th width="120">Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach($data as $row)
                           @php
                           $today = Carbon::today();
                           /* ================= AGE Y/M/D ================= */
                           $age='-';
                           if($row->birth_date){
                           $birth = Carbon::parse($row->birth_date);
                           $diff  = $birth->diff($today);
                           $age = $diff->y.' Tahun '
                           .$diff->m.' Bulan '
                           .$diff->d.' Hari';
                           }
                           /* ================= VISA ================= */
                           $visaStatus='-';
                           if($row->visa_expiry){
                           $days=$today->diffInDays(Carbon::parse($row->visa_expiry),false);
                           if($days<0) $visaStatus='Expired';
                           elseif($days<=30) $visaStatus="Warning ($days days)";
                           else $visaStatus="$days days left";
                           }
                           /* ================= RPTKA ================= */
                           $rptkaStatus='-';
                           if($row->rptka_expiry){
                           $days=$today->diffInDays(Carbon::parse($row->rptka_expiry),false);
                           if($days<0) $rptkaStatus='Expired';
                           elseif($days<=30) $rptkaStatus="Warning ($days days)";
                           else $rptkaStatus="$days days left";
                           }
                           @endphp
                           <tr>
                              <td>{{ $row->id }}</td>
                              <td>{{ $row->name }}</td>
                              <td>{{ $row->gender }}</td>
                              <td>{{ $row->place }}</td>
                              <td>{{ $row->birth_date }}</td>
                              <td><b>{{ $age }}</b></td>
                              <td>{{ $row->nationality }}</td>
                              <td>{{ $row->passport_number }}</td>
                              <td>{{ $row->passport_expiry }}</td>
                              <td>{{ $row->visa_type }}</td>
                              <td>{{ $row->visa_expiry }}</td>
                              <td>
                                 @if($visaStatus=='Expired')
                                 <span class="badge badge-danger">{{ $visaStatus }}</span>
                                 @elseif(str_contains($visaStatus,'Warning'))
                                 <span class="badge badge-warning">{{ $visaStatus }}</span>
                                 @else
                                 <span class="badge badge-success">{{ $visaStatus }}</span>
                                 @endif
                              </td>
                              <td>{{ $row->kitas_expiry }}</td>
                              <td>{{ $row->rptka_expiry }}</td>
                              <td>
                                 @if($rptkaStatus=='Expired')
                                 <span class="badge badge-danger">{{ $rptkaStatus }}</span>
                                 @elseif(str_contains($rptkaStatus,'Warning'))
                                 <span class="badge badge-warning">{{ $rptkaStatus }}</span>
                                 @else
                                 <span class="badge badge-success">{{ $rptkaStatus }}</span>
                                 @endif
                              </td>
                              <td class="text-center">
                                 <a href="{{ route('chu-family.edit',$row->id) }}"
                                    class="btn btn-primary btn-circle btn-sm">
                                 <i class="fas fa-edit"></i>
                                 </a>
                                 <button
                                    class="btn btn-danger btn-circle btn-sm btn-delete"
                                    data-link="{{ route('chu-family.delete',$row->id) }}"
                                    data-name="{{ $row->name }}"
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
      </div>
      <!-- DELETE MODAL -->
      <div class="modal fade" id="deleteModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title">Delete Data</h5>
                  <button class="close" data-dismiss="modal">×</button>
               </div>
               <div class="modal-body">
                  <p id="deleteText"></p>
               </div>
               <div class="modal-footer">
                  <button class="btn btn-secondary" data-dismiss="modal">
                  Cancel
                  </button>
                  <a id="deleteLink">
                  <button class="btn btn-danger">Delete</button>
                  </a>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
      <!-- SCRIPT TIDAK DIHAPUS -->
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>
         $('.btn-delete').click(function(){
         $('#deleteLink').attr('href',$(this).data('link'));
         $('#deleteText').text('Delete data '+$(this).data('name')+' ?');
         });
         
         $('#btnUpload').click(()=>$('#fileInput').click());
         
         $('#fileInput').change(function(){
         let file=this.files[0];
         if(file) $('#fileName').text(file.name);
         });
         
         $('#importForm').submit(function(e){
         
         e.preventDefault();
         
         let form=this;
         let formData=new FormData(form);
         
         $('#uploadProgress').show();
         
         $.ajax({
         
         xhr:function(){
         let xhr=new window.XMLHttpRequest();
         xhr.upload.addEventListener("progress",function(evt){
         if(evt.lengthComputable){
         let percent=Math.round((evt.loaded/evt.total)*100);
         $('#progressBar').css('width',percent+'%');
         $('#progressBar').text(percent+'%');
         }
         });
         return xhr;
         },
         
         url:$(form).attr('action'),
         type:'POST',
         data:formData,
         processData:false,
         contentType:false,
         
         success:function(){
         
         $('#progressBar').css('width','100%');
         $('#progressBar').text('100%');
         
         Swal.fire({
         icon:'success',
         title:'Import berhasil',
         text:'Halaman akan diperbarui...',
         showConfirmButton:false,
         timer:1500
         });
         
         setTimeout(()=>location.reload(),1500);
         },
         
         error:function(){
         Swal.fire({
         icon:'error',
         title:'Import gagal'
         });
         }
         
         });
         
         });
         
      </script>
   </body>
</html>