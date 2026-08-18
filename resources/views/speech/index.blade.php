<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
<!-- Page Wrapper -->
@include('sweetalert::alert')
<div id="wrapper">
@include('layout.sidebar')
    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main Content -->
        <div id="content">
            @include('layout.navbar')
            <!-- Begin Page Content -->
            <div class="container-fluid">

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Text To Speech</h1>
                </div>

                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Text To Speech</h6>
                    </div>
                    <div class="card-body">
                        <label>Text : </label>
                        <textarea type="text" class="form-control" id="speech"></textarea>
                        <br>
                        <label>Voice Type : </label>
                        <select name="voice" id="voice" class="form-control">
                            <option value="Indonesian Male">Indonesian Male</option>
                            <option value="Indonesian Female">Indonesian Female</option>
                        </select>
                        <small class="form-text text-muted">
                            Pilihan suara hanya berlaku untuk preview di browser. File MP3 yang diunduh
                            menggunakan satu suara standar Bahasa Indonesia.
                        </small>
                        <br>
                        <button id="submit" class="btn btn-primary">Proses</button>
                        <button id="cancel" class="btn btn-danger">Cancel</button>
                        <button id="download" class="btn btn-success">
                            <i class="fas fa-download"></i> Download MP3
                        </button>

                        <div id="result" class="mt-3"></div>
                    </div>
                </div>
                <!-- Content Row -->

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->


@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<!-- Page level custom scripts -->
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>
<script src="https://code.responsivevoice.org/responsivevoice.js?key=0zayRiU4"></script>
<script>
    async function texttospeech() {
        var voice = $('#voice').val();
        var teks = ' ' + $('#speech').val();
        await responsiveVoice.speak(teks, voice);
    }

    $('document').ready(function () {
        $('#submit').click(function() {
            texttospeech();
        });

        $('#cancel').click(function() {
            responsiveVoice.cancel();
        });

        $('#download').click(function () {
            var text = $('#speech').val().trim();

            if (!text) {
                Swal.fire('Peringatan', 'Teks tidak boleh kosong.', 'warning');
                return;
            }

            var $btn = $(this);
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm"></span> Memproses...'
            );
            $('#result').empty();

            $.ajax({
                url: '{{ route("speech.generate") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    text: text,
                    voice: $('#voice').val()
                },
                success: function (res) {
                    $('#result').html(
                        '<audio controls src="' + res.url + '" class="mb-2 w-100"></audio><br>' +
                        '<a href="' + res.url + '" download="' + res.filename + '" class="btn btn-outline-success">' +
                        '<i class="fas fa-download"></i> Simpan MP3</a>'
                    );
                },
                error: function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'Gagal membuat file MP3.';
                    Swal.fire('Error', msg, 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
    });
</script>
</html>