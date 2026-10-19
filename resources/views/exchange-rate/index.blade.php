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
                    <h1 class="h3 mb-0 text-gray-800">Data Kurs USD ke IDR</h1>
                    <div>
                    @canRoute('exchange-rates.sync')
                    <button id="btn-sync-today" type="button" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm">
                        <i class="fas fa-sync fa-sm text-white-50"></i> Sync Kurs Terbaru (BI)
                    </button>
                    @endcanRoute

                    @canRoute('exchange-rates.sync-frankfurter')
                    <button id="btn-sync-frankfurter" type="button" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                        <i class="fas fa-calendar-alt fa-sm text-white-50"></i> Sync dari Frankfurter
                    </button>
                    @endcanRoute
                    </div>
                </div>

                {{-- ===================== SESSION ALERT ===================== --}}
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

                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Riwayat Kurs Harian
                    </h6>

                    <div class="d-flex align-items-center flex-wrap">

                        {{-- ===================== FILTER TANGGAL (browsing histori tersimpan) ===================== --}}
                        <form method="GET" id="filterForm" class="form-inline mr-2">
                            <label class="mr-1 mb-0 small">Dari</label>
                            <input type="date" name="start_date" value="{{ $startDate }}"
                                class="form-control form-control-sm mr-2">

                            <label class="mr-1 mb-0 small">Sampai</label>
                            <input type="date" name="end_date" value="{{ $endDate }}"
                                class="form-control form-control-sm mr-2">

                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </form>

                    </div>

                    </div>
                    <div class="card-body">

                        @if(empty($rates) || $rates->isEmpty())
                        <div class="alert alert-info py-2 px-3 mb-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            Belum ada data kurs untuk rentang tanggal ini. Klik <strong>Sync Kurs Terbaru (BI)</strong>
                            atau <strong>Sync dari Frankfurter</strong> untuk mengambil data.
                        </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Kurs Jual</th>
                                        <th>Kurs Beli</th>
                                        <th>Kurs Tengah</th>
                                        <th>Sumber</th>
                                        <th>Terakhir Diupdate</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rates as $rate)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td data-order="{{ $rate->rate_date->format('Y-m-d') }}">
                                            {{ $rate->rate_date->copy()->locale('id')->translatedFormat('j F Y') }}
                                        </td>
                                        <td>Rp {{ number_format($rate->kurs_jual, 2, ',', '.') }}</td>
                                        <td>Rp {{ number_format($rate->kurs_beli, 2, ',', '.') }}</td>
                                        <td>Rp {{ number_format($rate->kurs_tengah, 2, ',', '.') }}</td>
                                        <td>
                                            <span class="badge badge-secondary">{{ $rate->source }}</span>
                                        </td>
                                        <td data-order="{{ $rate->updated_at->format('Y-m-d H:i:s') }}">
                                            {{ $rate->updated_at->copy()->locale('id')->translatedFormat('j F Y H:i') }}
                                        </td>
                                        <td class="text-center">
                                            @canRoute('exchange-rates.update')
                                            <button type="button"
                                                class="btn btn-sm btn-warning btn-edit-rate"
                                                title="Edit kurs"
                                                data-id="{{ $rate->id }}"
                                                data-date-label="{{ $rate->rate_date->copy()->locale('id')->translatedFormat('j F Y') }}"
                                                data-kurs-jual="{{ $rate->kurs_jual }}"
                                                data-kurs-beli="{{ $rate->kurs_beli }}"
                                                data-kurs-tengah="{{ $rate->kurs_tengah }}">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcanRoute
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- /Content Row -->

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

{{-- ===================== MODAL EDIT KURS ===================== --}}
<div class="modal fade" id="editRateModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="form-edit-rate" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Kurs — <span id="edit-rate-date"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-rate-id">

                <div class="form-group">
                    <label for="edit-kurs-jual">Kurs Jual (Rp)</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="edit-kurs-jual" required>
                </div>
                <div class="form-group">
                    <label for="edit-kurs-beli">Kurs Beli (Rp)</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="edit-kurs-beli" required>
                </div>
                <div class="form-group mb-0">
                    <label for="edit-kurs-tengah">Kurs Tengah (Rp)</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="edit-kurs-tengah" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary" id="btn-save-rate">Simpan</button>
            </div>
        </form>
    </div>
</div>

<br>
@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function(){

    $('#dataTable').DataTable({
        pageLength: 10,
        responsive: true,
        autoWidth: false,
        columnDefs: [
            { orderable: false, targets: -1 }
        ]
    });

});
</script>

<script>

