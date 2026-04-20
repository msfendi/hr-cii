<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Create Expat On Leave</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Create Expat On Leave </h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('expat.onleave.store') }}"> @csrf @if ($message = Session::get('success')) <div class="alert alert-success">{{ $message }}</div> @endif @if ($errors->any()) <div class="alert alert-danger">
                    <ul class="mb-0"> @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach </ul>
                  </div> @endif <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>NPK</label>
                        <input type="text" name="npk" class="form-control" required>
                      </div>
                      <div class="form-group">
                        <label>On Leave Start</label>
                        <input type="date" name="onleave_start" class="form-control" required>
                      </div>
                      <div class="form-group">
                        <label>On Leave End</label>
                        <input type="date" name="onleave_end" class="form-control" required>
                      </div>
                      <div class="form-group">
                        <label>Leave Type</label>
                        <input type="text" name="leave_type" class="form-control">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Remark</label>
                        <textarea name="remark" class="form-control" rows="4"></textarea>
                      </div>
                    </div>
                    <div class="col-md-12">
                      <div class="form-group">
                      <label>Component & Amount</label>

                      <div id="component-rows">

                          <div class="row mb-2 component-row align-items-center">

                              {{-- COMPONENT --}}
                              <div class="col-md-4">
                                  <select name="component[]" 
                                          class="form-control select2" required>
                                      <option value="">-- Select Component --</option>
                                      @foreach($components as $item)
                                          <option value="{{ $item->id }}">
                                              {{ $item->component }}
                                          </option>
                                      @endforeach
                                  </select>
                              </div>

                              {{-- AMOUNT --}}
                              <div class="col-md-3">
                                  <input type="number"
                                        step="0.01"
                                        name="amount[]"
                                        class="form-control"
                                        placeholder="Amount"
                                        required>
                              </div>

                              
                              {{-- TRANSACTION DATE --}}
                              <div class="col-md-3">
                                  <input type="date"
                                        step="0.01"
                                        name="transactions_date[]"
                                        class="form-control"
                                        placeholder="Transaction Date"
                                        required>
                              </div>

                              {{-- REMOVE --}}
                              <div class="col-md-2">
                                  <button type="button" class="btn btn-danger remove-row btn-block">
                                      Remove
                                  </button>
                              </div>

                          </div>

                      </div>

                      <button type="button" class="btn btn-secondary mt-2" id="add-component">
                          Add Component
                      </button>
                  </div>
                  <button type="submit" class="btn btn-primary btn-block"> Save Expat On Leave </button>
                </form>
              </div>
            </div>
          </div>
      </div>
      </div>
    </div>@include('layout.footer')
    {{-- SELECT2 --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
    <script>
      $(document).ready(function() {
        $('.select2').select2({
          width: '100%'
        });
        $('#component').change(function() {
          let type = $(this).find(':selected').data('type');
          $('#component_type').val(type ?? '');
        });
      });
    </script>
    <script>
$(document).ready(function(){

    $('.select2').select2({
        width:'100%'
    });

    $('#add-component').click(function(){

        let row = `
        <div class="row mb-2 component-row align-items-center">

            <div class="col-md-4">
                <select name="component[]" class="form-control select2" required>
                    <option value="">-- Select Component --</option>
                    @foreach($components as $item)
                        <option value="{{ $item->id }}">
                            {{ $item->component }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <input type="number" step="0.01"
                       name="amount[]"
                       class="form-control"
                       placeholder="Amount"
                       required>
            </div>

            <div class="col-md-3">
                <input type="date"
                      step="0.01"
                      name="transactions_date[]"
                      class="form-control"
                      placeholder="Transaction Date"
                      required>
            </div>

            <div class="col-md-2">
                <button type="button" class="btn btn-danger remove-row btn-block">
                    Remove
                </button>
            </div>

        </div>`;

        $('#component-rows').append(row);

        $('.select2').select2({
            width:'100%'
        });

    });

    $(document).on('click','.remove-row',function(){
        $(this).closest('.component-row').remove();
    });

});
</script>
  </body>
</html>