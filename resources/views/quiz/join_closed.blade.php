@extends('layouts.app')

@section('title', 'Kuis Tidak Aktif')

@section('content')
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6">
            <div class="card shadow text-center py-5">
                <div class="card-body">
                    <div class="badge-icon bg-light text-secondary mb-3" style="width: 80px; height: 80px; font-size: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h4 class="font-weight-bold text-gray-800">Kuis Tidak Tersedia</h4>
                    <p class="text-muted mb-4">
                        Kuis <strong>"{{ $quiz->title }}"</strong> saat ini 
                        <strong>{{ $quiz->status === 'draft' ? 'belum dimulai' : 'sudah ditutup' }}</strong>.
                    </p>
                    <p class="text-muted small">
                        {{ $quiz->status === 'draft' 
                            ? 'Silakan tunggu panitia memulai kuis.' 
                            : 'Kuis telah berakhir. Terima kasih atas partisipasinya.' }}
                    </p>
                    <a href="{{ url('/') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-home mr-1"></i> Kembali ke Beranda
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection