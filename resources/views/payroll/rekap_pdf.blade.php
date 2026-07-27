<!DOCTYPE html>
<html>
  @php

  $deptTotal = function($rows,$components){

  $totals = [];

  foreach($components as $code=>$component){
  $totals[$code] = 0;
  }

  $grandTotal = 0;

  foreach($rows as $row){

  foreach($components as $code=>$component){
  $totals[$code] += ($row->$code ?? 0);
  }

  $grandTotal += ($row->total_salary ?? 0);
  }

  return [
  'components'=>$totals,
  'salary'=>$grandTotal
  ];
  };

  @endphp

  <head>
    <meta charset="utf-8">
    <style>
      /* ===== GLOBAL & PAGE SETUP ===== */
      @page {
        size: A4 landscape;  /* landscape memberi lebih banyak ruang */
        margin: 10mm 8mm;
      }

      body {
        font-family: sans-serif;
        font-size: 8px;       /* ukuran lebih kecil agar muat */
        margin: 0;
        padding: 0;
      }

      /* ===== TABEL UTAMA ===== */
      table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        table-layout: auto;   /* otomatis menyesuaikan lebar kolom */
        font-size: 8px;
      }

      table,
      th,
      td {
        border: 1px solid #000;
      }

      th,
      td {
        padding: 2px 3px;     /* padding lebih kecil */
        word-wrap: break-word;
        overflow-wrap: break-word;
        vertical-align: middle;
      }

      th {
        background: #eee;
        text-align: center;
      }

      thead {
        display: table-header-group;
      }

      tr {
        page-break-inside: avoid;
      }

      /* ===== JUDUL & BAGIAN ===== */
      .title {
        text-align: center;
        margin-bottom: 10px;
      }

      .section-title {
        margin-top: 20px;
        font-size: 12px;
        font-weight: bold;
      }

      .dept-block {
        page-break-inside: avoid;
        margin-bottom: 8px;
      }

      .page-break {
        page-break-before: always;
      }

      /* ===== LEBAR KOLOM (relatif) ===== */
      .col-npk {
        width: 7%;
      }
      .col-name {
        width: 14%;
      }
      .col-details {
        width: 5%;
      }
      .col-component {
        width: auto;
        min-width: 50px;
      }

      /* ===== TABEL RINGKASAN ===== */
      .summary-table {
        width: 100%;
        margin-top: 15px;
        table-layout: auto;
      }

      .summary-table th,
      .summary-table td {
        padding: 3px 5px;
      }

      /* ===== APPROVAL ===== */
      .approval-table {
        width: 100%;
        border: none;
      }
      .approval-table td {
        border: none;
        text-align: center;
        width: 25%;
      }
    </style>
  </head>

  <body>

    <div>
      <strong>PT. CHUTEX INTERNATIONAL INDONESIA</strong><br>
      SUKOHARJO
    </div>

    <br>

    <div class="center bold">REKAP PAYROLL</div>

    <br>

    <div class="center">
      Periode :
      {{ optional($groupedActive->first())->first()->period_name
?? optional($groupedResign->first())->first()->period_name
?? '-' }}
    </div>

    {{-- ================= KARYAWAN AKTIF ================= --}}
    <div class="section-title">KARYAWAN AKTIF {{ $folderName }}</div>

    @forelse($groupedActive as $dept => $employees)

    <div class="dept-block">
      <h4>Department : {{ $dept }}</h4>

      <table>
        <thead>
          <tr>
            <th class="col-npk">NPK</th>
            <th class="col-name">Name</th>

            {{-- 🔥 OVERTIME (DITAMBAH DI SINI) --}}
            <th class="col-details">MA</th>
            <th class="col-details">P1</th>
            <th class="col-details">CT</th>
            <th class="col-details">SD</th>
            <th class="col-details">BR</th>
            <th class="col-details">OUT</th>
            <th class="col-details">Ijin (Menit)</th>

            @foreach($allComponents as $component)
            <th class="col-component">{{ $component->name }}</th>
            @endforeach

            <th class="col-component">Total Salary</th>
          </tr>
        </thead>

        <tbody>

          @foreach($employees as $item)
          <tr>
            <td>{{ $item->employee_npk }}</td>
            <td>{{ $item->employee_name }}</td>

            {{-- 🔥 OVERTIME VALUE --}}
            <td align="center">{{ number_format($item->MA ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->P1 ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->CT ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->SD ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->BR ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->OUT ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->total_ijin_minutes ?? 0,0,',','.') }}</td>

            @foreach($allComponents as $code => $component)
            <td align="right">
              {{ number_format($item->$code ?? 0,0,',','.') }}
            </td>
            @endforeach

            <td align="right">
              {{ number_format($item->total_salary,0,',','.') }}
            </td>
          </tr>
          @endforeach

          @php
          $totDept = $deptTotal($employees,$allComponents);

          $totMA  = $employees->sum(fn($x) => $x->MA  ?? 0);
          $totP1  = $employees->sum(fn($x) => $x->P1  ?? 0);
          $totCT  = $employees->sum(fn($x) => $x->CT  ?? 0);
          $totSD  = $employees->sum(fn($x) => $x->SD  ?? 0);
          $totBR  = $employees->sum(fn($x) => $x->BR  ?? 0);
          $totOUT = $employees->sum(fn($x) => $x->OUT ?? 0);
          $totIjin = $employees->sum(fn($x) => $x->total_ijin_minutes ?? 0);
          @endphp

          <tr style="background:#f5f5f5;font-weight:bold">
            <td colspan="2" align="center">Total Karyawan: {{ $employees->count() }}</td>

            <td align="center">{{ number_format($totMA,0,',','.') }}</td>
            <td align="center">{{ number_format($totP1,0,',','.') }}</td>
            <td align="center">{{ number_format($totCT,0,',','.') }}</td>
            <td align="center">{{ number_format($totSD,0,',','.') }}</td>
            <td align="center">{{ number_format($totBR,0,',','.') }}</td>
            <td align="center">{{ number_format($totOUT,0,',','.') }}</td>
            <td align="center">{{ number_format($totIjin,0,',','.') }}</td>

            @foreach($allComponents as $code=>$component)
            <td align="right">
                {{ number_format($totDept['components'][$code] ?? 0,0,',','.') }}
            </td>
            @endforeach

            <td align="right">
                {{ number_format($totDept['salary'],0,',','.') }}
            </td>
        </tr>

        </tbody>
      </table>
    </div>

    @empty
    <p>Tidak ada karyawan aktif</p>
    @endforelse

    {{-- ================= RESIGN ================= --}}
    @if($groupedResign->count())
    <div class="page-break"></div>
    <div class="section-title">KARYAWAN RESIGN {{ $folderName }}</div>

    @foreach($groupedResign as $dept => $employees)

    <div class="dept-block">
      <h4>Department : {{ $dept }}</h4>

      <table>
        <thead>
          <tr>
            <th class="col-npk">NPK</th>
            <th class="col-name">Name</th>

            <th class="col-details">MA</th>
            <th class="col-details">P1</th>
            <th class="col-details">CT</th>
            <th class="col-details">SD</th>
            <th class="col-details">BR</th>
            <th class="col-details">OUT</th>
            <th class="col-details">Ijin (Menit)</th>

            @foreach($allComponents as $component)
            <th class="col-component">{{ $component->name }}</th>
            @endforeach

            <th class="col-component">Total Salary</th>
          </tr>
        </thead>

        <tbody>

          @foreach($employees as $item)
          <tr>
            <td>{{ $item->employee_npk }}</td>
            <td>{{ $item->employee_name }}</td>

            <td align="center">{{ number_format($item->MA ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->P1 ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->CT ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->SD ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->BR ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->OUT ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->total_ijin_minutes ?? 0,0,',','.') }}</td>

            @foreach($allComponents as $code => $component)
            <td align="right">
              {{ number_format($item->$code ?? 0,0,',','.') }}
            </td>
            @endforeach

            <td align="right">
              {{ number_format($item->total_salary,0,',','.') }}
            </td>
          </tr>
          @endforeach

          @php
          $totDept = $deptTotal($employees,$allComponents);

          $totMA  = $employees->sum(fn($x) => $x->MA  ?? 0);
          $totP1  = $employees->sum(fn($x) => $x->P1  ?? 0);
          $totCT  = $employees->sum(fn($x) => $x->CT  ?? 0);
          $totSD  = $employees->sum(fn($x) => $x->SD  ?? 0);
          $totBR  = $employees->sum(fn($x) => $x->BR  ?? 0);
          $totOUT = $employees->sum(fn($x) => $x->OUT ?? 0);
          $totIjin = $employees->sum(fn($x) => $x->total_ijin_minutes ?? 0);
          @endphp

          <tr style="background:#f5f5f5;font-weight:bold">
            <td colspan="2" align="center">Total Karyawan: {{ $employees->count() }}</td>

            <td align="center">{{ number_format($totMA,0,',','.') }}</td>
            <td align="center">{{ number_format($totP1,0,',','.') }}</td>
            <td align="center">{{ number_format($totCT,0,',','.') }}</td>
            <td align="center">{{ number_format($totSD,0,',','.') }}</td>
            <td align="center">{{ number_format($totBR,0,',','.') }}</td>
            <td align="center">{{ number_format($totOUT,0,',','.') }}</td>
            <td align="center">{{ number_format($totIjin,0,',','.') }}</td>

            @foreach($allComponents as $code=>$component)
            <td align="right">
                {{ number_format($totDept['components'][$code] ?? 0,0,',','.') }}
            </td>
            @endforeach

            <td align="right">
                {{ number_format($totDept['salary'],0,',','.') }}
            </td>
        </tr>

        </tbody>
      </table>

    </div>
    @endforeach
    @endif

    {{-- ================= MANGKIR ================= --}}
    @if($groupedMangkir->count())

    <div class="page-break"></div>
    <div class="section-title">KARYAWAN MANGKIR {{ $folderName }}</div>

    @foreach($groupedMangkir as $dept => $employees)

    <div class="dept-block">
      <h4>Department : {{ $dept }}</h4>

      <table>
        <thead>
          <tr>
            <th class="col-npk">NPK</th>
            <th class="col-name">Name</th>

            <th class="col-details">MA</th>
            <th class="col-details">P1</th>
            <th class="col-details">CT</th>
            <th class="col-details">SD</th>
            <th class="col-details">BR</th>
            <th class="col-details">OUT</th>
            <th class="col-details">Ijin (Menit)</th>

            @foreach($allComponents as $component)
            <th class="col-component">{{ $component->name }}</th>
            @endforeach

            <th class="col-component">Total Salary</th>
          </tr>
        </thead>

        <tbody>

          @foreach($employees as $item)
          <tr>
            <td>{{ $item->employee_npk }}</td>
            <td>{{ $item->employee_name }}</td>

            <td align="center">{{ number_format($item->MA ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->P1 ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->CT ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->SD ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->BR ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->OUT ?? 0,0,',','.') }}</td>
            <td align="center">{{ number_format($item->total_ijin_minutes ?? 0,0,',','.') }}</td>

            @foreach($allComponents as $code=>$component)
            <td align="right">
              {{ number_format($item->$code ?? 0,0,',','.') }}
            </td>
            @endforeach

            <td align="right">
              {{ number_format($item->total_salary,0,',','.') }}
            </td>
          </tr>
          @endforeach

          @php
          $totDept = $deptTotal($employees,$allComponents);

          $totMA  = $employees->sum(fn($x) => $x->MA  ?? 0);
          $totP1  = $employees->sum(fn($x) => $x->P1  ?? 0);
          $totCT  = $employees->sum(fn($x) => $x->CT  ?? 0);
          $totSD  = $employees->sum(fn($x) => $x->SD  ?? 0);
          $totBR  = $employees->sum(fn($x) => $x->BR  ?? 0);
          $totOUT = $employees->sum(fn($x) => $x->OUT ?? 0);
          $totIjin = $employees->sum(fn($x) => $x->total_ijin_minutes ?? 0);
          @endphp

          <tr style="background:#f5f5f5;font-weight:bold">
            <td colspan="2" align="center">Total Karyawan: {{ $employees->count() }}</td>

            <td align="center">{{ number_format($totMA,0,',','.') }}</td>
            <td align="center">{{ number_format($totP1,0,',','.') }}</td>
            <td align="center">{{ number_format($totCT,0,',','.') }}</td>
            <td align="center">{{ number_format($totSD,0,',','.') }}</td>
            <td align="center">{{ number_format($totBR,0,',','.') }}</td>
            <td align="center">{{ number_format($totOUT,0,',','.') }}</td>
            <td align="center">{{ number_format($totIjin,0,',','.') }}</td>

            @foreach($allComponents as $code=>$component)
            <td align="right">
                {{ number_format($totDept['components'][$code] ?? 0,0,',','.') }}
            </td>
            @endforeach

            <td align="right">
                {{ number_format($totDept['salary'],0,',','.') }}
            </td>
        </tr>

        </tbody>
      </table>

    </div>

    @endforeach
    @endif
