@extends('layouts.app')

@section('title', 'Buat Kuis / Polling Baru')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-plus text-primary mr-2"></i> Buat Sesi Interaktif Baru
    </h1>
    <a href="{{ route('quiz.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Kembali
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('quiz.store') }}" method="POST">
    @csrf

    {{-- ===== PEMILIH MODE ===== --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Langkah 1 — Pilih Mode</h6>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">Mode menentukan jenis pertanyaan dan cara hasil ditampilkan secara real-time.</p>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="w-100 h-100" for="modeQuiz">
                        <input type="radio" class="custom-control-input d-none" id="modeQuiz" name="mode" value="quiz"
                               {{ old('mode','quiz') === 'quiz' ? 'checked' : '' }}>
                        <div class="border rounded p-3 h-100 mode-card" data-mode="quiz">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-primary mr-2"><i class="fas fa-graduation-cap"></i></span>
                                <strong>Kuis</strong>
                            </div>
                            <p class="small text-muted mb-0">Pertanyaan pilihan ganda dengan jawaban benar/salah. Menampilkan skor akhir peserta.</p>
                        </div>
                    </label>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="w-100 h-100" for="modePolling">
                        <input type="radio" class="custom-control-input d-none" id="modePolling" name="mode" value="polling"
                               {{ old('mode') === 'polling' ? 'checked' : '' }}>
                        <div class="border rounded p-3 h-100 mode-card" data-mode="polling">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-info mr-2"><i class="fas fa-chart-bar"></i></span>
                                <strong>Live Polling</strong>
                            </div>
                            <p class="small text-muted mb-0">Pemungutan suara satu opsi per pertanyaan. Hasil tampil sebagai grafik batang real-time, tanpa penilaian.</p>
                        </div>
                    </label>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="w-100 h-100" for="modeWordCloud">
                        <input type="radio" class="custom-control-input d-none" id="modeWordCloud" name="mode" value="word_cloud"
                               {{ old('mode') === 'word_cloud' ? 'checked' : '' }}>
                        <div class="border rounded p-3 h-100 mode-card" data-mode="word_cloud">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-success mr-2"><i class="fas fa-cloud"></i></span>
                                <strong>Word Cloud</strong>
                            </div>
                            <p class="small text-muted mb-0">Peserta mengetik kata/frasa bebas. Kata paling sering muncul ditampilkan lebih besar pada awan kata.</p>
                        </div>
                    </label>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== INFORMASI SESI ===== --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Langkah 2 — Informasi Sesi</h6>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label for="title" class="font-weight-bold">Judul</label>
                <input type="text" name="title" id="title" class="form-control" placeholder="Contoh: Kuis Produk Baru, Polling Kepuasan Karyawan" required maxlength="200" value="{{ old('title') }}">
            </div>
            <div class="form-group">
                <label for="description" class="font-weight-bold">Deskripsi (opsional)</label>
                <textarea name="description" id="description" class="form-control" rows="3" placeholder="Deskripsi singkat tentang sesi ini...">{{ old('description') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i> Buat &amp; Atur Pertanyaan
            </button>
        </div>
    </div>
</form>
@endsection

@section('styles')
<style>
.mode-card { transition: all .15s ease-in-out; cursor: pointer; background:#fff; }
.mode-card:hover { box-shadow: 0 .2rem .6rem rgba(78,115,223,.18); transform: translateY(-1px); }
.custom-control-input:checked ~ .mode-card {
    border-color: #4e73df !important;
    box-shadow: 0 0 0 .2rem rgba(78,115,223,.25);
    background: #f6f9ff;
}
</style>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modeInputs = document.querySelectorAll('input[name="mode"]');
    
    // Klik pada label/card → centang radio
    modeInputs.forEach(input => {
        const label = input.closest('label');
        if (label) {
            label.addEventListener('click', function(e) {
                e.preventDefault();
                input.checked = true;
                updateCardStyles();
            });
        }
    });
    
    function updateCardStyles() {
        modeInputs.forEach(input => {
            const card = input.closest('label')?.querySelector('.mode-card');
            if (card) {
                if (input.checked) {
                    card.style.borderColor = '#4e73df';
                    card.style.boxShadow = '0 0 0 .2rem rgba(78,115,223,.25)';
                    card.style.background = '#f6f9ff';
                } else {
                    card.style.borderColor = '#ddd';
                    card.style.boxShadow = 'none';
                    card.style.background = '#fff';
                }
            }
        });
    }
    
    // Inisialisasi visual awal
    updateCardStyles();
});
</script>
@endsection
