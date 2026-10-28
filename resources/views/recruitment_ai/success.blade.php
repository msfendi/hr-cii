{{--
    Halaman sukses Form Recruitment (versi AI).
    Route : recruitment-ai.success
--}}
@extends('recruitment_ai.layout')

@section('title', 'Pendaftaran Berhasil | E-HRIS')
@section('page_title', 'Pendaftaran Berhasil')

@section('steps')
    <span class="rf-step is-done"><span class="rf-step-dot"><i class="fas fa-check"></i></span><span class="rf-step-label">Dokumen</span></span>
    <span class="rf-step is-done"><span class="rf-step-dot"><i class="fas fa-check"></i></span><span class="rf-step-label">Periksa Data</span></span>
    <span class="rf-step is-done"><span class="rf-step-dot"><i class="fas fa-check"></i></span><span class="rf-step-label">Terkirim</span></span>
@endsection

@section('content')
    @php
        $applicantName = $name ?: 'Pelamar';
        $flow = ['Dokumen diunggah', 'Data terbaca AI', 'Data diperiksa', 'Pengajuan terkirim'];
    @endphp

    <div class="rf-shell" style="grid-template-columns:minmax(0,1fr);">
        <div style="max-width:640px;margin:0 auto;width:100%;">

            <div class="rf-panel">
                <div style="padding:2.5rem 2rem 2rem;text-align:center;">

                    <span style="display:inline-flex;align-items:center;justify-content:center;color:var(--rf-positive);font-size:1.75rem;line-height:1;">
                        <i class="fas fa-circle-check"></i>
                    </span>

                    <h1 style="font-size:1.25rem;font-weight:800;color:var(--rf-text);margin:.75rem 0 .375rem;letter-spacing:-.01em;">
                        Pendaftaran Berhasil
                    </h1>
                    <p style="font-size:.875rem;color:var(--rf-muted);max-width:46ch;margin:0 auto 1rem;">
                        Terima kasih, <b style="color:var(--rf-text);">{{ $applicantName }}</b>.
                        Data pendaftaran Anda sudah tersimpan dan menunggu proses seleksi.
                    </p>

                    @if ($reference)
                        <div style="margin-bottom:1.25rem;">
                            <span class="rf-chip rf-chip-primary" style="font-size:.8125rem;padding:.3rem .75rem;">
                                <i class="fas fa-hashtag"></i>
                                {{ $reference }}
                            </span>
                        </div>
                    @endif

                    <div class="rf-note text-left" style="text-align:left;">
                        <i class="fas fa-mobile-alt"></i>
                        <span>
                            <b>Screenshot halaman ini</b> lalu kirimkan ke nomor HRD berikut agar lamaran Anda
                            segera diproses:
                            <span style="display:flex;flex-wrap:wrap;gap:.375rem;margin-top:.5rem;">
                                <span class="rf-chip rf-chip-neutral">
                                    <i class="fab fa-whatsapp" style="color:#128c4a;"></i> 0851-9851-4858
                                </span>
                                <span class="rf-chip rf-chip-neutral">
                                    <i class="fab fa-whatsapp" style="color:#128c4a;"></i> 0851-1122-9639
                                </span>
                            </span>
                        </span>
                    </div>

                    <div class="rf-note" style="text-align:left;margin-top:.75rem;">
                        <i class="fas fa-envelope"></i>
                        <span>
                            Ada pertanyaan? Hubungi
                            <a href="mailto:recruitment@chutex.id" style="color:var(--rf-primary);font-weight:700;text-decoration:none;">recruitment@chutex.id</a>
                        </span>
                    </div>

                    <ol style="list-style:none;margin:1.5rem 0 0;padding:0;display:flex;flex-wrap:wrap;gap:.375rem;justify-content:center;">
                        @foreach ($flow as $step)
                            <li class="rf-chip rf-chip-neutral">
                                <i class="fas fa-check" style="color:var(--rf-positive);"></i>
                                {{ $step }}
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            <p style="text-align:center;margin-top:.875rem;font-size:.75rem;color:var(--rf-muted);">
                Anda dapat menutup halaman ini.
            </p>
        </div>
    </div>
@endsection
