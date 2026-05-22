<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Edit Expat Cost</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Edit Expat Cost </h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('expat.cost.update', $data->id) }}"> @csrf {{-- ALERT --}} @if ($message = Session::get('success')) <div class="alert alert-success">{{ $message }}</div> @endif @if ($errors->any()) <div class="alert alert-danger">
                    <ul class="mb-0"> @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach </ul>
                  </div> @endif <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>NPK</label>
                        <select class="form-control select2" name="npk" required>
                          <option value="">-- Select Employee --</option>
                          @foreach($employees as $emp)
                              <option value="{{ $emp->NPK }}" {{ old('npk', $data->npk) == $emp->NPK ? 'selected' : '' }}>
                                  {{ $emp->NPK }} - {{ $emp->NAMA_KARYAWAN }}
                              </option>
                          @endforeach
                        </select>
                      </div>
                      <div class="form-group">
                        <label>Component</label>
                        <select name="component" class="form-control select2" required>
                          <option value="">-- Select Component --</option>
                          @foreach($components as $item)
                              <option value="{{ $item->id }}" {{ (old('component', $data->component) == $item->id || old('component', $data->component) == $item->component) ? 'selected' : '' }}>
                                  {{ $item->component }}
                              </option>
                          @endforeach
                        </select>
                      </div>
                      <div class="form-group">
                        <label>Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" value="{{ old('amount', $data->amount) }}" required>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Transaction Date</label>
                        <input type="date" name="transactions_date" class="form-control" value="{{ old('transactions_date', $data->transactions_date) }}" required>
                      </div>
                      <div class="form-group">
                        <label>Remark</label>
                        <textarea name="remark" class="form-control" rows="4">{{ old('remark', $data->remark) }}</textarea>
                      </div>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary btn-block"> Update Expat Cost </button>
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
          width: '100%'
        });
      });
    </script>
  </body>
</html>
      