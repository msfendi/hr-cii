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
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
               <h1 class="h3 mb-0 text-gray-800">Create Shift</h1>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">Form Create Shift</h6>
               </div>
               <div class="card-body">
                  <form method="POST" action="{{ route('shift.store') }}">
                     @csrf
                     <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required>
                     </div>
                     <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="work_start" class="form-control" required>
                     </div>
                     <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="work_end" class="form-control" required>
                     </div>
                     
                     <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                     </div>
                     
                     <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" required>
                     </div>
                     <div>
                        <label>Shift Gender :</label>
                        <select class="form-control" id="gender" name="gender">
                           <option value="L">Male</option>
                           <option value="P">Female</option>
                           <option value="ALL">All</option>
                        </select>
                     </div>
                     <div class="form-group mt-3">
                        <label>Status Hari Libur :</label>
                        <select class="form-control" id="is_holiday" name="is_holiday">
                           <option value="0">Tidak Libur</option>
                           <option value="1">Libur</option>
                        </select>
                     </div>
                     <br>
                     <button type="submit" class="btn btn-primary btn-block">
                     Create
                     </button>
                  </form>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
   </body>
</html>