<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body class="bg-gradient-primary">
@include('sweetalert::alert')

<div class="container container-center">
    <div class="text-center">
        <img src="{{ asset('img/chutex.svg') }}" style="width:150px;">
        <h1 class="h4 text-white"><b>PT. Chutex International Indonesia</b></h1>
        <h1 class="h1 text-white mb-4"><b>E-HRIS</b></h1>
    </div>

    <div class="card shadow-lg my-5">
        <div class="card-body">

            <ul class="nav nav-tabs" id="loginTab">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#qrlogin">
                        QR Code Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#manual">
                        Manual Login
                    </a>
                </li>
            </ul>

            <div class="tab-content mt-4">

                <!-- TAB 1 -->
                <div class="tab-pane fade" id="manual">

                    <form method="POST" action="{{ route('employee-payroll.verify-password') }}">
                        @csrf

                        <div class="form-group">
                            <label>NPK</label>
                            <input type="text" class="form-control" name="npk">
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" name="password">
                        </div>

                        <div class="form-group">
                            <label>Payroll Period</label>

                            <select class="form-control" name="run_id" id="run_id_manual">
                                <option value="" selected disabled>-- Pilih Periode --</option>

                                @foreach($periods as $period)
                                <option value="{{$period->id}}">
                                    {{ date('F Y',strtotime($period->start_date)) }}
                                </option>
                                @endforeach

                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block" id="btnManual" disabled>
                            Show Payroll Slip
                        </button>

                    </form>

                </div>

                <!-- TAB 2 -->
                <div class="tab-pane fade show active" id="qrlogin">

                    <div id="step1">

                        <div class="form-group">
                            <label>Pilih Periode</label>

                            <select class="form-control" id="run_id_qr">
                                <option value="" selected disabled>-- Pilih Periode --</option>

                                @foreach($periods as $period)
                                <option value="{{$period->id}}">
                                    {{ date('F Y',strtotime($period->start_date)) }}
                                </option>
                                @endforeach
                            </select>

                        </div>

                        <button type="button" class="btn btn-success btn-block" id="btnNextQR" disabled onclick="nextQR()">
                            Next
                        </button>

                    </div>

                    <div id="step2" style="display:none">

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

let scanned = false;

const codeReader = new ZXing.BrowserQRCodeReader();

codeReader.decodeFromVideoDevice(null, 'video', (result, err) => {

    if (result && !scanned) {

        let arr = result.text.split("_");
        let npk = arr[0];

        // ✅ VALIDASI FORMAT NPK
        let regexNpk = /^C-\d{5}$/;

        if(!regexNpk.test(npk)){

            Swal.fire({
                icon: 'error',
                title: 'QR Tidak Valid',
                text: 'Format NPK Tidak Valid',
                confirmButtonText: 'Scan Ulang'
            });

            return; // stop proses
        }

        scanned = true;

        codeReader.reset(); // stop kamera

        let run_id = $("#run_id_qr").val();

        Swal.fire({
            icon: 'success',
            title: 'QR Code Berhasil Dibaca',
            text: 'Sedang membuka slip payroll...',
            showConfirmButton: false,
            timer: 1500,
            allowOutsideClick: false
        });

        setTimeout(function(){

            window.location.href =
            "/employee-payroll/qr-login?npk="+npk+"&run_id="+run_id;

        },1500);
    }

});

</script>
<script>

function toggleQR(){
    $("#btnNextQR").prop("disabled", !$("#run_id_qr").val());
}

function toggleManual(){
    $("#btnManual").prop("disabled", !$("#run_id_manual").val());
}

$(document).ready(function(){

    $("#run_id_manual").on("change", toggleManual);
    $("#run_id_qr").on("change", toggleQR);

    // FORCE refresh state saat load
    setTimeout(function(){
        $("#run_id_manual").trigger("change");
        $("#run_id_qr").trigger("change");
    },100);

    // Saat pindah TAB
    $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
        $("#run_id_manual").trigger("change");
        $("#run_id_qr").trigger("change");
    });

});

function nextQR(){

    if(!$("#run_id_qr").val()){
        Swal.fire({
            icon:'warning',
            title:'Pilih Periode dulu'
        });
        return;
    }

    $("#step1").hide();
    $("#step2").show();
}

</script>
</body>
</html>