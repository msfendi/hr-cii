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
                    <h1 class="h3 mb-0 text-gray-800">BPJS Exception</h1>

                    <div>
                        <a href="{{ route('bpjs-exceptions.create') }}"
                           class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                            <i class="fas fa-plus fa-sm text-white-50"></i>
                            Create BPJS Exception
                        </a>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Data BPJS Exception
                        </h6>
                    </div>

                    <div class="card-body">

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

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm"
                                   id="dataTable"
                                   width="100%"
                                   cellspacing="0">

                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>NPK</th>
                                        <th>Name</th>
                                        <th>Dept</th>
                                        <th>Component</th>
                                        <th>Percentage</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach($data as $row)
                                    <tr>
                                        <td>{{ $row->id }}</td>
                                        <td>{{ $row->npk }}</td>
                                        <td>{{ $row->name }}</td>
                                        <td>{{ $row->DEPARTEMENT }}</td>
                                        <td>{{ $row->component }}</td>
                                        <td>{{ number_format($row->percentage,2) }} %</td>

                                        <td class="text-center">

                                            <a href="{{ route('bpjs-exceptions.edit',$row->id) }}"
                                                class="btn btn-primary btn-circle btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <a class="btn btn-danger btn-circle btn-sm btn-delete"
                                                data-delete-link="{{ route('bpjs-exceptions.destroy',$row->id) }}"
                                                data-name="{{ $row->npk }}"
                                                data-toggle="modal"
                                                data-target="#deleteModal">
                                                <i class="fas fa-trash"></i>
                                            </a>

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

        <div class="modal fade"
            id="deleteModal"
            tabindex="-1"
            role="dialog">

            <div class="modal-dialog modal-md" role="document">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            Delete Record
                        </h5>

                        <button class="close"
                            type="button"
                            data-dismiss="modal">
                            <span>x</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <p id="modal-text"></p>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary"
                            type="button"
                            data-dismiss="modal">
                            Tutup
                        </button>

                        <form id="delete-form"
                            method="POST">
                            @csrf
                            @method('DELETE')

                            <button class="btn btn-danger">
                                Confirm
                            </button>
                        </form>
                    </div>

                </div>
            </div>

        </div>

@include('layout.footer')

</body>

<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('js/demo/datatables-demo.js') }}"></script>

<script>
$('.btn-delete').on('click', function(){

    $('#delete-form').attr('action',
        $(this).data('delete-link')
    );

    $('#modal-text').text(
        'Apakah anda yakin ingin menghapus data ' +
        $(this).data('name') + ' ?'
    );
});
</script>

</html>
