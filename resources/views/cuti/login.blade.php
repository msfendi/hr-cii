<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body class="bg-gradient-primary">
@include('sweetalert::alert')

<div class="container container-center">
    <div class="text-center mt-5">
        <img src="{{ asset('img/chutex.svg') }}" style="width:150px;">
        <h1 class="h4 text-white"><b>PT. Chutex International Indonesia</b></h1>
        <h1 class="h1 text-white mb-4"><b>Pengajuan Cuti Online</b></h1>
    </div>

    <div class="card shadow-lg my-5">
        <div class="card-body">

            <ul class="nav nav-tabs" id="loginTab">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#manual">
                        Manual Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#qrlogin">
                        QR Code Login
                    </a>
                </li>
            </ul>

            <div class="tab-content mt-4">

                <!-- TAB 1 -->
                <div class="tab-pane fade show active" id="manual">
                    <form method="POST" action="{{ route('pengajuan-cuti.verify-manual') }}">
                        @csrf
                        <div class="form-group">
                            <label>NPK</label>
                            <input type="text" class="form-control" name="npk" required placeholder="Masukkan NPK Anda">
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" name="password" required placeholder="Masukkan Password Anda">
                        </div>

                        <button class="btn btn-primary btn-block">
                            Masuk Pengajuan Cuti
                        </button>
                    </form>
                </div>

                <!-- TAB 2 -->
                <div class="tab-pane fade" id="qrlogin">
                    <div id="step2">
                        <video id="video" width="100%" height="300" style="object-fit: cover"></video>
                        <p class="text-center text-muted mt-2">Arahkan QR Code ID Card Anda ke kamera utama.</p>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

@include('layout.footerscript')
<script src="https://unpkg.com/@zxing/library@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let scanned = false;
let codeReader = new ZXing.BrowserQRCodeReader();

function startScanner() {
    scanned = false;
    codeReader.decodeFromVideoDevice(null, 'video', (result, err) => {
        if (result && !scanned) {
            scanned = true;
            codeReader.reset(); // stop kamera

            let arr = result.text.split("_");
            let npk = arr[0];

            Swal.fire({
                icon: 'success',
                title: 'QR Code Berhasil Dibaca',
                text: 'Sedang memproses login cuti...',
                showConfirmButton: false,
                timer: 1500,
                allowOutsideClick: false
            });

            setTimeout(function(){
                window.location.href = "{{ route('pengajuan-cuti.qr-login') }}?npk=" + npk;
            }, 1000);
        }
    });
}

$(document).ready(function() {
    // Jalankan langsung scanner jika tab qr sedang aktif (untuk load pertama, biarpun hidden, dia start)
    startScanner();

    // Refresh scanner ketika tab QR Code diklik
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        if (e.target.hash === '#qrlogin') {
            startScanner();
        } else {
            codeReader.reset();
        }
    });
});
</script>

</body>
</html>
