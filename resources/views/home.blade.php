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
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalpkwt">Loading...</div>
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
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="sewingemployees">Loading...</div>
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
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="nonsewingemployees">Loading...</div>
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
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="deptsewing">Loading...</div>
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
                        <!-- Recruitment Flow -->
                        <div class="col-xl-8 col-lg-7">
                            <div class="card shadow mb-4">
                                <!-- Card Header - Dropdown -->
                                <div
                                    class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Recruitment vs Attrition (2024)</h6>
                                    <div class="dropdown no-arrow">
                                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in"
                                            aria-labelledby="dropdownMenuLink">
                                            <div class="dropdown-header">Options:</div>
                                            <a class="dropdown-item" href="#">View Details</a>
                                            <a class="dropdown-item" href="#">Download CSV</a>
                                        </div>
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

                        <!-- Gender Distribution -->
                        <div class="col-xl-4 col-lg-5">
                            <div class="card shadow mb-4">
                                <!-- Card Header - Dropdown -->
                                <div
                                    class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Gender Distribution</h6>
                                </div>
                                <!-- Card Body -->
                                <div class="card-body">
                                    <div class="chart-pie pt-4 pb-2" style="height: 250px;">
                                        <canvas id="genderChart"></canvas>
                                    </div>
                                    <div class="mt-4 text-center small">
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-primary"></i> Male
                                        </span>
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-success"></i> Female
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 2 -->
                    <div class="row">
                        <!-- Department Distribution -->
                        <div class="col-xl-6 col-lg-6">
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
                                                    <th>Name</th>
                                                    <th>Dept</th>
                                                    <th>Join Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Budi Santoso</td>
                                                    <td>IT</td>
                                                    <td>2024-01-15</td>
                                                    <td><span class="badge badge-success">Active</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Siti Aminah</td>
                                                    <td>Finance</td>
                                                    <td>2024-01-20</td>
                                                    <td><span class="badge badge-success">Active</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Agus Kurniawan</td>
                                                    <td>Production</td>
                                                    <td>2024-02-01</td>
                                                    <td><span class="badge badge-warning">Probation</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Rina Wati</td>
                                                    <td>HR</td>
                                                    <td>2024-02-05</td>
                                                    <td><span class="badge badge-success">Active</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Dewi Lestari</td>
                                                    <td>Marketing</td>
                                                    <td>2024-02-10</td>
                                                    <td><span class="badge badge-warning">Probation</span></td>
                                                </tr>
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
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        Chart.defaults.font.family = 'Nunito, -apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
        Chart.defaults.color = '#858796';

        document.addEventListener("DOMContentLoaded", function() {
            
            // --- 1. REAL DATA: KPI Cards ---
            fetch("{{ route('home.get-recap-count') }}")
                .then(response => response.json())
                .then(responseData => {
                    // Update KPI Cards
                    if(document.getElementById('totalpkwt')) document.getElementById('totalpkwt').textContent = responseData.totalpkwt;
                    if(document.getElementById('nonsewingemployees')) document.getElementById('nonsewingemployees').textContent = responseData.nonsewingemployees;
                    if(document.getElementById('sewingemployees')) document.getElementById('sewingemployees').textContent = responseData.sewingemployees;
                    if(document.getElementById('deptsewing')) document.getElementById('deptsewing').textContent = responseData.deptsewing;
                })
                .catch(error => console.error('Error fetching recap data:', error));

            fetch("{{ route('home.get-pkwt-chart') }}")
                .then(response => response.json())
                .then(responseData => {
                    const ctxRecruitment = document.getElementById('recruitmentChart');
                    new Chart(ctxRecruitment, {
                        type: 'bar',
                        data: {
                            labels: responseData.labels,
                            datasets: [{
                                label: 'PKWT Count',
                                data: responseData.data,
                                backgroundColor: "#4e73df",
                                hoverBackgroundColor: "#2e59d9",
                                borderColor: "#4e73df",
                                borderWidth: 1
                            }]
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
                                    ticks: { maxTicksLimit: 5, padding: 10 },
                                    grid: {
                                        color: "rgb(234, 236, 244)",
                                        zeroLineColor: "rgb(234, 236, 244)",
                                        drawBorder: false,
                                        borderDash: [2],
                                        zeroLineBorderDash: [2]
                                    }
                                },
                            },
                            plugins: {
                                legend: { display: true },
                                title: { display: true, text: 'PKWT Trend (Last 5 Months)' }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error fetching chart data:', error));

            // --- 3. FAKE DATA: Other Charts ---
            
            // Gender Distribution (Doughnut)
            // Check if element exists before creating chart to avoid errors if ID changes
            const ctxGender = document.getElementById("genderChart");
            if (ctxGender) {
                new Chart(ctxGender, {
                    type: 'doughnut',
                    data: {
                        labels: ["Male", "Female"],
                        datasets: [{
                            data: [45, 55],
                            backgroundColor: ['#4e73df', '#1cc88a'],
                            hoverBackgroundColor: ['#2e59d9', '#17a673'],
                            hoverBorderColor: "rgba(234, 236, 244, 1)",
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        tooltips: {
                            backgroundColor: "rgb(255,255,255)",
                            bodyFontColor: "#858796",
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            xPadding: 15,
                            yPadding: 15,
                            displayColors: false,
                            caretPadding: 10,
                        },
                        legend: { display: false },
                        cutout: '80%',
                    },
                });
            }

            // Department Distribution (Horizontal Bar)
            const ctxDept = document.getElementById("departmentChart");
            if (ctxDept) {
                new Chart(ctxDept, {
                    type: 'bar',
                    data: {
                        labels: ["Sewing", "Cutting", "Finishing", "Warehouse", "Office"],
                        datasets: [{
                            label: 'Employees',
                            data: [2500, 800, 1200, 400, 150],
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
        });
    </script>
</body>

</html>