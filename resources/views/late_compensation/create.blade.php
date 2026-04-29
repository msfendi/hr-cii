<!DOCTYPE html>
<html lang="en">
   @include('layout.header')
   <body id="page-top">
      <div id="wrapper">
         @include('layout.sidebar')
         <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
               @include('layout.navbar')
               <div class="container-fluid">
                  <h1 class="h3 mb-4 text-gray-800">
                     Create Late Compensation
                  </h1>
                  <div class="card shadow">
                     <div class="card-header">
                        <h6 class="font-weight-bold text-primary">
                           Form Create
                        </h6>
                     </div>
                     <div class="card-body">
                        <form method="POST"
                           action="{{ route('late-compensation.store') }}">
                           @csrf
                           <div class="form-group">
                              <label>NPK</label>
                              <select name="npk" id="npk" class="form-control select2" required>
                                 <option value="">-- Pilih Karyawan --</option>
                                 @foreach($employees as $emp)
                                       <option value="{{ $emp->NPK }}">
                                          {{ $emp->NPK }} - {{ $emp->NAMA_KARYAWAN }}
                                       </option>
                                 @endforeach
                              </select>
                           </div>
                           <div class="form-group">
                              <label>Date</label>
                              <input type="date" name="date"
                                 class="form-control" required>
                           </div>
                           <div class="form-group">
                              <label>Reason</label>
                              <textarea name="reason"
                                 class="form-control" required></textarea>
                           </div>
                           <button class="btn btn-primary btn-block">
                           Save Data
                           </button>
                        </form>
                     </div>
                  </div>
               </div>
            </div>
            @include('layout.footer')
         </div>
      </div>
   </body>
   <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
   <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
   <script>
   $(document).ready(function() {
      $('.select2').select2({
         placeholder: "Select Employee",
         allowClear: true,
         width: '100%'
      });
   });
   </script>
</html>