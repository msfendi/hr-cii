<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Create Expat Master</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Create Expat Master </h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('expat.master.store') }}"> @csrf {{-- ALERT --}} @if ($message = Session::get('success')) <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                  </div> @endif @if ($message = Session::get('error')) <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                  </div> @endif @if ($errors->any()) <div class="alert alert-danger">
                    <ul class="mb-0"> @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach </ul>
                  </div> @endif <div class="row">
                    <!-- LEFT -->
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>NPK</label>
                        <input type="text" name="npk" class="form-control" value="{{ old('npk') }}" required>
                      </div>
                      <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}">
                      </div>
                      <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" class="form-control" value="{{ old('position') }}">
                      </div>
                      <div class="form-group">
                        <label>Joining Date</label>
                        <input type="date" name="joining_date" class="form-control" value="{{ old('joining_date') }}">
                      </div>
                      <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}">
                      </div>
                      <div class="form-group">
                        <label>Passport Number</label>
                        <input type="text" name="passport_number" class="form-control" value="{{ old('passport_number') }}">
                      </div>
                      <div class="form-group">
                        <label>Passport Expiry</label>
                        <input type="date" name="passport_expiry" class="form-control" value="{{ old('passport_expiry') }}">
                      </div>
                    </div>
                    <!-- RIGHT -->
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>KITAS Expiry</label>
                        <input type="date" name="kitas_expiry" class="form-control" value="{{ old('kitas_expiry') }}">
                      </div>
                      <div class="form-group">
                        <label>RPTKA Expiry</label>
                        <input type="date" name="rptka_expiry" class="form-control" value="{{ old('rptka_expiry') }}">
                      </div>
                      <div class="form-group">
                        <label>MERP Expiry</label>
                        <input type="date" name="merp_expiry" class="form-control" value="{{ old('merp_expiry') }}">
                      </div>
                      <div class="form-group">
                        <label>House Address</label>
                        <textarea name="house_address" class="form-control" rows="3">{{ old('house_address') }}</textarea>
                      </div>
                      <div class="form-group">
                        <label>House Start Date</label>
                        <input type="date" name="house_startdate" class="form-control" value="{{ old('house_startdate') }}">
                      </div>
                      <div class="form-group">
                        <label>Lease End Date</label>
                        <input type="date" name="lease_enddate" class="form-control" value="{{ old('lease_enddate') }}">
                      </div>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-12">
                      <button type="submit" class="btn btn-primary btn-block"> Save Expat Master </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div> @include('layout.footer')
      </div>
    </div>
  </body>
</html>