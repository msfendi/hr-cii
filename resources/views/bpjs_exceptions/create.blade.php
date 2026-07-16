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
        <h1 class="h3 mb-0 text-gray-800">
            Create BPJS Exception
        </h1>
    </div>

    <div class="card shadow mb-4">

        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Form Create BPJS Exception
            </h6>
        </div>

        <div class="card-body">

            <form method="POST"
                  action="{{ route('bpjs-exceptions.store') }}">

                @csrf

                <div>
                    <label>NPK :</label>

                    <select class="form-control"
                            id="npk"
                            name="npk"
                            required>

                        <option value="">Choose Employee</option>

                        @foreach($employees as $employee)
                            <option value="{{ $employee->npk }}">
                                {{ $employee->npk }} - {{ $employee->name }}
                            </option>
                        @endforeach

                    </select>
                </div>

                <br>

                <div>
                    <label>Component :</label>

                    <select class="form-control"
                            id="component"
                            name="component"
                            required>

                            <option value="bpjs_kesehatan">BPJS Kesehatan</option>
                            <option value="bpjs_ketenagakerjaan">BPJS Ketenagakerjaan</option>

                    </select>
                </div>

                <br>

                <div>
                    <label>Percentage :</label>

                    <input type="number"
                           step="0.01"
                           class="form-control"
                           name="percentage"
                           required>
                </div>

                <br>

                <div class="row">
                    <div class="col-12">
                        <button type="submit"
                                class="btn btn-primary btn-block">
                            Create
                        </button>
                    </div>
                </div>

            </form>

        </div>
    </div>

</div>

</div>

@include('layout.footer')

</body>

<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet"/>

<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>

<script>
$('#npk').select2({
    allowClear:true,
    width:'100%'
});
</script>

</html>