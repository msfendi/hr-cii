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
                    <h1 class="h3 mb-0 text-gray-800">Manajemen Saldo Cuti</h1>
                    <button class="btn btn-success btn-sm shadow-sm" id="btn-generate">
                        <i class="fas fa-sync-alt fa-sm"></i> Generate Balance Tahunan
                    </button>
                </div>
                
                <div class="card shadow mb-2">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Daftar Karyawan</h6>
                        <div>
                            <select id="department_filter" class="form-control form-control-sm d-inline-block shadow-sm" style="width: 200px; margin-right: 10px;">
                                <option value="">Filter Department</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->ID_DEPT }}">{{ $dept->DEPARTEMENT }}</option>
                                @endforeach
                            </select>
                            <input type="text" id="year_filter" class="form-control form-control-sm d-inline-block shadow-sm yearpicker" value="{{ date('Y') }}" style="width: 100px; background-color: white;" readonly>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>NPK</th>
                                        <th>Nama Karyawan</th>
                                        <th>Department</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->
@include('layout.footer')
    </div>
</div>

{{-- ===================== MODAL GENERATE BALANCE ===================== --}}
<div class="modal fade" id="modalGenerate" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg" style="border-radius: 10px; overflow: hidden;">
            <div class="modal-header pb-0 pt-4 px-4 bg-white border-0">
                <h5 class="modal-title font-weight-bold ml-2">
                    <i class="fas fa-sync-alt text-success mr-2"></i> Generate Balance Tahunan
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body px-4 pb-2">
                <p class="text-muted small mb-3">
                    Fitur ini akan membuat saldo cuti untuk seluruh karyawan yang sudah bekerja
                    <strong>minimal 1 tahun</strong> (TMK ≤ setahun lalu) dan masih aktif (TKK kosong).
                </p>

                <div class="form-group mb-2">
                    <label class="small font-weight-bold text-muted ml-1">Tahun Periode <span class="text-danger">*</span></label>
                    <input type="text" id="generate_year" class="form-control yearpicker" value="{{ date('Y') }}" style="background-color: white;" readonly>
                    <small class="text-muted ml-1">Data yang sudah ada (skipped) tidak akan ditimpa.</small>
                </div>

                {{-- Result area, tersembunyi sampai generate selesai --}}
                <div id="generate_result" class="d-none mt-3">
                    <hr>
                    <h6 class="font-weight-bold text-success"><i class="fas fa-check-circle mr-1"></i> Generate Selesai</h6>
                    <div class="row text-center mt-2">
                        <div class="col-4">
                            <div class="border rounded py-2">
                                <div class="text-muted small">Total Karyawan</div>
                                <strong class="h5 text-dark" id="res_employees">-</strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded py-2 border-success">
                                <div class="text-muted small">Dibuat Baru</div>
                                <strong class="h5 text-success" id="res_created">-</strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded py-2">
                                <div class="text-muted small">Dilewati</div>
                                <strong class="h5 text-secondary" id="res_skipped">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pb-4 pr-4 bg-white">
                <button type="button" class="btn btn-secondary px-4 font-weight-bold" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-success px-4 font-weight-bold shadow-sm" id="btn-confirm-generate">
                    <i class="fas fa-play mr-1"></i> Jalankan Generate
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize Year Picker
        $('.yearpicker').datepicker({
            format: "yyyy",
            viewMode: "years", 
            minViewMode: "years",
            autoclose: true
        });
        // ── DATATABLE ────────────────────────────────────────────────
        var table = $('#dataTable').DataTable({
            ajax: {
                url: '{{ route("leave-balances.get-data") }}',
                data: function(d) {
                    d.department_id = $('#department_filter').val();
                    d.year = $('#year_filter').val();
                },
                dataSrc: 'data'
            },
            pageLength: 15,
            columns: [
                { 
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'NPK' },
                { data: 'NAMA_KARYAWAN' },
                { data: 'DEPARTEMENT' },
                { 
                    data: null,
                    render: function(data, type, row) {
                        var year = $('#year_filter').val() || new Date().getFullYear();
                        return '<a href="{{ url("leave-balances/show") }}/' + row.NPK + '?year=' + year + '" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Detail Cuti</a>';
                    }
                }
            ]
        });

        $('#department_filter').on('change', function() {
            table.ajax.reload();
        });

        $('#year_filter').on('changeDate', function() {
            table.ajax.reload();
        });

        // ── BUKA MODAL GENERATE ──────────────────────────────────────
        $('#btn-generate').on('click', function () {
            // Set input modal generate_year mengikuti filter tahun yang sedang dipilih
            var currentFilterYear = $('#year_filter').val() || new Date().getFullYear();
            $('#generate_year').val(currentFilterYear);

            // Reset result panel tiap buka
            $('#generate_result').addClass('d-none');
            $('#res_employees, #res_created, #res_skipped').text('-');
            $('#btn-confirm-generate').prop('disabled', false).html('<i class="fas fa-play mr-1"></i> Jalankan Generate');
            $('#modalGenerate').modal('show');
        });

        // ── EKSEKUSI GENERATE ────────────────────────────────────────
        $('#btn-confirm-generate').on('click', function () {
            var year = $('#generate_year').val();

            Swal.fire({
                title: 'Konfirmasi Generate',
                html: 'Generate saldo cuti untuk tahun <b>' + year + '</b>?<br><small class="text-muted">Proses ini mungkin memerlukan waktu beberapa detik.</small>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#1cc88a',
                cancelButtonText: 'Batal',
                confirmButtonText: 'Ya, Generate!'
            }).then(function (result) {
                if (result.isConfirmed) {
                    var $btn = $('#btn-confirm-generate');
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Sedang Proses...');

                    $.ajax({
                        url: '{{ route("leave-balances.generate-yearly") }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            year: year
                        },
                        success: function (res) {
                            if (res.status === 'success') {
                                $('#res_employees').text(res.employees_count);
                                $('#res_created').text(res.created);
                                $('#res_skipped').text(res.skipped);
                                $('#generate_result').removeClass('d-none');
                                $btn.prop('disabled', true).html('<i class="fas fa-check mr-1"></i> Selesai');
                                table.ajax.reload();
                            } else {
                                Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                                $btn.prop('disabled', false).html('<i class="fas fa-play mr-1"></i> Jalankan Generate');
                            }
                        },
                        error: function (xhr) {
                            var msg = 'Gagal menjalankan generate.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                            console.log();
                            Swal.fire('Error', msg, 'error');
                            $btn.prop('disabled', false).html('<i class="fas fa-play mr-1"></i> Jalankan Generate');
                        }
                    });
                }
            });
        });
    });
</script>
</body>
</html>
