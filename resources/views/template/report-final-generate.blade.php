<!DOCTYPE html>
<html lang="en">
@php
    /**
     * ------------------------------------------------------------------
     * PRECOMPUTE STAGE
     * Everything that used to be re-parsed with Carbon inside nested
     * loops (per day, per employee) is now computed ONCE here.
     * This is the main fix for the "500 rows but very slow" issue.
     * ------------------------------------------------------------------
     */

    // Normalize holiday days to int once (was array_map('intval', $days) inside every helper call)
    $daysInt = array_map('intval', $days ?? []);

    // Find year / month / total days in month from the first record that has a TANGGAL
    $year = null;
    $month = null;
    $getTotalDays = null;

    foreach ($employees as $emp) {
        if (!empty($emp->TANGGAL)) {
            $first = \Carbon\Carbon::parse($emp->TANGGAL);
            $year = $first->format('Y');
            $month = $first->format('m');
            $getTotalDays = $first->daysInMonth;
            break;
        }
    }

    // Precompute weekend + holiday flag for every day-of-month ONCE (was recomputed per employee per day)
    $dayMeta = [];
    if ($year && $month) {
        for ($d = 1; $d <= $getTotalDays; $d++) {
            $dt = \Carbon\Carbon::createFromDate((int) $year, (int) $month, $d);
            $dayMeta[$d] = [
                'weekend' => $dt->isWeekend(),
                'holiday' => in_array($d, $daysInt, true),
            ];
        }
    }

    /**
     * Group the flat $employees list (already ordered by SUBDIVISI, NPK, TANGGAL)
     * into one bucket per NPK, keyed by day-of-month => record.
     * TMK / TKK are parsed with Carbon exactly ONCE per employee (not once per day).
     */
    $groups = [];

    foreach ($employees as $emp) {
        $npk = $emp->NPK;

        if (!isset($groups[$npk])) {
            $groups[$npk] = [
                'subdivisi' => $emp->SUBDIVISI,
                'nama' => $emp->NAMA_KARYAWAN,
                'npk' => $npk,
                'tmk_day' => null, // int day-of-month, 'after' (joins in a later month), or null (already joined before this month)
                'tkk_day' => null, // int day-of-month, 'before' (left before this month), or null (still active)
                'records' => [],   // day-of-month => employee record
            ];

            if (!empty($emp->TMK)) {
                $tmk = \Carbon\Carbon::parse($emp->TMK);
                if ($tmk->format('Y-m') == $year . '-' . $month) {
                    $groups[$npk]['tmk_day'] = (int) $tmk->format('d');
                } elseif ($tmk->format('Y-m') > $year . '-' . $month) {
                    $groups[$npk]['tmk_day'] = 'after';
                }
            }

            if (!empty($emp->TKK)) {
                $tkk = \Carbon\Carbon::parse($emp->TKK);
                if ($tkk->format('Y-m') == $year . '-' . $month) {
                    $groups[$npk]['tkk_day'] = (int) $tkk->format('d');
                } elseif ($tkk->format('Y-m') < $year . '-' . $month) {
                    $groups[$npk]['tkk_day'] = 'before';
                }
            }
        }

        if (!empty($emp->TANGGAL)) {
            $day = (int) \Carbon\Carbon::parse($emp->TANGGAL)->format('d');
            $groups[$npk]['records'][$day] = $emp;
        }
    }

    /**
     * Resolve what a single day-cell should show for a given employee group.
     * Replaces getEmptyCellHtml() + getActualKeterangan() — no Carbon calls in here at all,
     * everything needed was precomputed above.
     */
    if (!function_exists('resolveDayCell')) {
        function resolveDayCell($day, array $group, array $dayMeta)
        {
            // Not yet joined this month
            if ($group['tmk_day'] === 'after' || (is_int($group['tmk_day']) && $day < $group['tmk_day'])) {
                return ['lines' => ['-', 'BR']];
            }

            // Already left before/within this month
            if ($group['tkk_day'] === 'before' || (is_int($group['tkk_day']) && $day >= $group['tkk_day'])) {
                return ['lines' => ['-', 'OUT']];
            }

            $meta = $dayMeta[$day] ?? ['weekend' => false, 'holiday' => false];
            $record = $group['records'][$day] ?? null;

            if (!$record) {
                if ($meta['holiday'] || $meta['weekend']) {
                    return ['lines' => ['-', 'LBR']];
                }
                return ['lines' => ['-', '-', 'MA']];
            }

            $hasTime = $record->JAM_PAGI != null || $record->JAM_SIANG != null || $record->JAM_MALAM != null;

            if ($meta['weekend'] && $hasTime) {
                $keterangan = 'MSK';
            } elseif ($meta['weekend'] && $record->KETERANGAN != 'CT') {
                $keterangan = 'LBR';
            } elseif ($meta['holiday']) {
                $keterangan = $hasTime ? 'MSK' : 'LBR';
            } else {
                $keterangan = $record->KETERANGAN != null ? $record->KETERANGAN : ($hasTime ? 'MSK' : 'MA');
            }

            return [
                'jam_pagi' => $record->JAM_PAGI != null ? $record->JAM_PAGI : ($record->JAM_SIANG != null ? $record->JAM_SIANG : '-'),
                'jam_malam' => $record->JAM_MALAM != null ? $record->JAM_MALAM : ($record->JAM_SIANG != null ? $record->JAM_SIANG : '-'),
                'keterangan' => $keterangan,
            ];
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
        }
    </style>
</head>

<body>
    <div class="header">
        @if($year && $month)
            <h2>Data Kehadiran Karyawan - {{ \Carbon\Carbon::createFromDate((int) $year, (int) $month, 1)->format('F Y') }}
            </h2>
        @endif
    </div>

    <div class="table-container">
        <table>
            @php $tempSubdivisi = null; @endphp

            @foreach($groups as $npk => $group)
                {{-- Print a new header row only when SUBDIVISI actually changes --}}
                @if($group['subdivisi'] !== $tempSubdivisi)
                    <tr>
                        <th>Dept</th>
                        <th>NPK</th>
                        <th>Nama Karyawan</th>
                        @for($date = 1; $date <= $getTotalDays; $date++)
                            <th>{{ $date }}</th>
                        @endfor
                        <th>Keterangan</th>
                    </tr>
                    @php $tempSubdivisi = $group['subdivisi']; @endphp
                @endif

                <tr>
                    <td>{{ $group['subdivisi'] }}</td>
                    <td>{{ $group['npk'] }}</td>
                    <td class="employee-name">{{ $group['nama'] }}</td>

                    @for($day = 1; $day <= $getTotalDays; $day++)
                        @php $cell = resolveDayCell($day, $group, $dayMeta); @endphp
                        <td>
                            @if(isset($cell['keterangan']))
                                {{ $cell['jam_pagi'] }}
                                <br style="mso-data-placement:same-cell;" />
                                {{ $cell['jam_malam'] }}
                                <br style="mso-data-placement:same-cell;" />
                                {{ $cell['keterangan'] }}
                            @else
                                @foreach($cell['lines'] as $line)
                                    {{ $line }}
                                    <br style="mso-data-placement:same-cell;" />
                                @endforeach
                            @endif
                        </td>
                    @endfor

                    <td>Jam Masuk <br> Jam Pulang <br> Keterangan</td>
                </tr>
            @endforeach
        </table>
    </div>
</body>

</html>