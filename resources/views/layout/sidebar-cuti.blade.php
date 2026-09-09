<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
        <div class="sidebar-brand-icon">
            <img src="{{ asset('img/chutex.svg') }}" style="width: 40px;">
        </div>
        <div class="sidebar-brand-text mx-3">E-HRIS <sup>Cuti</sup></div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Form -->
    <li class="nav-item {{ request()->routeIs('pengajuan-cuti.form') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('pengajuan-cuti.form') }}">
            <i class="fas fa-fw fa-file-alt"></i>
            <span>Pengajuan Cuti</span>
        </a>
    </li>

    <!-- Nav Item - Riwayat -->
    <li class="nav-item {{ request()->routeIs('pengajuan-cuti.riwayat') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('pengajuan-cuti.riwayat') }}">
            <i class="fas fa-fw fa-history"></i>
            <span>Riwayat Pengajuan</span>
        </a>
    </li>

    <!-- Nav Item - Approval -->
    @if (auth()->check() && \App\Models\ApprovalRule::where('approval_id', auth()->user()->npk)->exists())
    <li class="nav-item {{ request()->routeIs('pengajuan-cuti.approval') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('pengajuan-cuti.approval') }}">
            <i class="fas fa-fw fa-check-circle"></i>
            <span>Approval Cuti</span>
        </a>
    </li>
    @elseif (session('cuti_employee_npk') && \App\Models\ApprovalRule::where('approval_id', session('cuti_employee_npk'))->exists())
    <li class="nav-item {{ request()->routeIs('pengajuan-cuti.portal-approval*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('pengajuan-cuti.portal-approval') }}">
            <i class="fas fa-fw fa-check-circle"></i>
            <span>Approval Cuti</span>
        </a>
    </li>
    @endif

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>
<!-- End of Sidebar -->