<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
<!-- Page Wrapper -->
@include('sweetalert::alert')
<div id="wrapper">
@include('layout.sidebar')
    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main Content -->
        <div id="content">
            @include('layout.navbar')
            <!-- Begin Page Content -->
            <div class="container-fluid">

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Payroll Process</h1>
                    <div>
                    </div>
                </div>
                
                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Payroll Process</h6>
                    </div>
                    <div class="card-body">
                            <form method="POST" action="{{ route('payroll-process.process') }}">
                            @csrf

                            <div class="mb-3">
                                <div class="form-group">
                                    <label>Payroll Periods :</label>
                                    <select class="form-control" id="period_id" name="period_id" required>
                                        <option value="">Pilih Periode</option>
                                        @foreach($periods as $period)
                                        <option value="{{ $period->id }}">
                                        {{ $period->name }}
                                        </option>
                                        @endforeach
                                    </select>

                                </div>
                            </div>
                            <button class="btn btn-primary">
                            Process Payroll
                            </button>

                            </form>
                    </div>
                </div>
                <!-- Content Row -->

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->
        <!-- Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md" role="document" >
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="delete-title" class="modal-title" id="exampleModalLabel">Delete Record</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <div class="modal-body"><p id="modal-text-payroll_comp"></p></div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Tutup</button>
                        <a id="btn-confirm" href=""><button class="btn btn-primary" type="button">Confirm</button></a>
                    </div>
                </div>
            </div>
        </div>

@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<!-- Page level custom scripts -->
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>

<script src="{{asset('vendor/jquery/select2.min.js')}}"></script>
<script type="text/javascript">
    $("#period_id").select2({
          allowClear: true,
          placeholder: 'Pilih Periode Payroll',
    });
</script>
<script>
    $('.btn-delete-payroll_comp').on('click', function () {
        $('#btn-confirm').attr('href', $(this).data('delete-link'));
        $("#modal-text-payroll_comp").text('Apakah anda yakin ingin menghapus payroll_comp ' + $(this).data('payroll_comp-name') + '?');
    });
</script>
</html>