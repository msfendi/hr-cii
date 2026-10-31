@if (config('app.env') === 'local' && !config('app.debug'))
    {{-- Disable debugbar in local production-like --}}
@else
    {{-- Debugbar --}}
    {{-- <script>console.log('Debugbar loaded');</script> --}}
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleIds = ['#sidebarToggle', '#sidebarToggleTop'];
        const sidebar = document.querySelector('.sidebar');
        const body = document.body;

        function setSidebarState(expanded) {
            if (expanded) {
                body.classList.add('sidebar-toggled');
                if (sidebar) sidebar.classList.add('toggled');
            } else {
                body.classList.remove('sidebar-toggled');
                if (sidebar) sidebar.classList.remove('toggled');
            }
            // Update aria-expanded on all toggle buttons
            toggleIds.forEach(selector => {
                const btn = document.querySelector(selector);
                if (btn) btn.setAttribute('aria-expanded', expanded);
            });
            // Persist to localStorage
            localStorage.setItem('sidebarToggled', expanded);
        }

        function toggleSidebar() {
            const currentlyExpanded = body.classList.contains('sidebar-toggled');
            setSidebarState(!currentlyExpanded);
        }

        // Initialize from localStorage (or default to expanded)
        const savedState = localStorage.getItem('sidebarToggled');
        if (savedState !== null) {
            setSidebarState(savedState === 'true');
        } else {
            // Default: sidebar expanded (not toggled)
            setSidebarState(false);
        }

        // Bind click handlers
        toggleIds.forEach(selector => {
            const btn = document.querySelector(selector);
            if (btn && !btn.dataset.sidebarToggleBound) {
                btn.dataset.sidebarToggleBound = 'true';
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 768) {
                const sidebar = document.querySelector('.sidebar');
                const isClickInsideSidebar = sidebar && sidebar.contains(e.target);
                const isClickOnToggle = toggleIds.some(sel => {
                    const btn = document.querySelector(sel);
                    return btn && btn.contains(e.target);
                });
                if (body.classList.contains('sidebar-toggled') && !isClickInsideSidebar && !isClickOnToggle) {
                    setSidebarState(false);
                }
            }
        });
    });
</script>

<!-- Re-init sidebar toggle on dynamic navigation (pjax, turbolinks, livewire, etc.) -->
<script>
(function() {
    function bindSidebarToggle() {
        const toggleIds = ['#sidebarToggle', '#sidebarToggleTop'];
        const sidebar = document.querySelector('.sidebar');
        const body = document.body;

        function setSidebarState(expanded) {
            if (expanded) {
                body.classList.add('sidebar-toggled');
                if (sidebar) sidebar.classList.add('toggled');
            } else {
                body.classList.remove('sidebar-toggled');
                if (sidebar) sidebar.classList.remove('toggled');
            }
            toggleIds.forEach(selector => {
                const btn = document.querySelector(selector);
                if (btn) btn.setAttribute('aria-expanded', expanded);
            });
        }

        function toggleSidebar() {
            const currentlyExpanded = body.classList.contains('sidebar-toggled');
            setSidebarState(!currentlyExpanded);
            localStorage.setItem('sidebarToggled', !currentlyExpanded);
        }

        toggleIds.forEach(selector => {
            const btn = document.querySelector(selector);
            if (btn && !btn.dataset.sidebarToggleBound) {
                btn.dataset.sidebarToggleBound = 'true';
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }
        });
    }

    // Initial bind
    bindSidebarToggle();

    // Re-bind on common dynamic navigation events
    document.addEventListener('pjax:complete', bindSidebarToggle);
    document.addEventListener('turbolinks:load', bindSidebarToggle);
    document.addEventListener('livewire:load', bindSidebarToggle);
    document.addEventListener('DOMContentLoaded', bindSidebarToggle);
})();
</script>

@if (isset($hasFaceAttendanceAssets) && $hasFaceAttendanceAssets)
    @include('face_attendance._scripts')
@endif

@stack('scripts')