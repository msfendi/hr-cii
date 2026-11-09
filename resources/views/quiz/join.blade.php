@extends('layouts.app')

@section('title', 'Bergabung ke Kuis: ' . $quiz->title)

@section('content')
<!-- Scoped: containerFluid .py-3 di layouts/app sudah padding, + .container + .row + .col-md-8
     = double/triple padding. Override container ini supaya tidak terasa ganda.
     QR: sembunyikan <canvas> qrcodejs (sumber) agar hanya <img> final yang tampil,
     karena qrcodejs menulis keduanya dan display:block !important lama memunculkan canvas. -->
<style>
.container-fluid.py-3 { padding: 0.5rem 1rem !important; }
#qrCodeJoin { width: 200px !important; height: 200px !important; margin: 0 auto !important; line-height: 0; }
#qrCodeJoin canvas { display: none !important; }
#qrCodeJoin img { display: block !important; margin: 0 auto !important; }
</style>
<div class="container" style="padding-left:0; padding-right:0;">
    <div class="row justify-content-center mt-5">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center py-4">
                    <h4 class="mb-0"><i class="fas fa-poll mr-2"></i> {{ $quiz->title }}</h4>
                    <p class="mb-0 mt-1 opacity-75">{{ $quiz->description ?? 'Kuis / Polling interaktif' }}</p>
                </div>
                <div class="card-body p-5 text-center">
                    @if($quiz->status === 'active')
                        <div class="mb-4" style="display:flex; flex-direction:column; align-items:center; justify-content:center;">
                            <i class="fas fa-qrcode fa-4x text-primary mb-3"></i>
                            <h5>Scan QR Code untuk Bergabung</h5>
                            <p class="text-muted">Atau buka link: <code>{{ url('/quiz/join/' . $quiz->qr_code) }}</code></p>
                            <div id="qrCodeJoin"></div>
                        </div>

                        <form id="joinForm">
                            @csrf
                            <div class="form-group text-left">
                                <label for="participant_name" class="font-weight-bold">Nama Anda</label>
                                <input type="text" name="participant_name" id="participant_name" class="form-control form-control-lg" placeholder="Masukkan nama lengkap Anda" required autofocus maxlength="100">
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg btn-block mt-3">
                                <i class="fas fa-sign-in-alt mr-2"></i> Bergabung ke Kuis
                            </button>
                        </form>
                    @else
                        <div class="text-center py-4">
                            <div class="badge-icon bg-light text-warning mb-3" style="width: 80px; height: 80px; font-size: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h4 class="font-weight-bold text-gray-800">Kuis Belum Dimulai / Sudah Berakhir</h4>
                            <p class="text-muted">Status: <strong>{{ $quiz->status === 'draft' ? 'Draft (Belum Dimulai)' : 'Ditutup' }}</strong></p>
                            <p class="text-muted small">Silakan tunggu instruksi dari panitia.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    // Generate QR Code (hanya ada saat kuis aktif)
    if (document.getElementById('joinForm')) {
    new QRCode(document.getElementById('qrCodeJoin'), {
        text: '{{ url("/quiz/join/" . $quiz->qr_code) }}',
        width: 200,
        height: 200,
        colorDark: '#1e3c72',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });

    // Form submit
    document.getElementById('joinForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const name = document.getElementById('participant_name').value.trim();
        
        if (!name) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Bergabung...';

        try {
            const res = await fetch('{{ route("quiz.joinStore", $quiz->qr_code) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ participant_name: name })
            });

            const data = await res.json();

            if (data.success) {
                // Redirect ke pertanyaan pertama
                window.location.href = '{{ url("/quiz/session") }}' + '/' + data.session_id + '/question/' + data.questions[0].id;
            } else {
                alert(data.message || 'Gagal bergabung.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i> Bergabung ke Kuis';
            }
        } catch (err) {
            console.error(err);
            alert('Gagal terhubung ke server.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i> Bergabung ke Kuis';
        }
    });
    }
</script>
@endsection