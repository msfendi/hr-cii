{{-- Floating button AI Assistant — dipakai HRIS v1 (layouts/app) dan
     HRIS v2 (hris_v2/layout). Dibuka di tab baru supaya konteks HRIS yang
     sedang dibuka tidak hilang.

     Disembunyikan di halaman login: dari sana tombol ini hanya mengantar
     user ke /chatbot yang butuh login (lingkaran tanpa guna).
     Permintaan 17 Nov 2026: teks tombol TIDAK boleh bergaris bawah saat hover. --}}
@php
    // Disembunyikan di halaman login (tombol hanya mengantar ke /chatbot yang
    // butuh login) dan di halaman chatbot itu sendiri (tujuan tombol ini sudah
    // sedang dibuka — tombol jadi lingkaran tanpa guna).
    $fabSembunyi = in_array(request()->route()?->getName(), [
        'v2.login',
        'v2.employee.login',
        'chatbot.index',
    ], true);
@endphp

@unless ($fabSembunyi)
@once('v2-ai-fab-markup')
<a href="{{ url('/chatbot') }}" target="_blank" rel="noopener"
   class="v2-ai-fab" id="v2-ai-fab"
   aria-label="Buka AI Assistant Chutex" title="AI Assistant — tanya data HRIS">
    <span class="v2-ai-fab-ring" aria-hidden="true"></span>
    <img src="{{ asset('img/chutex_ai_assistant.png') }}" alt="" class="v2-ai-fab-photo">
    <span class="v2-ai-fab-text">
        <strong>AI Assistant</strong>
        <small>Tanya data HRIS</small>
    </span>
</a>

<style>
.v2-ai-fab {
    position: fixed; right: 22px; bottom: 22px; z-index: 1040;
    display: flex; align-items: center; gap: 10px;
    padding: 7px 16px 7px 7px;
    border-radius: 999px;
    background: linear-gradient(135deg, #17356f, #2b5c93);
    border: 1px solid rgba(34, 211, 238, .45);
    box-shadow: 0 14px 34px rgba(15, 37, 87, .34), 0 0 0 1px rgba(255, 255, 255, .06) inset;
    text-decoration: none !important;
    color: #fff;
    transition: transform .18s ease, box-shadow .18s ease;
}
/* Tombol ini berbentuk kapsul; garis bawah pada teks terbaca sebagai
   kerusakan visual, jadi dimatikan untuk semua keadaan. */
.v2-ai-fab:hover, .v2-ai-fab:focus, .v2-ai-fab:active,
.v2-ai-fab *:hover, .v2-ai-fab:hover strong, .v2-ai-fab:hover small {
    text-decoration: none !important;
}
.v2-ai-fab:hover, .v2-ai-fab:focus-visible {
    transform: translateY(-2px);
    box-shadow: 0 18px 40px rgba(15, 37, 87, .42);
    color: #fff;
}
.v2-ai-fab-photo {
    width: 42px; height: 42px; border-radius: 50%;
    object-fit: cover; display: block;
    border: 2px solid rgba(255, 255, 255, .85);
    background: #e8effb;
}
.v2-ai-fab-text { display: flex; flex-direction: column; line-height: 1.15; }
/* Warna teks dikunci eksplisit: di halaman HRIS v1 (AdminLTE) warna <strong>
   dan <small> bisa diwarisi dari tema halaman sehingga tulisan "AI Assistant"
   nyaris tak terlihat di atas kapsul biru. */
.v2-ai-fab .v2-ai-fab-text strong {
    font-size: 13px; font-weight: 700; letter-spacing: .01em;
    color: #ffffff !important;
    text-shadow: 0 1px 2px rgba(6, 14, 32, .45);
}
.v2-ai-fab .v2-ai-fab-text small {
    font-size: 10.5px; letter-spacing: .02em;
    color: #d5e7ff !important;
    text-shadow: 0 1px 2px rgba(6, 14, 32, .35);
}
.v2-ai-fab-ring {
    position: absolute; inset: -4px; border-radius: 999px;
    border: 1px solid rgba(34, 211, 238, .35);
    animation: v2-ai-pulse 2.8s ease-out infinite;
    pointer-events: none;
}
@keyframes v2-ai-pulse {
    0%   { transform: scale(.94); opacity: .85; }
    70%  { transform: scale(1.06); opacity: 0; }
    100% { transform: scale(1.06); opacity: 0; }
}
@media (max-width: 640px) {
    .v2-ai-fab { right: 14px; bottom: 14px; padding: 6px; gap: 0; }
    .v2-ai-fab-text { display: none; }
    .v2-ai-fab-photo { width: 46px; height: 46px; }
}
@media (prefers-reduced-motion: reduce) {
    .v2-ai-fab-ring { animation: none; opacity: .35; }
}
</style>
@endonce
@endunless