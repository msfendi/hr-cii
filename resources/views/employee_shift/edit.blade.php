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
               <h1 class="h3 mb-0 text-gray-800">Edit Employee Shift</h1>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">Form Edit Employee Shift</h6>
               </div>
               <div class="card-body">
                  <form method="POST"
                     action="{{ route('employee-shift.update',$employeeShift->id) }}">
                     @csrf
                     @method('PUT')
                     <div class="form-group">
                        <label>NPK</label>
                        <input type="text"
                           name="npk"
                           class="form-control"
                           value="{{ $employeeShift->npk }}"
                           required>
                     </div>
                     {{-- nama --}}
                     <div class="form-group">
                        <label>Nama</label>
                        <input type="text"
                           name="nama"
                           class="form-control"
                           value="{{ $biodatas->NAMA_KARYAWAN }}"
                           disabled>
                     </div>
                     <div class="form-group">
                        <label>Shift</label>
                        <select name="shift_id" class="form-control" required>
                        @foreach($shifts as $shift)
                        <option value="{{ $shift->id }}"
                        {{ $employeeShift->shift_id == $shift->id ? 'selected' : '' }}>
                        {{ $shift->name }}
                        </option>
                        @endforeach
                        </select>
                     </div>
                     <div class="form-group">
                        <label>Shift Date</label>
                        <input type="date"
                           name="shift_date"
                           class="form-control"
                           value="{{ \Carbon\Carbon::parse($employeeShift->shift_date)->format('Y-m-d') }}"
                           required>
                     </div>
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