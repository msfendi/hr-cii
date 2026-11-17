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

                @if ($item->media_path)
                    <div class="card shadow mb-3">
                        <div class="card-body d-flex align-items-center">
                            <div class="mr-3">
                                @if ($item->jenisMedia() === 'gambar')
                                    <img src="{{ $item->mediaSrc() }}" alt="{{ $item->title }}"
                                         style="width:160px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #e3e6f0;">
                                @else
                                    <div style="width:160px;height:90px;border-radius:8px;background:#101828;color:#fff;display:flex;align-items:center;justify-content:center;">
                                        <i class="fas fa-video fa-2x"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="small text-muted">
                                Berkas media saat ini: <code>{{ $item->media_path }}</code>
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