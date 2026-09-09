<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Biodata Karyawan - {{ $npk }}</title>

    <style>
        @page {
            margin: 11mm 9mm 16mm 9mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            color: #0f172a;
            background: #ffffff;
        }

        .footer {
            position: fixed;
            bottom: -7mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7.4pt;
            color: #64748b;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .header td {
            padding: 0;
            vertical-align: middle;
        }

        .header-line {
            border-bottom: 2.5px solid #0f172a;
            margin-bottom: 7px;
        }

        .logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
        }

        .company-name {
            margin: 0;
            font-size: 11.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35px;
            color: #0f172a;
        }

        .company-sub {
            margin: 2px 0 0;
            font-size: 8.1pt;
            color: #475569;
            line-height: 1.35;
        }

        .meta-box {
            margin-left: auto;
            width: 178px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 8px;
            padding: 5px 7px;
            font-size: 8.1pt;
            color: #334155;
        }

        .meta-box div {
            margin: 1px 0;
        }

        .meta-label {
            display: inline-block;
            width: 54px;
            color: #64748b;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 7.4pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35px;
            border: 1px solid transparent;
        }

        .badge-staff {
            background: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .badge-nonstaff {
            background: #fff7ed;
            color: #9a3412;
            border-color: #fed7aa;
        }

        .badge-contract {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .badge-muted {
            background: #f8fafc;
            color: #475569;
            border-color: #cbd5e1;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #cbd5e1;
        }

        .summary td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            vertical-align: top;
            font-size: 9pt;
            background: #ffffff;
        }

        .summary .label {
            width: 14%;
            background: #f8fafc;
            font-size: 7.9pt;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .summary .value {
            width: 27%;
            font-weight: 600;
        }

        .summary .photo-cell {
            width: 18%;
            text-align: center;
            vertical-align: middle;
            background: #ffffff;
        }

        .photo {
            width: 84px;
            height: 110px;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            background: #ffffff;
        }

        .photo-empty {
            width: 84px;
            height: 110px;
            margin: 0 auto;
            border: 1px dashed #94a3b8;
            border-radius: 4px;
            color: #64748b;
            font-size: 7.5pt;
            background: #f8fafc;
        }

        .photo-empty span {
            display: inline-block;
            margin-top: 43px;
            line-height: 1.35;
        }

        .small-note {
            margin-top: 3px;
            font-size: 7.4pt;
            color: #64748b;
        }

        .section {
            margin-top: 6px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
        }

        .section-title {
            background: #0f172a;
            color: #ffffff;
            padding: 5px 8px;
            font-size: 8.7pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            page-break-after: avoid;
        }

        .section-body {
            padding: 0;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .info-table td {
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            padding: 4px 6px;
            font-size: 8.7pt;
            vertical-align: top;
            background: #ffffff;
        }

        .info-table tr:last-child td {
            border-bottom: none;
        }

        .info-table td:last-child {
            border-right: none;
        }

        .info-label {
            width: 17%;
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 7.8pt;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .info-value {
            width: 33%;
            color: #0f172a;
            font-weight: 600;
        }


        .info-value.normal {
            font-weight: 400;
            overflow-wrap: anywhere;
            line-height: 1.3;
        }

        .text-center {
            text-align: center;
        }

        .nowrap {
            white-space: nowrap;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .data-table th {
            background: #f1f5f9;
            white-space: nowrap;
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
            font-size: 7.7pt;
            text-transform: uppercase;
            letter-spacing: .35px;
            color: #334155;
            text-align: left;
        }

        .data-table td {
            border: 1px solid #cbd5e1;
            overflow-wrap: anywhere;
            padding: 4px 5px;
            font-size: 8.7pt;
            vertical-align: top;
        }

        .data-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .data-table tr {
            page-break-inside: avoid;
        }

        .empty-state {
            padding: 7px 8px;
            color: #64748b;
            font-style: italic;
            background: #f8fafc;
            text-align: center;
            font-size: 8.6pt;
        }

        .statement {
            margin-top: 8px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 8px;
            padding: 7px 9px;
            font-size: 8.5pt;
            color: #334155;
            text-align: justify;
            line-height: 1.35;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 9px;
            page-break-inside: avoid;
        }

        .signature-table td {
            width: 50%;
            padding: 0 8px;
            vertical-align: top;
        }

        .sign-box {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            min-height: 88px;
            padding: 9px 11px;
            text-align: center;
            background: #ffffff;
        }

        .sign-role {
            font-size: 8.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35px;
            color: #334155;
            margin-bottom: 39px;
            line-height: 1.35;
        }

        .sign-name {
            display: inline-block;
            min-width: 210px;
            border-top: 1px dotted #475569;
            padding-top: 5px;
            font-weight: 700;
            font-size: 9pt;
        }

        .sub-title {
            padding: 6px 8px 3px;
            font-size: 8.3pt;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .muted {
            color: #64748b;
            font-weight: 400;
        }
    </style>
</head>

<body>
    @php
        $formatDate = function ($value, $format = 'd F Y') {
            if (empty($value)) {
                return '-';
            }

            try {
                return \Carbon\Carbon::parse($value)->translatedFormat($format);
            } catch (\Throwable $e) {
                return '-';
            }
        };

        $normalizeAssoc = function ($data) {
            if (is_string($data)) {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
            if (is_object($data)) {
                $data = (array) $data;
            }
            return is_array($data) ? $data : [];
        };

        $normalizeList = function ($items, $singleKey) {
            if (is_string($items)) {
                $decoded = json_decode($items, true);
                if (is_array($decoded)) {
                    $items = $decoded;
                }
            }

            if ($items instanceof \Illuminate\Support\Collection) {
                $items = $items->toArray();
            }

            if (is_object($items)) {
                $items = (array) $items;
            }

            if (!is_array($items) || empty($items)) {
                return [];
            }

            if (isset($items[$singleKey])) {
                return [(array) $items];
            }

            $result = [];
            foreach ($items as $item) {
                $result[] = (array) $item;
            }

            return array_values($result);
        };

        $nama = $pkwt->NAMA ?? ($biodata->NAMA_KARYAWAN ?? '-');
        $line = trim(($sectionName ?? '') . ($lineInfo ? ' (' . $lineInfo . ')' : '')) ?: '-';

        $tglLahir = $pkwt->TGLLAHIR ?? ($pelamar->TGL_LAHIR ?? null);

        $jk = $pkwt->JK ?? ($pelamar->JENIS_KELAMIN ?? ($biodata->JENIS_KEL ?? null));
        $jkLabel = $jk == 'L' ? 'Laki-laki' : ($jk == 'P' ? 'Perempuan' : ($jk ?: '-'));

        $usiaText = $pkwt->USIA ?? '-';
        if ($tglLahir) {
            try {
                $usiaText = \Carbon\Carbon::parse($tglLahir)->age . ' Tahun';
            } catch (\Throwable $e) {
                // biarkan fallback
            }
        }

        $alamatKtp = trim(
            trim($pkwt->ALAMAT ?? ($pelamar->ALAMAT_LENGKAP ?? '')) . ', ' .
            trim($pkwt->KABUPATEN ?? ($pelamar->KABUPATEN ?? '')),
            ', '
        );

        if ($alamatKtp === '') {
            $alamatKtp = '-';
        }

        $alamatDomisili = 'Sama dengan alamat KTP';
        if (!empty($pelamarDetail->alamat_skrg)) {
            $alamatDomisili = trim(
                $pelamarDetail->alamat_skrg .
                ($pelamarDetail->kabupaten_kota_skrg ? ', ' . $pelamarDetail->kabupaten_kota_skrg : '')
            );
        }

        $dataAyah = $normalizeAssoc($dataAyah ?? []);
        $dataIbu = $normalizeAssoc($dataIbu ?? []);

        $saudaraList = $normalizeList($saudaraKandung ?? [], 'nama');
        $anakList = $normalizeList($dataAnak ?? [], 'nama');
        $eduList = $normalizeList($riwayatPendidikan ?? [], 'tingkat');
        $expList = $normalizeList($pengalamanKerja ?? [], 'perusahaan');

        $alamatKab = $pkwt->KABUPATEN ?? ($pelamar->KABUPATEN ?? 'Semarang');
    @endphp

    <div class="footer">
        Dicetak pada {{ now()->translatedFormat('d F Y, H:i') }} WIB
        | Sistem HRIS PT. Chutex International Indonesia
        | Biodata Karyawan - NPK: {{ $npk }}
    </div>

    {{-- HEADER --}}
    <table class="header">
        <tr>
            <td width="64" valign="middle">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" class="logo" alt="Logo">
                @endif
            </td>
            <td valign="middle">
                <p class="company-name">PT. Chutex International Indonesia</p>
                <p class="company-sub">Human Resources Department — Employee Biodata Management</p>
                <p class="company-sub">Formulir Biodata Karyawan / Data bersifat rahasia — Dokumen Resmi Internal HRD
                </p>
            </td>
            <td width="190" valign="middle">
                <div class="meta-box">
                    <div><span class="meta-label">No</span>: BIO/{{ $npk }}</div>
                    <div><span class="meta-label">Tanggal</span>: {{ now()->translatedFormat('d F Y') }}</div>
                    <div><span class="meta-label">NPK</span>: {{ $npk }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="header-line"></div>

    {{-- SUMMARY UTAMA --}}
    <table class="summary">
        <colgroup>
            <col style="width:14%">
            <col style="width:27%">
            <col style="width:14%">
            <col style="width:27%">
            <col style="width:18%">
        </colgroup>
        <tr>
            <td class="label">NPK</td>
            <td class="value">{{ $npk }}</td>

            <td class="label">Status</td>
            <td class="value">
                @if(!empty($biodata) && !empty($biodata->IS_STAFF))
                    <span class="badge badge-staff">Staff</span>
                @else
                    <span class="badge badge-nonstaff">Non-Staff</span>
                @endif
            </td>

            <td class="photo-cell" rowspan="4">
                @if(!empty($photoBase64))
                    <img src="{{ $photoBase64 }}" class="photo" alt="Foto Karyawan">
                @else
                    <div class="photo-empty">
                        <span>Pas Foto<br>3 x 4</span>
                    </div>
                @endif
                <div class="small-note">Foto 3x4</div>
            </td>
        </tr>

        <tr>
            <td class="label">Nama Lengkap</td>
            <td class="value" colspan="3">{{ $nama }}</td>
        </tr>

        <tr>
            <td class="label">Departemen</td>
            <td class="value">{{ $deptName ?? '-' }}</td>

            <td class="label">Bagian / Line</td>
            <td class="value">{{ $line }}</td>
        </tr>

        <tr>
            <td class="label">Barcode</td>
            <td class="value">{{ $biodata->BARCODE ?? '-' }}</td>

            <td class="label">Dicetak</td>
            <td class="value">{{ now()->translatedFormat('d/m/Y H:i') }}</td>
        </tr>
    </table>

    {{-- I. DATA PRIBADI --}}
    <div class="section">
        <div class="section-title">I. Data Pribadi</div>
        <div class="section-body">
            <table class="info-table">
                <colgroup>
                    <col style="width:17%">
                    <col style="width:33%">
                    <col style="width:17%">
                    <col style="width:33%">
                </colgroup>
                <tr>
                    <td class="info-label">NIK / No. KTP</td>
                    <td class="info-value">{{ $pkwt->KTP ?? ($pelamar->NIK ?? '-') }}</td>

                    <td class="info-label">Agama</td>
                    <td class="info-value">{{ $pkwt->AGAMA ?? ($pelamar->AGAMA ?? '-') }}</td>
                </tr>

                <tr>
                    <td class="info-label">No. Kartu Keluarga</td>
                    <td class="info-value">{{ $pkwt->NO_KK ?? ($pelamar->NO_KK ?? '-') }}</td>

                    <td class="info-label">Status Nikah</td>
                    <td class="info-value">{{ $pkwt->STATUS ?? ($pelamar->STATUS ?? '-') }}</td>
                </tr>

                <tr>
                    <td class="info-label">No. SIM</td>
                    <td class="info-value">{{ $pelamarDetail->nomor_sim ?? '-' }}</td>

                    <td class="info-label">Tanggungan</td>
                    <td class="info-value">{{ $pkwt->TANGGUNGAN ?? ($pelamar->TANGGUNGAN ?? '-') }}</td>
                </tr>

                <tr>
                    <td class="info-label">Tempat Lahir</td>
                    <td class="info-value">{{ $pkwt->TMPTLAHIR ?? ($pelamar->TMPT_LAHIR ?? '-') }}</td>

                    <td class="info-label">Kewarganegaraan</td>
                    <td class="info-value">{{ $pelamarDetail->warga_negara ?? 'WNI' }}</td>
                </tr>

                <tr>
                    <td class="info-label">Tanggal Lahir</td>
                    <td class="info-value">{{ $formatDate($tglLahir) }}</td>

                    <td class="info-label">Nama Ibu Kandung</td>
                    <td class="info-value">{{ $pkwt->IBU ?? ($pelamar->IBU ?? '-') }}</td>
                </tr>

                <tr>
                    <td class="info-label">Usia</td>
                    <td class="info-value">{{ $usiaText }}</td>

                    <td class="info-label">BPJS Ketenagakerjaan</td>
                    <td class="info-value">{{ $pelamarDetail->bpjs_tk ?? '-' }}</td>
                </tr>

                <tr>
                    <td class="info-label">Jenis Kelamin</td>
                    <td class="info-value">{{ $jkLabel }}</td>

                    <td class="info-label">BPJS Kesehatan</td>
                    <td class="info-value">{{ $pelamarDetail->bpjs_kes ?? '-' }}</td>
                </tr>

                <tr>
                    <td class="info-label">No. Rekening Bank</td>
                    <td class="info-value">{{ $bankAccount ?? ($pkwt->NOREK ?? '-') }}</td>

                    <td class="info-label">Faskes BPJS</td>
                    <td class="info-value">{{ $pkwt->FASKES ?? '-' }}</td>
                </tr>

                <tr>
                    <td class="info-label">Bakat / Hobi</td>
                    <td class="info-value normal" colspan="3">{{ $pelamarDetail->bakat_hobby ?? '-' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- II. DATA PEKERJAAN --}}
    <div class="section">
        <div class="section-title">II. Data Pekerjaan &amp; Penempatan</div>
        <div class="section-body">
            <table class="info-table">
                <colgroup>
                    <col style="width:17%">
                    <col style="width:33%">
                    <col style="width:17%">
                    <col style="width:33%">
                </colgroup>
                <tr>
                    <td class="info-label">TMK (Tgl Masuk)</td>
                    <td class="info-value">{{ $formatDate($pkwt->TMK ?? null) }}</td>

                    <td class="info-label">Kontrak Ke-</td>
                    <td class="info-value">{{ $contract->contract_ke ?? '-' }}</td>
                </tr>

                <tr>
                    <td class="info-label">Status Kontrak</td>
                    <td class="info-value">
                        @if(!empty($contract))
                            <span class="badge badge-contract">{{ $contract->status_contract ?: 'Kontrak' }}</span>
                        @else
                            <span class="badge badge-muted">Belum ada kontrak</span>
                        @endif
                    </td>

                    <td class="info-label">Masa Berlaku s/d</td>
                    <td class="info-value">{{ $formatDate($contract->end_date ?? null) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- III. ALAMAT & KONTAK --}}
    <div class="section">
        <div class="section-title">III. Alamat &amp; Kontak</div>
        <div class="section-body">
            <table class="info-table">
                <colgroup>
                    <col style="width:17%">
                    <col style="width:33%">
                    <col style="width:17%">
                    <col style="width:33%">
                </colgroup>
                <tr>
                    <td class="info-label">Alamat KTP</td>
                    <td class="info-value normal" colspan="3">{{ $alamatKtp }}</td>
                </tr>

                <tr>
                    <td class="info-label">Alamat Domisili</td>
                    <td class="info-value normal" colspan="3">{{ $alamatDomisili }}</td>
                </tr>

                <tr>
                    <td class="info-label">Status Domisili</td>
                    <td class="info-value">{{ $pelamarDetail->status_domisili ?? '-' }}</td>

                    <td class="info-label">No. HP / WhatsApp</td>
                    <td class="info-value">{{ $pkwt->HP ?? ($pelamar->HP ?? '-') }}</td>
                </tr>

                <tr>
                    <td class="info-label">Kontak Darurat</td>
                    <td class="info-value">{{ $pelamarDetail->nama_ktk_darurat ?? '-' }}</td>

                    <td class="info-label">Hubungan</td>
                    <td class="info-value">{{ $pelamarDetail->hubungan ?? '-' }}</td>
                </tr>

                <tr>
                    <td class="info-label">No. Telp Darurat</td>
                    <td class="info-value normal" colspan="3">{{ $pelamarDetail->no_telp_darurat ?? '-' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- IV. DATA KELUARGA --}}
    <div class="section">
        <div class="section-title">IV. Data Keluarga</div>
        <div class="section-body">
            <table class="info-table">
                <colgroup>
                    <col style="width:17%">
                    <col style="width:33%">
                    <col style="width:17%">
                    <col style="width:33%">
                </colgroup>
                <tr>
                    <td class="info-label">Nama Ayah</td>
                    <td class="info-value">{{ $dataAyah['nama'] ?? '-' }}</td>

                    <td class="info-label">Nama Ibu</td>
                    <td class="info-value">{{ $dataIbu['nama'] ?? ($pkwt->IBU ?? '-') }}</td>
                </tr>

                <tr>
                    <td class="info-label">Tgl Lahir</td>
                    <td class="info-value">{{ $formatDate($dataAyah['tgl_lahir'] ?? null) }}</td>

                    <td class="info-label">Tgl Lahir</td>
                    <td class="info-value">{{ $formatDate($dataIbu['tgl_lahir'] ?? null) }}</td>
                </tr>

                <tr>
                    <td class="info-label">Pendidikan</td>
                    <td class="info-value">{{ $dataAyah['pendidikan'] ?? '-' }}</td>

                    <td class="info-label">Pendidikan</td>
                    <td class="info-value">{{ $dataIbu['pendidikan'] ?? '-' }}</td>
                </tr>

                <tr>
                    <td class="info-label">Pekerjaan</td>
                    <td class="info-value">{{ $dataAyah['pekerjaan'] ?? '-' }}</td>

                    <td class="info-label">Pekerjaan</td>
                    <td class="info-value">{{ $dataIbu['pekerjaan'] ?? '-' }}</td>
                </tr>
            </table>

            @if(count($saudaraList) > 0)
                <div class="sub-title">Saudara Kandung</div>
                <table class="data-table">
                    <colgroup>
                        <col style="width:4%">
                        <col style="width:24%">
                        <col style="width:7%">
                        <col style="width:14%">
                        <col style="width:22%">
                        <col style="width:29%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Saudara</th>
                            <th width="7%">L/P</th>
                            <th width="14%">Tgl Lahir</th>
                            <th>Pendidikan</th>
                            <th>Pekerjaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($saudaraList as $i => $s)
                            @if(!empty($s['nama']))
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $s['nama'] ?? '-' }}</td>
                                    <td>{{ $s['gender'] ?? ($s['jk'] ?? '-') }}</td>
                                    <td>{{ $formatDate($s['tgl_lahir'] ?? null, 'd-m-Y') }}</td>
                                    <td>{{ $s['pendidikan'] ?? '-' }}</td>
                                    <td>{{ $s['pekerjaan'] ?? '-' }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(count($anakList) > 0)
                <div class="sub-title">Data Anak</div>
                <table class="data-table">
                    <colgroup>
                        <col style="width:4%">
                        <col style="width:22%">
                        <col style="width:7%">
                        <col style="width:19%">
                        <col style="width:14%">
                        <col style="width:17%">
                        <col style="width:17%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Anak</th>
                            <th width="7%">L/P</th>
                            <th>Tempat Lahir</th>
                            <th width="14%">Tgl Lahir</th>
                            <th>Pendidikan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($anakList as $i => $a)
                            @if(!empty($a['nama']))
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $a['nama'] ?? '-' }}</td>
                                    <td>{{ $a['gender'] ?? ($a['jk'] ?? '-') }}</td>
                                    <td>{{ $a['tempat_lahir'] ?? '-' }}</td>
                                    <td>{{ $formatDate($a['tgl_lahir'] ?? null, 'd-m-Y') }}</td>
                                    <td>{{ $a['pendidikan'] ?? '-' }}</td>
                                    <td>{{ $a['status'] ?? '-' }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(count($saudaraList) === 0 && count($anakList) === 0)
                <div class="empty-state">Data saudara/anak belum tercatat.</div>
            @endif
        </div>
    </div>

    {{-- V. RIWAYAT PENDIDIKAN --}}
    <div class="section">
        <div class="section-title">V. Riwayat Pendidikan Formal</div>
        <div class="section-body">
            @if(count($eduList) > 0)
                <table class="data-table">
                    <colgroup>
                        <col style="width:4%">
                        <col style="width:8%">
                        <col style="width:29%">
                        <col style="width:21%">
                        <col style="width:12%">
                        <col style="width:12%">
                        <col style="width:14%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Jenjang</th>
                            <th>Nama Institusi / Sekolah</th>
                            <th>Jurusan</th>
                            <th width="10%">Tahun Masuk</th>
                            <th width="10%">Tahun Lulus</th>
                            <th width="10%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($eduList as $i => $edu)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $edu['tingkat'] ?? '-' }}</td>
                                <td>{{ $edu['institusi'] ?? ($edu['nama_sekolah'] ?? '-') }}</td>
                                <td>{{ $edu['jurusan'] ?? '-' }}</td>
                                <td>{{ $edu['dari'] ?? ($edu['tahun_masuk'] ?? '-') }}</td>
                                <td>{{ $edu['sampai'] ?? ($edu['tahun_lulus'] ?? '-') }}</td>
                                <td>
                                    @php
                                        $lulus = $edu['lulus'] ?? null;
                                    @endphp

                                    @if($lulus === 1 || $lulus === true || $lulus === '1')
                                        Lulus
                                    @elseif($lulus === 0 || $lulus === false || $lulus === '0')
                                        Belum Lulus
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-state">Data riwayat pendidikan belum tercatat.</div>
            @endif
        </div>
    </div>

    {{-- VI. PENGALAMAN KERJA --}}
    <div class="section">
        <div class="section-title">VI. Riwayat Pengalaman Kerja</div>
        <div class="section-body">
            @if(count($expList) > 0)
                <table class="data-table">
                    <colgroup>
                        <col style="width:4%">
                        <col style="width:24%">
                        <col style="width:10%">
                        <col style="width:12%">
                        <col style="width:18%">
                        <col style="width:14%">
                        <col style="width:18%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Perusahaan</th>
                            <th width="10%">Dari</th>
                            <th width="12%">Sampai</th>
                            <th>Jabatan</th>
                            <th>Departemen</th>
                            <th>Alasan Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expList as $i => $exp)
                            @if(!empty($exp['perusahaan']) || !empty($exp['nama_perusahaan']))
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $exp['perusahaan'] ?? ($exp['nama_perusahaan'] ?? '-') }}</td>
                                    <td>{{ $exp['dari'] ?? ($exp['tahun_masuk'] ?? '-') }}</td>
                                    <td>
                                        @if(!empty($exp['masih_bekerja']) && ($exp['masih_bekerja'] == 1 || $exp['masih_bekerja'] === true || $exp['masih_bekerja'] === '1'))
                                            Sekarang
                                        @else
                                            {{ $exp['sampai'] ?? ($exp['tahun_keluar'] ?? '-') }}
                                        @endif
                                    </td>
                                    <td>{{ $exp['jabatan'] ?? ($exp['posisi'] ?? '-') }}</td>
                                    <td>{{ $exp['departemen'] ?? '-' }}</td>
                                    <td>{{ $exp['alasan'] ?? ($exp['alasan_keluar'] ?? '-') }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-state">Belum ada riwayat pengalaman kerja.</div>
            @endif
        </div>
    </div>

    {{-- VII. INFORMASI LAINNYA --}}
    <div class="section">
        <div class="section-title">VII. Informasi Lainnya</div>
        <div class="section-body">
            <table class="info-table">
                <colgroup>
                    <col style="width:17%">
                    <col style="width:33%">
                    <col style="width:17%">
                    <col style="width:33%">
                </colgroup>
                <tr>
                    <td class="info-label">Motivasi Kerja</td>
                    <td class="info-value normal" colspan="3">{{ $pelamarDetail->motivasi ?? '-' }}</td>
                </tr>

                <tr>
                    <td class="info-label">Kegiatan Ekstra</td>
                    <td class="info-value normal">{{ $pelamarDetail->kegiatan_ekstra ?? '-' }}</td>

                    <td class="info-label">Transportasi</td>
                    <td class="info-value">{{ $pelamarDetail->mode_transportasi ?? '-' }}</td>
                </tr>

                <tr>
                    <td class="info-label">Ikut Program KB</td>
                    <td class="info-value" colspan="3">
                        @if($pelamarDetail && $pelamarDetail->ikut_kb !== null)
                            {{ $pelamarDetail->ikut_kb ? 'Ya' : 'Tidak' }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>

    {{-- PERNYATAAN --}}
    {{-- <div class="statement">
        <strong>Pernyataan:</strong>
        Saya menyatakan bahwa keterangan yang saya berikan di atas adalah benar dan dapat dipertanggungjawabkan.
        Apabila di kemudian hari terbukti ada keterangan yang tidak benar, saya bersedia menerima sanksi atau tindakan
        sesuai peraturan perusahaan.
    </div> --}}
</body>

</html>