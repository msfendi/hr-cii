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
               <h1 class="h3 mb-0 text-gray-800">Update Shift</h1>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">Form Update Shift</h6>
               </div>
               <div class="card-body">
                  <form method="POST" action="{{ route('shift.update',$shift->id) }}">
                     @csrf
                     @method('PUT')
                     <div class="form-group">
                        <label>Name</label>
                        <input type="text"
                           name="name"
                           value="{{ $shift->name }}"
                           class="form-control"
                           required>
                     </div>
                     <div class="form-group">
                        <label>Start Date</label>
                        <input type="date"
                           name="start_date"
                           value="{{ $shift->start_date }}"
                           class="form-control"
                           required>
                     </div>
                     <div class="form-group">
                        <label>End Date</label>
                        <input type="date"
                           name="end_date"
                           value="{{ $shift->end_date }}"
                           class="form-control"
                           required>
                     </div>
                     <div class="form-group">
                        <label>Start Time</label>
                        <input type="time"
                           name="work_start"
                           value="{{ $shift->work_start }}"
                           class="form-control"
                           required>
                     </div>
                     <div class="form-group">
                        <label>End Time</label>
                        <input type="time"
                           name="work_end"
                           value="{{ $shift->work_end }}"
                           class="form-control"
                           required>
                     </div>
                     <div>
                        <label>Shift Gender :</label>
                        <select class="form-control" id="gender" name="gender">
                        <option value="L" {{ $shift->gender == 'L' ? 'selected' : '' }}>Male</option>
                        <option value="P" {{ $shift->gender == 'P' ? 'selected' : '' }}>Female</option>
                        <option value="ALL" {{ $shift->gender == 'ALL' ? 'selected' : '' }}>All</option>
                        </select>
                     </div>
                     <br>
                     <button class="btn btn-primary btn-block">
                     Update
                     </button>
                  </form>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
   </body>
</html>