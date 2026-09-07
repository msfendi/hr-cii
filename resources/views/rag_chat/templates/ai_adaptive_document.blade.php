<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $doc['title'] ?? 'Dokumen HR-CII' }}</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.45;
        }
        .header-kop {
            border-bottom: 2px solid {{ $doc['theme_color'] ?? '#1e3a8a' }};
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: {{ $doc['theme_color'] ?? '#1e3a8a' }};
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .company-desc {
            font-size: 9px;
            color: #64748b;
        }
        .doc-title-box {
            text-align: center;
            margin: 10px 0 14px 0;
        }
        .doc-main-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .doc-ref-no {
            font-size: 10px;
            color: #475569;
            margin-top: 2px;
        }
        .meta-container {
            margin-bottom: 12px;
            width: 100%;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }
        .meta-table td {
            padding: 2px 4px;
            vertical-align: top;
        }
        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 15%;
        }
        .meta-val {
            color: #0f172a;
        }
        /* KPI / Stat Badges */
        .kpi-row {
            margin-bottom: 14px;
            width: 100%;
        }
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
        }
        .kpi-card {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-top: 3px solid {{ $doc['theme_color'] ?? '#1e3a8a' }};
            padding: 6px 8px;
            text-align: center;
            border-radius: 4px;
        }
        .kpi-val {
            font-size: 14px;
            font-weight: bold;
            color: {{ $doc['theme_color'] ?? '#1e3a8a' }};
        }
        .kpi-label {
            font-size: 8.5px;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 2px;
        }
        /* Narrative Paragraphs */
        .paragraph-text {
            font-size: 10px;
            color: #334155;
            margin-bottom: 10px;
            text-align: justify;
            line-height: 1.5;
        }
        /* Single Entity Detail Table (Bio/Detail) */
        .entity-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px;
            margin-bottom: 12px;
        }
        .entity-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }
        .entity-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #edf2f7;
        }
        .entity-table tr:last-child td {
            border-bottom: none;
        }
        .entity-key {
            font-weight: bold;
            color: #475569;
            width: 25%;
        }
        /* Tabular Data */
        table.data-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.data-grid th {
            background-color: {{ $doc['theme_color'] ?? '#1e3a8a' }};
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            padding: 6px 5px;
            text-align: left;
            border: 1px solid {{ $doc['theme_color'] ?? '#1e3a8a' }};
        }
        table.data-grid td {
            padding: 5px;
            border: 1px solid #cbd5e1;
            font-size: 9px;
            vertical-align: middle;
        }
        table.data-grid tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            font-size: 8px;
            font-weight: bold;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .badge-active { background-color: #dcfce7; color: #166534; }
        .badge-inactive { background-color: #fee2e2; color: #991b1b; }
        .badge-info { background-color: #e0f2fe; color: #075985; }

        /* Notes Box */
        .notes-box {
            background-color: #f1f5f9;
            border-left: 3px solid {{ $doc['theme_color'] ?? '#1e3a8a' }};
            padding: 8px 10px;
            margin-bottom: 14px;
            border-radius: 0 4px 4px 0;
            font-size: 9px;
            color: #334155;
        }
        .notes-title {
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }
        /* Signature Section */
        .signature-table {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .signature-cell {
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
        .signature-line {
            margin-top: 45px;
            border-bottom: 1px solid #333333;
            font-weight: bold;
            color: #0f172a;
            padding-bottom: 2px;
            font-size: 9.5px;
        }
        .signature-role {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 2px;
        }
        .footer {
            position: fixed;
            bottom: -5px;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <!-- KOP SURAT / HEADER -->
    <div class="header-kop">
        <table style="width: 100%;">
            <tr>
                <td style="vertical-align: middle;">
                    <div class="company-name">{{ $doc['company_name'] ?? 'PT. CHUTEX INTERNATIONAL INDONESIA' }}</div>
                    <div class="company-desc">{{ $doc['company_subtitle'] ?? 'Human Resources Management & Enterprise Operation System' }}</div>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <div style="font-size: 8.5px; color: #64748b;">Tanggal Cetak:</div>
                    <div style="font-weight: bold; font-size: 9px; color: #1e293b;">{{ $doc['generated_at'] ?? now()->translatedFormat('d F Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- DOCUMENT TITLE & REF -->
    <div class="doc-title-box">
        <div class="doc-main-title">{{ $doc['title'] ?? 'LAPORAN DATA' }}</div>
        @if(!empty($doc['ref_number']))
            <div class="doc-ref-no">Nomor: {{ $doc['ref_number'] }}</div>
        @endif
    </div>

    <!-- META INFORMATION -->
    @if(!empty($doc['meta']) && count($doc['meta']) > 0)
        <div class="meta-container">
            <table class="meta-table">
                @foreach(array_chunk($doc['meta'], 2, true) as $chunk)
                    <tr>
                        @foreach($chunk as $label => $val)
                            <td class="meta-label">{{ $label }}:</td>
                            <td class="meta-val" style="width: 35%;">{{ $val }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <!-- STATISTIC / KPI CARDS -->
    @if(!empty($doc['kpi_cards']) && count($doc['kpi_cards']) > 0)
        <div class="kpi-row">
            <table class="kpi-table">
                <tr>
                    @foreach($doc['kpi_cards'] as $kpi)
                        <td class="kpi-card" style="width: {{ 100 / count($doc['kpi_cards']) }}%;">
                            <div class="kpi-val">{{ $kpi['value'] ?? '-' }}</div>
                            <div class="kpi-label">{{ $kpi['label'] ?? '' }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    <!-- OPENING PARAGRAPHS (SURAT / MEMO / PENGANTAR) -->
    @if(!empty($doc['opening_paragraphs']))
        @foreach($doc['opening_paragraphs'] as $p)
            <div class="paragraph-text">{{ $p }}</div>
        @endforeach
    @endif

    <!-- SINGLE ENTITY BIO / DETAIL VIEW (If 1 row or focused profile) -->
    @if(!empty($doc['entity_details']) && count($doc['entity_details']) > 0)
        <div class="entity-box">
            <table class="entity-table">
                @foreach($doc['entity_details'] as $k => $v)
                    <tr>
                        <td class="entity-key">{{ $k }}</td>
                        <td style="width: 10px; text-align: center;">:</td>
                        <td><strong>{{ $v }}</strong></td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <!-- TABULAR DATA -->
    @if(!empty($doc['headers']) && !empty($doc['rows']))
        <table class="data-grid">
            <thead>
                <tr>
                    <th style="width: 20px; text-align: center;">No</th>
                    @foreach($doc['headers'] as $h)
                        <th style="{{ isset($h['align']) ? 'text-align: ' . $h['align'] . ';' : '' }}{{ isset($h['width']) ? 'width: ' . $h['width'] . ';' : '' }}">
                            {{ $h['label'] ?? $h }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($doc['rows'] as $idx => $row)
                    <tr>
                        <td style="text-align: center;">{{ $idx + 1 }}</td>
                        @foreach($doc['headers'] as $h)
                            @php
                                $fieldKey = is_array($h) ? ($h['key'] ?? '') : $h;
                                $val = $row[$fieldKey] ?? '-';
                                $align = is_array($h) ? ($h['align'] ?? 'left') : 'left';
                            @endphp
                            <td style="text-align: {{ $align }};">
                                @if(is_string($val) && (strtolower($val) === 'aktif' || strtolower($val) === 'active'))
                                    <span class="badge badge-active">{{ $val }}</span>
                                @elseif(is_string($val) && (strtolower($val) === 'nonaktif' || strtolower($val) === 'inactive' || strtolower($val) === 'berakhir'))
                                    <span class="badge badge-inactive">{{ $val }}</span>
                                @else
                                    {{ $val }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="font-size: 8px; color: #64748b; margin-top: -6px; margin-bottom: 12px;">
            Total Baris Data: {{ count($doc['rows']) }} data
        </div>
    @endif

    <!-- CLOSING PARAGRAPHS / KETERANGAN LANJUTAN -->
    @if(!empty($doc['closing_paragraphs']))
        @foreach($doc['closing_paragraphs'] as $p)
            <div class="paragraph-text">{{ $p }}</div>
        @endforeach
    @endif

    <!-- NOTES / CATATAN & REKOMENDASI -->
    @if(!empty($doc['notes']))
        <div class="notes-box">
            <div class="notes-title">Catatan / Keterangan:</div>
            <div>{{ $doc['notes'] }}</div>
        </div>
    @endif

    <!-- SIGNATURES SECTION -->
    @if(!empty($doc['signatures']) && count($doc['signatures']) > 0)
        <table class="signature-table">
            <tr>
                @foreach($doc['signatures'] as $sig)
                    <td class="signature-cell" style="width: {{ 100 / count($doc['signatures']) }}%;">
                        <div>{{ $sig['caption'] ?? 'Mengetahui,' }}</div>
                        <div class="signature-line">{{ $sig['name'] ?? 'HR Management' }}</div>
                        <div class="signature-role">{{ $sig['role'] ?? 'PT. Chutex International Indonesia' }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <!-- FOOTER -->
    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td>Sistem Otomasi Dokumen HR-CII &bull; Dokumen Rahasia Perusahaan</td>
                <td style="text-align: right;">Hal. 1 / Cetak Otomatis</td>
            </tr>
        </table>
    </div>
</body>
</html>
