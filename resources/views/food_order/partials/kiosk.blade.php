<!-- resources/views/food_order/partials/kiosk.blade.php -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .fm-kiosk{ --fm-red:#4E73DF; --fm-red-dark:#224ABE; --fm-yellow:#F6C23E; --fm-green:#1CC88A;
        --fm-danger:#E74A3B; --fm-danger-dark:#BE2617;
        --fm-dark:#5A5C69; --fm-cream:#EAECF4; --fm-white:#FFFFFF; --fm-gray:#858796; --fm-border:#E3E6F0;
        --fm-shadow:0 10px 30px rgba(78,115,223,.10); font-family:'Inter',sans-serif; color:var(--fm-dark);
        max-width:1200px; margin:0 auto; width:100%; }
    .fm-kiosk h1,.fm-kiosk h2,.fm-kiosk h3,.fm-kiosk .fm-display{ font-family:'Baloo 2',sans-serif; }

    /* Hero */
    .fm-hero{ background:linear-gradient(135deg,var(--fm-red),var(--fm-red-dark)); border-radius:24px;
        padding:26px 30px; color:#fff; margin-bottom:22px; box-shadow:var(--fm-shadow);
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; }
    .fm-hero h1{ font-size:1.7rem; font-weight:800; margin-bottom:4px; }
    .fm-hero p{ margin:0; opacity:.9; font-size:.86rem; }
    .fm-hero .fm-date-box{ background:rgba(255,255,255,.15); border-radius:16px; padding:12px 16px; }
    .fm-hero .fm-date-box label{ font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; opacity:.85; margin-bottom:4px; display:block; font-weight:700; }
    .fm-hero .fm-date-box input{ border:none; border-radius:10px; padding:8px 12px; font-weight:700; color:var(--fm-dark); font-size:.9rem; min-width:170px; }
    .fm-hero .fm-min-note{ font-size:.72rem; opacity:.85; margin-top:6px; }

    /* Header actions (logout scan) */
    .fm-header-actions{ display:flex; justify-content:flex-end; margin-bottom:14px; }
    .fm-user-chip{ background:var(--fm-white); border-radius:16px; padding:8px 16px; box-shadow:var(--fm-shadow);
        display:flex; align-items:center; gap:12px; font-size:.85rem; flex-wrap:wrap; }
    .fm-user-chip .fm-user-name{ font-weight:700; color:var(--fm-dark); }
    .fm-user-chip .fm-user-npk{ color:var(--fm-gray); font-size:.78rem; }
    .fm-btn-logout-scan{ background:var(--fm-white); border:1.5px solid #F5C6C0; color:var(--fm-danger); font-weight:700;
        padding:6px 16px; border-radius:12px; font-size:.8rem; white-space:nowrap; }
    .fm-btn-logout-scan:hover{ background:#FDECEA; color:var(--fm-danger-dark); }

    /* Current order receipt */
    .fm-current-order{ background:var(--fm-white); border-radius:20px; padding:18px 22px; margin-bottom:26px;
        border-left:6px solid var(--fm-green); box-shadow:var(--fm-shadow);
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; }
    .fm-current-order .fm-co-label{ font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:var(--fm-gray); font-weight:700; margin-bottom:3px; }
    .fm-current-order .fm-co-menu{ font-size:1.1rem; font-weight:700; }
    .fm-current-order .fm-co-canteen{ color:var(--fm-gray); font-size:.85rem; }
    .fm-status-chip{ display:inline-flex; align-items:center; gap:5px; font-size:.72rem; font-weight:700; padding:4px 11px;
        border-radius:20px; background:#FFF3D6; color:#9A6B00; margin-left:8px; }
    .fm-status-chip.locked{ background:#F3EDE4; color:var(--fm-gray); }
    .fm-btn-cancel{ background:var(--fm-white); border:1.5px solid #F5C6C0; color:var(--fm-danger); font-weight:700;
        padding:9px 20px; border-radius:14px; font-size:.85rem; }
    .fm-btn-cancel:hover{ background:#FDECEA; color:var(--fm-danger-dark); }

    /* Canteen tabs */
    .fm-tabs{ display:flex; gap:10px; flex-wrap:wrap; margin-bottom:22px; border:none; }
    .fm-tabs .nav-link{ background:var(--fm-white); border:1.5px solid var(--fm-border); color:var(--fm-dark);
        font-weight:700; font-size:.86rem; padding:10px 20px; border-radius:20px; display:flex; align-items:center; gap:8px; }
    .fm-tabs .nav-link i{ color:var(--fm-red); }
    .fm-tabs .nav-link.active{ background:var(--fm-red); border-color:var(--fm-red); color:#fff; box-shadow:0 8px 18px rgba(78,115,223,.28); }
    .fm-tabs .nav-link.active i{ color:#fff; }
    .fm-tabs .badge-count{ background:rgba(0,0,0,.08); color:inherit; font-size:.7rem; padding:2px 8px; border-radius:20px; }
    .fm-tabs .nav-link.active .badge-count{ background:rgba(255,255,255,.25); }

    /* Menu grid */
    .fm-menu-card{ background:var(--fm-white); border-radius:20px; overflow:hidden; box-shadow:var(--fm-shadow);
        transition:transform .15s ease, box-shadow .15s ease; height:100%; display:flex; flex-direction:column; border:2px solid transparent; }
    .fm-menu-card:hover{ transform:translateY(-4px); }
    .fm-menu-card.selected{ border-color:var(--fm-green); }
    .fm-menu-photo{ height:160px; background:var(--fm-cream); position:relative; overflow:hidden; }
    .fm-menu-photo img{ width:100%; height:100%; object-fit:cover; }
    .fm-menu-photo .fm-photo-placeholder{ display:flex; align-items:center; justify-content:center; height:100%; color:#C9B79C; font-size:2rem; }
    .fm-menu-photo .fm-selected-badge{ position:absolute; top:10px; right:10px; background:var(--fm-green); color:#fff;
        width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.85rem; box-shadow:0 4px 10px rgba(0,0,0,.2); }
    .fm-menu-body{ padding:14px 16px 16px; display:flex; flex-direction:column; flex:1; }
    .fm-menu-name{ font-weight:700; font-size:1rem; margin-bottom:4px; line-height:1.25; }
    .fm-menu-desc{ font-size:.8rem; color:var(--fm-gray); margin-bottom:10px; min-height:18px; }
    .fm-quota-chip{ display:inline-block; font-size:.7rem; font-weight:700; padding:3px 10px; border-radius:20px; margin-bottom:10px; align-self:flex-start; }
    .fm-quota-chip.ok{ background:#FFF3D6; color:#9A6B00; }
    .fm-quota-chip.out{ background:#FDECEA; color:var(--fm-danger-dark); }
    .fm-menu-body .fm-btn-choose{ margin-top:auto; width:100%; border:none; border-radius:14px; font-weight:700;
        padding:10px 12px; font-size:.85rem; }
    .fm-btn-choose.primary{ background:var(--fm-red); color:#fff; box-shadow:0 6px 14px rgba(78,115,223,.28); }
    .fm-btn-choose.primary:hover{ background:var(--fm-red-dark); }
    .fm-btn-choose.selected{ background:var(--fm-green); color:#fff; }
    .fm-btn-choose.disabled{ background:#F3EDE4; color:var(--fm-gray); cursor:not-allowed; }

    .fm-empty-state{ background:var(--fm-white); border-radius:20px; padding:40px 20px; text-align:center; color:var(--fm-gray); box-shadow:var(--fm-shadow); }
    .fm-empty-state i{ font-size:2rem; margin-bottom:10px; display:block; color:#E7D9C3; }

    .fm-alert{ border-radius:14px; font-weight:600; font-size:.88rem; }

    /* ===== Mobile fix: jangan full-bleed, kasih napas kanan-kiri ===== */
    @media (max-width:576px){
        .fm-kiosk{ padding:0 4px; }
        .fm-hero{ padding:20px 18px; border-radius:18px; }
        .fm-hero h1{ font-size:1.35rem; }
        .fm-hero .fm-date-box{ width:100%; }
        .fm-hero .fm-date-box input{ width:100%; min-width:0; }
        .fm-current-order{ padding:16px 18px; border-radius:16px; }
        .fm-user-chip{ width:100%; justify-content:space-between; }
        .fm-tabs .nav-link{ padding:8px 14px; font-size:.8rem; }
        .fm-menu-photo{ height:130px; }
    }
</style>

<div class="fm-kiosk">

    @if($isQrMode)
    <br>
        <div class="fm-header-actions">
            <div class="fm-user-chip">
                <div>
                    <div class="fm-user-name"><i class="fas fa-id-card text-primary"></i> {{ session('food_order.nama') }}</div>
                    <div class="fm-user-npk">NPK: {{ session('food_order.npk') }}</div>
                </div>
                <form method="POST" action="{{ route('food-orders.logout-scan') }}"
                      onsubmit="return confirm('Keluar dari sesi ini?');">
                    @csrf
                    <button type="submit" class="fm-btn-logout-scan">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    @endif

    <div class="fm-hero">
        <div>
            <h1><i class="fas fa-utensils"></i> Pesan Makanan Karyawan</h1>
            <p>Pilih menu favoritmu untuk tanggal yang tersedia — kayak pesan di kiosk resto!</p>
        </div>
        <form method="GET" action="{{ route('food-orders.index') }}" class="fm-date-box">
            <label>Tanggal Pesan</label>
            <input type="text" id="orderDatePicker" name="date"
                   value="{{ $selectedDate->toDateString() }}"
                   data-min="{{ $minDate->toDateString() }}"
                   autocomplete="off">
            <div class="fm-min-note"><i class="fas fa-info-circle"></i> Minimal H-1 (besok: {{ $minDate->translatedFormat('d M Y') }})</div>
        </form>
    </div>

    @if(session('success'))
        <div class="alert alert-success fm-alert">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger fm-alert">{{ session('error') }}</div>
    @endif

    @if($myOrder)
        <div class="fm-current-order">
            <div>
                <div class="fm-co-label">Pesanan kamu · {{ $selectedDate->translatedFormat('d M Y') }}</div>
                <div class="fm-co-menu">{{ $myOrder->foodMenu->name }}
                    @if($myOrder->canBeEdited())
                        <span class="fm-status-chip"><i class="fas fa-circle"></i> {{ ucfirst($myOrder->status) }}</span>
                    @else
                        <span class="fm-status-chip locked"><i class="fas fa-lock"></i> Terkunci</span>
                    @endif
                </div>
                <div class="fm-co-canteen"><i class="fas fa-store"></i> {{ $myOrder->foodMenu->canteen->name }}</div>
            </div>
            @if($myOrder->canBeEdited())
                <form method="POST" action="{{ route('food-orders.destroy', $myOrder->id) }}"
                      onsubmit="return confirm('Batalkan pesanan ini?');">
                    @csrf @method('DELETE')
                    <button class="fm-btn-cancel"><i class="fas fa-times"></i> Batalkan Pesanan</button>
                </form>
            @endif
        </div>
    @endif

    @if($menus->isEmpty())
        <div class="fm-empty-state">
            <i class="fas fa-calendar-times"></i>
            Belum ada menu tersedia untuk tanggal {{ $selectedDate->translatedFormat('d M Y') }}.
        </div>
    @else
        <ul class="nav fm-tabs" id="canteenTabs" role="tablist">
            @foreach($menus as $canteenName => $menuList)
                <li class="nav-item">
                    <a class="nav-link {{ $loop->first ? 'active' : '' }}" id="tab-{{ $loop->index }}-btn"
                       data-toggle="pill" href="#tab-{{ $loop->index }}" role="tab">
                        <i class="fas fa-store"></i> {{ $canteenName }}
                        <span class="badge-count">{{ $menuList->count() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            @foreach($menus as $canteenName => $menuList)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab-{{ $loop->index }}" role="tabpanel">
                    <div class="row">
                        @foreach($menuList as $menu)
                            @php
                                $remaining = $menu->remainingQuota($selectedDate);
                                $isSelected = optional($myOrder)->food_menu_id === $menu->id;
                                $isOut = !is_null($remaining) && $remaining <= 0 && !$isSelected;
                                $isLocked = $myOrder && !$myOrder->canBeEdited();
                            @endphp
                            <div class="col-xl-3 col-lg-4 col-md-6 col-6 mb-4">
                                <div class="fm-menu-card {{ $isSelected ? 'selected' : '' }}">
                                    <div class="fm-menu-photo">
                                        @if($menu->photo)
                                            <img src="{{ asset('storage/'.$menu->photo) }}" alt="{{ $menu->name }}">
                                        @else
                                            <div class="fm-photo-placeholder"><i class="fas fa-utensils"></i></div>
                                        @endif
                                        @if($isSelected)
                                            <span class="fm-selected-badge"><i class="fas fa-check"></i></span>
                                        @endif
                                    </div>
                                    <div class="fm-menu-body">
                                        <div class="fm-menu-name">{{ $menu->name }}</div>
                                        <div class="fm-menu-desc">{{ Str::limit($menu->description, 55) }}</div>

                                        @if(!is_null($remaining))
                                            <span class="fm-quota-chip {{ $remaining > 0 ? 'ok' : 'out' }}">
                                                <i class="fas fa-layer-group"></i> Sisa kuota: {{ $remaining }}
                                            </span>
                                        @endif

                                        @if($isOut)
                                            <button class="fm-btn-choose disabled" disabled>Kuota Habis</button>
                                        @elseif($isLocked)
                                            <button class="fm-btn-choose disabled" disabled>Terkunci</button>
                                        @elseif($isSelected)
                                            <button class="fm-btn-choose selected" disabled><i class="fas fa-check"></i> Dipilih</button>
                                        @else
                                            <form method="POST" action="{{ route('food-orders.store') }}">
                                                @csrf
                                                <input type="hidden" name="food_menu_id" value="{{ $menu->id }}">
                                                <input type="hidden" name="order_date" value="{{ $selectedDate->toDateString() }}">
                                                <button type="submit" class="fm-btn-choose primary">
                                                    {{ $myOrder ? 'Ganti ke Menu Ini' : 'Pilih Menu Ini' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>