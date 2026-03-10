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

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Poliklinik — Daftar Kunjungan</h1>
                </div>

                <!-- Filter & Actions Card -->
                <div class="card shadow mb-2">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Data Kunjungan</h6>
                        <div class="d-flex align-items-center flex-wrap">
                            <button type="button" class="btn btn-success btn-sm mb-2 mr-2" data-toggle="modal" data-target="#daftarModal">
                                <i class="fas fa-plus-circle"></i> Daftar Kunjungan Baru
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="filter_tanggal">Tanggal</label>
                                <input type="date" class="form-control form-control-sm" id="filter_tanggal" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-md-3">
                                <label for="filter_status">Status</label>
                                <select class="form-control form-control-sm" id="filter_status">
                                    <option value="all">Semua</option>
                                    <option value="menunggu">Menunggu</option>
                                    <option value="diperiksa">Diperiksa</option>
                                    <option value="selesai">Selesai</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_departemen">Departemen</label>
                                <select class="form-control form-control-sm" id="filter_departemen">
                                    <option value="">Semua</option>
                                    @foreach($departemens as $id => $nama)
                                        <option value="{{ $nama }}">{{ $nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="kunjunganTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>No. Antrian</th>
                                        <th>NPK</th>
                                        <th>Nama Karyawan</th>
                                        <th>Departemen</th>
                                        <th>Tanggal</th>
                                        <th>Keluhan</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- End of Main Content -->
@include('layout.footer')

<!-- Modal Daftar Kunjungan Baru -->
<div class="modal fade" id="daftarModal" tabindex="-1" role="dialog" aria-labelledby="daftarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="daftarModalLabel"><i class="fas fa-user-plus"></i> Daftar Kunjungan Baru</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="daftarForm">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="NPK">Karyawan (NPK / Nama)</label>
                        <select class="form-control" id="NPK" name="NPK" style="width: 100%;" required>
                            <option value="">-- Cari Karyawan --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="keluhan">Keluhan</label>
                        <textarea class="form-control" id="keluhan" name="keluhan" rows="3" placeholder="Masukkan keluhan pasien..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_kunjungan">Tanggal Kunjungan</label>
                        <input type="date" class="form-control" id="tanggal_kunjungan" name="tanggal_kunjungan" value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Daftarkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css" rel="stylesheet" />

<script>
$(document).ready(function() {
    // Select2 for karyawan search
    $('#NPK').select2({
        dropdownParent: $('#daftarModal'),
        theme: 'bootstrap4',
        placeholder: '-- Cari NPK atau Nama Karyawan --',
        allowClear: true,
        ajax: {
            url: '{{ route("kunjungan.search-karyawan") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return { results: data.results };
            },
            cache: true
        },
        minimumInputLength: 2
    });

    // DataTable
    var table = $('#kunjunganTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("kunjungan.get-data") }}',
            type: 'GET',
            data: function(d) {
                d.tanggal = $('#filter_tanggal').val();
                d.status = $('#filter_status').val();
                d.departemen = $('#filter_departemen').val();
            }
        },
        columns: [
            { data: 'no_antrian', name: 'no_antrian' },
            { data: 'NPK', name: 'NPK', orderable: false },
            { data: 'nama_karyawan', name: 'nama_karyawan', orderable: false, searchable: false },
            { data: 'departemen', name: 'departemen', orderable: false, searchable: false },
            { data: 'tanggal', name: 'tanggal_kunjungan', searchable: false },
            { data: 'keluhan', name: 'keluhan', render: function(data) {
                return data && data.length > 50 ? data.substring(0, 50) + '...' : data;
            }},
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'id', name: 'id', orderable: false, searchable: false, render: function(data, type, row) {
                var cetakUrl = '{{ route("kunjungan.cetak-kartu", ":id") }}'.replace(':id', data);
                var kartuUrl = '{{ route("report.kartu-berobat", ":npk") }}'.replace(':npk', row.NPK);
                return '<a href="' + cetakUrl + '" class="btn btn-sm btn-info mr-1" target="_blank" title="Cetak Kartu Kunjungan"><i class="fas fa-print"></i></a>' +
                       '<a href="' + kartuUrl + '" class="btn btn-sm btn-primary" target="_blank" title="Kartu Berobat"><i class="fas fa-id-card"></i></a>';
            }}
        ],
        order: [[0, 'asc']],
        pageLength: 20
    });

    // Filter change → reload table
    $('#filter_tanggal, #filter_status, #filter_departemen').on('change', function() {
        table.ajax.reload();
    });

    // Submit Daftar Form
    $('#daftarForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: '{{ route("kunjungan.store") }}',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                $('#daftarModal').modal('hide');
                $('#daftarForm')[0].reset();
                $('#NPK').val(null).trigger('change');
                table.ajax.reload();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: response.message,
                    confirmButtonText: 'OK'
                });
            },
            error: function(xhr) {
                var errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMsg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: errorMsg,
                    confirmButtonText: 'OK'
                });
            }
        });
    });
});
</script>
</html>
