<!DOCTYPE html>
<html lang="id">
@include('layout.header')

{{-- PERBAIKAN (2026-11-03): SweetAlert2 dimuat di HEAD, sebelum blok
     script mana pun di halaman ini. Sebelumnya saveNext() memanggil
     Swal.fire(...) tetapi library-nya belum tentu tersedia, sehingga
     muncul:
         Uncaught ReferenceError: Swal is not defined
             at saveNext (card-menu-hris)
     Halaman ini memakai layout.header + @include('sweetalert::alert'),
     dan sweetalert::alert() hanya merender script notifikasi, BUKAN
     library Swal. Karena itu CDN dimuat eksplisit di sini. --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 24px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background-color: #cbd0e0;
        transition: .3s;
        border-radius: 24px;
    }
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0,0,0,.25);
    }
    .toggle-switch input:checked + .toggle-slider {
        background-color: #1cc88a;
    }
    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(22px);
    }
    .card-menu-icon {
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-size: 18px;
    }
    .card-menu-icon.bg-primary-soft { background: rgba(78,115,223,.12); color: #4e73df; }
    .card-menu-icon.bg-success-soft { background: rgba(28,200,138,.12); color: #1cc88a; }
    .card-menu-icon.bg-info-soft    { background: rgba(54,185,204,.12); color: #36b9cc; }
    .card-menu-icon.bg-warning-soft { background: rgba(246,194,62,.14); color: #f6c23e; }
    .card-menu-icon.bg-danger-soft  { background: rgba(231,76,60,.12); color: #e74a3b; }
    .card-menu-icon.text-muted      { background: rgba(133,141,164,.12); color: #858da0; }
</style>

<body id="page-top">
@include('sweetalert::alert')

<div id="wrapper">
    @if(auth()->check())
        @include('layout.sidebar')
    @endif

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @if(auth()->check())
                @include('layout.navbar')
            @endif

            <div class="container-fluid py-3">

                <!-- Header -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                        <i class="fas fa-th-large text-primary mr-2"></i> Pengaturan Card Menu HRIS
                    </h1>
                    <div class="mt-2 mt-sm-0">
                        <a href="{{ route('face.attendance.settings.index') }}" class="btn btn-sm btn-outline-secondary shadow-sm rounded-pill px-3 mr-1">
                            <i class="fas fa-cog mr-1"></i> Pengaturan Presensi
                        </a>
                        <a href="{{ url('/') }}" class="btn btn-sm btn-outline-primary shadow-sm rounded-pill px-3">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali ke HRIS
                        </a>
                    </div>
                </div>

                @php $st = $status ?? ['table_exists' => true, 'row_count' => 1, 'db_error' => null]; @endphp

                @if(!empty($st['db_error']))
                    <div class="alert alert-danger border-left-danger shadow-sm" role="alert">
                        <h6 class="font-weight-bold mb-1"><i class="fas fa-database mr-1"></i> Database tidak terjangkau</h6>
                        <div class="small mb-0">Toggle <strong>tidak akan tersimpan</strong>. {{ \Illuminate\Support\Str::limit($st['db_error'], 180) }}</div>
                    </div>
                @elseif(empty($st['table_exists']))
                    <div class="alert alert-danger border-left-danger shadow-sm" role="alert">
                        <h6 class="font-weight-bold mb-1"><i class="fas fa-table mr-1"></i> Tabel <code>card_menu_settings</code> belum ada</h6>
                        <div class="small mb-0">Jalankan <code>php artisan migrate --force</code> di server, lalu muat ulang halaman ini.</div>
                    </div>
                @elseif((int) ($st['row_count'] ?? 0) === 0)
                    <div class="alert alert-warning border-left-warning shadow-sm" role="alert">
                        <h6 class="font-weight-bold mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> Tabel pengaturan masih kosong</h6>
                        <div class="small mb-0">Selama tabel kosong, halaman HRIS memakai daftar bawaan sehingga toggle tidak berefek.
                            Jalankan <code>php artisan migrate --force</code> atau <code>php artisan card-menu:doctor --fix</code>.</div>
                    </div>
                @endif

                <div class="alert alert-info border-left-info shadow-sm" role="alert">
                    <i class="fas fa-info-circle mr-2"></i>
                    Aktifkan atau nonaktifkan card yang tampil di halaman utama HRIS.
                    Perubahan langsung berlaku pada halaman <a href="{{ url('/') }}" target="_blank">HRIS</a>
                    dan halaman <a href="{{ url('/') }}" target="_blank">Login</a>.
                </div>

                <!-- Card List -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-white d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list-ul mr-1"></i> Daftar Card Menu
                        </h6>
                        <span class="badge badge-primary px-3 py-1 font-weight-bold">
                            {{ count($cards) }} Card
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="tableCardMenu" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="5%" class="text-center">#</th>
                                        <th width="8%" class="text-center">Ikon</th>
                                        <th width="24%">Nama Card</th>
                                        <th width="28%">Route</th>
                                        <th width="10%" class="text-center">Urutan</th>
                                        <th width="15%" class="text-center">Status</th>
                                        <th width="10%" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $no = 1; @endphp
                                    @foreach($cards as $card)
                                    <tr>
                                        <td class="text-center font-weight-bold">{{ $no++ }}</td>
                                        <td class="text-center">
                                            <div class="card-menu-icon bg-{{ $card['color'] ?: 'primary' }}-soft mx-auto">
                                                <i class="{{ $card['icon'] ?: 'fas fa-circle' }}"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="font-weight-bold text-gray-900">{{ $card['name'] }}</span>
                                            <small class="text-muted d-block">Key: {{ $card['key'] }}</small>
                                        </td>
                                        <td>
                                            <code class="small">{{ $card['route'] ?: '-' }}</code>
                                        </td>
                                        <td class="text-center">{{ $card['order'] ?? '-' }}</td>
                                        <td class="text-center">
                                            <label class="toggle-switch" title="Tampilkan / Sembunyikan">
                                                <input type="checkbox" class="card-menu-toggle"
                                                       data-key="{{ $card['key'] }}"
                                                       data-name="{{ $card['name'] }}"
                                                       data-original="{{ ($card['is_visible'] ?? false) ? 'true' : 'false' }}"
                                                       {{ ($card['is_visible'] ?? false) ? 'checked' : '' }}>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </td>
                                        <td class="text-center">
                                            @if($card['is_visible'] ?? false)
                                                <span class="badge badge-success px-2 py-1"><i class="fas fa-eye mr-1"></i> Tampil</span>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1"><i class="fas fa-eye-slash mr-1"></i> Sembunyi</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Tombol Simpan: menyimpan semua perubahan toggle sekaligus --}}
                        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-3 border-top">
                            <div class="text-muted small mb-2 mb-md-0" id="cardMenuDirtyInfo">
                                <i class="fas fa-info-circle mr-1"></i>
                                <span id="cardMenuDirtyText">Belum ada perubahan.</span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-secondary mr-2" id="cardMenuResetBtn" disabled>
                                    <i class="fas fa-undo mr-1"></i> Batalkan Perubahan
                                </button>
                                <button type="button" class="btn btn-primary" id="cardMenuSaveBtn" disabled>
                                    <i class="fas fa-save mr-1"></i> Simpan Pengaturan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-white">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-eye mr-1"></i> Preview Card yang Sedang Ditampilkan
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row" id="previewCards">
                            @foreach($cards as $card)
                                @if($card['is_visible'] ?? false)
                                <div class="col-lg-3 col-md-4 col-6 mb-3" data-preview-key="{{ $card['key'] }}">
                                    <div class="card border-left-{{ $card['color'] ?: 'primary' }} shadow h-100">
                                        <div class="card-body text-center py-3">
                                            <div class="card-menu-icon bg-{{ $card['color'] ?: 'primary' }}-soft mx-auto mb-2">
                                                <i class="{{ $card['icon'] ?: 'fas fa-circle' }}"></i>
                                            </div>
                                            <h6 class="mb-0 small font-weight-bold">{{ $card['name'] }}</h6>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>{{-- /container-fluid --}}
        </div>{{-- /content --}}

{{-- PERBAIKAN: jQuery dimuat oleh layout.footer. Tanpa include ini,
     seluruh handler toggle tidak pernah terpasang ($ is not defined)
     sehingga status selalu "Belum ada perubahan" dan tombol simpan mati. --}}
@include('layout.footer')

<script>
    // Toggle show/hide card menu
    // ===== Perubahan toggle ditahan dulu, baru disimpan lewat tombol Simpan =====

    $(document).on('change', '.card-menu-toggle', function() {
        const $input = $(this);
        const key = $input.attr('data-key');
        const name = $input.attr('data-name');
        const visible = $input.is(':checked');

        updateDirtyState();
        updatePreview(key, visible, name, $input);
    });

    function collectChanges() {
        const changes = {};
        $('.card-menu-toggle').each(function() {
            const $t = $(this);
            const key = $t.attr('data-key');
            const now = $t.is(':checked');
            const orig = $t.attr('data-original') === 'true';
            if (now !== orig) {
                changes[key] = now ? 1 : 0;
            }
        });
        return changes;
    }

    function updateDirtyState() {
        const changes = collectChanges();
        const count = Object.keys(changes).length;
        const $info = $('#cardMenuDirtyText');
        const $save = $('#cardMenuSaveBtn');
        const $reset = $('#cardMenuResetBtn');

        if (count > 0) {
            $info.html('<strong class="text-warning">' + count + ' perubahan belum disimpan.</strong> Tekan "Simpan Pengaturan" untuk menerapkan.');
            $save.prop('disabled', false);
            $reset.prop('disabled', false);
        } else {
            $info.text('Belum ada perubahan.');
            $save.prop('disabled', true);
            $reset.prop('disabled', true);
        }
    }

    // Simpan semua perubahan sekaligus
    $(document).on('click', '#cardMenuSaveBtn', function() {
        const changes = collectChanges();
        const keys = Object.keys(changes);
        if (keys.length === 0) return;

        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

        let idx = 0;
        const failed = [];

        function saveNext() {
            if (idx >= keys.length) {
                $btn.html(originalHtml).prop('disabled', false);

                if (failed.length === 0) {
                    $('.card-menu-toggle').each(function() {
                        const $t = $(this);
                        $t.attr('data-original', String($t.is(':checked')));
                    });
                    Swal.fire({
                        icon: 'success',
                        title: 'Pengaturan Tersimpan',
                        text: 'Perubahan card menu HRIS berhasil diterapkan.',
                        timer: 1400,
                        showConfirmButton: false
                    }).then(function() {
                        window.location.reload();
                    });
                    setTimeout(function() { window.location.reload(); }, 1600);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Sebagian Gagal Disimpan',
                        html: 'Card berikut gagal disimpan:<br><strong>' + failed.join(', ') + '</strong><br><br>' +
                              'Silakan periksa koneksi database lalu coba lagi.'
                    });
                    updateDirtyState();
                }
                return;
            }

            const key = keys[idx];
            const visible = changes[key];
            const $input = $('.card-menu-toggle[data-key="' + key + '"]');
            const name = $input.attr('data-name') || key;

            $.ajax({
                url: "{{ route('card-menu-hris.update') }}",
                type: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { key: key, is_visible: visible },
                success: function(res) {
                    if (!res || !res.success) failed.push(name);
                    idx++;
                    saveNext();
                },
                error: function() {
                    failed.push(name);
                    idx++;
                    saveNext();
                }
            });
        }

        saveNext();
    });

    // Batalkan perubahan: kembalikan toggle ke kondisi tersimpan
    $(document).on('click', '#cardMenuResetBtn', function() {
        $('.card-menu-toggle').each(function() {
            const $t = $(this);
            const key = $t.attr('data-key');
            const orig = $t.attr('data-original') === 'true';
            $t.prop('checked', orig);
            updatePreview(key, orig, $t.attr('data-name'), $t);
        });
        updateDirtyState();
    });

    // Peringatkan bila halaman ditinggal dengan perubahan belum disimpan
    window.addEventListener('beforeunload', function(e) {
        if (Object.keys(collectChanges()).length > 0) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });

    // Simpan baseline awal setiap toggle agar bisa dideteksi perubahannya
    $(function() {
        $('.card-menu-toggle').each(function() {
            const $t = $(this);
            $t.attr('data-original', String($t.is(':checked')));
        });
        updateDirtyState();
    });

    function updatePreview(key, visible, name, $input) {
        const $preview = $('[data-preview-key="' + key + '"]');
        if (visible) {
            // PERBAIKAN: jangan location.reload() di sini. Reload membuang semua
            // perubahan toggle yang belum disimpan (status kembali "Belum ada perubahan").
            if ($preview.length > 0) {
                $preview.removeClass('d-none');
            }
            // Jika preview belum ada, cukup biarkan — preview akan lengkap
            // setelah tombol "Simpan Pengaturan" ditekan.
        } else {
            $preview.addClass('d-none');
        }

        // update badge di tabel
        const $row = $input.closest('tr');
        const $badge = $row.find('.badge');
        if (visible) {
            $badge.removeClass('badge-secondary').addClass('badge-success')
                  .html('<i class="fas fa-eye mr-1"></i> Tampil');
        } else {
            $badge.removeClass('badge-success').addClass('badge-secondary')
                  .html('<i class="fas fa-eye-slash mr-1"></i> Sembunyi');
        }
    }
</script>

{{-- Catatan: SweetAlert2 sudah dimuat di bagian HEAD halaman ini. --}}

<script>
    // Jaring pengaman: kalau CDN diblokir/gagal dimuat (offline, proxy,
    // atau CSP), sediakan implementasi cadangan supaya tombol Simpan
    // tidak pernah gagal diam-diam karena "Swal is not defined".
    if (typeof window.Swal === 'undefined') {
        window.Swal = {
            fire: function (opts) {
                opts = opts || {};
                var judul = opts.title || '';
                var pesan = opts.text || '';
                if (!pesan && opts.html) {
                    pesan = String(opts.html).replace(/<br\s*\/?>/gi, '\n')
                                              .replace(/<\/(strong|b|div|p)>/gi, '')
                                              .replace(/<[^>]+>/g, '');
                }
                var isError = opts.icon === 'error';
                if (isError) {
                    window.alert((judul ? judul + '\n\n' : '') + pesan);
                } else {
                    // Notifikasi sukses singkat: tidak perlu dialog.
                    console.log('[Simpan] ' + judul + (pesan ? ' - ' + pesan : ''));
                }
                return { then: function (cb) { if (typeof cb === 'function') cb(); return this; } };
            }
        };
    }
</script>

</body>
</html>
