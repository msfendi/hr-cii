<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Create Insentif Threshold</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Create Insentif Threshold </h6>
              </div>
              <div class="card-body">
                     <form method="POST"
                        action="{{ route('insentif.threshold.store') }}">
                        @csrf
                        <div class="form-group">
                            <label>Insentif Type</label>
                            <select name="insentif_type" class="form-control select2" required>
                                <option value="">-- Select Insentif Type --</option>
                                <option value="Line">Sewing</option>
                                <option value="Pad Print">Pad Print</option>
                                <option value="Cutting">Cutting</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Days</label>
                            <input type="number" name="days"
                                class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Minimum</label>
                            <input type="number" step="0.01"
                                name="minimum" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Type</label>
                            <select name="type" class="form-control select2" required>
                                <option value="">-- Select Type --</option>
                                <option value="Percentage">Percentage</option>
                                <option value="Fixed">Fixed</option>
                            </select>
                        </div>
                           <button class="btn btn-primary btn-block">
                           Save Threshold
                           </button>
                     </form>
              </div>
            </div>
          </div>
        </div> @include('layout.footer')
      </div>
    </div>
   </body>
</html>