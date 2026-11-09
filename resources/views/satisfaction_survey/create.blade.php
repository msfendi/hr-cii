<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kuesioner Kepuasan Karyawan — PT Chutex International Indonesia</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">

    <style>
        body { background: linear-gradient(135deg, #f6f9ff 0%, #e8eefc 100%); }
        .survey-card { border: none; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(0,0,0,.08); overflow: hidden; }
        .section-title { background: #4e73df; color: #fff; padding: .75rem 1rem; border-radius: .75rem; font-weight: 700; margin: 1rem 0 .75rem; }
        .likert-row { display: flex; flex-wrap: wrap; gap: .5rem; }
        .likert-row label { flex: 1 1 18%; min-width: 64px; }
        .likert-opt { display: block; text-align: center; border: 2px solid #e3e6f0; border-radius: .75rem; padding: .6rem .25rem; cursor: pointer; font-weight: 600; background: #fff; }
        .likert-opt input { display: none; }
        .likert-opt input:checked + .likert-label { background: #4e73df; color: #fff; }
        .likert-opt:has(input:checked) { background: #4e73df; color: #fff !important; border-color: #4e73df; }
        .likert-opt:has(input:checked) .likert-label { background: transparent !important; color: #fff !important; }
        .likert-label { display: block; border-radius: .5rem; padding: .2rem 0; }
        .likert-opt small { display: block; font-weight: 400; font-size: .65rem; }
        @media (max-width: 576px) {
            .likert-row label { flex: 1 1 31%; }
            .card-body { padding: 1rem !important; }
        }
        textarea[rows] { width: 100%; border-radius: .75rem; }
        .progress-bar-soft { height: 8px; background: #eef2fd; border-radius: 999px; overflow: hidden; }
        .progress-bar-soft > div { height: 100%; background: #4e73df; width: 0%; transition: width .3s; }
    </style>
</head>
<body>
<div class="container py-3">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card survey-card">
                <div class="card-header bg-gradient-primary text-white text-center py-3">
                    <h5 class="font-weight-bold mb-0"><i class="fas fa-heart mr-2"></i> Kuesioner Kepuasan Karyawan</h5>
                    <small>Anonim — tanpa nama / nomor karyawan. Tidak ada jawaban benar atau salah.</small>
                </div>
                <div class="card-body p-4 p-md-5">

                    <div class="alert alert-info small">
                        Berikan tanda centang (✓) pada salah satu pilihan: <strong>1</strong> Sangat Tidak Setuju · <strong>2</strong> Tidak Setuju · <strong>3</strong> Biasa Saja · <strong>4</strong> Setuju · <strong>5</strong> Sangat Setuju.
                        Jawab sesuai pengalaman Anda yang sebenarnya.
                    </div>

                    <div class="progress-bar-soft mb-3"><div id="progressFill"></div></div>
                    <div class="small text-muted mb-3" id="progressText">0 / {{ $questions->count() }} terjawab</div>

                    @if ($errors->any())
                        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                    @endif

                    <form id="surveyForm" action="{{ route('satisfaction-survey.store') }}" method="POST">
                        @csrf

                        @php $no = 0; @endphp
                        @foreach($grouped as $section => $qgroup)
                            @php
                                $titles = ['B' => 'B. Tentang Pekerjaan', 'C' => 'C. Tentang Atasan', 'D' => 'D. Tentang Lingkungan Kerja', 'E' => 'E. Sikap dan Perlakuan di Tempat Kerja', 'F' => 'F. Tentang Perusahaan', 'G' => 'G. Pendapat dan Saran (boleh dikosongkan)'];
                                $title = $titles[$section] ?? "Bagian {$section}";
                            @endphp
                            <div class="section-title">{{ $title }}</div>
                            @foreach($qgroup as $q)
                                @php $no++; @endphp
                                <div class="form-group p-3 bg-white border rounded" style="border-radius:.75rem;">
                                    <label class="font-weight-bold mb-2"><span class="badge badge-primary">{{ $no }}</span> {{ $q->question_text }}</label>

                                    @if($q->isLikert() || $q->question_type === 'likert')
                                        <div class="likert-row">
                                            @foreach([1=>'Sangat Tidak Setuju',2=>'Tidak Setuju',3=>'Biasa Saja',4=>'Setuju',5=>'Sangat Setuju'] as $score => $label)
                                            <label>
                                                <span class="likert-opt">
                                                    <input type="radio" name="answers[{{ $q->id }}]" value="{{ $score }}" {{ old("answers.{$q->id}") == $score ? 'checked' : '' }}>
                                                    <span class="likert-label">{{ $score }} <small>{{ $label }}</small></span>
                                                </span>
                                            </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <textarea name="answers[{{ $q->id }}]" rows="3" class="form-control" placeholder="Tulis pendapat Anda… (opsional)">{{ old("answers.{$q->id}") }}</textarea>
                                    @endif
                                </div>
                            @endforeach
                        @endforeach

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg px-5" id="btnSubmit">
                                <i class="fas fa-paper-plane mr-1"></i> Kirim Jawaban
                            </button>
                            <div class="small text-muted mt-2">Pendapat Anda penting untuk menjadikan Chutex tempat kerja yang lebih baik. Terima kasih atas partisipasi Anda.</div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3"><a href="/" class="text-muted small"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Beranda</a></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  const form = document.getElementById('surveyForm');
  const total = {{ $questions->count() }};
  function updateProgress(){
    const answered = document.querySelectorAll('#surveyForm input[type=\"radio\"]:checked').length + document.querySelectorAll('#surveyForm textarea').length;
    // hitung textarea non-kosong saja untuk progress open
    let openAnswered = 0;
    document.querySelectorAll('#surveyForm textarea').forEach(t=>{ if(t.value.trim()!=='') openAnswered++; });
    const likertAnswered = document.querySelectorAll('#surveyForm input[type=\"radio\"]:checked').length;
    const pct = total ? Math.round((likertAnswered + openAnswered) / total * 100) : 0;
    document.getElementById('progressFill').style.width = pct + '%';
    document.getElementById('progressText').textContent = (likertAnswered + openAnswered) + ' / ' + total + ' terjawab';
  }
  document.addEventListener('change', updateProgress);
  document.addEventListener('input', updateProgress);
  updateProgress();

  form.addEventListener('submit', function(e){
    const likertAll = document.querySelectorAll('#surveyForm input[type=\"radio\"]');
    // pastikan setiap pertanyaan likert punya jawaban
    const groups = {};
    likertAll.forEach(r=>{ if(!groups[r.name]) groups[r.name]=false; if(r.checked) groups[r.name]=true; });
    for(const k in groups){ if(!groups[k]){ e.preventDefault(); document.querySelector(`input[name=\"${k}\"]`).focus(); if(window.Swal) Swal.fire({icon:'warning', title:'Belum lengkap', text:'Masih ada pertanyaan yang belum dijawab. Silakan jawab semua pertanyaan likert.'}); return; } }
    document.getElementById('btnSubmit').disabled = true;
    document.getElementById('btnSubmit').innerHTML = '<i class=\"fas fa-spinner fa-spin mr-1\"></i> Mengirim…';
    // Fallback: bila fetch/route berbeda, biarkan submit normal. Kita sengaja tidak fetch agar CSRF & redirect konsisten.
  });
})();
</script>
</body>
</html>
