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
    {{-- Career card: goes straight to the /lowongan route (job-vacancy.public) --}}
    <div class="col-12 col-md-4 mb-3">
        <a href="{{ route('job-vacancy.public') }}" class="v2-menu-card">
            <div class="v2-card v2-card-hover pub-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="v2-menu-icon pub-icon-a"><i class="fas fa-briefcase"></i></div>
                    <span class="v2-num">01</span>
                </div>
                <span class="v2-label mb-1">Vacancies</span>
                <h5 class="v2-card-title mb-2">Careers — Job Openings</h5>
                <div class="v2-muted" style="font-size:.88rem;">Open positions along with their requirements.</div>
                <span class="arrow">View openings <i class="fas fa-arrow-right"></i></span>
            </div>
        </a>
    </div>

    <div class="col-12 col-md-4 mb-3">
        <a href="{{ route('portal.recruitment-status.index') }}" class="v2-menu-card">
            <div class="v2-card v2-card-hover pub-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="v2-menu-icon pub-icon-b"><i class="fas fa-search"></i></div>
                    <span class="v2-num">02</span>
                </div>
                <span class="v2-label mb-1">Application</span>
                <h5 class="v2-card-title mb-2">Check Application Status</h5>
                <div class="v2-muted" style="font-size:.88rem;">Track your application (registration number / national ID).</div>
                <span class="arrow">Track status <i class="fas fa-arrow-right"></i></span>
            </div>
        </a>
    </div>

    <div class="col-12 col-md-4 mb-3">
        <a href="{{ route('satisfaction-survey.create') }}" class="v2-menu-card">
            <div class="v2-card v2-card-hover pub-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="v2-menu-icon pub-icon-c"><i class="fas fa-clipboard-list"></i></div>
                    <span class="v2-num">03</span>
                </div>
                <span class="v2-label mb-1">Feedback</span>
                <h5 class="v2-card-title mb-2">Satisfaction Survey</h5>
                <div class="v2-muted" style="font-size:.88rem;">Fill in the satisfaction survey: 29 scale questions and 6 open questions. No login needed.</div>
                <span class="arrow">Take the survey <i class="fas fa-arrow-right"></i></span>
            </div>
        </a>
    </div>
</div>
@endsection
