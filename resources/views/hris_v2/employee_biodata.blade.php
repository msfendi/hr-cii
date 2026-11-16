@extends('hris_v2.layout')

@section('judul', 'Employee Data')

{{-- Page-scoped styling: only /v2/karyawan/biodata loads these rules. --}}
@push('styles')
<style>
    /* ---------- Employee data (biodata) ---------- */
    .bio-hero-in { display: flex; align-items: flex-start; gap: 18px; padding: 22px 22px 20px; }
    .bio-avatar {
        width: 88px; height: 88px; flex: 0 0 88px; border-radius: 50%;
        object-fit: cover; border: 2px solid rgba(255, 255, 255, .28);
        box-shadow: 0 0 0 4px rgba(59, 140, 255, .16), 0 12px 30px rgba(0, 0, 0, .5);
    }
    .bio-avatar-fallback {
        width: 88px; height: 88px; flex: 0 0 88px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.9rem; font-weight: 800; color: #ffffff;
        background: linear-gradient(135deg, var(--v2-primary) 0%, var(--v2-info) 100%);
        border: 2px solid rgba(255, 255, 255, .28);
        box-shadow: 0 0 0 4px rgba(59, 140, 255, .16), 0 12px 30px rgba(0, 0, 0, .5);
    }
    .bio-ident { min-width: 0; flex: 1 1 auto; }
    .bio-name {
        margin: 2px 0 4px; font-size: 1.34rem; font-weight: 600;
        color: #ffffff; letter-spacing: -.01em; line-height: 1.2;
    }
    .bio-role { color: var(--v2-secondary); font-size: .9rem; }
    .bio-chips { margin-top: 10px; }

    /* Summary tiles */
    .bio-stat { display: flex; align-items: center; gap: 13px; padding: 15px 16px; height: 100%; }
    .bio-stat-ico {
        width: 42px; height: 42px; flex: 0 0 42px; border-radius: .5rem;
        display: flex; align-items: center; justify-content: center;
        color: #ffffff; font-size: 1.05rem;
    }
    .bio-stat-lbl {
        font-size: .66rem; text-transform: uppercase; letter-spacing: .09em;
        color: #7dd3fc; font-weight: 800; line-height: 1.25;
    }
    .bio-stat-val { font-size: 1.04rem; font-weight: 400; color: #eef3fb; line-height: 1.3; }

    /* Section cards */
    .bio-sec { scroll-margin-top: 88px; }
    .bio-sec-head {
        display: flex; align-items: center; gap: 12px;
        padding: 15px 20px; border-bottom: 1px solid var(--v2-line);
    }
    .bio-sec-ico {
        width: 38px; height: 38px; flex: 0 0 38px; border-radius: .5rem;
        display: flex; align-items: center; justify-content: center;
        color: #ffffff; font-size: .98rem;
    }
    .bio-sec-title { margin: 0; font-size: 1rem; font-weight: 700; color: var(--v2-heading); }
    .bio-count {
        margin-left: 8px; font-size: .72rem; font-weight: 700; color: #b9c8dd;
        background: rgba(255, 255, 255, .06); border: 1px solid var(--v2-line); border-radius: 999px;
        padding: 2px 9px;
    }
    .bio-sec-body { padding: 18px 20px 20px; }

    /* Label / value grid — labels are headings (bold), values are data (not bold) */
    .bio-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px 22px; }
    .bio-item { min-width: 0; }
    .bio-item.span-all { grid-column: 1 / -1; }
    .bio-lbl {
        font-size: .66rem; text-transform: uppercase; letter-spacing: .09em;
        color: #7dd3fc; font-weight: 800; margin-bottom: 3px;
    }
    .bio-val { color: #e6edf9; font-size: .95rem; font-weight: 400; line-height: 1.5; word-break: break-word; }

    /* Sub-heading inside a section */
    .bio-sub {
        display: flex; align-items: center; gap: 8px; margin: 20px 0 10px;
        font-size: .72rem; text-transform: uppercase; letter-spacing: .09em;
        color: var(--v2-secondary); font-weight: 800;
    }
    .bio-sub:first-child { margin-top: 0; }
    .bio-sub i { color: var(--v2-primary); }

    /* Compact label/value pairs (family, education, experience) */
    .bio-kv { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px 18px; margin: 0; }
    .bio-kv > div { min-width: 0; }
    .bio-kv dt {
        font-size: .66rem; text-transform: uppercase; letter-spacing: .07em;
        color: #7dd3fc; font-weight: 800; margin-bottom: 1px;
    }
    .bio-kv dd { margin: 0; font-size: .92rem; font-weight: 400; color: #e6edf9; word-break: break-word; }

    /* Record list (children, siblings, education, work experience) */
    .bio-rec { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
    .bio-rec-item {
        border: 1px solid var(--v2-line); border-radius: .5rem;
        background: rgba(255, 255, 255, .035); padding: 13px 15px;
    }
    .bio-rec-head { display: flex; align-items: center; gap: 9px; margin-bottom: 10px; }
    .bio-rec-no {
        width: 24px; height: 24px; flex: 0 0 24px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .72rem; font-weight: 800; color: #9cc6ff;
        background: rgba(59, 140, 255, .16); border: 1px solid rgba(59, 140, 255, .38);
    }
    .bio-rec-name { font-size: .96rem; font-weight: 700; color: var(--v2-heading); }

    .bio-empty {
        display: flex; align-items: center; gap: 9px;
        border: 1px dashed var(--v2-line-strong); border-radius: .5rem;
        padding: 13px 15px; color: var(--v2-muted); font-size: .86rem;
    }
    .bio-footnote { color: var(--v2-muted); font-size: .85rem; }
    .bio-tel { color: #e6edf9; text-decoration: none; }

    @media (max-width: 767.98px) {
        .bio-hero-in { padding: 18px 16px; gap: 14px; flex-wrap: wrap; }
        .bio-avatar, .bio-avatar-fallback { width: 68px; height: 68px; flex-basis: 68px; font-size: 1.5rem; }
        .bio-name { font-size: 1.15rem; }
        .bio-sec-body { padding: 16px; }
        .bio-sec-head { padding: 14px 16px; }
        .bio-grid, .bio-kv { grid-template-columns: 1fr; }
    }
    @media print {
        .v2-topbar, .v2-footer, .bio-hero .btn { display: none !important; }
        .v2-card { box-shadow: none; border-color: #ccd2e3; }
        .bio-sec { break-inside: avoid; }
    }
</style>
@endpush

@section('content')
@php
    /* English labels for the columns that appear in family, education,
       and work-experience data (source: pelamar_details). */
    $labelKolom = [
        'nama' => 'Name', 'nama_lengkap' => 'Name', 'hubungan' => 'Relationship',
        'usia' => 'Age', 'pekerjaan' => 'Occupation', 'pendidikan' => 'Education',
        'alamat' => 'Address', 'jenis_kelamin' => 'Gender', 'jk' => 'Gender',
        'tanggal_lahir' => 'Date of Birth', 'tgl_lahir' => 'Date of Birth',
        'status' => 'Status', 'keterangan' => 'Notes',
        'perusahaan' => 'Company', 'nama_perusahaan' => 'Company', 'jabatan' => 'Position',
        'posisi' => 'Position', 'tahun_mulai' => 'Start Year', 'tahun_selesai' => 'End Year',
        'tgl_mulai' => 'Start', 'tgl_selesai' => 'End', 'periode' => 'Period',
        'masa_kerja' => 'Years of Service', 'deskripsi' => 'Description', 'alamat_perusahaan' => 'Address',
        'institusi' => 'Institution', 'sekolah' => 'School', 'nama_sekolah' => 'School',
        'jenjang' => 'Level', 'level' => 'Level', 'jurusan' => 'Major',
        'tahun' => 'Year', 'tahun_lulus' => 'Graduation Year', 'ipk' => 'GPA', 'nilai' => 'Grade',
    ];
    $namaKolom = function ($kunci) use ($labelKolom) {
        if (isset($labelKolom[$kunci])) {
            return $labelKolom[$kunci];
        }

        return ucwords(str_replace(['_', '-'], ' ', (string) $kunci));
    };
    $adalahDaftar = function ($nilai) {
        return is_array($nilai) && $nilai !== [] && array_keys($nilai) === range(0, count($nilai) - 1);
    };
    $adaIsi = function ($nilai) {
        if (is_array($nilai)) {
            return array_filter($nilai, fn ($v) => ! is_array($v) && $v !== null && $v !== '') !== [];
        }

        return $nilai !== null && $nilai !== '';
    };
    $inisial = strtoupper(substr($bio['nama'] !== '' ? $bio['nama'] : $npk, 0, 1));

@endphp

{{-- ===================== Identity header ===================== --}}
<div class="v2-card mb-3">
    <div class="bio-hero-in">
        @if($bio['foto'])
            <img src="{{ $bio['foto'] }}" alt="Photo {{ $bio['nama'] }}" class="bio-avatar">
        @else
            <div class="bio-avatar-fallback" aria-hidden="true">{{ $inisial }}</div>
        @endif
        <div class="bio-ident">
            <h1 class="bio-name">{{ $bio['nama'] }}</h1>
            <div class="bio-role">
                <i class="fas fa-sitemap mr-1"></i>{{ $bio['departemen'] }}
            </div>
            <div class="bio-chips">
                <span class="v2-badge v2-badge-cyan">NPK {{ $bio['npk'] }}</span>
            </div>
        </div>
        <a href="{{ route('v2.employee.home') }}" class="btn v2-btn v2-btn-ghost btn-sm mt-1">
            <i class="fas fa-arrow-left mr-1"></i> Home
        </a>
    </div>
</div>

@if(! $bio['ada'])
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle mr-1"></i>Employee data for NPK <strong>{{ $npk }}</strong> is not available in the employee database yet. Contact HRD to complete the data.</div>
@else
    {{-- ===================== Summary tiles ===================== --}}
    <div class="row mb-3">
        <div class="col-6 col-md-3 mb-3 mb-md-0">
            <div class="v2-card bio-stat h-100">
                <div class="bio-stat-ico" style="background:#4e73df;border:1px solid #4e73df;box-shadow:0 .15rem .5rem rgba(78,115,223,.35);"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="bio-stat-lbl">Years of Service</div>
                    <div class="bio-stat-val">{{ $bio['masaKerja'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3 mb-md-0">
            <div class="v2-card bio-stat h-100">
                <div class="bio-stat-ico" style="background:#36b9cc;border:1px solid #36b9cc;box-shadow:0 .15rem .5rem rgba(54,185,204,.35);"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <div class="bio-stat-lbl">Join Date</div>
                    <div class="bio-stat-val">{{ $bio['tanggalMasuk'] ?: '-' }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="v2-card bio-stat h-100">
                <div class="bio-stat-ico" style="background:#1cc88a;border:1px solid #1cc88a;box-shadow:0 .15rem .5rem rgba(28,200,138,.35);"><i class="fas fa-file-signature"></i></div>
                <div>
                    <div class="bio-stat-lbl">Active Contract Until</div>
                    <div class="bio-stat-val">{{ $bio['kontrakAkhir'] ?: '-' }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="v2-card bio-stat h-100">
                <div class="bio-stat-ico" style="background:#858796;border:1px solid #858796;box-shadow:0 .15rem .5rem rgba(133,135,150,.35);"><i class="fas fa-phone-alt"></i></div>
                <div>
                    <div class="bio-stat-lbl">Phone</div>
                    <div class="bio-stat-val">
                        @if($bio['nomorTelepon'] && $bio['nomorTelepon'] !== '-')
                            <span class="bio-tel">{{ $bio['nomorTelepon'] }}</span>
                        @else
                            -
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- ==================== Left column ==================== --}}
        <div class="col-12 col-lg-6">

            {{-- Personal data & contact --}}
            <section id="personal" class="v2-card bio-sec mb-3">
                <div class="bio-sec-head">
                    <div class="bio-sec-ico" style="background:#4e73df;border:1px solid #4e73df;box-shadow:0 .15rem .5rem rgba(78,115,223,.35);"><i class="fas fa-user-circle"></i></div>
                    <h2 class="bio-sec-title">Personal Data &amp; Contact</h2>
                </div>
                <div class="bio-sec-body">
                    <div class="bio-grid">
                        @foreach($bio['pribadi'] as $baris)
                            @if(! empty($baris['value']))
                                @php $lebar = in_array($baris['label'], ['ID Card Address'], true) ? ' span-all' : ''; @endphp
                                <div class="bio-item{{ $lebar }}">
                                    <div class="bio-lbl"><i class="fas {{ $baris['icon'] }} mr-1"></i>{{ $baris['label'] }}</div>
                                    <div class="bio-val">{{ $baris['value'] }}</div>
                                </div>
                            @endif
                        @endforeach
                        @if(! empty($bio['kontakDarurat']['domisili']))
                            <div class="bio-item span-all">
                                <div class="bio-lbl"><i class="fas fa-map-marked-alt mr-1"></i>Residential Address</div>
                                <div class="bio-val">{{ $bio['kontakDarurat']['domisili'] }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Employment data --}}
            <section id="employment" class="v2-card bio-sec mb-3">
                <div class="bio-sec-head">
                    <div class="bio-sec-ico" style="background:#36b9cc;border:1px solid #36b9cc;box-shadow:0 .15rem .5rem rgba(54,185,204,.35);"><i class="fas fa-briefcase"></i></div>
                    <h2 class="bio-sec-title">Employment Data</h2>
                </div>
                <div class="bio-sec-body">
                    @php $kepegawaian = collect($bio['kepegawaian'])->filter(fn ($b) => ! empty($b['value'])); @endphp
                    @if($kepegawaian->isEmpty())
                        <div class="bio-empty"><i class="fas fa-inbox"></i>No employment records yet.</div>
                    @else
                        <div class="bio-grid">
                            @foreach($kepegawaian as $baris)
                                <div class="bio-item">
                                    <div class="bio-lbl"><i class="fas {{ $baris['icon'] }} mr-1"></i>{{ $baris['label'] }}</div>
                                    <div class="bio-val">{{ $baris['value'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            {{-- Contract history --}}
            <section id="contracts" class="v2-card bio-sec mb-3">
                <div class="bio-sec-head">
                    <div class="bio-sec-ico" style="background:#1cc88a;border:1px solid #1cc88a;box-shadow:0 .15rem .5rem rgba(28,200,138,.35);"><i class="fas fa-file-signature"></i></div>
                    <h2 class="bio-sec-title">Contract History</h2>
                    @if($bio['kontrak'] !== [])
                        <span class="bio-count ml-auto">{{ count($bio['kontrak']) }}</span>
                    @endif
                </div>
                <div class="bio-sec-body">
                    @if($bio['kontrak'] === [])
                        <div class="bio-empty"><i class="fas fa-inbox"></i>No contract data for this NPK yet.</div>
                    @else
                        <div class="v2-table-wrap">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Contract</th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bio['kontrak'] as $k)
                                        @php $statusKontrak = strtoupper((string) $k['status']); @endphp
                                        <tr>
                                            <td>#{{ $k['ke'] ?: '-' }}</td>
                                            <td>{{ $k['mulai'] ?: '-' }}</td>
                                            <td>{{ $k['selesai'] ?: '-' }}</td>
                                            <td>{{ $k['durasi'] }}</td>
                                            <td>
                                                <span class="badge {{ $statusKontrak === 'AKTIF' ? 'badge-success' : 'badge-secondary' }}">
                                                    {{ $k['status'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        {{-- ==================== Right column ==================== --}}
        <div class="col-12 col-lg-6">

            {{-- Family & emergency contact --}}
            <section id="family" class="v2-card bio-sec mb-3">
                <div class="bio-sec-head">
                    <div class="bio-sec-ico" style="background:#6f42c1;border:1px solid #6f42c1;box-shadow:0 .15rem .5rem rgba(111,66,193,.35);"><i class="fas fa-users"></i></div>
                    <h2 class="bio-sec-title">Family &amp; Emergency Contact</h2>
                </div>
                <div class="bio-sec-body">
                    <div class="row">
                        @foreach([['orang' => 'Father', 'ikon' => 'fa-male', 'data' => $bio['ayah']],
                                  ['orang' => 'Mother', 'ikon' => 'fa-female', 'data' => $bio['ibu']]] as $anggota)
                            @php
                                $isiOrang = [];
                                foreach ($anggota['data'] as $kunci => $nilai) {
                                    if ($adaIsi($nilai)) {
                                        $isiOrang[$kunci] = $nilai;
                                    }
                                }
                            @endphp
                            <div class="col-12 col-sm-6">
                                <div class="bio-sub"><i class="fas {{ $anggota['ikon'] }}"></i>{{ $anggota['orang'] }}</div>
                                @if($isiOrang === [])
                                    <div class="bio-empty"><i class="fas fa-inbox"></i>No data yet</div>
                                @else
                                    <dl class="bio-kv">
                                        @foreach($isiOrang as $kunci => $nilai)
                                            <div>
                                                <dt>{{ $namaKolom($kunci) }}</dt>
                                                <dd>{{ $nilai }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Emergency contact --}}
                    <div class="bio-sub"><i class="fas fa-phone-volume"></i>Emergency Contact</div>
                    @if($adaIsi($bio['kontakDarurat']['nama'] ?? null) || $adaIsi($bio['kontakDarurat']['telepon'] ?? null))
                        <div class="bio-grid">
                            <div class="bio-item">
                                <div class="bio-lbl">Name</div>
                                <div class="bio-val">
                                    {{ $bio['kontakDarurat']['nama'] ?: '-' }}
                                    @if(! empty($bio['kontakDarurat']['hubungan']))
                                        <span class="v2-muted">({{ $bio['kontakDarurat']['hubungan'] }})</span>
                                    @endif
                                </div>
                            </div>
                            <div class="bio-item">
                                <div class="bio-lbl">Phone</div>
                                <div class="bio-val">
                                    @if(! empty($bio['kontakDarurat']['telepon']))
                                        <span class="bio-tel">{{ $bio['kontakDarurat']['telepon'] }}</span>
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="bio-empty"><i class="fas fa-inbox"></i>No data yet</div>
                    @endif

                    @include('hris_v2._biodata_records', [
                        'judul' => 'Children', 'ikon' => 'fa-child', 'data' => $bio['anak'],
                        'kosong' => 'No data yet', 'namaKolom' => $namaKolom, 'adalahDaftar' => $adalahDaftar,
                    ])

                    @include('hris_v2._biodata_records', [
                        'judul' => 'Siblings', 'ikon' => 'fa-user-friends', 'data' => $bio['saudara'],
                        'kosong' => 'No data yet', 'namaKolom' => $namaKolom, 'adalahDaftar' => $adalahDaftar,
                    ])
                </div>
            </section>

            {{-- Education history --}}
            <section id="education" class="v2-card bio-sec mb-3">
                <div class="bio-sec-head">
                    <div class="bio-sec-ico" style="background:#f6c23e;border:1px solid #f6c23e;box-shadow:0 .15rem .5rem rgba(246,194,62,.35);"><i class="fas fa-graduation-cap"></i></div>
                    <h2 class="bio-sec-title">Education History</h2>
                </div>
                <div class="bio-sec-body">
                    @include('hris_v2._biodata_records', [
                        'judul' => 'Schools & Degrees', 'ikon' => 'fa-graduation-cap', 'data' => $bio['pendidikan'],
                        'kosong' => 'No education history in the applicant data.',
                        'namaKolom' => $namaKolom, 'adalahDaftar' => $adalahDaftar,
                    ])
                </div>
            </section>

            {{-- Work experience --}}
            <section id="experience" class="v2-card bio-sec mb-3">
                <div class="bio-sec-head">
                    <div class="bio-sec-ico" style="background:#e74a3b;border:1px solid #e74a3b;box-shadow:0 .15rem .5rem rgba(231,74,59,.35);"><i class="fas fa-history"></i></div>
                    <h2 class="bio-sec-title">Work Experience History</h2>
                </div>
                <div class="bio-sec-body">
                    @include('hris_v2._biodata_records', [
                        'judul' => 'Previous Employment', 'ikon' => 'fa-briefcase', 'data' => $bio['pengalaman'],
                        'kosong' => 'No work experience history in the applicant data.',
                        'namaKolom' => $namaKolom, 'adalahDaftar' => $adalahDaftar,
                    ])
                </div>
            </section>
        </div>
    </div>

    <div class="v2-footnote mt-1">
        <i class="fas fa-info-circle mr-1"></i>Data is sourced from employment records (PKWT/BIODATA) and applicant data. If anything is incorrect, contact HRD for an update.</div>
@endif
@endsection
