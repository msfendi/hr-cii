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
                     Edit Late Compensation
                  </h1>
                  <div class="card shadow">
                     <div class="card-header">
                        <h6 class="font-weight-bold text-primary">
                           Form Edit
                        </h6>
                     </div>
                     <div class="card-body">
                        <form method="POST"
                           action="{{ route('late-compensation.update',$data->id) }}">
                           @csrf
                           <div class="form-group">
                              <label>NPK</label>
                              <input type="text"
                                 name="npk"
                                 value="{{ $data->npk }}"
                                 class="form-control" required readonly>
                           </div>
                           <div class="form-group">
                              <label>Date</label>
                              <input type="date"
                                 name="date"
                                 value="{{ $data->date }}"
                                 class="form-control" required>
                           </div>
                           <div class="form-group">
                              <label>Reason</label>
                              <textarea name="reason"
                                 class="form-control" required>{{ $data->reason }}</textarea>
                           </div>
                           <button class="btn btn-success btn-block">
                           Update Data
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
</html>