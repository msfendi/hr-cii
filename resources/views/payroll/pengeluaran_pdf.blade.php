<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <style>
      .page-break {
        page-break-before: always;
      }

      body {
        font-family: "Courier New", monospace;
        font-size: 12px;
      }

      table {
        width: 100%;
        border-collapse: collapse;
      }

      th,
      td {
        padding: 5px;
      }

      .border {
        border: 1px solid black;
      }

      .center {
        text-align: center;
      }

      .right {
        text-align: right;
      }

      .bold {
        font-weight: bold;
      }
    </style>
  </head>
  <body> @php $periode = '-'; if($groupedActive->count()){ $firstDept = $groupedActive->first(); if($firstDept->count()){ $periode = $firstDept->first()->period_name; } } @endphp {{-- ===================================== --}}
    {{-- HALAMAN 1 - KARYAWAN AKTIF --}}
    {{-- ===================================== --}} @php $total_bruto = 0; $total_potongan = 0; @endphp <div>
      <strong>PT. CHUTEX INTERNATIONAL INDONESIA</strong>
      <br> SUKOHARJO
    </div>
    <br>
    <div class="center bold"> PERMOHONAN PENGELUARAN GAJI VIA BANK </div>
    <br>
    <table>
      <tr>
        <td width="150">Kepada</td>
        <td>: Kepala bagian Kasir</td>
      </tr>
      <tr>
        <td>Dari</td>
        <td>: Seksi Gaji</td>
      </tr>
      <tr>
        <td>Tanggal Bayar</td>
        <td>: {{ $periode }}</td>
      </tr>
      <tr>
        <td>Keterangan</td>
        <td>: KARYAWAN AKTIF</td>
      </tr>
    </table>
    <br>
    <table class="border">
      <thead>
        <tr>
          <th class="border">URAIAN</th>
          <th class="border">RINCIAN (Rp)</th>
          <th class="border">TOTAL (Rp)</th>
        </tr>
      </thead>
      <tbody>
        {{-- EARNING --}} @foreach($allComponents as $code => $component) @php $value = $activeTotals[$code] ?? 0; @endphp @if(($component->type ?? 'earning') == 'earning') @php $total_bruto += $value; @endphp <tr>
          <td class="border">{{ $component->name }}</td>
          <td class="border right">{{ number_format($value,0,',','.') }}</td>
          <td class="border"></td>
        </tr> @endif @endforeach <tr>
          <td class="border bold">TOTAL UPAH BRUTO</td>
          <td class="border"></td>
          <td class="border right bold">{{ number_format($total_bruto,0,',','.') }}</td>
        </tr>
        {{-- POTONGAN --}}
        <tr>
          <td class="border bold">POTONGAN</td>
          <td class="border"></td>
          <td class="border"></td>
        </tr> @foreach($allComponents as $code => $component) @php $value = $activeTotals[$code] ?? 0; @endphp @if(($component->type ?? 'earning') == 'deduction') @php $total_potongan += $value; @endphp <tr>
          <td class="border">{{ $component->name }}</td>
          <td class="border right">{{ number_format($value,0,',','.') }}</td>
          <td class="border"></td>
        </tr> @endif @endforeach <tr>
          <td class="border bold">TOTAL POTONGAN</td>
          <td class="border"></td>
          <td class="border right bold">{{ number_format($total_potongan,0,',','.') }}</td>
        </tr>
        <tr>
          <td class="border bold">TOTAL UPAH NETTO</td>
          <td></td>
          <td class="border right bold">
            {{ number_format($total_bruto - $total_potongan,0,',','.') }}
          </td>
        </tr>
      </tbody>
    </table>
    <br>
    <br>
    <div>
        @php
        $netto_aktif = $total_bruto - $total_potongan; // bisa float besar

        // Ubah ke string dengan presisi tinggi
        $netto_str = number_format($netto_aktif, 4, '.', ''); // 4 desimal
        $parts = explode('.', $netto_str);
        $int_part = (int)$parts[0];
        $decimal_part = (float)('0.' . ($parts[1] ?? 0));

        // Pembulatan normal
        if ($decimal_part >= 0.5) {
            $netto_bulat = $int_part + 1;
        } else {
            $netto_bulat = $int_part;
        }
        @endphp

        <div>Terbilang: <i>{{ terbilang($netto_bulat) }}</i></div>
    </div>
    <br>
    <br>
    <br>
    
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
    {{-- ===================================== --}}
{{-- HALAMAN 2 - KARYAWAN RESIGN --}}
{{-- ===================================== --}}
@if(!empty($resignTotals))

