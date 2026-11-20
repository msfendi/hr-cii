{{-- Daftar banner What's New (HRIS v1 & v2). --}}
<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<body id="page-top">

<div id="wrapper">
    @include('layout.sidebar')

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @include('layout.navbar')

            <div class="container-fluid">

                <div class="d-sm-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h1 class="h3 mb-0 text-gray-800">What's New</h1>
                        <p class="text-muted small mb-0">
                            Banner tampil sebagai modal (dialog) di HRIS v1 dan HRIS v2 setelah karyawan login.
                        </p>
                    </div>
                    <a href="{{ route('admin.whats-new.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Tambah Banner
                    </a>
                </div>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0 pl-3">
                            @foreach ($errors->all() as $pesan)
                                <li>{{ $pesan }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Daftar Banner</h6>
                        <span class="badge badge-info">{{ $items->count() }} banner</span>
                    </div>

                    <div class="card-body">
                        @if ($items->isEmpty())
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-bullhorn fa-2x mb-2 d-block"></i>
                                Belum ada banner. Klik <strong>Tambah Banner</strong> untuk membuat yang pertama.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="46">Urut</th>
                                            <th width="120">Media</th>
                                            <th>Judul &amp; penjelasan</th>
                                            <th width="150">Tombol</th>
                                            <th width="160">Periode</th>
                                            <th width="95">Status</th>
                                            <th width="190">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($items as $item)
                                        <tr>
                                            <td class="text-center font-weight-bold">{{ $item->sort_order }}</td>
                                            <td>
                                                @php $jenis = $item->jenisMedia(); @endphp
                                                @if ($jenis === 'gambar')
                                                    <img src="{{ $item->mediaSrc() }}" alt="{{ $item->title }}"
                                                         style="width:104px;height:58px;object-fit:cover;border-radius:6px;border:1px solid #e3e6f0;">
                                                @elseif ($jenis === 'video' || $jenis === 'video-url')
                                                    <div style="width:104px;height:58px;border-radius:6px;background:#101828;color:#fff;display:flex;align-items:center;justify-content:center;">
                                                        <i class="fas fa-video"></i>
                                                    </div>
                                                @elseif ($jenis === 'youtube' || $jenis === 'embed')
                                                    <div style="width:104px;height:58px;border-radius:6px;background:#b91c1c;color:#fff;display:flex;align-items:center;justify-content:center;">
                                                        <i class="fab fa-youtube"></i>
                                                    </div>
                                                @else
                                                    <div class="text-muted small">tanpa media</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="font-weight-bold text-gray-800">{{ $item->title }}</div>
                                                @if ($item->summary)
                                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($item->summary, 120) }}</div>
                                                @endif
                                            </td>
                                            <td class="small">
                                                @if ($item->button_label)
                                                    <span class="badge badge-light border">{{ $item->button_label }}</span>
                                                    <div class="text-muted mt-1" style="word-break:break-all;">
                                                        {{ $item->button_route ? 'route: ' . $item->button_route : $item->button_url }}
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="small">
                                                @if ($item->starts_at || $item->ends_at)
                                                    {{ optional($item->starts_at)->format('d/m/Y H:i') ?? 'sekarang' }}
                                                    <br>s/d {{ optional($item->ends_at)->format('d/m/Y H:i') ?? 'seterusnya' }}
                                                @else
                                                    <span class="text-muted">selalu</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if ($item->is_active)
                                                    <span class="badge badge-success">Aktif</span>
                                                @else
                                                    <span class="badge badge-secondary">Nonaktif</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($item->punyaVideo())
                                                <button type="button"
                                                        class="btn btn-info btn-circle btn-sm btn-qr-banner"
                                                        data-qr-url="{{ route('admin.whats-new.qr', $item->id) }}"
                                                        data-watch-url="{{ route('whats-new.watch', $item->id) }}"
                                                        data-judul="{{ $item->title }}"
                                                        data-aktif="{{ $item->sedangAktif() ? '1' : '0' }}"
                                                        title="QR code video — dipindai untuk menonton di ponsel">
                                                    <i class="fas fa-qrcode"></i>
                                                </button>
                                                @endif

                                                <a href="{{ route('admin.whats-new.edit', $item->id) }}"
                                                   class="btn btn-primary btn-circle btn-sm" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <form method="POST" action="{{ route('admin.whats-new.toggle', $item->id) }}"
                                                      class="d-inline">
                                                    @csrf
                                                    <button type="submit"
                                                            class="btn btn-{{ $item->is_active ? 'warning' : 'success' }} btn-circle btn-sm"
                                                            title="{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                        <i class="fas fa-{{ $item->is_active ? 'pause' : 'play' }}"></i>
                                                    </button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.whats-new.destroy', $item->id) }}"
                                                      class="d-inline"
                                                      onsubmit="return confirm('Hapus banner {{ $item->title }}? Media yang diunggah ikut terhapus.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-circle btn-sm" title="Hapus">
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

                <div class="card border-left-info shadow mb-4">
                    <div class="card-body small text-muted">
                        <strong>Tips:</strong> nomor urut terbesar dianggap paling baru dan tampil lebih dulu
                        di modal karyawan. Kolom tombol opsional —
                        isi <em>route</em> (mis. <code>hris_v2.biodata</code>) atau URL luar. Banner
                        hanya muncul bila status <em>Aktif</em> dan berada dalam periode.
                        <div class="mt-2">
                            <i class="fas fa-qrcode text-info mr-1"></i>
                            Banner video punya tombol <strong>QR</strong>. QR mengarah ke halaman tonton
                            video yang bisa dibuka dari ponsel tanpa login; halaman itu otomatis menolak
                            bila banner <strong>dinonaktifkan</strong> atau periodenya sudah lewat.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Modal QR code video --}}
        <div class="modal fade" id="wnQrModal" tabindex="-1" role="dialog" aria-labelledby="wnQrJudul" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-info text-white py-2">
                        <h6 class="modal-title font-weight-bold" id="wnQrJudul">
                            <i class="fas fa-qrcode mr-1"></i> QR Video What's New
                        </h6>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body text-center">
                        <div class="font-weight-bold text-gray-800 mb-1" id="wnQrNama">-</div>
                        <p class="text-muted small mb-3">
                            Pindai QR ini dengan kamera ponsel untuk membuka videonya.
                        </p>

                        <div id="wnQrMuat" class="py-4 text-muted small" hidden>
                            <i class="fas fa-circle-notch fa-spin mr-1"></i> Menyiapkan QR...
                        </div>
                        <div id="wnQrGagal" class="alert alert-danger small py-2 px-3" hidden>
                            QR gagal dimuat. Coba tutup lalu buka lagi.
                        </div>

                        <div class="d-flex justify-content-center">
                            <img id="wnQrGambar" src="" alt="QR code video"
                                 class="border rounded bg-white p-2"
                                 style="width:240px;height:240px;object-fit:contain;display:none;">
                        </div>

                        <div id="wnQrPeringatan" class="alert alert-warning small text-left py-2 px-3 mt-3" hidden>
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Banner ini sedang <strong>tidak aktif</strong>. QR hanya bisa dibuka
                            setelah banner diaktifkan dan berada dalam periode tayang.
                        </div>

                        <div class="input-group input-group-sm mt-3">
                            <input type="text" id="wnQrTautan" class="form-control" readonly>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="wnQrSalin">
                                    <i class="fas fa-copy"></i> Salin
                                </button>
                            </div>
                        </div>

                        <a href="#" id="wnQrBuka" target="_blank" rel="noopener"
                           class="btn btn-outline-primary btn-sm mt-3">
                            <i class="fas fa-external-link-alt mr-1"></i> Buka halaman video
                        </a>
                    </div>
                    <div class="modal-footer py-2 justify-content-center">
                        <button type="button" class="btn btn-secondary btn-sm px-4 rounded-pill" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i> Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @include('layout.footer')
    </div>
