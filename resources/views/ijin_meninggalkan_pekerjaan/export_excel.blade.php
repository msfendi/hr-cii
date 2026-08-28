<table style="border-collapse:collapse; font-family:Arial,sans-serif; font-size:11px; width:100%;">
    <thead>
        <tr>
            <th colspan="11"
                style="border:1px solid #000; padding:6px; text-align:center; font-size:13px; font-weight:bold;">
                Laporan Ijin Meninggalkan Pekerjaan<br>
                <span style="font-weight:normal; font-size:11px;">Periode: {{ $label }}</span>
            </th>
        </tr>
        <tr>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">No</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">NPK</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">Nama Karyawan</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">Departemen</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">Tanggal</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">Jam Keluar</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">Rencana Kembali</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">Jam Kembali</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">Break</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1; text-align:center;">Potong Jam Kerja</th>
            <th style="border:1px solid #000; padding:4px; background:#DCE6F1;">Alasan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data as $i => $row)
            <tr>
                <td style="border:1px solid #000; padding:4px; text-align:center;">{{ $i + 1 }}</td>
                <td style="border:1px solid #000; padding:4px;">{{ $row->npk }}</td>
                <td style="border:1px solid #000; padding:4px;">{{ $row->NAMA_KARYAWAN }}</td>
                <td style="border:1px solid #000; padding:4px;">{{ $row->DEPARTEMENT }}</td>
                <td style="border:1px solid #000; padding:4px; text-align:center;">{{ $row->tanggal }}</td>
                <td style="border:1px solid #000; padding:4px; text-align:center;">
                    {{ $row->jam_keluar ? \Carbon\Carbon::parse($row->jam_keluar)->format('H:i') : '-' }}
                </td>
                <td style="border:1px solid #000; padding:4px; text-align:center;">
                    {{ $row->rencana_kembali ? \Carbon\Carbon::parse($row->rencana_kembali)->format('H:i') : '-' }}
                </td>
                <td style="border:1px solid #000; padding:4px; text-align:center;">
                    {{ $row->jam_kembali ? \Carbon\Carbon::parse($row->jam_kembali)->format('H:i') : '-' }}
                </td>
                <td style="border:1px solid #000; padding:4px; text-align:center;">
                    @if($row->sesi)
                        {{ $row->sesi }}
                        ({{ $row->time_start ? \Carbon\Carbon::parse($row->time_start)->format('H:i') : '-' }}
                        - {{ $row->time_end ? \Carbon\Carbon::parse($row->time_end)->format('H:i') : '-' }})
                    @else
                        -
                    @endif
                </td>
                <td style="border:1px solid #000; padding:4px; text-align:center;">
                    {{ $row->is_deduction ? 'Dipotong' : 'Tidak Dipotong' }}
                </td>
                <td style="border:1px solid #000; padding:4px;">{{ $row->reason }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="11" style="border:1px solid #000; padding:8px; text-align:center;">
                    Tidak ada data untuk periode ini.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>