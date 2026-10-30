<!DOCTYPE html>
<html lang="id">
@include('layout.header')
<body id="page-top" class="bg-gradient-primary">
@include('sweetalert::alert')

<div class="container py-4">
    @yield('content')
</div>

@include('layout.footerscript')
@yield('scripts')
@stack('scripts')
</body>
</html>
