{{--
    Tabel data jawaban AI (R35).

    Dirender SERVER dari baris hasil query yang sama dengan grafik, bukan dari
    teks jawaban model. Tujuannya: angka tabel dan angka grafik tidak mungkin
    berbeda lagi (dulu model menulis tabelnya sendiri sehingga 4 baris gaji
    5.000.000 tampil di grafik tetapi tidak ada di tabel).

    Parameter:
    - $table : array{kolom: [{key,label,uang}], baris: [[sel,...]], jumlah: int}
    - $chart : meta grafik (opsional) untuk keterangan jumlah baris yang dipakai
--}}
@php
    $tb = $table ?? null;
    $ch = $chart ?? null;
@endphp

@if (! empty($tb['kolom']) && ! empty($tb['baris']))
    <p class="message-data-caption">
        <i class="fas fa-table"></i>
        <span>
            Tabel data &middot; {{ $tb['jumlah'] ?? count($tb['baris']) }} baris
            @if (! empty($ch['bar_count']))
                &middot; grafik memakai {{ $ch['bar_count'] }} baris pertama
            @endif
        </span>
    </p>
    <div class="cw-table-wrap">
        <table class="md-table">
            <thead>
                <tr>
                    @foreach ($tb['kolom'] as $kolom)
                        <th @class(['angka' => ! empty($kolom['uang'])])>{{ $kolom['label'] ?? $kolom['key'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($tb['baris'] as $baris)
                    <tr>
                        @foreach ($baris as $indeks => $sel)
                            <td @class(['angka' => ! empty($tb['kolom'][$indeks]['uang'])])>{{ $sel }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
