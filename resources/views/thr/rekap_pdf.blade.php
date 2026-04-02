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
      }

      table,
      th,
      td {
        border: 1px solid #000;
      }

      th,
      td {
        padding: 4px;
      }

      th {
        background: #eee;
      }

      .center {
        text-align: center;
      }

      .bold {
        font-weight: bold;
      }

      .section-title {
        margin-top: 15px;
        font-size: 14px;
        font-weight: bold;
      }

      .page-break {
        page-break-before: always;
      }

      tr {
        page-break-inside: avoid;
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
    <div class="center bold">REKAP THR</div>
    <div class="center"> Periode : {{ $period_name ?? '-' }}
    </div>
    {{-- ================= HITUNG TOTAL ================= --}} @php $totalSalary = 0; $totalAllowance = 0; $totalThr = 0; @endphp {{-- ================= DATA ================= --}}
    <div class="section-title">KARYAWAN</div> @forelse($groupedActive as $dept => $employees) <h4>Department : {{ $dept }}</h4>
    <table>
      <thead>
        <tr>
          <th width="70">NPK</th>
          <th width="200">Name</th>
          <th>Salary</th>
          <th>Allowance</th>
          <th>Working Months</th>
          <th>THR</th>
        </tr>
      </thead>
      <tbody> @foreach($employees as $item) @php $totalSalary += $item->basic_salary ?? 0; $totalAllowance += $item->allowance ?? 0; $totalThr += $item->thr ?? 0; @endphp <tr>
          <td>{{ $item->employee_npk }}</td>
          <td>{{ $item->employee_name }}</td>
          <td align="right">
            {{ number_format($item->basic_salary ?? 0,0,',','.') }}
          </td>
          <td align="right">
            {{ number_format($item->allowance ?? 0,0,',','.') }}
          </td>
          <td align="right">
            {{ number_format($item->working_months ?? 0,0,',','.') }}
          </td>
          <td align="right" class="bold">
            {{ number_format($item->thr ?? 0,0,',','.') }}
          </td>
        </tr> @endforeach </tbody>
    </table> @empty <p>Tidak ada data</p> @endforelse {{-- ================= REKAP TOTAL ================= --}}
    <div class="page-break"></div>
    <h3>REKAP THR</h3>
    <table>
      <thead>
        <tr>
          <th>Component</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="bold">Basic Salary</td>
          <td align="right">
            {{ number_format($totalSalary,0,',','.') }}
          </td>
        </tr>
        <tr>
          <td class="bold">Allowance</td>
          <td align="right">
            {{ number_format($totalAllowance,0,',','.') }}
          </td>
        </tr>
        <tr>
          <td class="bold">Total THR</td>
          <td align="right" class="bold">
            {{ number_format($totalThr,0,',','.') }}
          </td>
        </tr>
      </tbody>
    </table>
  </body>
</html>