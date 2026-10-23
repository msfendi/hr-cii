{{-- Tabel satu department, dipecah per "Bagian" agar muat A4 landscape dengan kolom lega --}}
@php
  $totDept = $deptTotal($employees, $allComponents);
  $absKeys = ['MA','P1','CT','CU','SD','BR','OUT'];
  $totAbs  = [];
  foreach ($absKeys as $k) { $totAbs[$k] = $employees->sum(fn($x) => $x->$k ?? 0); }
  $totIjin = $employees->sum(fn($x) => $x->total_ijin_minutes ?? 0);
  $lastIdx = count($chunks) - 1;
@endphp

@foreach($chunks as $ci => $chunkCodes)
@php
  $isFirst = $ci === 0;
  $isLast  = $ci === $lastIdx;
  $tw = $W['npk'] + $W['name']
      + ($isFirst ? (7 * $W['abs']) + $W['ijin'] : 0)
      + (count($chunkCodes) * $W['comp'])
      + ($isLast ? $W['total'] : 0);
@endphp

@if(count($chunks) > 1)
<div class="chunk-label">Bagian {{ $ci + 1 }} / {{ count($chunks) }}</div>
@endif

<table style="width:{{ $tw }}mm">
  <thead>
    <tr>
      <th class="col-npk">NPK</th>
      <th class="col-name">Name</th>

      @if($isFirst)
      @foreach($absKeys as $k)
      <th class="col-abs">{{ $k }}</th>
      @endforeach
      <th class="col-details">Ijin (Menit)</th>
      @endif

      @foreach($chunkCodes as $code)
      <th class="col-component">{{ $allComponents[$code]->name }}</th>
      @endforeach

      @if($isLast)
      <th class="col-total">Total Salary</th>
      @endif
    </tr>
  </thead>

  <tbody>
    @foreach($employees as $item)
    <tr>
      <td>{{ $item->employee_npk }}</td>
      <td>{{ $item->employee_name }}</td>

      @if($isFirst)
      @foreach($absKeys as $k)
      <td align="center">{{ number_format($item->$k ?? 0,0,',','.') }}</td>
      @endforeach
      <td align="center">{{ number_format($item->total_ijin_minutes ?? 0,0,',','.') }}</td>
      @endif

      @foreach($chunkCodes as $code)
      <td align="right" class="num">{{ number_format($item->$code ?? 0,0,',','.') }}</td>
      @endforeach

      @if($isLast)
      <td align="right" class="num">{{ number_format($item->total_salary,0,',','.') }}</td>
      @endif
    </tr>
    @endforeach

    <tr style="background:#f5f5f5;font-weight:bold">
      <td colspan="2" align="center">Total Karyawan: {{ $employees->count() }}</td>

      @if($isFirst)
      @foreach($absKeys as $k)
      <td align="center">{{ number_format($totAbs[$k],0,',','.') }}</td>
      @endforeach
      <td align="center">{{ number_format($totIjin,0,',','.') }}</td>
      @endif

      @foreach($chunkCodes as $code)
      <td align="right" class="num">{{ number_format($totDept['components'][$code] ?? 0,0,',','.') }}</td>
      @endforeach

      @if($isLast)
      <td align="right" class="num">{{ number_format($totDept['salary'],0,',','.') }}</td>
      @endif
    </tr>
  </tbody>
</table>
@endforeach
