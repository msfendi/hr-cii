@extends('layouts.app')
@section('content')
<div x-data="chat" x-init="init()" class="relative flex h-full flex-col bg-white">
    {{-- Splash screen --}}
    <div x-show="showingSplash" x-transition:leave="transition ease-in duration-500"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="splash-overlay absolute inset-0 z-50 flex flex-col items-center justify-center gap-4 bg-white">
        <img src="{{ asset('images/chutex-ai-mascot.png') }}" alt="Chutex AI" class="mascot-float w-36 select-none rounded-2xl">
        <p class="text-lg font-semibold text-ink-700">Preparing AI<span class="typing-dots-inline"><span>.</span><span>.</span><span>.</span></span></p>
    </div>
    {{-- Top bar --}}
    <header class="flex h-14 shrink-0 items-center justify-between border-b border-ink-200 px-4">
        <div class="flex items-center gap-3">
            <button class="rounded-lg p-2 text-ink-500 hover:bg-ink-100 lg:hidden" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle sidebar">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <img src="{{ asset('images/chutex-ai-head.png') }}" alt="Chutex AI" class="h-9 w-9">
            <span class="text-lg font-bold text-ink-900">Chutex <span class="text-brand-500">AI</span></span>
            {{-- Mode 3D: kantor virtual --}}
            <button @click="toggleOffice()"
                    class="ml-1 hidden items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 transition hover:border-brand-300 hover:bg-brand-100 sm:inline-flex"
                    :class="officeOpen ? 'border-brand-400 bg-brand-100' : ''"
                    title="Buka kantor virtual 3D" aria-label="Mode 3D kantor virtual">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                <span>Mode 3D</span>
                <span x-show="officeWorkingCount() > 0" x-text="officeWorkingCount()" class="rounded-full bg-brand-500 px-1.5 text-[10px] font-bold text-white"></span>
            </button>
        </div>
        <div class="flex items-center gap-2" x-data="{ open: false }" @click.outside="open = false">
            <button @click="open = !open" class="flex items-center gap-2 rounded-full border border-ink-200 py-1 pl-1 pr-3 text-sm font-medium text-ink-700 hover:bg-ink-50">
                <span class="grid h-7 w-7 place-items-center rounded-full bg-brand-500 text-xs font-bold text-white">{{ substr(auth()->user()->name, 0, 1) }}</span>
                <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                <svg class="h-4 w-4 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-cloak x-transition class="absolute right-4 top-14 z-40 w-56 rounded-xl border border-ink-200 bg-white p-2 shadow-lg">
                <div class="px-3 py-2">
                    <p class="truncate text-sm font-semibold text-ink-800">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-ink-500">{{ auth()->user()->email }}</p>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="badge mt-2 bg-brand-50 text-brand-700">Admin</a>
                    @endif
                </div>
                <hr class="my-1 border-ink-100">
                <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-50">Profil &amp; Password</a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-50">Admin Panel</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">Keluar</button></form>
            </div>
        </div>
    </header>

    <div class="flex min-h-0 flex-1">
        {{-- Sidebar riwayat chat --}}
        <aside class="fixed inset-y-0 left-0 z-30 mt-14 flex w-72 shrink-0 flex-col border-r border-ink-200 bg-white transition-transform lg:static lg:mt-0"
               :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
            <div class="space-y-3 p-3">
                <button @click="newChat()" class="btn-primary w-full">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
                    New Chat
                </button>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                    <input type="search" x-model="search" @input="debounceFetch()" placeholder="Search chat…" class="input pl-9">
                </div>
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto px-3 pb-4">
                <template x-if="loadingList"><p class="px-2 py-4 text-sm text-ink-400">Memuat…</p></template>
                <template x-for="group in groupedConversations()" :key="group[0]">
                    <div class="mb-4">
                        <p class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-ink-400" x-text="group[0]"></p>
                        <template x-for="c in group[1]" :key="c.id">
                            <div class="group relative mb-0.5 overflow-hidden rounded-lg"
                                 x-data="{ sx: 0, sy: 0, moved: false }"
                                 @touchstart="sx = $event.touches[0].clientX; sy = $event.touches[0].clientY; moved = false"
                                 @touchmove="if ($store.swipe.current === c.id) { $event.preventDefault(); return } const dx = $event.touches[0].clientX - sx, dy = $event.touches[0].clientY - sy; if (!moved && Math.abs(dx) > 36 && Math.abs(dx) > Math.abs(dy) * 1.6) { moved = true; dx < 0 ? $store.swipe.open(c.id) : $store.swipe.close() }">
                                {{-- Aksi di belakang: geser kiri = Rename & Delete --}}
                                <div class="absolute inset-y-0 right-0 flex items-stretch gap-1 pr-1">
                                    <button @click="renamingId = c.id; renameValue = c.title; $store.swipe.close(); $nextTick(() => $refs['rn' + c.id]?.focus())"
                                            class="rounded-md bg-ink-200 px-2.5 text-xs font-medium text-ink-700 hover:bg-ink-300" title="Rename">✏️ Ubah</button>
                                    <button @click="remove(c); $store.swipe.close()"
                                            class="rounded-md bg-red-500 px-2.5 text-xs font-medium text-white hover:bg-red-600" title="Delete">🗑 Hapus</button>
                                </div>
                                {{-- Judul: sliding layer (sentuh 2x / tap membuka aksi; keyboard ←→ juga bisa) --}}
                                <div :class="$store.swipe.current === c.id ? '-translate-x-[136px]' : 'translate-x-0'"
                                     class="relative z-10 w-full bg-white transition-transform duration-150"
                                     style="touch-action: pan-y"
                                     tabindex="0"
                                     @keyup.left="$store.swipe.open(c.id)" @keyup.right="$store.swipe.close()"
                                     @click="if ($store.swipe.current === c.id) { $store.swipe.close(); return }
                                             if (Date.now() - (window.__lastTapTime || 0) < 350 && window.__lastTapId === c.id) {
                                                 window.__lastTapTime = 0; $store.swipe.open(c.id);
                                             } else { window.__lastTapTime = Date.now(); window.__lastTapId = c.id; openConversation(c.id) }">
                                    <a :href="`/chat/${c.id}`" @click.prevent
                                       class="block truncate px-3 py-2 text-sm hover:bg-ink-100"
                                       :class="activeId === c.id ? 'bg-brand-50 font-medium text-brand-700' : 'text-ink-600'"
                                       x-text="c.title"></a>
                                </div>
                                {{-- desktop: ikon aksi saat hover (disembunyikan di layar sentuh) --}}
                                <div class="absolute right-1 top-1.5 z-20 hidden gap-1 lg:group-hover:flex">
                                    <button @click="renamingId = c.id; renameValue = c.title" class="rounded p-1 bg-white/70 text-ink-400 hover:bg-ink-200 hover:text-ink-700" title="Rename">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M11 4H4v16h16v-7M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button @click="remove(c)" class="rounded p-1 bg-white/70 text-ink-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M3 6h18M8 6V4h8v2m-9 0v14a2 2 0 002 2h6a2 2 0 002-2V6"/></svg>
                                    </button>
                                </div>
                                <div x-show="renamingId === c.id" x-cloak class="relative z-10 bg-white px-2 py-1">
                                    <input :id="'rn' + c.id" :x-ref="'rn' + c.id" x-model="renameValue" @keyup.enter="rename(c)" @keyup.escape="renamingId = null" @blur="renamingId = null" class="input text-sm" placeholder="Judul baru…">
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!loadingList && conversations.length === 0">
                    <p class="px-2 py-4 text-sm text-ink-400">Belum ada chat. Mulai percakapan baru.</p>
                </template>
            </nav>
        </aside>
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen=false" class="fixed inset-0 z-20 bg-black/30 lg:hidden"></div>

        {{-- Area chat --}}
        <main class="flex min-w-0 flex-1 flex-col">
            {{-- Mode 3D: kantor virtual (satu ruang per departemen) --}}
            <section x-show="officeOpen" x-cloak
                     class="flex h-full min-h-0 flex-col border-b border-ink-200 bg-ink-50">
                <div class="flex shrink-0 flex-wrap items-center gap-2 border-b border-ink-200 bg-white px-3 py-2">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold text-ink-800">Kantor Virtual</h2>
                        <span class="rounded-full bg-ink-100 px-2 py-0.5 text-[11px] text-ink-600"
                              x-text="(officeState.stats?.rooms || 0) + ' ruang'"></span>
                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] text-brand-700"
                              x-text="(officeState.stats?.characters || 0) + ' karakter'"></span>
                        <span x-show="officeWorkingCount() > 0" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700"
                              x-text="officeWorkingCount() + ' sedang bekerja'"></span>
                    </div>
                    <div class="ml-auto flex items-center gap-1.5">
                        <button @click="resetOfficeView()"
                                class="rounded-lg border border-ink-200 bg-white px-2.5 py-1 text-xs font-medium text-ink-600 hover:bg-ink-50"
                                title="Tampilkan semua ruang">Semua ruang</button>
                        <button @click="refreshOffice()"
                                class="rounded-lg border border-ink-200 bg-white px-2.5 py-1 text-xs font-medium text-ink-600 hover:bg-ink-50"
                                title="Muat ulang state">Segarkan</button>
                        <button @click="closeOffice()"
                                class="rounded-lg border border-ink-200 bg-white px-2.5 py-1 text-xs font-medium text-ink-600 hover:bg-ink-50"
                                title="Tutup mode 3D">Tutup</button>
                    </div>
                </div>

                {{-- Daftar ruang (departemen) --}}
                <div class="flex shrink-0 gap-1.5 overflow-x-auto border-b border-ink-200 bg-white px-3 py-2">
                    <template x-for="room in (officeState.rooms || [])" :key="room.id">
                        <button @click="focusOfficeRoom(room.id)"
                                :class="officeFocused === room.id ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-ink-200 bg-white text-ink-600 hover:bg-ink-50'"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium">
                            <span class="font-bold" x-text="room.code"></span>
                            <span x-text="room.name"></span>
                            <span x-show="room.working_count > 0"
                                  class="rounded-full bg-amber-400 px-1.5 text-[10px] font-bold text-white"
                                  x-text="room.working_count"></span>
                        </button>
                    </template>
                </div>

                {{-- Canvas 3D --}}
                <div class="relative min-h-0 flex-1">
                    <div x-ref="officeCanvas" class="h-full w-full"></div>
                    <p x-show="officeError" x-text="officeError"
                       class="absolute inset-0 grid place-items-center bg-white/80 p-6 text-center text-sm text-ink-500"></p>
                    <div x-show="!officeError && !(officeState.rooms || []).length"
                         class="absolute inset-0 grid place-items-center bg-white/80 p-6 text-center text-sm text-ink-500">
                        Belum ada departemen terdaftar.
                    </div>
                    {{-- Daftar karakter yang sedang bekerja --}}
                    <div class="pointer-events-none absolute bottom-3 left-3 max-w-xs rounded-xl border border-ink-200 bg-white/95 p-2.5 shadow-lg">
                        <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-ink-400">Sedang bekerja</p>
                        <template x-for="room in (officeState.rooms || [])" :key="'w-'+room.id">
                            <template x-for="m in (room.members || []).filter(x => x.working)" :key="m.user_id">
                                <div class="flex items-center gap-2 py-0.5 text-xs">
                                    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-500 text-[9px] font-bold text-white"
                                          x-text="m.initials"></span>
                                    <span class="truncate text-ink-700" x-text="m.name"></span>
                                    <span class="truncate text-ink-400" x-text="'· ' + m.department"></span>
                                </div>
                            </template>
                        </template>
                        <p x-show="officeWorkingCount() === 0" class="py-1 text-xs text-ink-400">Belum ada yang bekerja.</p>
                    </div>
                    <p class="pointer-events-none absolute bottom-3 right-3 rounded-lg bg-white/90 px-2 py-1 text-[11px] text-ink-500">
                        Seret untuk memutar · scroll untuk zoom
                    </p>
                </div>
            </section>

            <div x-ref="scroller" class="min-h-0 flex-1 overflow-y-auto" x-show="!officeOpen">
                {{-- Empty state dengan maskot (PRD §11) --}}
                <div x-show="!activeId" class="flex h-full flex-col items-center justify-center p-8 text-center">
                    <img src="{{ asset('images/chutex-ai-mascot.png') }}" alt="Chutex AI" class="mascot-float w-44 select-none rounded-2xl">
                    <h2 class="mt-6 text-xl font-bold text-ink-900">Halo, {{ auth()->user()->name }} 👋</h2>
                    <p class="mt-2 max-w-md text-sm text-ink-500">Saya Chutex AI. Apa yang ingin Anda kerjakan hari ini?</p>
                    <button @click="newChat()" class="btn-primary mt-6 w-full max-w-xs justify-center">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.77 9.77 0 01-4-.84L3 20l1.35-3.37A7.956 7.956 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        Mulai Chat
                    </button>
                    <div class="mt-4 grid w-full max-w-lg grid-cols-1 gap-2 sm:grid-cols-3">
                        <button @click="newChat().then(()=>{input='Bantu draft email profesional untuk ';autoGrow()})" class="btn-secondary justify-start text-xs">✉️ Draft email</button>
                        <button @click="newChat().then(()=>{input='Jelaskan ';autoGrow()})" class="btn-secondary justify-start text-xs">💡 Penjelasan singkat</button>
                        <button @click="newChat().then(()=>{input='Ringkas teks berikut: ';autoGrow()})" class="btn-secondary justify-start text-xs">📝 Ringkasan</button>
                    </div>
                </div>

            <div x-show="activeId" x-cloak class="mx-auto w-full max-w-3xl space-y-6 p-4 sm:p-6">
                    <template x-for="(msg, i) in messages" :key="msg.id ?? 'tmp-'+i">
                        <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex gap-3'">
                            <div x-show="msg.role !== 'user'" class="mt-1 shrink-0">
                                <img src="{{ asset('images/chutex-ai-head.png') }}" alt="" class="h-8 w-8">
                            </div>
                            <div :class="msg.role === 'user'
                                ? 'max-w-[80%] rounded-2xl rounded-br-md bg-brand-500 px-4 py-2.5 text-white'
                                : 'min-w-0 max-w-[85%] rounded-2xl rounded-bl-md border border-ink-200 bg-white px-4 py-2.5 shadow-sm'">
                                                                {{-- assistant content dengan markdown --}}
                                <div x-show="msg.role === 'assistant' && msg.content" class="chat-md overflow-x-auto" x-html="htmlFor(msg)"></div>
                                {{-- Jika konten kosong tapi pesan sudah SELESAI (done/failed),
                                     tampilkan fallback supaya bubble tak kosong. Saat masih
                                     pending, jangan tampilkan fallback — biarkan badge
                                     "sedang berpikir" di bawah yang memandu. --}}
                                <div x-show="msg.role === 'assistant' && !msg.content" class="text-sm text-ink-500">Maaf, jawaban AI kosong. Silakan coba lagi.</div>
                                {{-- lampiran hasil kerja AI (token MEDIA:): gambar/video inline, dokumen jadi kartu --}}
                                <template x-if="msg.role === 'assistant' && msg.attachments && msg.attachments.length">
                                    <div class="mt-2 flex flex-col gap-2">
                                        <template x-for="a in msg.attachments" :key="a.id">
                                          <div class="contents">
                                            <template x-if="imgUrl(a)">
                                                <img :src="imgUrl(a)" :alt="a.original_name" loading="lazy" @click="openModal(imgUrl(a))"
                                                     class="max-h-80 w-auto max-w-full cursor-zoom-in rounded-xl border border-ink-200 object-contain">
                                            </template>
                                            <template x-if="isVideo(a)">
                                                <video :src="'/api/attachments/' + a.id + '/preview'" controls preload="metadata"
                                                       class="max-h-80 w-full max-w-full rounded-xl border border-ink-200 bg-black"></video>
                                            </template>
                                            <template x-if="!imgUrl(a) && !isVideo(a)">
                                                <div class="flex max-w-sm flex-col gap-1.5 rounded-xl border border-ink-200 bg-ink-50 p-3 hover:border-brand-300 hover:bg-brand-50/40">
                                                    <a :href="'/api/attachments/' + a.id + '?dl=1'" target="_blank" rel="noopener" download
                                                       class="flex items-center gap-3">
                                                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white text-brand-600 shadow-sm">
                                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <div class="truncate text-sm font-medium text-ink-800" x-text="a.original_name"></div>
                                                            <div class="text-xs text-ink-400"><span x-text="fmtSize(a.size)"></span> · <span x-text="(a.mime || '').split('/').pop().toUpperCase()"></span></div>
                                                        </div>
                                                    </a>
                                                    <div class="flex items-center gap-2">
                                                        <a :href="'/api/attachments/' + a.id + '?dl=1'" target="_blank" rel="noopener" download
                                                           class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-600">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                                            Unduh
                                                        </a>
                                                        <a :href="'/api/attachments/' + a.id + '/preview'" target="_blank" rel="noopener"
                                                           class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-1.5 text-xs font-medium text-ink-600 hover:bg-ink-50">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                            Buka
                                                        </a>
                                                    </div>
                                                </div>
                                            </template>
                                                      </div>
                                        </template>
                                    </div>
                                </template>
                                {{-- Opsi klarifikasi interaktif: user klik untuk mengarahkan jawaban AI --}}
                                <template x-if="optionList(msg).length">
                                    <div class="mt-3 flex flex-wrap gap-2 border-t border-ink-100 pt-3">
                                        <p class="w-full text-[11px] font-semibold uppercase tracking-wide text-ink-400">Pilih salah satu</p>
                                        <template x-for="opt in optionList(msg)" :key="opt">
                                            <button type="button" @click="chooseOption(msg, opt)"
                                                    class="inline-flex items-center gap-1.5 rounded-full border border-brand-300 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:border-brand-400 hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-200">
                                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9.75L12 13.5l3.75-3.75M12 15v-3.75"/></svg>
                                                <span class="max-w-[220px] truncate" x-text="opt"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                                {{-- user content teks polos (escape via x-text = aman XSS) --}}
                                <div x-show="msg.role === 'user'" class="whitespace-pre-wrap text-[15px]" x-text="msg.content"></div>
                                {{-- lampiran pesan user --}}
                                <template x-if="msg.role === 'user' && msg.attachments && msg.attachments.length">
                                    <div class="mt-2 flex flex-wrap items-start gap-1.5">
                                        <template x-for="a in msg.attachments" :key="a.id">
                                          <div class="contents">
                                            <template x-if="imgUrl(a)">
                                                <img :src="imgUrl(a)" :alt="a.original_name" loading="lazy" @click="openModal(imgUrl(a))"
                                                     class="max-h-40 w-auto max-w-[200px] cursor-zoom-in rounded-lg border border-white/30 object-cover">
                                            </template>
                                            <a x-show="!imgUrl(a)" :href="typeof a.id === 'number' ? '/api/attachments/' + a.id : null" target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-2 py-1 text-xs hover:bg-white/25">
                                                <svg class="h-3.5 w-3.5 opacity-80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94a3 3 0 114.243 4.243L8.966 20.3a1.5 1.5 0 01-2.122-2.122l9.746-9.746"/></svg>
                                                <span class="max-w-[160px] truncate" x-text="a.original_name"></span>
                                                <span class="opacity-70" x-text="fmtSize(a.size)"></span>
                                            </a>
                                          </div>
                                        </template>
                                    </div>
                                </template>
                                {{-- draft kosong saat streaming, atau placeholder pending dari stream terputus --}}
                                <div x-show="msg.role === 'assistant' && msg.status === 'pending' && !msg.content" class="flex items-center gap-1 py-1">
                                    <span class="typing-dot h-2 w-2 rounded-full bg-brand-400"></span>
                                    <span class="typing-dot h-2 w-2 rounded-full bg-brand-400"></span>
                                    <span class="typing-dot h-2 w-2 rounded-full bg-brand-400"></span>
                                    <span class="ml-2 text-xs text-ink-400">Chutex AI sedang berpikir…</span>
                                </div>
                                <div class="mt-1 flex items-center gap-2 text-[11px] text-ink-400">
                                    <span x-text="fmtTime(msg.created_at)"></span>
                                    <span x-show="msg.status === 'blocked'" class="badge bg-amber-50 text-amber-700">diblokir</span>
                                    <span x-show="msg.status === 'failed'" class="badge bg-red-50 text-red-600">gagal</span>
                                    <span x-show="msg.status === 'stopped'" class="badge bg-ink-100 text-ink-500">dihentikan</span>
                                    <button x-show="msg.content && msg.role === 'assistant'" @click="copyText(msg)" class="opacity-0 transition group-hover:opacity-100 hover:text-ink-600" :class="'group'" style="opacity:inherit">
                                        <span x-show="!msg._copied">📋 copy</span><span x-show="msg._copied">✓ tersalin</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="flex justify-center gap-2 pb-2" x-show="messages.length > 1 && !streaming">
                        <button @click="regenerate()" class="btn-secondary text-xs">🔄 Regenerate jawaban</button>
                    </div>
                </div>
            </div>

            {{-- Composer --}}
            <div x-show="activeId" x-cloak class="shrink-0 border-t border-ink-200 bg-white p-3 sm:p-4"
                                  @dragenter.prevent="dragging = true"
                 @dragover.prevent="dragging = true"
                 @dragleave.prevent="dragging = false"
                 @drop.prevent="onDrop($event)">
                <div x-ref="composer" class="relative mx-auto max-w-3xl">
                    {{-- Overlay drag & drop --}}
                    <div x-show="dragging" x-cloak x-transition.opacity
                         class="pointer-events-none absolute -inset-2 z-10 grid place-items-center rounded-2xl border-2 border-dashed border-brand-400 bg-brand-50/90">
                        <p class="text-sm font-medium text-brand-700">📎 Lepas file untuk dilampirkan</p>
                    </div>
                    <p x-show="error" x-text="error" class="mb-2 text-sm text-red-600"></p>
                    {{-- Chip file terpilih --}}
                    <div x-show="pendingFiles.length" x-cloak class="mb-2 flex flex-wrap gap-2">
                        <template x-for="(f, i) in pendingFiles" :key="i">
                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 bg-ink-50 py-1 pl-2 pr-1 text-xs">
                                <svg class="h-3.5 w-3.5 shrink-0 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94a3 3 0 114.243 4.243L8.966 20.3a1.5 1.5 0 01-2.122-2.122l9.746-9.746"/></svg>
                                <span class="max-w-[180px] truncate" x-text="f.name"></span>
                                <span class="text-ink-400" x-text="fmtSize(f.size)"></span>
                                <button @click="removeFile(i)" class="grid h-5 w-5 place-items-center rounded hover:bg-ink-200" aria-label="Hapus file">✕</button>
                            </span>
                        </template>
                    </div>
                    <div class="flex items-end gap-2 rounded-2xl border border-ink-200 bg-ink-50 p-2 focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-100">
                        {{-- Lampiran --}}
                        <div class="relative">
                            <input type="file" x-ref="fileInput" class="sr-only" multiple
                                   accept=".pdf,.docx,.xlsx,.pptx,.txt,.md,.csv,.json,.png,.jpg,.jpeg,.gif,.webp" @change="pickFiles($event)">
                            <button @click="$refs.fileInput.click()" class="grid h-9 w-9 place-items-center rounded-xl text-ink-400 hover:bg-ink-100 hover:text-ink-600" aria-label="Lampirkan file" title="Lampirkan file (dokumen atau gambar, maks 5 file / 5 MB)">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94a3 3 0 114.243 4.243L8.966 20.3a1.5 1.5 0 01-2.122-2.122l9.746-9.746"/></svg>
                            </button>
                        </div>
                        <textarea x-ref="textarea" x-model="input" rows="1" @keydown="onKeydown($event)" @input="autoGrow()" @paste="onPaste($event)"
                                  placeholder="Tanya Chutex AI…"
                                  class="max-h-52 flex-1 resize-none bg-transparent px-2 py-1.5 text-[15px] outline-none"></textarea>
                        <button x-show="!streaming && !messages.some(m => m.status === 'pending')" @click="send()" :disabled="!input.trim() && !pendingFiles.length" class="btn-primary !rounded-xl !px-3 !py-2" aria-label="Kirim" title="Kirim (Enter)">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M3.4 20.4l17.45-7.48a1 1 0 000-1.84L3.4 3.6a.993.993 0 00-1.39.91L2 9.12c0 .5.37.93.87.99L17 11 2.87 12.88c-.5.07-.87.5-.87 1l.01 4.61c0 .71.73 1.2 1.39.91z"/></svg>
                        </button>
                        <button x-show="streaming" x-cloak @click="stop()" class="btn-danger !px-3 !py-2" aria-label="Hentikan">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                        </button>
                    </div>
                    <p class="mt-1 text-center text-[11px] text-ink-400">Chutex AI dapat keliru. Verifikasi informasi penting. Prompt terkait sistem internal diblokir otomatis.</p>
                </div>
            </div>
        </main>
        {{-- Modal preview gambar hasil AI (klik gambar di bubble) --}}
        <div x-show="modalUrl" x-cloak @click="closeModal()" @keydown.escape.window="closeModal()"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-6 backdrop-blur-sm">
            <img :src="modalUrl" alt="" @click.stop
                 class="max-h-[90vh] max-w-[92vw] rounded-xl bg-white object-contain shadow-2xl">
            <button class="absolute right-5 top-5 grid h-10 w-10 place-items-center rounded-full bg-white/15 text-white hover:bg-white/25" aria-label="Tutup">&times;</button>
        </div>
    </div>
</div>

@endsection
