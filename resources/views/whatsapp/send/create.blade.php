<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
<!-- Page Wrapper -->
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
                    <h1 class="h3 mb-0 text-gray-800">Create Send</h1>
                </div>
                

                <!-- Approach -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Form Create Send</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('send-template') }}"> @csrf <label>Device</label>
                        <select name="device_id" class="form-control"> @foreach($devices as $device) <option value="{{ $device->id }}">{{ $device->name }}</option> @endforeach </select>
                        <br>
                        <label>Template</label>
                        <select name="template_id" class="form-control"> @foreach($templates as $template) <option value="{{ $template->id }}">{{ $template->name }}</option> @endforeach </select>
                        <br>
                        <label>Target Number</label>
                        <input type="text" name="target" class="form-control" placeholder="628xxxx">
                        <br>
                        <label>Nama</label>
                        <input type="text" name="variables[nama]" class="form-control">
                        <br>
                        <label>Tanggal</label>
                        <input type="date" name="variables[tanggal]" class="form-control">
                        <br>
                        <label>Jam</label>
                        <input type="time" name="variables[jam]" class="form-control">
                        <br>
                        <button class="btn btn-success btn-block"> Send Whatsapp </button>
                      </form>
                    </div>
                </div>

                <!-- Content Row -->

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

@include('layout.footer')
</body>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
<script type="text/javascript">
    $("#role_id").select2({
          allowClear: true
    });
</script>
</html>