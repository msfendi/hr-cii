{{--
    ============================================================
    PARTIAL V31.3 (2026-11-04) — WAJAH ANIMASI LIVENESS
    ============================================================

    Dipakai bersama oleh:
      - resources/views/face_attendance/index.blade.php  (/face-attendance)
      - resources/views/face_attendance/login.blade.php  (/face-attendance/login)

    Tujuannya satu: menghapus duplikasi. Sebelumnya halaman login
    hanya punya teks tanpa animasi, sedangkan halaman presensi punya
    SVG tapi gerakannya SALAH (lihat catatan di bawah). Dengan satu
    partial, kedua halaman pasti tampil sama — tidak mungkin lagi
    yang satu diupdate dan yang lain tertinggal.

    APA YANG DIPERBAIKI DI V31.3
    ----------------------------
    1. BUG TUMPUKAN KATA (dilaporkan user lewat screenshot)
       Dulu teks petunjuk memakai dua <span> yang ditumpuk di SATU
       grid-area:

           .liveness-hint-bergantian {
               display: inline-grid;
               grid-template-areas: "tumpuk";   <-- keduanya di area sama
           }
           .liveness-hint-bergantian > span { grid-area: tumpuk; }

       Kedua span SELALU berada di posisi yang sama. Yang membedakan
       hanya opacity, dan opacity dianimasikan dengan dua delay
       berbeda (0s dan 4.2s). Akibatnya ada jendela waktu di mana
       keduanya opacity:1 -> teks saling menimpa dan jadi tidak
       terbaca ("atukukraengterbaksardisepekatkaninkasian" di
       screenshot). Selain itu, animasi opacity TIDAK berjalan saat
       elemen berada di dalam .alert Bootstrap yang punya animasi
       lain, sehingga di beberapa browser macet di kondisi tumpuk.

       Sekarang: SATU baris teks saja, tanpa tumpukan, tanpa animasi
       opacity. Tidak ada cara bagi dua teks untuk berimpit.

    2. ANIMASI BUKAN "MENOLEH" (dilaporkan user)
       Dulu kepala hanya di-rotate() 2D di bidang gambar:

           @keyframes kepalaMenoleh {
               28% { transform: rotate(-8deg) translateX(-1.6px); }
           }

       rotate() 2D memutar gambar seperti kertas yang dipelintir.
       Itu terlihat seperti kepala MIRING (telinga ikut naik-turun),
       bukan kepala MENOLEH (berputar pada sumbu leher).

       Menoleh sejati = rotasi terhadap sumbu Y (vertikal), sehingga:
         - sisi jauh wajah menyempit (terlihat lebih tipis)
         - sisi dekat wajah melebar
         - hidung bergeser ke arah hadap
         - mata ikut bergeser, telinga yang jauh tertutup sebagian
         - telinga TIDAK naik/turun

       Itu dicapai dengan rotateY() pada wadah berperspektif, bukan
       rotate(). Ditambah geser hidung/mata/pupil agar arah hadap
       terbaca, dan telinga jauh yang menyusut.

    KENAPA 3D CSS, BUKAN GAMBAR/VIDEO
    ---------------------------------
    - tidak ada request jaringan tambahan (halaman sudah berat:
      leaflet + face-api + sweetalert)
    - tidak ada aset biner yang harus ikut di-deploy
    - ukurannya beberapa KB teks saja
    - otomatis berhenti saat "prefers-reduced-motion"

    Parameter:
      $ukuran  (int)   lebar/tinggi SVG dalam piksel, default 56
      $teksAlat(bool)  true = sertakan atribut aria-hidden (default),
                       karena maknanya sudah ada di teks sebelahnya
--}}
@php
    // Nilai default supaya partial ini aman dipakai tanpa parameter.
    $ukuran    = $ukuran    ?? 56;
    $teksAlat  = $teksAlat  ?? true;
    // Dipakai untuk memberi id unik bila nanti perlu >1 wajah di satu
    // halaman. Saat ini selalu 1, tapi jangan sampai id bentrok.
    $idWajah   = 'lvFace' . substr(md5((string) $ukuran), 0, 6);
@endphp

