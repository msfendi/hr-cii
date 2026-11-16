@extends('hris_v2.layout')

@section('judul', 'Portal HRIS v2')

@push('styles')
<style>
    .v2-num { font-size: .72rem; letter-spacing: .26em; color: rgba(238, 243, 251, .38); font-weight: 300; }
    .portal-card { min-height: 292px; }
    .portal-card:hover .v2-menu-icon {
        color: #fff; background: rgba(59, 140, 255, .22); border-color: rgba(59, 140, 255, .55);
        box-shadow: 0 0 26px rgba(59, 140, 255, .35);
    }
    .portal-foot { font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; color: rgba(238, 243, 251, .34); }
</style>
@endpush

@section('content')
<section class="v2-hero">
    <img src="{{ asset('img/chutex_logo.png') }}" alt="PT Chutex International Indonesia" class="v2-logo-hero">
    <span class="v2-kicker">Human Resource Information System</span>
    <h1>One portal for people,<br>payroll and presence.</h1>
    @include('hris_v2._typewriter', ['kalimat' => [
        'Payslip, face attendance, and leave in a single portal',
        'Secure staff login with Face ID',
        'Company HRIS data, accurate and real-time',
    ]])
</section>

<div class="row">
    {{-- 1. PORTAL USER (STAFF/ADMIN) --}}
    <div class="col-12 col-md-4 mb-3">
        <div class="v2-card v2-card-hover portal-card p-4 h-100 d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="v2-menu-icon"><i class="fas fa-user-shield"></i></div>
                <span class="v2-num">01</span>
            </div>
            <span class="v2-label mb-1">Staff access</span>
            <h5 class="v2-card-title mb-2">Staff Portal</h5>
            <p class="v2-muted mb-3" style="font-size:.9rem;">Staff/admin login to manage HRIS: employee data, payroll, reports, and system settings. Use a password or Face ID.</p>
            <div class="mt-auto">
                <a href="{{ route('v2.login') }}" class="btn v2-btn v2-btn-primary btn-block">
                    <i class="fas fa-sign-in-alt mr-1"></i>Staff Login</a>
            </div>
        </div>
    </div>

    {{-- 2. PORTAL KARYAWAN --}}
    <div class="col-12 col-md-4 mb-3">
        <div class="v2-card v2-card-hover portal-card p-4 h-100 d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="v2-menu-icon"><i class="fas fa-id-badge"></i></div>
                <span class="v2-num">02</span>
            </div>
            <span class="v2-label mb-1">Employee access</span>
            <h5 class="v2-card-title mb-2">Employee Portal</h5>
            <p class="v2-muted mb-3" style="font-size:.9rem;">One login (NPK + face verification) unlocks every permitted menu: payslip, face attendance, and leave requests.</p>
            <div class="mt-auto">
                @if($sesiAktif)
                    <a href="{{ route('v2.employee.home') }}" class="btn v2-btn v2-btn-primary btn-block mb-2">
                        <i class="fas fa-door-open mr-1"></i> Continue as {{ $namaSesi }}
                    </a>
                    <form method="POST" action="{{ route('v2.employee.logout') }}" class="mb-0">
                        @csrf
                        <button class="btn v2-btn v2-btn-ghost btn-block btn-sm">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('v2.employee.login') }}" class="btn v2-btn v2-btn-primary btn-block">
                        <i class="fas fa-camera mr-1"></i>Employee Login</a>
                @endif
            </div>
        </div>
    </div>

    {{-- 3. PORTAL PUBLIK --}}
    <div class="col-12 col-md-4 mb-3">
        <div class="v2-card v2-card-hover portal-card p-4 h-100 d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="v2-menu-icon"><i class="fas fa-bullhorn"></i></div>
                <span class="v2-num">03</span>
            </div>
            <span class="v2-label mb-1">Open to everyone</span>
            <h5 class="v2-card-title mb-2">Public Portal</h5>
            <p class="v2-muted mb-3" style="font-size:.9rem;">No login needed: browse job openings (careers) and check application status.</p>
            <div class="mt-auto">
                <a href="{{ route('v2.public.home') }}" class="btn v2-btn v2-btn-ghost btn-block">
                    <i class="fas fa-globe mr-1"></i>Public Portal</a>
            </div>
        </div>
    </div>
</div>

<div class="text-center mt-4">
    <span class="portal-foot">Face ID · Payslip · Leave · Careers — all in one place</span>
</div>
@endsection
