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
            font-size: 12.5px; color: rgba(255,255,255,.45); }
.tl-line { flex: 0 0 20px; height: 2px; background: rgba(255,255,255,.2); margin-bottom: 14px; transition: background .2s; }
.tl-line.done { background: #1cc88a; }

/* Doc pills */
.doc-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: 6px;
    background: #f8f9fc; border: 1.5px solid #e3e6f0;
    color: #4a5568; font-size: 12.5px; font-weight: 600;
    text-decoration: none; transition: all .15s; margin: 3px;
}
.doc-pill:hover { background: #4e73df; color: #fff; border-color: #4e73df; text-decoration: none; }
.doc-pill i { font-size: 15px; }

/* WA Modal */
#whatsappModal .modal-content { border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 16px 50px rgba(0,0,0,.18); }

/* Filter active button */
.btn-group .btn.active { box-shadow: inset 0 2px 4px rgba(0,0,0,.15); }

/* DataTables tweaks */
.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input {
    font-size: 13px;
    border-radius: 5px;
    border: 1px solid #d1d5db;
    padding: 4px 8px;
}
.dataTables_wrapper .dataTables_info { font-size: 13px; color: #6b7280; }
.dataTables_wrapper .dataTables_paginate .paginate_button {
    font-size: 13px !important; border-radius: 5px !important; padding: 4px 10px !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #4e73df !important; color: #fff !important; border-color: #4e73df !important;
}
</style>
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
                        <div class="btn-group shadow-sm" role="group">
                            <a href="{{ route('recruitment.index') }}"
                                class="btn btn-sm {{ is_null($status) ? 'btn-primary active' : 'btn-outline-primary' }}">
                                <i class="fas fa-th-list mr-1"></i>Semua
                            </a>
                            <a href="{{ route('recruitment.index', ['status' => 'never_confirm']) }}"
                                class="btn btn-sm {{ $status === 'never_confirm' ? 'btn-secondary active' : 'btn-outline-secondary' }}">
                                <i class="fas fa-inbox mr-1"></i>Applied
                            </a>
                            <a href="{{ route('recruitment.index', ['status' => 'ready_test']) }}"
                                class="btn btn-sm {{ $status === 'ready_test' ? 'btn-info active' : 'btn-outline-info' }}">
                                <i class="fas fa-clipboard-list mr-1"></i>Test
                            </a>
                            <a href="{{ route('recruitment.index', ['status' => 'ready_interview']) }}"
                                class="btn btn-sm {{ $status === 'ready_interview' ? 'btn-warning active' : 'btn-outline-warning' }}">
                                <i class="fas fa-comments mr-1"></i>Interview
                            </a>
                            <a href="{{ route('recruitment.index', ['status' => 'onboarding']) }}"
                                class="btn btn-sm {{ $status === 'onboarding' ? 'btn-success active' : 'btn-outline-success' }}">
                                <i class="fas fa-check-circle mr-1"></i>Onboarding
                            </a>
                            <a href="{{ route('recruitment.index', ['status' => 'decline']) }}"
                                class="btn btn-sm {{ $status === 'decline' ? 'btn-danger active' : 'btn-outline-danger' }}">
                                <i class="fas fa-times-circle mr-1"></i>Decline
                            </a>
                        </div>
                    </div>

                    {{-- Flash Messages --}}
                    @foreach (['success', 'error', 'warning', 'info'] as $type)
                        @if ($msg = Session::get($type))
                            <div class="alert alert-{{ $type }} alert-dismissible fade show shadow-sm" role="alert">
                                <i class="fas fa-{{ $type === 'success' ? 'check-circle' : ($type === 'error' ? 'times-circle' : ($type === 'warning' ? 'exclamation-triangle' : 'info-circle')) }} mr-2"></i>
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
                            <span class="badge badge-light text-primary font-weight-bold px-3 py-1" id="tbl-count">–</span>
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
                                            <th style="min-width:160px">Status Apply</th>
                                            <th width="70" class="text-center">Dok.</th>
                                            <th width="120" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($recruitments as $recruitment)
                                            @php
                                                $initial = strtoupper(mb_substr($recruitment->NAMA ?? 'X', 0, 1));
                                                $isMale = strtoupper($recruitment->JENIS_KELAMIN ?? '') === 'LAKI-LAKI';
                                                $sa = $recruitment->STATUS_APPLY ?? null;

                                                $sClass = match ($sa) {
                                                    'APPLIED' => 's-applied',
                                                    'INVITATION TEST' => 's-test',
                                                    'CALLED TO INTERVIEW' => 's-interview',
                                                    'ONBOARDING' => 's-onboard',
                                                    'REJECTED' => 's-reject',
                                                    default => 's-default',
                                                };
                                                $sIcon = match ($sa) {
                                                    'APPLIED' => 'fa-inbox',
                                                    'INVITATION TEST' => 'fa-vial',
                                                    'CALLED TO INTERVIEW' => 'fa-comments',
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
                                                $docCount = collect($docs)->filter()->count();
                                            @endphp
                                            <tr>
                                                {{-- # --}}
                                                <td class="text-center text-muted">{{ $loop->iteration }}</td>

                                                {{-- Nama --}}
                                                <td>
                                                    <div class="d-flex align-items-center" style="gap:10px;">
                                                        <div class="av {{ $isMale ? 'av-m' : 'av-f' }}">{{ $initial }}</div>
                                                        <div>
                                                            <div class="name-main">{{ $recruitment->NAMA }}</div>
                                                            <div class="name-sub">{{ $isMale ? '♂ Laki-laki' : '♀ Perempuan' }}</div>
                                                            @if($recruitment->jabatan)
                                                                <div class="name-sub"><i class="fas fa-briefcase mr-1" style="font-size:10px;"></i>{{ $recruitment->jabatan }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>

                                                {{-- NIK / TTL --}}
                                                <td>
                                                    <div style="font-size:12.5px; font-weight:600; font-family:monospace; color:#2d3748; letter-spacing:.5px;">{{ $recruitment->NIK ?? '-' }}</div>
                                                    <div class="name-sub"><i class="fas fa-map-marker-alt mr-1" style="font-size:10px;"></i>{{ $recruitment->TMPT_LAHIR ?? '-' }}</div>
                                                    <div class="name-sub">
                                                        <i class="fas fa-calendar mr-1" style="font-size:10px;"></i>
                                                        {{ $recruitment->TGL_LAHIR ? \Carbon\Carbon::parse($recruitment->TGL_LAHIR)->format('d M Y') : '-' }}
                                                    </div>
                                                    <div class="name-sub" style="color:#94a3b8;">{{ $recruitment->UMUR ?? '-' }}</div>
                                                </td>

                                                {{-- Pendidikan --}}
                                                <td>
                                                    <span class="badge badge-light border" style="font-size:12px; padding:4px 8px; font-weight:700;">{{ $recruitment->PENDIDIKAN ?? '-' }}</span>
                                                    <div class="name-sub mt-1">{{ $recruitment->JURUSAN ?? '-' }}</div>
                                                    <div class="name-sub">{{ $recruitment->NAMA_SEKOLAH ?? '-' }}</div>
                                                    @if($recruitment->KABUPATEN_SEKOLAH)
                                                        <div class="name-sub" style="color:#94a3b8;">{{ $recruitment->KABUPATEN_SEKOLAH }}</div>
                                                    @endif
                                                </td>

                                                {{-- Fisik --}}
                                                <td class="text-center">
                                                    <div style="font-size:13px; font-weight:700; color:#2d3748;">
                                                        <i class="fas fa-ruler-vertical text-info mr-1" style="font-size:11px;"></i>{{ $recruitment->TINGGI_BADAN ?? '-' }} cm
                                                    </div>
                                                    <div style="font-size:13px; font-weight:700; color:#2d3748;">
                                                        <i class="fas fa-weight text-warning mr-1" style="font-size:11px;"></i>{{ $recruitment->BERAT_BADAN ?? '-' }} kg
                                                    </div>
                                                </td>

                                                {{-- Kontak --}}
                                                <td>
                                                    <div style="font-size:13px; font-weight:700; color:#2d3748;">{{ $recruitment->HP ?? '-' }}</div>
                                                    <div class="name-sub"><i class="fas fa-map-pin mr-1" style="font-size:10px;"></i>{{ $recruitment->KABUPATEN ?? '-' }}</div>
                                                    @if($recruitment->ALAMAT_DOMISILI)
                                                        <div class="name-sub" style="color:#94a3b8;">Domisili: {{ $recruitment->ALAMAT_DOMISILI }}</div>
                                                    @endif
                                                </td>

                                                {{-- Agama / Status --}}
                                                <td>
                                                    <div class="name-main" style="font-size:13px;">{{ $recruitment->AGAMA ?? '-' }}</div>
                                                    <div class="name-sub">{{ $recruitment->STATUS ?? '-' }}</div>
                                                    <div class="name-sub">{{ $recruitment->TANGGUNGAN ?? '0' }} tanggungan</div>
                                                </td>

                                                {{-- Status Apply --}}
                                                <td>
                                                    <span class="s-pill {{ $sClass }}">
                                                        <i class="fas {{ $sIcon }}"></i>{{ $recruitment->status_apply ?? $sa ?? '-' }}
                                                    </span>
                                                </td>

                                                {{-- Dokumen --}}
                                                <td class="text-center">
                                                    @if ($docCount > 0)
                                                        <div class="dropdown">
                                                            <button class="doc-fold-btn dropdown-toggle" type="button" data-toggle="dropdown">
                                                                <i class="fas fa-folder-open text-warning"></i>
                                                                <span class="badge badge-primary" style="font-size:9px;">{{ $docCount }}</span>
                                                            </button>
                                                            <div class="dropdown-menu dropdown-menu-right shadow-sm" style="min-width:160px;">
                                                                @foreach ($docs as $label => $path)
                                                                    @if ($path)
                                                                        @php $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); @endphp
                                                                        <a class="dropdown-item d-flex align-items-center" style="font-size:12.5px; gap:8px;"
                                                                            href="{{ asset('storage/' . $path) }}" target="_blank">
                                                                            <i class="fas {{ in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? 'fa-image text-purple' : 'fa-file-pdf text-danger' }}"></i>
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
                                                            data-dept="{{ $recruitment->dept ?? '-' }}"
                                                            data-toggle="modal" data-target="#whatsappModal">
                                                            <i class="fab fa-whatsapp"></i> WA
                                                        </button>
                                                        <button type="button" class="act-btn act-det btn-detail"
                                                            data-recruitment="{{ json_encode($recruitment) }}"
                                                            data-toggle="modal" data-target="#detailModal">
                                                            <i class="fas fa-eye"></i> Detail
                                                        </button>
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
                                <button type="button" class="close text-white ml-2" data-dismiss="modal" style="opacity:.8; font-size:22px;"><span>&times;</span></button>
                            </div>

                            {{-- Timeline --}}
                            <div class="tl-bar">
                                @foreach([
                                        ['tl_apply', 'fa-inbox', 'Applied'],
                                        ['tl_test', 'fa-vial', 'Test'],
                                        ['tl_interview', 'fa-comments', 'Interview'],
                                        ['tl_health', 'fa-heartbeat', 'Kesehatan'],
                                        ['tl_onboard', 'fa-check-circle', 'Onboarding'],
                                    ] as $i => [$tid, $tic, $tlb])
                                                                    @if($i > 0)
                                                                        <div class="tl-line" id="{{ $tid }}_line"></div>
                                                                    @endif
                                                                    <div class="tl-step">
                                                                        <div class="tl-dot" id="{{ $tid }}_dot"><i class="fas {{ $tic }}" style="font-size:10px;"></i></div>
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
                            </div>
                        </div>{{-- /det-hero --}}

                        {{-- TAB PANES --}}
                        <div class="det-body">

                            {{-- ── TAB: PRIBADI ── --}}
                            <div class="det-pane active" id="pane-pribadi">
                                <div class="row" style="row-gap:0;">
                                    <div class="col-md-6">
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-blue"><i class="fas fa-id-card"></i> Identitas Diri</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-2">
                                                    <div><div class="fl">NIK</div><div class="fv" id="dp_nik">–</div></div>
                                                    <div><div class="fl">No. KK</div><div class="fv" id="dp_kk">–</div></div>
                                                    <div><div class="fl">Jenis Kelamin</div><div class="fv" id="dp_jk">–</div></div>
                                                    <div><div class="fl">Agama</div><div class="fv" id="dp_agama">–</div></div>
                                                    <div><div class="fl">Tempat Lahir</div><div class="fv" id="dp_tmpt">–</div></div>
                                                    <div><div class="fl">Tanggal Lahir</div><div class="fv" id="dp_tgl">–</div></div>
                                                    <div><div class="fl">Umur</div><div class="fv" id="dp_umur">–</div></div>
                                                    <div><div class="fl">Warga Negara</div><div class="fv" id="dp_wn">–</div></div>
                                                    <div><div class="fl">Status Nikah</div><div class="fv" id="dp_status">–</div></div>
                                                    <div><div class="fl">Tanggungan</div><div class="fv" id="dp_tang">–</div></div>
                                                    <div><div class="fl">Ikut KB</div><div class="fv" id="dp_kb">–</div></div>
                                                    <div><div class="fl">No. SIM</div><div class="fv" id="dp_sim">–</div></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-cyan"><i class="fas fa-running"></i> Fisik & Info Tambahan</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-2">
                                                    <div><div class="fl">Tinggi Badan</div><div class="fv" id="dp_tb">–</div></div>
                                                    <div><div class="fl">Berat Badan</div><div class="fv" id="dp_bb">–</div></div>
                                                    <div><div class="fl">Transportasi</div><div class="fv" id="dp_transport">–</div></div>
                                                    <div><div class="fl">Bakat / Hobby</div><div class="fv" id="dp_bakat">–</div></div>
                                                    <div><div class="fl">BPJS TK</div><div class="fv" id="dp_bpjstk">–</div></div>
                                                    <div><div class="fl">BPJS Kesehatan</div><div class="fv" id="dp_bpjskes">–</div></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-purple"><i class="fas fa-briefcase"></i> Posisi yang Dilamar</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-2">
                                                    <div><div class="fl">Jabatan</div><div class="fv" id="dp_jabatan">–</div></div>
                                                    <div><div class="fl">Department</div><div class="fv" id="dp_dept">–</div></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-gray"><i class="fas fa-lightbulb"></i> Motivasi & Kegiatan</div>
                                            <div class="sec-card-body" style="font-size:14.5px;">
                                                <div class="fl">Kegiatan Ekstra</div>
                                                <div class="fv mb-2" id="dp_ekstra">–</div>
                                                <div class="fl">Motivasi</div>
                                                <div class="fv" id="dp_motivasi" style="white-space:pre-line; line-height:1.6; font-weight:400; color:#4a5568;">–</div>
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
                                            <div class="sec-card-hd hd-green"><i class="fas fa-phone"></i> Kontak Utama</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-1" style="grid-template-columns:1fr;">
                                                    <div><div class="fl">Nomor HP</div><div class="fv" id="dk_hp">–</div></div>
                                                    <div><div class="fl">Alamat KTP</div><div class="fv" id="dk_alamat_ktp">–</div></div>
                                                    <div><div class="fl">Kabupaten (KTP)</div><div class="fv" id="dk_kab">–</div></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-yellow"><i class="fas fa-home"></i> Domisili Sekarang</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-1" style="grid-template-columns:1fr;">
                                                    <div><div class="fl">Alamat Sekarang</div><div class="fv" id="dk_alamat_skrg">–</div></div>
                                                    <div><div class="fl">Kab/Kota Sekarang</div><div class="fv" id="dk_kab_skrg">–</div></div>
                                                    <div><div class="fl">Status Domisili</div><div class="fv" id="dk_domisili">–</div></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-red"><i class="fas fa-exclamation-triangle"></i> Kontak Darurat</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-1" style="grid-template-columns:1fr;">
                                                    <div><div class="fl">Nama</div><div class="fv" id="dk_ktk_nama">–</div></div>
                                                    <div><div class="fl">Hubungan</div><div class="fv" id="dk_ktk_hub">–</div></div>
                                                    <div><div class="fl">No. Telepon</div><div class="fv" id="dk_ktk_telp">–</div></div>
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
                                                    <div><div class="fl">Nama</div><div class="fv" id="df_ayah_nama">–</div></div>
                                                    <div><div class="fl">Tgl Lahir</div><div class="fv" id="df_ayah_tgl">–</div></div>
                                                    <div><div class="fl">Pendidikan</div><div class="fv" id="df_ayah_pend">–</div></div>
                                                    <div><div class="fl">Pekerjaan</div><div class="fv" id="df_ayah_kerja">–</div></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="sec-card">
                                            <div class="sec-card-hd hd-purple"><i class="fas fa-female"></i> Data Ibu</div>
                                            <div class="sec-card-body">
                                                <div class="fg fg-2">
                                                    <div><div class="fl">Nama</div><div class="fv" id="df_ibu_nama">–</div></div>
                                                    <div><div class="fl">Tgl Lahir</div><div class="fv" id="df_ibu_tgl">–</div></div>
                                                    <div><div class="fl">Pendidikan</div><div class="fv" id="df_ibu_pend">–</div></div>
                                                    <div><div class="fl">Pekerjaan</div><div class="fv" id="df_ibu_kerja">–</div></div>
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
                                    <div class="sec-card-hd hd-yellow"><i class="fas fa-graduation-cap"></i> Pendidikan Terakhir</div>
                                    <div class="sec-card-body">
                                        <div class="fg fg-4">
                                            <div><div class="fl">Jenjang</div><div class="fv" id="dpd_pend">–</div></div>
                                            <div><div class="fl">Jurusan</div><div class="fv" id="dpd_jurusan">–</div></div>
                                            <div><div class="fl">Nama Sekolah</div><div class="fv" id="dpd_sekolah">–</div></div>
                                            <div><div class="fl">Kabupaten</div><div class="fv" id="dpd_kabsekolah">–</div></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-blue"><i class="fas fa-history"></i> Riwayat Pendidikan</div>
                                    <div class="sec-card-body" id="tbl_pendidikan_wrap"></div>
                                </div>
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-purple"><i class="fas fa-star"></i> Kegiatan Ekstra</div>
                                    <div class="sec-card-body">
                                        <div class="fv" id="dpd_ekstra" style="font-size:15px; font-weight:600; color:#4a5568;">–</div>
                                    </div>
                                </div>
                            </div>

                            {{-- ── TAB: KARIR ── --}}
                            <div class="det-pane" id="pane-karir">
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-cyan"><i class="fas fa-building"></i> Pengalaman Kerja</div>
                                    <div class="sec-card-body" id="tbl_pengalaman_wrap"></div>
                                </div>
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-gray"><i class="fas fa-comment-alt"></i> Motivasi Melamar</div>
                                    <div class="sec-card-body">
                                        <div class="fv" id="dpk_motivasi" style="white-space:pre-line; line-height:1.7; font-weight:400; color:#4a5568;">–</div>
                                    </div>
                                </div>
                            </div>

                            {{-- ── TAB: DOKUMEN ── --}}
                            <div class="det-pane" id="pane-dokumen">
                                <div class="sec-card">
                                    <div class="sec-card-hd hd-red"><i class="fas fa-folder-open"></i> Dokumen Pelamar</div>
                                    <div class="sec-card-body">
                                        <p class="text-muted mb-2" style="font-size:12px;">Klik dokumen untuk membuka di tab baru.</p>
                                        <div id="doc_grid_modal" style="display:flex; flex-wrap:wrap;"></div>
                                    </div>
                                </div>
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
                            <div class="modal-header border-0" style="background:linear-gradient(135deg,#128c7e,#25d366); padding:16px 24px;">
                                <h5 class="modal-title text-white font-weight-bold mb-0">
                                    <i class="fab fa-whatsapp mr-2" style="font-size:20px;"></i>Kirim Pesan WhatsApp
                                </h5>
                                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.9;"><span>&times;</span></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="text-xs font-weight-bold text-uppercase text-muted">Penerima</label>
                                            <input type="text" name="nama" id="wa_nama" class="form-control form-control-sm" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="text-xs font-weight-bold text-uppercase text-muted">Nomor HP</label>
                                            <input type="text" name="nomor_hp" id="wa_phone" class="form-control form-control-sm" readonly>
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
                                                <label class="text-xs font-weight-bold text-uppercase text-muted">Tanggal</label>
                                                <input type="date" name="tgl_schedule" id="wa_date" class="form-control form-control-sm">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="text-xs font-weight-bold text-uppercase text-muted">Waktu</label>
                                                <input type="time" name="time_schedule" id="wa_time" class="form-control form-control-sm">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="text-xs font-weight-bold text-uppercase text-muted">Preview Pesan</label>
                                    <textarea name="message" id="wa_message" class="form-control" rows="9" required style="font-size:14.5px; line-height:1.65;"></textarea>
                                    <small class="form-text text-muted">Pesan dapat diedit secara manual sebelum dikirim.</small>
                                </div>
                            </div>
                            <div class="modal-footer bg-light border-top py-2">
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-success btn-sm font-weight-bold px-4">
                                    <i class="fab fa-whatsapp mr-1"></i>Kirim WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @include('layout.footer')
        </div>
    </div>

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

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

        /* ── WA Templates ── */
        const WA = {
            invitation: "Haloo, [NAMA]\nSelamat! Anda terpilih untuk melanjutkan ke tahap berikutnya dalam Rekrutmen untuk Posisi [JABATAN] PT Chutex International Indonesia.\n\nSebagai langkah selanjutnya, kami mengundang Anda untuk interview pada:\n\nHari, Tanggal: [DATE]\nWaktu: [TIME] WIB-Selesai\nAlamat: https://maps.app.goo.gl/MfkgQPUbuFhtRHf96\n\nHarap konfirmasi kehadiran:\nNama_HADIR/TIDAK HADIR\n\nDatang 30 menit sebelum jadwal dengan:\n1. Membawa pulpen hitam\n2. Membawa KTP asli\n3. Menggunakan pakaian hitam putih\n\nWASPADA PENIPUAN! PT Chutex International Indonesia TIDAK PERNAH memungut biaya apapun.\n\nSalam,\nRecruitment PT Chutex International Indonesia",
            interview:  "Semangat Pagi, [NAMA]\nSelamat! Anda terpilih untuk melanjutkan ke tahap Interview HRD.\n\nHari, Tanggal: [DATE]\nWaktu: [TIME] WIB-Selesai\nAlamat: https://maps.app.goo.gl/MfkgQPUbuFhtRHf96\n\nKonfirmasi: Nama_HADIR/TIDAK HADIR\n\nHarap membawa:\n* Surat lamaran kerja & CV\n* FC KTP 8 lbr, FC KK 3 lbr, FC Ijazah 5 lbr\n* FC Akta Kelahiran 1 lbr, SKCK berlaku\n* Surat Sehat, Pas foto 3x4 merah 7 lbr\n\nWASPADA PENIPUAN!\n\nSalam,\nRecruitment PT Chutex International Indonesia",
            onboarding: "Semangat Pagi, [NAMA]\n\nSelamat! Anda dinyatakan LOLOS. Efektif per [DATE] Anda resmi bergabung sebagai [JABATAN] ([DEPARTMEN]).\n\nHadir sebelum pukul 08.00:\nhttps://maps.app.goo.gl/MfkgQPUbuFhtRHf96\nBertemu Mbak Lala HRD. Pakaian hitam putih & lengkapi berkas.\n\nWASPADA PENIPUAN!\n\nSalam,\nRecruitment PT Chutex International Indonesia",
            rejection:  "Halo [NAMA],\n\nTerima kasih telah melamar dan mengikuti proses rekrutmen di PT Chutex International Indonesia.\n\nSetelah melalui proses seleksi, saat ini kami belum dapat melanjutkan proses Anda. Tetap semangat!\n\nSalam,\nRecruitment PT Chutex International Indonesia"
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
            $('#wa_datetime_container').toggle(['invitation','interview','onboarding'].includes(t));
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

        /* ── Helpers ── */
        const v    = (x, fb) => (x !== null && x !== undefined && x !== '') ? x : (fb || '–');
        const fmtD = d => { if (!d) return '–'; try { return new Date(d).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}); } catch(e){ return d; } };
        const pj   = r => { if (!r) return null; if (typeof r === 'object') return r; try { return JSON.parse(r); } catch(e){ return null; } };

        function stbl(headers, rows) {
            if (!rows || !rows.length) return '<div class="stbl-empty"><i class="fas fa-minus mr-1"></i>Tidak ada data</div>';
            let h = '<div class="table-responsive"><table class="stbl"><thead><tr>' + headers.map(x=>`<th>${x}</th>`).join('') + '</tr></thead><tbody>';
            rows.forEach(r => { h += '<tr>' + r.map(c=>`<td>${v(c)}</td>`).join('') + '</tr>'; });
            return h + '</tbody></table></div>';
        }

        function setTl(id, lineId, status, date) {
            const dot = $('#' + id + '_dot');
            if (lineId) { const line = $('#' + lineId + '_line'); line.removeClass('done'); if (status === 'done') line.addClass('done'); }
            $('#' + id + '_date').text(date ? fmtD(date) : '–');
            dot.removeClass('done active');
            if (status === 'done')   dot.addClass('done');
            if (status === 'active') dot.addClass('active');
        }

        /* ── Detail modal ── */
        $(document).on('click', '.btn-detail', function () {
            const d = $(this).data('recruitment');

            // Reset tabs
            $('.det-tab').removeClass('active').first().addClass('active');
            $('.det-pane').removeClass('active').first().addClass('active');

            // Hero
            $('#det_av').text((d.NAMA || 'X').charAt(0).toUpperCase());
            $('#det_nama').text(v(d.NAMA));
            $('#det_sub').text(
                (d.JENIS_KELAMIN === 'LAKI-LAKI' ? '♂ Laki-laki' : '♀ Perempuan') +
                ' · ' + v(d.AGAMA) + ' · ' + v(d.UMUR) +
                (d.jabatan ? ' · ' + d.jabatan : '')
            );
            $('#det_status_hero').text(d.STATUS_APPLY || '–');

            // Timeline
            const isTest  = d.is_test === 'TRUE' || d.is_test === true;
            const isIntvw = d.is_interview === 'TRUE' || d.is_interview === true;
            const isHlth  = d.is_kesehatan === 'TRUE' || d.is_kesehatan === true;
            const isOnb   = d.STATUS_APPLY === 'ONBOARDING';
            setTl('tl_apply',    null,           'done',             null);
            setTl('tl_test',     'tl_test',      isTest  ? 'done':'' , d.tgl_test);
            setTl('tl_interview','tl_interview', isIntvw ? 'done':'',  d.tgl_interview);
            setTl('tl_health',   'tl_health',   isHlth  ? 'done':'',  d.tgl_kesehatan);
            setTl('tl_onboard',  'tl_onboard',  isOnb   ? 'done':'',  d.tgl_diterima);

            // Pribadi
            $('#dp_nik').text(v(d.NIK)); $('#dp_kk').text(v(d.NO_KK));
            $('#dp_jk').text(d.JENIS_KELAMIN === 'LAKI-LAKI' ? '♂ Laki-laki' : '♀ Perempuan');
            $('#dp_agama').text(v(d.AGAMA)); $('#dp_tmpt').text(v(d.TMPT_LAHIR));
            $('#dp_tgl').text(fmtD(d.TGL_LAHIR)); $('#dp_umur').text(v(d.UMUR));
            $('#dp_wn').text(v(d.warga_negara)); $('#dp_status').text(v(d.STATUS));
            $('#dp_tang').text(v(d.TANGGUNGAN,'0') + ' orang');
            $('#dp_kb').text(d.ikut_kb == 1 ? 'Ya' : (d.ikut_kb == 0 ? 'Tidak' : '–'));
            $('#dp_sim').text(v(d.nomor_sim));
            $('#dp_tb').text(v(d.TINGGI_BADAN) + ' cm'); $('#dp_bb').text(v(d.BERAT_BADAN) + ' kg');
            $('#dp_transport').text(v(d.mode_transportasi)); $('#dp_bakat').text(v(d.bakat_hobby));
            $('#dp_bpjstk').text(v(d.bpjs_tk)); $('#dp_bpjskes').text(v(d.bpjs_kes));
            $('#dp_jabatan').text(v(d.jabatan)); $('#dp_dept').text(v(d.department));
            $('#dp_ekstra').text(v(d.kegiatan_ekstra)); $('#dp_motivasi').text(v(d.motivasi));

            // Kontak
            $('#dk_hp').text(v(d.HP)); $('#dk_alamat_ktp').text(v(d.ALAMAT_LENGKAP));
            $('#dk_kab').text(v(d.KABUPATEN)); $('#dk_alamat_skrg').text(v(d.alamat_skrg));
            $('#dk_kab_skrg').text(v(d.kabupaten_kota_skrg)); $('#dk_domisili').text(v(d.status_domisili));
            $('#dk_ktk_nama').text(v(d.nama_ktk_darurat)); $('#dk_ktk_hub').text(v(d.hubungan));
            $('#dk_ktk_telp').text(v(d.no_telp_darurat));

            // Keluarga
            const ayah = pj(d.data_ayah), ibu = pj(d.data_ibu);
            $('#df_ayah_nama').text(v(ayah&&ayah.nama)); $('#df_ayah_tgl').text(fmtD(ayah&&ayah.tgl_lahir));
            $('#df_ayah_pend').text(v(ayah&&ayah.pendidikan)); $('#df_ayah_kerja').text(v(ayah&&ayah.pekerjaan));
            $('#df_ibu_nama').text(v(ibu&&ibu.nama)); $('#df_ibu_tgl').text(fmtD(ibu&&ibu.tgl_lahir));
            $('#df_ibu_pend').text(v(ibu&&ibu.pendidikan)); $('#df_ibu_kerja').text(v(ibu&&ibu.pekerjaan));

            const sdr = pj(d.saudara_kandung);
            $('#tbl_saudara_wrap').html(stbl(['Nama','Tgl Lahir','Gender','Pendidikan','Pekerjaan'],
                sdr && sdr.map(s=>[s.nama,fmtD(s.tgl_lahir),s.gender,s.pendidikan,s.pekerjaan])));

            const ank = pj(d.data_anak);
            $('#tbl_anak_wrap').html(stbl(['Nama','Tempat Lahir','Tgl Lahir','Gender','Pendidikan','Status'],
                ank && ank.map(a=>[a.nama,a.tempat_lahir,fmtD(a.tgl_lahir),a.gender,a.pendidikan,a.status])));

            // Pendidikan
            $('#dpd_pend').text(v(d.PENDIDIKAN)); $('#dpd_jurusan').text(v(d.JURUSAN));
            $('#dpd_sekolah').text(v(d.NAMA_SEKOLAH)); $('#dpd_kabsekolah').text(v(d.KABUPATEN_SEKOLAH));
            $('#dpd_ekstra').text(v(d.kegiatan_ekstra));
            const rp = pj(d.riwayat_pendidikan);
            $('#tbl_pendidikan_wrap').html(stbl(['Tingkat','Institusi','Jurusan','Dari','Sampai','Lulus'],
                rp && rp.map(r=>[r.tingkat,r.institusi,r.jurusan,r.dari,r.sampai,r.lulus=='1'?'✓ Lulus':'–'])));

            // Karir
            const pg = pj(d.pengalaman_kerja);
            $('#tbl_pengalaman_wrap').html(stbl(['Perusahaan','Dari','Sampai','Jabatan','Departemen','Alasan Keluar'],
                pg && pg.map(p=>[p.perusahaan,p.dari,p.sampai,p.jabatan,p.departemen,p.alasan])));
            $('#dpk_motivasi').text(v(d.motivasi));

            // Dokumen
            const docMap = [
                { label:'Surat Lamaran', path: d.file_surat_lamaran, icon:'fa-file-pdf',      color:'#e74a3b' },
                { label:'CV',            path: d.file_cv,            icon:'fa-file-alt',       color:'#4e73df' },
                { label:'KTP',           path: d.file_ktp,           icon:'fa-id-card',        color:'#36b9cc' },
                { label:'Kartu Keluarga',path: d.file_kk,            icon:'fa-users',          color:'#1cc88a' },
                { label:'Pas Foto',      path: d.file_pas_foto,      icon:'fa-image',          color:'#8b5cf6' },
                { label:'Ijazah',        path: d.file_ijasah,        icon:'fa-graduation-cap', color:'#f6c23e' },
                { label:'Akta Lahir',    path: d.file_akta_kelahiran,icon:'fa-certificate',    color:'#f97316' },
                { label:'SKCK',          path: d.file_skck,          icon:'fa-shield-alt',     color:'#6366f1' },
                { label:'Surat Sehat',   path: d.file_surat_sehat,   icon:'fa-heartbeat',      color:'#e91e63' },
            ];
            let dh = '';
            docMap.forEach(doc => {
                if (doc.path) {
                    dh += `<a href="/storage/${doc.path}" target="_blank" class="doc-pill">
                        <i class="fas ${doc.icon}" style="color:${doc.color}"></i>${doc.label}
                    </a>`;
                }
            });
            $('#doc_grid_modal').html(dh || '<span class="text-muted"><i class="fas fa-folder-open mr-1"></i>Tidak ada dokumen tersedia</span>');
        });
    });
    </script>

    <script src="{{ asset('js/demo/datatables-demo.js') }}"></script>

</body>
</html>