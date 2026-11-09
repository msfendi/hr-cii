<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body>
@include('sweetalert::alert')

{{-- DataTables untuk response tabel AI --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">

@php
    $exportLabels = [
        'xlsx'  => ['icon' => 'fa-file-excel',      'name' => 'Excel'],
        'csv'   => ['icon' => 'fa-file-csv',         'name' => 'CSV'],
        'pdf'   => ['icon' => 'fa-file-pdf',         'name' => 'PDF'],
        'image' => ['icon' => 'fa-file-image',       'name' => 'Gambar'],
        'json'  => ['icon' => 'fa-file-code',        'name' => 'JSON'],
        'txt'   => ['icon' => 'fa-file-alt',         'name' => 'Text'],
    ];
    $aiProvider = config('chatbot.default_provider', 'router9');
@endphp

{{-- Data bridge untuk JS --}}
<div id="chatbot-app" class="d-none"
     data-store-url="{{ route('chatbot.sessions.store') }}"
     data-index-url="{{ route('chatbot.index') }}"
     data-send-url-template="{{ url('chatbot/sessions/__ID__/send') }}"
     data-active-session="{{ $activeSession?->id ?? '' }}"
     data-ai-provider="{{ $aiProvider }}"
></div>

<div class="chat-layout">

    {{-- ======== SIDEBAR KIRI ======== --}}
    <aside class="chat-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-robot"></i>
                <span>AI Chat</span>
            </div>
            <button id="btn-new-session" class="btn-new-session" title="Chat Baru">
                <i class="fas fa-plus"></i>
                <span>Chat Baru</span>
            </button>
        </div>

        <div class="sidebar-sessions">
            @forelse ($sessions as $sess)
                <a href="{{ route('chatbot.index') }}?session={{ $sess->id }}"
                   class="session-item {{ $activeSession && $activeSession->id === $sess->id ? 'session-item--active' : '' }}">
                    <div class="session-item-icon">
                        <i class="fas fa-comment-dots"></i>
                    </div>
                    <div class="session-item-info">
                        <span class="session-item-title">{{ $sess->title ?? 'Chat ' . $sess->id }}</span>
                        <span class="session-item-date">{{ $sess->created_at->format('d M, H:i') }}</span>
                    </div>
                </a>
            @empty
                <div class="sidebar-empty">
                    <i class="fas fa-comment-slash"></i>
                    <p>Belum ada chat</p>
                </div>
            @endforelse
        </div>
    </aside>

    {{-- ======== MAIN CHAT AREA ======== --}}
    <div class="chat-main">

        {{-- Header --}}
        <header class="chat-header">
            <div class="chat-header-left">
                <div class="chat-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="chat-title-block">
                    <h1 class="chat-title">AI Assistant</h1>
                    <p class="chat-subtitle">Chutex International Indonesia</p>
                </div>
            </div>
            <div class="chat-header-right">
                <span class="badge-ai">AI Powered</span>
                <a href="{{ url('/') }}" class="btn-back-home" title="Kembali ke Beranda">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali ke Beranda</span>
                </a>
            </div>
        </header>

        {{-- Area pesan --}}
        <main class="chat-window" id="chat-messages">
            @if ($activeSession)
                @forelse ($activeSession->messages as $message)
                    @php
                        $isUser = $message->role === 'user';
                        $meta = $message->meta ?? [];
                        $export = $meta['export'] ?? null;

                        if (! $isUser) {
                            $renderedContent = Illuminate\Support\Str::markdown($message->content ?? '', [
                                'html_input' => 'strip',
                                'allow_unsafe_links' => false,
                            ]);
                            $renderedContent = str_replace('<table>', '<div class="cw-table-wrap"><table>', $renderedContent);
                            $renderedContent = str_replace('</table>', '</table></div>', $renderedContent);
                        }
                    @endphp

                    <div class="message-row {{ $isUser ? 'message-row--user' : 'message-row--ai' }}">
                        <div class="message-avatar {{ $isUser ? 'message-avatar--user' : 'message-avatar--ai' }}">
                            <i class="fas {{ $isUser ? 'fa-user' : 'fa-robot' }}"></i>
                        </div>

                        <div class="message-bubble-col">
                            <div class="message-bubble {{ $isUser ? 'message-bubble--user' : 'message-bubble--ai' }}">
                                @if ($isUser)
                                    @if (trim((string) $message->content) !== '')
                                        <div class="message-plain">{{ $message->content }}</div>
                                    @endif
                                    @if (! empty($meta['attachments']))
                                        <div class="message-attachments">
                                            @foreach ($meta['attachments'] as $att)
                                                @if (($att['kind'] ?? null) === 'image')
                                                    <a href="{{ $att['url'] }}" target="_blank" rel="noopener" class="message-attach-image">
                                                        <img src="{{ $att['url'] }}" alt="{{ $att['original_name'] }}">
                                                    </a>
                                                @else
                                                    <a href="{{ $att['url'] }}" target="_blank" rel="noopener" class="message-attach-file">
                                                        <i class="fas {{ match($att['kind'] ?? 'document') { 'video' => 'fa-file-video', 'audio' => 'fa-file-audio', default => 'fa-file-lines' } }}"></i>
                                                        <span class="message-attach-file-name">{{ $att['original_name'] }}</span>
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <div class="message-markdown">
                                        {!! $renderedContent !!}
                                    </div>
                                @endif

                                @if (! empty($export) && $export['type'] === 'html_table')
                                    <p class="message-data-caption">
                                        <i class="fas fa-table"></i>
                                        <span>Hasil data &middot; {{ $export['row_count'] ?? 0 }} baris</span>
                                    </p>
                                    {!! $export['html'] !!}
                                @elseif (! empty($export))
                                    @php $label = $exportLabels[$export['type']] ?? ['icon' => 'fa-file', 'name' => strtoupper($export['type'])]; @endphp
                                    <a href="{{ $export['url'] }}" target="_blank" rel="noopener" class="message-export">
                                        <span class="message-export-icon message-export-icon--{{ $export['type'] }}">
                                            <i class="fas {{ $label['icon'] }}"></i>
                                        </span>
                                        <span class="message-export-info">
                                            <span class="message-export-name">{{ $export['filename'] }}</span>
                                            <span class="message-export-meta">
                                                {{ $label['name'] }}
                                                @if (! empty($export['row_count']))
                                                    &middot; {{ $export['row_count'] }} baris
                                                @endif
                                            </span>
                                        </span>
                                        <i class="fas fa-download message-export-download"></i>
                                    </a>
                                @endif

                                @if (! empty($meta['sql']))
                                    <details class="message-details">
                                        <summary>Lihat query yang dijalankan</summary>
                                        <pre>{{ $meta['sql'] }}</pre>
                                    </details>
                                @endif

                                @if (! empty($meta['error']))
                                    <p class="message-error">
                                        <i class="fas fa-triangle-exclamation"></i>
                                        <span>Catatan: {{ $meta['error'] }}</span>
                                    </p>
                                @endif
                            </div>

                            @if (! empty($message->created_at))
                                <span class="message-time">{{ $message->created_at->format('H:i') }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-comments"></i></div>
                        <h3>Belum ada percakapan</h3>
                        <p>Mulai ketik pesan pertama Anda di bawah</p>
                    </div>
                @endforelse
            @else
                <div class="welcome-state">
                    <div class="welcome-icon"><i class="fas fa-robot"></i></div>
                    <h2>AI Assistant</h2>
                    <p class="welcome-desc">Tanya apa saja seputar data di sistem ini. AI akan membaca data (read-only) dan langsung menjawab pertanyaan Anda &mdash; hasilnya bisa diunduh saat relevan.</p>

                    <div class="welcome-features">
                        <div class="feature-item"><i class="fas fa-check text-success"></i> Tanya jawab data secara natural</div>
                        <div class="feature-item"><i class="fas fa-check text-success"></i> Data hanya dibaca (read-only), aman dieksplorasi</div>
                        <div class="feature-item"><i class="fas fa-check text-success"></i> Bisa lampirkan file (gambar, dokumen, dll)</div>
                        <div class="feature-item"><i class="fas fa-check text-success"></i> AI bisa saja salah, tolong double check response</div>
                    </div>

                    <button id="btn-start-chat" type="button" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane mr-2"></i> Mulai Chat Baru
                    </button>
                    <p id="new-session-error" class="d-none mt-3 text-danger small"></p>
                </div>
            @endif
        </main>

        {{-- Composer input --}}
        @if ($activeSession)
        <footer class="chat-composer">
            <div id="attach-list" class="attach-list d-none"></div>
            <p id="attach-error" class="d-none mb-2 text-danger small"></p>
            <form id="chat-form" class="composer-form" action="javascript:void(0)" method="post" onsubmit="return false;">
                @csrf
                <input type="file" id="attach-input" class="d-none" multiple
                       accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt,.ods,.odp,.mp4,.mov,.avi,.mkv,.webm,.mp3,.wav,.ogg">
                <button id="btn-attach" type="button" class="btn-attach" title="Lampirkan file">
                    <i class="fas fa-paperclip"></i>
                </button>
                <textarea id="chat-input" rows="1"
                          placeholder="Tulis pesan atau lampirkan file... (Enter untuk kirim, Shift+Enter untuk baris baru)"
                          class="form-control chat-textarea"></textarea>
                <button id="btn-send" type="submit" class="btn-send">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
            <p id="chat-typing" class="d-none typing-indicator">
                <span class="typing-dots"><span></span><span></span><span></span></span>
                AI sedang mengetik...
            </p>
            <p class="composer-hint">
                AI bisa saja salah, tolong double check response. Bisa juga tarik &amp; lepas file ke area chat.
                Format: gambar, dokumen (doc/xlsx/ppt/pdf/txt/csv), audio, video &middot; maks 5 file, 25MB/file.
            </p>

            <div id="drop-overlay" class="drop-overlay d-none">
                <div class="drop-overlay-inner">
                    <i class="fas fa-cloud-arrow-up"></i>
                    <span>Lepas file di sini untuk melampirkan</span>
                </div>
            </div>
        </footer>
        @endif

    </div>{{-- .chat-main --}}
</div>{{-- .chat-layout --}}

<style>
/* ============================================
   CHATBOT REDESIGN — Sidebar + Main
   ============================================ */

*, *::before, *::after { box-sizing: border-box; }

body {
    margin: 0;
    padding: 0;
    background: #f1f5f9 !important;
    min-height: 100vh;
    overflow: hidden; /* prevent double scrollbar */
}

/* Root layout */
.chat-layout {
    display: flex;
    height: 100vh;
    overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

/* ---- SIDEBAR ---- */
.chat-sidebar {
    width: 260px;
    min-width: 260px;
    background: #1e293b;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-right: 1px solid #0f172a;
}

.sidebar-header {
    padding: 18px 16px 14px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #fff;
    font-size: 16px;
    font-weight: 700;
}
.sidebar-logo i { color: #60a5fa; font-size: 18px; }

.btn-new-session {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 9px 14px;
    background: #2563eb;
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease;
}
.btn-new-session:hover { background: #1d4ed8; }
.btn-new-session i { font-size: 13px; }

.sidebar-sessions {
    flex: 1;
    overflow-y: auto;
    padding: 10px 8px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.sidebar-sessions::-webkit-scrollbar { width: 4px; }
.sidebar-sessions::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 2px; }

.session-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 10px;
    border-radius: 8px;
    color: #94a3b8;
    text-decoration: none;
    transition: all 0.12s ease;
    min-width: 0;
}
.session-item:hover { background: rgba(255,255,255,0.07); color: #e2e8f0; text-decoration: none; }
.session-item--active { background: rgba(37,99,235,0.3); color: #93c5fd !important; }
.session-item--active .session-item-icon { color: #60a5fa; }

.session-item-icon { flex-shrink: 0; font-size: 14px; }
.session-item-info { display: flex; flex-direction: column; min-width: 0; }
.session-item-title {
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #e2e8f0;
}
.session-item--active .session-item-title { color: #93c5fd; }
.session-item-date { font-size: 11px; color: #64748b; margin-top: 1px; }

.sidebar-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 32px 16px;
    color: #475569;
    font-size: 13px;
    text-align: center;
}
.sidebar-empty i { font-size: 22px; }

/* ---- MAIN CHAT ---- */
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #fff;
    min-width: 0;
}

/* ---- HEADER ---- */
.chat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}
.chat-header-left { display: flex; align-items: center; gap: 12px; }
.chat-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, #1e3a8a, #2563eb);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 17px;
    box-shadow: 0 2px 8px rgba(37,99,235,0.3);
}
.chat-title { margin: 0; font-size: 17px; font-weight: 700; color: #1e293b; }
.chat-subtitle { margin: 2px 0 0; font-size: 11.5px; color: #64748b; }

.chat-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.badge-ai {
    background: #eff6ff;
    color: #2563eb;
    padding: 5px 11px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 600;
    border: 1px solid #bfdbfe;
    white-space: nowrap;
}

/* Tombol kembali di header — inline, bukan fixed */
.btn-back-home {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 14px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #374151;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    white-space: nowrap;
    box-shadow: 0 1px 4px rgba(15,23,42,0.07);
    transition: all 0.15s ease;
}
.btn-back-home:hover {
    background: #f8fafc;
    border-color: #93c5fd;
    color: #2563eb;
    text-decoration: none;
}
.btn-back-home i { font-size: 12px; }

/* ---- CHAT WINDOW ---- */
.chat-window {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    background: #f8fafc;
}
.chat-window::-webkit-scrollbar { width: 6px; }
.chat-window::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

/* ---- EMPTY / WELCOME ---- */
.empty-state, .welcome-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 48px 20px;
    color: #64748b;
    flex: 1;
}
.empty-icon, .welcome-icon {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff, #dbeafe);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; color: #2563eb; margin-bottom: 14px;
}
.empty-state h3 { margin: 0 0 6px; font-size: 17px; font-weight: 600; color: #374151; }
.empty-state p { margin: 0; font-size: 13.5px; }
.welcome-state h2 { margin: 0 0 10px; font-size: 22px; font-weight: 700; color: #1e293b; }
.welcome-desc { margin: 0 0 20px; font-size: 13.5px; line-height: 1.6; max-width: 480px; }
.welcome-features {
    display: flex; flex-direction: column; gap: 8px;
    margin-bottom: 22px; max-width: 400px; width: 100%;
}
.feature-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 13px;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 9px;
    font-size: 13px; color: #374151; text-align: left;
}
.feature-item i { flex-shrink: 0; font-size: 12px; }
.btn-lg { padding: 12px 26px; font-size: 14.5px; font-weight: 600; }

/* ---- MESSAGES ---- */
.message-row {
    display: flex; gap: 10px;
    max-width: 85%;
    animation: fadeInUp 0.22s ease;
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.message-row--user { align-self: flex-end; flex-direction: row-reverse; }
.message-row--ai  { align-self: flex-start; }

.message-avatar {
    flex: 0 0 32px; width: 32px; height: 32px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 12.5px; color: #fff; flex-shrink: 0;
}
.message-avatar--ai   { background: linear-gradient(135deg, #1e3a8a, #2563eb); }
.message-avatar--user { background: #94a3b8; }

.message-bubble-col {
    display: flex; flex-direction: column; max-width: 100%;
}
.message-row--user .message-bubble-col { align-items: flex-end; }
.message-row--ai  .message-bubble-col { align-items: flex-start; }

.message-bubble {
    border-radius: 16px; padding: 11px 15px;
    font-size: 14px; line-height: 1.65;
    box-shadow: 0 1px 3px rgba(15,23,42,0.07);
    word-wrap: break-word;
}
.message-bubble--user {
    background: #2563eb; color: #fff;
    border-bottom-right-radius: 5px;
}
.message-bubble--ai {
    background: #fff; color: #1e293b;
    border: 1px solid #e2e8f0;
    border-bottom-left-radius: 5px;
}

.message-plain { white-space: pre-wrap; }
.message-markdown { line-height: 1.72; }
.message-markdown p { margin: 0 0 10px; }
.message-markdown p:last-child { margin-bottom: 0; }
.message-markdown code { background: #f1f5f9; padding: 2px 5px; border-radius: 4px; font-size: 12.5px; }
.message-markdown pre { background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 8px; overflow-x: auto; font-size: 12px; }
.message-markdown pre code { background: none; padding: 0; color: inherit; }
.message-markdown table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12.5px; }
.message-markdown th, .message-markdown td { border: 1px solid #e2e8f0; padding: 7px 10px; text-align: left; }
.message-markdown th { background: #f8fafc; font-weight: 600; }
.cw-table-wrap { overflow-x: auto; margin: 10px -15px; padding: 0 15px 10px; }

/* Attachments */
.message-attachments { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 7px; }
.message-attach-image { display: block; width: 76px; height: 76px; border-radius: 7px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08); }
.message-attach-image img { width: 100%; height: 100%; object-fit: cover; }
.message-attach-file { display: flex; align-items: center; gap: 7px; background: rgba(0,0,0,0.04); border-radius: 7px; padding: 7px 11px; font-size: 12.5px; max-width: 220px; color: inherit; text-decoration: none; }
.message-bubble--user .message-attach-file { background: rgba(255,255,255,0.16); color: #fff; }
.message-attach-file:hover { opacity: 0.85; }
.message-attach-file-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Export */
.message-data-caption { margin: 9px 0 5px; font-size: 11.5px; color: #64748b; display: flex; align-items: center; gap: 5px; }
.message-export { display: flex; align-items: center; gap: 10px; padding: 9px 11px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 9px; text-decoration: none; color: #374151; margin-top: 7px; transition: all 0.12s; }
.message-export:hover { background: #eff6ff; border-color: #93c5fd; }
.message-export-icon { display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 7px; background: #fff; font-size: 13px; color: #2563eb; }
.message-export-icon--xlsx { color: #16a34a; }
.message-export-icon--pdf  { color: #dc2626; }
.message-export-icon--image{ color: #7c3aed; }
.message-export-info { display: flex; flex-direction: column; min-width: 0; }
.message-export-name { font-weight: 500; font-size: 12.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.message-export-meta { font-size: 11px; color: #64748b; }
.message-export-download { color: #94a3b8; font-size: 12px; margin-left: auto; }

/* Details / Error */
.message-details { margin-top: 9px; }
.message-details summary { cursor: pointer; font-size: 11.5px; color: #64748b; padding: 3px 0; }
.message-details pre { background: #1e293b; color: #e2e8f0; padding: 9px; border-radius: 6px; overflow-x: auto; font-size: 11px; margin-top: 5px; }
.message-error { margin-top: 7px; padding: 7px 10px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 7px; color: #dc2626; font-size: 11.5px; display: flex; align-items: center; gap: 5px; }
.message-time { font-size: 10px; color: #94a3b8; margin-top: 4px; padding: 0 2px; }
.message-row--user .message-time { text-align: right; }

/* ---- COMPOSER ---- */
.chat-composer {
    background: #fff;
    border-top: 1px solid #e2e8f0;
    padding: 14px 20px;
    flex-shrink: 0;
    position: relative;
}
.attach-list { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 9px; }
.attach-chip { display: flex; align-items: center; gap: 6px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 7px; padding: 5px 9px; font-size: 11.5px; color: #334155; max-width: 220px; }
.attach-chip img { width: 22px; height: 22px; object-fit: cover; border-radius: 3px; }
.attach-chip-icon { width: 22px; height: 22px; border-radius: 3px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 10px; }
.attach-chip-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
.attach-chip-remove { background: none; border: none; color: #94a3b8; font-size: 10px; padding: 2px; line-height: 1; cursor: pointer; }
.attach-chip-remove:hover { color: #ef4444; }

.composer-form { display: flex; align-items: flex-end; gap: 9px; }
.btn-attach { flex: 0 0 42px; height: 42px; border-radius: 11px; background: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 15px; cursor: pointer; transition: all 0.12s; }
.btn-attach:hover { background: #e2e8f0; color: #374151; }
.chat-textarea { flex: 1; min-height: 42px; max-height: 130px; resize: none; padding: 10px 13px; border: 1px solid #e2e8f0; border-radius: 11px; font-size: 14px; line-height: 1.5; background: #fafbfc; transition: all 0.12s; }
.chat-textarea:focus { outline: none; border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.btn-send { flex: 0 0 42px; height: 42px; border-radius: 11px; background: linear-gradient(135deg, #1e3a8a, #2563eb); border: none; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 15px; cursor: pointer; transition: all 0.12s; box-shadow: 0 2px 8px rgba(37,99,235,0.3); }
.btn-send:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(37,99,235,0.4); }
.btn-send:active { transform: translateY(0); }

.typing-indicator { display: flex; align-items: center; gap: 7px; padding: 7px 11px; font-size: 11.5px; color: #64748b; margin-top: 7px; }
.typing-dots { display: flex; gap: 3px; }
.typing-dots span { width: 5px; height: 5px; border-radius: 50%; background: #2563eb; animation: typingBounce 1.2s infinite ease-in-out; }
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingBounce { 0%,80%,100% { transform: scale(0.8); opacity: 0.5; } 40% { transform: scale(1.2); opacity: 1; } }

.composer-hint { margin: 8px 0 0; font-size: 11px; color: #94a3b8; line-height: 1.5; }

.drop-overlay {
    position: fixed; inset: 0;
    background: rgba(37,99,235,0.1);
    border: 3px dashed #2563eb;
    z-index: 1000;
    display: flex; align-items: center; justify-content: center;
    pointer-events: none;
}
.drop-overlay-inner { background: #fff; border-radius: 13px; padding: 22px 30px; box-shadow: 0 12px 40px rgba(15,23,42,0.2); display: flex; align-items: center; gap: 11px; font-size: 14px; font-weight: 600; color: #1d4ed8; }
.drop-overlay-inner i { font-size: 22px; }

/* Responsive */
@media (max-width: 640px) {
    .chat-sidebar { width: 220px; min-width: 220px; }
    .btn-back-home span { display: none; }
    .chat-header { padding: 11px 16px; }
    .chat-window { padding: 14px; gap: 14px; }
    .message-row { max-width: 92%; }
    .chat-composer { padding: 11px 14px; }
    .chat-textarea { font-size: 16px; }
}
@media (max-width: 480px) {
    .chat-sidebar { display: none; }
}
</style>

@push('scripts')
<script>
(function() {
    'use strict';

    // DOM
    const chatbotApp    = document.getElementById('chatbot-app');
    const chatForm      = document.getElementById('chat-form');
    const chatInput     = document.getElementById('chat-input');
    const btnSend       = document.getElementById('btn-send');
    const btnAttach     = document.getElementById('btn-attach');
    const attachInput   = document.getElementById('attach-input');
    const attachList    = document.getElementById('attach-list');
    const attachError   = document.getElementById('attach-error');
    const chatMessages  = document.getElementById('chat-messages');
    const typingIndicator = document.getElementById('chat-typing');
    const dropOverlay   = document.getElementById('drop-overlay');
    const btnStartChat  = document.getElementById('btn-start-chat');
    const btnNewSession = document.getElementById('btn-new-session');
    const newSessionError = document.getElementById('new-session-error');

    const storeUrl      = chatbotApp?.dataset?.storeUrl   || '{{ route("chatbot.sessions.store") }}';
    const indexUrl      = chatbotApp?.dataset?.indexUrl   || '{{ route("chatbot.index") }}';
    const aiProvider    = chatbotApp?.dataset?.aiProvider || '{{ $aiProvider }}';
    const activeSession = chatbotApp?.dataset?.activeSession || '';

    let pendingFiles = [];
    let isSending    = false;

    // Auto-resize textarea
    if (chatInput) {
        chatInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 130) + 'px';
        });
    }

    /* ---- File handling ---- */
    const MAX_FILES = 5;
    const MAX_SIZE  = 25 * 1024 * 1024;
    const ALLOWED_TYPES = [
        'image/jpeg','image/png','image/gif','image/webp','image/bmp',
        'application/pdf',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain','text/csv','text/rtf',
        'application/vnd.oasis.opendocument.text','application/vnd.oasis.opendocument.spreadsheet','application/vnd.oasis.opendocument.presentation',
        'video/mp4','video/quicktime','video/x-msvideo','video/x-matroska','video/webm',
        'audio/mpeg','audio/wav','audio/ogg',
    ];

    function formatSize(b) {
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
        return (b/1048576).toFixed(1) + ' MB';
    }
    function getFileIcon(t) {
        if (t.startsWith('image/')) return 'fa-file-image';
        if (t === 'application/pdf') return 'fa-file-pdf';
        if (t.includes('word') || t.includes('document')) return 'fa-file-word';
        if (t.includes('excel') || t.includes('spreadsheet')) return 'fa-file-excel';
        if (t.includes('powerpoint') || t.includes('presentation')) return 'fa-file-powerpoint';
        if (t.startsWith('video/')) return 'fa-file-video';
        if (t.startsWith('audio/')) return 'fa-file-audio';
        if (t.startsWith('text/')) return 'fa-file-alt';
        return 'fa-file';
    }
    function validateFile(f) {
        if (!ALLOWED_TYPES.includes(f.type)) return 'Tipe file tidak didukung: ' + f.type;
        if (f.size > MAX_SIZE) return 'File terlalu besar (maks 25MB): ' + formatSize(f.size);
        return null;
    }
    function renderAttachList() {
        if (!attachList) return;
        attachList.innerHTML = '';
        if (pendingFiles.length === 0) { attachList.classList.add('d-none'); return; }
        attachList.classList.remove('d-none');
        pendingFiles.forEach((file, idx) => {
            const chip = document.createElement('div');
            chip.className = 'attach-chip';
            chip.innerHTML = `<i class="fas ${getFileIcon(file.type)} attach-chip-icon"></i>
                <span class="attach-chip-name" title="${file.name}">${file.name}</span>
                <span class="attach-chip-name" style="color:#94a3b8;font-size:10.5px;">${formatSize(file.size)}</span>
                <button type="button" class="attach-chip-remove" data-idx="${idx}" title="Hapus"><i class="fas fa-times"></i></button>`;
            attachList.appendChild(chip);
        });
        if (attachError) attachError.classList.add('d-none');
    }
    function addFiles(files) {
        for (const f of files) {
            const err = validateFile(f);
            if (err) {
                if (attachError) { attachError.textContent = err; attachError.classList.remove('d-none'); }
                continue;
            }
            if (pendingFiles.length >= MAX_FILES) {
                if (attachError) { attachError.textContent = 'Maksimal ' + MAX_FILES + ' file'; attachError.classList.remove('d-none'); }
                break;
            }
            pendingFiles.push(f);
        }
        renderAttachList();
    }

    if (btnAttach) btnAttach.addEventListener('click', () => attachInput.click());
    if (attachInput) attachInput.addEventListener('change', e => { addFiles(Array.from(e.target.files)); attachInput.value = ''; });
    if (attachList) attachList.addEventListener('click', e => {
        const btn = e.target.closest('.attach-chip-remove');
        if (btn) { pendingFiles.splice(parseInt(btn.dataset.idx,10), 1); renderAttachList(); }
    });

    /* ---- Drag & drop ---- */
    ['dragenter','dragover'].forEach(evt => {
        document.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); if (dropOverlay) dropOverlay.classList.remove('d-none'); });
    });
    ['dragleave','drop'].forEach(evt => {
        document.addEventListener(evt, e => {
            e.preventDefault(); e.stopPropagation();
            if (evt === 'drop') addFiles(Array.from(e.dataTransfer.files));
            if (dropOverlay) dropOverlay.classList.add('d-none');
        });
    });

    /* ---- Send message ---- */
    async function sendMessage(content, files) {
        if (isSending) return;
        isSending = true;
        if (btnSend) btnSend.disabled = true;
        if (chatInput) chatInput.disabled = true;
        if (btnAttach) btnAttach.disabled = true;
        if (typingIndicator) typingIndicator.classList.remove('d-none');

        // Tambah bubble user di DOM sementara
        appendUserBubble(content, files);
        scrollToBottom();

        const formData = new FormData();
        formData.append('_token', getCsrf());
        formData.append('message', content);
        files.forEach(f => formData.append('attachments[]', f));

        // URL: /chatbot/sessions/{id}/send
        const sendUrl = (chatbotApp?.dataset?.sendUrlTemplate || '{{ url("chatbot/sessions/__ID__/send") }}')
                            .replace('__ID__', activeSession);

        // PERBAIKAN V32.10: guard — kalau tidak ada sesi aktif, jangan kirim
        // (perluannya request akan 404 dan user bingung).
        if (!activeSession) {
            appendAiBubble({ content: 'Belum ada sesi chat aktif. Silakan buat sesi baru terlebih dahulu.', meta: null });
            scrollToBottom();
            isSending = false;
            if (btnSend)   btnSend.disabled = false;
            if (chatInput) chatInput.disabled = false;
            if (btnAttach) btnAttach.disabled = false;
            if (typingIndicator) typingIndicator.classList.add('d-none');
            return;
        }

        try {
            const res  = await fetch(sendUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });

            let data;
            const rawText = await res.text();
            try {
                data = JSON.parse(rawText);
            } catch (e) {
                // Respons bukan JSON (mis. halaman error Laravel / 419 / 500).
                data = { message: 'Server membalas dengan status ' + res.status + '. ' + (rawText || '').slice(0, 300) };
            }

            if (!res.ok) {
                const msg = data.message || data.errors?.message || ('Gagal mengirim pesan (HTTP ' + res.status + ')');
                throw new Error(msg);
            }
            // Render response AI
            // Akar bubble kosong: endpoint /send membalas {assistant_message:...},
            // bukan {message:...}. appendAiBubble(data.message ?? data) berarti
            // data.message tidak ada -> msg = whole data -> content undefined ->
            // renderMarkdown('') -> bubble kosong. Pakai assistant_message dulu.
            appendAiBubble(data.assistant_message ?? data.message ?? data);
            scrollToBottom();
            // Update title session di sidebar jika ada
            if (data.session && data.session.title) {
                document.querySelectorAll('.session-item--active .session-item-title').forEach(el => {
                    el.textContent = data.session.title;
                });
            }
        } catch (err) {
            appendAiBubble({ content: 'Maaf, terjadi kendala: ' + err.message, meta: null });
            scrollToBottom();
            if (attachError) { attachError.textContent = err.message; attachError.classList.remove('d-none'); }
        } finally {
            isSending = false;
            if (btnSend)   btnSend.disabled = false;
            if (chatInput) chatInput.disabled = false;
            if (btnAttach) btnAttach.disabled = false;
            if (typingIndicator) typingIndicator.classList.add('d-none');
        }
    }

    /* Tambah bubble user di chat window */
    function appendUserBubble(content, files) {
        if (!chatMessages) return;
        const row = document.createElement('div');
        row.className = 'message-row message-row--user';
        let filesHtml = '';
        files.forEach(f => {
            if (f.type.startsWith('image/')) {
                const url = URL.createObjectURL(f);
                filesHtml += `<a class="message-attach-image" href="${url}" target="_blank"><img src="${url}" alt="${f.name}"></a>`;
            } else {
                filesHtml += `<span class="message-attach-file"><i class="fas fa-file"></i><span class="message-attach-file-name">${f.name}</span></span>`;
            }
        });
        const attachHtml = files.length ? `<div class="message-attachments">${filesHtml}</div>` : '';
        const textHtml = content ? `<div class="message-plain">${escHtml(content)}</div>` : '';
        row.innerHTML = `
            <div class="message-avatar message-avatar--user"><i class="fas fa-user"></i></div>
            <div class="message-bubble-col">
                <div class="message-bubble message-bubble--user">${textHtml}${attachHtml}</div>
                <span class="message-time">${nowTime()}</span>
            </div>`;
        chatMessages.appendChild(row);
    }

    /* Tambah bubble AI di chat window */
    function appendAiBubble(msg) {
        if (!chatMessages) return;
        const row = document.createElement('div');
        row.className = 'message-row message-row--ai';
        const content = msg.content ?? msg;
        const meta    = msg.meta ?? null;

        // Render markdown sederhana → bisa upgrade ke marked.js
        let bodyHtml = `<div class="message-markdown">${renderMarkdown(content)}</div>`;

        // Export attachment
        let exportHtml = '';
        if (meta && meta.export) {
            const ex = meta.export;
            if (ex.type === 'html_table') {
                exportHtml = `<p class="message-data-caption"><i class="fas fa-table"></i> Hasil data · ${ex.row_count ?? 0} baris</p>${ex.html}`;
            } else {
                exportHtml = `<a href="${ex.url}" target="_blank" rel="noopener" class="message-export">
                    <span class="message-export-icon"><i class="fas fa-file-download"></i></span>
                    <span class="message-export-info">
                        <span class="message-export-name">${ex.filename}</span>
                        <span class="message-export-meta">${ex.type.toUpperCase()}${ex.row_count ? ' · ' + ex.row_count + ' baris' : ''}</span>
                    </span>
                    <i class="fas fa-download message-export-download"></i>
                </a>`;
            }
        }

        // Error note
        let errHtml = '';
        if (meta && meta.error) {
            errHtml = `<p class="message-error"><i class="fas fa-triangle-exclamation"></i> ${escHtml(meta.error)}</p>`;
        }

        row.innerHTML = `
            <div class="message-avatar message-avatar--ai"><i class="fas fa-robot"></i></div>
            <div class="message-bubble-col">
                <div class="message-bubble message-bubble--ai">${bodyHtml}${exportHtml}${errHtml}</div>
                <span class="message-time">${nowTime()}</span>
            </div>`;
        chatMessages.appendChild(row);

        // Aktifkan DataTable setelah load async selesai; polling di init menangani siap/tidak.
        if (typeof window.__chatbotActivateTables === 'function') {
            window.__chatbotActivateTables();
        }
    }

    /* Aktifkan DataTable pada semua <table> di dalam elemen */
    function activateDataTables(container) {
        if (typeof $.fn === 'undefined' || typeof $.fn.DataTable === 'undefined') return;
        container.querySelectorAll('table').forEach(function (tbl) {
            if (!tbl.id) tbl.id = 'dt_' + Math.random().toString(36).slice(2, 8);
            if (!$.fn.DataTable.isDataTable('#' + tbl.id)) {
                tbl.classList.add('table', 'table-bordered', 'table-sm');
                $('#' + tbl.id).DataTable({
                    pageLength: 10,
                    scrollX: true,
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' }
                });
            }
        });
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function nowTime() {
        return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }
    /* Markdown sederhana: bold, italic, code, pre, heading, list, link, br */
    function renderMarkdown(text) {
        if (!text) return '';
        let h = escHtml(text);
        // code block
        h = h.replace(/```[\w]*\n?([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        // inline code
        h = h.replace(/`([^`]+)`/g, '<code>$1</code>');
        // heading
        h = h.replace(/^### (.+)$/gm, '<h5>$1</h5>');
        h = h.replace(/^## (.+)$/gm,  '<h4>$1</h4>');
        h = h.replace(/^# (.+)$/gm,   '<h3>$1</h3>');
        // bold & italic
        h = h.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
        h = h.replace(/\*\*(.+?)\*\*/g,     '<strong>$1</strong>');
        h = h.replace(/\*(.+?)\*/g,         '<em>$1</em>');
        // unordered list
        h = h.replace(/^\* (.+)$/gm, '<li>$1</li>');
        h = h.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');
        // ordered list
        h = h.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
        // link
        h = h.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        // paragraph
        h = h.replace(/\n\n/g, '</p><p>');
        h = '<p>' + h + '</p>';
        h = h.replace(/\n/g, '<br>');
        return h;
    }

    if (chatForm) {
        chatForm.addEventListener('submit', async e => {
            e.preventDefault();
            const content = chatInput.value.trim();
            if (!content && pendingFiles.length === 0) return;
            chatInput.value = '';
            chatInput.style.height = 'auto';
            const files = [...pendingFiles];
            pendingFiles = [];
            renderAttachList();
            await sendMessage(content, files);
        });
        chatInput.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit'));
            }
        });
    }

    /* ---- CSRF helper ---- */
    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    /* ---- Create new session ---- */
    async function createNewSession(btn, errEl) {
        if (isSending) return;
        isSending = true;
        if (btn) btn.disabled = true;
        if (errEl) errEl.classList.add('d-none');

        const fd = new FormData();
        fd.append('_token', getCsrf());
        fd.append('ai_provider', aiProvider);
        fd.append('mode', 'global');

        try {
            const res  = await fetch(storeUrl, { method: 'POST', body: fd });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Gagal membuat sesi');
            const id = data.session?.id ?? data.session_id;
            window.location.href = indexUrl + '?session=' + id;
        } catch (err) {
            if (errEl) { errEl.textContent = err.message; errEl.classList.remove('d-none'); }
        } finally {
            isSending = false;
            if (btn) btn.disabled = false;
        }
    }

    if (btnStartChat) btnStartChat.addEventListener('click', () => createNewSession(btnStartChat, newSessionError));
    if (btnNewSession) btnNewSession.addEventListener('click', () => createNewSession(btnNewSession, null));

    /* Tunggu sampai DataTables siap: halaman chatbot tidak memuat jQuery
       di <head>. Script DataTables sebelumnya ditaruh tepat sebelum
       layout.footerscript (yang justru memuat jQuery), jadi selalu error
       "jQuery is not defined". Polling ringan di sini; kalau tetap tak
       ada, activateDataTables menampilkan tabel polos apa adanya. */
    window.__chatbotActivateTables = function () {
        if (!chatMessages) return;
        chatMessages.querySelectorAll('table').forEach(function (tbl) {
            if (!tbl.id) tbl.id = 'dt_' + Math.random().toString(36).slice(2, 8);
            if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.DataTable === 'function') {
                if (!window.jQuery.fn.DataTable.isDataTable('#' + tbl.id)) {
                    tbl.classList.add('table', 'table-bordered', 'table-sm');
                    window.jQuery('#' + tbl.id).DataTable({
                        pageLength: 10,
                        scrollX: true,
                        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' }
                    });
                }
            } else {
                // Fallback polos — jangan biarkan tabel AI tanpa style Bootstrap.
                tbl.classList.add('table', 'table-bordered', 'table-sm');
            }
        });
    };
    let _dtWait = 0;
    const _dtWaitTimer = setInterval(function () {
        _dtWait += 200;
        const dtReady = window.jQuery && window.jQuery.fn &&
            typeof window.jQuery.fn.DataTable === 'function';
        if (dtReady || _dtWait >= 8000) {
            if (_dtWaitTimer) clearInterval(_dtWaitTimer);
            window.__chatbotActivateTables();
        }
    }, 200);

    /* ---- Scroll ---- */
    function scrollToBottom() {
        if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    scrollToBottom();
})();

/* DataTables (jQuery-dependent): dimuat SETELAH jQuery. Halaman chatbot tidak
   memuat jQuery di <head> — jQuery baru ada di layout.footerscript — jadi
   tag <script src=jquery.dataTables> yang ditaruh tepat sebelum include itu
   selalu error "jQuery is not defined". Muat di sini secara berurutan.
   Sumber: jsDelivr lokal-kan (sudah dipaki layout.header) supaya tidak
   bergantung CDN datatables; versi 1.13.8 sama dengan versi lain di app. */
(function () {
    var DT = [
        'https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js',
        'https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/js/dataTables.bootstrap5.min.js'
    ];
    function loadOne(i) {
        if (i >= DT.length) {
            if (typeof window.__chatbotActivateTables === 'function') {
                window.__chatbotActivateTables();
            }
            return;
        }
        var s = document.createElement('script');
        s.src = DT[i];
        s.async = false;
        s.onerror = function () {
            if (typeof window.__chatbotActivateTables === 'function') {
                window.__chatbotActivateTables();
            }
        };
        s.onload = function () { loadOne(i + 1); };
        document.body.appendChild(s);
    }
    loadOne(0);
})();
</script>
@endpush

{{-- PERBAIKAN v32 kl 9...[truncated]
     Tanpa include ini:
       1) @stack('scripts') TIDAK PERNAH di-render -> seluruh JS chatbot
          (termasuk handler submit #chat-form) tidak pernah jalan,
       2) form submit menjadi submit NATIVE -> browser navigate ke URL
          saat ini (/chatbot?session=1) -> pengguna melihat "halaman
          diarahkan ke /chatbot dan pesan tidak terkirim",
       3) jQuery + Bootstrap tidak termuat -> DataTable & modal error.
     File ini juga memuat @stack('scripts') di baris terakhir, jadi blok
     JS chatbot (yang memakai @push('scripts')) ikut ter-render. --}}
@include('layout.footerscript')
</body>
</html>
