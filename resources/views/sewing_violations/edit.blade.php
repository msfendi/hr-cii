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
    <h5>Edit Pelanggaran Sewing</h5>
</div>

<div class="card-body">

<form method="POST"
      action="{{ route('sewing-violations.update') }}">

@csrf

<input type="hidden"
       name="id"
       value="{{ $data->id }}">

<div class="form-group">
    <label>Department</label>

    <select name="id_dept"
            class="form-control"
            required>

        @foreach($dept as $d)

        <option value="{{ $d->ID_DEPT }}"
            {{ $data->id_dept == $d->ID_DEPT ? 'selected' : '' }}>
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
           value="{{ $data->tanggal }}"
           required>
</div>

<div class="form-group">
    <label>Pelanggaran</label>

    <textarea
        name="pelanggaran"
        class="form-control"
        rows="5"
        required>{{ $data->pelanggaran }}</textarea>
</div>

<button class="btn btn-success">
    Update
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
</html>