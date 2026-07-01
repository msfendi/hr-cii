<!DOCTYPE html>
<html lang="en">
@php
$year = '';
$month = '';
$getTotalDays = 0;

if (count($employees) > 0) {
    foreach ($employees as $emp) {
        if ($emp->TANGGAL != null) {
            $firstDate = \Carbon\Carbon::parse($emp->TANGGAL);
            $year = $firstDate->format('Y');
            $month = $firstDate->format('m');
            $getTotalDays = $firstDate->daysInMonth;
            break;
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
        
        th, td {
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
        }
    </style>
</head>
<body>
    <div class="header">
        @if($getTotalDays > 0)
            <h2>Data Kehadiran Karyawan - {{ \Carbon\Carbon::createFromFormat('Y-m', $year . '-' . $month)->format('F Y') }}</h2>
        @endif
    </div>

    @php
                // Group by KODE_BAGIAN first
                $groupedByDept = collect($employees)->groupBy('KODE_BAGIAN');
                @endphp
            
            @foreach($groupedByDept as $dept => $deptEmployees)
            <div class="table-container">
                <table>
                <!-- Table Header for each Department -->
                <tr>
                    <th>Dept</th>
                    <th>NPK</th>
                    <th>Nama Karyawan</th>
                    @for($date = 1; $date <= $getTotalDays; $date++)
                        <th>{{ $date }}</th>
                    @endfor
                    <th>#</th>
                </tr>
                
                @php
                    // Group by NPK for employees in this department
                    $groupedByNpk = collect($deptEmployees)->groupBy('NPK');
                @endphp
                
                @foreach($groupedByNpk as $npk => $records)
                    @php
                        $firstRecord = $records->first();
                        
                        // Optimize lookup by indexing records by day
                        $recordsByDate = [];
                        foreach($records as $rec) {
                            if($rec->TANGGAL) {
                                $dayNum = (int)\Carbon\Carbon::parse($rec->TANGGAL)->format('d');
                                $recordsByDate[$dayNum] = $rec;
                            }
                        }

                        $isBrMap = [];
                        $isOutMap = [];
                        for($d = 1; $d <= $getTotalDays; $d++) {
                            $isBrMap[$d] = false;
                            $isOutMap[$d] = false;
                            
                            if (!empty($firstRecord->TMK)) {
                                $tmkCarbon = \Carbon\Carbon::parse($firstRecord->TMK);
                                if ($tmkCarbon->format('Y') == $year && $tmkCarbon->format('m') == $month) {
                                    if ($d < (int) $tmkCarbon->format('d')) $isBrMap[$d] = true;
                                } elseif ($tmkCarbon->format('Y-m') > $year . '-' . $month) {
                                    $isBrMap[$d] = true;
                                }
                            }
                            
                            if (!empty($firstRecord->TKK)) {
                                $tkkCarbon = \Carbon\Carbon::parse($firstRecord->TKK);
                                if ($tkkCarbon->format('Y') == $year && $tkkCarbon->format('m') == $month) {
                                    if ($d >= (int) $tkkCarbon->format('d')) $isOutMap[$d] = true;
                                } elseif ($tkkCarbon->format('Y-m') < $year . '-' . $month) {
                                    $isOutMap[$d] = true;
                                }
                            }
                        }
                    @endphp
                    
                    <!-- Row 1: Jam Masuk -->
                    <tr>
                        <td rowspan="3">{{ $firstRecord->SUBDIVISI }}</td>
                        <td rowspan="3">{{ $firstRecord->NPK }}</td>
                        <td rowspan="3" style="text-align: left; padding-left: 5px;">{{ $firstRecord->NAMA_KARYAWAN }}</td>
                        
                        @for($date = 1; $date <= $getTotalDays; $date++)
                            @php $record = $recordsByDate[$date] ?? null; @endphp
                            <td>
                                @if($isBrMap[$date] || $isOutMap[$date])
                                    -
                                @elseif($record)
                                    {{ $record->JAM_PAGI != null ? $record->JAM_PAGI : ($record->JAM_SIANG != null ? $record->JAM_SIANG : '-') }}
                                @else
                                    -
                                @endif
                            </td>
                        @endfor
                        <td>Jam Masuk</td>
                    </tr>
                    
                    <!-- Row 2: Jam Pulang -->
                    <tr>
                        @for($date = 1; $date <= $getTotalDays; $date++)
                            @php $record = $recordsByDate[$date] ?? null; @endphp
                            <td>
                                @if($isBrMap[$date] || $isOutMap[$date])
                                    -
                                @elseif($record)
                                    {{ $record->JAM_MALAM != null ? $record->JAM_MALAM : ($record->JAM_SIANG != null ? $record->JAM_SIANG : '-') }}
                                @else
                                    -
                                @endif
                            </td>
                        @endfor
                        <td>Jam Pulang</td>
                    </tr>
                    
                    <!-- Row 3: Status -->
                    <tr>
                        @for($date = 1; $date <= $getTotalDays; $date++)
                            @php 
                                $record = $recordsByDate[$date] ?? null;
                                $isWeekend = \Carbon\Carbon::createFromFormat('Y-m-d', $year . '-' . $month . '-' . sprintf('%02d', $date))->isWeekend();
                                $isLiburNasional = in_array($date, array_map('intval', $days));
                            @endphp
                            <td>
                                @if($isBrMap[$date])
                                    BR
                                @elseif($isOutMap[$date])
                                    OUT
                                @elseif($record)
                                    @if($isWeekend && ($record->JAM_PAGI != null || $record->JAM_SIANG != null || $record->JAM_MALAM != null))
                                        MSK
                                    @elseif($isWeekend && $record->KETERANGAN != 'CT')
                                        LBR
                                    @elseif($isLiburNasional)
                                        {{ $record->JAM_PAGI != null || $record->JAM_SIANG != null || $record->JAM_MALAM != null ? 'MSK' : 'LBR' }}
                                    @else
                                        {{ $record->KETERANGAN != null ? $record->KETERANGAN : (($record->JAM_PAGI != null || $record->JAM_SIANG != null || $record->JAM_MALAM != null) ? 'MSK' : 'MA') }}
                                    @endif
                                @else
                                    @if($isLiburNasional || $isWeekend)
                                        LBR
                                    @else
                                        MA
                                    @endif
                                @endif
                            </td>
                        @endfor
                        <td>Keterangan</td>
                    </tr>
                @endforeach
            </table>
        </div>
        @endforeach
</body>
</html>
