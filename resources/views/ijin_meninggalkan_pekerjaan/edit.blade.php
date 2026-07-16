<!DOCTYPE html>
<html lang="en">
  @include('layout.header')

  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

  <body id="page-top">

    <div id="wrapper">
      @include('layout.sidebar')

      <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">

          @include('layout.navbar')

          <div class="container-fluid">

            <h1 class="h3 mb-4 text-gray-800">
              Edit Ijin Meninggalkan Pekerjaan
            </h1>

            <div class="card shadow">

              <div class="card-body">

                <form action="{{ route('ijin-meninggalkan-pekerjaan.update',$data->id) }}" method="POST">

                  @csrf
                  @method('PUT')

                  <div class="form-group">
                    <label>NPK</label>

                    <select name="npk" class="form-control select2" required>

                      @foreach($biodatas as $biodata)

                      <option value="{{ $biodata->NPK }}" {{ $data->npk == $biodata->NPK ? 'selected' : '' }}>
                        {{ $biodata->NPK }} - {{ $biodata->NAMA_KARYAWAN }}
                      </option>

                      @endforeach

                    </select>
                  </div>

                  <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" class="form-control"
                        value="{{ $data->tanggal ? \Carbon\Carbon::parse($data->tanggal)->format('Y-m-d') : '' }}" required>
                  </div>

                  <div class="form-group">
                    <label>Jam Keluar</label>
                    <input type="time" name="jam_keluar" class="form-control"
                        value="{{ $data->jam_keluar ? \Carbon\Carbon::parse($data->jam_keluar)->format('H:i') : '' }}" required>
                  </div>

                  <div class="form-group">
                    <label>Rencana Kembali</label>
                    <input type="time" name="rencana_kembali" class="form-control"
                        value="{{ $data->rencana_kembali ? \Carbon\Carbon::parse($data->rencana_kembali)->format('H:i') : '' }}">
                  </div>

                  <div class="form-group">
                    <label>Jam Kembali</label>
                    <input type="time" name="jam_kembali" class="form-control"
                        value="{{ $data->jam_kembali ? \Carbon\Carbon::parse($data->jam_kembali)->format('H:i') : '' }}">
                  </div>

                  <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" class="form-control">{{ $data->reason }}</textarea>
                  </div>

                  <button type="submit" class="btn btn-primary">
                    Update
                  </button>

                  <a href="{{ route('ijin-meninggalkan-pekerjaan.index') }}" class="btn btn-secondary">
                    Back
                  </a>

                </form>

              </div>
            </div>
            </div>

          </div>

          @include('layout.footer')

        </div>
      </div>

      <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

      <script>
        $('.select2').select2({
          width: '100%'
        });
      </script>

  </body>

</html>