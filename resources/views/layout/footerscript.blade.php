@if (config('app.env') === 'local' && !config('app.debug'))
    {{-- Disable debugbar in local production-like --}}
@else
    {{-- Debugbar --}}
    {{-- <script>console.log('Debugbar loaded');</script> --}}
@endif

{{-- Core vendor JS (jQuery, Bootstrap, Select2, dsb-admin).
     Halaman popup/login (employee-payroll, cuti/login) TIDAK meng-include
     'layout.footer' — hanya 'layout.footerscript' — sehingga jQuery tidak
     pernah termuat dan muncul error "$ is not defined", tab/jQuery tidak
     bisa diklik.

     CATATAN: script dimuat secara SINKRON (plain <script src>) agar $ tersedia
     SEBELUM inline script di bawah berjalan. Jangan pakai document.createElement
     + appendChild karena itu async dan $ masih undefined saat inline script jalan.

     PERBAIKAN V32 (2026-11-09): klaim "halaman yang juga include
     'layout.footer' tidak suffers double-load" ITU SALAH untuk
     layouts/app. Di layouts/app urutannya footer (baris 22) DULU,
     footerscript (baris 26) SESUDAHNYA, dan keduanya memuat
     jquery + bootstrap.bundle + sb-admin-2 yang sama.

     Akibat double-load: plugin Bootstrap 4.6 (termasuk collapse)
     terdaftar DUA kali. Handler klik collapse berjalan dua kali
     (toggle lalu toggle lagi) sehingga submenu sidebar TIDAK MAU
     TERTUTUP setelah terbuka (/face-attendance/settings &
     /face-attendance/admin/log, keduanya layouts/app).

     Solusi: @once menjamin blok vendor ini hanya dirender SEKALI
     per request, tidak peduli berapa kali file ini di-include.
     Halaman footer-only (tanpa footerscript) tetap dapat vendor dari
     footer; halaman layouts/app hanya dapat SATU kali. --}}

@once('vendor-js')
@if (!file_exists(public_path('vendor/jquery/jquery.min.js')))
    {{-- vendor jquery tidak ada, jangan emit tag rusak --}}
@else
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/jquery/select2.min.js') }}"></script>
    <script src="{{ asset('vendor/jquery-easing/jquery.easing.min.js') }}"></script>
    <script src="{{ asset('js/sb-admin-2.min.js') }}"></script>
@endif
@endonce

{{-- ============================================================
     TOGGLE SIDEBAR — SATU handler saja (PERBAIKAN 2026-11-03)
     ============================================================
     MASALAH: tombol burger (#sidebarToggleTop) tidak bisa show/hide sidebar.
     Awalnya ada TIGA sumber yang saling bertabrakan:
       1. sb-admin-2.min.js  -> jQuery: body.toggleClass + .sidebar.toggleClass
       2. blok inline #1     -> toggleSidebar() + localStorage
       3. blok inline #2     -> bindSidebarToggle() (idempoten via dataset)
     Handler #1 dan #3 sama-sama menempel pada tombol yang sama, sehingga
     satu klik = dua kali toggle (buka lalu tutup lagi seketika) -> sidebar
     terlihat "tidak bereaksi". Kombinasi ini yang membuat burger mati.

     SOLUSI: satu implementasi, didaftarkan lewat event delegation di
     document (tahan terhadap navigasi dinamis / konten yang di-render
     ulang), dan tiap klik hanya mengubah state SATU kali. Tidak ada lagi
     handler ganda karena sb-admin-2 dimatikan untuk tombol ini di bawah.
     ============================================================ --}}
<script>
(function () {
    'use strict';

    var KEY = 'sidebarToggled';

    function sidebarEl() { return document.querySelector('.sidebar'); }
    function isToggled()  { return document.body.classList.contains('sidebar-toggled'); }

    function apply(toggled, persist) {
        var sb = sidebarEl();
        document.body.classList.toggle('sidebar-toggled', toggled);
        if (sb) { sb.classList.toggle('toggled', toggled); }

        // Sinkronkan aria-expanded: true = sidebar terlihat (tidak toggled)
        ['#sidebarToggle', '#sidebarToggleTop'].forEach(function (sel) {
            var b = document.querySelector(sel);
            if (b) { b.setAttribute('aria-expanded', toggled ? 'false' : 'true'); }
        });

        if (persist) {
            try { localStorage.setItem(KEY, toggled ? '1' : '0'); } catch (e) {}
        }
    }

    // Delegation: satu listener untuk semua tombol, aman walau tombol
    // di-render ulang (AJAX / pjax). Tidak mungkin terjadi double-bind.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('#sidebarToggle, #sidebarToggleTop') : null;
        if (!btn) { return; }

        e.preventDefault();
        e.stopPropagation();
        // PENTING: jangan biarkan handler jQuery sb-admin-2 ikut jalan,
        // karena itu akan melakukan toggle kedua kalinya.
        if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }

        apply(!isToggled(), true);
    }, true); // capture: menang sebelum handler jQuery (bubble) dieksekusi

    // Pulihkan state terakhir
    var saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) {}
    apply(saved === '1', false);

    // Tutup otomatis di layar kecil saat klik di luar sidebar
    document.addEventListener('click', function (e) {
        if (window.innerWidth >= 768 || !isToggled()) { return; }
        var sb = sidebarEl();
        var btn = e.target.closest ? e.target.closest('#sidebarToggle, #sidebarToggleTop') : null;
        if (sb && sb.contains(e.target)) { return; }
        if (btn) { return; }
        apply(false, true);
    });
})();
</script>

@if (isset($hasFaceAttendanceAssets) && $hasFaceAttendanceAssets)
    @include('face_attendance._scripts')
@endif

{{-- @stack('scripts') dirender DI SINI (sebelumnya di layouts/app baris 28, setelah
     @include('layout.footerscript')). @stack dirender in place, jadi dulu output
     footerscript = [jQuery ... sidebar-race JS ...] lalu baru @stack -> JS halaman
     (@push('scripts'), mis. DataTables log_index) jalan SETELAH jQuery.
     Di v5 @stack dipindah? Tidak -- KEMBALI ke di sini adalah agar halaman yang
     @include('layout.footerscript') langsung (chatbot, event_invitation, auth/login,
     cuti/form, hris, approval, ...) ikut merender @push('scripts') mereka.
     layouts/app me-render footerscript lalu @stack('scripts') SATU kali di sini;
     jangan tambah @stack lagi di layouts/app (double-push -> DataTables tn/3). --}}
@stack('scripts')