</div>

@include('layout.footerscript')

{{-- QR dimuat saat modal dibuka, bukan saat halaman dirender: halaman daftar
     tetap ringan walau ada banyak banner video. --}}
<script>
    jQuery(function ($) {
        var $modal   = $('#wnQrModal');
        var $gambar  = $('#wnQrGambar');
        var $muat    = $('#wnQrMuat');
        var $gagal   = $('#wnQrGagal');
        var $tautan  = $('#wnQrTautan');
        var $buka    = $('#wnQrBuka');
        var $ingat   = $('#wnQrPeringatan');
        var qrSumber = '';

        $(document).on('click', '.btn-qr-banner', function () {
            var $btn    = $(this);
            qrSumber    = $btn.data('qr-url');
            var tonton  = $btn.data('watch-url');

            $('#wnQrNama').text($btn.data('judul') || '-');
            $tautan.val(tonton || '');
            $buka.attr('href', tonton || '#');
            $ingat.prop('hidden', String($btn.data('aktif')) === '1');

            // Kosongkan dulu supaya QR lama tidak sempat terlihat.
            $gambar.hide().attr('src', '');
            $gagal.prop('hidden', true);
            $muat.prop('hidden', false);

            $modal.modal('show');
        });

        // Foto/QR baru benar-benar diunduh pada saat modal tampil.
        $modal.on('show.bs.modal', function () {
            if (!qrSumber) { return; }
            $muat.prop('hidden', false);

            var img = new Image();
            img.onload = function () {
                $muat.prop('hidden', true);
                $gambar.attr('src', qrSumber).show();
            };
            img.onerror = function () {
                $muat.prop('hidden', true);
                $gagal.prop('hidden', false);
            };
            img.src = qrSumber;
        });

        // Lepaskan gambar saat modal ditutup agar memori tidak menumpuk.
        $modal.on('hidden.bs.modal', function () {
            $gambar.hide().attr('src', '');
        });

        $('#wnQrSalin').on('click', function () {
            var teks = $tautan.val();
            if (!teks) { return; }

            var sukses = function () {
                var $btn = $('#wnQrSalin');
                var asal = $btn.html();
                $btn.html('<i class="fas fa-check"></i> Tersalin');
                setTimeout(function () { $btn.html(asal); }, 1600);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(teks).then(sukses).catch(function () {});
            } else {
                $tautan.trigger('select');
                try { document.execCommand('copy'); sukses(); } catch (e) {}
            }
        });
    });
</script>
</body>
</html>