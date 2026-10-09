<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<style>
    /* ── Variables & Setup ── */
    body {
        font-family: 'Nunito', sans-serif;
    }

    /* Background */
    #content-wrapper {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 55%, #1a3a8f 100%) !important;
        min-height: 100vh;
    }

    /* Glassmorphism navbar */
    .topbar {
        position: relative;
        z-index: 999;
        background: rgba(255, 255, 255, .12) !important;
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, .15) !important;
        box-shadow: none !important;
    }

    .topbar .brand-name {
        color: #fff !important;
        font-weight: 800;
        font-size: .92rem;
    }

    .topbar .nav-link {
        color: rgba(255, 255, 255, .85) !important;
        font-size: .83rem;
        font-weight: 600;
        padding: .3rem .65rem !important;
        border-radius: .4rem;
    }

    .topbar .nav-link:hover {
        color: #fff !important;
        background: rgba(255, 255, 255, .12);
    }

    .topbar .nav-link.act {
        color: #fff !important;
        background: rgba(255, 255, 255, .18);
        border-bottom: 2px solid rgba(255, 255, 255, .7);
    }

    .topbar .btn-keluar {
        color: #fff !important;
        border-color: rgba(255, 255, 255, .4) !important;
        background: rgba(255, 255, 255, .1) !important;
        font-size: .75rem !important;
    }

    .topbar .btn-keluar:hover {
        background: rgba(255, 255, 255, .22) !important;
    }

    .topbar .navbar-toggler {
        border-color: rgba(255, 255, 255, .4) !important;
        padding: .2rem .4rem;
    }

    .topbar .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255,0.85)' stroke-linecap='round' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    @media(max-width:767.98px) {
        .topbar .navbar-collapse {
            background: rgba(26, 58, 143, .96);
            border-top: 1px solid rgba(255, 255, 255, .12);
            margin-top: .25rem;
            padding: .45rem .5rem .55rem;
            border-radius: 0 0 .5rem .5rem;
        }

        .topbar .nav-link {
            padding: .38rem .25rem !important;
        }

        .topbar .d-flex.ml-auto {
            margin-top: .4rem;
            padding-top: .4rem;
            border-top: 1px solid rgba(255, 255, 255, .1);
            width: 100%;
            justify-content: space-between;
        }
    }

    /* Heading */
    .pg-title {
        color: #fff !important;
        font-weight: 800;
        font-size: 1.05rem;
    }

    .pg-sub {
        color: rgba(255, 255, 255, .72) !important;
        font-size: .78rem;
    }

    /* ── UI Elements ── */
    .main-card {
        background: #fff;
        border: none;
        border-radius: .8rem;
        overflow: hidden;
        box-shadow: 0 .5rem 2.5rem rgba(0, 0, 0, .2);
        margin: 10px auto;
        width: 100%;
        text-align: center;
    }

    .mc-header {
        padding: .75rem 1.25rem;
        border-bottom: 1px solid #eaecf4;
        background: #f8f9fc;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .5rem;
        font-size: .95rem;
    }

    /* ── Stepper ── */
    .stepper-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 2rem;
    }

    .stepper-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        position: relative;
    }

    .stepper-item:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 18px;
        left: 50%;
        width: 100%;
        height: 3px;
        background: #e3e6f0;
        z-index: 0;
        transition: background .3s;
    }

    .stepper-item.completed:not(:last-child)::after {
        background: #4e73df;
    }

    .stepper-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #e3e6f0;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        z-index: 1;
        transition: all .3s;
    }

    .stepper-item.active .stepper-circle {
        background: #4e73df;
        transform: scale(1.15);
        box-shadow: 0 0 0 4px rgba(78, 115, 223, .15);
    }

    .stepper-item.completed .stepper-circle {
        background: #1cc88a;
    }

    .stepper-label {
        margin-top: .5rem;
        font-size: .8rem;
        font-weight: 600;
        color: #b7b9cc;
    }

    .stepper-item.active .stepper-label {
        color: #4e73df;
    }

    .stepper-item.completed .stepper-label {
        color: #1cc88a;
    }

    /* ── Panels & Review Rows ── */
    .step-panel {
        display: none;
        animation: fadeIn .4s ease;
    }

    .step-panel.active {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .review-row {
        display: flex;
        justify-content: space-between;
        padding: .65rem 0;
        border-bottom: 1px solid #f0f2f8;
        font-size: .85rem;
    }

    .review-row:last-child {
        border-bottom: none;
    }

    .review-label {
        color: #858796;
    }

    .review-value {
        font-weight: 700;
        color: #3a3b45;
        text-align: right;
        max-width: 60%;
    }

    /* Form Field Adjustments */
    .form-container {
        max-width: 700px;
        margin: 0 auto;
    }

    /* Leave blocks (multi pengajuan) */
    .leave-block {
        text-align: left;
        background: #fbfbfe;
        transition: box-shadow .2s;
    }

    .leave-block:hover {
        box-shadow: 0 .15rem .75rem rgba(0, 0, 0, .06);
    }

    .btn-remove-leave {
        line-height: 1;
    }

    /* Collapsible Saldo */
    [data-toggle="collapse"][aria-expanded="true"] .transition-icon {
        transform: rotate(180deg);
    }

    /* Footer */
    .sticky-footer {
        background: rgba(255, 255, 255, .08) !important;
        border-top: 1px solid rgba(255, 255, 255, .12) !important;
    }

    .sticky-footer span {
        color: rgba(255, 255, 255, .5) !important;
        font-size: .74rem;
    }

    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-up {
        animation: fadeUp .4s ease;
    }

    /* Flatpickr — selaraskan warna dengan tema biru aplikasi */
    .flatpickr-day.selected,
    .flatpickr-day.selected:hover {
        background: #4e73df;
        border-color: #4e73df;
    }

    .flatpickr-day.today {
        border-color: #4e73df;
    }

    .flatpickr-day.flatpickr-disabled,
    .flatpickr-day.flatpickr-disabled:hover {
        color: #d3d6e0;
        text-decoration: line-through;
    }

    /* ── Component Upload File & Preview ── */
    .file-upload-zone {
        border: 2px dashed #b7c9f8;
        background: #f8faff;
        border-radius: 12px;
        padding: 1.5rem 1.25rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s ease;
        position: relative;
    }

    .file-upload-zone:hover {
        border-color: #4e73df;
        background: #f0f4ff;
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(78, 115, 223, 0.08);
    }

    .file-upload-zone.dragover {
        border-color: #224abe;
        background: #e8efff;
        box-shadow: 0 0 0 4px rgba(78, 115, 223, 0.18);
        transform: scale(1.01);
    }

    .file-upload-icon-circle {
        width: 52px;
        height: 52px;
        margin: 0 auto 0.75rem auto;
        border-radius: 50%;
        background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        color: #4338ca;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        transition: all 0.25s ease;
    }

    .file-upload-zone:hover .file-upload-icon-circle {
        transform: scale(1.1);
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        color: #fff;
    }

    .file-upload-zone .upload-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 0.25rem;
    }

    .file-upload-zone .upload-subtitle {
        font-size: 0.76rem;
        color: #64748b;
        margin-bottom: 0.6rem;
    }

    .file-upload-zone .upload-badges {
        display: flex;
        justify-content: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .file-upload-zone .upload-badge {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 20px;
        background: #e2e8f0;
        color: #475569;
        letter-spacing: 0.4px;
        display: inline-flex;
        align-items: center;
    }

    /* File Previews Grid */
    .file-preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
        gap: 12px;
    }

    @media (max-width: 576px) {
        .file-preview-grid {
            grid-template-columns: 1fr;
        }
    }

    .file-preview-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        position: relative;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
    }

    .file-preview-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
    }

    .file-preview-thumb {
        height: 105px;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
        border-bottom: 1px solid #f1f5f9;
    }

    .file-preview-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .file-preview-card:hover .file-preview-thumb img {
        transform: scale(1.05);
    }

    .file-preview-thumb .doc-icon-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .file-preview-thumb .doc-icon-wrapper i {
        font-size: 2.5rem;
    }

    .doc-icon-pdf {
        color: #ef4444;
    }

    .doc-icon-word {
        color: #2563eb;
    }

    .doc-icon-image {
        color: #10b981;
    }

    .doc-icon-file {
        color: #64748b;
    }

    .file-type-pill {
        position: absolute;
        bottom: 6px;
        right: 6px;
        font-size: 0.62rem;
        font-weight: 800;
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
        z-index: 1;
    }

    .pill-pdf {
        background: #fee2e2;
        color: #b91c1c;
    }

    .pill-doc {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .pill-img {
        background: #d1fae5;
        color: #047857;
    }

    .pill-file {
        background: #f1f5f9;
        color: #475569;
    }

    .file-preview-body {
        padding: 8px 10px;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        background: #fff;
    }

    .file-preview-name {
        font-size: 0.78rem;
        font-weight: 700;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
    }

    .file-preview-size {
        font-size: 0.7rem;
        color: #64748b;
        margin-top: 3px;
    }

    .file-remove-btn {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid rgba(0, 0, 0, 0.08);
        color: #ef4444;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        cursor: pointer;
        padding: 0;
        z-index: 2;
        transition: all 0.2s;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
    }

    .file-remove-btn:hover {
        background: #ef4444;
        color: #fff;
        border-color: #ef4444;
        transform: scale(1.1);
    }

    /* Review Chip Badges */
    .review-file-badge {
        display: inline-flex;
        align-items: center;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 6px 10px;
        margin-right: 6px;
        margin-bottom: 6px;
        font-size: 0.78rem;
        color: #334155;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        transition: all 0.2s;
    }

    .review-file-badge:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
    }

    /* Reason Radio Options Styling */
    .leave-reason-radio-card {
        border: 1.5px solid #e3e6f0;
        border-radius: 8px;
        padding: 9px 14px;
        margin-bottom: 8px;
        background: #ffffff;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        display: flex;
        align-items: center;
    }

    .leave-reason-radio-card:hover {
        border-color: #4e73df;
        background: #f8faff;
    }

    .leave-reason-radio-card.is-selected {
        border-color: #4e73df;
        background: #edf2ff;
        box-shadow: 0 2px 5px rgba(78, 115, 223, 0.12);
    }

    .leave-reason-radio-card .custom-control-label {
        cursor: pointer;
        font-size: 0.88rem;
        user-select: none;
        width: 100%;
        margin-bottom: 0;
    }

    .leave-reason-radio-card .custom-control-input:checked ~ .custom-control-label {
        color: #224abe;
        font-weight: 700;
    }
