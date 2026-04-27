<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body class="bg-gradient-primary">
@include('sweetalert::alert')

<div class="container container-center">
    <div class="text-center">
        <img src="{{ asset('img/chutex.svg') }}" style="width:150px;">
        <h1 class="h4 text-white"><b>PT. Chutex International Indonesia</b></h1>
        <h1 class="h1 text-white mb-4"><b>HRIS</b></h1>
    </div>

    <!-- <div class="card shadow-lg my-5">
    <div class="card-body"> -->
        
        <!-- GRID MENU -->
        <div class="row justify-content-center">
            <!-- Menu Admin -->
            <div class="col-lg-3 col-md-4 col-6 mb-4">
                <a href="/login" class="text-decoration-none">
                    <div class="card shadow h-100 text-center menu-card">
                        <div class="card-body">
                            <i class="fas fa-users fa-3x text-primary mb-3"></i>
                            <h6 class="text-dark">Login</h6>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Menu 1 -->
            <div class="col-lg-3 col-md-4 col-6 mb-4">
                <a href="/employee-payroll" class="text-decoration-none">
                    <div class="card shadow h-100 text-center menu-card">
                        <div class="card-body">
                            <i class="fas fa-file-invoice-dollar fa-3x text-primary mb-3"></i>
                            <h6 class="text-dark">Lihat Slip</h6>
                        </div>
                    </div>
                </a>
            </div>
            <!-- Menu 2 -->
            <div class="col-lg-3 col-md-4 col-6 mb-4">
                <a href="/employee-thr" class="text-decoration-none">
                    <div class="card shadow h-100 text-center menu-card">
                        <div class="card-body">
                            <i class="fas fa-gift fa-3x text-primary mb-3"></i>
                            <h6 class="text-dark">Lihat Slip THR</h6>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Menu 3 -->
            <div class="col-lg-3 col-md-4 col-6 mb-4">
                <a href="/pengajuan-cuti/login" class="text-decoration-none">
                    <div class="card shadow h-100 text-center menu-card">
                        <div class="card-body">
                            <i class="fas fa-calendar-alt fa-3x text-success mb-3"></i>
                            <h6 class="text-dark">Pengajuan Cuti</h6>
                        </div>
                    </div>
                </a>
            </div>

        </div>

    <!-- </div>
</div> -->
</div>

@include('layout.footerscript')
</body>
</html>