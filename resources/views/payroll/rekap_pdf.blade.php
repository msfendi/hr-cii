<!DOCTYPE html>
<html>
   <head>
      <meta charset="utf-8">
      <style>
         body{
         font-family: sans-serif;
         font-size:10px;
         }
         table{
         width:100%;
         border-collapse:collapse;
         margin-bottom:20px;
         table-layout: fixed;
         }
         table,th,td{
         border:1px solid #000;
         }
         th,td{
         padding:4px;
         word-wrap: break-word;
         }
         th{
         background:#eee;
         }
         thead{
         display: table-header-group;
         }
         tr{
         page-break-inside: avoid;
         }
         h4{
         margin-top:15px;
         margin-bottom:5px;
         }
         /* FIX WIDTH */
         .col-npk{
         width:70px;
         }
         .col-name{
         width:160px;
         }
         .col-component{
         width:75px;
         }
         .title{
         text-align:center;
         margin-bottom:15px;
         }
         .logo{
         text-align:center;
         margin-bottom:10px;
         }
         .signature{
         margin-top:60px;
         text-align:right;
         }
         .dept-block{
         page-break-inside: avoid;
         }
      </style>
   </head>
   <body>
      <div class="logo">
         <img src="{{ public_path('img/chutex_logo.png') }}" height="60">
      </div>
      <div class="title">
         <h2>REKAP PAYROLL</h2>
         <h4>Periode : {{ $grouped->first()->first()->period_name ?? '-' }}</h4>
      </div>
      @php
      $grandTotals = [];
      @endphp
      @foreach($grouped as $dept => $employees)
      <div class="dept-block">
         <h4>Department : {{ $dept }}</h4>
         <table>
            <thead>
               <tr>
                  <th class="col-npk">NPK</th>
                  <th class="col-name">Name</th>
                  @foreach($allComponents as $code => $component)
                  <th class="col-component">{{ $component->name }}</th>
                  @endforeach
                  <th class="col-component">Total Salary</th>
               </tr>
            </thead>
            <tbody>
               @foreach($employees as $item)
               <tr>
                  <td class="col-npk">{{ $item->employee_npk }}</td>
                  <td class="col-name">{{ $item->employee_name }}</td>
                  @foreach($allComponents as $code => $component)
                  @php
                  $value = $item->$code ?? 0;
                  $grandTotals[$code] = ($grandTotals[$code] ?? 0) + $value;
                  @endphp
                  <td align="right">
                  {{ number_format($value,0,',','.') }}
                  </td>
                  @endforeach
                  <td align="right">
                     {{ number_format($item->total_salary,0,',','.') }}
                  </td>
               </tr>
               @endforeach
            </tbody>
         </table>
      </div>
      @endforeach
      @php
      $earningTotal = 0;
      $deductionTotal = 0;
      @endphp
      <h3>GRAND TOTAL PAYROLL</h3>
      <table>
         <thead>
            <tr>
               <th style="width:200px">Component</th>
               <th style="width:100px">Type</th>
               <th style="width:150px">Total</th>
            </tr>
         </thead>
         <tbody>
            @foreach($allComponents as $code => $component)
            @php
            $value = $grandTotals[$code] ?? 0;
            $type = $component->type ?? 'earning';
            if($type == 'deduction'){
            $value = -$value;
            $deductionTotal += $value;
            }else{
            $earningTotal += $value;
            }
            @endphp
            <tr>
               <td>{{ $component->name }}</td>
               <td align="center">
                  @if($type == 'earning')
                  <span style="color:green;font-weight:bold">EARNING</span>
                  @else
                  <span style="color:red;font-weight:bold">DEDUCTION</span>
                  @endif
               </td>
               <td align="right">
                  {{ number_format($value,0,',','.') }}
               </td>
            </tr>
            @endforeach
         </tbody>
      </table>
      <br>
      <table style="width:300px">
         <tr>
            <th>Total Earning</th>
            <td align="right" style="color:green;font-weight:bold">
               {{ number_format($earningTotal,0,',','.') }}
            </td>
         </tr>
         <tr>
            <th>Total Deduction</th>
            <td align="right" style="color:red;font-weight:bold">
               {{ number_format($deductionTotal,0,',','.') }}
            </td>
         </tr>
         <tr>
            <th>Net Payroll</th>
            <td align="right" style="font-weight:bold">
               {{ number_format($earningTotal + $deductionTotal,0,',','.') }}
            </td>
         </tr>
      </table>
      <div class="signature">
         <p>Approved by</p>
         <br><br><br>
         <b>General Manager</b>
      </div>
   </body>
</html>