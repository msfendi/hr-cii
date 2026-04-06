<!DOCTYPE html>
<html lang="en">
@include('layout.header')
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
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary btn-user btn-block">Login</button>
                                </div>
                                <hr>
                            </form>
                            <div class="text-center">
                                <a class="small" href="{{ route('register') }}">Don't have account? Register!</a>
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

codeReader.decodeFromVideoDevice(null, 'video', (result, err) => {

    if (result && !scanned) {

        scanned = true;

        let rawText = result.text.trim();

        console.log("QR RESULT:", rawText);

        let npk = rawText.split('_')[0];

        let regexNpk = /^C-\d{5}$/;

        if (!regexNpk.test(npk)) {

            scanned = false;

            Swal.fire({
                icon: 'error',
                title: 'QR Tidak Valid',
                text: 'Format NPK Tidak Valid'
            });

            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'QR Code Terbaca',
            text: 'Login...',
            showConfirmButton: false,
            timer: 1000
        });

        // ❗ JANGAN reset kamera dulu
        setTimeout(() => {

            window.location.href =
                "{{ route('login.qrauth') }}?qrcode=" + encodeURIComponent(npk);

        }, 1000);
    }

});
</script>
</html>