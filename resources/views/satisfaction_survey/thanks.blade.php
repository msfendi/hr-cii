<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terima Kasih — Kuesioner Kepuasan Karyawan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f6f9ff 0%, #e8eefc 100%); min-height: 100vh; display: flex; align-items: center; }
        .thanks-card { border: none; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(0,0,0,.08); }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card thanks-card">
                <div class="card-body p-5 text-center">
                    <div class="mb-3"><i class="fas fa-heart" style="font-size:48px; color:#e74a3b;"></i></div>
                    <h4 class="font-weight-bold">Terima kasih atas partisipasi Anda.</h4>
                    <p class="text-muted">Pendapat Anda penting untuk menjadikan Chutex tempat kerja yang lebih baik, nyaman, aman, dan saling menghargai.</p>
                    <a href="{{ route('satisfaction-survey.create') }}" class="btn btn-outline-primary">Isi Lagi</a>
                    <a href="/" class="btn btn-primary">Kembali ke Beranda</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
