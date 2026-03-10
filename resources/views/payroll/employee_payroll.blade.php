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
                            <select class="form-control" name="run_id">
                                @foreach($periods as $period)
                                <option value="{{$period->id}}">
                                    {{ date('F Y',strtotime($period->start_date)) }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <button class="btn btn-primary btn-block">
                            Show Payroll Slip
                        </button>

                    </form>

                </div>

                <!-- TAB 2 -->
                <div class="tab-pane fade" id="qrlogin">

                    <div id="step1">

                        <div class="form-group">
                            <label>Pilih Periode</label>
                            <select class="form-control" id="run_id_qr">
                                @foreach($periods as $period)
                                <option value="{{$period->id}}">
                                    {{ date('F Y',strtotime($period->start_date)) }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <button class="btn btn-success btn-block" onclick="nextQR()">
                            Next
                        </button>

                    </div>

                    <div id="step2" style="display:none">

                        <video id="video" width="100%" height="300" object-fit="cover"></video>

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
    $("#step1").hide();
    $("#step2").show();
}

let scanned = false;

const codeReader = new ZXing.BrowserQRCodeReader();

codeReader.decodeFromVideoDevice(null, 'video', (result, err) => {

    if (result && !scanned) {

        scanned = true;

        codeReader.reset(); // stop kamera

        let arr = result.text.split("_");
        let npk = arr[0];
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

</body>
</html>