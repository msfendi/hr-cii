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

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-notes-medical"></i> Form Pemeriksaan Pasien</h1>
                    <div class="d-flex">
                        <a href="{{ route('dokter.antrian') }}" class="btn btn-secondary btn-sm mr-2">
                            <i class="fas fa-arrow-left"></i> Kembali ke Antrian
                        </a>
                        <a href="{{ route('report.rekap', ['nama' => $kunjungan->NPK]) }}" class="btn btn-info btn-sm" target="_blank">
                            <i class="fas fa-history"></i> Rekap Pemeriksaan Karyawan
                        </a>
                    </div>
                </div>

                <!-- Patient Info Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-info text-white">
                        <h6 class="m-0 font-weight-bold"><i class="fas fa-user"></i> Informasi Pasien</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p class="mb-1"><strong>NPK:</strong></p>
                                <p>{{ $karyawan->NPK ?? '-' }}</p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1"><strong>Nama Karyawan:</strong></p>
                                <p>{{ $karyawan->NAMA_KARYAWAN ?? '-' }}</p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1"><strong>Departemen:</strong></p>
                                <p>{{ $karyawan->DEPARTEMENT ?? '-' }}</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <p class="mb-1"><strong>No. Antrian:</strong></p>
                                <p><span class="badge badge-primary p-2" style="font-size: 1rem;">{{ $kunjungan->no_antrian }}</span></p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1"><strong>Tanggal Kunjungan:</strong></p>
                                <p>{{ $kunjungan->tanggal_kunjungan->format('d/m/Y') }}</p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1"><strong>Jam Masuk:</strong></p>
                                <p>{{ $kunjungan->jam_masuk ? substr($kunjungan->jam_masuk, 0, 5) : '-' }}</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <p class="mb-1"><strong>Keluhan:</strong></p>
                                <div class="alert alert-warning mb-0">{{ $kunjungan->keluhan }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Examination Form -->
                @if($kunjungan->status == 'selesai')
                    <!-- Read-only view for completed visits -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-success text-white">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-check-circle"></i> Hasil Pemeriksaan</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Diagnosa:</strong></p>
                                    <p>{{ $kunjungan->diagnosa ?? '-' }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Tindakan:</strong></p>
                                    <p>{{ $kunjungan->tindakan ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <p class="mb-1"><strong>Catatan Dokter:</strong></p>
                                    <p>{{ $kunjungan->catatan_dokter ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <p class="mb-1"><strong>Resep Obat:</strong></p>
                                    @if($kunjungan->resepObats->count())
                                        <ul class="list-group">
                                            @foreach($kunjungan->resepObats as $resep)
                                                <li class="list-group-item">{{ $resep->keterangan_obat }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="text-muted">Tidak ada resep obat.</p>
                                    @endif
                                </div>
                            </div>
                            <hr>
                            <p class="text-muted mb-0">
                                <strong>Jam Selesai:</strong> {{ $kunjungan->jam_selesai ? substr($kunjungan->jam_selesai, 0, 5) : '-' }} |
                                <strong>Dokter:</strong> -
                            </p>
                        </div>
                    </div>
                @else
                    <!-- Editable form for in-progress visits -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-primary text-white">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-file-medical"></i> Form Pemeriksaan</h6>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('dokter.selesai-periksa', $kunjungan->id) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label for="diagnosa"><strong>Diagnosa</strong> <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="diagnosa" name="diagnosa" rows="3" placeholder="Masukkan diagnosa..." required>{{ old('diagnosa', $kunjungan->diagnosa) }}</textarea>
                                </div>

                                <div class="form-group">
                                    <label for="tindakan"><strong>Tindakan Medis</strong></label>
                                    <textarea class="form-control" id="tindakan" name="tindakan" rows="2" placeholder="Masukkan tindakan medis...">{{ old('tindakan', $kunjungan->tindakan) }}</textarea>
                                </div>

                                <div class="form-group">
                                    <label for="catatan_dokter"><strong>Catatan Dokter</strong></label>
                                    <textarea class="form-control" id="catatan_dokter" name="catatan_dokter" rows="2" placeholder="Catatan tambahan...">{{ old('catatan_dokter', $kunjungan->catatan_dokter) }}</textarea>
                                </div>

                                <!-- Dynamic Obat Repeater -->
                                <div class="form-group">
                                    <label><strong>Resep Obat</strong></label>
                                    <div id="obat-container">
                                        <div class="input-group mb-2 obat-row">
                                            <input type="text" class="form-control" name="obat[]" placeholder="Nama obat / keterangan obat">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-danger btn-remove-obat"><i class="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-obat">
                                        <i class="fas fa-plus"></i> Tambah Obat
                                    </button>
                                </div>

                                <hr>
                                <div class="d-flex justify-content-between">
                                    <a href="{{ route('dokter.antrian') }}" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali
                                    </a>
                                    <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Selesaikan pemeriksaan pasien ini?')">
                                        <i class="fas fa-check-circle"></i> Selesai Periksa
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

            </div>
        </div>
@include('layout.footer')
</body>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add obat row
    document.getElementById('btn-add-obat').addEventListener('click', function() {
        var container = document.getElementById('obat-container');
        var newRow = document.createElement('div');
        newRow.className = 'input-group mb-2 obat-row';
        newRow.innerHTML = '<input type="text" class="form-control" name="obat[]" placeholder="Nama obat / keterangan obat">' +
            '<div class="input-group-append">' +
            '<button type="button" class="btn btn-danger btn-remove-obat"><i class="fas fa-times"></i></button>' +
            '</div>';
        container.appendChild(newRow);
    });

    // Remove obat row
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-obat')) {
            var row = e.target.closest('.obat-row');
            var container = document.getElementById('obat-container');
            if (container.children.length > 1) {
                row.remove();
            } else {
                row.querySelector('input').value = '';
            }
        }
    });
});
</script>
</html>
