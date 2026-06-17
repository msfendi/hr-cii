<!DOCTYPE html>
<html>
   <head>
      <meta charset="utf-8">
      <style>
         body{
         font-family:sans-serif;
         font-size:10px;
         }
         table{
         width:100%;
         border-collapse:collapse;
         margin-bottom:15px;
         }
         table,th,td{
         border:1px solid #000;
         }
         th,td{
         padding:4px;
         }
         th{
         background:#eee;
         }
         .center{text-align:center;}
         .bold{font-weight:bold;}
         .section-title{
         margin-top:15px;
         font-size:14px;
         font-weight:bold;
         }
         .page-break{
         page-break-before:always;
         }
         tr{
         page-break-inside:avoid;
         }
      </style>
   </head>
   <body>
      {{-- ================= HEADER ================= --}}
      <div>
         <strong>PT. CHUTEX INTERNATIONAL INDONESIA</strong><br>
         SUKOHARJO
      </div>
      <br>
      <div class="center bold">
         REKAP KOMPENSASI
      </div>
      <div class="center">
         Tanggal : {{ $date->format('d F Y') }}
      </div>
      {{-- ================= INIT TOTAL ================= --}}
      @php
      $totalAmountAll = 0;
      $totalEmployeeAll = 0;
      @endphp
      {{-- ================= DATA PER DEPARTMENT ================= --}}
      @forelse($groups as $department => $rows)
      <div class="section-title">
         Department : {{ $department ?? '-' }}
      </div>
      <table>
         <thead>
            <tr>
               <th width="70">NPK</th>
               <th width="200">Name</th>
               <th>Contract Duration</th>
               <th>Contract Start</th>
               <th>Contract End</th>
               <th>Salary</th>
               <th width="150">Compensation</th>
            </tr>
         </thead>
         <tbody>
            @php
            $subtotalAmount = 0;
            $subtotalEmployee = 0;
            @endphp
            @foreach($rows as $item)
            @php
            $subtotalAmount += $item->amount;
            $subtotalEmployee++;
            $totalAmountAll += $item->amount;
            $totalEmployeeAll++;
            @endphp
            <tr>
               <td>{{ $item->npk }}</td>
               <td>{{ $item->employee_name ?? '-' }}</td>
               <td align="center">
                  {{ $item->month_duration ?? '-' }} bulan {{ $item->day_duration ?? '-' }} hari
               </td>
               <td>
                  {{ \Carbon\Carbon::parse($item->start_date)->format('d-m-Y') }}
               </td>
               <td>
                  {{ \Carbon\Carbon::parse($item->end_date)->format('d-m-Y') }}
               </td>
               <td align="right">
                  {{ number_format($item->salary ?? 0,0,',','.') }}
               </td>
               <td align="right" class="bold">
                  {{ number_format($item->amount,0,',','.') }}
               </td>
            </tr>
            @endforeach
            {{-- ===== SUB TOTAL PER DEPARTMENT ===== --}}
            <tr>
               <td colspan="5" class="bold center">
                  SUB TOTAL ({{ $subtotalEmployee }} Employee)
               </td>
               <td colspan="2" align="right" class="bold">
                  {{ number_format($subtotalAmount,0,',','.') }}
               </td>
            </tr>
         </tbody>
      </table>
      @empty
      <p>Tidak ada data</p>
      @endforelse
      {{-- ================= REKAP TOTAL ================= --}}
      <div class="page-break"></div>
      <h3>REKAP KOMPENSASI</h3>
      <table>
         <thead>
            <tr>
               <th>Component</th>
               <th>Total</th>
            </tr>
         </thead>
         <tbody>
            <tr>
               <td class="bold">Total Employee</td>
               <td align="right">
                  {{ number_format($totalEmployeeAll,0,',','.') }}
               </td>
            </tr>
            <tr>
               <td class="bold">Total Compensation</td>
               <td align="right" class="bold">
                  {{ number_format($totalAmountAll,0,',','.') }}
               </td>
            </tr>
         </tbody>
      </table>
      {{-- ================= APPROVAL ================= --}}
    @if(!empty($approvals))
      <table style="width:100%;border:none">
      <tr>

      @foreach($approvals as $approve)
      <td align="center" style="border:none;width:25%">

          <div style="height:80px">

              @if($approve['status']=='approve' && !empty($approve['signature_img']))
                  <img
                      src="{{ storage_path('app/public/signature/'.$approve['signature_img']) }}"
                      style="height:70px"
                  >
              @endif

          </div>

          <div style="margin-top:5px">
              <strong>{{ $approve['nama_karyawan'] }}</strong><br>
              {{ $approve['bagian'] }}<br>
              @if($approve['status']=='approve')
                  <span style="color:green;font-weight:bold">APPROVED</span>
              @else
                  <span style="color:orange;font-weight:bold">WAITING</span>
              @endif
          </div>

      </td>
      @endforeach

      </tr>
      </table>
      @endif
   </body>
</html>