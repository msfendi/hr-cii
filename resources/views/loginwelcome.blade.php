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
                        {{-- Anda dapat menambahkan tombol atau filter di sini jika diperlukan --}}
                    </div>

                    <!-- ============================================================
                    KONTEN WELCOME (dari loginwelcome.blade.php asli)
                    ============================================================ -->
                    <div class="welcome-wrapper">

                        <!-- HERO CARD -->
                        <div class="hero-card">
                            <div class="hero-content">

                                <!-- Left: Text -->
                                <div class="hero-text">
                                    <div class="hero-badge">
                                        <i class="fas fa-sparkles"></i>
                                        <span>Welcome back, Admin</span>
                                    </div>
                                    <h1>
                                        Your HRIS<br />
                                        <span>System</span>
                                    </h1>
                                    <p>
                                        Manage human resources, payroll, and order reconciliation
                                        seamlessly from one unified dashboard.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION TITLE -->
                        <div class="d-flex flex-wrap align-items-end justify-content-between mb-3">
                            <div>
                                <h2 class="section-title">Core Modules</h2>
                                <p class="section-subtitle">Your primary HRIS tools, all in one place.</p>
                            </div>
                        </div>

                        <!-- MODULE CARDS -->
                        <div class="module-grid">

                            <!-- HRIS -->
                            <div class="module-card">
                                <div class="card-glow" style="background:linear-gradient(135deg,#2563eb,#3b82f6);"></div>
                                <div class="icon-wrap blue">
                                    <i class="fas fa-users-cog"></i>
                                </div>
                                <h3>HRIS</h3>
                                <p>
                                    Complete employee lifecycle management — from onboarding, attendance, terminating, and more.
                                </p>
                            </div>

                            <!-- Payroll -->
                            <div class="module-card">
                                <div class="card-glow" style="background:linear-gradient(135deg,#059669,#10b981);"></div>
                                <div class="icon-wrap green">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <h3>Payroll</h3>
                                <p>
                                    Automated salary processing, payslips,
                                    and full compliance with Indonesian labor regulations.
                                </p>
                            </div>

                            <!-- Order Reconciliation -->
                            <div class="module-card">
                                <div class="card-glow" style="background:linear-gradient(135deg,#7c3aed,#8b5cf6);"></div>
                                <div class="icon-wrap purple">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <h3>Order Rekonsiliasi</h3>
                                <p>
                                    Match order on hand, material purchases, and bill of materials with
                                    real-time reconciliation and reporting.
                                </p>
                            </div>

                        </div>
                    </div>
                    <!-- end welcome-wrapper -->

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            @include('layout.footer')
        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- ============================================================
    STYLE KHUSUS UNTUK WELCOME (dipindahkan dari head asli)
    ============================================================ -->
    <style>
        /* semua gaya dari loginwelcome.blade.php asli */
        .welcome-wrapper {
            max-width: 1440px;
            width: 100%;
            margin: 0 auto;
        }

        .hero-card {
            background: linear-gradient(135deg, #0f2b4b 0%, #1a4a7a 50%, #2d6fa3 100%);
            border-radius: 32px;
            padding: 48px 56px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 20, 40, 0.35);
            margin-bottom: 40px;
        }

        .hero-card::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.06) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: 20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.04) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
        }

        .hero-text h1 {
            font-weight: 800;
            font-size: 2.6rem;
            letter-spacing: -0.03em;
            line-height: 1.2;
            margin-bottom: 12px;
        }

        .hero-text h1 span {
            background: linear-gradient(to right, #fcd34d, #fbbf24);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-text p {
            font-size: 1.1rem;
            opacity: 0.85;
            max-width: 500px;
            font-weight: 400;
            line-height: 1.6;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(6px);
            padding: 8px 20px;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.10);
            margin-top: 16px;
        }

        .hero-badge i {
            color: #fbbf24;
        }

        .hero-avatar-group {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .hero-avatar-group .avatars {
            display: flex;
        }

        .hero-avatar-group .avatars img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.7);
            margin-right: -12px;
            object-fit: cover;
            background: #2d6fa3;
        }

        .hero-avatar-group .avatars img:last-child {
            margin-right: 0;
        }

        .hero-avatar-group .avatars .avatar-more {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.7);
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            color: #fff;
            margin-right: 0;
        }

        .hero-stats-mini {
            display: flex;
            gap: 30px;
            background: rgba(255, 255, 255, 0.06);
            padding: 12px 28px;
            border-radius: 60px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .hero-stats-mini .stat-item {
            text-align: center;
        }

        .hero-stats-mini .stat-item .number {
            font-weight: 700;
            font-size: 1.3rem;
            display: block;
        }

        .hero-stats-mini .stat-item .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.7;
        }

        .section-title {
            font-weight: 700;
            font-size: 1.6rem;
            color: #0f2b4b;
            margin-bottom: 8px;
        }

        .section-subtitle {
            color: #64748b;
            font-weight: 400;
            margin-bottom: 28px;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .module-card {
            background: #fff;
            border-radius: 24px;
            padding: 32px 28px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
            transition: transform 0.25s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
            cursor: default;
        }

        .module-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px -12px rgba(0, 20, 40, 0.15);
        }

        .module-card .card-glow {
            position: absolute;
            top: -40px;
            right: -40px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            opacity: 0.06;
            pointer-events: none;
        }

        .module-card .icon-wrap {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #fff;
            margin-bottom: 18px;
        }

        .module-card .icon-wrap.blue {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
        }
        .module-card .icon-wrap.green {
            background: linear-gradient(135deg, #059669, #10b981);
        }
        .module-card .icon-wrap.purple {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6);
        }

        .module-card h3 {
            font-weight: 700;
            font-size: 1.25rem;
            color: #0f2b4b;
            margin-bottom: 6px;
        }

        .module-card p {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .module-card .meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        .module-card .meta-row .stat-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 100px;
            background: #f1f5f9;
            color: #334155;
        }

        .module-card .meta-row .stat-badge i {
            margin-right: 4px;
        }

        .module-card .meta-row .link-arrow {
            color: #2563eb;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: gap 0.2s;
        }

        .module-card .meta-row .link-arrow:hover {
            gap: 12px;
            color: #1d4ed8;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-bottom: 40px;
        }

        .quick-stat-item {
            background: #fff;
            border-radius: 18px;
            padding: 20px 22px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: background 0.2s;
        }

        .quick-stat-item:hover {
            background: #fafcff;
        }

        .quick-stat-item .qs-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .quick-stat-item .qs-icon.orange {
            background: #fef3c7;
            color: #d97706;
        }
        .quick-stat-item .qs-icon.cyan {
            background: #cffafe;
            color: #0891b2;
        }
        .quick-stat-item .qs-icon.rose {
            background: #fce7f3;
            color: #db2777;
        }
        .quick-stat-item .qs-icon.indigo {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .quick-stat-item .qs-info .qs-number {
            font-weight: 700;
            font-size: 1.25rem;
            color: #0f2b4b;
            line-height: 1.2;
        }

        .quick-stat-item .qs-info .qs-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
        }

        .recent-card {
            background: #fff;
            border-radius: 24px;
            padding: 28px 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.02);
            margin-bottom: 30px;
        }

        .recent-card .card-header-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .recent-card .card-header-custom h5 {
            font-weight: 700;
            color: #0f2b4b;
            margin: 0;
        }

        .recent-card .card-header-custom a {
            font-size: 0.85rem;
            font-weight: 600;
            color: #2563eb;
            text-decoration: none;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item .av {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e7ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #4f46e5;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .activity-item .act-content {
            flex: 1;
        }

        .activity-item .act-content .act-title {
            font-weight: 600;
            color: #0f2b4b;
            font-size: 0.95rem;
        }

        .activity-item .act-content .act-desc {
            font-size: 0.85rem;
            color: #64748b;
        }

        .activity-item .act-time {
            font-size: 0.75rem;
            color: #94a3b8;
            white-space: nowrap;
        }

        /* Animasi */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .module-card {
            animation: fadeUp 0.5s ease forwards;
        }
        .module-card:nth-child(2) {
            animation-delay: 0.08s;
        }
        .module-card:nth-child(3) {
            animation-delay: 0.16s;
        }

        .quick-stat-item {
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.1s;
            opacity: 0;
        }
        .quick-stat-item:nth-child(2) {
            animation-delay: 0.18s;
        }
        .quick-stat-item:nth-child(3) {
            animation-delay: 0.26s;
        }
        .quick-stat-item:nth-child(4) {
            animation-delay: 0.34s;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-card {
                padding: 32px 24px;
                border-radius: 24px;
            }
            .hero-text h1 {
                font-size: 2rem;
            }
            .hero-stats-mini {
                flex-wrap: wrap;
                gap: 16px;
                padding: 12px 18px;
                border-radius: 30px;
            }
            .hero-avatar-group .avatars img {
                width: 36px;
                height: 36px;
            }
            .module-grid {
                grid-template-columns: 1fr;
            }
            .quick-stats {
                grid-template-columns: 1fr 1fr;
            }
            .recent-card {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .quick-stats {
                grid-template-columns: 1fr;
            }
            .hero-text h1 {
                font-size: 1.6rem;
            }
        }
    </style>

</body>
</html>