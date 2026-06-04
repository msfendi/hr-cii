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
         {{-- TITLE --}}
         <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Guest Master</h1>
            <a href="{{ route('guest-master.create') }}"
               class="btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus"></i> Create Guest
            </a>
         </div>
         <div class="card shadow mb-4">
            {{-- HEADER --}}
            <div class="card-header py-3">
               <h6 class="m-0 font-weight-bold text-primary">
                  Guest Master Data
               </h6>
            </div>
            {{-- BODY --}}
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
                           <th>Date of Birth</th>
                           <th>Age</th>
                           <th>Nationality</th>
                           <th>Passport No</th>
                           <th>Visa Type</th>
                           <th>Date of Issue</th>
                           <th>Must Used Before</th>
                           <th>Visa Status</th>
                           <th>Arrival Date</th>
                           <th>Expired Visa</th>
                           <th>Return Date</th>
                           <th>Status</th>
                           <th>Remarks</th>
                           <th width="120">Action</th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach($data as $row)
                        <tr>
                           <td>{{ $row->id }}</td>
                           <td>
                              <span class="badge badge-dark">
                              {{ $row->guest_name }}
                              </span>
                           </td>
                           <td>{{ $row->gender }}</td>
                           <td>{{ $row->place }}</td>
                           <td>{{ $row->date_of_birth }}</td>
                           <td>
                              @php
                                 $ageStatus = '-';
                                 $ageClass = 'success';

                                 if ($row->date_of_birth) {
                                       $birthDate = \Carbon\Carbon::parse($row->date_of_birth);
                                       $today = \Carbon\Carbon::today();

                                       $age = $birthDate->diff($today);

                                       $ageStatus = $age->y . ' Tahun '
                                                . $age->m . ' Bulan '
                                                . $age->d . ' Hari';
                                 }
                              @endphp

                              <span class="badge badge-{{ $ageClass }}">
                                 {{ $ageStatus }}
                              </span>
                           </td>
                           <td>{{ $row->nationality }}</td>
                           <td>{{ $row->passport_no }}</td>
                           <td>{{ $row->visa_type }}</td>
                           <td>{{ $row->issue_date }}</td>
                           <td>{{ $row->must_used_date }}</td>
                           <td>{{ $row->visa_status }}</td>
                           <td>{{ $row->arrival_date }}</td>
                           <td>{{ $row->visa_expiry }}</td>
                           <td>{{ $row->return }}</td>
                           <td>{{ $row->remark }}</td>
                           
                           {{-- ================================================= --}}
                           <td>
                              @if($row->status)
                              <span class="badge badge-success">
                              {{ $row->status }}
                              </span>
                              @endif
                           </td>
                           <td class="text-center">
                              <a href="{{ route('guest-master.edit',$row->id) }}"
                                 class="btn btn-primary btn-circle btn-sm">
                              <i class="fas fa-edit"></i>
                              </a>
                              <button
                                 class="btn btn-danger btn-circle btn-sm btn-delete"
                                 data-link="{{ route('guest-master.delete',$row->id) }}"
                                 data-name="{{ $row->guest_name }}"
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
                  <h5 class="modal-title">Delete Guest</h5>
                  <button class="close" data-dismiss="modal">
                  <span>×</span>
                  </button>
               </div>
               <div class="modal-body">
                  <p id="deleteText"></p>
               </div>
               <div class="modal-footer">
                  <button class="btn btn-secondary" data-dismiss="modal">
                  Cancel
                  </button>
                  <a id="deleteLink">
                  <button class="btn btn-danger">
                  Delete
                  </button>
                  </a>
               </div>
            </div>
         </div>
      </div>
      </div>
      @include('layout.footer')
   </body>
   <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
   <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

   <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css" rel="stylesheet">

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
   <script>
      $('.btn-delete').click(function(){
      
          let link = $(this).data('link');
          let name = $(this).data('name');
      
          $('#deleteLink').attr('href',link);
      
          $('#deleteText').text(
              'Apakah yakin ingin menghapus guest '+name+' ?'
          );
      
      });

      $(document).ready(function () {

    $('#dataTable').DataTable({
        dom: '<"row mb-3"<"col-md-6"B><"col-md-6"f>>rtip',

        buttons: [
            {
                text: '<i class="fas fa-file-excel"></i> Export Guest Master',
                className: 'btn btn-success',
                action: function () {
                    window.location.href =
                        "{{ route('guest-master.export') }}";
                }
            }
        ]
    });

});
   </script>
</html>