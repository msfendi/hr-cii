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
               <h1 class="h3 mb-0 text-gray-800">Create Employee Shift</h1>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">Form Create Employee Shift</h6>
               </div>
               <div class="card-body">
                  <form method="POST" action="{{ route('employee-shift.store') }}">
                     @csrf
                     <div class="form-group">
                        <label>NPK</label>
                        <input type="text" name="npk" class="form-control" required>
                     </div>
                     <div class="form-group">
                        <label>Shift</label>
                        <select name="shift_id" class="form-control" required>
                           <option value="">-- Select Shift --</option>
                           @foreach($shifts as $shift)
                           <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                           @endforeach
                        </select>
                     </div>
                     <div class="form-group">
                        <label>Shift Date</label>
                        <input type="date" name="shift_date" class="form-control" required>
                     </div>
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