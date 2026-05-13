<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Update Payroll Master</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Update Payroll Master </h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('payroll-master.update',$data->id) }}"> @csrf @if ($message = Session::get('success')) <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                  </div> @endif @if ($message = Session::get('error')) <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                  </div> @endif @if ($message = Session::get('warning')) <div class="alert alert-warning alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                  </div> @endif @if ($message = Session::get('info')) <div class="alert alert-info alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                  </div> @endif <input type="hidden" class="form-control" name="id" value="{{ $data->id }}">
                  <div>
                    <label>NPK :</label>
                    <input type="text" class="form-control" name="npk" value="{{ $data->npk }}" required readonly>
                  </div>
                  <br>
                  <div>
                    <label>Bank Name :</label>
                    <input type="text" class="form-control" name="bank_name" value="{{ $data->bank_name }}" required readonly>
                  </div>
                  <br>
                  <div>
                    <label>Bank Account :</label>
                    <input type="text" class="form-control" name="bank_account" value="{{ $data->bank_account }}" required readonly>
                  </div>
                  <br>
                  <div class="row">
                    <div class="col-12">
                      <button type="submit" class="btn btn-primary btn-block"> Update Payroll Master </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div> @include('layout.footer')
  </body>
</html>