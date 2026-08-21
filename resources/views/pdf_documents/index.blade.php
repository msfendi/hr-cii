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
                    <h1 class="h3 mb-0 text-gray-800">PDF Booking Confirmation Extractor</h1>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#modalUploadPdf" id="btnUploadPdf">
                            <i class="fas fa-upload fa-sm text-white-50"></i> Upload PDF
                        </button>
                    </div>
                </div>

                <div class="alert alert-info">
                    Upload PDF booking confirmation, sistem otomatis menarik field-field penting
                    (Vessel/Voy, ETD, ETA, Shipper, Consignee, dll). Klik <strong>Lihat Detail</strong>
                    pada tiap baris untuk mencari field tertentu dari PDF tersebut.
                </div>

                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Daftar Dokumen PDF</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="table-pdf-documents" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Booking Number</th>
                                        <th>Nama File</th>
                                        <th>Diupload</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
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
    <!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->

<!-- Modal Upload -->
<div class="modal fade" id="modalUploadPdf" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="formUploadPdf" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Upload PDF Booking Confirmation</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>File PDF</label>
                        <input type="file" name="file" id="input_pdf_file" class="form-control-file" accept="application/pdf" required>
                        <small class="form-text text-muted">
                            Maks. 20MB, format .pdf.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitUploadPdf">
                        <i class="fas fa-upload"></i> Upload &amp; Parse
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail / Search Field -->
<div class="modal fade" id="modalPdfDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPdfDetailTitle">Detail Dokumen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Cari Key</label>
                    <input type="text" id="searchPdfKey" class="form-control" placeholder="Ketik key/label, contoh: Vessel/Voy, ETD, Consignee..." autocomplete="off">
                </div>

                <div id="pdfQuickResult" class="alert alert-light border" style="display:none;">
                    <div class="text-muted text-uppercase" style="font-size:11px;" id="pdfQuickResultLabel"></div>
                    <div class="font-weight-bold" style="font-size:18px;" id="pdfQuickResultValue"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="table-pdf-fields">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Key</th>
                                <th>Label (di PDF)</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody id="pdfFieldsBody">
                            <tr><td colspan="4" class="text-center text-muted">Memuat...</td></tr>
                        </tbody>
                    </table>
                </div>
                <p id="pdfNoMatch" class="text-muted" style="display:none;">Tidak ada field yang cocok dengan pencarian.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function () {

    let table = $('#table-pdf-documents').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('pdf-documents.data') }}",
        order: [[3, 'desc']], // urutkan default berdasarkan kolom "Diupload", terbaru dulu
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'booking_number', name: 'booking_number' },
            { data: 'original_filename', name: 'original_filename' },
            { data: 'created_at_fmt', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });

    // ===== Upload =====
    $('#formUploadPdf').on('submit', function (e) {
        e.preventDefault();

        let formData = new FormData(this);
        let $btn = $('#btnSubmitUploadPdf');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');

        $.ajax({
            url: "{{ route('pdf-documents.upload') }}",
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                $('#modalUploadPdf').modal('hide');
                Swal.fire('Berhasil', res.message, 'success');
                table.ajax.reload();
                $('#formUploadPdf')[0].reset();
            },
            error: function (xhr) {
                let msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Terjadi kesalahan, silakan coba lagi.';
                Swal.fire('Gagal', msg, 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-upload"></i> Upload &amp; Parse');
            }
        });
    });

    // ===== Lihat Detail (modal search field) =====
    $(document).on('click', '.btn-view-pdf-detail', function () {
        let id = $(this).data('id');

        $('#pdfFieldsBody').html('<tr><td colspan="4" class="text-center text-muted">Memuat...</td></tr>');
        $('#searchPdfKey').val('');
        $('#pdfQuickResult').hide();
        $('#pdfNoMatch').hide();
        $('#modalPdfDetail').modal('show');

        $.get("{{ url('pdf-documents') }}/" + id + "/fields", function (res) {
            if (!res.success) return;

            $('#modalPdfDetailTitle').text(res.document.booking_number || res.document.original_filename);
            renderPdfFields(res.fields);
        });
    });

    function renderPdfFields(fields) {
        if (fields.length === 0) {
            $('#pdfFieldsBody').html('<tr><td colspan="4" class="text-center text-muted">Tidak ada field yang berhasil diparse dari PDF ini.</td></tr>');
            return;
        }

        let rows = '';
        fields.forEach(function (f) {
            rows += `<tr data-key="${f.field_key.toLowerCase()}" data-label="${f.field_label.toLowerCase()}" data-value="${(f.field_value || '').toLowerCase()}">
                <td><span class="badge badge-info">${f.category}</span></td>
                <td><code>${f.field_key}</code></td>
                <td>${f.field_label}</td>
                <td>${f.field_value ?? ''}</td>
            </tr>`;
        });
        $('#pdfFieldsBody').html(rows);
    }

    $('#searchPdfKey').on('input', function () {
        let q = $(this).val().trim().toLowerCase();
        let $rows = $('#pdfFieldsBody tr[data-key]');
        let visibleCount = 0;
        let firstMatch = null;

        $rows.each(function () {
            let $row = $(this);
            let matches = q === ''
                || $row.data('key').toString().includes(q)
                || $row.data('label').toString().includes(q)
                || $row.data('value').toString().includes(q);

            $row.toggle(matches);

            if (matches) {
                visibleCount++;
                if (!firstMatch) firstMatch = $row;
            }
        });

        $('#pdfNoMatch').toggle(visibleCount === 0 && q !== '');

        if (q !== '' && firstMatch) {
            $('#pdfQuickResult').show();
            $('#pdfQuickResultLabel').text(firstMatch.find('td').eq(2).text());
            $('#pdfQuickResultValue').text(firstMatch.find('td').eq(3).text());
        } else {
            $('#pdfQuickResult').hide();
        }
    });

    // ===== Hapus =====
    $(document).on('click', '.btn-delete-pdf-document', function () {
        let id = $(this).data('id');

        Swal.fire({
            title: 'Yakin hapus dokumen ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ url('pdf-documents') }}/" + id,
                    method: 'POST',
                    data: { _method: 'DELETE', _token: '{{ csrf_token() }}' },
                    success: function (res) {
                        Swal.fire('Terhapus', res.message, 'success');
                        table.ajax.reload();
                    },
                    error: function (xhr) {
                        let msg = xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'Terjadi kesalahan, silakan coba lagi.';
                        Swal.fire('Gagal', msg, 'error');
                    }
                });
            }
        });
    });

});
</script>
</html>