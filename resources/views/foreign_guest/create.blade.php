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
                  <div class="d-sm-flex align-items-center justify-content-between mb-4">
                     <h1 class="h3 mb-0 text-gray-800">
                        Create Foreign Guest
                     </h1>
                  </div>
                  <div class="card shadow mb-4">
                     <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                           Form Foreign Guest
                        </h6>
                     </div>
                     <div class="card-body">
                        <form method="POST"
                           action="{{ route('foreign-guest.store') }}"
                           enctype="multipart/form-data">
                           @csrf
                           @if ($errors->any())
                           <div class="alert alert-danger">
                              <ul class="mb-0">
                                 @foreach ($errors->all() as $error)
                                 <li>{{ $error }}</li>
                                 @endforeach
                              </ul>
                           </div>
                           @endif
                           <div class="row">
                              {{-- LEFT --}}
                              <div class="col-md-6">
                                 <div class="form-group">
                                    <label>Guest Name</label>
                                    <input type="text" name="guest_name" class="form-control" required>
                                 </div>
                                 <div class="form-group">
                                    <label>Bank Account</label>
                                    <input type="text" name="bank_account" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Photo</label>
                                    <input type="file" name="photo" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Passport</label>
                                    <input type="file" name="passport" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Visa Type</label>
                                    <input type="text" name="visa_type" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Visa Application</label>
                                    <input type="file" name="visa_application" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Visa Status</label>
                                    <input type="text" name="visa_status" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Visa Invoice</label>
                                    <input type="number" name="visa_invoice" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Rent Invoice</label>
                                    <input type="number" name="rent_invoice" class="form-control">
                                 </div>
                              </div>
                              {{-- RIGHT --}}
                              <div class="col-md-6">
                                 <div class="form-group">
                                    <label>Flight Detail</label>
                                    <input type="text" name="flight_detail" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Flight ETA</label>
                                    <input type="time" name="flight_eta" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>ETA</label>
                                    <input type="date" name="eta" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Return</label>
                                    <input type="date" name="return" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Hotel</label>
                                    <input type="text" name="hotel" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Hotel File</label>
                                    <input type="file" name="hotel_file" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Hotel Invoice</label>
                                    <input type="number" name="hotel_invoice" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Status</label>
                                    <input type="text" name="status" class="form-control">
                                 </div>
                              </div>
                           </div>
                           <button type="submit"
                              class="btn btn-primary btn-block">
                           Save Foreign Guest
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