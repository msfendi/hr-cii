@include('layout.header')
<meta name="csrf-token" content="{{ csrf_token() }}">
<body class="bg-gradient-primary">
@include('sweetalert::alert')

<div class="container container-center">
    <div class="text-center">
        <img src="{{ asset('img/chutex.svg') }}" style="width: 150px;">
        <h1 class="h4 text-white"><b>PT. Chutex International Indonesia</b></h1>
        <h1 class="h1 text-white mb-4"><b>Food Order</b></h1>
    </div>

    <div class="card o-hidden border-0 shadow-lg my-5 mx-auto" style="max-width:500px;">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h1 class="h4 text-gray-900">Scan QR Code Karyawan</h1>
                <p class="text-muted small mb-0">Arahkan kamera ke QR code pada ID card Anda</p>
            </div>

            <div class="camera-box-sm">
                <video id="video"></video>
            </div>

            <div id="scan-status" class="text-center mt-3 small text-muted"></div>
        </div>
    </div>
</div>

@include('layout.footerscript')
</body>
<script src="https://unpkg.com/@zxing/library@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let scanned = false; // penanda supaya hanya 1x proses per sesi kamera
const codeReader = new ZXing.BrowserQRCodeReader();
const statusEl = document.getElementById('scan-status');

// Format wajib QR: NPK_NAMA, contoh: C-00000_TEST
const regexQr = /^[A-Za-z]-\d{5,}_.+$/;

function stopScanner() {
    try {
        codeReader.reset(); // hentikan stream kamera & loop decode
    } catch (e) {
        // no-op
    }
}

codeReader.decodeFromVideoDevice(null, 'video', (result, err) => {
    // Kalau sudah pernah berhasil diproses, abaikan hasil decode berikutnya
    // (video masih bisa sempat mengirim 1-2 frame terakhir sebelum reset benar2 stop)
    if (scanned) return;

    if (result) {
        const rawText = result.text.trim();

        if (!regexQr.test(rawText)) {
            // Format salah -> jangan set scanned = true, biar user bisa coba lagi
            // tapi beri jeda supaya tidak spam alert tiap frame
            if (!statusEl.dataset.locked) {
                statusEl.dataset.locked = '1';
                Swal.fire({ icon: 'error', title: 'QR Tidak Valid', text: 'Format QR Code tidak sesuai (NPK_NAMA).' })
                    .then(() => { delete statusEl.dataset.locked; });
            }
            return;
        }

        // QR valid -> kunci supaya tidak ada scan/kirim ganda
        scanned = true;
        stopScanner();

        statusEl.textContent = 'Memverifikasi...';
        Swal.fire({
            icon: 'success',
            title: 'QR Code Terbaca',
            text: 'Memverifikasi...',
            showConfirmButton: false,
            timer: 1000
        });

        setTimeout(() => {
            fetch("{{ route('food-orders.scan.verify') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ qr_code: rawText })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire({ icon: 'warning', title: 'Akses Ditolak', text: data.message })
                        .then(() => {
                            // izinkan scan ulang kalau gagal verifikasi (mis. NPK tidak ditemukan)
                            scanned = false;
                            statusEl.textContent = '';
                            codeReader.decodeFromVideoDevice(null, 'video', arguments.callee);
                        });
                    return;
                }
                window.location = data.redirect;
            })
            .catch(() => {
                scanned = false;
                statusEl.textContent = '';
                Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan, silakan coba lagi.' });
            });
        }, 800);
    }
}).catch(err => {
    Swal.fire({ icon: 'error', title: 'Kamera Tidak Tersedia', text: 'Tidak dapat mengakses kamera: ' + err });
});

// Hentikan kamera saat pindah halaman / tab ditutup, biar resource tidak bocor
window.addEventListener('beforeunload', stopScanner);
</script>
</html>