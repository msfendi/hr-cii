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

<div class="card shadow">

<div class="card-header">
    <h5>Tambah Pelanggaran Sewing</h5>
</div>

<div class="card-body">

<form method="POST"
      action="{{ route('sewing-violations.store') }}">

@csrf

<div class="form-group">
    <label>Department</label>

    <select name="id_dept"
            class="form-control select2"
            required>

        @foreach($dept as $d)
        <option value="{{ $d->ID_DEPT }}">
            {{ $d->DEPARTEMENT }}
        </option>
        @endforeach

    </select>
</div>

<div class="form-group">
    <label>Tanggal</label>

    <input type="date"
           name="tanggal"
           class="form-control"
           required>
</div>

<div class="form-group">
    <label>Pelanggaran</label>

    <textarea
        name="pelanggaran"
        class="form-control"
        rows="5"
        required></textarea>
</div>

<button class="btn btn-success">
    Save
</button>

<a href="{{ route('sewing-violations.index') }}"
   class="btn btn-secondary">
    Back
</a>

</form>

</div>
</div>

</div>

</div>

@include('layout.footer')

</body>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
        rel="stylesheet" />

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%'
            });
        });
    </script>
</html>