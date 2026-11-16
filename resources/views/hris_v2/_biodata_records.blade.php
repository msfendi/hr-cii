{{--
    Reusable list for biodata sections whose data comes from pelamar_details JSON
    (children, siblings, education, work experience).

    Required data:
      $judul   : string  — sub-heading text           (e.g. 'Children')
      $ikon    : string  — FontAwesome class          (e.g. 'fa-child')
      $data    : array   — decoded JSON from the controller
      $kosong  : string  — message when there is no data
      $namaKolom / $adalahDaftar : closures defined in employee_biodata.blade.php

    Two shapes are handled:
      * sequential list (list of records) -> one bordered item card per record,
        each with its own label/value grid;
      * associative map (a single record)  -> a plain label/value grid.
    Values that are themselves arrays are joined when they only contain
    scalars, otherwise skipped (same guard as the previous markup).
--}}
@php
    // Kunci yang dipakai sebagai judul item (agar tidak tampil dua kali).
    $kunciJudul = ['nama', 'nama_lengkap', 'institusi', 'sekolah', 'nama_sekolah',
                   'perusahaan', 'nama_perusahaan', 'jabatan', 'posisi', 'jenjang'];
    $tampil = function ($nilai) {
        if (is_array($nilai)) {
            $skalar = array_filter($nilai, fn ($v) => ! is_array($v) && $v !== null && $v !== '');
            return $skalar === [] ? null : implode(', ', $skalar);
        }

        return ($nilai === null || $nilai === '') ? null : $nilai;
    };
@endphp

<div class="bio-sub"><i class="fas {{ $ikon }}"></i>{{ $judul }}
    @if(is_array($data) && count($data) > 0)
        <span class="bio-count">{{ count($data) }}</span>
    @endif
</div>

@if(empty($data))
    <div class="bio-empty"><i class="fas fa-inbox"></i>{{ $kosong }}</div>
@elseif($adalahDaftar($data))
    <ol class="bio-rec">
        @foreach($data as $i => $baris)
            @php
                if (! is_array($baris)) {
                    $baris = ['value' => $baris];
                }
                // Judul item = nilai skalar pertama dari daftar prioritas.
                $judulItem = null;
                $kunciTerpakai = null;
                foreach ($kunciJudul as $kk) {
                    if (isset($baris[$kk]) && ! is_array($baris[$kk]) && $baris[$kk] !== '') {
                        $judulItem = $baris[$kk];
                        $kunciTerpakai = $kk;
                        break;
                    }
                }
                $barisTampil = [];
                foreach ($baris as $kunci => $nilai) {
                    if ($kunci === $kunciTerpakai) {
                        continue;
                    }
                    $teks = $tampil($nilai);
                    if ($teks !== null) {
                        $barisTampil[$kunci] = $teks;
                    }
                }
            @endphp
            <li class="bio-rec-item">
                <div class="bio-rec-head">
                    <span class="bio-rec-no">{{ $i + 1 }}</span>
                    <span class="bio-rec-name">{{ $judulItem ?: $judul . ' #' . ($i + 1) }}</span>
                </div>
                @if($barisTampil === [])
                    <div class="bio-footnote">No further details.</div>
                @else
                    <dl class="bio-kv">
                        @foreach($barisTampil as $kunci => $teks)
                            <div>
                                <dt>{{ $namaKolom($kunci) }}</dt>
                                <dd>{{ $teks }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </li>
        @endforeach
    </ol>
@else
    @php
        $barisMap = [];
        foreach ($data as $kunci => $nilai) {
            $teks = $tampil($nilai);
            if ($teks !== null) {
                $barisMap[$kunci] = $teks;
            }
        }
    @endphp
    @if($barisMap === [])
        <div class="bio-empty"><i class="fas fa-inbox"></i>{{ $kosong }}</div>
    @else
        <dl class="bio-kv">
            @foreach($barisMap as $kunci => $teks)
                <div>
                    <dt>{{ $namaKolom($kunci) }}</dt>
                    <dd>{{ $teks }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
@endif
