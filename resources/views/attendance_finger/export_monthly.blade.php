<table style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px;">
    <thead>
        <tr>
            <th colspan="{{ 5 + count($dates) }}"
                style="border:1px solid #000; padding:6px; text-align:center; font-size:14px;">
                Laporan Attendance - {{ $deptLabel }}<br>
                <span style="font-weight:normal; font-size:11px;">Periode: {{ $periodLabel }}</span>
            </th>
        </tr>
        <tr>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">No</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">NPK</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">Nama</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">Bagian</th>
            @foreach ($dates as $date)
                <th style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">
                    {{ \Carbon\Carbon::parse($date)->translatedFormat('d') }}
                </th>
            @endforeach
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($employees as $i => $emp)
            <tr>
                <td rowspan="2" style="border:1px solid #000; padding:4px; text-align:center; vertical-align:middle;">
                    {{ $i + 1 }}
                </td>
                <td rowspan="2" style="border:1px solid #000; padding:4px; vertical-align:middle;">{{ $emp['npk'] }}</td>
                <td rowspan="2" style="border:1px solid #000; padding:4px; vertical-align:middle;">{{ $emp['nama'] }}</td>
                <td rowspan="2" style="border:1px solid #000; padding:4px; vertical-align:middle;">{{ $emp['bagian'] }}</td>
                @foreach ($dates as $date)
                    @php $att = $emp['attendance'][$date] ?? ['masuk' => 'not scanned']; @endphp
                    <td
                        style="border:1px solid #000; padding:4px; text-align:center;{{ ($att['is_late'] ?? false) ? ' color:#dc3545; font-weight:bold;' : '' }}">
                        {{ $att['masuk'] }}
                    </td>
                @endforeach
                <td style="border:1px solid #000; padding:4px; text-align:center; font-weight:bold;">Masuk</td>
            </tr>
            <tr>
                @foreach ($dates as $date)
                    @php $att = $emp['attendance'][$date] ?? ['pulang' => 'not scanned']; @endphp
                    <td style="border:1px solid #000; padding:4px; text-align:center;">{{ $att['pulang'] }}</td>
                @endforeach
                <td style="border:1px solid #000; padding:4px; text-align:center; font-weight:bold;">Pulang</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ 5 + count($dates) }}" style="border:1px solid #000; padding:8px; text-align:center;">
                    Tidak ada data untuk filter ini.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>