<span class="lv-face-wrap" style="--lv-size: {{ (int) $ukuran }}px;">
    <svg class="lv-face-svg"
         viewBox="0 0 100 100"
         @if($teksAlat) aria-hidden="true" focusable="false" @else role="img" aria-label="Animasi wajah menoleh sebagai contoh gerakan" @endif>
        {{--
            PENTING: <g class="lv-persp"> memberi PERSPEKTIF. Tanpa
            perspective, rotateY() hanya menyempitkan gambar secara
            datar (seperti scaleX) dan tidak terlihat 3D. perspective
            membuat sisi yang menjauh terlihat lebih kecil — itulah
            yang membuat mata manusia membacanya sebagai "menoleh".
        --}}
        <g class="lv-persp">
            <g class="lv-head">
                {{-- telinga kiri: menyusut saat kepala menoleh ke kanan --}}
                <circle class="lv-ear lv-ear-kiri" cx="24" cy="52" r="5"
                        fill="#fff8e6" stroke="#c9a227" stroke-width="2.5"/>
                {{-- telinga kanan --}}
                <circle class="lv-ear lv-ear-kanan" cx="76" cy="52" r="5"
                        fill="#fff8e6" stroke="#c9a227" stroke-width="2.5"/>

                {{-- wajah --}}
                <ellipse cx="50" cy="50" rx="26" ry="31"
                         fill="#fff8e6" stroke="#c9a227" stroke-width="3"/>

                {{-- alis --}}
                <path d="M34 38 Q41 34 47 38" fill="none" stroke="#8a6d1f"
                      stroke-width="3" stroke-linecap="round"/>
                <path d="M53 38 Q59 34 66 38" fill="none" stroke="#8a6d1f"
                      stroke-width="3" stroke-linecap="round"/>

                {{--
                    Kelompok mata + hidung digeser bersama (lv-feat)
                    supaya arah hadap konsisten. Kalau hanya kepala
                    yang di-rotateY tanpa menggeser fitur wajah, hasilnya
                    terlihat seperti stiker yang diputar, bukan wajah.
                --}}
                <g class="lv-feat">
                    {{-- mata: putih --}}
                    <ellipse cx="40" cy="48" rx="7.5" ry="6" fill="#ffffff"
                             stroke="#8a6d1f" stroke-width="2"/>
                    <ellipse cx="60" cy="48" rx="7.5" ry="6" fill="#ffffff"
                             stroke="#8a6d1f" stroke-width="2"/>
                    {{-- pupil (ikut bergeser lebih jauh dari bola mata) --}}
                    <circle class="lv-pupil" cx="40" cy="48" r="3.2" fill="#3a3b45"/>
                    <circle class="lv-pupil" cx="60" cy="48" r="3.2" fill="#3a3b45"/>
                    {{-- hidung --}}
                    <path d="M50 52 L50 58 Q50 60 53 60" fill="none"
                          stroke="#8a6d1f" stroke-width="2.5" stroke-linecap="round"/>
                </g>

                {{-- mulut (tidak ikut bergeser sebanyak mata) --}}
                <path d="M42 68 Q50 73 58 68" fill="none" stroke="#8a6d1f"
                      stroke-width="3" stroke-linecap="round"/>

                {{--
                    Kelopak mata: menutup dari atas (scaleY 0 -> 1 ke bawah).
                    Diletakkan SETELAH mata supaya menutupi pupil.
                    transform-origin 50% 0% = menutup dari kelopak atas,
                    seperti kedipan sungguhan (bukan menyusut ke tengah).
                --}}
                <ellipse class="lv-lid" cx="40" cy="48" rx="7.6" ry="6.2" fill="#fff8e6"/>
                <ellipse class="lv-lid lv-lid-kanan" cx="60" cy="48" rx="7.6" ry="6.2" fill="#fff8e6"/>
            </g>
        </g>
    </svg>
</span>

