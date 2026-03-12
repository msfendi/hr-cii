<!DOCTYPE html>
<html>
   <head>
      <meta charset="utf-8">
      <style>
         .page-break {
            page-break-after: always;
         }
         .holiday{
            background:#ffd6d6;
         }

         .weekend{
            background:#ffeaea;
         }

         .absent{
            background:#ffef9f;
         }

         .status{
            font-weight:bold;
            text-align:center;
         }
      </style>
      <style>
         body{
         font-family: Arial, Helvetica, sans-serif;
         font-size:12px;
         padding: 20px;
         }
         .header{
         width:100%;
         border-bottom:2px solid #000;
         margin-bottom:20px;
         }
         .logo{
         width:120px;
         }
         .title{
         text-align:center;
         font-size:18px;
         font-weight:bold;
         }
         .table{
         width:100%;
         border-collapse: collapse;
         }
         .table th{
         background:#f2f2f2;
         }
         .table th, .table td{
         border:1px solid #ccc;
         padding:3px;
         }
         .right{
         text-align:right;
         }
         .center{
         text-align:center;
         }
         .section-title{
         margin-top:20px;
         font-weight:bold;
         }
         .total{
         font-size:14px;
         font-weight:bold;
         }
      </style>
   </head>
   <body>
      <!-- SLIP GAJI -->
      <div class="header">
         <table width="100%">
            <tr>
               <td width="100">
                     <img src="{{ public_path('img/chutex_logo.png') }}" class="logo" style="width: 50px;">
               </td>
               <td style="text-align: center; vertical-align: middle;">
                     <strong>PT CHUTEX INTERNATIONAL</strong><br>
                     SLIP GAJI KARYAWAN
               </td>
               <td width="100"></td> <!-- kosong supaya logo tidak mempengaruhi center -->
            </tr>
         </table>
      </div>
      <table width="100%" style="margin-bottom:20px;">
         <tr>
            <td width="120">NPK</td>
            <td>: {{ $employee->employee_npk }}</td>
         </tr>
         <tr>
            <td>Nama</td>
            <td>: {{ $employee->employee_name }}</td>
         </tr>
         <tr>
            <td>Periode</td>
            <td>: {{ $employee->period_name }}</td>
         </tr>
      </table>
      <div class="section-title">Pendapatan</div>
      <table class="table">
         <tr>
            <th>Komponen</th>
            <th width="150">Jumlah</th>
         </tr>
         @php $totalEarning = 0; @endphp
         @foreach($earnings as $name => $value)
         <tr>
            <td>{{ ucwords(str_replace('_',' ',$name)) }}</td>
            <td class="right">{{ number_format($value,0,',','.') }}</td>
         </tr>
         @php $totalEarning += $value; @endphp
         @endforeach
         <tr>
            <td class="total">Total Pendapatan</td>
            <td class="right total">{{ number_format($totalEarning,0,',','.') }}</td>
         </tr>
      </table>
      <div class="section-title">Potongan</div>
      <table class="table">
         <tr>
            <th>Komponen</th>
            <th width="150">Jumlah</th>
         </tr>
         @php $totalDeduction = 0; @endphp
         @foreach($deductions as $name => $value)
         <tr>
            <td>{{ ucwords(str_replace('_',' ',$name)) }}</td>
            <td class="right">{{ number_format($value,0,',','.') }}</td>
         </tr>
         @php $totalDeduction += $value; @endphp
         @endforeach
         <tr>
            <td class="total">Total Potongan</td>
            <td class="right total">{{ number_format($totalDeduction,0,',','.') }}</td>
         </tr>
      </table>
      <br><br>
      <table width="100%">
         <tr>
            <td class="total">TOTAL GAJI DITERIMA</td>
            <td class="right total">
               Rp {{ number_format($employee->total_salary,0,',','.') }}
            </td>
         </tr>
      </table>
      <!-- <br><br><br>
      <table width="100%">
         <tr>
            <td width="50%"></td>
            <td align="center">
               HR Department<br><br><br>
               _______________________
            </td>
         </tr>
      </table> -->
      <div class="page-break"></div>
      <h3 style="text-align:center;">REKAP ABSENSI KARYAWAN</h3>
         <table width="100%" style="margin-bottom:20px;">
            <tr>
               <td width="120">NPK</td>
               <td>: {{ $employee->employee_npk }}</td>
            </tr>
            <tr>
               <td>Nama</td>
               <td>: {{ $employee->employee_name }}</td>
            </tr>
            <tr>
               <td>Departement</td>
               <td>: {{ $employee->DEPARTEMENT ?? '-' }}</td>
            </tr>
         </table>
         <table class="table">
            <tr>
               <th>Tanggal</th>
               <th>Hari</th>
               <th>Jam Masuk</th>
               <th>Jam Pulang</th>
               <th>Status</th>
               <th>Overtime</th>
            </tr>
            @foreach($attendance as $row)
            @php
            $date = \Carbon\Carbon::parse($row->tanggal);
            $day = $date->translatedFormat('l');
            $isWeekend = $date->isWeekend();
            $isHoliday = in_array($date->format('Y-m-d'), $holidays ?? []);
               $rowClass = '';
            if($isHoliday){
               $rowClass = 'holiday';
            }
            elseif($isWeekend){
               $rowClass = 'weekend';
            }
            elseif(!$row->jam_masuk && !$row->jam_pulang){
               $rowClass = 'absent';
            }
            @endphp
            <tr class="{{ $rowClass }}">
               <td class="center">{{ $date->format('d-m-Y') }}</td>
               <td class="center">{{ $day }}</td>
               <td class="center">{{ $row->jam_masuk ?? '-' }}</td>
               <td class="center">{{ $row->jam_pulang ?? '-' }}</td>
               <td class="status">{{ $row->status }}</td>
               <td class="center">{{ $row->overtime }}</td>
            </tr>
            @endforeach
         </table>
         <br>
         <table class="table" width="50%">
            <tr>
               <td>Total Hadir</td>
               <td class="right">{{ $summary['hadir'] }}</td>
            </tr>
            <tr>
               <td>Lembur Resmi</td>
               <td class="right">{{ $summary['lembur_resmi'] }} Jam</td>
            </tr>
            <tr>
               <td>Lembur Khusus</td>
               <td class="right">{{ $summary['lembur_khusus'] }} Jam</td>
            </tr>
            @foreach($summary['status'] as $status => $count)
            <tr>
               <td>{{ $status }}</td>
               <td class="right">{{ $count }}</td>
            </tr>
            @endforeach
         </table>
   </body>
</html>