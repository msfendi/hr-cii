<table style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px;">
    <thead>
        <tr>
            <th rowspan="2" style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">No</th>
            <th rowspan="2" style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">NPK</th>
            <th rowspan="2" style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">Nama</th>
            <th rowspan="2" style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">Bagian
            </th>
            @foreach ($dates as $date)
                <th colspan="2" style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">
                    {{ \Carbon\Carbon::parse($date)->translatedFormat('d M') }}
                </th>
            @endforeach
        </tr>
        <tr>
            @foreach ($dates as $date)
                <th style="border:1px solid #000; padding:4px; background:#EAF1FB; text-align:center;">Masuk</th>
                <th style="border:1px solid #000; padding:4px; background:#EAF1FB; text-align:center;">Pulang</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($employees as $i => $emp)
            <tr>
                <td style="border:1px solid #000; padding:4px; text-align:center;">{{ $i + 1 }}</td>
                <td style="border:1px solid #000; padding:4px;">{{ $emp['npk'] }}</td>
                <td style="border:1px solid #000; padding:4px;">{{ $emp['nama'] }}</td>
                <td style="border:1px solid #000; padding:4px;">{{ $emp['bagian'] }}</td>
                @foreach ($dates as $date)
                    @php
                        $att = $emp['attendance'][$date] ?? ['masuk' => 'not scanned', 'pulang' => 'not scanned'];
                    @endphp
                    <td style="border:1px solid #000; padding:4px; text-align:center;">{{ $att['masuk'] }}</td>
                    <td style="border:1px solid #000; padding:4px; text-align:center;">{{ $att['pulang'] }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ 4 + count($dates) * 2 }}" style="border:1px solid #000; padding:8px; text-align:center;">
                    Tidak ada data expat pada rentang tanggal ini.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>