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
                        <h1 class="h3 mb-0 text-gray-800">HR Dashboard</h1>
                        {{-- <div class="d-flex align-items-center">
                            <label for="yearFilter" class="mr-2 font-weight-bold text-gray-600 mb-0">Year:</label>
                            <select id="yearFilter" class="form-control mr-3 shadow-sm border-0" style="width: 120px;">
                                <option value="2025">2025</option>
                                <option value="2024" selected>2024</option>
                                <option value="2023">2023</option>
                            </select>
                            <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"
                                onclick="window.print()">
                                <i class="fas fa-download fa-sm text-white-50"></i> Generate Report
                            </a>
                        </div> --}}
                    </div>

                    <!-- KPI Cards Row -->
                    <div class="row">
                        <!-- Total PKWT -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total PKWT</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalpkwt">
                                                Loading...</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dept Non Sewing -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Sewing Employees</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="sewingemployees">
                                                Loading...</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-building fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dept Sewing -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Non Sewing Employees</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="nonsewingemployees">
                                                Loading...</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-industry fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sewing Employees -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                Dept Sewing</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="deptsewing">
                                                Loading...</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-tshirt fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 1 -->
                    <div class="row">
                        <!-- Recruitment Flow & PKWT Trend -->
                        <div class="col-xl-8 col-lg-7">
                            <div class="card shadow mb-4">
                                <!-- Card Header -->
                                <div
                                    class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">PKWT Trend & Recruitment vs Attrition
                                    </h6>
                                    <div class="d-flex align-items-center">
                                        <input type="month" id="monthFilter" class="form-control form-control-sm"
                                            style="width: auto;">
                                    </div>
                                </div>
                                <!-- Card Body -->
                                <div class="card-body">
                                    <div class="chart-area" style="height: 350px;">
                                        <canvas id="recruitmentChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Department Distribution -->
                        <div class="col-xl-4 col-lg-5">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Employee Count by Department (Top 5)
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-bar" style="height: 300px;">
                                        <canvas id="departmentChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 2 -->
                    <div class="row">
                        <!-- Gender per Department -->
                        <div class="col-xl-6 col-lg-5">
                            <div class="card shadow mb-4">
                                <!-- Card Header -->
                                <div
                                    class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Gender per Department</h6>
                                </div>
                                <!-- Card Body -->
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered table-hover mb-0"
                                            id="genderDeptTable" width="100%">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Department</th>
                                                    <th class="text-center"><i class="fas fa-mars text-primary"></i>
                                                        Male</th>
                                                    <th class="text-center"><i class="fas fa-venus text-danger"></i>
                                                        Female</th>
                                                    <th class="text-center"><i class="fas fa-users text-success"></i>
                                                        Total</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity / Table -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Recent Joining Employees</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" id="recentTable" width="100%"
                                            cellspacing="0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>NPK</th>
                                                    <th>Name</th>
                                                    <th>Department</th>
                                                    <th>Join Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Data will be loaded via fetch -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 3: Turn Over Karyawan per Departemen per Bulan -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow mb-4">
                                <div
                                    class="card-header py-3 d-flex flex-row align-items-center justify-content-between"
                                    style="flex-wrap: wrap; row-gap: .5rem;">
                                    <h6 class="m-0 font-weight-bold text-primary">Turn Over Karyawan per Departemen
                                    </h6>
                                    <div class="d-flex align-items-center" style="flex-wrap: wrap; row-gap: .35rem;">
                                        <input type="month" id="turnoverMonth" value="{{ now()->format('Y-m') }}"
                                            class="form-control form-control-sm mr-2" style="width: auto;">
                                        <select id="turnoverSewing" class="form-control form-control-sm mr-2"
                                            style="width: auto;" title="Pengelompokan line sewing (leader line tidak ikut)">
                                            <option value="pisah" selected>Pisah per Line</option>
                                            <option value="global">Global Line (Sewing)</option>
                                            <option value="factory">Per Factory</option>
                                        </select>
                                        <select id="turnoverDept" class="form-control form-control-sm"
                                            style="width: auto; max-width: 240px;">
                                            <option value="ALL">Semua Departemen</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-6 col-md mb-2">
                                            <div class="border rounded p-2 h-100">
                                                <div class="text-xs text-uppercase text-muted font-weight-bold">Karyawan Aktif</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="tvAwal">0</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md mb-2">
                                            <div class="border rounded p-2 h-100">
                                                <div class="text-xs text-uppercase text-muted font-weight-bold">Masuk
                                                    (TMK)</div>
                                                <div class="h5 mb-0 font-weight-bold text-success" id="tvMasuk">0</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md mb-2">
                                            <div class="border rounded p-2 h-100">
                                                <div class="text-xs text-uppercase text-muted font-weight-bold">Keluar
                                                    (TKK)</div>
                                                <div class="h5 mb-0 font-weight-bold text-danger" id="tvKeluar">0</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md mb-2">
                                            <div class="border rounded p-2 h-100">
                                                <div class="text-xs text-uppercase text-muted font-weight-bold">Net
                                                    (Masuk - Keluar)</div>
                                                <div class="h5 mb-0 font-weight-bold text-primary" id="tvNet">0</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md mb-2">
                                            <div class="border rounded p-2 h-100">
                                                <div class="text-xs text-uppercase text-muted font-weight-bold">Turn
                                                    Over</div>
                                                <div class="h5 mb-0 font-weight-bold text-primary" id="tvRate">0 %</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="chart-bar" style="height: 300px;">
                                        <canvas id="turnoverChart"></canvas>
                                    </div>
                                    <hr>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered table-hover mb-0"
                                            id="turnoverTable" width="100%" cellspacing="0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Departemen</th>
                                                    <th class="text-center">Karyawan Aktif</th>
                                                    <th class="text-center">Masuk</th>
                                                    <th class="text-center">Keluar</th>
                                                    <th class="text-center">Net</th>
                                                    <th class="text-center">Turn Over (%)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Diisi lewat fetch /home/turnover -->
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="small text-muted mt-2" id="turnoverNote"></div>
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

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        Chart.defaults.font.family = 'Nunito, -apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
        Chart.defaults.color = '#858796';

        document.addEventListener("DOMContentLoaded", function () {

            // --- 1. REAL DATA: KPI Cards ---
            fetch("{{ route('home.get-recap-count') }}")
                .then(response => response.json())
                .then(responseData => {
                    // Update KPI Cards
                    if (document.getElementById('totalpkwt')) document.getElementById('totalpkwt').textContent = responseData.totalpkwt;
                    if (document.getElementById('nonsewingemployees')) document.getElementById('nonsewingemployees').textContent = responseData.nonsewingemployees;
                    if (document.getElementById('sewingemployees')) document.getElementById('sewingemployees').textContent = responseData.sewingemployees;
                    if (document.getElementById('deptsewing')) document.getElementById('deptsewing').textContent = responseData.deptsewing;

                    // Initialize DataTables for Recently Hired Employees
                    $('#recentTable').DataTable({
                        data: responseData.recentlyhired,
                        columns: [
                            { data: 'NPK' },
                            { data: 'NAMA' },
                            { data: 'BAGIAN' },
                            {
                                data: 'TMK',
                                render: function (data) {
                                    // Format date to dd-mm-yyyy if needed
                                    if (data) {
                                        const date = new Date(data);
                                        const day = String(date.getDate()).padStart(2, '0');
                                        const month = String(date.getMonth() + 1).padStart(2, '0');
                                        const year = date.getFullYear();
                                        return `${day}-${month}-${year}`;
                                    }
                                    return data;
                                }
                            },
                            {
                                data: 'TKK',
                                render: function (data) {
                                    if (data) {
                                        return '<span class="badge badge-danger">Not Active</span>';
                                    }
                                    return '<span class="badge badge-success">Active</span>';
                                }
                            }
                        ],
                        pageLength: 7,
                        lengthMenu: [10, 25, 50],
                        lengthChange: true,
                        searching: true,
                        ordering: true,
                        info: true,
                        paging: true
                    });

                    // Department Distribution (Horizontal Bar) - REAL DATA
                    const ctxDept = document.getElementById("departmentChart");
                    if (ctxDept && responseData.mostemployee) {
                        const deptLabels = responseData.mostemployee.map(item => item.department_name);
                        const deptData = responseData.mostemployee.map(item => item.employee_count);

                        new Chart(ctxDept, {
                            type: 'bar',
                            data: {
                                labels: deptLabels,
                                datasets: [{
                                    label: 'Employees',
                                    data: deptData,
                                    backgroundColor: "#36b9cc",
                                    hoverBackgroundColor: "#2c9faf",
                                    borderColor: "#36b9cc",
                                }],
                            },
                            options: {
                                indexAxis: 'y',
                                maintainAspectRatio: false,
                                layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                                scales: {
                                    x: {
                                        grid: { display: false, drawBorder: false },
                                        ticks: { maxTicksLimit: 6 }
                                    },
                                    y: {
                                        grid: {
                                            color: "rgb(234, 236, 244)",
                                            zeroLineColor: "rgb(234, 236, 244)",
                                            drawBorder: false,
                                            borderDash: [2],
                                            zeroLineBorderDash: [2]
                                        }
                                    },
                                },
                                plugins: { legend: { display: false } }
                            },
                        });
                    }

                    // Gender per Department DataTable
                    $('#genderDeptTable').DataTable({
                        data: responseData.genderperdept || [],
                        columns: [
                            { data: 'department_name' },
                            {
                                data: 'male_count',
                                className: 'text-center',
                                render: function (data) {
                                    return '<span class="badge badge-primary">' + data + '</span>';
                                }
                            },
                            {
                                data: 'female_count',
                                className: 'text-center',
                                render: function (data) {
                                    return '<span class="badge badge-danger">' + data + '</span>';
                                }
                            },
                            {
                                data: null,
                                className: 'text-center',
                                render: function (data, type, row) {
                                    const total = parseInt(row.male_count || 0) + parseInt(row.female_count || 0);
                                    return '<span class="badge badge-success">' + total + '</span>';
                                }
                            }
                        ],
                        pageLength: 10,
                        lengthMenu: [5, 10, 25, 50],
                        searching: true,
                        ordering: true,
                        order: [[0, 'asc']],
                        info: true,
                        paging: true
                    });
                })
                .catch(error => console.error('Error fetching recap data:', error));

            let recruitmentChartInstance = null;

            function loadPKWTChart(month = '') {
                let url = "{{ route('home.get-pkwt-chart') }}";
                if (month) {
                    url += `?month=${month}`;
                }

                fetch(url)
                    .then(response => response.json())
                    .then(responseData => {
                        const ctxRecruitment = document.getElementById('recruitmentChart');

                        if (recruitmentChartInstance) {
                            recruitmentChartInstance.destroy();
                        }

                        recruitmentChartInstance = new Chart(ctxRecruitment, {
                            type: 'bar',
                            data: {
                                labels: responseData.labels,
                                datasets: [
                                    {
                                        type: 'bar',
                                        label: 'PKWT Trend',
                                        data: responseData.data,
                                        backgroundColor: "#4e73df",
                                        borderColor: "#4e73df",
                                        borderWidth: 2,
                                        tension: 0.3,
                                        fill: false,
                                        yAxisID: 'y'
                                    },
                                    {
                                        type: 'bar',
                                        label: 'Joined (Masuk)',
                                        data: responseData.joined,
                                        backgroundColor: "#1cc88a",
                                        hoverBackgroundColor: "#17a673",
                                        borderColor: "#1cc88a",
                                        borderWidth: 1
                                    },
                                    {
                                        type: 'bar',
                                        label: 'Left (Keluar)',
                                        data: responseData.left,
                                        backgroundColor: "#e74a3b",
                                        hoverBackgroundColor: "#e02d1b",
                                        borderColor: "#e74a3b",
                                        borderWidth: 1
                                    }
                                ]
                            },
                            options: {
                                maintainAspectRatio: false,
                                layout: {
                                    padding: { left: 10, right: 25, top: 25, bottom: 0 }
                                },
                                scales: {
                                    x: {
                                        grid: { display: false, drawBorder: false },
                                        ticks: { maxTicksLimit: 12 }
                                    },
                                    y: {
                                        type: 'linear',
                                        display: true,
                                        position: 'left',
                                        ticks: { maxTicksLimit: 5, padding: 10 },
                                        grid: {
                                            color: "rgb(234, 236, 244)",
                                            zeroLineColor: "rgb(234, 236, 244)",
                                            drawBorder: false,
                                            borderDash: [2],
                                            zeroLineBorderDash: [2]
                                        }
                                    }
                                },
                                plugins: {
                                    legend: { display: true },
                                    title: { display: true, text: 'PKWT Trend vs Recruitment & Attrition (Last 12 Months)' }
                                }
                            }
                        });
                    })
                    .catch(error => console.error('Error fetching chart data:', error));
            }

            // Initial load
            loadPKWTChart();

            // --- Turn over karyawan per departemen per bulan (sumber: PKWT.TMK / PKWT.TKK) ---
            let turnoverChart = null;
            let turnoverTable = null;

            function setTeks(id, nilai) {
                const el = document.getElementById(id);
                if (el) { el.textContent = nilai; }
            }

            function isiPilihanDept(daftar, terpilih) {
                const sel = document.getElementById('turnoverDept');
                if (!sel) { return; }
                const nilai = terpilih || sel.value || 'ALL';
                const opsi = ['<option value="ALL">Semua Departemen</option>'];
                (daftar || []).forEach(function (d) {
                    const aman = String(d).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                    opsi.push('<option value="' + aman + '">' + aman + '</option>');
                });
                opsi.push('<option value="__NODEPT__">(Tanpa bagian)</option>');
                sel.innerHTML = opsi.join('');
                sel.value = nilai;
                if (sel.value !== nilai) { sel.value = 'ALL'; }
            }

            function loadTurnover() {
                const elBulan = document.getElementById('turnoverMonth');
                const elDept = document.getElementById('turnoverDept');
                const elSewing = document.getElementById('turnoverSewing');
                const bulan = elBulan ? (elBulan.value || '') : '';
                const dept = elDept ? (elDept.value || 'ALL') : 'ALL';
                const sewing = elSewing ? (elSewing.value || 'pisah') : 'pisah';
                const url = "{{ route('home.turnover') }}?month=" + encodeURIComponent(bulan)
                    + '&dept=' + encodeURIComponent(dept)
                    + '&sewing=' + encodeURIComponent(sewing);

                fetch(url)
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (elBulan && d.bulan) { elBulan.value = d.bulan; }
                        if (elSewing && d.sewing) { elSewing.value = d.sewing; }
                        isiPilihanDept(d.daftar_departemen, d.dept === 'ALL' ? null : d.dept);

                        const rk = d.ringkasan || {};
                        setTeks('tvAwal', rk.awal || 0);
                        setTeks('tvMasuk', rk.masuk || 0);
                        setTeks('tvKeluar', rk.keluar || 0);
                        setTeks('tvNet', rk.net || 0);
                        setTeks('tvRate', (rk.rate || 0) + ' %');

                        const judulDept = (d.dept === 'ALL') ? 'Semua Departemen' : d.dept;
                        const ctx = document.getElementById('turnoverChart');
                        if (ctx) {
                            if (turnoverChart) { turnoverChart.destroy(); }
                            turnoverChart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: (d.tren || {}).labels || [],
                                    datasets: [
                                        {
                                            type: 'bar', label: 'Masuk (TMK)',
                                            data: ((d.tren || {}).masuk) || [],
                                            backgroundColor: '#1cc88a', hoverBackgroundColor: '#17a673'
                                        },
                                        {
                                            type: 'bar', label: 'Keluar (TKK)',
                                            data: ((d.tren || {}).keluar) || [],
                                            backgroundColor: '#e74a3b', hoverBackgroundColor: '#e02d1b'
                                        },
                                        {
                                            type: 'line', label: 'Turn Over (%)',
                                            data: ((d.tren || {}).rate) || [],
                                            borderColor: '#4e73df', backgroundColor: '#4e73df',
                                            borderWidth: 2, tension: 0.3, fill: false, pointRadius: 2,
                                            yAxisID: 'y2'
                                        }
                                    ]
                                },
                                options: {
                                    maintainAspectRatio: false,
                                    layout: { padding: { left: 10, right: 10, top: 20, bottom: 0 } },
                                    scales: {
                                        x: { grid: { display: false }, ticks: { maxTicksLimit: 12 } },
                                        y: {
                                            beginAtZero: true,
                                            title: { display: true, text: 'Jumlah karyawan' },
                                            ticks: { maxTicksLimit: 6, precision: 0 },
                                            grid: {
                                                color: 'rgb(234, 236, 244)', drawBorder: false,
                                                borderDash: [2], zeroLineBorderDash: [2]
                                            }
                                        },
                                        y2: {
                                            beginAtZero: true, position: 'right',
                                            title: { display: true, text: 'Turn Over (%)' },
                                            grid: { drawOnChartArea: false },
                                            ticks: { maxTicksLimit: 5, callback: function (v) { return v + ' %'; } }
                                        }
                                    },
                                    plugins: {
                                        legend: { display: true },
                                        title: {
                                            display: true,
                                            text: 'Tren 12 bulan - ' + judulDept
                                        }
                                    }
                                }
                            });
                        }

                        if (turnoverTable) { turnoverTable.destroy(); }
                        $('#turnoverTable tbody').empty();
                        turnoverTable = $('#turnoverTable').DataTable({
                            data: d.per_departemen || [],
                            columns: [
                                { data: 'departemen' },
                                { data: 'awal', className: 'text-center' },
                                {
                                    data: 'masuk', className: 'text-center',
                                    render: function (v) { return '<span class="badge badge-success">' + (v || 0) + '</span>'; }
                                },
                                {
                                    data: 'keluar', className: 'text-center',
                                    render: function (v) {
                                        const n = v || 0;
                                        return '<span class="badge ' + (n > 0 ? 'badge-danger' : 'badge-secondary') + '">' + n + '</span>';
                                    }
                                },
                                {
                                    data: 'net', className: 'text-center',
                                    render: function (v) {
                                        const n = v || 0;
                                        const warna = n < 0 ? 'badge-danger' : (n > 0 ? 'badge-primary' : 'badge-secondary');
                                        return '<span class="badge ' + warna + '">' + n + '</span>';
                                    }
                                },
                                {
                                    data: 'rate', className: 'text-center',
                                    render: function (v) { return (v || 0) + ' %'; }
                                }
                            ],
                            pageLength: 10,
                            lengthMenu: [10, 25, 50, 100],
                            searching: true,
                            ordering: true,
                            order: [[2, 'desc']],
                            info: true,
                            paging: true,
                            language: {
                                emptyTable: 'Tidak ada data turn over pada bulan ini.',
                                zeroRecords: 'Tidak ada data yang cocok.'
                            }
                        });

                        const infoSewing = d.sewing === 'global'
                            ? ' Line digabung menjadi SEWING.'
                            : (d.sewing === 'factory' ? ' Line 1-42 = FACTORY 2, Line 43-84 = FACTORY 1.' : '');
                        setTeks('turnoverNote',
                            'Periode ' + (d.bulan_label || d.bulan) + ' - ' + judulDept
                            + '. Masuk = PKWT.TMK, Keluar = PKWT.TKK. Turn over = keluar / karyawan aktif x 100.' + infoSewing);
                    })
                    .catch(function (e) { console.error('Gagal memuat data turn over:', e); });
            }

            const elBulanTurnover = document.getElementById('turnoverMonth');
            if (elBulanTurnover) {
                elBulanTurnover.addEventListener('change', loadTurnover);
            }
            const elDeptTurnover = document.getElementById('turnoverDept');
            if (elDeptTurnover) {
                elDeptTurnover.addEventListener('change', loadTurnover);
            }
            const elSewingTurnover = document.getElementById('turnoverSewing');
            if (elSewingTurnover) {
                elSewingTurnover.addEventListener('change', loadTurnover);
            }
            loadTurnover();

            // On change
            const monthFilter = document.getElementById('monthFilter');
            if (monthFilter) {
                monthFilter.addEventListener('change', function () {
                    loadPKWTChart(this.value);
                });
            }
        });
    </script>
</body>

</html>