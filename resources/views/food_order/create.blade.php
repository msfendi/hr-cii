<!DOCTYPE html>
<html lang="en">
  @include('layout.header')
  <body id="page-top">
  @include('sweetalert::alert')
  <div id="wrapper">
  @include('layout.sidebar')
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        @include('layout.navbar')
        <div class="container-fluid">
          <h1 class="h3 mb-4 text-gray-800">Tambah Menu Makanan</h1>
          <div class="card shadow mb-4">
            <div class="card-body">
              <form action="{{ route('food-menus.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Kantin</label>
                    <select name="canteen_id" class="form-control" required>
                      <option value="">-- Pilih Kantin --</option>
                      @foreach($canteens as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group col-md-6">
                    <label>Nama Menu</label>
                    <input type="text" name="name" class="form-control" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Deskripsi</label>
                  <textarea name="description" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-row">
                  <!-- <div class="form-group col-md-4">
                    <label>Harga</label>
                    <input type="number" step="0.01" name="price" class="form-control" required>
                  </div> -->
                  <div class="form-group col-md-4">
                    <label>Kuota per Hari (kosongkan = unlimited)</label>
                    <input type="number" min="1" name="quota" class="form-control">
                  </div>
                  <div class="form-group col-md-4">
                    <label>Foto Menu</label>
                    <input type="file" name="photo" accept="image/*" class="form-control-file">
                  </div>
                </div>

                <hr>
                <h6 class="font-weight-bold text-primary">Pengaturan Ketersediaan</h6>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Tersedia mulai tanggal</label>
                    <input type="date" name="available_start" class="form-control">
                  </div>
                  <div class="form-group col-md-6">
                    <label>Sampai tanggal (opsional)</label>
                    <input type="date" name="available_end" class="form-control">
                  </div>
                </div>

                <div class="form-group">
                  <label>Hari tersedia (kosongkan = setiap hari)</label><br>
                  @foreach(['monday'=>'Senin','tuesday'=>'Selasa','wednesday'=>'Rabu','thursday'=>'Kamis','friday'=>'Jumat','saturday'=>'Sabtu','sunday'=>'Minggu'] as $val => $label)
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" name="available_days[]" value="{{ $val }}" id="day_{{ $val }}">
                      <label class="form-check-label" for="day_{{ $val }}">{{ $label }}</label>
                    </div>
                  @endforeach
                </div>

                <div class="form-group">
                  <label>Minggu ke- dalam bulan (kosongkan = setiap minggu)</label><br>
                  @foreach([1,2,3,4,5] as $w)
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" name="available_weeks[]" value="{{ $w }}" id="week_{{ $w }}">
                      <label class="form-check-label" for="week_{{ $w }}">Minggu ke-{{ $w }}</label>
                    </div>
                  @endforeach
                </div>

                <div class="form-group form-check">
                  <input type="checkbox" class="form-check-input" name="is_active" id="is_active" checked>
                  <label class="form-check-label" for="is_active">Aktif</label>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                <a href="{{ route('food-menus.index') }}" class="btn btn-secondary">Batal</a>
              </form>
            </div>
          </div>
        </div>
      </div>
  @include('layout.footer')
    </div>
  </body>
</html>