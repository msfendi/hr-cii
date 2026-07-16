<!DOCTYPE html>
<html lang="en">
@php
    $keterangan = '-';
    $jamMasuk = '-';
    $jamPulang = '-';
    $deptBefore = '';
    $npkBefore = '';

    $getTotalDays = null;
    $getTanggal = false;
    $sameNPK = false;
    $tempNPK = '';
    $tempKODE = '';
    $year = '';
    $month = '';
    $tidakFinger = 0;
    $anomali = false;

    $loopDays = 1;

    $lastDate = 0;

    if (!function_exists('getEmptyCellHtml')) {
        function getEmptyCellHtml($dayNum, $employee, $year, $month, $days)
        {
            $isBr = false;
            $isOut = false;

            if (!empty($employee->TMK)) {
                $tmkCarbon = \Carbon\Carbon::parse($employee->TMK);
                if ($tmkCarbon->format('Y') == $year && $tmkCarbon->format('m') == $month) {
                    if ($dayNum < (int) $tmkCarbon->format('d')) {
                        $isBr = true;
                    }
                } elseif ($tmkCarbon->format('Y-m') > $year . '-' . $month) {
                    $isBr = true;
                }
            }

            if (!empty($employee->TKK)) {
                $tkkCarbon = \Carbon\Carbon::parse($employee->TKK);
                if ($tkkCarbon->format('Y') == $year && $tkkCarbon->format('m') == $month) {
                    if ($dayNum >= (int) $tkkCarbon->format('d')) {
                        $isOut = true;
                    }
                } elseif ($tkkCarbon->format('Y-m') < $year . '-' . $month) {
                    $isOut = true;
                }
            }

            if ($isBr) {
                return '<td> - <br style="mso-data-placement:same-cell;" /> BR <br style="mso-data-placement:same-cell;" /> </td>';
            } elseif ($isOut) {
                return '<td> - <br style="mso-data-placement:same-cell;" /> OUT <br style="mso-data-placement:same-cell;" /> </td>';
            } elseif (in_array((int) $dayNum, array_map('intval', $days)) || \Carbon\Carbon::createFromFormat('Y-m-d', $year . '-' . $month . '-' . $dayNum)->isWeekend()) {
                return '<td> - <br style="mso-data-placement:same-cell;" /> LBR <br style="mso-data-placement:same-cell;" /> </td>';
            } else {
                return '<td>-<br style="mso-data-placement:same-cell;" /> - <br style="mso-data-placement:same-cell;" /> MA <br style="mso-data-placement:same-cell;" /></td>';
            }
        }
    }

    if (!function_exists('getActualKeterangan')) {
        function getActualKeterangan($dayNum, $employee, $year, $month, $days)
        {
            if (!empty($employee->TMK)) {
                $tmkCarbon = \Carbon\Carbon::parse($employee->TMK);
                if ($tmkCarbon->format('Y') == $year && $tmkCarbon->format('m') == $month) {
                    if ($dayNum < (int) $tmkCarbon->format('d'))
                        return 'BR';
                } elseif ($tmkCarbon->format('Y-m') > $year . '-' . $month) {
                    return 'BR';
                }
            }

            if (!empty($employee->TKK)) {
                $tkkCarbon = \Carbon\Carbon::parse($employee->TKK);
                if ($tkkCarbon->format('Y') == $year && $tkkCarbon->format('m') == $month) {
                    if ($dayNum >= (int) $tkkCarbon->format('d'))
                        return 'OUT';
                } elseif ($tkkCarbon->format('Y-m') < $year . '-' . $month) {
                    return 'OUT';
                }
            }

            $isWeekend = \Carbon\Carbon::createFromFormat('Y-m-d', $year . '-' . $month . '-' . $dayNum)->isWeekend();
            $isHoliday = in_array((int) $dayNum, array_map('intval', $days));
            $hasTime = $employee->JAM_PAGI != null || $employee->JAM_SIANG != null || $employee->JAM_MALAM != null;

            if ($isWeekend && $hasTime) {
                return 'MSK';
            } elseif ($isWeekend && $employee->KETERANGAN != 'CT') {
                return 'LBR';
            } elseif ($isHoliday) {
                return $hasTime ? 'MSK' : 'LBR';
            } else {
                return $employee->KETERANGAN != null ? $employee->KETERANGAN : ($hasTime ? 'MSK' : 'MA');
            }
        }
    }

