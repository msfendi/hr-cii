@extends('layouts.app')

@section('title', 'Hasil - ' . $quiz->title)

@section('content')
<div class="container">
    <div class="row justify-content-center mt-4">
        <div class="col-md-8">
            @if($quiz->isWordCloud() || $quiz->isPolling())
                {{-- Polling & Word Cloud: tidak ada skor, hanya konfirmasi --}}
                <div class="card shadow">
                    <div class="card-header bg-success text-white text-center py-4">
                        <h4 class="mb-0"><i class="fas fa-check-circle mr-2"></i> Terima Kasih!</h4>
                        <p class="mb-0 mt-1 opacity-75">{{ $quiz->title }}</p>
                    </div>
                    <div class="card-body text-center py-5">
                        <p class="text-muted mb-1">Peserta: <strong>{{ $session->participant_name }}</strong></p>
                        <p class="text-muted small mb-0">Jawaban Anda sudah tercatat. Hasil akan ditampilkan secara langsung oleh pemateri.</p>
                    </div>
                </div>
            @else
                {{-- Kuis: tampilkan skor + detail --}}
                <div class="card shadow">
                    <div class="card-header bg-success text-white text-center py-4">
                        <h4 class="mb-0"><i class="fas fa-flag-checkered mr-2"></i> Kuis Selesai!</h4>
                        <p class="mb-0 mt-1 opacity-75">{{ $quiz->title }}</p>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <h2 class="text-primary">{{ $percentage }}%</h2>
                            <p class="text-muted">{{ $score }} dari {{ $total }} jawaban benar</p>
                        </div>

                        <div class="progress mb-4" style="height: 20px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percentage }}%;" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100">
                                {{ $percentage }}%
                            </div>
                        </div>

                        <p class="text-muted mb-4">Peserta: <strong>{{ $session->participant_name }}</strong></p>
                    </div>
                    <div class="card-footer bg-light">
                        <h6 class="font-weight-bold text-left mb-3">Detail Jawaban</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Pertanyaan</th>
                                        <th>Jawaban Anda</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($detail as $idx => $d)
                                    <tr>
                                        <td class="text-center">{{ $idx + 1 }}</td>
                                        <td>{{ $d['question'] }}</td>
                                        <td>{{ $d['your_answer'] }}</td>
                                        <td class="text-center">
                                            @if($d['is_correct'])
                                                <span class="badge badge-success"><i class="fas fa-check mr-1"></i> Benar</span>
                                            @else
                                                <span class="badge badge-danger"><i class="fas fa-times mr-1"></i> Salah</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <div class="text-center mt-4">
                <a href="{{ url('/') }}" class="btn btn-primary">
                    <i class="fas fa-home mr-1"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</div>
@endsection