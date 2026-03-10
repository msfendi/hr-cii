<!DOCTYPE html>
<html>
   <head>
      <meta charset="utf-8">
      <style>
         .page-break {
            page-break-after: always;
         }
      </style>
      <style>
         body{
         font-family: Arial, Helvetica, sans-serif;
         font-size:12px;
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
         padding:6px;
         }
         .right{
         text-align:right;
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
               <td width="150">
                  <img src="{{ public_path('img/chutex_logo.png') }}" class="logo">
               </td>
               <td class="title">
                  PT CHUTEX INTERNATIONAL<br>
                  SLIP GAJI KARYAWAN
               </td>
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
      <br><br><br>
      <table width="100%">
         <tr>
            <td width="50%"></td>
            <td align="center">
               HR Department<br><br><br>
               _______________________
            </td>
         </tr>
      </table>
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
               <th width="120">Tanggal</th>
               <th width="120">Jam Masuk</th>
               <th width="120">Jam Pulang</th>
            </tr>
            @foreach($attendance as $row)
            <tr>
               <td>{{ date('d-m-Y', strtotime($row->tanggal)) }}</td>
               <td class="right">{{ $row->jam_masuk ?? '' }}</td>
               <td class="right">{{ $row->jam_pulang ?? '' }}</td>
            </tr>
            @endforeach
         </table>
   </body>
</html>