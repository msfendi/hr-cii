<!DOCTYPE html>
<html lang="en">

@include('layout.header')

<body class="bg-gradient-primary">

@include('sweetalert::alert')

<style>
    .menu-wrapper .row{
        --bs-gutter-x: 1.5rem;
        --bs-gutter-y: 3rem;   /* tambah jarak antar baris */
    }
    .menu-card{
        margin-bottom: 10px;
    }

    @media (min-width:992px){

        .menu-wrapper .row{
            row-gap:50px;
            column-gap:0;
        }

    }
    body.bg-gradient-primary{
        background:linear-gradient(135deg,#1e3c72 0%,#2a5298 45%,#6dd5ed 100%);
        min-height:100vh;
        overflow-x:hidden;
    }

    .container-center{
        min-height:100vh;
        display:flex;
        flex-direction:column;
        justify-content:center;
        align-items:center;
        padding:40px 20px;
    }

    .hris-logo{
        width:150px;
        filter:drop-shadow(0 5px 15px rgba(0,0,0,.3));
        animation:fadeDown .8s;
    }

    .brand-name{
        color:#fff;
        font-weight:700;
        text-shadow:0 3px 10px rgba(0,0,0,.25);
        margin-bottom:5px;
    }

    .app-name{
        color:#fff;
        font-weight:300;
        letter-spacing:4px;
        text-shadow:0 3px 10px rgba(0,0,0,.25);
        margin-bottom:45px;
    }

    .menu-wrapper{
        width:100%;
        max-width:1400px;
        margin:auto;
    }

    .menu-card{
        border:none;
        border-radius:18px;
        background:rgba(255,255,255,.95);
        backdrop-filter:blur(8px);
        transition:.35s;
        overflow:hidden;
        height:185px;
        display:flex;
        justify-content:center;
        align-items:center;
        position:relative;
    }

    .menu-card::before{
        content:'';
        position:absolute;
        top:0;
        left:0;
        width:100%;
        height:5px;
        background:linear-gradient(90deg,#4facfe,#00f2fe);
        transform:scaleX(0);
        transition:.3s;
        transform-origin:left;
    }

    .menu-card:hover::before{
        transform:scaleX(1);
    }

    .menu-card:hover{
        transform:translateY(-8px);
        box-shadow:0 18px 35px rgba(0,0,0,.25);
    }

    .menu-icon-wrapper{
        width:80px;
        height:80px;
        margin:auto auto 18px;
        border-radius:50%;
        display:flex;
        justify-content:center;
        align-items:center;
        background:linear-gradient(135deg,#edf7ff,#ffffff);
        box-shadow:inset 0 0 0 2px rgba(79,172,254,.2);
        transition:.3s;
    }

    .menu-card:hover .menu-icon-wrapper{
        background:linear-gradient(135deg,#4facfe,#00f2fe);
    }

    .menu-card:hover i{
        color:#fff !important;
    }

    .menu-icon-wrapper i{
        font-size:34px;
    }

    .menu-card h5{
        font-size:18px;
        margin:0;
        font-weight:600;
        color:#555;
    }

    @keyframes fadeDown{
        from{
            opacity:0;
            transform:translateY(-20px);
        }
        to{
            opacity:1;
            transform:translateY(0);
        }
    }

    @media (max-width:991px){

        .menu-card{
            height:170px;
        }

    }

    @media (max-width:768px){

        .brand-name{
            font-size:22px;
        }

        .app-name{
            font-size:42px;
        }

        .menu-card{
            height:155px;
        }

    }

    @media (max-width:576px){

        .container-center{
            padding:30px 15px;
        }

        .hris-logo{
            width:120px;
        }

        .brand-name{
            font-size:18px;
        }

        .app-name{
            font-size:34px;
            margin-bottom:30px;
        }

        .menu-card{
            height:145px;
        }

        .menu-icon-wrapper{
            width:65px;
            height:65px;
        }

        .menu-icon-wrapper i{
            font-size:28px;
        }

        .menu-card h5{
            font-size:15px;
        }

    }

</style>

<div class="container-fluid container-center">

    <div class="text-center">

        <img src="{{ asset('img/chutex.svg') }}" class="hris-logo mb-3">

        <h2 class="brand-name">
            PT. Chutex International Indonesia
        </h2>

        <h1 class="app-name">
            HRIS
        </h1>

    </div>

    <div class="menu-wrapper">
        <div class="row gx-4 gy-5 justify-content-center">

            <!-- Login -->
            <div class="col-lg-3 col-md-4 col-6">

                <a href="/login" class="text-decoration-none">

                    <div class="card menu-card shadow">

                        <div class="card-body text-center">

                            <div class="menu-icon-wrapper">
                                <i class="fas fa-users text-primary"></i>
                            </div>

                            <h5>Login</h5>

                        </div>

                    </div>

                </a>

            </div>

            <!-- Slip -->
            <div class="col-lg-3 col-md-4 col-6">

                <a href="/employee-payroll" class="text-decoration-none">

                    <div class="card menu-card shadow">

                        <div class="card-body text-center">

                            <div class="menu-icon-wrapper">
                                <i class="fas fa-file-invoice-dollar text-primary"></i>
                            </div>

                            <h5>Lihat Slip</h5>

                        </div>

                    </div>

                </a>

            </div>

            <!-- Career -->
            <div class="col-lg-3 col-md-4 col-6">

                <a href="/lowongan" class="text-decoration-none">

                    <div class="card menu-card shadow">

                        <div class="card-body text-center">

                            <div class="menu-icon-wrapper">
                                <i class="fas fa-briefcase text-success"></i>
                            </div>

                            <h5>Career</h5>

                        </div>

                    </div>

                </a>

            </div>
            <!-- Recruitment Announcement -->
            <div class="col-lg-3 col-md-4 col-6">

                <a href="/portal-recruitment-status" class="text-decoration-none">

                    <div class="card menu-card shadow">

                        <div class="card-body text-center">

                            <div class="menu-icon-wrapper">
                                <i class="fa fa-bullhorn text-success"></i>
                            </div>

                            <h5>Recruitment Announcement</h5>

                        </div>

                    </div>

                </a>

            </div>

            <!-- Food Orders -->
            <!-- <div class="col-lg-3 col-md-4 col-6">

                <a href="/food-orders/scan" class="text-decoration-none">

                    <div class="card menu-card shadow">

                        <div class="card-body text-center">

                            <div class="menu-icon-wrapper">
                                <i class="fas fa-utensils text-primary"></i>
                            </div>

                            <h5>Food Orders</h5>

                        </div>

                    </div>

                </a>

            </div> -->

            <!-- Tambahkan menu berikutnya di sini -->
            <!-- <div class="col-lg-3 col-md-4 col-6">

                <a href="#" class="text-decoration-none">

                    <div class="card menu-card shadow">

                        <div class="card-body text-center">

                            <div class="menu-icon-wrapper">
                                <i class="fas fa-calendar text-warning"></i>
                            </div>

                            <h5>Pengajuan Cuti</h5>

                        </div>

                    </div>

                </a>

            </div> -->

        </div>

    </div>

</div>

@include('layout.footerscript')

</body>
</html>