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
                        <h1 class="h3 mb-0 text-gray-800">Edit Banner What's New</h1>
                        <p class="text-muted small mb-0">{{ $item->title }}</p>
                    </div>
                    <a href="{{ route('admin.whats-new.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0 pl-3">
                            @foreach ($errors->all() as $pesan)
                                <li>{{ $pesan }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Media yang sudah tersimpan ditampilkan apa adanya (bukan sekadar ikon):
                     admin harus melihat persis apa yang akan tampil di modal. --}}
                @php
                    $jenisMedia = $item->jenisMedia();
                    $sumberMedia = $item->mediaSrc();
                @endphp

                @if ($sumberMedia || $item->media_path || $item->media_url)
                    <div class="card shadow mb-3">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Media saat ini</h6>
                            <span class="badge badge-light text-uppercase">{{ $jenisMedia }}</span>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-5 mb-3 mb-md-0">
                                    @if ($sumberMedia)
                                        @if ($jenisMedia === 'gambar')
                                            <img src="{{ $sumberMedia }}" alt="{{ $item->title }}"
                                                 style="width:100%;max-height:200px;object-fit:cover;border-radius:8px;border:1px solid #e3e6f0;">
                                        @elseif ($jenisMedia === 'video' || $jenisMedia === 'video-url')
                                            <video src="{{ $sumberMedia }}" controls muted playsinline preload="metadata"
                                                   style="width:100%;max-height:200px;border-radius:8px;background:#101828;"></video>
                                        @elseif ($jenisMedia === 'youtube' || $jenisMedia === 'embed')
                                            <div class="embed-responsive embed-responsive-16by9" style="border-radius:8px;overflow:hidden;">
                                                <iframe class="embed-responsive-item" src="{{ $item->embedUrl() }}"
                                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                        allowfullscreen title="{{ $item->title }}"></iframe>
                                            </div>
                                        @endif
                                    @else
                                        {{-- Berkas fisik di disk belum ada/tersedia di path storage --}}
                                        <div class="d-flex align-items-center justify-content-center p-3 text-white rounded"
                                             style="background: #1e293b; min-height: 120px; border-radius: 8px;">
                                            <div class="text-center">
                                                <i class="fas fa-{{ $item->media_type === 'video' ? 'video' : 'image' }} fa-2x mb-2 text-info"></i>
                                                <div class="small font-weight-bold">{{ basename($item->media_path ?? $item->media_url) }}</div>
                                                <div class="small text-muted">Tersimpan di database</div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="col-md-7 small text-muted">
                                    @if ($item->media_path)
                                        <div class="mb-1">Berkas: <code>{{ basename($item->media_path) }}</code></div>
                                    @else
                                        <div class="mb-1 text-break">URL: <code>{{ $item->media_url }}</code></div>
                                    @endif
                                    <div class="text-success font-weight-bold mb-1">
                                        <i class="fas fa-check-circle"></i> Media ini tetap tersimpan saat Anda menyimpan perubahan.
                                    </div>
                                    <div class="text-muted">
                                        Biarkan kolom unggah kosong bila tidak ingin menggantinya.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Form Banner</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.whats-new.update', $item->id) }}" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            @include('whats_new._form', [
                                'item' => $item,
                                'aksi' => route('admin.whats-new.update', $item->id),
                                'metode' => 'PUT',
                                'ruteTersedia' => $ruteTersedia,
                            ])
                        </form>
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