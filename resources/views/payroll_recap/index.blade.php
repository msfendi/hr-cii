<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<style>

.table-sm td,
.table-sm th{
    padding:.35rem .45rem;
    font-size:.82rem;
}

.card-header h6{
    font-size:.95rem;
}

.dataTables_wrapper{
    font-size:.85rem;
}

.dataTables_wrapper table td{
    padding:.45rem;
}

</style>
<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">

                @include('layout.navbar')

                <div class="container-fluid">

                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Rekap Payroll</h1>
                    </div>

                    {{-- ===================== FILTER PANEL ===================== --}}
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="filterForm">
                                <div class="row align-items-start">

                                    <div class="col-md-2 mb-3">
                                        <label class="small font-weight-bold text-gray-600 mb-1 d-block">Bulan Akhir</label>
                                        <input type="month" class="form-control" id="endMonth" name="end_month">
                                        <small class="form-text text-muted mb-0">Otomatis mundur 12 bulan</small>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label class="small font-weight-bold text-gray-600 mb-1 d-block">Karyawan (NPK)</label>
                                        <select id="npkSelect" class="form-control" style="width:100%">
                                            <option value="">Semua Karyawan</option>
                                        </select>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label class="small font-weight-bold text-gray-600 mb-1 d-block">Department</label>
                                        <select id="deptSelect" class="form-control" style="width:100%">
                                            <option value="">Semua Department</option>
                                            @foreach ($departments as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="small font-weight-bold text-gray-600 mb-1 d-block">Komponen Payroll</label>
                                        <select id="componentSelect" class="form-control" style="width:100%">
                                            <option value="total_salary">Total Take Home Pay</option>
                                            <optgroup label="Earning">
                                                @foreach ($components['earning'] as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                            <optgroup label="Deduction">
                                                @foreach ($components['deduction'] as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                        </select>
                                    </div>

                                </div>

                                <div class="d-flex">
                                    <button type="submit" class="btn btn-primary shadow-sm mr-2">
                                        <i class="fas fa-search fa-sm mr-1"></i> Terapkan Filter
                                    </button>
                                    <button type="button" id="resetFilter" class="btn btn-secondary shadow-sm">
                                        <i class="fas fa-undo fa-sm mr-1"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- ===================== KPI CARDS ===================== --}}
                    <div class="row">
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1"
                                                id="componentLabel">Total Take Home Pay</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiGrandTotal">Rp 0</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-coins fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Rata-rata / Bulan</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiAvgMonth">Rp 0</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Jumlah Karyawan (Max/Bulan)</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiMaxEmployees">0</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">

                        {{-- CHART --}}
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow h-100">

                                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        Rekap Payroll per Bulan
                                        <span class="text-gray-500 font-weight-normal"
                                            id="rangeLabel"></span>
                                    </h6>
                                </div>

                                <div class="card-body">

                                    <div id="chartLoading" class="text-center py-5" style="display:none;">
                                        <i class="fas fa-spinner fa-spin fa-2x text-gray-400"></i>
                                        <p class="text-gray-500 mt-2 mb-0">Memuat data...</p>
                                    </div>

                                    <div id="chartEmpty" class="text-center py-5" style="display:none;">
                                        <i class="fas fa-inbox fa-2x text-gray-300"></i>
                                        <p class="text-gray-500 mt-2 mb-0">Tidak ada data.</p>
                                    </div>

                                    <div style="height:350px">
                                        <canvas id="recapChart"></canvas>
                                    </div>

                                </div>
                            </div>
                        </div>

                        {{-- RINCIAN BULAN --}}
                        <div class="col-lg-6 mb-4">

                            <div class="card shadow h-100">

                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        Rincian per Bulan
                                    </h6>
                                </div>

                                <div class="card-body p-2">

                                    <div class="table-responsive">

                                        <table class="table table-sm table-bordered table-hover mb-0 small">

                                            <thead class="thead-light">

                                            <tr>
                                                <th rowspan="2" class="align-middle">Bulan</th>
                                                <th colspan="2" class="text-center text-success">Karyawan Aktif</th>
                                                <th colspan="2" class="text-center text-danger">Karyawan Keluar</th>
                                                <th rowspan="2" class="align-middle text-right">Total Keseluruhan</th>
                                            </tr>
                                            <tr>
                                                <th class="text-right text-success">Jumlah</th>
                                                <th class="text-right text-success">Total</th>
                                                <th class="text-right text-danger">Jumlah</th>
                                                <th class="text-right text-danger">Total</th>
                                            </tr>

                                            </thead>

                                            <tbody id="breakdownTableBody"></tbody>

                                            <tfoot>
                                                <tr class="font-weight-bold">
                                                    <td>Grand Total</td>
                                                    <td class="text-right" id="footerAktifCount">-</td>
                                                    <td class="text-right" id="footerAktifTotal">Rp 0</td>
                                                    <td class="text-right" id="footerKeluarCount">-</td>
                                                    <td class="text-right" id="footerKeluarTotal">Rp 0</td>
                                                    <td class="text-right" id="footerTotal">Rp 0</td>
                                                </tr>
                                            </tfoot>

                                        </table>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    {{-- ===================== TABLE RINCIAN PER KARYAWAN ===================== --}}
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Rincian per Karyawan</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover mb-0 small" id="employeeTable" width="100%" cellspacing="0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>NPK</th>
                                                    <th>Nama</th>
                                                    <th>Bagian</th>
                                                    <th>Status</th>
                                                    <th>Tanggal Keluar</th>
                                                    <th>Jumlah Periode</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
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
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        Chart.defaults.font.family = 'Nunito, -apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
        Chart.defaults.color = '#858796';

        const rupiah = (value) => new Intl.NumberFormat('id-ID', {
            style: 'currency', currency: 'IDR', minimumFractionDigits: 0
        }).format(value || 0);

        let recapChart = null;

        function setDefaultMonthRange() {
            const now = new Date();
            const end = new Date(now.getFullYear(), now.getMonth(), 1);
            const toMonthValue = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;

            $('#endMonth').val(toMonthValue(end));
        }

        function formatMonthIndonesia(value){

            if(!value) return '';

            // format YYYY-MM
            if(/^\d{4}-\d{2}$/.test(value)){
                const [y,m]=value.split('-');

                const bulan=[
                    'Januari','Februari','Maret','April','Mei','Juni',
                    'Juli','Agustus','September','Oktober','November','Desember'
                ];

                return bulan[parseInt(m)-1]+' '+y;
            }

            // format lain gunakan Date
            const d=new Date(value);

            if(!isNaN(d)){
                return d.toLocaleDateString('id-ID',{
                    month:'long',
                    year:'numeric'
                });
            }

            return value;
        }

        function loadChartData() {
            const params = {
                end_month: $('#endMonth').val(),
                npk: $('#npkSelect').val(),
                dept: $('#deptSelect').val(),
                component: $('#componentSelect').val(),
            };

            $('#chartLoading').show();
            $('#chartEmpty').hide();

            fetch("{{ route('payroll-recap.chart-data') }}?" + new URLSearchParams(params))
                .then(res => res.json())
                .then(data => {
                    $('#chartLoading').hide();

                    const hasData = data.values.some(v => v > 0);
                    $('#chartEmpty').toggle(!hasData);

                    // KPI
                    $('#componentLabel').text(data.component_label);
                    $('#kpiGrandTotal').text(rupiah(data.grand_total));
                    $('#kpiAvgMonth').text(rupiah(data.avg_per_month));
                    $('#kpiMaxEmployees').text(data.max_employees);
                    $('#rangeLabel').text(
                        '(' +
                        formatMonthIndonesia(data.range.start) +
                        ' s/d ' +
                        formatMonthIndonesia(data.range.end) +
                        ')'
                    );

                    // Chart - grouped bar: Aktif vs Keluar berdampingan (kanan-kiri)
                    if (recapChart) recapChart.destroy();
                    const ctx = document.getElementById('recapChart');
                    console.log(data.labels);
                    recapChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels.map(e=>formatMonthIndonesia(e)),
                            datasets: [
                                {
                                    label: 'Karyawan Aktif',
                                    data: data.aktif_values,
                                    backgroundColor: "#1cc88a",
                                    hoverBackgroundColor: "#17a673",
                                    borderColor: "#1cc88a",
                                    borderWidth: 1,
                                    borderRadius: 4,
                                    maxBarThickness: 30,
                                },
                                {
                                    label: 'Karyawan Keluar',
                                    data: data.keluar_values,
                                    backgroundColor: "#e74a3b",
                                    hoverBackgroundColor: "#d52a1a",
                                    borderColor: "#e74a3b",
                                    borderWidth: 1,
                                    borderRadius: 4,
                                    maxBarThickness: 30,
                                }
                            ]
                        },
                        options: {
                            maintainAspectRatio: false,
                            layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                            scales: {
                                x: { grid: { display: false, drawBorder: false } },
                                y: {
                                    ticks: { callback: (val) => rupiah(val) },
                                    grid: {
                                        color: "rgb(234, 236, 244)",
                                        drawBorder: false,
                                        borderDash: [2],
                                    }
                                },
                            },
                            plugins: {
                                legend: { display: true, position: 'bottom' },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => {
                                            const idx = ctx.dataIndex;
                                            const count = ctx.datasetIndex === 0 ? data.aktif_counts[idx] : data.keluar_counts[idx];
                                            return `${ctx.dataset.label}: ${rupiah(ctx.parsed.y)} (${count} karyawan)`;
                                        }
                                    }
                                }
                            }
                        }
                    });

                    // Tabel breakdown per bulan
                    let rows = '';
                    data.labels.forEach((label, i) => {
                        rows += `<tr>
                            <td>${formatMonthIndonesia(label)}</td>
                            <td class="text-right">${data.aktif_counts[i]}</td>
                            <td class="text-right">${rupiah(data.aktif_values[i])}</td>
                            <td class="text-right">${data.keluar_counts[i]}</td>
                            <td class="text-right">${rupiah(data.keluar_values[i])}</td>
                            <td class="text-right font-weight-bold">${rupiah(data.values[i])}</td>
                        </tr>`;
                    });
                    $('#breakdownTableBody').html(rows);

                    const totalAktif  = data.aktif_values.reduce((a, b) => a + b, 0);
                    const totalKeluar = data.keluar_values.reduce((a, b) => a + b, 0);
                    $('#footerAktifCount').text(Math.max(...data.aktif_counts));
                    $('#footerAktifTotal').text(rupiah(totalAktif));
                    $('#footerKeluarCount').text(Math.max(...data.keluar_counts));
                    $('#footerKeluarTotal').text(rupiah(totalKeluar));
                    $('#footerTotal').text(rupiah(data.grand_total));

                    // Tabel rincian per karyawan (DataTables)
                    updateEmployeeTable(data.employees);
                })
                .catch(err => {
                    $('#chartLoading').hide();
                    console.error('Error fetching recap chart data:', err);
                });
        }

        let employeeTable = null;

        function initEmployeeTable() {
            employeeTable = $('#employeeTable').DataTable({
                data: [],
                columns: [
                    { data: 'npk', title: 'NPK' },
                    { data: 'nama', title: 'Nama', defaultContent: '-' },
                    { data: 'bagian', title: 'Bagian', defaultContent: '-' },
                    {
                        data: 'status',
                        title: 'Status',
                        className: 'text-center',
                        render: (data) => data === 'keluar'
                            ? '<span class="badge badge-danger">Keluar</span>'
                            : '<span class="badge badge-success">Aktif</span>'
                    },
                    { data: 'tkk_formatted', title: 'Tanggal Keluar', defaultContent: '-' },
                    { data: 'months_count', title: 'Jumlah Periode', className: 'text-right' },
                    {
                        data: 'total',
                        title: 'Total',
                        className: 'text-right',
                        render: (data, type) => type === 'display' ? rupiah(data) : data
                    },
                ],
                order: [[6, 'desc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ karyawan',
                    infoEmpty: 'Tidak ada data',
                    infoFiltered: '(difilter dari _MAX_ total data)',
                    zeroRecords: 'Tidak ada karyawan yang cocok',
                    paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
                }
            });
        }

        function updateEmployeeTable(employees) {
            if (!employeeTable) return;
            employeeTable.clear();
            employeeTable.rows.add(employees);
            employeeTable.draw();
        }

        $(document).ready(function () {
            setDefaultMonthRange();
            initEmployeeTable();

            $('#npkSelect').select2({
                placeholder: 'Semua Karyawan',
                allowClear: true,
                ajax: {
                    url: "{{ route('payroll-recap.search-employee') }}",
                    dataType: 'json',
                    delay: 300,
                    data: params => ({ q: params.term }),
                    processResults: data => ({ results: data.results })
                },
                minimumInputLength: 0
            });

            $('#deptSelect').select2({ placeholder: 'Semua Department', allowClear: true });
            $('#componentSelect').select2({ minimumResultsForSearch: 0 });

            $('#filterForm').on('submit', function (e) {
                e.preventDefault();
                loadChartData();
            });

            $('#resetFilter').on('click', function () {
                setDefaultMonthRange();
                $('#npkSelect').val(null).trigger('change');
                $('#deptSelect').val(null).trigger('change');
                $('#componentSelect').val('total_salary').trigger('change');
                loadChartData();
            });

            loadChartData();
        });
    </script>
</body>

</html>