<div class="page-break"></div>
@php
$totalOrangAktif  = $groupedActive->sum(fn($g) => $g->count());
$totalOrangResign = $groupedResign->sum(fn($g) => $g->count());
$totalOrangMangkir = $groupedMangkir->sum(fn($g) => $g->count());
@endphp
    <h3>REKAP PAYROLL</h3>
    <table class="summary-table">
      <thead>
        <tr>
          <th>Component</th>
          <th>Type</th>
          <th>Total Aktif</th>
          <th>Total Resign</th>
          <th>Total Mangkir</th>
      </tr>
      </thead>
      <tbody> @php $activeEarning=0; $activeDeduction=0; $resignEarning=0; $resignDeduction=0;$mangkirEarning=0;$mangkirDeduction=0; @endphp @foreach($allComponents as $code=>$component) @php $activeValue = $activeTotals[$code] ?? 0; $resignValue = $resignTotals[$code] ?? 0;$mangkirValue = $mangkirTotals[$code] ?? 0; $type = $component->type ?? 'earning'; if($type=='deduction'){ $activeValue *= -1; $resignValue *= -1;$mangkirValue *= -1; $mangkirDeduction += $mangkirValue; $activeDeduction += $activeValue; $resignDeduction += $resignValue; }else{ $activeEarning += $activeValue; $resignEarning += $resignValue; $mangkirEarning += $mangkirValue; } @endphp <tr>
          <td>{{ $component->name }}</td>
          <td align="center"> @if($type=='earning') <span style="color:green;font-weight:bold">EARNING</span> @else <span style="color:red;font-weight:bold">DEDUCTION</span> @endif </td>
          <td align="right">{{ number_format($activeValue,0,',','.') }}</td>
          <td align="right">{{ number_format($resignValue,0,',','.') }}</td>
          <td align="right">{{ number_format($mangkirValue,0,',','.') }}</td>
        </tr> @endforeach </tbody>
    </table>
    <table class="summary-table">
      <tr>
    <th width="40%">Total Earning</th>

    <td align="right" style="color:green;font-weight:bold">
        Aktif :
        {{ number_format($activeEarning,0,',','.') }}
    </td>

    <td align="right" style="color:green;font-weight:bold">
        Resign :
        {{ number_format($resignEarning,0,',','.') }}
    </td>

    <td align="right" style="color:green;font-weight:bold">
        Mangkir :
        {{ number_format($mangkirEarning,0,',','.') }}
    </td>
