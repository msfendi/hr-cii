<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Realisasi Kantin</title>
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; font-size: 11px; color: #222; }
        table { width: 100%; border-collapse: collapse; }
        .header-table td { border: none; vertical-align: top; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 10px 0; }
        .info-table td { padding: 2px 4px; font-size: 11px; }
        .info-label { font-weight: bold; width: 90px; }
        .main-table { margin-top: 12px; }
        .main-table th, .main-table td {
            border: 1px solid #333;
            padding: 4px 6px;
            font-size: 10.5px;
        }
        .main-table th { background-color: #f0f0f0; text-align: center; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .row-weekend { background-color: #f8d7da; }
        .grand-total-row td { font-weight: bold; background-color: #fff3cd; }
        .signature-table { margin-top: 40px; }
        .signature-table td { text-align: center; padding: 4px; font-size: 11px; }
        .signature-space { height: 60px; }
        .signature-name { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>

    <h1>REALISASI {{ strtoupper($kantinLabel) }}</h1>

    <table class="info-table">
        <tr>
            <td class="info-label">KANTIN</td>
            <td>: {{ $kantinLabel }}</td>
        </tr>
        <tr>
            <td class="info-label">PERIODE</td>
            <td>: {{ $periode }}</td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th style="width:3%">NO.</th>
                <th style="width:20%">HARI, TANGGAL</th>
                <th style="width:10%">JUMLAH SCAN</th>
                <th style="width:14%">TIDAK SCAN<br>(security OS)</th>
                <th style="width:9%">Sift Malam</th>
                <th style="width:9%">Sift Siang</th>
                <th style="width:7%">TOTAL</th>
                <th style="width:10%">HARGA NASI</th>
                <th style="width:18%">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report as $i => $r)
                <tr class="{{ $r['is_weekend'] ? 'row-weekend' : '' }}">
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $r['date_label'] }}</td>
                    <td class="text-center">{{ $r['jumlah_scan'] ?: '' }}</td>
                    <td class="text-center">{{ $r['tidak_scan'] ?: '' }}</td>
                    <td class="text-center">{{ $r['sift_malam'] ?: '' }}</td>
                    <td class="text-center">{{ $r['sift_siang'] ?: '' }}</td>
                    <td class="text-center">{{ $r['total'] }}</td>
                    <td class="text-right">Rp {{ number_format($r['harga_nasi'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($r['total_cost'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="grand-total-row">
                <td colspan="8" class="text-right">TOTAL</td>
                <td class="text-right">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="header-table" style="margin-top:24px">
        <tr>
            <td style="width:60%"></td>
            <td style="width:40%; text-align:right; padding-right:10px;">
                {{-- Sesuaikan nama kota bila diperlukan --}}
                Sukoharjo, {{ \Carbon\Carbon::now()->locale('id')->translatedFormat('d F Y') }}
            </td>
        </tr>
    </table>

    <table class="signature-table">
        <tr>
            <td style="width:50%">Yang Mengajukan,</td>
            <td style="width:50%">Mengetahui,</td>
        </tr>
        <tr>
            <td class="signature-space"></td>
            <td class="signature-space"></td>
        </tr>
        <tr>
            <td class="signature-name">{{ $userName ?? 'Admin' }}</td>
            <td class="signature-name">ROSALIA WIWIEK WIDAWATI</td>
        </tr>
        <tr>
            <td>Admin</td>
            <td>Admin Manager</td>
        </tr>
    </table>

</body>
</html>