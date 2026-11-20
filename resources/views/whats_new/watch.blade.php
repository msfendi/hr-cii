{{-- Halaman tonton video What's New — target QR code.
     Berdiri sendiri (tanpa layout HRIS) karena dibuka dari ponsel hasil pindai
     QR, sering tanpa sesi login. Status akses ditentukan controller:
     banner nonaktif / di luar periode => 403 dengan kartu penjelasan. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $boleh ? $item->title . ' — ' : '' }}What's New Chutex</title>
    <link rel="icon" href="{{ asset('img/chutex.svg') }}" type="image/svg+xml">
    <style>
        :root {
            --biru-1: #1d3f86;
            --biru-2: #274b8f;
            --biru-3: #3a6fa8;
            --aksen: #22d3ee;
            --teks: #0f172a;
            --redup: #5b6b84;
            --garis: #e2e8f0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            padding: 22px 16px 40px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            background: radial-gradient(1200px 600px at 50% -10%, #e8f1ff 0%, #f4f6f9 45%, #eef1f6 100%);
            font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--teks);
        }
        .kartu {
            width: 100%;
            max-width: 860px;
            background: #fff;
            border: 1px solid var(--garis);
            border-radius: 18px;
            box-shadow: 0 18px 50px rgba(15, 37, 87, .13);
            overflow: hidden;
        }
        .kartu-kepala {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: linear-gradient(135deg, var(--biru-1), var(--biru-2) 55%, var(--biru-3));
            color: #fff;
        }
        .kartu-kepala img { height: 30px; width: auto; filter: brightness(0) invert(1); }
        .kartu-kepala .judul-kecil {
            font-size: 12px;
            letter-spacing: .12em;
            text-transform: uppercase;
            opacity: .85;
            margin-left: auto;
        }
        .isi { padding: 20px; }
        h1 { font-size: 20px; line-height: 1.35; margin: 0 0 8px; }
        .ringkas { color: var(--redup); font-size: 14px; line-height: 1.6; margin: 0 0 18px; }
        .pemutar {
            position: relative;
            width: 100%;
            background: #0b1220;
            border-radius: 14px;
            overflow: hidden;
            aspect-ratio: 16 / 9;
        }
        .pemutar video, .pemutar iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            object-fit: contain;
            background: #0b1220;
        }
        .catatan {
            margin: 16px 0 0;
            font-size: 12.5px;
            color: var(--redup);
            line-height: 1.6;
        }
        .tombol {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 18px;
            padding: 11px 20px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--biru-1), var(--biru-3));
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 10px 24px rgba(29, 63, 134, .28);
        }
        .tombol:hover { text-decoration: none; color: #fff; transform: translateY(-1px); }
        .kunci {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 18px;
            border: 1px solid #f3d2d2;
            background: #fdf4f4;
            border-radius: 14px;
        }
        .kunci-ikon {
            flex: 0 0 auto;
            width: 42px; height: 42px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: #fbe1e1; color: #b3261e;
            font-size: 18px; font-weight: 700;
        }
        .kunci strong { display: block; font-size: 15px; margin-bottom: 4px; }
        .kunci p { margin: 0; font-size: 13.5px; line-height: 1.6; color: #7a2b26; }
        @media (max-width: 560px) {
            body { padding: 14px 12px 28px; }
            .isi { padding: 16px; }
            h1 { font-size: 17px; }
        }
    </style>
</head>
<body>
    <div class="kartu">
        <div class="kartu-kepala">
            <img src="{{ asset('img/chutex.svg') }}" alt="Chutex">
            <span class="judul-kecil">What's New</span>
        </div>

        <div class="isi">
            @if ($boleh)
                <h1>{{ $item->title }}</h1>

                @if ($item->summary)
                    <p class="ringkas">{{ $item->summary }}</p>
                @endif

                @php $jenis = $item->jenisMedia(); @endphp

                <div class="pemutar">
                    @if ($jenis === 'video' || $jenis === 'video-url')
                        <video controls autoplay muted playsinline preload="metadata"
                               src="{{ $item->mediaSrc() }}"
                               poster="{{ asset('img/chutex_ai_assistant.png') }}">
                            Peramban Anda tidak mendukung pemutaran video.
                        </video>
                    @elseif ($jenis === 'youtube' || $jenis === 'embed')
                        <iframe src="{{ $item->embedUrl() }}"
                                title="{{ $item->title }}"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen></iframe>
                    @endif
                </div>

                @if ($item->button_label)
                    @php
                        // Route bisa saja sudah dihapus setelah banner dibuat;
                        // jangan sampai halaman publik ini error 500 karenanya.
                        $tautanTombol = $item->button_url;
                        if (! $tautanTombol && $item->button_route
                            && \Illuminate\Support\Facades\Route::has($item->button_route)) {
                            $tautanTombol = route($item->button_route);
                        }
                    @endphp
                    @if ($tautanTombol)
                        <a class="tombol" href="{{ $tautanTombol }}">{{ $item->button_label }}</a>
                    @endif
                @endif

                <p class="catatan">
                    Video ini bagian dari pengumuman What's New di HRIS Chutex.
                    Tautan ini berhenti bekerja otomatis begitu banner dinonaktifkan
                    atau periodenya berakhir.
                </p>
            @else
                <div class="kunci">
                    <div class="kunci-ikon">!</div>
                    <div>
                        <strong>Video tidak tersedia</strong>
                        <p>{{ $alasan }}</p>
                        <p style="margin-top:6px;">
                            Hubungi HRD bila Anda masih memerlukan informasinya.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>