@endphp

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kehadiran Karyawan</title>
    <style>
        @page {
            size: 13in 8.5in;
            margin: 20px;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12px;
            width: 13in;
            height: 8.5in;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0;
            font-size: 18px;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 auto;
        }

        tr {
            page-break-inside: avoid;
        }

        th,
        td {
            border: 1px solid black;
            padding: 3px;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background-color: black;
            color: white;
            font-weight: bold;
            font-size: 11px;
            min-width: 28px;
        }

        td {
            font-size: 10px;
            min-width: 28px;
        }

        .employee-name {
            text-align: left;
            padding-left: 5px;
            min-width: 100px;
            font-size: 11px;
        }

        .employee-nip {
            min-width: 80px;
            font-size: 10px;
        }

        .date-cell {
            line-height: 1.2;
        }

        .status {
            font-weight: bold;
            margin-bottom: 2px;
        }

        .time {
            font-size: 8px;
            color: #666;
            margin: 1px 0;
        }

        @media print {
            body {
                margin: 10px;
                font-size: 10px;
            }

            .header h2 {
                font-size: 16px;
            }

            th {
                font-size: 9px;
                padding: 2px;
                background-color: black;
                color: white;
                font-weight: bold;
                print-color-adjust: exact;
            }

            td {
                font-size: 8px;
                padding: 2px;
            }

            .employee-name {
                font-size: 9px;
            }

            .time {
                font-size: 7px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        @for($i = 0; $i < 31; $i++)
            @if(isset($employees[$i]) && $employees[$i]->TANGGAL != null && $getTanggal == false)
                <h2>Data Kehadiran Karyawan - {{\Carbon\Carbon::parse($employees[0]->TANGGAL)->format('F Y')}}</h2>
                @php
                    $getTotalDays = \Carbon\Carbon::parse($employees[$i]->TANGGAL)->daysInMonth;
                    $getTanggal = true;
                    $year = \Carbon\Carbon::parse($employees[$i]->TANGGAL)->format('Y');
                    $month = \Carbon\Carbon::parse($employees[$i]->TANGGAL)->format('m');
                @endphp
            @endif
        @endfor
    </div>

    <div class="table-container">
        <table>
            @for($i = 0; $i < count($employees); $i++)
                <!-- NPK BEDA -->
                @if($tempNPK != $employees[$i]->NPK)
                    <!-- KARYAWAN KELUAR TIDAK SAMPAI TUTUP BUKU -->
                    @if(($lastDate < $getTotalDays) && $lastDate != 0)
                        @for($sisa = $lastDate + 1; $sisa <= $getTotalDays; $sisa++)
                            {!! getEmptyCellHtml($sisa, $employees[$i - 1], $year, $month, $days) !!}
                        @endfor
                        @php
                            $lastDate = 0;
                        @endphp
                    @endif
                    <!-- KARYAWAN MASUK SETELAH BUKA BUKU -->
                    @if($i > 1)
                        <td>Jam Masuk <br> Jam Pulang <br> Keterangan </td>
                    @endif
                    <!-- KODE BAGIAN BEDA -->
                    @if($tempKODE != $employees[$i]->KODE_BAGIAN)
                        <tr>
                            <th>Dept</th>
                            <th>NPK</th>
                            <th>Nama Karyawan</th>
                            @for($date = 1; $date <= $getTotalDays; $date++)
                                <th>{{ $date }}</th>
                            @endfor
                            <th>Keterangan</th>
                        </tr>
                    @else
                        @php
                            $tempKODE = $employees[$i]->KODE_BAGIAN;
                        @endphp
                    @endif
                    <tr>
                        <td>
                            {{ $employees[$i]->SUBDIVISI }}
                        </td>
                        <td>
                            {{ $employees[$i]->NPK }}
                        </td>
                        <td>
                            {{ $employees[$i]->NAMA_KARYAWAN }}
                        </td>

                        <!-- JIKA NPK BEDA DAN TANGGAL PERTAMA ADALAH 1 -->
                        @if((int) \Carbon\Carbon::parse($employees[$i]->TANGGAL)->format('d') == 1)
                            <td>
                                <!-- <div class="mb-2"> -->
                                {{$employees[$i]->JAM_PAGI != null ? $employees[$i]->JAM_PAGI : ($employees[$i]->JAM_SIANG != null ? $employees[$i]->JAM_SIANG : '-')}}
                                <!-- </div> -->
                                <br style="mso-data-placement:same-cell;" />
                                <!-- {{-- <div class="mb-2"> --}} -->
                                {{$employees[$i]->JAM_MALAM != null ? $employees[$i]->JAM_MALAM : ($employees[$i]->JAM_SIANG != null ? $employees[$i]->JAM_SIANG : '-')}}
                                <!-- {{-- </div> --}} -->
                                <br style="mso-data-placement:same-cell;" />

                                {{ getActualKeterangan((int) \Carbon\Carbon::parse($employees[$i]->TANGGAL)->format('d'), $employees[$i], $year, $month, $days) }}
                            </td>
                            <!-- ANOMALI 1 TANGGAL -->
                            @if(isset($employees[$i + 1]) && $employees[$i]->NPK != $employees[$i + 1]->NPK)
                                @for($sisa = 2; $sisa <= $getTotalDays; $sisa++)
                                    {!! getEmptyCellHtml($sisa, $employees[$i], $year, $month, $days) !!}
                                @endfor
                            @endif
                        @else
                            @for($dayNum = 1; $dayNum < (int) \Carbon\Carbon::parse($employees[$i]->TANGGAL)->format('d'); $dayNum++)
                                {!! getEmptyCellHtml($dayNum, $employees[$i], $year, $month, $days) !!}
                            @endfor
                            <td>
                                <!-- <div class="mb-2"> -->
                                {{$employees[$i]->JAM_PAGI != null ? $employees[$i]->JAM_PAGI : ($employees[$i]->JAM_SIANG != null ? $employees[$i]->JAM_SIANG : '-')}}
                                <!-- </div> -->
                                <br style="mso-data-placement:same-cell;" />
                                <!-- {{-- <div class="mb-2"> --}} -->
                                {{$employees[$i]->JAM_MALAM != null ? $employees[$i]->JAM_MALAM : ($employees[$i]->JAM_SIANG != null ? $employees[$i]->JAM_SIANG : '-')}}
                                <!-- {{-- </div> --}} -->
                                <br style="mso-data-placement:same-cell;" />

                                {{ getActualKeterangan((int) \Carbon\Carbon::parse($employees[$i]->TANGGAL)->format('d'), $employees[$i], $year, $month, $days) }}
                            </td>
                        @endif

                        <!-- PINDAH NPK -->
                        @php
                            $tempNPK = $employees[$i]->NPK;
                            $tempKODE = $employees[$i]->KODE_BAGIAN;
                        @endphp
                @else
                        <!-- NPK SAMA -->
                        @php
                            $tidakFinger = (int) \Carbon\Carbon::parse($employees[$i]->TANGGAL)->format('d') - (int) \Carbon\Carbon::parse($employees[$i - 1]->TANGGAL)->format('d');
                        @endphp
                        @php
                            $prevDay = (int) \Carbon\Carbon::parse($employees[$i - 1]->TANGGAL)->format('d');
                        @endphp
                        @for($dayNum = $prevDay + 1; $dayNum < (int) \Carbon\Carbon::parse($employees[$i]->TANGGAL)->format('d'); $dayNum++)
                            {!! getEmptyCellHtml($dayNum, $employees[$i], $year, $month, $days) !!}
                        @endfor
                        @php
                            $tidakFinger = 0;
                        @endphp
                        <td>
                            <!-- <div class="mb-2"> -->
                            {{$employees[$i]->JAM_PAGI != null ? $employees[$i]->JAM_PAGI : ($employees[$i]->JAM_SIANG != null ? $employees[$i]->JAM_SIANG : '-')}}
                            <!-- </div> -->
                            <br style="mso-data-placement:same-cell;" />
                            <!-- {{-- <div class="mb-2"> --}} -->
                            {{$employees[$i]->JAM_MALAM != null ? $employees[$i]->JAM_MALAM : ($employees[$i]->JAM_SIANG != null ? $employees[$i]->JAM_SIANG : '-')}}
                            <!-- {{-- </div> --}} -->
                            <br style="mso-data-placement:same-cell;" />

                            {{ getActualKeterangan((int) \Carbon\Carbon::parse($employees[$i]->TANGGAL)->format('d'), $employees[$i], $year, $month, $days) }}
                        </td>
                        @php
                            $lastDate = (int) \Carbon\Carbon::parse($employees[$i]->TANGGAL)->format('d');
                        @endphp
                        @if($i == count($employees) - 1)
                            <td>Jam Masuk <br> Jam Pulang <br> Keterangan </td>
                        @endif
                    @endif
            @endfor
                <!-- UNTUK KARYAWAN TERAKHIR, JIKA BELUM SAMPAI AKHIR BULAN -->
                @if($lastDate > 0 && $lastDate < $getTotalDays && count($employees) > 0)
                    @for($sisa = $lastDate + 1; $sisa <= $getTotalDays; $sisa++)
                        {!! getEmptyCellHtml($sisa, $employees[count($employees) - 1], $year, $month, $days) !!}
                    @endfor
                    <td>Jam Masuk <br> Jam Pulang <br> Keterangan </td>
                @endif
            </tr>
        </table>
    </div>
</body>

</html>