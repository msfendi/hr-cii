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

        .badge-masuk {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pulang {
            background: #fee2e2;
            color: #991b1b;
        }

        /* kolom tanggal libur / weekend */
        #attendanceTable th.col-off,
        #attendanceTable td.col-off {
            background-color: #fecaca !important;
            color: #7f1d1d;
        }

        .attendance-legend {
            font-size: 12.5px;
        }

        .attendance-legend .legend-swatch {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 2px;
            vertical-align: middle;
            margin-right: 4px;
        }

        .attendance-legend .legend-swatch.off {
            background-color: #fecaca;
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

                            <div class="attendance-legend mb-2">
                                <span class="legend-swatch off"></span> Libur / Weekend
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

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

    <script>
        $(function () {
            let dt = null;
            let searchIndex = {}; // npk -> "nama npk bagian" lowercase
            const evenPageLength = [10, 20, 40, 60];

            function fmtHeaderDate(iso) {
                const d = new Date(iso + 'T00:00:00');
                return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0');
            }

            function offLabel(type) {
                return type === 'holiday' ? 'Libur' : 'Weekend';
            }

            function loadData() {
                $.get("{{ route('attendance.expat.data') }}", { period: $('#period').val() }, function (res) {
                    renderTable(res.dates, res.employees, res.offDates || {});
                }).fail(function (xhr) {
                    Swal.fire('Gagal memuat data', xhr.responseJSON?.error || 'Terjadi kesalahan', 'error');
                });
            }

            function renderTable(dates, employees, offDates) {
                let theadHtml = '<tr><th>No</th><th>NPK</th><th>Nama</th><th>Bagian</th>';
                dates.forEach(d => {
                    const off = offDates[d];
                    const cls = off ? ' class="col-off"' : '';
                    const title = off ? ` title="${offLabel(off)}"` : '';
                    theadHtml += `<th${cls}${title}>${fmtHeaderDate(d)}</th>`;
                });
                theadHtml += '<th>Keterangan</th></tr>';
                $('#attendanceThead').html(theadHtml);

                searchIndex = {};
                let bodyHtml = '';
                employees.forEach(emp => {
                    searchIndex[emp.npk] = `${emp.nama || ''} ${emp.npk || ''} ${emp.bagian || ''}`.toLowerCase();

                    bodyHtml += `<tr data-npk="${emp.npk}">` +
                        `<td rowspan="2">${emp.no}</td>` +
                        `<td rowspan="2">${emp.npk}</td>` +
                        `<td rowspan="2" class="text-left">${emp.nama}</td>` +
                        `<td rowspan="2" class="text-left">${emp.bagian ?? '-'}</td>`;
                    dates.forEach(d => {
                        const cls = offDates[d] ? ' class="col-off"' : '';
                        bodyHtml += `<td${cls}>${(emp.attendance[d] || {}).masuk || 'not scanned'}</td>`;
                    });
                    bodyHtml += `<td><span class="badge badge-masuk">Masuk</span></td></tr>`;

                    bodyHtml += `<tr data-npk="${emp.npk}">`;
                    dates.forEach(d => {
                        const cls = offDates[d] ? ' class="col-off"' : '';
                        bodyHtml += `<td${cls}>${(emp.attendance[d] || {}).pulang || 'not scanned'}</td>`;
                    });
                    bodyHtml += `<td><span class="badge badge-pulang">Pulang</span></td></tr>`;
                });
                $('#attendanceTbody').html(bodyHtml);

                if (dt) dt.destroy();

                dt = $('#attendanceTable').DataTable({
                    ordering: false,          // sorting per kolom akan merusak pasangan rowspan
                    paging: true,
                    pageLength: 20,           // WAJIB kelipatan 2
                    lengthMenu: evenPageLength,
                    searching: true,
                    dom: 'lrtip'              // tanpa 'f' bawaan — search pakai #customSearch sendiri
                });
            }

            // custom search: match ke nama/npk/bagian per-employee (bukan per-cell),
            // jadi baris Masuk & Pulang selalu ikut lolos/tersingkir bersamaan
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (!dt || settings.nTable.id !== 'attendanceTable') return true;
                const term = $('#customSearch').val().trim().toLowerCase();
                if (!term) return true;
                const npk = dt.row(dataIndex).node()?.getAttribute('data-npk');
                return (searchIndex[npk] || '').includes(term);
            });

            $('#customSearch').on('keyup', () => dt && dt.draw());
            $('#btnLoad').on('click', loadData);
            $('#btnExport').on('click', function () {
                window.location = "{{ route('attendance.expat.export') }}?period=" + $('#period').val();
            });

            loadData();
        });
    </script>
</body>

</html>