<!DOCTYPE html>
<html lang="id">
@include('layout.header')
<body id="page-top">
@include('partials.pwa_splash')
@include('sweetalert::alert')

<div id="wrapper">
    @if(auth()->check() || Session::has('face_attendance_npk'))
        @include('layout.sidebar')
    @endif

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @if(auth()->check() || Session::has('face_attendance_npk'))
                @include('layout.navbar')
            @endif

            <div class="container-fluid py-3">
                @yield('content')
            </div>
        </div>
        @include('layout.footer')
    </div>
</div>

@include('layout.footerscript')
{{-- @stack('scripts') TIDAK dirender di sini: sudah dirender di dalam
     layout.footerscript (di akhir file). Merender lagi di sini =
     double-push -> DataTables warning "Cannot reinitialise DataTable"
     (datatables.net/tn/3) pada halaman @push('scripts') (mis. log_index). --}}
@yield('scripts')
</body>
</html>
