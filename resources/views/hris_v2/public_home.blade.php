@extends('hris_v2.layout')

@section('judul', 'Public Portal — Careers & Recruitment')

@push('styles')
<style>
    .pub-card { position: relative; min-height: 232px; display: flex; flex-direction: column; }
    .pub-card .arrow {
        margin-top: auto; padding-top: 18px; display: inline-flex; align-items: center; gap: 9px;
        font-size: .7rem; letter-spacing: .18em; text-transform: uppercase; color: #9cc6ff;
    }
    .pub-card .arrow i { transition: transform .3s ease; }
    .pub-card:hover .arrow i { transform: translateX(5px); }
    .pub-icon-a { background: rgba(52, 211, 153, .14); border-color: rgba(52, 211, 153, .38); color: #7ff0c4; }
    .pub-icon-b { background: rgba(59, 140, 255, .16); border-color: rgba(59, 140, 255, .42); color: #9cc6ff; }
    .pub-icon-c { background: rgba(34, 211, 238, .14); border-color: rgba(34, 211, 238, .38); color: #7fe6f6; }
    .v2-num { font-size: .72rem; letter-spacing: .26em; color: rgba(238, 243, 251, .38); font-weight: 300; }
</style>
@endpush

@section('content')
<section class="v2-hero">
    <span class="v2-kicker">Careers &amp; Recruitment</span>
    <h1>Build your career<br>with Chutex International.</h1>
    <p>Openings, application tracking, and our service survey — no account required.</p>
</section>

<div class="row">
    {{-- Card dibuat dari HrisV2PublicCard::aktif() supaya admin bisa
         menampilkan / menyembunyikan tiap card di /pengaturan/card-menu-hris.
         Nomor (01, 02, ...) dihitung ulang agar tetap rapat. --}}
    @forelse($publicCards as $card)
        <div class="col-12 col-md-4 mb-3">
            @if($card['url'])
                <a href="{{ $card['url'] }}" class="v2-menu-card">
            @else
                <div class="v2-menu-card">
            @endif
                <div class="v2-card v2-card-hover pub-card p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="v2-menu-icon {{ $card['icon_class'] }}"><i class="{{ $card['icon'] }}"></i></div>
                        <span class="v2-num">{{ $card['number'] }}</span>
                    </div>
                    <span class="v2-label mb-1">{{ $card['kicker'] }}</span>
                    <h5 class="v2-card-title mb-2">{{ $card['title'] }}</h5>
                    <div class="v2-muted" style="font-size:.88rem;">{{ $card['description'] }}</div>
                    @if($card['url'])
                        <span class="arrow">{{ $card['cta'] }} <i class="fas fa-arrow-right"></i></span>
                    @endif
                </div>
            @if($card['url'])
                </a>
            @else
                </div>
            @endif
        </div>
    @empty
        <div class="col-12">
            <div class="v2-card p-4">
                <div class="v2-muted">No public services are available at the moment. Please check back later.</div>
            </div>
        </div>
    @endforelse
</div>
@endsection