<style>
    /* ==========================================================
       WAJAH LIVENESS — ANIMASI MENOLEH (3D) + BERKEDIP
       Nama kelas berawalan "lv-" supaya tidak bentrok dengan
       kelas Bootstrap atau kelas halaman lain.
       ========================================================== */

    .lv-face-wrap {
        display: inline-block;
        flex: 0 0 auto;
        width: var(--lv-size, 56px);
        height: var(--lv-size, 56px);
        line-height: 0;
        perspective: 320px;      /* kedalaman 3D; kecil = efek menoleh kuat */
        vertical-align: middle;
    }
    .lv-face-svg {
        width: 100%;
        height: 100%;
        overflow: visible;
        display: block;
    }

    .lv-persp {
        transform-style: preserve-3d;
    }

    /* Kepala: berputar pada sumbu Y (menoleh), BUKAN rotate() 2D */
    .lv-head {
        transform-box: view-box;
        transform-origin: 50% 50%;
        animation: lvMenoleh 5s ease-in-out infinite;
        will-change: transform;
    }
    @keyframes lvMenoleh {
        /* diam & hadap depan */
        0%, 8%    { transform: rotateY(0deg); }
        /* menoleh ke KANAN (wajah menghadap kanan layar) */
        22%, 34%  { transform: rotateY(24deg); }
        /* kembali ke depan */
        46%, 54%  { transform: rotateY(0deg); }
        /* menoleh ke KIRI */
        68%, 80%  { transform: rotateY(-24deg); }
        /* kembali & istirahat */
        92%, 100% { transform: rotateY(0deg); }
    }

    /* Mata+hidung/pupil bergeser searah hadap, sedikit lebih jauh
       dari rotasi kepala supaya arah hadap terbaca jelas.
       Nilai px di sini relatif terhadap viewBox 100 (SVG user unit). */
    .lv-feat {
        transform-box: view-box;
        transform-origin: 50% 50%;
        animation: lvFiturIkut 5s ease-in-out infinite;
        will-change: transform;
    }
    @keyframes lvFiturIkut {
        0%, 8%    { transform: translateX(0); }
        22%, 34%  { transform: translateX(2.6px); }
        46%, 54%  { transform: translateX(0); }
        68%, 80%  { transform: translateX(-2.6px); }
        92%, 100% { transform: translateX(0); }
    }

    .lv-pupil {
        animation: lvPupilIkut 5s ease-in-out infinite;
    }
    @keyframes lvPupilIkut {
        0%, 8%    { transform: translateX(0); }
        22%, 34%  { transform: translateX(1.4px); }
        46%, 54%  { transform: translateX(0); }
        68%, 80%  { transform: translateX(-1.4px); }
        92%, 100% { transform: translateX(0); }
    }

    /* Telinga jauh menyusut -> tanda kuat bahwa kepala berputar pada
       sumbu leher, bukan gambar yang digeser. */
    .lv-ear { transform-box: fill-box; transform-origin: center; }
    .lv-ear-kanan { animation: lvEarKanan 5s ease-in-out infinite; }
    .lv-ear-kiri  { animation: lvEarKiri  5s ease-in-out infinite; }
    @keyframes lvEarKanan {
        0%, 8%    { transform: scale(1); }
        22%, 34%  { transform: scale(0.72); }   /* menoleh kanan -> telinga kanan menjauh */
        46%, 54%  { transform: scale(1); }
        68%, 80%  { transform: scale(1.1); }    /* menoleh kiri -> telinga kanan mendekat */
        92%, 100% { transform: scale(1); }
    }
    @keyframes lvEarKiri {
        0%, 8%    { transform: scale(1); }
        22%, 34%  { transform: scale(1.1); }
        46%, 54%  { transform: scale(1); }
        68%, 80%  { transform: scale(0.72); }
        92%, 100% { transform: scale(1); }
    }

    /* Kedipan: kelopak turun & naik. 2 kali per siklus.
       PENTING (perbaikan V31.3d): .lv-lid adalah kelopak yang MENUTUP,
       jadi scaleY(1) = menutup, scaleY(0) = terbuka. Versi sebelumnya
       menulis kebalikannya sehingga mata tampak hampir selalu tertutup
       dan kedipannya tidak terlihat (hanya 3 frame yang terbuka). */
    .lv-lid {
        transform-box: fill-box;
        transform-origin: 50% 0%;
        animation: lvBerkedip 2.6s ease-in-out infinite;
    }
    .lv-lid-kanan { animation-delay: 0.03s; }
    @keyframes lvBerkedip {
        0%, 38%   { transform: scaleY(0); }     /* mata TERBUKA */
        44%       { transform: scaleY(1); }     /* menutup */
        50%       { transform: scaleY(0); }     /* terbuka lagi (kedip 1) */
        72%, 78%  { transform: scaleY(0); }     /* tetap terbuka */
        84%       { transform: scaleY(1); }     /* menutup (kedip 2) */
        90%, 100% { transform: scaleY(0); }     /* terbuka */
    }

    /* Aksesibilitas: bila user minta animasi dikurangi, hentikan.
       Wajah tetap tampil (tidak hilang), hanya diam. */
    @media (prefers-reduced-motion: reduce) {
        .lv-head, .lv-feat, .lv-pupil, .lv-ear, .lv-lid {
            animation: none !important;
        }
    }
</style>
