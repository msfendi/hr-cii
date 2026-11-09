@extends('layouts.app')

@section('title', 'Live: ' . $quiz->title)

@section('content')
<!-- Scoped: hilangkan double padding container di live (layouts/app .container-fluid.py-3
     sudah padding 1.5rem, tambah mb-4 + card-body 1.25rem bawaan = terasa ganda).
     Override lokal saja, tidak ubah layout global. -->
<style>
.container-fluid.py-3 { padding: 0.5rem 1rem !important; }
/* Oval padat: batasi lebar kontainer word cloud jadi 56rem agar cluster
   tetap mengelompok di tengah, bukan memanjang satu baris kiri->kanan. */
.word-cloud-container { max-width: 56rem; margin: 0 auto !important; }
</style>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1 text-gray-800">
            <i class="fas fa-broadcast-tower text-danger mr-2 pulse-icon"></i> {{ $quiz->title }}
        </h1>
        <p class="text-muted small mb-0">
            <span class="badge badge-{{ $quiz->isQuiz() ? 'primary' : ($quiz->isPolling() ? 'info' : 'success') }}">
                <i class="fas fa-{{ $quiz->isQuiz() ? 'graduation-cap' : ($quiz->isPolling() ? 'chart-bar' : 'cloud') }} mr-1"></i>
                {{ $quiz->modeLabel() }}
            </span>
            <span class="ml-2 text-success"><i class="fas fa-circle blink-dot"></i> Live</span>
        </p>
    </div>
    <div>
        <a href="{{ route('quiz.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
        <form action="{{ route('quiz.close', $quiz->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-danger" onclick="return confirm('Tutup sesi ini? Peserta tidak bisa lagi bergabung atau menjawab.')">
                <i class="fas fa-stop-circle mr-1"></i> Tutup Sesi
            </button>
        </form>
    </div>
</div>

{{-- Metrics Cards --}}
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-left-primary shadow h-100 py-2 metric-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Peserta Aktif</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalParticipants">0</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-left-success shadow h-100 py-2 metric-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Jawaban</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalResponses">0</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-left-info shadow h-100 py-2 metric-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pertanyaan</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $quiz->questions->count() }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-question-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-left-warning shadow h-100 py-2 metric-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">PIN Sesi</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $quiz->qr_code }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-key fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Questions Grid --}}
<div id="questionsContainer"></div>

@endsection

@section('styles')
<style>
.pulse-icon { animation: pulse 1.5s ease-in-out infinite; }
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.blink-dot { animation: blink 1.2s ease-in-out infinite; }
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
.metric-card { transition: transform 0.2s ease; }
.metric-card:hover { transform: translateY(-3px); }

.question-card {
    transition: all 0.3s ease;
    border-left: 4px solid #4e73df;
}
.question-card:hover { box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); }

