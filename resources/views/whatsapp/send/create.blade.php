<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Create Send</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Create Send </h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('send-template') }}"> @csrf
                  <!-- DEVICE -->
                  <label>Device</label>
                  <select name="device_id" class="form-control"> @foreach($devices as $device) <option value="{{ $device->id }}">
                      {{ $device->name }}
                    </option> @endforeach </select>
                  <br>
                  <!-- TEMPLATE -->
                  <label>Template</label>
                  <select name="template_id" class="form-control"> @foreach($templates as $template) <option value="{{ $template->id }}">
                      {{ $template->name }}
                    </option> @endforeach </select>
                  <br>
                  <!-- TARGET NUMBER -->
                  <label>Target</label>
                  <select name="target" id="target" class="form-control" required>
                    <option value="">-- Select Applicant --</option> @foreach($applicants as $applicant) <option value="{{ $applicant->phone }}" data-name="{{ $applicant->name }}">
                      {{ $applicant->name }} - {{ $applicant->position }}
                    </option> @endforeach
                  </select>
                  <br>
                  <!-- NAMA AUTO -->
                  <input type="hidden" id="nama" name="variables[nama]" class="form-control" readonly required>
                  <br>
                  <label>Tanggal</label>
                  <input type="date" name="variables[tanggal]" class="form-control" required>
                  <br>
                  <label>Jam</label>
                  <input type="time" name="variables[jam]" class="form-control" required>
                  <br>
                  <button class="btn btn-success btn-block"> Send Whatsapp </button>
                </form>
              </div>
            </div>
          </div>
        </div> @include('layout.footer')
      </div>
    </div>
  </body>
  <!-- SELECT2 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
  <script>
    $(document).ready(function() {
      // init select2
      $('#target').select2({
        placeholder: "Select Applicant",
        allowClear: true,
        width: '100%'
      });
      // AUTO FILL NAME
      $('#target').on('change', function() {
        let selected = $(this).find(':selected');
        let name = selected.data('name');
        $('#nama').val(name ?? '');
      });
    });
  </script>
</html>