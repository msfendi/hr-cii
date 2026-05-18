<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Create EPO</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Create EPO </h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('epo.store') }}">
                           @csrf
                           <div class="row">
                              <div class="col-md-6">
                                 <div class="form-group">
                                    <label>Expat Name</label>
                                    <input type="text" name="expat_name" class="form-control" required>
                                 </div>
                                 <div class="form-group">
                                    <label>Gender</label>
                                    <select name="gender" class="form-control">
                                       <option>Male</option>
                                       <option>Female</option>
                                    </select>
                                 </div>
                                 <div class="form-group">
                                    <label>Place</label>
                                    <input type="text" name="place" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Birth Date</label>
                                    <input type="date" name="date_of_birth" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Nationality</label>
                                    <input type="text" name="nationality" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Position</label>
                                    <input type="text" name="position" class="form-control">
                                 </div>
                              </div>
                              <div class="col-md-6">
                                 <div class="form-group">
                                    <label>Department</label>
                                    <input type="text" name="department" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Termination Date</label>
                                    <input type="date" name="termination_date" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Must Leave Indonesia</label>
                                    <input type="date" name="must_leave_date" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>EPO Cost</label>
                                    <input type="number" name="epo_cost" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>RPTKA Cancellation Cost</label>
                                    <input type="number" name="rptka_cancellation_cost" class="form-control">
                                 </div>
                                 <div class="form-group">
                                    <label>Remarks</label>
                                    <input type="text" name="remarks" class="form-control">
                                 </div>
                              </div>
                           </div>
                           <button class="btn btn-primary btn-block mt-4">
                           Save Data
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