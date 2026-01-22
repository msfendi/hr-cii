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

                @if ($message = Session::get('warning'))
                <div class="alert alert-warning alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>	
                    <strong>{{ $message }}</strong>
                </div>
                @endif

                @if ($message = Session::get('info'))
                <div class="alert alert-info alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>	
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                
                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                </div>
                <div class="row">
                    {{-- @if($roleusers[0]->rolename == 'Admin') --}}
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total PKWT</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalpkwt"></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- @endif --}}
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Department Non Sewing</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="deptnonsewing"></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Total Department Sewing</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="deptsewing"></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Total Revision Document</div>
                                        {{-- <div class="h5 mb-0 font-weight-bold text-gray-800">{{ ($totalrevision[0]->total) }}</div> --}}
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-retweet fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-secondary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                            Total Uploaded Document</div>
                                        {{-- <div class="h5 mb-0 font-weight-bold text-gray-800">{{ ($totaldocument[0]->total) }}</div> --}}
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-upload fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Chutex E-Signature Flow</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center" style="width: 100%; height: 400px;">
                            {{-- ChartJS --}}
                            <canvas id="karyawanChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <!-- End of Main Content -->

@include('layout.footer')
</body>
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        fetch("{{ route('home.get-pkwt-chart') }}")
            .then(response => response.json())
            .then(responseData => {
                const labels = responseData.labels;
                const chartData = responseData.data;

                const data = {
                    labels: labels,
                    datasets: [{
                        label: 'Karyawan Baru (PKWT)',
                        data: chartData,
                        borderColor: '#4e73df',
                        fill: true,
                        tension: 0.3
                    }]
                };

                const config = {
                    type: 'line',
                    data: data,
                    options: {
                        interaction: {
                            intersect: false,
                            mode: 'index',
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'PKWT Trend (Last 5 Months)'
                            },
                            legend: {
                                display: true
                            }
                        },
                        responsive: true,
                        maintainAspectRatio: false,
                    }
                };

                const ctx = document.getElementById('karyawanChart');
                new Chart(ctx, config);
            })
            .catch(error => console.error('Error fetching chart data:', error));

            fetch("{{ route('home.get-recap-count') }}")
            .then(response => response.json())
            .then(responseData => {
                const totalpkwt = responseData.totalpkwt;
                const deptnonsewing = responseData.deptnonsewing;
                const deptsewing = responseData.deptsewing;

                document.getElementById('totalpkwt').textContent = totalpkwt;
                document.getElementById('deptnonsewing').textContent = deptnonsewing;
                document.getElementById('deptsewing').textContent = deptsewing;
            })
            .catch(error => console.error('Error fetching recap data:', error));
    });
</script>
</html>