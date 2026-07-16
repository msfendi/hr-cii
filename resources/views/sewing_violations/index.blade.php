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
    <h1 class="h3 mb-0 text-gray-800">
        Sewing Violations
    </h1>

    <a href="{{ route('sewing-violations.create') }}"
       class="btn btn-primary btn-sm shadow-sm">
        <i class="fas fa-plus"></i>
        Create Data
    </a>
</div>

<div class="card shadow mb-4">

<div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary">
        Violations Data
    </h6>
</div>

<div class="card-body">

<div class="table-responsive">

<table class="table table-bordered table-sm" id="dataTable">

<thead class="thead-light">
<tr>
    <th>ID</th>
    <th>Department</th>
    <th>Pelanggaran</th>
    <th>Tanggal</th>
    <th width="120">Action</th>
</tr>
</thead>

<tbody>

@foreach($data as $row)

<tr>
    <td>{{ $row->id }}</td>
    <td>{{ $row->DEPARTEMENT }}</td>
    <td>{{ $row->pelanggaran }}</td>
    <td>{{ $row->tanggal }}</td>

    <td class="text-center">

        <a href="{{ route('sewing-violations.edit',$row->id) }}"
            class="btn btn-primary btn-circle btn-sm">
            <i class="fas fa-edit"></i>
        </a>

        <button
            class="btn btn-danger btn-circle btn-sm btn-delete"
            data-link="{{ route('sewing-violations.delete',$row->id) }}"
            data-name="{{ $row->pelanggaran }}"
            data-toggle="modal"
            data-target="#deleteModal">
            <i class="fas fa-trash"></i>
        </button>

    </td>
</tr>

@endforeach

</tbody>

</table>

</div>

</div>

</div>

</div>

<div class="modal fade" id="deleteModal">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    Delete Data
                </h5>

                <button class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <p id="deleteText"></p>
            </div>

            <div class="modal-footer">

                <button class="btn btn-secondary"
                    data-dismiss="modal">
                    Cancel
                </button>

                <a id="deleteLink">
                    <button class="btn btn-danger">
                        Delete
                    </button>
                </a>

            </div>

        </div>
    </div>
</div>

</div>

@include('layout.footer')

<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>

<script>
$('.btn-delete').click(function(){

    $('#deleteLink').attr(
        'href',
        $(this).data('link')
    );

    $('#deleteText').text(
        'Apakah yakin ingin menghapus data ini ?'
    );

});
</script>

</body>
</html>