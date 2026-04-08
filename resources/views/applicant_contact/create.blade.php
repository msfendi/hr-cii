<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Create Contact</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Form Create Contact</h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('applicant-contact.store') }}"> @csrf 
                  <input type="hidden" name="device" class="form-control" value ="{{ $device->phone }}" required>
                  <label>Name</label>
                  <input type="text" name="name" class="form-control" required>
                  <br>
                  <label>Phone</label>
                  <input type="text" name="phone" class="form-control" required>
                  <br>
                  <label>Position</label>
                  <input type="text" name="position" class="form-control">
                  <br>
                  <button class="btn btn-primary btn-block"> Save Contact </button>
                </form>
              </div>
            </div>
          </div>
        </div> @include('layout.footer')
  </body>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
  <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
</html>