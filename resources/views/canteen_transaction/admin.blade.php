<!DOCTYPE html>
<html lang="id">
@include('layout.header')
<body id="page-top">
@include('sweetalert::alert')
<div id="wrapper">
@include('layout.sidebar')
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @include('layout.navbar')
            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Admin Transaksi Kantin</h1>
                </div>

                @if ($message = Session::get('success'))
                    <div class="alert alert-success">{{ $message }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                <div class="row">
                    {{-- Master Kantin --}}
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Master Kantin (canteen_id 1/2)</h6></div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead><tr><th>Kantin</th><th>Nama</th><th>Lokasi</th><th>Kontak</th><th>Status</th></tr></thead>
                                        <tbody>
                                        @forelse($canteens as $c)
                                            <tr>
                                                <td>Kantin {{ $c->canteen_id ?: '-' }}</td>
                                                <td>{{ $c->name }}</td>
                                                <td>{{ $c->location }}</td>
                                                <td>{{ $c->contact_person ?: $c->pic_name }}{{ ($c->contact_phone ?: $c->pic_phone) ? ' / ' . ($c->contact_phone ?: $c->pic_phone) : '' }}</td>
                                                <td>{{ $c->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-center text-muted">Belum ada data — tambah lewat /canteens</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <a href="{{ route('canteens.index') }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Kelola Master Kantin</a>
                            </div>
                        </div>
                    </div>

                    {{-- Master Window --}}
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Master Jam Istirahat (3 Window)</h6></div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead><tr><th>Nama</th><th>Mulai</th><th>Selesai</th><th>Urutan</th><th>Aktif</th><th>Aksi</th></tr></thead>
                                        <tbody>
                                        @forelse($windows as $w)
                                            <tr>
                                                <td>{{ $w->name }}</td>
                                                <td>{{ $w->time_start }}</td>
                                                <td>{{ $w->time_end }}</td>
                                                <td>{{ $w->sort_order }}</td>
                                                <td>{{ $w->is_active ? 'Ya' : 'Tidak' }}</td>
                                                <td>
                                                    <form method="POST" action="{{ route('canteen-transaction.admin.window.destroy', $w->id) }}" onsubmit="return confirm('Hapus window ini?')" style="display:inline">
                                                        @csrf @method('DELETE')
                                                        <button class="btn btn-danger btn-sm">Hapus</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center text-muted">Belum ada window</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <form method="POST" action="{{ route('canteen-transaction.admin.window.store') }}" class="mt-3">
                                    @csrf
                                    <div class="form-row">
                                        <div class="form-group col-md-3"><label>Nama</label><input name="name" class="form-control" required placeholder="Window 1"></div>
                                        <div class="form-group col-md-3"><label>Mulai</label><input name="time_start" type="time" class="form-control" required></div>
                                        <div class="form-group col-md-3"><label>Selesai</label><input name="time_end" type="time" class="form-control" required></div>
                                        <div class="form-group col-md-2"><label>Urutan</label><input name="sort_order" type="number" class="form-control" value="0"></div>
                                        <div class="form-group col-md-1 mt-4"><div class="form-check"><input type="checkbox" name="is_active" value="1" checked class="form-check-input" id="wActive"><label class="form-check-label" for="wActive">Aktif</label></div></div>
                                    </div>
                                    <button class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah Window</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Pengaturan Transaksi Kantin</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('canteen-transaction.admin.setting.update') }}">
                            @csrf
                            <div class="form-row">
                                <div class="form-group col-md-3 form-check">
                                    <input type="checkbox" name="is_enabled" value="1" {{ $setting->is_enabled ? 'checked' : '' }} class="form-check-input" id="setEnabled">
                                    <label class="form-check-label" for="setEnabled">Fitur aktif</label>
                                </div>
                                <div class="form-group col-md-3 form-check">
                                    <input type="checkbox" name="liveness_canteen_enabled" value="1" {{ $setting->liveness_canteen_enabled ? 'checked' : '' }} class="form-check-input" id="setLiveness">
                                    <label class="form-check-label" for="setLiveness">Liveness check</label>
                                </div>
                                <div class="form-group col-md-3 form-check">
                                    <input type="checkbox" name="random_challenge_canteen_enabled" value="1" {{ $setting->random_challenge_canteen_enabled ? 'checked' : '' }} class="form-check-input" id="setChallenge">
                                    <label class="form-check-label" for="setChallenge">Random challenge</label>
                                </div>
                                <div class="form-group col-md-3 form-check">
                                    <input type="checkbox" name="require_geomap" value="1" {{ $setting->require_geomap ? 'checked' : '' }} class="form-check-input" id="setGeomap">
                                    <label class="form-check-label" for="setGeomap">Wajib di dalam geomap</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Geomap yang diperbolehkan (dari data geomap presensi)</label>
                                <select name="allowed_work_location_ids[]" class="form-control" multiple size="6">
                                    @foreach($locations as $loc)
                                        <option value="{{ $loc->id }}" {{ in_array($loc->id, $setting->allowed_work_location_ids ?: []) ? 'selected' : '' }}>{{ $loc->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Control+klik untuk pilih lebih dari satu. Kosongkan = tidak ada batasan geomap (bukan wajib).</small>
                            </div>
                            <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pengaturan</button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
        @include('layout.footer')
    </div>
</div>
</body>
</html>
