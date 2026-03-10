<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kartu Kunjungan #{{ $kunjungan->no_antrian }}</title>
    <style>
        @page {
            size: A5 portrait;
            margin: 10mm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
        }
        .card {
            border: 2px solid #4e73df;
            border-radius: 8px;
            padding: 15px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #4e73df;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            font-size: 16px;
            color: #4e73df;
        }
        .header p {
            margin: 2px 0;
            font-size: 10px;
            color: #666;
        }
        .antrian-box {
            text-align: center;
            margin: 10px 0;
        }
        .antrian-number {
            font-size: 48px;
            font-weight: bold;
            color: #4e73df;
            border: 3px solid #4e73df;
            border-radius: 50%;
            width: 80px;
            height: 80px;
            line-height: 75px;
            display: inline-block;
        }
        .antrian-label {
            font-size: 14px;
            font-weight: bold;
            margin-top: 5px;
            color: #333;
        }
        .info {
            margin: 10px 0;
        }
        .info table td {
            padding: 3px 5px;
        }
        .info table td.label {
            font-weight: bold;
            width: 120px;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 9px;
            color: #999;
            border-top: 1px dashed #ccc;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>POLIKLINIK PERUSAHAAN</h1>
            <p>PT. Chutex International Indonesia</p>
        </div>

        <div class="antrian-box">
            <div class="antrian-number">{{ $kunjungan->no_antrian }}</div>
            <div class="antrian-label">NOMOR ANTRIAN</div>
        </div>

        <div class="info">
            <table width="100%">
                <tr>
                    <td class="label">NPK</td>
                    <td>: {{ $karyawan->NPK ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Nama</td>
                    <td>: {{ $karyawan->NAMA_KARYAWAN ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Departemen</td>
                    <td>: {{ $karyawan->DEPARTEMENT ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Tanggal</td>
                    <td>: {{ $kunjungan->tanggal_kunjungan->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Keluhan</td>
                    <td>: {{ $kunjungan->keluhan }}</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            Dicetak: {{ now()->format('d/m/Y H:i') }} | Harap menunggu hingga nomor antrian dipanggil
        </div>
    </div>
</body>
</html>
