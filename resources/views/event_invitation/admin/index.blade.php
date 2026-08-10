<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<meta name="csrf-token" content="{{ csrf_token() }}">

<body id="page-top">
@include('sweetalert::alert')
<div id="wrapper">
    @include('layout.sidebar')

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @include('layout.navbar')

            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-flag mr-1"></i> Manajemen Event
                    </h1>
                    <button class="btn btn-danger shadow-sm" data-toggle="modal" data-target="#modalEvent" onclick="bukaModalTambah()">
                        <i class="fas fa-plus fa-sm mr-1"></i> Tambah Event
                    </button>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%">
                                <thead>
                                    <tr>
                                        <th style="width:3%;">#</th>
                                        <th>Nama Event</th>
                                        <th>Tanggal &amp; Waktu</th>
                                        <th>Lokasi</th>
                                        <th>Blade</th>
                                        <th class="text-center">Hadir</th>
                                        <th class="text-center">Tidak Hadir</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center" style="width:16%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($events as $i => $event)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                <b>{{ $event->nama_event }}</b>
                                                @if ($event->dress_code)
                                                    <div class="small text-muted">Dress code: {{ $event->dress_code }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $event->tanggal_display }}
                                                <div class="small text-muted">{{ $event->waktu_event }}</div>
                                            </td>
                                            <td>{{ $event->lokasi_event }}</td>
                                            <td><code>{{ $event->view_folder }}</code></td>
                                            <td class="text-center"><span class="badge badge-success">{{ $event->jumlah_hadir }}</span></td>
                                            <td class="text-center"><span class="badge badge-secondary">{{ $event->jumlah_tidak_hadir }}</span></td>
                                            <td class="text-center">
                                                @if ($event->is_active)
                                                    <span class="badge badge-success">Aktif</span>
                                                @else
                                                    <span class="badge badge-danger">Nonaktif</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @unless ($event->is_active)
                                                    <button class="btn btn-sm btn-outline-success" title="Aktifkan"
                                                        onclick="aktifkanEvent({{ $event->id }}, '{{ addslashes($event->nama_event) }}')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                @endunless
                                                <button class="btn btn-sm btn-outline-primary" title="Edit"
                                                    onclick='bukaModalEdit(@json($event))'>
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" title="Hapus"
                                                    onclick="hapusEvent({{ $event->id }}, '{{ addslashes($event->nama_event) }}')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                Belum ada event. Klik "Tambah Event" untuk membuat yang pertama.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('layout.footer')
    </div>
</div>


<div class="modal fade" id="modalEvent" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="form-event">
                @csrf
                <input type="hidden" name="_method" id="form-method" value="POST">
                <input type="hidden" name="event_id" id="event_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalEventTitle">Tambah Event</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Event</label>
                        <input type="text" name="nama_event" id="nama_event" class="form-control" required
                            placeholder="Perayaan HUT Kemerdekaan RI ke-81">
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Tanggal Event</label>
                            <input type="date" name="tanggal_event" id="tanggal_event" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Waktu Event</label>
                            <input type="text" name="waktu_event" id="waktu_event" class="form-control" required
                                placeholder="08.00 WIB - Selesai">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Lokasi Event</label>
                        <input type="text" name="lokasi_event" id="lokasi_event" class="form-control" required
                            placeholder="Lapangan Utama PT. Chutex International Indonesia">
                    </div>

                    <div class="form-group">
                        <label>Dress Code <span class="text-muted small">(opsional)</span></label>
                        <input type="text" name="dress_code" id="dress_code" class="form-control"
                            placeholder="Merah Putih / Batik Nusantara">
                    </div>

                    <div class="form-group">
                        <label>Detail Event <span class="text-muted small">(opsional)</span></label>
                        <textarea name="detail_event" id="detail_event" class="form-control" rows="3"
                            placeholder="Informasi tambahan tentang acara..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Pakai Blade Yang Mana</label>
                        <select name="view_folder" id="view_folder" class="form-control" required>
                            <option value="">-- Pilih folder blade --</option>
                            @foreach ($bladeFolders as $folder)
                                <option value="{{ $folder }}">{{ $folder }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            Folder ini harus sudah ada di <code>resources/views/event_invitation/{folder}/</code>
                            dan berisi <code>scan.blade.php</code> &amp; <code>form.blade.php</code>.
                        </small>
                    </div>

                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" name="is_active" id="is_active" value="1">
                        <label class="custom-control-label" for="is_active">
                            Jadikan event aktif (dipakai di halaman scan/RSVP publik)
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" id="btn-simpan-event">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function bukaModalTambah(){
    document.getElementById('form-event').reset();
    document.getElementById('modalEventTitle').textContent = 'Tambah Event';
    document.getElementById('form-method').value = 'POST';
    document.getElementById('event_id').value = '';
}