</tr>

<tr>
    <th>Total Deduction</th>

    <td align="right" style="color:red;font-weight:bold">
        Aktif :
        {{ number_format($activeDeduction,0,',','.') }}
    </td>

    <td align="right" style="color:red;font-weight:bold">
        Resign :
        {{ number_format($resignDeduction,0,',','.') }}
    </td>

    <td align="right" style="color:red;font-weight:bold">
        Mangkir :
        {{ number_format($mangkirDeduction,0,',','.') }}
    </td>
</tr>

<tr>
    <th>Net Payroll</th>

    <td align="right" style="font-weight:bold">
        Aktif :
        {{ number_format($activeEarning+$activeDeduction,0,',','.') }}
    </td>

    <td align="right" style="font-weight:bold">
        Resign :
        {{ number_format($resignEarning+$resignDeduction,0,',','.') }}
    </td>

    <td align="right" style="font-weight:bold">
        Mangkir :
        {{ number_format($mangkirEarning+$mangkirDeduction,0,',','.') }}
    </td>
</tr>

<tr>
    <th>Total Karyawan</th>

    <td align="center" style="font-weight:bold">
        Aktif :
        {{ number_format($totalOrangAktif,0,',','.') }} Orang
    </td>

    <td align="center" style="font-weight:bold">
        Resign :
        {{ number_format($totalOrangResign,0,',','.') }} Orang
    </td>

    <td align="center" style="font-weight:bold">
        Mangkir :
        {{ number_format($totalOrangMangkir,0,',','.') }} Orang
    </td>
</tr>
    </table>
    </table>
    {{-- ================= APPROVAL ================= --}}
    @if(!empty($approvals))
      <table style="width:100%;border:none">
      <tr>

      @foreach($approvals as $approve)
      <td align="center" style="border:none;width:25%">

          <div style="height:80px">

              @php
                  $sigPath = storage_path('app/public/signature/'.($approve['signature_img'] ?? ''));
                  $sigUrl = 'file:///' . str_replace('\\', '/', $sigPath);
              @endphp

              <div style="height:80px">
                  @if($approve['status']=='approve' && !empty($approve['signature_img']) && file_exists($sigPath))
                      <img
                          src="{{ $sigUrl }}"
                          style="height:70px"
                      >
                  @endif
              </div>

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