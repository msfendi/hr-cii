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
                                        <th class="text-center" style="width:18%;">Aksi</th>
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
                                                <button class="btn btn-sm btn-outline-info" title="Detail Peserta"
                                                    onclick="bukaModalPeserta({{ $event->id }}, '{{ addslashes($event->nama_event) }}')">
                                                    <i class="fas fa-users"></i>
                                                </button>
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

<!-- Modal Detail Peserta -->
<div class="modal fade" id="modalPeserta" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPesertaTitle">Detail Peserta</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body">
                <div id="pesertaLoading" class="text-center text-muted py-5">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                    <div>Memuat data peserta...</div>
                </div>

                <div id="pesertaContent" style="display:none;">
                    <div class="row mb-4">
                        <div class="col-md-7">
                            <div class="card shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="text-center text-gray-600 mb-3">% Kehadiran per Departemen</h6>
                                    <canvas id="chartPesertaDept" height="220"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="text-center text-gray-600 mb-3">Total Hadir vs Tidak Hadir</h6>
                                    <canvas id="chartPesertaTotal" height="220"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                        <div class="btn-group btn-group-sm mb-2" role="group" id="filterStatusPeserta">
                            <button type="button" class="btn btn-outline-secondary active" data-filter="">Semua</button>
                            <button type="button" class="btn btn-outline-success" data-filter="hadir">Hadir</button>
                            <button type="button" class="btn btn-outline-secondary" data-filter="tidak_hadir">Tidak Hadir</button>
                        </div>
                        <a href="#" id="btnExportPeserta" class="btn btn-sm btn-success mb-2">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="tablePeserta" width="100%">
                            <thead>
                                <tr>
                                    <th>NPK</th>
                                    <th>Nama</th>
                                    <th>Departemen</th>
                                    <th class="text-center">Status</th>
                                    <th>Ucapan</th>
                                    <th>Waktu Respon</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
</body>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
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

/* ------------------------------------------------------------ */
/*  Detail Peserta (hadir / tidak hadir) + chart + export         */
/* ------------------------------------------------------------ */

let tablePesertaDT = null;
let chartPesertaDept = null;
let chartPesertaTotal = null;
let statusFilterAktif = '';

function bukaModalPeserta(eventId, namaEvent){
    document.getElementById('modalPesertaTitle').textContent = `Detail Peserta - ${namaEvent}`;
    document.getElementById('btnExportPeserta').href = `{{ url('event-invitation/admin') }}/${eventId}/export`;

    document.getElementById('pesertaLoading').style.display = 'block';
    document.getElementById('pesertaContent').style.display = 'none';
    statusFilterAktif = '';

    $('#modalPeserta').modal('show');

    fetch(`{{ url('event-invitation/admin') }}/${eventId}/peserta`, {
        headers: { 'Accept': 'application/json' },
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('pesertaLoading').style.display = 'none';
        document.getElementById('pesertaContent').style.display = 'block';

        renderTablePeserta(data.peserta);

        try {
            renderChartPeserta(data.per_departemen);
        } catch (err) {
            console.error('Gagal render chart peserta:', err);
        }
    })
    .catch((err) => {
        console.error('Gagal memuat data peserta:', err);
        document.getElementById('pesertaLoading').style.display = 'none';
        $('#modalPeserta').modal('hide');
        Swal.fire({ icon: 'error', title: 'Gagal', text: 'Gagal memuat data peserta.' });
    });
}

/**
 * Pastikan plugin DataTables tersedia sebelum dipakai. Beberapa halaman
 * di app ini tidak me-load DataTables secara global, jadi kalau belum
 * ada kita muat dulu dari CDN baru jalankan callback.
 */
let dataTablesLoading = null;
function ensureDataTables(callback){
    if (typeof $.fn.DataTable === 'function') {
        callback();
        return;
    }

    if (dataTablesLoading) {
        dataTablesLoading.then(callback);
        return;
    }

    dataTablesLoading = new Promise((resolve) => {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css';
        document.head.appendChild(css);

        const script1 = document.createElement('script');
        script1.src = 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js';
        script1.onload = () => {
            const script2 = document.createElement('script');
            script2.src = 'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js';
            script2.onload = resolve;
            script2.onerror = () => { console.error('Gagal memuat dataTables.bootstrap4.min.js'); resolve(); };
            document.head.appendChild(script2);
        };
        script1.onerror = () => { console.error('Gagal memuat jquery.dataTables.min.js'); resolve(); };
        document.head.appendChild(script1);
    });

    dataTablesLoading.then(callback);
}

