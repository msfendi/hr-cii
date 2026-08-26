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
                        <h1 class="h3 mb-0 text-gray-800">Biodata Keluar</h1>
                    </div>

                    <div class="card shadow mb-2">
                        <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-danger">
                                <i class="fas fa-user-times mr-1"></i> Karyawan Keluar
                            </h6>
                            <div>
                                <select id="department_filter"
                                    class="form-control form-control-sm d-inline-block shadow-sm" style="width: 220px;">
                                    <option value="">— Semua Department —</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->ID_DEPT }}">{{ $dept->DEPARTEMENT }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-striped" id="dataTable" width="100%"
                                    cellspacing="0">
                                    <thead class="bg-danger text-white">
                                        <tr>
                                            <th class="text-center" style="width:40px;">No</th>
                                            <th>NPK</th>
                                            <th>Nama Karyawan</th>
                                            <th>KTP</th>
                                            <th>Bagian</th>
                                            <th>TMK</th>
                                            <th class="text-center">TKK</th>
                                            <th class="text-center" style="width:200px;">Keterangan</th>
                                            <th class="text-center">Alasan Keluar</th>
                                            <th class="text-center" style="width:80px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            @include('layout.footer')
        </div>
    </div>
</body>

<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function () {
        var table = $('#dataTable').DataTable({
            ajax: {
                url: '{{ route("biodata_keluar.get-data") }}',
                data: function (d) {
                    d.department_id = $('#department_filter').val();
                },
                dataSrc: 'data'
            },
            pageLength: 25,
            order: [[4, 'desc']],
            columns: [
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'NPK' },
                { data: 'NAMA' },
                { data: 'KTP', defaultContent: '-' },
                { data: 'BAGIAN' },
                {
                    data: 'TMK',
                    className: 'text-center',
                    render: function (data) {
                        if (!data) return '-';
                        var d = String(data).split('T')[0];
                        return d;
                    }
                },
                {
                    data: 'TKK',
                    className: 'text-center',
                    render: function (data) {
                        if (!data) return '-';
                        var d = String(data).split('T')[0];
                        return d;
                    }
                },
                {
                    data: 'KETERANGAN',
                    className: 'text-center',
                    render: function (data, type, row) {
                        var labelMap = { SPD: 'Resign', HK: 'Habis Kontrak', MA: 'Mangkir' };
                        var colorMap = { SPD: 'warning', HK: 'info', MA: 'danger' };
                        var label = data ? (labelMap[data] || data) : '— Belum diisi —';
                        var color = data ? (colorMap[data] || 'secondary') : 'light';
                        return '<span class="badge badge-' + color + ' px-2 py-1">' + label + '</span>';
                    }
                },
                {
                    data: 'leave_reasons',
                    className: 'text-center',
                    render: function (data) {
                        return data ? data : '-';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data, type, row) {
                        return '<button class="btn btn-sm btn-outline-primary btn-edit-ket" '
                            + 'data-row=\'' + JSON.stringify(row).replace(/'/g, "&#39;") + '\'>'
                            + '<i class="fas fa-edit"></i></button>';
                    }
                }
            ]
        });

        $('#department_filter').on('change', function () {
            table.ajax.reload();
        });

        // Helper format tanggal
        function fmtTgl(str) {
            if (!str) return '-';
            var d = String(str).split('T')[0].split(' ')[0];
            if (!d || d === '0000-00-00') return '-';
            var parts = d.split('-');
            var bln = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
            return parts[2] + ' ' + (bln[parseInt(parts[1], 10) - 1] || parts[1]) + ' ' + parts[0];
        }

        // Klik tombol edit → buka modal dengan detail
        $('body').on('click', '.btn-edit-ket', function () {
            var row = $(this).data('row');

            $('#detail_npk').text(row.NPK || '-');
            $('#detail_nama').text(row.NAMA || '-');
            $('#detail_ktp').text(row.KTP || '-');
            $('#detail_bagian').text(row.BAGIAN || '-');
            $('#detail_tmk').text(fmtTgl(row.TMK));

            // Set input TKK
            var tkkVal = '';
            if (row.TKK) {
                tkkVal = String(row.TKK).split('T')[0].split(' ')[0];
            }
            $('#modal_tkk').val(tkkVal);

            // Set select value
            var current = row.KETERANGAN || '';
            $('#modal_keterangan').val(current);

            var currentAlasan = row.leave_reasons || '';
            $('#modal_alasan_keluar').val(currentAlasan);

            // Store NPK for save
            $('#btnSaveKeterangan').data('npk', row.NPK);

            $('#editKeteranganModal').modal('show');
        });

        // Simpan keterangan & TKK dari modal
        $('#btnSaveKeterangan').on('click', function () {
            var npk = $(this).data('npk');
            var val = $('#modal_keterangan').val();
            var tkk = $('#modal_tkk').val();
            var alasan = $('#modal_alasan_keluar').val();
            var btn = $(this);

            if (!tkk) {
                Swal.fire('Peringatan', 'Harap isi Tanggal Keluar (TKK).', 'warning');
                return;
            }

            if (!val) {
                Swal.fire('Peringatan', 'Harap pilih Status Keluar.', 'warning');
                return;
            }

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan...');

            $.ajax({
                url: '/biodata-keluar/update/' + npk,
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    tkk: tkk,
                    keterangan: val,
                    leave_reasons: alasan
                },
                success: function (res) {
                    if (res.status === 'success') {
                        $('#editKeteranganModal').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function () {
                            table.ajax.reload(null, false);
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function (xhr) {
                    var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan.';
                    Swal.fire('Error', msg, 'error');
                },
                complete: function () {
                    btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Simpan');
                }
            });
        });
    });
</script>

<!-- Modal Detail & Update Keterangan -->
<div class="modal fade" id="editKeteranganModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg" style="border-radius: 10px; overflow: hidden;">
            <div class="modal-header bg-white border-0 pt-4 pb-2 px-4">
                <div>
                    <h5 class="modal-title font-weight-bold text-danger mb-0">
                        <i class="fas fa-user-times mr-2"></i>Detail Karyawan Keluar
                    </h5>
                    <p class="text-muted small mb-0 mt-1">Update status & tanggal keluar karyawan</p>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body px-4 pb-3">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="small font-weight-bold text-muted ml-1">NPK</label>
                        <div class="form-control bg-light px-3" id="detail_npk" style="min-height:38px;">-</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="small font-weight-bold text-muted ml-1">Nama Karyawan</label>
                        <div class="form-control bg-light px-3" id="detail_nama" style="min-height:38px;">-</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="small font-weight-bold text-muted ml-1">KTP</label>
                        <div class="form-control bg-light px-3" id="detail_ktp" style="min-height:38px;">-</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="small font-weight-bold text-muted ml-1">Bagian</label>
                        <div class="form-control bg-light px-3" id="detail_bagian" style="min-height:38px;">-</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="small font-weight-bold text-muted ml-1">TMK</label>
                        <div class="form-control bg-light px-3" id="detail_tmk" style="min-height:38px;">-</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="small font-weight-bold text-danger ml-1">TKK (Tanggal Keluar) <span class="text-danger">*</span></label>
                        <input type="date" id="modal_tkk" class="form-control px-3 border-danger font-weight-bold">
                    </div>
                </div>
                <hr class="my-2">
                <div class="row">
                    <div class="col-md-12 mb-2">
                        <label class="small font-weight-bold text-danger ml-1">Status Keluar
                            <span class="text-danger">*</span></label>
                        <select id="modal_keterangan"
                            class="form-control px-3 border-danger text-danger font-weight-bold">
                            <option value="" disabled selected>— Pilih Status Keluar —</option>
                            <option value="SPD">SPD — Resign</option>
                            <option value="HK">HK — Habis Kontrak</option>
                            <option value="MA">MA — Mangkir</option>
                        </select>
                    </div>
                    <div class="col-md-12 mb-2">
                        <label class="small font-weight-bold text-danger ml-1">Alasan Keluar</label>
                        <textarea id="modal_alasan_keluar" class="form-control px-3 border-danger text-danger" rows="3" placeholder="Masukkan alasan keluar..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-white">
                <button type="button" class="btn btn-secondary px-4 font-weight-bold"
                    data-dismiss="modal">Batal</button>
                <button type="button" id="btnSaveKeterangan" class="btn btn-danger px-4 font-weight-bold shadow-sm">
                    <i class="fas fa-save mr-1"></i>Simpan
                </button>
            </div>
        </div>
    </div>
</div>

</html>