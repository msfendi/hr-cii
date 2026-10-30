<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body id="page-top">
@include('sweetalert::alert')
<div id="wrapper">
    @include('layout.sidebar')
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @include('layout.navbar')
            <div class="container-fluid pb-5">

                {{-- Breadcrumb --}}
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent p-0 m-0">
                            <li class="breadcrumb-item"><a href="/biodata/index" class="text-primary"><i class="fas fa-users mr-1"></i>Data Karyawan</a></li>
                            <li class="breadcrumb-item active text-dark font-weight-bold">Manage — {{ $pkwt->NAMA }}</li>
                        </ol>
                    </nav>
                    <a href="/biodata/index" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar
                    </a>
                </div>

                {{-- ═══════════════════════════════════════════════════════ --}}
                {{-- HERO / HEADER CARD                                      --}}
                {{-- ═══════════════════════════════════════════════════════ --}}
                <div class="card shadow-sm border-0 mb-4 overflow-hidden" style="border-radius:14px; background:#fff;">
                    {{-- Banner atas dengan aksen modern --}}
                    <div style="background: linear-gradient(135deg, #1f2d3d 0%, #2b3d52 60%, #3a506b 100%); height: 95px; position: relative;">
                        <div style="position: absolute; right: 24px; top: 12px; opacity: 0.12; font-size: 65px; color: #fff;">
                            <i class="fas fa-id-badge"></i>
                        </div>
                    </div>

                    {{-- Isi Profil --}}
                    <div class="card-body px-4 pt-0 pb-4">
                        <div class="row align-items-end">
                            {{-- Foto Avatar --}}
                            <div class="col-auto" style="margin-top: -50px;">
                                <div class="position-relative d-inline-block">
                                    <div class="rounded-circle shadow border border-4 border-white" style="width: 105px; height: 105px; overflow: hidden; background: #eaecf4;">
                                        <img id="heroPhoto" src="{{ $profilePhotoUrl }}"
                                             alt="Foto {{ $pkwt->NAMA }}"
                                             class="w-100 h-100"
                                             style="object-fit: cover;"
                                             onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($pkwt->NAMA) }}&background=2b3d52&color=fff&size=105'">
                                    </div>
                                    <label for="heroPhotoInput" class="btn btn-sm btn-primary rounded-circle shadow position-absolute"
                                           style="bottom: 4px; right: 4px; width: 30px; height: 30px; padding: 0; display: flex; align-items: center; justify-content: center; cursor: pointer;"
                                           title="Ganti Foto Profil">
                                        <i class="fas fa-camera" style="font-size: 11px;"></i>
                                    </label>
                                    <form id="photoUploadForm" action="/biodata/manage/update-photo/{{ $pkwt->NPK }}" method="POST" enctype="multipart/form-data" class="d-none">
                                        @csrf
                                        <input type="file" id="heroPhotoInput" name="foto_profil" accept="image/*">
                                    </form>
                                </div>
                            </div>

                            {{-- Info Nama & Badge --}}
                            <div class="col mt-2 mt-md-0">
                                <div class="d-flex flex-wrap align-items-center mb-1" style="gap: 8px;">
                                    <h4 class="font-weight-bold text-dark mb-0 mr-1" style="font-size: 1.35rem;">{{ $pkwt->NAMA }}</h4>
                                    <span class="badge badge-primary px-2.5 py-1" style="font-size: 12px; border-radius: 6px;">
                                        <i class="fas fa-fingerprint mr-1"></i>NPK {{ $pkwt->NPK }}
                                    </span>
                                    @if($pkwt->IS_STAFF)
                                        <span class="badge badge-info px-2.5 py-1" style="font-size: 12px; border-radius: 6px;">
                                            <i class="fas fa-user-tie mr-1"></i>Staff
                                        </span>
                                    @else
                                        <span class="badge badge-light border text-muted px-2.5 py-1" style="font-size: 12px; border-radius: 6px;">
                                            Non-Staff
                                        </span>
                                    @endif
                                    @if($activeContract)
                                        <span class="badge badge-success px-2.5 py-1" style="font-size: 12px; border-radius: 6px;">
                                            <i class="fas fa-check-circle mr-1"></i>Kontrak Aktif
                                        </span>
                                    @else
                                        <span class="badge badge-secondary px-2.5 py-1" style="font-size: 12px; border-radius: 6px;">
                                            Non-Aktif
                                        </span>
                                    @endif
                                </div>

                                <div class="text-muted small d-flex flex-wrap align-items-center" style="gap: 12px;">
                                    <span><i class="fas fa-sitemap mr-1 text-primary"></i><strong>{{ $pkwt->BAGIAN ?? '-' }}</strong></span>
                                    @if($pkwt->section_name)
                                        <span class="text-gray-400">&bull;</span>
                                        <span><i class="fas fa-layer-group mr-1 text-info"></i>{{ $pkwt->section_name }}</span>
                                    @endif
                                    @if($nomorSim)
                                        <span class="text-gray-400">&bull;</span>
                                        <span><i class="fas fa-id-card mr-1 text-warning"></i>SIM: {{ $nomorSim }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Tombol Aksi Kanan --}}
                            <div class="col-12 col-md-auto mt-3 mt-md-0 d-flex flex-wrap" style="gap: 8px;">
                                <a href="/biodata/generate-pdf/{{ $pkwt->NPK }}" target="_blank" class="btn btn-sm btn-outline-danger shadow-sm">
                                    <i class="fas fa-file-pdf mr-1"></i>PDF Biodata
                                </a>
                                @if($canExportAllDocs)
                                <a href="/biodata/export-all-docs/{{ $pkwt->NPK }}" class="btn btn-sm btn-outline-secondary shadow-sm">
                                    <i class="fas fa-file-archive mr-1"></i>Export Dokumen
                                </a>
                                @endif
                            </div>
                        </div>

                        {{-- Strip Info Angka / Statistik --}}
                        <hr class="my-3" style="border-top: 1px solid #eef2f7;">

                        <div class="row text-center text-md-left">
                            <div class="col-6 col-md-3 mb-2 mb-md-0">
                                <div class="p-2.5 rounded bg-light border-0">
                                    <div class="text-xs text-uppercase font-weight-bold text-muted mb-1">
                                        <i class="fas fa-history mr-1 text-primary"></i>Masa Kerja
                                    </div>
                                    <div class="h6 font-weight-bold text-dark mb-0">{{ $masaKerja }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2 mb-md-0">
                                <div class="p-2.5 rounded bg-light border-0">
                                    <div class="text-xs text-uppercase font-weight-bold text-muted mb-1">
                                        <i class="fas fa-calendar-check mr-1 text-success"></i>Tgl Masuk (TMK)
                                    </div>
                                    <div class="h6 font-weight-bold text-dark mb-0">{{ $pkwt->TMK ? \Carbon\Carbon::parse($pkwt->TMK)->format('d/m/Y') : '-' }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-2.5 rounded bg-light border-0">
                                    <div class="text-xs text-uppercase font-weight-bold text-muted mb-1">
                                        <i class="fas fa-calendar-times mr-1 text-warning"></i>Akhir Kontrak
                                    </div>
                                    <div class="h6 font-weight-bold text-dark mb-0">{{ $activeContract && $activeContract->end_date ? \Carbon\Carbon::parse($activeContract->end_date)->format('d/m/Y') : '-' }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-2.5 rounded bg-light border-0">
                                    <div class="text-xs text-uppercase font-weight-bold text-muted mb-1">
                                        <i class="fas fa-university mr-1 text-info"></i>Rekening Bank
                                    </div>
                                    <div class="h6 font-weight-bold text-dark mb-0">{{ $pkwt->bank_account ?: 'Belum diisi' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════════ --}}
                {{-- TAB NAVIGATION                                          --}}
                {{-- ═══════════════════════════════════════════════════════ --}}
                <ul class="nav nav-pills custom-manage-tabs mb-4 flex-wrap" id="manageTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold" id="tab-detail" data-toggle="tab" href="#pane-detail" role="tab">
                            <i class="fas fa-id-card mr-1.5"></i>Detail Profil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="tab-edit" data-toggle="tab" href="#pane-edit" role="tab">
                            <i class="fas fa-user-edit mr-1.5"></i>Update Data
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="tab-dokumen" data-toggle="tab" href="#pane-dokumen" role="tab">
                            <i class="fas fa-folder-open mr-1.5"></i>Berkas Dokumen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold text-danger" id="tab-exit" data-toggle="tab" href="#pane-exit" role="tab">
                            <i class="fas fa-sign-out-alt mr-1.5"></i>Exit Karyawan
                        </a>
                    </li>
                </ul>

                <div class="tab-content">

                    {{-- ══════════════════════════════════════════ --}}
                    {{-- TAB 1: DETAIL PROFIL                       --}}
                    {{-- ══════════════════════════════════════════ --}}
                    <div class="tab-pane fade show active" id="pane-detail" role="tabpanel">
                        <div class="row">
                            {{-- Data Pribadi --}}
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm border-0 h-100" style="border-radius:12px;">
                                    <div class="card-header bg-white border-0 pb-0 pt-3">
                                        <h6 class="font-weight-bold text-gray-800 mb-0">
                                            <i class="fas fa-user mr-2 text-primary"></i>Data Pribadi & Kontak
                                        </h6>
                                    </div>
                                    <div class="card-body pt-3">
                                        @php
                                        $personalFields = [
                                            ['label'=>'Nama Lengkap','value'=>$pkwt->NAMA,'icon'=>'fa-user'],
                                            ['label'=>'NIK / KTP','value'=>$pkwt->KTP,'icon'=>'fa-id-card'],
                                            ['label'=>'No. Kartu Keluarga','value'=>$pkwt->NO_KK,'icon'=>'fa-home'],
                                            ['label'=>'Nomor SIM','value'=>$nomorSim,'icon'=>'fa-id-badge'],
                                            ['label'=>'Jenis Kelamin','value'=>$pkwt->JK === 'L' ? 'Laki-laki' : ($pkwt->JK === 'P' ? 'Perempuan' : '-'),'icon'=>'fa-venus-mars'],
                                            ['label'=>'Tempat, Tgl Lahir','value'=>($pkwt->TMPTLAHIR ? $pkwt->TMPTLAHIR.', ' : '').($pkwt->TGLLAHIR ? \Carbon\Carbon::parse($pkwt->TGLLAHIR)->format('d/m/Y') : '-'),'icon'=>'fa-birthday-cake'],
                                            ['label'=>'Usia','value'=>$pkwt->USIA ? $pkwt->USIA : '-','icon'=>'fa-calendar-alt'],
                                            ['label'=>'Agama','value'=>$pkwt->AGAMA,'icon'=>'fa-praying-hands'],
                                            ['label'=>'Status Pernikahan','value'=>$pkwt->STATUS,'icon'=>'fa-heart'],
                                            ['label'=>'Jumlah Tanggungan','value'=>$pkwt->TANGGUNGAN !== null ? $pkwt->TANGGUNGAN.' orang' : '-','icon'=>'fa-users'],
                                            ['label'=>'No. HP / WhatsApp','value'=>$pkwt->HP,'icon'=>'fa-phone'],
                                            ['label'=>'Alamat KTP','value'=>$pkwt->ALAMAT,'icon'=>'fa-map-marker-alt'],
                                            ['label'=>'Kabupaten / Kota','value'=>$pkwt->KABUPATEN,'icon'=>'fa-city'],
                                            ['label'=>'Pendidikan Terakhir','value'=>$pkwt->PDDK,'icon'=>'fa-graduation-cap'],
                                            ['label'=>'Jurusan','value'=>$pkwt->JURUSAN,'icon'=>'fa-book'],
                                        ];
                                        @endphp
                                        @foreach($personalFields as $f)
                                        <div class="d-flex align-items-start py-2" style="border-bottom:1px solid #f4f6f9;">
                                            <div class="flex-shrink-0 text-muted mr-3 mt-1" style="width:18px;text-align:center;">
                                                <i class="fas {{ $f['icon'] }}" style="font-size:12px;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="text-muted" style="font-size:11px;">{{ $f['label'] }}</div>
                                                <div class="font-weight-semibold text-dark" style="font-size:13px;">{{ $f['value'] ?: '-' }}</div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Data Kepegawaian & Kontrak --}}
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm border-0 mb-4" style="border-radius:12px;">
                                    <div class="card-header bg-white border-0 pb-0 pt-3">
                                        <h6 class="font-weight-bold text-gray-800 mb-0">
                                            <i class="fas fa-briefcase mr-2 text-success"></i>Data Kepegawaian
                                        </h6>
                                    </div>
                                    <div class="card-body pt-3">
                                        @php
                                        $empFields = [
                                            ['label'=>'NPK','value'=>$pkwt->NPK,'icon'=>'fa-fingerprint'],
                                            ['label'=>'Departemen / Bagian','value'=>$pkwt->BAGIAN,'icon'=>'fa-sitemap'],
                                            ['label'=>'Section','value'=>$pkwt->section_name,'icon'=>'fa-layer-group'],
                                            ['label'=>'Tgl Mulai Kerja (TMK)','value'=>$pkwt->TMK ? \Carbon\Carbon::parse($pkwt->TMK)->format('d/m/Y') : '-','icon'=>'fa-calendar-check'],
                                            ['label'=>'Tgl Keluar (TKK)','value'=>$pkwt->TKK ? \Carbon\Carbon::parse($pkwt->TKK)->format('d/m/Y') : '-','icon'=>'fa-calendar-times'],
                                            ['label'=>'Masa Kerja','value'=>$masaKerja,'icon'=>'fa-clock'],
                                            ['label'=>'No. Rekening Bank','value'=>$pkwt->bank_account ?: '-','icon'=>'fa-university'],
                                            ['label'=>'Status Karyawan','value'=>$pkwt->IS_STAFF ? 'Staff' : 'Non-Staff','icon'=>'fa-user-tie'],
                                        ];
                                        @endphp
                                        @foreach($empFields as $f)
                                        <div class="d-flex align-items-start py-2" style="border-bottom:1px solid #f4f6f9;">
                                            <div class="flex-shrink-0 text-muted mr-3 mt-1" style="width:18px;text-align:center;">
                                                <i class="fas {{ $f['icon'] }}" style="font-size:12px;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="text-muted" style="font-size:11px;">{{ $f['label'] }}</div>
                                                <div class="font-weight-semibold text-dark" style="font-size:13px;">{{ $f['value'] ?: '-' }}</div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Riwayat Kontrak --}}
                                <div class="card shadow-sm border-0 mb-4" style="border-radius:12px;">
                                    <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between pt-3 pb-2"
                                         style="cursor:pointer;" data-toggle="collapse" data-target="#contractHistory">
                                        <h6 class="font-weight-bold text-gray-800 mb-0">
                                            <i class="fas fa-file-contract mr-2 text-info"></i>Riwayat Kontrak
                                            <span class="badge badge-light border ml-1">{{ $contracts->count() }}</span>
                                        </h6>
                                        <i class="fas fa-chevron-down text-muted"></i>
                                    </div>
                                    <div id="contractHistory" class="collapse show">
                                        <div class="card-body pt-0">
                                            @if($contracts->isEmpty())
                                                <p class="text-muted text-center py-2 mb-0" style="font-size:12px;">Belum ada riwayat kontrak.</p>
                                            @else
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0" style="font-size:12.5px;">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th>Ke-</th>
                                                            <th>Mulai</th>
                                                            <th>Selesai</th>
                                                            <th>Durasi</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($contracts as $c)
                                                        <tr>
                                                            <td><span class="badge badge-light border">{{ $c->contract_ke }}</span></td>
                                                            <td>{{ $c->start_date ? \Carbon\Carbon::parse($c->start_date)->format('d/m/Y') : '-' }}</td>
                                                            <td>{{ $c->end_date ? \Carbon\Carbon::parse($c->end_date)->format('d/m/Y') : '-' }}</td>
                                                            <td>{{ $c->month_duration ?? '-' }} bln</td>
                                                            <td>
                                                                @if($c->status_contract === 'AKTIF')
                                                                    <span class="badge badge-success">AKTIF</span>
                                                                @else
                                                                    <span class="badge badge-secondary">{{ $c->status_contract }}</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Data Keluarga (Ayah, Ibu, Saudara, Anak) --}}
                        <div class="card shadow-sm border-0 mb-4" style="border-radius:12px;">
                            <div class="card-header bg-white border-0 pt-3 pb-2">
                                <h6 class="font-weight-bold text-gray-800 mb-0">
                                    <i class="fas fa-users mr-2 text-primary"></i>Data Keluarga
                                </h6>
                            </div>
                            <div class="card-body pt-2">
                                <div class="row mb-3">
                                    {{-- Ayah --}}
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <div class="p-3 rounded bg-light border-0 h-100">
                                            <div class="font-weight-bold text-dark mb-2" style="font-size:13px;">
                                                <i class="fas fa-male mr-1 text-primary"></i> Data Ayah
                                            </div>
                                            <div class="small text-muted mb-1">Nama: <span class="font-weight-semibold text-dark">{{ $dataAyah['nama'] ?? '-' }}</span></div>
                                            <div class="small text-muted mb-1">Tgl Lahir: <span class="text-dark">{{ !empty($dataAyah['tgl_lahir']) ? \Carbon\Carbon::parse($dataAyah['tgl_lahir'])->format('d/m/Y') : '-' }}</span></div>
                                            <div class="small text-muted mb-1">Pendidikan: <span class="text-dark">{{ $dataAyah['pendidikan'] ?? '-' }}</span></div>
                                            <div class="small text-muted">Pekerjaan: <span class="text-dark">{{ $dataAyah['pekerjaan'] ?? '-' }}</span></div>
                                        </div>
                                    </div>
                                    {{-- Ibu --}}
                                    <div class="col-md-6">
                                        <div class="p-3 rounded bg-light border-0 h-100">
                                            <div class="font-weight-bold text-dark mb-2" style="font-size:13px;">
                                                <i class="fas fa-female mr-1 text-danger"></i> Data Ibu
                                            </div>
                                            <div class="small text-muted mb-1">Nama: <span class="font-weight-semibold text-dark">{{ $dataIbu['nama'] ?? ($pkwt->IBU ?? '-') }}</span></div>
                                            <div class="small text-muted mb-1">Tgl Lahir: <span class="text-dark">{{ !empty($dataIbu['tgl_lahir']) ? \Carbon\Carbon::parse($dataIbu['tgl_lahir'])->format('d/m/Y') : '-' }}</span></div>
                                            <div class="small text-muted mb-1">Pendidikan: <span class="text-dark">{{ $dataIbu['pendidikan'] ?? '-' }}</span></div>
                                            <div class="small text-muted">Pekerjaan: <span class="text-dark">{{ $dataIbu['pekerjaan'] ?? '-' }}</span></div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Saudara Kandung --}}
                                <div class="mb-3">
                                    <div class="font-weight-bold text-dark mb-2" style="font-size:13px;">
                                        <i class="fas fa-user-friends mr-1 text-info"></i> Saudara Kandung
                                    </div>
                                    @if(empty($saudaraKandung))
                                        <div class="text-muted small py-1">Belum ada data saudara kandung tercatat.</div>
                                    @else
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th width="5%">No</th>
                                                    <th>Nama Saudara</th>
                                                    <th width="10%">L/P</th>
                                                    <th width="15%">Tgl Lahir</th>
                                                    <th>Pendidikan</th>
                                                    <th>Pekerjaan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($saudaraKandung as $idx => $s)
                                                <tr>
                                                    <td>{{ $idx + 1 }}</td>
                                                    <td class="font-weight-semibold">{{ $s['nama'] ?? '-' }}</td>
                                                    <td>{{ $s['gender'] ?? ($s['jk'] ?? '-') }}</td>
                                                    <td>{{ !empty($s['tgl_lahir']) ? \Carbon\Carbon::parse($s['tgl_lahir'])->format('d/m/Y') : '-' }}</td>
                                                    <td>{{ $s['pendidikan'] ?? '-' }}</td>
                                                    <td>{{ $s['pekerjaan'] ?? '-' }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @endif
                                </div>

                                {{-- Data Anak --}}
                                <div>
                                    <div class="font-weight-bold text-dark mb-2" style="font-size:13px;">
                                        <i class="fas fa-baby mr-1 text-warning"></i> Data Anak
                                    </div>
                                    @if(empty($dataAnak))
                                        <div class="text-muted small py-1">Belum ada data anak tercatat.</div>
                                    @else
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th width="5%">No</th>
                                                    <th>Nama Anak</th>
                                                    <th width="10%">L/P</th>
                                                    <th>Tempat Lahir</th>
                                                    <th width="15%">Tgl Lahir</th>
                                                    <th>Pendidikan</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($dataAnak as $idx => $a)
                                                <tr>
                                                    <td>{{ $idx + 1 }}</td>
                                                    <td class="font-weight-semibold">{{ $a['nama'] ?? '-' }}</td>
                                                    <td>{{ $a['gender'] ?? ($a['jk'] ?? '-') }}</td>
                                                    <td>{{ $a['tempat_lahir'] ?? '-' }}</td>
                                                    <td>{{ !empty($a['tgl_lahir']) ? \Carbon\Carbon::parse($a['tgl_lahir'])->format('d/m/Y') : '-' }}</td>
                                                    <td>{{ $a['pendidikan'] ?? '-' }}</td>
                                                    <td>{{ $a['status'] ?? '-' }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Riwayat Pendidikan Formal & Pengalaman Kerja --}}
                        <div class="row">
                            {{-- Riwayat Pendidikan --}}
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm border-0 h-100" style="border-radius:12px;">
                                    <div class="card-header bg-white border-0 pt-3 pb-2">
                                        <h6 class="font-weight-bold text-gray-800 mb-0">
                                            <i class="fas fa-graduation-cap mr-2 text-warning"></i>Riwayat Pendidikan Formal
                                        </h6>
                                    </div>
                                    <div class="card-body pt-2">
                                        @if(empty($riwayatPendidikan))
                                            <div class="text-muted small py-2">Belum ada riwayat pendidikan tercatat.</div>
                                        @else
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Jenjang</th>
                                                        <th>Institusi</th>
                                                        <th>Jurusan</th>
                                                        <th>Periode</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($riwayatPendidikan as $edu)
                                                    <tr>
                                                        <td class="font-weight-bold">{{ $edu['tingkat'] ?? '-' }}</td>
                                                        <td>{{ $edu['institusi'] ?? ($edu['nama_sekolah'] ?? '-') }}</td>
                                                        <td>{{ $edu['jurusan'] ?? '-' }}</td>
                                                        <td>{{ ($edu['dari'] ?? ($edu['tahun_masuk'] ?? '-')) }} - {{ ($edu['sampai'] ?? ($edu['tahun_lulus'] ?? '-')) }}</td>
                                                        <td>
                                                            @php $lulus = $edu['lulus'] ?? null; @endphp
                                                            @if($lulus === 1 || $lulus === true || $lulus === '1')
                                                                <span class="badge badge-success">Lulus</span>
                                                            @elseif($lulus === 0 || $lulus === false || $lulus === '0')
                                                                <span class="badge badge-secondary">Belum Lulus</span>
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Riwayat Pengalaman Kerja --}}
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm border-0 h-100" style="border-radius:12px;">
                                    <div class="card-header bg-white border-0 pt-3 pb-2">
                                        <h6 class="font-weight-bold text-gray-800 mb-0">
                                            <i class="fas fa-history mr-2 text-primary"></i>Riwayat Pengalaman Kerja
                                        </h6>
                                    </div>
                                    <div class="card-body pt-2">
                                        @if(empty($pengalamanKerja))
                                            <div class="text-muted small py-2">Belum ada riwayat pengalaman kerja tercatat.</div>
                                        @else
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Perusahaan</th>
                                                        <th>Jabatan</th>
                                                        <th>Periode</th>
                                                        <th>Alasan Keluar</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($pengalamanKerja as $exp)
                                                    <tr>
                                                        <td class="font-weight-bold">{{ $exp['perusahaan'] ?? '-' }}</td>
                                                        <td>{{ $exp['jabatan'] ?? '-' }}</td>
                                                        <td>{{ ($exp['dari'] ?? ($exp['tahun_masuk'] ?? '-')) }} - {{ ($exp['sampai'] ?? ($exp['tahun_keluar'] ?? '-')) }}</td>
                                                        <td>{{ $exp['alasan_keluar'] ?? '-' }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>{{-- /pane-detail --}}

                    {{-- ══════════════════════════════════════════ --}}
                    {{-- TAB 2: UPDATE DATA                         --}}
                    {{-- ══════════════════════════════════════════ --}}
                    <div class="tab-pane fade" id="pane-edit" role="tabpanel">
                        <div class="card shadow-sm border-0" style="border-radius:12px;">
                            <div class="card-header bg-white border-0 pt-3 pb-0">
                                <h6 class="font-weight-bold text-gray-800 mb-0">
                                    <i class="fas fa-user-edit mr-2 text-primary"></i>Update Formulir Data Karyawan
                                </h6>
                            </div>
                            <div class="card-body">
                                <form id="manageEditForm" action="/biodata/manage/update/{{ $pkwt->NPK }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="from_manage" value="1">

                                    {{-- Sub-tabs update form --}}
                                    <ul class="nav nav-tabs mb-4" id="editSubTabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-toggle="tab" href="#esub-biodata">Biodata Pribadi</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#esub-kerja">Kepegawaian</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#esub-ortu">Data Orang Tua</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#esub-edu">Riwayat Pendidikan</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#esub-exp">Pengalaman Kerja</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#esub-fam">Saudara & Anak</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#esub-dokumen">Upload Berkas</a>
                                        </li>
                                    </ul>

                                    <div class="tab-content">

                                        {{-- ── 1. BIODATA PRIBADI ── --}}
                                        <div class="tab-pane fade show active" id="esub-biodata">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">Nama Lengkap <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="nama" value="{{ $pkwt->NAMA }}" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">NIK / KTP</label>
                                                    <input type="text" class="form-control" name="nik" value="{{ $pkwt->KTP }}">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="small font-weight-bold">No. Kartu Keluarga (KK)</label>
                                                    <input type="text" class="form-control" name="nkk" value="{{ $pkwt->NO_KK }}">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="small font-weight-bold">Nomor SIM</label>
                                                    <input type="text" class="form-control" name="nomor_sim" value="{{ $nomorSim }}" placeholder="Contoh: SIM C / SIM A">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="small font-weight-bold">Jenis Kelamin</label>
                                                    <select class="form-control" name="jk">
                                                        <option value="L" {{ $pkwt->JK === 'L' ? 'selected' : '' }}>Laki-laki</option>
                                                        <option value="P" {{ $pkwt->JK === 'P' ? 'selected' : '' }}>Perempuan</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">Tempat Lahir</label>
                                                    <input type="text" class="form-control" name="tempat_lahir" value="{{ $pkwt->TMPTLAHIR }}">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">Tanggal Lahir</label>
                                                    <input type="date" class="form-control" name="tgl_lahir" value="{{ $pkwt->TGLLAHIR ? \Carbon\Carbon::parse($pkwt->TGLLAHIR)->format('Y-m-d') : '' }}">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="small font-weight-bold">Agama</label>
                                                    <select class="form-control" name="agama">
                                                        @foreach(['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'] as $ag)
                                                        <option value="{{ $ag }}" {{ $pkwt->AGAMA === $ag ? 'selected' : '' }}>{{ $ag }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="small font-weight-bold">Status Pernikahan</label>
                                                    <input type="text" class="form-control" name="status" value="{{ $pkwt->STATUS }}" placeholder="BELUM MENIKAH / MENIKAH">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="small font-weight-bold">Jumlah Tanggungan</label>
                                                    <input type="number" class="form-control" name="tanggungan" min="0" value="{{ $pkwt->TANGGUNGAN }}">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">No. HP / WhatsApp</label>
                                                    <input type="text" class="form-control" name="hp" value="{{ $pkwt->HP }}">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">Kabupaten / Kota</label>
                                                    <input type="text" class="form-control" name="kabupaten" value="{{ $pkwt->KABUPATEN }}">
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="small font-weight-bold">Alamat Lengkap KTP</label>
                                                    <textarea class="form-control" name="alamat" rows="2">{{ $pkwt->ALAMAT }}</textarea>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">Pendidikan Terakhir</label>
                                                    <input type="text" class="form-control" name="pendidikan" value="{{ $pkwt->PDDK }}" placeholder="SMA/SMK, D3, S1">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">Jurusan</label>
                                                    <input type="text" class="form-control" name="jurusan" value="{{ $pkwt->JURUSAN }}">
                                                </div>
                                            </div>
                                        </div>

                                        {{-- ── 2. KEPEGAWAIAN ── --}}
                                        <div class="tab-pane fade" id="esub-kerja">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">Departemen / Bagian</label>
                                                    <select class="form-control" name="id_dept">
                                                        <option value="">-- Pilih Departemen --</option>
                                                        @foreach($departments as $dept)
                                                        <option value="{{ $dept->ID_DEPT }}"
                                                            {{ ($pkwt->ID_DEPT ?? '') == $dept->ID_DEPT ? 'selected' : '' }}>
                                                            {{ $dept->DEPARTEMENT }}
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">Section</label>
                                                    <select class="form-control" name="section">
                                                        <option value="">-- Pilih Section --</option>
                                                        @foreach($sections as $sec)
                                                        <option value="{{ $sec->id }}"
                                                            {{ ($pkwt->SECTION ?? '') == $sec->id ? 'selected' : '' }}>
                                                            {{ $sec->name }}
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">Tanggal Mulai Kerja (TMK)</label>
                                                    <input type="date" class="form-control" name="tmk" value="{{ $pkwt->TMK ? \Carbon\Carbon::parse($pkwt->TMK)->format('Y-m-d') : '' }}">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="small font-weight-bold">No. Rekening Bank</label>
                                                    <input type="text" class="form-control" name="bank_account" value="{{ $pkwt->bank_account }}">
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="editIsStaff" name="is_staff" value="1" {{ $pkwt->IS_STAFF ? 'checked' : '' }}>
                                                        <label class="custom-control-label font-weight-bold" for="editIsStaff">Status Staff</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>
                                            <label class="small font-weight-bold">Ganti Foto Profil</label>
                                            <div class="d-flex align-items-center" style="gap:16px;">
                                                <div class="rounded border" style="width:75px;height:75px;overflow:hidden;background:#e9ecef;">
                                                    <img id="edit_previewImage" src="{{ $profilePhotoUrl }}"
                                                         class="w-100 h-100" style="object-fit:cover;"
                                                         onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($pkwt->NAMA) }}&size=75'">
                                                </div>
                                                <div>
                                                    <input type="file" class="form-control-file" name="foto_profil" id="edit_fotoInput" accept="image/*">
                                                    <small class="text-muted">JPG, PNG maks 2MB</small>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- ── 3. DATA ORANG TUA ── --}}
                                        <div class="tab-pane fade" id="esub-ortu">
                                            <div class="row">
                                                {{-- Data Ayah --}}
                                                <div class="col-md-6 mb-4">
                                                    <div class="p-3 rounded bg-light border-0">
                                                        <h6 class="font-weight-bold text-primary mb-3"><i class="fas fa-male mr-1"></i> Data Ayah</h6>
                                                        <div class="mb-2">
                                                            <label class="small font-weight-bold">Nama Ayah</label>
                                                            <input type="text" class="form-control form-control-sm" name="data_ayah[nama]" value="{{ $dataAyah['nama'] ?? '' }}" placeholder="Nama Lengkap Ayah">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="small font-weight-bold">Tanggal Lahir Ayah</label>
                                                            <input type="date" class="form-control form-control-sm" name="data_ayah[tgl_lahir]" value="{{ $dataAyah['tgl_lahir'] ?? '' }}">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="small font-weight-bold">Pendidikan Ayah</label>
                                                            <input type="text" class="form-control form-control-sm" name="data_ayah[pendidikan]" value="{{ $dataAyah['pendidikan'] ?? '' }}" placeholder="SD/SMP/SMA/D3/S1">
                                                        </div>
                                                        <div class="mb-0">
                                                            <label class="small font-weight-bold">Pekerjaan Ayah</label>
                                                            <input type="text" class="form-control form-control-sm" name="data_ayah[pekerjaan]" value="{{ $dataAyah['pekerjaan'] ?? '' }}" placeholder="Pekerjaan saat ini">
                                                        </div>
                                                    </div>
                                                </div>
                                                {{-- Data Ibu --}}
                                                <div class="col-md-6 mb-4">
                                                    <div class="p-3 rounded bg-light border-0">
                                                        <h6 class="font-weight-bold text-danger mb-3"><i class="fas fa-female mr-1"></i> Data Ibu</h6>
                                                        <div class="mb-2">
                                                            <label class="small font-weight-bold">Nama Ibu Kandung</label>
                                                            <input type="text" class="form-control form-control-sm" name="ibu" value="{{ $dataIbu['nama'] ?? ($pkwt->IBU ?? '') }}" placeholder="Nama Lengkap Ibu">
                                                            <input type="hidden" name="data_ibu[nama]" id="hiddenDataIbuNama" value="{{ $dataIbu['nama'] ?? ($pkwt->IBU ?? '') }}">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="small font-weight-bold">Tanggal Lahir Ibu</label>
                                                            <input type="date" class="form-control form-control-sm" name="data_ibu[tgl_lahir]" value="{{ $dataIbu['tgl_lahir'] ?? '' }}">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="small font-weight-bold">Pendidikan Ibu</label>
                                                            <input type="text" class="form-control form-control-sm" name="data_ibu[pendidikan]" value="{{ $dataIbu['pendidikan'] ?? '' }}" placeholder="SD/SMP/SMA/D3/S1">
                                                        </div>
                                                        <div class="mb-0">
                                                            <label class="small font-weight-bold">Pekerjaan Ibu</label>
                                                            <input type="text" class="form-control form-control-sm" name="data_ibu[pekerjaan]" value="{{ $dataIbu['pekerjaan'] ?? '' }}" placeholder="Pekerjaan / Ibu Rumah Tangga">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- ── 4. RIWAYAT PENDIDIKAN (REPEATER) ── --}}
                                        <div class="tab-pane fade" id="esub-edu">
                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <span class="small font-weight-bold text-muted">Daftar riwayat pendidikan formal:</span>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddEdu">
                                                    <i class="fas fa-plus mr-1"></i> Tambah Baris Pendidikan
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered" id="tableEdu" style="font-size:12px;">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th width="15%">Jenjang</th>
                                                            <th width="25%">Nama Institusi / Sekolah</th>
                                                            <th width="20%">Jurusan</th>
                                                            <th width="12%">Tahun Masuk</th>
                                                            <th width="12%">Tahun Lulus</th>
                                                            <th width="10%">Status</th>
                                                            <th width="6%">Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="eduTbody">
                                                        @php $eduRows = !empty($riwayatPendidikan) ? $riwayatPendidikan : []; @endphp
                                                        @forelse($eduRows as $i => $edu)
                                                        <tr class="edu-row">
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][tingkat]" value="{{ $edu['tingkat'] ?? '' }}" placeholder="SMA/SMK">
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][institusi]" value="{{ $edu['institusi'] ?? ($edu['nama_sekolah'] ?? '') }}" placeholder="Nama Sekolah / Kampus">
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][jurusan]" value="{{ $edu['jurusan'] ?? '' }}" placeholder="Jurusan">
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][dari]" value="{{ $edu['dari'] ?? ($edu['tahun_masuk'] ?? '') }}" placeholder="2018">
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][sampai]" value="{{ $edu['sampai'] ?? ($edu['tahun_lulus'] ?? '') }}" placeholder="2021">
                                                            </td>
                                                            <td>
                                                                <select class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][lulus]">
                                                                    <option value="1" {{ ($edu['lulus'] ?? null) == 1 ? 'selected' : '' }}>Lulus</option>
                                                                    <option value="0" {{ ($edu['lulus'] ?? null) === 0 || ($edu['lulus'] ?? null) === '0' ? 'selected' : '' }}>Belum Lulus</option>
                                                                </select>
                                                            </td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                                                            </td>
                                                        </tr>
                                                        @empty
                                                        <tr class="edu-row">
                                                            <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[0][tingkat]" placeholder="SMA/SMK/D3/S1"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[0][institusi]" placeholder="Nama Sekolah / Kampus"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[0][jurusan]" placeholder="Jurusan"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[0][dari]" placeholder="Tahun Masuk"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[0][sampai]" placeholder="Tahun Lulus"></td>
                                                            <td>
                                                                <select class="form-control form-control-sm" name="riwayat_pendidikan[0][lulus]">
                                                                    <option value="1">Lulus</option>
                                                                    <option value="0">Belum Lulus</option>
                                                                </select>
                                                            </td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                                                            </td>
                                                        </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        {{-- ── 5. PENGALAMAN KERJA (REPEATER) ── --}}
                                        <div class="tab-pane fade" id="esub-exp">
                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <span class="small font-weight-bold text-muted">Daftar riwayat pengalaman kerja:</span>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddExp">
                                                    <i class="fas fa-plus mr-1"></i> Tambah Pengalaman Kerja
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered" id="tableExp" style="font-size:12px;">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th width="24%">Nama Perusahaan</th>
                                                            <th width="18%">Jabatan</th>
                                                            <th width="16%">Departemen</th>
                                                            <th width="10%">Dari (Tahun)</th>
                                                            <th width="10%">Sampai (Tahun)</th>
                                                            <th width="16%">Alasan Keluar</th>
                                                            <th width="6%">Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="expTbody">
                                                        @php $expRows = !empty($pengalamanKerja) ? $pengalamanKerja : []; @endphp
                                                        @forelse($expRows as $i => $exp)
                                                        <tr class="exp-row">
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][perusahaan]" value="{{ $exp['perusahaan'] ?? '' }}" placeholder="PT..."></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][jabatan]" value="{{ $exp['jabatan'] ?? '' }}" placeholder="Operator..."></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][departemen]" value="{{ $exp['departemen'] ?? '' }}" placeholder="Produksi..."></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][dari]" value="{{ $exp['dari'] ?? ($exp['tahun_masuk'] ?? '') }}" placeholder="2019"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][sampai]" value="{{ $exp['sampai'] ?? ($exp['tahun_keluar'] ?? '') }}" placeholder="2022"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][alasan_keluar]" value="{{ $exp['alasan_keluar'] ?? '' }}" placeholder="Habis kontrak"></td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                                                            </td>
                                                        </tr>
                                                        @empty
                                                        <tr class="exp-row">
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[0][perusahaan]" placeholder="Nama Perusahaan"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[0][jabatan]" placeholder="Jabatan"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[0][departemen]" placeholder="Departemen"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[0][dari]" placeholder="Dari"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[0][sampai]" placeholder="Sampai"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[0][alasan_keluar]" placeholder="Alasan Keluar"></td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                                                            </td>
                                                        </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        {{-- ── 6. SAUDARA & ANAK (REPEATERS) ── --}}
                                        <div class="tab-pane fade" id="esub-fam">
                                            {{-- Saudara Kandung --}}
                                            <div class="mb-4">
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <span class="font-weight-bold text-dark" style="font-size:13px;"><i class="fas fa-user-friends mr-1 text-info"></i> Saudara Kandung</span>
                                                    <button type="button" class="btn btn-sm btn-outline-info" id="btnAddSaudara">
                                                        <i class="fas fa-plus mr-1"></i> Tambah Saudara
                                                    </button>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered" id="tableSaudara" style="font-size:12px;">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th width="30%">Nama Saudara</th>
                                                                <th width="12%">L/P</th>
                                                                <th width="18%">Tgl Lahir</th>
                                                                <th width="18%">Pendidikan</th>
                                                                <th width="16%">Pekerjaan</th>
                                                                <th width="6%">Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="saudaraTbody">
                                                            @php $saudaraRows = !empty($saudaraKandung) ? $saudaraKandung : []; @endphp
                                                            @forelse($saudaraRows as $i => $s)
                                                            <tr class="saudara-row">
                                                                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[{{ $i }}][nama]" value="{{ $s['nama'] ?? '' }}" placeholder="Nama"></td>
                                                                <td>
                                                                    <select class="form-control form-control-sm" name="saudara_kandung[{{ $i }}][gender]">
                                                                        <option value="L" {{ ($s['gender'] ?? ($s['jk'] ?? '')) == 'L' ? 'selected' : '' }}>L</option>
                                                                        <option value="P" {{ ($s['gender'] ?? ($s['jk'] ?? '')) == 'P' ? 'selected' : '' }}>P</option>
                                                                    </select>
                                                                </td>
                                                                <td><input type="date" class="form-control form-control-sm" name="saudara_kandung[{{ $i }}][tgl_lahir]" value="{{ $s['tgl_lahir'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[{{ $i }}][pendidikan]" value="{{ $s['pendidikan'] ?? '' }}" placeholder="Pendidikan"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[{{ $i }}][pekerjaan]" value="{{ $s['pekerjaan'] ?? '' }}" placeholder="Pekerjaan"></td>
                                                                <td class="text-center">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                                                                </td>
                                                            </tr>
                                                            @empty
                                                            <tr class="saudara-row">
                                                                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[0][nama]" placeholder="Nama"></td>
                                                                <td>
                                                                    <select class="form-control form-control-sm" name="saudara_kandung[0][gender]">
                                                                        <option value="L">L</option>
                                                                        <option value="P">P</option>
                                                                    </select>
                                                                </td>
                                                                <td><input type="date" class="form-control form-control-sm" name="saudara_kandung[0][tgl_lahir]"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[0][pendidikan]" placeholder="Pendidikan"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[0][pekerjaan]" placeholder="Pekerjaan"></td>
                                                                <td class="text-center">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                                                                </td>
                                                            </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            {{-- Data Anak --}}
                                            <div>
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <span class="font-weight-bold text-dark" style="font-size:13px;"><i class="fas fa-baby mr-1 text-warning"></i> Data Anak</span>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" id="btnAddAnak">
                                                        <i class="fas fa-plus mr-1"></i> Tambah Anak
                                                    </button>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered" id="tableAnak" style="font-size:12px;">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th width="26%">Nama Anak</th>
                                                                <th width="10%">L/P</th>
                                                                <th width="18%">Tempat Lahir</th>
                                                                <th width="16%">Tgl Lahir</th>
                                                                <th width="14%">Pendidikan</th>
                                                                <th width="10%">Status</th>
                                                                <th width="6%">Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="anakTbody">
                                                            @php $anakRows = !empty($dataAnak) ? $dataAnak : []; @endphp
                                                            @forelse($anakRows as $i => $a)
                                                            <tr class="anak-row">
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[{{ $i }}][nama]" value="{{ $a['nama'] ?? '' }}" placeholder="Nama"></td>
                                                                <td>
                                                                    <select class="form-control form-control-sm" name="data_anak[{{ $i }}][gender]">
                                                                        <option value="L" {{ ($a['gender'] ?? ($a['jk'] ?? '')) == 'L' ? 'selected' : '' }}>L</option>
                                                                        <option value="P" {{ ($a['gender'] ?? ($a['jk'] ?? '')) == 'P' ? 'selected' : '' }}>P</option>
                                                                    </select>
                                                                </td>
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[{{ $i }}][tempat_lahir]" value="{{ $a['tempat_lahir'] ?? '' }}" placeholder="Tempat Lahir"></td>
                                                                <td><input type="date" class="form-control form-control-sm" name="data_anak[{{ $i }}][tgl_lahir]" value="{{ $a['tgl_lahir'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[{{ $i }}][pendidikan]" value="{{ $a['pendidikan'] ?? '' }}" placeholder="Pendidikan"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[{{ $i }}][status]" value="{{ $a['status'] ?? '' }}" placeholder="Kandung/Tiri"></td>
                                                                <td class="text-center">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                                                                </td>
                                                            </tr>
                                                            @empty
                                                            <tr class="anak-row">
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[0][nama]" placeholder="Nama"></td>
                                                                <td>
                                                                    <select class="form-control form-control-sm" name="data_anak[0][gender]">
                                                                        <option value="L">L</option>
                                                                        <option value="P">P</option>
                                                                    </select>
                                                                </td>
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[0][tempat_lahir]" placeholder="Tempat Lahir"></td>
                                                                <td><input type="date" class="form-control form-control-sm" name="data_anak[0][tgl_lahir]"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[0][pendidikan]" placeholder="Pendidikan"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[0][status]" placeholder="Status"></td>
                                                                <td class="text-center">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                                                                </td>
                                                            </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- ── 7. UPLOAD DOKUMEN ── --}}
                                        <div class="tab-pane fade" id="esub-dokumen">
                                            <div class="row">
                                                @php
                                                $docInputs = [
                                                    ['name'=>'file_surat_lamaran','label'=>'Surat Lamaran'],
                                                    ['name'=>'file_cv','label'=>'Curriculum Vitae (CV)'],
                                                    ['name'=>'file_ktp','label'=>'KTP / Identitas'],
                                                    ['name'=>'file_kk','label'=>'Kartu Keluarga (KK)'],
                                                    ['name'=>'file_ijasah','label'=>'Ijazah Terakhir'],
                                                    ['name'=>'file_akta_kelahiran','label'=>'Akta Kelahiran'],
                                                    ['name'=>'file_skck','label'=>'SKCK Kepolisian'],
                                                    ['name'=>'file_surat_sehat','label'=>'Surat Sehat'],
                                                    ['name'=>'file_pas_foto','label'=>'Pas Foto'],
                                                ];
                                                @endphp
                                                @foreach($docInputs as $doc)
                                                @php $sfKey = str_replace('file_','',$doc['name']); if($sfKey=='ijasah') $sfKey='ijazah'; @endphp
                                                <div class="col-md-6 mb-3">
                                                    <div class="p-2.5 rounded bg-light border-0">
                                                        <label class="small font-weight-bold mb-1">{{ $doc['label'] }}</label>
                                                        @if(isset($softFiles[$sfKey]) && $softFiles[$sfKey]['hasFile'])
                                                            <div class="mb-1">
                                                                <a href="{{ $softFiles[$sfKey]['url'] }}" target="_blank" class="badge badge-success px-2 py-1">
                                                                    <i class="fas fa-check-circle mr-1"></i>File Tersedia &bull; Lihat
                                                                </a>
                                                            </div>
                                                        @else
                                                            <div class="mb-1"><span class="badge badge-light border text-muted px-2 py-1"><i class="fas fa-times-circle mr-1"></i>Belum ada berkas</span></div>
                                                        @endif
                                                        <input type="file" class="form-control-file" name="{{ $doc['name'] }}">
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>

                                    </div>{{-- /tab-content --}}

                                    <hr class="mt-4">
                                    <div class="d-flex justify-content-end" style="gap:8px;">
                                        <a href="/biodata/index" class="btn btn-outline-secondary">Batal</a>
                                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                            <i class="fas fa-save mr-1"></i> Simpan Perubahan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>{{-- /pane-edit --}}

                    {{-- ══════════════════════════════════════════ --}}
                    {{-- TAB 3: BERKAS DOKUMEN                      --}}
                    {{-- ══════════════════════════════════════════ --}}
                    <div class="tab-pane fade" id="pane-dokumen" role="tabpanel">
                        <div class="row">
                            @php
                            $docDefs = [
                                'surat_lamaran' => ['label'=>'Surat Lamaran','icon'=>'fa-envelope','color'=>'#4e73df'],
                                'cv'            => ['label'=>'Curriculum Vitae (CV)','icon'=>'fa-file-alt','color'=>'#36b9cc'],
                                'ktp'           => ['label'=>'KTP / Identitas','icon'=>'fa-id-card','color'=>'#1cc88a'],
                                'kk'            => ['label'=>'Kartu Keluarga (KK)','icon'=>'fa-home','color'=>'#f6c23e'],
                                'ijazah'        => ['label'=>'Ijazah Terakhir','icon'=>'fa-graduation-cap','color'=>'#6f42c1'],
                                'akta_kelahiran'=> ['label'=>'Akta Kelahiran','icon'=>'fa-baby','color'=>'#e83e8c'],
                                'skck'          => ['label'=>'SKCK Kepolisian','icon'=>'fa-shield-alt','color'=>'#20c997'],
                                'surat_sehat'   => ['label'=>'Surat Keterangan Sehat','icon'=>'fa-heartbeat','color'=>'#e74a3b'],
                                'pas_foto'      => ['label'=>'Pas Foto Berkas','icon'=>'fa-camera','color'=>'#858796'],
                            ];
                            @endphp
                            @foreach($docDefs as $key => $def)
                            @php $sf = $softFiles[$key] ?? null; $has = $sf && $sf['hasFile']; @endphp
                            <div class="col-md-4 col-sm-6 mb-4">
                                <div class="card border-0 shadow-sm h-100" style="border-radius:12px; border-left: 4px solid {{ $has ? $def['color'] : '#e3e6f0' }} !important;">
                                    <div class="card-body d-flex align-items-center" style="gap:14px;">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                             style="width:45px;height:45px;background:{{ $has ? $def['color'].'18' : '#f8f9fc' }};">
                                            <i class="fas {{ $def['icon'] }}" style="color:{{ $has ? $def['color'] : '#b7b9cc' }};font-size:18px;"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="font-weight-bold text-dark" style="font-size:13px;">{{ $def['label'] }}</div>
                                            @if($has)
                                                <span class="badge badge-success" style="font-size:10px;">Tersedia</span>
                                                <div class="mt-1" style="gap:6px;display:flex;">
                                                    <a href="{{ $sf['url'] }}" target="_blank" class="btn btn-xs btn-outline-primary" style="font-size:11px;padding:2px 8px;">
                                                        <i class="fas fa-eye mr-1"></i>Lihat
                                                    </a>
                                                    <a href="{{ $sf['url'] }}" download class="btn btn-xs btn-outline-secondary" style="font-size:11px;padding:2px 8px;">
                                                        <i class="fas fa-download mr-1"></i>Unduh
                                                    </a>
                                                </div>
                                            @else
                                                <span class="badge badge-light border text-muted" style="font-size:10px;">Belum Ada</span>
                                                <div class="mt-1">
                                                    <a href="#pane-edit" class="text-primary small" style="font-size:11px;"
                                                       onclick="$('#tab-edit').tab('show');setTimeout(()=>$('#editSubTabs a[href=\'#esub-dokumen\']').tab('show'),150);">
                                                        <i class="fas fa-upload mr-1"></i>Unggah Sekarang
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        {{-- Progress Bar Kelengkapan Dokumen --}}
                        @php
                        $docTotal = count($docDefs);
                        $docHas = collect($softFiles)->filter(fn($sf) => $sf['hasFile'])->count();
                        $docPct = $docTotal > 0 ? round($docHas/$docTotal*100) : 0;
                        @endphp
                        <div class="card border-0 shadow-sm" style="border-radius:12px;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="font-weight-bold text-dark small">Kelengkapan Dokumen Berkas</span>
                                    <span class="text-muted small font-weight-bold">{{ $docHas }} dari {{ $docTotal }} Berkas</span>
                                </div>
                                <div class="progress" style="height:8px;border-radius:6px;">
                                    <div class="progress-bar {{ $docPct==100 ? 'bg-success' : ($docPct>=50 ? 'bg-primary' : 'bg-warning') }}"
                                         role="progressbar" style="width:{{ $docPct }}%;border-radius:6px;" aria-valuenow="{{ $docPct }}">
                                    </div>
                                </div>
                                <small class="text-muted mt-1 d-block">{{ $docPct }}% dokumen telah terunggah</small>
                            </div>
                        </div>
                    </div>{{-- /pane-dokumen --}}

                    {{-- ══════════════════════════════════════════ --}}
                    {{-- TAB 4: EXIT KARYAWAN                       --}}
                    {{-- ══════════════════════════════════════════ --}}
                    <div class="tab-pane fade" id="pane-exit" role="tabpanel">
                        <div class="row justify-content-center">
                            <div class="col-lg-7">

                                {{-- Peringatan --}}
                                <div class="alert border-0 mb-4 d-flex align-items-start shadow-sm" style="background:#fff8e6;border-left:4px solid #f6c23e !important;border-radius:10px;">
                                    <i class="fas fa-exclamation-triangle text-warning mt-1 mr-3" style="font-size:18px;"></i>
                                    <div>
                                        <div class="font-weight-bold text-dark" style="font-size:13.5px;">Peringatan Tindakan Exit</div>
                                        <div class="text-muted small mt-1">
                                            Proses exit akan menonaktifkan status karyawan <strong>{{ $pkwt->NAMA }}</strong> (NPK: {{ $pkwt->NPK }})
                                            dan mengisi tanggal keluar (TKK). Pastikan data telah dikonfirmasi sebelum melanjutkan.
                                        </div>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm" style="border-radius:12px; border-top: 4px solid #e74a3b !important;">
                                    <div class="card-header bg-white border-0 pt-3 pb-0">
                                        <h6 class="font-weight-bold text-danger mb-0">
                                            <i class="fas fa-sign-out-alt mr-2"></i>Formulir Exit Karyawan
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="small font-weight-bold">Tanggal Keluar (TKK) <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="exitTkk" value="{{ now()->format('Y-m-d') }}">
                                        </div>
                                        <div class="mb-3">
                                            <label class="small font-weight-bold">Status Keluar <span class="text-danger">*</span></label>
                                            <select class="form-control" id="exitStatus">
                                                <option value="">-- Pilih Status Keluar --</option>
                                                <option value="HABIS">HABIS KONTRAK</option>
                                                <option value="PENGUNDURAN DIRI">PENGUNDURAN DIRI</option>
                                                <option value="DIPUTUS">DIPUTUS</option>
                                                <option value="MENINGGAL">MENINGGAL</option>
                                            </select>
                                        </div>
                                        <div class="mb-4">
                                            <label class="small font-weight-bold">Alasan / Catatan Keluar</label>
                                            <textarea class="form-control" id="exitAlasan" rows="3" placeholder="Keterangan tambahan alasan karyawan keluar..."></textarea>
                                        </div>
                                        <div class="d-flex justify-content-end" style="gap:8px;">
                                            <a href="/biodata/index" class="btn btn-outline-secondary">Batal</a>
                                            <button type="button" id="btnExitSubmit" class="btn btn-danger px-4 shadow-sm">
                                                <i class="fas fa-sign-out-alt mr-1"></i> Proses Exit
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>{{-- /pane-exit --}}

                </div>{{-- /tab-content --}}

            </div>{{-- /container-fluid --}}
        </div>{{-- /content --}}

        @include('layout.footer')
    </div>{{-- /content-wrapper --}}
