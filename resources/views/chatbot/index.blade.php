<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body>
@include('sweetalert::alert')

{{-- Catatan: tabel jawaban AI sengaja TIDAK memakai DataTables (permintaan
     user 15 Nov 2026). Tabel AI ditampilkan sebagai tabel biasa; lihat
     __chatbotStyleTables() di bagian script. --}}

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
                <img src="{{ asset('img/chutex_ai_assistant.png') }}" alt="AI Assistant Chutex" class="ai-avatar ai-avatar--xs">
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
                    <img src="{{ asset('img/chutex_ai_assistant.png') }}" alt="AI Assistant Chutex" class="ai-avatar">
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
                            @if ($isUser)
                                <i class="fas fa-user"></i>
                            @else
                                <img src="{{ asset('img/chutex_ai_assistant.png') }}" alt="AI Assistant" class="ai-avatar">
                            @endif
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

                                @if (! empty($meta['chart']))
                                    @php $ch = $meta['chart']; @endphp
                                    <figure class="message-chart" id="chart-{{ $message->id }}">
                                        <button type="button" class="message-chart-zoom" data-chart-zoom
                                                data-chart-src="{{ $ch['url'] }}"
                                                data-chart-alt="Grafik {{ $ch['nilai_kolom'] ?? '' }} per {{ $ch['label_kolom'] ?? '' }}"
                                                title="Klik untuk memperbesar grafik">
                                            <img src="{{ $ch['url'] }}" alt="Grafik {{ $ch['nilai_kolom'] ?? '' }} per {{ $ch['label_kolom'] ?? '' }}" loading="lazy"
                                                 onerror="this.closest('.message-chart').classList.add('message-chart--gagal')">
                                        </button>
                                        <p class="message-chart-gagal">
                                            <i class="fas fa-triangle-exclamation"></i>
                                            Grafik gagal dimuat di browser. Klik <strong>Unduh PNG</strong> untuk membuka gambarnya.
                                        </p>
                                        <figcaption>
                                            <i class="fas fa-chart-simple"></i>
                                            <span>
                                                Grafik batang &middot; {{ $ch['bar_count'] ?? 0 }} kategori
                                                @if (! empty($ch['otomatis'])) &middot; dibuat otomatis @endif
                                            </span>
                                            <a href="{{ $ch['url'] }}" target="_blank" rel="noopener" class="message-chart-download">
                                                <i class="fas fa-download"></i> Unduh PNG
                                            </a>
                                        </figcaption>
                                    </figure>
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
                                    <details class="message-sql">
                                        <summary><i class="fas fa-code"></i> Lihat SQL</summary>
                                        <pre>{{ $meta['sql'] }}</pre>
                                    </details>
                                @endif
                                @if (! empty($meta['links']))
                                    <div class="message-links">
                                        @foreach ($meta['links'] as $link)
                                            <a href="{{ $link['url'] }}" class="message-link-btn" target="_blank" rel="noopener">
                                                <i class="fas fa-arrow-right"></i> {{ $link['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
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
                    <div class="welcome-icon"><img src="{{ asset('img/chutex_ai_assistant.png') }}" alt="AI Assistant Chutex" class="ai-avatar"></div>
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

/* Foto profil AI Assistant (permintaan 16 Nov 2026): memakai aset
   img/chutex_ai_assistant.png di semua tempat avatar AI muncul. */
.ai-avatar {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: inherit;
}
.ai-avatar--xs { width: 24px; height: 24px; border-radius: 50%; flex-shrink: 0; }
.chat-avatar { padding: 0; overflow: hidden; background: #e8effb; }
.message-avatar--ai { overflow: hidden; background: #e8effb; box-shadow: 0 0 0 2px #dbeafe; }
.welcome-icon { overflow: hidden; box-shadow: 0 10px 26px rgba(30,58,138,0.18); }
.empty-icon { overflow: hidden; }

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
    /* Isi panjang (SQL/pre/tabel) tidak boleh menyeret lebar bubble:
       batas lebar + pemenggalan kata menjaga bubble tetap di kolomnya. */
    max-width: 100%; min-width: 0; overflow-wrap: anywhere;
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
.message-markdown ul,
.message-markdown ol {
    margin: 0 0 10px;
    padding-left: 22px;
    list-style-position: outside;
}
.message-markdown ul { list-style-type: disc; }
.message-markdown ol { list-style-type: decimal; }
.message-markdown li { margin-bottom: 4px; }
.message-markdown li:last-child { margin-bottom: 0; }
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
/* Grafik jawaban AI (PNG dibuat server) */
.message-chart { margin: 10px 0 2px; padding: 0; }
.message-chart img { display: block; width: auto; max-width: 100%; height: auto; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 9px; background: #fff; }
/* Klik grafik = perbesar di layar (lightbox), bukan unduh. Unduhan tetap
   lewat tombol "Unduh PNG" di caption. */
.message-chart-zoom { display: block; max-width: 100%; margin: 0; padding: 0; border: 0; background: none; cursor: zoom-in; line-height: 0; }
.message-chart-zoom:focus-visible { outline: 2px solid #2563eb; outline-offset: 3px; border-radius: 11px; }
.message-chart-zoom:hover img { border-color: #93b4ea; box-shadow: 0 4px 16px rgba(37, 99, 235, .14); }
.message-chart figcaption { display: flex; align-items: center; gap: 7px; margin-top: 6px; font-size: 11.5px; color: #64748b; flex-wrap: wrap; }
.message-chart figcaption i.fa-chart-simple { color: #274b8f; }
.message-chart-gagal { display: none; margin: 6px 0 0; padding: 8px 10px; border-radius: 8px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; font-size: 11.5px; }
.message-chart--gagal img { display: none; }
.message-chart--gagal .message-chart-gagal { display: block; }
.message-chart-download { margin-left: auto; color: #2563eb; text-decoration: none; font-weight: 500; }
.message-chart-download:hover { text-decoration: underline; }
.message-links { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.message-link-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12.5px; font-weight: 500; color: #1d3f86; background: #eef4ff; border: 1px solid #c7d8f5; border-radius: 999px; text-decoration: none; transition: all 0.12s; }
.message-link-btn:hover { background: #dce9ff; border-color: #9dbdf0; color: #16307a; }
.message-link-btn i { font-size: 10px; }
.md-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12.5px; }
.md-table th, .md-table td { border: 1px solid #e2e8f0; padding: 6px 10px; text-align: left; }
.md-table thead th { background: #f1f5f9; font-weight: 600; }

/* Details / Error */
/* SQL panjang: sebelumnya <pre> tanpa CSS sama sekali, sehingga satu baris
   SQL (~2.000 karakter) memaksa bubble melebar ke samping dan gambar grafik
   di bawahnya ikut melar. Sekarang dipatahkan per karakter + scroll internal. */
.message-sql { margin-top: 9px; max-width: 100%; min-width: 0; }
.message-sql summary { cursor: pointer; font-size: 11.5px; color: #64748b; padding: 3px 0; }
.message-sql summary:hover { color: #1d3f86; }
.message-sql pre {
    margin: 6px 0 0; padding: 10px 11px;
    max-width: 100%; max-height: 260px; overflow: auto;
    white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word;
    background: #0f172a; color: #dbe4f0; border-radius: 8px;
    font-size: 11px; line-height: 1.55;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.message-markdown pre, .message-details pre { max-width: 100%; }
.message-details { margin-top: 9px; }
.message-details summary { cursor: pointer; font-size: 11.5px; color: #64748b; padding: 3px 0; }
.message-details pre { background: #1e293b; color: #e2e8f0; padding: 9px; border-radius: 6px; overflow-x: auto; font-size: 11px; margin-top: 5px; }
.message-error { margin-top: 7px; padding: 7px 10px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 7px; color: #dc2626; font-size: 11.5px; display: flex; align-items: center; gap: 5px; }
.message-time { font-size: 10px; color: #94a3b8; margin-top: 4px; padding: 0 2px; }
.message-row--user .message-time { text-align: right; }

/* ---- LIGHTBOX GRAFIK (klik gambar = zoom) ---- */
.chart-lightbox {
    position: fixed; inset: 0; z-index: 2000;
    display: flex; align-items: center; justify-content: center;
    padding: 30px 24px 48px;
    background: rgba(8, 14, 26, .84);
    -webkit-backdrop-filter: blur(3px); backdrop-filter: blur(3px);
}
.chart-lightbox[hidden] { display: none; }
.chart-lightbox img {
    max-width: 94vw; max-height: 84vh; width: auto; height: auto;
    border-radius: 10px; background: #fff; box-shadow: 0 24px 70px rgba(0, 0, 0, .55);
}
.chart-lightbox-tutup {
    position: absolute; top: 16px; right: 18px;
    width: 42px; height: 42px; border-radius: 50%;
    border: 1px solid rgba(255,255,255,.35); background: rgba(255,255,255,.14);
    color: #fff; font-size: 16px; cursor: pointer; display: flex;
    align-items: center; justify-content: center; transition: background .15s;
}
.chart-lightbox-tutup:hover { background: rgba(255,255,255,.26); }
.chart-lightbox-hint {
    position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%);
    color: #c9d6e8; font-size: 12px; letter-spacing: .01em;
}

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
        // Jangan sampai kegagalan renderer menghapus jawaban AI: kalau
        // renderMarkdown melempar error, tampilkan teksnya apa adanya.
        let bodyHtml;
        try {
            bodyHtml = `<div class="message-markdown">${renderMarkdown(content)}</div>`;
        } catch (e) {
            if (window.console && console.warn) console.warn('renderMarkdown gagal:', e);
            bodyHtml = `<div class="message-plain">${escHtml(content)}</div>`;
        }

        // Grafik jawaban AI (gambar PNG dari server)
        let chartHtml = '';
        if (meta && meta.chart && meta.chart.url) {
            const ch = meta.chart;
            const ket = `Grafik batang · ${ch.bar_count ?? 0} kategori${ch.otomatis ? ' · dibuat otomatis' : ''}`;
            chartHtml = `<figure class="message-chart">
                <button type="button" class="message-chart-zoom" data-chart-zoom
                        data-chart-src="${escHtml(ch.url)}"
                        data-chart-alt="Grafik ${escHtml(ch.nilai_kolom ?? '')} per ${escHtml(ch.label_kolom ?? '')}"
                        title="Klik untuk memperbesar grafik">
                    <img src="${escHtml(ch.url)}" alt="Grafik ${escHtml(ch.nilai_kolom ?? '')} per ${escHtml(ch.label_kolom ?? '')}" loading="lazy"
                         onerror="this.closest('.message-chart').classList.add('message-chart--gagal')">
                </button>
                <p class="message-chart-gagal"><i class="fas fa-triangle-exclamation"></i> Grafik gagal dimuat di browser. Klik <strong>Unduh PNG</strong> untuk membuka gambarnya.</p>
                <figcaption>
                    <i class="fas fa-chart-simple"></i>
                    <span>${escHtml(ket)}</span>
                    <a href="${escHtml(ch.url)}" target="_blank" rel="noopener" class="message-chart-download"><i class="fas fa-download"></i> Unduh PNG</a>
                </figcaption>
            </figure>`;
        }

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

        // Action links (mis. ajukan cuti / lihat slip gaji)
        let linksHtml = '';
        if (meta && Array.isArray(meta.links) && meta.links.length) {
            const btns = meta.links.map(l =>
                `<a href="${escHtml(l.url)}" class="message-link-btn" target="_blank" rel="noopener"><i class="fas fa-arrow-right"></i> ${escHtml(l.label)}</a>`
            ).join('');
            linksHtml = `<div class="message-links">${btns}</div>`;
        }

        row.innerHTML = `
            <div class="message-avatar message-avatar--ai"><img src="{{ asset('img/chutex_ai_assistant.png') }}" alt="AI Assistant" class="ai-avatar"></div>
            <div class="message-bubble-col">
                <div class="message-bubble message-bubble--ai">${bodyHtml}${chartHtml}${exportHtml}${linksHtml}${errHtml}</div>
                <span class="message-time">${nowTime()}</span>
            </div>`;
        chatMessages.appendChild(row);

        // Rapikan tabel jawaban AI (tabel biasa, bukan DataTables).
        if (typeof window.__chatbotStyleTables === 'function') {
            window.__chatbotStyleTables();
        }
    }

    /* Tabel jawaban AI = tabel biasa. Fungsi ini hanya menambahkan kelas
       Bootstrap (table table-bordered table-sm) supaya rapi; tidak ada
       pencarian/pagination/sorting dan tidak ada library tambahan. */
    function stylePlainTables(container) {
        if (!container || !container.querySelectorAll) return;
        container.querySelectorAll('table').forEach(function (tbl) {
            tbl.classList.add('table', 'table-bordered', 'table-sm');
            tbl.classList.remove('dataTable', 'no-footer', 'dtr-inline');
            // Bungkus scroll horizontal seperlunya tanpa mengubah struktur DOM tabel.
            if (!tbl.parentElement || !tbl.parentElement.classList.contains('table-responsive')) {
                const wrap = document.createElement('div');
                wrap.className = 'table-responsive';
                tbl.parentNode.insertBefore(wrap, tbl);
                wrap.appendChild(tbl);
            }
        });
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function nowTime() {
        return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }
    /* Markdown sederhana: bold, italic, code, pre, heading, list, link, tabel, br */
    function renderMarkdownTable(h) {
        // Tabel markdown: baris header | a | b |, baris pemisah |---|---|, lalu baris data.
        // PENTING: jumlah grup kurung di regex harus SAMA dengan jumlah argumen
        // callback replace(). Sebelumnya baris pemisah memakai grup non-capture,
        // sehingga callback menerima (m, lead, block, rows-isi, offset) →
        // argumen "rows" berisi angka offset dan rows.trim() melempar
        // "rows.trim is not a function", lalu seluruh jawaban AI hilang.
        return h.replace(/(^|\n)((?:\s*\|[^\n]*\|\s*\n)+?(\s*\|(?:[\s:|-]+\|)+\s*\n)((?:\s*\|[^\n]*\|\s*\n?)+))/g,
            function (m, lead, block, sep, rows) {
                if (typeof rows !== 'string' || typeof block !== 'string') return m;
                function cells(line) {
                    return line.trim().replace(/^\|/, '').replace(/\|$/, '')
                        .split('|').map(function (c) { return c.trim(); });
                }
                var lines = block.trim().split('\n');
                var head = cells(lines[0]);
                var body = rows.trim().split('\n').filter(function (l) {
                    return l.trim() !== '';
                }).map(cells);
                if (!head.length || !body.length) return m;
                var html = '<div class="cw-table-wrap"><table class="md-table"><thead><tr>';
                head.forEach(function (c) { html += '<th>' + c + '</th>'; });
                html += '</tr></thead><tbody>';
                body.forEach(function (r) {
                    html += '<tr>';
                    r.forEach(function (c) { html += '<td>' + c + '</td>'; });
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                return lead + html;
            });
    }

    function renderMarkdownLists(text) {
        if (!text) return '';

        // Kelompokkan item daftar bernomor (`1. item`, `2. item`) ke dalam <ol>
        text = text.replace(/(?:^(?:[0-9]+[.)])\s+[^\n]+(?:\n|$))+/gm, function (block) {
            const items = block.trim().split('\n').map(function (line) {
                const isi = line.replace(/^[0-9]+[.)]\s+/, '').trim();
                return '<li>' + isi + '</li>';
            }).join('');
            return '<ol>' + items + '</ol>\n';
        });

        // Kelompokkan item daftar bullet (`* item`, `- item`) ke dalam <ul>
        text = text.replace(/(?:^(?:[*•\-])\s+[^\n]+(?:\n|$))+/gm, function (block) {
            const items = block.trim().split('\n').map(function (line) {
                const isi = line.replace(/^[*•\-]\s+/, '').trim();
                return '<li>' + isi + '</li>';
            }).join('');
            return '<ul>' + items + '</ul>\n';
        });

        return text;
    }

    function renderMarkdown(text) {
        if (!text) return '';
        let h = escHtml(text);
        // code block
        h = h.replace(/```[\w]*\n?([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        // tabel markdown (sebelum inline code supaya tidak rusak oleh | di dalam sel)
        h = renderMarkdownTable(h);
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
        // Daftar (bullet & bernomor). Dulu item bernomor hanya diubah jadi
        // <li> TANPA pembungkus <ol>, sehingga browser merender tandanya di
        // luar kotak bubble ("ordering keluar dari card"). Sekarang tiap
        // kelompok item dibungkus <ul>/<ol> sesuai jenisnya, dan item digabung
        // tanpa baris baru supaya tidak disisipi <br>.
        h = renderMarkdownLists(h);
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
        fd.append('mode', 'rag');

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

    /* Rapikan tabel AI yang sudah ada di halaman (tabel biasa, tanpa
       library apa pun). Tabel yang baru muncul dipanggil ulang di
       renderAiMessage(). */
    window.__chatbotStyleTables = function () {
        if (!chatMessages) return;
        stylePlainTables(chatMessages);
    };

    // Jalankan sekali untuk jawaban yang sudah ter-render dari server.
    window.__chatbotStyleTables();
    document.addEventListener('DOMContentLoaded', function () {
        window.__chatbotStyleTables();
    });

    /* ---- Scroll ---- */
    function scrollToBottom() {
        if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    scrollToBottom();
})();

/* Catatan: pemuat DataTables (CDN) sengaja DIHAPUS. Tabel jawaban AI
   ditampilkan sebagai tabel biasa; tidak ada pencarian, pagination,
   sorting, atau dependensi jQuery-DataTables di halaman ini. */
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
{{-- ======== TRANSITION PAGE (kunjungan pertama ke /chatbot) ========
     Permintaan 16 Nov 2026: saat user pertama kali diarahkan ke /chatbot,
     tampilkan halaman transisi berisi orb 3D yang berkumpul di logo Chutex,
     lalu menyebar dan baru menampilkan halaman chatbot.

     Aturan main:
       - hanya sekali per sesi browser (sessionStorage), atau dipaksa ?intro=1
       - tidak tampil saat membuka sesi lama (/chatbot?session=...)
       - klik / tombol Esc = lewati; prefers-reduced-motion = langsung lewati
       - logo memakai aset resmi img/chutex.svg (putih) di atas latar biru tua
     ================================================================ --}}
@if (! request()->filled('session'))
<style>
#cai-intro {
    position: fixed; inset: 0; z-index: 99999;
    display: flex; align-items: center; justify-content: center;
    background: radial-gradient(circle at 50% 42%, #274b8f 0%, #17305f 46%, #0a1830 100%);
    overflow: hidden; opacity: 1; transition: opacity .5s ease;
}
#cai-intro.cai-out { opacity: 0; pointer-events: none; }
.cai-stage { position: relative; width: min(76vw, 460px); aspect-ratio: 1 / 1; }
.cai-orb {
    position: absolute; left: 50%; top: 50%; width: var(--s); height: var(--s);
    margin: calc(var(--s) / -2) 0 0 calc(var(--s) / -2);
    border-radius: 50%;
    background: radial-gradient(circle at 32% 26%, #ffffff 0%, #b9e4ff 22%, #38bdf8 50%, #2563eb 76%, #0f2557 100%);
    box-shadow: 0 0 32px rgba(56, 189, 248, .5), inset -5px -7px 16px rgba(6, 18, 42, .6);
    will-change: transform, opacity;
}
.cai-core {
    position: absolute; inset: 17%;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    background: radial-gradient(circle at 50% 50%, rgba(15, 37, 87, .92) 0%, rgba(10, 24, 48, .72) 62%, rgba(10, 24, 48, 0) 72%);
    box-shadow: 0 0 60px rgba(34, 211, 238, .28);
}
.cai-logo { width: 54%; max-width: 200px; height: auto; filter: drop-shadow(0 6px 20px rgba(0,0,0,.35)); opacity: 0; }
.cai-ring {
    position: absolute; inset: 8%; border-radius: 50%;
    border: 1px solid rgba(148, 197, 255, .35);
    box-shadow: inset 0 0 40px rgba(56, 189, 248, .16);
    animation: cai-spin 14s linear infinite;
}
.cai-caption {
    position: absolute; left: 50%; bottom: -46px; transform: translateX(-50%);
    color: #cfe4ff; font-size: 12.5px; letter-spacing: .16em; text-transform: uppercase;
    white-space: nowrap; opacity: 0;
}
.cai-skip {
    position: absolute; right: 22px; bottom: 20px;
    color: #9fc0ea; font-size: 12px; letter-spacing: .04em;
    background: rgba(255,255,255,.06); border: 1px solid rgba(159,192,234,.35);
    padding: 6px 12px; border-radius: 20px; cursor: pointer;
}
@keyframes cai-spin { to { transform: rotate(360deg); } }
</style>

<div id="cai-intro" role="status" aria-label="Menyiapkan AI Assistant">
    <div class="cai-stage">
        <div class="cai-ring"></div>
        <div class="cai-core">
            <img src="{{ asset('img/chutex.svg') }}" alt="Chutex" class="cai-logo">
        </div>
        <div class="cai-caption">AI Assistant · Chutex HRIS</div>
    </div>
    <button type="button" class="cai-skip" id="cai-skip">Lewati</button>
</div>

<script>
(function () {
    var intro = document.getElementById('cai-intro');
    if (!intro) return;

    var KUNCI = 'chutex_ai_intro_v1';
    var paksa = new URLSearchParams(window.location.search).get('intro') === '1';
    var sudah = false;

    // localStorage dipakai supaya intro tidak terulang di tab baru (floating
    // button membuka chat di tab baru); sessionStorage hanya cadangan kalau
    // localStorage diblokir (mode privat ketat).
    try { sudah = window.localStorage.getItem(KUNCI) === '1'; } catch (e) { /* mode privat */ }
    if (!sudah) {
        try { sudah = window.sessionStorage.getItem(KUNCI) === '1'; } catch (e) { /* biarkan */ }
    }
    var kurangiGerak = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (kurangiGerak || (sudah && !paksa)) {
        intro.remove();
        return;
    }

    // Buang ?intro=1 dari address bar supaya refresh tidak memaksa intro lagi.
    if (paksa) {
        try {
            var u = new URL(window.location.href);
            u.searchParams.delete('intro');
            window.history.replaceState(null, '', u);
        } catch (e) { /* biarkan */ }
    }

    try { window.localStorage.setItem(KUNCI, '1'); } catch (e) { /* biarkan */ }
    try { window.sessionStorage.setItem(KUNCI, '1'); } catch (e) { /* biarkan */ }

    var stage = intro.querySelector('.cai-stage');
    var orbs = Array.prototype.slice.call(intro.querySelectorAll('.cai-orb'));
    var logo = intro.querySelector('.cai-logo');
    var caption = intro.querySelector('.cai-caption');
    var sudahTutup = false;
    var bisaAnimasi = stage && typeof stage.animate === 'function';

    var JARAK = Math.min(window.innerWidth, window.innerHeight) * (window.innerWidth < 520 ? 0.44 : 0.52);

    // Orb disiapkan di JS supaya jumlah & sudut sebarannya bisa diatur.
    var arah = [];
    var jumlah = window.innerWidth < 520 ? 7 : 9;
    for (var i = 0; i < jumlah; i++) {
        var sudut = (Math.PI * 2 / jumlah) * i - Math.PI / 2;
        var ukuran = 26 + (i % 3) * 14 + (i % 2) * 6;
        var orb = document.createElement('div');
        orb.className = 'cai-orb';
        orb.style.setProperty('--s', ukuran + 'px');
        orb.style.opacity = '0';
        stage.appendChild(orb);
        orbs.push(orb);
        arah.push({ sudut: sudut, ukuran: ukuran });
    }

    function tutup(segera) {
        if (sudahTutup) return;
        sudahTutup = true;
        intro.classList.add('cai-out');
        window.setTimeout(function () { intro.remove(); }, segera ? 0 : 520);
    }

    document.getElementById('cai-skip').addEventListener('click', function () { tutup(true); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') tutup(true);
    });

    if (!bisaAnimasi) {
        window.setTimeout(function () { tutup(false); }, 900);
        return;
    }

    // 1) Orb berkumpul dari luar membentuk cincin DI SEKITAR logo (bukan
    //    menimpanya, supaya logo tetap terbaca), lalu berputar pelan.
    var rCincin = JARAK * 0.46;

    orbs.forEach(function (orb, i) {
        var a = arah[i];
        var r0 = JARAK * (0.95 + (i % 3) * 0.12);
        var xC = Math.cos(a.sudut) * rCincin;
        var yC = Math.sin(a.sudut) * rCincin;
        orb.animate([
            { transform: 'translate3d(' + (Math.cos(a.sudut) * r0) + 'px,' + (Math.sin(a.sudut) * r0) + 'px,0) scale(.35)', opacity: 0 },
            { transform: 'translate3d(' + (xC * 1.06) + 'px,' + (yC * 1.06) + 'px,0) scale(1)', opacity: 1, offset: .74 },
            { transform: 'translate3d(' + xC + 'px,' + yC + 'px,0) scale(.94)', opacity: .95 }
        ], { duration: 820, delay: i * 55, easing: 'cubic-bezier(.22,.8,.24,1)', fill: 'forwards' });
    });

    logo.animate([{ opacity: 0, transform: 'scale(.86)' }, { opacity: 1, transform: 'scale(1)' }],
        { duration: 620, delay: 520, easing: 'ease-out', fill: 'forwards' });
    caption.animate([{ opacity: 0 }, { opacity: 1 }],
        { duration: 380, delay: 700, easing: 'ease-out', fill: 'forwards' });

    // 2) Orb menyebar keluar, lalu halaman chatbot ditampilkan.
    window.setTimeout(function () {
        orbs.forEach(function (orb, i) {
            var a = arah[i];
            var r1 = JARAK * (1.35 + (i % 4) * 0.14);
            var xC = Math.cos(a.sudut) * rCincin;
            var yC = Math.sin(a.sudut) * rCincin;
            orb.animate([
                { transform: 'translate3d(' + xC + 'px,' + yC + 'px,0) scale(.94)', opacity: .95 },
                { transform: 'translate3d(' + (Math.cos(a.sudut) * r1) + 'px,' + (Math.sin(a.sudut) * r1) + 'px,0) scale(.16)', opacity: 0 }
            ], { duration: 620, delay: i * 18, easing: 'cubic-bezier(.4,0,.68,1)', fill: 'forwards' });
        });
        logo.animate([{ opacity: 1, transform: 'scale(1)' }, { opacity: 0, transform: 'scale(1.14)' }],
            { duration: 420, easing: 'ease-in', fill: 'forwards' });
        caption.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 240, fill: 'forwards' });
    }, 1700);

    window.setTimeout(function () { tutup(false); }, 2150);
})();
</script>
@endif

<div class="chart-lightbox" id="chart-lightbox" hidden role="dialog" aria-modal="true" aria-label="Pratinjau grafik">
    <button type="button" class="chart-lightbox-tutup" id="chart-lightbox-tutup" aria-label="Tutup pratinjau grafik">
        <i class="fas fa-times" aria-hidden="true"></i>
    </button>
    <img id="chart-lightbox-img" src="" alt="">
    <span class="chart-lightbox-hint">Klik di luar gambar atau tekan Esc untuk menutup</span>
</div>

<script>
(function () {
    var kotak = document.getElementById('chart-lightbox');
    var gambar = document.getElementById('chart-lightbox-img');
    if (!kotak || !gambar) { return; }
    var pemicuTerakhir = null;

    function buka(src, alt, pemicu) {
        if (!src) { return; }
        gambar.src = src;
        gambar.alt = alt || 'Grafik';
        kotak.hidden = false;
        pemicuTerakhir = pemicu || null;
        document.body.style.overflow = 'hidden';
        var tombol = document.getElementById('chart-lightbox-tutup');
        if (tombol) { tombol.focus(); }
    }

    function tutup() {
        kotak.hidden = true;
        gambar.removeAttribute('src');
        document.body.style.overflow = '';
        if (pemicuTerakhir && pemicuTerakhir.focus) { pemicuTerakhir.focus(); }
        pemicuTerakhir = null;
    }

    /* Delegasi: berlaku untuk bubble lama (server) maupun bubble baru (JS). */
    document.addEventListener('click', function (e) {
        var tombol = e.target.closest ? e.target.closest('[data-chart-zoom]') : null;
        if (!tombol) { return; }
        e.preventDefault();
        buka(tombol.getAttribute('data-chart-src'), tombol.getAttribute('data-chart-alt'), tombol);
    });

    kotak.addEventListener('click', function (e) {
        /* Klik pada gambar sendiri tidak menutup. */
        if (e.target === gambar) { return; }
        tutup();
    });

    var tombolTutup = document.getElementById('chart-lightbox-tutup');
    if (tombolTutup) { tombolTutup.addEventListener('click', tutup); }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !kotak.hidden) { tutup(); }
    });
})();
</script>

@include('layout.footerscript')
</body>
</html>
