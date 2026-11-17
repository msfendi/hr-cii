<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: A4 landscape; margin: 9mm 10mm; }
        body { font-family: "Times New Roman", Times, serif; font-size: 10pt; color: #111; line-height: 1.18; }
        .frame { border: 1px solid #111; height: 190mm; position: relative; padding-bottom: 11mm; }
        table { border-collapse: collapse; }
        .header { width: 100%; }
        .header td { vertical-align: middle; border-bottom: 1px solid #111; }
        .brand { width: 24%; text-align: center; border-right: 1px solid #111; padding: 3mm 2mm; }
        .title-cell { width: 44%; text-align: center; border-right: 1px solid #111; padding: 3mm 2mm; }
        .doc-cell { width: 32%; padding: 2mm 3mm; }
        .logo { width: 24mm; height: auto; display: block; margin: 0 auto 1mm; }
        .company { font-weight: bold; font-size: 9pt; line-height: 1.15; }
        .company span { display: block; font-weight: normal; }
        .title { font-size: 15pt; font-weight: bold; letter-spacing: .35pt; }
        .doc { font-size: 8pt; line-height: 1.5; }
        .doc b { display: inline-block; width: 25mm; }
        .body { width: 100%; }
        .body td { vertical-align: top; }
        .main { width: 100%; padding: 3mm 4mm 0; }
        .fields { width: 100%; }
        .fields td { vertical-align: bottom; padding: 2mm 1mm 1mm; }
        .no { width: 7mm; white-space: nowrap; }
        .label { white-space: nowrap; }
        .entry { border-bottom: .65pt solid #222; padding: 0 1.2mm 1mm; height: 7mm; }
        .entry-name { width: 66%; }
        .entry-npk { width: 23%; }
        .entry-wide { width: 87%; }
        .entry-third { width: 25%; }
        .entry-date { width: 88%; min-height: 8mm; }
        .entry-long { width: 88%; min-height: 8mm; }
        .shift-row td { padding-bottom: 1.8mm; }
        .time-block { width: 100%; margin-top: .7mm; }
        .time-block td { vertical-align: bottom; padding: 1.3mm 1mm; }
        .time-label { width: 42mm; }
        .time-field { width: 32mm; text-align: center; border-bottom: .65pt solid #222; padding: 0 1mm 1mm; }
        .time-note { font-size: 8pt; padding-left: 1.5mm !important; }
        .sig-intro { width: 100%; margin-top: 2mm; }
        .sig-intro td { height: 8mm; vertical-align: bottom; padding: 0 2mm 1mm; }
        .sig-intro-left { width: 50%; text-align: center; }
        .sig-intro-right { width: 50%; text-align: center; }
        .sig-table { width: 100%; table-layout: fixed; }
        .sig-table td { border: .8pt solid #111; text-align: center; vertical-align: top; }
        .sig-space { height: 19mm; }
        .sig-label { height: 9mm; font-size: 8.8pt; font-weight: bold; vertical-align: middle !important; padding: 1.5mm 1mm; }
        .sig-name { min-height: 8mm; font-size: 8pt; line-height: 1.2; padding: 1.2mm 1mm; }
        .sig-name .meta { font-size: 7pt; }
        .ok { color: #146c43; font-weight: bold; }
        .no-decision { color: #a12622; font-weight: bold; }
        .notes { margin: 2.5mm 3mm 0; font-size: 8pt; line-height: 1.3; }
        .notes b { font-size: 8.5pt; }
        .foot { position: absolute; left: 3mm; right: 3mm; bottom: 1.5mm; text-align: center; font-size: 7pt; color: #444; }
    </style>
</head>
<body>
<div class="frame">
    <table class="header">
        <tr>
            <td class="brand">
                @if (!empty($logoPath) && file_exists($logoPath))
                    <img src="{{ $logoPath }}" class="logo" alt="Chutex">
                @endif
                <div class="company">PT. CHUTEX INTERNATIONAL<span>INDONESIA</span></div>
            </td>
            <td class="title-cell"><div class="title">{{ $title }}</div></td>
            <td class="doc-cell">
                <div class="doc">
                    <div><b>No. Dok</b>: {{ $doc_no }}</div>
                    <div><b>Active Date</b>: {{ $active_date }}</div>
                    <div><b>Rev</b>: {{ $rev }}</div>
                    <div><b>Rev Date</b>: {{ $rev_date }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="main">
        <table class="fields">
            <tr>
                <td class="no" style="width:6mm">1.</td><td class="label" style="width:22mm">Nama :</td>
                <td class="entry" style="width:78mm">{{ $nama }}</td><td></td>
                <td class="label" style="width:13mm">NPK :</td><td class="entry" style="width:42mm">{{ $npk }}</td><td></td>
            </tr>
            <tr>
                <td class="no">2.</td><td class="label">Jabatan :</td>
                <td colspan="5" class="entry entry-wide">{{ $jabatan }}</td>
            </tr>
            <tr class="shift-row">
                <td class="no">3.</td><td class="label">Bagian :</td>
                <td class="entry" style="width:44mm">{{ $bagian }}</td>
                <td class="label" style="width:15mm">Shift :</td><td class="entry" style="width:34mm">{{ $shift }}</td>
                <td class="label" style="width:26mm">Departemen :</td><td class="entry">{{ $departemen }}</td>
            </tr>
            <tr>
                <td class="no">4.</td><td class="label">Hari / Tgl :</td>
                <td colspan="5" class="entry entry-date">{{ $tanggal }}</td>
            </tr>
            <tr>
                <td class="no">5.</td><td class="label">Dinas ke :</td>
                <td colspan="5" class="entry entry-long">{{ $dinas_ke }}</td>
            </tr>
            <tr>
                <td class="no">6.</td><td class="label">Keperluan :</td>
                <td colspan="5" class="entry entry-long">{{ $keperluan }}</td>
            </tr>
        </table>

        <table class="time-block">
            <tr>
                <td class="no">7.</td>
                <td class="time-label">Jam keluar / rencana kembali :</td>
                <td class="time-field">{{ $jam_keluar }}</td>
                <td style="width:10mm;text-align:center">S/D</td>
                <td class="time-field">{{ $rencana_kembali }}</td>
                <td class="time-note">WIB (Diisi Departemen)</td>
            </tr>
            <tr>
                <td></td><td class="time-label">Jam kembali :</td>
                <td class="time-field">{{ $jam_kembali }}</td>
                <td colspan="3" class="time-note">WIB (Diisi Pos I)</td>
            </tr>
        </table>

        <table class="sig-intro">
            <tr>
                <td class="sig-intro-left">Mengetahui</td>
                <td class="sig-intro-right">{{ $kota }}</td>
            </tr>
        </table>
        <table class="sig-table">
            <tr>@foreach ($signatories as $sig)<td><div class="sig-space"></div></td>@endforeach</tr>
            <tr>@foreach ($signatories as $sig)<td class="sig-label">{{ $sig['label'] }}</td>@endforeach</tr>
            <tr>
                @foreach ($signatories as $sig)
                    <td class="sig-name">
                        @if ($sig['name'])
                            {{ $sig['name'] }}<br>
                            <span class="meta">{{ $sig['npk'] }}@if ($sig['jabatan']) · {{ $sig['jabatan'] }}@endif</span>
                            @if ($sig['decision'])
                                <br><span class="{{ $sig['decision'] === 'Disetujui' ? 'ok' : 'no-decision' }}">{{ $sig['decision'] }}@if ($sig['decided_at']) ({{ $sig['decided_at'] }})@endif</span>
                            @endif
                        @else
                            <span style="color:#888">&mdash;</span>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>

        <div class="notes">
            <b>Keterangan:</b><br>
            Harus ditandatangani oleh G.M / Manager yang bersangkutan<br>
            Dalam hal Manager tidak ada di tempat, Surat Ijin Dinas Luar ditandatangani oleh pejabat dibawahnya atau yang ditunjuk.
        </div>
    </div>
    <div class="foot">{{ $doc_no }} · Rev {{ $rev }} · {{ $title }} · No. Pengajuan #{{ $id ?? '' }}</div>
</div>
</body>
</html>
