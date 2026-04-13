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

                    {{-- Page Heading --}}
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Detail Saldo Cuti</h1>
                        <a href="{{ route('leave-balances.index') }}" class="btn btn-secondary btn-sm shadow-sm">
                            <i class="fas fa-arrow-left fa-sm"></i> Kembali
                        </a>
                    </div>

                    {{-- Info Card --}}
                    <div class="card shadow mb-3">
                        <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Informasi Karyawan</h6>
                            <form method="GET" action="{{ route('leave-balances.show', $employee->NPK) }}"
                                class="d-flex align-items-center mb-0">
                                <label class="small font-weight-bold text-muted mr-2 mb-0">Periode Tahun :</label>
                                <input type="text" name="year" id="filter_year"
                                    class="form-control form-control-sm mr-2 yearpicker"
                                    style="width: 110px; background-color: white;" value="{{ $year }}" readonly>
                            </form>
                        </div>
                        <div class="card-body py-3">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <span class="small font-weight-bold text-muted d-block">NPK</span>
                                    <strong>{{ $employee->NPK }}</strong>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <span class="small font-weight-bold text-muted d-block">Nama Karyawan</span>
                                    <strong class="text-primary">{{ $employee->NAMA_KARYAWAN }}</strong>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <span class="small font-weight-bold text-muted d-block">Department</span>
                                    <strong>{{ $employee->DEPARTEMENT }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Balance Table Card --}}

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-success">
                                        Saldo Cuti Tahun <span class="badge badge-success ml-1">{{ $year }}</span>
                                    </h6>
                                    <button class="btn btn-primary btn-sm shadow-sm" id="btn-tambah-cuti">
                                        <i class="fas fa-plus fa-sm"></i> Tambah / Set Jatah Cuti
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                                            <thead class="bg-primary text-white">
                                                <tr>
                                                    <th class="text-center">Jenis Cuti</th>
                                                    <th class="text-center">Jatah (Hari)</th>
                                                    <th class="text-center">Terpakai</th>
                                                    <th class="text-center">Sisa</th>
                                                    <th class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($details as $item)
                                                    <tr>
                                                        <td class="font-weight-bold">
                                                            {{ $item->type_name }}
                                                        </td>
                                                        <td class="text-center">
                                                            {{ $item->default_days }}
                                                        </td>
                                                        <td
                                                            class="text-center {{ $item->used_days > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}">
                                                            {{ $item->used_days }}
                                                        </td>
                                                        <td class="text-center font-weight-bold">{{ $item->remained_days }}
                                                        </td>
                                                        <td class="text-center">
                                                            @if($item->balance_id)
                                                                <button class="btn btn-warning btn-sm btn-edit-balance"
                                                                    data-id="{{ $item->balance_id }}"
                                                                    data-type="{{ $item->type_name }}"
                                                                    data-typeid="{{ $item->leave_type_id }}"
                                                                    data-remained="{{ $item->remained_days }}"
                                                                    data-used="{{ $item->used_days }}">
                                                                    <i class="fas fa-edit"></i> Edit
                                                                </button>
                                                                <button class="btn btn-danger btn-sm btn-delete-balance"
                                                                    data-id="{{ $item->balance_id }}"
                                                                    data-type="{{ $item->type_name }}">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            @else
                                                                <span class="badge badge-secondary">Belum di-set</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center py-4">
                                                            <i class="fas fa-inbox fa-2x text-muted d-block mb-2"></i>
                                                            <span class="text-muted">Belum ada data jatah cuti untuk tahun
                                                                {{ $year }}.</span>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                                    <h6 class="m-0 my-2 font-weight-bold text-success">Riwayat Pengajuan Cuti</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                        <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                                            <thead class="bg-primary text-white"
                                                style="position: sticky; top: 0; z-index: 1;">
                                                <tr>
                                                    <th>Jenis Cuti</th>
                                                    <th>Tgl Cuti</th>
                                                    <th class="text-center">Jml</th>
                                                    <th class="text-center">Status & Komentar</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($leaveHistory as $hist)
                                                    <tr>
                                                        <td class="font-weight-bold">{{ $hist->leave_type }}<br><small
                                                                class="text-muted">{{ $hist->reason }}</small></td>
                                                        <td>{{ $hist->start_date }}<br><small class="text-muted">s/d
                                                                {{ $hist->end_date }}</small></td>
                                                        <td class="text-center">{{ $hist->total_days }}</td>
                                                        <td class="text-center align-middle">
                                                            <span class="badge badge-{{ $hist->badge }}">{{ $hist->status }}</span>
                                                            @if($hist->comment)
                                                                <br><small class="text-muted">{{ $hist->comment }}</small>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="text-center py-4 text-muted">
                                                            <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                                                            Tidak ada riwayat pengajuan cuti di tahun {{ $year }}.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
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

    {{-- ===================== MODAL TAMBAH / EDIT BALANCE ===================== --}}
    <div class="modal fade" id="modalBalance" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg" style="border-radius: 10px; overflow: hidden;">
                <div class="modal-header pb-0 pt-4 px-4 bg-white border-0">
                    <h5 class="modal-title font-weight-bold ml-2" id="modalBalanceTitle">Set Jatah Cuti</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body px-4 pb-0">
                    <input type="hidden" id="balance_id">
                    <input type="hidden" id="balance_npk" value="{{ $employee->NPK }}">
                    <input type="hidden" id="balance_year" value="{{ $year }}">

                    <div class="form-group" id="group_leave_type">
                        <label class="small font-weight-bold text-muted ml-1">Jenis Cuti <span
                                class="text-danger">*</span></label>
                        <select id="balance_leave_type_id" class="form-control">
                            <option value="">-- Pilih Jenis Cuti --</option>
                            @foreach($leaveTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="show_type_label" class="form-group d-none">
                        <label class="small font-weight-bold text-muted ml-1">Jenis Cuti</label>
                        <input type="text" id="balance_type_label" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold text-muted ml-1">Jatah Hari (Remained Days) <span
                                class="text-danger">*</span></label>
                        <input type="number" id="balance_remained_days" class="form-control" min="0"
                            placeholder="Contoh: 12">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold text-muted ml-1">Hari Terpakai (Used Days) <span
                                class="text-danger">*</span></label>
                        <input type="number" id="balance_used_days" class="form-control" min="0" placeholder="0">
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 pr-4 bg-white">
                    <button type="button" class="btn btn-secondary px-4 font-weight-bold"
                        data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary px-4 font-weight-bold shadow-sm" id="btn-save-balance">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Page level plugins -->
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css"
        rel="stylesheet" />
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js"></script>

    <script>
        $(document).ready(function () {
            // ── INIT DATEPICKER ────────────────────────────────────────────────────────
            $('.yearpicker').datepicker({
                format: "yyyy",
                viewMode: "years",
                minViewMode: "years",
                autoclose: true
            }).on('changeDate', function () {
                if ($(this).attr('id') === 'filter_year') {
                    $(this).closest('form').submit();
                }
            });

            // ── BUKA MODAL TAMBAH ──────────────────────────────────────────────────────
            $('#btn-tambah-cuti').on('click', function () {
                $('#modalBalanceTitle').text('Tambah / Set Jatah Cuti');
                $('#balance_id').val('');
                // tampilkan dropdown type, sembunyikan label readonly
                $('#group_leave_type').removeClass('d-none');
                $('#show_type_label').addClass('d-none');
                $('#balance_leave_type_id').val('');
                $('#balance_remained_days').val('');
                $('#balance_used_days').val('0');
                $('#modalBalance').modal('show');
            });

            // ── BUKA MODAL EDIT ───────────────────────────────────────────────────────
            $('body').on('click', '.btn-edit-balance', function () {
                var id = $(this).data('id');
                var type = $(this).data('type');
                var typeId = $(this).data('typeid');
                var remained = $(this).data('remained');
                var used = $(this).data('used');

                $('#modalBalanceTitle').text('Edit Jatah Cuti');
                $('#balance_id').val(id);
                // sembunyikan dropdown, tampilkan label readonly
                $('#group_leave_type').addClass('d-none');
                $('#show_type_label').removeClass('d-none');
                $('#balance_type_label').val(type);
                $('#balance_leave_type_id').val(typeId);
                $('#balance_remained_days').val(remained);
                $('#balance_used_days').val(used);
                $('#modalBalance').modal('show');
            });

            // ── SIMPAN (Create / Update) ───────────────────────────────────────────────
            $('#btn-save-balance').on('click', function () {
                var id = $('#balance_id').val();
                var npk = $('#balance_npk').val();
                var year = $('#balance_year').val();
                var typeId = $('#balance_leave_type_id').val();
                var remained = $('#balance_remained_days').val();
                var used = $('#balance_used_days').val();

                if (remained === '' || remained === null) {
                    Swal.fire('Perhatian', 'Jatah hari tidak boleh kosong.', 'warning');
                    return;
                }

                if (id) {
                    // ── UPDATE ──
                    $.ajax({
                        url: '{{ route("leave-balances.update", ":id") }}'.replace(':id', id),
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            remained_days: remained,
                            used_days: used
                        },
                        success: function (res) {
                            if (res.status === 'success') {
                                Swal.fire('Berhasil!', 'Data jatah cuti berhasil diperbarui.', 'success')
                                    .then(function () { location.reload(); });
                            }
                        },
                        error: function () {
                            Swal.fire('Error', 'Gagal memperbarui data.', 'error');
                        }
                    });
                } else {
                    // ── STORE ──
                    if (!typeId) {
                        Swal.fire('Perhatian', 'Pilih jenis cuti terlebih dahulu.', 'warning');
                        return;
                    }
                    $.ajax({
                        url: '{{ route("leave-balances.store") }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            NPK: npk,
                            leave_type_id: typeId,
                            year: year,
                            remained_days: remained,
                            used_days: used
                        },
                        success: function (res) {
                            if (res.status === 'success') {
                                Swal.fire('Berhasil!', 'Jatah cuti berhasil disimpan.', 'success')
                                    .then(function () { location.reload(); });
                            }
                        },
                        error: function (xhr) {
                            var msg = 'Gagal menyimpan data.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                            Swal.fire('Error', msg, 'error');
                        }
                    });
                }
            });

            // ── DELETE ────────────────────────────────────────────────────────────────
            $('body').on('click', '.btn-delete-balance', function () {
                var id = $(this).data('id');
                var type = $(this).data('type');

                Swal.fire({
                    title: 'Hapus Jatah Cuti?',
                    html: 'Data jatah cuti <b>"' + type + '"</b> akan dihapus permanen.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e3342f',
                    cancelButtonText: 'Batal',
                    confirmButtonText: 'Ya, Hapus!'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route("leave-balances.destroy", ":id") }}'.replace(':id', id),
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                _method: 'DELETE'
                            },
                            success: function (res) {
                                if (res.status === 'success') {
                                    Swal.fire('Dihapus!', 'Data berhasil dihapus.', 'success')
                                        .then(function () { location.reload(); });
                                }
                            },
                            error: function () {
                                Swal.fire('Error', 'Gagal menghapus data.', 'error');
                            }
                        });
                    }
                });
            });

        });
    </script>
</body>

</html>