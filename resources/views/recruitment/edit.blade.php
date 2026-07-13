<!DOCTYPE html>
<html lang="en">

@include('layout.header')
<style>
    .page-header-orange {
        background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        padding: 24px;
        border-radius: 12px;
        color: #fff;
        margin-bottom: 24px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        position: relative;
        overflow: hidden;
    }

    .page-header-orange::after {
        content: '';
        position: absolute;
        right: -20px;
        top: -50px;
        width: 150px;
        height: 150px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
    }

    .sec-card-edit {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }

    .sec-card-edit-body {
        padding: 20px;
    }

    .form-group label {
        font-size: 13px;
        font-weight: 700 !important;
        color: #1e293b;
    }

    .form-control, .custom-select {
        font-size: 14px;
        border-radius: 6px;
    }

    .doc-existing {
        font-size: 12px;
        padding: 4px 8px;
        border-radius: 4px;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #334155;
        margin-top: 4px;
        text-decoration: none;
    }

    .doc-existing:hover {
        background: #e2e8f0;
        text-decoration: none;
        color: #0f172a;
    }

    /* ---- Tabbed edit UI ---- */
    .edit-tabs-shell {
        display: flex;
        gap: 20px;
        align-items: flex-start;
    }

    .edit-tabs-nav {
        flex: 0 0 220px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        overflow: hidden;
        position: sticky;
        top: 20px;
    }

    .edit-tabs-nav .nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 13px 16px;
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        border-left: 3px solid transparent;
        border-radius: 0;
        white-space: normal;
    }

    .edit-tabs-nav .nav-link i {
        width: 18px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }

    .edit-tabs-nav .nav-link:hover:not(.active) {
        background: #f8fafc;
        color: #1e293b;
    }

    .edit-tabs-nav .nav-link.active {
        background: #fff7ed;
        color: #ea580c;
        border-left-color: #ea580c;
    }

    .edit-tabs-nav .nav-link.active i {
        color: #ea580c;
    }

    .edit-tabs-nav .nav-link .step-badge {
        margin-left: auto;
        font-size: 10px;
        font-weight: 700;
        color: #cbd5e1;
    }

    .edit-tabs-nav .nav-link.active .step-badge {
        color: #fdba74;
    }

    .edit-tabs-content {
        flex: 1 1 auto;
        min-width: 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    }

    .edit-tabs-content .tab-pane-hd {
        padding: 16px 24px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .edit-tabs-content .tab-pane-body {
        padding: 24px;
    }

    .tab-pane-nav-btns {
        display: flex;
        justify-content: space-between;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 1px dashed #e2e8f0;
    }

    .tab-pane-nav-btns .btn-tab-nav {
        font-size: 13px;
        font-weight: 700;
        padding: 8px 16px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #475569;
    }

    .tab-pane-nav-btns .btn-tab-nav:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .tab-pane-nav-btns .btn-tab-nav.btn-tab-next {
        border-color: #ea580c;
        color: #ea580c;
    }

    .tab-pane-nav-btns .btn-tab-nav.btn-tab-next:hover {
        background: #ea580c;
        color: #fff;
    }

    @media (max-width: 991px) {
        .edit-tabs-shell {
            flex-direction: column;
        }

        .edit-tabs-nav {
            flex: 1 1 auto;
            width: 100%;
            position: static;
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
        }

        .edit-tabs-nav .nav-link {
            border-left: none;
            border-bottom: 3px solid transparent;
            flex: 0 0 auto;
        }

        .edit-tabs-nav .nav-link.active {
            border-left-color: transparent;
            border-bottom-color: #ea580c;
        }
    }
</style>

<body id="page-top">

    @include('sweetalert::alert')

    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')

                <div class="container-fluid pb-5">

                    <div class="page-header-orange d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-1 font-weight-bold">
                                <i class="fas fa-edit mr-2"></i>Edit Data Pelamar
                            </h1>
                            <p class="mb-0" style="opacity:0.9;">Update informasi untuk pelamar <strong>{{ $pelamar->NAMA }}</strong> ({{ $pelamar->NIK ?? '-' }})</p>
                        </div>
                        <a href="{{ route('recruitment.index') }}" class="btn btn-light btn-sm font-weight-bold text-orange shadow-sm px-3 rounded-pill" style="color: #ea580c;">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali ke List
                        </a>
                    </div>

                    <form action="{{ route('recruitment.update', $pelamar->id) }}" method="POST" enctype="multipart/form-data" id="formEditPelamar">
                        @csrf
                        @method('PUT')

                        <div class="edit-tabs-shell">

                            {{-- Sidebar Tab Nav --}}
                            <div class="edit-tabs-nav nav flex-column" role="tablist" aria-orientation="vertical" id="editPelamarTabs">
                                <a class="nav-link active" id="nav-pribadi-tab" data-toggle="tab" href="#tab-pribadi" role="tab" aria-controls="tab-pribadi" aria-selected="true">
                                    <i class="fas fa-id-card"></i> Data Pribadi <span class="step-badge">01</span>
                                </a>
                                <a class="nav-link" id="nav-kontak-tab" data-toggle="tab" href="#tab-kontak" role="tab" aria-controls="tab-kontak" aria-selected="false">
                                    <i class="fas fa-map-marker-alt"></i> Kontak & Alamat <span class="step-badge">02</span>
                                </a>
                                <a class="nav-link" id="nav-pekerjaan-tab" data-toggle="tab" href="#tab-pekerjaan" role="tab" aria-controls="tab-pekerjaan" aria-selected="false">
                                    <i class="fas fa-briefcase"></i> Pekerjaan <span class="step-badge">03</span>
                                </a>
                                <a class="nav-link" id="nav-fisik-tab" data-toggle="tab" href="#tab-fisik" role="tab" aria-controls="tab-fisik" aria-selected="false">
                                    <i class="fas fa-graduation-cap"></i> Pendidikan & Fisik <span class="step-badge">04</span>
                                </a>
                                <a class="nav-link" id="nav-keluarga-tab" data-toggle="tab" href="#tab-keluarga" role="tab" aria-controls="tab-keluarga" aria-selected="false">
                                    <i class="fas fa-users"></i> Data Keluarga <span class="step-badge">05</span>
                                </a>
                                <a class="nav-link" id="nav-riwayatdidik-tab" data-toggle="tab" href="#tab-riwayatdidik" role="tab" aria-controls="tab-riwayatdidik" aria-selected="false">
                                    <i class="fas fa-university"></i> Riwayat Pendidikan <span class="step-badge">06</span>
                                </a>
                                <a class="nav-link" id="nav-pengalaman-tab" data-toggle="tab" href="#tab-pengalaman" role="tab" aria-controls="tab-pengalaman" aria-selected="false">
                                    <i class="fas fa-building"></i> Pengalaman Kerja <span class="step-badge">07</span>
                                </a>
                                <a class="nav-link" id="nav-motivasi-tab" data-toggle="tab" href="#tab-motivasi" role="tab" aria-controls="tab-motivasi" aria-selected="false">
                                    <i class="fas fa-comment-dots"></i> Motivasi & Ekstra <span class="step-badge">08</span>
                                </a>
                                <a class="nav-link" id="nav-dokumen-tab" data-toggle="tab" href="#tab-dokumen" role="tab" aria-controls="tab-dokumen" aria-selected="false">
                                    <i class="fas fa-folder-open"></i> File Dokumen <span class="step-badge">09</span>
                                </a>
                            </div>

                            {{-- Tab Content --}}
                            <div class="edit-tabs-content">
                                <div class="tab-content" id="editPelamarTabsContent">

                                    {{-- Data Pribadi --}}
                                    <div class="tab-pane fade show active" id="tab-pribadi" role="tabpanel" aria-labelledby="nav-pribadi-tab">
                                        <div class="tab-pane-hd"><i class="fas fa-id-card text-primary"></i> Data Pribadi</div>
                                        <div class="tab-pane-body">
                                            <div class="row">
                                                <div class="col-md-4 form-group">
                                                    <label>Nama Lengkap <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="nama_lengkap" value="{{ old('nama_lengkap', $pelamar->NAMA) }}" required>
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>NIK <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="nik" value="{{ old('nik', $pelamar->NIK) }}" required>
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>No. KK <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="no_kk" value="{{ old('no_kk', $pelamar->NO_KK) }}" required>
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Jenis Kelamin</label>
                                                    <select class="form-control custom-select" name="jenis_kelamin">
                                                        <option value="L" {{ old('jenis_kelamin', $pelamar->JENIS_KELAMIN) === 'L' ? 'selected' : '' }}>Laki-laki</option>
                                                        <option value="P" {{ old('jenis_kelamin', $pelamar->JENIS_KELAMIN) === 'P' ? 'selected' : '' }}>Perempuan</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Tempat Lahir</label>
                                                    <input type="text" class="form-control" name="tempat_lahir" value="{{ old('tempat_lahir', $pelamar->TMPT_LAHIR) }}">
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Tanggal Lahir</label>
                                                    <input type="date" class="form-control" name="tanggal_lahir" value="{{ old('tanggal_lahir', $pelamar->TGL_LAHIR ? \Carbon\Carbon::parse($pelamar->TGL_LAHIR)->format('Y-m-d') : '') }}">
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Warga Negara</label>
                                                    <select class="form-control custom-select" name="warga_negara">
                                                        <option value="WNI" {{ old('warga_negara', $pelamar->warga_negara) === 'WNI' ? 'selected' : '' }}>WNI</option>
                                                        <option value="WNA" {{ old('warga_negara', $pelamar->warga_negara) === 'WNA' ? 'selected' : '' }}>WNA</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Agama</label>
                                                    <select class="form-control custom-select" name="agama">
                                                        @foreach(['Islam','Kristen','Katolik','Hindu','Buddha','Khonghucu'] as $agm)
                                                            <option value="{{ strtoupper($agm) }}" {{ old('agama', $pelamar->AGAMA) === strtoupper($agm) ? 'selected' : '' }}>{{ $agm }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Status Pernikahan</label>
                                                    <select class="form-control custom-select" name="status_pernikahan">
                                                        @foreach(['Belum Kawin','Kawin','Cerai Hidup','Cerai Mati'] as $sts)
                                                            <option value="{{ $sts }}" {{ old('status_pernikahan', $pelamar->STATUS) === $sts ? 'selected' : '' }}>{{ $sts }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-2 form-group">
                                                    <label>Tanggungan</label>
                                                    <input type="number" class="form-control" name="tanggungan" value="{{ old('tanggungan', $pelamar->TANGGUNGAN) }}">
                                                </div>
                                                <div class="col-md-2 form-group">
                                                    <label>Ikut KB</label>
                                                    <select class="form-control custom-select" name="kb">
                                                        <option value="Tidak" {{ old('kb', $pelamar->ikut_kb) == 0 ? 'selected' : '' }}>Tidak</option>
                                                        <option value="Ya" {{ old('kb', $pelamar->ikut_kb) == 1 ? 'selected' : '' }}>Ya</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2 form-group">
                                                    <label>No. SIM</label>
                                                    <input type="text" class="form-control" name="sim" value="{{ old('sim', $pelamar->nomor_sim) }}">
                                                </div>
                                            </div>
                                            <div class="tab-pane-nav-btns">
                                                <span></span>
                                                <button type="button" class="btn-tab-nav btn-tab-next" onclick="gotoTab('nav-kontak-tab')">Selanjutnya: Kontak & Alamat <i class="fas fa-arrow-right ml-1"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Kontak & Alamat --}}
                                    <div class="tab-pane fade" id="tab-kontak" role="tabpanel" aria-labelledby="nav-kontak-tab">
                                        <div class="tab-pane-hd"><i class="fas fa-map-marker-alt text-success"></i> Kontak & Alamat</div>
                                        <div class="tab-pane-body">
                                            <div class="row">
                                                <div class="col-md-4 form-group">
                                                    <label>Nomor HP</label>
                                                    <input type="text" class="form-control" name="nomor_hp" value="{{ old('nomor_hp', $pelamar->HP) }}">
                                                </div>
                                                <div class="col-md-12"></div>
                                                <div class="col-md-6 form-group">
                                                    <label>Alamat Asal (KTP)</label>
                                                    <textarea class="form-control" name="alamat_asal" rows="2">{{ old('alamat_asal', $pelamar->ALAMAT_LENGKAP) }}</textarea>
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Kab/Kota Asal</label>
                                                    <input type="text" class="form-control" name="kab_kota_asal" value="{{ old('kab_kota_asal', $pelamar->KABUPATEN) }}">
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Status Domisili Asal</label>
                                                    <select class="form-control custom-select" name="status_domisili_asal">
                                                        <option value="">- Pilih -</option>
                                                        @foreach(['Milik Sendiri','Sewa/Kontrak','Ikut Orang Tua'] as $dom)
                                                            <option value="{{ strtoupper($dom) }}" {{ old('status_domisili_asal', $pelamar->ALAMAT_DOMISILI) === strtoupper($dom) ? 'selected' : '' }}>{{ $dom }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="col-md-6 form-group">
                                                    <label>Alamat Sekarang</label>
                                                    <textarea class="form-control" name="alamat_sekarang" rows="2">{{ old('alamat_sekarang', $pelamar->alamat_skrg) }}</textarea>
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Kab/Kota Sekarang</label>
                                                    <input type="text" class="form-control" name="kab_kota_sekarang" value="{{ old('kab_kota_sekarang', $pelamar->kabupaten_kota_skrg) }}">
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Status Domisili Sekrg</label>
                                                    <select class="form-control custom-select" name="status_domisili_sekarang">
                                                        <option value="">- Pilih -</option>
                                                        @foreach(['Milik Sendiri','Sewa/Kontrak','Ikut Orang Tua'] as $dom)
                                                            <option value="{{ strtoupper($dom) }}" {{ old('status_domisili_sekarang', strtoupper($pelamar->status_domisili ?? '')) === strtoupper($dom) ? 'selected' : '' }}>{{ $dom }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="col-md-12 mt-2 mb-2"><strong class="text-muted" style="font-size:13px;">Kontak Darurat</strong></div>
                                                <div class="col-md-4 form-group">
                                                    <label>Nama Kontak</label>
                                                    <input type="text" class="form-control" name="nama_darurat" value="{{ old('nama_darurat', $pelamar->nama_ktk_darurat) }}">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Hubungan</label>
                                                    <input type="text" class="form-control" name="hubungan_darurat" value="{{ old('hubungan_darurat', $pelamar->hubungan) }}">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>No. Telp Darurat</label>
                                                    <input type="text" class="form-control" name="no_telepon_darurat" value="{{ old('no_telepon_darurat', $pelamar->no_telp_darurat) }}">
                                                </div>
                                            </div>
                                            <div class="tab-pane-nav-btns">
                                                <button type="button" class="btn-tab-nav" onclick="gotoTab('nav-pribadi-tab')"><i class="fas fa-arrow-left mr-1"></i> Sebelumnya</button>
                                                <button type="button" class="btn-tab-nav btn-tab-next" onclick="gotoTab('nav-pekerjaan-tab')">Selanjutnya: Pekerjaan <i class="fas fa-arrow-right ml-1"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Pekerjaan & Tambahan --}}
                                    <div class="tab-pane fade" id="tab-pekerjaan" role="tabpanel" aria-labelledby="nav-pekerjaan-tab">
                                        <div class="tab-pane-hd"><i class="fas fa-briefcase text-info"></i> Pekerjaan & Info Tambahan</div>
                                        <div class="tab-pane-body">
                                            <div class="row">
                                                <div class="col-md-4 form-group">
                                                    <label>Jabatan Dilamar</label>
                                                    <input type="text" class="form-control" name="jabatan" value="{{ old('jabatan', $pelamar->jabatan) }}">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Departemen</label>
                                                    <input type="text" class="form-control" name="department" value="{{ old('department', $pelamar->department) }}">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Transportasi</label>
                                                    <input type="text" class="form-control" name="transportasi" value="{{ old('transportasi', $pelamar->mode_transportasi) }}">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>No. BPJS Tenaga Kerja</label>
                                                    <input type="text" class="form-control" name="bpjs_tk" value="{{ old('bpjs_tk', $pelamar->bpjs_tk) }}">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>No. BPJS Kesehatan</label>
                                                    <input type="text" class="form-control" name="bpjs_kes" value="{{ old('bpjs_kes', $pelamar->bpjs_kes) }}">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Bakat / Hobby</label>
                                                    <input type="text" class="form-control" name="hobby" value="{{ old('hobby', $pelamar->bakat_hobby) }}">
                                                </div>
                                            </div>
                                            <div class="tab-pane-nav-btns">
                                                <button type="button" class="btn-tab-nav" onclick="gotoTab('nav-kontak-tab')"><i class="fas fa-arrow-left mr-1"></i> Sebelumnya</button>
                                                <button type="button" class="btn-tab-nav btn-tab-next" onclick="gotoTab('nav-fisik-tab')">Selanjutnya: Pendidikan & Fisik <i class="fas fa-arrow-right ml-1"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Pendidikan & Fisik --}}
                                    <div class="tab-pane fade" id="tab-fisik" role="tabpanel" aria-labelledby="nav-fisik-tab">
                                        <div class="tab-pane-hd"><i class="fas fa-graduation-cap text-warning"></i> Pendidikan & Fisik</div>
                                        <div class="tab-pane-body">
                                            <div class="row">
                                                <div class="col-md-3 form-group">
                                                    <label>Pendidikan Terakhir</label>
                                                    <select class="form-control custom-select" name="pendidikan">
                                                        @foreach(['SD','SMP','SMA/SMK','D3','S1','S2','S3'] as $edu)
                                                            <option value="{{ $edu }}" {{ old('pendidikan', strtoupper($pelamar->PENDIDIKAN ?? '')) === $edu ? 'selected' : '' }}>{{ $edu }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Jurusan</label>
                                                    <input type="text" class="form-control" name="jurusan" value="{{ old('jurusan', $pelamar->JURUSAN) }}">
                                                </div>
                                                <div class="col-md-6 form-group">
                                                    <label>Nama Sekolah / Institusi</label>
                                                    <input type="text" class="form-control" name="nama_sekolah" value="{{ old('nama_sekolah', $pelamar->NAMA_SEKOLAH) }}">
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Tinggi Badan (cm)</label>
                                                    <input type="number" class="form-control" name="tinggi_badan" value="{{ old('tinggi_badan', $pelamar->TINGGI_BADAN) }}">
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Berat Badan (kg)</label>
                                                    <input type="number" class="form-control" name="berat_badan" value="{{ old('berat_badan', $pelamar->BERAT_BADAN) }}">
                                                </div>
                                            </div>
                                            <div class="tab-pane-nav-btns">
                                                <button type="button" class="btn-tab-nav" onclick="gotoTab('nav-pekerjaan-tab')"><i class="fas fa-arrow-left mr-1"></i> Sebelumnya</button>
                                                <button type="button" class="btn-tab-nav btn-tab-next" onclick="gotoTab('nav-keluarga-tab')">Selanjutnya: Data Keluarga <i class="fas fa-arrow-right ml-1"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Data Keluarga --}}
                                    <div class="tab-pane fade" id="tab-keluarga" role="tabpanel" aria-labelledby="nav-keluarga-tab">
                                        <div class="tab-pane-hd"><i class="fas fa-users text-primary"></i> Data Keluarga</div>
                                        <div class="tab-pane-body">
                                            <h6>Data Orang Tua</h6>
                                            <div class="row mb-3">
                                                <div class="col-md-6 border-right">
                                                    <strong>Ayah</strong>
                                                    <div class="form-group mt-2">
                                                        <label>Nama</label>
                                                        <input type="text" class="form-control" name="data_ayah[nama]" value="{{ old('data_ayah.nama', $pelamar->data_ayah['nama'] ?? '') }}">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Tanggal Lahir</label>
                                                        <input type="date" class="form-control" name="data_ayah[tgl_lahir]" value="{{ old('data_ayah.tgl_lahir', $pelamar->data_ayah['tgl_lahir'] ?? '') }}">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Pendidikan</label>
                                                        <input type="text" class="form-control" name="data_ayah[pendidikan]" value="{{ old('data_ayah.pendidikan', $pelamar->data_ayah['pendidikan'] ?? '') }}">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Pekerjaan</label>
                                                        <input type="text" class="form-control" name="data_ayah[pekerjaan]" value="{{ old('data_ayah.pekerjaan', $pelamar->data_ayah['pekerjaan'] ?? '') }}">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Ibu</strong>
                                                    <div class="form-group mt-2">
                                                        <label>Nama</label>
                                                        <input type="text" class="form-control" name="data_ibu[nama]" value="{{ old('data_ibu.nama', $pelamar->data_ibu['nama'] ?? '') }}">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Tanggal Lahir</label>
                                                        <input type="date" class="form-control" name="data_ibu[tgl_lahir]" value="{{ old('data_ibu.tgl_lahir', $pelamar->data_ibu['tgl_lahir'] ?? '') }}">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Pendidikan</label>
                                                        <input type="text" class="form-control" name="data_ibu[pendidikan]" value="{{ old('data_ibu.pendidikan', $pelamar->data_ibu['pendidikan'] ?? '') }}">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Pekerjaan</label>
                                                        <input type="text" class="form-control" name="data_ibu[pekerjaan]" value="{{ old('data_ibu.pekerjaan', $pelamar->data_ibu['pekerjaan'] ?? '') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="m-0">Saudara Kandung</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addTableRow('tbl_saudara')"><i class="fas fa-plus"></i> Tambah</button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-sm" id="tbl_saudara">
                                                    <thead class="bg-light">
                                                        <tr>
                                                            <th>Nama</th><th>Tgl Lahir</th><th>Gender</th><th>Pendidikan</th><th>Pekerjaan</th><th width="40"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if($pelamar->saudara_kandung)
                                                            @foreach($pelamar->saudara_kandung as $i => $s)
                                                            <tr>
                                                                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[{{ $i }}][nama]" value="{{ $s['nama'] ?? '' }}"></td>
                                                                <td><input type="date" class="form-control form-control-sm" name="saudara_kandung[{{ $i }}][tgl_lahir]" value="{{ $s['tgl_lahir'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[{{ $i }}][gender]" value="{{ $s['gender'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[{{ $i }}][pendidikan]" value="{{ $s['pendidikan'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[{{ $i }}][pekerjaan]" value="{{ $s['pekerjaan'] ?? '' }}"></td>
                                                                <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                                                            </tr>
                                                            @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>

                                            <hr>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="m-0">Data Anak</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addTableRow('tbl_anak')"><i class="fas fa-plus"></i> Tambah</button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-sm" id="tbl_anak">
                                                    <thead class="bg-light">
                                                        <tr>
                                                            <th>Nama</th><th>Tempat Lahir</th><th>Tgl Lahir</th><th>Gender</th><th>Pendidikan</th><th>Status</th><th width="40"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if($pelamar->data_anak)
                                                            @foreach($pelamar->data_anak as $i => $a)
                                                            <tr>
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[{{ $i }}][nama]" value="{{ $a['nama'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[{{ $i }}][tempat_lahir]" value="{{ $a['tempat_lahir'] ?? '' }}"></td>
                                                                <td><input type="date" class="form-control form-control-sm" name="data_anak[{{ $i }}][tgl_lahir]" value="{{ $a['tgl_lahir'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[{{ $i }}][gender]" value="{{ $a['gender'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[{{ $i }}][pendidikan]" value="{{ $a['pendidikan'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="data_anak[{{ $i }}][status]" value="{{ $a['status'] ?? '' }}"></td>
                                                                <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                                                            </tr>
                                                            @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="tab-pane-nav-btns">
                                                <button type="button" class="btn-tab-nav" onclick="gotoTab('nav-fisik-tab')"><i class="fas fa-arrow-left mr-1"></i> Sebelumnya</button>
                                                <button type="button" class="btn-tab-nav btn-tab-next" onclick="gotoTab('nav-riwayatdidik-tab')">Selanjutnya: Riwayat Pendidikan <i class="fas fa-arrow-right ml-1"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Riwayat Pendidikan (Array) --}}
                                    <div class="tab-pane fade" id="tab-riwayatdidik" role="tabpanel" aria-labelledby="nav-riwayatdidik-tab">
                                        <div class="tab-pane-hd d-flex justify-content-between align-items-center">
                                            <div><i class="fas fa-university text-info"></i> Riwayat Pendidikan</div>
                                            <button type="button" class="btn btn-sm btn-outline-primary" style="background:#fff;" onclick="addTableRow('tbl_pendidikan')"><i class="fas fa-plus"></i> Tambah</button>
                                        </div>
                                        <div class="p-0">
                                            <div class="table-responsive m-0">
                                                <table class="table table-bordered table-sm m-0 border-0" id="tbl_pendidikan">
                                                    <thead class="bg-light">
                                                        <tr>
                                                            <th class="border-top-0 border-left-0">Tingkat</th><th>Institusi</th><th>Jurusan</th><th>Dari</th><th>Sampai</th><th>Lulus</th><th width="40" class="border-right-0 border-top-0"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if($pelamar->riwayat_pendidikan)
                                                            @foreach($pelamar->riwayat_pendidikan as $i => $r)
                                                            <tr>
                                                                <td class="border-left-0"><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][tingkat]" value="{{ $r['tingkat'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][institusi]" value="{{ $r['institusi'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][jurusan]" value="{{ $r['jurusan'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][dari]" value="{{ $r['dari'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][sampai]" value="{{ $r['sampai'] ?? '' }}"></td>
                                                                <td>
                                                                    <select class="form-control form-control-sm" name="riwayat_pendidikan[{{ $i }}][lulus]">
                                                                        <option value="1" {{ ($r['lulus'] ?? '') == '1' ? 'selected' : '' }}>Ya</option>
                                                                        <option value="0" {{ ($r['lulus'] ?? '') == '0' ? 'selected' : '' }}>Tidak</option>
                                                                    </select>
                                                                </td>
                                                                <td class="border-right-0"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                                                            </tr>
                                                            @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="tab-pane-nav-btns" style="padding:16px 24px;margin-top:0;">
                                                <button type="button" class="btn-tab-nav" onclick="gotoTab('nav-keluarga-tab')"><i class="fas fa-arrow-left mr-1"></i> Sebelumnya</button>
                                                <button type="button" class="btn-tab-nav btn-tab-next" onclick="gotoTab('nav-pengalaman-tab')">Selanjutnya: Pengalaman Kerja <i class="fas fa-arrow-right ml-1"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Pengalaman Kerja (Array) --}}
                                    <div class="tab-pane fade" id="tab-pengalaman" role="tabpanel" aria-labelledby="nav-pengalaman-tab">
                                        <div class="tab-pane-hd d-flex justify-content-between align-items-center">
                                            <div><i class="fas fa-building text-secondary"></i> Pengalaman Kerja</div>
                                            <button type="button" class="btn btn-sm btn-outline-primary" style="background:#fff;" onclick="addTableRow('tbl_pengalaman')"><i class="fas fa-plus"></i> Tambah</button>
                                        </div>
                                        <div class="p-0">
                                            <div class="table-responsive m-0">
                                                <table class="table table-bordered table-sm m-0 border-0" id="tbl_pengalaman">
                                                    <thead class="bg-light">
                                                        <tr>
                                                            <th class="border-top-0 border-left-0">Perusahaan</th><th>Dari</th><th>Sampai</th><th>Jabatan</th><th>Departemen</th><th>Alasan Keluar</th><th width="40" class="border-right-0 border-top-0"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if($pelamar->pengalaman_kerja)
                                                            @foreach($pelamar->pengalaman_kerja as $i => $p)
                                                            <tr>
                                                                <td class="border-left-0"><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][perusahaan]" value="{{ $p['perusahaan'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][dari]" value="{{ $p['dari'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][sampai]" value="{{ $p['sampai'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][jabatan]" value="{{ $p['jabatan'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][departemen]" value="{{ $p['departemen'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[{{ $i }}][alasan]" value="{{ $p['alasan'] ?? '' }}"></td>
                                                                <td class="border-right-0"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                                                            </tr>
                                                            @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="tab-pane-nav-btns" style="padding:16px 24px;margin-top:0;">
                                                <button type="button" class="btn-tab-nav" onclick="gotoTab('nav-riwayatdidik-tab')"><i class="fas fa-arrow-left mr-1"></i> Sebelumnya</button>
                                                <button type="button" class="btn-tab-nav btn-tab-next" onclick="gotoTab('nav-motivasi-tab')">Selanjutnya: Motivasi & Ekstra <i class="fas fa-arrow-right ml-1"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Motivasi & Ekstra --}}
                                    <div class="tab-pane fade" id="tab-motivasi" role="tabpanel" aria-labelledby="nav-motivasi-tab">
                                        <div class="tab-pane-hd"><i class="fas fa-comment-dots text-secondary"></i> Motivasi & Kegiatan Ekstra</div>
                                        <div class="tab-pane-body">
                                            <div class="row">
                                                <div class="col-md-6 form-group">
                                                    <label>Motivasi Bekerja</label>
                                                    <textarea class="form-control" name="motivasi" rows="4">{{ old('motivasi', $pelamar->motivasi) }}</textarea>
                                                </div>
                                                <div class="col-md-6 form-group">
                                                    <label>Kegiatan Ekstrakurikuler / Organisasi</label>
                                                    <textarea class="form-control" name="kegiatan_ekstra" rows="4">{{ old('kegiatan_ekstra', $pelamar->kegiatan_ekstra) }}</textarea>
                                                </div>
                                            </div>
                                            <div class="tab-pane-nav-btns">
                                                <button type="button" class="btn-tab-nav" onclick="gotoTab('nav-pengalaman-tab')"><i class="fas fa-arrow-left mr-1"></i> Sebelumnya</button>
                                                <button type="button" class="btn-tab-nav btn-tab-next" onclick="gotoTab('nav-dokumen-tab')">Selanjutnya: File Dokumen <i class="fas fa-arrow-right ml-1"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Dokumen (File Re-upload) --}}
                                    <div class="tab-pane fade" id="tab-dokumen" role="tabpanel" aria-labelledby="nav-dokumen-tab">
                                        <div class="tab-pane-hd"><i class="fas fa-folder-open text-danger"></i> File Dokumen</div>
                                        <div class="tab-pane-body">
                                            <p class="text-muted" style="font-size:12px;">*Kosongkan input file jika tidak ingin mengubah file yang sudah ada.</p>
                                            <div class="row">
                                                @php
                                                    $docs = [
                                                        ['label' => 'Surat Lamaran', 'name' => 'surat_lamaran', 'db' => 'file_surat_lamaran'],
                                                        ['label' => 'CV / Resume', 'name' => 'cv', 'db' => 'file_cv'],
                                                        ['label' => 'KTP', 'name' => 'scan_ktp', 'db' => 'file_ktp'],
                                                        ['label' => 'Kartu Keluarga', 'name' => 'scan_kk', 'db' => 'file_kk'],
                                                        ['label' => 'Pas Foto', 'name' => 'pas_foto', 'db' => 'file_pas_foto'],
                                                        ['label' => 'Ijazah Terakhir', 'name' => 'ijazah', 'db' => 'file_ijasah'],
                                                        ['label' => 'Akta Kelahiran', 'name' => 'scan_akta_kelahiran', 'db' => 'file_akta_kelahiran'],
                                                        ['label' => 'SKCK', 'name' => 'scan_skck', 'db' => 'file_skck'],
                                                        ['label' => 'Surat Sehat', 'name' => 'scan_blanko_kesehatan', 'db' => 'file_surat_sehat'],
                                                    ];
                                                @endphp
                                                @foreach($docs as $doc)
                                                <div class="col-md-4 form-group mb-4">
                                                    <label>{{ $doc['label'] }}</label>
                                                    <input type="file" class="form-control-file" name="{{ $doc['name'] }}" accept=".pdf,.jpg,.jpeg,.png">
                                                    @if(!empty($pelamar->{$doc['db']}))
                                                        <a href="{{ asset('storage/' . $pelamar->{$doc['db']}) }}" target="_blank" class="doc-existing">
                                                            <i class="fas fa-file-download text-primary"></i> Lihat File Tersimpan
                                                        </a>
                                                    @endif
                                                </div>
                                                @endforeach
                                            </div>
                                            <div class="tab-pane-nav-btns">
                                                <button type="button" class="btn-tab-nav" onclick="gotoTab('nav-motivasi-tab')"><i class="fas fa-arrow-left mr-1"></i> Sebelumnya</button>
                                                <span></span>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        {{-- Submit Button --}}
                        <div class="d-flex justify-content-end align-items-center mt-4" style="gap:10px;">
                            <a href="{{ route('recruitment.index') }}" class="btn btn-secondary px-4 font-weight-bold">Batal</a>
                            @canRoute('recruitment.update')
                                <button type="submit" class="btn text-white px-5 font-weight-bold shadow" style="background:#ea580c; border:none; padding:10px 20px;">
                                    <i class="fas fa-save mr-2"></i>Simpan Perubahan
                                </button>
                            @endcanRoute
                        </div>

                    </form>

                </div>
            </div>

            @include('layout.footer')
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    @include('layout.footerscript')
    <script>
        let rowIdx = 100;

        function addTableRow(tableId) {
            const tbody = document.querySelector(`#${tableId} tbody`);
            let html = '';
            if (tableId === 'tbl_saudara') {
                html = `<tr>
                    <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[${rowIdx}][nama]"></td>
                    <td><input type="date" class="form-control form-control-sm" name="saudara_kandung[${rowIdx}][tgl_lahir]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[${rowIdx}][gender]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[${rowIdx}][pendidikan]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="saudara_kandung[${rowIdx}][pekerjaan]"></td>
                    <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                </tr>`;
            } else if (tableId === 'tbl_anak') {
                html = `<tr>
                    <td><input type="text" class="form-control form-control-sm" name="data_anak[${rowIdx}][nama]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="data_anak[${rowIdx}][tempat_lahir]"></td>
                    <td><input type="date" class="form-control form-control-sm" name="data_anak[${rowIdx}][tgl_lahir]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="data_anak[${rowIdx}][gender]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="data_anak[${rowIdx}][pendidikan]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="data_anak[${rowIdx}][status]"></td>
                    <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                </tr>`;
            } else if (tableId === 'tbl_pendidikan') {
                html = `<tr>
                    <td class="border-left-0"><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[${rowIdx}][tingkat]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[${rowIdx}][institusi]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[${rowIdx}][jurusan]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[${rowIdx}][dari]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="riwayat_pendidikan[${rowIdx}][sampai]"></td>
                    <td>
                        <select class="form-control form-control-sm" name="riwayat_pendidikan[${rowIdx}][lulus]">
                            <option value="1">Ya</option>
                            <option value="0">Tidak</option>
                        </select>
                    </td>
                    <td class="border-right-0"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                </tr>`;
            } else if (tableId === 'tbl_pengalaman') {
                html = `<tr>
                    <td class="border-left-0"><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${rowIdx}][perusahaan]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${rowIdx}][dari]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${rowIdx}][sampai]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${rowIdx}][jabatan]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${rowIdx}][departemen]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="pengalaman_kerja[${rowIdx}][alasan]"></td>
                    <td class="border-right-0"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                </tr>`;
            }
            tbody.insertAdjacentHTML('beforeend', html);
            rowIdx++;
        }

        // Programmatically activate a tab (used by Sebelumnya/Selanjutnya buttons)
        function gotoTab(navId) {
            $('#' + navId).tab('show');
            document.querySelector('.edit-tabs-content').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // If a field inside a tab fails HTML5 validation, jump to that tab so the user sees it
        document.getElementById('formEditPelamar').addEventListener('invalid', function (e) {
            const pane = e.target.closest('.tab-pane');
            if (pane && !pane.classList.contains('active')) {
                $('#' + pane.getAttribute('aria-labelledby')).tab('show');
            }
        }, true);
    </script>
</body>
</html>