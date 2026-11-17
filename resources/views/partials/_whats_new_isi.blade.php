{{--
    Isi "What's New" — dipakai dua pembungkus:
      • partials/whats_new_slider.blade.php  (mode slider, HRIS v1)
      • partials/whats_new_slider.blade.php  (mode modal, HRIS v2)

    Variabel: $banners (Collection WhatsNewItem), $jumlah, $modal (bool).
    Id elemen (wn-track, wn-viewport, wn-posisi) hanya ada satu per halaman
    karena salah satu mode saja yang dirender.
--}}
<div class="wn-kepala">
    <span class="wn-badge"><i class="fas fa-bullhorn" aria-hidden="true"></i> What's New</span>
    <span class="wn-hitung" aria-hidden="true"><span id="wn-posisi">1</span>/{{ $jumlah }}</span>
    <button type="button" class="wn-tutup" data-wn-tutup
            aria-label="{{ $modal ? 'Tutup dialog What\'s New' : 'Tutup banner What\'s New' }}" title="Tutup">
        <i class="fas fa-times" aria-hidden="true"></i>
    </button>
</div>

<div class="wn-viewport" id="wn-viewport" tabindex="0" role="group"
     aria-roledescription="carousel" aria-label="Pengumuman terbaru">
    <div class="wn-track" id="wn-track">
        @foreach ($banners as $i => $banner)
            @php
                $jenis = $banner->jenisMedia();
                $sumber = $banner->mediaSrc();
                $embed = $banner->embedUrl();

                /* Autoplay: video harus bisu (muted) agar diizinkan browser.
                   Untuk iframe, src baru dipasang saat slide tampil supaya
                   pemutar tidak jalan di latar belakang. */
                $embedAuto = $embed
                    ? $embed . (str_contains($embed, '?') ? '&' : '?') . 'autoplay=1&mute=1&muted=1&playsinline=1&rel=0'
                    : null;
            @endphp
            <article class="wn-slide" role="group" aria-roledescription="slide"
                     aria-label="{{ $i + 1 }} dari {{ $jumlah }}" @if($i > 0) aria-hidden="true" @endif>
                <div class="wn-media wn-media--{{ $jenis }}">
                    @if ($jenis === 'gambar')
                        <img src="{{ $sumber }}" alt="{{ $banner->title }}" loading="lazy">
                    @elseif ($jenis === 'video' || $jenis === 'video-url')
                        <video class="wn-video" src="{{ $sumber }}" controls playsinline preload="metadata"
                               autoplay muted loop data-wn-video></video>
                    @elseif ($jenis === 'youtube' || $jenis === 'embed')
                        <iframe class="wn-embed" title="{{ $banner->title }}"
                                data-src="{{ $embedAuto }}"
                                @if (! $modal) src="{{ $embedAuto }}" @endif
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen loading="lazy"></iframe>
                    @else
                        <div class="wn-media-teks"><i class="fas fa-bullhorn" aria-hidden="true"></i></div>
                    @endif
                </div>

                <div class="wn-isi">
                    <h2 class="wn-judul">{{ $banner->title }}</h2>

                    @if ($banner->summary)
                        <p class="wn-teks">{{ $banner->summary }}</p>
                    @endif

                    @php $href = $banner->tombolHref(); @endphp
                    @if ($href && $banner->button_label)
                        <a class="wn-tombol" href="{{ $href }}"
                           @if ($banner->tombolEksternal()) target="_blank" rel="noopener noreferrer" @endif>
                            {{ $banner->button_label }}
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @if ($jumlah > 1)
        <button type="button" class="wn-panah wn-panah-kiri" data-wn-prev aria-label="Banner sebelumnya">
            <i class="fas fa-chevron-left" aria-hidden="true"></i>
        </button>
        <button type="button" class="wn-panah wn-panah-kanan" data-wn-next aria-label="Banner berikutnya">
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>
    @endif
</div>

@if ($jumlah > 1)
    <div class="wn-titik" role="tablist" aria-label="Pilih banner">
        @foreach ($banners as $i => $banner)
            <button type="button" class="wn-titik-item @if($i === 0) aktif @endif"
                    role="tab" data-wn-dot="{{ $i }}"
                    aria-label="Banner {{ $i + 1 }}: {{ \Illuminate\Support\Str::limit($banner->title, 40) }}"
                    aria-selected="{{ $i === 0 ? 'true' : 'false' }}"></button>
        @endforeach
    </div>
@endif