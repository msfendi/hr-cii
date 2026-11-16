@extends('hris_v2.layout')

@section('judul', 'Careers — Job Openings')

@section('content')
<div class="v2-card p-4 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="v2-menu-icon mr-3"><i class="fas fa-briefcase"></i></div>
            <div>
                <h4 class="font-weight-bold mb-1">Careers — Job Openings</h4>
                <div class="v2-footnote">Open positions at PT. Chutex International Indonesia.</div>
            </div>
        </div>
        <a href="{{ route('v2.public.home') }}" class="btn v2-btn v2-btn-ghost btn-sm mt-2 mt-md-0">
            <i class="fas fa-arrow-left mr-1"></i> Public Portal
        </a>
    </div>
</div>

@if(count($lowongan) === 0)
    <div class="alert alert-info">No job openings are published right now.</div>
@else
    <div class="row">
        @foreach($lowongan as $l)
            <div class="col-12 col-md-6 mb-3">
                <div class="v2-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="font-weight-bold mb-1">{{ $l['position'] }}</h5>
                        <span class="v2-badge v2-badge-navy">{{ $l['status_label'] ?: 'Vacancy' }}</span>
                    </div>
                    <div class="v2-muted mb-2" style="font-size:.88rem;">
                        {{ $l['department'] }} · needs {{ $l['total_needed'] ?? '-' }} people
                    </div>
                    <div class="v2-footnote">
                        <i class="far fa-calendar-alt mr-1"></i>Open {{ $l['open'] }} — closes {{ $l['close'] }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="v2-card p-3">
    <div class="v2-footnote">
        <i class="fas fa-info-circle mr-1"></i>Full details &amp; registration: <a href="{{ route('job-vacancy.public') }}">official job opening page</a>. Check application status at <a href="{{ route('portal.recruitment-status.index') }}">recruitment status portal</a>.
    </div>
</div>
@endsection
