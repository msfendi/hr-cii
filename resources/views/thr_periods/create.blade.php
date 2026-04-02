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

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Create THR Period</h1>
    </div>

    <!-- Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Form Create THR Period
            </h6>
        </div>

        <div class="card-body">

            <form method="POST" action="{{ route('thr-periods.store') }}">
            @csrf

            {{-- ALERT --}}
            @foreach (['success','error','warning','info'] as $msg)
                @if ($message = Session::get($msg))
                    <div class="alert alert-{{ $msg == 'error' ? 'danger' : $msg }}">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                @endif
            @endforeach

            <!-- THR Name -->
            <div class="form-group">
                <label>THR Name</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    class="form-control"
                    readonly
                    required
                >
            </div>

            <!-- Cutoff Date -->
            <div class="form-group">
                <label>Cutoff Date</label>
                <input
                    type="date"
                    name="cutoff_date"
                    id="cutoff_date"
                    class="form-control"
                    required
                >
            </div>

            <!-- Submit -->
            <button type="submit" class="btn btn-primary btn-block">
                Create THR Period
            </button>

            </form>

        </div>
    </div>

</div>
</div>

@include('layout.footer')

</div>
</div>

<script>
document.getElementById('cutoff_date').addEventListener('change', function () {

    let value = this.value;
    if(!value) return;

    let year = new Date(value).getFullYear();

    document.getElementById('name').value = "THR " + year;
});
</script>

</body>
</html>