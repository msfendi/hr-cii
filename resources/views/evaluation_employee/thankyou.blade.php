<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body class="bg-gradient-primary">
@include('sweetalert::alert')

<div class="container d-flex align-items-center justify-content-center" style="min-height:100vh;">

    <div class="card shadow-lg text-center p-5" style="max-width:600px; width:100%;">

        <h2 class="text-success mb-3">
            <i class="fas fa-check-circle"></i> Terima Kasih
        </h2>

        <h5 class="mb-3">
            Anda telah menyelesaikan evaluasi
        </h5>

        <p class="text-muted">
            Jawaban Anda telah berhasil disimpan ke sistem.
        </p>

        <div class="mt-4">
            <button onclick="window.close()" class="btn btn-secondary">
                Tutup Halaman
            </button>
        </div>

    </div>

</div>

@include('layout.footer')

</body>
</html>