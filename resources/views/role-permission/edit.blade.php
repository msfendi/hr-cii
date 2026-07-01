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
        <div>
            <h3 class="font-weight-bold text-gray-800 mb-1">
                Permission Role
            </h3>
            <p class="mb-0 text-muted">
                Atur permission untuk
                <span class="badge badge-primary px-3 py-2">
                    {{ $role->name }}
                </span>
            </p>
        </div>
    </div>

    <div class="card shadow">

        <div class="card-header bg-white">

            <div class="row align-items-center">

                <div class="col-md-6">
                    <h5 class="font-weight-bold text-primary mb-0">
                        Daftar Permission
                    </h5>
                </div>

                <div class="col-md-6">

                    <input
                        type="text"
                        class="form-control"
                        id="searchPermission"
                        placeholder="Cari permission...">

                </div>

            </div>

        </div>

        <div class="card-body">

            <form action="{{ route('role-permission.update',$role->id) }}" method="POST">

                @csrf
                @method('PUT')

                @foreach($permissions as $group => $items)

                <div class="card border-left-primary shadow-sm mb-4 permission-group">

                    <div class="card-header bg-light">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <h5 class="mb-0 font-weight-bold">
                                    {{ $group ?: 'Tanpa Group' }}
                                </h5>

                                <small class="text-muted">
                                    {{ count($items) }} Permission
                                </small>

                            </div>

                            <div>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-success check-all">
                                    ON Semua
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger uncheck-all">
                                    OFF Semua
                                </button>

                            </div>

                        </div>

                    </div>

                    <div class="card-body">

                        <div class="row">

                            @foreach($items as $permission)

                            <div class="col-lg-4 col-md-6 mb-3 permission-item">

                                <div class="d-flex justify-content-between align-items-center border rounded p-2">

                                    <div>

                                        <strong>{{ $permission->name }}</strong>

                                        @if($permission->route_name)
                                            <br>
                                            <small class="text-muted">
                                                {{ $permission->route_name }}
                                            </small>
                                        @endif

                                    </div>

                                    <div>

                                        <div class="custom-control custom-switch">

                                            <input
                                                type="checkbox"
                                                class="custom-control-input permission-switch"
                                                id="permission{{ $permission->id }}"
                                                name="permission_ids[]"
                                                value="{{ $permission->id }}"
                                                {{ in_array($permission->id,$assignedIds) ? 'checked' : '' }}>

                                            <label
                                                class="custom-control-label"
                                                for="permission{{ $permission->id }}">
                                            </label>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            @endforeach

                        </div>

                    </div>

                </div>

                @endforeach

                <div class="text-right">

                    <button class="btn btn-primary px-4">
                        <i class="fas fa-save"></i>
                        Simpan
                    </button>

                    <a href="{{ route('role-permission.index') }}"
                       class="btn btn-secondary">
                        Batal
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

</div>

@include('layout.footer')

<script>

$("#searchPermission").on("keyup", function(){

    let value = $(this).val().toLowerCase();

    $(".permission-item").filter(function(){

        $(this).toggle(
            $(this).text().toLowerCase().indexOf(value) > -1
        );

    });

});

$(".check-all").click(function(){

    $(this)
        .closest(".permission-group")
        .find(".permission-switch")
        .prop("checked",true);

});

$(".uncheck-all").click(function(){

    $(this)
        .closest(".permission-group")
        .find(".permission-switch")
        .prop("checked",false);

});

</script>

</body>
</html>