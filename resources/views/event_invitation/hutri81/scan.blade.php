@include('layout.header')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    :root{
        --merah: #C8102E;
        --merah-tua: #8B0000;
        --gold: #FFD700;
    }
    body.bg-merdeka{
        background: radial-gradient(circle at top, var(--merah) 0%, var(--merah-tua) 55%, #4a0000 100%);
    }
    .scan-ring{ position: relative; display: inline-block; }
    .scan-ring::before{
        content:'';
        position:absolute;
        inset:-6px;
        border-radius: 14px;
        border: 3px solid rgba(255,215,0,.55);
        pointer-events:none;
        animation: scan-pulse 1.8s ease-in-out infinite;
    }
    @keyframes scan-pulse{
        0%   { opacity:.9; transform:scale(1); }
        50%  { opacity:.3; transform:scale(1.02); }
        100% { opacity:.9; transform:scale(1); }
    }
    #video{ border-radius: 10px; }
    .card-scan-header{
        background: linear-gradient(135deg, var(--merah) 0%, var(--merah-tua) 100%);
        color:#fff;
        border-radius: .5rem .5rem 0 0;
        padding: 1.25rem 1.5rem;
        text-align:center;
    }
</style>
<body class="bg-merdeka">
@include('sweetalert::alert')

<div class="container container-center">
    <div class="text-center">
        <img src="{{ asset('img/chutex.svg') }}" style="width: 130px;">
        <h1 class="h4 text-white mb-1"><b>PT. Chutex International Indonesia</b></h1>
        <h1 class="h2 text-white mb-4">
            <b>🇮🇩 {{ $event->nama_event }}</b>
        </h1>
        <p class="text-white-50 mb-4">Scan QR Code pada ID Card Anda untuk mengisi konfirmasi kehadiran</p>
    </div>

    <div class="card o-hidden border-0 shadow-lg my-4 mx-auto" style="max-width:500px;">
        <div class="card-scan-header">
            <h5 class="mb-1"><i class="fas fa-qrcode mr-1"></i> Scan QR Code Karyawan</h5>
            <p class="small mb-0" style="opacity:.9;">Arahkan kamera ke QR code pada ID card Anda</p>
        </div>
        <div class="card-body p-4 text-center">
            <div class="scan-ring">
                <div class="camera-box-sm">
                    <video id="video"></video>
                </div>
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
let scanned = false;
const codeReader = new ZXing.BrowserQRCodeReader();
const statusEl = document.getElementById('scan-status');
const regexQr = /^[A-Za-z]-\d{5}(_.+)?$/;

function stopScanner() {
    try { codeReader.reset(); } catch (e) { /* no-op */ }
}

function startScanning() {
    codeReader.decodeFromVideoDevice(null, 'video', (result, err) => {
        if (scanned) return;

        if (result) {
            const rawText = result.text.trim();

            if (!regexQr.test(rawText)) {
                if (!statusEl.dataset.locked) {
                    statusEl.dataset.locked = '1';
                    Swal.fire({ icon: 'error', title: 'QR Tidak Valid', text: 'Format QR Code tidak sesuai (contoh: C-00827_NAMA KARYAWAN).' })
                        .then(() => { delete statusEl.dataset.locked; });
                }
                return;
            }

            scanned = true;
            stopScanner();
            statusEl.textContent = 'Memverifikasi...';

            setTimeout(() => {
                fetch("{{ route('event-invitation.scan.store', ['event' => $event->id]) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ qr_code: rawText })
                })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(({ ok, data }) => {
                    statusEl.textContent = '';

                    if (!ok) {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }).then(() => {
                            scanned = false;
                            startScanning();
                        });
                        return;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Scan Berhasil',
                        text: data.message,
                        timer: 1300,
                        showConfirmButton: false,
                    }).then(() => {
                        window.location.href = data.redirect;
                    });
                })
                .catch(() => {
                    statusEl.textContent = '';
                    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan, silakan coba lagi.' });
                    scanned = false;
                    startScanning();
                });
            }, 400);
        }
    }).catch(err => {
        Swal.fire({ icon: 'error', title: 'Kamera Tidak Tersedia', text: 'Tidak dapat mengakses kamera: ' + err });
    });
}

startScanning();
window.addEventListener('beforeunload', stopScanner);
</script>
</html>
