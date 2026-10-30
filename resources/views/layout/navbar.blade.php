<!-- Topbar -->
<style>
    .ai-assistant-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 18px;
        border-radius: 50px;
        background: linear-gradient(135deg, #6a4cff 0%, #a24bff 45%, #ff4ccb 100%);
        background-size: 200% 200%;
        color: #fff !important;
        font-weight: 600;
        font-size: 0.85rem;
        text-decoration: none !important;
        box-shadow: 0 4px 14px rgba(122, 74, 255, 0.4);
        transition: transform 0.2s ease, box-shadow 0.2s ease, background-position 0.4s ease;
        animation: ai-btn-gradient 4s ease infinite;
        position: relative;
        overflow: hidden;
    }

    .ai-assistant-btn:hover,
    .ai-assistant-btn:focus {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(122, 74, 255, 0.55);
        color: #fff !important;
        background-position: 100% 50%;
    }

    .ai-assistant-btn i {
        font-size: 1rem;
        animation: ai-btn-sparkle 1.8s ease-in-out infinite;
    }

    .ai-assistant-btn .ai-btn-text {
        white-space: nowrap;
    }

    .ai-assistant-btn::after {
        content: "";
        position: absolute;
        top: 0;
        left: -75%;
        width: 50%;
        height: 100%;
        background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.45), transparent);
        transform: skewX(-20deg);
        animation: ai-btn-shine 3s ease-in-out infinite;
    }

    @keyframes ai-btn-gradient {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    @keyframes ai-btn-sparkle {
        0%, 100% { transform: scale(1) rotate(0deg); }
        50% { transform: scale(1.15) rotate(8deg); }
    }

    @keyframes ai-btn-shine {
        0% { left: -75%; }
        50% { left: 125%; }
        100% { left: 125%; }
    }

    @media (max-width: 575.98px) {
        .ai-assistant-btn .ai-btn-text {
            display: none;
        }
        .ai-assistant-btn {
            padding: 8px 12px;
        }
    }
</style>

<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <!-- Sidebar Toggle (Topbar) -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
        <i class="fa fa-bars"></i>
    </button>
    
    <div class="text-center d-none d-md-inline">
        <!-- Counter - Alerts -->
        <button class="border-0" id="sidebarToggle" style="background-color: transparent;">
            <i class="fas fa-bars fa-fw"></i>
        </button>
    </div>

    <!-- Topbar Navbar -->
    <ul class="navbar-nav ml-auto">

        <!-- Nav Item - AI Assistant -->
        <!-- <li class="nav-item d-flex align-items-center mr-2">
            <a href="{{ route('chatbot.index') }}" class="ai-assistant-btn">
                <i class="fas fa-robot"></i>
                <span class="ai-btn-text">AI Assistant</span>
            </a>
        </li> -->

        <!-- Nav Item - Search Dropdown (Visible Only XS) -->
        <li class="nav-item dropdown no-arrow d-sm-none">
            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-search fa-fw"></i>
            </a>
            <!-- Dropdown - Messages -->
            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in"
                aria-labelledby="searchDropdown">
                <form class="form-inline mr-auto w-100 navbar-search">
                    <div class="input-group">
                        <input type="text" class="form-control bg-light border-0 small"
                            placeholder="Search for..." aria-label="Search"
                            aria-describedby="basic-addon2">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search fa-sm"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </li>

        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button"
            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
                <!-- Counter - Notifications -->
                <span class="badge badge-danger badge-counter" id="unreadBadge" style="display: none;">
                    <span id="unreadCount">0</span>
                </span>
            </a>
            <!-- Dropdown - Alerts (Quick Preview) -->
            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="alertsDropdown">
                <h6 class="dropdown-header fw-bold">
                    Notification Center
                </h6>
                <div id="quickNotificationPanel">
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="me-3">
                            <div class="bg-light rounded-circle p-3">
                                <i class="fas fa-spinner fa-spin text-primary"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500">Loading...</div>
                        </div>
                    </a>
                </div>
            </div>
        </li>

        <div class="topbar-divider d-none d-sm-block"></div>

        <!-- Nav Item - User Information -->
        @php
            $currentUserName = 'Tamu';
            if (auth()->check() && auth()->user()) {
                $currentUserName = auth()->user()->name;
            } elseif (session()->has('face_attendance_name') && session('face_attendance_name')) {
                $currentUserName = session('face_attendance_name');
            } elseif (session()->has('face_attendance_npk') && session('face_attendance_npk')) {
                $currentUserName = 'Karyawan (' . session('face_attendance_npk') . ')';
            }
        @endphp
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="mr-2 d-none d-lg-inline text-gray-600 small">{{ $currentUserName }}</span>
                <img class="img-profile rounded-circle"
                    src="{{asset('img/undraw_profile.svg')}}">
            </a>
            <!-- Dropdown - User Information -->
            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="userDropdown">
                <!-- <a class="dropdown-item" href="{{ route('user.profile') }}">
                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                    Profile
                </a> -->
                @if(session()->has('face_attendance_npk') && !auth()->check())
                    <form action="{{ route('face.attendance.logout-session') }}" method="POST" id="faceLogoutForm" style="display:none;">
                        @csrf
                    </form>
                    <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('faceLogoutForm').submit();">
                        <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                        Logout
                    </a>
                @elseif(auth()->check())
                    <a class="dropdown-item" href="{{ route('logout') }}">
                        <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                        Logout
                    </a>
                @else
                    <a class="dropdown-item" href="{{ route('login') }}">
                        <i class="fas fa-sign-in-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                        Login
                    </a>
                @endif
            </div>
        </li>

    </ul>

</nav>
<!-- End of Topbar -->

{{-- Fail-safe handler untuk Sidebar Toggle dan Profile/Alerts Dropdown --}}
<script>
(function() {
    function initNavbarHandlers() {
        // 1. Sidebar Toggle (Desktop & Mobile)
        const toggleButtons = document.querySelectorAll('#sidebarToggle, #sidebarToggleTop');
        toggleButtons.forEach(function(btn) {
            if (btn.__sidebarToggleBound) return;
            btn.__sidebarToggleBound = true;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                document.body.classList.toggle('sidebar-toggled');
                const sidebar = document.querySelector('.sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('toggled');
                    if (sidebar.classList.contains('toggled')) {
                        const collapses = sidebar.querySelectorAll('.collapse');
                        collapses.forEach(c => c.classList.remove('show'));
                    }
                }
            });
        });

        // 2. User Profile Dropdown Toggle
        const userDropdown = document.getElementById('userDropdown');
        if (userDropdown && !userDropdown.__dropdownBound) {
            userDropdown.__dropdownBound = true;
            userDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const parent = this.closest('.dropdown');
                const menu = parent ? parent.querySelector('.dropdown-menu') : null;
                if (menu) {
                    const isOpen = menu.classList.contains('show');
                    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
                    if (!isOpen) {
                        menu.classList.add('show');
                    }
                }
            });
        }

        // 3. Alerts / Notification Dropdown Toggle
        const alertsDropdown = document.getElementById('alertsDropdown');
        if (alertsDropdown && !alertsDropdown.__dropdownBound) {
            alertsDropdown.__dropdownBound = true;
            alertsDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const parent = this.closest('.dropdown');
                const menu = parent ? parent.querySelector('.dropdown-menu') : null;
                if (menu) {
                    const isOpen = menu.classList.contains('show');
                    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
                    if (!isOpen) {
                        menu.classList.add('show');
                    }
                }
            });
        }

        // Close dropdowns on outside click
        if (!document.__dropdownOutsideBound) {
            document.__dropdownOutsideBound = true;
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNavbarHandlers);
    } else {
        initNavbarHandlers();
    }
})();
</script>