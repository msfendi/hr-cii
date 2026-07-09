<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<meta name="csrf-token" content="{{ csrf_token() }}">
<body class="bg-gradient-primary">
    @include('sweetalert::alert')

    <div class="container container-center">
        <div class="text-center">
            <img src="{{ asset('img/chutex.svg') }}" style="width: 150px;">
            <h1 class="h4 text-white"><b>PT. Chutex International Indonesia</b></h1>
            <h1 class="h1 text-white mb-4"><b>HRIS</b></h1>
        </div>
        <div class="card o-hidden border-0 shadow-lg my-5">
            <div class="card-body p-0">
                <!-- Nested Row within Card Body -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="p-5">
                            <div class="text-center">
                                <h1 class="h4 text-gray-900 mb-4">Sign In with Email</h1>
                            </div>
                            <form class="user" action="{{ route('login') }}" method="post">
                                @csrf
                                @if ($message = Session::get('success'))
                                <div class="alert alert-success alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif

                                @if ($message = Session::get('error'))
                                <div class="alert alert-danger alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif

                                @if ($message = Session::get('warning'))
                                <div class="alert alert-warning alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif

                                @if ($message = Session::get('info'))
                                <div class="alert alert-info alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif
                                
                                <div class="form-group">
                                    <input type="email" class="form-control form-control-user @error('email') is-invalid @enderror" id="email" name="email"
                                        placeholder="Email" value="{{ old('email') }}">
                                    @error('email')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <input type="password" class="form-control form-control-user @error('password') is-invalid @enderror" id="password" name="password"
                                        placeholder="Password">
                                    @error('name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label class="small mb-1">Masukkan Kode Captcha</label>
                                    <div class="text-center mb-2">
                                        <img id="captcha-img"
                                            src="{{ route('captcha.image') }}?t={{ time() }}"
                                            alt="Captcha"
                                            style="border:1px solid #ccc; border-radius:4px; cursor:pointer;"
                                            title="Klik untuk refresh"
                                            onclick="refreshCaptcha()">
                                    </div>
                                    <div class="input-group">
                                        <input type="text" autocomplete="off"
                                            class="form-control form-control-user @error('captcha') is-invalid @enderror"
                                            id="captcha" name="captcha" placeholder="Masukkan kode di atas">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary" onclick="refreshCaptcha()" title="Refresh Captcha">
                                                &#x21bb;
                                            </button>
                                        </div>
                                        @error('captcha')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary btn-user btn-block">Login</button>
                                </div>
                                <!-- <hr> -->
                            </form>
                            <div class="text-center">
                                <!-- <a class="small" href="{{ route('register') }}">Don't have account? Register!</a> -->
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="p-5">
                            <div class="text-center">
                                <h1 class="h4 text-gray-900 mb-4">Sign In with ID Card</h1>
                            </div>
                            <div class="camera-box-sm">
                                <video id="video"></video>
                            </div>

                        </div>
                    </div>
                </div>
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

// Ambil / generate identitas device (persisten di localStorage)
function getDeviceInfo() {
    let deviceUuid = localStorage.getItem('hris_device_uuid');
    if (!deviceUuid) {
        deviceUuid = (crypto.randomUUID)
            ? crypto.randomUUID()
            : 'dev-' + Date.now() + '-' + Math.random().toString(36).substring(2, 10);
        localStorage.setItem('hris_device_uuid', deviceUuid);
    }

    const ua = navigator.userAgent;
    let deviceType = 'desktop';
    if (/Mobi|Android/i.test(ua)) deviceType = 'mobile';
    else if (/Tablet|iPad/i.test(ua)) deviceType = 'tablet';
    else if (/Macintosh|Windows NT/i.test(ua) && navigator.maxTouchPoints === 0) deviceType = 'desktop';

    let browser = 'Unknown';
    if (ua.includes('Edg')) browser = 'Edge';
    else if (ua.includes('Chrome')) browser = 'Chrome';
    else if (ua.includes('Firefox')) browser = 'Firefox';
    else if (ua.includes('Safari')) browser = 'Safari';

    const platform = navigator.platform || navigator.userAgentData?.platform || 'Unknown';
    const deviceName = `${deviceType} - ${platform} - ${browser}`;

    return { device_uuid: deviceUuid, device_type: deviceType, platform, browser, device_name: deviceName };
}

codeReader.decodeFromVideoDevice(null, 'video', (result, err) => {
    if (result && !scanned) {
        scanned = true;
        let rawText = result.text.trim();
        let npk = rawText.split('_')[0];
        let regexNpk = /^C-\d{5}$/;

        if (!regexNpk.test(npk)) {
            scanned = false;
            Swal.fire({ icon: 'error', title: 'QR Tidak Valid', text: 'Format NPK Tidak Valid' });
            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'QR Code Terbaca',
            text: 'Memverifikasi...',
            showConfirmButton: false,
            timer: 1000
        });

        setTimeout(() => {
            const device = getDeviceInfo();

            fetch("{{ route('login.qrauth') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    qrcode: npk,
                    ...device
                })
            })
            .then(res => res.json())
            .then(data => {

                if (!data.success) {

                    scanned = false;

                    Swal.fire({
                        icon: 'warning',
                        title: 'Akses Ditolak',
                        text: data.message
                    });

                    return;
                }

                window.location = data.redirect;

            });
        }, 1000);
    }
});
</script>
<script>
function refreshCaptcha() {
    // tambahkan timestamp supaya browser tidak pakai cache gambar lama
    document.getElementById('captcha-img').src =
        "{{ route('captcha.image') }}?t=" + new Date().getTime();
    document.getElementById('captcha').value = '';
}
</script>
</html>