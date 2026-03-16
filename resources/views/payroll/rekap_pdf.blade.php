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

      h4 {
        margin-top: 10px;
        margin-bottom: 5px;
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

      .summary-table {
        width: 400px;
        margin-top: 20px;
      }

      .summary-table th {
        background: #eee;
      }

      .rekap-container{
        width:100%;
        margin-top:20px;
      }

      .rekap-container td{
        vertical-align:top;
        border:none;
      }

      .rekap-box{
        width:100%;
      }
    </style>
  </head>
  <body>
    <div class="logo">
      <img src="{{ public_path('img/chutex_logo.png') }}" height="60">
    </div>
    <div class="title">
      <h2>REKAP PAYROLL</h2>
      <h4> Periode : {{ $groupedActive->first()->first()->period_name ?? $groupedResign->first()->first()->period_name ?? '-' }}
      </h4>
    </div>
    {{-- ========================= --}}
    {{-- KARYAWAN AKTIF --}}
    {{-- ========================= --}} @php $activeTotals = []; $activeEarning = 0; $activeDeduction = 0; @endphp <div class="section-title">KARYAWAN AKTIF</div> @foreach($groupedActive as $dept => $employees) <div class="dept-block">
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
            <td>{{ $item->employee_name }}</td> @foreach($allComponents as $code => $component) @php $value = $item->$code ?? 0; $activeTotals[$code] = ($activeTotals[$code] ?? 0) + $value; @endphp <td align="right">
              {{ number_format($value,0,',','.') }}
            </td> @endforeach <td align="right">
              {{ number_format($item->total_salary,0,',','.') }}
            </td>
          </tr> @endforeach </tbody>
      </table>
    </div> @endforeach <div class="page-break"></div>
    {{-- ========================= --}}
    {{-- KARYAWAN RESIGN --}}
    {{-- ========================= --}} @if($groupedResign->count()) @php $resignTotals = []; $resignEarning = 0; $resignDeduction = 0; @endphp <div class="page-break"></div>
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
            <td>{{ $item->employee_name }}</td> @foreach($allComponents as $code => $component) @php $value = $item->$code ?? 0; $resignTotals[$code] = ($resignTotals[$code] ?? 0) + $value; @endphp <td align="right">
              {{ number_format($value,0,',','.') }}
            </td> @endforeach <td align="right">
              {{ number_format($item->total_salary,0,',','.') }}
            </td>
          </tr> @endforeach </tbody>
      </table>
    </div> @endforeach
    <div class="page-break"></div>
    <h3>REKAP PAYROLL</h3>
    <table class="summary-table" style="width:100%">
      <thead>
        <tr>
          <th>Component</th>
          <th>Type</th>
          <th>Total Aktif</th>
          <th>Total Resign</th>
        </tr>
      </thead>
      <tbody> @php $activeEarning = 0; $activeDeduction = 0; $resignEarning = 0; $resignDeduction = 0; @endphp @foreach($allComponents as $code => $component) @php $activeValue = $activeTotals[$code] ?? 0; $resignValue = $resignTotals[$code] ?? 0; $type = $component->type ?? 'earning'; if($type == 'deduction'){ $activeValue = -$activeValue; $resignValue = -$resignValue; $activeDeduction += $activeValue; $resignDeduction += $resignValue; }else{ $activeEarning += $activeValue; $resignEarning += $resignValue; } @endphp <tr>
          <td>{{ $component->name }}</td>
          <td align="center"> @if($type == 'earning') <span style="color:green;font-weight:bold">EARNING</span> @else <span style="color:red;font-weight:bold">DEDUCTION</span> @endif </td>
          <td align="right">
            {{ number_format($activeValue,0,',','.') }}
          </td>
          <td align="right">
            {{ number_format($resignValue,0,',','.') }}
          </td>
        </tr> @endforeach </tbody>
    </table>
    <table class="summary-table" style="width:100%">
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
        <td align="right" style="font-weight:bold"> Aktif : {{ number_format($activeEarning + $activeDeduction,0,',','.') }}
        </td>
        <td align="right" style="font-weight:bold"> Resign : {{ number_format($resignEarning + $resignDeduction,0,',','.') }}
        </td>
      </tr>
    </table>@endif
  </body>
</html>