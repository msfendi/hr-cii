<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top">
    <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <h1 class="h3 mb-4 text-gray-800">Edit Health Test</h1>
            <form method="POST" action="{{ route('health-test.update',$data->id) }}"> @csrf @method('PUT') <div class="row">
                <!-- ================= KONDISI FISIK ================= -->
                <div class="col-lg-6">
                  <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white"> Kondisi Fisik </div>
                    <div class="card-body">
                      {{-- NIK SELECT2 --}}
                      <div class="form-group">
                        <label>NIK</label>
                        <select name="nik" id="nik" class="form-control select2" required>
                          <option value="">Pilih Pelamar</option> @foreach($pelamar as $p) <option value="{{$p->NIK}}" data-tinggi="{{$p->TINGGI_BADAN}}" data-berat="{{$p->BERAT_BADAN}}" {{ $data->nik == $p->NIK ? 'selected' : '' }}>
                            {{$p->NAMA}} ({{$p->NIK}})
                          </option> @endforeach
                        </select>
                      </div>
                      {{-- AUTO BODY --}}
                      <div class="form-group">
                        <label>Tinggi Badan</label>
                        <input type="text" id="tinggi" class="form-control" value="{{ $data->tinggi }}" readonly>
                      </div>
                      <div class="form-group">
                        <label>Berat Badan</label>
                        <input type="text" id="berat" class="form-control" value="{{ $data->berat }}" readonly>
                      </div> @php $kondisiFisik=[ 'cacat'=>'Cacat', 'buta_warna'=>'Buta Warna' ]; @endphp @foreach($kondisiFisik as $field=>$label) <div class="form-group row align-items-center">
                        <label class="col-sm-5 col-form-label">{{$label}}</label>
                        <div class="col-sm-7">
                          <input type="hidden" name="{{$field}}" value="0">
                          <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="{{$field}}" name="{{$field}}" value="1" {{ $data->$field ? 'checked' : '' }}>
                            <label class="custom-control-label" for="{{$field}}"> YA </label>
                          </div>
                        </div>
                      </div> @endforeach
                      <div class="form-group">
                          <div class="row">
                              <div class="col-md-6">
                                  <label>Visus Mata OD</label>
                                  <input
                                      type="text"
                                      name="visus_mata_od"
                                      class="form-control mb-2"
                                      placeholder="Visus Mata OD"
                                      value="{{ $data->visus_mata_od }}">
                              </div>

                              <div class="col-md-6">
                                  <label>Visus Mata OS</label>
                                  <input
                                      type="text"
                                      name="visus_mata_os"
                                      class="form-control mb-2"
                                      placeholder="Visus Mata OS"
                                      value="{{ $data->visus_mata_os }}">
                              </div>
                          </div>
                      </div>
                      <div class="form-group">
                        <div class="row">
                            <div class="col-md-6">
                                <label>Abdoment</label>
                                <input
                                    type="text"
                                    name="abdoment"
                                    class="form-control mb-2"
                                    placeholder="Abdoment"
                                    value="{{ $data->abdoment }}">
                            </div>

                            <div class="col-md-6">
                                <label>Gigi</label>
                                <input
                                    type="text"
                                    name="gigi"
                                    class="form-control mb-2"
                                    placeholder="Gigi"
                                    value="{{ $data->gigi }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                      <div class="row">
                          <div class="col-md-6">
                              <label>Abdoment</label>
                              <input
                                  type="text"
                                  name="cor_pulmo"
                                  class="form-control mb-2"
                                  placeholder="Cor Pulmo"
                                  value="{{ $data->cor_pulmo }}">
                          </div>

                          <div class="col-md-6">
                              <label>THT</label>
                              <input
                                  type="text"
                                  name="tht"
                                  class="form-control mb-2"
                                  placeholder="THT"
                                  value="{{ $data->tht }}">
                          </div>
                      </div>
                  </div>
                  <div class="form-group">
                      <div class="row">
                          <div class="col-md-6">
                              <label>Extremitas</label>
                              <input
                                  type="text"
                                  name="extreme"
                                  class="form-control mb-2"
                                  placeholder="EXTREMITAS"
                                  value="{{ $data->extreme }}">
                          </div>
                      </div>
                  </div>
                    </div>
                  </div>
                </div>
                <!-- ================= VITAL SIGN ================= -->
                <div class="col-lg-6">
                  <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white"> Vital Sign </div>
                    <div class="card-body">
                      <input type="text" name="tekanan_darah" class="form-control mb-2" placeholder="Tekanan Darah" value="{{ $data->tekanan_darah }}">
                      <input type="number" name="respirasi" class="form-control mb-2" placeholder="Respirasi" value="{{ $data->respirasi }}">
                      <input type="number" name="denyut" class="form-control mb-2" placeholder="Denyut" value="{{ $data->denyut }}">
                      <input type="number" name="suhu" class="form-control" placeholder="Suhu" value="{{ $data->suhu }}" step="any">
                    </div>
                  </div>
                </div>
                <!-- ================= RIWAYAT ================= -->
                <div class="col-lg-12">
                  <div class="card shadow mb-4">
                    <div class="card-header bg-warning"> Riwayat Penyakit </div>
                    <div class="card-body">
                      <div class="row"> @php $riwayat=[ 'paru'=>'Paru', 'hepatitis'=>'Hepatitis', 'jantung'=>'Jantung', 'thypoid'=>'Thypoid', 'alergi'=>'Alergi', 'ashma'=>'Ashma' ]; @endphp @foreach($riwayat as $field=>$label) <div class="col-md-4">
                          <input type="hidden" name="{{$field}}" value="0">
                          <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="{{$field}}" name="{{$field}}" value="1" {{ $data->$field ? 'checked' : '' }}>
                            <label class="custom-control-label" for="{{$field}}">
                              {{$label}}
                            </label>
                          </div>
                        </div> @endforeach </div>
                      <input type="text" name="lain" class="form-control mt-3" placeholder="Lainnya" value="{{ $data->lain }}">
                    </div>
                  </div>
                </div>
                <!-- ================= KESIMPULAN ================= -->
                <div class="col-lg-12">
                  <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white"> Kesimpulan </div>
                    <div class="card-body">
                      <input type="hidden" name="kesimpulan" value="0">
                      <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="kesimpulan" name="kesimpulan" value="1" {{ $data->kesimpulan ? 'checked' : '' }}>
                        <label class="custom-control-label" for="kesimpulan"> Sehat </label>
                      </div>
                      <input type="text" name="remark" class="form-control mt-3" placeholder="Remark" value="{{ $data->remark }}">
                    </div>
                  </div>
                </div>
              </div>
              <button class="btn btn-primary btn-lg btn-block"> Update Data </button>
            </form>
          </div> @include('layout.footer')
          <!-- SELECT2 -->
          <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
          <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
          <script>
           $('.select2').select2({
                placeholder: 'Pilih Pelamar',
                width: '100%'
            });

            /* AUTO BODY DATA */
            $('#nik').on('change', function () {

                let opt = $(this).find(':selected');

                $('#tinggi').val(opt.data('tinggi') ?? '');
                $('#berat').val(opt.data('berat') ?? '');
                $('#id').val(opt.data('id') ?? '');

            });

            /* LOAD DATA SAAT EDIT */
            $('#nik').trigger('change');
          </script>
        </div>
      </div>
    </div>
  </body>
</html>