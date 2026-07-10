{{--
    View  : registration/success.blade.php
    Route : registration.success
    Shown : Setelah semua 10 step berhasil di-submit
--}}

@extends('layouts_recruitments.app')

@section('title', 'Pendaftaran Berhasil | RecruitFlow')

@push('styles')
<style>
    /* Confetti burst (pure CSS, no library) */
    @keyframes confettiFall {
        0%   { opacity: 1; transform: translateY(-10px) rotate(0deg) scale(1); }
        100% { opacity: 0; transform: translateY(120px) rotate(360deg) scale(.6); }
    }
    .confetti-dot {
        position: absolute;
        width: 8px; height: 8px;
        border-radius: 50%;
        animation: confettiFall 1.6s ease-in forwards;
        pointer-events: none;
    }

    /* Success icon pulse */
    @keyframes successPulse {
        0%   { transform: scale(.6); opacity: 0; }
        60%  { transform: scale(1.08); opacity: 1; }
        100% { transform: scale(1); }
    }
    .success-icon-wrap {
        animation: successPulse .55s cubic-bezier(.22,1,.36,1) both;
        animation-delay: .1s;
    }

    /* Content fade-up stagger */
    @keyframes rfFadeUp {
        from { opacity:0; transform:translateY(12px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .su-d1 { animation: rfFadeUp .4s ease both; animation-delay: .3s; }
    .su-d2 { animation: rfFadeUp .4s ease both; animation-delay: .45s; }
    .su-d3 { animation: rfFadeUp .4s ease both; animation-delay: .6s; }
    .su-d4 { animation: rfFadeUp .4s ease both; animation-delay: .75s; }
    .su-d5 { animation: rfFadeUp .4s ease both; animation-delay: .9s; }

    /* Info row */
    .su-info-row {
        display: flex; align-items: flex-start; gap: .75rem;
        padding: .875rem 1rem;
        border-radius: .75rem;
        background: rgba(43,84,191,.05);
        border: 1px solid rgba(43,84,191,.12);
        text-align: left;
    }
    .su-info-row span.material-symbols-outlined { font-size: 1.1rem; color: #2b54bf; margin-top: .1rem; }
    .su-info-row p { font-size: .8125rem; line-height: 1.6; color: #434653; }

    /* Step summary chips */
    .su-chip {
        display: inline-flex; align-items: center; gap: .375rem;
        padding: .25rem .75rem;
        border-radius: .75rem;
        background: #e0e1f2; color: #444653;
        font-size: .68rem; font-weight: 700;
        letter-spacing: .03em;
    }
    .su-chip span.material-symbols-outlined { font-size: .85rem; color: #1a6b3c; }
</style>
@endpush

@section('breadcrumb')
    <span class="hover:text-primary cursor-pointer transition-colors">Dashboard</span>
    <span class="mx-2 text-outline">/</span>
    <span class="text-on-surface font-bold">Selesai</span>
@endsection

@section('page_title', 'Form Pendaftaran')

@section('content')

    @php
        $applicantName = session('applicant_name', 'Pelamar');
        $submittedAt   = session('submitted_at', now()->format('d M Y, H:i'));

        $completedSteps = [
            'Data Pribadi', 'Kontak & Alamat', 'Pengalaman Kerja', 'Data Keluarga',
            'Riwayat Pendidikan', 'Motivasi & Kegiatan', 'Data Fisik', 'Upload Dokumen',
        ];
    @endphp

    {{-- Completed stepper (all steps done) --}}
    @include('layouts_recruitments.partials._stepper', [
        'currentStep' => 8,
        'steps'       => $completedSteps,
    ])

    {{-- ============================================================
         SUCCESS CARD
         ============================================================ --}}
    <div class="max-w-lg mx-auto mb-8">
        <div class="bg-surface-container-lowest rounded-xl
                    shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.15)]
                    border border-outline-variant overflow-hidden
                    relative">

            {{-- Top accent bar --}}
            <div class="h-1.5 w-full" style="background: linear-gradient(90deg,#2b54bf 0%,#496eda 50%,#b5c4ff 100%);"></div>

            <div class="px-8 py-10 flex flex-col items-center text-center">

                {{-- Confetti anchor --}}
                <div class="relative mb-6" id="confetti-anchor">

                    {{-- Icon --}}
                    <div class="success-icon-wrap w-20 h-20 rounded-full flex items-center justify-center mx-auto"
                         style="background: rgba(43,84,191,.1);">
                        <span class="material-symbols-outlined text-primary"
                              style="font-variation-settings:'FILL' 1; font-size:3rem;">check_circle</span>
                    </div>

                </div>

                {{-- Headline --}}
                <h2 class="text-headline-xl font-bold text-on-surface mb-2 su-d1">
                    Pendaftaran Berhasil! 🎉
                </h2>
                <p class="text-body-md text-on-surface-variant mb-6 su-d2">
                    Terima kasih, <strong class="text-on-surface">{{ $applicantName }}</strong>!
                    Data pendaftaran Anda telah berhasil dikirimkan.
                </p>

                {{-- Application Ref --}}
                @if(session('application_ref'))
                <div class="mb-5 su-d2">
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full
                                 bg-primary/10 border border-primary/20 text-primary
                                 text-label-lg font-bold tracking-wide">
                        <span class="material-symbols-outlined" style="font-size:1rem;">confirmation_number</span>
                        {{ session('application_ref') }}
                    </span>
                </div>
                @endif

                {{-- Info rows --}}
                <div class="w-full space-y-3 mb-7 su-d3">

                    <div class="su-info-row">
                        <span class="material-symbols-outlined">schedule</span>
                        <p>
                            Dikirim pada <strong>{{ $submittedAt }}</strong>.
                            Simpan waktu ini sebagai referensi jika Anda membutuhkan konfirmasi.
                        </p>
                    </div>

                    <div class="su-info-row">
                        <span class="material-symbols-outlined">phone_iphone</span>
                        <p>
                            Tim HRD akan menghubungi Anda melalui <strong>nomor HP yang terdaftar</strong>.
                            Pastikan nomor HP Anda aktif dan dapat dihubungi.
                        </p>
                    </div>

                    <div class="su-info-row">
                        <span class="material-symbols-outlined">mark_email_unread</span>
                        <p>
                            Ada pertanyaan? Hubungi kami di
                            <a href="mailto:hrd@chutex.co.id"
                               class="text-primary font-bold hover:underline break-all">
                                recruitment@chutex.id
                            </a>
                        </p>
                    </div>

                </div>

                {{-- Completed steps summary --}}
                <div class="w-full su-d4">
                    <p class="text-label-lg text-on-surface-variant mb-3 text-left">
                        Data yang telah dikumpulkan:
                    </p>
                    <div class="flex flex-wrap gap-2 justify-start">
                        @foreach($completedSteps as $s)
                            <span class="su-chip">
                                <span class="material-symbols-outlined">check</span>
                                {{ $s }}
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- CTA --}}
                <div class="mt-8 w-full su-d5">
                    <p class="text-label-md text-on-surface-variant mb-4">
                        Anda dapat menutup halaman ini.
                    </p>
                </div>

            </div>

        </div>
    </div>

@endsection

@push('scripts')
<script>
    // Confetti burst saat halaman dimuat
    (function () {
        const colors  = ['#2b54bf','#496eda','#b5c4ff','#8c4b00','#ffb77c','#1a6b3c','#c8f0da'];
        const anchor  = document.getElementById('confetti-anchor');
        if (!anchor) return;

        const rect    = anchor.getBoundingClientRect();
        const cx      = rect.left + rect.width  / 2;
        const cy      = rect.top  + rect.height / 2;
        const N       = 28;

        for (let i = 0; i < N; i++) {
            const dot = document.createElement('div');
            dot.className = 'confetti-dot';

            const angle  = (i / N) * 360;
            const radius = 20 + Math.random() * 30;
            const x = cx + radius * Math.cos(angle * Math.PI / 180) - 4;
            const y = cy + radius * Math.sin(angle * Math.PI / 180) - 4;

            dot.style.cssText = `
                left: ${x}px; top: ${y}px; position: fixed;
                background: ${colors[i % colors.length]};
                animation-delay: ${Math.random() * 0.4}s;
                animation-duration: ${1.2 + Math.random() * 0.8}s;
                z-index: 9999;
            `;
            document.body.appendChild(dot);
            setTimeout(() => dot.remove(), 2500);
        }
    })();
</script>
@endpush
