<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <style>
      body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        padding: 20px;
      }

      .header {
        width: 100%;
        border-bottom: 2px solid #000;
        margin-bottom: 20px;
      }

      .logo {
        width: 60px;
      }

      .title {
        text-align: center;
        font-size: 18px;
        font-weight: bold;
      }

      .table {
        width: 100%;
        border-collapse: collapse;
      }

      .table th {
        background: #f2f2f2;
      }

      .table th,
      .table td {
        border: 1px solid #ccc;
        padding: 6px;
      }

      .right {
        text-align: right;
      }

      .center {
        text-align: center;
      }

      .section-title {
        margin-top: 20px;
        font-weight: bold;
      }

      .total {
        font-size: 14px;
        font-weight: bold;
      }

      .footer {
        margin-top: 60px;
      }
    </style>
  </head>
  <body>
    {{-- ===============================
HEADER
=============================== --}}
    <div class="header">
      <table width="100%">
        <tr>
          <td width="100">
            <img src="{{ public_path('img/chutex_logo.png') }}" class="logo">
          </td>
          <td class="title">
            <strong>PT CHUTEX INTERNATIONAL</strong>
            <br> SLIP TUNJANGAN HARI RAYA (THR)
          </td>
          <td width="100"></td>
        </tr>
      </table>
    </div>
    {{-- ===============================
EMPLOYEE INFO
=============================== --}}
    <table width="100%" style="margin-bottom:20px;">
      <tr>
        <td width="140">NPK</td>
        <td>: {{ $employee->employee_npk }}</td>
      </tr>
      <tr>
        <td>Nama</td>
        <td>: {{ $employee->employee_name }}</td>
      </tr>
      <tr>
        <td>Periode THR</td>
        <td>: {{ $employee->period_name }}</td>
      </tr>
    </table>
    {{-- ===============================
THR COMPONENT
=============================== --}}
    <div class="section-title">Perhitungan THR</div>
    <table class="table">
      <tr>
        <th>Komponen</th>
        <th width="200">Jumlah</th>
      </tr>
      <tr>
        <td>Gaji Pokok</td>
        <td class="right"> Rp {{ number_format($components['basic_salary'],0,',','.') }}
        </td>
      </tr>
      <tr>
        <td>Tunjangan Tetap</td>
        <td class="right"> Rp {{ number_format($components['allowance'],0,',','.') }}
        </td>
      </tr>
      <tr>
        <td>Masa Kerja</td>
        <td class="right">
          {{ $components['working_years'] }} Bulan
        </td>
      </tr>
    </table>
    {{-- ===============================
TOTAL THR
=============================== --}}
    <div class="section-title">Total THR</div>
    <table class="table">
      <tr>
        <td class="total">THR Diterima</td>
        <td class="right total"> Rp {{ number_format($employee->total_salary,0,',','.') }}
        </td>
      </tr>
    </table>
    {{-- ===============================
NOTE
=============================== --}}
    <p style="margin-top:20px;font-size:11px;"> THR diberikan sesuai Peraturan Menteri Ketenagakerjaan Republik Indonesia tentang Tunjangan Hari Raya Keagamaan bagi Pekerja/Buruh di Perusahaan. </p>
    {{-- ===============================
SIGNATURE
=============================== --}}
    <div class="footer">
      <table width="100%">
        <tr>
          <td width="50%"></td>
          <td class="center"> HR Department <br>
            <br>
            <br>
            <br> _______________________
          </td>
        </tr>
      </table>
    </div>
  </body>
</html>