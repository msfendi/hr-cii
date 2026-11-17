{{-- Floating button AI Assistant (permintaan 16 Nov 2026).
     Muncul di halaman yang memakai layout v2 dan mengarah ke /chatbot.
     Dibuka di tab baru supaya konteks HRIS yang sedang dibuka tidak hilang.

     Disembunyikan di halaman login v2: dari sana tombol ini hanya akan
     mengantar user ke /chatbot yang butuh login, terasa seperti lingkaran. --}}
@php
    $v2HalamanLogin = in_array(request()->route()?->getName(), ['v2.login', 'v2.employee.login'], true);
@endphp

@unless ($v2HalamanLogin)
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
    background: linear-gradient(135deg, rgba(29, 63, 134, .96), rgba(58, 111, 168, .96));
    border: 1px solid rgba(34, 211, 238, .45);
    box-shadow: 0 14px 34px rgba(15, 37, 87, .34), 0 0 0 1px rgba(255, 255, 255, .06) inset;
    text-decoration: none; color: #fff;
    transition: transform .18s ease, box-shadow .18s ease;
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
.v2-ai-fab-text strong { font-size: 13px; font-weight: 700; letter-spacing: .01em; }
.v2-ai-fab-text small { font-size: 10.5px; color: #cfe4ff; letter-spacing: .02em; }
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
@endunless