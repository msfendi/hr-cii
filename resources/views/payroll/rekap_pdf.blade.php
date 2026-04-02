<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <style>
      body {
        font-family: sans-serif;
        font-size: 10px;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
        table-layout: fixed;
      }

      table,
      th,
      td {
        border: 1px solid #000;
      }

      th,
      td {
        padding: 4px;
        word-wrap: break-word;
      }

      th {
        background: #eee;
      }

      thead {
        display: table-header-group;
      }

      tr {
        page-break-inside: avoid;
      }

      .title {
        text-align: center;
        margin-bottom: 15px;
      }

      .logo {
        text-align: center;
        margin-bottom: 10px;
      }

      .section-title {
        margin-top: 25px;
        font-size: 14px;
        font-weight: bold;
      }

      .dept-block {
        page-break-inside: avoid;
        margin-bottom: 10px;
      }

      .page-break {
        page-break-before: always;
      }

      .col-npk {
        width: 70px;
      }

      .col-name {
        width: 160px;
      }

      .col-component {
        width: 75px;
      }

      .summary-table {
        width: 100%;
        margin-top: 20px;
      }
    </style>
  </head>
  <body>
    {{-- ================= HEADER ================= --}}
    <div>
      <strong>PT. CHUTEX INTERNATIONAL INDONESIA</strong>
      <br> SUKOHARJO
    </div>
    <br>
    <div class="center bold"> REKAP PAYROLL </div>
    <br>
    <div class="center">Periode : {{ optional($groupedActive->first())->first()->period_name
    ?? optional($groupedResign->first())->first()->period_name
    ?? '-' }}</div>
    {{-- ================= KARYAWAN AKTIF ================= --}}
    <div class="section-title">KARYAWAN AKTIF</div> @forelse($groupedActive as $dept => $employees) <div class="dept-block">
      <h4>Department : {{ $dept }}</h4>
      <table>
        <thead>
          <tr>
            <th class="col-npk">NPK</th>
            <th class="col-name">Name</th> @foreach($allComponents as $component) <th class="col-component">{{ $component->name }}</th> @endforeach <th class="col-component">Total Salary</th>
          </tr>
        </thead>
        <tbody> @foreach($employees as $item) <tr>
            <td>{{ $item->employee_npk }}</td>
            <td>{{ $item->employee_name }}</td> @foreach($allComponents as $code => $component) <td align="right">
              {{ number_format($item->$code ?? 0,0,',','.') }}
            </td> @endforeach <td align="right">
              {{ number_format($item->total_salary,0,',','.') }}
            </td>
          </tr> @endforeach </tbody>
      </table>
    </div> @empty <p>Tidak ada karyawan aktif</p> @endforelse {{-- ================= KARYAWAN RESIGN ================= --}} @if($groupedResign->count()) <div class="page-break"></div>
    <div class="section-title">KARYAWAN RESIGN</div> @foreach($groupedResign as $dept => $employees) <div class="dept-block">
      <h4>Department : {{ $dept }}</h4>
      <table>
        <thead>
          <tr>
            <th class="col-npk">NPK</th>
            <th class="col-name">Name</th> @foreach($allComponents as $component) <th class="col-component">{{ $component->name }}</th> @endforeach <th class="col-component">Total Salary</th>
          </tr>
        </thead>
        <tbody> @foreach($employees as $item) <tr>
            <td>{{ $item->employee_npk }}</td>
            <td>{{ $item->employee_name }}</td> @foreach($allComponents as $code => $component) <td align="right">
              {{ number_format($item->$code ?? 0,0,',','.') }}
            </td> @endforeach <td align="right">
              {{ number_format($item->total_salary,0,',','.') }}
            </td>
          </tr> @endforeach </tbody>
      </table>
    </div> @endforeach @endif {{-- ================= REKAP (SELALU MUNCUL) ================= --}}
    <div class="page-break"></div>
    <h3>REKAP PAYROLL</h3>
    <table class="summary-table">
      <thead>
        <tr>
          <th>Component</th>
          <th>Type</th>
          <th>Total Aktif</th>
          <th>Total Resign</th>
        </tr>
      </thead>
      <tbody> @php $activeEarning=0; $activeDeduction=0; $resignEarning=0; $resignDeduction=0; @endphp @foreach($allComponents as $code=>$component) @php $activeValue = $activeTotals[$code] ?? 0; $resignValue = $resignTotals[$code] ?? 0; $type = $component->type ?? 'earning'; if($type=='deduction'){ $activeValue *= -1; $resignValue *= -1; $activeDeduction += $activeValue; $resignDeduction += $resignValue; }else{ $activeEarning += $activeValue; $resignEarning += $resignValue; } @endphp <tr>
          <td>{{ $component->name }}</td>
          <td align="center"> @if($type=='earning') <span style="color:green;font-weight:bold">EARNING</span> @else <span style="color:red;font-weight:bold">DEDUCTION</span> @endif </td>
          <td align="right">{{ number_format($activeValue,0,',','.') }}</td>
          <td align="right">{{ number_format($resignValue,0,',','.') }}</td>
        </tr> @endforeach </tbody>
    </table>
    <table class="summary-table">
      <tr>
        <th width="40%">Total Earning</th>
        <td align="right" style="color:green;font-weight:bold"> Aktif : {{ number_format($activeEarning,0,',','.') }}
        </td>
        <td align="right" style="color:green;font-weight:bold"> Resign : {{ number_format($resignEarning,0,',','.') }}
        </td>
      </tr>
      <tr>
        <th>Total Deduction</th>
        <td align="right" style="color:red;font-weight:bold"> Aktif : {{ number_format($activeDeduction,0,',','.') }}
        </td>
        <td align="right" style="color:red;font-weight:bold"> Resign : {{ number_format($resignDeduction,0,',','.') }}
        </td>
      </tr>
      <tr>
        <th>Net Payroll</th>
        <td align="right" style="font-weight:bold"> Aktif : {{ number_format($activeEarning+$activeDeduction,0,',','.') }}
        </td>
        <td align="right" style="font-weight:bold"> Resign : {{ number_format($resignEarning+$resignDeduction,0,',','.') }}
        </td>
      </tr>
    </table>
  </body>
</html>