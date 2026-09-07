<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Laporan Sistem HR-CII' }}</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333333;
            line-height: 1.4;
        }
        .header-container {
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .company-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 10px;
            color: #64748b;
        }
        .report-title {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 10px;
            margin-bottom: 4px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 15px;
            font-size: 10px;
        }
        .meta-table td {
            padding: 2px 0;
        }
        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #3b82f6;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .summary-title {
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 4px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.data-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
            padding: 7px 6px;
            text-align: left;
            border: 1px solid #1e3a8a;
        }
        table.data-table td {
            padding: 6px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
        }
        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .footer-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: right;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
        .signature-section {
            margin-top: 30px;
            width: 100%;
        }
        .signature-box {
            width: 200px;
            float: right;
            text-align: center;
        }
        .signature-line {
            margin-top: 50px;
            border-bottom: 1px solid #333;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header-container">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="company-title">PT. CHUTEX INTERNATIONAL INDONESIA</div>
                    <div class="company-subtitle">Human Resources & Enterprise Operation Management System</div>
                </td>
                <td style="text-align: right; vertical-align: bottom;">
                    <div class="company-subtitle">Tanggal Cetak: {{ $generated_at ?? now()->translatedFormat('d F Y H:i') }}</div>
                </td>
            </tr>
        </table>
        <div class="report-title">{{ $title }}</div>
    </div>

    @if(!empty($summary))
        <div class="summary-box">
            <div class="summary-title">Ringkasan / Catatan Analisis:</div>
            <div>{{ $summary }}</div>
        </div>
    @endif

    @if(!empty($headers) && !empty($rows))
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25px; text-align: center;">No</th>
                    @foreach($headers as $h)
                        <th>{{ ucwords(str_replace(['_', '-'], ' ', $h)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $idx => $row)
                    <tr>
                        <td style="text-align: center;">{{ $idx + 1 }}</td>
                        @foreach($headers as $h)
                            <td>{{ $row[$h] ?? '-' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="font-size: 9px; color: #64748b; margin-top: -10px; margin-bottom: 15px;">
            Total Baris Data: {{ count($rows) }} data
        </div>
    @else
        <div style="padding: 20px; text-align: center; color: #64748b; font-style: italic;">
            Tidak ada rincian data tabular untuk ditampilkan.
        </div>
    @endif

    <div class="signature-section">
        <div class="signature-box">
            <div>Disahkan Oleh,</div>
            <div class="signature-line">{{ $authorized_by ?? 'HR & General Affair' }}</div>
            <div style="font-size: 9px; color: #64748b;">Generated automatically by AI System</div>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="footer-container">
        Dokumen ini dihasilkan secara otomatis oleh HR-CII AI Assistant. Halaman 1
    </div>
</body>
</html>