</div>{{-- /wrapper --}}

<style>
/* Custom segmented tab bar */
.custom-manage-tabs {
    background: #eaecf4;
    padding: 5px;
    border-radius: 10px;
    gap: 4px;
}
.custom-manage-tabs .nav-link {
    color: #4e5564;
    border-radius: 8px;
    padding: 8px 18px;
    font-size: 13.5px;
    transition: all 0.2s;
}
.custom-manage-tabs .nav-link:hover {
    background: rgba(255,255,255,0.7);
    color: #2e384d;
}
.custom-manage-tabs .nav-link.active {
    background: #fff;
    color: #4e73df !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}
.custom-manage-tabs .nav-link.text-danger.active {
    background: #fff;
    color: #e74a3b !important;
    box-shadow: 0 2px 6px rgba(231,74,59,0.15);
}

.font-weight-semibold { font-weight: 600; }
.card { transition: box-shadow 0.2s; }
.btn-xs { padding: 2px 7px; font-size: 11px; }
</style>

<script>
$(function () {

    // ── Instant Profile Photo Upload ──────────────────────────────
    $('#heroPhotoInput').on('change', function () {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) { $('#heroPhoto').attr('src', e.target.result); };
        reader.readAsDataURL(file);

        var formData = new FormData($('#photoUploadForm')[0]);
        $.ajax({
            url: $('#photoUploadForm').attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Foto profil berhasil diperbarui',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
            error: function (xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: xhr.responseJSON?.message || 'Gagal mengunggah foto profil'
                });
            }
        });
    });

    // ── Edit Form Photo Preview ─────────────────────────────────
    $('#edit_fotoInput').on('change', function () {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) { $('#edit_previewImage').attr('src', e.target.result); };
        reader.readAsDataURL(file);
    });

    // ── Sinkronkan nama ibu kandung ke hidden data_ibu[nama] ───
    $('input[name="ibu"]').on('input', function () {
        $('#hiddenDataIbuNama').val($(this).val());
    });

    // ── Dynamic Row: Hapus Baris ────────────────────────────────
    $(document).on('click', '.btn-remove-row', function () {
        var $tbody = $(this).closest('tbody');
        if ($tbody.children('tr').length > 1) {
            $(this).closest('tr').remove();
        } else {
            // Jika tinggal 1 baris, kosongkan valuenya
            $(this).closest('tr').find('input').val('');
        }
    });

    // ── Dynamic Row: Tambah Pendidikan ──────────────────────────
    $('#btnAddEdu').on('click', function () {
        var idx = $('#eduTbody tr').length;
        var row = `
            <tr class="edu-row">
                <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[${idx}][tingkat]" placeholder="SMA/SMK/D3/S1"></td>
                <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[${idx}][institusi]" placeholder="Nama Sekolah / Kampus"></td>
                <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[${idx}][jurusan]" placeholder="Jurusan"></td>
                <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[${idx}][dari]" placeholder="Tahun Masuk"></td>
                <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[${idx}][sampai]" placeholder="Tahun Lulus"></td>
                <td>
                    <select class="form-control form-control-sm" name="riwayat_pendidikan[${idx}][lulus]">
                        <option value="1">Lulus</option>
                        <option value="0">Belum Lulus</option>
                    </select>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        $('#eduTbody').append(row);
    });

    // ── Dynamic Row: Tambah Pengalaman Kerja ────────────────────
    $('#btnAddExp').on('click', function () {
        var idx = $('#expTbody tr').length;
        var row = `
            <tr class="exp-row">
                <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${idx}][perusahaan]" placeholder="Nama Perusahaan"></td>
                <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${idx}][jabatan]" placeholder="Jabatan"></td>
                <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${idx}][departemen]" placeholder="Departemen"></td>
                <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${idx}][dari]" placeholder="Dari"></td>
                <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${idx}][sampai]" placeholder="Sampai"></td>
                <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${idx}][alasan_keluar]" placeholder="Alasan Keluar"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        $('#expTbody').append(row);
    });

    // ── Dynamic Row: Tambah Saudara ─────────────────────────────
    $('#btnAddSaudara').on('click', function () {
        var idx = $('#saudaraTbody tr').length;
        var row = `
            <tr class="saudara-row">
                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[${idx}][nama]" placeholder="Nama"></td>
                <td>
                    <select class="form-control form-control-sm" name="saudara_kandung[${idx}][gender]">
                        <option value="L">L</option>
                        <option value="P">P</option>
                    </select>
                </td>
                <td><input type="date" class="form-control form-control-sm" name="saudara_kandung[${idx}][tgl_lahir]"></td>
                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[${idx}][pendidikan]" placeholder="Pendidikan"></td>
                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[${idx}][pekerjaan]" placeholder="Pekerjaan"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        $('#saudaraTbody').append(row);
    });

    // ── Dynamic Row: Tambah Anak ────────────────────────────────
    $('#btnAddAnak').on('click', function () {
        var idx = $('#anakTbody tr').length;
        var row = `
            <tr class="anak-row">
                <td><input type="text" class="form-control form-control-sm" name="data_anak[${idx}][nama]" placeholder="Nama"></td>
                <td>
                    <select class="form-control form-control-sm" name="data_anak[${idx}][gender]">
                        <option value="L">L</option>
                        <option value="P">P</option>
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm" name="data_anak[${idx}][tempat_lahir]" placeholder="Tempat Lahir"></td>
                <td><input type="date" class="form-control form-control-sm" name="data_anak[${idx}][tgl_lahir]"></td>
                <td><input type="text" class="form-control form-control-sm" name="data_anak[${idx}][pendidikan]" placeholder="Pendidikan"></td>
                <td><input type="text" class="form-control form-control-sm" name="data_anak[${idx}][status]" placeholder="Status"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        $('#anakTbody').append(row);
    });

    // ── Exit Karyawan Submission ────────────────────────────────
    $('#btnExitSubmit').on('click', function () {
        var tkk    = $('#exitTkk').val();
        var status = $('#exitStatus').val();
        var alasan = $('#exitAlasan').val();

        if (!tkk) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Tanggal keluar (TKK) wajib diisi!' });
            return;
        }
        if (!status) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Status keluar wajib dipilih!' });
            return;
        }

        Swal.fire({
            title: 'Konfirmasi Exit Karyawan',
            html: `Apakah Anda yakin memproses exit untuk <strong>{{ $pkwt->NAMA }}</strong> (NPK: {{ $pkwt->NPK }})?<br><small class="text-muted">Tindakan ini akan menonaktifkan karyawan.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: 'Ya, Proses Exit',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $('#btnExitSubmit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');

            $.ajax({
                url: '/biodata/manage/exit/{{ $pkwt->NPK }}',
                method: 'GET',
                data: { tkk: tkk, status_keluar: status, leave_reasons: alasan },
                success: function (res) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Karyawan telah berhasil di-exit.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(function () {
                        window.location.href = '/biodata/index';
                    });
                },
                error: function (xhr) {
                    $('#btnExitSubmit').prop('disabled', false).html('<i class="fas fa-sign-out-alt mr-1"></i> Proses Exit');
                    Swal.fire({ icon: 'error', title: 'Gagal', text: xhr.responseJSON?.message || 'Terjadi kesalahan saat memproses exit.' });
                }
            });
        });
    });

    // ── Deep-link query param ?tab=edit|exit|dokumen ────────────
    var params = new URLSearchParams(window.location.search);
    var tabParam = params.get('tab');
    if (tabParam) {
        var tabMap = { edit: '#tab-edit', exit: '#tab-exit', dokumen: '#tab-dokumen' };
        if (tabMap[tabParam]) $(tabMap[tabParam]).tab('show');
    }
});
</script>

</html>
