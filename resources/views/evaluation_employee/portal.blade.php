<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body class="bg-gradient-primary">
@include('sweetalert::alert')

<div class="container container-center">
    <div class="text-center">
        <img src="{{ asset('img/chutex.svg') }}" style="width:150px;">
        <h1 class="h4 text-white"><b>PT. Chutex International Indonesia</b></h1>
        <h1 class="h1 text-white mb-4"><b>HRIS</b></h1>
    </div>

    <div class="card shadow-lg my-5">
        <div class="card-body">

            <ul class="nav nav-tabs" id="loginTab">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#qrlogin">
                        QR Code Login
                    </a>
                </li>
            </ul>

            <div class="tab-content mt-4">
                <!-- TAB 2 -->
                <div class="tab-pane fade show active" id="qrlogin">

                    <div id="step1">

                        <div class="camera-box">
                            <video id="video"></video>
                        </div>

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

function nextQR(){
    $("#step1").show();
}

function getParam(name) {
    let url = new URL(window.location.href);
    return url.searchParams.get(name);
}

let jobscope_id = getParam('jobscope_id');

let scanned = false;
const codeReader = new ZXing.BrowserQRCodeReader();

codeReader.decodeFromVideoDevice(null, 'video', (result, err) => {

    if (result && !scanned) {

        let arr = result.text.split("_");
        let npk = arr[0];

        // ✅ VALIDASI FORMAT NPK (C-00000)
        let regexNpk = /^C-\d{5}$/;

        if(!regexNpk.test(npk)){

            Swal.fire({
                icon: 'error',
                title: 'QR Tidak Valid',
                text: 'Format NPK Tidak Valid',
                confirmButtonText: 'Scan Ulang'
            });

            return; // STOP PROCESS
        }

        scanned = true;
        codeReader.reset();

        Swal.fire({
            icon: 'success',
            title: 'QR Code Berhasil Dibaca',
            text: 'Sedang menyiapkan soal..',
            showConfirmButton: false,
            timer: 1500,
            allowOutsideClick: false
        });

        setTimeout(function(){

            window.location.href =
            "/evaluation-employee/cbt?npk=" + npk + "&jobscope_id=" + jobscope_id;

        },1500);
    }

});

</script>

</body>
</html>