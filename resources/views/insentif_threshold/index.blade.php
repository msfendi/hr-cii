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
<h1 class="h3 mb-0 text-gray-800">Insentif Threshold</h1>

<a href="{{ route('insentif.threshold.create') }}"
class="btn btn-primary btn-sm">
<i class="fas fa-plus"></i> Create Threshold
</a>
</div>

<div class="card shadow mb-4">

<div class="card-header">
<h6 class="font-weight-bold text-primary">
Data Threshold
</h6>
</div>

<div class="card-body">

@if ($message = Session::get('success'))
<div class="alert alert-success">{{ $message }}</div>
@endif

<div class="table-responsive">

<table class="table table-bordered table-sm" id="dataTable">

<thead class="thead-light">
<tr>
<th>ID</th>
<th>Insentif Type</th>
<th>Days</th>
<th>Minimum</th>
<th>Type</th>
<th width="120">Action</th>
</tr>
</thead>

<tbody>
@foreach($data as $row)
<tr>
<td>{{ $row->id }}</td>
<td>{{ $row->insentif_type }}</td>
<td>{{ $row->days }}</td>
<td>{{ $row->minimum }} %</td>
<td>{{ $row->type }}</td>

<td class="text-center">

<a href="{{ route('insentif.threshold.edit',$row->id) }}"
class="btn btn-primary btn-circle btn-sm">
<i class="fas fa-edit"></i>
</a>

<button
class="btn btn-danger btn-circle btn-sm btn-delete"
data-link="{{ route('insentif.threshold.delete',$row->id) }}"
data-id="{{ $row->id }}"
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

</div>

@include('layout.footer')

</div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteModal">
<div class="modal-dialog">
<div class="modal-content">

<div class="modal-header">
<h5>Delete Data</h5>
<button class="close" data-dismiss="modal">×</button>
</div>

<div class="modal-body">
<p id="deleteText"></p>
</div>

<div class="modal-footer">
<button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
<a id="deleteLink"><button class="btn btn-danger">Delete</button></a>
</div>

</div>
</div>
</div>

<script>
$('.btn-delete').click(function(){
let link=$(this).data('link');
let id=$(this).data('id');

$('#deleteLink').attr('href',link);
$('#deleteText').text('Yakin hapus data ID '+id+' ?');
});
</script>
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>
        $(document).ready(function(){

            $('#dataTable').DataTable({
                order: [[0,'desc']], // pakai urutan ID dari Laravel
                pageLength: 10,
                responsive: true,
                autoWidth:false
            });

        });
        </script>

</body>
</html>