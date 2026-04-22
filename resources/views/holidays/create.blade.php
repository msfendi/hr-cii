<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Create Holiday</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Create Holiday </h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('holidays.store') }}"> @csrf {{-- ALERT --}} @if ($message = Session::get('success')) <div class="alert alert-success">{{ $message }}</div> @endif @if ($errors->any()) <div class="alert alert-danger">
                    <ul class="mb-0"> @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach </ul>
                  </div> @endif <div class="row">
                    <div class="col-md-12">
                      <div class="form-group">
                        <label>Holiday Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                      </div>
                      <div class="form-group">
                        <label>Holiday Date</label>
                        <input type="date" name="holiday_date" class="form-control" value="{{ old('holiday_date') }}" required>
                      </div>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary btn-block"> Submit Holiday </button>
                </form>
              </div>
            </div>
          </div>
        </div> @include('layout.footer')
      </div>
    </div>
    {{-- SELECT2 --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
    <script>
      $(document).ready(function() {
        $('.select2').select2({
          width: '100%',
          placeholder: 'Select Component'
        });
      });
    </script>
  </body>
</html>