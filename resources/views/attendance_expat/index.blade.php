<!DOCTYPE html>
<html lang="en">

@include('layout.header')

<head>
    <style>
        #attendanceTable th,
        #attendanceTable td {
            text-align: center;
            vertical-align: middle;
            font-size: 12.5px;
            white-space: nowrap;
        }

        #attendanceTable td.text-left {
            text-align: left;
            white-space: normal;
        }

        /* kolom tanggal libur / weekend */
        #attendanceTable th.col-off,
        #attendanceTable td.col-off {
            background-color: #fecaca !important;
        }

        /* sel jam masuk/pulang: 2 baris dalam 1 sel, biar gampang dibaca */
        .time-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            font-variant-numeric: tabular-nums;
        }

        .time-cell .time-in,
        .time-cell .time-out {
            font-weight: 600;
        }

        .time-cell .time-in { color: #065f46; }
        .time-cell .time-out {
            color: #991b1b;
            border-top: 1px dashed #e5e7eb;
            padding-top: 2px;
        }

        .time-cell .time-na { color: #9ca3af; font-weight: 400; }

        .time-cell i {
            width: 11px;
            display: inline-block;
            text-align: center;
            margin-right: 2px;
        }

        .attendance-legend {
            font-size: 12.5px;
        }

        .attendance-legend .legend-item {
            margin-right: 16px;
            white-space: nowrap;
        }

        .attendance-legend .legend-swatch {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 2px;
            vertical-align: middle;
            margin-right: 4px;
            background-color: #fecaca;
        }

        #exportEmpTable thead th {
            position: sticky;
            top: 0;
            z-index: 1;
        }
    </style>
</head>

<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')
                <div class="container-fluid">

                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                                <i class="fas fa-passport mr-2 text-primary"></i>Attendance Report - Expat
                            </h1>
                            <p class="mb-0 text-muted small">Rekap jam masuk & jam pulang karyawan expat per bulan.</p>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                <div class="form-inline">
                                    <label class="mr-2 font-weight-bold">Periode</label>
                                    <input type="month" id="period" class="form-control form-control-sm mr-2"
                                        value="{{ now()->format('Y-m') }}">
                                    <button type="button" id="btnLoad" class="btn btn-primary btn-sm mr-2"><i
                                            class="fas fa-search mr-1"></i>Tampilkan</button>
                                    <button type="button" id="btnExport" class="btn btn-success btn-sm"><i
                                            class="fas fa-file-excel mr-1"></i>Export Excel</button>
                                </div>
                                <input type="text" id="customSearch" class="form-control form-control-sm mt-2 mt-md-0"
                                    style="width:260px" placeholder="Cari nama / NPK / bagian...">
                            </div>

                            <div class="attendance-legend mb-2 d-flex flex-wrap align-items-center">
                                <span class="legend-item"><i class="fas fa-arrow-right text-success"></i> Masuk</span>
                                <span class="legend-item"><i class="fas fa-arrow-left text-danger"></i> Pulang</span>
                                <span class="legend-item"><span class="legend-swatch"></span> Libur / Weekend</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="attendanceTable" width="100%">
                                    <thead id="attendanceThead" class="thead-light"></thead>
                                    <tbody id="attendanceTbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            @include('layout.footer')
        </div>
    </div>

    {{-- ================= Modal Export ================= --}}
    <div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">
                        <i class="fas fa-file-excel mr-1 text-success"></i>Export Attendance Report
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <div class="form-group">
                        <label class="font-weight-bold d-block">Rentang Periode</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="exportPeriodMode" id="modeMonth" value="month" checked>
                            <label class="form-check-label" for="modeMonth">Per Bulan</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="exportPeriodMode" id="modeRange" value="range">
                            <label class="form-check-label" for="modeRange">Rentang Tanggal Custom</label>
                        </div>
                    </div>

                    <div class="form-group" id="exportMonthGroup">
                        <input type="month" id="exportPeriod" class="form-control form-control-sm" style="max-width:200px">
                    </div>

                    <div class="form-row d-none" id="exportRangeGroup">
                        <div class="form-group col-md-6">
                            <label class="small mb-1">Dari Tanggal</label>
                            <input type="date" id="exportStartDate" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="small mb-1">Sampai Tanggal</label>
                            <input type="date" id="exportEndDate" class="form-control form-control-sm">
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="font-weight-bold mb-0">Pilih Karyawan</label>
                        <input type="text" id="exportEmpSearch" class="form-control form-control-sm" style="width:220px" placeholder="Cari nama / NPK / bagian...">
                    </div>

                    <div class="table-responsive" style="max-height:320px; overflow-y:auto; border:1px solid #e3e6f0;">
                        <table class="table table-sm table-hover mb-0" id="exportEmpTable">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:40px;"><input type="checkbox" id="exportSelectAll" checked></th>
                                    <th>NPK</th>
                                    <th>Nama</th>
                                    <th>Bagian</th>
                                </tr>
                            </thead>
                            <tbody id="exportEmpTbody"></tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-2 mb-0">
                        <span id="exportSelectedCount">0</span> dari <span id="exportTotalCount">0</span> karyawan dipilih.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success btn-sm" id="btnDoExport">
                        <i class="fas fa-file-excel mr-1"></i>Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(function () {
            let dt = null;
            let searchIndex = {};      // npk -> "nama npk bagian" lowercase
            let lastEmployees = [];    // dipakai buat isi daftar karyawan di modal export

            // header kolom cuma tanggal (dd) — bulan sudah jelas dari filter Periode
            function fmtHeaderDate(iso) {
                const d = new Date(iso + 'T00:00:00');
                return String(d.getDate()).padStart(2, '0');
            }

            // dd/mm/yyyy lengkap, dipakai buat tooltip header aja
            function fmtFullDate(iso) {
                const d = new Date(iso + 'T00:00:00');
                return String(d.getDate()).padStart(2, '0') + '/' +
                    String(d.getMonth() + 1).padStart(2, '0') + '/' +
                    d.getFullYear();
            }

            function offLabel(type) {
                return type === 'holiday' ? 'Libur' : 'Weekend';
            }

            // "08:15:32" -> "08:15", kosong/"not scanned" -> "N/A"
            function fmtTime(val) {
                if (!val || val === 'not scanned') return 'N/A';
                return val.substring(0, 5);
            }

            // gabung Masuk & Pulang dalam 1 sel, 2 baris, ikon + warna beda
            // biar langsung kebaca sekilas tanpa perlu lihat kolom keterangan lagi
            function timeCellHtml(masuk, pulang) {
                const m = fmtTime(masuk);
                const p = fmtTime(pulang);
                const mCls = m === 'N/A' ? 'time-na' : 'time-in';
                const pCls = p === 'N/A' ? 'time-na' : 'time-out';
                return `<div class="time-cell">
                    <span class="${mCls}"><i class="fas fa-arrow-right"></i>${m}</span>
                    <span class="${pCls}"><i class="fas fa-arrow-left"></i>${p}</span>
                </div>`;
            }

            function loadData() {
                Swal.fire({
                    title: 'Memuat data...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                $.get("{{ route('attendance.expat.data') }}", { period: $('#period').val() }, function (res) {
                    lastEmployees = res.employees || [];
                    renderTable(res.dates, res.employees, res.offDates || {});
                    Swal.close();
                }).fail(function (xhr) {
                    Swal.fire('Gagal memuat data', xhr.responseJSON?.error || 'Terjadi kesalahan', 'error');
                });
            }

            function renderTable(dates, employees, offDates) {
                // destroy DULU, sebelum DOM thead/tbody diubah — jumlah kolom
                // (tanggal) berubah tiap periode, kalau destroy dipanggil
                // setelah markup diganti, DataTables bisa nyasar dan lempar
                // "Cannot reinitialise DataTable" pas init ulang.
                if ($.fn.dataTable.isDataTable('#attendanceTable')) {
                    $('#attendanceTable').DataTable().destroy();
                }
                dt = null;

                let theadHtml = '<tr><th>No</th><th>NPK</th><th>Nama</th><th>Bagian</th>';
                dates.forEach(d => {
                    const off = offDates[d];
                    const cls = off ? ' class="col-off"' : '';
                    const titleParts = [fmtFullDate(d)];
                    if (off) titleParts.push(offLabel(off));
                    theadHtml += `<th${cls} title="${titleParts.join(' - ')}">${fmtHeaderDate(d)}</th>`;
                });
                theadHtml += '</tr>';
                $('#attendanceThead').html(theadHtml);

                searchIndex = {};
                let bodyHtml = '';
                employees.forEach(emp => {
                    searchIndex[emp.npk] = `${emp.nama || ''} ${emp.npk || ''} ${emp.bagian || ''}`.toLowerCase();

                    // satu baris per karyawan — Masuk & Pulang digabung per sel tanggal,
                    // jadi nggak perlu rowspan lagi dan pagination DataTables aman dipakai
                    bodyHtml += `<tr data-npk="${emp.npk}">` +
                        `<td>${emp.no}</td>` +
                        `<td>${emp.npk}</td>` +
                        `<td class="text-left">${emp.nama}</td>` +
                        `<td class="text-left">${emp.bagian ?? '-'}</td>`;
                    dates.forEach(d => {
                        const cls = offDates[d] ? ' class="col-off"' : '';
                        const att = emp.attendance[d] || {};
                        bodyHtml += `<td${cls}>${timeCellHtml(att.masuk, att.pulang)}</td>`;
                    });
                    bodyHtml += `</tr>`;
                });
                $('#attendanceTbody').html(bodyHtml);

                dt = $('#attendanceTable').DataTable({
                    destroy: true,               // jaga-jaga kalau ada edge case lolos dari cek di atas
                    ordering: false,              // sorting per kolom akan merusak alignment tanggal
                    paging: true,
                    pageLength: 15,
                    lengthMenu: [10, 15, 25, 50, 100],
                    searching: true,
                    dom: 'lrtip'                  // tanpa 'f' bawaan — search pakai #customSearch sendiri
                });
            }

            // custom search: match ke nama/npk/bagian per-employee
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (!dt || settings.nTable.id !== 'attendanceTable') return true;
                const term = $('#customSearch').val().trim().toLowerCase();
                if (!term) return true;
                const npk = dt.row(dataIndex).node()?.getAttribute('data-npk');
                return (searchIndex[npk] || '').includes(term);
            });

            $('#customSearch').on('keyup', () => dt && dt.draw());
            $('#btnLoad').on('click', loadData);

            // ================= Export modal =================

            $('input[name="exportPeriodMode"]').on('change', function () {
                const isRange = $('#modeRange').is(':checked');
                $('#exportMonthGroup').toggleClass('d-none', isRange);
                $('#exportRangeGroup').toggleClass('d-none', !isRange);
            });

            function updateExportSelectedCount() {
                const total = $('.export-emp-chk').length;
                const checked = $('.export-emp-chk:checked').length;
                $('#exportSelectedCount').text(checked);
                $('#exportSelectAll').prop('checked', total > 0 && checked === total);
            }

            function populateExportEmployeeList() {
                // default: samain sama periode yang lagi ditampilkan di layar
                $('#exportPeriod').val($('#period').val());

                let rows = '';
                lastEmployees.forEach(emp => {
                    rows += `<tr>
                        <td><input type="checkbox" class="export-emp-chk" value="${emp.npk}" checked></td>
                        <td>${emp.npk}</td>
                        <td>${emp.nama}</td>
                        <td>${emp.bagian ?? '-'}</td>
                    </tr>`;
                });
                $('#exportEmpTbody').html(rows);
                $('#exportEmpSearch').val('');
                $('#exportTotalCount').text(lastEmployees.length);
                updateExportSelectedCount();
            }

            $('#btnExport').on('click', function () {
                if (!lastEmployees.length) {
                    Swal.fire('Data belum siap', 'Tunggu tabel selesai dimuat dulu, ya.', 'info');
                    return;
                }
                populateExportEmployeeList();
                $('#exportModal').modal('show');
            });

            $('#exportEmpTbody').on('change', '.export-emp-chk', updateExportSelectedCount);

            $('#exportSelectAll').on('change', function () {
                $('.export-emp-chk').prop('checked', $(this).is(':checked'));
                updateExportSelectedCount();
            });

            $('#exportEmpSearch').on('keyup', function () {
                const term = $(this).val().trim().toLowerCase();
                $('#exportEmpTbody tr').each(function () {
                    $(this).toggle($(this).text().toLowerCase().includes(term));
                });
            });

            $('#btnDoExport').on('click', function () {
                const isRange = $('#modeRange').is(':checked');
                const params = new URLSearchParams();

                if (isRange) {
                    const s = $('#exportStartDate').val();
                    const e = $('#exportEndDate').val();
                    if (!s || !e) {
                        Swal.fire('Lengkapi tanggal', 'Isi tanggal mulai dan tanggal akhir dulu.', 'warning');
                        return;
                    }
                    if (s > e) {
                        Swal.fire('Rentang tanggal salah', 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.', 'warning');
                        return;
                    }
                    params.set('start_date', s);
                    params.set('end_date', e);
                } else {
                    const p = $('#exportPeriod').val();
                    if (!p) {
                        Swal.fire('Pilih periode', 'Pilih bulan periode dulu.', 'warning');
                        return;
                    }
                    params.set('period', p);
                }

                const allChk  = $('.export-emp-chk');
                const checked = allChk.filter(':checked');
                if (checked.length === 0) {
                    Swal.fire('Pilih karyawan', 'Pilih minimal satu karyawan, atau centang Select All untuk semua.', 'warning');
                    return;
                }
                // kalau semua dicentang -> nggak usah kirim npks[], backend anggap "semua"
                if (checked.length < allChk.length) {
                    checked.each(function () { params.append('npks[]', $(this).val()); });
                }

                $('#exportModal').modal('hide');

                Swal.fire({
                    title: 'Membuat file Excel...',
                    text: 'Mohon tunggu, sedang menyiapkan data.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                // pakai fetch + blob (bukan window.location langsung) supaya loading
                // bisa ditutup TEPAT saat file-nya beneran selesai dibuat, dan kalau
                // export gagal di server, errornya kelihatan alih-alih cuma diam saja.
                fetch("{{ route('attendance.expat.export') }}?" + params.toString())
                    .then(async (res) => {
                        if (!res.ok) {
                            throw new Error('Export gagal (HTTP ' + res.status + ')');
                        }
                        const blob = await res.blob();

                        let filename = 'Attendance_Expat.xlsx';
                        const disposition = res.headers.get('Content-Disposition') || '';
                        const match = disposition.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
                        if (match && match[1]) filename = decodeURIComponent(match[1].replace(/"/g, ''));

                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url);

                        Swal.close();
                    })
                    .catch((err) => {
                        Swal.fire('Gagal export', err.message || 'Terjadi kesalahan saat export.', 'error');
                    });
            });

            loadData();
        });
    </script>
</body>

</html>