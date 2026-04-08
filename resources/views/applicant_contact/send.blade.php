<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Send Whatsapp</h1>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Form Send Whatsapp </h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('send-template') }}"> @csrf
                  <!-- DEVICE -->
                  <label>Device</label>
                  <select name="device_id" class="form-control" required> @foreach($devices as $device) <option value="{{ $device->id }}">
                      {{ $device->name }}
                    </option> @endforeach </select>
                  <br>
                  <!-- TEMPLATE -->
                  <label>Template</label>
                  <select name="template_id" class="form-control" required> @foreach($templates as $template) <option value="{{ $template->id }}" {{ $selectedTemplate == $template->id ? 'selected' : '' }}>
                      {{ $template->name }}
                    </option> @endforeach </select>
                  <br>
                  <!-- TARGET PHONE -->
                  <label>Target</label>
                  <input type="text" class="form-control" value="{{ $contact->phone ?? '' }}" readonly>
                  <input type="hidden" name="target" value="{{ $contact->phone ?? '' }}">
                  <br>
                  <!-- NAME -->
                  <label>Name</label>
                  <input type="text" class="form-control" value="{{ $contact->name ?? '' }}" readonly>
                  <input type="hidden" name="variables[nama]" value="{{ $contact->name ?? '' }}">
                  <br>
                  <!-- POSITION -->
                  <label>Position</label>
                  <input type="text" class="form-control" value="{{ $contact->position ?? '' }}" readonly>
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
</html>