/*
=====================================================
HELPER: ambil pesan error dari response AJAX
=====================================================
*/
function extractErrorMessage(xhr, fallback){
    if(xhr.responseJSON){
        if(xhr.responseJSON.errors){
            const first = Object.values(xhr.responseJSON.errors)[0];
            if(first && first.length){
                return first[0];
            }
        }
        if(xhr.responseJSON.message){
            return xhr.responseJSON.message;
        }
    }
    return fallback;
}

/*
=====================================================
SYNC KURS TERBARU (BANK INDONESIA)
=====================================================
*/
$(document).on('click', '#btn-sync-today', function(){

    Swal.fire({
        title: 'Sync Kurs Terbaru?',
        text: 'Rilis kurs USD ke IDR terbaru yang tayang di situs Bank Indonesia akan diambil dan disimpan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Sync'
    }).then((result) => {

        if(!result.isConfirmed){
            return;
        }

        Swal.fire({
            title: 'Processing...',
            text: 'Mengambil data kurs dari Bank Indonesia',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: "{{ route('exchange-rates.sync') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function(res){

                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: res.message
                }).then(() => {
                    location.reload();
                });

            },
            error: function(xhr){

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: extractErrorMessage(xhr, 'Gagal sync kurs terbaru dari Bank Indonesia')
                });

            }
        });

    });

});

/*
=====================================================
SYNC DARI FRANKFURTER (PILIH TANGGAL MULAI)
=====================================================
*/
$(document).on('click', '#btn-sync-frankfurter', function(){

    Swal.fire({
        title: 'Sync dari Frankfurter',
        html: '<div class="text-left small">' +
              'Data kurs dibuat dari tanggal yang dipilih sampai hari ini.<ul class="pl-3 mt-2 mb-0">' +
              '<li>Data yang sudah ada <strong>tidak ditimpa</strong>.</li>' +
              '<li>Hari libur / akhir pekan memakai kurs dari tanggal berikutnya.</li>' +
              '<li>Kurs tengah = kurs ECB; jual/beli = tengah ± spread (perkiraan, bisa diedit manual).</li>' +
              '</ul></div>',
        input: 'date',
        inputLabel: 'Mulai dari tanggal',
        inputValue: "{{ now()->startOfYear()->format('Y-m-d') }}",
        inputAttributes: {
            min: '1999-01-04',
            max: "{{ now()->format('Y-m-d') }}"
        },
        showCancelButton: true,
        confirmButtonText: 'Mulai Sync',
        preConfirm: (value) => {
            if(!value){
                Swal.showValidationMessage('Tanggal mulai wajib diisi');
                return false;
            }
            return value;
        }
    }).then((result) => {

        if(!result.isConfirmed){
            return;
        }

        Swal.fire({
            title: 'Processing...',
            text: 'Mengambil data kurs dari Frankfurter',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: "{{ route('exchange-rates.sync-frankfurter') }}",
            type: 'POST',
            timeout: 300000,
            data: {
                _token: "{{ csrf_token() }}",
                start_date: result.value
            },
            success: function(res){

                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: res.message
                }).then(() => {
                    location.reload();
                });

            },
            error: function(xhr){

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: extractErrorMessage(xhr, 'Gagal sync dari Frankfurter')
                });

            }
        });

    });

});

/*
=====================================================
EDIT KURS (BELI / JUAL / TENGAH)
=====================================================
*/
$(document).on('click', '.btn-edit-rate', function(){

    const btn = $(this);

    $('#edit-rate-id').val(btn.attr('data-id'));
    $('#edit-rate-date').text(btn.attr('data-date-label'));
    $('#edit-kurs-jual').val(btn.attr('data-kurs-jual'));
    $('#edit-kurs-beli').val(btn.attr('data-kurs-beli'));
    $('#edit-kurs-tengah').val(btn.attr('data-kurs-tengah'));

    $('#editRateModal').modal('show');

});

$(document).on('submit', '#form-edit-rate', function(e){

    e.preventDefault();

    const id  = $('#edit-rate-id').val();
    const url = "{{ route('exchange-rates.update', ':id') }}".replace(':id', id);
    const btn = $('#btn-save-rate');

    btn.prop('disabled', true);

    $.ajax({
        url: url,
        type: 'POST',
        data: {
            _token: "{{ csrf_token() }}",
            _method: 'PUT',
            kurs_jual: $('#edit-kurs-jual').val(),
            kurs_beli: $('#edit-kurs-beli').val(),
            kurs_tengah: $('#edit-kurs-tengah').val()
        },
        success: function(res){

            $('#editRateModal').modal('hide');

            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: res.message
            }).then(() => {
                location.reload();
            });

        },
        error: function(xhr){

            btn.prop('disabled', false);

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: extractErrorMessage(xhr, 'Gagal menyimpan perubahan kurs')
            });

        }
    });

});

</script>
</html>