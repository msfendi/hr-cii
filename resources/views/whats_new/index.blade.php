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
                                            <th width="150">Aksi</th>
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
                        <strong>Tips:</strong> urutan kecil tampil lebih dulu. Kolom tombol opsional —
                        isi <em>route</em> (mis. <code>hris_v2.biodata</code>) atau URL luar. Banner
                        hanya muncul bila status <em>Aktif</em> dan berada dalam periode.
                    </div>
                </div>

            </div>
        </div>

        @include('layout.footer')
    </div>
</div>

@include('layout.footerscript')
</body>
</html>