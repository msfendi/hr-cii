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
                     Create Chu Family
                  </h1>
                  <div class="card shadow">
                     <div class="card-body">
                        <form method="POST" action="{{ route('chu-family.store') }}">
                           @csrf
                           <div class="row">
                              <!-- LEFT -->
                              <div class="col-md-6">
                                 <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" name="name"
                                       class="form-control"
                                       value="{{ old('name') }}" required>
                                 </div>
                                 <div class="form-group">
                                    <label>Gender</label>
                                    <select name="gender" class="form-control" required>
                                       <option value="">-- Select Gender --</option>
                                       <option value="Male"
                                       {{ old('gender','Male')=='Male'?'selected':'' }}>
                                       Male
                                       </option>
                                       <option value="Female"
                                       {{ old('gender','Female')=='Female'?'selected':'' }}>
                                       Female
                                       </option>
                                    </select>
                                 </div>
                                 <div class="form-group">
                                    <label>Place</label>
                                    <input type="text" name="place"
                                       class="form-control"
                                       value="{{ old('place') }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Birth Date</label>
                                    <input type="date" name="birth_date"
                                       class="form-control"
                                       value="{{ old('birth_date') }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Nationality</label>
                                    <input type="text" name="nationality"
                                       class="form-control"
                                       value="{{ old('nationality') }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Passport Number</label>
                                    <input type="text" name="passport_number"
                                       class="form-control"
                                       value="{{ old('passport_number') }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Passport Expiry</label>
                                    <input type="date" name="passport_expiry"
                                       class="form-control"
                                       value="{{ old('passport_expiry') }}">
                                 </div>
                              </div>
                              <!-- RIGHT -->
                              <div class="col-md-6">
                                 <div class="form-group">
                                    <label>NPWP</label>
                                    <input type="text" name="npwp"
                                       class="form-control"
                                       value="{{ old('npwp') }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Visa Type</label>
                                    <input type="text" name="visa_type"
                                       class="form-control"
                                       value="{{ old('visa_type') }}">
                                 </div>
                                 <div class="form-group">
                                    <label>Visa Expiry</label>
                                    <input type="date" name="visa_expiry"
                                       class="form-control"
                                       value="{{ old('visa_expiry') }}">
                                 </div>
                                 <div class="form-group">
                                    <label>KITAS Expiry</label>
                                    <input type="date" name="kitas_expiry"
                                       class="form-control"
                                       value="{{ old('kitas_expiry') }}">
                                 </div>
                                 <div class="form-group">
                                    <label>RPTKA Expiry</label>
                                    <input type="date" name="rptka_expiry"
                                       class="form-control"
                                       value="{{ old('rptka_expiry') }}">
                                 </div>
                              </div>
                           </div>
                           <button type="submit" class="btn btn-primary btn-block mt-4">
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
</html>