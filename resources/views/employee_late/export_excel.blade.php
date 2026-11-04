<table style="border-collapse:collapse; font-family:Arial,sans-serif; font-size:11px; width:100%;">
    <thead>
        <tr>
            <th colspan="7"
                style="border:1px solid #000; padding:6px; text-align:center; font-size:13px; font-weight:bold;">
                Laporan Keterlambatan Karyawan (Employee Late)<br>
                <span style="font-weight:normal; font-size:11px;">Periode: {{ $label }}</span>
            </th>
        </tr>
        <tr>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">No</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">NPK</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">Nama Karyawan</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">Departemen</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">Tanggal</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">Arrival Time</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">Reason</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data as $i => $row)
            <tr>
                <td style="border:1px solid #000; padding:4px; text-align:center;">{{ $i + 1 }}</td>
                <td style="border:1px solid #000; padding:4px;">{{ $row->npk }}</td>
                <td style="border:1px solid #000; padding:4px;">{{ $row->NAMA_KARYAWAN ?? '-' }}</td>
                <td style="border:1px solid #000; padding:4px;">{{ $row->DEPARTEMENT ?? '-' }}</td>
                <td style="border:1px solid #000; padding:4px; text-align:center;">
                    {{ $row->date ? \Carbon\Carbon::parse($row->date)->format('d-m-Y') : '-' }}
                </td>
                <td style="border:1px solid #000; padding:4px; text-align:center;">
                    {{ $row->arrival_time ? \Carbon\Carbon::parse($row->arrival_time)->format('H:i') : '-' }}
                </td>
                <td style="border:1px solid #000; padding:4px;">{{ $row->reason }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="border:1px solid #000; padding:8px; text-align:center;">
                    Tidak ada data untuk periode ini.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
