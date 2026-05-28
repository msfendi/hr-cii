<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body>
    <table>
        @php
            $grouped = $kunjungans->groupBy(function ($item) {
                return $item->tanggal_kunjungan->format('Y-m-d');
            });
        @endphp

        @foreach($grouped as $date => $items)
            <tr>
                <td colspan="6" style="font-weight: bold; font-size: 14px; background-color: #d9edf7;">
                    Tanggal Kunjungan: {{ \Carbon\Carbon::parse($date)->format('d F Y') }}
                </td>
            </tr>
            <tr>
                <th style="font-weight: bold; text-align: center; background-color: #f2f2f2;">Jam Masuk</th>
                <th style="font-weight: bold; text-align: center; background-color: #f2f2f2;">Nama</th>
                <th style="font-weight: bold; text-align: center; background-color: #f2f2f2;">NPK</th>
                <th style="font-weight: bold; text-align: center; background-color: #f2f2f2;">Dept/Department</th>
                <th style="font-weight: bold; text-align: center; background-color: #f2f2f2;">Diagnosa</th>
                <th style="font-weight: bold; text-align: center; background-color: #f2f2f2;">Therapy (Obat &amp; Qty)</th>
            </tr>
            @foreach($items as $kunjungan)
                @php
                    $bio = $karyawanMap[$kunjungan->NPK] ?? null;
                    $nama = $kunjungan->nama ?? ($bio->NAMA_KARYAWAN ?? '-');
                    $dept = $kunjungan->dept ?? ($bio->DEPARTEMENT ?? '-');
                    $npk = $kunjungan->NPK ?? '-';
                    $jam = $kunjungan->jam_masuk ?? '-';
                    $diagnosa = $kunjungan->diagnosa ?? '-';

                    $therapy = [];
                    foreach ($kunjungan->resepObats as $resep) {
                        $t = $resep->keterangan_obat;
                        if ($resep->qty) {
                            $t .= ' (' . $resep->qty . ')';
                        }
                        $therapy[] = $t;
                    }
                    $therapyStr = implode(", \n", $therapy);
                    if (empty($therapyStr))
                        $therapyStr = '-';
                @endphp
                <tr>
                    <td style="vertical-align: top;">{{ $jam }}</td>
                    <td style="vertical-align: top;">{{ $nama }}</td>
                    <td style="vertical-align: top;">{{ $npk }}</td>
                    <td style="vertical-align: top;">{{ $dept }}</td>
                    <td style="vertical-align: top;">{{ $diagnosa }}</td>
                    <td style="vertical-align: top;">{!! e($therapyStr) !!}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="6"></td>
            </tr>
        @endforeach
    </table>
</body>

</html>