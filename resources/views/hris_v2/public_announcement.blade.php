@extends('hris_v2.layout')

@section('judul', 'Recruitment Announcements')

@section('content')
<div class="v2-card p-4 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="v2-menu-icon mr-3"><i class="fas fa-scroll"></i></div>
            <div>
                <h4 class="font-weight-bold mb-1">Recruitment Announcements</h4>
                <div class="v2-footnote">Information on selection stages and official recruitment announcements.</div>
            </div>
        </div>
        <a href="{{ route('v2.public.home') }}" class="btn v2-btn v2-btn-ghost btn-sm mt-2 mt-md-0">
            <i class="fas fa-arrow-left mr-1"></i> Public Portal
        </a>
    </div>
</div>

<div class="v2-card p-4 mb-3">
    <h6 class="font-weight-bold mb-2">Recruitment Stages</h6>
    <ol class="mb-0 pl-3 v2-muted" style="font-size:.9rem;">
        <li>Registration &amp; document upload (see <a href="{{ route('job-vacancy.public') }}">open positions</a>)</li>
        <li>Administrative screening</li>
        <li>Test &amp; interview</li>
        <li>Result announcement</li>
    </ol>
</div>

<div class="v2-card p-4 mb-3">
    <h6 class="font-weight-bold mb-2">Check Your Application Status</h6>
    <div class="v2-muted mb-3" style="font-size:.9rem;">Use the registration number you received when applying to see your position in the selection stages.</div>
    <a href="{{ route('portal.recruitment-status.index') }}" class="btn v2-btn v2-btn-primary">
        <i class="fas fa-search mr-1"></i>Open Application Status Check</a>
</div>

@if(count($lowongan))
    <div class="v2-card p-4">
        <h6 class="font-weight-bold mb-2">Currently open positions</h6>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr><th>Position</th><th>Department</th><th>Period</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach($lowongan as $l)
                        <tr>
                            <td class="font-weight-bold">{{ $l['position'] }}</td>
                            <td class="v2-muted">{{ $l['department'] }}</td>
                            <td class="v2-muted">{{ $l['open'] }} — {{ $l['close'] }}</td>
                            <td><span class="v2-badge v2-badge-navy">{{ $l['status_label'] ?: '-' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
