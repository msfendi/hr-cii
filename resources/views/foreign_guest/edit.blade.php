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
                        Edit Foreign Guest
                     </h1>
                  </div>
                  <div class="card shadow mb-4">
                     <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                           Form Edit Foreign Guest
                        </h6>
                     </div>
                     <div class="card-body">
                        <form method="POST"
                           action="{{ route('foreign-guest.update',$data->id) }}"
                           enctype="multipart/form-data">
                           @csrf
                           @method('PUT')
                           {{-- ERROR --}}
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
                              {{-- ================= LEFT ================= --}}
                              <div class="col-md-6">
                                 <div class="form-group">
                                    <label>Guest Name</label>
                                    <input type="text"
                                       name="guest_name"
                                       class="form-control"
                                       value="{{ old('guest_name',$data->guest_name) }}"
                                       required>
                                 </div>
                                 <div class="form-group">
                                    <label>Bank Account</label>
                                    <input type="text"
                                       name="bank_account"
                                       class="form-control"
                                       value="{{ old('bank_account',$data->bank_account) }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Photo</label>
                                    <input type="file" name="photo" class="form-control">
                                    @if($data->photo)
                                    <small class="text-success">
                                    Current File :
                                    <a href="{{ asset('storage/'.$data->photo) }}" target="_blank">
                                    View Photo
                                    </a>
                                    </small>
                                    @endif
                                 </div>
                                 <div class="form-group">
                                    <label>Passport</label>
                                    <input type="file" name="passport" class="form-control">
                                    @if($data->passport)
                                    <small class="text-success">
                                    <a href="{{ asset('storage/'.$data->passport) }}" target="_blank">
                                    View Passport
                                    </a>
                                    </small>
                                    @endif
                                 </div>
                                 <div class="form-group">
                                    <label>Visa Type</label>
                                    <input type="text"
                                       name="visa_type"
                                       class="form-control"
                                       value="{{ old('visa_type',$data->visa_type) }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Visa Application</label>
                                    <input type="file" name="visa_application" class="form-control">
                                    @if($data->visa_application)
                                    <small class="text-success">
                                    <a href="{{ asset('storage/'.$data->visa_application) }}" target="_blank">
                                    View File
                                    </a>
                                    </small>
                                    @endif
                                 </div>
                                 <div class="form-group">
                                    <label>Visa Status</label>
                                    <input type="text"
                                       name="visa_status"
                                       class="form-control"
                                       value="{{ old('visa_status',$data->visa_status) }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Visa Invoice</label>
                                    <input type="number"
                                       name="visa_invoice"
                                       class="form-control"
                                       value="{{ old('visa_invoice',$data->visa_invoice) }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Rent Invoice</label>
                                    <input type="number"
                                       name="rent_invoice"
                                       class="form-control"
                                       value="{{ old('rent_invoice',$data->rent_invoice) }}">
                                 </div>
                              </div>
                              {{-- ================= RIGHT ================= --}}
                              <div class="col-md-6">
                                 <div class="form-group">
                                    <label>Flight Detail</label>
                                    <input type="text"
                                       name="flight_detail"
                                       class="form-control"
                                       value="{{ old('flight_detail',$data->flight_detail) }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Flight ETA</label>
                                    <input type="time"
                                       name="flight_eta"
                                       class="form-control"
                                       value="{{ old('flight_eta',$data->flight_eta) }}">
                                 </div>
                                 <div class="form-group">
                                    <label>ETA</label>
                                    <input type="date"
                                       name="eta"
                                       class="form-control"
                                       value="{{ old('eta',$data->eta) }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Return</label>
                                    <input type="date"
                                       name="return"
                                       class="form-control"
                                       value="{{ old('return',$data->return) }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Hotel</label>
                                    <input type="text"
                                       name="hotel"
                                       class="form-control"
                                       value="{{ old('hotel',$data->hotel) }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Hotel File</label>
                                    <input type="file" name="hotel_file" class="form-control">
                                    @if($data->hotel_file)
                                    <small class="text-success">
                                    <a href="{{ asset('storage/'.$data->hotel_file) }}" target="_blank">
                                    View File
                                    </a>
                                    </small>
                                    @endif
                                 </div>
                                 <div class="form-group">
                                    <label>Hotel Invoice</label>
                                    <input type="number"
                                       name="hotel_invoice"
                                       class="form-control"
                                       value="{{ old('hotel_invoice',$data->hotel_invoice) }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Status</label>
                                    <input type="text"
                                       name="status"
                                       class="form-control"
                                       value="{{ old('status',$data->status) }}">
                                 </div>
                              </div>
                           </div>
                           <button type="submit" class="btn btn-primary btn-block">
                           Update Foreign Guest
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