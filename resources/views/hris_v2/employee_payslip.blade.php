@extends('hris_v2.layout')

@section('judul', 'Payslip — Employee Portal')

@push('styles')
<style>
    .slip-head h1 { font-size: clamp(1.6rem, 3.4vw, 2.3rem); margin-bottom: .25rem; }
    .slip-card { overflow: hidden; }
    .slip-card .table thead th { padding: 14px 20px; }
    .slip-card .table td { padding: 16px 20px; }
    .slip-period { color: #fff; font-weight: 600; letter-spacing: .01em; }
    .slip-range { color: var(--v2-muted); font-size: .86rem; }
</style>
@endpush

@section('content')
<section class="v2-hero slip-head text-left">
    <span class="v2-kicker">Payroll</span>
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="v2-menu-icon mr-3" style="width:58px;height:58px;border-radius:16px;font-size:1.25rem;">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div>
                <h1>Payslip</h1>
                <div class="v2-footnote">NPK <strong class="v2-value">{{ $npk }}</strong> — {{ $nama }} · only your own payslip is shown.</div>
            </div>
        </div>
        <a href="{{ route('v2.employee.home') }}" class="btn v2-btn v2-btn-ghost btn-sm mt-3 mt-md-0">
            <i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>
</section>

@if(! $slips['tersedia'])
    <div class="alert alert-warning">
        <strong>Payslip data could not be read.</strong>
        <div class="v2-footnote mt-1">{{ $slips['catatan'] }}</div>
        <div class="v2-footnote mt-1">Admin note: make sure the table <code>payroll_run_details</code>, <code>payroll_runs</code>, and
            <code>payroll_periods</code> can be read by the application database connection.
        </div>
    </div>
@elseif(count($slips['items']) === 0)
    <div class="alert alert-info mb-0">
        No payslip yet for NPK {{ $npk }} in any closed payroll period.
    </div>
@else
    <div class="v2-card slip-card p-0">
        <div class="v2-table-wrap">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Range</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($slips['items'] as $s)
                        <tr>
                            <td class="slip-period">{{ $s['periode'] }}</td>
                            <td class="slip-range">{{ $s['rentang'] }}</td>
                            <td class="text-right">
                                <a href="{{ $s['url'] }}" target="_blank" class="btn v2-btn v2-btn-primary btn-sm">
                                    <i class="fas fa-file-pdf mr-1"></i>Open Payslip</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
