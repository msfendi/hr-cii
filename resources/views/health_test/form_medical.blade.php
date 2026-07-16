<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <style>

   .checkbox{
      display:inline-block;
      width:12px;
      height:12px;
      border:1px solid #000;
      margin-right:6px;
      vertical-align:middle;
   }

   .checkbox.checked{
      background:#000;
   }

   </style>
    <style>
      body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
      }

      table {
        width: 100%;
        border-collapse: collapse;
      }

      td {
        padding: 4px;
      }

      .border td {
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

      .header {
        border: 1px solid black;
        margin-bottom: 8px;
      }

      .section {
        border: 1px solid black;
        margin-top: 10px;
      }
    </style>
  </head>
  <body>
    {{-- ================= HEADER ================= --}}
    <table class="header">
      <tr>
        <td width="20%" class="center">
          <img src="{{ public_path('img/chutex_logo.png') }}" width="70">
        </td>
        <td class="center bold" style="font-size:18px;"> BLANKO TEST <br>KESEHATAN </td>
      </tr>
    </table>
    {{-- ================= IDENTITAS ================= --}}
    <table class="header">
      <tr>
        <td width="25%">NAMA</td>
        <td>: {{ $data->NAMA }}</td>
      </tr>
      <tr>
        <td>TGL. LAHIR</td>
        <td>: {{ \Carbon\Carbon::parse($data->TGL_LAHIR)->translatedFormat('d F Y') }}</td>
      </tr>
      <tr>
        <td>JENIS KELAMIN</td>
        <td>: {{ $data->JENIS_KELAMIN }}</td>
      </tr>
      <tr>
        <td>PENDIDIKAN</td>
        <td>: {{ $data->PENDIDIKAN }}</td>
      </tr>
      <tr>
        <td>ALAMAT</td>
        <td>: {{ $data->ALAMAT_LENGKAP }}</td>
      </tr>
    </table>
    {{-- ================= I KONDISI FISIK ================= --}}
    <table class="section border">
      <tr class="bold center">
        <td width="65%">I. KONDISI FISIK</td>
        <td width="35%">YA / TIDAK</td>
      </tr>
      <tr>
        <td>1. CACAT</td>
        <td class="center">{{ $data->cacat ? 'YA':'TIDAK' }}</td>
      </tr>
      <tr>
        <td>2. BUTA WARNA</td>
        <td class="center">{{ $data->buta_warna ? 'YA':'TIDAK' }}</td>
      </tr>
      <tr>
        <td> 3. VISUS MATA <br> &nbsp;&nbsp;- OD <br> &nbsp;&nbsp;- OS </td>
        <td class="center">{{ $data->visus_mata_od }} <br> {{ $data->visus_mata_os }}</td>
      </tr>
      <tr>
        <td>4. TINGGI BADAN</td>
        <td class="center">{{ $data->tinggi }} CM</td>
      </tr>
      <tr>
        <td>5. BERAT BADAN</td>
        <td class="center">{{ $data->berat }} KG</td>
      </tr>
      <tr>
        <td>6. ABDOMENT</td>
        <td>{{ $data->abdoment }}</td>
      </tr>
      <tr>
        <td>7. GIGI</td>
        <td>{{ $data->gigi }}</td>
      </tr>
      <tr>
        <td>8. COR / PULMO</td>
        <td>{{ $data->cor_pulmo }}</td>
      </tr>
      <tr>
        <td>9. TELINGA / HIDUNG / TENGGOROKAN</td>
        <td>{{ $data->tht }}</td>
      </tr>
      <tr>
        <td>10. EXTREMITAS</td>
        <td>{{ $data->extreme }}</td>
      </tr>
    </table>
    {{-- ================= II VITAL SIGN ================= --}}
    <table class="section border">
      <tr class="bold center">
        <td width="65%">II. VITAL SIGN</td>
        <td width="35%">HASIL</td>
      </tr>
      <tr>
        <td>1. TEKANAN DARAH</td>
        <td class="right">{{ $data->tekanan_darah }} mmHg</td>
      </tr>
      <tr>
        <td>2. RESPIRASI</td>
        <td class="right">{{ $data->respirasi }} x/menit</td>
      </tr>
      <tr>
        <td>3. DENYUT NADI</td>
        <td class="right">{{ $data->denyut }} x/menit</td>
      </tr>
      <tr>
        <td>4. SUHU</td>
        <td class="right">{{ $data->suhu }} °C</td>
      </tr>
    </table>
    {{-- ================= III RIWAYAT ================= --}}
<table class="section border">
  <tr>
    <td>
      <b>III. RIWAYAT PENYAKIT YANG PERNAH DIDERITA :</b>
      <br>
      <br>
      <table width="100%">
        <tr>
          <td width="33%">
            <span class="checkbox {{ $data->paru ? 'checked' : '' }}"></span> PARU-PARU <br>
            <span class="checkbox {{ $data->hepatitis ? 'checked' : '' }}"></span> HEPATITIS <br>
            <span class="checkbox {{ $data->jantung ? 'checked' : '' }}"></span> JANTUNG
          </td>
          <td width="33%">
            <span class="checkbox {{ $data->thypoid ? 'checked' : '' }}"></span> THYPOID <br>
            <span class="checkbox {{ $data->alergi ? 'checked' : '' }}"></span> ALERGI <br>
            <span class="checkbox {{ $data->ashma ? 'checked' : '' }}"></span> ASHMA
          </td>
          <td width="33%">
            <span class="checkbox {{ $data->lain ? 'checked' : '' }}"></span> LAIN-LAIN <br>
            {{ $data->lain }}
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
    {{-- ================= FOOTER ================= --}}
    <table class="section border">
      <tr>
        <td width="60%">
          <b>KESIMPULAN</b>
          <br>
          {{ $data->kesimpulan ? '* SEHAT' : '' }}
          {{ !$data->kesimpulan ? '* KURANG SEHAT' : '' }}
          <br>
          <br>
          <b>Catatan :</b> {{ $data->remark }}
        </td>
        <td class="center"> SUKOHARJO, {{ \Carbon\Carbon::parse($data->created_at)->translatedFormat('d F Y') }}
          <br>
          <br> DOKTER / PETUGAS MEDIS DI <br> POLIKLINIK <br>
          <br>
          <br>
          <br> ( ........................................ )
        </td>
      </tr>
    </table>
  </body>
</html>