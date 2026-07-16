<!DOCTYPE html>
<html lang="en">
@include('layout.header')
@include('sweetalert::alert')
<body id="page-top">
    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')

                <div class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Create Payroll Master</h1>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Form Create Payroll Master</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('payroll-master.store') }}">
                                @csrf

                                {{-- Alert Messages --}}
                                @if ($message = Session::get('success'))
                                    <div class="alert alert-success alert-block">
                                        <button type="button" class="close" data-dismiss="alert">×</button>
                                        <strong>{{ $message }}</strong>
                                    </div>
                                @endif

                                @if ($message = Session::get('error'))
                                    <div class="alert alert-danger alert-block">
                                        <button type="button" class="close" data-dismiss="alert">×</button>
                                        <strong>{{ $message }}</strong>
                                    </div>
                                @endif

                                @if ($message = Session::get('warning'))
                                    <div class="alert alert-warning alert-block">
                                        <button type="button" class="close" data-dismiss="alert">×</button>
                                        <strong>{{ $message }}</strong>
                                    </div>
                                @endif

                                @if ($message = Session::get('info'))
                                    <div class="alert alert-info alert-block">
                                        <button type="button" class="close" data-dismiss="alert">×</button>
                                        <strong>{{ $message }}</strong>
                                    </div>
                                @endif

                                {{-- Form Fields --}}
                                <div>
                                    <label>NPK :</label>
                                    <select id="npk" class="form-control" name="npk" required>
                                        <option value="">Select Employee</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->NPK }}" {{ old('npk') == $employee->NPK ? 'selected' : '' }}>
                                                {{ $employee->NPK }} - {{ $employee->NAMA_KARYAWAN }}
                                            </option>
                                        @endforeach
                                        </select>
                                </div>
                                <br>
                                <div>
                                    <label>Bank Name :</label>
                                    <select class="form-control" name="bank_name" required>
                                        <option value="">Select Bank</option>
                                        <option value="Permata Bank" {{ old('bank_name') == 'Permata Bank' ? 'selected' : '' }}>Permata Bank</option>
                                    </select>
                                </div>
                                <br>
                                <div>
                                    <label>Bank Account :</label>
                                    <input type="text" class="form-control" name="bank_account" value="{{ old('bank_account') }}" required>
                                </div>
                                <br>
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-block">Create Payroll Master</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @include('layout.footer')
        </div>
    </div>
</body>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $("#npk").select2({
    allowClear:true,
    placeholder:'Choose Employee'
    });
</script>
</html>