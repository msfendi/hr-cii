@extends('hris_v2.layout')

@section('judul', 'Employee Portal')

@push('styles')
<style>
    .emp-hero { text-align: left; }
    .emp-hero h1 { font-size: clamp(1.7rem, 3.6vw, 2.6rem); margin-bottom: .3rem; }
    .emp-avatar {
        width: 62px; height: 62px; border-radius: 18px; flex: 0 0 auto;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; font-weight: 600; color: #fff;
        background: linear-gradient(140deg, rgba(59, 140, 255, .38), rgba(34, 211, 238, .22));
        border: 1px solid rgba(59, 140, 255, .55);
        box-shadow: 0 0 32px rgba(59, 140, 255, .3), inset 0 0 22px rgba(255, 255, 255, .1);
    }
    .emp-card { min-height: 210px; display: flex; flex-direction: column; }
    .emp-card .v2-menu-icon i { color: #fff; }
    .emp-card .go {
        margin-top: auto; padding-top: 16px; font-size: .68rem; letter-spacing: .18em;
        text-transform: uppercase; color: #9cc6ff; opacity: 0; transform: translateY(4px);
        transition: .3s ease;
    }
    .emp-card:hover .go { opacity: 1; transform: none; }
</style>
@endpush

@section('content')
<section class="v2-hero emp-hero">
    <span class="v2-kicker">Employee self service</span>
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="emp-avatar mr-3">{{ strtoupper(substr($profil['nama'], 0, 1)) }}</div>
            <div>
                <h1 class="mb-1">Hello, {{ $profil['nama'] }}.</h1>
                <div class="v2-footnote">
                    NPK <strong class="v2-value">{{ $npk }}</strong> · {{ $profil['dept'] }} · {{ $profil['bagian'] }}
                    @if($loginAt)
                        · login {{ \Carbon\Carbon::createFromTimestamp($loginAt)->timezone('Asia/Jakarta')->format('H:i') }} WIB
                    @endif
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center mt-3 mt-md-0">
            <span class="v2-badge v2-badge-cyan mb-0 mr-2" style="padding:7px 13px;">
                <i class="fas fa-circle mr-1" style="font-size:.45rem;"></i>Active session
            </span>
            <form method="POST" action="{{ route('v2.employee.logout') }}" class="mb-0">
                @csrf
                <button class="btn v2-btn v2-btn-ghost btn-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i>Sign out</button>
            </form>
        </div>
    </div>
</section>

<div class="row">
    @forelse($menus as $m)
        <div class="col-12 col-md-6 col-lg-4 mb-3">
            @if($m['url'])
                <a href="{{ $m['url'] }}" class="v2-menu-card">
            @endif
            <div class="v2-card v2-card-hover emp-card p-4 h-100">
                <div class="v2-menu-icon mb-3" style="background:{{ $m['color'] }};box-shadow:0 10px 26px rgba(0,0,0,.35);border-color:rgba(255,255,255,.28);">
                    <i class="{{ $m['icon'] }}"></i>
                </div>
                <h5 class="v2-card-title mb-1">{{ $m['label'] }}</h5>
                <div class="v2-muted" style="font-size:.88rem;">{{ $m['description'] }}</div>
                @if(! $m['url'])
                    <div class="v2-badge v2-badge-grey mt-3">Route unavailable — contact admin</div>
                @else
                    <span class="go">Open <i class="fas fa-arrow-right ml-1"></i></span>
                @endif
            </div>
            @if($m['url'])
                </a>
            @endif
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-warning mb-0">No employee portal menu is active yet. Contact admin to enable menus at <code>/v2/admin</code>.
            </div>
        </div>
    @endforelse
</div>
@endsection