.word-cloud-container {
    /* Sama persis dengan gambar pertama: putih, border biru tebal, rounded besar,
       cluster oval padat di tengah, tidak lurus horizontal memanjang. */
    background: #ffffff;
    border: 3px solid #2b4acb;
    border-radius: 1.5rem;
    padding: 3rem 2.5rem;
    min-height: 360px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    align-content: center;
    justify-content: center;
    /* rapat seperti gambar: gap kecil, bukan whitespace besar */
    gap: 0.15rem 0.7rem;
    line-height: 1.1;
    text-align: center;
}
.word-item {
    font-weight: 600;
    font-family: Nunito, Arial, sans-serif;
    line-height: 1.05;
    letter-spacing: -0.02em;
    display: inline-block;
    margin: 0 0.3rem;
    white-space: normal;
    animation: fadeInScale 0.55s ease-out;
    transition: transform 0.2s ease;
    cursor: default;
}
.word-item:hover { transform: scale(1.07); }
.word-empty { color: #999; font-size: 1rem; width: 100%; text-align: center; margin: 0; }
@keyframes fadeInScale {
    from { opacity: 0; transform: scale(0.5); }
    to { opacity: 1; transform: scale(1); }
}

canvas { max-height: 400px; }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
const quizMode = '{{ $quiz->mode }}';
const apiUrl = '{{ route("quiz.live-data", $quiz->id) }}';
let charts = {};

function fetchData() {
    fetch(apiUrl)
        .then(r => r.json())
        .then(data => {
            document.getElementById('totalParticipants').textContent = data.participants || 0;
            document.getElementById('totalResponses').textContent = data.total_responses || 0;
            renderQuestions(data.questions || []);
        })
        .catch(err => console.error('Fetch error:', err));
}

function renderQuestions(questions) {
    const container = document.getElementById('questionsContainer');
    container.innerHTML = '';

    questions.forEach((q, idx) => {
        const card = document.createElement('div');
        card.className = 'card shadow mb-4 question-card';
        card.innerHTML = `
            <div class="card-header py-3 bg-gradient-primary text-white">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-${quizMode === 'word_cloud' ? 'cloud' : 'poll'} mr-2"></i>
                    Pertanyaan ${idx + 1}: ${q.question_text}
                </h6>
                <small class="d-block mt-1 opacity-75">${q.total_votes || 0} jawaban</small>
            </div>
            <div class="card-body">
                <div id="question-${q.id}"></div>
            </div>
        `;
        container.appendChild(card);

        if (q.type === 'open_text') {
            renderWordCloud(q.id, q.word_cloud || []);
        } else {
            renderBarChart(q.id, q.options || []);
        }
    });
}

function renderBarChart(qid, options) {
    const target = document.getElementById(`question-${qid}`);
    target.innerHTML = '<canvas></canvas>';
    const canvas = target.querySelector('canvas');
    const ctx = canvas.getContext('2d');

    const labels = options.map(o => o.option_text || 'N/A');
    const counts = options.map(o => o.count || 0);
    const percentages = options.map(o => o.percentage || 0);

    if (charts[qid]) charts[qid].destroy();

    charts[qid] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Jumlah Suara',
                data: counts,
                backgroundColor: 'rgba(78, 115, 223, 0.8)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const pct = percentages[context.dataIndex];
                            return `${context.parsed.y} suara (${pct.toFixed(1)}%)`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
}

function renderWordCloud(qid, words) {
    const target = document.getElementById(`question-${qid}`);
    words = words || [];

    if (words.length === 0) {
        target.innerHTML = '<div class="word-cloud-container"><p class="word-empty mb-0">Belum ada jawaban.</p></div>';
        return;
    }

    // Palet gambar pertama: ungu/indigo, hijau, biru, coklat, merah bata — muted/jewel tone.
    const palette = ['#4b3f8f', '#6d28a8', '#2e5aa8', '#2f7d6b', '#5a8a3c', '#a85a2e', '#8f3f3f', '#3f6d8f', '#7a4a8f', '#b08d2e', '#3a5a8f', '#6b3a6b'];
    const cloud = document.createElement('div');
    cloud.className = 'word-cloud-container';

    // Acak urutan agar tidak lurus memanjang kiri->kanan. Gambar pertama
    // posisi kata acak membentuk oval, bukan baris terurut.
    const shuffled = [...words];
    for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }
    const maxWeight = Math.max(...words.map(w => w.weight || 1));
    const minWeight = Math.min(...words.map(w => w.weight || 1));
    const range = Math.max(1, maxWeight - minWeight);

    shuffled.forEach((w, idx) => {
        const span = document.createElement('span');
        span.className = 'word-item';
        span.textContent = (w.word || '').toLowerCase();
        const t = ((w.weight || 1) - minWeight) / range; // 0..1
        // Ukuran ala gambar pertama: dominan 44-58px, sedang 22-36px, kecil 14-20px
        let px = Math.round(14 + t * 40);
        // variasi acak ±4px agar tidak seragam satu baris
        px += Math.round((Math.random() - 0.5) * 8);
        if ((w.weight || 1) === maxWeight && words.length > 1) {
            px = 56 + Math.round((Math.random() - 0.5) * 4);
        }
        span.style.fontSize = px + 'px';
        span.style.color = palette[idx % palette.length];
        // offset vertikal acak kecil untuk kesan awan tidak rata
        span.style.transform = 'translateY(' + Math.round((Math.random() - 0.5) * 8) + 'px)';
        cloud.appendChild(span);
        // Pecah baris pseudo-acak setiap 3-5 kata agar tidak lurus memanjang
        if (idx > 1 && idx < shuffled.length - 1 && (idx + 1) % 4 === 0) {
            cloud.appendChild(document.createElement('br'));
        }
    });

    target.innerHTML = '';
    target.appendChild(cloud);
}

// Auto-refresh setiap 3 detik
setInterval(fetchData, 3000);
fetchData();
</script>
@endsection
