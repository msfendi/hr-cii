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
                                            <th class="text-center" style="width:130px;">Status Keluar</th>
                                            <th class="text-center">Alasan Keluar</th>
                                            <th class="text-center">Penalty</th>
                                            <th class="text-center">Agreement</th>
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
            <!-- SweetAlert2 sebelum footer agar tersedia saat jQuery ready -->
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            @include('layout.footer')
        </div>
    </div>
</body>

<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

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
                    render: function (data, type, row, meta) { return meta.row + 1; }
                },
                { data: 'NPK' },
                { data: 'NAMA' },
                { data: 'KTP', defaultContent: '-' },
                { data: 'BAGIAN' },
                {
                    data: 'TMK', className: 'text-center',
                    render: function (data) {
                        if (!data) return '-';
                        return String(data).split('T')[0];
                    }
                },
                {
                    data: 'TKK', className: 'text-center',
                    render: function (data) {
                        if (!data) return '-';
                        return String(data).split('T')[0];
                    }
                },
                {
                    data: 'KETERANGAN', className: 'text-center',
                    render: function (data) {
                        var labelMap = { SPD: 'Resign', HK: 'Habis Kontrak', MA: 'Mangkir' };
                        var colorMap = { SPD: 'warning', HK: 'info', MA: 'danger' };
                        var label = data ? (labelMap[data] || data) : '— Belum diisi —';
                        var color = data ? (colorMap[data] || 'secondary') : 'light';
                        return '<span class="badge badge-' + color + ' px-2 py-1">' + label + '</span>';
                    }
                },
                {
                    data: 'leave_reasons', className: 'text-center',
                    render: function (data) { return data ? data : '-'; }
                },
                {
                    data: 'file_penalty_letter', className: 'text-center',
                    render: function (data) {
                        if (!data) return '<span class="text-muted small">—</span>';
                        return '<a href="/storage/' + data + '" target="_blank" class="badge badge-danger px-2 py-1"><i class="fas fa-file-pdf mr-1"></i>Lihat</a>';
                    }
                },
                {
                    data: 'is_agreement_signed', className: 'text-center',
                    render: function (data) {
                        if (data == 1 || data === true) {
                            return '<span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i>Signed</span>';
                        }
                        return '<span class="badge badge-light border text-muted px-2 py-1">—</span>';
                    }
                },
                {
                    data: null, className: 'text-center', orderable: false,
                    render: function (data, type, row) {
                        return '<button class="btn btn-sm btn-outline-primary btn-edit-ket" '
                            + 'data-row=\'' + JSON.stringify(row).replace(/'/g, "&#39;") + '\'>'
                            + '<i class="fas fa-edit"></i></button>';
                    }
                }
            ]
        });

        $('#department_filter').on('change', function () { table.ajax.reload(); });

        // Helper format tanggal
        function fmtTgl(str) {
            if (!str) return '-';
            var d = String(str).split('T')[0].split(' ')[0];
            if (!d || d === '0000-00-00') return '-';
            var parts = d.split('-');
            var bln = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
            return parts[2] + ' ' + (bln[parseInt(parts[1], 10) - 1] || parts[1]) + ' ' + parts[0];
        }

        // Toggle resign section di dalam modal
        function toggleModalResignSection(status) {
            if (status === 'SPD') {
                $('#modalResignSection').removeClass('d-none').hide().slideDown(200);
            } else {
                $('#modalResignSection').slideUp(150, function () { $(this).addClass('d-none'); });
                $('#modal_penalty_letter').val('');
                $('#modalPenaltyLabel').text('Pilih file penalty letter...');
                $('#modal_agreement_signed').prop('checked', false);
            }
        }

        $('#modal_keterangan').on('change', function () {
            toggleModalResignSection($(this).val());
        });

        $('#modal_penalty_letter').on('change', function () {
            var fn = this.files && this.files.length ? this.files[0].name : 'Pilih file penalty letter...';
            $('#modalPenaltyLabel').text(fn);
        });

        // Klik tombol edit → isi modal
        $('body').on('click', '.btn-edit-ket', function () {
            var row = $(this).data('row');

            $('#detail_npk').text(row.NPK || '-');
            $('#detail_nama').text(row.NAMA || '-');
            $('#detail_ktp').text(row.KTP || '-');
            $('#detail_bagian').text(row.BAGIAN || '-');
            $('#detail_tmk').text(fmtTgl(row.TMK));

            var tkkVal = row.TKK ? String(row.TKK).split('T')[0].split(' ')[0] : '';
            $('#modal_tkk').val(tkkVal);

            var current = row.KETERANGAN || '';
            $('#modal_keterangan').val(current);
            $('#modal_alasan_keluar').val(row.leave_reasons || '');

            // Existing penalty letter link
            if (row.file_penalty_letter) {
                $('#existingPenaltyLink').attr('href', '/storage/' + row.file_penalty_letter)
                    .removeClass('d-none');
            } else {
                $('#existingPenaltyLink').addClass('d-none');
            }

            // Existing agreement
            $('#modal_agreement_signed').prop('checked', row.is_agreement_signed == 1);

            // Reset file input & label
            $('#modal_penalty_letter').val('');
            $('#modalPenaltyLabel').text('Ganti / Upload file baru...');

            // Show/hide resign section sesuai current status
            if (current === 'SPD') {
                $('#modalResignSection').removeClass('d-none');
            } else {
                $('#modalResignSection').addClass('d-none');
            }

            $('#btnSaveKeterangan').data('npk', row.NPK);
            $('#editKeteranganModal').modal('show');
        });

        // Simpan dari modal — pakai FormData untuk support file upload
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

            var fd = new FormData();
            fd.append('_token', '{{ csrf_token() }}');
            fd.append('tkk', tkk);
            fd.append('keterangan', val);
            fd.append('leave_reasons', alasan);

            if (val === 'SPD') {
                var penaltyInput = document.getElementById('modal_penalty_letter');
                if (penaltyInput && penaltyInput.files.length) {
                    fd.append('file_penalty_letter', penaltyInput.files[0]);
                }
                fd.append('is_agreement_signed', $('#modal_agreement_signed').is(':checked') ? '1' : '');
            }

            $.ajax({
                url: '/biodata-keluar/update/' + encodeURIComponent(npk),
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
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

<!-- Modal Detail & Update Keterangan (xl — lebih besar) -->
<div class="modal fade" id="editKeteranganModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content shadow-lg" style="border-radius: 12px; overflow: hidden;">

            {{-- Header --}}
            <div class="modal-header border-0 pt-4 pb-2 px-4" style="background: linear-gradient(135deg,#c0392b,#e74a3b);">
                <div>
                    <h5 class="modal-title font-weight-bold text-white mb-0">
                        <i class="fas fa-user-times mr-2"></i>Detail & Update Karyawan Keluar
                    </h5>
                    <p class="text-white-50 small mb-0 mt-1">Update status, tanggal keluar, dan dokumen resign</p>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity:.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            {{-- Body --}}
            <div class="modal-body px-4 py-3">

                {{-- Info Karyawan --}}
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="p-3 rounded" style="background:#f8f9fc; border-left:4px solid #e74a3b;">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <div class="small text-muted font-weight-bold">NPK</div>
                                    <div class="font-weight-bold text-dark" id="detail_npk">-</div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="small text-muted font-weight-bold">Nama Karyawan</div>
                                    <div class="font-weight-bold text-dark" id="detail_nama">-</div>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <div class="small text-muted font-weight-bold">Bagian</div>
                                    <div class="font-weight-bold text-dark" id="detail_bagian">-</div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="small text-muted font-weight-bold">TMK</div>
                                    <div class="font-weight-bold text-dark" id="detail_tmk">-</div>
                                </div>
                                <div class="col-md-3 mb-0">
                                    <div class="small text-muted font-weight-bold">KTP</div>
                                    <div class="text-dark small" id="detail_ktp">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                {{-- Form Edit --}}
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="small font-weight-bold text-danger">TKK (Tanggal Keluar) <span class="text-danger">*</span></label>
                        <input type="date" id="modal_tkk" class="form-control border-danger font-weight-bold">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="small font-weight-bold text-danger">Status Keluar <span class="text-danger">*</span></label>
                        <select id="modal_keterangan" class="form-control border-danger text-danger font-weight-bold">
                            <option value="" disabled selected>— Pilih Status Keluar —</option>
                            <option value="SPD">SPD — Resign</option>
                            <option value="HK">HK — Habis Kontrak</option>
                            <option value="MA">MA — Mangkir</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="small font-weight-bold">Alasan Keluar</label>
                        <textarea id="modal_alasan_keluar" class="form-control" rows="3"
                            placeholder="Keterangan tambahan..."></textarea>
                    </div>
                </div>

                {{-- Resign Section (conditional) --}}
                <div id="modalResignSection" class="d-none">
                    <div class="alert border-0 d-flex align-items-start mb-3"
                        style="background:#fff0f0; border-left:4px solid #e74a3b !important; border-radius:8px;">
                        <i class="fas fa-file-contract text-danger mt-1 mr-2" style="font-size:16px;"></i>
                        <div>
                            <div class="font-weight-bold text-danger" style="font-size:12.5px;">Dokumen Resign</div>
                            <div class="text-muted" style="font-size:12px;">Upload penalty letter dan konfirmasi
                                penandatanganan agreement hanya untuk status <strong>Resign</strong>.</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="small font-weight-bold">
                                <i class="fas fa-file-pdf text-danger mr-1"></i>
                                Penalty Letter <span class="text-muted font-weight-normal">(opsional)</span>
                            </label>
                            {{-- Existing file link --}}
                            <div class="mb-1">
                                <a id="existingPenaltyLink" href="#" target="_blank"
                                   class="badge badge-danger px-2 py-1 d-none">
                                    <i class="fas fa-file-pdf mr-1"></i>File saat ini — Lihat
                                </a>
                            </div>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="modal_penalty_letter"
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <label class="custom-file-label" for="modal_penalty_letter"
                                    id="modalPenaltyLabel">Ganti / Upload file baru...</label>
                            </div>
                            <small class="text-muted">Format: PDF, Word, atau gambar. Maks. 5 MB.</small>
                        </div>
                        <div class="col-md-5 mb-3 d-flex align-items-center">
                            <div class="w-100">
                                <label class="small font-weight-bold d-block">
                                    <i class="fas fa-handshake mr-1 text-secondary"></i>
                                    Penandatanganan Agreement
                                </label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="modal_agreement_signed">
                                    <label class="custom-control-label" for="modal_agreement_signed">
                                        Karyawan telah menandatangani <strong>resignation agreement</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Footer --}}
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
