<!DOCTYPE html>
<html lang="id">
@include('layout.header')
<body id="page-top">
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
@yield('scripts')
@stack('scripts')
</body>
</html>
