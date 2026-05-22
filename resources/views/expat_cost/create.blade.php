<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Create Expat Cost</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Create Expat Cost </h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('expat.cost.store') }}"> @csrf {{-- ALERT --}} @if ($message = Session::get('success')) <div class="alert alert-success">{{ $message }}</div> @endif @if ($errors->any()) <div class="alert alert-danger">
                    <ul class="mb-0"> @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach </ul>
                  </div> @endif <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Expat Name</label>
                        <select id="npk" name="npk" class="form-control select2" required>
                          <option value="">-- Select Expat Name --</option> @foreach($employees as $employee) <option value="{{ $employee->NPK }}">
                            {{ $employee->NPK }} - {{ $employee->NAMA_KARYAWAN }}
                          </option> @endforeach
                        </select>
                      </div>
                      <div class="form-group">
                        <label>Component</label>
                        <select id="component" name="component" class="form-control select2" required>
                          <option value="">-- Select Component --</option> @foreach($components as $item) <option value="{{ $item->id }}">
                            {{ $item->component }}
                          </option> @endforeach
                        </select>
                      </div>
                      <div class="form-group">
                        <label>Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" value="{{ old('amount') }}" required>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Transaction Date</label>
                        <input type="date" name="transactions_date" class="form-control" value="{{ old('transactions_date') }}" required>
                      </div>
                      <div class="form-group">
                        <label>Remark</label>
                        <textarea name="remark" class="form-control" rows="4">{{ old('remark') }}</textarea>
                      </div>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary btn-block"> Save Expat Cost </button>
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
        $('#component').select2({
          width: '100%',
          placeholder: 'Select Component'
        });
        $('#npk').select2({
          width: '100%',
          placeholder: 'Select Expat Name'
        });
      });
    </script>
  </body>
</html>