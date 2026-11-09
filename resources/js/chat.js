import { marked } from 'marked';
import DOMPurify from 'dompurify';
import hljs from 'highlight.js/lib/common';

marked.setOptions({ gfm: true, breaks: true });

export function renderMarkdown(text) {
    // Guard: jangan sampai objek/array bocor ke renderer sebagai teks.
    // marked.parse() men-stringify argumen non-string, yang sebelumnya
    // menampilkan literal "[object Object]" di bubble assistant.
    const safeText = typeof text === 'string'
        ? text
        : (Array.isArray(text)
            ? text.map((part) => (typeof part === 'string' ? part : (part?.text ?? ''))).join('')
            : '');
    const raw = marked.parse(safeText || '');
    return DOMPurify.sanitize(raw, {
        USE_PROFILES: { html: true },
        FORBID_TAGS: ['style', 'form', 'input', 'iframe', 'object', 'embed'],
        FORBID_ATTR: ['onerror', 'onload', 'onclick', 'style'],
    });
}

export function chatApp() {
    return {
        conversations: [],
        activeId: null,
        messages: [],
        input: '',
        loadingList: true,
        loadingMessages: false,
        streaming: false,
        _streams: {},
        /** UI getter: true kalau chat yang sedang dibuka punya stream aktif. */
        anyStreaming() { return !!(this._streams || {})[this.activeId]; },
        streamBuffer: '',
        modalUrl: null,
        sidebarOpen: false,
        renamingId: null,
        renameValue: '',
        search: '',
        error: '',
        abortController: null,
        pendingTimer: null,
        pendingFiles: [],
        dragging: false,
        showingSplash: true,
        MAX_FILES: 5,
        MAX_KB: 5120,
        OK_EXT: ['pdf','docx','xlsx','pptx','txt','md','csv','json','png','jpg','jpeg','gif','webp'],

        // ---- Mode 3D: kantor virtual ----
        officeOpen: false,
        officeReady: false,
        officeState: { rooms: [], viewer: {}, stats: {} },
        officeFocused: null,
        officeError: '',
        officeScene: null,
        officeTimer: null,

        pickFiles(e) {
            for (const f of e.target.files || []) {
                const ext = (f.name.split('.').pop() || '').toLowerCase();
                if (this.pendingFiles.length >= this.MAX_FILES) { this.error = 'Maksimal 5 file per pesan.'; break; }
                if (!this.OK_EXT.includes(ext)) { this.error = `Format .${ext} tidak didukung.`; continue; }
                if (f.size > this.MAX_KB * 1024) { this.error = `File "${f.name}" melebihi 5 MB.`; continue; }
                if (this.pendingFiles.some(p => p.name === f.name && p.size === f.size)) { continue; }
                this.pendingFiles.push(f);
                this.error = '';
            }
            e.target.value = '';
        },

        removeFile(i) { this.pendingFiles.splice(i, 1); },

        /** Drag & drop + paste: serahkan validasi ke pickFiles (ekstensi, ukuran, duplikat). */
        onDrop(e) {
            this.dragging = false;
            const files = [...(e.dataTransfer?.files || [])];
            if (files.length) this.addFiles(files);
        },
        onPaste(e) {
            const files = [...(e.clipboardData?.files || [])];
            if (files.length) { e.preventDefault(); this.addFiles(files); }
        },
        addFiles(files) {
            // pickFiles membaca e.target.files — samarkan objeknya.
            this.pickFiles({ target: { files, value: '' } });
        },

        fmtSize(bytes) {
            return bytes >= 1024 * 1024 ? (bytes / 1024 / 1024).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
        },

        /** URL inline utk lampiran bergambar; null utk dokumen biasa. */
        imgUrl(a) {
            // a.url = preview lokal (objectURL) sebelum upload ke server.
            if (a.url) return a.url;
            return (a.mime || '').startsWith('image/') ? '/api/attachments/' + a.id + '/preview' : null;
        },

        isVideo(a) {
            return (a.mime || '').startsWith('video/');
        },

        openModal(url) { this.modalUrl = url; },
        closeModal() { this.modalUrl = null; },

        csrf() {
            return document.head.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        async init() {
            // Splash screen: tampilkan 2.5 detik lalu hilangkan.
            setTimeout(() => { this.showingSplash = false; }, 2500);

            await this.fetchConversations();

            // Deep link /chat/{id}
            const m = window.location.pathname.match(/^\/chat\/(\d+)/);
            if (m) {
                await this.openConversation(parseInt(m[1], 10), false);
            }

            this.$nextTick(() => this.highlightAll());
        },

        async fetchConversations() {
            this.loadingList = true;
            try {
                const params = this.search ? { search: this.search } : {};
                const { data } = await axios.get('/api/conversations', { params });
                this.conversations = data.data;
            } finally {
                this.loadingList = false;
            }
        },

        debounceFetch() {
            clearTimeout(this._searchTimer);
            this._searchTimer = setTimeout(() => this.fetchConversations(), 300);
        },

        async newChat() {
            const { data } = await axios.post('/api/conversations');
            this.conversations.unshift({ ...data.data, messages_count: 0 });
            await this.openConversation(data.data.id, true);
            this.input = '';
            this.$refs.textarea?.focus();
        },

        async openConversation(id, pushUrl = true) {
            this.stopPendingWatch();
            this.activeId = id;
            this.error = '';
            this.messages = [];
            this.sidebarOpen = false;
            if (pushUrl) {
                history.pushState({}, '', `/chat/${id}`);
            }
            await this.loadMessages(id);
        },

        async loadMessages(id) {
            this.loadingMessages = true;
            try {
                const { data } = await axios.get(`/api/conversations/${id}/messages`, { params: { per_page: 100 } });
                // API mengembalikan oldest-first.
                // Kalau chat ini punya stream latar yang masih hidup, pakai
                // array-nya langsung supaya jawaban yang sedang jalan tidak hilang.
                const live = (this._streams || {})[id];
                this.messages = live ? live.msgs : (data.data || []);
                this.streaming = !!live;
            } finally {
                this.loadingMessages = false;
                this.$nextTick(() => {
                    this.highlightAll();
                    this.scrollBottom();
                });
                this.watchPending();
            }
        },

        /**
         * Pantau placeholder `pending` di conversation aktif.
         *
         * Kasus nyata: user refresh atau pindah tab saat AI masih menjawab.
         * Server tetap menyelesaikan jawaban (ignore_user_abort) dan menulisnya
         * ke DB, tapi browser sudah tidak punya koneksi SSE sehingga tidak tahu
         * kapan jawaban selesai — UI nyangkut di "Chutex AI sedang berpikir…"
         * sampai di-refresh manual. Polling ringan ini menutup celah itu:
         * begitu server menandai pesan selesai, jawaban langsung muncul.
         */
        watchPending() {
            this.stopPendingWatch();
            const id = this.activeId;
            if (!id || !this.messages.some(m => m.role === 'assistant' && m.status === 'pending')) return;

            this.pendingTimer = setInterval(() => this.syncPending(id), 2500);
        },

        stopPendingWatch() {
            if (this.pendingTimer) {
                clearInterval(this.pendingTimer);
                this.pendingTimer = null;
            }
        },

        async syncPending(id) {
            if (!id || id !== this.activeId) { this.stopPendingWatch(); return; }
            if (this.anyStreaming()) return;
            try {
                const { data } = await axios.get(`/api/conversations/${id}/messages`, { params: { per_page: 100 } });
                this.messages = data.data || [];
                this.$nextTick(() => { this.highlightAll(); this.scrollBottom(); });
                if (!this.messages.some(m => m.role === 'assistant' && m.status === 'pending')) {
                    this.stopPendingWatch();
                    await this.fetchConversations();
                }
            } catch {
                // Gagal sesaat tidak fatal — percobaan berikutnya menyusul.
            }
        },

        groupedConversations() {
            const groups = { Today: [], Yesterday: [], 'Previous 7 days': [], Older: [] };
            const now = new Date();
            const todayKey = now.toDateString();
            const yest = new Date(now); yest.setDate(now.getDate() - 1);
            const weekAgo = new Date(now); weekAgo.setDate(now.getDate() - 7);

            for (const c of this.conversations) {
                const d = new Date(c.last_message_at || c.updated_at);
                if (d.toDateString() === todayKey) groups.Today.push(c);
                else if (d.toDateString() === yest.toDateString()) groups.Yesterday.push(c);
                else if (d > weekAgo) groups['Previous 7 days'].push(c);
                else groups.Older.push(c);
            }
            return Object.entries(groups).filter(([, list]) => list.length > 0);
        },

        async send() {
            const content = this.input.trim();
            const files = this.pendingFiles.slice();
            if ((!content && files.length === 0) || this.anyStreaming() || !this.activeId) return;

            this.input = '';
            this.pendingFiles = [];
            this.autoGrow();
            this.error = '';
            // Bubble user langsung tampilkan lampiran (objectURL preview) —
            // tidak menunggu round-trip server.
            const previews = files.map((f, i) => ({
                id: 'local-' + i, original_name: f.name, size: f.size, mime: f.type || '',
                url: (f.type || '').startsWith('image/') ? URL.createObjectURL(f) : null,
            }));
            this.messages.push({ id: null, role: 'user', content, status: 'done', attachments: previews, created_at: new Date().toISOString() });
            await this.runStream(content, files, false);
        },

        /**
         * Alur SSE bersama untuk kirim biasa & regenerate.
         * regenerate=true: pesan user terakhir dipakai ulang oleh server,
         * jadi tidak ada bubble user duplikat (bug "response 3x").
         */
        async runStream(content, files, regenerate = false) {
            // Captured saat stream mulai: user boleh pindah/buat chat tanpa membuat
            // write di bawah ini out-of-range (bug lama: this.messages diganti ->
            // this.messages[draftIdx] undefined -> TypeError -> UI blank).
            const convId = this.activeId;
            const msgs = this.messages;
            this._streams = this._streams || {};
            this._streams[convId] = { ac: null, msgs };
            const mine = this._streams[convId];
            const fg = () => convId === this.activeId;
            this.streaming = fg();
            this.streamBuffer = '';
            msgs.push({ id: null, role: 'assistant', content: '', status: 'pending', created_at: new Date().toISOString() });
            const draft = msgs[msgs.length - 1];
            if (convId === this.activeId) this.scrollBottom();

            mine.ac = new AbortController();
            this.abortController = (convId === this.activeId) ? mine.ac : this.abortController;

            const useFiles = files.length > 0;
            let body;
            const headers = {
                'Accept': 'text/event-stream',
                'X-CSRF-TOKEN': this.csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            };
            if (useFiles) {
                body = new FormData();
                body.append('content', content);
                files.forEach(f => body.append('files[]', f));
                if (regenerate) body.append('regenerate', '1');
            } else {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify(regenerate ? { content, regenerate: true } : { content });
            }

            try {
                const res = await fetch(`/api/chat/${convId}`, {
                    method: 'POST',
                    signal: mine.ac.signal,
                    headers,
                    body,
                });

                if (res.status === 451 || (res.headers.get('content-type') || '').includes('application/json')) {
                    const payload = await res.json().catch(() => null);
                    if (payload?.blocked) {
                        msgs.splice(msgs.length - 1, 1); // buang draft kosong
                        msgs.push(payload.user_message, payload.assistant_message);
                        await this.fetchConversations();
                        if (fg()) this.scrollBottom();
                        return;
                    }
                    if (payload?.message && !payload.blocked) {
                        draft.content = payload.message;
                        draft.status = 'failed';
                        return;
                    }
                }

                if (!res.ok || !res.body) {
                    throw new Error('bad response ' + res.status);
                }

                const reader = res.body.getReader();
                const decoder = new TextDecoder();
                let buf = '';

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    buf += decoder.decode(value, { stream: true });

                    let sep;
                    while ((sep = buf.indexOf('\n\n')) !== -1) {
                        const chunk = buf.slice(0, sep);
                        buf = buf.slice(sep + 2);
                        this.handleSseEvent(chunk, draft, convId);
                    }
                    if (fg()) this.scrollBottom();
                }
            } catch (err) {
                if (err.name === 'AbortError') {
                    draft.status = 'stopped';
                    await this.stopServer(draft, convId);
                } else {
                    draft.content = draft.content || 'Maaf, Chutex AI sedang mengalami kendala. Silakan coba lagi beberapa saat lagi.';
                    draft.status = 'failed';
                }
            } finally {
                delete this._streams[convId];
                if (fg()) {
                    this.streaming = false;
                    this.abortController = null;
                    this.$nextTick(() => this.highlightAll());
                }
                await this.fetchConversations();
            }
        },

        handleSseEvent(raw, draft, convId = this.activeId) {
            let event = 'message';
            let data = '';
            for (const line of raw.split('\n')) {
                if (line.startsWith('event:')) event = line.slice(6).trim();
                else if (line.startsWith('data:')) data += line.slice(5).trim();
            }
            if (!data) return;
            let payload;
            try { payload = JSON.parse(data); } catch { return; }

            if (event === 'start') {
                // Server mengembalikan assistant_message_id placeholder;
                // sync ke DB draft agar id persisten (survive refresh).
                if (payload.assistant_message_id) {
                    draft.id = payload.assistant_message_id;
                    localStorage.setItem('streamMsgId_' + convId, payload.assistant_message_id);
                }
            } else if (event === 'delta') {
                draft.content = (draft.content || '') + payload.text;
            } else if (event === 'done') {
                draft.id = payload.assistant_message_id;
                draft.status = payload.status || 'done';
                // Server mengganti konten dgn versi bersih (token MEDIA: sudah dipanen
                // jadi lampiran) — pakai jika ada supaya teks mentah tak sempat tampil.
                if (payload.content) draft.content = payload.content;
                if (payload.attachments) draft.attachments = payload.attachments;
                // Opsi klarifikasi interaktif (tombol yang bisa diklik user).
                if (Array.isArray(payload.options)) draft.options = payload.options;
                // Bubble user: ganti preview lokal (objectURL) dengan attachment
                // server asli, revoke objectURL biar tidak bocor memori.
                if (payload.user_message_id) {
                    const ub = [...((this._streams[convId] || {}).msgs || this.messages)].reverse()
                        .find(m => m.role === 'user' && (m.id === null || m.id === payload.user_message_id));
                    if (ub) {
                        (ub.attachments || []).forEach(a => a.url && URL.revokeObjectURL(a.url));
                        ub.id = payload.user_message_id;
                        if (payload.user_attachments) ub.attachments = payload.user_attachments;
                    }
                }
                localStorage.removeItem('streamMsgId_' + convId);
            } else if (event === 'error') {
                draft.status = 'failed';
                if (!draft.content) draft.content = payload.fallback || 'Maaf, Chutex AI sedang mengalami kendala.';
                localStorage.removeItem('streamMsgId_' + convId);
            }
        },

        /**
         * Klik salah satu opsi klarifikasi: kirim label opsi sebagai pesan
         * user baru supaya AI menjawab sesuai arah yang dipilih.
         */
        async chooseOption(msg, option) {
            if (!option || this.anyStreaming() || !this.activeId) return;
            // Opsi yang sudah dipakai ditandai supaya tidak bisa diklik ulang.
            msg.options_used = true;
            this.input = option;
            await this.send();
        },

        /** Opsi klarifikasi yang masih bisa diklik (belum dipakai). */
        optionList(msg) {
            if (!msg || msg.role !== 'assistant') return [];
            if (!Array.isArray(msg.options) || msg.options.length === 0) return [];
            if (msg.options_used) return [];
            return msg.options;
        },

        async stopServer(draft, convId = this.activeId) {
            // Tanpa id server-side, tandai lokal; regenerate tetap tersedia.
            if (draft.id) {
                await fetch(`/api/conversations/${convId}/messages/${draft.id}/stop`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                }).catch(() => {});
            }
        },

        stop() {
            const s = this._streams && this._streams[this.activeId];
            s?.ac?.abort();
        },

        async regenerate() {
            if (!this.activeId || this.anyStreaming()) return;
            // Buang semua pesan assistant di akhir riwayat (termasuk
            // placeholder pending dari stream yang terputus / gagal),
            // lalu kirim ulang lewat SSE path yang sama.
            while (this.messages.length && this.messages[this.messages.length - 1].role === 'assistant') {
                this.messages.pop();
            }
            const lastUser = [...this.messages].reverse().find(m => m.role === 'user');
            if (!lastUser) { this.error = 'Tidak ada pesan untuk diulang.'; return; }

            await this.runStream(lastUser.content || '', [], true);
        },

        async rename(c) {
            const title = this.renameValue.trim();
            if (!title || title === c.title) { this.renamingId = null; return; }
            await axios.put(`/api/conversations/${c.id}`, { title });
            c.title = title;
            this.renamingId = null;
        },

        async remove(c) {
            if (!confirm(`Hapus chat "${c.title}"?`)) return;
            await axios.delete(`/api/conversations/${c.id}`);
            this.conversations = this.conversations.filter((x) => x.id !== c.id);
            if (this.activeId === c.id) {
                this.activeId = null;
                this.messages = [];
                history.pushState({}, '', '/chat');
            }
        },

        async copyText(msg) {
            await navigator.clipboard.writeText(msg.content);
            msg._copied = true;
            setTimeout(() => { msg._copied = false; }, 1500);
        },

        copyBlock(btn, pre) {
            const code = pre.querySelector('code')?.innerText ?? '';
            navigator.clipboard.writeText(code);
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = 'Copy'; }, 1500);
        },

        htmlFor(msg) {
            return renderMarkdown(msg.content);
        },

        highlightAll() {
            this.$el.querySelectorAll('.chat-md pre code:not([data-hl])').forEach((el) => {
                hljs.highlightElement(el);
                el.dataset.hl = '1';
                const pre = el.closest('pre');
                if (pre) {
                    const btn = document.createElement('button');
                    btn.className = 'code-copy';
                    btn.textContent = 'Copy';
                    btn.addEventListener('click', () => this.copyBlock(btn, pre));
                    pre.appendChild(btn);
                }
            });
        },

        scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.scroller;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        autoGrow() {
            const el = this.$refs.textarea;
            if (!el) return;
            // Reset ke 1 baris dulu supaya bisa shrink saat teks dihapus.
            el.style.height = 'auto';
            const h = Math.min(el.scrollHeight, 208); // max-h-52 = 13rem = 208px
            el.style.height = (h <= 40 ? '' : h + 'px'); // kosongkan kalau 1 baris biar CSS yang atur
        },

        onKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        },

        fmtTime(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        },

        // ---- Mode 3D: kantor virtual ----

        /** Buka panel kantor 3D. Scene dibuat lazily (biaya WebGL). */
        async openOffice() {
            this.officeOpen = true;
            this.officeError = '';
            await this.$nextTick();
            await this.initOfficeScene();
            await this.refreshOffice();
            this.startOfficePolling();
        },

        closeOffice() {
            this.officeOpen = false;
            this.stopOfficePolling();
        },

        toggleOffice() {
            if (this.officeOpen) this.closeOffice();
            else this.openOffice();
        },

        async initOfficeScene() {
            if (this.officeScene) {
                this.officeScene.start();
                return;
            }
            const container = this.$refs.officeCanvas;
            if (!container) return;

            try {
                // Three.js hefty — dimuat saat mode 3D dibuka, bukan di bundle awal.
                const { createOfficeScene } = await import('./office3d');
                this.officeScene = createOfficeScene(container, {
                    onRoomFocus: (state, focused) => {
                        this.officeFocused = focused;
                    },
                });
                this.officeScene.start();
                this.officeReady = true;
            } catch (e) {
                this.officeError = 'Mode 3D tidak bisa dimuat di perangkat ini.';
                this.officeScene = null;
            }
        },

        /** Ambil state kantor dari server (ruang + karakter + status bekerja). */
        async refreshOffice() {
            try {
                const { data } = await axios.get('/api/office/state');
                this.officeState = data;
                if (this.officeScene) this.officeScene.applyState(data);
            } catch (e) {
                this.officeError = 'Gagal memuat state kantor.';
            }
        },

        startOfficePolling() {
            this.stopOfficePolling();
            this.officeTimer = setInterval(() => {
                if (this.officeOpen) this.refreshOffice();
            }, 10000);
        },

        stopOfficePolling() {
            if (this.officeTimer) {
                clearInterval(this.officeTimer);
                this.officeTimer = null;
            }
        },

        /** Fokuskan kamera ke satu ruang (departemen). */
        focusOfficeRoom(roomId) {
            this.officeFocused = roomId;
            if (this.officeScene) this.officeScene.focusRoom(roomId);
        },

        resetOfficeView() {
            this.officeFocused = null;
            if (this.officeScene) this.officeScene.resetView();
        },

        /** Jumlah karakter yang sedang bekerja di seluruh kantor. */
        officeWorkingCount() {
            return (this.officeState.stats || {}).working || 0;
        },
    };
}
