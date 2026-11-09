@extends('layouts.app')

@section('title', 'Manajemen Kuis / Polling')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-poll text-primary mr-2"></i> Manajemen Kuis & Polling
        </h1>
        <a href="{{ route('quiz.create') }}" class="btn btn-primary">
            <i class="fas fa-plus mr-1"></i> Buat Kuis Baru
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Sesi Interaktif</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>Judul</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th>Pertanyaan</th>
                            <th>Peserta</th>
                            <th class="text-center">QR Code</th>
                            <th>Dibuat</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quizzes as $quiz)
                        <tr>
                            <td>{{ $quiz->title }}</td>
                            <td>
                                @php
                                    $modeBadge = 'primary';
                                    $modeIcon  = 'graduation-cap';
                                    if ($quiz->isPolling())   { $modeBadge = 'info';    $modeIcon = 'chart-bar'; }
                                    elseif ($quiz->isWordCloud()) { $modeBadge = 'success'; $modeIcon = 'cloud'; }
                                @endphp
                                <span class="badge badge-{{ $modeBadge }}">
                                    <i class="fas fa-{{ $modeIcon }} mr-1"></i>{{ $quiz->modeLabel() }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $quiz->status === 'active' ? 'success' : ($quiz->status === 'closed' ? 'secondary' : 'warning') }}">
                                    {{ $quiz->status === 'active' ? 'Aktif' : ($quiz->status === 'closed' ? 'Ditutup' : 'Draft') }}
                                </span>
                            </td>
                            <td>{{ $quiz->questions_count }}</td>
                            <td>{{ $quiz->sessions_count }}</td>
                            <td class="text-center align-middle">
                                <?php
                                $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(80)->generate(url('/quiz/join/' . $quiz->qr_code));
                                $b64 = base64_encode($svg);
                                ?>
                                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; width:100%;">
                                    <img src="data:image/svg+xml;base64,{{ $b64 }}" alt="QR" title="{{ $quiz->qr_code }}" width="80" height="80" style="display:block; margin:0 auto;">
                                    <small class="text-muted d-none d-md-block" style="display:block; width:100%; text-align:center;">{{ $quiz->qr_code }}</small>
                                </div>
                            </td>
                            <td>{{ $quiz->created_at->format('d M Y H:i') }}</td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('quiz.edit', $quiz->id) }}" class="btn btn-info" title="Edit Soal">
                                        <i class="fas fa-list"></i>
                                    </a>
                                    @if($quiz->status === 'draft')
                                                                            <form action="{{ route('quiz.start', $quiz->id) }}" method="POST" class="d-inline">
                                                                                @csrf
                                                                                <button type="submit" class="btn btn-success btn-sm" title="Mulai Kuis">
                                                                                    <i class="fas fa-play"></i>
                                                                                </button>
                                                                            </form>
                                    @elseif($quiz->status === 'active')
                                        <a href="{{ route('quiz.live', $quiz->id) }}" class="btn btn-primary" title="Live Monitoring">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                        <form action="{{ route('quiz.close', $quiz->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-warning btn-sm" title="Tutup Kuis">
                                                <i class="fas fa-stop"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('quiz.destroy', $quiz->id) }}" class="btn btn-danger" title="Hapus"
                                       onclick="return confirm('Hapus kuis ini? Semua soal, jawaban, dan sesi peserta akan ikut terhapus.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $quizzes->links() }}
        </div>
    </div>
@endsection