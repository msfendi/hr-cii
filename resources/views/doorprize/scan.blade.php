@include('layout.header')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    :root{
        --dp-primary: #4e73df;
        --dp-primary-dark: #2e59d9;
    }
    .badge-stat{
        font-size:.82rem;
        padding:.5rem .85rem;
        border-radius: 2rem;
    }
    #scan-log{
        max-height: 300px;
        overflow-y: auto;
        text-align: left;
    }
    #scan-log li{
        font-size:.82rem;
    }
    .scan-ring{
        position: relative;
        display: inline-block;
    }
    .scan-ring::before{
        content:'';
        position:absolute;
        inset:-6px;
        border-radius: 14px;
        border: 3px solid rgba(255,255,255,.35);
        pointer-events:none;
        animation: scan-pulse 1.8s ease-in-out infinite;
    }
    @keyframes scan-pulse{
        0%   { opacity:.9; transform:scale(1); }
        50%  { opacity:.3; transform:scale(1.02); }
        100% { opacity:.9; transform:scale(1); }
    }
    #video{
        border-radius: 10px;
    }
    .card-scan-header{
        background: linear-gradient(135deg, var(--dp-primary) 0%, var(--dp-primary-dark) 100%);
        color:#fff;
        border-radius: .5rem .5rem 0 0;
        padding: 1.25rem 1.5rem;
        text-align:center;
    }
</style>
<body class="bg-gradient-primary">
@include('sweetalert::alert')

<div class="container container-center">
    <div class="text-center">
        <img src="{{ asset('img/chutex.svg') }}" style="width: 150px;">
        <h1 class="h4 text-white"><b>PT. Chutex International Indonesia</b></h1>
        <h1 class="h1 text-white mb-4"><b><i class="fas fa-gift mr-2"></i>Scan Doorprize</b></h1>
    </div>

    <div class="card o-hidden border-0 shadow-lg my-5 mx-auto" style="max-width:500px;">
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

            <div class="text-center mt-3">
                <span class="badge badge-info badge-stat" id="badge-total">
                    <i class="fas fa-camera-retro"></i> Total Scan: {{ $totalScanned }}
                </span>
            </div>

            <ul id="scan-log" class="list-group mt-3"></ul>
        </div>
    </div>
</div>

@include('layout.footerscript')
</body>
<script src="https://unpkg.com/@zxing/library@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let scanned = false; // penanda supaya hanya 1 QR diproses per sesi decode
const codeReader = new ZXing.BrowserQRCodeReader();
const statusEl = document.getElementById('scan-status');

// Validasi awal di client: harus diawali pola NPK (C-00001), boleh ada
// suffix "_NAMA KARYAWAN" atau tidak. Parsing NPK yang sesungguhnya
// dilakukan di server supaya format QR fleksibel berubah kapan saja.
const regexQr = /^[A-Za-z]-\d{5}(_.+)?$/;

function stopScanner() {
    try {
        codeReader.reset(); // hentikan stream kamera & loop decode
    } catch (e) {
        // no-op
    }
}

function addLog(npk, status, message) {
    const colorClass = status === 'success' ? 'list-group-item-success'
        : status === 'duplicate' ? 'list-group-item-warning'
        : 'list-group-item-danger';
    const time = new Date().toLocaleTimeString();
    document.getElementById('scan-log').insertAdjacentHTML('afterbegin', `
        <li class="list-group-item ${colorClass}">
            <strong>${npk}</strong> - ${message}
            <span class="float-right text-muted small">${time}</span>
        </li>
    `);
}

function startScanning() {
    codeReader.decodeFromVideoDevice(null, 'video', (result, err) => {
        // Kalau sudah pernah berhasil diproses, abaikan hasil decode berikutnya
        // (video masih bisa sempat mengirim 1-2 frame terakhir sebelum reset benar2 stop)
        if (scanned) return;

        if (result) {
            const rawText = result.text.trim();

            if (!regexQr.test(rawText)) {
                // Format salah -> jangan set scanned = true, biar bisa langsung coba lagi
                // tapi beri jeda supaya tidak spam alert tiap frame
                if (!statusEl.dataset.locked) {
                    statusEl.dataset.locked = '1';
                    Swal.fire({ icon: 'error', title: 'QR Tidak Valid', text: 'Format QR Code tidak sesuai (contoh: C-00001_NAMA KARYAWAN).' })
                        .then(() => { delete statusEl.dataset.locked; });
                }
                return;
            }

            // QR valid -> kunci supaya tidak ada scan/kirim ganda
            scanned = true;
            stopScanner();

            statusEl.textContent = 'Memverifikasi...';

            setTimeout(() => {
                fetch("{{ route('doorprize.scan.store') }}", {
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
                    const npkShown = (data.data && data.data.npk) || rawText.split('_')[0].toUpperCase();

                    if (!ok) {
                        addLog(npkShown, data.status, data.message);

                        if (data.status === 'duplicate') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'QR Sudah Pernah Discan!',
                                html: `NPK <b>${npkShown}</b> sudah discan pada <br><b>${data.scanned_at ?? '-'}</b>`,
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
                        }
                    } else {
                        addLog(npkShown, 'success', data.message);
                        document.getElementById('badge-total').innerHTML = '<i class="fas fa-camera-retro"></i> Total Scan: ' + data.data.total_scanned;

                        Swal.fire({
                            icon: 'success',
                            title: 'Scan Berhasil',
                            html: `<b>${npkShown}</b><br>${data.data.name}<br><small>${data.data.department}</small>`,
                            timer: 1500,
                            showConfirmButton: false,
                        });
                    }

                    // izinkan scan berikutnya (kiosk dipakai bergantian oleh banyak karyawan)
                    scanned = false;
                    startScanning();
                })
                .catch(() => {
                    statusEl.textContent = '';
                    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan, silakan coba lagi.' });
                    scanned = false;
                    startScanning();
                });
            }, 500);
        }
        // NotFoundException dilempar terus-menerus selama tidak ada QR di frame, ini normal, diabaikan
    }).catch(err => {
        Swal.fire({ icon: 'error', title: 'Kamera Tidak Tersedia', text: 'Tidak dapat mengakses kamera: ' + err });
    });
}

startScanning();

// Hentikan kamera saat pindah halaman / tab ditutup, biar resource tidak bocor
window.addEventListener('beforeunload', stopScanner);
</script>
</html>