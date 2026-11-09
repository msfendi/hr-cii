<!DOCTYPE html>
<html lang="id"><head>
<meta charset="utf-8">
<title>Distribusi Likert Kuesioner Kepuasan</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
  h1 { font-size: 16px; margin: 0 0 2px; }
  h2 { font-size: 13px; margin: 14px 0 4px; color: #4e73df; }
  .meta { font-size: 10px; color: #555; margin-bottom: 10px; }
  table { border-collapse: collapse; width: 100%; margin-bottom: 12px; }
  th, td { border: 1px solid #b0b7d3; padding: 5px 6px; text-align: left; vertical-align: top; }
  th { background: #4e73df; color: #fff; }
  .num { text-align: center; }
  .total { font-weight: bold; background: #f8f9fc; }
  .bar { height: 8px; display: inline-block; vertical-align: middle; }
  .b1 { background: #e74a3b; } .b2 { background: #f6c23e; } .b3 { background: #4e73df; } .b4 { background: #1cc88a; } .b5 { background: #36b9cc; }
  .scale { font-size: 9px; color: #555; display: inline-block; vertical-align: middle; }
  .pct { font-size: 9px; color: #555; }
</style>
</head><body>
<h1>Distribusi Likert — Kuesioner Kepuasan Karyawan</h1>
<div class="meta">
  Dicetak {{ now()->format('Y-m-d H:i:s') }} (WIB) ·
  @if($from || $to)
    Filter: {{ $from ?: '—' }} s.d. {{ $to ?: '—' }} ·
  @endif
  Total jawaban Likert (semua pertanyaan): {{ $totalAll }} · Anonim (tanpa NPK/nama)
</div>
<table>
  <thead>
    <tr><th class="num">No.</th><th class="num">Bagian</th><th>Pertanyaan</th>
    <th class="num">Skor 1</th><th class="num">Skor 2</th><th class="num">Skor 3</th><th class="num">Skor 4</th><th class="num">Skor 5</th>
    <th class="num">Total</th><th class="num">Rata-rata</th></tr>
  </thead>
  <tbody>
  @foreach($questions as $i => $q)
    @php
      $d = $dist[$q->id] ?? [1=>0,2=>0,3=>0,4=>0,5=>0];
      $tot = array_sum($d);
      $mx = max(1, max($d));
    @endphp
    <tr>
      <td class="num">{{ $i + 1 }}</td>
      <td class="num">{{ $q->section }}</td>
      <td>{{ $q->question_text }}</td>
      @for($s = 1; $s <= 5; $s++)
        <td class="num">
          <span class="bar b{{ $s }}" style="width:{{ $tot ? round($d[$s] / $mx * 50) : 0 }}px;"></span>
          <span class="pct">{{ $d[$s] }}</span>
        </td>
      @endfor
      <td class="num total">{{ $tot }}</td>
      <td class="num total">{{ isset($avg[$q->id]) && $avg[$q->id] !== null ? number_format($avg[$q->id], 2) : '—' }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
<p class="meta">Skala: 1 = Sangat tidak setuju · 2 = Tidak setuju · 3 = Netral · 4 = Setuju · 5 = Sangat setuju. Bar mini di tiap sel menunjukkan proporsi relatif terhadap skor tertinggi pertanyaan tersebut.</p>
</body></html>
