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

    <style>
        .ai-analysis-card { border: 0; }
        .ai-analysis-card .card-header {
            background: linear-gradient(90deg, #eef4ff 0%, #f6f9ff 60%, #ffffff 100%);
            border-bottom: 1px solid #e3e6f0;
            gap: 10px;
        }
        .ai-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ai-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #4e73df;
            color: #fff;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .ai-panel {
            height: 100%;
            padding: 12px 14px;
            border: 1px solid #e3e6f0;
            border-radius: 12px;
            background: #fbfcff;
        }
        .ai-panel-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .ai-panel-icon {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #eef4ff;
            color: #4e73df;
        }
        .ai-panel-title { font-size: .86rem; font-weight: 800; color: #2f3a56; }
        .ai-panel-sub { font-size: .7rem; color: #858796; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .ai-list { list-style: none; margin: 0; padding: 0; }
        .ai-item {
            display: flex;
            gap: 10px;
            padding: 9px 0;
            border-top: 1px dashed #e3e6f0;
        }
        .ai-item:first-child { border-top: 0; padding-top: 0; }
        .ai-rank {
            flex: 0 0 24px;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #4e73df;
            color: #fff;
            font-size: .72rem;
            font-weight: 800;
        }
        .ai-item-title { font-size: .84rem; font-weight: 700; color: #2f3a56; }
        .ai-item-text { font-size: .78rem; color: #6b7280; line-height: 1.45; }
        .ai-metrics { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
        .ai-metric {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #eef4ff;
            color: #3d5ab8;
            font-size: .68rem;
            font-weight: 700;
        }
        .ai-item-empty { color: #858796; font-size: .78rem; display: block; }
        .ai-foot {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #e3e6f0;
            color: #858796;
            font-size: .72rem;
        }
        .ai-empty { text-align: center; padding: 14px 8px; color: #858796; }
        .ai-empty > i { font-size: 1.7rem; color: #c7d2fe; margin-bottom: 6px; }
        .export-compact-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 32px;
            padding: 0 12px;
            white-space: nowrap;
            font-size: .8rem;
            font-weight: 700;
        }
    </style>

    <div class="row mb-4">
        <div class="col-xl-4 col-md-12 mb-3">
            <form method="GET" action="{{ route('satisfaction-survey.admin.export') }}" class="card shadow mb-3">
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-6 mb-2">
                            <label class="small mb-1">Dari tanggal</label>
                            <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
                        </div>
                        <div class="form-group col-6 mb-2">
                            <label class="small mb-1">Sampai tanggal</label>
                            <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
                        </div>
                    </div>
                    <button class="btn btn-success btn-sm btn-block export-compact-btn">
                        <i class="fas fa-file-excel mr-1"></i>Export Excel
                    </button>
                    <small class="text-muted d-block mt-2">Kosongkan = export semua data. Kolom: No., Waktu Submit, lalu 1 kolom per pertanyaan (Likert 1-5 atau teks open).</small>
                </div>
            </form>

            <div class="card border-left-primary shadow py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Jawaban Masuk (per Paket Submit)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalResponses }}</div>
                            <div class="small text-muted">jumlah paket submit yang teridentifikasi</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-clipboard-check fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-md-12 mb-3">
            <div class="card shadow h-100 ai-analysis-card">
                <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                    <div class="ai-head">
                        <span class="ai-chip"><i class="fas fa-brain"></i> AI Analysis</span>
                        <h6 class="m-0 font-weight-bold text-gray-800">Tiga Masalah Utama</h6>
                    </div>
                    <form method="POST" action="{{ route('satisfaction-survey.admin.results.analyze') }}" id="aiAnalyzeForm">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary" id="aiAnalyzeButton">
                            <i class="fas fa-wand-magic-sparkles mr-1"></i>{{ $aiAnalysis ? 'Perbarui Analisis' : 'Buat Analisis' }}
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    @php
                        $analysisPayload = $aiAnalysis && is_array($aiAnalysis->analysis_json) ? $aiAnalysis->analysis_json : [];
                        $answerProblems = $analysisPayload['answers'] ?? $analysisPayload['problems'] ?? [];
                        $scoreProblems = $analysisPayload['scores'] ?? [];
                        $topRequests = $analysisPayload['requests'] ?? [];
                    @endphp

                    @if($aiAnalysis && (count($answerProblems) || count($scoreProblems) || count($topRequests)))
                        <div class="row">
                            <div class="col-lg-4 mb-3 mb-lg-0">
                                <div class="ai-panel">
                                    <div class="ai-panel-head">
                                        <span class="ai-panel-icon"><i class="fas fa-comment-dots"></i></span>
                                        <div>
                                            <div class="ai-panel-title">Dari Jawaban Esai</div>
                                            <div class="ai-panel-sub">answer_text</div>
                                        </div>
                                    </div>
                                    <ol class="ai-list">
                                        @foreach($answerProblems as $problem)
                                            <li class="ai-item">
                                                <span class="ai-rank">{{ $loop->iteration }}</span>
                                                <div>
                                                    <div class="ai-item-title">{{ $problem['title'] ?? 'Masalah' }}</div>
                                                    <div class="ai-item-text">{{ $problem['summary'] ?? '' }}</div>
                                                    @if(isset($problem['evidence_count']))
                                                        <span class="ai-metric">{{ (int) $problem['evidence_count'] }} sebutan</span>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            </div>
                            <div class="col-lg-4 mb-3 mb-lg-0">
                                <div class="ai-panel">
                                    <div class="ai-panel-head">
                                        <span class="ai-panel-icon"><i class="fas fa-hand-point-up"></i></span>
                                        <div>
                                            <div class="ai-panel-title">Top 3 Permintaan Karyawan</div>
                                            <div class="ai-panel-sub">dari answer_text</div>
                                        </div>
                                    </div>
                                    <ol class="ai-list">
                                        @forelse($topRequests as $req)
                                            <li class="ai-item">
                                                <span class="ai-rank">{{ $loop->iteration }}</span>
                                                <div>
                                                    <div class="ai-item-title">{{ $req['title'] ?? 'Permintaan' }}</div>
                                                    <div class="ai-item-text">{{ $req['summary'] ?? '' }}</div>
                                                    @if(isset($req['evidence_count']))
                                                        <span class="ai-metric">{{ (int) $req['evidence_count'] }} sebutan</span>
                                                    @endif
                                                </div>
                                            </li>
                                        @empty
                                            <li class="ai-item ai-item-empty">Belum ada data permintaan. Klik Perbarui Analisis.</li>
                                        @endforelse
                                    </ol>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="ai-panel">
                                    <div class="ai-panel-head">
                                        <span class="ai-panel-icon"><i class="fas fa-star-half-alt"></i></span>
                                        <div>
                                            <div class="ai-panel-title">Dari Skor Terendah</div>
                                            <div class="ai-panel-sub">question_id dan score Likert</div>
                                        </div>
                                    </div>
                                    <ol class="ai-list">
                                        @forelse($scoreProblems as $problem)
                                            <li class="ai-item">
                                                <span class="ai-rank">{{ $loop->iteration }}</span>
                                                <div>
                                                    <div class="ai-item-title">{{ $problem['title'] ?? 'Masalah' }}</div>
                                                    <div class="ai-item-text">{{ $problem['summary'] ?? '' }}</div>
                                                    <div class="ai-metrics">
                                                        @if(isset($problem['average_score']))
                                                            <span class="ai-metric">rata-rata {{ number_format((float) $problem['average_score'], 2) }}</span>
                                                        @endif
                                                        @if(isset($problem['question_id']))
                                                            <span class="ai-metric">pertanyaan #{{ (int) $problem['question_id'] }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </li>
                                        @empty
                                            <li class="ai-item ai-item-empty">Belum ada analisis skor. Klik Perbarui Analisis.</li>
                                        @endforelse
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="ai-foot">
                            <span><i class="fas fa-robot mr-1"></i>{{ $aiAnalysis->model ?? 'router9' }}</span>
                            <span>{{ $aiAnalysis->source_count }} jawaban esai</span>
                            <span>Dibuat {{ optional($aiAnalysis->generated_at)->format('d M Y H:i') }}</span>
                        </div>
                    @else
                        <div class="ai-empty">
                            <i class="fas fa-brain"></i>
                            <p class="mb-1">Analisis belum tersedia.</p>
                            <small class="text-muted">Klik Buat Analisis untuk membaca jawaban esai dan skor Likert terendah, lalu menyimpan tiga masalah utama dari masing-masing sumber.</small>
                        </div>
                    @endif
                    @if(isset($errors) && $errors->has('ai_analysis'))<div class="alert alert-warning mt-2 mb-0">{{ $errors->first('ai_analysis') }}</div>@endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">List Jawaban per Paket Submit (tanggal &amp; jam + isi jawaban)</h6>
            <span class="small text-muted">Klik <strong>Lihat Jawaban</strong> untuk membuka detail isi jawaban pada modal</span>
        </div>
        <div class="card-body">
            @if($paketList->isEmpty())
                <p class="text-muted mb-0">Belum ada jawaban masuk.</p>
            @else
            @php
                $paketModalPayload = $paketList->values()->map(function ($pk, $idx) {
                    $answers = collect($pk['answers'] ?? []);
                    $items = $answers->values()->map(function ($r, $aIdx) {
                        $isLikert = $r->question && $r->question->isLikert();
                        return [
                            'no'      => $aIdx + 1,
                            'section' => (string) ($r->question->section ?? '-'),
                            'question'=> (string) ($r->question->question_text ?? ''),
                            'type'    => $isLikert ? 'likert' : 'open',
                            'score'   => $isLikert ? (int) $r->score : null,
                            'answer'  => $isLikert ? (string) $r->score : (string) ($r->answer_text ?? ''),
                        ];
                    })->all();

                    $openFilledCount = collect($items)->filter(
                        fn ($it) => $it['type'] === 'open' && trim($it['answer']) !== ''
                    )->count();

                    return [
                        'no'         => $idx + 1,
                        'tanggal'    => (string) ($pk['waktu_tanggal'] ?? ($pk['waktu_label'] ?? '—')),
                        'jam'        => (string) ($pk['waktu_jam'] ?? ''),
                        'total'      => (int) ($pk['total'] ?? count($items)),
                        'likert_avg' => isset($pk['likert_avg']) && $pk['likert_avg'] !== null ? round((float) $pk['likert_avg'], 2) : null,
                        'open_count' => $openFilledCount,
                        'items'      => $items,
                    ];
                })->all();
            @endphp
            <style>
                #paketTable thead th {
                    background: #f8f9fc;
                    color: #4e73df;
                    font-size: 0.78rem;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    border-bottom: 2px solid #e3e6f0;
                    vertical-align: middle;
                }
                #paketTable tbody td {
                    vertical-align: middle;
                    padding: 0.75rem 0.75rem;
                    border-color: #eaecf4;
                }
                .paket-badge-num {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 34px;
                    height: 28px;
                    padding: 0 9px;
                    border-radius: 6px;
                    background: #eef2ff;
                    color: #3b5bdb;
                    font-weight: 700;
                    font-size: 0.82rem;
                }
                .paket-time-date {
                    font-weight: 700;
                    color: #2d3748;
                    font-size: 0.88rem;
                    line-height: 1.3;
                }
                .paket-time-clock {
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                    font-size: 0.78rem;
                    color: #718096;
                }
                .paket-summary-chips {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 6px;
                }
                .paket-score-pill {
                    display: inline-flex;
                    align-items: center;
                    padding: 0.22rem 0.65rem;
                    border-radius: 999px;
                    font-size: 0.76rem;
                    font-weight: 700;
                    white-space: nowrap;
                }
                .paket-score-high { background: #e6f9f0; color: #0f8a53; border: 1px solid #bdecd4; }
                .paket-score-mid  { background: #fff8e6; color: #9c6b00; border: 1px solid #f7df9d; }
                .paket-score-low  { background: #fdecec; color: #c0342b; border: 1px solid #f5c2be; }
                .paket-open-pill  { background: #eef4ff; color: #2b59b0; border: 1px solid #cfe0ff; }
                .paket-btn-view {
                    border-radius: 999px;
                    font-weight: 600;
                    padding: 0.34rem 0.9rem;
                    white-space: nowrap;
                }
                .paket-modal-content {
                    border: 0;
                    border-radius: 14px;
                    overflow: hidden;
                    box-shadow: 0 18px 44px rgba(15, 23, 42, 0.24);
                }
                .paket-modal-header {
                    background: linear-gradient(135deg, #1d3f86 0%, #274b8f 55%, #3a6fa8 100%);
                    color: #ffffff;
                    border-bottom: 0;
                    padding: 1.05rem 1.35rem;
                }
                .paket-modal-meta {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    padding: 0.8rem 1.35rem;
                    background: #f8fafc;
                    border-bottom: 1px solid #e2e8f0;
                }
                .paket-modal-chip {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 0.3rem 0.72rem;
                    border-radius: 999px;
                    background: #ffffff;
                    border: 1px solid #dbe2ee;
                    font-size: 0.8rem;
                    font-weight: 600;
                    color: #334155;
                }
                .paket-modal-body {
                    background: #f3f6fb;
                    padding: 1.1rem 1.35rem;
                }
                .paket-modal-card {
                    background: #ffffff;
                    border: 1px solid #e4e9f2;
                    border-radius: 10px;
                    padding: 0.9rem 1.05rem;
                    margin-bottom: 0.7rem;
                }
                .paket-modal-card:last-child {
                    margin-bottom: 0;
                }
                .paket-modal-qhead {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 8px;
                    flex-wrap: wrap;
                    margin-bottom: 0.38rem;
                }
                .paket-modal-num {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 24px;
                    height: 24px;
                    padding: 0 7px;
                    border-radius: 999px;
                    background: #eef2ff;
                    color: #1d3f86;
                    font-weight: 700;
                    font-size: 0.76rem;
                }
                .paket-sec-pill {
                    display: inline-block;
                    padding: 0.14rem 0.55rem;
                    font-size: 0.72rem;
                    font-weight: 700;
                    border-radius: 999px;
                    background: #f1f5f9;
                    color: #334155;
                }
                .paket-modal-qtext {
                    font-size: 0.92rem;
                    font-weight: 600;
                    color: #1e293b;
                    line-height: 1.48;
                    margin-bottom: 0.55rem;
                }
                .paket-modal-likert-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    flex-wrap: wrap;
                    gap: 8px;
                }
                .paket-likert-dots {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                }
                .paket-likert-dot {
                    width: 10px;
                    height: 10px;
                    border-radius: 50%;
                    background: #e2e8f0;
                    display: inline-block;
                }
                .paket-likert-dot.active {
                    background: #274b8f;
                }
                .paket-open-quote {
                    margin-top: 2px;
                    padding: 0.6rem 0.85rem;
                    border-left: 3px solid #274b8f;
                    background: #f8fafc;
                    border-radius: 0 8px 8px 0;
                    font-size: 0.88rem;
                    color: #1e293b;
                    line-height: 1.5;
                    white-space: pre-wrap;
                    word-break: break-word;
                }
                .paket-open-empty {
                    font-size: 0.83rem;
                    color: #94a3b8;
                    font-style: italic;
                }
            </style>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="paketTable">
                    <thead>
                        <tr>
                            <th width="72" class="text-center">Paket</th>
                            <th width="175">Tanggal &amp; Jam</th>
                            <th>Ringkasan Jawaban</th>
                            <th width="165" class="text-center">Isi Jawaban</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($paketModalPayload as $i => $pk)
                        <tr>
                            <td class="text-center" data-order="{{ $pk['no'] }}">
                                <span class="paket-badge-num">#{{ $pk['no'] }}</span>
                            </td>
                            <td data-order="{{ ($paketList[$i]['waktu_label'] ?? '') }}">
                                <div class="paket-time-date">{{ $pk['tanggal'] }}</div>
                                <div class="paket-time-clock"><i class="far fa-clock mr-1"></i>{{ $pk['jam'] }}</div>
                            </td>
                            <td>
                                <div class="paket-summary-chips">
                                    <span class="paket-score-pill" style="background:#f8fafc;color:#334155;border:1px solid #dbe2ee;">
                                        {{ $pk['total'] }} item
                                    </span>
                                    @if($pk['likert_avg'] !== null)
                                        @php
                                            $avgCls = $pk['likert_avg'] >= 4 ? 'paket-score-high' : ($pk['likert_avg'] >= 3 ? 'paket-score-mid' : 'paket-score-low');
                                        @endphp
                                        <span class="paket-score-pill {{ $avgCls }}">Avg Likert {{ number_format($pk['likert_avg'], 2) }} / 5</span>
                                    @endif
                                    @if($pk['open_count'] > 0)
                                        <span class="paket-score-pill paket-open-pill">{{ $pk['open_count'] }} catatan esai</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-center">
                                <button type="button"
                                    class="btn btn-sm btn-primary paket-btn-view btn-lihat-paket"
                                    data-paket-index="{{ $i }}">
                                    <i class="fas fa-eye mr-1"></i> Lihat Jawaban
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Modal Detail Jawaban per Paket Submit -->
            <div class="modal fade" id="paketDetailModal" tabindex="-1" role="dialog" aria-labelledby="paketDetailModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered" role="document">
                    <div class="modal-content paket-modal-content">
                        <div class="modal-header paket-modal-header">
                            <div>
                                <h5 class="modal-title font-weight-bold mb-1" id="paketDetailModalLabel">
                                    <i class="fas fa-clipboard-list mr-2"></i><span id="modalPaketTitle">Detail Paket Submit</span>
                                </h5>
                                <div class="small" style="opacity:0.9;" id="modalPaketSubtitle">-</div>
                            </div>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup" style="opacity:0.9;">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="paket-modal-meta" id="modalPaketMeta"></div>
                        <div class="modal-body paket-modal-body" id="modalPaketBody"></div>
                        <div class="modal-footer d-flex justify-content-between align-items-center bg-white">
                            <span class="small text-muted font-weight-bold" id="modalPaketCounter">-</span>
                            <div class="d-flex align-items-center" style="gap: 6px;">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="paketPrevBtn">
                                    <i class="fas fa-chevron-left mr-1"></i> Sebelumnya
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="paketNextBtn">
                                    Berikutnya <i class="fas fa-chevron-right ml-1"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    @php
        $likertStats = $stats->where('question_type', 'likert')->values();
        $likertAnswerSum = 0;
        $likertAvgSum = 0.0;
        $likertAvgCount = 0;
        foreach ($likertStats as $lq) {
            $likertAnswerSum += (int) $lq->responses_count;
            if (isset($avg[$lq->id]) && $avg[$lq->id] !== null) {
                $likertAvgSum += (float) $avg[$lq->id];
                $likertAvgCount++;
            }
        }
        $likertOverallAvg = $likertAvgCount > 0 ? $likertAvgSum / $likertAvgCount : null;
    @endphp
    <style>
        /* Distribusi Likert — chart inline */
        #likertTable td, #likertTable th { vertical-align: middle; }
        #likertTable td.lq-cell { font-size: .84rem; line-height: 1.35; }
        #likertTable .badge-section { background: #f1f3f9; color: #3a3b45; border: 1px solid #e3e6f0; font-weight: 600; font-size: .75rem; }
        #likertTable_wrapper .dataTables_filter input { border: 1px solid #d1d3e2; border-radius: 4px; padding: .25rem .5rem; }
        .lq-avg-pill { display: inline-block; min-width: 42px; text-align: center; border-radius: 20px; padding: 2px 8px; font-size: .8rem; font-weight: 700; color: #fff; }
        .lq-avg-danger  { background: #e74a3b; }
        .lq-avg-warning { background: #f6c23e; color: #5a4000; }
        .lq-avg-success { background: #1cc88a; }
        .lq-avg-none    { background: #adb5bd; }
        .lq-legend { display: flex; flex-wrap: wrap; gap: .6rem .9rem; font-size: .77rem; color: #5a5c69; margin-bottom: .75rem; }
        .lq-legend span { display: inline-flex; align-items: center; gap: 5px; }
        .lq-swatch { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
        .lq-swatch-1 { background: #e74a3b; }
        .lq-swatch-2 { background: #f6c23e; }
        .lq-swatch-3 { background: #4e73df; }
        .lq-swatch-4 { background: #1cc88a; }
        .lq-swatch-5 { background: #36b9cc; }
        /* CSS stacked bar — no JS needed */
        .lq-bar-wrap { display: flex; height: 20px; border-radius: 3px; overflow: hidden; width: 100%; max-width: 260px; background: #f1f3f9; }
        .lq-seg { height: 100%; transition: width .2s; }
        .lq-seg-1 { background: #e74a3b; }
        .lq-seg-2 { background: #f6c23e; }
        .lq-seg-3 { background: #4e73df; }
        .lq-seg-4 { background: #1cc88a; }
        .lq-seg-5 { background: #36b9cc; }
    </style>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Distribusi Likert per Pertanyaan (skala 1–5)</h6>
            <span class="small text-muted">
                {{ $likertStats->count() }} pertanyaan ·
                {{ number_format($likertAnswerSum, 0, ',', '.') }} jawaban ·
                rata-rata {{ $likertOverallAvg !== null ? number_format($likertOverallAvg, 2) : '—' }} / 5
            </span>
        </div>
        <div class="card-body">
            @if($likertStats->isEmpty())
                <p class="text-muted mb-0">Belum ada pertanyaan Likert aktif.</p>
            @else
                <div class="lq-legend">
                    <span><span class="lq-swatch lq-swatch-1"></span>1 — Sangat Kurang</span>
                    <span><span class="lq-swatch lq-swatch-2"></span>2 — Kurang</span>
                    <span><span class="lq-swatch lq-swatch-3"></span>3 — Cukup</span>
                    <span><span class="lq-swatch lq-swatch-4"></span>4 — Baik</span>
                    <span><span class="lq-swatch lq-swatch-5"></span>5 — Sangat Baik</span>
                </div>

                {{-- Siapkan data JSON untuk chart inline --}}
                @php
                    $lqChartData = [];
                    foreach ($likertStats as $q) {
                        $rowDist = $dist[$q->id] ?? [];
                        $counts = [];
                        for ($s = 1; $s <= 5; $s++) {
                            $counts[] = (int) ($rowDist[$s] ?? $rowDist[(string)$s] ?? 0);
                        }
                        $lqChartData[$q->id] = $counts;
                    }
                @endphp

                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="likertTable">
                        <thead>
                            <tr>
                                <th style="width:40px">No</th>
                                <th style="width:100px">Bagian</th>
                                <th>Pertanyaan</th>
                                <th style="width:260px">Distribusi</th>
                                <th style="width:80px" class="text-center">Rata-rata</th>
                                <th style="width:76px" class="text-center">Jawaban</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($likertStats as $i => $q)
                            @php
                                $counts  = $lqChartData[$q->id];
                                $rowSum  = array_sum($counts);
                                $rowAvg  = isset($avg[$q->id]) && $avg[$q->id] !== null ? round((float)$avg[$q->id], 2) : null;
                                $avgCls  = $rowAvg === null ? 'lq-avg-none' : ($rowAvg >= 4 ? 'lq-avg-success' : ($rowAvg >= 3 ? 'lq-avg-warning' : 'lq-avg-danger'));
                                $avgText = $rowAvg !== null ? number_format($rowAvg, 2) : '—';
                            @endphp
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td><span class="badge badge-section">{{ $q->section }}</span></td>
                                <td class="lq-cell">{{ $q->question_text }}</td>
                                <td>
                                    @if($rowSum === 0)
                                        <span class="text-muted small">Belum ada jawaban</span>
                                    @else
                                        <div class="lq-bar-wrap">
                                            @foreach($counts as $si => $cnt)
                                                @if($cnt > 0)
                                                    <div class="lq-seg lq-seg-{{ $si + 1 }}"
                                                         style="width:{{ round($cnt / $rowSum * 100, 1) }}%"
                                                         title="{{ $si + 1 }} {{ ['Sangat Kurang','Kurang','Cukup','Baik','Sangat Baik'][$si] }}: {{ $cnt }} ({{ round($cnt / $rowSum * 100, 1) }}%)"></div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center" data-order="{{ $rowAvg !== null ? number_format($rowAvg, 2, '.', '') : -1 }}">
                                    <span class="lq-avg-pill {{ $avgCls }}">{{ $avgText }}</span>
                                </td>
                                <td class="text-center" data-order="{{ $rowSum }}">
                                    <span class="text-muted small">{{ number_format($rowSum, 0, ',', '.') }}</span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
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
<script>
$(function(){
  if ($('#paketTable').length) $('#paketTable').DataTable({ pageLength:10, order:[[0,'desc']] });
  if ($('#likertTable').length) {
    $('#likertTable').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
      order: [[4, 'asc']],
      columnDefs: [{ orderable: false, targets: [3] }],
      language: {
        search: 'Cari pertanyaan:',
        lengthMenu: 'Tampilkan _MENU_ pertanyaan',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_ pertanyaan',
        infoEmpty: 'Tidak ada pertanyaan',
        infoFiltered: '(disaring dari _MAX_ pertanyaan)',
        zeroRecords: 'Pertanyaan tidak ditemukan',
        paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
      }
    });
  }
});
</script>
@if(isset($paketModalPayload))
<script>
(function(){
  var paketData = @json($paketModalPayload);
  var currentIdx = -1;
  var scoreLabels = { 5: 'Sangat Baik', 4: 'Baik', 3: 'Cukup', 2: 'Kurang', 1: 'Sangat Kurang' };

  function scoreClass(sc) {
    if (sc >= 4) return 'paket-score-high';
    if (sc >= 3) return 'paket-score-mid';
    return 'paket-score-low';
  }

  function renderModal(idx) {
    if (!Array.isArray(paketData) || idx < 0 || idx >= paketData.length) return;
    currentIdx = idx;
    var pk = paketData[idx];

    document.getElementById('modalPaketTitle').textContent = 'Detail Paket Submit #' + pk.no;
    document.getElementById('modalPaketSubtitle').textContent = 'Dikirim pada ' + pk.tanggal + (pk.jam ? ' pukul ' + pk.jam : '');
    document.getElementById('modalPaketCounter').textContent = 'Paket ' + (idx + 1) + ' dari ' + paketData.length;

    var metaEl = document.getElementById('modalPaketMeta');
    metaEl.innerHTML = '';
    var chips = [{ icon: 'fas fa-list-ol text-primary', text: pk.total + ' Pertanyaan' }];
    if (pk.likert_avg !== null && pk.likert_avg !== undefined) {
      chips.push({ icon: 'fas fa-star text-warning', text: 'Rata-rata Likert: ' + Number(pk.likert_avg).toFixed(2) + ' / 5' });
    }
    if (pk.open_count > 0) {
      chips.push({ icon: 'fas fa-comment-alt text-info', text: pk.open_count + ' Catatan Esai' });
    }
    chips.forEach(function(c) {
      var sp = document.createElement('span');
      sp.className = 'paket-modal-chip';
      var ic = document.createElement('i');
      ic.className = c.icon;
      sp.appendChild(ic);
      sp.appendChild(document.createTextNode(' ' + c.text));
      metaEl.appendChild(sp);
    });

    var bodyEl = document.getElementById('modalPaketBody');
    bodyEl.innerHTML = '';

    var items = Array.isArray(pk.items) ? pk.items : [];
    if (items.length === 0) {
      var empty = document.createElement('div');
      empty.className = 'text-center text-muted py-4';
      empty.textContent = 'Tidak ada item jawaban pada paket ini.';
      bodyEl.appendChild(empty);
    } else {
      items.forEach(function(it) {
        var card = document.createElement('div');
        card.className = 'paket-modal-card';

        var qHead = document.createElement('div');
        qHead.className = 'paket-modal-qhead';

        var left = document.createElement('div');
        left.className = 'd-flex align-items-center';
        left.style.gap = '6px';

        var num = document.createElement('span');
        num.className = 'paket-modal-num';
        num.textContent = String(it.no);
        left.appendChild(num);

        var sec = document.createElement('span');
        sec.className = 'paket-sec-pill';
        sec.textContent = it.section || '-';
        left.appendChild(sec);
        qHead.appendChild(left);

        var typeTag = document.createElement('span');
        typeTag.className = 'small text-muted';
        typeTag.textContent = it.type === 'likert' ? 'Skala 1-5' : 'Jawaban Terbuka';
        qHead.appendChild(typeTag);
        card.appendChild(qHead);

        var qText = document.createElement('div');
        qText.className = 'paket-modal-qtext';
        qText.textContent = it.question || '-';
        card.appendChild(qText);

        if (it.type === 'likert') {
          var sc = parseInt(it.score, 10);
          var row = document.createElement('div');
          row.className = 'paket-modal-likert-row';

          var pill = document.createElement('span');
          pill.className = 'paket-score-pill ' + scoreClass(sc);
          pill.textContent = !isNaN(sc)
            ? (sc + ' / 5' + (scoreLabels[sc] ? ' · ' + scoreLabels[sc] : ''))
            : (it.answer || '-');
          row.appendChild(pill);

          var dots = document.createElement('div');
          dots.className = 'paket-likert-dots';
          for (var s = 1; s <= 5; s++) {
            var dot = document.createElement('span');
            dot.className = 'paket-likert-dot' + (!isNaN(sc) && s <= sc ? ' active' : '');
            dots.appendChild(dot);
          }
          row.appendChild(dots);
          card.appendChild(row);
        } else {
          var txt = (it.answer || '').trim();
          if (txt !== '') {
            var quote = document.createElement('div');
            quote.className = 'paket-open-quote';
            quote.textContent = txt;
            card.appendChild(quote);
          } else {
            var none = document.createElement('div');
            none.className = 'paket-open-empty';
            none.textContent = '(tidak diisi)';
            card.appendChild(none);
          }
        }

        bodyEl.appendChild(card);
      });
    }

    document.getElementById('paketPrevBtn').disabled = idx <= 0;
    document.getElementById('paketNextBtn').disabled = idx >= paketData.length - 1;
    if (window.jQuery && typeof window.jQuery('#paketDetailModal').modal === 'function') {
      window.jQuery('#paketDetailModal').modal('show');
    }
  }

  document.addEventListener('click', function(e) {
    var btn = e.target.closest ? e.target.closest('.btn-lihat-paket') : null;
    if (btn) {
      var idx = parseInt(btn.getAttribute('data-paket-index'), 10);
      if (!isNaN(idx)) renderModal(idx);
      return;
    }
    if (e.target.closest && e.target.closest('#paketPrevBtn') && currentIdx > 0) {
      renderModal(currentIdx - 1);
    } else if (e.target.closest && e.target.closest('#paketNextBtn') && currentIdx < paketData.length - 1) {
      renderModal(currentIdx + 1);
    }
  });
})();
</script>
@endif
@php
    $aiFlashSuccess = session('ai_analysis_success');
    $aiFlashError = isset($errors) && $errors->has('ai_analysis') ? $errors->first('ai_analysis') : null;
@endphp
<script>
(function () {
  var SWEETALERT_ASSET = @json(asset('vendor/sweetalert/sweetalert.all.js'));
  var pending = [];
  var loading = false;

  function flush() {
    loading = false;
    var callbacks = pending.slice();
    pending.length = 0;
    callbacks.forEach(function (callback) {
      try { callback(window.Swal); } catch (error) { console.error(error); }
    });
  }

  // Halaman ini tidak selalu memuat SweetAlert lewat @include('sweetalert::alert')
  // karena paket hanya mencetak <script> saat ada session alert.config.
  // Jadi aset dimuat di sini bila window.Swal belum tersedia.
  function withSwal(callback) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      callback(window.Swal);
      return;
    }
    pending.push(callback);
    if (loading) return;
    loading = true;

    var script = document.createElement('script');
    script.src = SWEETALERT_ASSET;
    script.async = false;
    script.onload = flush;
    script.onerror = function () {
      console.error('SweetAlert gagal dimuat dari ' + SWEETALERT_ASSET);
      flush();
    };
    document.head.appendChild(script);
  }

  var form = document.getElementById('aiAnalyzeForm');
  if (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (form.getAttribute('data-submitting') === '1') return;
      form.setAttribute('data-submitting', '1');

      withSwal(function (Swal) {
        if (Swal && typeof Swal.fire === 'function') {
          Swal.fire({
            title: 'AI sedang menganalisis',
            html: 'Membaca jawaban esai dan skor terendah, lalu menyusun tiga masalah utama.'
              + '<br><span style="font-size:.82rem;color:#858796;">Proses ini bisa memakan waktu. Jangan tutup halaman.</span>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () { Swal.showLoading(); }
          });
        }
        form.submit();
      });
    });
  }

  var successMessage = @json($aiFlashSuccess ?? null);
  var errorMessage = @json($aiFlashError ?? null);

  if (successMessage) {
    withSwal(function (Swal) {
      if (!Swal) return;
      Swal.fire({
        icon: 'success',
        title: 'Analisis selesai',
        text: successMessage,
        confirmButtonText: 'OK',
        confirmButtonColor: '#4e73df'
      });
    });
  } else if (errorMessage) {
    withSwal(function (Swal) {
      if (!Swal) return;
      Swal.fire({
        icon: 'error',
        title: 'Analisis gagal',
        text: errorMessage,
        confirmButtonText: 'OK',
        confirmButtonColor: '#4e73df'
      });
    });
  }
})();
</script>
</body>
</html>
