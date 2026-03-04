<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kartu Berobat - {{ $karyawan->NAMA_KARYAWAN }}</title>
    <style>
        @page {
            margin: 20mm 15mm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header h2 {
            margin: 2px 0;
            font-size: 14px;
            font-weight: normal;
        }
        .header p {
            margin: 2px 0;
            font-size: 11px;
            color: #666;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 5px;
        }
        .logo-container img {
            height: 50px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 15px;
        }
        .info-table td {
            padding: 3px 5px;
            vertical-align: top;
        }
        .info-table td.label {
            font-weight: bold;
            width: 120px;
        }
        .visit-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .visit-table th {
            background-color: #4e73df;
            color: white;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }
        .visit-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #ddd;
            font-size: 10px;
            vertical-align: top;
        }
        .visit-table tr:nth-child(even) {
            background-color: #f8f9fc;
        }
        .summary {
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-size: 12px;
        }
        .summary span {
            margin: 0 20px;
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>KARTU BEROBAT KARYAWAN</h1>
        <h2>PT. Chutex International Indonesia</h2>
        <p>Poliklinik Perusahaan</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">NPK</td>
            <td>: {{ $karyawan->NPK }}</td>
        </tr>
        <tr>
            <td class="label">Nama</td>
            <td>: {{ $karyawan->NAMA_KARYAWAN }}</td>
        </tr>
        <tr>
            <td class="label">Departemen</td>
            <td>: {{ $karyawan->DEPARTEMENT ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Section</td>
            <td>: {{ $karyawan->SECTION ?? '-' }}</td>
        </tr>
    </table>

    <h3 style="margin-bottom: 5px; font-size: 13px; border-bottom: 1px solid #333; padding-bottom: 3px;">RIWAYAT KUNJUNGAN</h3>

    <table class="visit-table">
        <thead>
            <tr>
                <th width="30">No</th>
                <th width="70">Tanggal</th>
                <th>Diagnosa</th>
                <th>Tindakan</th>
                <th>Obat</th>
                <th width="80">Dokter</th>
            </tr>
        </thead>
        <tbody>
            @forelse($kunjungans as $index => $k)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $k->tanggal_kunjungan->format('d/m/Y') }}</td>
                <td>{{ $k->diagnosa ?? '-' }}</td>
                <td>{{ $k->tindakan ?? '-' }}</td>
                <td>
                    @if($k->resepObats->count())
                        @foreach($k->resepObats as $resep)
                            • {{ $resep->keterangan_obat }}<br>
                        @endforeach
                    @else
                        -
                    @endif
                </td>
                <td>{{ $dokters[$k->dokter_id] ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; color: #999;">Belum ada riwayat kunjungan.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <span>Total Kunjungan: {{ $totalKunjungan }}</span>
        <span>Kunjungan Tahun Ini ({{ date('Y') }}): {{ $kunjunganTahunIni }}</span>
    </div>

    <div class="footer">
        Dicetak pada: {{ now()->format('d/m/Y H:i:s') }} | Sistem Rekam Medis Poliklinik - PT. Chutex International Indonesia
    </div>
</body>
</html>