function bukaModalEdit(event){
    document.getElementById('form-event').reset();
    document.getElementById('modalEventTitle').textContent = 'Edit Event';
    document.getElementById('form-method').value = 'PUT';
    document.getElementById('event_id').value = event.id;

    document.getElementById('nama_event').value = event.nama_event ?? '';
    document.getElementById('tanggal_event').value = (event.tanggal_event ?? '').substring(0, 10);
    document.getElementById('waktu_event').value = event.waktu_event ?? '';
    document.getElementById('lokasi_event').value = event.lokasi_event ?? '';
    document.getElementById('dress_code').value = event.dress_code ?? '';
    document.getElementById('detail_event').value = event.detail_event ?? '';
    document.getElementById('view_folder').value = event.view_folder ?? '';
    document.getElementById('is_active').checked = !!event.is_active;

    $('#modalEvent').modal('show');
}

document.getElementById('form-event').addEventListener('submit', function(e){
    e.preventDefault();

    const id = document.getElementById('event_id').value;
    const method = document.getElementById('form-method').value; // POST atau PUT
    const url = id
        ? "{{ url('event-invitation/admin') }}/" + id
        : "{{ route('event-invitation.admin.store') }}";

    const formData = new FormData(this);
    if (method === 'PUT') formData.append('_method', 'PUT');

    const btn = document.getElementById('btn-simpan-event');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...';

    fetch(url, {
        method: 'POST', // Laravel method-spoofing lewat _method
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: formData,
    })
    .then(res => res.json().then(data => ({ ok: res.ok, data })))
    .then(({ ok, data }) => {
        btn.disabled = false;
        btn.innerHTML = 'Simpan';

        if (!ok) {
            const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Terjadi kesalahan.');
            Swal.fire({ icon: 'error', title: 'Gagal', text: firstError });
            return;
        }

        $('#modalEvent').modal('hide');
        Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = 'Simpan';
        Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan jaringan.' });
    });
});

function aktifkanEvent(id, nama){
    Swal.fire({
        icon: 'question',
        title: `Aktifkan "${nama}"?`,
        text: 'Event lain otomatis dinonaktifkan.',
        showCancelButton: true,
        confirmButtonText: 'Ya, aktifkan',
        cancelButtonText: 'Batal',
    }).then(result => {
        if (!result.isConfirmed) return;

        fetch(`{{ url('event-invitation/admin') }}/${id}/activate`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        })
        .then(res => res.json())
        .then(data => {
            Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan jaringan.' }));
    });
}

function hapusEvent(id, nama){
    Swal.fire({
        icon: 'warning',
        title: `Hapus "${nama}"?`,
        text: 'Seluruh data RSVP untuk event ini akan ikut terhapus. Tindakan ini tidak bisa dibatalkan.',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#e74a3b',
    }).then(result => {
        if (!result.isConfirmed) return;

        fetch(`{{ url('event-invitation/admin') }}/${id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: (() => { const fd = new FormData(); fd.append('_method', 'DELETE'); return fd; })(),
        })
        .then(res => res.json())
        .then(data => {
            Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan jaringan.' }));
    });
}
</script>
</html>
