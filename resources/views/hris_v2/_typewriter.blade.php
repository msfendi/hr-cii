{{--
    Typing animation — dipakai ulang di halaman-halaman Portal HRIS v2.
    Perilaku, kecepatan, dan bentuk kursor sama dengan hero hris.blade.php
    (Halaman HRIS v1): type 55ms/karakter, hapus 28ms, tahan 2,2 dtk, jeda 0,5 dtk.
    Skripnya ada di layout (sekali), markup di sini cukup membawa data-kalimat.

    Pemakaian:
        @include('hris_v2._typewriter', ['kalimat' => ['First line', 'Second line']])
--}}
@php
    $kalimat = $kalimat ?? [
        'Chutex HRIS v2 — AI Integrated Platform',
        'Payslip, face attendance, and leave in a single portal',
        'Secure staff login with Face ID',
        'Automatically linked to company HRIS data',
    ];
@endphp
<div class="v2-type-text" aria-live="polite" aria-atomic="true" data-v2-type="{{ json_encode(array_values($kalimat)) }}">
    <span class="v2-type-inner"></span><span class="v2-type-caret" aria-hidden="true"></span>
</div>