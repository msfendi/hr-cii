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
                        <br>
                        <button id="submit" class="btn btn-primary">Proses</button>
                        <button id="cancel" class="btn btn-danger">Cancel</button>
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
    });
</script>
</html>