function renderTablePeserta(peserta){
    const tbody = document.querySelector('#tablePeserta tbody');
    tbody.innerHTML = '';

    peserta.forEach(p => {
        const statusBadge = p.status === 'hadir'
            ? '<span class="badge badge-success">Hadir</span>'
            : '<span class="badge badge-secondary">Tidak Hadir</span>';

        const waktu = p.responded_at
            ? new Date(p.responded_at.replace(' ', 'T')).toLocaleString('id-ID')
            : '-';

        const tr = document.createElement('tr');
        tr.setAttribute('data-status', p.status ?? '');
        tr.innerHTML = `
            <td>${p.npk ?? '-'}</td>
            <td>${p.nama ?? '-'}</td>
            <td>${p.departemen ?? '-'}</td>
            <td class="text-center">${statusBadge}</td>
            <td>${p.ucapan ?? '-'}</td>
            <td>${waktu}</td>
        `;
        tbody.appendChild(tr);
    });

    ensureDataTables(() => {
        try {
            if (tablePesertaDT) {
                tablePesertaDT.destroy();
                tablePesertaDT = null;
            }
            tablePesertaDT = $('#tablePeserta').DataTable({
                pageLength: 10,
                order: [[2, 'asc']],
                language: { search: 'Cari:', emptyTable: 'Belum ada peserta.', zeroRecords: 'Tidak ada data yang cocok.' },
            });
        } catch (err) {
            console.error('DataTables gagal diinisialisasi, tampil sebagai tabel biasa:', err);
            tablePesertaDT = null;
        }

        terapkanFilterStatus();
    });
}

document.querySelectorAll('#filterStatusPeserta button').forEach(btn => {
    btn.addEventListener('click', function(){
        document.querySelectorAll('#filterStatusPeserta button').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        statusFilterAktif = this.dataset.filter;
        terapkanFilterStatus();
    });
});

function terapkanFilterStatus(){
    if (tablePesertaDT) {
        if (window.__pesertaSearchFn) {
            const idx = $.fn.dataTable.ext.search.indexOf(window.__pesertaSearchFn);
            if (idx > -1) $.fn.dataTable.ext.search.splice(idx, 1);
        }

        window.__pesertaSearchFn = function(settings, data, dataIndex){
            if (!statusFilterAktif) return true;
            const row = tablePesertaDT.row(dataIndex).node();
            return row.getAttribute('data-status') === statusFilterAktif;
        };
        $.fn.dataTable.ext.search.push(window.__pesertaSearchFn);
        tablePesertaDT.draw();
        return;
    }

    // Fallback tanpa DataTables: filter manual dengan show/hide baris.
    document.querySelectorAll('#tablePeserta tbody tr').forEach(tr => {
        const status = tr.getAttribute('data-status');
        tr.style.display = (!statusFilterAktif || status === statusFilterAktif) ? '' : 'none';
    });
}

function renderChartPeserta(perDepartemen){
    const labels     = perDepartemen.map(d => d.departemen ?? '-');
    const dataHadir  = perDepartemen.map(d => d.persen_hadir);
    const dataTidak  = perDepartemen.map(d => d.persen_tidak_hadir);
    const totalHadir = perDepartemen.reduce((sum, d) => sum + Number(d.hadir), 0);
    const totalTidak = perDepartemen.reduce((sum, d) => sum + Number(d.tidak_hadir), 0);

    if (chartPesertaDept) chartPesertaDept.destroy();
    if (chartPesertaTotal) chartPesertaTotal.destroy();

    chartPesertaDept = new Chart(document.getElementById('chartPesertaDept'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: '% Hadir', data: dataHadir, backgroundColor: '#1cc88a' },
                { label: '% Tidak Hadir', data: dataTidak, backgroundColor: '#e74a3b' },
            ],
        },
        options: {
            responsive: true,
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } },
            },
            plugins: {
                tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.raw}%` } },
                legend: { position: 'bottom' },
            },
        },
    });

    chartPesertaTotal = new Chart(document.getElementById('chartPesertaTotal'), {
        type: 'doughnut',
        data: {
            labels: ['Hadir', 'Tidak Hadir'],
            datasets: [{ data: [totalHadir, totalTidak], backgroundColor: ['#1cc88a', '#e74a3b'] }],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
        },
    });
}
</script>
</html>