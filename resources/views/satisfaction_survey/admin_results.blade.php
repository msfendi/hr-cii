<!DOCTYPE html>
<html lang="id">
@include('layout.header')
<body id="page-top">
@include('sweetalert::alert')
<div id="wrapper">
@include('layout.sidebar')
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @include('layout.navbar')
            <div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Hasil Kuesioner Kepuasan Karyawan</h1>
        <div>
            <a href="{{ route('satisfaction-survey.admin.index') }}" class="btn btn-sm btn-secondary"><i class="fas fa-pen"></i> Kelola Pertanyaan</a>
            <a href="{{ route('satisfaction-survey.admin.export') }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Export Excel (Semua)</a>
            <a href="{{ route('satisfaction-survey.admin.export-likert') }}" class="btn btn-sm btn-info"><i class="fas fa-chart-bar"></i> Export Likert Excel</a>
            <a href="{{ route('satisfaction-survey.admin.export-likert-pdf') }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> Export Likert PDF</a>
        </div>
    </div>
    <p class="small text-muted">Data <strong>anonim</strong> — tidak ada NPK/nama. 1 baris export = 1 partisipan.</p>

    @if ($message = Session::get('success'))
        <div class="alert alert-success">{{ $message }}</div>
    @endif

    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Jawaban Masuk (per Paket Submit)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalResponses }}</div>
                            <div class="small text-muted">paket submit — 1 partisipan = 1 paket (semua pertanyaan)</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-clipboard-check fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-8 col-md-6 mb-3">
            <form method="GET" action="{{ route('satisfaction-survey.admin.export') }}" class="card shadow">
                <div class="card-body">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4">
                            <label>Dari tanggal</label>
                            <input type="date" name="from" class="form-control" value="{{ request('from') }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Sampai tanggal</label>
                            <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                        </div>
                        <div class="form-group col-md-4">
                            <button class="btn btn-success btn-block"><i class="fas fa-file-excel"></i> Export Excel Filter</button>
                        </div>
                    </div>
                    <small class="text-muted">Kosongkan = export semua data. Kolom: No., Waktu Submit, lalu 1 kolom per pertanyaan (Likert 1–5 atau teks open).</small>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Ringkasan per Pertanyaan</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="dataTable">
                    <thead><tr><th>Bagian</th><th>Pertanyaan</th><th width="110">Tipe</th><th width="110">Jumlah Jawab</th><th width="110">Rata-rata Likert</th></tr></thead>
                    <tbody>
                    @foreach($stats as $q)
                        <tr>
                            <td>{{ $q->section }}</td>
                            <td>{{ $q->question_text }}</td>
                            <td>{{ $q->question_type === 'likert' ? 'Likert 1–5' : 'Open' }}</td>
                            <td>{{ $q->responses_count }}</td>
                            <td>{{ $q->question_type === 'likert' ? (isset($avg[$q->id]) && $avg[$q->id] !== null ? number_format($avg[$q->id], 2) : '—') : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">List Jawaban per Paket Submit (tanggal & jam + isi jawaban)</h6></div>
        <div class="card-body">
            @if($paketList->isEmpty())
                <p class="text-muted mb-0">Belum ada jawaban masuk.</p>
            @else
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="paketTable">
                    <thead><tr><th width="60">Paket</th><th width="160">Tanggal & Jam</th><th width="110">Total Jawab</th><th>Daftar Jawaban</th></tr></thead>
                    <tbody>
                    @foreach($paketList as $i => $pk)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $pk['waktu_label'] ?? '—' }}</td>
                            <td>{{ $pk['total'] ?? count($pk['answers'] ?? []) }}</td>
                            <td>
                                <div class="border rounded p-2 bg-light">
                                @foreach($pk['answers'] ?? [] as $r)
                                    <div class="d-flex justify-content-between align-items-center py-1 px-2 mb-1 bg-white border rounded">
                                        <span class="small text-gray-800 mr-2"><span class="badge badge-primary mr-1">{{ $r->question->section ?? '-' }}</span>{{ $r->question->question_text ?? '' }}</span>
                                        @if($r->question && $r->question->isLikert())
                                            @if($r->score >= 4)<span class="badge badge-success text-nowrap">Skor {{ $r->score }} / 5</span>@elseif($r->score >= 3)<span class="badge badge-warning text-nowrap">Skor {{ $r->score }} / 5</span>@else<span class="badge badge-danger text-nowrap">Skor {{ $r->score }} / 5</span>@endif
                                        @else
                                            <span class="small text-muted text-right ml-2">{{ $r->answer_text ? "“{$r->answer_text}”" : '(kosong)' }}</span>
                                        @endif
                                    </div>
                                @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Distribusi Likert per Pertanyaan (skala 1–5)</h6></div>
        <div class="card-body">
            <div class="row">
            @foreach($stats->where('question_type', 'likert') as $q)
                <div class="col-xl-6 col-md-12 mb-4">
                    <div class="font-weight-bold small mb-2">{{ $q->section }}. {{ $q->question_text }}</div>
                    <div style="height:220px;position:relative;"><canvas id="chartQ{{ $q->id }}"></canvas></div>
                    <div class="small text-muted mt-1">Rata-rata: {{ isset($avg[$q->id]) && $avg[$q->id] ? number_format($avg[$q->id],2) : '—' }} · Total: {{ $q->responses_count }}</div>
                </div>
            @endforeach
            </div>
            @if($stats->where('question_type','likert')->isEmpty())
                <p class="text-muted mb-0">Belum ada pertanyaan Likert aktif.</p>
            @endif
        </div>
    </div>
            </div>
        </div>
        @include('layout.footer')
    </div>
</div>
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script>$(function(){ $('#dataTable').DataTable({ pageLength:25 }); if ($('#paketTable').length) $('#paketTable').DataTable({ pageLength:25, order:[[0,'desc']] }); });</script>
<script>
(function(){
  var dist = @json($dist instanceof \Illuminate\Support\Collection ? $dist->all() : $dist);
  Object.keys(dist).forEach(function(qid){
    var el = document.getElementById('chartQ' + qid);
    if (!el) return;
    var d = dist[qid];
    var labels = [1,2,3,4,5];
    var data = labels.map(function(s){ return d[s] || d[String(s)] || 0; });
    new Chart(el, {
      type: 'bar',
      data: {
        labels: ['1','2','3','4','5'],
        datasets: [{
          label: 'Jumlah responden',
          data: data,
          backgroundColor: ['#e74a3b','#f6c23e','#4e73df','#1cc88a','#36b9cc'],
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: {
          x: { title: { display: true, text: 'Skala Likert' }, ticks: { stepSize: 1 } },
          y: { beginAtZero: true, ticks: { precision: 0, stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.05)' } }
        },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function(c){ return c.parsed.y + ' responden'; } } }
        }
      }
    });
  });
})();
</script>
</body>
</html>
