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
               <h1 class="h3 mb-0 text-gray-800">Foreign Guest</h1>
               @canRoute('foreign-guest.create')
            <a href="{{ route('foreign-guest.create') }}"
               class="btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus"></i> Create Guest
            </a>
            @endcanRoute
         </div>
         <div class="card shadow mb-4">
            {{-- HEADER --}}
            <div class="card-header py-3">
               <h6 class="m-0 font-weight-bold text-primary">
                  Foreign Guest Data
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
                           <th>Bank</th>
                           <th>Visa Type</th>
                           <th>Visa Invoice</th>
                           <th>Visa Status</th>
                           <th>Flight ETA</th>
                           <th>Rent Invoice</th>
                           <th>ETA</th>
                           <th>Return</th>
                           <th>Hotel</th>
                           <th>Hotel Invoice</th>
                           <th>Document</th>
                           <th>Status</th>
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
                           <td>{{ $row->bank_account }}</td>
                           <td>
                              @if($row->visa_type)
                              <span class="badge badge-primary">
                              {{ $row->visa_type }}
                              </span>
                              @endif
                           </td>
                           <td>{{ $row->visa_invoice }}</td>
                           <td>
                              @if($row->visa_status)
                              <span class="badge badge-info">
                              {{ $row->visa_status }}
                              </span>
                              @endif
                           </td>
                           <td>
                              @if($row->flight_eta)
                              <span class="badge badge-secondary">
                              {{ $row->flight_eta }}
                              </span>
                              @endif
                           </td>
                           <td>{{ $row->rent_invoice }}</td>
                           <td>
                              @if($row->eta)
                              <span class="badge badge-secondary">
                              {{ $row->eta }}
                              </span>
                              @endif
                           </td>
                           <td>
                              @if($row->return)
                              <span class="badge badge-secondary">
                              {{ $row->return }}
                              </span>
                              @endif
                           </td>
                           <td>{{ $row->hotel }}</td>
                           <td>{{ $row->hotel_invoice }}</td>
                           {{-- ================= ATTACHMENT ================= --}}
                           <td>
                              {{-- PHOTO --}}
                              @if($row->photo)
                              <a href="{{ asset('storage/'.$row->photo) }}"
                                 target="_blank"
                                 class="btn btn-dark btn-sm mb-1">
                              <i class="fas fa-image"></i> Photo
                              </a>
                              @endif
                              {{-- PASSPORT --}}
                              @if($row->passport)
                              <a href="{{ asset('storage/'.$row->passport) }}"
                                 target="_blank"
                                 class="btn btn-primary btn-sm mb-1">
                              <i class="fas fa-passport"></i> Passport
                              </a>
                              @endif
                              {{-- VISA APPLICATION --}}
                              @if($row->visa_application)
                              <a href="{{ asset('storage/'.$row->visa_application) }}"
                                 target="_blank"
                                 class="btn btn-info btn-sm mb-1">
                              <i class="fas fa-file-alt"></i> Visa
                              </a>
                              @endif
                              {{-- HOTEL FILE --}}
                              @if($row->hotel_file)
                              <a href="{{ asset('storage/'.$row->hotel_file) }}"
                                 target="_blank"
                                 class="btn btn-success btn-sm mb-1">
                              <i class="fas fa-hotel"></i> Hotel
                              </a>
                              @endif
                           </td>
                           {{-- ================================================= --}}
                           <td>
                              @if($row->status)
                              <span class="badge badge-success">
                              {{ $row->status }}
                              </span>
                              @endif
                           </td>
                           <td class="text-center">
                              @canRoute('foreign-guest.delete')
                              <a href="{{ route('foreign-guest.edit',$row->id) }}"
                                 class="btn btn-primary btn-circle btn-sm">
                              <i class="fas fa-edit"></i>
                              </a>
                              @endcanRoute
                              @canRoute('foreign-guest.delete')
                              <button
                                 class="btn btn-danger btn-circle btn-sm btn-delete"
                                 data-link="{{ route('foreign-guest.delete',$row->id) }}"
                                 data-name="{{ $row->guest_name }}"
                                 data-toggle="modal"
                                 data-target="#deleteModal">
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
   <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
   <script>
      $('.btn-delete').click(function(){
      
          let link = $(this).data('link');
          let name = $(this).data('name');
      
          $('#deleteLink').attr('href',link);
      
          $('#deleteText').text(
              'Apakah yakin ingin menghapus guest '+name+' ?'
          );
      
      });
   </script>
</html>