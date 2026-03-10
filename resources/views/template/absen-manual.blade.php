<!DOCTYPE html>
<html lang="id">
@php
    date_default_timezone_set("Asia/Jakarta");
    $isLibur = false;
    $array = json_decode(file_get_contents("https://raw.githubusercontent.com/guangrei/APIHariLibur_V2/main/calendar.json"),true);

    $tempNPK = '';
    $tempDEPT = '';
    $no = 1;
    $currentYear = date("Y");
    $currentMonth = date("m");
    $getTotalDays = \Carbon\Carbon::parse(date("Y-m-d"))->daysInMonth;
@endphp
<head>
    <meta charset="UTF-8">
    <title>Absensi Karyawan PT. CHUTEX</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 10px;
        }

        /* Layout Kop Atas */
        .header-top {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        .header-top td {
            border: none;
            padding: 2px;
            vertical-align: top;
        }
        .company-name { font-weight: bold; }
        .att-date { width: 20px; }
        .dots { border-bottom: 1px dotted black; display: inline-block; width: 150px; }

        /* Style Tabel Utama */
        table.main-table {
            border-collapse: collapse;
            width: 100%;
        }
        .main-table th, .main-table td {
            border: 1px solid black;
            text-align: center;
            height: 18px;
            padding: 0 2px;
        }

        /* Kolom Khusus */
        .col-nama { text-align: left; padding-left: 5px; width: 180px; }
        .col-npk { width: 60px; }
        .col-no { width: 25px; }
        .col-bagian { width: 60px; }
        .col-tgl { width: 20px; }

        /* Warna Merah (Hari Libur) */
        .bg-red { background-color: #ff0000 !important; }

        /* Grouping Headers */
        .header-month { font-weight: bold; }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

@for($i = 0; $i < count($employees); $i++)
    @if($tempDEPT != $employees[$i]->DEPARTEMENT)
    @php
        $no = 1;
    @endphp
    @if($i > 0)
        <!-- ISI SISA KOSONG TEMPLATE SAMPAI 30 BARIS -->
        @for($sisa = $no; $sisa <= 30; $sisa++)
            <tr>
                <td>{{ $sisa }}</td>
                <td></td>
                <td class="col-nama"></td>
                <td></td>
                <!-- JUMLAH HARI -->
                @for($date = 0; $date < $getTotalDays; $date++)
                    @php
                        $originalDate = $currentYear . '-' . $currentMonth . '-' . ($date + 1); 
                        $unixTime = strtotime($originalDate); 
                        $newDate = date("Y-m-d", $unixTime);
                    @endphp
                    @if(isset($array[$newDate]) && $array[$newDate]["holiday"])
                        @php
                            $isLibur = true;
                        @endphp
                    @elseif (date("D",strtotime($newDate))==="Sun" || date("D",strtotime($newDate))==="Sat")
                        @php
                            $isLibur = true;
                        @endphp
                    @else
                        @php
                            $isLibur = false;
                        @endphp
                    @endif
                    @if ($isLibur)
                        <td class="bg-red" style="background-color: #ff0000;"></td>
                    @else
                        <td></td>
                    @endif
                @endfor
            </tr>
        @endfor
        </tbody>
    </table>
    <div class='page-break'></div>
    <br>
    @endif
    <table class="header-top">
        <tr>
            <td class="company-name" style="width: 30%;">PT. CHUTEX INTERNATIONAL INDONESIA</td>
            <td style="width: 40%;">CHIEF : <span class="dots"></span></td>
            <td style="width: 30%; text-align: right;"></td>
        </tr>
        <tr>
            <td class="company-name">ABSENSI KARYAWAN</td>
            <td>SPV : <span class="dots"></span></td>
            <td>ADM : <span class="dots"></span> <span style="margin-left: 20px;">{{$employees[$i]->DEPARTEMENT}}</span></td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-no">NO.</th>
                <th rowspan="2" class="col-npk">NPK</th>
                <th rowspan="2" class="col-nama">NAMA</th>
                <th rowspan="2" class="col-bagian">BAGIAN</th>
                <th colspan="<?php echo $getTotalDays; ?>" class="header-month"><?php echo date("M-Y"); ?></th>
            </tr>
            <tr>
                @for($date = 0; $date < $getTotalDays; $date++)
                    @php
                        $originalDate = $currentYear . '-' . $currentMonth . '-' . ($date + 1); 
                        $unixTime = strtotime($originalDate); 
                        $newDate = date("Y-m-d", $unixTime);
                    @endphp
                    @if(isset($array[$newDate]) && $array[$newDate]["holiday"])
                        @php
                            $isLibur = true;
                        @endphp
                    @elseif (date("D",strtotime($newDate))==="Sun" || date("D",strtotime($newDate))==="Sat")
                        @php
                            $isLibur = true;
                        @endphp
                    @else
                        @php
                            $isLibur = false;
                        @endphp
                    @endif
                    @if ($isLibur)
                        <td class="bg-red" style="background-color: #ff0000;">{{ $date + 1 }}</td>
                    @else
                        <td>{{ $date + 1 }}</td>
                    @endif
                @endfor
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $no }}</td>
                <td>{{ $employees[$i]->NPK }}</td>
                <td class="col-nama" style="text-align: left;padding-left: 5px;">{{ $employees[$i]->NAMA_KARYAWAN }}</td>
                <td>{{ $employees[$i]->DEPARTEMENT }}</td>
                <!-- JUMLAH HARI -->
                @for($date = 0; $date < $getTotalDays; $date++)
                        @php
                            $originalDate = $currentYear . '-' . $currentMonth . '-' . ($date + 1); 
                            $unixTime = strtotime($originalDate); 
                            $newDate = date("Y-m-d", $unixTime);
                        @endphp
                        @if(isset($array[$newDate]) && $array[$newDate]["holiday"])
                            @php
                                $isLibur = true;
                            @endphp
                        @elseif (date("D",strtotime($newDate))==="Sun" || date("D",strtotime($newDate))==="Sat")
                            @php
                                $isLibur = true;
                            @endphp
                        @else
                            @php
                                $isLibur = false;
                            @endphp
                        @endif
                        @if ($isLibur)
                            <td class="bg-red" style="background-color: #ff0000;"></td>
                        @else
                            <td></td>
                        @endif
                    @endfor
            </tr>
    @php
        $tempDEPT = $employees[$i]->DEPARTEMENT;
        $no++;  
    @endphp
    <!-- DEPT SAMA -->
    @else
            <tr>
                <td>{{ $no }}</td>
                <td>{{ $employees[$i]->NPK }}</td>
                <td class="col-nama" style="text-align: left;padding-left: 5px;">{{ $employees[$i]->NAMA_KARYAWAN }}</td>
                <td>{{ $employees[$i]->DEPARTEMENT }}</td>
                <!-- JUMLAH HARI -->
                @for($date = 0; $date < $getTotalDays; $date++)
                        @php
                            $originalDate = $currentYear . '-' . $currentMonth . '-' . ($date + 1); 
                            $unixTime = strtotime($originalDate); 
                            $newDate = date("Y-m-d", $unixTime);
                        @endphp
                        @if(isset($array[$newDate]) && $array[$newDate]["holiday"])
                            @php
                                $isLibur = true;
                            @endphp
                        @elseif (date("D",strtotime($newDate))==="Sun" || date("D",strtotime($newDate))==="Sat")
                            @php
                                $isLibur = true;
                            @endphp
                        @else
                            @php
                                $isLibur = false;
                            @endphp
                        @endif
                        @if ($isLibur)
                            <td class="bg-red" style="background-color: #ff0000;"></td>
                        @else
                            <td></td>
                        @endif
                    @endfor
            </tr>
        @if($no % 30 == 0)
            </tbody>
        </table>
        <div class='page-break'></div>
        <br>
        <!-- RESET TABLE -->
        <table class="header-top">
            <tr>
                <td class="company-name" style="width: 30%;">PT. CHUTEX INTERNATIONAL INDONESIA</td>
                <td style="width: 40%;">CHIEF : <span class="dots"></span></td>
                <td style="width: 30%; text-align: right;"></td>
            </tr>
            <tr>
                <td class="company-name">ABSENSI KARYAWAN</td>
                <td>SPV : <span class="dots"></span></td>
                <td>ADM : <span class="dots"></span> <span style="margin-left: 20px;">{{$employees[$i]->DEPARTEMENT}}</span></td>
            </tr>
        </table>

        <table class="main-table">
            <thead>
                <tr>
                    <th rowspan="2" class="col-no">NO.</th>
                    <th rowspan="2" class="col-npk">NPK</th>
                    <th rowspan="2" class="col-nama">NAMA</th>
                    <th rowspan="2" class="col-bagian">BAGIAN</th>
                    <th colspan="<?php echo $getTotalDays; ?>" class="header-month"><?php echo date("M-Y"); ?></th>
                </tr>
                <tr>
                    @for($date = 0; $date < $getTotalDays; $date++)
                        @php
                            $originalDate = $currentYear . '-' . $currentMonth . '-' . ($date + 1); 
                            $unixTime = strtotime($originalDate); 
                            $newDate = date("Y-m-d", $unixTime);
                        @endphp
                        @if(isset($array[$newDate]) && $array[$newDate]["holiday"])
                            @php
                                $isLibur = true;
                            @endphp
                        @elseif (date("D",strtotime($newDate))==="Sun" || date("D",strtotime($newDate))==="Sat")
                            @php
                                $isLibur = true;
                            @endphp
                        @else
                            @php
                                $isLibur = false;
                            @endphp
                        @endif
                        @if ($isLibur)
                            <td class="bg-red" style="background-color: #ff0000;">{{ $date + 1 }}</td>
                        @else
                            <td>{{ $date + 1 }}</td>
                        @endif
                    @endfor
                </tr>
            </thead>
            <tbody>
        @endif
    @php
        $no++;
    @endphp
    @endif
    @if($i == count($employees)-1)
        <!-- ISI SISA KOSONG TEMPLATE SAMPAI 30 BARIS -->
        @for($sisa = $no; $sisa <= 30; $sisa++)
            <tr>
                <td>{{ $sisa }}</td>
                <td></td>
                <td class="col-nama"></td>
                <td></td>
                <!-- JUMLAH HARI -->
                @for($date = 0; $date < $getTotalDays; $date++)
                    @php
                        $originalDate = $currentYear . '-' . $currentMonth . '-' . ($date + 1); 
                        $unixTime = strtotime($originalDate); 
                        $newDate = date("Y-m-d", $unixTime);
                    @endphp
                    @if(isset($array[$newDate]) && $array[$newDate]["holiday"])
                        @php
                            $isLibur = true;
                        @endphp
                    @elseif (date("D",strtotime($newDate))==="Sun" || date("D",strtotime($newDate))==="Sat")
                        @php
                            $isLibur = true;
                        @endphp
                    @else
                        @php
                            $isLibur = false;
                        @endphp
                    @endif
                    @if ($isLibur)
                        <td class="bg-red" style="background-color: #ff0000;"></td>
                    @else
                        <td></td>
                    @endif
                @endfor
            </tr>
        @endfor
        </tbody>
    </table>
    <div class='page-break'></div>
    <br>
    @endif
@endfor
</body>
</html>