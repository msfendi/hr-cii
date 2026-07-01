<!DOCTYPE html>
<html lang="en">
  @include('layout.header')
  <body id="page-top">
  @include('sweetalert::alert')
  <div id="wrapper">
  @include('layout.sidebar')
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
          @include('layout.navbar')
          <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Create Employee Violation</h1>
              <a href="{{ route('employee-violation.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-arrow-left fa-sm"></i> Back </a>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Employee Violation </h6>
              </div>
              <div class="card-body">
                @if ($errors->any())
                <div class="alert alert-danger">
                  <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                  </ul>
                </div>
                @endif
                <form action="{{ route('employee-violation.store') }}" method="POST">
                  @csrf
                  <div class="form-group">
                    <label for="period_id">Period</label>
                    <select name="period_id" id="period_id" class="form-control">
                      <option value="">-- Pilih Period --</option>
                      @foreach($periods as $period)
                      <option value="{{ $period->id }}" {{ old('period_id') == $period->id ? 'selected' : '' }}>
                        {{ $period->name }}
                      </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group">
                        <label>NPK</label>

                        <select name="npk" id="npk" class="form-control select2">
                           <option value="">-- Pilih Karyawan --</option>

                           @foreach($employees as $employee)
                                 <option value="{{ $employee->npk }}"
                                    {{ old('npk') == $employee->npk ? 'selected' : '' }}>
                                    {{ $employee->npk }} - {{ $employee->name }}
                                 </option>
                           @endforeach
                        </select>
                     </div>
                  <div class="form-group">
                    <label for="percentage">Percentage</label>
                    <div class="input-group">
                      <input type="number" step="0.01" min="0" max="100" name="percentage" id="percentage" class="form-control" value="{{ old('percentage') }}" placeholder="0.00">
                      <div class="input-group-append">
                        <span class="input-group-text">%</span>
                      </div>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save </button>
                </form>
              </div>
            </div>
          </div>
          <!-- /.container-fluid -->
        </div>
    @include('layout.footer')
  </body>
  
   <script>
$(function () {
    $('#npk').select2({
        placeholder: '-- Pilih Karyawan --',
        allowClear: true,
        width: '100%'
    });
});
</script>
</html>