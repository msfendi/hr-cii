<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4;
            margin: 18px 22px;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* dompdf paginates per <tr>; these two rules stop it from
           slicing a row in half and keep the header repeating on
           every new page a table spills onto */
        thead {
            display: table-header-group;
        }

        tfoot {
            display: table-footer-group;
        }

        tr {
            page-break-inside: avoid;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #2d3748;
            background: #eef1f6;
            padding: 12px;
            margin: 0;
        }

        .page-break {
            page-break-after: always;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ============================================================
           HEADER
        ============================================================ */
        .doc-header {
            /* flat color instead of linear-gradient: dompdf approximates
               gradients with its own banding algorithm, so the shade never
               matches what you see in the browser blade view 1:1 */
            background-color: #1e3a70;
            border-radius: 12px;
            padding: 16px 22px;
            margin-bottom: 18px;
            color: #ffffff;
            page-break-inside: avoid;
        }

        .doc-header table {
            width: 100%;
            border-collapse: collapse;
        }

        .doc-header td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .doc-header .logo-cell {
            width: 56px;
            padding-right: 14px;
        }

        .doc-header .text-cell {
            padding-left: 0;
        }

        /* Real <table><tr><td> so vertical-align:middle centers reliably
           in dompdf regardless of the logo image's actual aspect ratio */
        .logo-box {
            background: #ffffff;
            border-radius: 8px;
            width: 46px;
            height: 46px;
        }

        .logo-box td {
            width: 46px;
            height: 46px;
            text-align: center;
            vertical-align: middle;
            padding: 0;
        }

        .logo-box img {
            max-width: 30px;
            max-height: 30px;
        }

        .company-name {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 0.4px;
            color: #ffffff;
        }

        .doc-title {
            font-size: 10.5px;
            letter-spacing: 1.4px;
            color: #c9d8ff;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .doc-header .accent-tag {
            background-color: #35528f;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 9.5px;
            color: #ffffff;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        /* ============================================================
           INFO CARD
        ============================================================ */
        .info-card {
            background: #ffffff;
            border: 1px solid #e3e8f0;
            border-radius: 10px;
            padding: 12px 18px;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .info-card table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-card td {
            border: none;
            padding: 3px 0;
            font-size: 11.5px;
        }

        .info-label {
            color: #8a94a6;
            width: 110px;
        }

        .info-sep {
            width: 12px;
            color: #cbd5e0;
        }

        .info-value {
            font-weight: bold;
            color: #1a2540;
        }

        .info-pill {
            display: inline-block;
            background: #eef2ff;
            color: #2b57c9;
            border-radius: 20px;
            padding: 3px 12px;
            font-size: 10.5px;
            font-weight: bold;
        }

        /* ============================================================
           SECTION TITLES
        ============================================================ */
        .section-title {
            font-size: 12.5px;
            font-weight: bold;
            color: #16264a;
            margin: 0 0 8px;
            padding: 6px 0 6px 12px;
            border-left: 4px solid #2b57c9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section-title.deduction {
            border-left-color: #d1453b;
            color: #7a231d;
        }

        .section-title.info-section {
            border-left-color: #2ea36c;
            color: #14532d;
        }

        /* ============================================================
           TABLES (CARD STYLE)
        ============================================================ */
        .card-wrap {
            background: #ffffff;
            border: 1px solid #e3e8f0;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: #eef2ff;
            color: #16264a;
            text-transform: uppercase;
            font-size: 9.5px;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            text-align: left;
            border-bottom: 2px solid #d7e0ff;
        }

        .table.deduction th {
            background: #fdecea;
            color: #7a231d;
            border-bottom: 2px solid #f6cac4;
        }

        .table td {
            padding: 9px 12px;
            border-bottom: 1px solid #eef1f6;
            font-size: 11px;
            line-height: 1.2;
            color: #2d3748;
        }

        /* Pendapatan & Potongan tables specifically: a bit more breathing
           room between rows than the compact tables elsewhere in the doc */
        .two-col-layout .table th {
            padding: 8px 8px;
        }

        .two-col-layout .table td {
            padding: 8px 8px;
            line-height: 1.2;
        }

        .two-col-layout .total-row td {
            padding: 8px 8px;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .row-alt td {
            background: #f8fafc;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .total-row td {
            background: #eef2ff !important;
            font-weight: bold;
            font-size: 12px;
            color: #16264a;
            border-top: 2px solid #c7d5ff;
        }

        .total-row.deduction td {
            background: #fdecea !important;
            color: #7a231d;
            border-top: 2px solid #f6cac4;
        }

        .adjustment-note {
            font-size: 9.5px;
            color: #718096;
            margin-top: 4px;
            padding-left: 10px;
            border-left: 2px solid #d7e0ff;
        }

        /* ============================================================
           GRAND TOTAL
        ============================================================ */
        .grand-total-box {
            margin-top: 6px;
            background-color: #237a4e;
            border-radius: 12px;
            padding: 16px 22px;
            color: #ffffff;
        }

        .grand-total-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .grand-total-box td {
            border: none;
            padding: 0;
        }

        .grand-total-label {
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #d7f5e4;
        }

        .grand-total-amount {
            font-size: 20px;
            font-weight: bold;
            color: #ffffff;
        }

        /* ============================================================
           REKAP ABSENSI
        ============================================================ */
        .page-title {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            color: #16264a;
            margin: 6px 0 16px;
        }

        .page-title .bar {
            display: block;
            width: 46px;
            height: 3px;
            background: #2b57c9;
            border-radius: 4px;
            margin: 6px auto 0;
        }

        .holiday td {
            background: #fdecea !important;
        }

        .weekend td {
            background: #fff6e0 !important;
        }

        .absent td {
            background: #fff0c2 !important;
        }

        .status {
            font-weight: bold;
            text-align: center;
        }

        .status-badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 20px;
            font-size: 8.5px;
            font-weight: bold;
            color: #ffffff;
        }

        /* smaller/denser variant used on the Rekap Absensi table so the
           whole month fits more tightly per page */
        .table-compact th {
            padding: 5px 7px;
            font-size: 8.5px;
        }

        .table-compact td {
            padding: 4px 7px;
            font-size: 9.5px;
        }

        .badge-hadir { background: #2ea36c; }
        .badge-terlambat { background: #e08b1b; }
        .badge-lembur { background: #2b57c9; }
        .badge-libur { background: #a0aec0; }
        .badge-absen { background: #d1453b; }
        .badge-default { background: #718096; }

        .summary-card {
            background: #ffffff;
            border: 1px solid #e3e8f0;
            border-radius: 10px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        /* keeps a .section-title glued to the card/table right after it,
           so dompdf never leaves a heading orphaned at the bottom of a page */
        .section-group {
            page-break-inside: avoid;
        }

        /* Pendapatan + Potongan side by side instead of stacked, so the
           combined block is roughly half the height and Total Gaji
           Diterima comfortably fits on the same page underneath it */
        .two-col-layout {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
        }

        .two-col-layout td {
            border: none;
            padding: 0;
            vertical-align: top;
            width: 50%;
        }

        .two-col-layout tr {
            border: none;
            padding: 10;
            vertical-align: top;
            width: 50%;
        }

        .two-col-layout .col-left {
            padding-right: 8px;
            vertical-align: top;
        }

        .two-col-layout .col-right {
            padding-left: 8px;
            vertical-align: top;
        }

        .summary-card .table td:first-child {
            color: #4a5568;
        }

        .summary-card .table td:last-child {
            font-weight: bold;
            color: #16264a;
        }

        /* ============================================================
           IJIN DETAIL
        ============================================================ */
        .total-pill {
            display: inline-block;
            background: #eef2ff;
            color: #2b57c9;
            border-radius: 20px;
            padding: 3px 12px;
            font-size: 10.5px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <!-- ================================================================
         SLIP GAJI
    ================================================================= -->
    <div class="doc-header">
        <table>
            <tr>
                <td class="logo-cell">
                    <table class="logo-box" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>
                                <img src="{{ public_path('img/chutex_logo.png') }}" alt="logo">
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="text-cell">
                    <div class="company-name">PT CHUTEX INTERNATIONAL</div>
                    <div class="doc-title">Slip Gaji Karyawan</div>
                </td>
                <td style="width:120px; text-align:right;">
                    <span class="accent-tag">{{ $employee->period_name }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-card">
        <table>
            <tr>
                <td class="info-label">NPK</td>
                <td class="info-sep">:</td>
                <td class="info-value">{{ $employee->employee_npk }}</td>
            </tr>
            <tr>
                <td class="info-label">Nama</td>
                <td class="info-sep">:</td>
                <td class="info-value">{{ $employee->employee_name }}</td>
            </tr>
            <tr>
                <td class="info-label">Periode</td>
                <td class="info-sep">:</td>
                <td class="info-value">{{ $employee->period_name }}</td>
            </tr>
        </table>
    </div>

    <table class="two-col-layout">
        <tr>
            <td class="col-left">
                <div class="section-title">Pendapatan</div>
                <div class="card-wrap">
                    <table class="table">
                        <tr>
                            <th>Komponen</th>
                            <th width="90">Jumlah</th>
                        </tr>
                        @php $totalEarning = 0; @endphp
                        @foreach($earnings as $name => $value)
                            @php
                                $labels = [
                                    'sixs_insentif' => '6S Insentif',
                                    'night_shift_compensation' => 'Night Shift Compensation',
                                ];
                                $label = $labels[$name] ?? ucwords(str_replace('_', ' ', $name));
                            @endphp
                            <tr class="{{ $loop->even ? 'row-alt' : '' }}">
                                <td>
                                    {{ $label }}

                                    @if($name === 'adjusment' && count($adjusment_details))
                                        <div class="adjustment-note">
                                            @foreach($adjusment_details as $index => $detail)
                                                ({{ $index + 1 }})
                                                {{ $detail->keterangan }}
                                                :
                                                {{ number_format($detail->adjusment,0,',','.') }}

                                                @if(!$loop->last)
                                                    <br>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                <td class="right">
                                    {{ number_format($value, 0, ',', '.') }}
                                </td>
                            </tr>
                            @php $totalEarning += $value; @endphp
                        @endforeach
                        <tr class="total-row">
                            <td>Total Pendapatan</td>
                            <td class="right">{{ number_format($totalEarning, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td class="col-right">
                <div class="section-title deduction">Potongan</div>
                <div class="card-wrap">
                    <table class="table deduction">
                        <tr>
                            <th>Komponen</th>
                            <th width="90">Jumlah</th>
                        </tr>
                        @php $totalDeduction = 0; @endphp
                        @foreach($deductions as $name => $value)
                            <tr class="{{ $loop->even ? 'row-alt' : '' }}">
                                <td>{{ ucwords(str_replace('_', ' ', $name)) }}</td>
                                <td class="right">{{ number_format($value, 0, ',', '.') }}</td>
                            </tr>
                            @php $totalDeduction += $value; @endphp
                        @endforeach
                        <tr class="total-row deduction">
                            <td>Total Potongan</td>
                            <td class="right">{{ number_format($totalDeduction, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="grand-total-box">
        <table>
            <tr>
                <td class="grand-total-label">Total Gaji Diterima</td>
                <td class="right grand-total-amount">
                    Rp {{ number_format($employee->total_salary, 0, ',', '.') }}
                </td>
            </tr>
        </table>
    </div>


    <div class="page-break"></div>

    <!-- ================================================================
         REKAP ABSENSI
    ================================================================= -->
    <div class="doc-header">
        <table>
            <tr>
                <td class="logo-cell">
                    <table class="logo-box" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>
                                <img src="{{ public_path('img/chutex_logo.png') }}" alt="logo">
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="text-cell">
                    <div class="company-name">PT CHUTEX INTERNATIONAL</div>
                    <div class="doc-title">Rekap Absensi Karyawan</div>
                </td>
                <td style="width:120px; text-align:right;">
                    <span class="accent-tag">{{ $employee->period_name }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-card">
        <table>
            <tr>
                <td class="info-label">NPK</td>
                <td class="info-sep">:</td>
                <td class="info-value">{{ $employee->employee_npk }}</td>
            </tr>
            <tr>
                <td class="info-label">Nama</td>
                <td class="info-sep">:</td>
                <td class="info-value">{{ $employee->employee_name }}</td>
            </tr>
            <tr>
                <td class="info-label">Departement</td>
                <td class="info-sep">:</td>
                <td class="info-value">{{ $employee->DEPARTEMENT ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="card-wrap" style="margin-bottom:16px;">
        <table class="table table-compact">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Hari</th>
                    <th>Jam Masuk</th>
                    <th>Jam Pulang</th>
                    <th>Status</th>
                    <th>Overtime</th>
                </tr>
            </thead>
            <tbody>
            @foreach($attendance as $row)
                @php
                $date = \Carbon\Carbon::parse($row->tanggal);
                $hariIndo = [
                    'Sunday' => 'Minggu',
                    'Monday' => 'Senin',
                    'Tuesday' => 'Selasa',
                    'Wednesday' => 'Rabu',
                    'Thursday' => 'Kamis',
                    'Friday' => 'Jumat',
                    'Saturday' => 'Sabtu',
                ];
                $day = $hariIndo[$date->format('l')];
                $isWeekend = $date->isWeekend();
                $isHoliday = in_array($date->format('Y-m-d'), $holidays ?? []);
                $rowClass = '';
                if($isHoliday){
                    $rowClass = 'holiday';
                }
                elseif($isWeekend){
                    $rowClass = 'weekend';
                }
                elseif(!$row->jam_masuk && !$row->jam_pulang){
                    $rowClass = 'absent';
                }
                elseif($row->status === 'MA' || $row->status === 'BR' || $row->status === 'PE' || $row->status === 'Terlambat'){
                    $rowClass = 'absent';
                }

                $badgeClass = 'badge-default';
                $statusUpper = strtoupper($row->status);
                if(in_array($row->status, ['Hadir', 'Scan Masuk', 'Scan Pulang'])){
                    $badgeClass = 'badge-hadir';
                } elseif($row->status === 'Terlambat'){
                    $badgeClass = 'badge-terlambat';
                } elseif($row->status === 'Lembur'){
                    $badgeClass = 'badge-lembur';
                } elseif($row->status === 'Libur'){
                    $badgeClass = 'badge-libur';
                } elseif(in_array($row->status, ['MA', 'BR', 'P1', 'SD', 'CT', 'H', 'OUT', 'PE', 'Tidak Finger'])){
                    $badgeClass = 'badge-absen';
                }
                @endphp
                <tr class="{{ $rowClass }}">
                    <td class="center">{{ $date->format('d-m-Y') }}</td>
                    <td class="center">{{ $day }}</td>
                    <td class="center">{{ $row->jam_masuk ?? '-' }}</td>
                    <td class="center">{{ $row->jam_pulang ?? '-' }}</td>
                    <td class="status">
                        <span class="status-badge {{ $badgeClass }}">{{ $row->status }}</span>
                    </td>
                    <td class="center">{{ $row->overtime }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="section-group">
    <div class="section-title info-section">Ringkasan Absensi</div>
    <div class="summary-card">
        <table class="table">
            <tr>
                <td>Total Hadir</td>
                <td class="right">{{ $summary['hadir'] }}</td>
            </tr>
            <tr class="row-alt">
                <td>Lembur Resmi</td>
                <td class="right">{{ $summary['lembur_resmi'] }} Jam</td>
            </tr>
            <tr>
                <td>Lembur Khusus</td>
                <td class="right">{{ $summary['lembur_khusus'] }} Jam</td>
            </tr>
            @foreach($summary['status'] as $status => $count)
                <tr class="{{ $loop->even ? 'row-alt' : '' }}">
                    <td>{{ $status }}</td>
                    <td class="right">{{ $count }}</td>
                </tr>
            @endforeach
            <tr class="{{ (count($summary['status']) % 2 === 0) ? '' : 'row-alt' }}">
                <td>Total Terlambat</td>
                <td class="right">{{ $late_minutes }} Menit</td>
            </tr>
            <tr class="{{ (count($summary['status']) % 2 === 0) ? 'row-alt' : '' }}">
                <td>Total Ijin Meninggalkan Pekerjaan</td>
                <td class="right">{{ $total_ijin }} Menit</td>
            </tr>
        </table>
    </div>
    </div>

    <div class="page-break"></div>

    <!-- ================================================================
         DETAIL IJIN MENINGGALKAN PEKERJAAN
    ================================================================= -->
    <div class="doc-header">
        <table>
            <tr>
                <td class="logo-cell">
                    <table class="logo-box" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>
                                <img src="{{ public_path('img/chutex_logo.png') }}" alt="logo">
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="text-cell">
                    <div class="company-name">PT CHUTEX INTERNATIONAL</div>
                    <div class="doc-title">Detail Ijin Meninggalkan Pekerjaan</div>
                </td>
                <td style="width:120px; text-align:right;">
                    <span class="accent-tag">{{ $employee->period_name }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-card">
        <table>
            <tr>
                <td class="info-label">NPK</td>
                <td class="info-sep">:</td>
                <td class="info-value">{{ $employee->employee_npk }}</td>
            </tr>
            <tr>
                <td class="info-label">Nama</td>
                <td class="info-sep">:</td>
                <td class="info-value">{{ $employee->employee_name }}</td>
            </tr>
            <tr>
                <td class="info-label">Departement</td>
                <td class="info-sep">:</td>
                <td class="info-value">{{ $employee->DEPARTEMENT ?? '-' }}</td>
            </tr>
            <tr>
                <td class="info-label">Total Ijin</td>
                <td class="info-sep">:</td>
                <td class="info-value"><span class="total-pill">{{ $total_ijin }} Menit</span></td>
            </tr>
        </table>
    </div>

    <div class="card-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th width="90">Tanggal</th>
                    <th width="70">Keluar</th>
                    <th width="90">Rencana Kembali</th>
                    <th width="70">Kembali</th>
                    <th>Alasan</th>
                    <th width="70">Menit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ijin_details as $item)
                    <tr class="{{ $loop->even ? 'row-alt' : '' }}">
                        <td class="center">
                            {{ \Carbon\Carbon::parse($item->tanggal)->format('d-m-Y') }}
                        </td>
                        <td class="center">
                            {{ $item->jam_keluar }}
                        </td>
                        <td class="center">
                            {{ $item->rencana_kembali }}
                        </td>
                        <td class="center">
                            {{ $item->jam_kembali ?? '-' }}
                        </td>
                        <td>
                            {{ $item->reason }}
                        </td>
                        <td class="right">
                            {{ $item->ijin_minutes }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="center" style="padding:16px; color:#a0aec0;">
                            Tidak ada data ijin meninggalkan pekerjaan pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if(count($ijin_details))
                <tfoot>
                    <tr class="total-row">
                        <th colspan="5" class="right">
                            Total
                        </th>
                        <th class="right">
                            {{ $total_ijin }}
                        </th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</body>

</html>