<div class="page-break"></div>

@php
$total_bruto = 0;
$total_potongan = 0;
@endphp

<div>
  <strong>PT. CHUTEX INTERNATIONAL INDONESIA</strong><br>
  SUKOHARJO
</div>

<br>

<div class="center bold">
  PERMOHONAN PENGELUARAN GAJI VIA BANK
</div>

<br>

<table>
<tr>
  <td width="150">Kepada</td>
  <td>: Kepala bagian Kasir</td>
</tr>
<tr>
  <td>Dari</td>
  <td>: Seksi Gaji</td>
</tr>
<tr>
  <td>Tanggal Bayar</td>
  <td>: {{ $periode }}</td>
</tr>
<tr>
  <td>Keterangan</td>
  <td>: KARYAWAN RESIGN</td>
</tr>
</table>

<br>

<table class="border">
      <thead>
        <tr>
          <th class="border">URAIAN</th>
          <th class="border">RINCIAN (Rp)</th>
          <th class="border">TOTAL (Rp)</th>
        </tr>
      </thead>
      <tbody>
        {{-- EARNING --}} @foreach($allComponents as $code => $component) @php $value = $resignTotals[$code] ?? 0; @endphp @if(($component->type ?? 'earning') == 'earning') @php $total_bruto += $value; @endphp <tr>
          <td class="border">{{ $component->name }}</td>
          <td class="border right">{{ number_format($value,0,',','.') }}</td>
          <td class="border"></td>
        </tr> @endif @endforeach <tr>
          <td class="border bold">TOTAL UPAH BRUTO</td>
          <td class="border"></td>
          <td class="border right bold">{{ number_format($total_bruto,0,',','.') }}</td>
        </tr>
        {{-- POTONGAN --}}
        <tr>
          <td class="border bold">POTONGAN</td>
          <td class="border"></td>
          <td class="border"></td>
        </tr> @foreach($allComponents as $code => $component) @php $value = $resignTotals[$code] ?? 0; @endphp @if(($component->type ?? 'earning') == 'deduction') @php $total_potongan += $value; @endphp <tr>
          <td class="border">{{ $component->name }}</td>
          <td class="border right">{{ number_format($value,0,',','.') }}</td>
          <td class="border"></td>
        </tr> @endif @endforeach <tr>
          <td class="border bold">TOTAL POTONGAN</td>
          <td class="border"></td>
          <td class="border right bold">{{ number_format($total_potongan,0,',','.') }}</td>
        </tr>
        <tr>
          <td class="border bold">TOTAL UPAH NETTO</td>
          <td></td>
          <td class="border right bold">
            {{ number_format($total_bruto - $total_potongan,0,',','.') }}
          </td>
        </tr>
      </tbody>
    </table>
    <br>
    <br>
    <div>
        @php
        $netto_resign = $total_bruto - $total_potongan; // bisa float besar

        // Ubah ke string dengan presisi tinggi
        $netto_str = number_format($netto_resign, 4, '.', ''); // 4 desimal
        $parts = explode('.', $netto_str);
        $int_part = (int)$parts[0];
        $decimal_part = (float)('0.' . ($parts[1] ?? 0));

        // Pembulatan normal
        if ($decimal_part >= 0.5) {
            $netto_bulat = $int_part + 1;
        } else {
            $netto_bulat = $int_part;
        }
        @endphp

        <div>Terbilang: <i>{{ terbilang($netto_bulat) }}</i></div>
    </div>
    <br>
    <br>
    <br>
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

@endif
  </body>
</html>