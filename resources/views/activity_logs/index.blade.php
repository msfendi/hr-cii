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
            <!-- TITLE -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
               <h1 class="h3 text-gray-800">
                  Activity Logs
               </h1>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">
                     System Audit Trail
                  </h6>
               </div>
               <div class="card-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm" id="dataTable">
                        <thead class="thead-light">
                           <tr>
                              <th>ID</th>
                              <th>User</th>
                              <th>Action</th>
                              <th>Model</th>
                              <th>Method</th>
                              <th>URL</th>
                              <th>IP</th>
                              <th>User Agent</th>
                              <th>Date</th>
                              <th>Detail</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach($logs as $log)
                           <tr>
                              <td>{{ $log->id }}</td>
                              <td>{{ optional($log->user)->name ?? '-' }}</td>
                              <td>
                                 @php
                                 $color = match($log->action){
                                 'created'=>'success',
                                 'updated'=>'warning',
                                 'deleted'=>'danger',
                                 default=>'secondary'
                                 };
                                 @endphp
                                 <span class="badge badge-{{ $color }}">
                                 {{ $log->action }}
                                 </span>
                              </td>
                              <td>{{ $log->model }}</td>
                              <td>{{ $log->method }}</td>
                              <td>{{ $log->url }}</td>
                              <td>{{ $log->ip }}</td>
                              <td>{{ $log->user_agent }}</td>
                              <td>
                                 {{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i:s') }}
                              </td>
                              <td class="text-center">
                                 <button
                                    class="btn btn-info btn-sm btn-detail"
                                    data-old='@json($log->old_data)'
                                    data-new='@json($log->new_data)'
                                    >
                                 <i class="fas fa-eye"></i>
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
      <!-- MODAL DETAIL -->
      <div class="modal fade" id="detailModal">
         <div class="modal-dialog modal-lg">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title">Activity Detail</h5>
                  <button class="close" data-dismiss="modal">×</button>
               </div>
               <div class="modal-body">
                  <div class="row">
                     <div class="col-md-6">
                        <h6>Old Data</h6>
                        <pre id="oldData"
                           style="background:#111;color:#0f0;padding:10px;height:350px;overflow:auto"></pre>
                     </div>
                     <div class="col-md-6">
                        <h6>New Data</h6>
                        <pre id="newData"
                           style="background:#111;color:#0ff;padding:10px;height:350px;overflow:auto"></pre>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
      <!-- DATATABLE FRONTEND ONLY -->
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script>

      $(function(){

      $('#dataTable').DataTable({
         pageLength:10,
         order:[[0,'desc']]
      });

      });


      /*
      |--------------------------------------------------------------------------
      | SHOW DETAIL
      |--------------------------------------------------------------------------
      */

      $(document).on('click','.btn-detail',function(){

         let oldData = $(this).attr('data-old');
         let newData = $(this).attr('data-new');

         // tampilkan apa adanya (pretty JSON)
         try{
            oldData = JSON.stringify(JSON.parse(oldData), null, 2);
         }catch(e){}

         try{
            newData = JSON.stringify(JSON.parse(newData), null, 2);
         }catch(e){}

         $('#oldData').text(oldData);
         $('#newData').text(newData);

         $('#detailModal').modal('show');

      });

      </script>
   </body>
</html>