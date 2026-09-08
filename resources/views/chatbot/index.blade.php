<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
<!-- Page Wrapper -->
@include('sweetalert::alert')
<div id="wrapper">
@include('layout.sidebar')
    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main Content -->
        <div id="content">
            @include('layout.navbar')
            <!-- Begin Page Content -->
            <div class="container-fluid">
                @php
                    $sessions = $sessions ?? collect();
                    $activeSession = $activeSession ?? null;

                    $exportLabels = [
                        'xlsx' => ['icon' => 'fa-file-excel', 'name' => 'Excel'],
                        'pdf' => ['icon' => 'fa-file-pdf', 'name' => 'PDF'],
                        'image' => ['icon' => 'fa-file-image', 'name' => 'Gambar'],
                    ];

                    // Sumber tunggal daftar AI provider: config('services.ai_providers')
                    // (lihat config/services.php). Hanya provider dengan 'enabled' => true
                    // yang ditampilkan, diurutkan berdasarkan 'order'. Saat ini cuma
                    // '9 Router' yang enabled, jadi itu satu-satunya (dan default)
                    // provider yang dipakai — tidak ada provider lain yang ditampilkan.
                    $aiProviders = collect(config('services.ai_providers', []))
                        ->map(function ($cfg, $key) {
                            $cfg['key'] = $key;
                            return $cfg;
                        })
                        ->filter(fn ($cfg) => ! empty($cfg['enabled']))
                        ->sortBy('order')
                        ->values();

                    $defaultProviderKey = $activeSession->ai_provider
                        ?? ($aiProviders->first()['key'] ?? 'router9');

                    // Semua provider (enabled maupun tidak) supaya sesi lama yang
                    // sudah tersimpan tetap bisa menampilkan label yang benar,
                    // bukan cuma provider yang saat ini aktif di form.
                    $allProviderLabels = collect(config('services.ai_providers', []))
                        ->map(fn ($cfg) => $cfg['label'] ?? null);

                    $providerLabel = fn ($key) => $allProviderLabels[$key] ?? ucfirst($key ?? '');
                @endphp

                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif

                @if ($message = Session::get('error'))
                <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif

                <!-- Chatbot Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-robot mr-1"></i> Chat Assistant
                        </h6>
                    </div>

                    <div class="card-body p-0">
                        <div id="chatbot-app"
                             class="cw-app row no-gutters"
                             data-active-session="{{ $activeSession->id ?? '' }}"
                             data-store-url="{{ route('chatbot.sessions.store') }}"
                             data-messages-url-template="{{ route('chatbot.sessions.messages', ['chatbotSession' => '__ID__']) }}"
                             data-send-url-template="{{ route('chatbot.sessions.send', ['chatbotSession' => '__ID__']) }}"
                             data-destroy-url-template="{{ route('chatbot.sessions.destroy', ['chatbotSession' => '__ID__']) }}"
                             data-index-url="{{ route('chatbot.index') }}"
                             data-provider-labels="{{ json_encode($allProviderLabels) }}">

                            {{-- ===================== SIDEBAR: RIWAYAT SESI ===================== --}}
                            <aside class="col-12 col-md-4 col-lg-3 cw-sidebar">
                                <div class="cw-sidebar-head">
                                    <span>Riwayat Chat</span>
                                    <button id="btn-new-chat" type="button" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus fa-sm"></i> Chat Baru
                                    </button>
                                </div>

                                <div id="session-list" class="cw-session-list">
                                    @forelse ($sessions as $s)
                                        <div class="cw-session-item-wrap">
                                            <button type="button"
                                                    class="cw-session-item {{ $activeSession && $activeSession->id === $s->id ? 'active' : '' }}"
                                                    data-session-id="{{ $s->id }}">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <span class="cw-session-title">{{ $s->resolveTitle() }}</span>
                                                    <span class="badge {{ $s->mode === 'rtg' ? 'badge-info' : 'badge-success' }} cw-session-badge">
                                                        {{ $s->mode === 'rtg' ? 'RTG' : 'Global' }}
                                                    </span>
                                                </div>
                                                <div class="cw-session-meta">
                                                    {{ $providerLabel($s->ai_provider) }} &middot; {{ $s->messages_count ?? $s->messages->count() ?? 0 }} pesan &middot; {{ $s->updated_at->diffForHumans() }}
                                                </div>
                                            </button>
                                            <button type="button" title="Hapus sesi"
                                                    class="cw-session-delete btn-delete-inline"
                                                    data-session-id="{{ $s->id }}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    @empty
                                        <p class="text-center text-muted small p-4 mb-0">
                                            Belum ada riwayat chat.<br>Mulai dari tombol "+ Chat Baru".
                                        </p>
                                    @endforelse
                                </div>
                            </aside>

                            {{-- ===================== PANEL UTAMA ===================== --}}
                            <section class="col-12 col-md-8 col-lg-9 cw-main">

                                <div id="new-session-panel"
                                     class="cw-new-session {{ $activeSession ? 'd-none' : '' }}"
                                     data-default-mode="{{ \App\Models\ChatbotSession::MODE_RTG }}"
                                     data-default-provider="{{ $defaultProviderKey }}">

                                    <div class="cw-overview text-center py-5 mx-auto" style="max-width:560px;">
                                        <div class="cw-overview-icon mb-3">
                                            <i class="fas fa-robot fa-2x text-primary"></i>
                                        </div>
                                        <h5 class="font-weight-bold mb-2">AI Assistant</h5>
                                        <p class="text-muted small mb-4">
                                            Tanya apa saja seputar data di sistem ini. AI akan membaca data
                                            (read-only) dan langsung menjawab pertanyaan Anda — hasilnya bisa
                                            diunduh saat relevan.
                                        </p>

                                        <ul class="list-unstyled small text-muted text-left mb-4 mx-auto" style="max-width:360px;">
                                            <li class="mb-2"><i class="fas fa-check text-success mr-2"></i>Tanya jawab data secara natural</li>
                                            <li class="mb-2"><i class="fas fa-check text-success mr-2"></i>Data hanya dibaca (read-only), aman dieksplorasi</li>
                                            <li class="mb-2"><i class="fas fa-check text-success mr-2"></i>Bisa lampirkan file (gambar, dokumen, dll)</li>
                                            <li class="mb-2"><i class="fas fa-check text-success mr-2"></i>AI bisa saja salah, tolong double check response</li>
                                        </ul>

                                        <button id="btn-start-chat" type="button" class="btn btn-primary">
                                            <i class="fas fa-paper-plane mr-1"></i> Mulai Chat
                                        </button>
                                        <p id="new-session-error" class="d-none mt-3 text-danger small"></p>
                                    </div>
                                </div>

                                {{-- Panel chat aktif --}}
                                <div id="chat-panel" class="cw-chat-panel {{ $activeSession ? '' : 'd-none' }}">
                                    <div class="cw-chat-head">
                                        <div>
                                            <p id="chat-title" class="mb-0 font-weight-bold text-gray-800">{{ $activeSession?->resolveTitle() ?? 'Chat baru' }}</p>
                                            <p class="mb-0 text-muted" style="font-size:11.5px;">
                                                <span id="chat-mode-badge">{{ $activeSession && $activeSession->mode === 'rtg' ? 'RTG (read-only)' : 'Global' }}</span>
                                                &middot; <span id="chat-provider-badge">{{ $activeSession ? $providerLabel($activeSession->ai_provider) : '' }}</span>
                                            </p>
                                        </div>
                                        <button id="btn-delete-session" type="button" class="btn btn-link btn-sm text-danger">
                                            <i class="fas fa-trash-alt mr-1"></i> Hapus sesi
                                        </button>
                                    </div>

                                    <div id="chat-messages" class="cw-window">
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

                                                <div class="cw-row {{ $isUser ? 'cw-row--user' : 'cw-row--ai' }}">
                                                    <div class="cw-avatar {{ $isUser ? 'cw-avatar--user' : 'cw-avatar--ai' }}">
                                                        <i class="fas {{ $isUser ? 'fa-user' : 'fa-robot' }}"></i>
                                                    </div>

                                                    <div class="cw-bubble-col">
                                                        <div class="cw-bubble {{ $isUser ? 'cw-bubble--user' : 'cw-bubble--ai' }}">
                                                            @if ($isUser)
                                                                @if (trim((string) $message->content) !== '')
                                                                    <div class="cw-plain">{{ $message->content }}</div>
                                                                @endif
                                                                @if (! empty($meta['attachments']))
                                                                    <div class="cw-attachments">
                                                                        @foreach ($meta['attachments'] as $att)
                                                                            @if (($att['kind'] ?? null) === 'image')
                                                                                <a href="{{ $att['url'] }}" target="_blank" rel="noopener" class="cw-attach-image">
                                                                                    <img src="{{ $att['url'] }}" alt="{{ $att['original_name'] }}">
                                                                                </a>
                                                                            @else
                                                                                <a href="{{ $att['url'] }}" target="_blank" rel="noopener" class="cw-attach-file">
                                                                                    <i class="fas {{ match($att['kind'] ?? 'document') { 'video' => 'fa-file-video', 'audio' => 'fa-file-audio', default => 'fa-file-lines' } }}"></i>
                                                                                    <span class="cw-attach-file-name">{{ $att['original_name'] }}</span>
                                                                                </a>
                                                                            @endif
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            @else
                                                                <div class="cw-markdown">
                                                                    {!! $renderedContent !!}
                                                                </div>
                                                            @endif

                                                            @if (! empty($export) && $export['type'] === 'html_table')
                                                                <p class="cw-data-caption">
                                                                    <i class="fas fa-table"></i>
                                                                    <span>Hasil data &middot; {{ $export['row_count'] ?? 0 }} baris</span>
                                                                </p>
                                                                {!! $export['html'] !!}
                                                            @elseif (! empty($export))
                                                                @php $label = $exportLabels[$export['type']] ?? ['icon' => 'fa-file', 'name' => strtoupper($export['type'])]; @endphp
                                                                <a href="{{ $export['url'] }}" target="_blank" rel="noopener" class="cw-export">
                                                                    <span class="cw-export-icon cw-export-icon--{{ $export['type'] }}">
                                                                        <i class="fas {{ $label['icon'] }}"></i>
                                                                    </span>
                                                                    <span class="cw-export-info">
                                                                        <span class="cw-export-name">{{ $export['filename'] }}</span>
                                                                        <span class="cw-export-meta">
                                                                            {{ $label['name'] }}
                                                                            @if (! empty($export['row_count']))
                                                                                &middot; {{ $export['row_count'] }} baris
                                                                            @endif
                                                                            &middot; Klik untuk unduh
                                                                        </span>
                                                                    </span>
                                                                    <i class="fas fa-download cw-export-download"></i>
                                                                </a>
                                                            @endif

                                                            @if (! empty($meta['sql']))
                                                                <details class="cw-details">
                                                                    <summary>Lihat query yang dijalankan</summary>
                                                                    <pre>{{ $meta['sql'] }}</pre>
                                                                </details>
                                                            @endif

                                                            @if (! empty($meta['error']))
                                                                <p class="cw-error">
                                                                    <i class="fas fa-triangle-exclamation"></i>
                                                                    <span>Catatan: {{ $meta['error'] }}</span>
                                                                </p>
                                                            @endif
                                                        </div>

                                                        @if (! empty($message->created_at))
                                                            <span class="cw-time">{{ $message->created_at->format('H:i') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @empty
                                                <p class="text-center text-muted small mt-5">Belum ada pesan. Mulai ketik di bawah.</p>
                                            @endforelse
                                        @endif
                                    </div>

                                    <div class="cw-composer">
                                        <div id="attach-list" class="cw-attach-list d-none"></div>
                                        <p id="attach-error" class="d-none mb-2 text-danger small"></p>
                                        <form id="chat-form" class="d-flex align-items-end">
                                            <input type="file" id="attach-input" class="d-none" multiple
                                                   accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt,.ods,.odp,.mp4,.mov,.avi,.mkv,.webm,.mp3,.wav,.ogg">
                                            <button id="btn-attach" type="button" class="btn btn-outline-secondary cw-attach-btn" title="Lampirkan file">
                                                <i class="fas fa-paperclip"></i>
                                            </button>
                                            <textarea id="chat-input" rows="1"
                                                      placeholder="Tulis pesan atau lampirkan file... (Enter untuk kirim, Shift+Enter untuk baris baru)"
                                                      class="form-control cw-textarea"></textarea>
                                            <button id="btn-send" type="submit" class="btn btn-primary cw-send-btn">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                        <p id="chat-typing" class="d-none mt-2 mb-0 text-muted" style="font-size:11.5px;">
                                            <span class="cw-typing"><span></span><span></span><span></span></span>
                                            AI sedang mengetik...
                                        </p>
                                        <p class="cw-attach-hint mt-2 mb-0">
                                            AI bisa saja salah, tolong double check response. Bisa juga tarik &amp; lepas file ke area chat. Format didukung: gambar, dokumen
                                            (doc/xlsx/ppt/pdf/txt/csv), audio, video &middot; maks 5 file, 25MB/file.
                                        </p>
                                    </div>

                                    <div id="drop-overlay" class="cw-drop-overlay d-none">
                                        <div class="cw-drop-overlay-inner">
                                            <i class="fas fa-cloud-arrow-up"></i>
                                            <span>Lepas file di sini untuk melampirkan</span>
                                        </div>
                                    </div>
                                </div>
                            </section>

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->
    </div>
    <!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->

@include('layout.footer')
</body>
<style>
    /* ---------- Shell 2 kolom (sidebar sesi + panel chat) ---------- */
    .cw-app { min-height: 78vh; }

    .cw-sidebar {
        border-right: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        max-height: 78vh;
    }

    .cw-sidebar-head {
        padding: 14px 16px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 600;
        font-size: 13px;
        color: #374151;
    }

    .cw-session-list {
        flex: 1;
        overflow-y: auto;
    }

    .cw-session-item-wrap {
        position: relative;
    }

    .cw-session-item-wrap:hover .cw-session-delete { opacity: 1; }

    .cw-session-item {
        width: 100%;
        text-align: left;
        background: none;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        padding: 10px 34px 10px 16px;
        cursor: pointer;
    }

    .cw-session-item:hover { background: #f8fafc; }
    .cw-session-item.active { background: #eff6ff; }

    .cw-session-title {
        font-size: 13px;
        font-weight: 600;
        color: #1f2937;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 150px;
    }

    .cw-session-badge { font-size: 9.5px; }

    .cw-session-meta {
        margin-top: 3px;
        font-size: 11px;
        color: #94a3b8;
    }

    .cw-session-delete {
        position: absolute;
        top: 10px;
        right: 8px;
        background: none;
        border: none;
        color: #cbd5e1;
        font-size: 11px;
        opacity: 0;
        transition: opacity .15s ease, color .15s ease;
    }

    .cw-session-delete:hover { color: #ef4444; }

    /* ---------- Panel utama ---------- */
    .cw-main {
        display: flex;
        flex-direction: column;
        max-height: 78vh;
    }

    .cw-new-session { padding: 28px 26px; overflow-y: auto; }

    .cw-mode-card {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 4px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px;
        cursor: pointer;
        margin-bottom: 0;
        transition: border-color .15s ease;
    }
    .cw-mode-card:hover { border-color: #93c5fd; }
    .cw-mode-card input[type="radio"] { position: absolute; top: 14px; right: 14px; }
    .cw-mode-card input[type="radio"]:checked ~ .cw-mode-card-title { color: #2563eb; }
    .cw-mode-card:has(input:checked) { border-color: #2563eb; box-shadow: 0 0 0 1px #2563eb inset; }
    .cw-mode-card-title { font-weight: 600; font-size: 13.5px; color: #1f2937; }
    .cw-mode-card-desc { font-size: 11.5px; color: #64748b; }

    .cw-chat-panel {
        display: flex;
        flex-direction: column;
        height: 78vh;
        position: relative;
    }

    .cw-chat-head {
        padding: 12px 18px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .cw-composer {
        border-top: 1px solid #e5e7eb;
        padding: 12px 16px;
        flex-shrink: 0;
        background: #fff;
    }

    .cw-textarea {
        resize: none;
        max-height: 120px;
        border-radius: 10px;
        font-size: 13px;
        margin-right: 8px;
    }

    .cw-send-btn {
        border-radius: 10px;
        width: 40px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .cw-attach-btn {
        border-radius: 10px;
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-right: 8px;
        color: #64748b;
    }
    .cw-attach-btn:hover { color: #1f2937; }

    .cw-attach-hint {
        font-size: 10.5px;
        color: #94a3b8;
    }

    /* ---------- Chip lampiran yang dipilih (belum dikirim) ---------- */
    .cw-attach-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 8px;
    }

    .cw-attach-chip {
        display: flex;
        align-items: center;
        gap: 6px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 4px 8px;
        font-size: 11.5px;
        color: #334155;
        max-width: 220px;
    }

    .cw-attach-chip img {
        width: 22px;
        height: 22px;
        object-fit: cover;
        border-radius: 4px;
        flex-shrink: 0;
    }

    .cw-attach-chip .cw-attach-chip-icon {
        width: 22px;
        height: 22px;
        border-radius: 4px;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #64748b;
        font-size: 11px;
    }

    .cw-attach-chip-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cw-attach-chip-remove {
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 11px;
        line-height: 1;
        padding: 2px;
        flex-shrink: 0;
    }
    .cw-attach-chip-remove:hover { color: #ef4444; }

    /* ---------- Lampiran yang sudah dikirim, tampil di dalam bubble ---------- */
    .cw-attachments {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 6px;
    }

    .cw-attach-image {
        display: block;
        width: 96px;
        height: 96px;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.08);
    }

    .cw-attach-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .cw-attach-file {
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(0, 0, 0, 0.04);
        border-radius: 8px;
        padding: 6px 10px;
        font-size: 12px;
        max-width: 220px;
        color: inherit;
    }
    .cw-bubble--user .cw-attach-file { background: rgba(255, 255, 255, 0.16); color: #fff; }
    .cw-attach-file:hover { text-decoration: none; opacity: .85; }

    .cw-attach-file-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* ---------- Overlay drag & drop di atas panel chat ---------- */
    .cw-drop-overlay {
        position: absolute;
        inset: 0;
        background: rgba(37, 99, 235, 0.08);
        border: 2px dashed #3b82f6;
        border-radius: 6px;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }

    .cw-drop-overlay-inner {
        background: #fff;
        border-radius: 10px;
        padding: 16px 22px;
        box-shadow: 0 6px 20px rgba(15, 23, 42, 0.15);
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13.5px;
        font-weight: 600;
        color: #1d4ed8;
    }
    .cw-drop-overlay-inner i { font-size: 20px; }

    /* ---------- Bubble chat (dari _message_styles.blade.php) ---------- */
    .cw-window {
        display: flex;
        flex-direction: column;
        gap: 14px;
        background: #f8fafc;
        flex: 1;
        overflow-y: auto;
        padding: 18px;
    }

    .cw-row { display: flex; align-items: flex-end; gap: 8px; }
    .cw-row.cw-row--user { justify-content: flex-end; }

    .cw-avatar {
        flex: 0 0 30px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        color: #fff;
    }

    .cw-avatar--ai { background: linear-gradient(135deg, #1e3a8a, #2563eb); }
    .cw-avatar--user { background: #94a3b8; order: 2; }

    .cw-bubble-col { display: flex; flex-direction: column; max-width: 75%; }
    .cw-row--user .cw-bubble-col { align-items: flex-end; }

    .cw-bubble {
        border-radius: 16px;
        padding: 10px 14px;
        font-size: 13.5px;
        line-height: 1.55;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        word-wrap: break-word;
    }

    .cw-bubble--user { background: #2563eb; color: #fff; border-bottom-right-radius: 4px; }
    .cw-bubble--ai { background: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; border-bottom-left-radius: 4px; }

    .cw-plain { white-space: pre-wrap; }

    .cw-time { font-size: 10.5px; color: #94a3b8; margin-top: 3px; padding: 0 4px; }

    .cw-markdown p { margin: 0 0 8px; }
    .cw-markdown p:last-child { margin-bottom: 0; }
    .cw-markdown ul, .cw-markdown ol { margin: 0 0 8px; padding-left: 20px; }
    .cw-markdown li { margin-bottom: 2px; }
    .cw-markdown strong { color: #0f172a; }
    .cw-markdown a { color: #2563eb; text-decoration: underline; }
    .cw-markdown h1, .cw-markdown h2, .cw-markdown h3,
    .cw-markdown h4, .cw-markdown h5, .cw-markdown h6 { font-size: 14px; margin: 0 0 6px; color: #0f172a; }
    .cw-markdown blockquote { margin: 0 0 8px; padding: 4px 10px; border-left: 3px solid #cbd5e1; color: #475569; }
    .cw-markdown code { background: #f1f5f9; color: #db2777; padding: 1px 5px; border-radius: 4px; font-size: 12px; }
    .cw-markdown pre { background: #0f172a; color: #e2e8f0; padding: 10px 12px; border-radius: 8px; overflow-x: auto; margin: 0 0 8px; }
    .cw-markdown pre code { background: transparent; color: inherit; padding: 0; }
    .cw-markdown table { border-collapse: collapse; width: 100%; margin: 0; font-size: 12.5px; min-width: 100%; }
    .cw-markdown th, .cw-markdown td { padding: 7px 11px; text-align: left; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
    .cw-markdown thead th { background: #f1f5f9; color: #0f172a; font-weight: 600; border-bottom: 1px solid #cbd5e1; }
    .cw-markdown tbody tr:nth-child(even) { background: #f8fafc; }
    .cw-markdown tbody tr:hover { background: #eff6ff; }
    .cw-markdown tbody tr:last-child td { border-bottom: none; }

    .cw-markdown .cw-table-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 10px; margin: 0 0 8px; }
    .cw-markdown .cw-table-wrap:last-child { margin-bottom: 0; }

    .cw-bubble--user .cw-markdown .cw-table-wrap { border-color: rgba(255, 255, 255, 0.35); }
    .cw-bubble--user .cw-markdown thead th { background: rgba(255, 255, 255, 0.14); color: #fff; border-bottom-color: rgba(255, 255, 255, 0.35); }
    .cw-bubble--user .cw-markdown td { border-bottom-color: rgba(255, 255, 255, 0.14); }
    .cw-bubble--user .cw-markdown tbody tr:nth-child(even) { background: rgba(255, 255, 255, 0.06); }
    .cw-bubble--user .cw-markdown tbody tr:hover { background: rgba(255, 255, 255, 0.1); }

    .cw-details { margin-top: 8px; font-size: 11.5px; }
    .cw-details summary { cursor: pointer; list-style: none; display: inline-flex; align-items: center; gap: 5px; color: #64748b; font-weight: 500; }
    .cw-details summary::-webkit-details-marker { display: none; }
    .cw-details summary::before { content: '▸'; font-size: 10px; }
    .cw-details[open] summary::before { content: '▾'; }
    .cw-details pre { margin-top: 6px; background: #0f172a; color: #e2e8f0; padding: 8px 10px; border-radius: 6px; font-size: 11px; overflow-x: auto; white-space: pre-wrap; }

    .cw-error { margin-top: 8px; padding: 6px 10px; border-radius: 6px; background: #fef2f2; color: #b91c1c; font-size: 11.5px; display: flex; gap: 6px; align-items: flex-start; }
    .cw-bubble--user .cw-error { background: rgba(255, 255, 255, 0.16); color: #fee2e2; }

    .cw-export {
        margin-top: 10px; display: flex; align-items: center; gap: 10px;
        padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 10px;
        background: #f8fafc; text-decoration: none;
        transition: border-color .15s ease, background .15s ease;
    }
    .cw-export:hover { border-color: #93c5fd; background: #eff6ff; text-decoration: none; }
    .cw-export-icon { flex: 0 0 34px; width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 15px; color: #fff; }
    .cw-export-icon--xlsx { background: #16a34a; }
    .cw-export-icon--pdf { background: #dc2626; }
    .cw-export-icon--image { background: #7c3aed; }
    .cw-export-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .cw-export-name { font-size: 12.5px; font-weight: 600; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cw-export-meta { font-size: 11px; color: #64748b; }
    .cw-export-download { color: #2563eb; font-size: 13px; }

    .cw-data-caption { margin-top: 10px; margin-bottom: 6px; font-size: 11px; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 6px; }
    .cw-data-caption i { color: #2563eb; }
    .cw-data-table-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 10px; max-width: 100%; }
    .cw-data-table { border-collapse: collapse; width: 100%; min-width: 100%; font-size: 12.5px; }
    .cw-data-table thead th { position: sticky; top: 0; background: #f1f5f9; color: #0f172a; font-weight: 600; text-align: left; padding: 8px 12px; border-bottom: 1px solid #cbd5e1; white-space: nowrap; }
    .cw-data-table tbody td { padding: 6px 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b; white-space: nowrap; }
    .cw-data-table tbody tr:last-child td { border-bottom: none; }
    .cw-data-table tbody tr:nth-child(even) { background: #f8fafc; }
    .cw-data-table tbody tr:hover { background: #eff6ff; }
    .cw-bubble--user .cw-data-table-wrap { border-color: rgba(255, 255, 255, 0.35); }
    .cw-bubble--user .cw-data-table thead th { background: rgba(255, 255, 255, 0.14); color: #fff; }
    .cw-bubble--user .cw-data-table tbody td { color: #fff; border-bottom-color: rgba(255, 255, 255, 0.14); }
    .cw-bubble--user .cw-data-table tbody tr:nth-child(even) { background: rgba(255, 255, 255, 0.06); }

    .cw-window::-webkit-scrollbar { width: 8px; }
    .cw-window::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }

    .cw-typing { display: inline-flex; align-items: center; gap: 4px; }
    .cw-typing span { width: 5px; height: 5px; border-radius: 50%; background: #94a3b8; animation: cw-typing-bounce 1.2s infinite ease-in-out; }
    .cw-typing span:nth-child(2) { animation-delay: .15s; }
    .cw-typing span:nth-child(3) { animation-delay: .3s; }
    @keyframes cw-typing-bounce {
        0%, 60%, 100% { transform: translateY(0); opacity: .5; }
        30% { transform: translateY(-3px); opacity: 1; }
    }

    @media (max-width: 767px) {
        .cw-sidebar { max-height: 260px; border-right: none; border-bottom: 1px solid #e5e7eb; }
        .cw-chat-panel { height: 62vh; }
    }
</style>

{{--
    ============================================================
    SCRIPT — logika chat (session baru, kirim pesan, render bubble
    di client, dsb) diambil & disesuaikan dari index.blade.php milik
    project chatbot yang lama. Ditulis vanilla JS (fetch) supaya
    tidak bergantung ke library tambahan.
    ============================================================
--}}
<script>
(function () {
    const app = document.getElementById('chatbot-app');
    if (!app) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const storeUrl = app.dataset.storeUrl;
    const messagesUrlTpl = app.dataset.messagesUrlTemplate;
    const sendUrlTpl = app.dataset.sendUrlTemplate;
    const destroyUrlTpl = app.dataset.destroyUrlTemplate;
    const indexUrl = app.dataset.indexUrl;

    // Label provider AI (mis. { router9: "9 Router" }), sumbernya dari
    // config('services.ai_providers') lewat blade — dipakai supaya badge
    // provider di header chat tidak sekadar ucfirst(key) mentah.
    let providerLabels = {};
    try { providerLabels = JSON.parse(app.dataset.providerLabels || '{}'); } catch (e) { providerLabels = {}; }

    function providerLabel(key) {
        return providerLabels[key] || (key ? key.charAt(0).toUpperCase() + key.slice(1) : '');
    }

    const newSessionPanel = document.getElementById('new-session-panel');
    const chatPanel = document.getElementById('chat-panel');
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    const chatTyping = document.getElementById('chat-typing');
    const chatTitle = document.getElementById('chat-title');
    const chatModeBadge = document.getElementById('chat-mode-badge');
    const chatProviderBadge = document.getElementById('chat-provider-badge');
    const newSessionError = document.getElementById('new-session-error');
    const btnSend = document.getElementById('btn-send');
    const btnAttach = document.getElementById('btn-attach');
    const attachInput = document.getElementById('attach-input');
    const attachList = document.getElementById('attach-list');
    const attachError = document.getElementById('attach-error');
    const dropOverlay = document.getElementById('drop-overlay');

    let activeSessionId = app.dataset.activeSession || null;

    /*
    |----------------------------------------------------------------
    | Lampiran file — pilih lewat tombol paperclip ATAU drag & drop,
    | multi-file, dengan whitelist ekstensi & batas ukuran yang
    | mencerminkan validasi di sisi server (ChatbotController).
    | Semua validasi di sini hanya untuk UX (feedback cepat) — validasi
    | yang sesungguhnya (termasuk cek MIME asli file) tetap dilakukan
    | di server, jadi tidak bisa dilewati dari sisi client.
    |----------------------------------------------------------------
    */
    const MAX_ATTACHMENTS = 5;
    const MAX_ATTACHMENT_BYTES = 25 * 1024 * 1024; // 25 MB
    const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods', 'odp',
        'mp4', 'mov', 'avi', 'mkv', 'webm', 'mp3', 'wav', 'ogg',
    ];
    const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    let pendingFiles = [];

    function fileExtension(name) {
        const parts = (name || '').split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function fileKind(name) {
        const ext = fileExtension(name);
        if (IMAGE_EXTENSIONS.includes(ext)) return 'image';
        if (['mp4', 'mov', 'avi', 'mkv', 'webm'].includes(ext)) return 'video';
        if (['mp3', 'wav', 'ogg'].includes(ext)) return 'audio';
        return 'document';
    }

    function formatFileSize(bytes) {
        if (!bytes && bytes !== 0) return '';
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    function showAttachError(msg) {
        attachError.textContent = msg;
        attachError.classList.remove('d-none');
    }

    function clearAttachError() {
        attachError.classList.add('d-none');
        attachError.textContent = '';
    }

    function addFiles(fileList) {
        clearAttachError();
        const incoming = Array.from(fileList || []);
        const rejected = [];

        for (const file of incoming) {
            if (pendingFiles.length >= MAX_ATTACHMENTS) {
                rejected.push(`${file.name} (maksimal ${MAX_ATTACHMENTS} file)`);
                continue;
            }

            const ext = fileExtension(file.name);

            if (!ALLOWED_EXTENSIONS.includes(ext)) {
                rejected.push(`${file.name} (format tidak didukung)`);
                continue;
            }

            if (file.size > MAX_ATTACHMENT_BYTES) {
                rejected.push(`${file.name} (ukuran melebihi 25MB)`);
                continue;
            }

            pendingFiles.push(file);
        }

        if (rejected.length) {
            showAttachError(`Tidak bisa ditambahkan: ${rejected.join(', ')}`);
        }

        renderAttachList();
    }

    function removeFileAt(index) {
        pendingFiles.splice(index, 1);
        renderAttachList();
    }

    function attachChipIconHtml(kind) {
        const icon = kind === 'video' ? 'fa-file-video' : kind === 'audio' ? 'fa-file-audio' : 'fa-file-lines';
        return `<span class="cw-attach-chip-icon"><i class="fas ${icon}"></i></span>`;
    }

    function renderAttachList() {
        if (!pendingFiles.length) {
            attachList.innerHTML = '';
            attachList.classList.add('d-none');
            return;
        }

        attachList.classList.remove('d-none');
        attachList.innerHTML = pendingFiles.map((file, index) => {
            const kind = fileKind(file.name);
            const preview = kind === 'image'
                ? `<img src="${URL.createObjectURL(file)}" alt="">`
                : attachChipIconHtml(kind);

            return `<span class="cw-attach-chip" data-index="${index}">
                ${preview}
                <span class="cw-attach-chip-name" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</span>
                <span class="text-muted">${formatFileSize(file.size)}</span>
                <button type="button" class="cw-attach-chip-remove" data-remove-index="${index}"><i class="fas fa-times"></i></button>
            </span>`;
        }).join('');

        attachList.querySelectorAll('[data-remove-index]').forEach((btn) => {
            btn.addEventListener('click', () => removeFileAt(Number(btn.dataset.removeIndex)));
        });
    }

    if (btnAttach && attachInput) {
        btnAttach.addEventListener('click', () => attachInput.click());
        attachInput.addEventListener('change', () => {
            addFiles(attachInput.files);
            attachInput.value = ''; // supaya bisa pilih file yang sama lagi kalau dihapus
        });
    }

    // Drag & drop file langsung ke area panel chat.
    if (chatPanel && dropOverlay) {
        let dragDepth = 0;

        const isFileDrag = (e) => Array.from(e.dataTransfer?.types || []).includes('Files');

        chatPanel.addEventListener('dragenter', (e) => {
            if (!activeSessionId || !isFileDrag(e)) return;
            e.preventDefault();
            dragDepth++;
            dropOverlay.classList.remove('d-none');
        });

        chatPanel.addEventListener('dragover', (e) => {
            if (!activeSessionId || !isFileDrag(e)) return;
            e.preventDefault();
        });

        chatPanel.addEventListener('dragleave', (e) => {
            if (!activeSessionId || !isFileDrag(e)) return;
            e.preventDefault();
            dragDepth = Math.max(0, dragDepth - 1);
            if (dragDepth === 0) dropOverlay.classList.add('d-none');
        });

        chatPanel.addEventListener('drop', (e) => {
            if (!activeSessionId || !isFileDrag(e)) return;
            e.preventDefault();
            dragDepth = 0;
            dropOverlay.classList.add('d-none');
            addFiles(e.dataTransfer.files);
        });
    }

    function api(url, options = {}) {
        return fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
        }).then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.message || 'Terjadi kesalahan.');
            }
            return data;
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    /*
    |----------------------------------------------------------------
    | Renderer markdown ringan untuk balasan AI di sisi client.
    | Menangani **bold**, *italic*, `code`, ```code block```,
    | list (- / 1.), [teks](url), dan tabel gaya GFM — supaya
    | menyamai hasil Str::markdown() di server tanpa library
    | tambahan. HTML di-escape lebih dulu supaya aman dari injeksi.
    |----------------------------------------------------------------
    */
    function renderMarkdown(raw) {
        let text = escapeHtml(raw);

        const codeBlocks = [];
        text = text.replace(/```([a-zA-Z0-9]*)\n?([\s\S]*?)```/g, (m, lang, code) => {
            codeBlocks.push(`<pre><code>${code.replace(/\n$/, '')}</code></pre>`);
            return `\u0000CB${codeBlocks.length - 1}\u0000`;
        });

        text = text.replace(/`([^`\n]+)`/g, '<code>$1</code>');
        text = text.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/__([^_\n]+)__/g, '<strong>$1</strong>');
        text = text.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
        text = text.replace(/(^|[^\w])_([^_\n]+)_(?!\w)/g, '$1<em>$2</em>');
        text = text.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

        const lines = text.split('\n');
        let html = '';
        let listBuffer = [];
        let listType = null;
        let paraBuffer = [];

        function flushList() {
            if (listBuffer.length) {
                html += `<${listType}>${listBuffer.map((li) => `<li>${li}</li>`).join('')}</${listType}>`;
            }
            listBuffer = [];
            listType = null;
        }

        function flushPara() {
            if (paraBuffer.length) {
                html += `<p>${paraBuffer.join('<br>')}</p>`;
            }
            paraBuffer = [];
        }

        function splitRow(line) {
            let l = line.trim();
            if (l.startsWith('|')) l = l.slice(1);
            if (l.endsWith('|')) l = l.slice(0, -1);
            return l.split('|').map((cell) => cell.trim());
        }

        function tryParseTable(startIndex) {
            const headerLine = lines[startIndex];
            const sepLine = lines[startIndex + 1];

            if (headerLine === undefined || sepLine === undefined) return null;
            if (!headerLine.includes('|') || !sepLine.includes('|')) return null;

            const sepCells = splitRow(sepLine);
            const isSeparator = sepCells.length > 0 && sepCells.every((c) => /^:?-{2,}:?$/.test(c));
            if (!isSeparator) return null;

            const headerCells = splitRow(headerLine);
            const aligns = sepCells.map((c) => {
                const left = c.startsWith(':');
                const right = c.endsWith(':');
                if (left && right) return 'center';
                if (right) return 'right';
                if (left) return 'left';
                return null;
            });

            let i = startIndex + 2;
            const bodyRows = [];
            while (i < lines.length && lines[i].trim() !== '' && lines[i].includes('|')) {
                bodyRows.push(splitRow(lines[i]));
                i++;
            }

            const alignAttr = (idx) => (aligns[idx] ? ` style="text-align:${aligns[idx]}"` : '');
            const theadCells = headerCells.map((cell, idx) => `<th${alignAttr(idx)}>${cell}</th>`).join('');
            const tbodyRows = bodyRows.map((cells) => {
                const tds = headerCells.map((_, idx) => `<td${alignAttr(idx)}>${cells[idx] ?? ''}</td>`).join('');
                return `<tr>${tds}</tr>`;
            }).join('');

            const tableHtml = `<div class="cw-table-wrap"><table><thead><tr>${theadCells}</tr></thead>`
                + `<tbody>${tbodyRows}</tbody></table></div>`;

            return { html: tableHtml, nextIndex: i };
        }

        let lineIndex = 0;
        while (lineIndex < lines.length) {
            const line = lines[lineIndex];
            const table = tryParseTable(lineIndex);

            if (table) {
                flushList();
                flushPara();
                html += table.html;
                lineIndex = table.nextIndex;
                continue;
            }

            const ulMatch = line.match(/^\s*[-*]\s+(.*)$/);
            const olMatch = line.match(/^\s*\d+\.\s+(.*)$/);

            if (ulMatch) {
                flushPara();
                if (listType !== 'ul') flushList();
                listType = 'ul';
                listBuffer.push(ulMatch[1]);
            } else if (olMatch) {
                flushPara();
                if (listType !== 'ol') flushList();
                listType = 'ol';
                listBuffer.push(olMatch[1]);
            } else if (line.trim() === '') {
                flushList();
                flushPara();
            } else {
                flushList();
                paraBuffer.push(line);
            }

            lineIndex++;
        }

        flushList();
        flushPara();

        html = html.replace(/\u0000CB(\d+)\u0000/g, (m, i) => codeBlocks[Number(i)]);
        return html;
    }

    const EXPORT_LABELS = {
        xlsx: { icon: 'fa-file-excel', name: 'Excel' },
        pdf: { icon: 'fa-file-pdf', name: 'PDF' },
        image: { icon: 'fa-file-image', name: 'Gambar' },
    };

    function formatTime(iso) {
        const date = iso ? new Date(iso) : new Date();
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    /**
     * Membangun markup bubble persis sama dengan blok Blade di atas,
     * supaya pesan yang di-append lewat JS tampil identik dengan yang
     * dirender server saat halaman di-reload.
     */
    function attachmentsHtml(attachments) {
        if (!attachments || !attachments.length) return '';

        const items = attachments.map((att) => {
            if (att.kind === 'image') {
                return `<a href="${att.url}" target="_blank" rel="noopener" class="cw-attach-image">
                    <img src="${att.url}" alt="${escapeHtml(att.original_name)}">
                </a>`;
            }

            const icon = att.kind === 'video' ? 'fa-file-video' : att.kind === 'audio' ? 'fa-file-audio' : 'fa-file-lines';

            return `<a href="${att.url}" target="_blank" rel="noopener" class="cw-attach-file">
                <i class="fas ${icon}"></i>
                <span class="cw-attach-file-name">${escapeHtml(att.original_name)}</span>
            </a>`;
        }).join('');

        return `<div class="cw-attachments">${items}</div>`;
    }

    function bubbleHtml(message) {
        const isUser = message.role === 'user';
        const meta = message.meta || {};
        const exportData = meta.export || null;
        const hasText = (message.content || '').trim() !== '';

        const contentHtml = isUser
            ? (hasText ? `<div class="cw-plain">${escapeHtml(message.content)}</div>` : '') + attachmentsHtml(meta.attachments)
            : `<div class="cw-markdown">${renderMarkdown(message.content)}</div>`;

        let extra = '';

        if (exportData && exportData.type === 'html_table') {
            extra += `<p class="cw-data-caption"><i class="fas fa-table"></i><span>Hasil data &middot; ${exportData.row_count || 0} baris</span></p>`;
            extra += exportData.html || '';
        } else if (exportData) {
            const label = EXPORT_LABELS[exportData.type] || { icon: 'fa-file', name: (exportData.type || '').toUpperCase() };
            const rowInfo = exportData.row_count ? ` &middot; ${exportData.row_count} baris` : '';

            extra += `<a href="${exportData.url}" target="_blank" rel="noopener" class="cw-export">
                <span class="cw-export-icon cw-export-icon--${exportData.type}"><i class="fas ${label.icon}"></i></span>
                <span class="cw-export-info">
                    <span class="cw-export-name">${escapeHtml(exportData.filename)}</span>
                    <span class="cw-export-meta">${label.name}${rowInfo} &middot; Klik untuk unduh</span>
                </span>
                <i class="fas fa-download cw-export-download"></i>
            </a>`;
        }

        if (meta.sql) {
            extra += `<details class="cw-details">
                <summary>Lihat query yang dijalankan</summary>
                <pre>${escapeHtml(meta.sql)}</pre>
            </details>`;
        }

        if (meta.error) {
            extra += `<p class="cw-error"><i class="fas fa-triangle-exclamation"></i><span>Catatan: ${escapeHtml(meta.error)}</span></p>`;
        }

        return `<div class="cw-row ${isUser ? 'cw-row--user' : 'cw-row--ai'}">
            <div class="cw-avatar ${isUser ? 'cw-avatar--user' : 'cw-avatar--ai'}">
                <i class="fas ${isUser ? 'fa-user' : 'fa-robot'}"></i>
            </div>
            <div class="cw-bubble-col">
                <div class="cw-bubble ${isUser ? 'cw-bubble--user' : 'cw-bubble--ai'}">
                    ${contentHtml}
                    ${extra}
                </div>
                <span class="cw-time">${formatTime(message.created_at)}</span>
            </div>
        </div>`;
    }

    function appendBubble(message) {
        chatMessages.insertAdjacentHTML('beforeend', bubbleHtml(message));
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function showChatPanel() {
        newSessionPanel.classList.add('d-none');
        chatPanel.classList.remove('d-none');
    }

    function showNewSessionPanel() {
        chatPanel.classList.add('d-none');
        newSessionPanel.classList.remove('d-none');
        newSessionError.classList.add('d-none');
    }

    function markActiveInSidebar(id) {
        document.querySelectorAll('.cw-session-item').forEach((el) => {
            el.classList.toggle('active', el.dataset.sessionId === String(id));
        });
    }

    async function openSession(id) {
        try {
            const data = await api(messagesUrlTpl.replace('__ID__', id));
            activeSessionId = id;

            chatTitle.textContent = data.session.title || 'Chat baru';
            chatModeBadge.textContent = data.session.mode === 'rtg' ? 'RTG (read-only)' : 'Global';
            chatProviderBadge.textContent = providerLabel(data.session.ai_provider);

            chatMessages.innerHTML = data.messages.length
                ? ''
                : '<p class="text-center text-muted small mt-5">Belum ada pesan. Mulai ketik di bawah.</p>';

            data.messages.forEach(appendBubble);

            showChatPanel();
            markActiveInSidebar(id);
        } catch (e) {
            alert(e.message);
        }
    }

    const btnNewChat = document.getElementById('btn-new-chat');
    if (btnNewChat) btnNewChat.addEventListener('click', showNewSessionPanel);

    const btnStartChat = document.getElementById('btn-start-chat');
    if (btnStartChat) {
        btnStartChat.addEventListener('click', async () => {
            // Tidak ada pilihan mode/provider di UI — langsung pakai default
            // yang disiapkan controller/blade (mode: RTG, provider: 9 Router).
            const mode = newSessionPanel?.dataset.defaultMode || 'rtg';
            const provider = newSessionPanel?.dataset.defaultProvider;

            if (!provider) {
                newSessionError.textContent = 'AI provider default belum dikonfigurasi. Hubungi admin.';
                newSessionError.classList.remove('d-none');
                return;
            }

            try {
                const data = await api(storeUrl, {
                    method: 'POST',
                    body: JSON.stringify({ mode, ai_provider: provider }),
                });

                window.location.href = `${indexUrl}?session=${data.session.id}`;
            } catch (e) {
                newSessionError.textContent = e.message;
                newSessionError.classList.remove('d-none');
            }
        });
    }

    document.querySelectorAll('.cw-session-item').forEach((el) => {
        el.addEventListener('click', () => openSession(el.dataset.sessionId));
    });

    document.querySelectorAll('.btn-delete-inline').forEach((el) => {
        el.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (!confirm('Hapus sesi chat ini beserta semua pesannya?')) return;

            const id = el.dataset.sessionId;
            await api(destroyUrlTpl.replace('__ID__', id), { method: 'DELETE' });
            window.location.href = indexUrl;
        });
    });

    const btnDeleteSession = document.getElementById('btn-delete-session');
    if (btnDeleteSession) {
        btnDeleteSession.addEventListener('click', async () => {
            if (!activeSessionId) return;
            if (!confirm('Hapus sesi chat ini beserta semua pesannya?')) return;

            await api(destroyUrlTpl.replace('__ID__', activeSessionId), { method: 'DELETE' });
            window.location.href = indexUrl;
        });
    }

    /**
     * Sama seperti api(), tapi kirim FormData (multipart) tanpa header
     * Content-Type manual — dibiarkan browser yang set otomatis beserta
     * boundary-nya, supaya file di dalam FormData terkirim dengan benar.
     */
    function apiMultipart(url, formData) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
        }).then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.message || 'Terjadi kesalahan.');
            }
            return data;
        });
    }

    if (chatForm) {
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = chatInput.value.trim();
            const files = pendingFiles.slice();
            if ((!text && !files.length) || !activeSessionId) return;

            clearAttachError();

            // Preview lokal supaya lampiran langsung terlihat di bubble
            // sebelum respons server datang (khusus gambar dipakai object
            // URL sementara supaya thumbnail langsung tampil).
            const localAttachments = files.map((file) => ({
                original_name: file.name,
                kind: fileKind(file.name),
                url: fileKind(file.name) === 'image' ? URL.createObjectURL(file) : '#',
            }));

            appendBubble({
                role: 'user',
                content: text,
                meta: localAttachments.length ? { attachments: localAttachments } : {},
                created_at: new Date().toISOString(),
            });

            chatInput.value = '';
            chatInput.style.height = 'auto';
            chatInput.disabled = true;
            btnSend.disabled = true;
            if (btnAttach) btnAttach.disabled = true;
            pendingFiles = [];
            renderAttachList();
            chatTyping.classList.remove('d-none');
            chatMessages.scrollTop = chatMessages.scrollHeight;

            try {
                let data;

                if (files.length) {
                    const formData = new FormData();
                    if (text) formData.append('message', text);
                    files.forEach((file) => formData.append('attachments[]', file));

                    data = await apiMultipart(sendUrlTpl.replace('__ID__', activeSessionId), formData);
                } else {
                    data = await api(sendUrlTpl.replace('__ID__', activeSessionId), {
                        method: 'POST',
                        body: JSON.stringify({ message: text }),
                    });
                }

                appendBubble(data.assistant_message);
                chatTitle.textContent = data.session.title || chatTitle.textContent;
            } catch (err) {
                appendBubble({ role: 'assistant', content: `Gagal mengirim pesan: ${err.message}`, meta: {}, created_at: new Date().toISOString() });
            } finally {
                chatTyping.classList.add('d-none');
                chatInput.disabled = false;
                btnSend.disabled = false;
                if (btnAttach) btnAttach.disabled = false;
                chatInput.focus();
            }
        });

        chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.requestSubmit();
            }
        });

        chatInput.addEventListener('input', () => {
            chatInput.style.height = 'auto';
            chatInput.style.height = `${chatInput.scrollHeight}px`;
        });
    }

    if (activeSessionId) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
})();
</script>
</html>