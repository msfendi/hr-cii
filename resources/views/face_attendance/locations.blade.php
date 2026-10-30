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
                        <h1 class="h3 mb-0 text-gray-800">Kelola Lokasi Kerja</h1>
                        <a href="{{ route('face.attendance.map') }}" class="btn btn-sm btn-primary shadow-sm mt-2 mt-sm-0">
                            <i class="fas fa-map fa-sm text-white-50 mr-1"></i> Buka Peta &amp; Gambar Polygon
                        </a>
                    </div>

                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                        </div>
                    @endif

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Daftar Lokasi Kerja</h6>
                            <span class="badge badge-primary badge-pill">{{ $locations->count() }}</span>
                        </div>
                        <div class="card-body">
                            @if ($locations->isEmpty())
                                <div class="text-center py-5">
                                    <i class="fas fa-map-marker-alt fa-4x text-gray-300 mb-3"></i>
                                    <p class="text-muted mb-0">Belum ada lokasi kerja terdaftar.</p>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover mb-0" width="100%" cellspacing="0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width:50px;">#</th>
                                                <th>Nama Lokasi</th>
                                                <th>Keterangan</th>
                                                <th class="text-center">Titik Polygon</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-center" style="width:110px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($locations as $i => $loc)
                                                <tr>
                                                    <td>{{ $i + 1 }}</td>
                                                    <td class="font-weight-bold">{{ $loc->name }}</td>
                                                    <td class="small text-muted">{{ $loc->description ?: '-' }}</td>
                                                    <td class="text-center small">{{ $loc->point_count }} titik</td>
                                                    <td class="text-center">
                                                        <span class="badge {{ $loc->is_active ? 'badge-success' : 'badge-secondary' }}">
                                                            {{ $loc->is_active ? 'Aktif' : 'Tidak Aktif' }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                                                            data-id="{{ $loc->id }}"
                                                            data-name="{{ $loc->name }}"
                                                            data-description="{{ $loc->description }}"
                                                            data-active="{{ $loc->is_active ? 1 : 0 }}"
                                                            title="Edit">
                                                            <i class="fas fa-pen"></i>
                                                        </button>
                                                        <form action="{{ route('face.attendance.location.destroy', $loc->id) }}" method="POST" class="d-inline form-delete">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>
            </div>

            {{-- Modal edit --}}
            <div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Lokasi Kerja</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="editId">
                            <div class="form-group">
                                <label for="editName" class="font-weight-bold small">Nama lokasi <span class="text-danger">*</span></label>
                                <input type="text" id="editName" class="form-control" maxlength="100">
                            </div>
                            <div class="form-group">
                                <label for="editDesc" class="font-weight-bold small">Keterangan</label>
                                <textarea id="editDesc" class="form-control" rows="3" maxlength="255"></textarea>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="editActive">
                                <label class="custom-control-label" for="editActive">Aktif (dipakai untuk validasi absensi)</label>
                            </div>
                            <div class="small text-muted mt-3">Untuk mengubah bentuk polygon, hapus lokasi lalu gambar ulang di peta.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="button" class="btn btn-primary" id="btnSaveEdit"><i class="fas fa-save mr-1"></i> Simpan</button>
                        </div>
                    </div>
                </div>
            </div>

            @include('layout.footer')

<script>
    if (typeof Swal === 'undefined') {
        document.write('<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"><\/script>');
    }
</script>
<script>
    (function () {
        const storeUrl = @json(route('face.attendance.location.store'));
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        // Konfirmasi hapus
        document.querySelectorAll('.form-delete').forEach((form) => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                Swal.fire({
                    title: 'Hapus lokasi ini?',
                    text: 'Data lokasi akan dihapus permanen.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#e74a3b',
                    reverseButtons: true,
                }).then((r) => { if (r.isConfirmed) form.submit(); });
            });
        });

        // Buka modal edit
        document.querySelectorAll('.btn-edit').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('editId').value = btn.dataset.id;
                document.getElementById('editName').value = btn.dataset.name || '';
                document.getElementById('editDesc').value = btn.dataset.description || '';
                document.getElementById('editActive').checked = btn.dataset.active === '1';
                $('#modalEdit').modal('show');
            });
        });

        // Simpan edit
        document.getElementById('btnSaveEdit').addEventListener('click', async () => {
            const name = document.getElementById('editName').value.trim();
            if (!name) {
                Swal.fire('Peringatan', 'Nama lokasi wajib diisi.', 'warning');
                return;
            }

            try {
                const res = await fetch(storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        id: Number(document.getElementById('editId').value),
                        name: name,
                        description: document.getElementById('editDesc').value.trim(),
                        is_active: document.getElementById('editActive').checked,
                    }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Gagal menyimpan.');
                    Swal.fire('Gagal', msg, 'error');
                    return;
                }
                $('#modalEdit').modal('hide');
                await Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, timer: 1200, showConfirmButton: false });
                window.location.reload();
            } catch (err) {
                Swal.fire('Error', err.message || String(err), 'error');
            }
        });
    })();
</script>
</body>
</html>
