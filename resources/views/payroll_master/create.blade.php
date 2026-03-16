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
                                    <input type="text" class="form-control" name="npk" value="{{ old('npk') }}" required>
                                </div>
                                <br>
                                <div>
                                    <label>Salary :</label>
                                    <input type="number" class="form-control" name="salary" value="{{ old('salary') }}" required>
                                </div>
                                <br>
                                <div>
                                    <label>Allowance :</label>
                                    <input type="number" class="form-control" name="allowance" value="{{ old('allowance') }}">
                                </div>
                                <br>
                                <div>
                                    <label>PPH 21 :</label>
                                    <input type="number" class="form-control" name="pph21" value="{{ old('pph21') }}">
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
</html>