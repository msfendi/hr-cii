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
               <h1 class="h3 mb-0 text-gray-800">Whatsapp Devices</h1>
               <a href="{{ route('devices.create') }}" class="btn btn-primary btn-sm">
               <i class="fas fa-plus"></i> Add Device
               </a>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">Device List</h6>
               </div>
               <div class="card-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm" id="dataTable">
                        <thead>
                           <tr>
                              <th>ID</th>
                              <th>Name</th>
                              <th>Phone</th>
                              <th>Status</th>
                              <th>Quota</th>
                              <th width="120">Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach($devices as $device)
                           @php
                           $apiDevice = $fonnteDevices[$device->phone] ?? null;
                           $status = strtolower($apiDevice['status'] ?? 'unknown');
                           $quota  = $apiDevice['quota'] ?? '-';
                           $badge = $status == 'connect'
                           ? 'success'
                           : ($status == 'disconnect'
                           ? 'danger'
                           : 'secondary');
                           @endphp
                           <tr>
                              <td>{{ $device->id }}</td>
                              <td>{{ $device->name }}</td>
                              <td>{{ $device->phone }}</td>
                              <td>
                                 <span class="badge badge-{{ $badge }}">
                                 {{ ucfirst($status) }}
                                 </span>
                              </td>
                              <td>
                                 <span class="badge badge-info">
                                 {{ $quota }}
                                 </span>
                              </td>
                              <td class="text-center">
                                 <div class="d-flex justify-content-center align-items-center">
                                    {{-- QR BUTTON --}}
                                    @if($status != 'connect')
                                    <button
                                       class="btn btn-success btn-circle btn-sm mr-1 btn-qr"
                                       data-id="{{ $device->id }}"
                                       >
                                    <i class="fas fa-qrcode"></i>
                                    </button>
                                    @endif
                                    {{-- DISCONNECT BUTTON --}}
                                    @if($status != 'disconnect')
                                    <form
                                       action="{{ route('devices.disconnect',$device->id) }}"
                                       method="POST"
                                       onsubmit="return confirm('Disconnect this device?')"
                                       >
                                       @csrf
                                       <button class="btn btn-warning btn-circle btn-sm mr-1">
                                       <i class="fas fa-times"></i>
                                       </button>
                                    </form>
                                    @endif
                                    {{-- DELETE BUTTON (OPEN MODAL) --}}
                                    <button
                                       class="btn btn-danger btn-circle btn-sm btn-delete"
                                       data-id="{{ $device->id }}"
                                       >
                                    <i class="fas fa-trash"></i>
                                    </button>
                                    {{-- REAL DELETE FORM --}}
                                    <form
                                       id="delete-form-{{ $device->id }}"
                                       action="{{ route('devices.destroy',$device->id) }}"
                                       method="POST"
                                       style="display:none;"
                                       >
                                       @csrf
                                       @method('DELETE')
                                    </form>
                                 </div>
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
      {{-- ===================== QR MODAL ===================== --}}
      <div class="modal fade" id="qrModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title">Scan Whatsapp QR</h5>
                  <button class="close" data-dismiss="modal">&times;</button>
               </div>
               <div class="modal-body text-center">
                  <div id="qrLoading">
                     <i class="fas fa-spinner fa-spin fa-3x"></i>
                     <p>Loading QR...</p>
                  </div>
                  <img id="qrImage" style="max-width:300px; display:none">
                  <div id="qrConnected"
                     class="alert alert-success"
                     style="display:none">
                     Device Already Connected ✅
                  </div>
               </div>
            </div>
         </div>
      </div>
      {{-- ===================== DELETE CONFIRM MODAL ===================== --}}
      <div class="modal fade" id="deleteModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header bg-danger text-white">
                  <h5 class="modal-title">Delete Device</h5>
                  <button class="close text-white" data-dismiss="modal">&times;</button>
               </div>
               <div class="modal-body text-center">
                  <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                  <h5>Are you sure?</h5>
                  <p>This device will be permanently deleted.</p>
               </div>
               <div class="modal-footer justify-content-center">
                  <button class="btn btn-secondary" data-dismiss="modal">
                  Cancel
                  </button>
                  <button id="confirmDelete" class="btn btn-danger">
                  Yes, Delete
                  </button>
               </div>
            </div>
         </div>
      </div>
   </body>
   <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
   <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
   <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
   <script>
      /* ================= QR MODAL ================= */
      $('.btn-qr').click(function () {
      
          let id = $(this).data('id');
      
          $('#qrModal').modal('show');
      
          $('#qrLoading').show();
          $('#qrImage').hide();
          $('#qrConnected').hide();
      
          $.get('/devices/' + id + '/qr', function(res){
      
              $('#qrLoading').hide();
      
              if(res.connected){
                  $('#qrConnected').show();
                  return;
              }
      
              $('#qrImage')
                  .attr('src','data:image/png;base64,'+res.qr)
                  .show();
      
          }).fail(function(xhr){
      
              $('#qrLoading').hide();
              alert(xhr.responseJSON.error);
      
          });
      });
      
      
      /* ================= DELETE MODAL ================= */
      let deleteId = null;
      
      $('.btn-delete').click(function(){
      
          deleteId = $(this).data('id');
      
          $('#deleteModal').modal('show');
      });
      
      $('#confirmDelete').click(function(){
      
          if(deleteId){
              $('#delete-form-' + deleteId).submit();
          }
      
      });
   </script>
</html>