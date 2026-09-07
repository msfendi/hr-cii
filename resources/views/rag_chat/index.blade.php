<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<style>
    .chat-card {
        height: calc(100vh - 180px);
        display: flex;
        flex-direction: column;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        background-color: #f8fafc;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .chat-bubble {
        max-width: 82%;
        padding: 14px 18px;
        border-radius: 14px;
        font-size: 14px;
        line-height: 1.5;
        position: relative;
        animation: fadeIn 0.25s ease-in-out;
    }
    .chat-bubble.user {
        align-self: flex-end;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #ffffff;
        border-bottom-right-radius: 2px;
    }
    .chat-bubble.assistant {
        align-self: flex-start;
        background: #ffffff;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 2px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .chat-bubble.assistant table {
        width: 100%;
        margin: 12px 0;
        border-collapse: collapse;
        font-size: 12px;
    }
    .chat-bubble.assistant th, .chat-bubble.assistant td {
        border: 1px solid #cbd5e1;
        padding: 6px 10px;
        text-align: left;
    }
    .chat-bubble.assistant th {
        background-color: #f1f5f9;
        font-weight: 600;
    }
    .sql-badge-box {
        background: #1e293b;
        color: #38bdf8;
        padding: 10px 14px;
        border-radius: 8px;
        font-family: 'Courier New', Courier, monospace;
        font-size: 11px;
        margin-top: 10px;
        overflow-x: auto;
    }
    .doc-card {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        padding: 12px 16px;
        margin-top: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .doc-card.pdf {
        background: #fef2f2;
        border-color: #fecaca;
    }
    .quick-pill {
        cursor: pointer;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #475569;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        transition: all 0.2s ease;
        display: inline-block;
        margin-right: 6px;
        margin-bottom: 6px;
    }
    .quick-pill:hover {
        background: #3b82f6;
        color: #ffffff;
        border-color: #3b82f6;
    }
    .typing-indicator span {
        display: inline-block;
        width: 6px;
        height: 6px;
        background-color: #94a3b8;
        border-radius: 50%;
        margin-right: 3px;
        animation: typing 1s infinite alternate;
    }
    .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typing {
        from { transform: translateY(0); opacity: 0.4; }
        to { transform: translateY(-6px); opacity: 1; }
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')

                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h1 class="h4 mb-0 text-gray-800 font-weight-bold">
                                <i class="fas fa-robot text-primary mr-2"></i>AI Assistant & RAG Analytics
                            </h1>
                            <p class="text-muted small mb-0">Tanya kebijakan/SOP, query database live seluruh modul, dan auto-generate dokumen PDF/Excel.</p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary shadow-sm mr-2" data-toggle="modal" data-target="#knowledgeModal">
                                <i class="fas fa-book mr-1"></i> Knowledge Base ({{ $knowledgeBases->count() }})
                            </button>
                            <button type="button" id="btnClearChat" class="btn btn-sm btn-outline-danger shadow-sm">
                                <i class="fas fa-trash-alt mr-1"></i> Reset Chat
                            </button>
                        </div>
                    </div>

                    <!-- Main Chat Card -->
                    <div class="card chat-card">
                        <!-- Chat Message Area -->
                        <div class="chat-messages" id="chatMessages">
                            @if($messages->isEmpty())
                                <div class="text-center my-auto py-5" id="emptyState">
                                    <div class="mb-3">
                                        <i class="fas fa-sparkles text-primary fa-3x"></i>
                                    </div>
                                    <h5 class="font-weight-bold text-gray-800">Halo! Ada yang bisa saya bantu hari ini?</h5>
                                    <p class="text-muted small max-w-lg mx-auto" style="max-width: 550px;">
                                        Saya dapat menjawab pertanyaan operasional, melakukan query data secara otomatis dari database sistem HR-CII, serta membuatkan dokumen laporan (PDF & Excel) siap unduh.
                                    </p>
                                    <div class="mt-4">
                                        <div class="small font-weight-bold text-gray-600 mb-2">Coba pertanyaan cepat berikut:</div>
                                        <div>
                                            <span class="quick-pill" onclick="sendQuickMessage('Berapa jumlah karyawan aktif saat ini per departemen?')">
                                                📊 Jumlah Karyawan per Dept
                                            </span>
                                            <span class="quick-pill" onclick="sendQuickMessage('Buatkan laporan rekap PKWT aktif dalam format PDF')">
                                                📄 Export Rekap PKWT (PDF)
                                            </span>
                                            <span class="quick-pill" onclick="sendQuickMessage('Export daftar karyawan departemen IT ke Excel')">
                                                📗 Export Karyawan (Excel)
                                            </span>
                                            <span class="quick-pill" onclick="sendQuickMessage('Tampilkan data karyawan yang terlambat minggu ini')">
                                                🕒 Karyawan Terlambat
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @else
                                @foreach($messages as $msg)
                                    <div class="chat-bubble {{ $msg->role }}">
                                        @if($msg->role === 'assistant')
                                            <div class="d-flex align-items-center mb-1 text-primary small font-weight-bold">
                                                <i class="fas fa-robot mr-1"></i> HR-CII AI
                                                @if($msg->intent)
                                                    <span class="badge badge-light ml-2 border text-muted" style="font-size: 10px;">{{ strtoupper($msg->intent) }}</span>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="message-content">
                                            {!! nl2br(e($msg->content)) !!}
                                        </div>

                                        @if(!empty($msg->executed_sql))
                                            <div class="sql-badge-box">
                                                <div class="d-flex justify-content-between text-muted small mb-1">
                                                    <span><i class="fas fa-database mr-1"></i> Executed SQL:</span>
                                                </div>
                                                <code>{{ $msg->executed_sql }}</code>
                                            </div>
                                        @endif

                                        @if(!empty($msg->generated_document_url))
                                            @php
                                                $isPdf = $msg->generated_document_type === 'pdf';
                                            @endphp
                                            <div class="doc-card {{ $isPdf ? 'pdf' : 'excel' }}">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas {{ $isPdf ? 'fa-file-pdf text-danger' : 'fa-file-excel text-success' }} fa-2x mr-3"></i>
                                                    <div>
                                                        <div class="font-weight-bold text-gray-800 small">{{ $msg->generated_document_filename ?? 'Dokumen Laporan' }}</div>
                                                        <div class="text-muted" style="font-size: 11px;">Format: {{ strtoupper($msg->generated_document_type) }}</div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <a href="{{ route('rag-chat.download', ['filename' => $msg->generated_document_filename]) }}" class="btn btn-sm {{ $isPdf ? 'btn-danger' : 'btn-success' }} shadow-sm">
                                                        <i class="fas fa-download mr-1"></i> Unduh File
                                                    </a>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="text-right mt-1 text-muted" style="font-size: 10px;">
                                            {{ $msg->created_at->format('H:i') }}
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>

                        <!-- Typing Indicator -->
                        <div id="typingIndicator" class="px-4 py-2 d-none" style="background-color: #f8fafc;">
                            <div class="chat-bubble assistant py-2 px-3">
                                <div class="typing-indicator d-flex align-items-center">
                                    <span class="mr-1"></span>
                                    <span class="mr-1"></span>
                                    <span></span>
                                    <small class="text-muted ml-2 font-italic">AI sedang memproses permintaan & data...</small>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Input Box -->
                        <div class="p-3 bg-white border-top">
                            <form id="chatForm" class="d-flex align-items-center">
                                @csrf
                                <input type="hidden" id="sessionId" value="{{ $session->session_id }}">
                                <input
                                    type="text"
                                    id="messageInput"
                                    class="form-control mr-2 shadow-none"
                                    placeholder="Ketik pertanyaan atau minta dokumen (contoh: 'Buatkan rekap keterlambatan format PDF')..."
                                    autocomplete="off"
                                    required
                                >
                                <button type="submit" id="btnSend" class="btn btn-primary px-4 shadow-sm">
                                    <i class="fas fa-paper-plane mr-1"></i> Kirim
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @include('layout.footer')
        </div>
    </div>

    <!-- Modal Knowledge Base -->
    <div class="modal fade" id="knowledgeModal" tabindex="-1" role="dialog" aria-labelledby="knowledgeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold" id="knowledgeModalLabel">
                        <i class="fas fa-book text-primary mr-1"></i> RAG Knowledge Base Management
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Upload Section -->
                    <div class="card mb-4 bg-light border-0">
                        <div class="card-body">
                            <h6 class="font-weight-bold text-gray-800 mb-2">Unggah Dokumen SOP / Kebijakan Baru</h6>
                            <form id="uploadKnowledgeForm" enctype="multipart/form-data">
                                @csrf
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label class="small font-weight-bold">Judul Dokumen</label>
                                        <input type="text" name="title" class="form-control form-control-sm" placeholder="Contoh: SOP Cuti & Izin 2026" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label class="small font-weight-bold">Kategori</label>
                                        <select name="category" class="form-control form-control-sm">
                                            <option value="policy">Kebijakan Perusahaan</option>
                                            <option value="sop">SOP & Petunjuk Teknis</option>
                                            <option value="manual">Manual Book</option>
                                            <option value="general">Umum</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="small font-weight-bold">Pilih File (PDF, TXT, DOCX)</label>
                                    <input type="file" name="file" class="form-control-file" accept=".pdf,.txt,.doc,.docx" required>
                                </div>
                                <button type="submit" id="btnUploadKnowledge" class="btn btn-sm btn-primary">
                                    <i class="fas fa-cloud-upload-alt mr-1"></i> Proses & Ingest ke RAG
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Knowledge List -->
                    <h6 class="font-weight-bold text-gray-800 mb-2">Daftar Dokumen Pengetahuan Terdaftar</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>Judul</th>
                                    <th>Kategori</th>
                                    <th>Total Chunks</th>
                                    <th>Tanggal Upload</th>
                                    <th style="width: 70px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="knowledgeListBody">
                                @forelse($knowledgeBases as $kb)
                                    <tr id="kb-row-{{ $kb->id }}">
                                        <td><strong>{{ $kb->title }}</strong></td>
                                        <td><span class="badge badge-secondary">{{ $kb->category }}</span></td>
                                        <td>{{ $kb->total_chunks }} chunks</td>
                                        <td>{{ $kb->created_at->format('d/m/Y') }}</td>
                                        <td class="text-center">
                                            <button class="btn btn-xs btn-danger" onclick="deleteKnowledge({{ $kb->id }})">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">Belum ada dokumen yang diunggah.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('layout.footerscript')

    <!-- Marked.js for markdown rendering -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

    <script>
        const chatMessages = document.getElementById('chatMessages');
        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('messageInput');
        const sessionId = document.getElementById('sessionId').value;
        const typingIndicator = document.getElementById('typingIndicator');
        const btnSend = document.getElementById('btnSend');

        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        scrollToBottom();

        function sendQuickMessage(text) {
            messageInput.value = text;
            chatForm.dispatchEvent(new Event('submit'));
        }

        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (!message) return;

            // Remove empty state if present
            const emptyState = document.getElementById('emptyState');
            if (emptyState) emptyState.remove();

            // Append user bubble
            appendUserBubble(message);
            messageInput.value = '';
            messageInput.disabled = true;
            btnSend.disabled = true;

            // Show typing indicator
            typingIndicator.classList.remove('d-none');
            scrollToBottom();

            try {
                const response = await fetch("{{ route('rag-chat.send') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        message: message,
                        session_id: sessionId
                    })
                });

                const data = await response.json();
                typingIndicator.classList.add('d-none');

                if (data.success && data.message) {
                    appendAssistantBubble(data.message);
                } else {
                    appendAssistantBubble({
                        content: "Maaf, terjadi kesalahan: " + (data.message || "Gagal memproses pesan.")
                    });
                }
            } catch (err) {
                typingIndicator.classList.add('d-none');
                appendAssistantBubble({
                    content: "Terjadi gangguan jaringan atau error server. Silakan coba lagi."
                });
            } finally {
                messageInput.disabled = false;
                btnSend.disabled = false;
                messageInput.focus();
                scrollToBottom();
            }
        });

        function appendUserBubble(text) {
            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble user';
            bubble.innerHTML = `<div>${escapeHtml(text)}</div><div class="text-right mt-1 text-white-50" style="font-size: 10px;">${getCurrentTime()}</div>`;
            chatMessages.appendChild(bubble);
            scrollToBottom();
        }

        function appendAssistantBubble(msg) {
            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble assistant';

            let parsedContent = '';
            try {
                parsedContent = marked.parse(msg.content || '');
            } catch(e) {
                parsedContent = escapeHtml(msg.content || '').replace(/\n/g, '<br>');
            }

            let sqlHtml = '';
            if (msg.executed_sql) {
                sqlHtml = `
                    <div class="sql-badge-box">
                        <div class="d-flex justify-content-between text-muted small mb-1">
                            <span><i class="fas fa-database mr-1"></i> Executed SQL:</span>
                        </div>
                        <code>${escapeHtml(msg.executed_sql)}</code>
                    </div>
                `;
            }

            let docHtml = '';
            if (msg.generated_document_url) {
                const isPdf = msg.generated_document_type === 'pdf';
                docHtml = `
                    <div class="doc-card ${isPdf ? 'pdf' : 'excel'}">
                        <div class="d-flex align-items-center">
                            <i class="fas ${isPdf ? 'fa-file-pdf text-danger' : 'fa-file-excel text-success'} fa-2x mr-3"></i>
                            <div>
                                <div class="font-weight-bold text-gray-800 small">${escapeHtml(msg.generated_document_filename || 'Dokumen Laporan')}</div>
                                <div class="text-muted" style="font-size: 11px;">Format: ${(msg.generated_document_type || '').toUpperCase()}</div>
                            </div>
                        </div>
                        <div>
                            <a href="/ai-assistant/download/${msg.generated_document_filename}" class="btn btn-sm ${isPdf ? 'btn-danger' : 'btn-success'} shadow-sm">
                                <i class="fas fa-download mr-1"></i> Unduh File
                            </a>
                        </div>
                    </div>
                `;
            }

            const intentTag = msg.intent ? `<span class="badge badge-light ml-2 border text-muted" style="font-size: 10px;">${msg.intent.toUpperCase()}</span>` : '';

            bubble.innerHTML = `
                <div class="d-flex align-items-center mb-1 text-primary small font-weight-bold">
                    <i class="fas fa-robot mr-1"></i> HR-CII AI ${intentTag}
                </div>
                <div class="message-content">${parsedContent}</div>
                ${sqlHtml}
                ${docHtml}
                <div class="text-right mt-1 text-muted" style="font-size: 10px;">${msg.created_at || getCurrentTime()}</div>
            `;

            chatMessages.appendChild(bubble);
            scrollToBottom();
        }

        // Reset chat
        document.getElementById('btnClearChat').addEventListener('click', async function() {
            if (!confirm('Apakah Anda yakin ingin menghapus seluruh riwayat percakapan ini?')) return;

            try {
                const res = await fetch("{{ route('rag-chat.clear') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                const d = await res.json();
                if (d.success) {
                    location.reload();
                }
            } catch (e) {
                alert('Gagal membersihkan riwayat chat.');
            }
        });

        // Upload Knowledge
        document.getElementById('uploadKnowledgeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnUploadKnowledge');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Memproses Dokumen...';

            const formData = new FormData(this);

            try {
                const res = await fetch("{{ route('rag-chat.upload') }}", {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                const d = await res.json();
                if (d.success) {
                    alert(d.message);
                    location.reload();
                } else {
                    alert(d.message || 'Gagal mengunggah file.');
                }
            } catch (err) {
                alert('Terjadi kesalahan saat memproses file.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cloud-upload-alt mr-1"></i> Proses & Ingest ke RAG';
            }
        });

        async function deleteKnowledge(id) {
            if (!confirm('Hapus dokumen ini dari knowledge base?')) return;

            try {
                const res = await fetch(`/ai-assistant/knowledge/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                const d = await res.json();
                if (d.success) {
                    const row = document.getElementById(`kb-row-${id}`);
                    if (row) row.remove();
                }
            } catch (e) {
                alert('Gagal menghapus knowledge.');
            }
        }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return (text || '').toString().replace(/[&<>"']/g, m => map[m]);
        }

        function getCurrentTime() {
            const now = new Date();
            return now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
        }
    </script>
</body>
</html>