</style>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<body id="page-top">
    @include('sweetalert::alert')

    <div id="wrapper">
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">

                {{-- Navbar --}}
                <nav class="navbar navbar-expand-md navbar-light bg-white topbar mb-4 static-top shadow">
                    <div class="d-flex align-items-center">
                        <img src="{{ asset('img/chutex.svg') }}" style="width:36px;" class="mr-2">
                        <span class="font-weight-bold text-white" style="font-size:1.05rem;">E-HRIS</span>
                    </div>
                    <button class="navbar-toggler ml-auto" type="button" data-toggle="collapse"
                        data-target="#navbarCuti" aria-controls="navbarCuti" aria-expanded="false"
                        aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarCuti">
                        <ul class="navbar-nav mr-auto ml-3">
                            <li class="nav-item">
                                <a class="nav-link act" href="{{ route('pengajuan-cuti.form') }}">
                                    <i class="fas fa-file-alt fa-sm mr-1"></i> Pengajuan Cuti
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('pengajuan-cuti.riwayat') }}">
                                    <i class="fas fa-tasks fa-sm mr-1"></i> Riwayat Pengajuan
                                </a>
                            </li>
                            @if (session('cuti_employee_npk') && \App\Models\ApprovalRule::where('approval_id', session('cuti_employee_npk'))->exists())
                                <li
                                    class="nav-item {{ request()->routeIs('pengajuan-cuti.portal-approval*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('pengajuan-cuti.portal-approval') }}">
                                        <i class="fas fa-fw fa-check-circle"></i>
                                        <span>Approval Cuti</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                        <div class="d-flex align-items-center ml-auto">
                            <span class="mr-3 text-white small d-none d-md-inline" style="opacity:0.9;">
                                <i class="fas fa-user-circle"></i>
                                {{ $employee->NAMA_KARYAWAN }} &mdash; {{ $employee->DEPARTEMENT }}
                            </span>
                            <a href="{{ route('pengajuan-cuti.login') }}" class="btn btn-sm btn-keluar">
                                <i class="fas fa-sign-out-alt fa-sm"></i> Keluar
                            </a>
                        </div>
                    </div>
                </nav>

                {{-- ── Main Content ── --}}
                <div class="container-fluid px-3 px-md-4 fade-up" style="max-width: 900px; padding-bottom: 2rem;">

                    {{-- Header --}}
                    <div class="mb-4 text-white">
                        <h1 class="h3 font-weight-bold mb-1"><i class="fas fa-calendar-check mr-2"></i>Pengajuan Cuti
                        </h1>
                        <p class="mb-0" style="font-size:.9rem; opacity:0.9;">Isi form di bawah untuk mengajukan cuti
                            Anda kepada atasan. Anda bisa menambahkan lebih dari satu jenis cuti sekaligus — setiap
                            jenis cuti akan diajukan sebagai pengajuan approval yang terpisah, jenis cuti yang sama
                            hanya boleh dipilih sekali, dan tanggalnya tidak boleh tumpang tindih antar pengajuan.</p>
                    </div>

                    {{-- Form Card --}}
                    <div class="main-card text-left mb-4">
                        <div class="mc-header">
                            <span class="mc-title font-weight-bold" style="color: #3a3b45;"><i
                                    class="fas fa-file-signature text-primary mr-2"></i>Formulir Pengajuan Cuti</span>
                        </div>
                        <div class="card-body p-4 p-md-5">

                            {{-- Stepper UI --}}
                            <div class="stepper-wrapper">
                                <div class="stepper-item active" id="step-ind-1">
                                    <div class="stepper-circle" id="sc-1"><i class="fas fa-file-alt fa-sm"></i></div>
                                    <div class="stepper-label">Isi Form</div>
                                </div>
                                <div class="stepper-item" id="step-ind-2">
                                    <div class="stepper-circle" id="sc-2"><i class="fas fa-search fa-sm"></i></div>
                                    <div class="stepper-label">Review Data</div>
                                </div>
                            </div>

                            {{-- Final Form Content --}}
                            <form id="cuti-form" action="{{ route('pengajuan-cuti.submit-form') }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="npk" value="{{ $employee->NPK }}">
                                <input type="hidden" name="nama" value="{{ $employee->NAMA_KARYAWAN }}">
                                <input type="hidden" name="bagian" value="{{ $employee->DEPARTEMENT }}">

                                {{-- ══ STEP 1: Form Input ══ --}}
                                <div class="step-panel active" id="panel-1">
                                    <div class="form-container">
                                        {{-- Karyawan Profil Box --}}
                                        <div class="alert alert-primary d-flex align-items-center mb-4 border-0"
                                            style="background:#eef2cf; border-radius:.6rem;">
                                            <i class="fas fa-id-badge fa-2x text-primary mr-3"></i>
                                            <div>
                                                <div class="font-weight-bold text-gray-900" style="font-size:.95rem;">
                                                    {{ $employee->NAMA_KARYAWAN }}
                                                </div>
                                                <div class="text-muted" style="font-size:.8rem;">{{ $employee->NPK }}
                                                    &middot; {{ $employee->DEPARTEMENT }}</div>
                                            </div>
                                        </div>

                                        {{-- Saldo Cuti Karyawan (Collapsible Panel) --}}
                                        @if(isset($leaveBalances) && $leaveBalances->count() > 0)
                                            <div class="card border mb-4"
                                                style="border-radius: 0.65rem; border-color: #e3e6f0; background: #fff; overflow: hidden;">
                                                <a href="#collapseSaldoCuti"
                                                    class="d-block card-header py-2 px-3 text-decoration-none"
                                                    data-toggle="collapse" role="button" aria-expanded="false"
                                                    aria-controls="collapseSaldoCuti"
                                                    style="background: #f8f9fc; border: 0;">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-wallet text-primary mr-2"
                                                                style="font-size: 0.92rem;"></i>
                                                            <span class="font-weight-bold text-gray-800"
                                                                style="font-size: 0.85rem;">Sisa Saldo Cuti
                                                                ({{ date('Y') }})</span>
                                                        </div>
                                                        <div class="d-flex align-items-center text-primary font-weight-bold"
                                                            style="font-size: 0.78rem;">
                                                            <span class="mr-1">Lihat Rincian</span>
                                                            <i class="fas fa-chevron-down transition-icon"
                                                                style="font-size: 0.7rem; transition: transform 0.25s ease;"></i>
                                                        </div>
                                                    </div>
                                                </a>
                                                <div class="collapse" id="collapseSaldoCuti">
                                                    <div class="card-body p-3 pt-2 border-top"
                                                        style="border-color: #edf2f7 !important;">
                                                        <div class="row pt-1">
                                                            @foreach($leaveBalances as $bal)
                                                                @php
                                                                    $sisa = (int) $bal->remained_days;
                                                                    $used = (int) $bal->used_days;
                                                                    $badgeBg = $sisa <= 2 ? '#fef2f2' : ($sisa <= 5 ? '#fefce8' : '#f0fdf4');
                                                                    $badgeBorder = $sisa <= 2 ? '#fecaca' : ($sisa <= 5 ? '#fef08a' : '#bbf7d0');
                                                                    $badgeText = $sisa <= 2 ? '#b91c1c' : ($sisa <= 5 ? '#a16207' : '#15803d');
                                                                @endphp
                                                                <div
                                                                    class="col-sm-6 {{ $leaveBalances->count() >= 3 ? 'col-lg-4' : '' }} mb-2">
                                                                    <div class="card h-100 border shadow-none"
                                                                        style="border-radius: 0.55rem; border-color: #e3e6f0 !important; background: #fafbfc;">
                                                                        <div
                                                                            class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                                                                            <div
                                                                                class="d-flex justify-content-between align-items-center mb-1">
                                                                                <span
                                                                                    class="font-weight-bold text-gray-800 text-truncate mr-2"
                                                                                    style="font-size: 0.83rem;"
                                                                                    title="{{ $bal->leave_type_name }}">
                                                                                    {{ $bal->leave_type_name }}
                                                                                </span>
                                                                            </div>
                                                                            <div class="d-flex align-items-center justify-content-between text-muted"
                                                                                style="font-size: 0.74rem;">
                                                                                <span class="badge px-2 py-1 font-weight-bold"
                                                                                    style="background: {{ $badgeBg }}; color: {{ $badgeText }}; border: 1px solid {{ $badgeBorder }}; border-radius: 20px; font-size: 0.7rem; white-space: nowrap;">
                                                                                    {{ $sisa }} hari sisa
                                                                                </span>
                                                                                <span></span>
                                                                                <span><i
                                                                                        class="fas fa-history mr-1 font-weight-bold text-gray-800"></i>
                                                                                    Terpakai:</span>
                                                                                <span
                                                                                    class="font-weight-bold text-gray-600">{{ $used }}
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Kontainer blok cuti — diisi lewat JS agar bisa ditambah/dihapus dinamis
                                        --}}
                                        <div id="leaves-container"></div>

                                        <div class="text-center mb-4">
                                            <button type="button" class="btn btn-outline-primary btn-sm px-3"
                                                id="btnTambahCuti">
                                                <i class="fas fa-plus mr-1"></i> Tambah Pengajuan Cuti Lain
                                            </button>
                                        </div>

                                        <div class="d-flex justify-content-between border-top pt-4 mt-2">
                                            <a href="{{ route('pengajuan-cuti.login') }}"
                                                class="btn btn-light text-gray-700 px-4">Batal</a>
                                            <button type="button" class="btn btn-primary px-4 shadow-sm"
                                                onclick="goToReview()">Review <i
                                                    class="fas fa-arrow-right ml-1"></i></button>
                                        </div>
                                    </div>
                                </div>


                                {{-- ══ STEP 2: Review Form ══ --}}
                                <div class="step-panel" id="panel-2">
                                    <div class="form-container">
                                        <h6 class="text-primary font-weight-bold mb-3"><i
                                                class="fas fa-clipboard-check mr-2"></i>Review Pengajuan</h6>
                                        <p class="text-muted small mb-4">Pastikan data pengajuan cuti Anda di bawah ini
                                            sudah benar sebelum melakukan konfirmasi akhir. Setiap jenis cuti akan
                                            diajukan sebagai pengajuan approval terpisah.</p>

                                        <div id="review-container"></div>

                                        <div class="d-flex justify-content-between pt-2">
                                            <button type="button" class="btn btn-light px-4 text-gray-700"
                                                onclick="backToForm()">
                                                <i class="fas fa-arrow-left mr-1"></i> Kembali
                                            </button>
                                            <button type="submit" class="btn btn-success px-5 shadow-sm">
                                                <i class="fas fa-paper-plane mr-1"></i> Kirim Semua Pengajuan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->

                <footer class="sticky-footer mt-auto">
                    <div class="text-center py-1">
                        <span>Copyright &copy; PT. Chutex International Indonesia {{ date('Y') }}</span>
                    </div>
                </footer>

            </div>
            <!-- End of Main Content -->
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Modal Preview Gambar Lampiran -->
    <div class="modal fade" id="filePreviewModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content border-0 shadow">
                <div class="modal-header py-2 px-3 bg-light d-flex align-items-center justify-content-between">
                    <h6 class="modal-title font-weight-bold text-gray-800 text-truncate mr-2" id="filePreviewTitle"
                        style="font-size: 0.9rem;">
                        <i class="far fa-file-image text-primary mr-1"></i> Preview Lampiran
                    </h6>
                    <button type="button" class="close ml-auto" data-dismiss="modal" aria-label="Close"
                        style="font-size: 1.4rem;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-2 text-center bg-dark"
                    style="min-height: 250px; display: flex; align-items: center; justify-content: center; border-bottom-left-radius: .3rem; border-bottom-right-radius: .3rem;">
                    <img id="filePreviewImage" src=""
                        style="max-width: 100%; max-height: 75vh; border-radius: 4px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);"
                        alt="Preview Lampiran">
                </div>
            </div>
        </div>
    </div>

    @include('layout.footerscript')

    <!-- Flatpickr: dipakai agar tanggal yang sudah dipilih di satu blok cuti bisa didisable di blok lainnya -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

    <script>
        flatpickr.localize(flatpickr.l10ns.id);

        /* ══ Data dari server ══ */
        // masterLeaveType sudah difilter di controller sesuai gender karyawan (gender_type 'A' = semua)
        const leaveTypeOptions = @json($masterLeaveType->map(function ($t) {
            return ['id' => $t->id, 'name' => $t->name, 'code' => $t->code];
        }));
        const holidays = @json($holidays ?? []);
        const leaveReasons = @json($leaveReasons ?? []);
        const employeeNpk = @json($employee->NPK);

        // Jenis cuti yang WAJIB upload lampiran file
        const attachRequiredCodes = ['menikah', 'menikahkan_anak', 'suami_istri_meninggal',
            'keluarga_meninggal', 'anak_meninggal', 'menantu_meninggal', 'orang_tua_meninggal'];
        const getBalanceUrl = @json(route('pengajuan-cuti.get-leave-balance'));

        const now = new Date();
        const todayStr = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().split('T')[0];

        let leaveIndex = 0;

        /* ── Utilities ── */
        const formatDate = (val) => {
            if (!val) return '-';
            const [y, m, d] = val.split('-');
            return `${d} ${['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][parseInt(m) - 1]} ${y}`;
        };

        // tanggal_mulai dan tanggal_selesai dihitung INCLUSIVE (keduanya adalah hari cuti).
        // Rentang [tanggal_mulai, tanggal_selesai] — melewati akhir pekan & hari libur.
        const computeWorkingDays = (startVal, endVal) => {
            if (!startVal || !endVal) return 0;
            let start = new Date(startVal);
            let end = new Date(endVal);
            if (end < start) return -1;

            let diff = 0;
            let current = new Date(start);
            while (current <= end) {
                let dayOfWeek = current.getDay();
                let tempDate = new Date(current.getTime() - (current.getTimezoneOffset() * 60000));
                let dateString = tempDate.toISOString().split('T')[0];
                let isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
                let isHoliday = holidays.includes(dateString);
                if (!isWeekend && !isHoliday) diff++;
                current.setDate(current.getDate() + 1);
            }
            return diff;
        };

        /* ── Cek tumpang tindih tanggal antar blok (interval tertutup/inclusive [mulai, selesai]) ── */
        const getBlockRange = (block) => {
            const mulai = block.querySelector('.leave-mulai').value;
            const selesai = block.querySelector('.leave-selesai').value;
            if (!mulai || !selesai) return null;
            return { start: mulai, end: selesai };
        };

        const rangesOverlap = (a, b) => (a.start <= b.end) && (b.start <= a.end);

        const checkOverlaps = () => {
            const blocks = Array.from(document.querySelectorAll('.leave-block'));
            let anyOverlap = false;

            blocks.forEach(b => {
                const info = b.querySelector('.leave-overlap-info');
                info.style.display = 'none';
                info.innerHTML = '';
            });

            for (let i = 0; i < blocks.length; i++) {
                const rangeI = getBlockRange(blocks[i]);
                if (!rangeI) continue;
                for (let j = 0; j < i; j++) {
                    const rangeJ = getBlockRange(blocks[j]);
                    if (!rangeJ) continue;
                    if (rangesOverlap(rangeI, rangeJ)) {
                        anyOverlap = true;
                        const info = blocks[i].querySelector('.leave-overlap-info');
                        info.style.display = 'block';
                        info.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i> Tanggal bertumpang tindih dengan Cuti #${j + 1}.`;
                    }
                }
            }
            return !anyOverlap;
        };

        /* ── Satu jenis cuti hanya boleh dipilih di satu blok — nonaktifkan di blok lain ── */
        const updateJenisAvailability = () => {
            const blocks = Array.from(document.querySelectorAll('.leave-block'));
            const selectedValues = blocks
                .map(b => b.querySelector('.leave-jenis').value)
                .filter(v => v);

            blocks.forEach(b => {
                const select = b.querySelector('.leave-jenis');
                const currentVal = select.value;
                Array.from(select.options).forEach(opt => {
                    if (!opt.value) return; // lewati placeholder "— Pilih Jenis Cuti —"
                    opt.disabled = selectedValues.includes(opt.value) && opt.value !== currentVal;
                });
            });
        };

        /* ── Datepicker (flatpickr): tanggal yang sudah dipakai di satu blok didisable di blok lain ──
           Catatan: ini hanya bantuan UX (mencegah memilih tanggal yang PERSIS berada di dalam rentang
           blok lain). checkOverlaps() tetap jadi penjaga utama untuk kasus rentang baru yang "membungkus"
           rentang blok lain tanpa titik ujungnya sendiri jatuh di dalam rentang tsb. */
        function initBlockDatepickers(block) {
            const mulaiInput = block.querySelector('.leave-mulai');
            const selesaiInput = block.querySelector('.leave-selesai');

            flatpickr(mulaiInput, {
                dateFormat: 'Y-m-d',
                minDate: 'today',
                disableMobile: true,
                onChange: () => {
                    handleBlockDateChange(block);
                    refreshAllDatepickersDisabledDates();
                }
            });

            flatpickr(selesaiInput, {
                dateFormat: 'Y-m-d',
                minDate: 'today',
                disableMobile: true,
                onChange: () => {
                    handleBlockDateChange(block);
                    refreshAllDatepickersDisabledDates();
                }
            });
        }

        function destroyBlockDatepickers(block) {
            const mulaiInput = block.querySelector('.leave-mulai');
            const selesaiInput = block.querySelector('.leave-selesai');
            if (mulaiInput._flatpickr) mulaiInput._flatpickr.destroy();
            if (selesaiInput._flatpickr) selesaiInput._flatpickr.destroy();
        }

        function refreshAllDatepickersDisabledDates() {
            const blocks = Array.from(document.querySelectorAll('.leave-block'));
            const withRanges = blocks.map(b => ({ block: b, range: getBlockRange(b) }));

            blocks.forEach(b => {
                const others = withRanges.filter(r => r.block !== b && r.range);
                const disableRanges = others.map(r => ({ from: r.range.start, to: r.range.end }));

                const mulaiInput = b.querySelector('.leave-mulai');
                const selesaiInput = b.querySelector('.leave-selesai');
                if (mulaiInput._flatpickr) mulaiInput._flatpickr.set('disable', disableRanges);
                if (selesaiInput._flatpickr) selesaiInput._flatpickr.set('disable', disableRanges);
            });
        }

        /* ── Membangun 1 blok cuti ── */
        function createLeaveBlockHTML(idx) {
            const optionsHtml = leaveTypeOptions.map(o =>
                `<option value="${o.id}" data-name="${o.name.replace(/"/g, '&quot;')}" data-code="${o.code}">${o.name}</option>`
            ).join('');

            return `
            <div class="leave-block border rounded p-3 p-md-4 mb-4 position-relative" data-index="${idx}">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-leave"
                    style="position:absolute; top:.65rem; right:.65rem; display:none;" title="Hapus cuti ini">
                    <i class="fas fa-times"></i>
                </button>

                <div class="font-weight-bold text-primary small mb-3">
                    <i class="fas fa-calendar-alt mr-1"></i> Pengajuan Cuti #${idx + 1}
                </div>

                <div class="form-group mb-4">
                    <label class="font-weight-bold text-gray-700 small">Jenis Cuti <span class="text-danger">*</span></label>
                    <select class="form-control leave-jenis" name="leaves[${idx}][jenis_cuti]" required>
                        <option value="" disabled selected>— Pilih Jenis Cuti —</option>
                        ${optionsHtml}
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group mb-4">
                        <label class="font-weight-bold text-gray-700 small">Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="text" class="form-control leave-mulai" name="leaves[${idx}][tanggal_mulai]"
                            placeholder="Pilih tanggal" autocomplete="off" readonly required>
                    </div>
                    <div class="col-md-6 form-group mb-4">
                        <label class="font-weight-bold text-gray-700 small">Tanggal Selesai <span class="text-danger">*</span></label>
                        <input type="text" class="form-control leave-selesai" name="leaves[${idx}][tanggal_selesai]"
                            placeholder="Pilih tanggal" autocomplete="off" readonly required>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label class="font-weight-bold text-gray-700 small">Jumlah Hari</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light text-primary"><i class="fas fa-calendar-day"></i></span>
                        </div>
                        <input type="text" class="form-control bg-light leave-hari-display" readonly
                            placeholder="Otomatis dihitung...">
                    </div>
                    <small class="form-text mt-2 font-weight-bold leave-sisa-info" style="display:none;"></small>
                    <small class="form-text mt-1 font-weight-bold text-danger leave-overlap-info" style="display:none;"></small>
                </div>

                <div class="form-group mb-0 leave-reason-wrapper">
                    <label class="font-weight-bold text-gray-700 small">
                        Keterangan / Alasan <span class="text-danger">*</span>
                    </label>

                    {{-- Placeholder saat jenis cuti belum dipilih --}}
                    <div class="leave-reason-empty-alert alert alert-light border text-muted py-2 px-3 small mb-0 d-flex align-items-center"
                        style="background: #f8f9fc; border-color: #e3e6f0; border-radius: 0.5rem;">
                        <i class="fas fa-info-circle text-primary mr-2" style="font-size: 0.95rem;"></i>
                        <span>Pilih <strong>Jenis Cuti</strong> di atas untuk melihat pilihan alasan cuti.</span>
                    </div>

                    {{-- Container template reasons (radio buttons) --}}
                    <div class="leave-reason-container mt-2" style="display:none;">
                        <div class="leave-reason-list"></div>
                    </div>

                    {{-- Textbox untuk input manual / sendiri --}}
                    <div class="leave-reason-custom-box mt-2" style="display:none;">
                        <label class="font-weight-bold text-gray-600 small mb-1 leave-reason-custom-label">
                            <i class="fas fa-pen mr-1 text-primary"></i> Tuliskan Alasan Sendiri: <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control leave-custom-textarea" rows="2"
                            placeholder="Ketik alasan cuti Anda di sini..."></textarea>
                    </div>

                    {{-- Hidden / Actual field yang dikirim ke backend --}}
                    <textarea class="leave-keterangan d-none" name="leaves[${idx}][keterangan]"></textarea>
                </div>

                <div class="form-group mb-0 mt-4 leave-attach-wrapper" style="display:none;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="font-weight-bold text-gray-700 small mb-0">
                            <i class="fas fa-paperclip text-primary mr-1"></i> Lampiran Dokumen Pendukung <span class="text-danger">*</span>
                        </label>
                        <span class="badge badge-light border text-muted px-2 py-1 font-weight-normal" style="font-size: 0.7rem;">Wajib dilampirkan</span>
                    </div>

                    <!-- Dropzone upload area -->
                    <div class="file-upload-zone mt-2" data-index="${idx}">
                        <input type="file" class="leave-attach-files d-none"
                            name="leaves[${idx}][attach_files][]"
                            multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <div class="file-upload-icon-circle">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="upload-title">
                            Tarik & lepas dokumen di sini, atau <span class="text-primary font-weight-bold" style="text-decoration: underline;">Pilih File</span>
                        </div>
                        <div class="upload-subtitle">
                            Maksimal 5MB per file &bull; Dokumen harus jelas terbaca
                        </div>
                        <div class="upload-badges">
                            <span class="upload-badge"><i class="far fa-file-pdf text-danger mr-1"></i>PDF</span>
                            <span class="upload-badge"><i class="far fa-file-image text-success mr-1"></i>JPG / PNG</span>
                            <span class="upload-badge"><i class="far fa-file-word text-primary mr-1"></i>DOC / DOCX</span>
                        </div>
                    </div>

                    <!-- Preview area -->
                    <div class="leave-attach-preview mt-3" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center px-1 mb-2">
                            <span class="small font-weight-bold text-gray-700 preview-count-label">
                                <i class="fas fa-check-circle text-success mr-1"></i> 0 file dipilih
                            </span>
                            <button type="button" class="btn btn-link btn-sm text-danger p-0 btn-clear-files" style="font-size: 0.74rem; text-decoration: none;">
                                <i class="fas fa-trash-alt mr-1"></i> Hapus Semua
                            </button>
                        </div>
                        <div class="file-preview-grid"></div>
                    </div>
                </div>
            </div>`;
        }

        function appendLeaveBlock() {
            const idx = leaveIndex++;
            const container = document.getElementById('leaves-container');
            const wrapper = document.createElement('div');
            wrapper.innerHTML = createLeaveBlockHTML(idx);
            const block = wrapper.firstElementChild;
            container.appendChild(block);
            initBlockDatepickers(block);
            initBlockFileUpload(block);
            updateRemoveButtonsVisibility();
            updateJenisAvailability();
            refreshAllDatepickersDisabledDates();
        }

        function updateRemoveButtonsVisibility() {
            const blocks = document.querySelectorAll('.leave-block');
            blocks.forEach(b => {
                const btn = b.querySelector('.btn-remove-leave');
                btn.style.display = blocks.length > 1 ? 'inline-block' : 'none';
            });
        }

        function renumberBlockLabels() {
            document.querySelectorAll('.leave-block').forEach((b, i) => {
                b.querySelector('.font-weight-bold.text-primary.small').innerHTML =
                    `<i class="fas fa-calendar-alt mr-1"></i> Pengajuan Cuti #${i + 1}`;
            });
        }

        /* ── Hitung hari & cek sisa saldo per blok ── */
        function handleBlockDateChange(block) {
            const jenis = block.querySelector('.leave-jenis');
            const mulai = block.querySelector('.leave-mulai');
            const selesai = block.querySelector('.leave-selesai');
            const hariDisp = block.querySelector('.leave-hari-display');
            const sisaInfo = block.querySelector('.leave-sisa-info');

            // Tanggal selesai tidak boleh sebelum tanggal mulai (boleh sama = cuti 1 hari)
            if (mulai.value && selesai._flatpickr) {
                selesai._flatpickr.set('minDate', mulai.value);
                if (selesai.value && selesai.value < mulai.value) {
                    selesai._flatpickr.clear();
                }
            }

            const diff = computeWorkingDays(mulai.value, selesai.value);

            if (diff > 0) {
                hariDisp.value = `${diff} Hari`;
            } else {
                hariDisp.value = mulai.value && selesai.value ? 'Tgl tdk valid / Libur' : '';
                if (!jenis.value || !mulai.value) {
                    sisaInfo.style.display = 'none';
                }
            }

            if (jenis.value && mulai.value) {
                checkBlockBalance(block, diff);
            }

            checkOverlaps();
        }

        function checkBlockBalance(block, days) {
            const jenis = block.querySelector('.leave-jenis');
            const mulai = block.querySelector('.leave-mulai');
            const selesai = block.querySelector('.leave-selesai');
            const hariDisp = block.querySelector('.leave-hari-display');
            const sisaInfo = block.querySelector('.leave-sisa-info');

            sisaInfo.style.display = 'block';
            sisaInfo.className = 'form-text text-info mt-2 small font-weight-bold leave-sisa-info';
            sisaInfo.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i> Memeriksa sisa cuti...';

            const params = new URLSearchParams({
                npk: employeeNpk,
                leave_type_id: jenis.value,
                start_date: mulai.value
            });

            fetch(`${getBalanceUrl}?${params.toString()}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        sisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold leave-sisa-info';
                        sisaInfo.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Gagal mengecek data cuti.';
                        return;
                    }

                    // Batasi tanggal selesai sesuai sisa saldo cuti yang tersedia
                    if (res.max_end_date) {
                        if (selesai._flatpickr) selesai._flatpickr.set('maxDate', res.max_end_date);
                        selesai.dataset.maxEnd = res.max_end_date;
                        if (selesai.value && selesai.value > res.max_end_date) {
                            if (selesai._flatpickr) selesai._flatpickr.clear();
                            hariDisp.value = '';
                        }
                    } else {
                        if (selesai._flatpickr) selesai._flatpickr.set('maxDate', null);
                        delete selesai.dataset.maxEnd;
                    }

                    const recalculatedDiff = computeWorkingDays(mulai.value, selesai.value);
                    const effectiveDiff = selesai.value ? recalculatedDiff : days;

                    if (res.sisa <= 0) {
                        sisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold leave-sisa-info';
                        sisaInfo.innerHTML = `<i class="fas fa-times-circle mr-1"></i> ${res.keterangan}. Anda tidak memiliki sisa cuti.`;
                    } else if (effectiveDiff > res.sisa) {
                        sisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold leave-sisa-info';
                        sisaInfo.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i> ${res.keterangan}. Sisa cuti tidak mencukupi.`;
                    } else {
                        const maxInfo = res.max_end_date ? ` Maks. tanggal selesai: <strong>${formatDate(res.max_end_date)}</strong>.` : '';
                        sisaInfo.className = 'form-text text-success mt-2 small font-weight-bold leave-sisa-info';
                        sisaInfo.innerHTML = `<i class="fas fa-check-circle mr-1"></i> ${res.keterangan}. Sisa cuti mencukupi.${maxInfo}`;
                    }

                    refreshAllDatepickersDisabledDates();
                }).catch(() => {
                    sisaInfo.className = 'form-text text-danger mt-2 small font-weight-bold leave-sisa-info';
                    sisaInfo.innerHTML = '<i class="fas fa-wifi mr-1"></i> Koneksi bermasalah saat mengecek cuti.';
                });
        }

        /* ── Event delegation & File Upload Handler ── */
        function initBlockFileUpload(block) {
            const zone = block.querySelector('.file-upload-zone');
            const input = block.querySelector('.leave-attach-files');
            const grid = block.querySelector('.file-preview-grid');
            const clearBtn = block.querySelector('.btn-clear-files');

            if (!zone || !input) return;

            // Inisialisasi list internal file
            input._storedFiles = [];

            // Klik zone untuk buka file picker
            zone.addEventListener('click', (e) => {
                if (e.target !== input) {
                    input.click();
                }
            });

            // Drag and drop events
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('dragover');
            });

            zone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('dragover');
            });

            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('dragover');
                if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                    handleNewFiles(input, e.dataTransfer.files);
                }
            });

            // Input change saat user pilih file via dialog
            input.addEventListener('change', (e) => {
                if (input.files && input.files.length > 0) {
                    handleNewFiles(input, input.files);
                }
            });

            // Hapus semua file
            if (clearBtn) {
                clearBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    clearAllFiles(input);
                });
            }

            // Hapus file satuan atau perbesar gambar
            if (grid) {
                grid.addEventListener('click', (e) => {
                    const removeBtn = e.target.closest('.file-remove-btn');
                    if (removeBtn) {
                        e.stopPropagation();
                        const fileIdx = parseInt(removeBtn.getAttribute('data-index'), 10);
                        removeSingleFile(input, fileIdx);
                        return;
                    }

                    const zoomable = e.target.closest('.img-zoomable');
                    if (zoomable) {
                        e.stopPropagation();
                        showImageModal(zoomable.src, zoomable.getAttribute('alt') || 'Preview Foto');
                    }
                });
            }
        }

        function handleNewFiles(input, filesList) {
            if (!input._storedFiles) {
                input._storedFiles = [];
            }

            const MAX_SIZE = 5 * 1024 * 1024; // 5MB
            const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            const oversizeFiles = [];
            const invalidExtFiles = [];

            Array.from(filesList).forEach(file => {
                const parts = file.name.split('.');
                const ext = parts.length > 1 ? parts.pop().toLowerCase() : '';

                if (!ALLOWED_EXT.includes(ext)) {
                    invalidExtFiles.push(file.name);
                    return;
                }

                if (file.size > MAX_SIZE) {
                    oversizeFiles.push(file.name);
                    return;
                }

                // Hindari duplikasi file persis (nama & ukuran sama)
                const exists = input._storedFiles.some(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified);
                if (!exists) {
                    input._storedFiles.push(file);
                }
            });

            if (invalidExtFiles.length > 0) {
                alert('Format file berikut tidak diizinkan:\n- ' + invalidExtFiles.join('\n- ') + '\n\nGunakan format: PDF, JPG, PNG, atau DOC/DOCX.');
            }
            if (oversizeFiles.length > 0) {
                alert('Ukuran file berikut melebihi batas 5MB:\n- ' + oversizeFiles.join('\n- '));
            }

            syncStoredFilesToInput(input);
            renderFilePreviews(input);
        }

        function syncStoredFilesToInput(input) {
            const dt = new DataTransfer();
            (input._storedFiles || []).forEach(file => {
                dt.items.add(file);
            });
            input.files = dt.files;
        }

        function removeSingleFile(input, index) {
            if (input._storedFiles && input._storedFiles[index]) {
                input._storedFiles.splice(index, 1);
                syncStoredFilesToInput(input);
                renderFilePreviews(input);
            }
        }

        function clearAllFiles(input) {
            input._storedFiles = [];
            syncStoredFilesToInput(input);
            renderFilePreviews(input);
        }

        function renderFilePreviews(input) {
            const block = input.closest('.leave-block');
            const previewWrapper = block.querySelector('.leave-attach-preview');
            const countLabel = block.querySelector('.preview-count-label');
            const grid = block.querySelector('.file-preview-grid');
            const files = input._storedFiles || [];

            if (files.length === 0) {
                previewWrapper.style.display = 'none';
                grid.innerHTML = '';
                return;
            }

            previewWrapper.style.display = 'block';
            countLabel.innerHTML = `<i class="fas fa-check-circle text-success mr-1"></i> <strong>${files.length}</strong> file berhasil dipilih`;
            grid.innerHTML = '';

            files.forEach((file, index) => {
                const parts = file.name.split('.');
                const ext = parts.length > 1 ? parts.pop().toLowerCase() : '';
                const sizeFormatted = file.size > 1024 * 1024
                    ? (file.size / (1024 * 1024)).toFixed(2) + ' MB'
                    : (file.size / 1024).toFixed(1) + ' KB';

                const isImage = ['jpg', 'jpeg', 'png'].includes(ext);
                const isPdf = ext === 'pdf';
                const isWord = ['doc', 'docx'].includes(ext);

                let iconHtml = '';
                let pillClass = 'pill-file';
                let pillText = ext.toUpperCase() || 'FILE';

                if (isPdf) {
                    pillClass = 'pill-pdf';
                    iconHtml = `<div class="doc-icon-wrapper"><i class="fas fa-file-pdf doc-icon-pdf"></i></div>`;
                } else if (isWord) {
                    pillClass = 'pill-doc';
                    iconHtml = `<div class="doc-icon-wrapper"><i class="fas fa-file-word doc-icon-word"></i></div>`;
                } else if (isImage) {
                    pillClass = 'pill-img';
                    iconHtml = `<div class="doc-icon-wrapper"><i class="fas fa-file-image doc-icon-image"></i></div>`;
                } else {
                    iconHtml = `<div class="doc-icon-wrapper"><i class="fas fa-file-alt doc-icon-file"></i></div>`;
                }

                const card = document.createElement('div');
                card.className = 'file-preview-card';
                card.innerHTML = `
                    <button type="button" class="file-remove-btn" data-index="${index}" title="Hapus file ini">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="file-preview-thumb" data-index="${index}">
                        ${iconHtml}
                        <span class="file-type-pill ${pillClass}">${pillText}</span>
                    </div>
                    <div class="file-preview-body">
                        <div class="file-preview-name" title="${file.name.replace(/"/g, '&quot;')}">${file.name}</div>
                        <div class="file-preview-size"><i class="fas fa-hdd mr-1 text-secondary"></i>${sizeFormatted}</div>
                    </div>
                `;

                if (isImage) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const thumbDiv = card.querySelector('.file-preview-thumb');
                        if (thumbDiv) {
                            thumbDiv.innerHTML = `
                                <img src="${e.target.result}" alt="${file.name.replace(/"/g, '&quot;')}" class="img-zoomable" style="cursor: zoom-in;" title="Klik untuk melihat foto">
                                <span class="file-type-pill ${pillClass}">${pillText}</span>
                            `;
                        }
                    };
                    reader.readAsDataURL(file);
                }

                grid.appendChild(card);
            });
        }

        function showImageModal(src, title) {
            const modalImg = document.getElementById('filePreviewImage');
            const modalTitle = document.getElementById('filePreviewTitle');
            if (modalImg && modalTitle) {
                modalImg.src = src;
                modalTitle.innerHTML = `<i class="far fa-file-image text-primary mr-2"></i> ${title}`;
                $('#filePreviewModal').modal('show');
            }
        }

        /* ── Update template reason berdasarkan jenis cuti yang dipilih ── */
        function updateLeaveReasons(block) {
            const jenisSelect = block.querySelector('.leave-jenis');
            const selectedTypeId = jenisSelect.value;
            const idx = block.getAttribute('data-index');

            const emptyAlert = block.querySelector('.leave-reason-empty-alert');
            const reasonContainer = block.querySelector('.leave-reason-container');
            const reasonList = block.querySelector('.leave-reason-list');
            const customBox = block.querySelector('.leave-reason-custom-box');
            const customTextarea = block.querySelector('.leave-custom-textarea');
            const customLabel = block.querySelector('.leave-reason-custom-label');
            const finalKeterangan = block.querySelector('.leave-keterangan');

            // Reset nilai awal
            finalKeterangan.value = '';
            customTextarea.value = '';

            if (!selectedTypeId) {
                if (emptyAlert) emptyAlert.style.display = 'flex';
                if (reasonContainer) reasonContainer.style.display = 'none';
                if (reasonList) reasonList.innerHTML = '';
                if (customBox) customBox.style.display = 'none';
                return;
            }

            if (emptyAlert) emptyAlert.style.display = 'none';

            // Filter data template reason dari tabel leave_reasons berdasarkan leave_type_id
            const matchedReasons = leaveReasons.filter(r => String(r.leave_type_id) === String(selectedTypeId));

            if (matchedReasons.length > 0) {
                // Tampilkan container radio button template reasons
                reasonContainer.style.display = 'block';
                customBox.style.display = 'none';

                let html = '';
                matchedReasons.forEach((r, rIdx) => {
                    const radioId = `reason_${idx}_${r.id || rIdx}`;
                    const rawReason = r.reason || '';
                    const escapedReason = rawReason.replace(/"/g, '&quot;');
                    html += `
                    <div class="custom-control custom-radio leave-reason-radio-card" data-reason="${escapedReason}">
                        <input type="radio" id="${radioId}" name="leaves_reason_choice_${idx}" 
                            class="custom-control-input leave-radio-item" value="${escapedReason}">
                        <label class="custom-control-label text-gray-800" for="${radioId}">
                            ${rawReason}
                        </label>
                    </div>`;
                });

                // Tambahkan opsi Radio untuk "Input Alasan Sendiri"
                const customRadioId = `reason_${idx}_custom`;
                html += `
                <div class="custom-control custom-radio leave-reason-radio-card leave-reason-radio-custom" data-reason="__custom__">
                    <input type="radio" id="${customRadioId}" name="leaves_reason_choice_${idx}" 
                        class="custom-control-input leave-radio-item leave-radio-custom-trigger" value="__custom__">
                    <label class="custom-control-label text-primary font-weight-bold" for="${customRadioId}">
                        <i class="fas fa-edit mr-1"></i> Lainnya (Input alasan sendiri)
                    </label>
                </div>`;

                reasonList.innerHTML = html;
                if (customLabel) {
                    customLabel.innerHTML = '<i class="fas fa-pen mr-1 text-primary"></i> Tuliskan Alasan Sendiri: <span class="text-danger">*</span>';
                }
            } else {
                // Jika tidak ada template reason di database untuk jenis cuti ini, langsung buka textbox
                reasonContainer.style.display = 'none';
                reasonList.innerHTML = '';
                customBox.style.display = 'block';
                if (customLabel) {
                    customLabel.innerHTML = '<i class="fas fa-pen mr-1 text-primary"></i> Keterangan / Alasan Cuti: <span class="text-danger">*</span>';
                }
            }
        }

        document.getElementById('leaves-container').addEventListener('change', function (e) {
            if (e.target.matches('.leave-jenis')) {
                updateJenisAvailability();
                handleBlockDateChange(e.target.closest('.leave-block'));
                updateLeaveReasons(e.target.closest('.leave-block'));

                // Toggle tampilan upload file berdasarkan jenis cuti
                const block = e.target.closest('.leave-block');
                const selectedOption = e.target.options[e.target.selectedIndex];
                const code = selectedOption ? selectedOption.getAttribute('data-code') : '';
                const attachWrapper = block.querySelector('.leave-attach-wrapper');
                const attachInput = block.querySelector('.leave-attach-files');

                if (attachRequiredCodes.includes(code)) {
                    attachWrapper.style.display = 'block';
                } else {
                    attachWrapper.style.display = 'none';
                    if (attachInput) {
                        clearAllFiles(attachInput);
                    }
                }
            }

            // Saat radio template reason dipilih
            if (e.target.matches('.leave-radio-item')) {
                const block = e.target.closest('.leave-block');
                const customBox = block.querySelector('.leave-reason-custom-box');
                const customTextarea = block.querySelector('.leave-custom-textarea');
                const finalKeterangan = block.querySelector('.leave-keterangan');

                // Update styling active pada radio cards
                block.querySelectorAll('.leave-reason-radio-card').forEach(card => {
                    const input = card.querySelector('.leave-radio-item');
                    if (input && input.checked) {
                        card.classList.add('is-selected');
                    } else {
                        card.classList.remove('is-selected');
                    }
                });

                if (e.target.value === '__custom__') {
                    customBox.style.display = 'block';
                    customTextarea.focus();
                    finalKeterangan.value = customTextarea.value;
                } else {
                    customBox.style.display = 'none';
                    finalKeterangan.value = e.target.value;
                }
            }
        });

        // Event listener saat user mengetik di textbox alasan sendiri
        document.getElementById('leaves-container').addEventListener('input', function (e) {
            if (e.target.matches('.leave-custom-textarea')) {
                const block = e.target.closest('.leave-block');
                const finalKeterangan = block.querySelector('.leave-keterangan');
                if (finalKeterangan) {
                    finalKeterangan.value = e.target.value;
                }
            }
        });

        document.getElementById('leaves-container').addEventListener('click', function (e) {
            // Klik card radio button reason untuk kemudahan pengguna
            const card = e.target.closest('.leave-reason-radio-card');
            if (card && !e.target.matches('input[type="radio"]') && !e.target.matches('label')) {
                const radio = card.querySelector('.leave-radio-item');
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            const removeBtn = e.target.closest('.btn-remove-leave');
            if (removeBtn) {
                const block = removeBtn.closest('.leave-block');
                destroyBlockDatepickers(block);
                block.remove();
                updateRemoveButtonsVisibility();
                renumberBlockLabels();
                updateJenisAvailability();
                checkOverlaps();
                refreshAllDatepickersDisabledDates();
            }
        });

        document.getElementById('btnTambahCuti').addEventListener('click', appendLeaveBlock);

        /* ── Panel Navigations ── */
        window.goToReview = () => {
            const blocks = document.querySelectorAll('.leave-block');
            if (blocks.length === 0) {
                alert('Minimal harus ada 1 pengajuan cuti.');
                return;
            }

            if (!checkOverlaps()) {
                alert('Ada tanggal cuti yang tumpang tindih antar pengajuan. Silakan perbaiki tanggalnya terlebih dahulu.');
                return;
            }

            const chosenTypes = [];
            let reviewHtml = '';
            for (const block of blocks) {
                const jenis = block.querySelector('.leave-jenis');
                const mulai = block.querySelector('.leave-mulai');
                const selesai = block.querySelector('.leave-selesai');
                const ket = block.querySelector('.leave-keterangan');
                const nomor = block.querySelector('.font-weight-bold.text-primary.small').textContent.trim();

                if (!jenis.value) { jenis.reportValidity(); return; }
                if (!mulai.value) { mulai.reportValidity(); return; }
                if (!selesai.value) { selesai.reportValidity(); return; }
                if (!ket.value.trim()) {
                    const customBox = block.querySelector('.leave-reason-custom-box');
                    const customTextarea = block.querySelector('.leave-custom-textarea');
                    if (customBox && customBox.style.display !== 'none') {
                        customTextarea.focus();
                        alert(`${nomor}: Silakan tulis alasan cuti pada kotak keterangan yang tersedia.`);
                    } else {
                        alert(`${nomor}: Silakan pilih salah satu alasan cuti atau pilih opsi input alasan sendiri.`);
                    }
                    return;
                }

                // Validasi lampiran file jika wajib
                const attachWrapper = block.querySelector('.leave-attach-wrapper');
                const attachInput = block.querySelector('.leave-attach-files');
                const isAttachRequired = attachWrapper && attachWrapper.style.display !== 'none';
                const fileCount = (attachInput && attachInput._storedFiles) ? attachInput._storedFiles.length : (attachInput && attachInput.files ? attachInput.files.length : 0);

                if (isAttachRequired && fileCount === 0) {
                    alert(`${nomor}: Lampiran dokumen wajib diunggah untuk jenis cuti ini.`);
                    return;
                }

                if (chosenTypes.includes(jenis.value)) {
                    alert(`${nomor}: Jenis cuti ini sudah dipilih di pengajuan lain. Setiap jenis cuti hanya boleh dipilih sekali.`);
                    return;
                }
                chosenTypes.push(jenis.value);

                if (mulai.value < todayStr) {
                    alert(`${nomor}: Tanggal mulai tidak boleh sebelum hari ini.`);
                    return;
                }

                const diff = computeWorkingDays(mulai.value, selesai.value);
                if (!diff || diff <= 0) {
                    alert(`${nomor}: Format tanggal tidak valid (tanggal mulai harus lebih awal atau sama dengan tanggal selesai, dan bukan hanya akhir pekan/libur).`);
                    return;
                }

                const maxAttr = selesai.dataset.maxEnd;
                if (maxAttr && selesai.value > maxAttr) {
                    alert(`${nomor}: Tanggal selesai melebihi sisa saldo cuti yang tersedia.`);
                    return;
                }

                const leaveName = jenis.options[jenis.selectedIndex].getAttribute('data-name');
                const filesList = (attachInput && attachInput._storedFiles) ? attachInput._storedFiles : (attachInput && attachInput.files ? Array.from(attachInput.files) : []);

                reviewHtml += `
                <div class="bg-light p-3 p-md-4 rounded mb-3 border">
                    <div class="font-weight-bold text-primary small mb-2"><i class="fas fa-calendar-alt mr-1"></i> ${nomor}</div>
                    <div class="review-row">
                        <span class="review-label">Jenis Cuti</span>
                        <span class="review-value"><span class="badge badge-primary px-2 py-1">${leaveName}</span></span>
                    </div>
                    <div class="review-row">
                        <span class="review-label">Tanggal Mulai</span>
                        <span class="review-value">${formatDate(mulai.value)}</span>
                    </div>
                    <div class="review-row">
                        <span class="review-label">Tanggal Selesai</span>
                        <span class="review-value">${formatDate(selesai.value)}</span>
                    </div>
                    <div class="review-row">
                        <span class="review-label">Total Hari</span>
                        <span class="review-value text-primary">${diff} Hari</span>
                    </div>
                    <div class="review-row">
                        <span class="review-label">Alasan</span>
                        <span class="review-value">${ket.value}</span>
                    </div>
                    ${filesList.length > 0 ? `
                    <div class="review-row align-items-start">
                        <span class="review-label pt-1"><i class="fas fa-paperclip mr-1"></i>Lampiran (${filesList.length})</span>
                        <div class="review-value text-left" style="max-width: 65%;">
                            <div class="d-flex flex-wrap" style="gap: 6px;">
                                ${filesList.map(f => {
                    const parts = f.name.split('.');
                    const ext = parts.length > 1 ? parts.pop().toLowerCase() : '';
                    const isPdf = ext === 'pdf';
                    const isWord = ['doc', 'docx'].includes(ext);
                    const isImg = ['jpg', 'jpeg', 'png'].includes(ext);
                    let iconClass = 'fa-file-alt text-secondary';
                    if (isPdf) iconClass = 'fa-file-pdf text-danger';
                    else if (isWord) iconClass = 'fa-file-word text-primary';
                    else if (isImg) iconClass = 'fa-file-image text-success';
                    const sizeStr = f.size > 1024 * 1024
                        ? (f.size / (1024 * 1024)).toFixed(1) + ' MB'
                        : (f.size / 1024).toFixed(0) + ' KB';
                    return `<div class="review-file-badge">
                                        <i class="far ${iconClass} mr-2" style="font-size: 1rem;"></i>
                                        <div style="line-height: 1.2;">
                                            <div class="font-weight-bold text-truncate" style="font-size: 0.78rem; max-width: 170px;" title="${f.name.replace(/"/g, '&quot;')}">${f.name}</div>
                                            <div class="text-muted" style="font-size: 0.68rem;">${sizeStr}</div>
                                        </div>
                                    </div>`;
                }).join('')}
                            </div>
                        </div>
                    </div>` : ''}
                </div>`;
            }

            document.getElementById('review-container').innerHTML = reviewHtml;
            switchPanel(1, 2, true);
        };

        window.backToForm = () => switchPanel(2, 1, false);

        const switchPanel = (hideParam, showParam, isNext) => {
            document.getElementById(`panel-${hideParam}`).classList.remove('active');
            document.getElementById(`panel-${showParam}`).classList.add('active');

            let s1 = document.getElementById('step-ind-1'),
                s2 = document.getElementById('step-ind-2'),
                sc1 = document.getElementById('sc-1');

            if (isNext) {
                s1.classList.remove('active'); s1.classList.add('completed');
                sc1.innerHTML = '<i class="fas fa-check fa-sm"></i>';
                s2.classList.add('active');
            } else {
                s2.classList.remove('active');
                s1.classList.remove('completed'); s1.classList.add('active');
                sc1.innerHTML = '<i class="fas fa-file-alt fa-sm"></i>';
            }
        };

        /* ── Inisialisasi: satu blok cuti default saat halaman dimuat ── */
        document.addEventListener('DOMContentLoaded', appendLeaveBlock);
    </script>
</body>

</html>