<!DOCTYPE html>
<html lang="en">

@include('layout.header')

<head>
    <style>
        /* ═══════════════════════════════════════════
   TABLE — more readable, bigger cells
═══════════════════════════════════════════ */
        #dataTable thead th {
            background: #4e73df;
            color: #fff;
            border-color: #3d60d0;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .3px;
            padding: 11px 14px;
            white-space: nowrap;
            vertical-align: middle;
        }

        #dataTable tbody td {
            padding: 10px 14px;
            vertical-align: middle;
            font-size: 15px;
            border-color: #e3e6f0;
            line-height: 1.5;
        }

        #dataTable tbody tr:hover {
            background: #eef2ff;
        }

        #dataTable tbody tr:nth-child(even) {
            background: #fafbff;
        }

        #dataTable tbody tr:nth-child(even):hover {
            background: #eef2ff;
        }

        /* Avatar */
        .av {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 15px;
            flex-shrink: 0;
        }

        .av-m {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .av-f {
            background: #fce7f3;
            color: #be185d;
        }

        /* Name block */
        .name-main {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
        }

        .name-sub {
            font-size: 13.5px;
            color: #718096;
            margin-top: 1px;
        }

        /* Status badges */
        .s-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: .2px;
            white-space: nowrap;
        }

        .s-applied {
            background: #dbeafe;
            color: #1e40af;
        }

        .s-test {
            background: #e0f2fe;
            color: #0369a1;
        }

        .s-interview {
            background: #fef3c7;
            color: #92400e;
        }

        .s-onboard {
            background: #d1fae5;
            color: #065f46;
        }

        .s-salary {
            background: #e0e7ff;
            color: #4338ca;
        }

        .s-reject {
            background: #fee2e2;
            color: #991b1b;
        }

        .s-default {
            background: #f3f4f6;
            color: #374151;
        }

        /* Schedule date tag */
        .sched-tag {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }

        /* Action buttons */
        .act-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 5px;
            border: none;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
            width: 100%;
            justify-content: center;
        }

        .act-wa {
            background: #dcfce7;
            color: #15803d;
        }

        .act-wa:hover {
            background: #16a34a;
            color: #fff;
        }

        .act-det {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .act-det:hover {
            background: #2563eb;
            color: #fff;
        }

        .act-edit {
            background: #ffedd5;
            color: #ea580c;
            text-decoration: none;
        }

        .act-edit:hover {
            background: #ea580c;
            color: #fff;
            text-decoration: none;
        }

        .act-salary {
            background: #e0e7ff;
            color: #4338ca;
        }

        .act-salary:hover {
            background: #4338ca;
            color: #fff;
        }

        .salary-badge {
            display: block;
            text-align: center;
            font-size: 10.5px;
            font-weight: 700;
            padding: 4px 6px;
            border-radius: 6px;
            line-height: 1.5;
        }

        .salary-badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .salary-badge-finish {
            background: #d1fae5;
            color: #065f46;
        }

        .salary-badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Doc folder btn */
        .doc-fold-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 5px;
            border: 1px solid #e3e6f0;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            background: #f8f9fc;
            color: #5a5c69;
            transition: all .15s;
        }

        .doc-fold-btn:hover {
            background: #4e73df;
            color: #fff;
            border-color: #4e73df;
        }

        .doc-zero {
            font-size: 11px;
            color: #b7b9cc;
        }

        /* ═══════════════════════════════════════════
   MODAL — wide, compact, tabbed, colorful
═══════════════════════════════════════════ */
        #detailModal .modal-dialog {
            max-width: 1000px;
        }

        #detailModal .modal-content {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .22);
        }

        /* Hero strip */
        .det-hero {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            padding: 18px 24px 0;
            position: relative;
        }

        .det-hero::before {
            content: '';
            position: absolute;
            right: -30px;
            top: -30px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07);
        }

        .det-av {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .2);
            border: 2.5px solid rgba(255, 255, 255, .35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }

        .det-name {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
        }

        .det-sub {
            font-size: 16px;
            color: rgba(255, 255, 255, .7);
            margin-top: 2px;
        }

        /* Status badge in hero */
        .hero-status {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            background: rgba(255, 255, 255, .15);
            color: #fff;
            border: 1.5px solid rgba(255, 255, 255, .3);
            white-space: nowrap;
        }

        /* Tabs nav */
        .det-tabs {
            display: flex;
            background: rgba(0, 0, 0, .15);
            margin: 16px -24px 0;
            padding: 0 16px;
            overflow-x: auto;
        }

        .det-tab {
            padding: 9px 16px;
            font-size: 16px;
            font-weight: 700;
            color: rgba(255, 255, 255, .6);
            cursor: pointer;
            border-bottom: 2.5px solid transparent;
            white-space: nowrap;
            transition: all .15s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .det-tab:hover {
            color: rgba(255, 255, 255, .9);
        }

        .det-tab.active {
            color: #fff;
            border-bottom-color: #fff;
            background: rgba(255, 255, 255, .08);
        }

        /* Tab panes */
        .det-body {
            background: #f4f6fc;
            min-height: 340px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .det-pane {
            display: none;
            padding: 18px 20px 12px;
        }

        .det-pane.active {
            display: block;
        }

        /* Section cards inside modal */
        .sec-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e3e6f0;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .sec-card-hd {
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 1px solid #e3e6f0;
        }

        .hd-blue {
            background: #eff4ff;
            color: #3b5bdb;
            border-bottom-color: #dce4fd;
        }

        .hd-green {
            background: #ebfbee;
            color: #2f9e44;
            border-bottom-color: #d3f9d8;
        }

        .hd-yellow {
            background: #fff9db;
            color: #e67700;
            border-bottom-color: #ffec99;
        }

        .hd-red {
            background: #fff5f5;
            color: #c92a2a;
            border-bottom-color: #ffc9c9;
        }

        .hd-cyan {
            background: #e3fafc;
            color: #0c8599;
            border-bottom-color: #99e9f2;
        }

        .hd-purple {
            background: #f3f0ff;
            color: #7048e8;
            border-bottom-color: #d0bfff;
        }

        .hd-gray {
            background: #f8f9fa;
            color: #495057;
            border-bottom-color: #dee2e6;
        }

        .sec-card-body {
            padding: 10px 14px;
        }

        /* Field grid */
        .fg {
            display: grid;
            gap: 8px 16px;
        }

        .fg-2 {
            grid-template-columns: 1fr 1fr;
        }

        .fg-3 {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .fg-4 {
            grid-template-columns: 1fr 1fr 1fr 1fr;
        }

        .fl {
            font-size: 12.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #b7b9cc;
            margin-bottom: 1px;
        }

        .fv {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            word-break: break-word;
        }

        /* Sub-table */
        .stbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
        }

        .stbl thead th {
            background: #4e73df;
            color: #fff;
            padding: 6px 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: .3px;
            font-weight: 700;
        }

        .stbl tbody td {
            padding: 6px 10px;
            border-bottom: 1px solid #edf2f7;
            color: #2d3748;
        }

        .stbl tbody tr:last-child td {
            border-bottom: none;
        }

        .stbl tbody tr:hover {
            background: #f7f9fc;
        }

        .stbl-empty {
            font-size: 14px;
            color: #b7b9cc;
            padding: 6px 0;
        }

        /* Timeline in hero */
        .tl-bar {
            display: flex;
            align-items: center;
            background: rgba(0, 0, 0, .12);
            margin: 10px -24px 0;
            padding: 8px 24px;
        }

        .tl-step {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .tl-dot {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .2);
            color: rgba(255, 255, 255, .5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            border: 2px solid rgba(255, 255, 255, .2);
            transition: all .2s;
        }

        .tl-dot.done {
            background: #1cc88a;
            border-color: #1cc88a;
            color: #fff;
        }

        .tl-dot.active {
            background: #f6c23e;
            border-color: #f6c23e;
            color: #fff;
        }

        .tl-lbl {
            font-size: 13px;
            color: rgba(255, 255, 255, .55);
            margin-top: 3px;
            font-weight: 600;
        }

        .tl-date {
            font-size: 12.5px;
            color: rgba(255, 255, 255, .45);
        }

        .tl-line {
            flex: 0 0 20px;
            height: 2px;
            background: rgba(255, 255, 255, .2);
            margin-bottom: 14px;
            transition: background .2s;
        }

        .tl-line.done {
            background: #1cc88a;
        }

        .tl-dot.failed {
            background: #e74a3b;
            border-color: #e74a3b;
            color: #fff;
        }

        .tl-line.failed {
            background: #e74a3b;
        }

        /* Doc pills */
        .doc-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            background: #f8f9fc;
            border: 1.5px solid #e3e6f0;
            color: #4a5568;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            transition: all .15s;
            margin: 3px;
        }

        .doc-pill:hover {
            background: #4e73df;
            color: #fff;
            border-color: #4e73df;
            text-decoration: none;
        }

        .doc-pill i {
            font-size: 15px;
        }

        /* WA Modal */
        #whatsappModal .modal-content {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 16px 50px rgba(0, 0, 0, .18);
        }

        /* Salary Modal */
        #salaryModal .modal-content {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 16px 50px rgba(0, 0, 0, .18);
        }

        /* Filter active button */
        .btn-group .btn.active {
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, .15);
        }

        /* DataTables tweaks */
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            font-size: 13px;
            border-radius: 5px;
            border: 1px solid #d1d5db;
            padding: 4px 8px;
        }

        .dataTables_wrapper .dataTables_info {
            font-size: 13px;
            color: #6b7280;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            font-size: 13px !important;
            border-radius: 5px !important;
            padding: 4px 10px !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4e73df !important;
            color: #fff !important;
            border-color: #4e73df !important;
        }

        /* Penilaian step lock */
        .penilaian-step {
            position: relative;
            transition: opacity .2s;
        }

        .penilaian-step.locked {
            opacity: .55;
            pointer-events: none;
        }

        .step-lock-badge {
            display: none;
            position: absolute;
            top: 8px;
            right: 10px;
            background: #6c757d;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
            letter-spacing: .4px;
            text-transform: uppercase;
            z-index: 2;
        }

        .penilaian-step.locked .step-lock-badge {
            display: inline-block;
        }

        .step-number {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            margin-right: 5px;
            background: rgba(255, 255, 255, .25);
            color: #fff;
        }

        /* Image viewer trigger cursor */
        .img-viewer-link {
            cursor: zoom-in;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.css">
</head>

<body id="page-top">

    @include('sweetalert::alert')

    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')

                <div class="container-fluid">

                    {{-- Page Heading --}}
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                                <i class="fas fa-users mr-2 text-primary"></i>Recruitment List
                            </h1>
                            <p class="mb-0 text-muted small">Kelola pipeline pelamar &amp; status rekrutmen</p>
                        </div>
                        <div class="d-flex align-items-center" style="gap:10px;">
                            {{-- Tgl Pendaftaran Filter --}}
                            <div>
                                <label class="d-block mb-1"
                                    style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6b7280;">
                                    <i class="fas fa-calendar-alt mr-1"></i>Tgl Pendaftaran
                                </label>
                                <input type="date" class="form-control form-control-sm" id="filter_tgl"
                                    value="{{ request('tgl_pendaftaran') }}"
                                    style="min-width:140px; font-size:13px; font-weight:600; border-radius:6px; border:1.5px solid #d1d5db; cursor:pointer;"
                                    onchange="filterTgl(this.value)">
                            </div>
                            {{-- Pipeline Filter --}}
                            <div>
                                <label class="d-block mb-1"
                                    style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6b7280;">
                                    <i class="fas fa-filter mr-1"></i>Pipeline
                                </label>
                                <select class="form-control form-control-sm" id="filter_pipeline"
                                    style="min-width:160px; font-size:13px; font-weight:600; border-radius:6px; border:1.5px solid #d1d5db; cursor:pointer;"
                                    onchange="if(this.value) window.location.href=this.value;">
                                    <option value="{{ route('recruitment.index', request()->except('status')) }}" {{ is_null($status) ? 'selected' : '' }}>Semua Pelamar</option>
                                    <option
                                        value="{{ route('recruitment.index', array_merge(request()->query(), ['status' => 'never_confirm'])) }}"
                                        {{ $status === 'never_confirm' ? 'selected' : '' }}>Applied</option>
                                    <option
                                        value="{{ route('recruitment.index', array_merge(request()->query(), ['status' => 'onboarding'])) }}"
                                        {{ $status === 'onboarding' ? 'selected' : '' }}>Onboarding</option>
                                    <option
                                        value="{{ route('recruitment.index', array_merge(request()->query(), ['status' => 'decline'])) }}"
                                        {{ $status === 'decline' ? 'selected' : '' }}>Decline</option>
                                </select>
                            </div>

                            {{-- Penilaian Step Filter --}}
                            <div>
                                <label class="d-block mb-1"
                                    style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6b7280;">
                                    <i class="fas fa-tasks mr-1"></i>Tahap Penilaian
                                </label>
                                <select class="form-control form-control-sm" id="filter_penilaian"
                                    style="min-width:180px; font-size:13px; font-weight:600; border-radius:6px; border:1.5px solid #d1d5db; cursor:pointer;"
                                    onchange="if(this.value) window.location.href=this.value;">
                                    <option value="{{ route('recruitment.index', request()->except('status')) }}" {{ !in_array($status, ['step_interview', 'step_kesehatan', 'step_teknis', 'step_user']) ? 'selected' : '' }}>— Semua Tahap —</option>
                                    <option
                                        value="{{ route('recruitment.index', array_merge(request()->query(), ['status' => 'step_interview'])) }}"
                                        {{ $status === 'step_interview' ? 'selected' : '' }}>Tes Interview</option>
                                    <option
                                        value="{{ route('recruitment.index', array_merge(request()->query(), ['status' => 'step_kesehatan'])) }}"
                                        {{ $status === 'step_kesehatan' ? 'selected' : '' }}>Tes Kesehatan</option>
                                    <option
                                        value="{{ route('recruitment.index', array_merge(request()->query(), ['status' => 'step_teknis'])) }}"
                                        {{ $status === 'step_teknis' ? 'selected' : '' }}>Tes Teknis</option>
                                    <option
                                        value="{{ route('recruitment.index', array_merge(request()->query(), ['status' => 'step_user'])) }}"
                                        {{ $status === 'step_user' ? 'selected' : '' }}>Tes User</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Flash Messages --}}
                    @foreach (['success', 'error', 'warning', 'info'] as $type)
                        @if ($msg = Session::get($type))
                            <div class="alert alert-{{ $type }} alert-dismissible fade show shadow-sm" role="alert">
                                <i
                                    class="fas fa-{{ $type === 'success' ? 'check-circle' : ($type === 'error' ? 'times-circle' : ($type === 'warning' ? 'exclamation-triangle' : 'info-circle')) }} mr-2"></i>
                                <strong>{{ $msg }}</strong>
                                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            </div>
                        @endif
                    @endforeach

                    {{-- Main Card --}}
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between"
                            style="background: linear-gradient(135deg,#4e73df 0%,#3a5cc7 100%);">
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="fas fa-table mr-2"></i>Data Pelamar
                            </h6>
                            <span class="badge badge-light text-primary font-weight-bold px-3 py-1"
                                id="tbl-count">–</span>
                        </div>
                        <div class="card-body px-3 py-3">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th width="40">#</th>
                                            <th style="min-width:200px">Nama Pelamar</th>
                                            <th style="min-width:160px">NIK / TTL</th>
                                            <th style="min-width:150px">Pendidikan</th>
                                            <th style="min-width:100px">Fisik</th>
                                            <th style="min-width:120px">Kontak</th>
                                            <th style="min-width:90px">Agama / Status</th>
                                            <th style="min-width:160px">Departemen / Posisi</th>
                                            <th style="min-width:120px">Tanggal Apply</th>
                                            <th style="min-width:160px">Status Apply</th>
                                            <th style="min-width:170px">Hasil Test</th>
                                            <th width="70" class="text-center">Dok.</th>
                                            <th width="130" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($recruitments as $recruitment)
                                                                            @php
                                                                                $initial = strtoupper(mb_substr($recruitment->NAMA ?? 'X', 0, 1));
                                                                                $isMale = strtoupper($recruitment->JENIS_KELAMIN ?? '') === 'L';
                                                                                // Catatan: kolom status ada di pelamar_details (pd.status_apply,
                                                                                // lowercase), bukan di PELAMAR — sebelumnya dicek pakai
                                                                                // STATUS_APPLY (uppercase) yang tidak pernah ada sehingga
                                                                                // $sa selalu null dan badge selalu jatuh ke default.
                                                                                $sa = $recruitment->status_apply ?? null;

                                                                                $sClass = match ($sa) {
                                                                                    'APPLIED' => 's-applied',
                                                                                    'INVITATION TEST' => 's-test',
                                                                                    'CALLED TO INTERVIEW' => 's-interview',
                                                                                    'READY FOR SALARY' => 's-salary',
                                                                                    'ONBOARDING' => 's-onboard',
                                                                                    'REJECTED' => 's-reject',
                                                                                    default => 's-default',
                                                                                };
                                                                                $sIcon = match ($sa) {
                                                                                    'APPLIED' => 'fa-inbox',
                                                                                    'INVITATION TEST' => 'fa-vial',
                                                                                    'CALLED TO INTERVIEW' => 'fa-comments',
                                                                                    'READY FOR SALARY' => 'fa-money-check-alt',
                                                                                    'ONBOARDING' => 'fa-check-circle',
                                                                                    'REJECTED' => 'fa-times-circle',
                                                                                    default => 'fa-circle',
                                                                                };

                                                                                $docs = [
                                                                                    'Surat Lamaran' => $recruitment->file_surat_lamaran,
                                                                                    'CV' => $recruitment->file_cv,
                                                                                    'KTP' => $recruitment->file_ktp,
                                                                                    'KK' => $recruitment->file_kk,
                                                                                    'Pas Foto' => $recruitment->file_pas_foto,
                                                                                    'Ijazah' => $recruitment->file_ijasah,
                                                                                    'Akta Lahir' => $recruitment->file_akta_kelahiran,
                                                                                    'SKCK' => $recruitment->file_skck,
                                                                                    'Surat Sehat' => $recruitment->file_surat_sehat,
                                                                                ];
                                                                                $docCount = collect($docs)->filter()->count()
                                                                                    + (isset($healthTestMap[$recruitment->NIK]) ? 1 : 0);

                                                                                // Pengajuan gaji terbaru untuk pelamar ini (kalau ada)
                                                                                $sal = $salaryMap[$recruitment->ID] ?? null;
                                                                            @endphp
                                                                            <tr>
                                                                                {{-- # --}}
                                                                                <td class="text-center text-muted">{{ $loop->iteration }}</td>

                                                                                {{-- Nama --}}
                                                                                <td>
                                                                                    <div class="d-flex align-items-center" style="gap:10px;">
                                                                                        <div class="av {{ $isMale ? 'av-m' : 'av-f' }}">{{ $initial }}</div>
                                                                                        <div>
                                                                                            <div class="name-main" {!! in_array($recruitment->NIK, $exPkwtKtp ?? []) ? 'style="color: red;" title="Pernah Terdaftar di PKWT"' : '' !!}>{{ $recruitment->NAMA }}</div>
                                                                                            <div class="name-sub">
                                                                                                {{ $isMale ? '♂ Laki-laki' : '♀ Perempuan' }}
                                                                                            </div>
                                                                                            @if($recruitment->jabatan)
                                                                                                <div class="name-sub"><i class="fas fa-briefcase mr-1"
                                                                                                        style="font-size:10px;"></i>{{ $recruitment->jabatan }}
                                                                                                </div>
                                                                                            @endif
                                                                                        </div>
                                                                                    </div>
                                                                                </td>

                                                                                {{-- NIK / TTL --}}
                                                                                <td>
                                                                                    <div
                                                                                        style="font-size:12.5px; font-weight:600; font-family:monospace; color:#2d3748; letter-spacing:.5px;">
                                                                                        {{ $recruitment->NIK ?? '-' }}
                                                                                    </div>
                                                                                    <div class="name-sub"><i class="fas fa-map-marker-alt mr-1"
                                                                                            style="font-size:10px;"></i>{{ $recruitment->TMPT_LAHIR ?? '-' }}
                                                                                    </div>
                                                                                    <div class="name-sub">
                                                                                        <i class="fas fa-calendar mr-1" style="font-size:10px;"></i>
                                                                                        {{ $recruitment->TGL_LAHIR ? \Carbon\Carbon::parse($recruitment->TGL_LAHIR)->format('d M Y') : '-' }}
                                                                                    </div>
                                                                                    <div class="name-sub" style="color:#94a3b8;">
                                                                                        {{ $recruitment->UMUR ?? '-' }}
                                                                                    </div>
                                                                                </td>

                                                                                {{-- Pendidikan --}}
                                                                                <td>
                                                                                    <span class="badge badge-light border"
                                                                                        style="font-size:12px; padding:4px 8px; font-weight:700;">{{ $recruitment->PENDIDIKAN ?? '-' }}</span>
                                                                                    <div class="name-sub mt-1">{{ $recruitment->JURUSAN ?? '-' }}</div>
                                                                                    <div class="name-sub">{{ $recruitment->NAMA_SEKOLAH ?? '-' }}</div>
                                                                                    @if($recruitment->KABUPATEN_SEKOLAH)
                                                                                        <div class="name-sub" style="color:#94a3b8;">
                                                                                            {{ $recruitment->KABUPATEN_SEKOLAH }}
                                                                                        </div>
                                                                                    @endif
                                                                                </td>

                                                                                {{-- Fisik --}}
                                                                                <td class="text-center">
                                                                                    <div style="font-size:13px; font-weight:700; color:#2d3748;">
                                                                                        <i class="fas fa-ruler-vertical text-info mr-1"
                                                                                            style="font-size:11px;"></i>{{ $recruitment->TINGGI_BADAN ?? '-' }}
                                                                                        cm
                                                                                    </div>
                                                                                    <div style="font-size:13px; font-weight:700; color:#2d3748;">
                                                                                        <i class="fas fa-weight text-warning mr-1"
                                                                                            style="font-size:11px;"></i>{{ $recruitment->BERAT_BADAN ?? '-' }}
                                                                                        kg
                                                                                    </div>
                                                                                </td>

                                                                                {{-- Kontak --}}
                                                                                <td>
                                                                                    <div style="font-size:13px; font-weight:700; color:#2d3748;">
                                                                                        {{ $recruitment->HP ?? '-' }}
                                                                                    </div>
                                                                                    <div class="name-sub"><i class="fas fa-map-pin mr-1"
                                                                                            style="font-size:10px;"></i>{{ $recruitment->KABUPATEN ?? '-' }}
                                                                                    </div>
                                                                                    @if($recruitment->ALAMAT_DOMISILI)
                                                                                        <div class="name-sub" style="color:#94a3b8;">Domisili:
                                                                                            {{ $recruitment->ALAMAT_DOMISILI }}
                                                                                        </div>
                                                                                    @endif
                                                                                </td>

                                                                                {{-- Agama / Status --}}
                                                                                <td>
                                                                                    <div class="name-main" style="font-size:13px;">
                                                                                        {{ $recruitment->AGAMA ?? '-' }}
                                                                                    </div>
                                                                                    <div class="name-sub">{{ $recruitment->STATUS ?? '-' }}</div>
                                                                                    <div class="name-sub">{{ $recruitment->TANGGUNGAN ?? '0' }} tanggungan
                                                                                    </div>
                                                                                </td>

                                                                                {{-- Departemen / Posisi --}}
                                                                                <td>
                                                                                    <div class="name-main" style="font-size:13px;">
                                                                                        {{ $recruitment->department ?? '-' }}
                                                                                    </div>
                                                                                    <div class="name-sub">{{ $recruitment->jabatan ?? '-' }}</div>
                                                                                </td>

                                                                                {{-- Tanggal Apply --}}
                                                                                <td>
                                                                                    <div class="name-main" style="font-size:13px;">
                                                                                        {{ 
                                                                                                                                                                                                                                                                                                                                                            $recruitment->created_at
                                            ? \Carbon\Carbon::parse($recruitment->created_at)->format('d F Y')
                                            : '-' 
                                                                                                                                                                                                                                                                                                                                                        }}</div>
                                                                                </td>

                                                                                {{-- Status Apply --}}
                                                                                <td>
                                                                                    <span class="s-pill {{ $sClass }}">
                                                                                        <i
                                                                                            class="fas {{ $sIcon }}"></i>{{ $recruitment->status_apply ?? $sa ?? '-' }}
                                                                                        <br>
                                                                                        @if($recruitment->status_apply == 'ONBOARDING')
                                                                                            @if($recruitment->tgl_diterima != null)
                                                                                                <i class="fas fa-check-circle"
                                                                                                    style="font-size:13px;">{{ \Carbon\Carbon::parse($recruitment->tgl_diterima)->format('d F Y') }}</i>
                                                                                            @else
                                                                                                <i class="fas fa-exclamation-circle" style="font-size:13px;">Tanggal
                                                                                                    Diterima Belum Diisi</i>
                                                                                            @endif
                                                                                        @endif
                                                                                    </span>
                                                                                </td>

                                                                                {{-- Hasil Test --}}
                                                                                <td>
                                                                                    @php
                                                                                        $stepResults = [
                                                                                            'Interview' => $recruitment->result_interview ?? null,
                                                                                            'Kesehatan' => $recruitment->result_kesehatan ?? null,
                                                                                            'Teknis' => $recruitment->result_test ?? null,
                                                                                            'User' => $recruitment->result_user ?? null,
                                                                                        ];
                                                                                        $hasAnyResult = collect($stepResults)->filter(fn($v) => !is_null($v) && $v !== '')->isNotEmpty();

                                                                                        // Gaji (kalau staff & sudah pernah diajukan) ditampilkan sebagai
                                                                                        // baris tambahan di kolom yang sama, bukan badge terpisah di Aksi.
                                                                                        if ($sal) {
                                                                                            $gStyle = match ($sal->status) {
                                                                                                'finish' => 'background:#dcfce7; color:#166534; border:1px solid #bbf7d0;',
                                                                                                'rejected' => 'background:#fee2e2; color:#991b1b; border:1px solid #fecaca;',
                                                                                                default => 'background:#e0e7ff; color:#4338ca; border:1px solid #c7d2fe;',
                                                                                            };
                                                                                            $gIcon = match ($sal->status) {
                                                                                                'finish' => 'fa-check-circle',
                                                                                                'rejected' => 'fa-times-circle',
                                                                                                default => 'fa-hourglass-half',
                                                                                            };
                                                                                            $gStepLabel = ($sal->current_step === 0)
                                                                                                ? 'Management Dept'
                                                                                                : (($sal->current_step === 1) ? 'General Manager' : '');
                                                                                            $gText = match ($sal->status) {
                                                                                                'finish' => 'Gaji: Rp ' . number_format($sal->approved_salary, 0, ',', '.'),
                                                                                                'rejected' => 'Gaji Ditolak',
                                                                                                default => 'Gaji: Menunggu ' . $gStepLabel,
                                                                                            };
                                                                                        }
                                                                                    @endphp
                                                                                    @if($hasAnyResult || $sal)
                                                                                        <div style="display:flex; flex-direction:column; gap:3px;">
                                                                                            @foreach($stepResults as $label => $val)
                                                                                                @if(!is_null($val) && $val !== '')
                                                                                                    @php
                                                                                                        $rStyle = match (strtoupper($val)) {
                                                                                                            'TRUE' => 'background:#dcfce7; color:#166534; border:1px solid #bbf7d0;',
                                                                                                            'FALSE' => 'background:#fee2e2; color:#991b1b; border:1px solid #fecaca;',
                                                                                                            'SKIP' => 'background:#fef9c3; color:#854d0e; border:1px solid #fef08a;',
                                                                                                            default => 'background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;',
                                                                                                        };
                                                                                                        $rIcon = match (strtoupper($val)) {
                                                                                                            'TRUE' => 'fa-check-circle',
                                                                                                            'FALSE' => 'fa-times-circle',
                                                                                                            'SKIP' => 'fa-forward',
                                                                                                            default => 'fa-circle',
                                                                                                        };
                                                                                                        $rText = match (strtoupper($val)) {
                                                                                                            'TRUE' => 'LOLOS',
                                                                                                            'FALSE' => 'TIDAK LOLOS',
                                                                                                            'SKIP' => 'DILEWATI',
                                                                                                            default => $val,
                                                                                                        };
                                                                                                    @endphp
                                                                                                    <div
                                                                                                        style="display:flex; align-items:center; gap:5px; font-size:11px; font-weight:600; padding:2px 6px; border-radius:4px; {{ $rStyle }}">
                                                                                                        <i class="fas {{ $rIcon }}" style="font-size:10px;"></i>
                                                                                                        <span style="flex:1;">{{ $label }}</span>
                                                                                                        <span>{{ $rText }}</span>
                                                                                                    </div>
                                                                                                @endif
                                                                                            @endforeach
                                                                                            @if($sal)
                                                                                                <div
                                                                                                    style="display:flex; align-items:center; gap:5px; font-size:11px; font-weight:600; padding:2px 6px; border-radius:4px; {{ $gStyle }}">
                                                                                                    <i class="fas {{ $gIcon }}" style="font-size:10px;"></i>
                                                                                                    <span style="flex:1;">{{ $gText }}</span>
                                                                                                </div>
                                                                                            @endif
                                                                                        </div>
                                                                                    @else
                                                                                        <span class="text-muted" style="font-size:12px;">—</span>
                                                                                    @endif
                                                                                </td>

                                                                                {{-- Dokumen --}}
                                                                                <td class="text-center">
                                                                                    @if ($docCount > 0)
                                                                                        <div class="dropdown">
                                                                                            <button class="doc-fold-btn dropdown-toggle" type="button"
                                                                                                data-toggle="dropdown">
                                                                                                <i class="fas fa-folder-open text-warning"></i>
                                                                                                <span class="badge badge-primary"
                                                                                                    style="font-size:9px;">{{ $docCount }}</span>
                                                                                            </button>
                                                                                            <div class="dropdown-menu dropdown-menu-right shadow-sm"
                                                                                                style="min-width:160px;">
                                                                                                @foreach ($docs as $label => $path)
                                                                                                    @if ($path)
                                                                                                        @php
                                                                                                            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                                                                                            $isImgExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp']);
                                                                                                            $fileUrl = asset('storage/' . $path);
                                                                                                        @endphp
                                                                                                        <a class="dropdown-item d-flex align-items-center {{ $isImgExt ? 'img-viewer-link' : '' }}"
                                                                                                            style="font-size:12.5px; gap:8px;" href="{{ $fileUrl }}"
                                                                                                            data-url="{{ $fileUrl }}" data-label="{{ $label }}"
                                                                                                            @if(!$isImgExt) target="_blank" @endif>
                                                                                                            <i
                                                                                                                class="fas {{ $isImgExt ? 'fa-image text-purple' : 'fa-file-pdf text-danger' }}"></i>
                                                                                                            {{ $label }}
                                                                                                        </a>
                                                                                                    @endif
                                                                                                @endforeach
                                                                                            </div>
                                                                                        </div>
                                                                                    @else
                                                                                        <span class="doc-zero"><i class="fas fa-folder"></i></span>
                                                                                    @endif
                                                                                </td>

                                                                                {{-- Aksi --}}
                                                                                <td>
                                                                                    <div style="display:flex; flex-direction:column; gap:5px;">
                                                                                        <button type="button" class="act-btn act-wa btn-whatsapp"
                                                                                            data-nama="{{ $recruitment->NAMA }}"
                                                                                            data-phone="{{ $recruitment->HP }}"
                                                                                            data-npk="{{ $recruitment->NPK }}"
                                                                                            data-id="{{ $recruitment->ID }}"
                                                                                            data-jabatan="{{ $recruitment->jabatan ?? '-' }}"
                                                                                            data-dept="{{ $recruitment->dept ?? '-' }}" data-toggle="modal"
                                                                                            data-target="#whatsappModal">
                                                                                            <i class="fab fa-whatsapp"></i> WA
                                                                                        </button>
                                                                                        @canRoute('recruitment.edit')
                                                                                        <a href="{{ route('recruitment.edit', $recruitment->ID) }}"
                                                                                            class="act-btn act-edit">
                                                                                            <i class="fas fa-edit"></i> Edit
                                                                                        </a>
                                                                                        @endcanRoute
                                                                                        <button type="button" class="act-btn act-det btn-detail"
                                                                                            data-recruitment="{{ json_encode($recruitment) }}"
                                                                                            data-pkwt="{{ isset($pkwtRecords[$recruitment->NIK]) ? json_encode($pkwtRecords[$recruitment->NIK]) : 'null' }}"
                                                                                            data-salary="{{ $sal ? json_encode($sal) : 'null' }}"
                                                                                            data-toggle="modal" data-target="#detailModal">
                                                                                            <i class="fas fa-eye"></i> Detail
                                                                                        </button>

                                                                                        {{--
                                                                                        Pengajuan Gaji Karyawan Baru — hanya untuk posisi staff
                                                                                        (PELAMAR.is_staff, diturunkan dari recruitment_position saat
                                                                                        input jabatan/dept pelamar).

                                                                                        - Belum pernah ajukan / pengajuan sebelumnya ditolak -> tombol
                                                                                        buka modal buat pengajuan BARU.
                                                                                        - Sudah ada pengajuan tapi belum ada satupun approver yang
                                                                                        memproses (progress step 0 masih 'pending') -> tombol tetap
                                                                                        tampil tapi jadi mode UPDATE, HR masih bisa ubah nominal /
                                                                                        approver selama belum diproses.
                                                                                        - Sudah mulai diproses (minimal 1 approver sudah approve) atau
                                                                                        sudah selesai (finish) -> tombol disembunyikan; status &
                                                                                        nominalnya sudah terlihat di kolom "Hasil Test".
                                                                                        --}}
                                                                                        @php
                                                                                            $isStaff = (int) ($recruitment->is_staff ?? $recruitment->IS_STAFF ?? 0) === 1;
                                                                                        @endphp
                                                                                        @if($isStaff)
                                                                                            @php
                                                                                                $salEditable = $sal
                                                                                                    && $sal->status === 'pending'
                                                                                                    && (($sal->progress[0]['status'] ?? null) === 'pending');
                                                                                                $showSalaryButton = !$sal || $sal->status === 'rejected' || $salEditable;
                                                                                            @endphp
                                                                                            @if($showSalaryButton)
                                                                                                <button type="button" class="act-btn act-salary btn-salary"
                                                                                                    data-id="{{ $recruitment->ID }}"
                                                                                                    data-nama="{{ $recruitment->NAMA }}"
                                                                                                    data-jabatan="{{ $recruitment->jabatan ?? '-' }}"
                                                                                                    data-dept="{{ $recruitment->department ?? $recruitment->dept ?? '-' }}"
                                                                                                    data-salary='@json($salEditable ? $sal : null)'
                                                                                                    data-toggle="modal" data-target="#salaryModal">
                                                                                                    <i class="fas fa-money-bill-wave"></i>
                                                                                                    {{ $salEditable ? 'Update Pengajuan Gaji' : 'Ajukan Gaji' }}
                                                                                                </button>
                                                                                            @elseif($sal && $sal->status === 'pending')
                                                                                                <span class="salary-badge salary-badge-pending">
                                                                                                    <i class="fas fa-lock"></i> Sedang diproses approval
                                                                                                </span>
                                                                                            @endif
                                                                                        @endif
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════
            DETAIL MODAL — compact, wide, colorful tabs
            ══════════════════════════════════════════════════════ --}}
            <div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-2xl" role="document" style="max-width:2000px;">
                    <div class="modal-content">

                        {{-- HERO --}}
                        <div class="det-hero">
                            <div class="d-flex align-items-center" style="gap:14px; padding-bottom:12px;">
                                <div class="det-av" id="det_av">D</div>
                                <div class="flex-grow-1">
                                    <div class="det-name" id="det_nama">–</div>
                                    <div class="det-sub" id="det_sub">–</div>
                                </div>
                                <button type="button" class="close text-white ml-2" data-dismiss="modal"
                                    style="opacity:.8; font-size:22px;"><span>&times;</span></button>
                            </div>

                            {{-- Timeline --}}
                            <div class="tl-bar">
                                @foreach([
                                        ['tl_apply', 'fa-inbox', 'Applied'],
                                        ['tl_interview', 'fa-comments', 'Interview'],
                                        ['tl_health', 'fa-heartbeat', 'Kesehatan'],
                                        ['tl_test', 'fa-vial', 'Tes Teknis'],
                                        ['tl_user', 'fa-user-check', 'Tes User'],
                                        ['tl_onboard', 'fa-check-circle', 'Onboarding'],
                                    ] as $i => [$tid, $tic, $tlb])
                                    @if($i > 0)
                                        <div class="tl-line" id="{{ $tid }}_line"></div>
                                    @endif
                                    <div class="tl-step">
                                        <div class="tl-dot" id="{{ $tid }}_dot"><i class="fas {{ $tic }}"
                                                style="font-size:10px;"></i></div>
                                        <div class="tl-lbl">{{ $tlb }}</div>
                                        <div class="tl-date" id="{{ $tid }}_date">–</div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Tab Nav --}}
                            <div class="det-tabs">
                                <div class="det-tab active" data-tab="pribadi">
                                    <i class="fas fa-id-card"></i> Pribadi
                                </div>
                                <div class="det-tab" data-tab="kontak">
                                    <i class="fas fa-map-marker-alt"></i> Kontak
                                </div>
                                <div class="det-tab" data-tab="keluarga">
                                    <i class="fas fa-users"></i> Keluarga
                                </div>
                                <div class="det-tab" data-tab="pendidikan">
                                    <i class="fas fa-graduation-cap"></i> Pendidikan
                                </div>
                                <div class="det-tab" data-tab="karir">
                                    <i class="fas fa-briefcase"></i> Karir
                                </div>
                                <div class="det-tab" data-tab="dokumen">
                                    <i class="fas fa-folder-open"></i> Dokumen
                                </div>
                                <div class="det-tab" data-tab="pkwt" id="tab-pkwt-header" style="display:none;">
                                    <i class="fas fa-history"></i> Riwayat PKWT
                                </div>
                                <div class="det-tab" data-tab="salary" id="tab-salary-header" style="display:none;">
                                    <i class="fas fa-money-bill-wave"></i> Pengajuan Gaji
                                </div>
                                <div class="det-tab" data-tab="penilaian">
                                    <i class="fas fa-tasks"></i> Penilaian
                                </div>
                            </div>
                        </div>{{-- /det-hero --}}

                        {{-- TAB PANES --}}
                        <div class="det-body">

                            {{-- ── TAB: PRIBADI ── --}}
                            <div class="det-pane active" id="pane-pribadi">
                                <div class="row" style="row-gap:0;">
                                    <div class="col-md-6">
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-blue"><i class="fas fa-id-card"></i> Identitas
                                                Diri</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-2">
                                                    <div>
                                                        <div class="fl">NIK</div>
                                                        <div class="fv" id="dp_nik">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">No. KK</div>
                                                        <div class="fv" id="dp_kk">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Jenis Kelamin</div>
                                                        <div class="fv" id="dp_jk">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Agama</div>
                                                        <div class="fv" id="dp_agama">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Tempat Lahir</div>
                                                        <div class="fv" id="dp_tmpt">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Tanggal Lahir</div>
                                                        <div class="fv" id="dp_tgl">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Umur</div>
                                                        <div class="fv" id="dp_umur">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Warga Negara</div>
                                                        <div class="fv" id="dp_wn">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Status Nikah</div>
                                                        <div class="fv" id="dp_status">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Tanggungan</div>
                                                        <div class="fv" id="dp_tang">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Ikut KB</div>
                                                        <div class="fv" id="dp_kb">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">No. SIM</div>
                                                        <div class="fv" id="dp_sim">–</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-cyan"><i class="fas fa-running"></i> Fisik & Info
                                                Tambahan</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-2">
                                                    <div>
                                                        <div class="fl">Tinggi Badan</div>
                                                        <div class="fv" id="dp_tb">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Berat Badan</div>
                                                        <div class="fv" id="dp_bb">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Transportasi</div>
                                                        <div class="fv" id="dp_transport">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Bakat / Hobby</div>
                                                        <div class="fv" id="dp_bakat">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">BPJS TK</div>
                                                        <div class="fv" id="dp_bpjstk">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">BPJS Kesehatan</div>
                                                        <div class="fv" id="dp_bpjskes">–</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-purple"><i class="fas fa-briefcase"></i> Posisi
                                                yang Dilamar</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-2">
                                                    <div>
                                                        <div class="fl">Jabatan</div>
                                                        <div class="fv" id="dp_jabatan">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Department</div>
                                                        <div class="fv" id="dp_dept">–</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-gray"><i class="fas fa-lightbulb"></i> Motivasi &
                                                Kegiatan</div>
                                            <div class="sec-card-body" style="font-size:14.5px;">
                                                <div class="fl">Kegiatan Ekstra</div>
                                                <div class="fv mb-2" id="dp_ekstra">–</div>
                                                <div class="fl">Motivasi</div>
                                                <div class="fv" id="dp_motivasi"
                                                    style="white-space:pre-line; line-height:1.6; font-weight:400; color:#4a5568;">
                                                    –</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- ── TAB: KONTAK ── --}}
                            <div class="det-pane" id="pane-kontak">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-green"><i class="fas fa-phone"></i> Kontak Utama
                                            </div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-1" style="grid-template-columns:1fr;">
                                                    <div>
                                                        <div class="fl">Nomor HP</div>
                                                        <div class="fv" id="dk_hp">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Alamat KTP</div>
                                                        <div class="fv" id="dk_alamat_ktp">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Kabupaten (KTP)</div>
                                                        <div class="fv" id="dk_kab">–</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-yellow"><i class="fas fa-home"></i> Domisili
                                                Sekarang</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-1" style="grid-template-columns:1fr;">
                                                    <div>
                                                        <div class="fl">Alamat Sekarang</div>
                                                        <div class="fv" id="dk_alamat_skrg">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Kab/Kota Sekarang</div>
                                                        <div class="fv" id="dk_kab_skrg">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Status Domisili</div>
                                                        <div class="fv" id="dk_domisili">–</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-red"><i class="fas fa-exclamation-triangle"></i>
                                                Kontak Darurat</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-1" style="grid-template-columns:1fr;">
                                                    <div>
                                                        <div class="fl">Nama</div>
                                                        <div class="fv" id="dk_ktk_nama">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Hubungan</div>
                                                        <div class="fv" id="dk_ktk_hub">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">No. Telepon</div>
                                                        <div class="fv" id="dk_ktk_telp">–</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- ── TAB: KELUARGA ── --}}
                            <div class="det-pane" id="pane-keluarga">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-blue"><i class="fas fa-male"></i> Data Ayah</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-2">
                                                    <div>
                                                        <div class="fl">Nama</div>
                                                        <div class="fv" id="df_ayah_nama">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Tgl Lahir</div>
                                                        <div class="fv" id="df_ayah_tgl">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Pendidikan</div>
                                                        <div class="fv" id="df_ayah_pend">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Pekerjaan</div>
                                                        <div class="fv" id="df_ayah_kerja">–</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-purple"><i class="fas fa-female"></i> Data Ibu
                                            </div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-2">
                                                    <div>
                                                        <div class="fl">Nama</div>
                                                        <div class="fv" id="df_ibu_nama">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Tgl Lahir</div>
                                                        <div class="fv" id="df_ibu_tgl">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Pendidikan</div>
                                                        <div class="fv" id="df_ibu_pend">–</div>
                                                    </div>
                                                    <div>
                                                        <div class="fl">Pekerjaan</div>
                                                        <div class="fv" id="df_ibu_kerja">–</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-green"><i class="fas fa-child"></i> Saudara Kandung</div>
                                    <div class="sec-card-body" id="tbl_saudara_wrap"></div>
                                </div>
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-yellow"><i class="fas fa-baby"></i> Data Anak</div>
                                    <div class="sec-card-body" id="tbl_anak_wrap"></div>
                                </div>
                            </div>

                            {{-- ── TAB: PENDIDIKAN ── --}}
                            <div class="det-pane" id="pane-pendidikan">
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-yellow"><i class="fas fa-graduation-cap"></i> Pendidikan
                                        Terakhir</div>
                                    <div class="sec-card-body">
                                        <div class="fg fg-4">
                                            <div>
                                                <div class="fl">Jenjang</div>
                                                <div class="fv" id="dpd_pend">–</div>
                                            </div>
                                            <div>
                                                <div class="fl">Jurusan</div>
                                                <div class="fv" id="dpd_jurusan">–</div>
                                            </div>
                                            <div>
                                                <div class="fl">Nama Sekolah</div>
                                                <div class="fv" id="dpd_sekolah">–</div>
                                            </div>
                                            <div>
                                                <div class="fl">Kabupaten</div>
                                                <div class="fv" id="dpd_kabsekolah">–</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-blue"><i class="fas fa-history"></i> Riwayat Pendidikan
                                    </div>
                                    <div class="sec-card-body" id="tbl_pendidikan_wrap"></div>
                                </div>
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-purple"><i class="fas fa-star"></i> Kegiatan Ekstra</div>
                                    <div class="sec-card-body">
                                        <div class="fv" id="dpd_ekstra"
                                            style="font-size:15px; font-weight:600; color:#4a5568;">–</div>
                                    </div>
                                </div>
                            </div>

                            {{-- ── TAB: KARIR ── --}}
                            <div class="det-pane" id="pane-karir">
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-cyan"><i class="fas fa-building"></i> Pengalaman Kerja
                                    </div>
                                    <div class="sec-card-body" id="tbl_pengalaman_wrap"></div>
                                </div>
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-gray"><i class="fas fa-comment-alt"></i> Motivasi Melamar
                                    </div>
                                    <div class="sec-card-body">
                                        <div class="fv" id="dpk_motivasi"
                                            style="white-space:pre-line; line-height:1.7; font-weight:400; color:#4a5568;">
                                            –</div>
                                    </div>
                                </div>
                            </div>

                            {{-- ── TAB: DOKUMEN ── --}}
                            <div class="det-pane" id="pane-dokumen">
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-red"><i class="fas fa-folder-open"></i> Dokumen Pelamar
                                    </div>
                                    <div class="sec-card-body">
                                        <p class="text-muted mb-2" style="font-size:12px;">Klik dokumen gambar untuk
                                            membuka dengan image viewer (bisa rotate). Dokumen non-gambar akan dibuka di
                                            tab baru.</p>
                                        <div id="doc_grid_modal" style="display:flex; flex-wrap:wrap;"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- ── TAB: PKWT ── --}}
                            <div class="det-pane" id="pane-pkwt">
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-orange"><i class="fas fa-history"></i> Riwayat Keluar
                                        PKWT</div>
                                    <div class="sec-card-body">
                                        <div id="pkwt_history_table"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- ── TAB: PENGAJUAN GAJI (Staff Only) ── --}}
                            <div class="det-pane" id="pane-salary">
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-purple"><i class="fas fa-money-bill-wave"></i> Pengajuan
                                        Gaji Karyawan Baru</div>
                                    <div class="sec-card-body">
                                        <div class="fg fg-3 mb-2">
                                            <div>
                                                <div class="fl">Status</div>
                                                <div class="fv" id="sg_status_badge">–</div>
                                            </div>
                                            <div>
                                                <div class="fl">Expected Salary</div>
                                                <div class="fv" id="sg_expected">–</div>
                                            </div>
                                            <div>
                                                <div class="fl">Approved Salary</div>
                                                <div class="fv" id="sg_approved">–</div>
                                            </div>
                                        </div>
                                        <div class="fl mb-1">Progress Approval</div>
                                        <div id="sg_steps_wrap" style="font-size:14px;">–</div>
                                    </div>
                                </div>
                            </div>

                            {{-- ── TAB: PENILAIAN ── --}}
                            <div class="det-pane" id="pane-penilaian">
                                <form action="{{ route('recruitment.updatePenilaian') }}" method="POST"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="id" id="penilaian_id">

                                    {{-- Step indicator --}}
                                    <div class="d-flex align-items-center mb-3 px-1" style="gap:6px;">
                                        <div class="step-indicator" id="step_ind_1"
                                            style="display:flex;align-items:center;gap:4px;font-size:12px;font-weight:700;">
                                            <div
                                                style="width:22px;height:22px;border-radius:50%;background:#fef3c7;color:#92400e;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;">
                                                1</div>
                                            <span style="color:#92400e;">Interview</span>
                                        </div>
                                        <div style="flex:1;height:2px;background:#e3e6f0;"></div>
                                        <div class="step-indicator" id="step_ind_2"
                                            style="display:flex;align-items:center;gap:4px;font-size:12px;font-weight:700;">
                                            <div
                                                style="width:22px;height:22px;border-radius:50%;background:#f1f3f5;color:#adb5bd;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;">
                                                2</div>
                                            <span style="color:#adb5bd;">Kesehatan</span>
                                        </div>
                                        <div style="flex:1;height:2px;background:#e3e6f0;"></div>
                                        <div class="step-indicator" id="step_ind_3"
                                            style="display:flex;align-items:center;gap:4px;font-size:12px;font-weight:700;">
                                            <div
                                                style="width:22px;height:22px;border-radius:50%;background:#f1f3f5;color:#adb5bd;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;">
                                                3</div>
                                            <span style="color:#adb5bd;">Tes Teknis</span>
                                        </div>
                                        <div style="flex:1;height:2px;background:#e3e6f0;"></div>
                                        <div class="step-indicator" id="step_ind_4"
                                            style="display:flex;align-items:center;gap:4px;font-size:12px;font-weight:700;">
                                            <div
                                                style="width:22px;height:22px;border-radius:50%;background:#f1f3f5;color:#adb5bd;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;">
                                                4</div>
                                            <span style="color:#adb5bd;">Tes User</span>
                                        </div>
                                    </div>

                                    <div class="row" style="row-gap:0;">
                                        {{-- Step 1: Interview --}}
                                        <div class="col-md-6 mb-3">
                                            <div class="penilaian-step" id="step_interview">
                                                <div class="sec-card h-100">
                                                    <div class="sec-card-hd hd-yellow">
                                                        <span class="step-number">1</span>
                                                        <i class="fas fa-comments"></i> Tes Interview
                                                    </div>
                                                    <div class="sec-card-body">
                                                        <div class="form-group mb-2">
                                                            <label class="fl">Status Kelulusan</label>
                                                            <select
                                                                class="form-control form-control-sm penilaian-select"
                                                                name="result_interview" id="penilaian_result_interview"
                                                                data-next="step_kesehatan">
                                                                <option value="">Belum Dinilai</option>
                                                                <option value="TRUE">✅ Lolos</option>
                                                                <option value="FALSE">❌ Tidak Lolos</option>
                                                                <option value="SKIP">⏭️ Lewati</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group mb-0">
                                                            <label class="fl">Catatan</label>
                                                            <textarea class="form-control form-control-sm"
                                                                name="comment_interview"
                                                                id="penilaian_comment_interview" rows="2"
                                                                placeholder="Opsional..."></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Step 2: Kesehatan --}}
                                        <div class="col-md-6 mb-3">
                                            <div class="penilaian-step" id="step_kesehatan">
                                                <div class="sec-card h-100">
                                                    <div class="sec-card-hd hd-green">
                                                        <span class="step-number">2</span>
                                                        <i class="fas fa-heartbeat"></i> Tes Kesehatan
                                                    </div>
                                                    <div class="sec-card-body">
                                                        <div class="form-group mb-2">
                                                            <label class="fl">Status Kelulusan</label>
                                                            <select
                                                                class="form-control form-control-sm penilaian-select"
                                                                name="result_kesehatan" id="penilaian_result_kesehatan"
                                                                data-next="step_teknis">
                                                                <option value="">Belum Dinilai</option>
                                                                <option value="TRUE">✅ Lolos</option>
                                                                <option value="FALSE">❌ Tidak Lolos</option>
                                                                <option value="SKIP">⏭️ Lewati</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group mb-0">
                                                            <label class="fl">Catatan</label>
                                                            <textarea class="form-control form-control-sm"
                                                                name="comment_kesehatan"
                                                                id="penilaian_comment_kesehatan" rows="2"
                                                                placeholder="Opsional..."></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Step 3: Tes Teknis --}}
                                        <div class="col-md-6 mb-3">
                                            <div class="penilaian-step" id="step_teknis">
                                                <div class="sec-card h-100">
                                                    <div class="sec-card-hd hd-blue">
                                                        <span class="step-number">3</span>
                                                        <i class="fas fa-vial"></i> Tes Teknis
                                                    </div>
                                                    <div class="sec-card-body">
                                                        <div class="form-group mb-2">
                                                            <label class="fl">Status Kelulusan</label>
                                                            <select
                                                                class="form-control form-control-sm penilaian-select"
                                                                name="result_test" id="penilaian_result_test"
                                                                data-next="step_user">
                                                                <option value="">Belum Dinilai</option>
                                                                <option value="TRUE">✅ Lolos</option>
                                                                <option value="FALSE">❌ Tidak Lolos</option>
                                                                <option value="SKIP">⏭️ Lewati</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group mb-2">
                                                            <label class="fl">Catatan</label>
                                                            <textarea class="form-control form-control-sm"
                                                                name="comment_test" id="penilaian_comment_test" rows="2"
                                                                placeholder="Opsional..."></textarea>
                                                        </div>
                                                        <div class="form-group mb-0">
                                                            <label class="fl">Upload File Hasil</label>
                                                            <input type="file" name="file_test" id="penilaian_file_test"
                                                                class="form-control-file" style="font-size: 12px;">
                                                            <div id="penilaian_file_link" class="mt-1"
                                                                style="display:none; font-size:12px;">
                                                                <a href="#" target="_blank"><i
                                                                        class="fas fa-file-download mr-1"></i>Lihat File
                                                                    Tersimpan</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Step 4: Tes User --}}
                                        <div class="col-md-6 mb-3">
                                            <div class="penilaian-step" id="step_user">
                                                <div class="sec-card h-100">
                                                    <div class="sec-card-hd hd-purple">
                                                        <span class="step-number">4</span>
                                                        <i class="fas fa-user-check"></i> Tes User
                                                    </div>
                                                    <div class="sec-card-body">
                                                        <div class="form-group mb-2">
                                                            <label class="fl">Status Kelulusan</label>
                                                            <select
                                                                class="form-control form-control-sm penilaian-select"
                                                                name="result_user" id="penilaian_result_user"
                                                                data-next="">
                                                                <option value="">Belum Dinilai</option>
                                                                <option value="TRUE">✅ Lolos</option>
                                                                <option value="FALSE">❌ Tidak Lolos</option>
                                                                <option value="SKIP">⏭️ Lewati</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group mb-0">
                                                            <label class="fl">Catatan</label>
                                                            <textarea class="form-control form-control-sm"
                                                                name="comment_user" id="penilaian_comment_user" rows="2"
                                                                placeholder="Opsional..."></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right mt-1">
                                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-4">
                                            <i class="fas fa-save mr-1"></i> Simpan Penilaian
                                        </button>
                                    </div>
                                </form>
                            </div>

                        </div>{{-- /det-body --}}

                        <div class="modal-footer bg-white border-top py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i>Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>{{-- /#detailModal --}}


            {{-- ══════════════════════════════════════════════════════
            WHATSAPP MODAL
            ══════════════════════════════════════════════════════ --}}
            <div class="modal fade" id="whatsappModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <form action="{{ route('recruitment.sendWhatsApp') }}" method="POST">
                            @csrf
                            <div class="modal-header border-0"
                                style="background:linear-gradient(135deg,#128c7e,#25d366); padding:16px 24px;">
                                <h5 class="modal-title text-white font-weight-bold mb-0">
                                    <i class="fab fa-whatsapp mr-2" style="font-size:20px;"></i>Kirim Pesan WhatsApp
                                </h5>
                                <button type="button" class="close text-white" data-dismiss="modal"
                                    style="opacity:.9;"><span>&times;</span></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label
                                                class="text-xs font-weight-bold text-uppercase text-muted">Penerima</label>
                                            <input type="text" name="nama" id="wa_nama"
                                                class="form-control form-control-sm" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="text-xs font-weight-bold text-uppercase text-muted">Nomor
                                                HP</label>
                                            <input type="text" name="nomor_hp" id="wa_phone"
                                                class="form-control form-control-sm" readonly>
                                            <input type="hidden" name="npk" id="wa_npk">
                                            <input type="hidden" name="id" id="wa_id">
                                            <input type="hidden" id="wa_jabatan">
                                            <input type="hidden" id="wa_dept">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="text-xs font-weight-bold text-uppercase text-muted">Tipe Pesan</label>
                                    <select name="type" id="wa_type" class="form-control form-control-sm" required>
                                        <option value="">-- Pilih Tipe --</option>
                                        <option value="invitation">🧪 Undangan Test</option>
                                        <option value="interview">💬 Pemanggilan Interview HR</option>
                                        <option value="onboarding">✅ Diterima / Onboarding</option>
                                        <option value="rejection">❌ Penolakan</option>
                                    </select>
                                </div>
                                <div id="wa_datetime_container" style="display:none;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label
                                                    class="text-xs font-weight-bold text-uppercase text-muted">Tanggal</label>
                                                <input type="date" name="tgl_schedule" id="wa_date"
                                                    class="form-control form-control-sm">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label
                                                    class="text-xs font-weight-bold text-uppercase text-muted">Waktu</label>
                                                <input type="time" name="time_schedule" id="wa_time"
                                                    class="form-control form-control-sm">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="text-xs font-weight-bold text-uppercase text-muted">Preview
                                        Pesan</label>
                                    <textarea name="message" id="wa_message" class="form-control" rows="9" required
                                        style="font-size:14.5px; line-height:1.65;"></textarea>
                                    <small class="form-text text-muted">Pesan dapat diedit secara manual sebelum
                                        dikirim.</small>
                                </div>
                                <div class="form-group mb-0 custom-control custom-checkbox">
                                    <input type="checkbox" name="send_wa" id="send_wa_check"
                                        class="custom-control-input" value="1" checked>
                                    <label class="custom-control-label font-weight-bold text-primary"
                                        for="send_wa_check" style="cursor:pointer">
                                        Kirim pesan WhatsApp ke pelamar ini
                                    </label>
                                </div>
                            </div>
                            <div class="modal-footer bg-light border-top py-2">
                                <button type="button" class="btn btn-secondary btn-sm"
                                    data-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-success btn-sm font-weight-bold px-4">
                                    <i class="fab fa-send mr-1"></i>Submit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════
            SALARY MODAL — pengajuan gaji karyawan baru
            ══════════════════════════════════════════════════════ --}}
            <div class="modal fade" id="salaryModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
                        <form action="{{ route('salary-approve.store') }}" method="POST" id="salaryForm">
                            @csrf
                            <div class="modal-header border-0"
                                style="background:linear-gradient(135deg,#4338ca,#6366f1); padding:20px 28px;">
                                <h4 class="modal-title text-white font-weight-bold mb-0" id="salaryModalTitle">
                                    <i class="fas fa-money-bill-wave mr-2"></i>Pengajuan Gaji Karyawan Baru
                                </h4>
                                <button type="button" class="close text-white" data-dismiss="modal"
                                    style="opacity:.9; font-size: 1.5rem;"><span>&times;</span></button>
                            </div>
                            <div class="modal-body p-4" style="font-size: 15px;">
                                <input type="hidden" name="id_pelamar" id="sal_id_pelamar">
                                <input type="hidden" name="jabatan" id="sal_jabatan">
                                <input type="hidden" name="department" id="sal_department">
                                <div id="sal_method_slot"></div>
                                <div class="alert alert-info py-2 mb-3" id="sal_edit_notice"
                                    style="display:none; font-size:12.5px;">
                                    <i class="fas fa-info-circle mr-1"></i>Memperbarui pengajuan yang sudah ada. Ini
                                    hanya bisa dilakukan selama belum ada approver yang memproses.
                                </div>

                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark mb-1" style="font-size: 14px;">NAMA
                                        PELAMAR</label>
                                    <input type="text" id="sal_nama"
                                        class="form-control form-control-lg bg-light text-dark font-weight-bold"
                                        readonly style="font-size: 16px;">
                                </div>

                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark mb-1" style="font-size: 14px;">EXPECTED
                                        SALARY</label>
                                    <div class="input-group input-group-lg">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-primary text-white font-weight-bold"
                                                style="font-size: 16px;">Rp</span>
                                        </div>
                                        <input type="text" id="sal_expected_salary_display"
                                            class="form-control form-control-lg font-weight-bold" placeholder="0"
                                            required style="font-size: 16px;">
                                        <input type="hidden" name="expected_salary" id="sal_expected_salary">
                                    </div>
                                    <small class="form-text text-muted" style="font-size: 13px;">Format otomatis nominal
                                        rupiah (contoh: 4.500.000)</small>
                                </div>

                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark mb-1" style="font-size: 14px;">APPROVAL —
                                        MANAGEMENT DEPT</label>
                                    <select name="management_npk[]" id="sal_management_npk" class="form-control select2"
                                        required style="width: 100%;">
                                        <option value="">-- Pilih Management Dept --</option>
                                        @foreach($approvers['management'] ?? [] as $u)
                                            <option value="{{ $u->npk }}">{{ $u->name }} ({{ $u->department ?? '-' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group mb-2">
                                    <label class="font-weight-bold text-dark mb-1" style="font-size: 14px;">APPROVAL —
                                        GENERAL MANAGER</label>
                                    <select name="gm_npk[]" id="sal_gm_npk" class="form-control select2" required
                                        style="width: 100%;">
                                        <option value="">-- Pilih General Manager --</option>
                                        @foreach($approvers['gm'] ?? [] as $u)
                                            <option value="{{ $u->npk }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer bg-light border-top py-3 px-4">
                                <button type="button" class="btn btn-secondary btn-lg px-4 font-weight-bold"
                                    data-dismiss="modal" style="font-size: 15px;">Batal</button>
                                <button type="submit" class="btn btn-primary btn-lg px-4 font-weight-bold"
                                    style="font-size: 15px;" id="salaryModalSubmitBtn">
                                    <i class="fas fa-paper-plane mr-1"></i>Ajukan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>{{-- /#salaryModal --}}

            @include('layout.footer')
        </div>
    </div>

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.js"></script>

    <script>
        $(document).ready(function () {

            /* ── DataTable ── */
            $('#dataTable').DataTable({
                pageLength: 10,
                order: [],
                language: {
                    search: 'Cari:',
                    searchPlaceholder: 'Nama, NIK, HP...',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Data _START_–_END_ dari _TOTAL_ pelamar',
                    infoEmpty: 'Tidak ada data',
                    zeroRecords: 'Tidak ada data ditemukan',
                    paginate: { previous: '‹ Prev', next: 'Next ›' }
                },
                drawCallback: function () {
                    $('#tbl-count').text(this.api().page.info().recordsTotal + ' pelamar');
                }
            });

            /* ── Tab switching ── */
            $(document).on('click', '.det-tab', function () {
                const tab = $(this).data('tab');
                $('.det-tab').removeClass('active');
                $(this).addClass('active');
                $('.det-pane').removeClass('active');
                $('#pane-' + tab).addClass('active');
            });

            /* ══════════════════════════════════════════════
               IMAGE VIEWER (Viewer.js) — opens image attachments
               with zoom, drag, and ROTATE (left/right) + flip.
            ══════════════════════════════════════════════ */
            let __imgViewerInstance = null;
            let __imgViewerEl = null;

            function openImageViewer(url, label) {
                if (!url) return;

                if (__imgViewerInstance) {
                    try { __imgViewerInstance.destroy(); } catch (e) { }
                    __imgViewerInstance = null;
                }
                if (__imgViewerEl) {
                    $(__imgViewerEl).remove();
                    __imgViewerEl = null;
                }

                const img = document.createElement('img');
                img.src = url;
                img.alt = label || 'Dokumen';
                img.style.display = 'none';
                document.body.appendChild(img);
                __imgViewerEl = img;

                __imgViewerInstance = new Viewer(img, {
                    inline: false,
                    navbar: false,
                    title: [1, (image) => label || 'Dokumen'],
                    toolbar: {
                        zoomIn: 1,
                        zoomOut: 1,
                        oneToOne: 1,
                        reset: 1,
                        prev: 0,
                        play: 0,
                        next: 0,
                        rotateLeft: 1,
                        rotateRight: 1,
                        flipHorizontal: 1,
                        flipVertical: 1,
                    },
                    movable: true,
                    zoomable: true,
                    rotatable: true,
                    scalable: true,
                    transition: true,
                    keyboard: true,
                    hidden: function () {
                        if (__imgViewerInstance) {
                            try { __imgViewerInstance.destroy(); } catch (e) { }
                            __imgViewerInstance = null;
                        }
                        if (__imgViewerEl) {
                            $(__imgViewerEl).remove();
                            __imgViewerEl = null;
                        }
                    }
                });

                __imgViewerInstance.show();
            }

            $(document).on('click', '.img-viewer-link', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const url = $(this).data('url');
                const label = $(this).data('label') || $(this).text().trim();
                openImageViewer(url, label);
            });

            /* ── WA Templates ── */
            const WA = {
                invitation: "[PANGGILAN PERTAMA] Haloo, [NAMA] \nSemangat pagi! HRD PT Chutex International Indonesia telah menerima lamaran Anda dan kami mengundang Anda untuk mengikuti Tes Seleksi Calon Karyawan pada: \n\nKami HRD PT Chutex International Indonesia telah menerima lamaran anda. Kami Mengundang anda untuk tes seleksi pada : \n\nHari : [DATE] \nPukul : [TIME] WIB-Selesai \nTempat : PT Chutex International Indonesia \nBerikut alamat perusahaan kami: \nhttps://maps.app.goo.gl/2ecG1uupdf3F4rSt8 \n\nDiharap untuk datang tepat waktu untuk mengikuti Tes seleksi Calon Karyawan dan segera konfirmasi kehadiran dengan membalas WA ini. \n\nPada saat datang interview segera Lapor ke satpam untuk menemui Mbak Fitri HRD Bukan yang lain. \n\nAnda wajib membawa berkas saat interview (meskipun sudah menitipkan lamaran). Adapun rincian berkas lamaran yg harus ada : \n* Surat lamaran kerja \n* Daftar riwayat hidup \n* FC KTP 2 lembar \n* FC KK 1 lembar \n* FC Ijazah 1 lembar \n* FC Akta kelahiran 1 lembar \n* FC SKCK yang tanggalnya masih berlaku 1 lembar \n* Pas foto ukuran 3x4 background merah 2 lembar \n\nNB : \n1. Datang memakai kemeja putih sopan \n2. Bagi yang berhijab, jilbab instan warna putih/hitam \n3. Membawa bolpoin hitam \n4. WAJIB Membawa KTP dan SIM asli \n5. WAJIB bersepatu dan menggunakan kaos kaki \n\nSebagai perhatian, KTP ASLI wajib dibawa saat interview. Apabila security meminta tanda pengenal, harap siapkan SIM atau FC KTP (Jika tidak memiliki SIM bisa diganti dengan FC KTP)",
                interview: "[PANGGILAN KEDUA] \n\nSemangat pagi. \n\nSelamat Anda telah lolos dari tahap 1! Kami Mengundang anda untuk Seleksi Tahap 2 pada : \n\nHari : [DATE] \nPukul : [TIME] WIB-Selesai \nTempat : PT Chutex International Indonesia \nBerikut alamat perusahaan kami: \nhttps://maps.app.goo.gl/2ecG1uupdf3F4rSt8 \n\nDiharap untuk datang tepat waktu untuk mengikuti Tes seleksi Calon Karyawan dan segera konfirmasi kehadiran dengan membalas WA ini. \n\nPada saat datang interview segera Lapor ke satpam untuk menemui Mbak Lala HRD Bukan yang lain. \n\nDimohon mengkonfirmasi kehadiran dengan membalas pesan WhatsApp kami dengan format: Nama_Hadir \n\nBersiaplah, langkah Anda untuk bergabung dengan PT Chutex International Indonesia semakin dekat. Semoga berhasil! \n\nWASPADA PENIPUAN! Dalam proses rekrutmen, PT Chutex International Indonesia TIDAK PERNAH memungut biaya apapun dan TIDAK bekerja sama dengan agen perjalanan manapun. \n\nSalam, Recruitment PT Chutex International Indonesia",
                onboarding: "[PANGGILAN LOLOS] \n\nSemangat Pagi \n\nSelamat! Setelah mengikuti rangkaian proses rekrutmen, Saudara dinyatakan LOLOS. Efektif per [DATE] Anda resmi bergabung menjadi karyawan di PT Chutex International Indonesia. Diharapkan untuk hadir, sebelum pukul 08.00 ke kantor (Alamat: https://maps.app.goo.gl/2ecG1uupdf3F4rSt8) dan bertemu Mbak Lala HRD. \n\nMohon mengirimkan FOTO FORMAL (untuk foto ID Card) ke WhatsApp grup berikut https://chat.whatsapp.com/LY3nxvStAUp2xXzcRSWMSP dan konfirmasi kehadiran dengan format: Nama_Bersedia Hadir \n\nJika TIDAK mengirimkan foto formal dan konfirmasi kehadiran akan kami anggap gugur. \n\nCATATAN: Sebelum masuk menjadi karyawan, wajib membuat rekening BANK PERMATA (pembuatan rekening akan diarahkan di WAG). \n\nHarap datang dengan pakaian hitam putih (untuk yang berjilbab wajib menggunakan jilbab instan/bergo) dan melengkapi berkas yang belum ada saja. Adapun kelengkapan berkas sebagai berikut : \n* surat lamaran kerja \n* daftar riwayat hidup \n* fc ktp 1 lembar \n* fc kk 1 lembar \n* fc ijazah 1 lembar \n* fc akta kelahiran 1 lembar \n* fc skck yg tglnya masih berlaku 1 lembar \n* pas foto ukuran 3x4 background merah 2 lembar \n\nSalam, Tim Recruitment PT Chutex International Indonesia",
                rejection: "Halo [NAMA],\n\nTerima kasih telah melamar dan mengikuti proses rekrutmen di PT Chutex International Indonesia.\n\nSetelah melalui proses seleksi, saat ini kami belum dapat melanjutkan proses Anda. Tetap semangat!\n\nSalam,\nRecruitment PT Chutex International Indonesia"
            };

            $(document).on('click', '.btn-whatsapp', function () {
                $('#wa_nama').val($(this).data('nama'));
                $('#wa_phone').val($(this).data('phone'));
                $('#wa_npk').val($(this).data('npk'));
                $('#wa_id').val($(this).data('id'));
                $('#wa_jabatan').val($(this).data('jabatan'));
                $('#wa_dept').val($(this).data('dept'));
                $('#wa_type').val('').trigger('change');
                $('#wa_message').val('');
                $('#wa_datetime_container').hide();
            });

            $('#wa_type, #wa_date, #wa_time').on('change', function () {
                const t = $('#wa_type').val(), n = $('#wa_nama').val(), d = $('#wa_date').val(), h = $('#wa_time').val();
                const jab = $('#wa_jabatan').val(), dep = $('#wa_dept').val();
                $('#wa_datetime_container').toggle(['invitation', 'interview', 'onboarding'].includes(t));
                if (t && WA[t]) {
                    let msg = WA[t]
                        .replace(/\[NAMA\]/g, n)
                        .replace(/\[DATE\]/g, d || '____')
                        .replace(/\[TIME\]/g, h || '____')
                        .replace(/\[JABATAN\]/g, jab || '-')
                        .replace(/\[DEPARTMEN\]/g, dep || '-');
                    $('#wa_message').val(msg);
                }
            });

            /* ── Salary modal (create / update) ── */
            $(document).on('click', '.btn-salary', function () {
                let sal = $(this).data('salary');
                if (typeof sal === 'string') {
                    try { sal = JSON.parse(sal); } catch (e) { sal = null; }
                }
                $('#sal_id_pelamar').val($(this).data('id'));
                $('#sal_nama').val($(this).data('nama'));
                $('#sal_jabatan').val($(this).data('jabatan'));
                $('#sal_department').val($(this).data('dept'));

                // Simpan di elemen modal, dipakai nanti di shown.bs.modal
                $('#salaryModal').data('editSal', (sal && typeof sal === 'object') ? sal : null);
            });

            $('#salaryModal').on('shown.bs.modal', function () {
                $('#sal_management_npk').select2({
                    dropdownParent: $('#salaryModal'),
                    placeholder: '-- Pilih Management Dept --',
                    allowClear: true
                });
                $('#sal_gm_npk').select2({
                    dropdownParent: $('#salaryModal'),
                    placeholder: '-- Pilih General Manager --',
                    allowClear: true
                });

                const sal = $(this).data('editSal');
                const $form = $('#salaryForm');

                if (sal && sal.id) {
                    // Mode UPDATE — pengajuan sudah ada & belum ada approver yang memproses
                    const updateUrl = '{{ route('salary-approve.update', ':id') }}'.replace(':id', sal.id);
                    $form.attr('action', updateUrl);
                    $('#sal_method_slot').html('<input type="hidden" name="_method" value="PUT">');
                    $('#salaryModalTitle').html('<i class="fas fa-money-bill-wave mr-2"></i>Update Pengajuan Gaji');
                    $('#salaryModalSubmitBtn').html('<i class="fas fa-save mr-1"></i>Update Pengajuan');
                    $('#sal_edit_notice').show();

                    $('#sal_expected_salary').val(sal.expected_salary);
                    $('#sal_expected_salary_display').val(Number(sal.expected_salary).toLocaleString('id-ID'));

                    let mgmtNpk = [], gmNpk = [];
                    try {
                        let p0 = typeof sal.progress[0] === 'string' ? JSON.parse(sal.progress[0]) : sal.progress[0];
                        let rawMgmt = p0 ? p0.npk : null;
                        mgmtNpk = typeof rawMgmt === 'string' ? JSON.parse(rawMgmt) : (rawMgmt || []);
                    } catch (e) { }

                    try {
                        let p1 = typeof sal.progress[1] === 'string' ? JSON.parse(sal.progress[1]) : sal.progress[1];
                        let rawGm = p1 ? p1.npk : null;
                        gmNpk = typeof rawGm === 'string' ? JSON.parse(rawGm) : (rawGm || []);
                    } catch (e) { }

                    $('#sal_management_npk').val(mgmtNpk).trigger('change');
                    $('#sal_gm_npk').val(gmNpk).trigger('change');
                } else {
                    // Mode BARU
                    $form.attr('action', '{{ route('salary-approve.store') }}');
                    $('#sal_method_slot').html('');
                    $('#salaryModalTitle').html('<i class="fas fa-money-bill-wave mr-2"></i>Pengajuan Gaji Karyawan Baru');
                    $('#salaryModalSubmitBtn').html('<i class="fas fa-paper-plane mr-1"></i>Ajukan');
                    $('#sal_edit_notice').hide();

                    $('#sal_expected_salary_display').val('');
                    $('#sal_expected_salary').val('');
                    $('#sal_management_npk').val(null).trigger('change');
                    $('#sal_gm_npk').val(null).trigger('change');
                }
            });

            $(document).on('input', '#sal_expected_salary_display', function () {
                let val = $(this).val().replace(/[^0-9]/g, '');
                $('#sal_expected_salary').val(val);
                if (val) {
                    $(this).val(parseInt(val, 10).toLocaleString('id-ID'));
                } else {
                    $(this).val('');
                }
            });

            /* ── Helpers ── */
            const v = (x, fb) => (x !== null && x !== undefined && x !== '') ? x : (fb || '–');
            const fmtD = d => { if (!d) return '–'; try { return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }); } catch (e) { return d; } };
            const pj = r => { if (!r) return null; if (typeof r === 'object') return r; try { return JSON.parse(r); } catch (e) { return null; } };

            const toArr = r => {
                let parsed = r;
                if (parsed && typeof parsed !== 'object') {
                    try { parsed = JSON.parse(parsed); } catch (e) { parsed = null; }
                }
                if (!parsed) return [];
                return Array.isArray(parsed) ? parsed : Object.values(parsed);
            };

            function stbl(headers, rows) {
                if (!rows || !rows.length) return '<div class="stbl-empty"><i class="fas fa-minus mr-1"></i>Tidak ada data</div>';
                let h = '<div class="table-responsive"><table class="stbl"><thead><tr>' + headers.map(x => `<th>${x}</th>`).join('') + '</tr></thead><tbody>';
                rows.forEach(r => { h += '<tr>' + r.map(c => `<td>${v(c)}</td>`).join('') + '</tr>'; });
                return h + '</tbody></table></div>';
            }

            function setTl(id, lineId, status, date) {
                const dot = $('#' + id + '_dot');
                if (lineId) {
                    const line = $('#' + lineId + '_line');
                    line.removeClass('done failed');
                    if (status === 'done') line.addClass('done');
                    if (status === 'failed') line.addClass('failed');
                }
                $('#' + id + '_date').text(date ? fmtD(date) : '–');
                dot.removeClass('done active failed');
                if (status === 'done') dot.addClass('done');
                if (status === 'active') dot.addClass('active');
                if (status === 'failed') dot.addClass('failed');
            }

            function salaryStatusBadge(status) {
                if (status === 'finish') return '<span class="badge badge-success">Disetujui</span>';
                if (status === 'rejected') return '<span class="badge badge-danger">Ditolak</span>';
                if (status === 'pending') return '<span class="badge badge-warning">Menunggu Approval</span>';
                return '<span class="badge badge-secondary">Belum Diajukan</span>';
            }

            /* ── Detail modal ── */
            $(document).on('click', '.btn-detail', function () {
                const d = $(this).data('recruitment');

                // Reset tabs
                $('.det-tab').removeClass('active').first().addClass('active');
                $('.det-pane').removeClass('active').first().addClass('active');

                // Handle PKWT data
                const pkwt = $(this).data('pkwt');
                if (pkwt && pkwt !== 'null') {
                    $('#tab-pkwt-header').show();
                    let pkwtRows = [];
                    let pkwtArr = toArr(pkwt);
                    pkwtArr.forEach(p => {
                        pkwtRows.push([
                            p.NAMA,
                            p.TMK ? fmtD(p.TMK) : '-',
                            p.TKK ? fmtD(p.TKK) : '-',
                            p.KETERANGAN || '-',
                            p.leave_reasons || '-'
                        ]);
                    });
                    $('#pkwt_history_table').html(stbl(['Nama di PKWT', 'TMK', 'TKK', 'Status Keluar', 'Alasan Keluar'], pkwtRows));
                } else {
                    $('#tab-pkwt-header').hide();
                    $('#pkwt_history_table').html('');
                }

                // Handle Pengajuan Gaji data (Staff only tab)
                const isStaff = (d.is_staff == 1 || d.IS_STAFF == 1);
                if (isStaff) {
                    $('#tab-salary-header').show();
                } else {
                    $('#tab-salary-header').hide();
                }

                const sal = $(this).data('salary');
                if (sal && sal !== 'null') {
                    $('#sg_status_badge').html(salaryStatusBadge(sal.status));
                    $('#sg_expected').text(sal.expected_salary ? 'Rp ' + Number(sal.expected_salary).toLocaleString('id-ID') : '-');
                    $('#sg_approved').text(sal.approved_salary ? 'Rp ' + Number(sal.approved_salary).toLocaleString('id-ID') : '-');

                    let stepsHtml = '';
                    (sal.steps || []).forEach(step => {
                        stepsHtml += `<div class="mb-2"><strong>${step.label}${step.done ? ' <span class="text-success">(Selesai)</span>' : ''}:</strong><br>`;
                        (step.approvers || []).forEach(a => {
                            const icon = a.status === 'approve'
                                ? '<i class="fas fa-check-circle text-success"></i>'
                                : '<i class="fas fa-hourglass-half text-warning"></i>';
                            stepsHtml += `${icon} ${v(a.name)} &nbsp;&nbsp; `;
                        });
                        stepsHtml += `</div>`;
                    });
                    $('#sg_steps_wrap').html(stepsHtml || '<span class="text-muted">-</span>');
                } else {
                    $('#sg_status_badge').html(salaryStatusBadge(null));
                    $('#sg_expected').text('-');
                    $('#sg_approved').text('-');
                    $('#sg_steps_wrap').html('<span class="text-muted">Belum ada pengajuan gaji untuk pelamar ini.</span>');
                }

                // Hero
                $('#det_av').text((d.NAMA || 'X').charAt(0).toUpperCase());
                $('#det_nama').text(v(d.NAMA));
                $('#det_sub').text(
                    (d.JENIS_KELAMIN === 'L' ? '♂ Laki-laki' : '♀ Perempuan') +
                    ' · ' + v(d.AGAMA) + ' · ' + v(d.UMUR) +
                    (d.jabatan ? ' · ' + d.jabatan : '')
                );
                $('#det_status_hero').text(d.STATUS_APPLY || '–');

                const isTest = d.is_test === 'TRUE' || d.is_test === true;
                const isIntvw = d.is_interview === 'TRUE' || d.is_interview === true;
                const isHlth = d.is_kesehatan === 'TRUE' || d.is_kesehatan === true;
                const isOnb = d.STATUS_APPLY === 'ONBOARDING';
                const intvStatus = d.result_interview === 'TRUE' ? 'done' :
                    (d.result_interview === 'FALSE' ? 'failed' :
                        (d.result_interview === 'SKIP' ? 'active' : 'active'));

                const hlthStatus = d.result_kesehatan === 'TRUE' ? 'done' :
                    (d.result_kesehatan === 'FALSE' ? 'failed' :
                        (d.result_kesehatan === 'SKIP' ? 'active' :
                            ((d.result_interview === 'TRUE' || d.result_interview === 'SKIP') ? 'active' : '')));

                const testStatus = d.result_test === 'TRUE' ? 'done' :
                    (d.result_test === 'FALSE' ? 'failed' :
                        (d.result_test === 'SKIP' ? 'active' :
                            ((d.result_kesehatan === 'TRUE' || d.result_kesehatan === 'SKIP') ? 'active' : '')));

                const userStatus = d.result_user === 'TRUE' ? 'done' :
                    (d.result_user === 'FALSE' ? 'failed' :
                        (d.result_user === 'SKIP' ? 'active' :
                            ((d.result_test === 'TRUE' || d.result_test === 'SKIP') ? 'active' : '')));

                const onboardStatus = isOnb ? 'done' : ((d.result_user === 'TRUE' || d.result_user === 'SKIP') ? 'active' : '');


                setTl('tl_apply', null, 'done', null);
                setTl('tl_interview', 'tl_interview', intvStatus, d.tgl_interview);
                setTl('tl_health', 'tl_health', hlthStatus, d.tgl_kesehatan);
                setTl('tl_test', 'tl_test', testStatus, d.tgl_test);
                setTl('tl_user', 'tl_user', userStatus, null);
                setTl('tl_onboard', 'tl_onboard', onboardStatus, d.tgl_diterima);

                // Penilaian form — populate values
                $('#penilaian_id').val(d.ID);
                $('#penilaian_result_interview').val(d.result_interview || '');
                $('#penilaian_comment_interview').val(d.comment_interview || '');
                $('#penilaian_result_kesehatan').val(d.result_kesehatan || '');
                $('#penilaian_comment_kesehatan').val(d.comment_kesehatan || '');
                $('#penilaian_result_test').val(d.result_test || '');
                $('#penilaian_comment_test').val(d.comment_test || '');
                $('#penilaian_result_user').val(d.result_user || '');
                $('#penilaian_comment_user').val(d.comment_user || '');
                $('#penilaian_file_test').val('');

                if (d.file_test) {
                    $('#penilaian_file_link').show().find('a').attr('href', '/storage/' + d.file_test);
                } else {
                    $('#penilaian_file_link').hide().find('a').attr('href', '#');
                }

                // Update step indicators
                function updateStepInd(indId, result) {
                    const $ind = $('#' + indId);
                    const $dot = $ind.find('div');
                    const $lbl = $ind.find('span');
                    if (result === 'TRUE') {
                        $dot.css({ 'background': '#d1fae5', 'color': '#065f46' });
                        $lbl.css('color', '#065f46');
                    } else if (result === 'FALSE') {
                        $dot.css({ 'background': '#fee2e2', 'color': '#991b1b' });
                        $lbl.css('color', '#991b1b');
                    } else if (result === 'SKIP') {
                        $dot.css({ 'background': '#e0f2fe', 'color': '#0369a1' });
                        $lbl.css('color', '#0369a1');
                    } else {
                        $dot.css({ 'background': '#f1f3f5', 'color': '#adb5bd' });
                        $lbl.css('color', '#adb5bd');
                    }
                }
                updateStepInd('step_ind_1', d.result_interview);
                updateStepInd('step_ind_2', d.result_kesehatan);
                updateStepInd('step_ind_3', d.result_test);
                updateStepInd('step_ind_4', d.result_user);

                // Pribadi
                $('#dp_nik').text(v(d.NIK));
                $('#dp_kk').text(v(d.NO_KK));
                $('#dp_jk').text(d.JENIS_KELAMIN === 'L' ? '♂ Laki-laki' : '♀ Perempuan');
                $('#dp_agama').text(v(d.AGAMA));
                $('#dp_tmpt').text(v(d.TMPT_LAHIR));
                $('#dp_tgl').text(fmtD(d.TGL_LAHIR));
                $('#dp_umur').text(v(d.UMUR));
                $('#dp_wn').text(v(d.warga_negara));
                const statusMap = { 'BM': 'Belum Menikah', 'M': 'Menikah', 'CH': 'Cerai Hidup', 'CM': 'Cerai Mati' };
                $('#dp_status').text(statusMap[d.STATUS] || v(d.STATUS));
                $('#dp_tang').text(v(d.TANGGUNGAN, '0') + ' orang');
                $('#dp_kb').text(d.ikut_kb == 1 ? 'Ya' : (d.ikut_kb == 0 ? 'Tidak' : '–'));
                $('#dp_sim').text(v(d.nomor_sim));
                $('#dp_tb').text(v(d.TINGGI_BADAN) + ' cm');
                $('#dp_bb').text(v(d.BERAT_BADAN) + ' kg');
                $('#dp_transport').text(v(d.mode_transportasi));
                $('#dp_bakat').text(v(d.bakat_hobby));
                $('#dp_bpjstk').text(v(d.bpjs_tk));
                $('#dp_bpjskes').text(v(d.bpjs_kes));
                $('#dp_jabatan').text(v(d.jabatan));
                $('#dp_dept').text(v(d.department));
                $('#dp_ekstra').text(v(d.kegiatan_ekstra));
                $('#dp_motivasi').text(v(d.motivasi));

                // Kontak
                $('#dk_hp').text(v(d.HP)); $('#dk_alamat_ktp').text(v(d.ALAMAT_LENGKAP));
                $('#dk_kab').text(v(d.KABUPATEN)); $('#dk_alamat_skrg').text(v(d.alamat_skrg));
                $('#dk_kab_skrg').text(v(d.kabupaten_kota_skrg)); $('#dk_domisili').text(v(d.status_domisili));
                $('#dk_ktk_nama').text(v(d.nama_ktk_darurat)); $('#dk_ktk_hub').text(v(d.hubungan));
                $('#dk_ktk_telp').text(v(d.no_telp_darurat));

                // Keluarga
                const ayah = pj(d.data_ayah), ibu = pj(d.data_ibu);
                $('#df_ayah_nama').text(v(ayah && ayah.nama)); $('#df_ayah_tgl').text(fmtD(ayah && ayah.tgl_lahir));
                $('#df_ayah_pend').text(v(ayah && ayah.pendidikan)); $('#df_ayah_kerja').text(v(ayah && ayah.pekerjaan));
                $('#df_ibu_nama').text(v(ibu && ibu.nama)); $('#df_ibu_tgl').text(fmtD(ibu && ibu.tgl_lahir));
                $('#df_ibu_pend').text(v(ibu && ibu.pendidikan)); $('#df_ibu_kerja').text(v(ibu && ibu.pekerjaan));

                const sdr = pj(d.saudara_kandung);
                $('#tbl_saudara_wrap').html(stbl(['Nama', 'Tgl Lahir', 'Gender', 'Pendidikan', 'Pekerjaan'],
                    sdr && Object.values(sdr).map(s => [s.nama, fmtD(s.tgl_lahir), s.gender, s.pendidikan, s.pekerjaan])));

                const ank = pj(d.data_anak);
                $('#tbl_anak_wrap').html(stbl(['Nama', 'Tempat Lahir', 'Tgl Lahir', 'Gender', 'Pendidikan', 'Status'],
                    ank && Object.values(ank).map(a => [a.nama, a.tempat_lahir, fmtD(a.tgl_lahir), a.gender, a.pendidikan, a.status])));

                // Pendidikan
                $('#dpd_pend').text(v(d.PENDIDIKAN)); $('#dpd_jurusan').text(v(d.JURUSAN));
                $('#dpd_sekolah').text(v(d.NAMA_SEKOLAH)); $('#dpd_kabsekolah').text(v(d.KABUPATEN_SEKOLAH));
                $('#dpd_ekstra').text(v(d.kegiatan_ekstra));
                const rp = pj(d.riwayat_pendidikan);
                $('#tbl_pendidikan_wrap').html(stbl(['Tingkat', 'Institusi', 'Jurusan', 'Dari', 'Sampai', 'Lulus'],
                    rp && Object.values(rp).map(r => [r.tingkat, r.institusi, r.jurusan, r.dari, r.sampai, r.lulus == '1' ? '✓ Lulus' : '–'])));

                // Karir
                const pg = pj(d.pengalaman_kerja);
                $('#tbl_pengalaman_wrap').html(stbl(['Perusahaan', 'Dari', 'Sampai', 'Jabatan', 'Departemen', 'Alasan Keluar'],
                    pg && Object.values(pg).map(p => [p.perusahaan, p.dari, p.sampai, p.jabatan, p.departemen, p.alasan])));
                $('#dpk_motivasi').text(v(d.motivasi));

                // Dokumen
                const docMap = [
                    { label: 'Surat Lamaran', path: d.file_surat_lamaran, icon: 'fa-file-pdf', color: '#e74a3b' },
                    { label: 'CV', path: d.file_cv, icon: 'fa-file-alt', color: '#4e73df' },
                    { label: 'KTP', path: d.file_ktp, icon: 'fa-id-card', color: '#36b9cc' },
                    { label: 'Kartu Keluarga', path: d.file_kk, icon: 'fa-users', color: '#1cc88a' },
                    { label: 'Pas Foto', path: d.file_pas_foto, icon: 'fa-image', color: '#8b5cf6' },
                    { label: 'Ijazah', path: d.file_ijasah, icon: 'fa-graduation-cap', color: '#f6c23e' },
                    { label: 'Akta Lahir', path: d.file_akta_kelahiran, icon: 'fa-certificate', color: '#f97316' },
                    { label: 'SKCK', path: d.file_skck, icon: 'fa-shield-alt', color: '#6366f1' },
                    { label: 'Surat Sehat', path: d.file_surat_sehat, icon: 'fa-heartbeat', color: '#e91e63' },
                ];
                const IMG_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
                let dh = '';
                docMap.forEach(doc => {
                    if (doc.path) {
                        const ext = (doc.path.split('.').pop() || '').toLowerCase();
                        const isImg = IMG_EXT.includes(ext);
                        const url = '/storage/' + doc.path;
                        if (isImg) {
                            dh += `<a href="#" data-url="${url}" data-label="${doc.label}" class="doc-pill img-viewer-link">
                            <i class="fas ${doc.icon}" style="color:${doc.color}"></i>${doc.label}
                        </a>`;
                        } else {
                            dh += `<a href="${url}" target="_blank" class="doc-pill">
                            <i class="fas ${doc.icon}" style="color:${doc.color}"></i>${doc.label}
                        </a>`;
                        }
                    }
                });
                $('#doc_grid_modal').html(dh || '<span class="text-muted"><i class="fas fa-folder-open mr-1"></i>Tidak ada dokumen tersedia</span>');
            });
        });


        function filterTgl(val) {
            let url = new URL(window.location.href);
            if (val) {
                url.searchParams.set('tgl_pendaftaran', val);
            } else {
                url.searchParams.delete('tgl_pendaftaran');
            }
            window.location.href = url.toString();
        }
    </script>

    <script src="{{ asset('js/demo/datatables-demo.js') }}"></script>

</body>

</html>