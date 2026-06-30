@php
    use App\Models\Menu;

    $role = $roleusers[0]->rolename;
    $currentRole = \App\Models\Role::where('name', $role)->first();
    $sidebarMenus = $currentRole ? Menu::buildSidebarFor($currentRole) : collect();
@endphp

<!-- <script>
    const currentRole = @json($currentRole);
    console.log(currentRole);
</script> -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
   {{-- BRAND --}}
   <a class="sidebar-brand d-flex align-items-center justify-content-center">
      <div class="sidebar-brand-icon">
         <img src="{{ asset('img/chutex.svg') }}" style="width:40px;">
      </div>
      <div class="sidebar-brand-text mx-3">Chutex <sup>Sys</sup></div>
   </a>
   <hr class="sidebar-divider my-0">

   {{-- DASHBOARD (selalu tampil, tidak butuh permission) --}}
   <li class="nav-item active">
      <a class="nav-link" href="{{ route('home') }}">
         <i class="fas fa-tachometer-alt"></i>
         <span>Dashboard</span>
      </a>
   </li>

   {{-- MENU DINAMIS DARI DATABASE --}}
   @foreach ($sidebarMenus as $menu)
      @if ($menu->children->isEmpty() && $menu->route_name)
         {{-- Parent tanpa anak tapi punya route sendiri, tampilkan sebagai link langsung --}}
         <li class="nav-item">
            <a class="nav-link" href="{{ route($menu->route_name) }}">
               <i class="{{ $menu->icon ?? 'fas fa-circle' }}"></i>
               <span>{{ $menu->name }}</span>
            </a>
         </li>
      @else
         <li class="nav-item">
            <a class="nav-link collapsed" data-toggle="collapse" data-target="#menu-{{ $menu->id }}">
               <i class="{{ $menu->icon ?? 'fas fa-circle' }}"></i>
               <span>{{ $menu->name }}</span>
            </a>
            <div id="menu-{{ $menu->id }}" class="collapse" data-parent="#accordionSidebar">
               <div class="collapse-inner bg-white rounded">
                  @foreach ($menu->children as $child)
                     <a class="collapse-item" href="{{ $child->route_name ? route($child->route_name) : '#' }}">
                        {{ $child->name }}
                     </a>
                  @endforeach
               </div>
            </div>
         </li>
      @endif
   @endforeach

   <hr class="sidebar-divider d-none d-md-block">
</ul>

{{-- Bagian notifikasi realtime (Pusher/Reverb) di bawah ini TIDAK berubah,
     biarkan persis seperti file sidebar lama Anda --}}




    <div id="notif-container"
        style="position:fixed;top:20px;right:20px;z-index:9999;">
    </div>

    <!-- Pusher -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

    <!-- Laravel Echo -->
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo/dist/echo.iife.js"></script>

    <script>
        window.Pusher = Pusher;

        const EchoClass = window.Echo.default ?? window.Echo;
        const wsHost = window.location.hostname;

        window.Echo = new EchoClass({
            broadcaster: 'pusher',

            key: window.reverbKey,

            wsHost: wsHost,
            wsPort: 8800,
            wssPort: 443,

            cluster: 'mt1',

            forceTLS: window.location.protocol === 'https:',
            disableStats: true,
            enabledTransports: ['ws','wss'],
        });
    </script>
    <script>
window.Echo.channel('notification-channel')
.listen('NotificationEvent', (e) => {

    /*
    =====================================
    TYPE CONFIG
    =====================================
    */

    const types = {

        success:{
            border:'#28a745',
            bg:'#e9f9ee',
            color:'#28a745',
            icon:'✔'
        },

        danger:{
            border:'#dc3545',
            bg:'#fdeaea',
            color:'#dc3545',
            icon:'✖'
        },

        warning:{
            border:'#ffc107',
            bg:'#fff8e1',
            color:'#ffc107',
            icon:'⚠'
        }

    };

    const type = types[e.type] ?? types.success;

    /*
    =====================================
    BUILD NOTIFICATION
    =====================================
    */

    const notif = document.createElement('div');

    notif.innerHTML = `
    <div style="
        display:flex;
        align-items:center;
        gap:14px;
        background:rgba(255,255,255,.95);
        backdrop-filter:blur(10px);
        border-left:5px solid ${type.border};
        padding:16px;
        margin-bottom:12px;
        border-radius:12px;
        box-shadow:0 8px 18px rgba(0,0,0,.12);
        font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
        animation:slideIn .35s ease;
    ">

        <!-- ICON -->
        <div style="
            width:42px;
            height:42px;
            border-radius:50%;
            background:${type.bg};
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:18px;
            color:${type.color};
            font-weight:bold;
            flex-shrink:0;
        ">
            ${type.icon}
        </div>

        <!-- CONTENT -->
        <div style="flex:1">

            <div style="
                font-size:15px;
                font-weight:600;
                color:#222;
                margin-bottom:3px;
            ">
                ${e.title}
            </div>

            <div style="
                font-size:14px;
                color:#555;
                line-height:1.5;
            ">
                ${e.message}
            </div>

        </div>

    </div>
    `;

    document.getElementById('notif-container')
        .prepend(notif);

    /*
    =====================================
    AUTO REMOVE
    =====================================
    */

    setTimeout(() => {
        notif.style.opacity = 0;
        notif.style.transform = 'translateY(-10px)';
        setTimeout(()=>notif.remove(),300);
    },4000);

});
</script>