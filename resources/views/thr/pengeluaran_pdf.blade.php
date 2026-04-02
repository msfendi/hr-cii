<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>
body{
    font-family:"Courier New", monospace;
    font-size:12px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    padding:5px;
}

.border{
    border:1px solid black;
}

.center{
    text-align:center;
}

.right{
    text-align:right;
}

.bold{
    font-weight:bold;
}
</style>

</head>

<body>

@php

/*
|--------------------------------------------------------------------------
| TERBILANG FUNCTION
|--------------------------------------------------------------------------
*/

function penyebut($nilai) {
    $nilai = abs($nilai);
    $huruf = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    
    if ($nilai < 12) {
        return " " . $huruf[$nilai];
    } elseif ($nilai < 20) {
        return penyebut($nilai - 10) . " Belas";
    } elseif ($nilai < 100) {
        return penyebut(floor($nilai / 10)) . " Puluh" . penyebut($nilai % 10);
    } elseif ($nilai < 200) {
        return " Seratus" . penyebut($nilai - 100);
    } elseif ($nilai < 1000) {
        return penyebut(floor($nilai / 100)) . " Ratus" . penyebut($nilai % 100);
    } elseif ($nilai < 2000) {
        return " Seribu" . penyebut($nilai - 1000);
    } elseif ($nilai < 1000000) {
        return penyebut(floor($nilai / 1000)) . " Ribu" . penyebut($nilai % 1000);
    } elseif ($nilai < 1000000000) {
        return penyebut(floor($nilai / 1000000)) . " Juta" . penyebut($nilai % 1000000);
    } elseif ($nilai < 1000000000000) {
        // ganti fmod dengan %
        return penyebut(floor($nilai / 1000000000)) . " Milyar" . penyebut($nilai % 1000000000);
    } else {
        return "";
    }
}

function terbilang($nilai) {
    return trim(penyebut($nilai)) . " Rupiah";
}

/*
|--------------------------------------------------------------------------
| HITUNG TOTAL THR
|--------------------------------------------------------------------------
*/

$total_thr = 0;

foreach($groupedActive as $dept){
    foreach($dept as $row){
        $total_thr += (float)($row->thr ?? 0);
    }
}

/*
|--------------------------------------------------------------------------
| PEMBULATAN TERBILANG
|--------------------------------------------------------------------------
*/

$netto_bulat = round($total_thr);

@endphp


{{-- HEADER --}}
<div>
    <strong>PT. CHUTEX INTERNATIONAL INDONESIA</strong><br>
    SUKOHARJO
</div>

<br>

<div class="center bold">
    PERMOHONAN PENGELUARAN THR VIA BANK
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
    <td>: {{ $period_name }}</td>
</tr>
<tr>
    <td>Keterangan</td>
    <td>: PEMBAYARAN THR</td>
</tr>
</table>

<br>

{{-- TABLE --}}
<table class="border">
<thead>
<tr>
    <th class="border">URAIAN</th>
    <th class="border">RINCIAN (Rp)</th>
    <th class="border">TOTAL (Rp)</th>
</tr>
</thead>

<tbody>

<tr>
    <td class="border">THR</td>
    <td class="border right">
        {{ number_format($total_thr,0,',','.') }}
    </td>
    <td class="border"></td>
</tr>

<tr>
    <td class="border bold">TOTAL THR</td>
    <td></td>
    <td class="border right bold">
        {{ number_format($total_thr,0,',','.') }}
    </td>
</tr>

</tbody>
</table>

<br><br>
        @php
        $netto_aktif = $total_thr; // bisa float besar

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

<br><br><br>

<table width="100%">
<tr>
    <td>Mengetahui,</td>
    <td class="right">Diajukan,</td>
</tr>

<tr>
<td>
<br><br><br>
</td>
<td></td>
</tr>

<tr>
<td>Vice President</td>
<td class="right">Manager HRD</td>
</tr>
</table>

</body>
</html>